<?php
namespace Saleslayer\Synccatalog\Block\Adminhtml;

class Synccatalog extends \Magento\Backend\Block\Widget\Grid\Container
{
    /**
     * Constructor
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_controller = 'adminhtml_synccatalog';
        $this->_blockGroup = 'Saleslayer_Synccatalog';
        $this->_headerText = __('Sales Layer Connectors');
        $this->_addButtonLabel = __('Add New Connector');
        parent::_construct();
    }
    
}
