<?php

namespace Hevelop\Printer\Console\Command;


//TODO: clean up unused classes
use Blackbird\ContentManager\Model\ContentFactory;
use Blackbird\ContentManager\Model\ContentTypeFactory;
use Dompdf\Dompdf;
use Hevelop\Import\Helper\Import;
use function ltrim;
use Magento\Catalog\Block\Product\ImageBuilder;
use Hevelop\Import\Helper\ImageFactory as HelperImageFactory;
use Magento\Catalog\Model\Config;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Action;
use Magento\Catalog\Model\Product\Interceptor;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\State;
use Magento\Framework\Config\ConfigOptionsListConstants;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Url;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\ObjectManagerFactory;

class ProductGeneratePdfCommand extends AbstractCommand
{

    const PDF_DIR = 'catalog/product/pdf/';

    const PDF_VERSION = '5';

    /**
     * @var string
     */
    protected $_master_file_dir = BP . '/var/import/valsana/';
    /**
     * @var string
     */
    protected $_archive_dir = BP . '/var/import/archive/';

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Framework\Filter\Factory
     */
    protected $_filterFactory;

    /**
     * @var \Blackbird\ContentManager\Helper\Content\Data
     */
    protected $_helperContent;

    /**
     * @var ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * @var \Blackbird\ContentManager\Model\Factory
     */
    protected $_modelFactory;

    /**
     * @var ProductFactory
     */
    protected $_productFactory;

    /**
     * @var DeploymentConfig
     */
    protected $deploymentConfig;


    /**
     * @var Url
     */
    protected $_urlBuilder;

    /**
     * @var Repository
     */
    protected $_assetRepo;

    /**
     * @var ImageBuilder
     */
    protected $imageBuilder;

    /**
     * @var ContentFactory
     */
    protected $_contentFactory;

    /**
     * @var ContentTypeFactory
     */
    protected $_contentTypeFactory;

    /**
     * @var Config $catalogConfig
     */
    protected $_catalogConfig;

    /**
     * @var HelperImageFactory
     */
    private $helperImageFactory;


    /**
     * ProductGeneratePdfCommand constructor.
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param File $file
     * @param LoggerInterface $logger
     * @param Import $helper
     * @param ObjectManagerInterface $_objectManager
     * @param ObjectManagerFactory $objectManagerFactory
     * @param ProductFactory $productFactory
     * @param DeploymentConfig $deploymentConfig
     * @param State $state
     * @param Url $url
     * @param Repository $assetRepo
     * @param ImageBuilder $imageBuilder
     * @param ContentFactory $contentFactory
     * @param ContentTypeFactory $contentTypeFactory
     * @param Config $catalogConfig
     * @param HelperImageFactory $helperImageFactory
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        File $file,
        LoggerInterface $logger,
        Import $helper,
        ObjectManagerInterface $_objectManager,
        ObjectManagerFactory $objectManagerFactory,
        ProductFactory $productFactory,
        DeploymentConfig $deploymentConfig,
        State $state,
        Url $url,
        Repository $assetRepo,
        ImageBuilder $imageBuilder,
        ContentFactory $contentFactory,
        ContentTypeFactory $contentTypeFactory,
        Config $catalogConfig,
        HelperImageFactory $helperImageFactory
    ) {
        $this->_storeManager = $storeManager;
        $this->_fileManager = $file;
        $this->logger = $logger;
        $this->_objectManager = $_objectManager;
        $this->_productFactory = $productFactory;
        $this->deploymentConfig = $deploymentConfig;
        $this->_state = $state;
        $this->_urlBuilder = $url;
        $this->_assetRepo = $assetRepo;
        $this->imageBuilder = $imageBuilder;
        $this->_contentFactory = $contentFactory;
        $this->_contentTypeFactory = $contentTypeFactory;
        $this->_catalogConfig = $catalogConfig;
        $this->helperImageFactory = $helperImageFactory;

        parent::__construct(
            $scopeConfig,
            $file,
            $helper,
            $objectManagerFactory,
            $storeManager,
            $state,
            $logger
        );
    }

    /**
     * Configures the current command.
     * @throws \InvalidArgumentException
     */
    protected function configure()
    {
        $this->setName('hevelop:product:generate-pdf')
            ->setDescription('Generate product PDF');
        parent::configure();
    }


    /**
     * @return string
     */
    public function getPdfDir()
    {
        return $this->_helper->getPdfDir();
    }


    /**
     * Executes the current command.
     *
     * This method is not abstract because you can use this class
     * as a concrete class. In this case, instead of defining the
     * execute() method, you set the code to execute by passing
     * a Closure to the setCode() method.
     *
     * @param InputInterface $input An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     *
     * @return null|int null or 0 if everything went fine, or an error code
     * @throws \Magento\Framework\Exception\LocalizedException
     *
     * @throws \LogicException When this abstract method is not implemented
     *
     * @see setCode()
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::beforeExecute($input, $output);
        $this->_state->setAreaCode(Area::AREA_ADMIN);

        if ($this->_fileManager->fileExists($this->getPdfDir(), false) === false) {
            $this->_fileManager->mkdir($this->getPdfDir());
        }

        $this->generate($input, $output, true);
    }/** @noinspection DisconnectedForeachInstructionInspection */


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param bool $enableProgressBar
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Zend_Crypt_Hmac_Exception
     */
    public function generate(InputInterface $input, OutputInterface $output, $enableProgressBar = false)
    {
        $output->writeln('<info>****************** Sync Start ******************<info>');


        $errorCount = 0;

        //counter
        $skippedCount = 0;
        $generateCount = 0;
        $totCount = 0;

        $this->enableProgressBar = $enableProgressBar;

        /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $productCollection */
        $productCollection = $this->_productFactory->create()->getCollection();

        $this->startExecution($output, $productCollection->getSize());


        //TODO: Foreach store as store... then cycle products

        foreach ($productCollection as $product) {
            $totCount++;

            $this->_storeManager->setCurrentStore($this->getItStore());
            $product = $this->_productFactory->create()->setStoreId($this->getItStore()->getId())->load($product->getId());

            $productHash = $product->getData('hash');
            $hash = $this->getHash($product);

            if ($hash !== $productHash || true) {
                $this->progressMessage("<info>Generating PDF for product {$product->getId()}<info>");
                $this->generatePdf($product, 0); //TODO cycle through stores for language
                $generateCount++;
            } else {
                $this->progressMessage("<info>Skipping PDF generation for product {$product->getId()} <info>");
                $skippedCount++;
            }

            $this->progressAdvance();

        }

        $this->finishExecution();

        if ($errorCount) {
            $output->writeln("<info>error count $errorCount<info>");
            throw new \RuntimeException("Error count $errorCount, see valsana log");
        }
        $output->writeln('<info>****************************************<info>');
        $output->writeln('<info>********* Process complete<info>');
        $output->writeln("<info>********* Tot: $totCount<info>");
        $output->writeln("<info>********* Generated: $generateCount<info>");
        $output->writeln("<info>********* Skipped: $skippedCount<info>");
        $output->writeln('<info>****************************************<info>');

    }


    /**
     * Retrieve logo image URL
     *
     * @return string
     * @throws \RuntimeException
     */
    protected function _getLogoUrl()
    {

        //TODO: get site logo using magento methods
        $logoPath = BP . DIRECTORY_SEPARATOR . 'pub/static/frontend/Hevelop/valsana/it_IT/images/logo-main.png';
        if ($this->_fileManager->fileExists($logoPath, false) === false) {
            throw new \RuntimeException('File ' . $logoPath . ' does not exists.');
        }
        return $logoPath;

    }

    /**
     * Method getImage.
     *
     * @param $product
     * @param $imageId
     * @param array $attributes
     * @return string
     */
    public function getImage($product, $imageId, array $attributes = [])
    {
        /** @var \Hevelop\Import\Helper\Image $helperImage */
        $helperImage = $this->helperImageFactory->create()
            ->init($product, $imageId, [
                'width' => 500,
                'height' => 500,
            ])
            ->constrainOnly(true)->keepAspectRatio(true)->keepFrame(false)
            ->setImageFile(ltrim($product->getImage(), '/'));

        return $helperImage->getNewFileAbsolutePath();
    }//end getImage()


    /**
     * @param Product $product
     * @throws \RuntimeException
     * @throws LocalizedException
     * @throws \InvalidArgumentException
     */
    protected function generatePdf(Product $product, Store $store) {

        $logo = $this->_getLogoUrl();
        $today = date('d/m/Y');


        $this->_storeManager->setCurrentStore($store);
        $staticUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_STATIC, true);
        $pdfHtml = 'test'; //TODO: generate html in separated phtml file

        $dompdf = new Dompdf([
            'isHtml5ParserEnabled' => true
        ]);
        $dompdf->loadHtml($pdfHtml);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $this->_fileManager->write($this->getPdfDir() . $product->getId() . '_' . $product->getSku() . '_' . $store->getId() . '.pdf', $dompdf->output());
        $this->_fileManager->write($this->getPdfDir() . $product->getId() . '_' . $product->getSku() . '_' . $store->getId() . '.html', $pdfHtml);

    }//end generatePdf()

}//end class
