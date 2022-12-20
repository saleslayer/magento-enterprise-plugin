<?php
namespace Saleslayer\Synccatalog\Model;

/*
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);
umask(0);
*/

use Magento\Catalog\Api\ProductAttributeManagementInterface as productAttributeManagementInterface;
use Magento\Catalog\Model\Category as categoryModel;
use Magento\Catalog\Model\Product as productModel;
use Magento\Catalog\Model\ProductRepository as productRepository;
use Magento\Catalog\Api\Data\ProductLinkInterface as productLinkInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory as productInterfaceFactory;
use Magento\Catalog\Model\Product\Attribute\Source\Countryofmanufacture as countryOfManufacture;
use \Magento\Catalog\Model\Category\Attribute\Source\Layout as layoutSource;
use Magento\CatalogInventory\Model\Configuration as catalogInventoryConfiguration;
use Magento\CatalogUrlRewrite\Model\CategoryUrlPathGenerator as categoryUrlPathGenerator;
use Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator as productUrlPathGenerator;
use Magento\Cron\Model\Schedule as cronSchedule;
use Magento\Eav\Model\Config as eavConfig;
use Magento\Eav\Model\Entity\Attribute as attribute;
use Magento\Eav\Model\Entity\Attribute\Set as attribute_set;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\Collection as collectionOption;
use Magento\Framework\App\Cache\TypeListInterface as typeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface as scopeConfigInterface;
use Magento\Framework\App\DeploymentConfig as deploymentConfig;
use Magento\Framework\App\ProductMetadataInterface as productMetadata;
use Magento\Framework\App\ResourceConnection as resourceConnection;
use Magento\Framework\Data\Collection\AbstractDb as resourceCollection;
use Magento\Framework\Filesystem\DirectoryList  as directoryListFilesystem;
use Magento\Framework\Model\Context as context;
use Magento\Framework\Model\ResourceModel\AbstractResource as resource;
use Magento\Framework\Registry as registry;
use Magento\Indexer\Model\Indexer as indexer;
use Saleslayer\Synccatalog\Helper\Config as synccatalogConfigHelper;
use Saleslayer\Synccatalog\Helper\Data as synccatalogDataHelper;
use Saleslayer\Synccatalog\Model\SalesLayerConn as SalesLayerConn;
use Zend_Db_Expr as Expr;

/**
 * Class Saleslayer_Synccatalog_Model_Syncdatacron
 */
class Syncdatacron extends Synccatalog{
    
    protected       $sl_time_ini_sync_data_process;
    protected       $max_execution_time                 = 290;
    protected       $end_process;
    protected       $initialized_vars                   = false;
    protected       $sql_items_delete                   = array();
    protected       $category_fields                    = array();
    protected       $product_fields                     = array();
    protected       $product_format_fields              = array();
    // protected       $indexers_status                    = 'default';
    // protected       $indexer_collection_ids             = array();
    // protected       $indexers_info                      = array();
    protected       $syncdata_pid;
    protected       $processed_items                    = array();
    protected       $cats_to_process                    = false;
    protected       $cats_corrected                     = false;
    protected       $updated_product_formats            = false;

    protected       $test_one_item                      = false;
    protected       $multiconn_table_data_loaded        = false;

    /**
     * Sales Layer Syncdata constructor.
     * @return void
     */
    public function __construct(
                context $context,
                registry $registry,
                SalesLayerConn $salesLayerConn,
                synccatalogDataHelper $synccatalogDataHelper,
                synccatalogConfigHelper $synccatalogConfigHelper,
                directoryListFilesystem $directoryListFilesystem,
                categoryModel $categoryModel,
                productModel $productModel,
                attribute $attribute,
                attribute_set $attribute_set,
                productAttributeManagementInterface $productAttributeManagementInterface,
                indexer $indexer,
                resourceConnection $resourceConnection,
                collectionOption $collectionOption,
                cronSchedule $cronSchedule,
                scopeConfigInterface $scopeConfigInterface,
                categoryUrlPathGenerator $categoryUrlPathGenerator,
                productUrlPathGenerator $productUrlPathGenerator,
                catalogInventoryConfiguration $catalogInventoryConfiguration,
                deploymentConfig $deploymentConfig,
                eavConfig $eavConfig,
                typeListInterface $typeListInterface,
                productMetadata $productMetadata,
                countryOfManufacture $countryOfManufacture,
                layoutSource $layoutSource,
                resource $resource = null,
                resourceCollection $resourceCollection = null,
                array $data = []/* ,
            	productInterfaceFactory $productInterfaceFactory = null,
                productRepository $productRepository = null,
                productLinkInterface $productLinkInterface = null */) {
        parent::__construct($context,
                            $registry, 
                            $salesLayerConn, 
                            $synccatalogDataHelper, 
                            $synccatalogConfigHelper,
                            $directoryListFilesystem,
                            $categoryModel, 
                            $productModel,
                            $attribute,
                            $attribute_set,
                            $productAttributeManagementInterface,
                            $indexer,
                            $resourceConnection,
                            $collectionOption,
                            $cronSchedule,
                            $scopeConfigInterface,
                            $categoryUrlPathGenerator,
                            $productUrlPathGenerator,
                            $catalogInventoryConfiguration,
                            $deploymentConfig,
                            $eavConfig,
                            $typeListInterface,
                            $productMetadata,
                            $countryOfManufacture,
                            $layoutSource,
                            $resource,
                            $resourceCollection,
                            $data/* ,
                            $productInterfaceFactory,
                            $productRepository,
                            $productLinkInterface */);

    }

    /**
     * Function to check current process time to avoid exceding the limit.
     * @return void
     */
    private function check_process_time(){

        $current_process_time = microtime(1) - $this->sl_time_ini_sync_data_process;
        
        if ($current_process_time >= $this->max_execution_time){

            $this->end_process = true;

        }

    }

    /**
     * Function to initialize catalogue vars to load before synchronizing.
     * @return void
     */
    private function initialize_vars(){

        if (!$this->initialized_vars){

            if (!$this->execute_slyr_load_functions()){

                $this->debbug('## Error. Could not load synchronization parameters. Please check error log.', 'syncdata');
                $this->end_process = true;

            }
            
            $this->category_fields = array('category_field_name', 'category_field_url_key', 'category_field_description', 'category_field_image', 'category_field_meta_title', 'category_field_meta_keywords', 'category_field_meta_description', 'category_field_active', 'category_images_sizes', 'category_field_page_layout','category_field_is_anchor');
            $this->product_fields = array('product_field_name', 'product_field_description', 'product_field_description_short', 'product_field_price', 'product_field_image', 'product_field_sku', 'product_field_qty', 'product_field_inventory_backorders', 'product_field_inventory_min_sale_qty', 'product_field_inventory_max_sale_qty', 'product_field_meta_title', 'product_field_meta_keywords', 'product_field_meta_description', 'product_field_length', 'product_field_width', 'product_field_height', 'product_field_weight', 'product_field_status', 'product_field_visibility', 'product_field_related_references', 'product_field_crosssell_references', 'product_field_upsell_references', 'product_field_attribute_set_id', 'product_images_sizes','main_image_extension', 'product_field_tax_class_id', 'product_field_country_of_manufacture', 'product_field_special_price', 'product_field_special_from_date', 'product_field_special_to_date', 'grouping_ref_field_linked');
            $this->product_format_fields = array('format_images_sizes', 'main_image_extension', 'format_field_sku', 'format_name', 'format_price', 'format_quantity', 'format_field_inventory_backorders', 'format_field_inventory_min_sale_qty', 'format_field_inventory_max_sale_qty', 'format_image', 'format_field_tax_class_id', 'format_field_country_of_manufacture', 'format_field_visibility', 'format_field_special_price', 'format_field_special_from_date', 'format_field_special_to_date');

            $this->initialized_vars = true;

        }

    }

    /**
     * Function to check sql rows to delete from sync data table.
     * @param  boolean $force_delete                will force delete from database
     * @return void
     */
    private function check_sql_items_delete($force_delete = false){

        if (count($this->sql_items_delete) >= 20 || ($force_delete && count($this->sql_items_delete) > 0)){
            
            if ($this->test_one_item === false){
            
                $items = implode(',', $this->sql_items_delete);

                $sql_delete = " DELETE FROM ".$this->saleslayer_syncdata_table.
                                    " WHERE id IN (".$items.")";

                $this->sl_connection_query($sql_delete);

            }

            $this->sql_items_delete = array();

        }

    }

    /**
     * Function to check sync data pid flag in database and delete kill it if the process is stuck.
     * @return void
     */
    private function check_sync_data_flag(){

        $items_to_process = $this->connection->query(" SELECT count(*) as count FROM ".$this->saleslayer_syncdata_table)->fetch();
        
        if (isset($items_to_process['count']) && $items_to_process['count'] > 0){

            $current_flag = $this->connection->query(" SELECT * FROM ".$this->saleslayer_syncdata_flag_table." ORDER BY id DESC LIMIT 1")->fetch();
            $now = strtotime('now');
            $date_now = date('Y-m-d H:i:s', $now);

            if ( empty($current_flag)){

                $sl_query_flag_to_insert = " INSERT INTO ".$this->saleslayer_syncdata_flag_table.
                                         " ( syncdata_pid, syncdata_last_date) VALUES ".
                                         "('".$this->syncdata_pid."', '".$date_now."')";
                
                $this->sl_connection_query($sl_query_flag_to_insert);

                return;
            }

                
            if ($current_flag['syncdata_pid'] == 0){
            
                $sl_query_flag_to_update = " UPDATE ".$this->saleslayer_syncdata_flag_table.
                                        " SET syncdata_pid = ".$this->syncdata_pid.", syncdata_last_date = '".$date_now."'".
                                        " WHERE id = ".$current_flag['id'];
            
                $this->sl_connection_query($sl_query_flag_to_update);

                return;

            }

            $interval  = abs($now - strtotime($current_flag['syncdata_last_date']));
            $minutes   = round($interval / 60);
            
            if ($minutes < 10){
            
                $this->debbug('Data is already being processed.', 'syncdata');
                $this->end_process = true;

                return;

            }
                
            if ($this->syncdata_pid === $current_flag['syncdata_pid']){

                $this->debbug('Pid is the same as current.', 'syncdata');

            }

            $flag_pid_is_alive = $this->has_pid_alive($current_flag['syncdata_pid']);
            
            if ($flag_pid_is_alive){
            
                try{

                    $this->debbug('Killing pid: '.$current_flag['syncdata_pid'].' with user: '.get_current_user(), 'syncdata');
                    
                    $result_kill = posix_kill($current_flag['syncdata_pid'], 0);

                    if (!$result_kill){

                        $this->debbug('## Error. Could not kill pid '.$current_flag['syncdata_pid'], 'syncdata');

                    }

                }catch(\Exception $e){
            
                    $this->debbug('## Error. Exception killing pid '.$current_flag['syncdata_pid'].': '.print_r($e->getMessage(),1), 'syncdata');
            
                }
                                            
            }

            $sl_query_flag_to_update = " UPDATE ".$this->saleslayer_syncdata_flag_table.
                                    " SET syncdata_pid = ".$this->syncdata_pid.", syncdata_last_date = '".$date_now."'".
                                    " WHERE id = ".$current_flag['id'];

            $this->sl_connection_query($sl_query_flag_to_update);
            
        }

    }

    /**
    * Function to disable sync data pid flag in database.
    * @return void
    */
    private function disable_sync_data_flag(){
        try{

            $current_flag = $this->connection->query(" SELECT * FROM ".$this->saleslayer_syncdata_flag_table." ORDER BY id DESC LIMIT 1")->fetch();

            if (!empty($current_flag)){
    
                $sl = " UPDATE ".$this->saleslayer_syncdata_flag_table.
                                        " SET syncdata_pid = 0".
                                        " WHERE id = ".$current_flag['id'];
                $this->sl_connection_query($sl);
    
            }
        
        }catch(\Exception $e){

            $this->debbug('## Error. Deleting sync_data_flag: '.$e->getMessage(), 'syncdata');

        }

    }

    /**
     * Function to delete registers that have more than 3 tries
     * @return void
     */
    private function clearExcededAttemps(){
        try{
            
            $sql_delete = " DELETE FROM ".$this->saleslayer_syncdata_table." WHERE sync_tries >= 3";

            $this->sl_connection_query($sql_delete);

        }catch(\Exception $e){

            $this->debbug('## Error. Clearing exceeded attemps: '.$e->getMessage(), 'syncdata');

        }
    }

    /**
     * Function to check if the current hour is between the config synchronization hours
     * @return void
     */
    private function testRecurrentExecution(){
        $hour_from = $this->sync_data_hour_from.':00';
        $hour_from_time = strtotime($hour_from);
        $hour_until = $this->sync_data_hour_until.':00';
        $hour_until_time = strtotime($hour_until);
        $hour_now = date('H').':00';
        $hour_now_time = strtotime($hour_now);
    
        if (($hour_from_time < $hour_until_time && $hour_now_time >= $hour_from_time && $hour_now_time <= $hour_until_time) 
         || ($hour_from_time > $hour_until_time && ($hour_now_time >= $hour_from_time || $hour_now_time <= $hour_until_time)) 
         ||  $hour_from_time == $hour_until_time){
            
            $this->debbug('Current hour '.$hour_now.' for sync data process.', 'syncdata');
        
        } else {
        
            $this->end_process = true;
            $this->debbug('Current hour '.$hour_now.' is not set between hour from '.$hour_from.' and hour until '.$hour_until.'. Finishing sync data process.', 'syncdata');
        
        }
    }

    /**
     * Function to synchronize Sales Layer stored data 
     * @return void
     */
    public function sync_data_connectors_db(){ 

        $this->sl_time_ini_sync_data_process = microtime(1);

        $this->loadConfigParameters();
        $this->load_magento_variables();

        if ($this->clean_main_debug_file) file_put_contents($this->sl_logs_path.'_debbug_log_saleslayer_'.date('Y-m-d').'.dat', "");

        $this->debbug("==== Sync Data DB INIT ".date('Y-m-d H:i:s')." ====", 'syncdata');
        $this->debbug("==== Synccatalog version: ". $this->moduleVersion ." ====", 'syncdata');
        $this->debbug("==== Magento version: ". $this->productMetadata->getVersion() . " - " . $this->productMetadata->getEdition() ." ====", 'syncdata');

        $this->clearExcededAttemps();

        $this->syncdata_pid = getmypid();

        $this->end_process = false;        
        if (!in_array($this->sync_data_hour_from, array('', null, 0)) || !in_array($this->sync_data_hour_until, array('', null, 0))){
            
            $this->testRecurrentExecution();   

        }

        if (!$this->end_process){

            $this->check_sync_data_flag();

            if (!$this->end_process){

                $this->deleteItems();

                $this->updateAllTableItems();
                
            }

            $this->check_sql_items_delete(true);

            if (!$this->end_process){

                $this->clearExcededAttemps();

            }       

            $this->disable_sync_data_flag();

        }

        $this->generateSummary();
        
        $this->debbug('### time_all_syncdata_process: '.(microtime(1) - $this->sl_time_ini_sync_data_process).' seconds.', 'syncdata');

        $this->debbug("==== Sync Data DB END ====", 'syncdata');

    }

    /**
     * Function to process update items
     * @return void
     */
    private function updateAllTableItems(){

        $indexes = array('category', 'product', 'product_format', 'product_links', 'product__images');
        
        $old_index = reset($indexes);

        $this->cats_to_process = $this->cats_corrected = false;

        foreach ($indexes as $index) {

            if(!$this->updateItems($index)){
                break;
            }
             
        }

        if (!empty($this->processed_items)){

            $this->clean_cache();

        }

        if ($this->updated_product_formats){

            $this->reindexAfterFormats();

        }

    }

    /**
     * Function to update items
     * @param  string $index            type of item to process
     * @return boolean                  result of update
     */
    private function updateItems($index){
        
        do{

            $items_to_update = $this->connection->fetchAll(" SELECT * FROM ".$this->saleslayer_syncdata_table." WHERE sync_type = 'update' and item_type = '".$index."' and sync_tries <= 2 ORDER BY item_type ASC, sync_tries ASC, id ASC LIMIT 50");

            if ($this->test_one_item !== false && is_numeric($this->test_one_item)){

                $items_to_update = $this->connection->fetchAll(" SELECT * FROM ".$this->saleslayer_syncdata_table." WHERE sync_type = 'update' and item_type = '".$index."' and sync_tries <= 2 and id = ".$this->test_one_item." ORDER BY item_type ASC, sync_tries ASC, id ASC LIMIT 50");

            }

            if ($index == 'category' && !$this->cats_to_process){

                if (!empty($items_to_update)){

                    $this->cats_to_process = true;

                }

            }else if ($index !== 'category'){

                if ($this->cats_to_process && !$this->cats_corrected){

                    $this->correct_categories_core_data();
                    $this->cats_corrected = true;

                }

            }

            if (empty($items_to_update)){
                return true;
            }

            $this->initialize_vars();

            foreach ($items_to_update as $item_to_update) {
                
                $this->check_process_time();

                if ($this->end_process){

                    $this->debbug('Breaking syncdata process due to time limit.', 'syncdata');
                    return false;

                }
             
                $this->updateItem($item_to_update);

                if ($this->test_one_item !== false) $this->end_process = true;
                if ($this->end_process){

                    return false;

                }

            }

        }while(!empty($items_to_update));

        return true;
        
    }

    /**
     * Function to delete items
     * @return void
     */
    private function deleteItems(){

        try {

            $items_to_delete = $this->connection->fetchAll(" SELECT * FROM ".$this->saleslayer_syncdata_table." WHERE sync_type = 'delete' ORDER BY item_type ASC, sync_tries ASC, id ASC");
            
            if (!empty($items_to_delete)){
                
                $this->initialize_vars();

                foreach ($items_to_delete as $item_to_delete) {
                    
                    $this->check_process_time();
                    $this->check_sql_items_delete();

                    if ($this->end_process){

                        $this->debbug('Breaking syncdata process due to time limit.', 'syncdata');
                        break;

                    }

                    $this->deleteItem($item_to_delete);

                }

            }

        } catch (\Exception $e) {

            $this->debbug('## Error. Deleting syncdata process: '.$e->getMessage(), 'syncdata');

        }

    }

    /**
     * Function to process delete item
     * @param  string $item             item to delete
     * @return void
     */
    private function deleteItem($item_to_delete){

        $sync_tries = $item_to_delete['sync_tries'];
        $sync_params = json_decode(stripslashes($item_to_delete['sync_params']),1);
        $this->processing_connector_id = $sync_params['conn_params']['connector_id'];

        // if (null === $this->comp_id || $this->comp_id == ''){

        //     $this->debbug('cargamos load_sl_multiconn_table_data debido a diferencia de comp_id');
        //     $this->load_sl_multiconn_table_data(); 

        // }

        $this->comp_id = $sync_params['conn_params']['comp_id'];

        if ($this->comp_id != $sync_params['conn_params']['comp_id'] || !$this->multiconn_table_data_loaded){
            $this->load_sl_multiconn_table_data(); 
            $this->multiconn_table_data_loaded = true;
        } 
        
        $this->store_view_ids = $sync_params['conn_params']['store_view_ids'];

        $sl_id = json_decode(stripslashes($item_to_delete['item_data']),1);

        switch ($item_to_delete['item_type']) {
        
            case 'category':
                
                $result_delete = $this->delete_stored_category_db($sl_id);
                break;
            
            case 'product':
                
                $result_delete = $this->delete_stored_product_db($sl_id);
                break;

            case 'product_format':
                
                $result_delete = $this->delete_stored_product_format_db($sl_id);
                break;

            default:
                
                $this->debbug('## Error. Incorrect item: '.print_R($item_to_delete,1), 'syncdata');
                break;

        }

        if( $result_delete == 'item_not_deleted'){
            $sync_tries++;

            $sql_update = " UPDATE ".$this->saleslayer_syncdata_table." SET sync_tries = ".$sync_tries." WHERE id = ".$item_to_delete['id'];

            $this->sl_connection_query($sql_update);
        }else{
            $this->sql_items_delete[] = $item_to_delete['id'];
        }

    }

    /**
     * Function to generate summary of current synchronization process.
     * @return void
     */
    private function generateSummary(){

        if (!empty($this->processed_items)){

            foreach ($this->processed_items as $processed_item_type => $processed_item_type_count) {
                
                $this->debbug('- Processed_items - type: '.$processed_item_type.' count: '.$processed_item_type_count, 'syncdata');

            }

        }

    }

    /**
     * Function to clean Magento cache
     * @return void
     */
    public function clean_cache(){

        $time_ini_clean_all_caches = microtime(1);

        $types = [
            \Magento\Framework\App\Cache\Type\Block::TYPE_IDENTIFIER,
            \Magento\PageCache\Model\Cache\Type::TYPE_IDENTIFIER
        ];

        foreach ($types as $type) {

            $time_ini_clean_cache = microtime(1);
            $this->typeListInterface->cleanType($type);
            if ($this->sl_DEBBUG > 1) $this->debbug('### time_clean_cache: ', 'timer', (microtime(1) - $time_ini_clean_cache));

        }

        $this->debbug('Cache cleaned for: '.print_r($types,1));
        $this->debbug('#### time_clean_all_caches: ', 'timer', (microtime(1) - $time_ini_clean_all_caches));

    }

    /**
     * Function to reindex Magento indexers after product format synchronization
     * @return void
     */
    public function reindexAfterFormats(){

        $time_ini_reindex_after_formats = microtime(1);

        $indexLists = array('catalog_product_attribute', 'catalogrule_product');

        if (version_compare($this->mg_version, '2.4.0') >= 0) {
        
            $indexLists[] = 'catalogsearch_fulltext';

        }

        foreach($indexLists as $indexList) {
            
            try{

                $time_ini_index_row = microtime(1);
                $categoryIndexer = $this->indexer->load($indexList);
           
                if (!$categoryIndexer->isScheduled()) {
                
                    if ($this->sl_DEBBUG > 0) $this->debbug('Reindexing indexer after product formats sync: '.$indexList, 'syncdata');
                    $categoryIndexer->reindexAll();
                                        
                }

            }catch(\Exception $e){

                $this->debbug('## Error. Updating index row '.$indexList.' : '.print_R($e->getMessage(),1), 'syncdata');

            }

            if ($this->sl_DEBBUG > 2) $this->debbug('## time_index_row '.$indexList.': ', 'timer', (microtime(1) - $time_ini_index_row));

        }

        $this->debbug('#### time_reindex_after_formats: ', 'timer', (microtime(1) - $time_ini_reindex_after_formats));

    }

    /**
     * Function to update item depending on type.
     * @param  $item_to_update          item date to update in Magento
     * @return void
     */
    private function updateItem($item_to_update){
         
        $sync_tries = $item_to_update['sync_tries'];
        
        $item_data = json_decode($item_to_update['item_data'],1);
        
        if ($item_data == ''){
        
            $this->debbug("## Error. Decoding item's data: ".print_R($item_to_update['item_data'],1), 'syncdata');
            $this->sql_items_delete[] = $item_to_update['id'];
            $this->check_sql_items_delete(true);
            return;

        }

        if ($item_to_update['sync_params'] != ''){

            $sync_params = $this->loadSyncParams($item_to_update);

        }

        if (!isset($this->processed_items[$item_to_update['item_type']])){

            $this->processed_items[$item_to_update['item_type']] = 0;

        }

        $this->processed_items[$item_to_update['item_type']]++;
        
        switch ($item_to_update['item_type']) {
            case 'category':
                
                $result_update = $this->updateCategory($item_data, $sync_params);
                break;
            
            case 'product':
                
                $result_update = $this->updateProduct($item_data, $sync_params);
                break;

            case 'product_format':
                
                $result_update = $this->updateProductFormat($item_data, $sync_params);
                break;

            case 'product_links':
                
                $result_update = $this->updateProductLinks($item_data);
                break;

            case 'product__images':

                $result_update = $this->updateProductImages($item_data);
                break;

            default:
                
                $this->debbug('## Error. Incorrect item: '.print_R($item_to_update,1), 'syncdata');
                break;
        }

        
        if ($result_update != 'item_not_updated'){

            $this->sql_items_delete[] = $item_to_update['id'];
            $this->check_sql_items_delete(true);

            return;

        }
             
        $sync_tries++;
        $item_data_string = '';
        
        if ($sync_tries == 2 && $item_to_update['item_type'] == 'category'){

            $resultado = $this->reorganize_category_parent_ids_db($item_data);

            $item_data_string = ", item_data = '".json_encode($resultado)."'";

        }

        $sql_update = " UPDATE ".$this->saleslayer_syncdata_table.
                                    " SET sync_tries = ".$sync_tries.
                                    $item_data_string.
                                    " WHERE id = ".$item_to_update['id'];

        $this->sl_connection_query($sql_update);
        $this->check_sql_items_delete(true);

    }

    /**
     * Function to update product images
     * @param  array $item_data                 item data
     * @return string                           if the item has been updated or not
     */
    private function updateProductImages($item_data){
                    
        if (!isset($item_data['product_id']) && !isset($item_data['format_id'])){

            $this->debbug('## Error. Updating item images - Unknown index: '.print_R($item_data,1), 'syncdata');
            return 'item_updated';
        }

        $item_index = 'product';

        if (isset($item_data['format_id'])){

            $item_index = 'format';

        }

        $time_ini_sync = microtime(1);
        $this->debbug(' >> '.ucfirst($item_index).' images synchronization initialized << ');
        $this->sync_stored_product_images_db($item_data, $item_index);
        $this->debbug(' >> '.ucfirst($item_index).' images synchronization finished << ');
        $this->debbug('#### time_sync_stored_product_images: ', 'timer', (microtime(1) - $time_ini_sync));

        return 'item_updated';

    }

    /**
     * Function to update product links
     * @param  array $item_data         item data
     * @return string                   if the link has been updated or not
     */
    private function updateProductLinks($item_data){

        $time_ini_sync = microtime(1);
        $this->debbug(' >> Product links synchronization initialized << ');
        $this->sync_stored_product_links_db($item_data);
        $this->debbug(' >> Product links synchronization finished << ');
        $this->debbug('#### time_sync_stored_product_links: ', 'timer', (microtime(1) - $time_ini_sync));
        
        return 'item_updated';

    }

    /**
     * Function to update product format
     * @param  array $item_data             item data
     * @param  array $sync_params           synchronization params
     * @return boolean                      result of update
     */
    private function updateProductFormat($item_data, $sync_params){

        $this->avoid_stock_update = $sync_params['avoid_stock_update'];
        $this->format_configurable_attributes = $sync_params['format_configurable_attributes'];

        foreach ($this->product_format_fields as $product_format_field) {
            
            if (isset($sync_params['product_format_fields'][$product_format_field])){

                $this->$product_format_field = $sync_params['product_format_fields'][$product_format_field];

            }

        }

        if (isset($sync_params['format_additional_fields']) && !empty($sync_params['format_additional_fields'])){

            foreach ($sync_params['format_additional_fields'] as $field_name => $field_name_value) {
                
                $this->format_additional_fields[$field_name] = $field_name_value;

            }

        }

        if (isset($sync_params['product_formats_media_field_names']) && !empty($sync_params['product_formats_media_field_names'])){

            $this->media_field_names['product_formats'] = $sync_params['product_formats_media_field_names'];

        }
        
        $time_ini_sync = microtime(1);
        $this->debbug(' >> Format synchronization initialized << ');
        $result_update = $this->sync_stored_format_db($item_data);
        $this->debbug(' >> Format synchronization finished << ');
        $this->debbug('#### time_sync_stored_product_format: ', 'timer', (microtime(1) - $time_ini_sync));

        $this->updated_product_formats = true;
        
        return $result_update;

    }

    /**
     * Function to update product
     * @param  array $item_data             item data
     * @param  array $sync_params           synchronization params
     * @return boolean                      result of update
     */
    private function updateProduct($item_data, $sync_params){

        $this->attribute_set_collection = $sync_params['attribute_set_collection'];
        $this->default_attribute_set_id = $sync_params['default_attribute_set_id'];
        $this->avoid_stock_update = $sync_params['avoid_stock_update'];
        $this->products_previous_categories = $sync_params['products_previous_categories'];
        
        foreach ($this->product_fields as $product_field) {
            
            if (isset($sync_params['product_fields'][$product_field])){

                $this->$product_field = $sync_params['product_fields'][$product_field];
                
            }

        }

        if (isset($sync_params['product_additional_fields']) && !empty($sync_params['product_additional_fields'])){

            foreach ($sync_params['product_additional_fields'] as $field_name => $field_name_value) {
                
                $this->product_additional_fields[$field_name] = $field_name_value;

            }

        }

        if (isset($sync_params['products_media_field_names']) && !empty($sync_params['products_media_field_names'])){

            $this->media_field_names['products'] = $sync_params['products_media_field_names'];

        }
        
        $time_ini_sync = microtime(1);
        $this->debbug(' >> Product synchronization initialized << ');
        $result_update = $this->sync_stored_product_db($item_data);
        $this->debbug(' >> Product synchronization finished << ');
        $this->debbug('#### time_sync_stored_product: ', 'timer', (microtime(1) - $time_ini_sync));
        
        return $result_update;

    }

    /**
     * Function to update category
     * @param  array $item_data             item data
     * @param  array $sync_params           synchronization params
     * @return boolean                      result of update
     */
    private function updateCategory($item_data, $sync_params){

        $this->default_category_id = $sync_params['default_category_id'];
        $this->category_is_anchor = $sync_params['category_is_anchor'];
        $this->category_page_layout = $sync_params['category_page_layout'];
        
        foreach ($this->category_fields as $category_field) {
            
            if (isset($sync_params['category_fields'][$category_field])){

                $this->$category_field = $sync_params['category_fields'][$category_field];

            }

        }

        if (isset($sync_params['catalogue_media_field_names']) && !empty($sync_params['catalogue_media_field_names'])){

            $this->media_field_names['catalogue'] = $sync_params['catalogue_media_field_names'];

        }
        
        $time_ini_sync = microtime(1);
        $this->debbug(' >> Category synchronization initialized << ');
        $result_update = $this->sync_stored_category_db($item_data);
        $this->debbug(' >> Category synchronization finished << ');
        $this->debbug('#### time_sync_stored_category: ', 'timer', (microtime(1) - $time_ini_sync));

        return $result_update;
       
    }

    /**
     * Function to load synchonization params into class parameters
     * @param  array $item_to_update                item to update
     * @return array $sync_params                   decoded sync params
     */
    private function loadSyncParams($item_to_update){

        $sync_params = json_decode(stripslashes($item_to_update['sync_params']),1);
        $this->processing_connector_id = $sync_params['conn_params']['connector_id'];
        ($this->comp_id != $sync_params['conn_params']['comp_id']) ? $load_sl_multiconn_table_data = true : $load_sl_multiconn_table_data = false;
        $this->comp_id = $sync_params['conn_params']['comp_id'];
        if ($load_sl_multiconn_table_data){ $this->load_sl_multiconn_table_data(); }
        $this->store_view_ids = $sync_params['conn_params']['store_view_ids'];
        $this->website_ids = $sync_params['conn_params']['website_ids'];

        return $sync_params;
        
    }

}