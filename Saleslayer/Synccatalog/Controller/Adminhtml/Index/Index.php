<?php
namespace Saleslayer\Synccatalog\Controller\Adminhtml\Index;

use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends \Magento\Backend\App\Action
{
	/**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }
	
    /**
     * Check the permission to run it
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Saleslayer_Synccatalog::synccatalog_manage');
    }

    /**
     * Synccatalog List action
     *
     * @return void
     */
    public function execute()
    {
		/** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu(
            'Saleslayer_Synccatalog::synccatalog_manage'
        )->addBreadcrumb(
            __('Synccatalog'),
            __('Synccatalog')
        )->addBreadcrumb(
            __('Manage Synccatalog'),
            __('Manage Synccatalog')
        );
        $resultPage->getConfig()->getTitle()->prepend(__('Synccatalog'));
        return $resultPage;
    }
}
