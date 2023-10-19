<?php

namespace Saleslayer\Synccatalog\Controller\Adminhtml\Ajax;
use Magento\Framework\Controller\ResultFactory;

class Updateconnfield extends \Magento\Framework\App\Action\Action
{
    protected $modelo;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\Framework\View\Result\PageFactory $resultFactory,
        \Saleslayer\Synccatalog\Model\Synccatalog $modelo
    ) {
        $this->jsonHelper = $jsonHelper;
        $this->resultFactory = $resultFactory;
        parent::__construct($context);
        $this->modelo = $modelo;
    }

    public function execute()
    {

        $connector_id = $this->getRequest()->getParam('connector_id');
        $field_name = $this->getRequest()->getParam('field_name');
        $field_value = $this->getRequest()->getParam('field_value');
        
        $result_update = $this->modelo->update_conn_field($connector_id, $field_name, $field_value);

        $field_names = array('default_cat_id'                 => 'Default category',
                             'avoid_stock_update'             => 'Avoid stock update',
                             'auto_sync'                      => 'Auto Sync',
                             'auto_sync_hour'                 => 'Auto Sync hour',
                             'category_page_layout'           => 'Category page layout',
                             'category_is_anchor'             => 'Category is anchor',
                             'store_view_ids'                 => 'Store view ids',
                             'products_previous_categories'   => 'Products previous categories',
                             'format_configurable_attributes' => 'Format configurable attributes');

        /** @var \Magento\Framework\Controller\Result\Raw $response */
        $response = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $response->setHeader('Content-type', 'text/plain');

        $array_return = array();
        $field_message = (isset($field_names[$field_name])) ? $field_names[$field_name] : $field_name;

        if ($result_update){

            $array_return['message_type'] = 'success';
            $array_return['message'] = 'Field updated successfully '.$field_message;

        }else{

            $array_return['message_type'] = 'error';
            $array_return['message'] = 'Error on field update '.$field_message;

        }
        $response->setContents($this->jsonHelper->jsonEncode( $array_return));

        return $response;
    }
}