<?php

namespace Bluethink\KlevuXmlFeed\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Model\Stock\StockItemRepository;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 *  Class Feed
 */
class Feed
{
    /**
     * @var $doc
     */
    private $doc;

    /**
     * @var DOMDocumentFactory
     */
    private $DOMDocumentFactory;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var CollectionFactory
     */
    private $productCollectionFactory;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var StockItemRepository
     */
    private $stockItemRepository;

    /**
     * @var WebsiteRepositoryInterface
     */
    private $websiteRepository;

    /**
     * @var StoreManagerInterface
     */
    private $storeRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * Feed Constructor
     *
     * @param DOMDocumentFactory $DOMDocumentFactory
     * @param Filesystem $filesystem
     * @param CollectionFactory $productCollectionFactory
     * @param ProductRepositoryInterface $productRepository
     * @param StockItemRepository $stockItemRepository
     * @param WebsiteRepositoryInterface $websiteRepository
     * @param StoreManagerInterface $storeRepository
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     */
    public function __construct(
        DOMDocumentFactory $DOMDocumentFactory,
        Filesystem $filesystem,
        CollectionFactory $productCollectionFactory,
        ProductRepositoryInterface $productRepository,
        StockItemRepository $stockItemRepository,
        WebsiteRepositoryInterface $websiteRepository,
        StoreManagerInterface $storeRepository,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    ) {
        $this->DOMDocumentFactory = $DOMDocumentFactory;
        $this->filesystem = $filesystem;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productRepository = $productRepository;
        $this->stockItemRepository = $stockItemRepository;
        $this->websiteRepository = $websiteRepository;
        $this->storeRepository = $storeRepository;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    /**
     * @param $store
     * @return bool
     */
    public function generate($store): bool
    {
        try {
            $this->doc = $this->DOMDocumentFactory->create('1.0', 'UTF-8');

            $xmlRoot = $this->doc->createElement("rss");
            $xmlRoot = $this->doc->appendChild($xmlRoot);
            $xmlRoot->setAttribute('version', '2.0');
            $xmlRoot->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:g', "http://base.google.com/ns/1.0");

            $channelNode = $xmlRoot->appendChild($this->doc->createElement('channel'));
            $channelNode->appendChild($this->doc->createElement('title', $store->getName()));
            $shopLink = $this->scopeConfig->getValue('vsbridge_indexer_settings/redis_cache_settings/vsf_base_url',
                ScopeInterface::SCOPE_STORE, $store->getId());
            $channelNode->appendChild($this->doc->createElement('link', $shopLink));

            $feed_products = $this->getProductData($store->getId());

            foreach ($feed_products as $key => $product) {

                $itemNode = $channelNode->appendChild($this->doc->createElement('item'));
                foreach ($product as $key => $value) {

                    $this->xmlTo($itemNode, $key, $value);
                }

            }

            $this->doc->formatOutput = true;
            $content = $this->doc->saveXML();

            $media = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
            $media->writeFile('klevuxmlfeed/klevu_products_feed_' . $store->getId() . '.xml', $content);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Feed->Generate : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @param $storeId
     * @return array
     * @throws NoSuchEntityException
     */
    public function getProductData($storeId): array
    {
        $collection = $this->getProductCollectionByFilter($storeId);
        $productName = '';
        $productName1 = '';
        $productName2 = '';
        $productBrand = '';
        $productStack = '';
        $xmlArray = array();
        $collectionSize = count($collection->getData());
        $tempProduct = '';
        foreach ($collection->getData() as $key => $product) {
            try {
                $productData = $this->productRepository->getById($product['entity_id'], false, $storeId);
            } catch (\Exception $e) {
                $this->logger->error('Feed->getProductData : ' . $e->getMessage());
                continue;
            }
            if ($productData->getProductListingStack() == "" || $productData->getProductListingStack() == null) {
                $productData->setProductListingStack(10);
            }
            if ($productName == $productData->getName() && $productBrand == $productData->getProductBrand()) {
                if ($productName1 == $productData->getProductName1()) {
                    if ($productStack < $productData->getProductListingStack()) {
                        $tempProduct = $productData;
                        $productStack = $productData->getProductListingStack();
                    }
                    $tempProduct->setItemGroupId('p' . $tempProduct->getEntityId());
                    if ($key == $collectionSize - 1) {
                        $xmlArray[] = $this->createXmlMapping($tempProduct, $storeId);
                    }
                } else {
                    if ($productName2 == $productData->getProductName2()) {
                        if ($productStack < $productData->getProductListingStack()) {
                            $tempProduct = $productData;
                            $productStack = $productData->getProductListingStack();
                        }
                        $tempProduct->setItemGroupId('p' . $tempProduct->getEntityId());
                        if ($key == $collectionSize - 1) {
                            $xmlArray[] = $this->createXmlMapping($tempProduct, $storeId);
                        }
                    } else {
                        if ($tempProduct) {
                            $xmlArray[] = $this->createXmlMapping($tempProduct, $storeId);
                        }
                        $tempProduct = $productData;
                        $productName = $productData->getName();
                        $productName1 = $productData->getProductName1();
                        $productName2 = $productData->getProductName2();
                        $productBrand = $productData->getProductBrand();
                        $productStack = $productData->getProductListingStack();
                        if ($key == $collectionSize - 1) {
                            $xmlArray[] = $this->createXmlMapping($productData, $storeId);
                        }
                    }
                }
            } elseif ($productName != $productData->getName() || $productBrand != $productData->getProductBrand()) {
                if ($tempProduct) {
                    $xmlArray[] = $this->createXmlMapping($tempProduct, $storeId);
                }
                $tempProduct = $productData;
                $productName = $productData->getName();
                $productName1 = $productData->getProductName1();
                $productName2 = $productData->getProductName2();
                $productBrand = $productData->getProductBrand();
                $productStack = $productData->getProductListingStack();
                if ($key == $collectionSize - 1) {
                    $xmlArray[] = $this->createXmlMapping($productData, $storeId);
                }
            }
        }
        return $xmlArray;
    }

    /**
     * @param $storeId
     * @return Collection
     */
    public function getProductCollectionByFilter($storeId): Collection
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect('*');
        $collection->addStoreFilter($storeId);
        $collection->addAttributeToFilter('url_key', ['neq' => null]);
        $collection->addAttributeToFilter('product_brand', ['neq' => null]);
        $collection->addAttributeToFilter('name', ['neq' => null]);
        $collection->addAttributeToFilter('product_name_1', ['neq' => null]);
        $collection->addAttributeToFilter('product_name_2', ['neq' => null]);
        $collection->addUrlRewrite();
        $collection->getSelect()
            ->group(array('name', 'product_brand', 'product_name_1', 'product_name_2'));
        return $collection;
    }

    /**
     * @param $tempProduct
     * @param $storeId
     * @return array
     * @throws NoSuchEntityException
     */
    public function createXmlMapping($productData, $storeId): array
    {
        try {
            $stock = $this->stockItemRepository->get($productData->getEntityId());
        } catch (\Exception $e) {
            $stock = '';
            $this->logger->error('Feed->CreateXmlMapping->Stock : ' . $e->getMessage());
        }

        $product = [
            'id' => $productData->getEntityId(),
        ];
        if ($productData->getItemGroupId()) {
            $product += [
                'item_group_id' => $productData->getItemGroupId(),
            ];
        }
        $product += [
            'name' => $productData->getName(),
            'sku' => $productData->getSku(),
            'product_name_1' => $productData->getProductName1(),
            'product_name_2' => $productData->getProductName2(),
            'product_brand' => $productData->getProductBrand(),
            'product_collection' => $productData->getProductCollection(),
            'color' => $productData->getColor(),
            'product_material' => $productData->getProductMaterial(),
            'product_function' => $productData->getProductFunction(),
            'product_form' => $productData->getProductForm(),
            'categories' => $this->getCategories($productData),
            'attribute_set' => $productData->getAttributeSetId(),
            'category_ids' => $productData->getCategoryIds(),
            'product_assembly' => $productData->getProductAssembly(),
            'product_certificate' => $productData->getProductCertificate(),
            'product_amount_seatings' => $productData->getProductAmountSeatings(),
            'product_country' => $productData->getProductCountry(),
            'product_material_fabric' => $productData->getProductMaterialFabric(),
            'product_material_frame' => $productData->getProductMaterialFrame(),
            'product_material_padding' => $productData->getProductMaterialPadding(),
            'product_material_table_top' => $productData->getProductMaterialTableTop(),
            'product_separable_fabric' => $productData->getSku(),
            'url_key' => $productData->getUrlKey(),
            'description' => $productData->getDescription(),
            'short_description' => $productData->getShortDescription(),
            'website_id' => $productData->getWebsiteIds(),
            '_product_websites' => $this->getWebsites($productData->getWebsiteIds()),
            '_stores' => $this->getStores($productData->getStoreIds()),
            '_type' => $productData->getTypeId(),
            'product_color_extended' => $productData->getProductColorExtended(),
            'visibility' => $productData->getVisibility(),
            'status' => $productData->getStatus(),
            'product_online' => $productData->getStatus(),
            'weight' => $productData->getWeight(),
            'price' => $productData->getPrice(),
            'special_price' => $productData->getSpecialPrice(),
            'base_image' => $productData->getImage(),
            'additional_images' => $this->getAdditionalImages($productData->getMediaGalleryEntries()),
            'small_image' => $productData->getSmallImage(),
            'product_measure_width' => $productData->getProductMeasureWidth(),
            'product_measure_depth' => $productData->getProductMeasureDepth(),
            'product_measure_height' => $productData->getProductMeasureHeight(),
            'product_measure_seat_height' => $productData->getProductMeasureSeatHeight(),
            'product_measure_height_to_table_top' => $productData->getProductMeasureHeightToTableTop(),
            'product_measure_pole_diameter' => $productData->getProductMeasurePoleDiameter(),
            'product_measure_seat_depth' => $productData->getProductMeasureSeatDepth(),
            'product_measure_thickness' => $productData->getProductMeasureThickness(),
            'product_measure_width_between_legs' => $productData->getProductMeasureWidthBetweenLegs(),
            'product_released_year' => $productData->getProductReleasedYear(),
            'new_from_date' => $productData->getNewFromDate(),
            'new_to_date' => $productData->getNewToDate(),
            'product_sale_status' => $productData->getProductSaleStatus(),
            'product_sale_channels' => $productData->getProductSaleChannels(),
            'collection_products' => $this->getRelatedProductCollection($productData, $storeId),
        ];

        if ($stock) {
            $product += [
                'is_in_stock' => $stock->getIsInStock(),
            ];
        } else {
            $product += [
                'is_in_stock' => '',
            ];
        }

        return $product;
    }

    /**
     * @param $product
     * @return array
     */
    public function getCategories($product): array
    {
        $categories = $product->getCategoryCollection()->addUrlRewriteToResult();
        $categoryArray = array();
        foreach ($categories as $category) {
            $path = explode('/', $category->getPath());
            $categoryPath = array();
            foreach ($path as $pathId) {
                $categoryPath[] = $category->load($pathId)->getName();
            }
            $categoryArray[] = implode('/', $categoryPath);
        }
        return $categoryArray;
    }

    /**
     * @param $websitesIds
     * @return array
     * @throws NoSuchEntityException
     */
    public function getWebsites($websitesIds): array
    {
        $websiteData = array();
        foreach ($websitesIds as $websitesId) {
            $websiteData[] = $this->websiteRepository->getById($websitesId)->getName();
        }
        return $websiteData;
    }

    /**
     * @param $storeIds
     * @return array
     * @throws NoSuchEntityException
     */
    public function getStores($storeIds): array
    {
        $storeData = array();
        foreach ($storeIds as $storeId) {
            $storeData[] = $this->storeRepository->getStore($storeId)->getName();
        }
        return $storeData;
    }

    /**
     * @param $images
     * @return array
     */
    public function getAdditionalImages($images): array
    {
        $imageData = array();
        foreach ($images as $image) {
            $imageData[] = $image->getFile();
        }
        return $imageData;
    }

    /**
     * @param $product
     * @param $storeId
     * @return array|string
     */
    public function getRelatedProductCollection($product, $storeId)
    {
        if ($product->getTypeId() == 'collection') {
            $collection = $this->productCollectionFactory->create();
            $collection->addAttributeToSelect('entity_id');
            $collection->addStoreFilter($storeId);
            $collection->addAttributeToFilter('type_id', ['neq' => 'collection']);
            $collection->addAttributeToFilter('product_collection', ['eq' => $product->getSku()]);

            $relatedCollectionId = array();
            foreach ($collection as $product) {
                $relatedCollectionId[] = $product->getId();
            }
            return $relatedCollectionId;
        }
        return "";
    }

    /**
     * @param $itemNode
     * @param $key
     * @param $value
     * @return void
     */
    public function xmlTo($itemNode, $key, $value)
    {
        if (is_array($value)) {
            $subItemNode = $itemNode->appendChild($this->doc->createElement($key));
            foreach ($value as $key2 => $value2) {
                try {
                    if (is_array($value2)) {
                        $this->xmlTo($subItemNode, $key2, $value2);
                    } else {
                        if (is_numeric($key2)) {
                            $tmpKey = $this->getKey($key);
                            $subItemNode->appendChild($this->doc->createElement($tmpKey))->appendChild($this->doc->createTextNode($value2));
                        } else {
                            $subItemNode->appendChild($this->doc->createElement($key2))->appendChild($this->doc->createTextNode($value2));
                        }
                    }
                } catch (\Exception $e) {
                    $this->logger->error($e->getMessage());
                }
            }
        } else {
            $itemNode->appendChild($this->doc->createElement($key))->appendChild($this->doc->createTextNode($value));
        }
    }

    /**
     * @param $key
     * @return string|void
     */
    public function getKey($key)
    {
        if ($key == 'categories') {
            return 'category';
        } elseif ($key == 'category_ids') {
            return 'category_id';
        } elseif ($key == '_product_websites') {
            return '_product_website';
        } elseif ($key == 'additional_images') {
            return 'additional_image';
        } elseif ($key == 'collection_products') {
            return 'collection_product';
        } elseif ($key == '_stores') {
            return '_store';
        }
    }
}

