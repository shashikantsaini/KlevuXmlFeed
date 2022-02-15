<?php

namespace Bluethink\KlevuXmlFeed\Cron;

use Bluethink\KlevuXmlFeed\Model\Feed;
use Magento\Store\Api\StoreRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 *  Class ExportXml
 */
class ExportXml
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Feed
     */
    private $generateFeed;

    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;

    /**
     * ExportXml Constructor
     *
     * @param Feed $generateFeed
     * @param StoreRepositoryInterface $storeRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        Feed $generateFeed,
        StoreRepositoryInterface $storeRepository,
        LoggerInterface $logger
    ) {
        $this->generateFeed = $generateFeed;
        $this->storeRepository = $storeRepository;
        $this->logger = $logger;
    }

    /**
     * @return void
     */
    public function execute()
    {
        $stores = $this->storeRepository->getList();
        foreach ($stores as $store) {
            $feed = $this->generateFeed->generate($store);
            if ($feed) {
                $this->logger->info('Feed Created for Store : ' . $store->getId() . "->" . $store->getName());
            } else {
                $this->logger->info('Error in Feed Creation for Store : ' . $store->getId() . "->" . $store->getName());
            }
        }
    }
}
