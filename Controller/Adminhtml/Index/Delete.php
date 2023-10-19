<?php
namespace Saleslayer\Synccatalog\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;

class Delete extends \Magento\Backend\App\Action
{
    /**
     * {@inheritdoc}
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Saleslayer_Synccatalog::delete');
    }

    /**
     * Delete action
     *
     * @return void
     */
    public function execute()
    {
        // check if we know what should be deleted
        $id = $this->getRequest()->getParam('id');
		/** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($id) {
            // $connector_id = "";
            try {
                // init model and delete
                $model = $this->_objectManager->create('Saleslayer\Synccatalog\Model\Synccatalog');
                $model->load($id);
                $model->delete();
                // display success message
                $this->messageManager->addSuccess(__('The connector has been deleted.'));
                // go to grid
                return $resultRedirect->setPath('*/*/');
            } catch (\Exception $e) {
                // display error message
                $this->messageManager->addError($e->getMessage());
                // go back to edit form
                return $resultRedirect->setPath('*/*/edit', ['page_id' => $id]);
            }
        }
        // display error message
        $this->messageManager->addError(__('We can\'t find a connector to delete.'));
        // go to grid
        return $resultRedirect->setPath('*/*/');
    }
}
