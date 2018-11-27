<?php
/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Hevelop\Printer\Controller\Pdf;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Registry;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Filesystem;
use Magento\Store\Model\StoreManagerInterface;


/**
 * Class Generate
 * @package Hevelop\Printer\Controller\Pdf
 */
class Generate extends \Hevelop\Printer\Controller\AbstractIndex
{

    /**
     * @var PageFactory
     */
    public $_pageFactory;


    /**
     * @var Registry
     */
    public $_coreRegistry;

    /**
     * @var File
     */
    public $_fileManager;

    /**
     * @var Filesystem
     */
    public $_filesystem;

    /**
     * @var StoreManagerInterface
     */
    public $_storeManager;

    /**
     * Generate constructor.
     * @param Context $context
     * @param PageFactory $pageFactory
     * @param Printer $printer
     * @param Registry $coreRegistry
     * @param File $file
     * @param Filesystem $filesystem
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        Registry $coreRegistry,
        File $file,
        Filesystem $filesystem,
        StoreManagerInterface $storeManager
    ) {
        $this->_pageFactory = $pageFactory;
        $this->_coreRegistry = $coreRegistry;
        $this->_fileManager = $file;
        $this->_filesystem = $filesystem;
        $this->_storeManager = $storeManager;
        parent::__construct($context);
    }

    /**
     * @return int|\Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|\Magento\Framework\View\Result\Page|null
     */
    public function execute()
    {

        $resultPage = $this->_pageFactory->create(false);

        if (!empty($this->getRequest()->getParams())) {
            $productId = array_keys($this->getRequest()->getParams())[0];
        } else {
            return null;
        }

        $storeCode = $this->_storeManager->getStore()->getCode();


        //If pdf already exist, redirect to file.
        if ($this->pdfExist($productId)) {
            $pdfFile =  $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA ) . 'pdf/' . $productId . '_' . $storeCode . '.pdf';
            $this->_redirect($pdfFile);
            return 0 ;
        }

        $resultPage->addHandle('printer_pdf_generate');
        $this->_coreRegistry->register('printer_product_id', $productId);

        return $resultPage;
    }

    /**
     * @param $productId
     * @return bool
     */
    public function pdfExist($productId)
    {

        $storeCode = $this->_storeManager->getStore()->getCode();
        $file = $this->_filesystem->getDirectoryRead('media')->getAbsolutePath() . 'pdf/' . $productId . '_' . $storeCode . '.pdf';

        if ($this->_fileManager->fileExists($file)) {
            $hours = round(((time() - filemtime($file)) / 60 / 60  ) , 2);


            //TODO: this should be a backend config
            if ($hours > 24){
                return false;
            }

            return true;
        }
        
        return false;

    }

}