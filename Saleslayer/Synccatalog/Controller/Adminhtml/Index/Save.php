<?php
namespace Saleslayer\Synccatalog\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;

class Save extends \Magento\Backend\App\Action
{
    /**
     * @var PostDataProcessor
     */
    protected $dataProcessor;

    /**
     * @param Action\Context $context
     * @param PostDataProcessor $dataProcessor
     */
    public function __construct(Action\Context $context, PostDataProcessor $dataProcessor)
    {
        $this->dataProcessor = $dataProcessor;
        parent::__construct($context);
    }

    /**
     * {@inheritdoc}
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Saleslayer_Synccatalog::save');
    }

    /**
     * Save action
     *
     * @return void
     */
    public function execute()
    {

        $data = $this->getRequest()->getPostValue();
        
        if ($data) {
            $data = $this->dataProcessor->filter($data);
            $model = $this->_objectManager->create('Saleslayer\Synccatalog\Model\Synccatalog');

            $id = $this->getRequest()->getParam('id');
            if ($id) {
                $model->load($id);

                $connector_id = $model->getConnectorId();
                $store_view_ids = $this->getRequest()->getParam('store_view_ids');
                $format_configurable_attributes = $this->getRequest()->getParam('format_configurable_attributes');
                $default_cat_id = $this->getRequest()->getParam('default_cat_id');
                $page_layout = $this->getRequest()->getParam('category_page_layout');
                $this->getRequest()->getParam('products_previous_categories') !== null ? $products_previous_categories = 1 : $products_previous_categories = 0;
                $this->getRequest()->getParam('avoid_stock_update') !== null ? $avoid_stock_update = 1 : $avoid_stock_update = 0;
                $this->getRequest()->getParam('category_is_anchor') == 1 ? $is_anchor = 1 : $is_anchor = 0;
                $auto_sync = $this->getRequest()->getParam('auto_sync');
                $model->update_conn_field($connector_id, 'store_view_ids', $store_view_ids);
                $model->update_conn_field($connector_id, 'format_configurable_attributes', $format_configurable_attributes);
                $model->update_conn_field($connector_id, 'products_previous_categories', $products_previous_categories);
                $model->update_conn_field($connector_id, 'auto_sync', $auto_sync);
                $model->update_conn_field($connector_id, 'avoid_stock_update', $avoid_stock_update);
                $model->update_conn_field($connector_id, 'default_cat_id', $default_cat_id);
                $model->update_conn_field($connector_id, 'category_is_anchor', $is_anchor);
                $model->update_conn_field($connector_id, 'category_page_layout', $page_layout);

                $data_return = $model->store_sync_data($connector_id);

                if (is_array($data_return)){

                    $indexes = array('categories_to_delete', 'products_to_delete', 'product_formats_to_delete', 'categories_to_sync', 'products_to_sync', 'product_formats_to_sync');

                    $delete_msg = $sync_msg = '';
                    
                    foreach ($indexes as $idx){

                        if (isset($data_return[$idx]) && $data_return[$idx] > 0){

                            $msg = $data_return[$idx];

                            if (strpos($idx, 'delete') !== false){

                                ($delete_msg == '') ? $delete_msg = 'To delete: ' : $delete_msg .= ', ';
                                $delete_msg .= $msg.' '.str_replace('_', ' ', substr($idx, 0, strpos($idx, '_to')));

                            }else{

                                ($sync_msg == '') ? $sync_msg = 'To synchronize: ' : $sync_msg .= ', ';
                                $sync_msg .= $msg.' '.str_replace('_', ' ', substr($idx, 0, strpos($idx, '_to')));
                             
                            }

                        }

                    }

                    if ($delete_msg != '' || $sync_msg != ''){

                        $this->messageManager->addSuccess(__('Sales Layer synchronization data stored successfully!'));
                        $this->messageManager->addSuccess(__('Total items stored: '));
                        if ($delete_msg != ''){ $this->messageManager->addSuccess(__($delete_msg.'.')); }
                        if ($sync_msg != ''){ $this->messageManager->addSuccess(__($sync_msg).'.'); }

                    }else{

                        $this->messageManager->addWarning(__('There is no information to synchronize.'));

                    }

                }else{

                    $this->messageManager->addWarning(__($data_return));

                }


            }else{
                
                $model->load($data['connector_id'], 'connector_id');
                
                if ($model->getId()){
                    $this->messageManager->addError(__('The connector already exists.'));
                }else{

                    try {
                        $connector_id = $this->getRequest()->getParam('connector_id');
                        $secret_key = $this->getRequest()->getParam('secret_key');
                        $store_view_ids = $this->getRequest()->getParam('store_view_ids');
                        $format_configurable_attributes = $this->getRequest()->getParam('format_configurable_attributes');
                        $default_cat_id = $this->getRequest()->getParam('default_cat_id');
                        $page_layout = $this->getRequest()->getParam('category_page_layout');
                        $this->getRequest()->getParam('products_previous_categories') !== null ? $products_previous_categories = 1 : $products_previous_categories = 0;
                        $this->getRequest()->getParam('avoid_stock_update') !== null ? $avoid_stock_update = 1 : $avoid_stock_update = 0;
                        $auto_sync = $this->getRequest()->getParam('auto_sync');
                        $this->getRequest()->getParam('category_is_anchor') == 1 ? $is_anchor = 1 : $is_anchor = 0;
                        
                        $result_login = $model->login_saleslayer($connector_id, $secret_key);

                        if ($result_login == 'login_ok'){

                            $model->update_conn_field($connector_id, 'store_view_ids', $store_view_ids);
                            $model->update_conn_field($connector_id, 'format_configurable_attributes', $format_configurable_attributes);
                            $model->update_conn_field($connector_id, 'products_previous_categories', $products_previous_categories);
                            $model->update_conn_field($connector_id, 'auto_sync', $auto_sync);
                            $model->update_conn_field($connector_id, 'avoid_stock_update', $avoid_stock_update);
                            $model->update_conn_field($connector_id, 'default_cat_id', $default_cat_id);
                            $model->update_conn_field($connector_id, 'category_is_anchor', $is_anchor);
                            $model->update_conn_field($connector_id, 'category_page_layout', $page_layout);

                            $this->messageManager->addSuccess(__('Sales Layer connection established successfully!'));

                        }else{

                            $this->messageManager->addWarning(__('Could not create the connector: '.$result_login));

                        }

                        $this->_redirect('*/synchronization');

                    } catch (\Magento\Framework\Model\Exception $e) {
                        $this->messageManager->addError($e->getMessage());
                    } catch (\RuntimeException $e) {
                        $this->messageManager->addError($e->getMessage());
                    } catch (\Exception $e) {
                        $this->messageManager->addException($e, $e->getMessage());
                    }
                }
            }
            
            $this->_redirect('*/*/');
            return;

        }
        $this->_redirect('*/*/');
    }
}
