<?php

namespace Bluethink\KlevuXmlFeed\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Bluethink\KlevuXmlFeed\Model\Feed;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Class ExportXml
 */
class ExportXml extends Command
{
    /**
     *  Store Id
     */
    const STORE_ID = 'storeid';

    /**
     * @var State
     */
    private $state;

    /**
     * @var Filesystem
     */
    private $filesystem;

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
     * @param Filesystem $filesystem
     * @param Feed $generateFeed
     * @param StoreRepositoryInterface $storeRepository
     * @param State $state
     * @throws FileSystemException
     */
    public function __construct(
        Filesystem $filesystem,
        Feed $generateFeed,
        StoreRepositoryInterface $storeRepository,
        State $state
    ) {
        $this->filesystem = $filesystem;
        $this->directory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $this->generateFeed = $generateFeed;
        $this->storeRepository = $storeRepository;
        $this->state = $state;
        parent::__construct();
    }

    /**
     * @return void
     */
    protected function configure()
    {
        $this->setName('klevufeed:xml:generate');
        $this->setDescription('You can generate Klevu Feed XML using this command');
        $this->addOption(
            self::STORE_ID,
            null,
            InputOption::VALUE_OPTIONAL,
            'Store'
        );

        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode(Area::AREA_FRONTEND);
        $storeId = $input->getOption(self::STORE_ID);
        if ($storeId != null) {
            $store = $this->storeRepository->getById($storeId);
            $feed = $this->generateFeed->generate($store);
            if ($feed) {
                $output->writeln('Feed Created for Store : ' . $storeId . "->" . $store->getName());
            } else {
                $output->writeln('Error in Feed Creation for Store : ' . $storeId . "->" . $store->getName());
            }
        } else {
            $stores = $this->storeRepository->getList();
            foreach ($stores as $store) {
                $feed = $this->generateFeed->generate($store);
                if ($feed) {
                    $output->writeln('Feed Created for Store : ' . $store->getId() . "->" . $store->getName());
                } else {
                    $output->writeln('Error in Feed Creation for Store : ' . $store->getId() . "->" . $store->getName());
                }
            }
        }
    }
}
