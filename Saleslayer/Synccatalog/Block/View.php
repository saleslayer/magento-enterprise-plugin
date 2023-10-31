<?php
namespace Saleslayer\Synccatalog\Block;

/**
 * Sales order view block
 */
class View extends \Magento\Framework\View\Element\Template
{

    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry = null;

    /** @var \Saleslayer\Synccatalog\Helper\Data */
    protected $_dataHelper;
    protected $httpContext;
    
    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\App\Http\Context $httpContext
     * @param \Magento\Payment\Helper\Data $paymentHelper
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Http\Context $httpContext,
        \Saleslayer\Synccatalog\Helper\Data $dataHelper,
        array $data = []
    ) {
        $this->_coreRegistry = $registry;
        $this->httpContext = $httpContext;
        $this->_dataHelper = $dataHelper;
        parent::__construct($context, $data);
    }

    /**
     * @return void
     */
    protected function _prepareLayout()
    {
        $this->pageConfig->getTitle()->set($this->getConnectors()->getTitle());
    }

    /**
     * Retrieve current order model instance
     *
     * @return \Saleslayer\Synccatalog\Model\Synccatalog
     */
    public function getConnectors()
    {
        return $this->_coreRegistry->registry('current_connectors');
    }

    /**
     * Return back url for logged in and guest users
     *
     * @return string
     */
    public function getBackUrl()
    {
        return $this->getUrl('synccatalog/index/index');
    }

    /**
     * Return back title for logged in and guest users
     *
     * @return string
     */
    public function getBackTitle()
    {
        if ($this->httpContext->getValue(Context::CONTEXT_AUTH)) {
            return __('Back to Connectors');
        }
        return __('View Another Connectors');
    }
    
}
