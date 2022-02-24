<?php
namespace Saleslayer\Synccatalog\Model;

/*
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);
umask(0);
*/

use \Magento\Framework\Model\Context as context;
use \Magento\Framework\Registry as registry;
use \Magento\Framework\Model\ResourceModel\AbstractResource as resource;
use \Magento\Framework\Data\Collection\AbstractDb as resourceCollection;
use Saleslayer\Synccatalog\Model\SalesLayerConn as SalesLayerConn;
use Saleslayer\Synccatalog\Helper\Data as synccatalogDataHelper;
use Saleslayer\Synccatalog\Helper\Config as synccatalogConfigHelper;
use \Magento\Framework\Filesystem\DirectoryList  as directoryListFilesystem;
use \Magento\Catalog\Model\Category as categoryModel;
use \Magento\Catalog\Model\Product as productModel;
use \Magento\Eav\Model\Entity\Attribute as attribute;
use \Magento\Eav\Model\Entity\Attribute\Set as attribute_set;
use \Magento\Catalog\Api\ProductAttributeManagementInterface as productAttributeManagementInterface;
use \Magento\Indexer\Model\Indexer as indexer;
use \Magento\Framework\App\ResourceConnection as resourceConnection;
use \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\Collection as collectionOption;
use \Magento\Cron\Model\Schedule as cronSchedule;
use \Magento\Framework\App\Config\ScopeConfigInterface as scopeConfigInterface;
use \Magento\CatalogUrlRewrite\Model\CategoryUrlPathGenerator as categoryUrlPathGenerator;
use \Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator as productUrlPathGenerator;
use \Magento\CatalogInventory\Model\Configuration as catalogInventoryConfiguration;
use \Magento\Framework\App\DeploymentConfig as deploymentConfig;
use \Magento\Eav\Model\Config as eavConfig;
use \Magento\Framework\App\Cache\TypeListInterface as typeListInterface;
use \Magento\Framework\App\ProductMetadataInterface as productMetadata;
use \Magento\Catalog\Model\Product\Attribute\Source\Countryofmanufacture as countryOfManufacture;

/**
 * Class Saleslayer_Synccatalog_Model_Autosynccron
 */
class Autosynccron extends Synccatalog{
    
    protected       $sl_time_ini_auto_sync_process;
    protected       $cron_schedule_table            = 'cron_schedule';
    protected       $cronSchedule;

    /**
     * Sales Layer Autosync constructor.
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
                resource $resource = null,
                resourceCollection $resourceCollection = null,
                array $data = []) {
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
                            $resource,
                            $resourceCollection,
                            $data);
        $this->cron_schedule_table           = $this->resourceConnection->getTableName($this->cron_schedule_table);

    }
    
    /**
     * Function to sort connectors by unix_to_update or auto_sync values.
     * @param  array $conn_a                first connector to sort
     * @param  array $conn_b                second connector to sort
     * @return integer                      comparative of connectors
     */
    private function sort_by_unix_to_update($conn_a, $conn_b) {

        $unix_a = $conn_a['unix_to_update'];
        $unix_b = $conn_b['unix_to_update'];

        if ($unix_a == $unix_b) {
             
            $auto_a = $conn_a['auto_sync'];
            $auto_b = $conn_b['auto_sync'];
           
            if ($auto_a == $auto_b){
                
                return 0;

            }   

            return ($auto_a > $auto_b) ? -1 : 1;

        }
      
        return ($unix_a < $unix_b) ? -1 : 1;
    
    }   

    /**
     * Function to check if sync data crons are stuck
     * @return void
     */
    private function check_sync_data_crons(){

        $now = strtotime('now');
        $date_now = date('Y-m-d H:i:s', $now);
        $current_flag = $this->connection->query(" SELECT * FROM ".$this->saleslayer_syncdata_flag_table." ORDER BY id DESC LIMIT 1")->fetch();

        if (!empty($current_flag)){

            if ($current_flag['syncdata_pid'] !== 0){
            
                $interval  = abs($now - strtotime($current_flag['syncdata_last_date']));
                
                if ($interval >= 480){

                    $flag_pid_is_alive = $this->has_pid_alive($current_flag['syncdata_pid']);
                
                    if ($flag_pid_is_alive){
                    
                        try{

                            $this->debbug('Killing pid: '.$current_flag['syncdata_pid'], 'autosync');
                            shell_exec("kill -9 ".$current_flag['syncdata_pid']);
                    
                        }catch(\Exception $e){
                    
                            $this->debbug('## Error. Exception killing pid '.$current_flag['syncdata_pid'].': '.print_r($e->getMessage(),1), 'autosync');
                    
                        }
                
                    }

                    $sl_query_flag_to_update = " UPDATE ".$this->saleslayer_syncdata_flag_table.
                                            " SET syncdata_pid = 0, syncdata_last_date = '".$date_now."'".
                                            " WHERE id = ".$current_flag['id'];

                    $this->sl_connection_query($sl_query_flag_to_update);

                }

            }

        }

        $running_crons = $this->connection->fetchAll(" SELECT * FROM ".$this->cron_schedule_table." WHERE job_code = 'Saleslayer_Synccatalog_Syncdatacron' AND status = 'running' AND executed_at IS NOT NULL");
        
        foreach ($running_crons as $keyRC => $running_cron) {
            
            $cron = $this->cronSchedule->load($running_cron['schedule_id']);
            $interval  = abs($now - strtotime($running_cron['executed_at']));

            if ($interval >= 480){

                $this->debbug('Killing cron job '.$running_cron['job_code'].' with schedule_id '.$running_cron['schedule_id'].'. Scheduled at '.$running_cron['scheduled_at'].', executed at '.$running_cron['executed_at'].' with time interval of '.$interval.' seconds.', 'autosync');

                try{

                    $cron->setStatus($cron::STATUS_ERROR);
                    $cron->setFinishedAt(date('Y-m-d H:i:s'));
                    $cron->setMessages('Sales Layer job terminated by Autosync verification check due to time limit.');
                    $cron->save();

                }catch(\Exception $e){

                    $this->debbug('## Error. Exception killing cron job '.$running_cron['schedule_id'].': '.print_r($e->getMessage(),1), 'autosync');

                }
         
            }

        }

    }

    /**
     * Function to check and synchronize Sales Layer connectors with auto-synchronization enabled.
     * @return void
     */
    public function auto_sync_connectors(){

        $this->loadConfigParameters();
        $this->load_magento_variables();

        $this->debbug("==== AUTOSync INIT ".date('Y-m-d H:i:s')." ====", 'autosync');
        $this->debbug("==== Synccatalog version: ". $this->moduleVersion ." ====", 'autosync');
        $this->debbug("==== Magento version: ". $this->productMetadata->getVersion() . " - " . $this->productMetadata->getEdition() ." ====", 'autosync');

        $this->delete_sl_logs_since_days();
        $this->check_sync_data_crons();

        $this->sl_time_ini_auto_sync_process = microtime(1);
        
        $items_processing = $this->connection->query(" SELECT count(*) as count FROM ".$this->saleslayer_syncdata_table)->fetch();

        if (isset($items_processing['count']) && $items_processing['count'] > 0){

            $this->debbug("There are still ".$items_processing['count']." items processing, wait until is finished and synchronize again.", 'autosync');
           
        }else{

            try {

                $all_connectors = $this->getConnectors();
                
                $now = strtotime('now');
                
                if (!empty($all_connectors)){

                    $connectors_to_check = array();

                    foreach ($all_connectors as $idx_conn => $connector) {

                        if ($connector['auto_sync'] > 0){
                            
                            $connector_last_sync = $connector['last_sync'];
                            $connector_last_sync_unix = strtotime($connector_last_sync);
                            
                            $unix_to_update = $now - ($connector['auto_sync'] * 3600);
                            
                            if ($connector_last_sync_unix == ''){

                                $connector['unix_to_update'] = $unix_to_update;
                                $connectors_to_check[] = $connector;

                            }else{
                                
                                if ($connector['auto_sync'] >= 24){
                                    
                                    $unix_to_update_hour = mktime($connector['auto_sync_hour'],0,0,date('m', $unix_to_update),date('d', $unix_to_update),date('Y', $unix_to_update));
                                    
                                    if ($connector_last_sync_unix < $unix_to_update_hour){
                                    
                                        $connector['unix_to_update'] = $unix_to_update_hour;
                                        $connectors_to_check[] = $connector;

                                    }

                                }else if ($connector_last_sync_unix < $unix_to_update){

                                    $connector['unix_to_update'] = $unix_to_update;
                                    $connectors_to_check[] = $connector;

                                }

                            }

                        }

                    }

                    if ($connectors_to_check){

                        uasort($connectors_to_check, array($this, 'sort_by_unix_to_update'));

                        foreach ($connectors_to_check as $connector) {

                            if ($connector['auto_sync'] >= 24){

                                $last_sync_time = mktime($connector['auto_sync_hour'],0,0,date('m', $now),date('d', $now),date('Y', $now));
                                $last_sync = date('Y-m-d H:i:s', $last_sync_time);
                            
                            }else{
                            
                                $last_sync = date('Y-m-d H:i:s');
                            
                            }

                            $connector_id = $connector['connector_id'];

                            $this->debbug("Connector to auto-synchronize: " . $connector_id, 'autosync');

                            $time_ini_cron_sync = microtime(1);

                            $time_random = rand(10, 20);
                            sleep($time_random);
                            $this->debbug("#### time_random: " . $time_random . ' seconds.', 'autosync');
                            
                            $data_return = $this->store_sync_data($connector_id, $last_sync);
                            
                            $this->debbug("#### time_cron_sync: " . (microtime(1) - $time_ini_cron_sync - $time_random) . ' seconds.', 'autosync');

                            
                            if (is_array($data_return)){

                                //If the connector result has data we break process so it can sync.
                                if (!empty($data_return)){ 

                                    break;

                                }

                                //If the connector result data is empty we continue to the next connector.

                            }else{

                                //If the connector result is not an array must be an error message..
                                $this->debbug($data_return, 'autosync');

                            }

                            
                        }

                    }else{

                        $this->debbug("Currently there aren't connectors to synchronize.", 'autosync');

                    }
              
                }else{

                    $this->debbug("There aren't any configured connectors.", 'autosync');

                }
            } catch (\Exception $e) {

                $this->debbug('## Error. Autosync process: '.$e->getMessage(), 'autosync');

            }

        }

        $this->debbug('##### time_all_autosync_process: '.(microtime(1) - $this->sl_time_ini_auto_sync_process).' seconds.', 'autosync');

        $this->debbug("==== AUTOSync END ====", 'autosync');

    }

    /**
     * Function to delete SL logs since X days
     * @return void
     */
    private function delete_sl_logs_since_days(){

        if (in_array($this->delete_sl_logs_since_days, array('', null, 0))) return false;

        $time_ini_delete_sl_logs = microtime(1);
        
        $log_folder_files = scandir($this->sl_logs_path);

        if (!empty($log_folder_files)){

            if (($key = array_search('.', $log_folder_files)) !== false) unset($log_folder_files[$key]);
            if (($key = array_search('..', $log_folder_files)) !== false) unset($log_folder_files[$key]);
            
            if (!empty($log_folder_files)){

                $filters_replace = array('/(_error_debbug_log_saleslayer_)/', '/(_debbug_log_saleslayer_timers_)/', '/(_debbug_log_saleslayer_auto_sync_)/', '/(_debbug_log_saleslayer_sync_data_)/', '/(_debbug_log_saleslayer_)/', '/(.dat)/');

                foreach ($log_folder_files as $log_folder_file) {
                    
                    if (strpos($log_folder_file, '_debbug_log_saleslayer_') !== false){
                        
                        $file_date = preg_replace($filters_replace, '', $log_folder_file);            
                        $is_valid_date = (bool)strtotime($file_date);

                        if ($is_valid_date){

                            if (strtotime($file_date) < strtotime('-'.$this->delete_sl_logs_since_days.' days')){

                                $file_path = $this->sl_logs_path.$log_folder_file;

                                if (file_exists($file_path)){

                                    $this->debbug('Deleting SL log: '.$log_folder_file.' for being older than '.$this->delete_sl_logs_since_days.' days.');
                                    unlink($file_path);

                                }

                            }

                        }

                    }

                }
                
            }

        }

        // $this->debbug('##### time_delete_sl_logs: '.(microtime(1) - $time_ini_delete_sl_logs).' seconds');

    }

}