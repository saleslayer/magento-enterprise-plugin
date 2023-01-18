<?php
namespace Saleslayer\Synccatalog\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;

class Tools extends \Magento\Backend\App\Action
{
    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry = null;

	/**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory;
	
    /**
     * @param Action\Context $context
	 * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Magento\Framework\Registry $registry
     */
    public function __construct(Action\Context $context, \Magento\Framework\View\Result\PageFactory $resultPageFactory, \Magento\Framework\Registry $registry)
    {
		$this->resultPageFactory = $resultPageFactory;
        $this->_coreRegistry = $registry;
        parent::__construct($context);
    }

    /**
     * {@inheritdoc}
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Saleslayer_Synccatalog::tools');
    }

    /**
     * Init actions
     *
     * @return $this
     */

    protected function _initAction()
    {
        // load layout, set active menu and breadcrumbs
		/** @var \Magento\Backend\Model\View\Result\Page $resultPage */
      $resultPage = $this->resultPageFactory->create();
       /* $resultPage->setActiveMenu(
            'Saleslayer_Synccatalog::synccatalog_tools'
        );*/

       /*
        $resultPage->setActiveMenu(
            'Saleslayer_Synccatalog::synccatalog_manage'
        )->addBreadcrumb(
            __('Synccatalog'),
            __('Synccatalog')
        )->addBreadcrumb(
            __('Manage Synccatalog'),
            __('Manage Synccatalog')
        );*/
		return $resultPage;
    }

    /**
     * Edit Synccatalog page
     *
     * @return \Magento\Backend\Model\View\Result\Page|\Magento\Backend\Model\View\Result\Redirect
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function execute()
    {

        // $resultPage = $this->_initAction();
        // $resultPage->addBreadcrumb(
        //     __('Tools'),
        //     __('Tools')
        // );
        // $resultPage->getConfig()->getTitle()
        //     ->prepend( __('Sales Layer tools'));
        
        // return $resultPage;

        // 1. Get ID and create model
        $id = $this->getRequest()->getParam('id');
        $model = $this->_objectManager->create('Saleslayer\Synccatalog\Model\Synccatalog');

        // 2. Initial checking
        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                $this->messageManager->addError(__('This connector no longer exists.'));
                /** \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
                $resultRedirect = $this->resultRedirectFactory->create();

                return $resultRedirect->setPath('*/*/');
            }
        }

        // 3. Set entered data if was error when we do save
        $data = $this->_objectManager->get('Magento\Backend\Model\Session')->getFormData(true);
        if (!empty($data)) {
            $model->setData($data);
        }

        // 4. Register model to use later in blocks
       // $this->_coreRegistry->register('fortools', $model);

        // 5. Build edit form
		/** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->_initAction();
        $resultPage->addBreadcrumb(
            $id ? __('Tools') : __('Tools'),
            $id ? __('Tools') : __('Tools')
        );
        //$resultPage->getConfig()->getTitle()->prepend(__('Synccatalog'));
        $resultPage->getConfig()->getTitle()
            ->prepend( __('Sales Layer tools'));
			
        return $resultPage;
    }
}
