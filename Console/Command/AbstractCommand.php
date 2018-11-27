<?php

namespace Hevelop\Printer\Console\Command;

use Magento\Framework\App\Area;
use Magento\Backend\App\Area\FrontNameResolver;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ObjectManagerFactory;
use Magento\Framework\App\State;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Magento\Framework\Registry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\ObjectManager\ConfigLoaderInterface;
use Magento\Indexer\Model\Indexer\CollectionFactory;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Input\ArrayInput;

/**
 * Class AbstractCommand
 * @package Hevelop\Collini\Console\Command
 * @category Magento_Module
 * @author   Simone Marcato <simone@hevelop.com>
 * @license  http://opensource.org/licenses/agpl-3.0  GNU Affero General Public License v3 (AGPL-3.0)
 * @link     https://hevelop.com/
 */
class AbstractCommand extends Command
{

    const OPTION_LOG = 'log';

    /**
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var File
     */
    protected $fileManager;

    /**
     * @var ObjectManagerFactory
     */
    protected $objectManagerFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var float
     */
    protected $timeStart;

    /**
     * @var bool
     */
    protected $enableProgressBar;

    /**
     * @var State
     */
    protected $state;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var OutputInterface
     */
    protected $_output;

    /**
     * @var InputInterface
     */
    protected $_input;

    /**
     * @var array
     */
    protected $_domDocumentsCache = [];

    /**
     * @var ProgressBar
     */
    protected $progressBar;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $_progressOutput;

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @var int
     */
    protected $barWidth = 60;

    /**
     * @var
     */
    protected $_timeStart;

    /**
     * @var int
     */
    protected $_skipPull = 0;

    /**
     * @var array
     */
    protected $websites = [];

    /**
     * @var array
     */
    protected $_allStoreIds;

    /** @var  boolean */
    protected $cronRun;


    /**
     * AbstractCommand constructor.
     * @param ScopeConfigInterface $scopeConfig
     * @param File $file
     * @param ObjectManagerFactory $objectManagerFactory
     * @param StoreManagerInterface $storeManager
     * @param State $state
     * @param Registry $registry
     * @param LoggerInterface $logger
     * @throws \LogicException
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        File $file,
        ObjectManagerFactory $objectManagerFactory,
        StoreManagerInterface $storeManager,
        State $state,
        Registry $registry,
        LoggerInterface $logger
    ) {
    
        $this->_scopeConfig = $scopeConfig;
        $this->fileManager = $file;
        $this->objectManagerFactory = $objectManagerFactory;
        $this->storeManager = $storeManager;
        $this->registry = $registry;
        $this->timeStart = round(microtime(true), 3);
        $this->enableProgressBar = true;
        $this->state = $state;
        $this->logger = $logger;


        parent::__construct();
    }


    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->addOption(static::OPTION_LOG, 'l', InputOption::VALUE_NONE, 'Specify output mode')
            ->addOption('skip-pull', null, InputOption::VALUE_NONE, 'Skip call ws to update local file');
        parent::configure();
    }


    /**
     * Generate Progress Bar
     * @param OutputInterface $output
     * @param int $length
     * @return void
     */
    public function initProgressBar(OutputInterface $output, int $length)
    {
        if ($this->enableProgressBar) {
            $this->progressBar = new ProgressBar($output, $length);
            $this->progressBar->setBarWidth($this->barWidth);
//        $progress->setBarCharacter('=');
            $this->progressBar->setProgressCharacter("\xF0\x9F\x8D\xBA");
            $this->progressBar->setFormat(" <fg=white;bg=black>%message%</>\n <fg=white;bg=yellow>%current%/%max% [%bar%] %percent:3s%%</>\n " . "\xF0\x9F\x8F\x81" . '  <fg=white;bg=blue> %elapsed:6s%/%estimated:-6s% %memory:6s%</>');
            $this->progressBar->start();
        } else {
            $this->_output = $output;
        }
    }


    /**
     * @throws \LogicException
     */
    public function progressAdvance()
    {
        if ($this->enableProgressBar) {
            $this->progressBar->advance();
        }
    }


    /**
     *
     */
    public function progressFinish()
    {
        if ($this->enableProgressBar) {
            $this->progressBar->finish();
        }
    }


    /**
     * @param string $message
     */
    public function progressMessage(string $message)
    {
        if ($this->enableProgressBar) {
            $this->progressBar->setMessage(str_pad($message, $this->barWidth + 20));
        } else {
            $this->_output->writeln($message);
        }
    }


    /**
     * @return float
     */
    public function getElapsedTime()
    {
        return round(microtime(true) - $this->_timeStart, 3);
    }


    /**
     * @param $progress
     */
    public function logElapsedTime($progress)
    {
        $progress->setMessage('Elapsed time: ' . $this->getElapsedTime());
    }


    /**
     * @param $scheduled
     *
     * @throws LocalizedException
     */
    public function editIndex($scheduled)
    {
        $indexers = $this->getAllIndexers();

        foreach ($indexers as $indexer) {
            try {
                $indexer->setScheduled($scheduled);
            } catch (LocalizedException $e) {
                $this->_output->writeln($e->getMessage() . PHP_EOL);
            } catch (\Exception $e) {
                $this->_output->writeln($indexer->getTitle() . ' indexer process unknown error:' . PHP_EOL);
                $this->_output->writeln($e->getMessage() . PHP_EOL);
            }
        }
    }


    /**
     * Returns all indexers
     *
     * @return IndexerInterface[]
     * @throws LocalizedException
     */
    protected function getAllIndexers()
    {
        $collectionFactory = $this->getObjectManager()->create(CollectionFactory::class);
        return $collectionFactory->create()->getItems();
    }


    /**
     * Gets initialized object manager
     *
     * @return ObjectManagerInterface
     * @throws LocalizedException
     */
    protected function getObjectManager()
    {
        if (null === $this->objectManager) {
            $area = FrontNameResolver::AREA_CODE;
            $this->objectManager = $this->objectManagerFactory->create($_SERVER);
            /** @var \Magento\Framework\App\State $appState */
            $appState = $this->objectManager->get(State::class);
            $appState->setAreaCode($area);
            $configLoader = $this->objectManager->get(ConfigLoaderInterface::class);
            $this->objectManager->configure($configLoader->load($area));
        }
        return $this->objectManager;
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param $logFile
     */
    protected function _beforeExecute(InputInterface $input, OutputInterface $output, $logFile)
    {
        $this->_timeStart = round(microtime(true), 3);
        $this->_progressOutput = $output;

        $this->_input = $input;

        if ($this->cronRun) {
            $this->enableProgressBar = false;
            $this->_output = $output;
        } else {
            // log outpute
            if ($input->getOption('log')) {
                /**
                 * write output to file
                 */
                $this->_output = new StreamOutput(fopen(BP . '/var/log/' . $logFile . '.log', 'a+'));
                $this->enableProgressBar = false;
            } else {
                $this->_output = $output;
                $this->enableProgressBar = true;
            }

        }
    }


    /**
     * @return $this
     */
    protected function _afterExecute()
    {
        return $this;
    }


    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     * @throws LocalizedException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->registry->unregister('isSecureArea');
        $this->registry->register('isSecureArea', true);

        if (!($input instanceof ArrayInput)) {
            $this->state->setAreaCode(Area::AREA_ADMIN);
        }

        if ($this->state->getAreaCode() == Area::AREA_CRONTAB) {
            $this->cronRun = true;
        } else {
            $this->cronRun = false;
        }
    }


    /**
     * @param OutputInterface $output
     * @param int $length
     */
    protected function startExecution(OutputInterface $output, int $length)
    {
        $this->initProgressBar($output, $length);
    }


    /**
     *
     */
    protected function finishExecution()
    {
        $this->progressFinish();
    }


    /**
     * @return array
     */
    protected function getAllWebsiteIds()
    {
        $result = [];
        foreach ($this->getAllWebsites() as $website) {
            $result[] = $website->getId();
        }
        return $result;
    }


    /**
     * @return array|\Magento\Store\Api\Data\WebsiteInterface[]
     */
    protected function getAllWebsites()
    {
        if (!$this->websites) {
            $this->websites = $this->storeManager->getWebsites();
        }
        return $this->websites;
    }


    /**
     * @return array
     */
    public function getAllStoreIds()
    {
        if (!$this->_allStoreIds) {
            $allStores = $this->storeManager->getStores(true);
            $allStoreIds = [];

            foreach ($allStores as $store) {
                $allStoreIds[] = $store->getId();
            }
            $this->_allStoreIds = $allStoreIds;
        }

        return $this->_allStoreIds;
    }
}
