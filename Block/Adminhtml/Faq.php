<?php
namespace Saleslayer\Synccatalog\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class Faq extends Template
{
    
    protected $_backendUrl;

    public function __construct(
        Context $context,
        array $data = []
    ) {
        $this->_backendUrl = $context->getUrlBuilder();
        parent::__construct($context, $data);
    }

    function getViewSLLogsUrl(){

        return $this->_backendUrl->getUrl('synccatalog/index/showdebbug');

    }

    function getSLToolsUrl(){

        return $this->_backendUrl->getUrl('synccatalog/index/tools');

    }

    function getSLConfigurationParametersUrl(){

        return $this->_backendUrl->getUrl('adminhtml/system_config/edit/section/synccatalog');

    }

}