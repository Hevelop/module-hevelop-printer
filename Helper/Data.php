<?php
/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Hevelop\Printer\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Dompdf\Dompdf;
use Dompdf\Options;
use Magento\Catalog\Model\Product as ModelProduct;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Framework\View\Element\Template;
use Magento\Theme\Block\Html\Header\Logo;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\App\Response\Http;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;


/**
 * Class Data
 * @package Hevelop\Printer\Helper
 */
class Data extends \Magento\Framework\App\Helper\AbstractHelper
{

    const TEMPLATE = 'Hevelop_Printer::product/view/pdf.phtml';

    /**
     * @var \Magento\Theme\Block\Html\Header\Logo
     */
    public $_logo;

    /**
     * @var \Magento\Framework\View\Element\Template
     */
    public $_template;

    /**
     * @var ProductFactory
     */
    public $_productFactory;


    /**
     * @var PageFactory
     */
    public $_resultPageFactory;


    public $_response;

    public $_storeManagerInterface;

    /**
     * Data constructor.
     * @param Context $context
     * @param Logo $logo
     * @param Template $template
     * @param ProductFactory $productFactory
     * @param PageFactory $resultPageFactory
     * @param File $file
     */
    public function __construct(
        Context $context,
        Logo $logo,
        Template $template,
        ProductFactory $productFactory,
        PageFactory $resultPageFactory,
        File $file,
        Http $response,
        StoreManagerInterface $storeManagerInterface,
        Filesystem $filesystem

    ) {
        $this->_logo = $logo;
        $this->_template = $template;
        $this->_productFactory = $productFactory;
        $this->_resultPageFactory = $resultPageFactory;
        $this->_fileManager = $file;
        $this->_response = $response;
        $this->_storeManagerInterface = $storeManagerInterface;
        $this->_filesystem = $filesystem;
        parent::__construct($context);
    }


    public function getLogoSrc()
    {
        return $this->_logo->getLogoSrc();
    }


    public function getMediaDir(){
        return $this->_storeManagerInterface->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA ) . 'pdf/';
    }

    public function getMediaPath(){
        return $this->_filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath();
    }


    public function generatePdf($html, $product)
    {
        $productId = $product->getId();

        $storeCode = $product->getStore()->getCode();

        $html = str_replace('{{media url="' , $this->getMediaPath() , $html);
        $html = str_replace('"}}' , '' , $html);
        $html = str_replace('&ge;' , '>=' , $html);

        $options = new Options();

        $options->setIsHtml5ParserEnabled(true);
        $options->setIsRemoteEnabled(true);

        $dompdf = new Dompdf();
        $dompdf->setOptions($options);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $dompdf->stream("dompdf_out.pdf", array("Attachment" => false));
//        exit(0);
        $this->_fileManager->write( $this->getMediaPath() ."pdf/" . $productId . '_' . $storeCode . '.pdf', $dompdf->output(), 777);

        return $this->_response->setRedirect($this->getMediaDir() . $productId . '_' . $storeCode . '.pdf');


    }

}
