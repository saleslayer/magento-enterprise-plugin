<?php
namespace Saleslayer\Synccatalog\Block\Adminhtml\Synccatalog;

class Edit extends \Magento\Backend\Block\Widget\Form\Container
{
    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry = null;

    /**
     * @param \Magento\Backend\Block\Widget\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Widget\Context $context,
        \Magento\Framework\Registry $registry,
        array $data = []
    ) {
        $this->_coreRegistry = $registry;
        parent::__construct($context, $data);
    }
    /**
     * Initialize cms page edit block
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_objectId = 'id';
        $this->_blockGroup = 'Saleslayer_Synccatalog';
        $this->_controller = 'adminhtml_synccatalog';

        parent::_construct();

        
        $model = $this->_coreRegistry->registry('synccatalog');
        $modelData = $model->getData();

        if (empty($modelData)){
            $this->buttonList->update('save', 'label', __('Save Connector'));
            $this->buttonList->remove('delete');
        }else{
            $this->buttonList->update('save', 'label', __('Synchronize Connector'));
            $this->buttonList->update('delete', 'label', __('Delete Connector'));
            $this->buttonList->remove('reset');
        }
    }

    /**
     * Retrieve text for header element depending on loaded page
     *
     * @return string
     */
    public function getHeaderText()
    {
        if ($this->_coreRegistry->registry('synccatalog')->getId()) {
            return __("Edit Connector '%1'", $this->escapeHtml($this->_coreRegistry->registry('synccatalog')->getTitle()));
        } else {
            return __('New Connector');
        }
    }

    /**
     * Check permission for passed action
     *
     * @param string $resourceId
     * @return bool
     */
    // protected function _isAllowedAction($resourceId)
    // {
    //     return $this->_authorization->isAllowed($resourceId);
    // }

    /**
     * Getter of url for "Save and Continue" button
     * tab_id will be replaced by desired by JS later
     *
     * @return string
     */
    protected function _getSaveAndContinueUrl()
    {
        return $this->getUrl('synccatalog/*/save', ['_current' => true, 'back' => 'edit', 'active_tab' => '{{tab_id}}']);
    }

}
