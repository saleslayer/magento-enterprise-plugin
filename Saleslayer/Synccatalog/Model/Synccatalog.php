<?php
namespace Saleslayer\Synccatalog\Model;

/**
 * Synccatalog Model
 **/

use Magento\Framework\Model\Context as context;
use Magento\Framework\Registry as registry;
use Magento\Framework\Model\ResourceModel\AbstractResource as resource;
use Magento\Framework\Data\Collection\AbstractDb as resourceCollection;
use Magento\Framework\Filesystem\DirectoryList  as directoryListFilesystem;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Catalog\Model\Category as categoryModel;
use Magento\Catalog\Model\Product as productModel;
use Magento\Catalog\Api\ProductRepositoryInterface as productRepository;
use Magento\Eav\Model\Entity\Attribute as attribute;
use Magento\Eav\Model\Entity\Attribute\Set as attribute_set;
use Magento\Catalog\Api\ProductAttributeManagementInterface as productAttributeManagementInterface;
use Magento\Indexer\Model\Indexer as indexer;
use Magento\Framework\App\ResourceConnection as resourceConnection;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\Collection as collectionOption;
use Magento\Cron\Model\Schedule as cronSchedule;
use Magento\Framework\App\Config\ScopeConfigInterface as scopeConfigInterface;
use Magento\CatalogUrlRewrite\Model\CategoryUrlPathGenerator as categoryUrlPathGenerator;
use Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator as productUrlPathGenerator;
use Magento\CatalogInventory\Model\Configuration as catalogInventoryConfiguration;
use Magento\Framework\App\DeploymentConfig as deploymentConfig;
use Magento\Eav\Model\Config as eavConfig;
use Magento\Framework\App\Cache\TypeListInterface as typeListInterface;
use Magento\Framework\App\ProductMetadataInterface as productMetadata;
use Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGenerator;
use Magento\Catalog\Model\Product\Attribute\Backend\Media\ImageEntryConverter;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\Framework\Config\ConfigOptionsListConstants;
use Magento\Catalog\Model\Product\Attribute\Source\Countryofmanufacture as countryOfManufacture;
use Magento\Catalog\Model\Category\Attribute\Source\Layout as layoutSource;
use Saleslayer\Synccatalog\Model\SalesLayerConn as SalesLayerConn;
use Saleslayer\Synccatalog\Helper\Data as synccatalogDataHelper;
use Saleslayer\Synccatalog\Helper\Config as synccatalogConfigHelper;
use \Zend_Db_Expr as Expr;

class Synccatalog extends \Magento\Framework\Model\AbstractModel
{    
    protected $synccatalogDataHelper;
    protected $synccatalogConfigHelper;
    protected $categoryModel;
    protected $productModel;
    protected $attribute;
    protected $attribute_set;
    protected $productAttributeManagementInterface;
    protected $indexer;
    protected $resourceConnection;
    protected $collectionOption;
    protected $cronSchedule;
    protected $scopeConfigInterface;
    protected $categoryUrlPathGenerator;
    protected $productUrlPathGenerator;
    protected $catalogInventoryConfiguration;
    protected $deploymentConfig;
    protected $eavConfig;
    protected $typeListInterface;
    protected $productMetadata;
    protected $countryOfManufacture;
    protected $layoutSource;
    protected $salesLayerConn;
    protected $connection;
    protected $directoryListFilesystem;
    protected $_productRepository;
    
    const sl_API_version    = '1.17';
    const sl_connector_type = 'CN_MAGNT';
    const sl_data           = 'Synccatalog_data';
    const sl_connectorid    = 'Synccatalog_connectorid';
    const sl_secretkey      = 'Synccatalog_secretkey';
    const sl_rememberme     = 'Synccatalog_rememberme';
    const sl_time           = 'Synccatalog_response_time';
    const sl_def_lang       = 'Synccatalog_response_default_language';
    const sl_lang           = 'Synccatalog_response_language';
    const sl_lang_used      = 'Synccatalog_response_languages_used';
    const sl_conn_schema    = 'Synccatalog_response_connector_schema';
    const sl_data_schema    = 'Synccatalog_response_data_schema';
    const sl_api_version    = 'Synccatalog_response_api_version';
    const sl_action         = 'Synccatalog_response_action';
    const sl_default_cat_id = 'Synccatalog_default_category_id';
    
    protected $sl_DEBBUG         = 0;
    protected $sl_time_ini_process = '';
    // protected $manage_indexers = 1;
    protected $avoid_images_updates = 0;
    protected $sync_data_hour_from = 0;
    protected $sync_data_hour_until = 0;
    protected $format_type_creation = 'simple';
    protected $delete_sl_logs_since_days = 0;

    protected $tablePrefix                          = null;
    protected $comp_id;
    protected $default_category_id;
    protected $avoid_stock_update;
    protected $sl_language;
    protected $sl_data_schema;
    protected $processing_connector_id;

    protected $category_field_id                    = 'id';
    protected $category_field_catalogue_parent_id   = 'catalogue_parent_id';
    protected $category_field_name                  = 'section_name';
    protected $category_field_url_key               = 'section_url_key';
    protected $category_field_description           = 'section_description';
    protected $category_field_image                 = 'section_image';
    protected $category_field_meta_title            = 'section_meta_title';
    protected $category_field_meta_keywords         = 'section_meta_keywords';
    protected $category_field_meta_description      = 'section_meta_description';
    protected $category_field_active                = 'section_active';
    protected $category_field_page_layout           = 'section_page_layout';
    protected $category_field_is_anchor             = 'section_is_anchor';
    protected $category_path_base                   = BP.'/pub/media/catalog/category/';
    protected $category_images_sizes                = [];
    protected $category_is_anchor                   = 0;
    protected $category_page_layout                 = '1column';
    protected $layout_options                       = [];

    protected $categories_collection                = [];
    protected $saleslayer_root_category_id          = '';

    protected $default_attribute_set_id;
    
    protected $attributes_options_collection        = [];
    protected $products_collection                  = [];
    protected $products_collection_skus             = [];
    protected $products_collection_names            = [];
    protected $product_field_id                     = 'id';
    protected $product_field_catalogue_id           = 'catalogue_id';
    protected $product_field_name                   = 'product_name';
    protected $product_field_description            = 'product_description';
    protected $product_field_description_short      = 'product_description_short';
    protected $product_field_price                  = 'product_price';
    protected $product_field_special_price          = 'product_special_price';
    protected $product_field_special_from_date      = 'product_special_from_date';
    protected $product_field_special_to_date        = 'product_special_to_date';
    protected $product_field_image                  = 'product_image';
    protected $product_field_sku                    = 'sku';
    protected $product_field_qty                    = 'qty';
    protected $product_field_inventory_backorders   = 'product_inventory_backorders';
    protected $product_field_inventory_min_sale_qty = 'product_inventory_min_sale_qty';
    protected $product_field_inventory_max_sale_qty = 'product_inventory_max_sale_qty';
    







    /* protected $product_field_out_of_stock_qty       = 'out_of_stock_qty';
    protected $product_field_inventory_use_config_min_qty     = 'product_inventory_use_config_min_qty';
    protected $product_field_inventory_use_config_manage_stock= 'product_inventory_use_config_manage_stock';
    protected $product_field_inventory_min_qty                = 'product_inventory_min_qty'; */








    protected $product_field_meta_title             = 'product_meta_title';
    protected $product_field_meta_keywords          = 'product_meta_keywords';
    protected $product_field_meta_description       = 'product_meta_description';
    protected $product_field_length                 = 'product_length';
    protected $product_field_width                  = 'product_width';
    protected $product_field_height                 = 'product_height';
    protected $product_field_weight                 = 'product_weight';
    protected $product_field_status                 = 'product_status';
    protected $product_field_visibility             = 'product_visibility';
    protected $product_field_related_references     = 'related_products_references';
    protected $product_field_crosssell_references   = 'crosssell_products_references';
    protected $product_field_upsell_references      = 'upsell_products_references';
    protected $product_field_attribute_set_id       = 'attribute_set_id';
    protected $product_field_tax_class_id           = 'product_tax_class_id';
    protected $product_field_country_of_manufacture = 'product_country_of_manufacture';
    protected $product_field_website                = 'product_website';
    protected $product_path_base                    = BP.'/pub/media/catalog/product/';
    protected $product_images_sizes                 = [];
    protected $products_previous_categories;

    protected $main_image_extension                 = '';
    protected $product_additional_fields            = [];
    protected $product_additional_fields_images     = [];
    protected $grouping_ref_field_linked            = 0;
    protected $existing_links_data                  = [];
    protected $item_image_type                      = 'product';

    protected $format_images_sizes                  = [];
    protected $format_field_id                      = 'id';
    protected $format_field_products_id             = 'products_id';
    protected $format_field_sku                     = 'format_sku';
    protected $format_field_name                    = 'format_name';
    protected $format_field_price                   = 'format_price';
    protected $format_field_special_price           = 'format_special_price';
    protected $format_field_special_from_date       = 'format_special_from_date';
    protected $format_field_special_to_date         = 'format_special_to_date';
    protected $format_field_quantity                = 'format_quantity';
    protected $format_field_inventory_backorders    = 'format_inventory_backorders';
    protected $format_field_inventory_min_sale_qty  = 'format_inventory_min_sale_qty';
    protected $format_field_inventory_max_sale_qty  = 'format_inventory_max_sale_qty';











    /* protected $format_field_out_of_stock_qty        = 'out_of_stock_qty';
    protected $format_field_inventory_use_config_min_qty      = 'format_inventory_use_config_min_qty';
    protected $format_field_inventory_use_config_manage_stock = 'format_inventory_use_config_manage_stock';
    protected $format_field_inventory_min_qty                 = 'format_inventory_min_qty'; */











    protected $format_field_image                   = 'format_image';
    protected $format_field_tax_class_id            = 'format_tax_class_id';
    protected $format_field_country_of_manufacture  = 'format_country_of_manufacture';
    protected $format_field_visibility              = 'format_visibility';
    protected $format_field_website                 = 'format_website';

    protected $all_store_view_ids                   = [];
    protected $store_view_ids                       = [];
    protected $website_ids                          = [];
    protected $format_configurable_attributes       = [];

    protected $category_enabled_attribute_is_global = false;
    protected $product_enabled_attribute_is_global  = false;

    protected $product_type_simple                  = 'simple';
    protected $product_type_grouped                 = 'grouped';
    protected $product_type_configurable            = 'configurable';
    protected $product_type_virtual                 = 'virtual';
    protected $product_type_downloadable            = 'downloadable';
    protected $status_enabled                       = 1;
    protected $status_disabled                      = 2;
    protected $visibility_not_visible               = 1;
    protected $visibility_in_catalog                = 2;
    protected $visibility_in_search                 = 3;
    protected $visibility_both                      = 4;
    protected $category_entity                      = 'catalog_category';
    protected $product_entity                       = 'catalog_product';
    protected $scope_global                         = 1;
    protected $product_link_type_grouped_db         = '';
    protected $product_link_type_related_db         = '';
    protected $product_link_type_upsell_db          = '';
    protected $product_link_type_crosssell_db       = '';
    protected $config_manage_stock                  = 0;
    protected $config_default_product_tax_class     = 0;
    protected $config_notify_stock_qty              = 0;
    protected $config_catalog_product_flat          = 0;
    protected $config_catalog_category_flat         = 0;

    protected $config_backorders                    = 0;
    protected $backorders_no                        = 0;
    protected $backorders_yes_nonotify              = 1;
    protected $backorders_yes_notify                = 2;
    protected $config_min_sale_qty                  = 1;
    protected $config_max_sale_qty                  = 10000;

    protected $products_not_synced                  = [];
    protected $deleted_stored_categories_ids        = [];

    protected $media_field_names                    = [];

    protected $sql_to_insert                        = [];
    protected $sql_to_insert_limit                  = 1;
    protected $storage_process_errors               = [];

    protected $saleslayer_multiconn_table           = 'saleslayer_synccatalog_multiconn';
    protected $saleslayer_syncdata_table            = 'saleslayer_synccatalog_syncdata';
    protected $saleslayer_syncdata_flag_table       = 'saleslayer_synccatalog_syncdata_flag';
    protected $sl_multiconn_table_data;
 
    protected $catalog_category_product_table       = 'catalog_category_product';

    protected $category_saleslayer_id_attribute;
    protected $category_saleslayer_id_attribute_backend_type;
    protected $category_saleslayer_comp_id_attribute;
    protected $category_saleslayer_comp_id_attribute_backend_type;
    protected $product_saleslayer_id_attribute;
    protected $product_saleslayer_id_attribute_backend_type;
    protected $product_saleslayer_comp_id_attribute;
    protected $product_saleslayer_comp_id_attribute_backend_type;
    protected $product_saleslayer_format_id_attribute;
    protected $product_saleslayer_format_id_attribute_backend_type;

    protected $category_entity_type_id;
    protected $product_entity_type_id;

    protected $mg_category_id                       = null;
    protected $mg_category_current_row_id           = null;
    protected $mg_category_row_ids                  = [];
    protected $mg_category_level;
    protected $mg_parent_category_id                = null;
    protected $mg_parent_category_current_row_id    = null;
    protected $mg_parent_category_row_ids           = [];
    protected $stored_url_files_sizes               = [];

    protected $category_created                     = false;
    protected $processed_global_attributes;
    protected $inexistent_attributes                = [];

    protected $product_created                      = false;
    protected $mg_product_id                        = null;
    protected $mg_product_current_row_id            = null;
    protected $mg_product_row_ids                   = null;
    protected $mg_product_attribute_set_id          = null;
    protected $sl_product_mg_category_ids           = [];
    protected $format_created                       = false;
    protected $mg_format_id                         = null;
    protected $mg_format_current_row_id             = null;
    protected $mg_format_row_ids                    = null;
    protected $format_additional_fields             = [];

    protected $sl_logs_path                         = BP.'/var/log/sl_logs/';
    protected $sl_logs_folder_checked               = false;

    protected $tax_class_collection_loaded          = false;
    protected $tax_class_collection                 = [];

    protected $websites_collection_loaded           = false;
    protected $websites_collection                  = [];

    protected $mg_version                           = '';
    protected $mg_edition                           = '';
    protected $mg_tables_23                         = [];

    protected $test_sync_all                        = false;
    protected $clean_main_debug_file                = false;

    protected $tables_identifiers                   = [];

    protected $moduleVersion = null;

    /**
     * Function __construct
     * @param context                             $context                             \Magento\Framework\Model\Context
     * @param registry                            $registry                            \Magento\Framework\Registry
     * @param SalesLayerConn                      $salesLayerConn                      Saleslayer\Synccatalog\Model\SalesLayerConn
     * @param synccatalogDataHelper               $synccatalogDataHelper               Saleslayer\Synccatalog\Helper\Data
     * @param directoryListFilesystem             $directoryListFilesystem             \Magento\Framework\Filesystem\DirectoryList
     * @param categoryModel                       $categoryModel                       \Magento\Catalog\Model\Category
     * @param productModel                        $productModel                        \Magento\Catalog\Model\Product
     * @param attribute                           $attribute                           \Magento\Eav\Model\Entity\Attribute
     * @param attribute_set                       $attribute_set                       \Magento\Eav\Model\Entity\Attribute\Set
     * @param productAttributeManagementInterface $productAttributeManagementInterface \Magento\Catalog\Api\ProductAttributeManagementInterface
     * @param indexer                             $indexer                             \Magento\Indexer\Model\Indexer
     * @param resourceConnection                  $resourceConnection                  \Magento\Framework\App\ResourceConnection
     * @param collectionOption                    $collectionOption                    \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\Collection
     * @param cronSchedule                        $cronSchedule                        \Magento\Cron\Model\Schedule
     * @param scopeConfigInterface                $scopeConfigInterface                \Magento\Framework\App\Config\ScopeConfigInterface
     * @param categoryUrlPathGenerator            $categoryUrlPathGenerator            \Magento\CatalogUrlRewrite\Model\CategoryUrlPathGenerator
     * @param productUrlPathGenerator             $productUrlPathGenerator             \Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator
     * @param catalogInventoryConfiguration       $catalogInventoryConfiguration       \Magento\CatalogInventory\Model\Configuration
     * @param deploymentConfig                    $deploymentConfig                    \Magento\Framework\App\DeploymentConfig
     * @param eavConfig                           $eavConfig                           \Magento\Eav\Model\Config
     * @param typeListInterface                   $typeListInterface                   \Magento\Framework\App\Cache\TypeListInterface
     * @param productMetadata                     $productMetadata                     \Magento\Framework\App\ProductMetadataInterface
     * @param countryOfManufacture                $countryOfManufacture                \Magento\Catalog\Model\Product\Attribute\Source\Countryofmanufacture
     * @param layoutSource                        $layoutSource                        \Magento\Catalog\Model\Category\Attribute\Source\Layout
     * @param resource|null                       $resource                            \Magento\Framework\Model\ResourceModel\AbstractResource
     * @param resourceCollection|null             $resourceCollection                  \Magento\Framework\Data\Collection\AbstractDb
     * @param productRepository                   $productRepository                   \Magento\Catalog\Api\ProductRepositoryInterface
     * 
     * @param array                               $data                                
     */
    public function __construct(
        context $context,
        registry $registry,
        SalesLayerConn $salesLayerConn,
        synccatalogDataHelper $synccatalogDataHelper,
        synccatalogConfigHelper $synccatalogConfigHelper,
        directoryListFilesystem  $directoryListFilesystem,
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
        productRepository $productRepository,
        resource $resource = null,
        resourceCollection $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);

        $this->salesLayerConn                           = $salesLayerConn;
        $this->synccatalogDataHelper                    = $synccatalogDataHelper;
        $this->synccatalogConfigHelper                  = $synccatalogConfigHelper;
        $this->directoryListFilesystem                  = $directoryListFilesystem;
        $this->categoryModel                            = $categoryModel;
        $this->productModel                             = $productModel;
	    $this->_productRepository                       = $productRepository;
        $this->attribute                                = $attribute;
        $this->attribute_set                            = $attribute_set;
        $this->productAttributeManagementInterface      = $productAttributeManagementInterface;
        $this->indexer                                  = $indexer;
        $this->resourceConnection                       = $resourceConnection;
        $this->collectionOption                         = $collectionOption;
        $this->cronSchedule                             = $cronSchedule;
        $this->scopeConfigInterface                     = $scopeConfigInterface;
        $this->categoryUrlPathGenerator                 = $categoryUrlPathGenerator;
        $this->productUrlPathGenerator                  = $productUrlPathGenerator;
        $this->catalogInventoryConfiguration            = $catalogInventoryConfiguration;
        $this->deploymentConfig                         = $deploymentConfig;
        $this->eavConfig                                = $eavConfig;
        $this->typeListInterface                        = $typeListInterface;
        $this->productMetadata                          = $productMetadata;
        $this->countryOfManufacture                     = $countryOfManufacture;
        $this->layoutSource                             = $layoutSource;
        $this->connection                               = $this->resourceConnection->getConnection();
        $this->saleslayer_multiconn_table               = $this->resourceConnection->getTableName($this->saleslayer_multiconn_table);
        $this->saleslayer_syncdata_table                = $this->resourceConnection->getTableName($this->saleslayer_syncdata_table);
        $this->saleslayer_syncdata_flag_table           = $this->resourceConnection->getTableName($this->saleslayer_syncdata_flag_table);
        $this->catalog_category_product_table           = $this->resourceConnection->getTableName($this->catalog_category_product_table);

        $this->moduleVersion = $this->connection->fetchAll("SELECT schema_version FROM `setup_module` WHERE (`module` = 'Saleslayer_Synccatalog') LIMIT 1;")[0]['schema_version'] ?? '';
    }

    /**
     * Function to initialize resource model
     *
     * @return void
     */
    protected function _construct(){

        $this->_init('Saleslayer\Synccatalog\Model\ResourceModel\Synccatalog');
        $this->sl_time_ini_process = microtime(1);

    }

    /**
     * Function to initialize config parameters
     *
     * @return void
     */
    public function loadConfigParameters(){

        $this->sl_DEBBUG = $this->synccatalogConfigHelper->getDebugerLevel();
        $this->sql_to_insert_limit = $this->synccatalogConfigHelper->getSqlToInsertLimit();
        // $this->manage_indexers = $this->synccatalogConfigHelper->getManageIndexers();
        $this->avoid_images_updates = $this->synccatalogConfigHelper->getAvoidImagesUpdates();
        $this->sync_data_hour_from = $this->synccatalogConfigHelper->getSyncDataHourFrom();
        $this->sync_data_hour_until = $this->synccatalogConfigHelper->getSyncDataHourUntil();
        $this->format_type_creation = $this->synccatalogConfigHelper->getFormatTypeCreation();
        $this->delete_sl_logs_since_days = $this->synccatalogConfigHelper->getDeleteSLLogsSinceDays();

    }

    /**
     * Function to debbug into a Sales Layer log.
     * @param string $msg       message to save
     * @param string $type      type of message to save
     * @param int $seconds      seconds for timer debbug
     * @return void
     */
    public function debbug($msg, $type = '', $seconds = null){
        
        if (!$this->sl_logs_folder_checked){

            $this->sl_logs_path = $this->directoryListFilesystem->getPath('log').'/sl_logs/';

            if (!file_exists($this->sl_logs_path)) {
                
                mkdir($this->sl_logs_path, 0777, true);
            
            }

            $this->sl_logs_folder_checked = true;

        }

        if ($this->sl_DEBBUG > 0){

            $error_write = false;
            if (strpos($msg, '## Error.') !== false){
                $error_write = true;
                $error_file = $this->sl_logs_path.'_error_debbug_log_saleslayer_'.date('Y-m-d').'.dat';
            }

            switch ($type) {
                case 'timer':
                    $file = $this->sl_logs_path.'_debbug_log_saleslayer_timers_'.date('Y-m-d').'.dat';

                    if (null !== $seconds){

                        // if ($seconds > 0.10) $msg = 'Notice Time - '.$msg;
                        // if ($seconds > 0.50) $msg = 'Warning Time! - '.$msg;
                        // if ($seconds > 1) $msg = 'ALERT TIME!! - '.$msg;
                        // if ($seconds > 3) $msg = 'CRITICAL TIME!!! - '.$msg;

                        $msg .= $seconds.' seconds.';

                    }else{

                        $msg = 'ERROR - NULL SECONDS on timer debug!!! - '.$msg;

                    }

                    break;

                case 'autosync':
                    $file = $this->sl_logs_path.'_debbug_log_saleslayer_auto_sync_'.date('Y-m-d').'.dat';
                    break;

                case 'syncdata':
                    $file = $this->sl_logs_path.'_debbug_log_saleslayer_sync_data_'.date('Y-m-d').'.dat';
                    break;

                default:
                    $file = $this->sl_logs_path.'_debbug_log_saleslayer_'.date('Y-m-d').'.dat';
                    break;
            }

            $new_file = false;
            if (!file_exists($file)){ $new_file = true; }

            if ($this->sl_DEBBUG > 1){

                $mem = sprintf("%05.2f", (memory_get_usage(true)/1024)/1024);

                $pid = getmypid();

                $time_end_process = round(microtime(true) - $this->sl_time_ini_process);

                $srv = 'NonEx';

                if (function_exists('sys_getloadavg')) {
                    
                    $srv = sys_getloadavg();
                    
                    if (is_array($srv) && isset($srv[0])){

                        $srv = $srv[0];

                    }
                    
                }
               
                $msg = "pid:{$pid} - mem:{$mem} - time:{$time_end_process} - srv:{$srv} - $msg";
            
            }

            file_put_contents($file, "$msg\r\n", FILE_APPEND);

            if ($new_file){ chmod($file, 0777); }

            if ($error_write){

                $new_error_file = false;
                
                if (!file_exists($error_file)){ $new_error_file = true; }

                file_put_contents($error_file, "$msg\r\n", FILE_APPEND);

                if ($new_error_file){ chmod($error_file, 0777); }

            }

        }

        
    }

    /**
     * Function to update a connector's field value.
     * @param string   $connector_id                   Sales Layer connector id
     * @param string   $field_name                     connector field name
     * @return string   $field_value                    field value
     */
    private function get_conn_field($connector_id, $field_name) {

        if (null === $connector_id || $connector_id === '') {
        
            $this->debbug('## Error. Invalid Sales Layer Connector ID.');
            throw new \InvalidArgumentException('Invalid Sales Layer Connector ID.');
            
        }else{
        
            $config_record = $this->load($connector_id, 'connector_id');

            if (!$config_record) {
        
                $this->debbug('## Error. Sales Layer master data corrupted.');
                throw new \InvalidArgumentException('Sales Layer master data corrupted.');
        
            }

            $conn_data = $config_record->getData();

            $field_value = '';

            switch ($field_name) {
                case 'languages':
                    
                    $field_value = explode(',', $conn_data[$field_name]);
                    $field_value = reset($field_value);

                    if (!$field_value) { $field_value = $conn_data['default_language']; }

                    break;
                case 'last_update':

                    (isset($conn_data[$field_name]) && $conn_data[$field_name] != '0000-00-00 00:00:00') ? $field_value = $conn_data[$field_name] : $field_value = null;

                    break;
                case 'avoid_stock_update':

                    $field_value = $conn_data[$field_name];
                    if ($field_value != '1'){ $field_value = '0'; }

                    break;
                case 'category_is_anchor':
                    
                    $field_value = $conn_data[$field_name];
                    if ($field_value != '1'){ $field_value = '0'; }

                    break;
                case 'default_cat_id':

                    $field_value = $conn_data['default_cat_id'];
                    
                    $category_core_data = $this->get_category_core_data($field_value);

                    if (null === $category_core_data){

                        //If the default category does not exist, we set Sales Layer root category and update the connector.
                        if ($this->saleslayer_root_category_id != ''){
                                                        
                            $field_value = $this->saleslayer_root_category_id; 
                            $config_record->setDefaultCatId($field_value);
                            $config_record->save();

                        }

                    }

                    if (null === $field_value || $field_value == ''){                

                        $this->debbug('## Error. Sales Layer master data corrupted. No default category.');
                        throw new \InvalidArgumentException('Sales Layer master data corrupted. No default category.');

                    }

                    break;
                default:

                    if (isset($conn_data[$field_name])){ $field_value = $conn_data[$field_name]; }
                    break;

            }

            if ($this->sl_DEBBUG > 1) $this->debbug('Connector field: '.$field_name.' - field_value: '.$field_value);
            return $field_value;
        
        }

    }

    /**
     * Function to update a connector's field value.
     * @param string   $connector_id               Sales Layer connector id
     * @param string   $field_name                 connector field name
     * @param string   $field_value                connector field value
     * @return  boolean                             result of update
     */
    public function update_conn_field($connector_id, $field_name, $field_value) {

        $this->loadConfigParameters();

        if (in_array($field_name, array('id', 'connector_id', 'secret_key', 'comp_id', 'default_language', 'last_update', 'languages', 'updater_version', ''))){ 
            return false; 
        }

        if (in_array($field_name, array('store_view_ids', 'format_configurable_attributes')) && $field_value !== null){ 
            $field_value = json_encode($field_value); 
        }

        $config_record   = $this->load($connector_id, 'connector_id');
        $conn_data      = $config_record->getData();
        
        $boolean_fields = array('avoid_stock_update' => 1,'products_previous_categories' => 0, 'category_is_anchor' => 1);
        
        if (isset($boolean_fields[$field_name])){
            
            if (null === $field_value || $field_value != $boolean_fields[$field_name]){
            
                ($boolean_fields[$field_name] == 0) ? $field_value = 1 : $field_value = 0;

            }

        }
       
        $mandatory_fields = array('default_cat_id' => 0, 'auto_sync' => 0, 'category_is_anchor' => null); 

        if (isset($mandatory_fields[$field_name]) && (($field_name == 'auto_sync' && (null === $field_value || $field_value === '')) || ($field_name != 'auto_sync' && (null === $field_value || $field_value == '')))){
            
            $this->debbug('## Error. Updating connector: $connector_id field: $field_name field_value: $field_value - Empty value for mandatory field.');
            return false;

        }

        if ($conn_data[$field_name] != $field_value){

            try{

                $config_record->setData($field_name, $field_value);
                $config_record->save();
                if ($this->sl_DEBBUG > 1) $this->debbug('Connector field: $field_name updated to: $field_value');

            }catch(\Exception $e){
            
                $this->debbug('## Error. Updating connector: $connector_id field: $field_name to: $field_value - '.$e->getMessage());
                return false;

            }

        }

        return true;

    }

    /**
     * Function to load the connector's store view ids.
     * @param string $connector_id             Sales Layer connector id
     * @return void
     */
    private function loadStoreViewIds($connector_id) {
        
        $store_view_ids = $this->get_conn_field($connector_id, 'store_view_ids');
        
        if (null !== $store_view_ids){

            $this->store_view_ids = json_decode($store_view_ids, true);

            //$all_stores = $this->getAllStores();

            //If only 'All store views' is set, we load all stores to process.
            /* if (count($this->store_view_ids) == 1 && reset($this->store_view_ids) == 0){

                foreach ($all_stores as $store) {
                    
                    if ($store['store_id'] != 0){

                        $this->store_view_ids[] = $store['store_id'];

                    }

                }

            }

            asort($this->store_view_ids); */

            $websites = $this->getWebsitesIdsByStoreIds($this->store_view_ids);

            foreach ($websites as $website) {
                $this->website_ids[] = $website['website_id'];
            }

            //foreach ($this->store_view_ids as $store_view_id) {
                
                /* if (isset($all_stores[$store_view_id]) && $all_stores[$store_view_id]['website_id'] != 0 && !isset($this->website_ids[$all_stores[$store_view_id]['website_id']])){

                    $this->website_ids[$all_stores[$store_view_id]['website_id']] = 0;

                } */

                //$this->website_ids[] = $this->store_view_ids[$store_view_id]['website_id'];

            //}

            //$this->website_ids = array_keys($this->website_ids);

            if ($this->sl_DEBBUG > 1) {
                $this->debbug("Configuration store view ids: ".print_r($this->store_view_ids,1));
            }

            if ($this->sl_DEBBUG > 1) {
                $this->debbug("Configuration website ids: ".print_r($this->website_ids,1));
            }

        }

    }

    private function getWebsiteStoreviewRelations(array $website_ids = []): array
    {
        $statement = "SELECT
        store.website_id AS website_id,
        store_website.code AS website_code,
        store_website.name AS website_name,
        store_group.code AS store_code,
        store_group.name AS store_name,
        store.store_id AS storeview_id,
        store.code AS storeview_code,
        store.name AS storeview_name
        FROM store
        INNER JOIN store_group
        ON store.group_id = store_group.group_id
        INNER JOIN store_website
        ON store.website_id = store_website.website_id";

        if (! empty($website_ids)) {
            $statement .= ' WHERE store.website_id IN (' . implode(',', $website_ids) . ');';
        }

        return $this->connection->fetchAll($statement);
    }

    private function getWebsitesIdsByStoreIds(array $storeIds = []): array
    {
        $statement = "SELECT
        store.website_id AS website_id,
        store_website.code AS website_code,
        store_website.name AS website_name
        FROM store
        INNER JOIN store_website
        ON store.website_id = store_website.website_id";

        if (! empty($storeIds)) {
            if (count($storeIds) === 1 && ((int) $storeIds[0]) === 0) {
                $statement .= " WHERE NOT store_website.code = 'admin' GROUP BY store.website_id;";
            } else {
                $statement .= " WHERE store.store_id IN (" . implode(",", $storeIds) . ") GROUP BY store.website_id;";
            }
        }

        return $this->connection->fetchAll($statement);
    }

    /**
     * Function to load all store view ids.
     * @return void
     */
    private function loadAllStoreViewIds(){

        $this->all_store_view_ids = array(0);

        $all_stores = $this->getAllStores();
       
        if (!empty($all_stores)){

            $this->all_store_view_ids = array_unique(array_merge($this->all_store_view_ids, array_keys($all_stores)));

        }

    }

    /**
     * Function to get all stores from database
     * @return array $all_stores            all stores in database
     */
    private function getAllStores(){

        $all_stores = [];

        $store_table = $this->getTable('store');

        if (null !== $store_table){

            $all_stores_data = $this->connection->fetchAll(
                $this->connection->select()
                    ->from(
                        [$store_table],
                        ['store_id', 'website_id']
                    )
            );

            if (!empty($all_stores_data)){

                foreach ($all_stores_data as $store_data) {
                    
                    $all_stores[$store_data['store_id']] = $store_data;

                }

            }

        }

        return $all_stores;

    }

    /**
     * Function to load websites collection into a class variable.
     * @return void
     */
    private function loadWebsitesCollection(){

        if (!$this->websites_collection_loaded){
            
            $store_website_table = $this->getTable('store_website');
    
            if (null !== $store_website_table){
                
                $all_websites_data = $this->connection->fetchAll(
                    $this->connection->select()
                    ->from(
                        [$store_website_table],
                        ['website_id', 'code', 'name']
                        )
                    ->where('website_id <> ?', 0)
                    );
                    
                if (!empty($all_websites_data)){
    
                    foreach ($all_websites_data as $website_data) {
                        
                        $this->websites_collection[$website_data['website_id']] = $website_data;
    
                    }
    
                }
            
            }
    
            $this->websites_collection_loaded = true;
        
        }
        
    }

    /**
     * Function to get the connector's format configurable attributes.
     * @param string $connector_id             Sales Layer connector id
     * @return void
     */
    private function load_format_configurable_attributes ($connector_id) {

        $format_configurable_attributes = $this->get_conn_field($connector_id, 'format_configurable_attributes');

        if (null !== $format_configurable_attributes) {
        
            $this->format_configurable_attributes = json_decode($format_configurable_attributes,1);
            
            if ($this->sl_DEBBUG > 1) $this->debbug("Format configurable attributes ids: ".print_r($this->format_configurable_attributes,1));
        
        }

    }

    /**
     * Function to get the connector's products previous categories option.
     * @param string $connector_id             Sales Layer connector id
     * @return void
     */
    private function load_products_previous_categories ($connector_id) {

        $products_previous_categories = $this->get_conn_field($connector_id, 'products_previous_categories');

        if (null === $products_previous_categories){

            $products_previous_categories = 0;
        
        }

        $this->products_previous_categories = $products_previous_categories;
        
        if ($this->sl_DEBBUG > 1) $this->debbug("Products previous categories option: ".print_r($this->products_previous_categories,1));

    }

    /**
     * Function to load Magento variables into local class variables.
     * @return void
     */
    public function load_magento_variables(){

        $this->category_entity_type_id          = $this->eavConfig->getEntityType($this->categoryModel::ENTITY)->getEntityTypeId();
        $this->product_entity_type_id           = $this->eavConfig->getEntityType($this->productModel::ENTITY)->getEntityTypeId();
        $this->product_type_simple              = \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE;
        $this->product_type_configurable        = \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE;
        $this->product_type_grouped             = \Magento\GroupedProduct\Model\Product\Type\Grouped::TYPE_CODE;
        $this->product_type_virtual             = \Magento\Catalog\Model\Product\Type::TYPE_VIRTUAL;
        $this->product_type_downloadable        = \Magento\Downloadable\Model\Product\Type::TYPE_DOWNLOADABLE;
        $this->status_enabled                   = \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED;
        $this->status_disabled                  = \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_DISABLED;
        $this->visibility_both                  = \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH;
        $this->visibility_not_visible           = \Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE;
        $this->visibility_in_catalog            = \Magento\Catalog\Model\Product\Visibility::VISIBILITY_IN_CATALOG;
        $this->visibility_in_search             = \Magento\Catalog\Model\Product\Visibility::VISIBILITY_IN_SEARCH;
        $this->category_entity                  = \Magento\Catalog\Model\Category::ENTITY;
        $this->product_entity                   = \Magento\Catalog\Model\Product::ENTITY;
        $this->scope_global                     = \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL;
        $this->product_link_type_grouped_db     = \Magento\GroupedProduct\Model\ResourceModel\Product\Link::LINK_TYPE_GROUPED;
        $this->product_link_type_related_db     = \Magento\Catalog\Model\Product\Link::LINK_TYPE_RELATED;
        $this->product_link_type_upsell_db      = \Magento\Catalog\Model\Product\Link::LINK_TYPE_UPSELL;
        $this->product_link_type_crosssell_db   = \Magento\Catalog\Model\Product\Link::LINK_TYPE_CROSSSELL;
        $this->config_manage_stock              = $this->scopeConfigInterface->getValue('cataloginventory/item_options/manage_stock');
        $this->config_default_product_tax_class = $this->scopeConfigInterface->getValue('tax/classes/default_product_tax_class');
        $this->config_notify_stock_qty          = $this->scopeConfigInterface->getValue('cataloginventory/item_options/notify_stock_qty');
        $this->config_catalog_category_flat     = $this->scopeConfigInterface->getValue('catalog/frontend/flat_catalog_category');
        $this->config_catalog_product_flat      = $this->scopeConfigInterface->getValue('catalog/frontend/flat_catalog_product');
        $this->mg_version                       = $this->productMetadata->getVersion();
        $this->mg_edition                       = trim(strtolower($this->productMetadata->getEdition()));

        $this->backorders_no                    = \Magento\CatalogInventory\Model\Stock::BACKORDERS_NO;
        $this->backorders_yes_nonotify          = \Magento\CatalogInventory\Model\Stock::BACKORDERS_YES_NONOTIFY;
        $this->backorders_yes_notify            = \Magento\CatalogInventory\Model\Stock::BACKORDERS_YES_NOTIFY;
        $this->config_backorders                = $this->scopeConfigInterface->getValue('cataloginventory/item_options/backorders');
        $this->config_min_sale_qty              = json_decode($this->scopeConfigInterface->getValue('cataloginventory/item_options/min_sale_qty'),1);

        if (is_array($this->config_min_sale_qty)){

            if (isset($this->config_min_sale_qty[\Magento\Customer\Model\Group::CUST_GROUP_ALL])){

                $this->config_min_sale_qty = $this->config_min_sale_qty[\Magento\Customer\Model\Group::CUST_GROUP_ALL];

            }else{

                $this->config_min_sale_qty = reset($this->config_min_sale_qty);

            }

        }

        $this->config_max_sale_qty              = $this->scopeConfigInterface->getValue('cataloginventory/item_options/max_sale_qty');
    
        if (version_compare($this->mg_version, '2.3.0') < 0) {
      
            $this->mg_tables_23[] = 'inventory_source_item';

        }
      
    }

    /**
     * Function to login into Sales Layer with the connector credentials.
     * @param string $connector_id             Sales Layer connector id
     * @param string $secretKey                Sales Layer connector secret key
     * @return timestamp $get_response_time     response time from the connection
     */
    public function login_saleslayer ($connector_id, $secretKey) {

        $this->debbug('Process login...');

        $this->loadSaleslayerRootCategory();
        $this->load_magento_variables();

        $slconn = $this->connect_saleslayer($connector_id, $secretKey);

        if (!is_object($slconn)){

            return $slconn;

        }

        $configRecord = $this->load($connector_id, 'connector_id');
        $data = $configRecord->getData();
        
        if (!isset($data['id']) || null === $data['id']) {
            $this->createConn($connector_id, $secretKey);
        }

        $this->updateConn($connector_id, $slconn);

        $get_response_time = $slconn->get_response_time('timestamp');

        return 'login_ok';

    }

    /**
     * Function to create the connector in the Sales Layer table.
     * @param string $connector_id             Sales Layer connector id
     * @param string $secretKey                Sales Layer connector secret key
     * @return void
     */
    private function createConn($connector_id, $secretKey){

        $category_id = $this->saleslayer_root_category_id;
        if (null === $category_id || $category_id == ''){ $category_id = 1; }
        
        $this->addData(array('connector_id' => $connector_id, 'secret_key' => $secretKey, 'default_cat_id' => $category_id, 'store_view_ids' => 0, null));
        $this->save();
    }

    /**
     * Function to update the connector data in the Sales Layer table.
     * @param string $connector_id             Sales Layer connector id
     * @param array $slconn                    Sales Layer connector object
     * @param timestamp $last_update           last update from the connector
     * @return void
     */
    private function updateConn($connector_id, $slconn, $last_update = null){

        if ($this->sl_DEBBUG > 1) $this->debbug("Updating connector...");
        if ($this->sl_DEBBUG > 1) $this->debbug("Last update...".$last_update);
        
        $configRecord = $this->load($connector_id, 'connector_id');
        
        if ($slconn->get_response_languages_used()) {

            $get_response_default_language = $slconn->get_response_default_language();
            $get_response_languages_used   = $slconn->get_response_languages_used();
            $get_response_languages_used   = implode(',', $get_response_languages_used);

            $configRecord->setDefault_language($get_response_default_language);
            $configRecord->setLanguages       ($get_response_languages_used);
        }
        
        $configRecord->setComp_id($slconn->get_response_company_ID());
        
        $get_response_api_version = $slconn->get_response_api_version();
        
        $configRecord->setUpdater_version($get_response_api_version);

        if (null !== $last_update) { $configRecord->setLast_update($last_update); }

        $configRecord->save();

    }

    /**
     * Function to get the data schema from the connector.
     * @param array $slconn                    Sales Layer connector object
     * @return array $schema                    schema data
     */
    private function get_data_schema ($slconn) {

        $info = $slconn->get_response_table_information();
        $schema = [];

        if (is_array($info) && !empty($info)){

            foreach ($info as $table => $data) {

                if (isset($data['table_joins'])) {

                    $schema[$table]['table_joins'] = $data['table_joins'];
                }

                if (isset($data['fields'])) {

                    foreach ($data['fields'] as $field => $struc) {

                        if (isset($struc['has_multilingual']) and $struc['has_multilingual']){

                            if (!isset($schema[$table][$field])) {

                                $schema[$table]['fields'][$struc['basename']] = array(

                                    'type'              => $struc['type'],
                                    'has_multilingual'  => 1,
                                    'multilingual_name' => $field
                                );

                                if ($struc['type']=='image') {

                                    $schema[$table]['fields'][$struc['basename']]['image_sizes'] = $struc['image_sizes'];

                                }

                                if (isset($struc['origin'])){

                                    $schema[$table]['fields'][$struc['basename']]['origin'] = $struc['origin'];

                                }

                            }

                        } else {

                            $schema[$table]['fields'][$field] = $struc;

                        }

                    }

                }

            }

            if ($this->sl_DEBBUG > 2) $this->debbug('Schema: '.print_r($schema,1));
            
            return $schema;
        
        }else{

            return false;

        }

    }

    /**
     * Function to connect to Sales Layer with the connector credentials.
     * @param string $connector_id             Sales Layer connector id
     * @param string $secretKey                Sales Layer connector secret key
     * @return array $slconn                    Sales Layer connector object
     */
    private function connect_saleslayer ($connector_id, $secretKey) {

        $slconn = new SalesLayerConn ($connector_id, $secretKey);

        $slconn->set_API_version(self::sl_API_version);

        $last_date_update = $this->get_conn_field($connector_id, 'last_update') ?? '';
        
        $this->debbug('Connecting with API... (last update: '.$last_date_update.')');

        if ($last_date_update !== null && preg_match('/^\d{4}-/', $last_date_update)) $last_date_update = strtotime($last_date_update);

        $slconn->set_group_multicategory(true);
        
        if ($this->test_sync_all){
        
            $slconn->get_info();
        
        }else{
        
            $slconn->get_info($last_date_update);
        
        }

        if ($slconn->has_response_error()) {
            return $slconn->get_response_error_message();
        }

        if ($response_connector_schema = $slconn->get_response_connector_schema()) {

            $response_connector_type = $response_connector_schema['connector_type'];

            if ($response_connector_type != self::sl_connector_type) {
                return 'Invalid Sales Layer connector type';
            }
        }

        return $slconn;
    }

    /**
     * Function to store connector's data to synchronize.
     * @param string $connector_id              Sales Layer connector id
     * @param  datetime $last_sync              Last connector synchronization datetime.
     * @return array $arrayReturn               array with stored synchronization data
     */
    public function store_sync_data ($connector_id, $last_sync = null) {
        
        $time_ini_data = microtime(1);

        $this->loadConfigParameters();
        $this->load_magento_variables();

        if ($this->clean_main_debug_file) file_put_contents($this->sl_logs_path.'_debbug_log_saleslayer_'.date('Y-m-d').'.dat', "");

        if( $items_processing = $this->isProcessing() ){
            $this->debbug('### time_store_sync_data: ', 'timer', (microtime(1) - $time_ini_data));
            return "There are still ".$items_processing['count']." items processing, wait until is finished and synchronize again.";
        }
        
        $this->debbug("\r\n==== Store Sync Data INIT ====\r\n");
        
        $this->updateLastSync($last_sync, $connector_id);

        $slconn = $this->connect_saleslayer($connector_id, $this->get_conn_field($connector_id, 'secret_key'));

        if (!is_object($slconn)){

            $this->debbug("\r\n==== Store Sync Data END ====\r\n");

            return $slconn;

        }

        $this->updateConn($connector_id, $slconn, $slconn->get_response_time());
        
        $sync_params = [];
        $sync_params['conn_params']['comp_id'] = $slconn->get_response_company_ID();
        $sync_params['conn_params']['connector_id'] = $connector_id;
        
        $get_response_table_data  = $slconn->get_response_table_data();
        $get_response_time        = $slconn->get_response_time('timestamp');

        $this->getResponseLanguages($slconn);
        
        $get_data_schema = $this->get_data_schema($slconn);

        if (!$get_data_schema){

            $this->debbug("\r\n==== Store Sync Data END ====\r\n");

            return "The information is being prepared by the API. Please try again in a few minutes.";

        }

        $this->sl_data_schema = json_encode($get_data_schema);
        unset($get_data_schema);

        $this->debbug('Update new date: '.$get_response_time.' ('.date('Y-m-d H:i:s', $get_response_time).')');

        if ($get_response_table_data) {

            $time_process = microtime(1);
            $arrayReturn = $this->processResponse($get_response_table_data, $connector_id, $sync_params); 
            if ($this->sl_DEBBUG > 1) $this->debbug('##### time_all_store_process: ', 'timer', (microtime(1) - $time_process));

        }else{

            $arrayReturn = [];

        }

        
        $this->debbug('### time_store_sync_data: ', 'timer', (microtime(1) - $time_ini_data));
        $this->debbug("\r\n==== Store Sync Data END ====\r\n");

        return $arrayReturn;
    
    }

    /**
     * Function to insert sync data into the database.
     * @param boolean $force_insert             forces sql to be inserted
     * @return void
     */
    private function insert_syncdata_sql($force_insert = false){

        if (!empty($this->sql_to_insert) && (count($this->sql_to_insert) >= $this->sql_to_insert_limit || $force_insert)){

            try{

                $this->connection->insertMultiple(
                    $this->saleslayer_syncdata_table,
                    $this->sql_to_insert
                );

            }catch(\Exception $e){

                $this->debbug('## Error. Insert syncdata SQL message: '.$e->getMessage());
                $this->debbug('## Error. Insert syncdata SQL items: '.print_r($this->sql_to_insert,1));

            }

            $this->sql_to_insert = [];
            
        }

    }

    /**
    * Function to store Sales Layer categories data.
    * @param array $arrayCatalogue                 categories data to organize
    * @return array $categories_data_to_store       categories data to store
    */
    private function prepare_category_data_to_store ($arrayCatalogue) {

        $data_schema              = json_decode($this->sl_data_schema, 1);
        $schema                   = $data_schema['catalogue'];

        if (!isset($schema['fields'][$this->category_field_name])){

            $error_message = 'Category name field must be defined in order to synchronize information.';
            $this->debbug('## Error. '.$error_message);
            $this->storage_process_errors[$this->category_field_name] = $error_message;
            return false;

        }

        $category_data_to_store = [];

        $category_data_to_store['default_category_id'] = $this->default_category_id;
        $category_data_to_store['category_is_anchor'] = $this->category_is_anchor;
        $category_data_to_store['category_page_layout'] = $this->category_page_layout;
        $category_data_to_store['avoid_images_updates'] = $this->avoid_images_updates;

        $this->category_images_sizes = $this->getImgSizes($schema['fields'], $this->category_field_image, 'Category');

        $category_data_to_store['category_fields']['category_images_sizes'] = $this->category_images_sizes;

        $field_names = ['category_field_name',
                        'category_field_description',
                        'category_field_image',
                        'category_field_meta_title',
                        'category_field_meta_keywords',
                        'category_field_meta_description',
                        'category_field_page_layout',
                        'category_field_is_anchor'
                    ];

        $channel_fields = [];

        foreach ($field_names as $field_name){

            $data_store_field = $this->setDataStoreFields($field_name, $schema['fields']);

            if (isset($schema['fields'][$data_store_field]['origin']) && $schema['fields'][$data_store_field]['origin'] == 'channel'){

                $channel_fields[] = $data_store_field;

            } 
            
            $category_data_to_store['category_fields'][$field_name] = $data_store_field;
        
        }

        if (isset($this->media_field_names['catalogue']) && !empty($this->media_field_names['catalogue'])){

            $category_data_to_store['catalogue_media_field_names'] = $this->media_field_names['catalogue'];

        }

        if (!empty($arrayCatalogue)){

            if (!empty($channel_fields)){

                foreach ($arrayCatalogue as $keyCat => $category) {
                    
                    $arrayCatalogue[$keyCat]['data'] = array_diff_key($category['data'], array_flip($channel_fields));

                }

            }
            
            unset($channel_fields);

            $time_ini_reorganize = microtime(1);
            $arrayCatalogue = $this->reorganizeCategories($arrayCatalogue);
            if ($this->sl_DEBBUG > 1) $this->debbug('### time_reorganize_categories: ', 'timer', (microtime(1) - $time_ini_reorganize));
            
            $category_data_to_store['category_data'] = $arrayCatalogue;

        }

        return $category_data_to_store;

    }

    /**
    * Function to store Sales Layer products data.
    * @param array $arrayProducts              products data to organize
    * @return array $products_data_to_store     products data to store
    */
    private function prepare_product_data_to_store($arrayProducts){
        
        $fixed_product_fields = [
            'ID',
            'ID_catalogue',
            $this->product_field_name,
            $this->product_field_description,
            $this->product_field_description_short,
            $this->product_field_price,
            $this->product_field_image,
            'image_sizes',
            $this->product_field_sku,
            $this->product_field_qty,
            $this->product_field_attribute_set_id,
            $this->product_field_meta_title,
            $this->product_field_meta_keywords,
            $this->product_field_meta_description,
            $this->product_field_length,
            $this->product_field_width,
            $this->product_field_height,
            $this->product_field_weight,
            $this->product_field_related_references,
            $this->product_field_crosssell_references,
            $this->product_field_upsell_references,
            $this->product_field_inventory_backorders,
            $this->product_field_inventory_min_sale_qty,
            $this->product_field_inventory_max_sale_qty,
            $this->product_field_status,
            $this->product_field_visibility,
            $this->product_field_tax_class_id,
            $this->product_field_country_of_manufacture,
            $this->product_field_special_price,
            $this->product_field_special_from_date,
            $this->product_field_special_to_date,
            $this->product_field_website
        ];

        $product_data_to_store = [];

        $product_data_to_store['avoid_stock_update'] = $this->avoid_stock_update;
        $product_data_to_store['products_previous_categories'] = $this->products_previous_categories;
        $product_data_to_store['avoid_images_updates'] = $this->avoid_images_updates;
        $product_data_to_store['attribute_set_collection'] = $this->getAttributeSetCollection();
        
        $default_attribute_set_id = $this->getAttributeSetId($product_data_to_store['attribute_set_collection']);
        
        $product_data_to_store['default_attribute_set_id'] = $default_attribute_set_id;

        $data_schema = json_decode($this->sl_data_schema, 1);
        $schema      = $data_schema['products'];
        unset($data_schema);

        if (!isset($schema['fields'][$this->product_field_name])){

            $this->storage_process_errors[$this->product_field_name] = 'Product name field must be defined in order to synchronize information.';

        }

        if (isset($schema['fields'][strtolower($this->product_field_sku)])){
            $this->product_field_sku = strtolower($this->product_field_sku);
        }else if (isset($schema['fields'][strtoupper($this->product_field_sku)])){
            $this->product_field_sku = strtoupper($this->product_field_sku);
        }

        //Check of sku field case sensitive
        if (!isset($schema['fields'][$this->product_field_sku])){

            $this->storage_process_errors[$this->product_field_sku] = 'Product SKU field must be defined in order to synchronize information.';

        }

        if (!isset($schema['fields'][$this->product_field_price])){

            $this->storage_process_errors[$this->product_field_price] = 'Product price field must be defined in order to synchronize information.';

        }

        $this->product_images_sizes = $this->getImgSizes($schema['fields'], $this->product_field_image, 'Product');

        $product_data_to_store['product_fields']['product_images_sizes'] = $this->product_images_sizes;
        $product_data_to_store['product_fields']['main_image_extension'] = $this->product_images_sizes[0];

        $field_names = [
            'product_field_name',
            'product_field_sku',
            'product_field_description',
            'product_field_description_short',
            'product_field_price',
            'product_field_image',
            'product_field_attribute_set_id',
            'product_field_meta_title',
            'product_field_meta_keywords',
            'product_field_meta_description',
            'product_field_length',
            'product_field_width',
            'product_field_height',
            'product_field_weight',
            'product_field_related_references',
            'product_field_crosssell_references',
            'product_field_upsell_references',
            'product_field_inventory_backorders',
            'product_field_inventory_min_sale_qty',
            'product_field_inventory_max_sale_qty',
            /* 'product_field_out_of_stock_qty',
            'product_field_inventory_use_config_min_qty',
            'product_field_inventory_use_config_manage_stock',
            'product_field_inventory_min_qty', */
            'product_field_status',
            'product_field_visibility',
            'product_field_tax_class_id',
            'product_field_country_of_manufacture',
            'product_field_special_price',
            'product_field_special_from_date',
            'product_field_special_to_date',
            'product_field_website'
        ];
    
        if (!empty($schema['fields'][$this->product_field_qty])) {

            $field_names[] = 'product_field_qty';

        }

        $channel_fields = [];

        foreach ($field_names as $field_name){

            $data_store_field = $this->setDataStoreFields($field_name, $schema['fields']);

            if (isset($schema['fields'][$data_store_field]['origin']) && $schema['fields'][$data_store_field]['origin'] == 'channel'){

                $channel_fields[] = $data_store_field;

                if ($field_name == 'product_field_price'){
                
                    $this->storage_process_errors[$this->product_field_price] = 'Product price field must be assigned in SL Connector in order to synchronize information.';
                    break;
                
                }

            }
            
            $product_data_to_store['product_fields'][$field_name] = $data_store_field;
        
        }

        if (!empty($this->storage_process_errors)){

            foreach ($this->storage_process_errors as $error_message){

                $this->debbug('## Error. '.$error_message);

            }

            return false;

        }

        if (!empty($schema['fields'])){
        
            $grouping_ref_fields = $this->getGroupingRefs($schema);

            $grouping_ref_field_linked = 0;

            if (!empty($grouping_ref_fields)){

                $fixed_product_fields = array_merge($fixed_product_fields, $grouping_ref_fields);

                foreach ($grouping_ref_fields as $grouping_ref_field) {
                    
                    if (isset($schema['fields'][$grouping_ref_field]['origin']) && $schema['fields'][$grouping_ref_field]['origin'] == 'channel'){

                        $channel_fields[] = $grouping_ref_field;

                    }else{

                        $grouping_ref_field_linked = 1;

                    }

                }

            }

            $product_data_to_store['product_fields']['grouping_ref_field_linked'] = $grouping_ref_field_linked;

            $grouping_qty_fields = $this->getGroupingQty($schema);

            if (!empty($grouping_qty_fields)){

                $fixed_product_fields = array_merge($fixed_product_fields, $grouping_qty_fields);

                foreach ($grouping_qty_fields as $grouping_qty_field) {
                    
                    if (isset($schema['fields'][$grouping_qty_field]['origin']) && $schema['fields'][$grouping_qty_field]['origin'] == 'channel'){

                        $channel_fields[] = $grouping_qty_field;

                    }

                }

            }

            $product_data_to_store['product_additional_fields'] = $this->setAdditionalFields($schema['fields'], $fixed_product_fields);          
            
        }

        if ($this->sl_DEBBUG > 1 and isset($product_data_to_store['product_additional_fields']) && count($product_data_to_store['product_additional_fields'])){

            $this->debbug("Product additional fields:\n".print_r($product_data_to_store['product_additional_fields'],1));

        }

        if (isset($this->media_field_names['products']) && !empty($this->media_field_names['products'])){

            $product_data_to_store['products_media_field_names'] = $this->media_field_names['products'];

        }
        
        if (!empty($arrayProducts)){

            if (!empty($channel_fields)){

                foreach ($arrayProducts as $keyProd => $product) {
                    
                    $arrayProducts[$keyProd]['data'] = array_diff_key($product['data'], array_flip($channel_fields));

                }

            }
            
            unset($channel_fields);

            $product_data_to_store['product_data'] = $this->checkAttributes($arrayProducts, $product_data_to_store, $default_attribute_set_id);

        }

        return $product_data_to_store;
    }

    /**
    * Function to store Sales Layer product formats data.
    * @param array $arrayFormats               product formats data to organize
    * @return array $product_format_data_to_store     product formats data to store
    */
    private function prepare_product_format_data_to_store($arrayFormats){

        $fixed_format_fields = [
            'ID',
            'ID_products',
            $this->format_field_sku,
            $this->format_field_name,
            $this->format_field_price,
            $this->format_field_quantity,
            $this->format_field_image,
            'image_sizes',
            $this->format_field_tax_class_id,
            $this->format_field_country_of_manufacture,
            $this->format_field_special_price,
            $this->format_field_special_from_date,
            $this->format_field_special_to_date,
            $this->format_field_visibility,
            $this->format_field_inventory_backorders,
            $this->format_field_inventory_min_sale_qty,
            $this->format_field_inventory_max_sale_qty,
            $this->format_field_website
        ];

        $product_format_data_to_store = [];

        $product_format_data_to_store['avoid_stock_update'] = $this->avoid_stock_update;
        $product_format_data_to_store['format_configurable_attributes'] = $this->format_configurable_attributes;
        $product_format_data_to_store['avoid_images_updates'] = $this->avoid_images_updates;

        $data_schema = json_decode($this->sl_data_schema, 1);
        $schema      = $data_schema['product_formats'];
        unset($data_schema);

        //Renombramos campos multiidioma para que los codigos de atributos configurables coincidan con los atributos en MG
        $arrayFormats = $this->organizeTablesIndex($arrayFormats, $schema['fields']);

        if (!isset($schema['fields'][$this->format_field_name])){

            $this->storage_process_errors[$this->format_field_name] = 'Product format name field must be defined in order to synchronize information.';

        }

        $product_format_data_to_store['product_format_fields']['format_field_name'] = $this->format_field_name;

        if (!isset($schema['fields'][$this->format_field_sku])){

            $this->storage_process_errors[$this->format_field_sku] = 'Product format SKU field must be defined in order to synchronize information.';

        }

        if (!isset($schema['fields'][$this->format_field_price])){

            $this->storage_process_errors[$this->format_field_price] = 'Product format price field must be defined in order to synchronize information.';

        }

        $product_format_data_to_store['product_format_fields']['format_field_sku'] = $this->format_field_sku;
        $product_format_data_to_store['product_format_fields']['format_field_price'] = $this->format_field_price;
        $product_format_data_to_store['product_format_fields']['format_field_quantity'] = $this->format_field_quantity;
        $product_format_data_to_store['product_format_fields']['format_field_inventory_backorders'] = $this->format_field_inventory_backorders;
        $product_format_data_to_store['product_format_fields']['format_field_inventory_min_sale_qty'] = $this->format_field_inventory_min_sale_qty;
        $product_format_data_to_store['product_format_fields']['format_field_inventory_max_sale_qty'] = $this->format_field_inventory_max_sale_qty;

        /* $product_format_data_to_store['product_format_fields']['format_field_inventory_use_config_min_qty'] = $this->format_field_inventory_use_config_min_qty;
        $product_format_data_to_store['product_format_fields']['format_field_inventory_use_config_manage_stock'] = $this->format_field_inventory_use_config_manage_stock;
        $product_format_data_to_store['product_format_fields']['format_field_inventory_min_qty'] = $this->format_field_inventory_min_qty; */


        $product_format_data_to_store['product_format_fields']['format_field_image'] = $this->format_field_image;
        $product_format_data_to_store['product_format_fields']['format_field_tax_class_id'] = $this->format_field_tax_class_id;
        $product_format_data_to_store['product_format_fields']['format_field_country_of_manufacture'] = $this->format_field_country_of_manufacture;
        $product_format_data_to_store['product_format_fields']['format_field_special_price'] = $this->format_field_special_price;
        $product_format_data_to_store['product_format_fields']['format_field_special_from_date'] = $this->format_field_special_from_date;
        $product_format_data_to_store['product_format_fields']['format_field_special_to_date'] = $this->format_field_special_to_date;
        $product_format_data_to_store['product_format_fields']['format_field_visibility'] = $this->format_field_visibility;
        $product_format_data_to_store['product_format_fields']['format_field_website'] = $this->format_field_website;

        $this->format_images_sizes = $this->getImgSizes($schema['fields'], $this->format_field_image, 'Product format');

        $product_format_data_to_store['product_format_fields']['format_images_sizes'] = $this->format_images_sizes;
        $product_format_data_to_store['product_format_fields']['main_image_extension'] = $this->format_images_sizes[0];   

        $channel_fields = [];

        if (!empty($schema['fields'])){
           
            foreach ($schema['fields'] as $field_name => $field_props){

                if (!in_array($field_name, $fixed_format_fields)){

                    $product_format_data_to_store['format_additional_fields'][$field_name] = $field_name;

                }

                if (isset($field_props['origin']) && $field_props['origin'] == 'channel'){

                    $channel_fields[] = $field_name;

                    if ($field_name == 'format_price'){
                
                        $this->storage_process_errors[$this->product_field_price] = 'Product format price field must be assigned in SL Connector in order to synchronize information.';
                        break;
                    
                    }

                }

            }

        }

        if (!empty($this->storage_process_errors)){

            foreach ($this->storage_process_errors as $error_message){

                $this->debbug('## Error. '.$error_message);

            }

            return false;

        }

        if ($this->sl_DEBBUG > 1 and isset($product_format_data_to_store['format_additional_fields']) && count($product_format_data_to_store['format_additional_fields'])){

            $this->debbug("Product format additional fields:\n".print_r($product_format_data_to_store['format_additional_fields'],1));

        }

        if (isset($this->media_field_names['product_formats']) && !empty($this->media_field_names['product_formats'])){

            $product_format_data_to_store['product_formats_media_field_names'] = $this->media_field_names['product_formats'];

        }

        if (!empty($arrayFormats) && !empty($channel_fields)){

            foreach ($arrayFormats as $keyForm => $format) {
                
                $arrayFormats[$keyForm]['data'] = array_diff_key($format['data'], array_flip($channel_fields));

            }    

            unset($channel_fields);

        }

        if (!empty($this->format_configurable_attributes)){

            $arrayFormats = $this->prepareConfigurableAttrs($arrayFormats, $schema);
            

        }

        $product_format_data_to_store['product_format_data'] = $arrayFormats;

        return $product_format_data_to_store;

    }

    /**
     * Function to synchronize Sales Layer stored category.
     * @param array $category              category to synchronize
     * @return string                       category updated or not
     */
    public function sync_stored_category_db($category){

        $this->cleanMGVars();

        if ($this->sl_DEBBUG > 2) $this->debbug('Synchronizing stored category: '.print_r($category,1));

        $time_ini_check_category = microtime(1);
        if ($this->check_category_db($category)){
            $this->debbug('### check_category: ', 'timer', (microtime(1) - $time_ini_check_category));

            $syncCat = true;

            $time_ini_sync_category_core_data = microtime(1);
            if (!$this->sync_category_core_data_db($category)){
                $syncCat = false;
            }
            $this->debbug('### sync_category_core_data: ', 'timer', (microtime(1) - $time_ini_sync_category_core_data));

            if (empty($this->store_view_ids)){

                $this->store_view_ids = array(0);

            }

            if ($syncCat){

                if ($this->category_created === true){

                    $store_view_ids = $this->store_view_ids;
                    if (!in_array(0, $store_view_ids)){ 
                        $store_view_ids[] = 0; 
                        asort($store_view_ids);
                    }

                    $time_ini_sync_category_data_global = microtime(1);
                    $this->sync_category_data_db($category, $store_view_ids);
                    $this->debbug('### time_sync_category_data_global: ', 'timer', (microtime(1) - $time_ini_sync_category_data_global));
                    $this->category_created = false;

                }else{

                    $time_ini_sync_category_data_global = microtime(1);
                    $this->sync_category_data_db($category, $this->store_view_ids);
                    $this->debbug('### time_sync_category_data_global: ', 'timer', (microtime(1) - $time_ini_sync_category_data_global));

                }

            }

            if ($syncCat){
                
                return 'item_updated';

            }

        }

        return 'item_not_updated';

    }

    /**
     * Function to synchronize Sales Layer category data.
     * @param array $category                  category to synchronize
     * @param array $store_view_ids            store view ids to synchronize 
     * @return boolean                          result of category data synchronization
     */
    private function sync_category_data_db($category, $store_view_ids){

        $time_ini_sync_category_prepare_data = microtime(1);

        $sl_id = $category[$this->category_field_id];
        
        if (null === $this->mg_category_id){

            $this->find_saleslayer_category_id_db($sl_id);
        
        }

        $this->debbug(" > Updating category data ID: $sl_id");
        
        if ($this->sl_DEBBUG > 1 && isset($category['data'][$this->category_field_name])) $this->debbug(" Name ({$this->category_field_name}): ".$category['data'][$this->category_field_name]);

        $mg_category_fields = [
            $this->category_field_name => 'name',
            $this->category_field_url_key => 'url_key',
            $this->category_field_meta_title => 'meta_title',
            $this->category_field_meta_keywords => 'meta_keywords',
            $this->category_field_meta_description => 'meta_description',
            $this->category_field_active => 'is_active',
            $this->category_field_description => 'description',
            $this->category_field_image => 'image',
            $this->category_field_page_layout => 'page_layout', 
            $this->category_field_is_anchor => 'is_anchor'
        ];

        $sl_category_data_to_sync = [
            'is_anchor' => $this->category_is_anchor,
            'page_layout' => $this->category_page_layout,
            'include_in_menu' => 1
        ];
        
        $sl_category_image_data_to_sync = [];

        foreach ($mg_category_fields as $sl_category_field => $mg_category_field) {
            
            $time_ini_prepare_field = microtime(1);

            if (isset($category['data'][$sl_category_field])){

                if ($mg_category_field == 'description'){

                    $time_ini_check_html_text = microtime(1);
                    $sl_category_data_to_sync[$mg_category_field] = $this->sl_check_html_text($category['data'][$sl_category_field]);
                    if ($this->sl_DEBBUG > 2) $this->debbug('# time_check_html_text: ', 'timer', (microtime(1) - $time_ini_check_html_text));

                }else if ($mg_category_field == 'is_active' || $mg_category_field == 'is_anchor'){

                    if ($mg_category_field == 'is_anchor' && $category['data'][$sl_category_field] == '') $category['data'][$sl_category_field] = $this->category_is_anchor;
                    $sl_category_field_bool = $this->SLValidateStatusValue($category['data'][$sl_category_field]);
                    
                    if (!$sl_category_field_bool){
            
                        $sl_category_data_to_sync[$mg_category_field] = 0;

                    }else{

                        $sl_category_data_to_sync[$mg_category_field] = 1;

                    }

                }else if ($mg_category_field == 'image'){

                    $sl_category_image_data_to_sync[$mg_category_field] = $category['data'][$sl_category_field];

                }else if ($mg_category_field == 'page_layout'){

                    $sl_category_data_to_sync[$mg_category_field] = $this->SLValidateLayoutValue($category['data'][$sl_category_field]);

                }else{

                    $sl_category_data_to_sync[$mg_category_field] = $category['data'][$sl_category_field];

                }

            }else if ($mg_category_field == 'is_active'){

                $sl_category_data_to_sync['is_active'] = 1;

            }

            if ($this->sl_DEBBUG > 2) $this->debbug('# time_prepare_field: ', 'timer', (microtime(1) - $time_ini_prepare_field));

        }

        if (!isset($sl_category_data_to_sync['url_key']) && isset($sl_category_data_to_sync['name'])){

            $sl_category_data_to_sync['url_key'] = $sl_category_data_to_sync['name'];

        }

        $time_ini_format_url_key = microtime(1);
        $sl_category_data_to_sync['url_key'] = $this->categoryModel->formatUrlKey($sl_category_data_to_sync['url_key']);
        if ($this->sl_DEBBUG > 2) $this->debbug('# time_format_url_key: ', 'timer', (microtime(1) - $time_ini_format_url_key));

        if ($this->sl_DEBBUG > 1) $this->debbug('## sync_category_prepare_data: ', 'timer', (microtime(1) - $time_ini_sync_category_prepare_data));

        $this->debbug(" > SL category data to sync: ".print_r($sl_category_data_to_sync,1));

        foreach ($store_view_ids as $store_view_id) {
            
            $this->debbug(" > In store view id: ".$store_view_id);
            $time_ini_sync_category_store_data = microtime(1);

            foreach ($this->mg_category_row_ids as $mg_category_row_id){
                
                $this->setValues($mg_category_row_id, 'catalog_category_entity', $sl_category_data_to_sync, $this->category_entity_type_id, $store_view_id, true, false, $this->mg_category_row_ids);

            }

            if ($this->sl_DEBBUG > 1) $this->debbug('## sync_category_data store_view_id: '.$store_view_id.': ', 'timer', (microtime(1) - $time_ini_sync_category_store_data));

        }

        // if (!empty($sl_category_image_data_to_sync)){

            // $this->debbug(" > SL category image data to sync: ".print_r($sl_category_image_data_to_sync,1));
            $time_ini_sync_category_image_store_data = microtime(1);

            foreach ($this->mg_category_row_ids as $mg_category_row_id){

                $this->setCategoryImage($mg_category_row_id, 'catalog_category_entity', $sl_category_image_data_to_sync, $this->category_entity_type_id, 0);

            }

            if ($this->sl_DEBBUG > 1) $this->debbug('## sync_category_image_data store_view_id 0: ', 'timer', (microtime(1) - $time_ini_sync_category_image_store_data));

        // }

        $time_ini_sync_category_url_rewrite = microtime(1);
        $this->setCategoryUrlRewrite($store_view_ids);
        if ($this->sl_DEBBUG > 1) $this->debbug('### sync_category_url_rewrite: ', 'timer', (microtime(1) - $time_ini_sync_category_url_rewrite));
        
        return true;

    }

    /**
     * Function to rewrite category url tables.
     * @param array $store_view_ids            store view ids to rewrite url 
     * @return void
     */
    private function setCategoryUrlRewrite($store_view_ids){

        $time_ini_category_url_rewrite = microtime(1);

        $category_table = $this->getTable('catalog_category_entity');

        foreach ($store_view_ids as $store_view_id) {
            
            $time_ini_category_url_rewrite_store = microtime(1);

            $mg_category_fields = array('url_key' => '', 'url_path' => '');
            $mg_category_fields = $this->getValues($this->mg_category_current_row_id, 'catalog_category_entity', $mg_category_fields, $this->category_entity_type_id, $store_view_id);
            
            if (!isset($mg_category_fields['url_key']) || isset($mg_category_fields['url_key']) && $mg_category_fields['url_key'] == ''){

                $this->debbug('## Error. Url Key not found in store: '.$store_view_id.' for category with MG ID: '.$this->mg_category_current_row_id.'. Skipping category url rewrite update.');
                continue;

            }

            if ($this->mg_edition == 'enterprise'){

                $category_data = array('url_key' => $mg_category_fields['url_key'],
                                        'store_id' => $store_view_id,
                                        'level' => $this->mg_category_level);

                $mg_category_entity_id = $this->connection->fetchRow(
                    $this->connection->select()
                        ->from(
                            [$category_table],
                            ['entity_id']
                        )
                        ->where($this->tables_identifiers[$category_table] . ' = ?', $this->mg_category_current_row_id)                        
                        ->limit(1)
                );

                if (!empty($mg_category_entity_id) && isset($mg_category_entity_id['entity_id'])){

                    $category_data['entity_id'] = $mg_category_entity_id['entity_id'];

                }else{

                    $this->debbug('## Error. Entity ID not found in store: '.$store_view_id.' for category with MG row ID: '.$this->mg_category_current_row_id.'. Skipping category url rewrite update.');
                    continue;

                }

                $mg_parent_category_entity_id = $this->connection->fetchRow(
                    $this->connection->select()
                        ->from(
                            [$category_table],
                            ['entity_id']
                        )
                        ->where($this->tables_identifiers[$category_table] . ' = ?', $this->mg_parent_category_current_row_id)
                        ->limit(1)
                );

                if (!empty($mg_parent_category_entity_id) && isset($mg_parent_category_entity_id['entity_id'])){

                    $category_data['parent_id'] = $mg_parent_category_entity_id['entity_id'];

                }else{

                    $this->debbug('## Error. Parent entity ID not found in store: '.$store_view_id.' for category parent with MG row ID: '.$this->mg_parent_category_current_row_id.'. Skipping category url rewrite update.');
                    continue;

                }


            }else{

                $category_data = array('entity_id' => $this->mg_category_id,
                                        'url_key' => $mg_category_fields['url_key'],
                                        'store_id' => $store_view_id,
                                        'parent_id' => $this->mg_parent_category_id,
                                        'level' => $this->mg_category_level);

            }

            $category = $this->categoryModel;
            $category->setData($category_data);

            // if (!isset($mg_category_fields['url_path']) || isset($mg_category_fields['url_path']) && $mg_category_fields['url_path'] == ''){

                $urlPath = $this->categoryUrlPathGenerator->getUrlPath($category);
                $category->setUrlPath($urlPath);

            // }else{

            //     $category->setUrlPath($mg_category_fields['url_path']);
            //     $urlPath = $mg_category_fields['url_path'];

            // }

            if (!$urlPath) {
                
                $this->debbug("## Error. Couldn't generate category url path: ".print_r($category->getData(),1));
                continue;

            }else{

                foreach ($this->mg_category_row_ids as $mg_category_row_id) {
                    
                    $this->setValues($mg_category_row_id, 'catalog_category_entity', array('url_path' => $urlPath) , $this->category_entity_type_id, $store_view_id, true, false, $this->mg_category_row_ids);

                }

            }

            if ($store_view_id == 0){

                continue;

            }

            $requestPath = $this->categoryUrlPathGenerator->getUrlPathWithSuffix(
                $category,
                $store_view_id
            );

            $url_rewrite_table = $this->getTable('url_rewrite');

            $exists = $this->connection->fetchOne(
                $this->connection->select()
                    ->from($url_rewrite_table, new Expr(1))
                    ->where('entity_type = ?', CategoryUrlRewriteGenerator::ENTITY_TYPE)
                    ->where('request_path = ?', $requestPath)
                    ->where('store_id = ?', $category->getStoreId())
                    ->where('is_autogenerated = ?', 1)
                    ->where('entity_id <> ?', $category->getEntityId())
            );

            if ($exists) {

                $category->setUrlKey($category->formatUrlKey($category_data['url_key'] . '-' . $category->getStoreId()));
                $requestPath = $this->categoryUrlPathGenerator->getUrlPathWithSuffix(
                    $category,
                    $category->getStoreId()
                );

                $increment = 0;

                do{

                    $exists = $this->connection->fetchOne(
                        $this->connection->select()
                            ->from($url_rewrite_table, new Expr(1))
                            ->where('entity_type = ?', CategoryUrlRewriteGenerator::ENTITY_TYPE)
                            ->where('request_path = ?', $requestPath)
                            ->where('store_id = ?', $category->getStoreId())
                            ->where('is_autogenerated = ?', 1)
                            ->where('entity_id <> ?', $category->getEntityId())
                    );

                    if ($exists){

                        $category->setUrlKey($category->formatUrlKey($category_data['url_key'] . '-' . $category->getStoreId() . '-' . $increment));
                        $requestPath = $this->categoryUrlPathGenerator->getUrlPathWithSuffix(
                            $category,
                            $category->getStoreId()
                        );
                        $increment++;

                    }

                }while($exists);

            }

            $rewriteId = $this->connection->fetchOne(
                $this->connection->select()
                    ->from($url_rewrite_table, ['url_rewrite_id'])
                    ->where('entity_type = ?', CategoryUrlRewriteGenerator::ENTITY_TYPE)
                    ->where('entity_id = ?', $category->getEntityId())
                    ->where('is_autogenerated = ?', 1)
                    ->where('store_id = ?', $category->getStoreId())
                );

            if ($rewriteId) {
            
                try{
                    
                    $this->connection->update(
                        $url_rewrite_table,
                        ['request_path' => $requestPath],
                        ['url_rewrite_id = ?' => $rewriteId]
                    );

                }catch(\Exception $e){

                    $this->debbug('## Error. Updating category url rewrite. Url path: '.$requestPath.' already exists on a different category: '.$e->getMessage());
                    continue;

                }

            } else {
            
                $data = [
                    'entity_type'      => CategoryUrlRewriteGenerator::ENTITY_TYPE,
                    'entity_id'        => $category->getEntityId(),
                    'request_path'     => $requestPath,
                    'target_path'      => 'catalog/category/view/id/' . $category->getEntityId(),
                    'redirect_type'    => 0,
                    'store_id'         => $category->getStoreId(),
                    'is_autogenerated' => 1
                ];

                try{

                    $this->connection->insertOnDuplicate(
                        $url_rewrite_table,
                        $data,
                        array_keys($data)
                    );
                
                }catch(\Exception $e){

                    $this->debbug('## Error. Inserting category url rewrite. Url path: '.$requestPath.' already exists on a different category: '.$e->getMessage());
                    continue;

                }

            }

            if ($this->sl_DEBBUG > 2) $this->debbug('### time_category_url_rewrite_store: ', 'timer', (microtime(1) - $time_ini_category_url_rewrite_store));

        }

        if ($this->sl_DEBBUG > 2) $this->debbug('### time_category_url_rewrite: ', 'timer', (microtime(1) - $time_ini_category_url_rewrite));

    }

    /**
     * Function to synchronize Sales Layer category core data.
     * @param array $category                   category to synchronize
     * @return boolean                          result of category data synchronization
     */
    private function sync_category_core_data_db($category) {

        $sl_id        = $category[$this->category_field_id];
        $sl_parent_id = $category[$this->category_field_catalogue_parent_id];
        $sl_category_name = '';
        if (isset($category['data'][$this->category_field_name]) && $category['data'][$this->category_field_name] !== ''){
            $sl_category_name = $category['data'][$this->category_field_name].' ';
        }

        $mg_parent_category_path = '';

        if ($sl_parent_id != '0'){

            if (null === $this->mg_parent_category_id){

                $this->find_saleslayer_category_id_db($sl_parent_id, 0, 'parent');
               
            }

            $mg_parent_category_core_data = $this->get_category_core_data($this->mg_parent_category_id);
            $mg_parent_category_path = $mg_parent_category_core_data['path'];
            $mg_parent_category_level = $mg_parent_category_core_data['level'];
        
        }else{

            $mg_parent_category_core_data = $this->get_category_core_data($this->default_category_id);
            $this->mg_parent_category_id = $this->default_category_id;
            $this->mg_parent_category_row_ids = $this->getEntityRowIds($this->mg_parent_category_id, 'category');
            $this->mg_parent_category_current_row_id = $this->getEntityCurrentRowId($this->mg_parent_category_id, 'category');
            // $mg_parent_category_path = '1/'.$this->mg_parent_category_id;
            // $mg_parent_category_level = 0;
            $mg_parent_category_path = $mg_parent_category_core_data['path'];
            $mg_parent_category_level = $mg_parent_category_core_data['level'];

        }

        if (null === $this->mg_category_id) {
            $this->find_saleslayer_category_id_db($sl_id);
        }

        if (null !== $this->mg_category_id) {

            try{

                $category_table = $this->getTable('catalog_category_entity');
                $mg_category_core_data = $this->get_category_core_data($this->mg_category_id);
                $mg_parent_category_path .= '/'.$mg_category_core_data['entity_id'];
                $this->mg_category_level = $mg_category_core_data['level'];

                if ($this->category_created){

                    $position = $this->connection->fetchOne(
                        $this->connection->select()
                            ->from(
                                $category_table,
                                [new Expr('MAX(`position`) + 1')]
                            )
                            ->where('parent_id = ?', $mg_parent_category_core_data['entity_id'])
                            ->group('parent_id')
                    );

                    if (!$position) $position = 0;

                    foreach ($this->mg_category_row_ids as $mg_category_row_id) {

                        $this->connection->update($category_table, ['position' => $position], $this->tables_identifiers[$category_table].' = ' . $mg_category_row_id);

                    }

                }

                $this->debbug(" > Updating category core data ID: $sl_id (parent: $sl_parent_id: $mg_parent_category_path)");

                $refresh_stats = true;

                if ($mg_category_core_data['parent_id'] != $mg_parent_category_core_data['entity_id'] || $mg_category_core_data['path'] != $mg_parent_category_path){ 

                    if (!$this->category_created){

                        $position = $this->connection->fetchOne(
                            $this->connection->select()
                                ->from(
                                    $category_table,
                                    [new Expr('MAX(`position`) + 1')]
                                )
                                ->where('parent_id = ?', $mg_parent_category_core_data['entity_id'])
                                ->group('parent_id')
                        );

                        if (!$position) $position = 0;

                        foreach ($this->mg_category_row_ids as $mg_category_row_id) {

                            $this->connection->update($category_table, ['position' => $position], $this->tables_identifiers[$category_table].' = ' . $mg_category_row_id);

                        }

                    }

                    try{

                        foreach ($this->mg_category_row_ids as $mg_category_row_id) {

                            $this->connection->update($category_table, ['path' => $mg_parent_category_path, 'parent_id' => $mg_parent_category_core_data['entity_id']], $this->tables_identifiers[$category_table].' = ' . $mg_category_row_id); 
                        
                        }

                        $refresh_stats = true;

                    }catch(\Exception $e){

                        $this->debbug('## Error. Reorganizing the category: '.$e->getMessage());

                    }

                    if ($refresh_stats){

                        $parent_categories_ids_to_check = array($this->mg_category_id => 0);
                        
                        do{

                            foreach ($parent_categories_ids_to_check as $parent_category_id_to_check => $nullval) {
                                
                                $incorrect_children_categories_path_sql = " SELECT ch.".$this->tables_identifiers[$category_table]." as child_id,ch.path as old_path, CONCAT ( cp.path, '/', ch.entity_id ) as correct_path, ch.parent_id as child_parent_id, cp.entity_id parent_id ".
                                                                    " , cp.path as parent_path ".
                                                                    " FROM ".$category_table." ch ".
                                                                    " LEFT JOIN ".$category_table." cp ON cp.entity_id = ch.parent_id ".
                                                                    " WHERE CONCAT ( cp.path, '/', ch.entity_id ) != ch.path ".
                                                                    " AND ch.parent_id = ".$parent_category_id_to_check.
                                                                    " ORDER BY cp.level ";

                                $incorrect_children_categories_path = $this->connection->fetchAll($incorrect_children_categories_path_sql);

                                if (!empty($incorrect_children_categories_path)){

                                    foreach ($incorrect_children_categories_path as $incorrect_children_category_path) {
                                        
                                        try{

                                            $this->connection->update($category_table, ['path' => $incorrect_children_category_path['correct_path']], $this->tables_identifiers[$category_table].' = ' . $incorrect_children_category_path['child_id']);

                                        }catch(\Exception $e){

                                            $this->debbug('## Error. Correcting category children path: '.print_r($e->getMessage(),1));

                                        }

                                        if (!isset($parent_categories_ids_to_check[$incorrect_children_category_path['child_id']])){

                                            $parent_categories_ids_to_check[$incorrect_children_category_path['child_id']] = 0;

                                        }

                                    }

                                }

                                if (isset($parent_categories_ids_to_check[$parent_category_id_to_check])){

                                    unset($parent_categories_ids_to_check[$parent_category_id_to_check]);

                                }
                                
                            }

                        }while(!empty($parent_categories_ids_to_check));

                    }

                }

                foreach ($this->mg_parent_category_row_ids as $mg_parent_category_row_id) {
                    
                    $incorrect_level_categories_sql = " SELECT LENGTH(path)-LENGTH(REPLACE(path,'/','')) AS correct_level, `level` ".
                                                    " FROM ".$category_table.
                                                    " WHERE LENGTH(path)-LENGTH(REPLACE(path,'/','')) != `level`".
                                                    " AND ".$this->tables_identifiers[$category_table]." = ".$mg_parent_category_row_id;

                    $incorrect_level_parent_category = $this->connection->fetchRow($incorrect_level_categories_sql);

                    if (!empty($incorrect_level_parent_category)){

                        try{

                            $resultado = $this->connection->update($category_table, ['level' => $incorrect_level_parent_category['correct_level']], $this->tables_identifiers[$category_table]. ' = ' . $mg_parent_category_row_id);

                        }catch(\Exception $e){

                            $this->debbug('## Error. Correcting parent category level: '.print_r($e->getMessage(),1));

                        }

                        $correct_category_level = $incorrect_level_parent_category['correct_level'] + 1;

                    }else{

                        $correct_category_level = $mg_parent_category_level + 1;
                        
                    }

                    if ($mg_category_core_data['level'] != $correct_category_level){

                        try{

                            foreach ($this->mg_category_row_ids as $mg_category_row_id) {
                            
                                $this->connection->update($category_table, ['level' => $correct_category_level], $this->tables_identifiers[$category_table].' = ' . $mg_category_row_id);
                                
                                $mg_category_core_data['level'] = $correct_category_level;

                            }

                            $this->mg_category_level = $correct_category_level;

                        }catch(\Exception $e){

                            $this->debbug('## Error. Correcting category level: '.print_r($e->getMessage(),1));

                        }

                    }else{

                        $incorrect_level_categories_sql = " SELECT LENGTH(path)-LENGTH(REPLACE(path,'/','')) AS correct_level, `level` ".
                                                        " FROM ".$category_table.
                                                        " WHERE LENGTH(path)-LENGTH(REPLACE(path,'/','')) != `level`".
                                                        " AND ".$this->tables_identifiers[$category_table]." = ".$mg_parent_category_row_id;
                                                                                
                        $incorrect_level_category = $this->connection->fetchRow($incorrect_level_categories_sql);

                        if (!empty($incorrect_level_category)){

                            try{

                                $this->connection->update($category_table, ['level' => $incorrect_level_category['correct_level']], $this->tables_identifiers[$category_table].' = ' . $mg_parent_category_row_id);

                            }catch(\Exception $e){

                                $this->debbug('## Error. Correcting category level: '.print_r($e->getMessage(),1));

                            }

                        }

                    }

                }

            } catch (\Exception $e) {
                
                $this->debbug("## Error. Updating core category ".$sl_category_name." with SL ID: ".$sl_id." path data: ".$e->getMessage());
                return false;

            }

            $this->saveMultiConnCategory($sl_id, $sl_category_name);

        }

        return true;

    }

    /**
     * Function to correct categories core data, such as children, path, parent and level.
     * @return void
     */
    public function correct_categories_core_data(){

        $this->loadConfigParameters();
        $this->load_magento_variables();

        if ($this->clean_main_debug_file) file_put_contents($this->sl_logs_path.'_debbug_log_saleslayer_'.date('Y-m-d').'.dat', "");
        
        $this->debbug('exec time: '.print_r(date('Y-m-d H:i:s'),1));

        $this->execute_slyr_load_functions();

        $time_ini_correct_categories = microtime(1);

        if ($this->config_catalog_category_flat == 1){
            
            $time_ini_manage_indexes = microtime(1);
            $this->manageIndexes(array('catalog_category_flat'));
            $this->debbug('## time_manage_indexes: ', 'timer', (microtime(1) - $time_ini_manage_indexes));

        }

        $category_table = $this->getTable('catalog_category_entity');
        
        $incorrect_children_categories_path_sql = " SELECT ch.".$this->tables_identifiers[$category_table]." as child_id,ch.path as old_path, CONCAT ( cp.path, '/', ch.entity_id ) as correct_path, ch.parent_id as child_parent_id, cp.entity_id parent_id ".
                                            " , cp.path as parent_path ".
                                            " FROM ".$category_table." ch ".
                                            " LEFT JOIN ".$category_table." cp ON cp.entity_id = ch.parent_id ".
                                            " WHERE CONCAT ( cp.path, '/', ch.entity_id ) != ch.path ".
                                            " ORDER BY cp.level ";

        $incorrect_children_categories_path = $this->connection->fetchAll($incorrect_children_categories_path_sql);

        if (!empty($incorrect_children_categories_path)){
            
            $parent_categories_ids_to_check = [];
            $first_iteration = true;

            foreach ($incorrect_children_categories_path as $incorrect_children_category_path) {

                $parent_categories_ids_to_check[$incorrect_children_category_path['child_id']] = 0;

            }
            
            do{

                foreach ($parent_categories_ids_to_check as $parent_category_id_to_check => $nullval) {
                    
                    if (!$first_iteration){

                        $incorrect_children_categories_path_sql = " SELECT ch.".$this->tables_identifiers[$category_table]." as child_id,ch.path as old_path, CONCAT ( cp.path, '/', ch.entity_id ) as correct_path, ch.parent_id as child_parent_id, cp.entity_id parent_id ".
                                                            " , cp.path as parent_path ".
                                                            " FROM ".$category_table." ch ".
                                                            " LEFT JOIN ".$category_table." cp ON cp.entity_id = ch.parent_id ".
                                                            " WHERE CONCAT ( cp.path, '/', ch.entity_id ) != ch.path ".
                                                            " AND ch.parent_id = ".$parent_category_id_to_check.
                                                            " ORDER BY cp.level ";

                        $incorrect_children_categories_path = $this->connection->fetchAll($incorrect_children_categories_path_sql);

                    }else{

                        $first_iteration = false;

                    }

                    if (!empty($incorrect_children_categories_path)){

                        foreach ($incorrect_children_categories_path as $incorrect_children_category_path) {
                            
                            try{

                                $this->connection->update($category_table, ['path' => $incorrect_children_category_path['correct_path']], $this->tables_identifiers[$category_table].' = ' . $incorrect_children_category_path['child_id']);

                            }catch(\Exception $e){

                                $this->debbug('## Error. Correcting category children path: '.print_r($e->getMessage(),1));

                            }

                            if (!isset($parent_categories_ids_to_check[$incorrect_children_category_path['child_id']])){

                                $parent_categories_ids_to_check[$incorrect_children_category_path['child_id']] = 0;

                            }

                        }

                    }

                    if (isset($parent_categories_ids_to_check[$parent_category_id_to_check])){

                        unset($parent_categories_ids_to_check[$parent_category_id_to_check]);

                    }
                    
                }

            }while(!empty($parent_categories_ids_to_check));

        }

        $incorrect_categories_children_sql = " SELECT p.".$this->tables_identifiers[$category_table].", p.path, p.children_count, COUNT(c.entity_id) AS correct_children_count, ".
                                            " COUNT(c.entity_id) - p.children_count AS child_diff ".
                                            " FROM ".$category_table." p ".
                                            " LEFT JOIN ".$category_table." c ON c.path LIKE CONCAT(p.path,'/%') ".
                                            " WHERE 1 ".
                                            " GROUP BY p.".$this->tables_identifiers[$category_table].
                                            " HAVING correct_children_count != p.children_count";

        $incorrect_categories_children = $this->connection->fetchAll($incorrect_categories_children_sql);
        
        if (!empty($incorrect_categories_children)){

            foreach ($incorrect_categories_children as $incorrect_category_children) {
                
                try{

                    $this->connection->update($category_table, ['children_count' => $incorrect_category_children['correct_children_count']], $this->tables_identifiers[$category_table].' = ' . $incorrect_category_children[$this->tables_identifiers[$category_table]]);

                }catch(\Exception $e){

                    $this->debbug('## Error. Correcting category children: '.print_r($e->getMessage(),1));

                }

            }

        }

        $incorrect_level_categories_sql = " SELECT ".$this->tables_identifiers[$category_table].", LENGTH(path)-LENGTH(REPLACE(path,'/','')) AS correct_level, `level` ".
                                        " FROM ".$category_table.
                                        " WHERE LENGTH(path)-LENGTH(REPLACE(path,'/','')) != `level`";
                                                                            
        $incorrect_level_categories = $this->connection->fetchAll($incorrect_level_categories_sql);
                                                                            
        if (!empty($incorrect_level_categories)){

            foreach ($incorrect_level_categories as $incorrect_level_category) {
                
                try{

                    $this->connection->update($category_table, ['level' => $incorrect_level_category['correct_level']], $this->tables_identifiers[$category_table].' = ' . $incorrect_level_category[$this->tables_identifiers[$category_table]]);

                }catch(\Exception $e){

                    $this->debbug('## Error. Correcting category level: '.print_r($e->getMessage(),1));

                }

            }

        }

        $this->debbug('#### time_correct_categories: ', 'timer', (microtime(1) - $time_ini_correct_categories));

    }

    /**
     * Function to reorganize category parent ids after two synchronize tries.
     * @param array $category                   category to reorganize parent ids
     * @return array $category                  category with parent ids reorganized
     */
    public function reorganize_category_parent_ids_db($category){

        if (!is_array($category[$this->category_field_catalogue_parent_id])){
                                           
            $category_parent_ids = array($category[$this->category_field_catalogue_parent_id]);
           
        }else{
           
            $category_parent_ids = $category[$this->category_field_catalogue_parent_id];
           
        }

        $has_any_parent = false;

        foreach ($category_parent_ids as $category_parent_id) {
               
            if ($category_parent_id == 0 || null !== $this->find_saleslayer_category_id_db($category_parent_id, 0, 'parent')){
        
                $has_any_parent = true;
                break;

            } 

        }

        if (!$has_any_parent){

            $category[$this->category_field_catalogue_parent_id] = 0;
            
        }

        return $category; 

    }

    /**
     * Function to synchronize Sales Layer stored product.
     * @param array $product                product to synchronize
     * @return string                       product updated or not
     */
    public function sync_stored_product_db($product){

        $this->cleanMGVars();

        if ($this->sl_DEBBUG > 2) $this->debbug('Synchronizing stored product: '.print_r($product,1));

        $time_ini_check_product = microtime(1);
        if ($this->check_product_db($product)){
            $this->debbug('### check_product: ', 'timer', (microtime(1) - $time_ini_check_product));
            
            $syncProd = true;

            $time_ini_sync_product_core_data = microtime(1);
            if (!$this->sync_product_core_data_db($product)){
                $syncProd = false;
            }
            $this->debbug('### sync_product_core_data: ', 'timer', (microtime(1) - $time_ini_sync_product_core_data));

            if (empty($this->store_view_ids)){

                $this->store_view_ids = array(0);

            }

            if ($syncProd){

                if ($this->product_created === true){

                    $store_view_ids = $this->store_view_ids;
                    if (!in_array(0, $store_view_ids)){ 
                        $store_view_ids[] = 0; 
                        asort($store_view_ids);
                    }

                    $time_ini_sync_product_data_global = microtime(1);
                    $this->sync_product_data_db($product, $store_view_ids);
                    $this->debbug('### time_sync_product_data_global: ', 'timer', (microtime(1) - $time_ini_sync_product_data_global));
                    $this->product_created = false;

                }else{

                    $time_ini_sync_product_data_global = microtime(1);
                    $this->sync_product_data_db($product, $this->store_view_ids);
                    $this->debbug('### time_sync_product_data_global: ', 'timer', (microtime(1) - $time_ini_sync_product_data_global));

                }

                if ($this->avoid_images_updates){

                    $this->debbug(" > Avoiding update of product images. Option checked.");

                }else{

                    $time_ini_sync_product_images = microtime(1);
                    $this->prepare_product_images_to_store_db($this->mg_product_id, $product, 'product');
                    $this->debbug('### sync_product_images: ', 'timer', (microtime(1) - $time_ini_sync_product_images));
                
                }   

            }

            if ($syncProd){
                
                return 'item_updated';

            }   

        }

        return 'item_not_updated';

    }

    /**
     * Function to check if Sales Layer product exists.
     * @param array $product                    product to synchronize
     * @return boolean                          result of product check
     */
    private function check_product_db(array $product): bool
    {
        $hasFailed = [];

        $sl_id = $product[$this->product_field_id];
        $this->debbug(" > Checking product with SL ID: $sl_id");

        if (($product['data'][$this->product_field_name] ?? '') === '') {
            $hasFailed[$this->product_field_name] = '## Error. Product with SL ID: '.$sl_id.' has no name.';
        }

        if (!is_numeric($product['data'][$this->product_field_price]) || $product['data'][$this->product_field_price] <= 0) {
            $hasFailed[$this->product_field_price] = '## Error. Product with SL ID: '.$sl_id.' has no valid price.';
        }

        if (($product['data'][$this->product_field_sku] ?? '') === '') {
            $hasFailed[$this->product_field_sku] = '## Error. Product with name: '.$product['data'][$this->product_field_name].' and SL ID: '.$sl_id.' has no SKU.';
        }

        $this->sl_product_mg_category_ids = $this->find_product_category_ids_db($product[$this->product_field_catalogue_id]);
        
        if (empty($this->sl_product_mg_category_ids)) {
            $hasFailed['category'] = '## Error. Product '.$product['data'][$this->product_field_name].' with SL ID '.$product['id'].' has no valid categories.';
        }

        if (! empty($hasFailed)) {
            foreach ($hasFailed as $debugStr) {
                $this->debbug($debugStr);
            }
            return false;
        }

        $sl_sku = $product['data'][$this->product_field_sku];
        
        $time_ini_find_saleslayer_product_id = microtime(1);
        $this->find_saleslayer_product_id_db($sl_id);
        if ($this->sl_DEBBUG > 2) $this->debbug('# time_find_saleslayer_product_id: ', 'timer', (microtime(1) - $time_ini_find_saleslayer_product_id));
        
        $time_ini_check_duplicated_sku = microtime(1);
        if (!$this->check_duplicated_sku_db('product', $sl_sku, $sl_id)){
            if ($this->sl_DEBBUG > 1) $this->debbug('# time_check_duplicated_sku: ', 'timer', (microtime(1) - $time_ini_check_duplicated_sku));

            $product_already_assigned = false;
            if (null !== $this->mg_product_id) $product_already_assigned = true;

            $time_ini_get_product_id_by_sku = microtime(1);
            $this->get_product_id_by_sku_db($sl_sku, 'product');
            if ($this->sl_DEBBUG > 2) $this->debbug('# time_get_product_id_by_sku: ', 'timer', (microtime(1) - $time_ini_get_product_id_by_sku));
            
            if(null !== $this->mg_product_id) {
            
                if (!$product_already_assigned){

                    $time_ini_set_credentials = microtime(1);
                    $sl_credentials = array('status' => $this->status_enabled, 'saleslayer_id' => $sl_id, 'saleslayer_comp_id' => $this->comp_id);

                    foreach ($this->mg_product_row_ids as $mg_product_row_id) {
                    
                        $this->setValues($mg_product_row_id, 'catalog_product_entity', $sl_credentials, $this->product_entity_type_id, 0, false, false, $this->mg_product_row_ids);

                    }
                    if ($this->sl_DEBBUG > 2) $this->debbug('# time_set_credentials: ', 'timer', (microtime(1) - $time_ini_set_credentials));

                }

                return true;

            }else{

                if ($this->create_product_db($sl_id, $sl_sku)){
                
                    return true;
                
                }else{
                
                    return false;
                
                }

            }
    
        }

        return false;

    }

    /**
     * Function to create Sales Layer product.
     * @param int $product                      product id
     * @param string $sl_sku                    product sku
     * @return boolean                          result of product creation
     */
    private function create_product_db($product_id, $sl_sku = null) {

        $time_ini_create_product = microtime(1);

        $product_table = $this->getTable('catalog_product_entity');
        $time_ini_read_table_status_create_product = microtime(1);
        $table_status = $this->connection->showTableStatus($product_table);
        if ($this->sl_DEBBUG > 2) $this->debbug('# time_read_table_status_create_product: ', 'timer', (microtime(1) - $time_ini_read_table_status_create_product));
        
        if ($this->mg_edition == 'enterprise'){

            $row_id = $table_status['Auto_increment'];

            $sequence_product_table = $this->getTable('sequence_product');
            $table_sequence_status = $this->connection->showTableStatus($sequence_product_table);

            $entity_id = $table_sequence_status['Auto_increment'];

            $sequence_values = [
                'sequence_value' => $entity_id
            ];

            $result_sequence_create = $this->connection->insertOnDuplicate(
                $sequence_product_table,
                $sequence_values,
                array_keys($sequence_values)
            );

            if ($result_sequence_create){

                $values = [
                    'entity_id' => $entity_id,
                    'attribute_set_id' => $this->default_attribute_set_id,
                    'type_id' => $this->product_type_simple,
                    'sku' => $sl_sku, 
                    'has_options' => 0,
                    'required_options' => 0,
                    'row_id' => $row_id
                ];

                $result_create = $this->connection->insertOnDuplicate(
                    $product_table,
                    $values,
                    array_keys($values)
                );

                if (!$result_create){

                    $this->connection->delete(
                        $sequence_product_table,
                        ['sequence_value = ?' => $entity_id]
                    );

                    return false;

                }

            }else{

                return false;

            }

        }else{

            $entity_id = $table_status['Auto_increment'];

            $values = [
                'entity_id' => $entity_id,
                'attribute_set_id' => $this->default_attribute_set_id,
                'type_id' => $this->product_type_simple,
                'sku' => $sl_sku, 
                'has_options' => 0,
                'required_options' => 0
            ];

            $time_ini_insert_create_product = microtime(1);
            
            try{
            
                $result_create = $this->connection->insertOnDuplicate(
                    $product_table,
                    $values,
                    array_keys($values)
                );
                
            }catch(\Exception $e){

                $this->debbug('## Error. Creating product: '.print_r($e->getMessage(),1));

            }
            if ($this->sl_DEBBUG > 2) $this->debbug('# time_insert_create_product: ', 'timer', (microtime(1) - $time_ini_insert_create_product));
        
        }

        if ($result_create){

            if ($this->mg_edition == 'enterprise'){

                $this->mg_product_row_ids = array($row_id);
                $this->mg_product_current_row_id = $row_id;

            }else{

                $this->mg_product_row_ids = array($entity_id);
                $this->mg_product_current_row_id = $entity_id;

            }


            $this->product_created = true;
            $this->mg_product_id = $entity_id;

            $time_ini_set_credentials = microtime(1);
            $sl_credentials = array('status' => $this->status_enabled, 'saleslayer_id' => $product_id, 'saleslayer_comp_id' => $this->comp_id);
            
            foreach ($this->mg_product_row_ids as $mg_product_row_id) {
            
                $this->setValues($mg_product_row_id, 'catalog_product_entity', $sl_credentials, $this->product_entity_type_id, 0, false, false, $this->mg_product_row_ids);

            }
            if ($this->sl_DEBBUG > 2) $this->debbug('# time_set_credentials: ', 'timer', (microtime(1) - $time_ini_set_credentials));

            if ($this->sl_DEBBUG > 2) $this->debbug('# time_create_product: ', 'timer', (microtime(1) - $time_ini_create_product));
            
            return true;

        }

        if ($this->sl_DEBBUG > 2) $this->debbug('# time_create_product: ', 'timer', (microtime(1) - $time_ini_create_product));
        return false;

    }

    /**
     * Function to synchronize Sales Layer product core data.
     * @param array $product                    product to synchronize
     * @return boolean                          result of product data synchronization
     */
    private function sync_product_core_data_db($product){
        
        $sl_data = $product['data'];

        $this->debbug(" > Updating product core data ID: ".$product['id']." (parent IDs: ".print_r($this->sl_product_mg_category_ids,1).')');
        
        if (null === $this->mg_product_id) {

            // $this->mg_product_id = $this->find_saleslayer_product_id_db($product[$this->product_field_id]);
            $this->find_saleslayer_product_id_db($product[$this->product_field_id]);
   
        }

        if (null === $this->mg_product_id) {
            return true;
        }
  

        $this->updateProductDB($sl_data);

        $this->updateItemWebsite($this->mg_product_id, $sl_data, $this->product_field_website);

        $this->updateProductCategory();

        $this->updateProductStock($sl_data);
    
        $this->groupProduct($sl_data);

        $this->saveProductCons($product[$this->product_field_id]);

        return true;

    }

    /**
     * Function to update item stock.
     * @param int $item_id                  item id to update stock
     * @param boolean $sl_qty               stock to update, if false will check and update stock tables
     * @return void
     */
    private function update_item_stock($item_id, $sl_inventory_data = []){
        $manage_stock = $this->config_manage_stock;
        $is_in_stock = 0;
        $use_config_manage_stock = $use_config_backorders = $use_config_min_sale_qty = $use_config_max_sale_qty = 1;

        $sl_backorders = $this->config_backorders;
        $sl_min_sale_qty = $this->config_min_sale_qty;
        $sl_max_sale_qty = $this->config_max_sale_qty;
    
        $mg_product_core_data = $this->get_product_core_data($item_id);
     
        $cataloginventory_stock_item_table = $this->getTable('cataloginventory_stock_item');
        $stock_id = new Expr(1);

        $mg_existing_stock = $this->connection->fetchRow(
            $this->connection->select()
                ->from(
                    $cataloginventory_stock_item_table
                )
                ->where('product_id = ?', $item_id)
                ->where('stock_id = ?', $stock_id)
                ->limit(1)
        );

        $avoid_stock_update = $avoid_backorders_update = $avoid_min_sale_qty_update = $avoid_max_sale_qty_update = false;

        if (isset($sl_inventory_data['sl_qty'])){

            if (null !== $sl_inventory_data['sl_qty'] && $sl_inventory_data['sl_qty'] !== ''){
                
                $sl_qty = $sl_inventory_data['sl_qty'];
            
            }else if (!empty($mg_existing_stock)){

                $sl_qty = $mg_existing_stock['qty'];
            
            }else{

                $sl_qty = 0;
            
            }

            if ($sl_qty) $is_in_stock = 1;

            if ($mg_product_core_data['type_id'] == $this->product_type_configurable){

                $manage_stock = 0;
                
            }else{

                $manage_stock = 1;
                
            }

            if ($manage_stock != $this->config_manage_stock) $use_config_manage_stock = 0;

        }else{

            $avoid_stock_update = true;

        }

        if (isset($sl_inventory_data['backorders'])){

            $sl_backorders = $this->SLValidateInventoryBackordersValue($sl_inventory_data['backorders']);
            if ($sl_backorders != $this->config_backorders) $use_config_backorders = 0;

        }else{

            $avoid_backorders_update = true;
            
        }

        if (isset($sl_inventory_data['min_sale_qty'])){

            $sl_min_sale_qty = $sl_inventory_data['min_sale_qty'];
            
            if ($sl_min_sale_qty != $this->config_min_sale_qty) $use_config_min_sale_qty = 0;

        }else{

            $avoid_min_sale_qty_update = true;
            
        }

        if (isset($sl_inventory_data['max_sale_qty'])){

            $sl_max_sale_qty = $sl_inventory_data['max_sale_qty'];

            if ($sl_max_sale_qty != $this->config_max_sale_qty) $use_config_max_sale_qty = 0;

        }else{

            $avoid_max_sale_qty_update = true;

        }

        $default_website_id = $this->catalogInventoryConfiguration->getDefaultScopeId();

        if (!empty($mg_existing_stock)){
    
            $stock_data_to_update = [];

            if (!$avoid_stock_update){

                if ($sl_qty != $mg_existing_stock['qty']){

                    $stock_data_to_update['qty'] = $sl_qty;

                }

                if ($is_in_stock != $mg_existing_stock['is_in_stock']){

                    $stock_data_to_update['is_in_stock'] = $is_in_stock;

                }

                if ($manage_stock != $mg_existing_stock['manage_stock']){

                    $stock_data_to_update['manage_stock'] = $manage_stock;

                }

                if ($use_config_manage_stock != $mg_existing_stock['use_config_manage_stock']){

                    $stock_data_to_update['use_config_manage_stock'] = $use_config_manage_stock;

                }

            }

            if (!$avoid_backorders_update){
                
                if ($sl_backorders != $mg_existing_stock['backorders']){

                    $stock_data_to_update['backorders'] = $sl_backorders;

                }

                if ($use_config_backorders != $mg_existing_stock['use_config_backorders']){

                    $stock_data_to_update['use_config_backorders'] = $use_config_backorders;

                }

            }

            if (!$avoid_min_sale_qty_update){

                if ($sl_min_sale_qty != $mg_existing_stock['min_sale_qty']){

                    $stock_data_to_update['min_sale_qty'] = $sl_min_sale_qty;

                }

                if ($use_config_min_sale_qty != $mg_existing_stock['use_config_min_sale_qty']){

                    $stock_data_to_update['use_config_min_sale_qty'] = $use_config_min_sale_qty;

                }

            }

            if (!$avoid_max_sale_qty_update){

                if ($sl_max_sale_qty != $mg_existing_stock['max_sale_qty']){

                    $stock_data_to_update['max_sale_qty'] = $sl_max_sale_qty;

                }

                if ($use_config_max_sale_qty != $mg_existing_stock['use_config_max_sale_qty']){

                    $stock_data_to_update['use_config_max_sale_qty'] = $use_config_max_sale_qty;

                }

            }
            
            if (!empty($stock_data_to_update)){

                $this->connection->update($cataloginventory_stock_item_table, $stock_data_to_update, 'item_id = ' . $mg_existing_stock['item_id']);

            }

        }else{
            
            if ($avoid_stock_update){
                
                $sl_qty = 0;
                $is_in_stock = 0;

                if ($mg_product_core_data['type_id'] == $this->product_type_configurable) $manage_stock = 0;

                if ($manage_stock != $this->config_manage_stock) $use_config_manage_stock = 0;

            }
            
            $query_insert = " INSERT INTO ".$cataloginventory_stock_item_table."(`product_id`,`stock_id`,`qty`,`is_in_stock`,`low_stock_date`,`stock_status_changed_auto`,`website_id`,`manage_stock`,`use_config_manage_stock`,`notify_stock_qty`,`qty_increments`,`backorders`,`use_config_backorders`,`min_sale_qty`,`use_config_min_sale_qty`,`max_sale_qty`,`use_config_max_sale_qty`) values (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?);";
            
            $this->sl_connection_query($query_insert, array($item_id, $stock_id, $sl_qty, $is_in_stock , new Expr('NULL'), new Expr(0), new Expr($default_website_id), $manage_stock, $use_config_manage_stock, $this->config_notify_stock_qty, new Expr(1), $sl_backorders, $use_config_backorders, $sl_min_sale_qty, $use_config_min_sale_qty, $sl_max_sale_qty, $use_config_max_sale_qty));

        }

        if (!$avoid_stock_update){
            $cataloginventory_stock_status_table = $this->getTable('cataloginventory_stock_status');

            $mg_existing_stock_status = $this->connection->fetchRow(
                $this->connection->select()
                    ->from(
                        $cataloginventory_stock_status_table
                    )
                    ->where('product_id = ?', $item_id)
                    ->where('stock_id = ?', $stock_id)
                    ->where('website_id = ?', new Expr($default_website_id))
                    ->limit(1)
            );

            if (!empty($mg_existing_stock_status)){
            
                $stock_status_data_to_update = [];

                if ($sl_qty != $mg_existing_stock_status['qty']){

                    $stock_status_data_to_update['qty'] = $sl_qty;

                }

                if ($is_in_stock != $mg_existing_stock_status['stock_status']){

                    $stock_status_data_to_update['stock_status'] = $is_in_stock;

                }

                if (!empty($stock_status_data_to_update)){

                    $this->connection->update($cataloginventory_stock_status_table, $stock_status_data_to_update, ['product_id = ?' => $mg_existing_stock_status['product_id'], 'stock_id = ?' => $mg_existing_stock_status['stock_id'], 'website_id = ?' => $mg_existing_stock_status['website_id']]);

                }

            }else{

                $query_insert = " INSERT INTO ".$cataloginventory_stock_status_table."(`product_id`,`website_id`,`stock_id`,`qty`,`stock_status`) values (?,?,?,?,?);";
                
                $this->sl_connection_query($query_insert,array($item_id, new Expr($default_website_id), $stock_id, $sl_qty, $is_in_stock));

            }

            $inventory_source_item_table = $this->getTable('inventory_source_item');

            if (null !== $inventory_source_item_table) {

                $mg_existing_inventory_source_item = $this->connection->fetchRow(
                    $this->connection->select()
                        ->from(
                            $inventory_source_item_table
                        )
                        ->where('sku = ?', $mg_product_core_data['sku'])
                        ->limit(1)
                );

                if (!empty($mg_existing_inventory_source_item)){
                
                    $inventory_source_item_data_to_update = [];

                    if ($sl_qty != $mg_existing_inventory_source_item['quantity']){

                        $inventory_source_item_data_to_update['quantity'] = $sl_qty;

                    }

                    if ($is_in_stock != $mg_existing_inventory_source_item['status']){

                        $inventory_source_item_data_to_update['status'] = $is_in_stock;

                    }

                    if (!empty($inventory_source_item_data_to_update)){

                        $this->connection->update($inventory_source_item_table, $inventory_source_item_data_to_update, 'source_item_id = ' . $mg_existing_inventory_source_item['source_item_id']);

                    }

                }else{

                    $query_insert = " INSERT INTO ".$inventory_source_item_table."(`source_code`,`sku`,`quantity`,`status`) values (?,?,?,?);";
                    
                    $this->sl_connection_query($query_insert,array(new Expr('default'), $mg_product_core_data['sku'], $sl_qty, $is_in_stock));

                }

            }

        }

    }

    /**
     * Function to rewrite product url tables.
     * @param array $store_view_ids            store view ids to rewrite url 
     * @return void
     */
    private function setProductUrlRewrite($store_view_ids){

        $time_ini_product_url_rewrite = microtime(1);

        $url_rewrite_table = $this->getTable('url_rewrite');
        $catalog_url_rewrite_product_category_table = $this->getTable('catalog_url_rewrite_product_category');

        foreach ($store_view_ids as $store_view_id) {
            
            $time_ini_product_url_rewrite_store = microtime(1);

            $mg_product_fields = array('url_key' => '', 'url_path' => '');
            $mg_product_fields = $this->getValues($this->mg_product_current_row_id, 'catalog_product_entity', $mg_product_fields, $this->product_entity_type_id, $store_view_id);
            
            if (!isset($mg_product_fields['url_key']) || isset($mg_product_fields['url_key']) && $mg_product_fields['url_key'] == ''){

                $this->debbug('## Error. Url Key not found in store: '.$store_view_id.' for product with MG ID: '.$this->mg_product_current_row_id.'. Skipping product url rewrite update.');
                continue;

            }

            $product_data = array('entity_id' => $this->mg_product_id,
                                    'url_key' => $mg_product_fields['url_key'],
                                    'store_id' => $store_view_id
                                    );
            
            $product = $this->productModel;
            $product->setData($product_data);

            if ($this->sl_DEBBUG > 2) $this->debbug('# time_set_product_model_data: ', 'timer', (microtime(1) - $time_ini_product_url_rewrite_store));

            $time_ini_update_url_path = microtime(1);

            // if (!isset($mg_product_fields['url_path']) || isset($mg_product_fields['url_path']) && $mg_product_fields['url_path'] == ''){

                $urlPath = $this->productUrlPathGenerator->getUrlPath($product);
                $product->setUrlPath($urlPath);

            // }else{

            //     $product->setUrlPath($mg_product_fields['url_path']);
            //     $urlPath = $mg_product_fields['url_path'];

            // }

            if (!$urlPath) {
                
                $this->debbug("## Error. Couldn't generate product url path: ".print_r($product->getData(),1));
                continue;

            }else{

                foreach ($this->mg_product_row_ids as $mg_product_row_id) {
                    
                    $this->setValues($mg_product_row_id, 'catalog_product_entity', array('url_path' => $urlPath) , $this->product_entity_type_id, $store_view_id, true, false, $this->mg_product_row_ids);

                }

            }
            
            if ($this->sl_DEBBUG > 2) $this->debbug('# time_update_url_path: ', 'timer', (microtime(1) - $time_ini_update_url_path));

            if ($store_view_id == 0){

                continue;

            }

            $productUrlPath = $this->getProductUrlPath($product);
            $product = $productUrlPath['product'];
            $requestPath = $productUrlPath['requestPath']; 

            $paths = [
                $requestPath => [
                    'request_path' => $requestPath,
                    'target_path'  => 'catalog/product/view/id/' . $product->getEntityId(),
                    'metadata'     => null,
                    'category_id'  => null,
                ],
            ];

            $categories = $product->getCategoryCollection();
            $categories->addAttributeToSelect('url_key');
            $categories->setStoreId($store_view_id);

            $time_ini_categories = microtime(1);

            if (!empty($categories)){

                foreach ($categories as $category) {
                    
                    if ((string) $category->getParentId() !== '1'){

                        $productUrlPath = $this->getProductUrlPath($product, $category);
                        $requestPath = $productUrlPath['requestPath']; 
                        
                        $paths[$requestPath] = [
                            'request_path' => $requestPath,
                            'target_path'  => 'catalog/product/view/id/' . $product->getEntityId() . '/category/' . $category->getId(),
                            'metadata'     => '{"category_id":"' . $category->getId() . '"}',
                            'category_id'  => $category->getId(),
                        ];

                    }

                    $parents             = $category->getParentCategories();
                    
                    foreach ($parents as $parent) {
                   
                        if ((string) $parent->getParentId() !== '1'){
                        
                            $productUrlPath = $this->getProductUrlPath($product, $category);
                            $requestPath = $productUrlPath['requestPath'];

                            if (isset($paths[$requestPath])) {
                            
                                continue;
                            
                            }

                            $paths[$requestPath] = [
                                'request_path' => $requestPath,
                                'target_path'  => 'catalog/product/view/id/' . $product->getEntityId() . '/category/' . $parent->getId(),
                                'metadata'     => '{"category_id":"' . $parent->getId() . '"}',
                                'category_id'  => $parent->getId(),
                            ];

                        }

                    }

                }
                
            }
            
            if ($this->sl_DEBBUG > 2) $this->debbug('# time_categories: ', 'timer', (microtime(1) - $time_ini_categories));

            foreach ($paths as $path) {

                $time_ini_product_url_rewrite_path = microtime(1);

                if (!isset($path['request_path'], $path['target_path'])) {

                    continue;
                
                }

                $requestPath = $path['request_path'];
                $targetPath = $path['target_path'];
                $metadata = $path['metadata'];

                $rewriteId = $this->connection->fetchOne(
                    $this->connection->select()->from($url_rewrite_table, ['url_rewrite_id'])->where('entity_type = ?', ProductUrlRewriteGenerator::ENTITY_TYPE)->where(
                        'target_path = ?',
                        $targetPath
                    )->where('entity_id = ?', $product->getEntityId())->where('store_id = ?', $product->getStoreId())->where('is_autogenerated = ?', 1)
                );

                $time_ini_rewrite_id = microtime(1);

                if ($rewriteId) {
                    
                    try{

                        $this->connection->update(
                            $url_rewrite_table,
                            ['request_path' => $requestPath, 'metadata' => $metadata],
                            ['url_rewrite_id = ?' => $rewriteId]
                        );

                    }catch(\Exception $e){

                        $this->debbug('## Error. Updating product path url rewrite. Url path: '.$requestPath.' already exists on a different product path: '.$e->getMessage());
                        continue;

                    }

                } else {

                    $data = [
                        'entity_type'      => ProductUrlRewriteGenerator::ENTITY_TYPE,
                        'entity_id'        => $product->getEntityId(),
                        'request_path'     => $requestPath,
                        'target_path'      => $targetPath,
                        'redirect_type'    => 0,
                        'store_id'         => $product->getStoreId(),
                        'is_autogenerated' => 1,
                        'metadata'         => $metadata,
                    ];

                    try{

                        $this->connection->insertOnDuplicate(
                            $url_rewrite_table,
                            $data,
                            array_keys($data)
                        );
                    
                    }catch(\Exception $e){

                        $this->debbug('## Error. Inserting product path url rewrite. Url path: '.$requestPath.' already exists on a different product path: '.$e->getMessage());
                        continue;

                    }

                    if ($path['category_id']) {
                      
                        $rewriteId = $this->connection->fetchOne(
                            $this->connection->select()
                                ->from($url_rewrite_table, ['url_rewrite_id'])
                                ->where('entity_type = ?', ProductUrlRewriteGenerator::ENTITY_TYPE)
                                ->where('target_path = ?', $targetPath)
                                ->where('entity_id = ?', $product->getEntityId())
                                ->where('is_autogenerated = ?', 1)
                                ->where('store_id = ?', $product->getStoreId())
                        );

                    }

                }
            
                if ($this->sl_DEBBUG > 2) $this->debbug('# time_rewrite_id: ', 'timer', (microtime(1) - $time_ini_rewrite_id));

                if ($rewriteId && $path['category_id']) {

                    $data = [
                        'url_rewrite_id' => $rewriteId,
                        'category_id'    => $path['category_id'],
                        'product_id'     => $product->getEntityId(),
                    ];

                    $time_ini_delete_path = microtime(1);

                    $this->connection->delete(
                        $catalog_url_rewrite_product_category_table,
                        ['url_rewrite_id = ?' => $rewriteId]
                    );

                    if ($this->sl_DEBBUG > 2) $this->debbug('# time_delete_path: ', 'timer', (microtime(1) - $time_ini_delete_path));

                    $time_ini_insert_path = microtime(1);

                    try{

                        $this->connection->insertOnDuplicate(
                            $catalog_url_rewrite_product_category_table,
                            $data,
                            array_keys($data)
                        );

                    }catch(\Exception $e){

                        $this->debbug('## Error. Inserting catalog product category url rewrite id: '.$e->getMessage());
                        continue;

                    }

                    if ($this->sl_DEBBUG > 2) $this->debbug('# time_insert_path: ', 'timer', (microtime(1) - $time_ini_insert_path));

                }

                if ($this->sl_DEBBUG > 2) $this->debbug('# time_product_url_rewrite_path: ', 'timer', (microtime(1) - $time_ini_product_url_rewrite_path));

            }

            if ($this->sl_DEBBUG > 2) $this->debbug('# time_product_url_rewrite_store: '.$store_view_id.': ', 'timer', (microtime(1) - $time_ini_product_url_rewrite_store));

        }

        if ($this->sl_DEBBUG > 1) $this->debbug('## time_product_url_rewrite: ', 'timer', (microtime(1) - $time_ini_product_url_rewrite));

    }

    /**
     * Function to obtain non-repeated url path for product 
     * @param  object $product      MG product object
     * @param  object $category     MG category object
     * @return array                product object and requestPath obtained
     */
    protected function getProductUrlPath($product, $category = null){

        $time_ini_generate_url_path = microtime(1);
        
        $url_rewrite_table = $this->getTable('url_rewrite');

        $requestPath = $this->productUrlPathGenerator->getUrlPathWithSuffix(
            $product,
            $product->getStoreId(),
            $category
        );
        
        $exists = $this->connection->fetchOne(
            $this->connection->select()
                ->from($url_rewrite_table, new Expr(1))
                ->where('entity_type = ?', ProductUrlRewriteGenerator::ENTITY_TYPE)
                ->where('request_path = ?', $requestPath)
                ->where('store_id = ?', $product->getStoreId())
                ->where('is_autogenerated = ?', 1)
                ->where('entity_id <> ?', $product->getEntityId())
        );

        if ($exists) {

            $increment = 0;

            do{

                if (null === $category){

                    $product->setUrlKey($product->formatUrlKey($product->getUrlKey() . '-' . $product->getStoreId() . '-' . $increment));
                    
                }
                
                $product_url_path = $this->productUrlPathGenerator->getUrlPath(
                    $product,
                    $category
                );
                
                $product_url_path = $product_url_path . '-' . $product->getStoreId() . '-' . $increment;
                
                $product_url_suffix = $this->scopeConfigInterface->getValue(
                    $this->productUrlPathGenerator::XML_PATH_PRODUCT_URL_SUFFIX,
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                    $product->getStoreId()
                );

                $requestPath = $product_url_path . $product_url_suffix;
                
                $exists = $this->connection->fetchOne(
                    $this->connection->select()
                        ->from($url_rewrite_table, new Expr(1))
                        ->where('entity_type = ?', ProductUrlRewriteGenerator::ENTITY_TYPE)
                        ->where('request_path = ?', $requestPath)
                        ->where('store_id = ?', $product->getStoreId())
                        ->where('is_autogenerated = ?', 1)
                        ->where('entity_id <> ?', $product->getEntityId())
                );
            
                $increment++;
                
            }while($exists);

        }

        if ($this->sl_DEBBUG > 2) $this->debbug('# time_generate_url_path: ', 'timer', (microtime(1) - $time_ini_generate_url_path));
        return array('product' => $product, 'requestPath' => $requestPath);

    }

    /**
     * Function to synchronize Sales Layer product data.
     * @param array $product                   product to synchronize
     * @param array $store_view_ids            store view ids to synchronize 
     * @return boolean                         result of product data synchronization
     */
    private function sync_product_data_db($product, $store_view_ids){

        $time_ini_sync_product_prepare_data = microtime(1);

        $sl_id = $product[$this->product_field_id];
       
        if (null === $this->mg_product_id) {
            $this->find_saleslayer_product_id_db($sl_id);
        }

        $this->debbug(" > Updating product data ID: $sl_id");

        if ($this->sl_DEBBUG > 1 && isset($product['data'][$this->product_field_name])) {
            $this->debbug(" Name ({$this->product_field_name}): ".$product['data'][$this->product_field_name]);
        }

        $mg_product_fields = [
            $this->product_field_name => 'name',
            $this->product_field_description => 'description',
            $this->product_field_description_short => 'short_description',
            $this->product_field_meta_title => 'meta_title',
            $this->product_field_meta_keywords => 'meta_keyword',
            $this->product_field_meta_description => 'meta_description',
            $this->product_field_status => 'status',
            $this->product_field_visibility => 'visibility',
            $this->product_field_length => 'length',
            $this->product_field_width => 'width',
            $this->product_field_height => 'height',
            $this->product_field_weight => 'weight',
            $this->product_field_price => 'price',
            $this->product_field_tax_class_id => 'tax_class_id',
            $this->product_field_country_of_manufacture => 'country_of_manufacture',
            $this->product_field_special_price => 'special_price',
            $this->product_field_special_from_date => 'special_from_date', 
            $this->product_field_special_to_date => 'special_to_date'
        ];

        $sl_product_data_to_sync = [
            'status' => $this->status_enabled,
            'visibility' => $this->visibility_both
        ];
      
        if ($this->product_created === true){
            $sl_product_data_to_sync['tax_class_id'] = $this->config_default_product_tax_class;
        }

        $time_ini_get_product_core_data = microtime(1);
        $mg_product_core_data = $this->get_product_core_data($this->mg_product_id);
        $this->mg_product_attribute_set_id = $mg_product_core_data['attribute_set_id'];
        if ($this->sl_DEBBUG > 2) $this->debbug('# time_get_product_core_data: ', 'timer', (microtime(1) - $time_ini_get_product_core_data));

        $sl_product_data_to_sync = $this->prepareAllFields($mg_product_fields, $product, $sl_product_data_to_sync, $mg_product_core_data);
                
        $sl_product_additional_data_to_sync = $this->prepareAllAdditionalFields($product);

        if ($this->sl_DEBBUG > 1) $this->debbug('## time_sync_product_prepare_data: ', 'timer', (microtime(1) - $time_ini_sync_product_prepare_data));

        $time_sync_product_all_data = microtime(1);
        $this->syncProdStoreAllData($store_view_ids, $sl_product_data_to_sync, $sl_product_additional_data_to_sync, $product['data'][$this->product_field_sku]);
        if ($this->sl_DEBBUG > 1) $this->debbug('## time_sync_product_store_all_data: ', 'timer', (microtime(1) - $time_sync_product_all_data));

        $time_ini_url_rewrite = microtime(1);
        $this->setProductUrlRewrite($store_view_ids);
        if ($this->sl_DEBBUG > 1) $this->debbug('## time_sync_product_url_rewrite: ', 'timer', (microtime(1) - $time_ini_url_rewrite));

        $time_ini_manage_indexes = microtime(1);
        $indexLists = array('catalog_product_category', 'catalog_product_attribute', 'catalog_product_price', 'catalogrule_product');
        if ($this->config_catalog_product_flat == 1){ 
            $indexLists[] = 'catalog_product_flat'; 
        }
        $this->manageIndexes($indexLists, $this->mg_product_id);
        $this->debbug('## time_manage_indexes: ', 'timer', (microtime(1) - $time_ini_manage_indexes));
        
        return true;

    }

    /**
     * Function to prepare product images to store.
     * @param int $mg_item_id                   Magento item id
     * @param array $item_data                  item data
     * @param string $type                      type of item to prepare images
     * @return string                           product images to store
     */
    private function prepare_product_images_to_store_db($mg_item_id, $item_data, $type = 'product'){

        $sl_product_images = [];
        if ($type == 'format'){

            $this->debbug(" > Storing product format images SL ID: ".$item_data[$this->format_field_id]);
            $item_field_image = $this->format_field_image;
            $this->item_image_type = 'format';

        }else{

            $this->debbug(" > Storing product images SL ID: ".$item_data[$this->product_field_id]);
            $item_field_image = $this->product_field_image;            
            $this->item_image_type = 'product';

        }

        if (isset($item_data['data'][$item_field_image]) && !empty($item_data['data'][$item_field_image])){
            
            $sl_product_images = $item_data['data'][$item_field_image];

        }

        $time_ini_load_sl_images = microtime(1);
        $main_image_to_process = $final_images = $existing_images_to_delete = [];

        if ($type == 'format'){

            $item_images_sizes = $this->format_images_sizes;

        }else{

            $item_images_sizes = $this->product_images_sizes;

        }

        if (!empty($sl_product_images)){
        
            $main_image_selected = false;

            foreach ($sl_product_images as $images) {
                
                foreach ($item_images_sizes as $img_format) {
                
                    if (!empty($images[$img_format])){

                        $media_attribute = [];

                        $image_url = $images[$img_format];
                        
                        $image_url_info = pathinfo($image_url);

                        if (strpos($image_url, '%') !== false) {

                            $image_url_filename = rawurldecode($image_url_info['filename']);

                        }else{

                            $image_url_filename = $image_url_info['filename'];

                        }

                        $image_filename = preg_replace('/[^a-z0-9_\\-\\.]+/i', '_', $image_url_filename).'.'.$image_url_info['extension'];
                        
                        if (!$main_image_selected && $img_format == $this->main_image_extension){

                            $media_attribute = array('image', 'small_image', 'thumbnail', 'swatch_image');

                            $main_image_selected = true;
                            $main_image_to_process = array('url' => $image_url, 'media_attribute' => $media_attribute, 'image_name' => $image_filename);
                     
                        }

                        $final_images[$image_filename] = array('url' => $image_url, 'media_attribute' => $media_attribute);

                        break;

                    }

                }

            }

        }

        if ($this->sl_DEBBUG > 2) $this->debbug('# time_load_sl_images: ', 'timer', (microtime(1) - $time_ini_load_sl_images));
        
        $time_load_additional_images = microtime(1);

        if (isset($this->product_additional_fields_images[$mg_item_id]) && !empty($this->product_additional_fields_images[$mg_item_id])){

            foreach ($this->product_additional_fields_images[$mg_item_id] as $field_name_value => $media){
                
                foreach ($media as $media_image) {

                    $media_info = pathinfo($media_image);

                    if (strpos($media_image, '%') !== false) {

                        $media_filename = rawurldecode($media_info['filename']);

                    }else{

                        $media_filename = $media_info['filename'];

                    }

                    $media_image_filename = preg_replace('/[^a-z0-9_\\-\\.]+/i', '_', $media_filename).'.'.$media_info['extension'];

                    $final_images[$media_image_filename] = array('url' => $media_image, 'media_attribute' => array($field_name_value));
                }

                unset($this->product_additional_fields_images[$mg_item_id][$field_name_value]);
                if (empty($this->product_additional_fields_images[$mg_item_id])){
                    unset($this->product_additional_fields_images[$mg_item_id]);
                }

            }

        }
     
        if ($this->sl_DEBBUG > 2) $this->debbug('# time_load_additional_images: ', 'timer', (microtime(1) - $time_load_additional_images));

        $main_image_processed = false;

        $time_ini_check_existing = microtime(1);
        $existing_items = $this->getProductMediaGalleryEntries($mg_item_id);
        
        if (!empty($existing_items)){

            $main_image_to_process_image_name = '';
            if (!empty($main_image_to_process)) $main_image_to_process_image_name = $main_image_to_process['image_name'];

            $check_data = $this->check_existing_items($mg_item_id, $existing_items, $main_image_to_process_image_name, $final_images);
        
            if (isset($check_data['main_image_to_process_file_size'])){ 

                $main_image_to_process['file_size'] = $check_data['main_image_to_process_file_size'];

            }

            $main_image_processed = $check_data['main_image_processed'];
            $final_images = $check_data['final_images'];
            $existing_images_to_delete = $check_data['existing_images_to_delete'];
            unset($check_data);

        }
        
        if ($this->sl_DEBBUG > 2) $this->debbug('# time_check_existing: ', 'timer', (microtime(1) - $time_ini_check_existing));

        if (!$main_image_processed && !empty($main_image_to_process)){

            $time_ini_save_main_image = microtime(1);

            $main_image_file_size = null;
            if (isset($main_image_to_process['file_size'])){

                $main_image_file_size = $main_image_to_process['file_size'];

            }

            $this->debbug('Processing main image: '.$main_image_to_process['image_name'].' with url: '.$main_image_to_process['url'].(!empty($main_image_to_process['media_attribute']) ? ' with media_attribute: '.print_r($main_image_to_process['media_attribute'],1) : '' ));
            
            if ($this->item_image_type == 'product'){

                foreach ($this->mg_product_row_ids as $mg_product_row_id) {
                    
                    $this->processImage($mg_product_row_id, $main_image_to_process['image_name'], $main_image_to_process['url'], $main_image_to_process['media_attribute'], $main_image_file_size);

                }

            }else{

                foreach ($this->mg_format_row_ids as $mg_format_row_id) {
                    
                    $this->processImage($mg_format_row_id, $main_image_to_process['image_name'], $main_image_to_process['url'], $main_image_to_process['media_attribute'], $main_image_file_size);

                }

            }

            unset($final_images[$main_image_to_process['image_name']]);
            
            if ($this->sl_DEBBUG > 2) $this->debbug('# time_save_main_image: ', 'timer', (microtime(1) - $time_ini_save_main_image));

        }
        
        if (!empty($final_images) || !empty($existing_images_to_delete)){

            $images_data[$type.'_id'] = $mg_item_id;

            if (!empty($final_images)){

                $images_data['final_images'] = $final_images;   

            }

            if (!empty($existing_images_to_delete)){
                $images_data['existing_images_to_delete'] = $existing_images_to_delete;   
            }

            try{

                $sql_query_to_insert = " INSERT INTO ".$this->saleslayer_syncdata_table.
                                                 " ( sync_type, item_type, item_data, sync_params ) VALUES ".
                                                 "('update', 'product__images', '".addslashes(json_encode($images_data))."', '')";

                $this->connection->query($sql_query_to_insert);

            }catch(\Exception $e){

                $this->debbug('## Error. Insert syncdata SQL query: '.$sql_query_to_insert);
                $this->debbug('## Error. Insert syncdata SQL message: '.$e->getMessage());

            }

        }else{

            $this->checkItemMediaAttributes($mg_item_id);

        }

    }

    /**
     * Function to get product media gallery entries
     * @param  int $mg_item_id                  Magento item id
     * @return array $media_gallery_images      item gallery entries
     */
    private function getProductMediaGalleryEntries($mg_item_id){

        $productModel = clone $this->productModel;
        $mg_product = $productModel->setStoreId(0)->load($mg_item_id);
                
        $product_images = $mg_product->getMediaAttributeValues();
        $media_gallery_images = $mg_product->getMediaGallery('images');

        foreach ($media_gallery_images as $keyImg => $media_gallery_image) {

            $media_gallery_images[$keyImg]['types'] = array_keys($product_images, $media_gallery_image['file']);
            
        }

        return $media_gallery_images;

    }

    /**
    * Function to check existing images.
    * @param int $mg_item_id                                Magento item id
    * @param array $existing_items                          existing images
    * @param string $main_image_to_process_image_name       image name of main image to process
    * @param array $final_images                            final images to process
    * @return array $return_data                            data checked to continue image process
    */
    private function check_existing_items($mg_item_id, $existing_items, $main_image_to_process_image_name = '', $final_images = []){

        $return_data = [];
        $existing_images_to_delete = [];
        $main_image_processed = false;

        foreach ($existing_items as $item_data) {

            $time_ini_item_check = microtime(1);
            if (!is_array($item_data['types'])){ $item_data['types'] = []; }
            if (!empty($item_data['types'])){ asort($item_data['types']); }
            if ($this->sl_DEBBUG > 2) $this->debbug('# time_item_get_data: ', 'timer', (microtime(1) - $time_ini_item_check));
        
            $time_ini_item_parse = microtime(1);
            $parse_url_item = pathinfo($item_data['file']);
            $item_url = $this->product_path_base.$item_data['file'];
            if ($this->sl_DEBBUG > 2) $this->debbug('# time_item_parse: ', 'timer', (microtime(1) - $time_ini_item_parse));
            
            $time_ini_item_size = microtime(1);
            $item_size = $this->sl_get_file_size($item_url);
            if ($this->sl_DEBBUG > 2) $this->debbug('# time_item_size: ', 'timer', (microtime(1) - $time_ini_item_size));
            $item_filename = $parse_url_item['filename'].'.'.$parse_url_item['extension'];
        
            if ($item_size){
                
                if (isset($final_images[$item_filename])){
        
                    $time_ini_image_size = microtime(1);
                    $image_size = $this->sl_get_file_size($final_images[$item_filename]['url']);
                    if ($this->sl_DEBBUG > 2) $this->debbug('# time_image_size: ', 'timer', (microtime(1) - $time_ini_image_size));

                    if ($image_size){
                        
                        $final_images[$item_filename]['file_size'] = $image_size;
                        if (!$main_image_processed && $main_image_to_process_image_name == $item_filename){
                            
                            $return_data['main_image_to_process_file_size'] = $image_size;

                        }

                        if ($image_size == $item_size){
        
                            $image_media_attribute = $final_images[$item_filename]['media_attribute'];
                            if (!empty($image_media_attribute)){ asort($image_media_attribute); }
        
                            $time_ini_mod_item = microtime(1);

                            $this->check_existing_item_types($mg_item_id, $item_data['file'], $item_data['types'], $image_media_attribute);
                            $this->check_existing_item_enabled($item_data['value_id'], $item_data['disabled']);

                            if (!$main_image_processed && $main_image_to_process_image_name == $item_filename){

                                
                                $main_image_processed = true;

                            }

                            if ($this->sl_DEBBUG > 2) $this->debbug('# time_item_check: ', 'timer', (microtime(1) - $time_ini_item_check));
                            if ($this->sl_DEBBUG > 2) $this->debbug('# time_mod_item: ', 'timer', (microtime(1) - $time_ini_mod_item));
                            unset($final_images[$item_filename]);
                            continue;

                        }

                    }else{

                        // La imagen de SL no existe o es incorrecta, saltamos y dejamos la actual de MG.
                        unset($final_images[$item_filename]);
                        continue;

                    }

                }

            }

            if (isset($item_data['row_id'])){

                $existing_images_to_delete[$item_data['value_id']] = array('primary_key_id' => $item_data['row_id'], 'types' => $item_data['types'], 'filename' => $item_data['file']);

            }else{

                $existing_images_to_delete[$item_data['value_id']] = array('primary_key_id' => $item_data['entity_id'], 'types' => $item_data['types'], 'filename' => $item_data['file']);

            }

            if ($this->sl_DEBBUG > 2) $this->debbug('# time_item_check: ', 'timer', (microtime(1) - $time_ini_item_check));

        }

        $return_data['main_image_processed'] = $main_image_processed;
        $return_data['final_images'] = $final_images;
        $return_data['existing_images_to_delete'] = $existing_images_to_delete;

        return $return_data;

    }

    /**
    * Function to check if an existing image has the same image types, if not, we update them.
    * @param int $mg_item_id                        Magento item id
    * @param string $mg_item_file                   item file
    * @param array $mg_item_types                   Magento item types
    * @param array $sl_item_types                   Sales Layer item types
    * @return void
    */
    private function check_existing_item_types($mg_item_id, $mg_item_file, $mg_item_types, $sl_item_types){

        if ($mg_item_types != $sl_item_types && !empty($sl_item_types)){

            $time_ini_update_types = microtime(1);        
            $images_data_to_update = [];

            foreach ($sl_item_types as $sl_item_type) {
                
                $images_data_to_update[$sl_item_type] = $mg_item_file;

            }

            if ($this->sl_DEBBUG > 1) $this->debbug('Updating existing item file: '.$mg_item_file.' image types: '.print_r($mg_item_types,1));
            if ($this->sl_DEBBUG > 1) $this->debbug('With SL image types: '.print_r($sl_item_types,1));

            if ($this->item_image_type == 'product'){

                foreach ($this->mg_product_row_ids as $mg_product_row_id) {
                    
                    $this->setProductImageTypes($mg_product_row_id, 'catalog_product_entity', $images_data_to_update, $this->product_entity_type_id);

                }

            }else{

                foreach ($this->mg_format_row_ids as $mg_format_row_id) {
                    
                    $this->setProductImageTypes($mg_format_row_id, 'catalog_product_entity', $images_data_to_update, $this->product_entity_type_id);

                }

            }

            if ($this->sl_DEBBUG > 2) $this->debbug('# time_item_update_types: ', 'timer', (microtime(1) - $time_ini_update_types));
            
        }

    }

    /**
    * Function to check if an existing image is enabled, if not, we enable it.
    * @param int $mg_item_id                    Magento item id
    * @param int $mg_item_disabled              item disabled status
    * @return void
    */
    private function check_existing_item_enabled($mg_item_id, $mg_item_disabled){

        if ($mg_item_disabled != 0){

            $time_ini_update_disabled = microtime(1);
            $catalog_product_entity_media_gallery_value_table = $this->getTable('catalog_product_entity_media_gallery_value');

            try{

                $this->connection->update($catalog_product_entity_media_gallery_value_table, ['disabled' => 0], 'value_id = ' . $mg_item_id);

            }catch(\Exception $e){

                $this->debbug('## Error. Enabling image item with ID: '.$mg_item_id.', message: '.$e->getMessage());

            }

            if ($this->sl_DEBBUG > 2) $this->debbug('# time_item_update_disabled: ', 'timer', (microtime(1) - $time_ini_update_disabled));
          
        }

    }

    /**
    * Function to synchronize Sales Layer stored product images.
    * @param array $images_data            product to synchronize
    * @param string $item_index            index of product or format
    * @return void
    */
    public function sync_stored_product_images_db($images_data, $item_index){

        if ($item_index == 'product'){

            $item_id = $images_data['product_id'];
            $this->item_image_type = 'product';

            $this->mg_product_row_ids = $this->getEntityRowIds($item_id, 'product');
            $this->mg_product_current_row_id = $this->getEntityCurrentRowId($item_id, 'product');

        }else{
            
            $item_id = $images_data['format_id'];
            $this->item_image_type = 'format';

            $this->mg_format_row_ids = $this->getEntityRowIds($item_id, 'product');
            $this->mg_format_current_row_id = $this->getEntityCurrentRowId($item_id, 'product');
        
        }

        $item_core_data = $this->get_product_core_data($item_id);
        
        if (null === $item_core_data){

            $this->debbug('## Error. The '.$item_index.' with MG ID: '.$item_id.' does not exist. Cannot update '.$item_index.' additional images.');
            return false;
            
        }else if (!isset($item_core_data['attribute_set_id'])){

            $this->debbug('## Error. The '.$item_index.' with MG ID: '.$item_id.' does not have attribute set id. Cannot update '.$item_index.' additional images.');
            return false;

        }else if (isset($item_core_data['attribute_set_id']) && in_array($item_core_data['attribute_set_id'], array(null, 0, false))){

            $this->debbug('## Error. The '.$item_index.' with MG ID: '.$item_id.' has an invalid attribute set id. Cannot update '.$item_index.' additional images: '.print_r($item_core_data['attribute_set_id'],1));
            return false;

        }
        
        $this->mg_product_attribute_set_id = $item_core_data['attribute_set_id'];

        $this->debbug(" > Updating stored ".$item_index." images ID: ".$item_id);
        
        $time_ini_delete_images = microtime(1);

        if (isset($images_data['existing_images_to_delete']) && !empty($images_data['existing_images_to_delete'])){

            foreach ($images_data['existing_images_to_delete'] as $id_image_to_delete => $image_to_delete){
 
                $this->debbug(" Deleting image: ".$image_to_delete['filename']);

                $galleryEntityTable = $this->getTable('catalog_product_entity_media_gallery_value_to_entity');
                
                try{

                    $query_delete = " DELETE FROM ".$galleryEntityTable." WHERE value_id = ".$id_image_to_delete." AND ".$this->tables_identifiers[$galleryEntityTable]." = ".$image_to_delete['primary_key_id'];
                    $this->sl_connection_query($query_delete);
                
                }catch(\Exception $e){

                    $this->debbug('## Error. Deleting image from galleryEntityTable: '.$e->getMessage());

                }

                $catalog_product_entity_media_gallery_value_table = $this->getTable('catalog_product_entity_media_gallery_value');
                
                try{

                    $query_delete = " DELETE FROM ".$catalog_product_entity_media_gallery_value_table." WHERE value_id = ".$id_image_to_delete." AND ".$this->tables_identifiers[$catalog_product_entity_media_gallery_value_table]." = ".$image_to_delete['primary_key_id'];
                    $this->sl_connection_query($query_delete);
                    
                }catch(\Exception $e){

                    $this->debbug('## Error. Deleting image from catalog_product_entity_media_gallery_value_table: '.$e->getMessage());

                }

                $galleryTable = $this->getTable('catalog_product_entity_media_gallery');
                // if (isset($image_to_delete['types'])){

                //     $image_types = [];
                //     if (!is_array($image_to_delete['types']) && $image_to_delete['types']){

                //         $image_types = array($image_to_delete['types']);

                //     }else if (is_array($image_to_delete['types']) && !empty($image_to_delete['types'])){

                //         $image_types = $image_to_delete['types'];

                //     }

                //     if (!empty($image_types)){

                //         $image_data_to_remove = [];

                //         foreach ($image_types as $image_type) {
                            
                //             $image_data_to_remove[$image_type] = '';

                //         }

                //         $time_ini_image_delete_types = microtime(1);
                //         $this->debbug('Deleting image types: '.print_r($image_types,1));
                //         $this->setProductImageTypes($item_id, 'catalog_product_entity', $image_data_to_remove, $this->product_entity_type_id);
                //         if ($this->sl_DEBBUG > 2) $this->debbug('# time_image_delete_types: ', 'timer', (microtime(1) - $time_ini_image_delete_types));

                //     }

                // }

                $is_in_other_items = $this->connection->fetchOne(
                    $this->connection->select()
                    ->from($galleryEntityTable, [new Expr('COUNT(*)')])
                    ->where($this->tables_identifiers[$galleryEntityTable].' != ?', $image_to_delete['primary_key_id'])
                    ->where('value_id = ?', $id_image_to_delete)
                );

                if ($is_in_other_items > 0){

                    $this->debbug("The image is assigned to another item, we don't eliminate it.");
                
                }else{

                    try{

                        $query_delete = " DELETE FROM ".$galleryTable." WHERE value_id = ".$id_image_to_delete;
                        $this->sl_connection_query($query_delete);
                    
                    }catch(\Exception $e){

                        $this->debbug('## Error. Deleting image from galleryTable: '.$e->getMessage());

                    }

                    $image_full_path = $this->product_path_base.$image_to_delete['filename'];

                    if (file_exists($image_full_path)){ 
                        
                        unlink($image_full_path); 

                    }

                }

            }

        }

        if ($this->sl_DEBBUG > 2) $this->debbug('# time_delete_images: ', 'timer', (microtime(1) - $time_ini_delete_images));

        $time_ini_process_final_images = microtime(1);

        if (isset($images_data['final_images']) && !empty($images_data['final_images'])){

            foreach ($images_data['final_images'] as $image_filename => $image_info) {
                
                $image_file_size = null;
                if (isset($image_info['file_size'])){

                    $image_file_size = $image_info['file_size'];

                }

                $this->debbug('Processing image: '.$image_filename.' with url: '.$image_info['url'].(!empty($image_info['media_attribute']) ? ' and media_attribute: '.print_r($image_info['media_attribute'],1) : '' ));
                
                if ($this->item_image_type == 'product'){

                    foreach ($this->mg_product_row_ids as $mg_product_row_id) {
                        
                        $this->processImage($mg_product_row_id, $image_filename, $image_info['url'], $image_info['media_attribute'], $image_file_size);

                    }

                }else{

                    foreach ($this->mg_format_row_ids as $mg_format_row_id) {
                        
                        $this->processImage($mg_format_row_id, $image_filename, $image_info['url'], $image_info['media_attribute'], $image_file_size);

                    }

                }

            }

            if ($this->sl_DEBBUG > 2) $this->debbug('# time_process_final_images: ', 'timer', (microtime(1) - $time_ini_process_final_images));

        }
        
        $this->checkItemMediaAttributes($item_id);

    }

    /**
     * Function to check if there are products with images and no image types assigned
     * @param  int      $item_id    MG item id to check
     * @return void
     */
    private function checkItemMediaAttributes($item_id){

        $mg_product_core_data = $this->get_product_core_data($item_id);
        $this->mg_product_attribute_set_id = $mg_product_core_data['attribute_set_id'];

        $time_ini_check_item_media_attributes = microtime(1);

        $existing_images = $this->getProductMediaGalleryEntries($item_id);

        if (!empty($existing_images)){

            $media_attributes = array('image' => 0, 'small_image' => 0, 'thumbnail' => 0, 'swatch_image' => 0);
            $main_image_id = 0;

            foreach ($existing_images as $image_id => $existing_image){
            
                if ($main_image_id == 0) $main_image_id = $image_id;

                if (isset($existing_image['types']) && !empty($existing_image['types'])){

                    foreach ($existing_image['types'] as $existing_item_type){
                        
                        if (isset($media_attributes[$existing_item_type])){

                            if ($media_attributes[$existing_item_type] != 0){

                                // $this->debbug('## Error. Image type '.$existing_item_type.' is already assigned to another image id in media_attributes: '.print_r($media_attributes,1));

                            }else{
                                
                                $media_attributes[$existing_item_type] = $image_id;

                                if ($existing_item_type == 'image' && $main_image_id !== $image_id) $main_image_id = $image_id;

                            }

                        }

                    }

                }

            }

            $image_data_to_update = [];

            foreach ($media_attributes as $image_type => $image_id) {
                
                if ($image_id == 0) $image_data_to_update[$image_type] = $existing_images[$main_image_id]['file'];

            }

            if (!empty($image_data_to_update)){

                if ($this->item_image_type == 'product'){

                    foreach ($this->mg_product_row_ids as $mg_product_row_id) {
                        
                        $this->setProductImageTypes($mg_product_row_id, 'catalog_product_entity', $image_data_to_update, $this->product_entity_type_id);

                    }

                }else{

                    foreach ($this->mg_format_row_ids as $mg_format_row_id) {
                        
                        $this->setProductImageTypes($mg_format_row_id, 'catalog_product_entity', $image_data_to_update, $this->product_entity_type_id);

                    }

                }



            }

        }
                
        if ($this->sl_DEBBUG > 2) $this->debbug('# time_check_item_media_attributes: ', 'timer', (microtime(1) - $time_ini_check_item_media_attributes));
       
    }

    /**
     * Function to process image.
     * @param int $entity_id                    Magento item id
     * @param string $image_filename            image name
     * @param string $image_url                 image url
     * @param array $image_types                image types to assign
     * @param int $image_file_size              image file size, if null, we check it
     * @return string                           product images to store
     */
    private function processImage($entity_id, $image_filename, $image_url, $image_types, $image_file_size = null){

        $time_ini_process_image = microtime(1);

        $process_image = true;
        $existing_image = false;
        $image_value_id = $img_filename = '';

        $galleryTable = $this->getTable('catalog_product_entity_media_gallery');
        $galleryEntityTable = $this->getTable('catalog_product_entity_media_gallery_value_to_entity');
        $galleryValueTable = $this->getTable('catalog_product_entity_media_gallery_value');

        $image_name_to_check = '/'.substr($image_filename, 0,1).'/'.substr($image_filename, 1,1).'/'.$image_filename;

        $existing_image_id = $this->connection->fetchOne(
            $this->connection->select()
                ->from(
                   [$galleryTable],
                    ['value_id']
                )
                ->where('value' . ' = ?', $image_name_to_check)
        );

        if ($existing_image_id){

            $image_value_id = $existing_image_id;

            $image_full_path = $this->product_path_base.$image_name_to_check;
        
            $time_ini_mg_image_size = microtime(1);
            $mg_image_size = $this->sl_get_file_size($image_full_path);
            if ($this->sl_DEBBUG > 2) $this->debbug('# time_mg_image_size: ', 'timer', (microtime(1) - $time_ini_mg_image_size));

            $time_ini_sl_image_size = microtime(1);
            if (null !== $image_file_size) {
        
                $sl_image_size = $image_file_size;

            }else{

                $sl_image_size = $this->sl_get_file_size($image_url);
                
            }

            if ($this->sl_DEBBUG > 2) $this->debbug('# time_sl_image_size: ', 'timer', (microtime(1) - $time_ini_sl_image_size));
    
            if ($sl_image_size != $mg_image_size){

                //La imagen es distinta, la reprocesaremos

            }else{

                $process_image = false;
                $img_filename = $image_filename;

                $this->connection->update($galleryTable, ['value' => $image_name_to_check], 'value_id = ' . $existing_image_id);

            }

            $image_value_id = $existing_image_id;
            $existing_image = true;

        }

        $time_ini_save_image_total = microtime(1);

        if ($process_image){

            $time_ini_check_waste = microtime(1);
            $image_filepath = $this->product_path_base.substr($image_filename, 0,1).'/'.substr($image_filename, 1,1).'/';
            $image_full_path = $image_filepath.$image_filename;
            if (file_exists($image_full_path)){ 
                unlink($image_full_path); 
            }
            if ($this->sl_DEBBUG > 2) $this->debbug('# time_check_waste: ', 'timer', (microtime(1) - $time_ini_check_waste));

            $img_filename = $this->prepareImage($image_url, $image_filepath, false);
           
            if (!$img_filename){

                //No se ha podido preparar la imagen por error, devolvemos false
                $this->debbug('## Error. Downloading image: '.$image_url.' , message: '.$e->getMessage());
                return false;

            }

        }
        
        if ($this->sl_DEBBUG > 2) $this->debbug('# time_save_image_local: ', 'timer', (microtime(1) - $time_ini_save_image_total));

        $time_ini_update_types = microtime(1);
        $image_data_to_update = [];

        foreach ($image_types as $image_type) {
            
            $image_data_to_update[$image_type] = $image_name_to_check;

        }

        if ($this->sl_DEBBUG > 1) $this->debbug('Setting on image: '.$image_name_to_check.' , types: '.print_r($image_types,1));
        $this->setProductImageTypes($entity_id, 'catalog_product_entity', $image_data_to_update, $this->product_entity_type_id);
        if ($this->sl_DEBBUG > 2) $this->debbug('# time_item_update_types: ', 'timer', (microtime(1) - $time_ini_update_types));

        $time_ini_set_image_data = microtime(1);

        if (!$existing_image){

            $image_value_id = $this->connection->fetchOne(
                $this->connection->select()->from($galleryTable, [new Expr('MAX(`value_id`) + 1')])
            );

            if (!$image_value_id) $image_value_id = 1;

            $media_gallery_attribute = $this->getAttribute('media_gallery', $this->product_entity_type_id);
       
            $gallery_table_data = [
                'value_id'          => $image_value_id,
                'attribute_id'      => $media_gallery_attribute[\Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_ID],
                'value'             => $image_name_to_check,
                'media_type'        => ImageEntryConverter::MEDIA_TYPE_CODE,
                'disabled'          => 0,
            ];
            
            $this->connection->insertOnDuplicate($galleryTable, $gallery_table_data, array_keys($gallery_table_data));
            
        }

        $position = $this->connection->fetchOne(
            $this->connection->select()
                ->from(
                   [$galleryValueTable],
                    [new Expr('MAX(`position`) + 1')]
                )
                ->where($this->tables_identifiers[$galleryValueTable] . ' = ?', $entity_id)
        );
        
        if (!$position) $position = 1;

        $table_status = $this->connection->showTableStatus($galleryValueTable);
        $record_id = $table_status['Auto_increment'];

        $gallery_value_table_data = [
            'value_id'                                      => $image_value_id,
            'store_id'                                      => 0,
            $this->tables_identifiers[$galleryValueTable]   => $entity_id,
            'label'                                         => null,
            'position'                                      => $position,
            'disabled'                                      => 0,
            'record_id'                                     => $record_id
        ];

        $this->connection->insertOnDuplicate($galleryValueTable, $gallery_value_table_data, array_keys($gallery_value_table_data));

        $gallery_entity_table_data = [
            'value_id'                                          => $image_value_id,
            $this->tables_identifiers[$galleryEntityTable]      => $entity_id,
        ];

        $this->connection->insertOnDuplicate($galleryEntityTable, $gallery_entity_table_data, array_keys($gallery_entity_table_data));
        
        if ($this->sl_DEBBUG > 2) $this->debbug('# time_set_image_data: ', 'timer', (microtime(1) - $time_ini_set_image_data));
        if ($this->sl_DEBBUG > 2) $this->debbug('# time_save_image_total: ', 'timer', (microtime(1) - $time_ini_save_image_total));
        if ($this->sl_DEBBUG > 1) $this->debbug('# time_process_image: ', 'timer', (microtime(1) - $time_ini_process_image));
        
    }

    /**
     * Function to clean associated items from a product.
     * @param int $product_id               product id from product to clean associated items
     * @return void
     */
    private function clean_associated_product_db(){

        $time_ini_clean_associated_product = microtime(1);

        $product_table = $this->getTable('catalog_product_entity');
        $catalog_product_link_table = $this->getTable('catalog_product_link');

        $query_delete = " DELETE FROM ".$catalog_product_link_table." WHERE product_id = ".$this->mg_product_current_row_id." AND link_type_id = ".$this->product_link_type_grouped_db;
        $this->sl_connection_query($query_delete);

        $this->connection->update($product_table, ['type_id' => $this->product_type_simple], $this->tables_identifiers[$product_table].' = ' . $this->mg_product_id);

        if ($this->sl_DEBBUG > 2) $this->debbug('# time_clean_associated_product: ', 'timer', (microtime(1) - $time_ini_clean_associated_product));

    }

    /**
    * Function to synchronize Sales Layer stored product links.
    * @param array $all_linked_product_data            product links to synchronize
    * @return string                                    product links updated or not
    */
    public function sync_stored_product_links_db($all_linked_product_data){

        $product_table = $this->getTable('catalog_product_entity');
        $product_link_table = $this->getTable('catalog_product_link');
        $product_link_attribute_table = $this->getTable('catalog_product_link_attribute');
        
        $product_link_attributes_data = $this->connection->fetchAll(
            $this->connection->select()
                ->from(
                   [$product_link_attribute_table]
                )
        );

        $link_attributes_data = [];

        if (!empty($product_link_attributes_data)){

            foreach ($product_link_attributes_data as $product_link_attribute_data) {
                
                $link_table = $this->getTable('catalog_product_link_attribute_' . $product_link_attribute_data['data_type']);

                if (null !== $link_table) {

                    $link_attributes_data[$product_link_attribute_data['link_type_id']][$product_link_attribute_data['product_link_attribute_code']] = array('product_link_attribute_id' => $product_link_attribute_data['product_link_attribute_id'], 'table' => $link_table);

                }

            }

        }

        $time_ini_update_links = microtime(1);

        foreach ($all_linked_product_data as $product_id => $linked_product_data) {
           
            $this->debbug(" > Updating stored product links ID: ".$product_id);

            if('item_not_updated' == $this->processProductLink($product_id, $linked_product_data, $product_link_table, $product_table, $link_attributes_data )){
                return 'item_not_updated';
            }

        }
        
        if ($this->sl_DEBBUG > 1) $this->debbug('# time_update_links: ', 'timer', (microtime(1) - $time_ini_update_links));
        
        return 'item_updated';

    }

    /**
     * Function to check if image attributes are global, if not, change them.
     * @return void
     */
    private function checkImageAttributes(){
        
        $image_attributes = array('image', 'small_image', 'thumbnail');

        foreach ($image_attributes as $image_attribute) {
            
            $attribute_id = $this->attribute->getIdByCode($this->product_entity, $image_attribute);
            $attribute = $this->attribute;
            $attribute->load($attribute_id);

            if ($attribute->getIsGlobal() != $this->scope_global){
                $attribute->setIsGlobal($this->scope_global);
                $attribute->save();
            }
        }
    }

    /**
     * Function to check if active attributes are global or not and store them in class variables.
     * @return void
     */
    private function checkActiveAttributes(){
        
        $attribute_id = $this->attribute->getIdByCode($this->category_entity, 'is_active');
        $attribute = $this->attribute;
        $attribute->load($attribute_id);
        if ($attribute->getIsGlobal() == $this->scope_global){ $this->category_enabled_attribute_is_global = true; }

        $attribute_id = $this->attribute->getIdByCode($this->product_entity, 'status');
        $attribute = $this->attribute;
        $attribute->load($attribute_id);
        if ($attribute->getIsGlobal() == $this->scope_global){ $this->product_enabled_attribute_is_global = true; }

    }

    /**
     * Function to find product category parent ids.
     * @param array $product_catalogue_ids          categories to find parents 
     * @return array $mg_category_ids               result of categories and its parents
     */
    private function find_product_category_ids_db($product_catalogue_ids){
        
        $mg_category_ids = [];
        
        if (!is_array($product_catalogue_ids)){ $product_catalogue_ids = array($product_catalogue_ids); }

        if (!empty($product_catalogue_ids)){
        
            foreach ($product_catalogue_ids as $product_catalogue_id){

                if (intval($product_catalogue_id) != 0){
                     
                    $this->find_saleslayer_category_id_db($product_catalogue_id);

                    if (null !== $this->mg_category_id) {
                        
                        if ($this->products_previous_categories == 1){
                        
                            $mg_category_core_data = $this->get_category_core_data($this->mg_category_id);
                            $mg_category_path = explode("/", $mg_category_core_data['path']);

                            if (!empty($mg_category_path) && count($mg_category_path) > 1){

                                unset($mg_category_path[0]);

                                foreach ($mg_category_path as $mg_category_path_id) {
                                    
                                    if (!in_array($mg_category_path_id, $mg_category_ids)){ 

                                        array_push($mg_category_ids, $mg_category_path_id);
                                    
                                    }

                                }

                            }

                        }else{
                            
                            if (!in_array($this->mg_category_id, $mg_category_ids)){ 

                                array_push($mg_category_ids, $this->mg_category_id);
                            
                            }

                        }

                    }

                }
        
            }

        }

        if (!empty($mg_category_ids)){

            $mg_category_ids = array_unique($mg_category_ids);
        
        }

        return $mg_category_ids;

    }

    /**
     * Function to find an option value inside class attribute array
     * @param int $attribute_set_id             id of attribute set to search
     * @param int $attribute_id                 id of attribute to search
     * @param string $attribute_option_value    option value to search for
     * @param int $store_view_id                id of store view to search first
     * @return int                              id of attribute option value found
     */
    private function find_attribute_option_value_db($attribute_set_id, $attribute_id, $attribute_option_value, $store_view_id){

        $attribute_option_value_lower = strtolower($attribute_option_value);
        $attribute_option_value_special = htmlspecialchars($attribute_option_value_lower);

       if ($store_view_id != 0){

           if (isset($this->attributes_options_collection[$attribute_set_id][$attribute_id]['options'][$store_view_id][$attribute_option_value_lower])){
               return $this->attributes_options_collection[$attribute_set_id][$attribute_id]['options'][$store_view_id][$attribute_option_value_lower];

           }else if (isset($this->attributes_options_collection[$attribute_set_id][$attribute_id]['options'][$store_view_id][$attribute_option_value_special])){
               return $this->attributes_options_collection[$attribute_set_id][$attribute_id]['options'][$store_view_id][$attribute_option_value_special];

           }

       }

       if (isset($this->attributes_options_collection[$attribute_set_id][$attribute_id]['options'][0][$attribute_option_value_lower])){
            $this->updateAttributeOption_db($attribute_set_id, $attribute_id, $this->attributes_options_collection[$attribute_set_id][$attribute_id]['options'][0][$attribute_option_value_lower], $attribute_option_value);
           return $this->attributes_options_collection[$attribute_set_id][$attribute_id]['options'][0][$attribute_option_value_lower];

       }else if (isset($this->attributes_options_collection[$attribute_set_id][$attribute_id]['options'][0][$attribute_option_value_special])){
           $this->updateAttributeOption_db($attribute_set_id, $attribute_id, $this->attributes_options_collection[$attribute_set_id][$attribute_id]['options'][0][$attribute_option_value_special], $attribute_option_value);
           return $this->attributes_options_collection[$attribute_set_id][$attribute_id]['options'][0][$attribute_option_value_special];

       }        

       $rest_stores = array_diff($this->store_view_ids, array(0, $store_view_id));
       
       if (!empty($rest_stores)){

           foreach ($rest_stores as $rest_store_id){

               if (isset($this->attributes_options_collection[$attribute_set_id][$attribute_id]['options'][$rest_store_id][$attribute_option_value_lower])){
                   $this->updateAttributeOption_db($attribute_set_id, $attribute_id, $this->attributes_options_collection[$attribute_set_id][$attribute_id]['options'][$rest_store_id][$attribute_option_value_lower], $attribute_option_value);
                   return $this->attributes_options_collection[$attribute_set_id][$attribute_id]['options'][$rest_store_id][$attribute_option_value_lower];

               }else if (isset($this->attributes_options_collection[$attribute_set_id][$attribute_id]['options'][$rest_store_id][$attribute_option_value_special])){
                   $this->updateAttributeOption_db($attribute_set_id, $attribute_id, $this->attributes_options_collection[$attribute_set_id][$attribute_id]['options'][$rest_store_id][$attribute_option_value_special], $attribute_option_value);
                   return $this->attributes_options_collection[$attribute_set_id][$attribute_id]['options'][$rest_store_id][$attribute_option_value_special];

               }

           }

       }

       return $this->addAttributeOption_db($attribute_set_id, $attribute_id, $attribute_option_value);

    }

    /**
     * Function to create an attribute option that doesn't exist.
     * @param int $attribute_set_id         attribute set id to store new option id. 
     * @param int $attribute_id             attribute id to add option.
     * @param string $attribute_option      attribute option name.
     * @return int $option_id               new option id
     */
    private function addAttributeOption_db($attribute_set_id, $attribute_id, $attribute_option){
        
        $option_id = $this->synccatalogDataHelper->createOrGetId($attribute_id, $attribute_option, $this->store_view_ids);        

        if ($option_id){

            foreach ($this->store_view_ids as $store_view_id) {

                if (!isset($this->attributes_options_collection[$attribute_set_id][$attribute_id]['options'][$store_view_id])){

                    $this->attributes_options_collection[$attribute_set_id][$attribute_id]['options'][$store_view_id] = [];

                }

                $this->attributes_options_collection[$attribute_set_id][$attribute_id]['options'][$store_view_id][strtolower($attribute_option)] = $option_id;

            }

        }

        return $option_id;

    }

    /**
     * Function to update an attribute option value.
     * @param int $attribute_set_id                 id of attribute set to update
     * @param int $attribute_id                     id of attribute to update
     * @param int $attribute_option_id              id of attribute option to update
     * @param string $attribute_option_value        option value to update
     * @param string $store_view_id_found           id of store view to search first
     * @return string                               id of attribute option value found
     */
    private function updateAttributeOption_db($attribute_set_id, $attribute_id, $attribute_option_id, $attribute_option_value){

        $store_views_to_update = [];

        foreach ($this->all_store_view_ids as $all_stores_view_id) {

            $optioncollection = clone $this->collectionOption;

            $option = $optioncollection
                        ->setAttributeFilter($attribute_id)
                        ->addFieldToFilter('main_table.option_id', $attribute_option_id)
                        ->setStoreFilter($all_stores_view_id, false)
                        ->getFirstItem();

            if (!empty($option->getData()) ){

                $store_views_to_update[$all_stores_view_id] = $option->getValue();

            }else{

                if (in_array($all_stores_view_id, $this->store_view_ids)){

                    $option_value_found = false;

                    if (isset($this->attributes_options_collection[$attribute_set_id][$attribute_id]['options'][$all_stores_view_id])){

                        $option_value_found = array_search($attribute_option_id, $this->attributes_options_collection[$attribute_set_id][$attribute_id]['options'][$all_stores_view_id]);
                    }

                    if (!$option_value_found){ $option_value_found = $attribute_option_value; }

                    $store_views_to_update[$all_stores_view_id] = $option_value_found;

                }

            }

        }

        if (!empty($store_views_to_update)){

            try{

                $result_update = $this->synccatalogDataHelper->updateAttributeOption($attribute_id, $attribute_option_id, $store_views_to_update);

            }catch(\Exception $e){

                $result_update = false;
                $this->debbug('## Error. Updating attribute option: '.$e->getMessage());

            }
            
            if ($result_update) {

                foreach ($store_views_to_update as $store_view_to_update => $store_view_option_value){

                    if (!isset($this->attributes_options_collection[$attribute_set_id][$attribute_id]['options'][$store_view_to_update])){

                        $this->attributes_options_collection[$attribute_set_id][$attribute_id]['options'][$store_view_to_update] = [];

                    }

                    $this->attributes_options_collection[$attribute_set_id][$attribute_id]['options'][$store_view_to_update][strtolower($store_view_option_value)] = $attribute_option_id;

                }

            }

        }

    }

    /**
     * Function to synchronize Sales Layer stored product format.
     * @param array $format                 product format to synchronize
     * @return string                       product format updated or not
     */
    public function sync_stored_format_db($format){

        $this->cleanMGVars();

        if ($this->sl_DEBBUG > 2) $this->debbug('Synchronizing stored product format: '.print_r($format,1));

        $time_ini_format_process = microtime(1);

        $format['data'][$this->format_field_name] = $format[$this->format_field_id].'_'.$format['data'][$this->format_field_name];
        $sl_product_id = $format[$this->format_field_products_id];
        
        $this->find_saleslayer_product_id_db($sl_product_id);
        
        if (null !== $this->mg_product_id) {

            $parent_product_data = $this->get_product_core_data($this->mg_product_id);
            $this->mg_product_attribute_set_id = $parent_product_data['attribute_set_id'];

            $time_ini_check_format = microtime(1);
            if ($this->check_format_db($format)){
                $this->debbug('### check_format: ', 'timer', (microtime(1) - $time_ini_check_format));
                
                $syncForm = true;

                $time_ini_sync_format_core_data = microtime(1);
                if (!$this->sync_format_core_data_db($format)){
                    $syncForm = false;
                }
                $this->debbug('### sync_format_core_data: ', 'timer', (microtime(1) - $time_ini_sync_format_core_data));

                if (empty($this->store_view_ids)){

                    $this->store_view_ids = array(0);

                }

                if ($syncForm){

                     if ($this->format_created === true){

                        $store_view_ids = $this->store_view_ids;
                        if (!in_array(0, $store_view_ids)){ 
                            $store_view_ids[] = 0; 
                            asort($store_view_ids);
                        }
               
                        $time_ini_sync_format_data_global = microtime(1);
                        $this->sync_format_data_db($format, $store_view_ids);
                        $this->debbug('### time_sync_format_data_global: ', 'timer', (microtime(1) - $time_ini_sync_format_data_global));
                        $this->format_created = false;

                    }else{

                        $time_ini_sync_format_data_global = microtime(1);
                        $this->sync_format_data_db($format, $this->store_view_ids);
                        $this->debbug('### time_sync_format_data_global: ', 'timer', (microtime(1) - $time_ini_sync_format_data_global));

                    }

                    if ($this->avoid_images_updates){

                        $this->debbug(" > Avoiding update of product format images. Option checked.");

                    }else{

                        $time_ini_sync_format_images = microtime(1);
                        $this->prepare_product_images_to_store_db($this->mg_format_id, $format, 'format');
                        $this->debbug('### sync_format_images: ', 'timer', (microtime(1) - $time_ini_sync_format_images));
                    
                    }

                }

                if (!$syncForm){
                    
                    return 'item_not_updated';

                }

            }else{

                return 'item_not_updated';

            }

          
        }else{
        
            $this->debbug("## Error. Format parent product doesn't exist.");
            return 'item_not_updated';
        
        }

        if (isset($format['parent_product_attributes_ids']) && !empty($format['parent_product_attributes_ids'])){

            $time_ini_assign_product_formats = microtime(1);
            $this->assign_product_formats_db($format);
            $this->debbug('### assign_product_formats: ', 'timer', (microtime(1) - $time_ini_assign_product_formats));

        }else{
            $this->debbug('Format does not have any configurable attributes, we skip the assignation.');
        }

        $this->debbug('### time_format_process: ', 'timer', (microtime(1) - $time_ini_format_process));

        return 'item_updated';

    }

    /**
     * Function to check if Sales Layer product format exists.
     * @param array $format                     product format to synchronize
     * @return boolean                          result of product format check
     */
    private function check_format_db($format){

        $sl_id = $format[$this->format_field_id];
        $sl_product_id = $format[$this->format_field_products_id];

        $this->debbug(" > Checking product format with SL ID: $sl_id");
        if ($format['data'][$this->format_field_name] == ''){

            $this->debbug('## Error. Product format with SL ID: '.$sl_id.' has no name.');
            return false;

        }

        if ($format['data'][$this->format_field_sku] == ''){

            $this->debbug('## Error. Product with name: '.$format['data'][$this->format_field_name].' and SL ID: '.$sl_id.' has no SKU.');
            return false;

        }

        $sl_sku = $format['data'][$this->format_field_sku];

        $this->mg_format_id = $this->find_saleslayer_format_id_db(null, $sl_id);

        if (!$this->check_duplicated_sku_db('product_format', $sl_sku, $sl_product_id, $sl_id)){

            $format_already_assigned = false;
            if (null !== $this->mg_format_id) $format_already_assigned = true;

            $this->get_product_id_by_sku_db($sl_sku, 'format');
            
            if(null !== $this->mg_format_id) {
            
                if (!$format_already_assigned){

                    $sl_credentials = [
                        'status'                => $this->status_enabled,
                        'saleslayer_id'         => $sl_product_id,
                        'saleslayer_comp_id'    => $this->comp_id,
                        'saleslayer_format_id'  => $sl_id
                    ];

                    $this->setValues($this->mg_format_id, 'catalog_product_entity', $sl_credentials, $this->product_entity_type_id, 0);

                }
                
                return true;

            }else{

                if ($this->create_format_db($sl_product_id, $sl_id, $sl_sku)){
                    return true;
                }else{
                    return false;
                }

            }
    
        }

        return false;

    }

    /**
     * Function to create Sales Layer product format.
     * @param int $product_id                   product id
     * @param int $format_id                    product format id
     * @param string $sl_sku                    product format sku
     * @return boolean                          result of product format creation
     */
    private function create_format_db($product_id, $format_id, $sl_sku = null) {

        $product_table = $this->getTable('catalog_product_entity');
        $table_status = $this->connection->showTableStatus($product_table);
        

        if (!in_array($this->format_type_creation, array($this->product_type_simple, $this->product_type_virtual))){
            $type_creation = $this->product_type_simple;
        }else{
            $type_creation = $this->format_type_creation;
        }

        if ($this->mg_edition == 'enterprise'){

            $row_id = $table_status['Auto_increment'];

            $sequence_product_table = $this->getTable('sequence_product');
            $table_sequence_status = $this->connection->showTableStatus($sequence_product_table);

            $entity_id = $table_sequence_status['Auto_increment'];

            $sequence_values = [
                'sequence_value' => $entity_id
            ];

            $result_sequence_create = $this->connection->insertOnDuplicate(
                $sequence_product_table,
                $sequence_values,
                array_keys($sequence_values)
            );

            if ($result_sequence_create){

                $values = [
                    'entity_id' => $entity_id,
                    'attribute_set_id' => $this->mg_product_attribute_set_id,
                    'type_id' => $type_creation,
                    'sku' => $sl_sku, 
                    'has_options' => 0,
                    'required_options' => 0,
                    'row_id' => $row_id
                ];

                $result_create = $this->connection->insertOnDuplicate(
                    $product_table,
                    $values,
                    array_keys($values)
                );

                if (!$result_create){

                    $this->connection->delete(
                        $sequence_product_table,
                        ['sequence_value = ?' => $entity_id]
                    );

                    return false;

                }

            }

        }else{

            $entity_id = $table_status['Auto_increment'];

            $values = [
                'entity_id' => $entity_id,
                'attribute_set_id' => $this->mg_product_attribute_set_id,
                'type_id' => $type_creation,
                'sku' => $sl_sku, 
                'has_options' => 0,
                'required_options' => 0
            ];

            $result_create = $this->connection->insertOnDuplicate(
                $product_table,
                $values,
                array_keys($values)
            );

        }

        if ($result_create){

            if ($this->mg_edition == 'enterprise'){

                $this->mg_format_row_ids = array($row_id);
                $this->mg_format_current_row_id = $row_id;

            }else{

                $this->mg_format_row_ids = array($entity_id);
                $this->mg_format_current_row_id = $entity_id;

            }

            $this->format_created = true;
            $this->mg_format_id = $entity_id;

            $sl_credentials = [
                'status' => $this->status_enabled,
                'saleslayer_id' => $product_id,
                'saleslayer_comp_id' => $this->comp_id,
                'saleslayer_format_id' => $format_id
            ];
            
            foreach ($this->mg_format_row_ids as $mg_format_row_id) {
            
                $this->setValues($mg_format_row_id, 'catalog_product_entity', $sl_credentials, $this->product_entity_type_id, 0, false, false, $this->mg_format_row_ids);

            }

            return true;

        }

        return false;

    }

    /**
     * Function to synchronize Sales Layer product format core data.
     * @param array $format                     product format to synchronize
     * @return boolean                          result of product format data synchronization
     */
    private function sync_format_core_data_db($format){
        
        $sl_id = $format[$this->format_field_id];
        $sl_product_id = $format[$this->format_field_products_id];
        $sl_data = $format['data'];
        
        
        $this->debbug(" > Updating product format core data ID: $sl_id");

        if (null === $this->mg_format_id){

            $this->mg_format_id = $this->find_saleslayer_format_id_db($sl_product_id, $sl_id);
         
        }

        if (null !== $this->mg_format_id) {

            $product_table = $this->getTable('catalog_product_entity');
            $mg_format_core_data = $this->get_product_core_data($this->mg_format_id);
            
            $mg_format_data_to_update = [];

            $sl_sku = $sl_data[$this->format_field_sku];

            if ($mg_format_core_data['sku'] != $sl_sku){
                
               $mg_format_data_to_update['sku'] = $sl_sku;
                   
            }

            if ($this->mg_product_attribute_set_id != $mg_format_core_data['attribute_set_id']){

                $mg_format_data_to_update['attribute_set_id'] = $this->mg_product_attribute_set_id;

            }

            if (!empty($mg_format_data_to_update)){

                try{

                    $this->connection->update($product_table, $mg_format_data_to_update, $this->tables_identifiers[$product_table].' = ' . $this->mg_format_id);

                }catch(\Exception $e){

                    $this->debbug('## Error. Updating product format core data: '.$e->getMessage());

                }

            }
            
            $this->updateItemWebsite($this->mg_format_id, $sl_data, $this->format_field_website);

            if ($this->format_created === true || $this->avoid_stock_update == '0'){

                $sl_inventory_data = [];
                
                $inventory_fields = array('sl_qty' => $this->format_field_quantity, 'backorders' => $this->format_field_inventory_backorders, 'min_sale_qty' => $this->format_field_inventory_min_sale_qty, 'max_sale_qty' => $this->format_field_inventory_max_sale_qty);

                foreach ($inventory_fields as $field_to_update => $sl_field) {
                    
                    if (isset($sl_data[$sl_field])){

                        if (is_array($sl_data[$sl_field])){

                            $sl_inventory_data[$field_to_update] = reset($sl_data[$sl_field]);
                        
                        }else{

                            $sl_inventory_data[$field_to_update] = $sl_data[$sl_field];
                        
                        }

                    }else if ($this->format_created === true){

                        switch ($field_to_update) {

                            case 'sl_qty':
                                $sl_inventory_data[$field_to_update] = 0;
                                break;
                            
                            case 'backorders':
                                $sl_inventory_data[$field_to_update] = $this->config_backorders;
                                break;

                            case 'min_sale_qty':
                                $sl_inventory_data[$field_to_update] = $this->config_min_sale_qty;
                                break;

                            case 'max_sale_qty':
                                $sl_inventory_data[$field_to_update] = $this->config_max_sale_qty;
                                break;

                            default:

                                break;

                        }

                    }

                }

                if (!empty($sl_inventory_data)){

                    $this->update_item_stock($this->mg_format_id, $sl_inventory_data);

                }

            }

            $conn_insert = true;
            if (isset($this->sl_multiconn_table_data['format'][$sl_id]) && !empty($this->sl_multiconn_table_data['format'][$sl_id]['sl_connectors'])){

                $conn_found = array_search($this->processing_connector_id, $this->sl_multiconn_table_data['format'][$sl_id]['sl_connectors']);

                if (!is_numeric($conn_found)){

                    $this->sl_multiconn_table_data['format'][$sl_id]['sl_connectors'][] = $this->processing_connector_id;

                    $new_connectors_data = json_encode($this->sl_multiconn_table_data['format'][$sl_id]['sl_connectors']);

                    $query_update  = " UPDATE ".$this->saleslayer_multiconn_table." SET sl_connectors = ? WHERE id = ? ";

                    $this->sl_connection_query($query_update, array($new_connectors_data, $this->sl_multiconn_table_data['format'][$sl_id]['id']));
                    
                }

                $conn_insert = false;

            }

            if ($conn_insert){

                $connectors_data = json_encode(array($this->processing_connector_id));

                $query_insert = " INSERT INTO ".$this->saleslayer_multiconn_table."(`item_type`,`sl_id`,`sl_comp_id`,`sl_connectors`) values ( ? , ? , ? , ? );";

                $this->sl_connection_query($query_insert, array('format', $sl_id, $this->comp_id, $connectors_data));

            }

        }

        return true;

    }

    /**
     * Function to synchronize Sales Layer product format data.
     * @param array $format                     product format to synchronize
     * @param array $store_view_ids             store view ids to synchronize 
     * @return boolean                          result of product format data synchronization
     */
    private function sync_format_data_db($format, $store_view_ids){

        $time_ini_sync_format_prepare_data = microtime(1);

        $sl_id = $format[$this->format_field_id];
        $sl_product_id = $format[$this->format_field_products_id];
       
        if (null === $this->mg_format_id){

            $this->mg_format_id = $this->find_saleslayer_format_id_db($sl_product_id, $sl_id);
          
        }

        $this->debbug(" > Updating product format data ID: $sl_id");

        if ($this->sl_DEBBUG > 1 && isset($format['data'][$this->format_field_name])) {
            $this->debbug(" Name ({$this->format_field_name}): ".$format['data'][$this->format_field_name]);
        }

        $mg_format_fields = [
            $this->format_field_name => 'name',
            $this->format_field_price => 'price',
            $this->format_field_tax_class_id => 'tax_class_id',
            $this->format_field_country_of_manufacture => 'country_of_manufacture',
            $this->format_field_special_price => 'special_price',
            $this->format_field_special_from_date => 'special_from_date',
            $this->format_field_special_to_date => 'special_to_date',
            $this->format_field_visibility => 'visibility'
        ];
        
        $sl_format_data_to_sync = [
            'status' => $this->status_enabled,
            'visibility' => $this->visibility_not_visible
        ];
        
        if ($this->format_created === true){
            $sl_format_data_to_sync['tax_class_id'] = $this->config_default_product_tax_class;
        }

        $mg_format_core_data = $this->get_product_core_data($this->mg_format_id);
        $this->mg_product_attribute_set_id = $mg_format_core_data['attribute_set_id'];

        foreach ($mg_format_fields as $sl_format_field => $mg_format_field) {
            
            if (isset($format['data'][$sl_format_field])){

                switch ($mg_format_field) {
                    case 'description':
                    case 'short_description':

                        $sl_format_data_to_sync[$mg_format_field] = $this->sl_check_html_text($format['data'][$sl_format_field]);

                        break;

                    case 'name':

                        $sl_format_data_to_sync['url_key'] = $this->productModel->formatUrlKey($format['data'][$sl_format_field].'-'.$mg_format_core_data['sku']);
                        $sl_format_data_to_sync[$mg_format_field] = $format['data'][$sl_format_field];

                        break;

                    case 'status':
                        
                        if (!$this->SLValidateStatusValue($format['data'][$sl_format_field])){

                            $sl_format_data_to_sync[$mg_format_field] = $this->status_disabled;

                        }else{

                            $sl_format_data_to_sync[$mg_format_field] = $this->status_enabled;

                        }

                        break;

                    case 'visibility':

                        if (isset($format['data'][$sl_format_field])){

                            if (is_array($format['data'][$sl_format_field])){

                                $sl_format_visibility = reset($format['data'][$sl_format_field]);
                            
                            }else{

                                $sl_format_visibility = $format['data'][$sl_format_field];
                            
                            }

                            if ($return_visibility = $this->SLValidateVisibilityValue($sl_format_visibility)){

                                $sl_format_data_to_sync[$mg_format_field] = $return_visibility;

                            }

                        }

                        break;

                    case 'tax_class_id':

                        if (isset($format['data'][$sl_format_field])){

                            $sl_tax_class_id = '';

                            if (is_array($format['data'][$sl_format_field])){

                                if (!empty($format['data'][$sl_format_field])) $sl_tax_class_id = reset($format['data'][$sl_format_field]);
                            
                            }else{

                                $sl_tax_class_id = $format['data'][$sl_format_field];
                            
                            }
                            
                            if ($sl_tax_class_id !== '') $sl_format_data_to_sync[$mg_format_field] = $this->findTaxClassId($sl_tax_class_id);

                        }

                        break;

                    case 'country_of_manufacture':

                        if (isset($format['data'][$sl_format_field])){

                            if (is_array($format['data'][$sl_format_field])){

                                $sl_country_of_manufacture = reset($format['data'][$sl_format_field]);
                            
                            }else{

                                $sl_country_of_manufacture = $format['data'][$sl_format_field];
                            
                            }

                            $sl_format_data_to_sync[$mg_format_field] = $this->findCountryOfManufacture($sl_country_of_manufacture);

                        }

                        break;

                    case 'special_from_date':
                    case 'special_to_date':

                        if (is_numeric($format['data'][$sl_format_field])){

                            $sl_format_data_to_sync[$mg_format_field] = date('Y/m/d H:i:s', $format['data'][$sl_format_field]);

                        }else{

                            if ($format['data'][$sl_format_field] == '0000-00-00 00:00:00') $format['data'][$sl_format_field] = null;

                            if (null !== $format['data'][$sl_format_field] && $format['data'][$sl_format_field] !== ''){
                            
                                if (strpos($format['data'][$sl_format_field], ':') === false) $format['data'][$sl_format_field] .= ' 00:00:00';

                                $sl_time = strtotime($format['data'][$sl_format_field]);

                                if ($sl_time != ''){
                                    
                                    $format['data'][$sl_format_field] = date('Y/m/d H:i:s',$sl_time);
                                    
                                }

                            }

                            $sl_format_data_to_sync[$mg_format_field] = $format['data'][$sl_format_field];
                            
                        }

                        break;

                    case 'price':
                    case 'special_price':

                        $price_value = '';
                    
                        (is_array($format['data'][$sl_format_field])) ? $price_value = reset($format['data'][$sl_format_field]) : $price_value = $format['data'][$sl_format_field];

                        if (!is_numeric($price_value)){
                            
                            if (strpos($price_value, ',') !== false){
                                
                                $price_value = str_replace(',', '.', $price_value);
                                
                            } 
                            
                            if (filter_var($price_value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)){
                                
                                $price_value = filter_var($price_value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                                
                            }
                            
                            if (!is_numeric($price_value) || $price_value === ''){
                                                        
                                $price_value = null;
                       
                            }
                            
                        }else if ($price_value <= 0 && $mg_format_field == 'special_price'){
                                                    
                            $price_value = null;
                            
                        }else if ($price_value < 0 && $mg_format_field == 'price'){
                                                    
                            $price_value = null;
                            
                        }

                        if ((null !== $price_value) || (null === $price_value && $mg_format_field == 'special_price')) {

                            $sl_format_data_to_sync[$mg_format_field] = $price_value;
                       
                        }else if (null === $price_value && $mg_format_field == 'price'){

                            if (isset($format['data'][$this->format_field_sku])){

                                $format_index = 'SKU '.$format['data'][$this->format_field_sku];

                            }else{

                                $format_index = 'SL ID '.$format[$this->format_field_id];

                            }

                            $this->debbug('## Error. Product format with '.$format_index.' has a price that does not have a valid format, it will not be updated. Original value: '.print_r($format['data'][$sl_format_field],1));

                        }
                        
                        break;
                    
                    default:

                        $sl_format_data_to_sync[$mg_format_field] = $format['data'][$sl_format_field];

                        break;
                }
               
            }

        }

        $sl_format_additional_data_to_sync = [];

        if (count($this->format_additional_fields) > 0) {
            
            if (null === $this->mg_product_attribute_set_id){

                $this->debbug('## Error. Product format does not have attribute set id. Cannot update product format additional attribute values.');

            }else{

                foreach($this->format_additional_fields as $field_name => $field_name_value) {
                    
                    if (!isset($format['data'][$field_name_value])){

                        continue;

                    }

                    $time_ini_get_attribute_additional = microtime(1);
                    $result_check = $this->checkAttributeInSetId($field_name, $this->product_entity_type_id, $this->mg_product_attribute_set_id);
                    if ($this->sl_DEBBUG > 2) $this->debbug('# time_get_attribute_additional: ', 'timer', (microtime(1) - $time_ini_get_attribute_additional));

                    if (!$result_check){

                        continue;

                    }

                    if ((is_array($format['data'][$field_name_value]) && empty($format['data'][$field_name_value])) || (!is_array($format['data'][$field_name_value]) && $format['data'][$field_name_value] == '')){

                        $sl_format_additional_data_to_sync[$field_name] = '';

                    }else{

                        $sl_format_additional_data_to_sync[$field_name] = $format['data'][$field_name_value];

                    } 

                }

            }

        }

        if ($this->sl_DEBBUG > 1) $this->debbug('## time_sync_format_prepare_data: ', 'timer', (microtime(1) - $time_ini_sync_format_prepare_data));

        $this->syncFormatStoreAllData($store_view_ids, $sl_format_data_to_sync, $sl_format_additional_data_to_sync, $format['data'][$this->format_field_sku]);

        $time_ini_manage_indexes = microtime(1);
        $indexLists = array('catalog_product_attribute', 'catalog_product_price', 'catalogrule_product');
        if ($this->config_catalog_product_flat == 1){ 
            $indexLists[] = 'catalog_product_flat'; 
        }
        $this->manageIndexes($indexLists, $this->mg_format_id);
        $this->debbug('## time_manage_indexes: ', 'timer', (microtime(1) - $time_ini_manage_indexes));

        return true;

    }

    /**
     * Function to assign format to a product.
     * @param array $format                    format data to assign
     * @return void
     */
    private function assign_product_formats_db($format){

        $format_parent_product_attribute_ids = [];

        $product_table = $this->getTable('catalog_product_entity');
        $catalog_product_relation_table = $this->getTable('catalog_product_relation');
        $catalog_product_super_link_table = $this->getTable('catalog_product_super_link');
        $catalog_product_super_attribute_table = $this->getTable('catalog_product_super_attribute');
        $catalog_product_super_attribute_label_table = $this->getTable('catalog_product_super_attribute_label');

        if (isset($format['parent_product_attributes_ids']) && !empty($format['parent_product_attributes_ids'])){

            //hacemos check de atributos y set_id, eliminamos sobrantes y añadimos los que vienen, con etiquetas
            if ($this->mg_edition == 'enterprise') {
                $mg_product_super_attributes = $this->connection->fetchAll(
                    $this->connection->select()
                    ->from($catalog_product_super_attribute_table)
                    ->where('product_id = ?', $this->mg_product_current_row_id)
                );
            } else {
                $mg_product_super_attributes = $this->connection->fetchAll(
                    $this->connection->select()
                    ->from($catalog_product_super_attribute_table)
                    ->where('product_id = ?', $this->mg_product_id)
                );
            }

            $format_parent_product_attribute_ids = $format['parent_product_attributes_ids'];

            foreach ($format_parent_product_attribute_ids as $keyAttr => $format_parent_product_attribute_id){
                
                $attribute = $this->getAttributeInSetById($format_parent_product_attribute_id, $this->product_entity_type_id, $this->mg_product_attribute_set_id);
                
                if (empty($attribute)){
                    
                    $this->debbug('## Error. The attribute with MG ID: '.$format_parent_product_attribute_id.' does not exist or it is not associated to the product attribute set id.');
                    unset($format_parent_product_attribute_ids[$keyAttr]);
                    continue;

                }

                if ($this->mg_edition == 'enterprise') {
                    $product_super_attribute_id = $this->connection->fetchOne(
                        $this->connection->select()
                        ->from($catalog_product_super_attribute_table)
                        ->where('product_id = ?', $this->mg_product_current_row_id)
                        ->where('attribute_id = ?', $format_parent_product_attribute_id)
                    );
                } else {
                    $product_super_attribute_id = $this->connection->fetchOne(
                        $this->connection->select()
                        ->from($catalog_product_super_attribute_table)
                        ->where('product_id = ?', $this->mg_product_id)
                        ->where('attribute_id = ?', $format_parent_product_attribute_id)
                    );
                }

                if (!$product_super_attribute_id){

                    if ($this->mg_edition == 'enterprise') {
                        $position = $this->connection->fetchOne(
                            $this->connection->select()
                                ->from(
                                    $catalog_product_super_attribute_table,
                                    [new Expr('MAX(`position`) + 1')]
                                )
                                ->where('product_id = ?', $this->mg_product_current_row_id)
                                ->group('product_id')
                        );
                    } else {
                        $position = $this->connection->fetchOne(
                            $this->connection->select()
                                ->from(
                                    $catalog_product_super_attribute_table,
                                    [new Expr('MAX(`position`) + 1')]
                                )
                                ->where('product_id = ?', $this->mg_product_id)
                                ->group('product_id')
                        );
                    }
                    

                    if (!$position) $position = 0;

                    
                    $query_insert = " INSERT INTO ".$catalog_product_super_attribute_table."(`product_id`,`attribute_id`,`position`) values (?,?,?);";

                    if ($this->mg_edition == 'enterprise') {
                        $this->sl_connection_query($query_insert,array($this->mg_product_current_row_id, $format_parent_product_attribute_id, $position));

                        $product_super_attribute_id = $this->connection->fetchOne(
                            $this->connection->select()
                            ->from($catalog_product_super_attribute_table)
                            ->where('product_id = ?', $this->mg_product_current_row_id)
                            ->where('attribute_id = ?', $format_parent_product_attribute_id)
                        );
                    } else {
                        $this->sl_connection_query($query_insert,array($this->mg_product_id, $format_parent_product_attribute_id, $position));

                        $product_super_attribute_id = $this->connection->fetchOne(
                            $this->connection->select()
                            ->from($catalog_product_super_attribute_table)
                            ->where('product_id = ?', $this->mg_product_id)
                            ->where('attribute_id = ?', $format_parent_product_attribute_id)
                        );
                    }

                }

                if (!$product_super_attribute_id){

                    $this->debbug('## Error. Could not associate attribute product format.');

                }else{

                    $product_super_attribute_label_data = $this->connection->fetchRow(
                        $this->connection->select()
                        ->from($catalog_product_super_attribute_label_table)
                        ->where('product_super_attribute_id = ?', $product_super_attribute_id)
                        ->where('store_id = ?', 0)
                    );

                    if (!empty($product_super_attribute_label_data)){

                        if ($product_super_attribute_label_data['value'] != $attribute[\Magento\Eav\Api\Data\AttributeInterface::FRONTEND_LABEL]){

                            $this->connection->update($catalog_product_super_attribute_label_table, ['value' => $attribute[\Magento\Eav\Api\Data\AttributeInterface::FRONTEND_LABEL]], 'value_id = ' . $product_super_attribute_label_data['value_id']);

                        }

                    }else{

                        $query_insert = " INSERT INTO ".$catalog_product_super_attribute_label_table."(`product_super_attribute_id`,`store_id`,`use_default`,`value`) values (?,?,?,?);";
                        $this->sl_connection_query($query_insert,array($product_super_attribute_id, 0, 0, $attribute[\Magento\Eav\Api\Data\AttributeInterface::FRONTEND_LABEL]));

                    }

                }

                foreach ($mg_product_super_attributes as $keyMGPSA => $mg_product_super_attribute) {
                    
                    if ($mg_product_super_attribute['attribute_id'] == $format_parent_product_attribute_id){

                        unset($mg_product_super_attributes[$keyMGPSA]);

                    }

                }           

            }

            if (!empty($mg_product_super_attributes)){

                foreach ($mg_product_super_attributes as $mg_product_super_attribute) {
                    
                    $this->connection->delete(
                        $catalog_product_super_attribute_table,
                        ['product_super_attribute_id = ?' => $mg_product_super_attribute['product_super_attribute_id']]
                    );
                       
                    $this->connection->delete(
                        $catalog_product_super_attribute_label_table,
                        ['product_super_attribute_id = ?' => $mg_product_super_attribute['product_super_attribute_id']]
                    );

                }

            }

        }

        if (empty($format_parent_product_attribute_ids)){

            if ($this->mg_edition == 'enterprise') {
                $this->connection->delete(
                    $catalog_product_relation_table,
                    ['parent_id = ?' => $this->mg_product_current_row_id]
                );

                //eliminamos super_links
                $this->connection->delete(
                    $catalog_product_super_link_table,
                    ['parent_id = ?' => $this->mg_product_current_row_id]
                );
            } else {
                $this->connection->delete(
                    $catalog_product_relation_table,
                    ['parent_id = ?' => $this->mg_product_id]
                );

                //eliminamos super_links
                $this->connection->delete(
                    $catalog_product_super_link_table,
                    ['parent_id = ?' => $this->mg_product_id]
                );
            }

            //leemos filtro ids
            if ($this->mg_edition == 'enterprise') {
                $product_super_attribute_ids_filter = $this->connection->fetchOne(
                    $this->connection->select()
                        ->from(
                            [$catalog_product_super_attribute_table],
                            [new Expr('GROUP_CONCAT(product_super_attribute_id SEPARATOR ",")')]
                        )
                        ->where('product_id' . ' = ?', $this->mg_product_current_row_id)
                );
            } else {
                $product_super_attribute_ids_filter = $this->connection->fetchOne(
                    $this->connection->select()
                        ->from(
                            [$catalog_product_super_attribute_table],
                            [new Expr('GROUP_CONCAT(product_super_attribute_id SEPARATOR ",")')]
                        )
                        ->where('product_id' . ' = ?', $this->mg_product_id)
                );
            }

            if (null === $product_super_attribute_ids_filter || $product_super_attribute_ids_filter == ''){
                
                $this->debbug('Product has no associated attributes, we finish assignation.');

            }else{

                if ($this->mg_edition == 'enterprise') {
                    $this->connection->delete(
                        $catalog_product_super_attribute_table,
                        ['product_id = ?' => $this->mg_product_current_row_id]
                    );
                    
                    $this->connection->delete(
                        $catalog_product_super_attribute_label_table,
                        ['product_super_attribute_id IN ('.$product_super_attribute_ids_filter.')']
                    );
                } else {
                    $this->connection->delete(
                        $catalog_product_super_attribute_table,
                        ['product_id = ?' => $this->mg_product_id]
                    );
                       
                    $this->connection->delete(
                        $catalog_product_super_attribute_label_table,
                        ['product_super_attribute_id IN ('.$product_super_attribute_ids_filter.')']
                    );
                }
                
            }

            //cambiamos producto a simple
            if ($this->mg_edition == 'enterprise') {
                $this->connection->update($product_table, [
                    'type_id' => $this->product_type_simple,
                    'has_options' => 0,
                    'required_options' => 0
                ], $this->tables_identifiers[$product_table].' = ' . $this->mg_product_current_row_id);
            } else {
                $this->connection->update($product_table, [
                    'type_id' => $this->product_type_simple,
                    'has_options' => 0,
                    'required_options' => 0
                ], $this->tables_identifiers[$product_table].' = ' . $this->mg_product_id);
            }

            $this->update_item_stock($this->mg_product_id, array('sl_qty' => ''));
            

        }else{
            
            //checkeamos producto
            $parent_product_data = $this->get_product_core_data($this->mg_product_id);

            if ($parent_product_data['type_id'] != $this->product_type_configurable || $parent_product_data['has_options'] != 1 || $parent_product_data['required_options'] != 1){

                if ($this->mg_edition == 'enterprise') {
                    $this->connection->update($product_table, array('type_id' => $this->product_type_configurable, 'has_options' => 1, 'required_options' => 1), $this->tables_identifiers[$product_table].' = ' . $this->mg_product_current_row_id);
                } else {
                    $this->connection->update($product_table, array('type_id' => $this->product_type_configurable, 'has_options' => 1, 'required_options' => 1), $this->tables_identifiers[$product_table].' = ' . $this->mg_product_id);
                }
                
                $this->update_item_stock($this->mg_product_id, array('sl_qty' => ''));
    
            }

            //procesamos relation
            if ($this->mg_edition == 'enterprise') {
                $relation_exist = $this->connection->fetchOne(
                    $this->connection->select()
                    ->from($catalog_product_relation_table, [new Expr('COUNT(*)')])
                    ->where('parent_id = ?', $this->mg_product_current_row_id)
                    ->where('child_id = ?', $this->mg_format_id)
                );
            } else {
                $relation_exist = $this->connection->fetchOne(
                    $this->connection->select()
                    ->from($catalog_product_relation_table, [new Expr('COUNT(*)')])
                    ->where('parent_id = ?', $this->mg_product_id)
                    ->where('child_id = ?', $this->mg_format_id)
                );
            }

            if ($relation_exist == 0){

                $query_insert = " INSERT INTO ".$catalog_product_relation_table."(`parent_id`,`child_id`) values (?,?);";

                if ($this->mg_edition == 'enterprise') {
                    $this->sl_connection_query($query_insert,array($this->mg_product_current_row_id, $this->mg_format_id));
                } else {
                    $this->sl_connection_query($query_insert,array($this->mg_product_id, $this->mg_format_id));
                }

            }

            //procesamos super_link 
            if ($this->mg_edition == 'enterprise') {
                $super_link_exist = $this->connection->fetchOne(
                    $this->connection->select()
                    ->from($catalog_product_super_link_table, [new Expr('COUNT(*)')])
                    ->where('parent_id = ?', $this->mg_product_current_row_id)
                    ->where('product_id = ?', $this->mg_format_id)
                );
            } else {
                $super_link_exist = $this->connection->fetchOne(
                    $this->connection->select()
                    ->from($catalog_product_super_link_table, [new Expr('COUNT(*)')])
                    ->where('parent_id = ?', $this->mg_product_id)
                    ->where('product_id = ?', $this->mg_format_id)
                );
            }

            if ($super_link_exist == 0){

                $query_insert = " INSERT INTO ".$catalog_product_super_link_table."(`parent_id`,`product_id`) values (?,?);";
                
                if ($this->mg_edition == 'enterprise') {
                    $this->sl_connection_query($query_insert,array($this->mg_product_current_row_id, $this->mg_format_id));
                } else {
                    $this->sl_connection_query($query_insert,array($this->mg_product_id, $this->mg_format_id));
                }

            }

        }

    }

    /**
     * Function to get media field value
     * @param string $type             type of table
     * @param string $field_name       field name to extact media from data
     * @param array $data              array containing media data
     * @return array or boolean         array or media values
     */
    private function get_media_field_value($type, $field_name, $data){
        
        $media = [];

        if (in_array($field_name, $this->media_field_names[$type])){
            foreach ($data as $hash) {
                foreach ($hash as $file) {
                    array_push($media, $file);
                }
            }
        }

        if (!empty($media)){
            return $media;
        }else{
            return false;
        }

    }

    /**
     * Function to store media data from Sales Layer product additional attributes into variable.
     * @return void
     */
    private function load_media_field_names(){
        
        $data_schema = json_decode($this->sl_data_schema, 1);

        foreach ($data_schema as $type => $type_schema) {
            
            foreach ($type_schema['fields'] as $field_name => $field_info) {
                
                if ($field_info['type'] == 'image'){

                    $this->media_field_names[$type][] = $field_name;

                }

            }

        }

    }

    /**
     * Function to organize Sales Layer tables if they're multilingual.
     * @param array $tables                tables to organize
     * @param array $tableStructure        structure of the tables
     * @return array $tables                tables organized
     */
    private function organizeTablesIndex($tables, $tableStructure){
        
        foreach ($tableStructure as $keyStruct => $fieldStruct) {
            if (isset($fieldStruct['multilingual_name'])){
                foreach ($tables as $keyTab => $fieldTable) {
                    if (array_key_exists($fieldStruct['multilingual_name'], $fieldTable['data'])){
                        $tables[$keyTab]['data'][$keyStruct] = $tables[$keyTab]['data'][$fieldStruct['multilingual_name']];
                        unset($tables[$keyTab]['data'][$fieldStruct['multilingual_name']]);
                    }
                }   
            }
        }
    
        return $tables;
    
    }

    /**
     * Function to check ir the url exists.
     * @param string $url      of the image
     * @return boolean            if the url exists
     */
    private function url_exists ($url) {

        $time_ini_url_exists = microtime(1);

        $handle = @fopen($url, 'r');

        if ($handle === false) { 
            return false; 
        }

        fclose($handle);

        if ($this->sl_DEBBUG > 2) $this->debbug('## time_url_exists: ', 'timer', (microtime(1) - $time_ini_url_exists));
        
        return true;
    }

    /**
     * Function to store the url image.
     * @param string $image_url         image url of the image
     * @param string $path_base         path base of the image
     * @param string $returnFilepath    return file name with or without path
     * @return string                   file name with or withotu local path
     */
    private function prepareImage ($image_url, $path_base, $returnFilepath = true) {

        $time_ini_prepare_image = microtime(1);
        
        //Replace https to http.
        $image_url  = str_replace('https://', 'http://', $image_url); 

        if ($this->sl_DEBBUG > 2) $this->debbug(" > Importing image: $image_url");

        $image_url_info = pathinfo($image_url);

        if (strpos($image_url, '%') !== false) {

            $fileinfo_filename = rawurldecode($image_url_info['filename']);

        }else{

            $fileinfo_filename = $image_url_info['filename'];

        }

        $filename = preg_replace('/[^a-z0-9_\\-\\.]+/i', '_', $fileinfo_filename).'.'.$image_url_info['extension'];

        $time_ini_get_contents = microtime(1);
        
        $image_content_str = @file_get_contents(trim($image_url));

        if (!$image_content_str) {

            if (strpos($image_url, '%') !== false) {

                $image_content_str = @file_get_contents(trim($image_url_info['dirname'].'/'.rawurldecode($image_url_info['basename'])));

            }else{

                $image_content_str = @file_get_contents(trim($image_url_info['dirname'].'/'.rawurlencode($image_url_info['basename'])));

            }

        }

        if ($this->sl_DEBBUG > 2) $this->debbug('## time_get_contents: ', 'timer', (microtime(1) - $time_ini_get_contents));

        if ($image_content_str) {

            if (!file_exists($path_base)) {
                mkdir($path_base, 0777, true);
            }

            $filepath  = $path_base.$filename;
            //Store the image from external url to the temp storage folder.
            $time_ini_put_contents = microtime(1);
            file_put_contents($filepath, $image_content_str);
            chmod($filepath, 0777);
            if ($this->sl_DEBBUG > 2) $this->debbug('## time_put_contents: ', 'timer', (microtime(1) - $time_ini_put_contents));

            if ($this->sl_DEBBUG > 2) $this->debbug(" Image saved in: $filepath");

            if ($this->sl_DEBBUG > 2) $this->debbug('## time_prepare_image: ', 'timer', (microtime(1) - $time_ini_prepare_image));
            return ($returnFilepath) ? $filepath : $filename;
        
        }

        if ($this->sl_DEBBUG > 2) $this->debbug('## time_prepare_image: ', 'timer', (microtime(1) - $time_ini_prepare_image));
        return null;
    }

    /**
     * Function to find the product id associated to the Sales Layer product id.
     * @param int $saleslayer_id                Sales Layer product id
     * @param int $store_view_id                store view id to search 
     * @return int $product_id                  Magento product id
     */
    private function find_saleslayer_product_id_db($saleslayer_id, $store_view_id = 0) {

        $product_table = $this->getTable('catalog_product_entity');
        $product_saleslayer_id_table = $this->getTable('catalog_product_entity_' . $this->product_saleslayer_id_attribute_backend_type);
        $product_saleslayer_comp_id_table = $this->getTable('catalog_product_entity_' . $this->product_saleslayer_comp_id_attribute_backend_type);
        $product_saleslayer_format_id_table = $this->getTable('catalog_product_entity_' . $this->product_saleslayer_format_id_attribute_backend_type);

        $products_data = $this->connection->fetchAll(
            $this->connection->select()
                ->from(
                   ['p1' => $product_saleslayer_id_table],
                    [$this->tables_identifiers[$product_saleslayer_id_table] => 'p1.'.$this->tables_identifiers[$product_saleslayer_id_table],
                    'saleslayer_id' => 'p1.value']
                )
                ->where('p1.attribute_id' . ' = ?', $this->product_saleslayer_id_attribute)
                ->where('p1.value' . ' = ?', $saleslayer_id)
                ->where('p1.store_id' . ' = ?', $store_view_id)
                ->joinLeft(
                    ['p2' => $product_saleslayer_comp_id_table], 
                    'p1.'.$this->tables_identifiers[$product_saleslayer_id_table].' = p2.'.$this->tables_identifiers[$product_saleslayer_comp_id_table].' AND p1.store_id = p2.store_id AND p2.attribute_id = '.$this->product_saleslayer_comp_id_attribute,
                    ['saleslayer_comp_id' => 'p2.value']
                )
                ->joinLeft(
                    ['p3' => $product_saleslayer_format_id_table], 
                    'p1.'.$this->tables_identifiers[$product_saleslayer_id_table].' = p3.'.$this->tables_identifiers[$product_saleslayer_format_id_table].' AND p1.store_id = p3.store_id AND p3.attribute_id = '.$this->product_saleslayer_format_id_attribute,
                    ['saleslayer_format_id' => 'p3.value']
                )
                ->joinRight(
                    ['p4' => $product_table], 
                    'p1.'.$this->tables_identifiers[$product_saleslayer_id_table].' = p4.'.$this->tables_identifiers[$product_table],
                    ['entity_id']
                )
                ->group('p1.'.$this->tables_identifiers[$product_saleslayer_id_table])
        );

        if (!empty($products_data)){

            $product_id = $product_id_temp = '';
            
            foreach ($products_data as $product_data) {    

                if (isset($product_data['saleslayer_format_id']) && !in_array($product_data['saleslayer_format_id'], array(0, '', null))){

                    continue;

                }

                if (isset($product_data['saleslayer_comp_id'])){

                    $product_saleslayer_comp_id = $product_data['saleslayer_comp_id'];
                
                }else{

                    $product_saleslayer_comp_id = '';

                }

                if (!in_array($product_saleslayer_comp_id, array(0, '', null))){

                    if ($product_saleslayer_comp_id != $this->comp_id){
                
                        //The product belongs to another company.
                        continue;

                    }else{

                        //The product matches.
                        $product_id = $product_data['entity_id'];
                        break;
                        
                    }

                }else{

                    //The product matches the identificator and it's without company.
                    $product_id_temp = $product_data['entity_id'];
                    continue;

                }

            }

            if ($product_id == '' && $product_id_temp != ''){

                $product_id = $product_id_temp;

                if ($this->mg_edition == 'enterprise'){
                
                    $this->mg_product_row_ids = $this->getEntityRowIds($product_id, 'product');
                    $this->mg_product_current_row_id = $this->getEntityCurrentRowId($product_id, 'product');

                }

                $this->mg_product_id = $product_id;

                //Updating SL company credentials
                $sl_credentials = array('saleslayer_comp_id' => $this->comp_id);

                foreach ($this->mg_product_row_ids as $mg_product_row_id) {
                    
                    $this->setValues($mg_product_row_id, 'catalog_product_entity', $sl_credentials, $this->product_entity_type_id, $store_view_id, false, false, $this->mg_product_row_ids);

                }
             
            }

            if ($product_id != ''){

                $this->mg_product_id = $product_id;

                if ($this->mg_edition == 'enterprise'){

                    $this->mg_product_row_ids = $this->getEntityRowIds($product_id, 'product');
                    $this->mg_product_current_row_id = $this->getEntityCurrentRowId($product_id, 'product');

                }

                return $product_id;

            }

        }

        return null;

    }


    /**
     * Function to find the product format id associated to the Sales Layer product format id.
     * @param int $saleslayer_id                Sales Layer product id
     * @param int $saleslayer_format_id         Sales Layer product format id
     * @param int $store_view_id                store view id to search 
     * @return int $format_id                   Magento product format id
     */
    private function find_saleslayer_format_id_db($saleslayer_id = null, $saleslayer_format_id = 0, $store_view_id = 0) {

        if ($saleslayer_format_id == 0) return null;

        $product_table = $this->getTable('catalog_product_entity');
        $product_saleslayer_id_table = $this->getTable('catalog_product_entity_' . $this->product_saleslayer_id_attribute_backend_type);
        $product_saleslayer_comp_id_table = $this->getTable('catalog_product_entity_' . $this->product_saleslayer_comp_id_attribute_backend_type);
        $product_saleslayer_format_id_table = $this->getTable('catalog_product_entity_' . $this->product_saleslayer_format_id_attribute_backend_type);

        $formats_data = $this->connection->fetchAll(
            $this->connection->select()
                ->from(
                   ['p1' => $product_saleslayer_format_id_table],
                    [$this->tables_identifiers[$product_saleslayer_format_id_table] => 'p1.'.$this->tables_identifiers[$product_saleslayer_format_id_table],
                    'saleslayer_format_id' => 'p1.value']
                )
                ->where('p1.attribute_id' . ' = ?', $this->product_saleslayer_format_id_attribute)
                ->where('p1.value' . ' = ?', $saleslayer_format_id)
                ->where('p1.store_id' . ' = ?', $store_view_id)
                ->joinLeft(
                    ['p2' => $product_saleslayer_comp_id_table], 
                    'p1.'.$this->tables_identifiers[$product_saleslayer_format_id_table].' = p2.'.$this->tables_identifiers[$product_saleslayer_comp_id_table].' AND p1.store_id = p2.store_id AND p2.attribute_id = '.$this->product_saleslayer_comp_id_attribute,
                    ['saleslayer_comp_id' => 'p2.value']
                )
                ->joinLeft(
                    ['p3' => $product_saleslayer_id_table], 
                    'p1.'.$this->tables_identifiers[$product_saleslayer_format_id_table].' = p3.'.$this->tables_identifiers[$product_saleslayer_id_table].' AND p1.store_id = p3.store_id AND p3.attribute_id = '.$this->product_saleslayer_id_attribute,
                    ['saleslayer_id' => 'p3.value']
                )
                ->joinRight(
                    ['c4' => $product_table], 
                    'p1.'.$this->tables_identifiers[$product_saleslayer_format_id_table].' = c4.'.$this->tables_identifiers[$product_table],
                    ['entity_id']
                )
                ->group('p1.'.$this->tables_identifiers[$product_saleslayer_format_id_table])
        );

        if (!empty($formats_data)){

            $format_id = $format_id_temp = '';
            
            foreach ($formats_data as $format_data) {    

                if (null !== $saleslayer_id) {
                    
                    if (!isset($format_data['saleslayer_id']) || (isset($format_data['saleslayer_id']) && $format_data['saleslayer_id'] != $saleslayer_id)){
                    
                        continue;

                    } 

                }

                if (isset($format_data['saleslayer_comp_id'])){

                    $format_saleslayer_comp_id = $format_data['saleslayer_comp_id'];
                
                }else{

                    $format_saleslayer_comp_id = '';

                }

                if (!in_array($format_saleslayer_comp_id, array(0, '', null))){

                    if ($format_saleslayer_comp_id != $this->comp_id){
            
                        //The product format belongs to another company.
                        continue;

                    }else{
            
                        //The product format matches;
                        $format_id = $format_data['entity_id'];
                        break;
                        
                    }

                }else{

                    //The product format matches the identificator and it's without company.
                    $format_id_temp = $format_data['entity_id'];
                    continue;

                }

            }

            if ($format_id == '' && $format_id_temp != ''){

                $format_id = $format_id_temp;

                if ($this->mg_edition == 'enterprise'){
                
                    $this->mg_format_row_ids = $this->getEntityRowIds($format_id, 'product');
                    $this->mg_format_current_row_id = $this->getEntityCurrentRowId($format_id, 'product');

                }

                $this->mg_format_id = $format_id;
                
                //Updating SL company credentials
                $sl_credentials = array('saleslayer_comp_id' => $this->comp_id);
                if (null !== $saleslayer_id) { $sl_credentials['saleslayer_id'] = $saleslayer_id; }

                foreach ($this->mg_format_row_ids as $mg_format_row_id) {
                    
                    $this->setValues($mg_format_row_id, 'catalog_product_entity', $sl_credentials, $this->product_entity_type_id, $store_view_id, false, false, $this->mg_format_row_ids);

                }

            }

            if ($format_id != ''){

                $this->mg_format_id = $format_id;

                if ($this->mg_edition == 'enterprise'){

                    $this->mg_format_row_ids = $this->getEntityRowIds($format_id, 'product');
                    $this->mg_format_current_row_id = $this->getEntityCurrentRowId($format_id, 'product');

                }

                return $format_id;

            }

        }

        return null;

    }

    /**
     * Function to check if SKU already exists on another product or product format than the one we're synchronizing.
     * @param string $type                     product or product format
     * @param string $sl_sku                   SKU of the product that we want to check
     * @param int $saleslayer_id               id of the product that you want to check 
     * @param int $saleslayer_format_id        id of the product format that you want to check
     * @param int $store_view_id               store view id to check
     * @return boolean                         if the SKU already exists on another product
     */
    private function check_duplicated_sku_db($type, $sl_sku, $saleslayer_id, $saleslayer_format_id = 0, $store_view_id = 0){

        $product_table = $this->getTable('catalog_product_entity');
        $product_saleslayer_id_table = $this->getTable('catalog_product_entity_' . $this->product_saleslayer_id_attribute_backend_type);
        $product_saleslayer_comp_id_table = $this->getTable('catalog_product_entity_' . $this->product_saleslayer_comp_id_attribute_backend_type);
        $product_saleslayer_format_id_table = $this->getTable('catalog_product_entity_' . $this->product_saleslayer_format_id_attribute_backend_type);

        $product_data = $this->connection->fetchRow(
            $this->connection->select()
                ->from(
                   ['p1' => $product_table],
                    ['entity_id' => 'p1.entity_id',
                    'sku' => 'p1.sku']
                )
                ->where('p1.sku' . ' = ?', $sl_sku)
                ->joinLeft(
                    ['p2' => $product_saleslayer_id_table], 
                    'p1.'.$this->tables_identifiers[$product_table].' = p2.'.$this->tables_identifiers[$product_saleslayer_id_table].' AND p2.store_id = '.$store_view_id.' AND p2.attribute_id = '.$this->product_saleslayer_id_attribute,
                    ['saleslayer_id' => 'p2.value']
                )
                ->joinLeft(
                    ['p3' => $product_saleslayer_comp_id_table], 
                    'p1.'.$this->tables_identifiers[$product_table].' = p3.'.$this->tables_identifiers[$product_saleslayer_comp_id_table].' AND p3.store_id = '.$store_view_id.' AND p3.attribute_id = '.$this->product_saleslayer_comp_id_attribute,
                    ['saleslayer_comp_id' => 'p3.value']
                )
                ->joinLeft(
                    ['p4' => $product_saleslayer_format_id_table], 
                    'p1.'.$this->tables_identifiers[$product_table].' = p4.'.$this->tables_identifiers[$product_saleslayer_format_id_table].' AND p4.store_id = '.$store_view_id.' AND p4.attribute_id = '.$this->product_saleslayer_format_id_attribute,
                    ['saleslayer_format_id' => 'p4.value']
                )
                ->group('p1.'.$this->tables_identifiers[$product_table])
                ->limit(1)
        );

        if (!empty($product_data)){
            
            $existing_product_saleslayer_id = $existing_product_saleslayer_comp_id = $existing_product_saleslayer_format_id = 0;
            if (isset($product_data['saleslayer_id'])){ $existing_product_saleslayer_id = $product_data['saleslayer_id']; }
            if (in_array($existing_product_saleslayer_id, array('', null))){ $existing_product_saleslayer_id = 0; }
            if (isset($product_data['saleslayer_comp_id'])){ $existing_product_saleslayer_comp_id = $product_data['saleslayer_comp_id']; }
            if (in_array($existing_product_saleslayer_comp_id, array('', null))){ $existing_product_saleslayer_comp_id = 0; }
            if (isset($product_data['saleslayer_format_id'])){ $existing_product_saleslayer_format_id = $product_data['saleslayer_format_id']; }
            if (in_array($existing_product_saleslayer_format_id, array('', null))){ $existing_product_saleslayer_format_id = 0; }
            
            if (($type == 'product' && null !== $this->mg_product_id) || ($type == 'product_format' && null !== $this->mg_format_id)){

                if (($type == 'product' && $product_data['entity_id'] !== $this->mg_product_id) || ($type == 'product_format' && $product_data['entity_id'] !== $this->mg_format_id)){
             
                    if ($type == 'product'){

                        $this->debbug("## Error. The product with SKU ".$sl_sku." hasn't been synchronized because the same SKU is already in use.");

                    }else{

                        $this->debbug("## Error. The product format with SKU ".$sl_sku." hasn't been synchronized because the same SKU is already in use.");

                    }

                    return true;
                
                }

            }else{

                if (($type == 'product' && $existing_product_saleslayer_id == 0 && $existing_product_saleslayer_comp_id == 0) || ($type == 'product_format' && $existing_product_saleslayer_id == 0 && $existing_product_saleslayer_comp_id == 0 && $existing_product_saleslayer_format_id == 0)){

                    if ($type == 'product'){

                        $this->debbug('Product found with same SKU '.$sl_sku.' and MG ID: '.$product_data['entity_id'].' without SL credentials assigned.');
                    
                    }else{

                        $this->debbug('Product format found with same SKU '.$sl_sku.' and MG ID: '.$product_data['entity_id'].' without SL credentials assigned.');
                    
                    }

                }else{

                    if ($saleslayer_id != $existing_product_saleslayer_id || $this->comp_id != $existing_product_saleslayer_comp_id || $saleslayer_format_id != $existing_product_saleslayer_format_id){

                        if ($type == 'product'){

                            $this->debbug("## Error. The product with SKU ".$sl_sku." hasn't been synchronized because the same SKU is already in use.");

                        }else{

                            $this->debbug("## Error. The product format with SKU ".$sl_sku." hasn't been synchronized because the same SKU is already in use.");

                        }

                        return true;

                    }

                }

            }

        }

        return false;

    }

    /**
     * Function to delete a stored category.
     * @param string $sl_id         Sales Layer category id to delete
     * @return string               category deleted or not
     */
    public function delete_stored_category_db ($sl_id) {

        $this->debbug('Disabling category with SL id: '.$sl_id.' comp_id: '.$this->comp_id);
        
        $this->find_saleslayer_category_id_db($sl_id);
        
        if (null !== $this->mg_category_id){

            $mg_category_fields = array('name' => '');

            $category_table = $this->getTable('catalog_category_entity');

            $mg_category_fields = $this->getValues($this->mg_category_current_row_id, 'catalog_category_entity', $mg_category_fields, $this->category_entity_type_id);
                
            $deletedMessage = "The category with title ".$mg_category_fields['name'];            

            try{

                $delete_category = true;
            
                if (isset($this->sl_multiconn_table_data['category'][$sl_id]) && !empty($this->sl_multiconn_table_data['category'][$sl_id]['sl_connectors'])){

                    $conn_found = array_search($this->processing_connector_id, $this->sl_multiconn_table_data['category'][$sl_id]['sl_connectors']);
                    
                    if (is_numeric($conn_found)){

                        unset($this->sl_multiconn_table_data['category'][$sl_id]['sl_connectors'][$conn_found]);

                        if (empty($this->sl_multiconn_table_data['category'][$sl_id]['sl_connectors'])){
                            
                            $query_delete = " DELETE FROM ".$this->saleslayer_multiconn_table." WHERE id = ? ";
            
                            $this->sl_connection_query($query_delete, array($this->sl_multiconn_table_data['category'][$sl_id]['id']));

                        }else{

                            $new_connectors_data = json_encode($this->sl_multiconn_table_data['category'][$sl_id]['sl_connectors']);
            
                            $query_update  = " UPDATE ".$this->saleslayer_multiconn_table." SET sl_connectors = ?  WHERE id = ? ";
            
                            $this->sl_connection_query($query_update, array($new_connectors_data, $this->sl_multiconn_table_data['category'][$sl_id]['id']));

                            $delete_category = false;
                        }

                    }else{

                        $delete_category = false;

                    }

                }

                if (!$delete_category){

                    $this->debbug("## Error. ".$deletedMessage. " couldn't been disabled because is being used by another connector.");
                    return 'item_deleted';

                }else{

                    $empty_values = array('is_active' => 0, 'saleslayer_id' => '', 'saleslayer_comp_id' => '');
                
                    foreach ($this->mg_category_row_ids as $mg_category_row_id) {
                        
                        $this->setValues($mg_category_row_id, 'catalog_category_entity', $empty_values, $this->category_entity_type_id, 0, false, false, $this->mg_category_row_ids);

                    }

                    if (!$this->category_enabled_attribute_is_global){

                        if (!empty($this->all_store_view_ids)){

                            foreach ($this->all_store_view_ids as $store_view_id){

                                if ($store_view_id == 0){ 
                                    continue; 
                                }
                                
                                $empty_values = array('is_active' => 0);
                                
                                foreach ($this->mg_category_row_ids as $mg_category_row_id) {

                                    $this->setValues($mg_category_row_id, 'catalog_category_entity', $empty_values, $this->category_entity_type_id, $store_view_id, false, false, $this->mg_category_row_ids);

                                }

                            }

                        }

                    }
                    
                    $this->debbug($deletedMessage." has been disabled.");
       
                }

            }catch(\Exception $e){

                $this->debbug("## Error. ".$deletedMessage. " couldn't been disabled - ".$e->getMessage());
                return 'item_not_deleted';

            }

            if ($delete_category){

                try{

                    //Reorganize categories under this
                    $is_active_attribute = $this->getAttribute('is_active', $this->category_entity_type_id);

                    if (empty($is_active_attribute)){
                        return 'item_deleted';
                    }

                    if (!isset($is_active_attribute[\Magento\Eav\Api\Data\AttributeInterface::BACKEND_TYPE])) {
                        return 'item_deleted';
                    }

                    $category_table = $this->getTable('catalog_category_entity');
                    $category_is_active_table = $this->getTable('catalog_category_entity_' . $is_active_attribute[\Magento\Eav\Api\Data\AttributeInterface::BACKEND_TYPE]);

                    $categories_under_deleted_parent = [];
                    
                    if (null !== $category_is_active_table) {

                        $categories_under_deleted_parent = $this->connection->fetchAll(
                            $this->connection->select()
                                ->from(['c1' => $category_table])
                                ->where('c1.path LIKE "%/'.$this->mg_category_id.'/%"')
                                ->joinLeft(
                                    ['c2' => $category_is_active_table], 
                                    'c1.'.$this->tables_identifiers[$category_table].' = c2.'.$this->tables_identifiers[$category_is_active_table].' AND c2.store_id = 0 AND c2.value = 1 AND c2.attribute_id = '.$is_active_attribute[\Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_ID],
                                    ['is_active' => 'c2.value']
                                )
                        );

                    }

                    if (!empty($categories_under_deleted_parent)){
                        
                        foreach ($categories_under_deleted_parent as $category_under_deleted_parent) {
                            
                            $path_ids = explode('/', $category_under_deleted_parent['path']);
                            $new_path = $parent_id = '';

                            foreach ($path_ids as $path_id){
                                
                                if ($path_id == $this->mg_category_id){

                                    continue;

                                }

                                $new_path .= $path_id;
                            
                                if ($path_id != end($path_ids)){ 

                                    $new_path .= '/'; 
                                    $parent_id = $path_id;

                                }

                            }

                            $position = $this->connection->fetchOne(
                                $this->connection->select()
                                    ->from(
                                        $category_table,
                                        [new Expr('MAX(`position`) + 1')]
                                    )
                                    ->where('parent_id = ?', $category_under_deleted_parent['parent_id'])
                                    ->group('parent_id')
                            );

                            if (!$position) $position = 0;

                            $new_level = $category_under_deleted_parent['level'] - 1;
                            
                            $this->connection->update($category_table, ['path' => $new_path, 'parent_id' => $parent_id, 'position' => $position, 'level' => $new_level], $this->tables_identifiers[$category_table].' = ' . $category_under_deleted_parent[$this->tables_identifiers[$category_table]]);

                        }

                        $incorrect_categories_children_sql = " SELECT p.".$this->tables_identifiers[$category_table].", p.path, p.children_count, COUNT(c.entity_id) AS correct_children_count, ".
                                                            " COUNT(c.entity_id) - p.children_count AS child_diff ".
                                                            " FROM ".$category_table." p ".
                                                            " LEFT JOIN ".$category_table." c ON c.path LIKE CONCAT(p.path,'/%') ".
                                                            " WHERE 1 ".
                                                            " GROUP BY p.".$this->tables_identifiers[$category_table].
                                                            " HAVING correct_children_count != p.children_count";

                        $incorrect_categories_children = $this->connection->fetchAll($incorrect_categories_children_sql);

                        if (!empty($incorrect_categories_children)){

                            foreach ($incorrect_categories_children as $incorrect_category_children) {
                                
                                try{

                                    $this->connection->update($category_table, ['children_count' => $incorrect_category_children['correct_children_count']], $this->tables_identifiers[$category_table].' = ' . $incorrect_category_children[$this->tables_identifiers[$category_table]]);

                                }catch(\Exception $e){

                                    $this->debbug('## Error. Correcting category children: '.print_r($e->getMessage(),1));

                                }

                            }

                        }

                    }

                }catch(\Exception $e){

                    $this->debbug("## Error. ".$deletedMessage. " couldn't been reorganized - ".$e->getMessage());
                    return 'item_deleted';

                }
                
            }

            return 'item_deleted';

        }else{

            $this->debbug("## Notice. The category doesn't exist.");
            return 'item_not_found';

        }
 
    }

    /**
     * Function to delete a stored product.
     * @param string $sl_id         Sales Layer product id to delete
     * @return string               product deleted or not
     */
    public function delete_stored_product_db($sl_id){

        $this->debbug('Disabling product with SL id: '.$sl_id.' comp_id: '.$this->comp_id);
       
        $this->find_saleslayer_product_id_db($sl_id);
                 
        if (null !== $this->mg_product_id){
        
            $mg_product_core_data = $this->get_product_core_data($this->mg_product_id);

            $mg_product_fields = array('name' => '');
            $mg_product_fields = $this->getValues($this->mg_product_current_row_id, 'catalog_product_entity', $mg_product_fields, $this->product_entity_type_id);
            
            $deletedMessage = "The product with SKU ".$mg_product_core_data['sku']." and title: ".$mg_product_fields['name'];         

            try{

                $delete_product = true;

                if (isset($this->sl_multiconn_table_data['product'][$sl_id]) && !empty($this->sl_multiconn_table_data['product'][$sl_id]['sl_connectors'])){

                    $conn_found = array_search($this->processing_connector_id, $this->sl_multiconn_table_data['product'][$sl_id]['sl_connectors']);

                    if (is_numeric($conn_found)){

                        unset($this->sl_multiconn_table_data['product'][$sl_id]['sl_connectors'][$conn_found]);

                        if (empty($this->sl_multiconn_table_data['product'][$sl_id]['sl_connectors'])){

                            $query_delete = " DELETE FROM ".$this->saleslayer_multiconn_table." WHERE id = ?";

                            $this->sl_connection_query($query_delete, array($this->sl_multiconn_table_data['product'][$sl_id]['id']));

                        }else{

                            $new_connectors_data = json_encode($this->sl_multiconn_table_data['product'][$sl_id]['sl_connectors']);

                            $query_update  = " UPDATE ".$this->saleslayer_multiconn_table." SET sl_connectors = ?  WHERE id = ? ";

                            $this->sl_connection_query($query_update, array($new_connectors_data, $this->sl_multiconn_table_data['product'][$sl_id]['id']));

                            $delete_product = false;

                        }

                    }else{

                        $delete_product = false;

                    }

                }

                if (!$delete_product){

                    $this->debbug("## Error. ".$deletedMessage. " couldn't been disabled because is being used by another connector.");
                
                }else{

                    $empty_values = array('status' => $this->status_disabled, 'saleslayer_id' => '', 'saleslayer_comp_id' => '');
                  
                    foreach ($this->mg_product_row_ids as $mg_product_row_id) {
                        
                        $this->setValues($mg_product_row_id, 'catalog_product_entity', $empty_values, $this->product_entity_type_id, 0, false, false, $this->mg_product_row_ids);

                    }

                    if (!$this->product_enabled_attribute_is_global){

                        if (!empty($this->all_store_view_ids)){

                            foreach ($this->all_store_view_ids as $store_view_id){

                                if ($store_view_id == 0){ 
                                    continue; 
                                }
                                
                                $empty_values = array('status' => $this->status_disabled);
                                
                                foreach ($this->mg_product_row_ids as $mg_product_row_id) {
                                    
                                    $this->setValues($mg_product_row_id, 'catalog_product_entity', $empty_values, $this->product_entity_type_id, $store_view_id, false, false, $this->mg_product_row_ids);

                                }

                            }

                        }

                    }

                    $this->debbug($deletedMessage." has been disabled.");
                    
                }

                return 'item_deleted';
                
            }catch(\Exception $e){

                $this->debbug("## Error. ".$deletedMessage. " couldn't been disabled - ".$e->getMessage());
                return 'item_not_deleted';

            }

        }else{

            $this->debbug("## Notice. The product doesn't exist.");
            return 'item_not_found';

        }

    }

    /**
     * Function to delete a stored product format.
     * @param string $sl_id         Sales Layer format id to delete
     * @return string               format deleted or not
     */
    public function delete_stored_product_format_db($sl_id){

        $this->debbug('Disabling product format with SL id: '.$sl_id.' comp_id: '.$this->comp_id);
        $mg_format_id = $this->find_saleslayer_format_id_db(null, $sl_id);
                
        if (null !== $mg_format_id){

            $mg_format_core_data = $this->get_product_core_data($mg_format_id);

            $mg_format_fields = array('name' => '');
            $mg_format_fields = $this->getValues($mg_format_id, 'catalog_product_entity', $mg_format_fields, $this->product_entity_type_id);

            $deletedMessage = "The product format with SKU ".$mg_format_core_data['sku']." and title: ".$mg_format_fields['name'];      

            try{

                $delete_format = true;

                if (isset($this->sl_multiconn_table_data['format'][$sl_id]) && !empty($this->sl_multiconn_table_data['format'][$sl_id]['sl_connectors'])){

                    $conn_found = array_search($this->processing_connector_id, $this->sl_multiconn_table_data['format'][$sl_id]['sl_connectors']);

                    if (is_numeric($conn_found)){

                        unset($this->sl_multiconn_table_data['format'][$sl_id]['sl_connectors'][$conn_found]);

                        if (empty($this->sl_multiconn_table_data['format'][$sl_id]['sl_connectors'])){

                            $query_delete = " DELETE FROM ".$this->saleslayer_multiconn_table." WHERE id = ?";

                            $this->sl_connection_query($query_delete, array($this->sl_multiconn_table_data['format'][$sl_id]['id']));

                        }else{

                            $new_connectors_data = json_encode($this->sl_multiconn_table_data['format'][$sl_id]['sl_connectors']);

                            $query_update  = " UPDATE ".$this->saleslayer_multiconn_table." SET sl_connectors =  ?  WHERE id =  ? ";

                            $this->sl_connection_query($query_update, array($new_connectors_data, $this->sl_multiconn_table_data['format'][$sl_id]['id']));

                            $delete_format = false;

                        }
                    
                    }else{

                        $delete_format = false;

                    }

                }

                if (!$delete_format){

                    $this->debbug("## Error. ".$deletedMessage. " couldn't been disabled because is being used by another connector.");
                
                }else{

                    $empty_values = array('status' => $this->status_disabled, 'saleslayer_id' => '', 'saleslayer_comp_id' => '', 'saleslayer_format_id' => '');
                    $this->setValues($mg_format_id, 'catalog_product_entity', $empty_values, $this->product_entity_type_id, 0);
                    
                    if (!$this->product_enabled_attribute_is_global){

                        if (!empty($this->all_store_view_ids)){

                            foreach ($this->all_store_view_ids as $store_view_id){

                                if ($store_view_id == 0){ 
                                    continue; 
                                }
                                
                                $empty_values = array('status' => $this->status_disabled);
                                $this->setValues($mg_format_id, 'catalog_product_entity', $empty_values, $this->product_entity_type_id, $store_view_id, false, false, $this->all_store_view_ids);
                                
                            }

                        }

                    }

                    $catalog_product_relation_table = $this->getTable('catalog_product_relation');

                    $relation_parent_id = $this->connection->fetchOne(
                        $this->connection->select()
                            ->from(
                                $catalog_product_relation_table,
                                ['parent_id']
                            )
                            ->where('child_id' . ' = ?', $mg_format_id)
                            ->group('parent_id')
                    );

                    if (!$relation_parent_id){

                        //No está asignado a ningún producto, terminamos

                    }else{

                        //delete relation
                        $this->connection->delete(
                            $catalog_product_relation_table,
                            ['parent_id = ?' => $relation_parent_id, 'child_id = ?' => $mg_format_id]
                        );

                        $catalog_product_super_link_table = $this->getTable('catalog_product_super_link');
                        //delete link relation
                        $this->connection->delete(
                            $catalog_product_super_link_table,
                            ['parent_id = ?' => $relation_parent_id, 'product_id = ?' => $mg_format_id]
                        );

                        $are_there_other_relations = $this->connection->fetchOne(
                            $this->connection->select()
                            ->from($catalog_product_relation_table, [new Expr('COUNT(*)')])
                            ->where('parent_id = ?', $relation_parent_id)
                        );
                        
                        if ($are_there_other_relations > 0){

                            //Hay otras relaciones, terminamos

                        }else{

                            $catalog_product_super_attribute_table = $this->getTable('catalog_product_super_attribute');

                            $product_super_attribute_ids_filter = $this->connection->fetchOne(
                                $this->connection->select()
                                    ->from(
                                        [$catalog_product_super_attribute_table],
                                        [new Expr('GROUP_CONCAT(product_super_attribute_id SEPARATOR ",")')]
                                    )
                                    ->where('product_id' . ' = ?', $relation_parent_id)
                            );
                         
                            if (null === $product_super_attribute_ids_filter || $product_super_attribute_ids_filter == ''){
                                
                                //El producto no tiene atributos asociados, terminamos

                            }else{

                                $this->connection->delete(
                                    $catalog_product_super_attribute_table,
                                    ['product_id = ?' => $relation_parent_id]
                                );

                                $catalog_product_super_attribute_label_table = $this->getTable('catalog_product_super_attribute_label');
                                   
                                $this->connection->delete(
                                    $catalog_product_super_attribute_label_table,
                                    ['product_super_attribute_id IN ('.$product_super_attribute_ids_filter.')']
                                );

                                //cambiamos producto a simple
                                $product_table = $this->getTable('catalog_product_entity');
                                $this->connection->update($product_table, array('type_id' => $this->product_type_simple, 'has_options' => 0, 'required_options' => 0), $this->tables_identifiers[$product_table].' = ' . $relation_parent_id);

                                $this->update_item_stock($relation_parent_id, array('sl_qty' => ''));

                            }

                        }

                    }

                    $this->debbug($deletedMessage." has been disabled.");

                }
                
                return 'item_deleted';
                
            }catch(\Exception $e){

                $this->debbug("## Error. ".$deletedMessage. " couldn't been disabled - ".$e->getMessage());
                return 'item_not_deleted';

            }

        }else{

            $this->debbug("## Notice. The product format doesn't exist.");
            return 'item_not_found';

        }

    }

    /**
     * Function to sort images by dimension.
     * @param array $img_a      first image to sort
     * @param array $img_b      second image to sort
     * @return array            comparative of the images
     */
    private function sortByDimension ($img_a, $img_b) {

        $area_a = $img_a['width'] * $img_a['height'];
        $area_b = $img_b['width'] * $img_b['height'];

        return strnatcmp($area_b, $area_a);
    }

    /**
     * Function to order an array of images.
     * @param array $array_img      images to order
     * @return array                array of ordered images
     */
    private function order_array_img ($array_img) {

        $has_ORG = false;
            
        if (isset($array_img['ORG'])){
        
            if (count($array_img) == 1){
        
                return $array_img;

            }
        
            $has_ORG = true;
            unset($array_img['ORG']);
        
        }
        
        if (!empty($array_img) && count($array_img) > 1){
    
            uasort($array_img, array($this, "sortByDimension"));

        }
        
        if ($has_ORG){
        
            $array_img = ['ORG' => []] + $array_img;
        
        }
        
        return $array_img;

    }

    /**
     * Function to load Sales Layer root Category.
     * @return void
     */
    private function loadSaleslayerRootCategory(){

        $name_attribute = $this->getAttribute('name', $this->category_entity_type_id);

        if (empty($name_attribute)){
            $this->debbug('## Error. Category name attribute does not exist, please correct this.');
            return false;
        }

        if (!isset($name_attribute[\Magento\Eav\Api\Data\AttributeInterface::BACKEND_TYPE])) {
            $this->debbug('## Error. Category name attribute does not have a backend type, please correct this.');
            return false;
        }

        $category_table = $this->getTable('catalog_category_entity');
        $category_name_table = $this->getTable('catalog_category_entity_' . $name_attribute[\Magento\Eav\Api\Data\AttributeInterface::BACKEND_TYPE]);
        
        if (null !== $category_name_table) {
        
            $sl_root_category_data = $this->connection->fetchRow(
                $this->connection->select()
                    ->from(
                       ['c1' => $category_name_table],
                        ['c1.'.$this->tables_identifiers[$category_name_table]]
                    )
                    ->where('c1.attribute_id' . ' = ?', $name_attribute[\Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_ID])
                    ->where('c1.value' . ' = ?', 'Sales Layer')
                    ->where('c1.store_id' . ' = ?', 0)
                    ->where('c2.parent_id' . ' = ?', 1)
                    ->joinLeft(
                        ['c2' => $category_table], 
                        'c1.'.$this->tables_identifiers[$category_name_table].' = c2.'.$this->tables_identifiers[$category_name_table],// and c2.parent_id = 1',
                        ['parent_id' => 'c2.parent_id',
                        'entity_id' => 'c2.entity_id']
                    )
                    ->group('c1.'.$this->tables_identifiers[$category_name_table])
                    ->limit(1)
            );

            if (!empty($sl_root_category_data)){

                $this->saleslayer_root_category_id = $sl_root_category_data['entity_id'];

            }

        }

        return true;

    }
    
    /**
     * Function to get product id by sku.
     * @param string $sl_sku                        product sku
     * @return int $product_data['entity_id']       product id or false
     */
    private function get_product_id_by_sku_db($sl_sku, $product_type = 'product'){

        $product_table = $this->getTable('catalog_product_entity');

        $product_data = $this->connection->fetchRow(
            $this->connection->select()
                ->from(
                   [$product_table],
                    ['entity_id' => 'entity_id',
                    'sku' => 'sku']
                )
                ->where('sku' . ' = ?', $sl_sku)
                ->limit(1)
        );

        if (!empty($product_data)){

            if ($product_type == 'product'){

                $this->mg_product_id = $product_data['entity_id'];
                
                if ($this->mg_edition == 'enterprise'){

                    $this->mg_product_row_ids = $this->getEntityRowIds($this->mg_product_id, 'product');
                    $this->mg_product_current_row_id = $this->getEntityCurrentRowId($this->mg_product_id, 'product');

                }

            }else{

                $this->mg_format_id = $product_data['entity_id'];

                if ($this->mg_edition == 'enterprise'){

                    $this->mg_format_row_ids = $this->getEntityRowIds($this->mg_format_id, 'product');
                    $this->mg_format_current_row_id = $this->getEntityCurrentRowId($this->mg_format_id, 'product');

                }

            }

            return $product_data['entity_id'];

        }

        if ($product_type == 'product'){
        
            $this->mg_product_id = null;

            if ($this->mg_edition == 'enterprise'){

                $this->mg_product_row_ids = null;
                $this->mg_product_current_row_id = null;

            }

        }else{

            $this->mg_format_id = null;

            if ($this->mg_edition == 'enterprise'){

                $this->mg_format_row_ids = null;
                $this->mg_format_current_row_id = null;

            }

        }

        return null;

    }

    /**
     * Function to reorganize categories by its parents
     * @param array $categories         data to reorganize
     * @return array $new_categories    reorganized data
     */
    private function reorganizeCategories($categories){
            
        $new_categories = [];

        if (count($categories) > 0){

            $counter = 0;
            $first_level = $first_clean = true;
            $categories_loaded = [];
            
            do{

                $level_categories = $this->getLevelCategories($categories, $categories_loaded, $first_level);
            
                if (!empty($level_categories)){

                    $counter = 0;
                    $first_level = false;

                    foreach ($categories as $keyCat => $category) {
                        
                        if (isset($level_categories[$category[$this->category_field_id]])){
                            
                            array_push($new_categories, $category);
                            $categories_loaded[$category[$this->category_field_id]] = 0;
                            unset($categories[$keyCat]);

                        }

                    }

                }else{

                    $counter++;

                }

                if ($counter == 3){
            
                    if ($first_clean && !empty($categories)){

                        $categories_not_loaded_ids = array_flip(array_column($categories, $this->category_field_id));
            
                        foreach ($categories as $keyCat => $category) {
                            
                            if (!is_array($category[$this->category_field_catalogue_parent_id])){
                            
                                $category_parent_ids = array($category[$this->category_field_catalogue_parent_id]);
                            
                            }else{
                            
                                $category_parent_ids = array($category[$this->category_field_catalogue_parent_id]);
                            
                            }

                            $has_any_parent = false;
                            
                            foreach ($category_parent_ids as $category_parent_id) {
                                
                                if (isset($categories_not_loaded_ids[$category_parent_id])){

                                    $has_any_parent = true;
                                    break;

                                } 

                            }

                            if (!$has_any_parent){

                                $category[$this->category_field_catalogue_parent_id] = 0;

                                array_push($new_categories, $category);
                                $categories_loaded[$category[$this->category_field_id]] = 0;
                                unset($categories[$keyCat]);

                                $counter = 0;
                                $first_level = $first_clean = false;

                            }

                        }

                    }else{

                        break;

                    }

                }

            }while (count($categories) > 0);    
        
        }

        return $new_categories;

    }

    /**
     * Function to get categories by its root level
     * @param array $categories             categories to obtain by level
     * @param array $categories_loaded      categories already loaded
     * @param boolean $first                first time checking this level
     * @return array $level_categories      categories that own to that level
     */
    private function getLevelCategories($categories, $categories_loaded, $first = false){

        $level_categories = [];

        if ($first){

            foreach ($categories as $category) {
                
                if (!is_array($category[$this->category_field_catalogue_parent_id]) && $category[$this->category_field_catalogue_parent_id] == 0){

                    $level_categories[$category[$this->category_field_id]] = 0;
                
                }

            }

        }else{

            foreach ($categories as $category) {
                
                if (!is_array($category[$this->category_field_catalogue_parent_id])){
                    $category_parent_ids = array($category[$this->category_field_catalogue_parent_id]);
                }else{
                    $category_parent_ids = array($category[$this->category_field_catalogue_parent_id]);
                }

                $parents_loaded = true;
                foreach ($category_parent_ids as $category_parent_id) {
                    
                    if (!isset($categories_loaded[$category_parent_id])){

                        $parents_loaded = false;
                        break;
                    } 
                }

                if ($parents_loaded){

                    $level_categories[$category[$this->category_field_id]] = 0;

                }

            }

        }

        return $level_categories;

    }

    /**
     * Function to validate if a text contains html tags, if not, adds line break tags to avoid auto-compress.
     * @param string $text_check        text to check
     * @return string                   original text or corrected
     */
    private function sl_check_html_text($text_check){
        
        if (is_array($text_check)){ 
            
            if (!empty($text_check)){

                $text_check = reset($text_check);
            }else{
                $text_check = '';

            }
        }

        if ($text_check === null || preg_match('/<[^<]+>/s', $text_check)){
        
            return $text_check;

        }else{

            return nl2br($text_check);

        }

    }

    /**
     * Function to validate status value and return true/false.
     * @param boolean/integer/string $value         value to check
     * @return boolean                              boolean value
     */
    private function SLValidateStatusValue($value){
        
        if ( is_bool( $value ) && $value === false){
        
            return false;
        
        }

        if ( ( is_string( $value ) && in_array( strtolower( $value ), array('false', '0', '2' , 'no', 'disabled', 'disable') ) )
             || ( is_numeric( $value ) && ($value === 0 || $value === 2 ) ) ) {
            
            return false;
        
        }

        return true;

    }

    /**
     * Function to validate visibility value and return MG option value.
     * @param integer/string $value                 value to check
     * @return integer                              MG option value
     */
    private function SLValidateVisibilityValue($value){
        
        if (is_numeric($value)){
            
            if (in_array($value, array($this->visibility_both, $this->visibility_not_visible, $this->visibility_in_search, $this->visibility_in_catalog))){

                return $value;

            }

        }else if (is_string($value) && $value !== null){
            
            $value_to_check = str_replace(' ', '_', strtolower($value));
            
            if (preg_match('~(not|visible|individually)~', $value_to_check)) {
            
                return $this->visibility_not_visible;
            
            }else{

                $preg_catalog = preg_match('~(catalog)~', $value_to_check);
                $preg_search = preg_match('~(search)~', $value_to_check);
        
                if ($preg_catalog && $preg_search){
                    
                    return $this->visibility_both;

                }else if ($preg_catalog && !$preg_search){
                    
                    return $this->visibility_in_catalog;

                }else if ($preg_search && !$preg_catalog){
                    
                    return $this->visibility_in_search;

                }

            }

        }
        
        return false;

    }

    /**
     * Function to validate visibility value and return MG option value.
     * @param integer/string $value                 value to check
     * @return integer                              MG option value
     */
    private function SLValidateInventoryBackordersValue($value = ''){
        
        $return_value = $this->config_backorders;

        if (null !== $value && $value !== ''){
            
            if (is_numeric($value)){
                
                if (in_array($value, array($this->backorders_no, $this->backorders_yes_nonotify, $this->backorders_yes_notify))){
                    
                    return $value;

                }

            }else if (is_string($value) && $value !== null){
                
                $value_to_check = str_replace(' ', '_', strtolower($value));

                // Allowed values:
                // 0 - No Backorders
                // 1 - Allow Qty Below 0
                // 2 - Allow Qty Below 0 and Notify Customer

                $preg_no_backorder = preg_match('~(no|backorder)~', $value_to_check);
                $preg_allow_below = preg_match('~(allow|below)~', $value_to_check);
                $preg_notify_customer = preg_match('~(notify|customer)~', $value_to_check);

                if ($preg_notify_customer){
                    
                    return $this->backorders_yes_notify;

                }else if ($preg_allow_below){
                    
                    return $this->backorders_yes_nonotify;

                }else if ($preg_no_backorder){
                    
                    return $this->backorders_no;

                }

            }
        
        }

        return $return_value;

    }

    /**
     * Function to validate Layout value and return MG option value.
     * @param string $sl_layout_value               value to check
     * @return string                               MG option value
     */
    private function SLValidateLayoutValue($sl_layout_value){

        if (is_array($sl_layout_value)) $sl_layout_value = reset($sl_layout_value);
        $sl_layout_value = trim(strtolower($sl_layout_value));
        if ($sl_layout_value === null) return $this->category_page_layout;
       
        if (empty($this->layout_options)){

            $layout_options = $this->layoutSource->getAllOptions();
            
            $indexes = array('label', 'value');

            foreach ($layout_options as $keyLO => $layout_option) {

                $new_layout_option = [];

                foreach ($indexes as $index) {

                    if (is_object($layout_option[$index])){

                        $new_layout_option[$index] = trim(strtolower(json_decode(json_encode($layout_option[$index]), true)));

                    }else{

                        $new_layout_option[$index] = trim(strtolower($layout_option[$index]));

                    }

                }

                $this->layout_options[] = $new_layout_option;

            }

        }

        if (!empty($this->layout_options)){

            $word_layout_value = false;

            if (preg_match('~(no|layout|updates)~', $sl_layout_value)){
           
                $word_layout_value = '';
           
            }else if (preg_match('~(empty)~', $sl_layout_value)){
               
                $word_layout_value = 'empty';
           
            }else if (preg_match('~(cms|page)~', $sl_layout_value)){
                                      
                $word_layout_value = 'cms-full-width';

            }else if (preg_match('~(category)~', $sl_layout_value)){
                           
                $word_layout_value = 'category-full-width';

            }else if (preg_match('~(product)~', $sl_layout_value)){
                           
                $word_layout_value = 'product-full-width';

            }else{

                $preg_1 = preg_match('~(1)~', $sl_layout_value);
                $preg_2 = preg_match('~(2)~', $sl_layout_value);
                $preg_3 = preg_match('~(3)~', $sl_layout_value);
                $preg_left = preg_match('~(left)~', $sl_layout_value);
                $preg_right = preg_match('~(right)~', $sl_layout_value);

                if ($preg_1){

                    $word_layout_value = '1column';

                }else if ($preg_2 && $preg_right){

                    $word_layout_value = '2columns-right';

                }else if ($preg_2 || $preg_left){

                    $word_layout_value = '2columns-left';

                }else if ($preg_3){

                    $word_layout_value = '3columns';

                }

            }

            foreach ($this->layout_options as $layout_option) {

                if ($sl_layout_value == $layout_option['value'] || $sl_layout_value == $layout_option['label']){

                    return $layout_option['value'];

                }

            }

            if ($word_layout_value !== false){

                foreach ($this->layout_options as $layout_option) {

                    if ($word_layout_value == $layout_option['value']){

                        return $word_layout_value;

                    } 

                }

            }

       }
       
       return $this->category_page_layout;

    }

    /**
     * Function to execute all Sales Layer functions that pre-load class variables.
     * @return void
     */
    public function execute_slyr_load_functions(){

        if (!$this->load_sl_attributes()){
            return false;
        }
        $this->checkImageAttributes(); 
        $this->checkActiveAttributes();
        if (!$this->loadSaleslayerRootCategory()){
            return false;
        }
        $this->loadAllStoreViewIds();
        // $this->load_sl_multiconn_table_data(); 
        return true;

    }

    /**
     * Function to clean magento class variables.
     * @return void
     */
    private function cleanMGVars(){

        $this->mg_category_id                       = null;
        $this->mg_category_current_row_id           = null;
        $this->mg_category_row_ids                  = [];
        $this->mg_parent_category_id                = null;
        $this->mg_parent_category_current_row_id    = null;
        $this->mg_parent_category_row_ids           = [];
        $this->mg_category_level                    = null;
        $this->mg_product_id                        = null;
        $this->mg_product_current_row_id            = null;
        $this->mg_product_row_ids                   = null;
        $this->mg_product_attribute_set_id          = null;
        $this->mg_format_id                         = null;
        $this->mg_format_current_row_id             = null;
        $this->mg_format_row_ids                    = null;
        $this->processed_global_attributes          = [];

    } 

    /**
     * Function to get all Sales Layer connectors.
     * @return array                   array of connectors
     */
    public function getConnectors(){

        $all_connectors = $this->getCollection();

        $connectors = [];
        
        if (count($all_connectors) > 0){

            foreach ($all_connectors as $connector) {

                $connector_data = $connector->getData();
                if (isset($connector_data['avoid_stock_update']) && $connector_data['avoid_stock_update'] !== '1'){ $connector_data['avoid_stock_update'] = '0'; }
                $connectors[] = $connector_data;

            }
            
        }
        
        return $connectors;

    }

    /**
     * Function to delete Sales Layer logs.
     * @return void
     */
    public function deleteSLLogs(){

        $log_folder_files = scandir($this->sl_logs_path);
        
        if (!empty($log_folder_files)){
            foreach ($log_folder_files as $log_folder_file) {

                if (strpos($log_folder_file, '_debbug_log_saleslayer_') !== false){

                    $file_path = $this->sl_logs_path.$log_folder_file;

                    if (file_exists($file_path)){

                        unlink($file_path);

                    }

                }

            }

        }

    }

    /**
     * Function to delete Sales Layer regs.
     * @return void
     */
    public function deleteSLRegs(){

        $this->loadConfigParameters();

        $items_to_process = $this->connection->query(" SELECT count(*) as count FROM ".$this->saleslayer_syncdata_table)->fetch();

        if (isset($items_to_process['count']) && $items_to_process['count'] > 0){

            $this->debbug("Deleting ".$items_to_process['count']." items to process...");

            try{

                $sql_query_delete = " DELETE FROM ".$this->saleslayer_syncdata_table;
                $this->sl_connection_query($sql_query_delete);
              
            }catch(\Exception $e){
             
                $this->debbug('## Error. Delete syncdata SQL message: '.$e->getMessage());
                $this->debbug('## Error. Delete syncdata SQL query: '.$sql_query_delete);

            }

        }

    }

    /**
     * Function to delete unused images.
     * @return void
     */
    public function deleteUnusedImages(){

        $this->loadConfigParameters();

        $time_ini_delete_all_unused_images = microtime(1);
        
        $galleryTable = $this->getTable('catalog_product_entity_media_gallery');
        $galleryEntityTable = $this->getTable('catalog_product_entity_media_gallery_value_to_entity');

        $unused_images_to_delete_sql = " SELECT * FROM ".$galleryTable.
                                            " WHERE value_id NOT IN (SELECT DISTINCT(value_id) FROM ".$galleryEntityTable.")";

        $unused_images_to_delete = $this->connection->fetchAll($unused_images_to_delete_sql);

        if (!empty($unused_images_to_delete)){

            $count_deleted = 0;

            $this->debbug("Deleting ".count($unused_images_to_delete)." images ...");

            foreach ($unused_images_to_delete as $unused_image_to_delete) {

                $this->debbug("Deleting ".$unused_image_to_delete['value']." ...");
        
                try{

                    $sql_query_delete = " DELETE FROM ".$galleryTable." WHERE value_id = ".$unused_image_to_delete['value_id'];
                    $this->sl_connection_query($sql_query_delete);
                    $count_deleted++;

                }catch (\Exception $e){

                    $this->debbug('## Error. Deleting unused image SQL message: '.$e->getMessage());
                    $this->debbug('## Error. Deleting unused image SQL query: '.$sql_query_delete);
                    continue;

                }

                try{

                    $image_path = $this->product_path_base.$unused_image_to_delete['value'];
                    
                    if (file_exists($image_path)){ 

                        unlink($image_path);

                    }else{

                        $this->debbug("## Notice. Could not read local image with path: ".$image_path." to delete.");

                    }
                    
                }catch (\Exception $e){

                    $this->debbug('## Error. Deleting unused image: '.$image_path.' from stored path: '.$e->getMessage());

                }

            }

        }else{

            return ' No unused images to delete found.';

        }
        
        $this->debbug('# time_delete_all_unused_images: ', 'timer', (microtime(1) - $time_ini_delete_all_unused_images));
        return ' '.$count_deleted.' unused images deleted.';

    }

    /**
     * Function to download Sales Layer logs.
     * @return void
     */
    public function downloadSLLogs(){

        $this->loadConfigParameters();
        $this->load_magento_variables();

        $files = [];

        $log_folder_files = scandir($this->sl_logs_path);

        if (!empty($log_folder_files)){

            foreach ($log_folder_files as $log_folder_file) {

                if (strpos($log_folder_file, '_debbug_log_saleslayer_') !== false){

                    $files[] = $log_folder_file;

                }

            }

        }else{

            $this->debbug('## Error. Logs files not found in: '.$this->sl_logs_path.'. Found files: '.print_r($log_folder_files,1));
            return false;
        }

        
        $zipname = $this->sl_logs_path.'sl_logs_'.date('Y-m-d H-i-s').'.zip';
        $zip = new \ZipArchive();

        $zip->open($zipname, $zip::CREATE);

        $files_found = false;

        foreach ($files as $file) {

            $file_path = $this->sl_logs_path . $file;

            if (file_exists($file_path)) {

                $files_found = true;
                $zip->addFile($file_path, $file);

            }

        }

        $zip->close();

        if (!$files_found) {

            if (file_exists($zipname)) unlink($zipname);
            $this->debbug('## Error. SL logs zip not found.');

        } else {

            if (file_exists($zipname)) {

                try{

                    header('Content-Description: File Transfer');
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename='.basename($zipname));
                    header('Content-Transfer-Encoding: binary');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');
                    header('Content-Length: ' . filesize($zipname));
                    
                    ob_clean();
                    flush();

                    chmod($zipname, 0777);

                    readfile($zipname);

                    unlink($zipname);

                    return true;

                }catch(\Exception $e){
                    
                    $this->debbug('## Error. Downloading SL logs zip: '.$e->getMessage());

                }

            }else{

                $this->debbug('## Error. SL logs zip does not exist.');

            }

        }

        return false;

    }

    /**
     * Function to delete Sales Layer indexes.
     * @return void
     */
    public function deleteSLIndexes(){

        $this->loadConfigParameters();
        
        $attributes_tables = array('catalog_category_entity_decimal', 'catalog_category_entity_int', 'catalog_category_entity_text', 'catalog_category_entity_varchar',
                                    'catalog_product_entity_decimal', 'catalog_product_entity_int', 'catalog_product_entity_text', 'catalog_product_entity_varchar');

        foreach ($attributes_tables as $attribute_table) {

            try{

                $this->connection->dropIndex($attribute_table, 'SLYR_CREDENTIALS');

            }catch(\Exception $e){

                $this->debbug('## Error. Deleting index in table '.$attribute_table.': '.$e->getMessage());

            }
    
        }

    }

    /**
     * Function to unlink old items in Magento that don't exist already in Sales Layer.
     * @return void
     */
    // public function unlinkOldItems(){

    //     $connectors = $this->getConnectors();

    //     $sl_connectors_data = [];
    //     $this->load_models();

    //     if (!empty($connectors)){

    //         $this->loadConfigParameters();

    //         foreach ($connectors as $connector) {

    //             $connector_id = $connector['connector_id'];
    //             $secret_key = $connector['secret_key'];

    //             $slconn = new SalesLayerConn($connector_id, $secret_key);

    //             $slconn->set_API_version(self::sl_API_version);
    //             $slconn->set_group_multicategory(true);
    //             $slconn->get_info();

                // if ($slconn->has_response_error()) { 
                //     continue; 
                // }

                // if ($response_connector_schema = $slconn->get_response_connector_schema()) {

                //     $response_connector_type = $response_connector_schema['connector_type'];

                //     if ($response_connector_type != self::sl_connector_type) { 
                //         continue; 
                //     }

                // }

    //             $comp_id = $slconn->get_response_company_ID();

    //             $get_response_table_data  = $slconn->get_response_table_data();

    //             $get_data_schema = self::get_data_schema($slconn);

                // if (!$get_data_schema){ 
                //     continue; 
                // }

    //             $products_schema = $get_data_schema['products'];

    //             if (!empty($products_schema['fields'][strtolower($this->product_field_sku)])){

    //                 $this->product_field_sku = strtolower($this->product_field_sku);

    //             }else if (!empty($products_schema['fields'][strtoupper($this->product_field_sku)])){

    //                 $this->product_field_sku = strtoupper($this->product_field_sku);

    //             }

    //             if ($get_response_table_data) {

    //                 if (!isset($sl_connectors_data[$comp_id])){ $sl_connectors_data[$comp_id] = []; }

    //                 foreach ($get_response_table_data as $nombre_tabla => $data_tabla) {

    //                     $modified_data = $data_tabla['modified'];

    //                     switch ($nombre_tabla) {
    //                         case 'catalogue':

    //                             // $this->debbug('Count total categories: '.count($modified_data));
    //                             foreach ($modified_data as $keyCat => $category) {

    //                                 $sl_name = '';
    //                                 if (isset($category['data'][$this->category_field_name]) && $category['data'][$this->category_field_name] !== ''){
    //                                     $sl_name = $category['data'][$this->category_field_name];
    //                                 }
                                    
    //                                 $sl_connectors_data[$comp_id]['category'][$category[$this->category_field_id]] = [];

    //                                 if ($sl_name !== ''){
    //                                     $sl_connectors_data[$comp_id]['category'][$category[$this->category_field_id]]['name'] = $sl_name;
    //                                 }
                                    
    //                             }

    //                             break;
    //                         case 'products':

    //                             // $this->debbug('Count total products: '.count($modified_data));
    //                             foreach ($modified_data as $keyProd => $product) {

    //                                 $sl_name = $sl_sku = '';
    //                                 if (isset($product['data'][$this->product_field_name]) && $product['data'][$this->product_field_name] !== ''){
    //                                     $sl_name = $product['data'][$this->product_field_name];
    //                                 }
    //                                 if (isset($product['data'][$this->product_field_sku]) && $product['data'][$this->product_field_sku] !== ''){
    //                                     $sl_sku = $product['data'][$this->product_field_sku];
    //                                 }

    //                                 $sl_connectors_data[$comp_id]['product'][$product[$this->product_field_id]] = [];

    //                                 if ($sl_name !== ''){
    //                                     $sl_connectors_data[$comp_id]['product'][$product[$this->product_field_id]]['name'] = $sl_name;
    //                                 }
    //                                 if ($sl_sku !== ''){
    //                                     $sl_connectors_data[$comp_id]['product'][$product[$this->product_field_id]]['sku'] = $sl_sku;
    //                                 }

    //                             }

    //                             break;
    //                         case 'product_formats':

    //                             // $this->debbug('Count total product formats: '.count($modified_data));
    //                             foreach ($modified_data as $keyForm => $format) {

    //                                 $sl_name = $sl_sku = '';
    //                                 if (isset($format['data'][$this->format_field_name]) && $format['data'][$this->format_field_name] !== ''){
    //                                     $sl_name = $format['data'][$this->format_field_name];
    //                                 }
    //                                 if (isset($format['data'][$this->format_field_sku]) && $format['data'][$this->format_field_sku] !== ''){
    //                                     $sl_sku = $format['data'][$this->format_field_name];
    //                                 }else{
    //                                     if (isset($format['data'][$this->format_field_name]) && $format['data'][$this->format_field_name] !== ''){
    //                                         $sl_sku = 'sku_'.$format['data'][$this->format_field_name];
    //                                     }
    //                                 }

    //                                 $sl_connectors_data[$comp_id]['format'][$format[$this->format_field_id]] = [];
    //                                 if ($sl_name !== ''){
    //                                     $sl_connectors_data[$comp_id]['format'][$format[$this->format_field_id]]['name'] = $sl_name;
    //                                 }
    //                                 if ($sl_sku !== ''){
    //                                     $sl_connectors_data[$comp_id]['format'][$format[$this->format_field_id]]['sku'] = $sl_sku;
    //                                 }

    //                             }

    //                             break;
    //                         default:

    //                             $this->debbug('## Error. Synchronizing, table '.$nombre_tabla.' not recognized.');

    //                             break;
    //                     }

    //                 }

    //             }

    //         }

    //         $unlinked_items = $duplicated_items = [];
    //         if (!empty($sl_connectors_data)){

    //             $empty_value = array(null => 0, '' => 0, null => 0);

    //             $this->load_categories_collection();

    //             if (!empty($this->categories_collection)){

    //                 foreach ($this->categories_collection as $keyCat => $category) {

    //                     if ($category['parent_id'] == 0 || $category['parent_id'] == 1){
    //                         continue;
    //                     }

    //                     $category_saleslayerid = $category['saleslayer_id'];
    //                     $category_saleslayercompid = $category['saleslayer_comp_id'];

    //                     $unlink = true;

    //                     if (!isset($empty_value[$category_saleslayerid]) && !isset($empty_value[$category_saleslayercompid])){

    //                         if (isset($sl_connectors_data[$category_saleslayercompid]['category'][$category_saleslayerid])){

    //                             if (isset($unlinked_items[$category_saleslayercompid]['category'][$category_saleslayerid])){

    //                                 $this->debbug('@@@ category already unlinked SL id: '.$category_saleslayerid.' SL comp_id: '.$category_saleslayercompid);
    //                                 $this->debbug('@@@ MG unlinked id: '.$unlinked_items[$category_saleslayercompid]['category'][$category_saleslayerid].' MG new unlink id: '.$category['entity_id']);

    //                                 foreach ($unlinked_items[$category_saleslayercompid]['category'][$category_saleslayerid] as  $dup_to_reg) {
    //                                     $duplicated_items['category'][$dup_to_reg['id']] = $dup_to_reg['name'];
    //                                 }
    //                                 $duplicated_items['category'][$category['entity_id']] = $category['name'];
    //                             }

    //                             $unlinked_items[$category_saleslayercompid]['category'][$category_saleslayerid][] = array('id' => $category['entity_id'], 'name' => $category['name']);
    //                             $unlink = false;

    //                         }

    //                     }

    //                     if ($unlink){

    //                         try{

    //                             $this->debbug('@@@ category unlink id: '.$category['entity_id'].' name: '.$category['name']);
    //                             $this->debbug('@@@ category unlink category_saleslayerid: '.print_r($category_saleslayerid,1));
    //                             $this->debbug('@@@ category unlink category_saleslayercompid: '.print_r($category_saleslayercompid,1));

    //                             $category_update = $this->load_category_model($category['entity_id']);
    //                             $category_update->setData('saleslayer_id', '');
    //                             $category_update->setData('saleslayer_comp_id', '');
    //                             $category_update->setIsActive(0);
    //                             $category_update->save();

    //                             $deleted_categories_ids[] = $category['entity_id'];
    //                             $this->categories_collection[$category['entity_id']]['is_active'] = 0;

    //                         } catch (\Exception $e) {

    //                             $this->debbug('## Error. Unlinking category: '.$e->getMessage());

    //                         }

    //                     }

    //                 }

    //                 //Process to reorganize the category tree avoiding disabled categories just eliminated.
    //                 if (!empty($deleted_categories_ids)){

    //                     foreach ($this->categories_collection as $category_col) {

    //                         if (in_array($category_col['parent_id'], $deleted_categories_ids) && $category_col['is_active'] == 1){

    //                             $path_ids = explode('/', $category_col['path']);
    //                             $new_path = $parent_id = '';

    //                             foreach ($path_ids as $path_id){

    //                                 if (in_array($path_id, $deleted_categories_ids)){
    //                                     continue;
    //                                 }

    //                                 $new_path .= $path_id;

    //                                 if ($path_id != end($path_ids)){

    //                                     $new_path .= '/';
    //                                     $parent_id = $path_id;

    //                                 }

    //                             }

    //                             try{

    //                                 $category = $this->load_category_model($category_col['entity_id']);
    //                                 $category->setPath($new_path);
    //                                 $category->setParentId($parent_id);
    //                                 $category->save();

    //                                 $this->categories_collection[$category_col['entity_id']]['path'] = $new_path;
    //                                 $this->categories_collection[$category_col['entity_id']]['parent_id'] = $parent_id;

    //                             }catch(\Exception $e){

    //                                 $this->debbug('## Error. Reorganizing category tree: '.$e->getMessage());

    //                             }

    //                         }

    //                     }

    //                 }

    //             }

    //             $this->load_products_collection();

    //             if (!empty($this->products_collection)){

    //                 foreach ($this->products_collection as $keyProd => $product) {

    //                     $product_saleslayerid = $product['saleslayer_id'];
    //                     $product_saleslayercompid = $product['saleslayer_comp_id'];
    //                     $product_saleslayerformatid = $product['saleslayer_format_id'];

    //                     $unlink = true;

    //                     if (!isset($empty_value[$product_saleslayerid]) && !isset($empty_value[$product_saleslayercompid])){

    //                         // if (empty(Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild($product->getId()))){}
    //                         if (!isset($empty_value[$product_saleslayerformatid])){

    //                             if (isset($sl_connectors_data[$product_saleslayercompid]['format'][$product_saleslayerformatid])){

    //                                 if (isset($unlinked_items[$product_saleslayercompid]['format'][$product_saleslayerformatid])){
    //                                     $this->debbug('@@@ format already unlinked SL format_id: '.$product_saleslayerformatid.' SL comp_id: '.$product_saleslayercompid.' SL prod_id: '.$product_saleslayerid);
    //                                     $this->debbug('@@@ MG unlinked id: '.$unlinked_items[$product_saleslayercompid]['format'][$product_saleslayerformatid].' MG new unlink id: '.$product['entity_id']);
    //                                 }

    //                                 $unlinked_items[$product_saleslayercompid]['format'][$product_saleslayerformatid] = $product['entity_id'];

    //                                 $unlink = false;

    //                             }

    //                         }else{

    //                             if (isset($sl_connectors_data[$product_saleslayercompid]['product'][$product_saleslayerid])){

    //                                 if (isset($unlinked_items[$product_saleslayercompid]['product'][$product_saleslayerid])){

    //                                     $this->debbug('@@@ product already unlinked SL id: '.$product_saleslayerid.' SL comp_id: '.$product_saleslayercompid);
    //                                     $this->debbug('@@@ MG unlinked data: '.print_r($unlinked_items[$product_saleslayercompid]['product'][$product_saleslayerid],1));
    //                                     $this->debbug('@@@ MG new unlink id: '.$product['entity_id']);

    //                                     foreach ($unlinked_items[$product_saleslayercompid]['product'][$product_saleslayerid] as  $dup_to_reg) {
    //                                         $duplicated_items['product'][$dup_to_reg['id']] = $dup_to_reg['sku'];
    //                                     }

    //                                     $duplicated_items['product'][$product['entity_id']] = $product['sku'];

    //                                 }

    //                                 $unlinked_items[$product_saleslayercompid]['product'][$product_saleslayerid][] = array('id' => $product['entity_id'], 'sku' => $product['sku']);
    //                                 $unlink = false;

    //                             }

    //                         }

    //                     }

    //                     if ($unlink){

    //                         try {

    //                             $this->debbug('@@@ product unlink id: '.$product['entity_id'].' sku: '.$product['sku'].' name: '.$product['name']);
    //                             $this->debbug('@@@ product unlink product_saleslayerid: '.print_r($product_saleslayerid,1));
    //                             $this->debbug('@@@ product unlink product_saleslayercompid: '.print_r($product_saleslayercompid,1));
    //                             $this->debbug('@@@ product unlink product_saleslayerformatid: '.print_r($product_saleslayerformatid,1));

    //                             $product_update = $this->load_product_model($product['entity_id']);
    //                             $product_update->setData('saleslayer_id', '');
    //                             $product_update->setData('saleslayer_comp_id', '');
    //                             $product_update->setData('saleslayer_format_id', '');
    //                             $product_update->setStatus($this->status_disabled);
    //                             $product_update->save();

    //                             $deleted_products_ids[] = $product['entity_id'];
    //                             $this->products_collection[$product['entity_id']]['status'] = $this->status_disabled;

    //                         } catch (\Exception $e) {

    //                             $this->debbug('### Error. Unlinking product: '.$e->getMessage());

    //                         }

    //                     }

    //                 }

    //             }

    //             if (!empty($duplicated_items)){

    //                 foreach ($duplicated_items as $type => $items) {

    //                     foreach ($items as $item_id => $item_data) {

    //                         if ($type == 'category'){

    //                             $dup_category = $this->load_category_model($item_id);

    //                             try{

    //                                 $this->debbug('@@@ duplicated category unlink id: '.$dup_category->getId().' name: '.$dup_category->getName());
    //                                 $this->debbug('@@@ duplicated category unlink category_saleslayerid: '.print_r($dup_category->getSaleslayerId(),1));
    //                                 $this->debbug('@@@ duplicated category unlink category_saleslayercompid: '.print_r($dup_category->getSaleslayerCompId(),1));

    //                                 $dup_category->setData('saleslayer_id', '');
    //                                 $dup_category->setData('saleslayer_comp_id', '');
    //                                 $dup_category->setIsActive(0);
    //                                 $dup_category->save();

    //                             } catch (\Exception $e) {

    //                                 $this->debbug('### Error. Unlinking duplicated category: '.$e->getMessage());

    //                             }

    //                         }else{

    //                             $dup_product = $this->load_product_model($item_id);

    //                             try{

    //                                 $this->debbug('@@@ duplicated product unlink id: '.$dup_product->getId().' sku: '.$dup_product->getSku().' name: '.$dup_product->getName());
    //                                 $this->debbug('@@@ duplicated product unlink product_saleslayerid: '.print_r($dup_product->getSaleslayerId(),1));
    //                                 $this->debbug('@@@ duplicated product unlink product_saleslayercompid: '.print_r($dup_product->getSaleslayerCompId(),1));
    //                                 $this->debbug('@@@ duplicated product unlink product_saleslayerformatid: '.print_r($dup_product->getSaleslayerFormatId(),1));

    //                                 $dup_product->setData('saleslayer_id', '');
    //                                 $dup_product->setData('saleslayer_comp_id', '');
    //                                 $dup_product->setData('saleslayer_format_id', '');
    //                                 $dup_product->setStatus($this->status_disabled);
    //                                 $dup_product->save();

    //                             } catch (\Exception $e) {

    //                                 $this->debbug('### Error. Unlinking duplicated product: '.$e->getMessage());
    //                             }

    //                         }

    //                     }

    //                 }

    //             }

    //         }

    //     }

    // }

    /**
     * Function to load multi-connector items in SL Multiconn table.
     * @return void
     */
    public function loadMulticonnItems(){

        $connectors = $this->getConnectors();
        $sl_data = [];

        if (empty($connectors)){
            return;
        }

        $this->loadConfigParameters();
        $this->load_magento_variables();

        foreach ($connectors as $connector) {

            $sl_data = $this->loadConnItems($connector, $sl_data);
           
        }

        if (!empty($sl_data)){

            $this->saveConns($sl_data);

        }

    }

    /**
     * Function to load multiconn data into class param.
     * @return void
     */
    public function load_sl_multiconn_table_data(){

        $sl_multiconn_table_data = $this->connection->fetchAll(" SELECT * FROM ".$this->saleslayer_multiconn_table." WHERE sl_comp_id = ".$this->comp_id);

        if (!empty($sl_multiconn_table_data)){

            foreach ($sl_multiconn_table_data as $sl_multiconn_reg) {

                if ($sl_multiconn_reg['sl_connectors'] !== ''){
                
                    $sl_multiconn_reg['sl_connectors'] = json_decode($sl_multiconn_reg['sl_connectors'],1);
                
                }

                if (!isset($this->sl_multiconn_table_data[$sl_multiconn_reg['item_type']])){ $this->sl_multiconn_table_data[$sl_multiconn_reg['item_type']] = []; }
                
                $this->sl_multiconn_table_data[$sl_multiconn_reg['item_type']][$sl_multiconn_reg['sl_id']] = array('id' => $sl_multiconn_reg['id'], 'sl_connectors' => $sl_multiconn_reg['sl_connectors']);

            }

        }

    }

    /**
     * Function to execute a sql and commit it.
     * @param string $query                 sql to execute
     * @param array $params                 parameters to attach to query
     * @return void
     */
    public function sl_connection_query($query, $params = []){

        $this->connection->beginTransaction();
        
        try{

            if (!empty($params)){

                $this->connection->query($query, $params);

            }else{

                $this->connection->query($query);

            }

            $this->connection->commit();

        }catch(\Exception $e) {
            
            $this->connection->rollBack();

            if (!empty($params)){

                $this->debbug('## Error. SL SQL query: '.$query.' - params: '.print_r($params,1));
                
            }else{

                $this->debbug('## Error. SL SQL query: '.$query);
                
            }

            $this->debbug('## Error. SL SQL error message: '.$e->getMessage());

        }

    }

    /**
     * Function to delete Sales Layer log file.
     * @param array $files_to_delete        log files to delete from system
     * @return boolean
     */
    public function deleteSLLogFile($files_to_delete){

        $this->loadConfigParameters();
        $this->load_magento_variables();

        if (!is_array($files_to_delete)){ $files_to_delete = array($files_to_delete); }

        if (empty($files_to_delete)){ 
            return false; 
        }

        foreach ($files_to_delete as $file_to_delete) {

            $file_array = explode('/',$file_to_delete);
            $file_to_delete = end($file_array);

            if($file_to_delete !== null && preg_match('/[A-Za-z0-9]*.[A-Za-z0-9]{3}/',$file_to_delete)) {
                $file_path = $this->sl_logs_path . $file_to_delete;

                if ( file_exists( $file_path ) ) {

                    unlink( $file_path );

                }
            }

        }

        return true;

    }

    /**
     * Function to show content log file.
     * @param string $logfile               log file which we want to show content
     * @return array
     */
    public function showContentFile($logfile){

        $this->loadConfigParameters();
        $this->load_magento_variables();

        $logfile = html_entity_decode($logfile);
        $response = [];
        $response[1] = [];
        $elements_array =  explode('/',$logfile);
        $logfile = end($elements_array);
        $exportlines = '';

        if($logfile !== null && preg_match('/[A-Za-z0-9]*.[A-Za-z0-9]{3}/',$logfile) && file_exists( $this->sl_logs_path.$logfile)){
            $file = file($this->sl_logs_path.$logfile);
            $listed = 0;
            $warnings = 0;
            $numerrors = 0;
            if(sizeof( $file)>=1){
                $spacingarray = [];
                foreach ( $file as  $line){

                    if(count($spacingarray)>=1 &&  stripos($line,")") !== false){
                        array_pop($spacingarray);
                    }

                    if(count($spacingarray)>=1){
                        if(stripos($line,"(") !== false ){
                            array_pop($spacingarray);
                            $spacing = implode('',$spacingarray);
                            $spacingarray[] = '&emsp;&emsp;';
                        }else{
                            $spacing = implode('',$spacingarray);
                        }
                    }else{
                        $spacing = '';
                    }
                    $listed ++;
                    if (stripos($line,"## Error.") !== false ||stripos($line, "error") !== false) {
                        $iderror = 'id="iderror'.$numerrors.'"';
                        $exportlines .='<span class="alert-danger col-xs-12" '.$iderror.'><i class="fas fa-times text-danger mar-10"></i>  '.$spacing.$line.'</span><br>';
                        $numerrors++;
                    }elseif(stripos($line, "warning") !== false) {
                        $idwarning = 'id="idwarning'.$warnings.'"';
                        $exportlines .='<span class="alert-warning col-xs-12" '.$idwarning.'><i class="fas fa-exclamation-circle text-warning mar-10"></i>  '.$spacing.$line.'</span><br>';
                        $warnings++;
                    }elseif(stripos($line, "Saleslayer_Synccatalog") !== false) {
                        $exportlines .='<span class="alert-info col-xs-12"><i class="fas fa-info-circle text-info mar-10"></i>  '.$spacing.$line.'</span><br>';
                    }else{
                        $exportlines .='<span class="col-xs-12">'.$spacing.$line.'</span><br>';
                    }
                    if(stripos($line,"Array") !== false){
                        $spacingarray[] = '&emsp;&emsp;';
                    }
                }
            }else{
                $exportlines .= '';
            }

            $response[0] = 1;
            $response[1] = $exportlines;
            $response[2] = $listed;
            $response[3] = $warnings;
            $response[4] = $numerrors;

        }else{
            $response[0] = 1;
            $response[1] = array('Log file does not exist.');
            $response[2] = 0;
            $response[3] = 0;
            $response[4] = 0;

        }

        $response['function'] = 'showlogfilecontent';

        return $response;

    }

    /**
     * Function to check files Sales Layer logs.
     * @return array
     */
   public function checkFilesLogs(){
       
       $this->loadConfigParameters();
       $this->load_magento_variables();

       $files       = [];
       $response    = [];
       $response[1] = [];

       $log_folder_files = scandir($this->sl_logs_path);

       if (!empty($log_folder_files)){

           foreach ($log_folder_files as $log_folder_file) {
               if (strpos($log_folder_file, '_saleslayer_') !== false){//_debbug_log_saleslayer_
                   $files[] = $log_folder_file;
               }
           }

           if(sizeof($files)>=1){

               $table = [
                'file'      =>[],
                'lines'     =>[],
                'warnings'  =>[],
                'errors'    =>[]
            ];

               foreach ($files as $file) {
                   $errors = 0;
                   $warnings = 0;
                   $lines = 0;
                   $file_path = $this->sl_logs_path . $file;

                   if (file_exists($file_path)) {
                       $table['file'][] = htmlspecialchars($file, ENT_QUOTES, 'UTF-8');
                       $fileopened = file($this->sl_logs_path.$file);
                       if(sizeof( $fileopened)>=1){
                           $errors = 0;
                           $warnings = 0;
                           $lines = 0;
                           foreach ( $fileopened as  $line){
                               $lines++;
                               if (stripos($line,"## Error.") !== false ||stripos($line, "error") !== false) {
                                   $errors++;
                               }elseif(stripos($line, "warning") !== false) {
                                   $warnings++;
                               }
                           }
                       }
                       $table['lines'][]     =  $lines;
                       $table['warnings'][] =  $warnings;
                       $table['errors'][]   =  $errors;
                   }
               }

               $response[0] = 1;
               $response[1] = $table;
           }else{
               $response[0] = 0;
               $response[1] = 'No log files to show.';
           }
       }else{
           $response[0] = 0;
           $response[1] = 'No log files to show.';
       }
       $response['function'] = 'showlogfiles';

       return $response;

   }

    /**
     * Function to filter empty and null values
     * @param string or integer $value      value to filter
     * @return integer                      returns 1 if value is not empty or null
     */
    private function array_filter_empty_value($value){

        return !(trim($value) === "" || $value === null);

    }

    /**
     * Function to search the pid and return if it's still running or not
     * @param int $pid          pid to search
     * @return boolean          status of pid running
     */
    public function has_pid_alive($pid){

        if ($pid){

            if (strtolower(substr(PHP_OS, 0, 3)) == 'win') {

                $wmi = new \COM('winmgmts://');
                $process = $wmi->ExecQuery("SELECT ProcessId FROM Win32_Process WHERE ProcessId='$pid'");

                $process_count = count($process);

                if ($this->sl_DEBBUG > 2){ $this->debbug("Searching active process pid '$pid' by Windows. Is active? ".($process_count > 0 ? 'Yes' : 'No')); }

                return ($process_count > 0 ? true : false);

            } else if (function_exists('posix_getpgid')) {

                if ($this->sl_DEBBUG > 2) { $this->debbug("Searching active process pid '$pid' by posix_getpgid. Is active? ".(posix_getpgid($pid) ? 'Yes' : 'No')); }

                return (posix_getpgid($pid) ? true : false);

            } else {

                if ($this->sl_DEBBUG > 2) { $this->debbug("Searching active process pid '$pid' by ps -p. Is active? ".(shell_exec("ps -p $pid | wc -l") > 1 ? 'Yes' : 'No')); }

                if (shell_exec("ps -p $pid | wc -l") > 1) { 
                    return true; 
                }

            }
        }

        return false;
        
    }

    /**
     * Function to load Sales Layer attributes.
     * @return boolean              if attributes have been loaded or not
     */
    private function load_sl_attributes(){

        $attributes_error = false;

        $sl_attributes = array($this->category_entity_type_id => array('category_saleslayer_id_attribute' => 'saleslayer_id', 'category_saleslayer_comp_id_attribute' => 'saleslayer_comp_id'), 
                                $this->product_entity_type_id => array('product_saleslayer_id_attribute' => 'saleslayer_id', 'product_saleslayer_comp_id_attribute' => 'saleslayer_comp_id', 'product_saleslayer_format_id_attribute' => 'saleslayer_format_id'));

        foreach ($sl_attributes as $entity_type_id => $entity_attributes) {

            foreach ($entity_attributes as $attribute_const => $attribute_code) {

                $attribute = $this->getAttributeWysiwyg($attribute_code, $entity_type_id);
                if (empty($attribute)) {
            
                    $attributes_error = true;
                    break 2;
            
                }else{

                    $this->$attribute_const = $attribute[\Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_ID];
                    $this->{$attribute_const.'_backend_type'} = $attribute[\Magento\Eav\Api\Data\AttributeInterface::BACKEND_TYPE];

                    if ($attribute[\Magento\Catalog\Model\ResourceModel\Eav\Attribute::KEY_IS_GLOBAL] != $this->scope_global){

                        try{

                            $this->connection->update($this->getTable('catalog_eav_attribute'), [\Magento\Catalog\Model\ResourceModel\Eav\Attribute::KEY_IS_GLOBAL => $this->scope_global], \Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_ID . ' = ' . $attribute[\Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_ID]);

                        }catch(\Exception $e){

                            $this->debbug('## Error. Updating SL attribute global value: '.print_r($e->getMessage(),1));

                        }

                    }

                }

            }

        }

        if ($attributes_error){

            $this->debbug('## Error. Reading Sales Layer attributes, please compile again.');

            return false;

        }

        return true;

    }

    /**
     * Function to get category Magento core data.
     * @param  int $category_id                Magento category id
     * @return array $category_data            Magento category core data
     */
    private function get_category_core_data($category_id){

        $category_table = $this->getTable('catalog_category_entity');

        if (null === $category_table){ 

            return null; 

        }

        $category_data = $this->connection->fetchRow(
            $this->connection->select()
                ->from($category_table)
                ->where('entity_id = ?', $category_id)
        );

        if (!empty($category_data)){

            return $category_data;

        }

        return null;

    }

    protected function getEntityRowIds($entity_id, $entity_type = 'category'){

        $entity_table = $this->getTable('catalog_'.$entity_type.'_entity');

        $entity_row_ids_sql = " SELECT row_id".
                            " FROM ".$entity_table.
                            " WHERE entity_id = ".$entity_id;

        $entity_row_ids = $this->connection->fetchAll($entity_row_ids_sql);

        $row_ids = [];

        if (!empty($entity_row_ids)){

            foreach ($entity_row_ids as $entity_row_id) {
                
                $row_ids[] = $entity_row_id['row_id']; 

            }

        }else{

            $row_ids[] = $entity_id;

        }

        return $row_ids;

    }

    protected function getEntityCurrentRowId($entity_id, $entity_type = 'category'){

        $entity_table = $this->getTable('catalog_'.$entity_type.'_entity');

        $mg_entity_current_row_id = $this->connection->fetchRow(
            $this->connection->select()
                ->from($entity_table,
                    ['row_id'])
                ->where('entity_id = ?', $entity_id)
        );

        if (!empty($mg_entity_current_row_id) && isset($mg_entity_current_row_id['row_id'])){

            return $mg_entity_current_row_id['row_id'];

        }

        return null;

    }

    /**
     * Function to get product Magento core data.
     * @param  int $product_id                Magento product id
     * @return array $product_data            Magento product core data
     */
    private function get_product_core_data($product_id){

        $product_table = $this->getTable('catalog_product_entity');

        if (null === $product_table){ 

            return false; 

        }
        
        $product_core_data = $this->connection->fetchRow(
            $this->connection->select()
                ->from($product_table)
                ->where('entity_id = ?', $product_id)
        );

        if (!empty($product_core_data)){

            return $product_core_data;

        }

        return null;

    }


    /**
     * Function to find the category id associated to the Sales Layer category id.
     * @param int $saleslayer_id                Sales Layer category id
     * @param int $store_view_id                store view id to search 
     * @return int $category_id                 Magento category id
     */
    private function find_saleslayer_category_id_db($saleslayer_id, $store_view_id = 0, $category_type = 'category') {

        $category_table = $this->getTable('catalog_category_entity');
        $category_saleslayer_id_table = $this->getTable('catalog_category_entity_' . $this->category_saleslayer_id_attribute_backend_type);
        $category_saleslayer_comp_id_table = $this->getTable('catalog_category_entity_' . $this->category_saleslayer_comp_id_attribute_backend_type);

        $categories_data = $this->connection->fetchAll(
            $this->connection->select()
                ->from(
                   ['c1' => $category_saleslayer_id_table],
                    [$this->tables_identifiers[$category_saleslayer_id_table] => 'c1.'.$this->tables_identifiers[$category_saleslayer_id_table],
                    'saleslayer_id' => 'c1.value']
                )
                ->where('c1.attribute_id' . ' = ?', $this->category_saleslayer_id_attribute)
                ->where('c1.value' . ' = ?', $saleslayer_id)
                ->where('c1.store_id' . ' = ?', $store_view_id)
                ->joinLeft(
                    ['c2' => $category_saleslayer_comp_id_table], 
                    'c1.'.$this->tables_identifiers[$category_saleslayer_id_table].' = c2.'.$this->tables_identifiers[$category_saleslayer_comp_id_table].' AND c1.store_id = c2.store_id AND c2.attribute_id = '.$this->category_saleslayer_comp_id_attribute,
                    ['saleslayer_comp_id' => 'c2.value']
                )
                ->joinRight(
                    ['c3' => $category_table], 
                    'c1.'.$this->tables_identifiers[$category_saleslayer_id_table].' = c3.'.$this->tables_identifiers[$category_table],
                    ['entity_id']
                )
                ->group('c1.'.$this->tables_identifiers[$category_saleslayer_id_table])
        );

        if (!empty($categories_data)){

            $category_id = $category_id_temp = '';
            
            foreach ($categories_data as $category_data) {    

                if (isset($category_data['saleslayer_comp_id'])){

                    $category_saleslayer_comp_id = $category_data['saleslayer_comp_id'];
                
                }else{

                    $category_saleslayer_comp_id = '';

                }

                if (!in_array($category_saleslayer_comp_id, array(0, '', null))){

                    if ($category_saleslayer_comp_id != $this->comp_id){
                
                        //The category belongs to another company.
                        continue;

                    }else{

                        //The category matches.
                        $category_id = $category_data['entity_id'];
                        break;
                        
                    }

                }else{

                    //The category matches the identificator and it's without company.
                    $category_id_temp = $category_data['entity_id'];
                    continue;

                }

            }

            if ($category_id == '' && $category_id_temp != ''){

                $category_id = $category_id_temp;

                if ($category_type == 'parent'){

                    if ($this->mg_edition == 'enterprise'){
                    
                        $this->mg_parent_category_row_ids = $this->getEntityRowIds($category_id, 'category');

                        $this->mg_parent_category_current_row_id = $this->getEntityCurrentRowId($category_id, 'category');

                    }

                    $this->mg_parent_category_id = $category_id;

                    //Updating SL company credentials
                    $sl_credentials = array('saleslayer_comp_id' => $this->comp_id);

                    foreach ($this->mg_parent_category_row_ids as $mg_parent_category_row_id) {
                        
                        $this->setValues($mg_parent_category_row_id, 'catalog_category_entity', $sl_credentials, $this->category_entity_type_id, $store_view_id, false, false, $this->mg_parent_category_row_ids);

                    }

                }else{

                    if ($this->mg_edition == 'enterprise'){
                    
                        $this->mg_category_row_ids = $this->getEntityRowIds($category_id, 'category');

                        $this->mg_category_current_row_id = $this->getEntityCurrentRowId($category_id, 'category');

                    }

                    $this->mg_category_id = $category_id;
                
                    //Updating SL company credentials
                    $sl_credentials = array('saleslayer_comp_id' => $this->comp_id);

                    foreach ($this->mg_category_row_ids as $mg_category_row_id) {
                        
                        $this->setValues($mg_category_row_id, 'catalog_category_entity', $sl_credentials, $this->category_entity_type_id, $store_view_id, false, false, $this->mg_category_row_ids);

                    }

                }

                
            }

            if ($category_id != ''){

                if ($category_type == 'parent'){

                    $this->mg_parent_category_id = $category_id;

                    if ($this->mg_edition == 'enterprise'){

                        $this->mg_parent_category_row_ids = $this->getEntityRowIds($category_id, 'category');
                        $this->mg_parent_category_current_row_id = $this->getEntityCurrentRowId($category_id, 'category');

                    }

                }else{

                    $this->mg_category_id = $category_id;

                    if ($this->mg_edition == 'enterprise'){

                        $this->mg_category_row_ids = $this->getEntityRowIds($category_id, 'category');
                        $this->mg_category_current_row_id = $this->getEntityCurrentRowId($category_id, 'category');

                    }
                    
                }
                
                return $category_id;

            }

        }

        return null;

    }

    /**
     * Function to find a category by name and if it's not assigned, assign it with the Sales Layer category id.
     * @param string $category_urlkey           category url_key
     * @param int $saleslayer_id                Sales Layer category id
     * @param int $store_view_id                store view id to search 
     * @return bool
     */
    private function assignSaleslayerCategoryByUrlKey($category_urlkey, $saleslayer_id, $store_view_id = 0): bool
    {
        $urlkey_attribute = $this->getAttribute('url_key', $this->category_entity_type_id);
        
        if (empty($urlkey_attribute)){
            $this->debbug('## Error. Category url_key attribute does not exist, please correct this.');
            return false;
        }

        if (!isset($urlkey_attribute[\Magento\Eav\Api\Data\AttributeInterface::BACKEND_TYPE])) {
            $this->debbug('## Error. Category url_key attribute does not have a backend type, please correct this.');
            return false;
        }

        $category_table = $this->getTable('catalog_category_entity');
        $category_urlkey_table = $this->getTable('catalog_category_entity_' . $urlkey_attribute[\Magento\Eav\Api\Data\AttributeInterface::BACKEND_TYPE]);
        $category_saleslayer_id_table = $this->getTable('catalog_category_entity_' . $this->category_saleslayer_id_attribute_backend_type);
        $category_saleslayer_comp_id_table = $this->getTable('catalog_category_entity_' . $this->category_saleslayer_comp_id_attribute_backend_type);
        
        $categories_data = $this->connection->fetchAll(
            $this->connection->select()
                ->from(
                   ['c1' => $category_urlkey_table],
                    [$this->tables_identifiers[$category_urlkey_table] => 'c1.'.$this->tables_identifiers[$category_urlkey_table],
                    'url_key' => 'c1.value']
                )
                ->where('c1.attribute_id' . ' = ?', $urlkey_attribute[\Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_ID])
                ->where('c1.value' . ' = ?', $category_urlkey)
                ->where('c1.store_id' . ' = ?', $store_view_id)
                ->where('c4.level > 1')
                ->joinLeft(
                    ['c2' => $category_saleslayer_id_table], 
                    'c1.'.$this->tables_identifiers[$category_urlkey_table].' = c2.'.$this->tables_identifiers[$category_saleslayer_id_table].' AND c1.store_id = c2.store_id AND c2.attribute_id = '.$this->category_saleslayer_id_attribute,
                    ['saleslayer_id' => 'c2.value']
                )
                ->joinLeft(
                    ['c3' => $category_saleslayer_comp_id_table], 
                    'c1.'.$this->tables_identifiers[$category_urlkey_table].' = c3.'.$this->tables_identifiers[$category_saleslayer_comp_id_table].' AND c1.store_id = c3.store_id AND c3.attribute_id = '.$this->category_saleslayer_comp_id_attribute,
                    ['saleslayer_comp_id' => 'c3.value']
                )
                ->joinRight(
                    ['c4' => $category_table], 
                    'c1.'.$this->tables_identifiers[$category_urlkey_table].' = c4.'.$this->tables_identifiers[$category_table],
                    ['path', 'entity_id', 'parent_id']
                )
                ->group('c1.'.$this->tables_identifiers[$category_urlkey_table])
        );
        
        if (!empty($categories_data)){

            $category_id_found = $category_id_found_temp = 0;
            
            foreach ($categories_data as $category_data) {
        
                // verificar la necesidad de comentar este scope para evitar categorias duplicadas
                if ((isset($category_data['saleslayer_id']) && !in_array($category_data['saleslayer_id'], array(0, '', null))) && (isset($category_data['saleslayer_comp_id']) && !in_array($category_data['saleslayer_comp_id'], array(0, '', null)))){
                    continue;
                }
                            
                $path = $category_data['path'];
                $path_ids = explode('/', $path);
                
                if (isset($path_ids[1])){
                    
                    $path_data = $this->connection->fetchRow(
                        $this->connection->select()
                            ->from(
                                [$category_table],
                                ['entity_id','parent_id']
                            )
                            ->where('entity_id' . ' = ?', $path_ids[1])
                            ->limit(1)
                    );

                    if (!empty($path_data) && $path_data['parent_id'] == 1){

                       if (null !== $this->mg_parent_category_id && $category_data['parent_id'] == $this->mg_parent_category_id){

                            $category_id_found = $category_data['entity_id'];
                            break;

                        }else if ($category_id_found_temp == 0){

                            $category_id_found_temp = $category_data['entity_id'];
                    
                        }

                    }

                }

            }

            if ($category_id_found == 0 && $category_id_found_temp !== 0) $category_id_found = $category_id_found_temp;
            
            if ($category_id_found !== 0){

                if ($this->mg_edition == 'enterprise'){
                    
                    $this->mg_category_row_ids = $this->getEntityRowIds($category_id_found, 'category');
                    $this->mg_category_current_row_id = $this->getEntityCurrentRowId($category_id_found, 'category');
                    
                }
                
                $sl_credentials = array('is_active' => 1, 'saleslayer_id' => $saleslayer_id, 'saleslayer_comp_id' => $this->comp_id);
                $this->mg_category_id = $category_id_found;
                
                foreach ($this->mg_category_row_ids as $mg_category_row_id) {
                    
                    $this->setValues($mg_category_row_id, 'catalog_category_entity', $sl_credentials, $this->category_entity_type_id, 0, false, false, $this->mg_category_row_ids);

                }

                return true;

            }

        }

        return false;

    }

    /**
     * Function to create Sales Layer category.
     * @param int $saleslayer_id                Sales Layer category id
     * @return boolean                          result of category creation
     */
    private function create_category_db($saleslayer_id) {

        $category_table = $this->getTable('catalog_category_entity');
        $table_status = $this->connection->showTableStatus($category_table);
        
        if ($this->mg_edition == 'enterprise'){

            $row_id = $table_status['Auto_increment'];

            $sequence_category_table = $this->getTable('sequence_catalog_category');
            $table_sequence_status = $this->connection->showTableStatus($sequence_category_table);
        
            $entity_id = $table_sequence_status['Auto_increment'];

            $sequence_values = [
                'sequence_value' => $entity_id
            ];
        
            $result_sequence_create = $this->connection->insertOnDuplicate(
                $sequence_category_table,
                $sequence_values,
                array_keys($sequence_values)
            );
        
            if ($result_sequence_create){

                $values = [
                    'entity_id' => $entity_id,
                    'attribute_set_id' => $this->category_entity_type_id, 
                    'parent_id' => 1,
                    'path' => '1/'.$entity_id,
                    'row_id' => $row_id
                ];

                $result_create = $this->connection->insertOnDuplicate(
                    $category_table,
                    $values,
                    array_keys($values)
                );

                if (!$result_create){

                    $this->connection->delete(
                        $sequence_category_table,
                        ['sequence_value = ?' => $entity_id]
                    );

                    return false;

                }

            }else{

                return false;

            }

        }else{

            $entity_id = $table_status['Auto_increment'];

            $values = [
                'entity_id' => $entity_id,
                'attribute_set_id' => $this->category_entity_type_id, 
                'parent_id' => 1,
                'path' => '1/'.$entity_id,
            ];

            $result_create = $this->connection->insertOnDuplicate(
                $category_table,
                $values,
                array_keys($values)
            );

        }

        if ($result_create){

            if ($this->mg_edition == 'enterprise'){

                $this->mg_category_row_ids = array($row_id);
                $this->mg_category_current_row_id = $row_id;

            }else{

                $this->mg_category_row_ids = array($entity_id);
                $this->mg_category_current_row_id = $entity_id;

            }

            $sl_credentials = array('is_active' => 1, 'saleslayer_id' => $saleslayer_id, 'saleslayer_comp_id' => $this->comp_id);
            $this->category_created = true;
            $this->mg_category_id = $entity_id;
            
            foreach ($this->mg_category_row_ids as $mg_category_row_id) {
            
                $this->setValues($mg_category_row_id, 'catalog_category_entity', $sl_credentials, $this->category_entity_type_id, 0, false, false, $this->mg_category_row_ids);

            }

            return true;

        }

        return false;

    }

    /**
     * Function to check if Sales Layer category exists.
     * @param array $category                   category to synchronize
     * @return boolean                          result of category check
     */
    private function check_category_db(array $category): bool
    {
        $saleslayer_category_id = (int) $category[$this->category_field_id];
        $saleslayer_parent_category_id = (int) $category[$this->category_field_catalogue_parent_id];

        if ($saleslayer_parent_category_id !== 0) {

            $this->find_saleslayer_category_id_db($saleslayer_parent_category_id, 0, 'parent');

            if (null === $this->mg_parent_category_id){
                $this->debbug('## Error. Category has no parent.');
                return false;
            }

        }

        $this->find_saleslayer_category_id_db($saleslayer_category_id);

        $category_assigned = false;

        if (null === $this->mg_category_id) {

            if (isset($category['data'][$this->category_field_name]) && $category['data'][$this->category_field_name] != ''){
                
                $transliterator = \Transliterator::createFromRules(':: Any-Latin; :: Latin-ASCII; :: NFD; :: [:Nonspacing Mark:] Remove; :: Lower(); :: NFC;', \Transliterator::FORWARD);
                $normalized = $transliterator->transliterate($category['data'][$this->category_field_name]);
                $url_key = $this->categoryModel->formatUrlKey($normalized);
                $category_assigned = $this->assignSaleslayerCategoryByUrlKey($url_key, $saleslayer_category_id);

            }

        } else {

            $category_assigned = true;

        }

        if (false === $category_assigned) {
        
            if (false === $this->create_category_db($saleslayer_category_id)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Function to get table name with prefix.
     * @param string $tableName               table to search
     * @return string                         table in database with prefix
     */
    private function getTable($tableName){
        
        $tableNameReturn = $this->connection->getTableName($tableName);

        if ($this->connection->isTableExists($tableNameReturn)){

            $tablePrefix = $this->getTablePrefix();

            if ($tablePrefix && strpos($tableNameReturn, $tablePrefix) !== 0) {

                $tableNameReturn = $tablePrefix . $tableNameReturn;

            }

            if (!isset($this->tables_identifiers[$tableNameReturn])){

                $this->tables_identifiers[$tableNameReturn] = $this->getColumnIdentifier($tableNameReturn);

            }

            return $tableNameReturn;

        }

        if (!in_array($tableName, $this->mg_tables_23)){

            $this->debbug('## Error. The table '.$tableName.' does not exist.');

        }

        return null;

    }

    /**
     * Function to get table prefix
     * @return string                        configuration table prefix
     */
    private function getTablePrefix(){

        if (null === $this->tablePrefix) {
                
            $this->tablePrefix = (string)$this->deploymentConfig->get(
                ConfigOptionsListConstants::CONFIG_PATH_DB_PREFIX
            );
        
        }
        
        return $this->tablePrefix;
    }

    /**
     * Function to set values to attributes
     * @param int $entityId                             Magento entity id
     * @param string $entityTable                       Magento table to process data
     * @param array $values                             values to process
     * @param int $entityTypeId                         entity type id of item
     * @param int $storeId                              store view id to process data
     * @param boolean $store_global_attributes          if true, stores global attributes into class variables to avoid processing in all stores
     * @param boolean $product_additional_fields        if true, gets attribute data and extracts attribute value 
     * @return void
     */
    private function setValues($entityId, $entityTable, $values, $entityTypeId, $storeId, $store_global_attributes = false, $product_additional_fields = false, array $row_ids = []){ 

        $tables_insert_values = [];

        $time_ini_set_value_all_attributes = microtime(1);
        
        foreach ($values as $code => $value) {

            $tables_insert_value = $this->setValue($entityId, $entityTable, $code, $value, $entityTypeId, $storeId, $store_global_attributes, $product_additional_fields, $row_ids);

            if (is_array($tables_insert_value)){

                if (!isset($tables_insert_values[$tables_insert_value['attribute_table']])){

                    $tables_insert_values[$tables_insert_value['attribute_table']] = [];

                }

                $tables_insert_values[$tables_insert_value['attribute_table']][] = $tables_insert_value['values'];

           }

        }

        if ($this->sl_DEBBUG > 2) $this->debbug('# time_set_value_all_attributes: ', 'timer', (microtime(1) - $time_ini_set_value_all_attributes));

        $this->insertNewAttributes($tables_insert_values);

    }

    /**
     * Function to set value to attributes
     * @param int $entityId                             Magento entity id
     * @param string $entityTable                       Magento table to process data
     * @param string $code                              attribute code
     * @param array $values                             value to process
     * @param int $entityTypeId                         entity type id of item
     * @param int $storeId                              store view id to process
     * @param boolean $store_global_attributes          if true, stores global attributes into class variables to avoid processing in all stores
     * @param boolean $product_additional_fields        if true, gets attribute data and extracts attribute value 
     */
    private function setValue($entityId, $entityTable, $code, $value, $entityTypeId, $storeId, $store_global_attributes = false, $product_additional_fields = false, array $row_ids = []){

        $time_ini_set_value_attribute = microtime(1);

        $result_get = $this->getAttributeAndValue($code, $entityId, $entityTypeId, $storeId, $value, $product_additional_fields);

        if (!$result_get){

            return false;

        }

        if (isset($result_get['value'])){

            $value = $result_get['value'];

        }

        $attribute = $result_get['attribute'];
        
        $time_ini_identify_attribute = microtime(1);

        $backendType = $attribute[\Magento\Eav\Api\Data\AttributeInterface::BACKEND_TYPE];

        if ($backendType != 'static'){

           $entityTable  .= '_' . $backendType;

        }

        $attribute_table = $this->getTable($entityTable);

        if (null === $attribute_table){ 

            return false; 

        }

        $identifier = $this->tables_identifiers[$attribute_table];

        if ($this->sl_DEBBUG > 2) $this->debbug('# time_identify_attribute: ', 'timer', (microtime(1) - $time_ini_identify_attribute));

        if ($attribute[\Magento\Catalog\Model\ResourceModel\Eav\Attribute::KEY_IS_GLOBAL] == $this->scope_global){

            if(!$this->globalizeAttribute($store_global_attributes, $attribute[\Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_ID], $attribute_table, $identifier, $entityId)){
                return false;
            };

            $storeIdTemp = $storeId;
            $storeId = 0;

        }
       
        $tables_insert_value = $this->saveAttributeValue($attribute, $attribute_table, $identifier, $entityId, $storeId, $value);

        $time_ini_restore_store = microtime(1);

        if ($attribute[\Magento\Catalog\Model\ResourceModel\Eav\Attribute::KEY_IS_GLOBAL] == $this->scope_global){

            $storeId = $storeIdTemp;

            if ($store_global_attributes && ((array_search($entityId, $row_ids) === (count($row_ids) - 1)))) {

                $this->processed_global_attributes[$attribute[\Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_ID]] = 0;

            }

        }

        if ($this->sl_DEBBUG > 2) $this->debbug('# time_restore_store: ', 'timer', (microtime(1) - $time_ini_restore_store));
        if ($this->sl_DEBBUG > 2) $this->debbug('# time_set_value_attribute: ', 'timer', (microtime(1) - $time_ini_set_value_attribute));

        return $tables_insert_value;

    }

    /**
     * Function to save attribute value
     * @param array $attribute              attribute data
     * @param string $attribute_table       attribute table
     * @param string $identifier            table identifier
     * @param int $entityId                 Magento entity id
     * @param int $storeId                  store view id to process
     * @param string $value                 value to process
     * @return array|false                  data to insert
     */
    private function saveAttributeValue($attribute, $attribute_table, $identifier, $entityId, $storeId, $value){

        $time_ini_read_datos = microtime(1);
        
        $datos = $this->connection->fetchRow(
                    $this->connection->select()
                    ->from(
                        $attribute_table,
                        ['value_id', 'value']
                    )->where('attribute_id' . ' = ?', $attribute[\Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_ID])
                    ->where('store_id' . ' = ?', $storeId)
                    ->where($identifier . ' = ?', $entityId)
                    ->limit(1)
                );

        if ($this->sl_DEBBUG > 2) $this->debbug('# time_read_datos: ', 'timer', (microtime(1) - $time_ini_read_datos));

        $time_ini_store_value = microtime(1);
        
        if (empty($datos) || (!empty($datos) && !isset($datos['value_id']))){    

            $values = [
                'attribute_id' => $attribute[\Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_ID],
                'store_id' => $storeId,
                $identifier => $entityId,
                'value' => $value
            ];

            $tables_insert_value = [
                'attribute_table' => $attribute_table,
                'values' => $values
            ];

            if ($this->sl_DEBBUG > 2) $this->debbug('# time_store_value: ', 'timer', (microtime(1) - $time_ini_store_value));

            return $tables_insert_value;

        }

        $this->updateAttribute($datos, $attribute, $value, $attribute_table);

        if ($this->sl_DEBBUG > 2) $this->debbug('# time_store_value: ', 'timer', (microtime(1) - $time_ini_store_value));
        return false;

    }

    /**
     * Function to get attribute and value
     * @param  string $code                         attribute code
     * @param  int $entityId                        Magento entity id
     * @param  int $entityTypeId                    entity type id of item
     * @param  int $storeId                         store view id to process
     * @param  string $value                        value to extract
     * @param  boolean $product_additional_fields   if true, gets attribute data and extracts attribute value
     * @return array|false                          attribute and value
     */
    private function getAttributeAndValue($code, $entityId, $entityTypeId, $storeId, $value, $product_additional_fields = false){

        $return_array = [];

        if ($product_additional_fields){

            $time_ini_get_attribute_additional = microtime(1);
            $attribute = $this->getAttributeAdditional($code, $entityTypeId, $this->mg_product_attribute_set_id);
            if ($this->sl_DEBBUG > 2) $this->debbug('# time_get_attribute_additional: ', 'timer', (microtime(1) - $time_ini_get_attribute_additional));

            if (empty($attribute)){

                return false;

            }

            if ($value != ''){
                
                $time_ini_extract_additional_value = microtime(1);
                $value = $this->extractAdditionalValue($entityId, $storeId, $attribute, $value);

                if ($attribute['frontend_input'] == 'media_image'){
                
                    // Value stored in global param, will be processed at the image preparation
                    if ($this->sl_DEBBUG > 2) $this->debbug('# time_extract_additional_value: ', 'timer', (microtime(1) - $time_ini_extract_additional_value));
                    return false;
                
                }

                $return_array['value'] = $value;
                
                if ($this->sl_DEBBUG > 2) $this->debbug('# time_extract_additional_value: ', 'timer', (microtime(1) - $time_ini_extract_additional_value));

            }

        }else{

            $time_ini_get_attribute_wysiwyg = microtime(1);
            $attribute = $this->getAttributeWysiwyg($code, $entityTypeId);
            if ($this->sl_DEBBUG > 2) $this->debbug('# time_get_attribute_wysiwyg: ', 'timer', (microtime(1) - $time_ini_get_attribute_wysiwyg));

        }

        if (empty($attribute)){

            if (!isset($this->inexistent_attributes[$code])) {

                $this->inexistent_attributes[$code] = 0;

                if (!in_array($code, array('length', 'width', 'height'))){

                    $this->debbug('## Error. The attribute with code '.$code.' does not exist, we cannot set data.');

                }

            }
            
            return false;

        }

        if (!isset($attribute[\Magento\Eav\Api\Data\AttributeInterface::BACKEND_TYPE]) || (isset($attribute[\Magento\Eav\Api\Data\AttributeInterface::BACKEND_TYPE]) && $attribute[\Magento\Eav\Api\Data\AttributeInterface::BACKEND_TYPE] === 'static')){
                            
            return false;

        }

        $return_array['attribute'] = $attribute;

        return $return_array;

    }

    /**
     * Function to extract value from Sales Layer array
     * @param int $entityId                         Magento entity id
     * @param int $store_view_id                    store view id to extract value
     * @param array $attribute                      attribute to get value
     * @param string $sl_value                      Sales Layer incoming value
     * @return string $additional_field_value       field value found
     */
    private function extractAdditionalValue($entityId, $store_view_id, $attribute, $sl_value){

        switch ($attribute['frontend_input']){
            case 'media_image':

                if ($sl_value == ''){

                    return false;

                }else{

                    if (!isset($this->product_additional_fields_images[$entityId][$attribute['attribute_code']])){

                        if (null !== $this->mg_format_id){

                            $type = 'product_formats';

                        }else{
                        
                            $type = 'products';

                        }
                            
                        $media = $this->get_media_field_value($type, $attribute['attribute_code'], $sl_value);

                        if ($media){

                            $this->product_additional_fields_images[$entityId][$attribute['attribute_code']] = $media;

                        }

                    }

                } 

                break;

            case 'multiselect':

                $value_to_update = $sl_options = '';

                (is_array($sl_value)) ? $sl_options = $sl_value : $sl_options = array($sl_value);

                foreach ($sl_options as $additional_field_value) {

                    $value_found = $this->find_attribute_option_value_db($attribute['attribute_set_id'], $attribute['attribute_id'], $additional_field_value, $store_view_id);
                   
                    if ($value_found){

                        if ($value_to_update == ''){

                            $value_to_update = $value_found;

                        }else{

                            $value_to_update .= ','.$value_found;

                        }

                    }

                }

                if ($value_to_update != ''){

                    return $value_to_update;

                }

                break;

            case 'select':

                $additional_field_value = '';
                
                (is_array($sl_value)) ? $additional_field_value = reset($sl_value) : $additional_field_value = $sl_value;
                
                $attribute_value_id = $this->find_attribute_option_value_db($attribute['attribute_set_id'], $attribute['attribute_id'], $additional_field_value, $store_view_id);
                                        
                if ($attribute_value_id){

                    return $attribute_value_id;
                
                }

                break;

            case 'price':

                $additional_field_value = '';
                
                (is_array($sl_value)) ? $additional_field_value = reset($sl_value) : $additional_field_value = $sl_value;

                if (!is_numeric($additional_field_value) && filter_var($additional_field_value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)){

                    $value_to_update = filter_var($additional_field_value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

                }else{

                    $value_to_update = $additional_field_value;

                }

                return $value_to_update;

                break;

            case 'boolean':

                $additional_field_value = '';

                (is_array($sl_value)) ? $additional_field_value = reset($sl_value) : $additional_field_value = $sl_value;
                
                $value_to_update = filter_var($additional_field_value, FILTER_VALIDATE_BOOLEAN);

                return $value_to_update;
                
                break;

            case 'date':

                $additional_field_value = '';

                (is_array($sl_value)) ? $additional_field_value = reset($sl_value) : $additional_field_value = $sl_value;
                
                return $additional_field_value;
                
                break;

            case 'weee':

                break;

            default:

                $additional_field_value = '';
                
                (is_array($sl_value)) ? $additional_field_value = implode(', ', array_filter($sl_value, array($this, 'array_filter_empty_value'))) : $additional_field_value = $sl_value;
                
                $additional_field_value = $this->sl_check_html_text($additional_field_value);

                return $additional_field_value;
                
                break;
        }

        return '';

    }

    /**
     * Function to get values from Magento database
     * @param int $entityId                         Magento entity id
     * @param string $entityTable                   Magento table to process data
     * @param array $values                         values to process
     * @param int $entityTypeId                     entity type id of item
     * @param int $storeId                          store view id to search values
     * @return array $values_to_return              values found
     */
    private function getValues($entityId, $entityTable, $values, $entityTypeId, $storeId = 0){
        
        $values_to_return  = [];
        $values_codes = array_keys($values);

        foreach ($values_codes as $code) {

            $globalStoreId = '';
            $attribute = $this->getAttributeWysiwyg($code, $entityTypeId);
        
            if (empty($attribute)) {
                continue;
            }

            if (!isset($attribute[\Magento\Eav\Api\Data\AttributeInterface::BACKEND_TYPE])) {
                continue;
            }

            $backendType = $attribute[\Magento\Eav\Api\Data\AttributeInterface::BACKEND_TYPE];

            $entity_table = $entityTable;

            if ($backendType != 'static'){

               $entity_table  .= '_' . $backendType;

            }
       
            $attribute_table = $this->getTable($entity_table);
            
            if (null === $attribute_table){ 
                continue; 
            }

            $identifier = $this->tables_identifiers[$attribute_table];
        
            if ($attribute[\Magento\Catalog\Model\ResourceModel\Eav\Attribute::KEY_IS_GLOBAL] == $this->scope_global){

                $globalStoreId = 0;

            }

            $datos = $this->connection->fetchRow(
                            $this->connection->select()
                            ->from(
                                $attribute_table,
                                ['value_id', 'value']
                            )->where('attribute_id' . ' = ?', $attribute[\Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_ID])
                            ->where('store_id' . ' = ?', (($globalStoreId !== '') ? $globalStoreId : $storeId))
                            ->where($identifier . ' = ?', $entityId)
                            ->limit(1)
                            );

            if (!empty($datos) && isset($datos['value_id'])){

                $values_to_return[$code] = $datos['value'];

            }

        }

        return $values_to_return;

    }

    /**
     * Function to set category image
     * @param int $entityId                         Magento entity id
     * @param string $entityTable                   Magento table to process data
     * @param array $values                         values to process
     * @param int $entityTypeId                     entity type id of item
     * @param int $storeId                          store view id to search values
     * @return void
     */
    private function setCategoryImage($entityId, $entityTable, $values, $entityTypeId, $storeId){
        
        $tables_insert_values = [];

        if (!empty($values)){

            $this->debbug(" > SL category image data to sync: ".print_r($values,1));
            
            foreach ($values as $code => $value) {
    
                $image_data = $this->checkSlImages($value);
                
                if ($image_data['sl_category_image_url'] == ''){
    
                    //La imagen de SL no existe o es incorrecta, saltamos y dejamos la actual de MG.
                    continue;
    
                }

                $attribute_info = $this->getCategoryAttributeInfo($code, $entityTable, $entityTypeId, $entityId);
                if ($attribute_info === false) continue;

                $attribute = $attribute_info['attribute'];
                $attribute_table = $attribute_info['attribute_table'];
                $identifier = $attribute_info['identifier'];
    
                $time_ini_check_mg_image = microtime(1);
                $datos = $this->connection->fetchRow(
                                $this->connection->select()
                                ->from(
                                    $attribute_table,
                                    ['value_id', 'value']
                                )->where('attribute_id' . ' = ?', $attribute[\Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_ID])
                                ->where('store_id' . ' = ?', $storeId)
                                ->where($identifier . ' = ?', $entityId)
                                ->limit(1)
                                );
    
                if (empty($datos) || (!empty($datos) && !isset($datos['value_id']))){
    
                    $tables_insert_values = $this->prepareImageInsert($image_data, $attribute, $storeId, $identifier, $entityId, $tables_insert_values, $attribute_table );
                    
                }else{
    
                    $this->updateCategoryImage($datos, $image_data, $attribute_table,  $time_ini_check_mg_image );
    
                }
    
                if ($attribute[\Magento\Catalog\Model\ResourceModel\Eav\Attribute::KEY_IS_GLOBAL] == $this->scope_global){
    
                    $this->processed_global_attributes[$attribute[\Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_ID]] = 0;
               
                }
    
            }
    
            $this->insertNewAttributes($tables_insert_values);

        }else{

            $this->debbug(" > Deleting SL category image");

            $attribute_info = $this->getCategoryAttributeInfo('image', $entityTable, $entityTypeId, $entityId);
            if ($attribute_info === false) return false;

            $attribute = $attribute_info['attribute'];
            $attribute_table = $attribute_info['attribute_table'];
            $identifier = $attribute_info['identifier'];

            $time_ini_delete_mg_image = microtime(1);
            try{

                $this->connection->delete(
                    $attribute_table, 
                    ['attribute_id = ?' => $attribute[\Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_ID],
                    'store_id' . ' = ?' => $storeId,
                    $identifier . ' = ?' => $entityId]
                );

                if ($attribute[\Magento\Catalog\Model\ResourceModel\Eav\Attribute::KEY_IS_GLOBAL] == $this->scope_global){
    
                    $this->processed_global_attributes[$attribute[\Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_ID]] = 0;
               
                }
            
            }catch(\Exception $e){

                $this->debbug('## Error. Deleting category image: '.print_r($e->getMessage(),1));

            }
            if ($this->sl_DEBBUG > 2) $this->debbug('## time_delete_mg_image: ', 'timer', (microtime(1) - $time_ini_delete_mg_image));

        }

    }

    /**
     * Function to get category attribute info
     * @param string $code                          attribute code
     * @param string $entityTable                   Magento table to process data
     * @param int $entityTypeId                     entity type id of item
     * @param int $entityId                         Magento entity id
     * @return void
     */
    private function getCategoryAttributeInfo($attribute_code, $entityTable, $entityTypeId, $entityId){

        $attribute_info = [];

        $attribute = $this->getAttributeWysiwyg($attribute_code, $entityTypeId);
    
        if (empty($attribute) ||
            !isset($attribute[\Magento\Eav\Api\Data\AttributeInterface::BACKEND_TYPE]) || 
            (isset($attribute[\Magento\Eav\Api\Data\AttributeInterface::BACKEND_TYPE]) && 
            $attribute[\Magento\Eav\Api\Data\AttributeInterface::BACKEND_TYPE] === 'static')){
            
            return false;

        }

        $backendType = $attribute[\Magento\Eav\Api\Data\AttributeInterface::BACKEND_TYPE];
        $attribute_table = $this->getTable($entityTable . '_' . $backendType);

        if (null === $attribute_table){ 
            return false; 
        }

        $identifier = $this->tables_identifiers[$attribute_table];

        if ($attribute[\Magento\Catalog\Model\ResourceModel\Eav\Attribute::KEY_IS_GLOBAL] == $this->scope_global){

            // Enviamos true para que siempre valide si el atributo ha sido procesado
            if(!$this->globalizeAttribute(true, $attribute[\Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_ID], $attribute_table, $identifier, $entityId)){
                return false;
            };

        }

        return ['attribute' => $attribute,
                'attribute_table' => $attribute_table,
                'identifier' => $identifier];

    }

    /**
     * Function to read remote or local file size 
     * @param string $url                   remote or local file url 
     * @return int $url_filesize            file size
     */
    private function sl_get_file_size($url){

        if (strpos($url, 'http') !== false){

            try{

                $time_ini_check_url_size = microtime(1);
                
                if (isset($this->stored_url_files_sizes[$url])){

                    if ($this->sl_DEBBUG > 2) $this->debbug('# time_check_size_url stored: ', 'timer', (microtime(1) - $time_ini_check_url_size));
                    return $this->stored_url_files_sizes[$url];

                }else{
                    
                    $headers = @get_headers($url, TRUE);
                    
                    if (strpos($headers[0], '200') === false) {

                        $fileinfo = pathinfo($url);

                        if (strpos($url, '%') !== false) {

                            $url = $fileinfo['dirname'].'/'.rawurldecode($fileinfo['basename']);
                            $headers = @get_headers($url, TRUE);
                            
                        }else{

                            $url = $fileinfo['dirname'].'/'.rawurlencode($fileinfo['basename']);
                            $headers = @get_headers($url, TRUE);

                        }

                    }

                    if ($this->sl_DEBBUG > 2) $this->debbug('# time_check_size_url: ', 'timer', (microtime(1) - $time_ini_check_url_size));

                }

            }catch(\Exception $e){

                $this->debbug("## Error. Remote image with URL ".$url." couldn't been synchronized: ".$e->getMessage());
                return false;

            }

            if (isset($headers['Content-Length'])){
               
               $this->stored_url_files_sizes[$url] = $headers['Content-Length'];
               return $headers['Content-Length'];
            
            }else if (isset($headers['content-length'])){
            
                $this->stored_url_files_sizes[$url] = $headers['content-length'];
                return $headers['content-length'];
            
            }
        
            return false;

        }else{

            try{

                $time_ini_check_local_size = microtime(1);
                $url_filesize = filesize($url);
                clearstatcache();
                if ($this->sl_DEBBUG > 2) $this->debbug('# time_check_size_local: ', 'timer', (microtime(1) - $time_ini_check_local_size));
                return $url_filesize; 

            }catch(\Exception $e){

                $this->debbug("## Notice. Could not read local image with URL ".$url." : ".$e->getMessage());

            }

            return false;

        }

    }

    /**
     * Function to set product image types
     * @param int $entityId                         Magento entity id
     * @param string $entityTable                   Magento table to process data
     * @param array $values                         values to process
     * @param int $entityTypeId                     entity type id of item
     * @return void
     */
    private function setProductImageTypes($entityId, $entityTable, $values, $entityTypeId){

        if (null === $this->mg_product_attribute_set_id){

            $this->debbug('## Error. Product does not have attribute set id. Cannot update product image types: '.print_r($this->mg_product_attribute_set_id,1));
            return false;

        }

        $tables_insert_values = [];

        foreach ($values as $code => $value) {
        
            $attribute = $this->getAttributeAdditional($code, $entityTypeId, $this->mg_product_attribute_set_id);

            if (empty($attribute)){
                
                $this->debbug('## Error. The attribute '.$code.' does not exist or it is not associated to the product attribute set id.');
                continue;

            }

            if (!isset($attribute[\Magento\Eav\Api\Data\AttributeInterface::BACKEND_TYPE]) || (isset($attribute[\Magento\Eav\Api\Data\AttributeInterface::BACKEND_TYPE]) && $attribute[\Magento\Eav\Api\Data\AttributeInterface::BACKEND_TYPE] === 'static')){
                                
                $this->debbug('## Error. The attribute '.$code.' does not have backend type or is static.');
                continue;

            }

            $backendType = $attribute[\Magento\Eav\Api\Data\AttributeInterface::BACKEND_TYPE];
            $attribute_table = $this->getTable($entityTable. '_' . $backendType);

            if (null === $attribute_table) {
                continue;
            }

            $identifier = $this->tables_identifiers[$attribute_table];
            
            if ($attribute[\Magento\Catalog\Model\ResourceModel\Eav\Attribute::KEY_IS_GLOBAL] != $this->scope_global){

                //Enviamos false para que siempre elimine los atributos de otras tiendas
                if(!$this->globalizeAttribute(false, $attribute[\Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_ID], $attribute_table, $identifier, $entityId)){
                    continue;
                };

            }

            $datos = $this->connection->fetchRow(
                        $this->connection->select()
                        ->from(
                            $attribute_table,
                            ['value_id', 'value']
                        )->where('attribute_id' . ' = ?', $attribute[\Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_ID])
                        ->where('store_id' . ' = ?', 0)
                        ->where($identifier . ' = ?', $entityId)
                        ->limit(1)
                    );

            if (empty($datos) || (!empty($datos) && !isset($datos['value_id']))){

                if ($value == ''){

                    continue;

                }

                if (!isset($tables_insert_values[$attribute_table])){ $tables_insert_values[$attribute_table] = []; }
                $values = array('attribute_id' => $attribute[\Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_ID],
                                'store_id' => 0,
                                $identifier => $entityId,
                                'value' => $value);
                $tables_insert_values[$attribute_table][] = $values;

            }else{

                $datos_value = $datos['value'];

                if ($datos_value != $value){

                    if ($value == ''){

                        $query_delete = " DELETE FROM ".$attribute_table." WHERE value_id = ".$datos['value_id'];
                        $this->sl_connection_query($query_delete);

                    }else{

                        try{

                            $this->connection->update($attribute_table, ['value' => $value], 'value_id = ' . $datos['value_id']);

                        }catch(\Exception $e){

                            $this->debbug('## Error. Updating value: '.print_r($e->getMessage(),1));

                        }

                    }

                }

            }

        }

        if (!empty($tables_insert_values)){

            foreach ($tables_insert_values as $table_name => $table_values) {
                
                try{

                    $this->connection->insertMultiple($table_name, $table_values);
                
                }catch(\Exception $e){

                    $this->debbug('## Error. Inserting multiple attributes values: '.$e->getMessage());

                }

            }

        }

    }

    /**
     * Function to get attribute with wysiwyg option value
     * @param string $code                              attribute code to search
     * @param int $entityTypeId                         entity type id of item
     * @return boolean|array                            attribute found
     */
    private function getAttributeWysiwyg($code, $entityTypeId){
        
        $attribute = $this->connection->fetchRow(
            $this->connection->select()
                ->from(
                    ['a' => $this->getTable('eav_attribute')],
                    [
                        \Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_ID,
                        \Magento\Eav\Api\Data\AttributeInterface::BACKEND_TYPE
                    ]
                )
                ->where(\Magento\Eav\Api\Data\AttributeInterface::ENTITY_TYPE_ID . ' = ?', $entityTypeId)
                ->where(\Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_CODE . ' = ?', $code)
                ->joinInner(['c' => $this->getTable('catalog_eav_attribute')],
                            'c.attribute_id = a.attribute_id',
                            [\Magento\Catalog\Api\Data\EavAttributeInterface::IS_WYSIWYG_ENABLED,
                            \Magento\Catalog\Model\ResourceModel\Eav\Attribute::KEY_IS_GLOBAL]
                    )
                ->limit(1)
        );

        if (empty($attribute)) {
            return false;
        }

        return $attribute;

    }

    /**
     * Function to check if attribute is set in the attribute set id
     * @param string $code                          attribute code to search
     * @param int $entityTypeId                     entity type id of item
     * @param int $attributeSetId                   attribute set id
     * @return boolean                              if attribute is set
     */
    private function checkAttributeInSetId($code, $entityTypeId, $attributeSetId){
        $attribute = $this->connection->fetchRow(
            $this->connection->select()
                ->from(
                    ['ea' => $this->getTable('eav_attribute')],
                    [
                        \Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_ID,
                        \Magento\Eav\Api\Data\AttributeInterface::ENTITY_TYPE_ID,
                        \Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_CODE,
                        \Magento\Eav\Api\Data\AttributeInterface::FRONTEND_INPUT,
                        \Magento\Eav\Api\Data\AttributeInterface::BACKEND_TYPE
                    ]
                )
                ->where('ea.'.\Magento\Eav\Api\Data\AttributeInterface::ENTITY_TYPE_ID . ' = ?', $entityTypeId)
                ->where('ea.'.\Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_CODE . ' = ?', $code)
                ->where('eas.'.\Magento\Eav\Api\Data\AttributeGroupInterface::ATTRIBUTE_SET_ID . ' = ?', $attributeSetId)
                ->joinLeft(['eas' => $this->getTable('eav_attribute_set')],
                            'ea.entity_type_id = eas.entity_type_id',
                            [\Magento\Eav\Api\Data\AttributeGroupInterface::ATTRIBUTE_SET_ID]
                    )
                ->joinRight(['eea' => $this->getTable('eav_entity_attribute')],
                            'ea.entity_type_id = eea.entity_type_id AND eas.attribute_set_id = eea.attribute_set_id AND ea.attribute_id = eea.attribute_id',
                            []
                    )
                ->joinInner(['cea' => $this->getTable('catalog_eav_attribute')],
                            'ea.attribute_id = cea.attribute_id',
                            [\Magento\Catalog\Api\Data\EavAttributeInterface::IS_WYSIWYG_ENABLED,
                            \Magento\Catalog\Model\ResourceModel\Eav\Attribute::KEY_IS_GLOBAL]
                    )
                ->limit(1)
        );

        if (empty($attribute)) {
        
            return false;
        
        }else{

            return true;

        }

    }

    /**
     * Function to get attribute with wysiwyg option value, if it's set in the attribute set id, and load it's options
     * @param string $code                              attribute code to search
     * @param int $entityTypeId                         entity type id of item
     * @param int $attributeSetId                       attribute set id
     * @return boolean|array                            attribute found
     */
    private function getAttributeAdditional($code, $entityTypeId, $attributeSetId){
        
        $attribute = $this->connection->fetchRow(
            $this->connection->select()
                ->from(
                    ['ea' => $this->getTable('eav_attribute')],
                    [
                        \Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_ID,
                        \Magento\Eav\Api\Data\AttributeInterface::ENTITY_TYPE_ID,
                        \Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_CODE,
                        \Magento\Eav\Api\Data\AttributeInterface::FRONTEND_INPUT,
                        \Magento\Eav\Api\Data\AttributeInterface::BACKEND_TYPE
                    ]
                )
                ->where('ea.'.\Magento\Eav\Api\Data\AttributeInterface::ENTITY_TYPE_ID . ' = ?', $entityTypeId)
                ->where('ea.'.\Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_CODE . ' = ?', $code)
                ->where('eas.'.\Magento\Eav\Api\Data\AttributeGroupInterface::ATTRIBUTE_SET_ID . ' = ?', $attributeSetId)
                ->joinLeft(['eas' => $this->getTable('eav_attribute_set')],
                            'ea.entity_type_id = eas.entity_type_id',
                            [\Magento\Eav\Api\Data\AttributeGroupInterface::ATTRIBUTE_SET_ID]
                    )
                ->joinRight(['eea' => $this->getTable('eav_entity_attribute')],
                            'ea.entity_type_id = eea.entity_type_id AND eas.attribute_set_id = eea.attribute_set_id AND ea.attribute_id = eea.attribute_id',
                            []
                    )
                ->joinInner(['cea' => $this->getTable('catalog_eav_attribute')],
                            'ea.attribute_id = cea.attribute_id',
                            [\Magento\Catalog\Api\Data\EavAttributeInterface::IS_WYSIWYG_ENABLED,
                            \Magento\Catalog\Model\ResourceModel\Eav\Attribute::KEY_IS_GLOBAL]
                    )
                ->limit(1)
        );

        if (empty($attribute)) {
        
            return false;
        
        }else{

            if (in_array($attribute['frontend_input'], array('select', 'multiselect'))){

                if (!isset($this->attributes_options_collection[$attributeSetId])){

                    $this->attributes_options_collection[$attributeSetId] = [];

                }

                if (!isset($this->attributes_options_collection[$attributeSetId][$attribute['attribute_id']])){

                    $this->attributes_options_collection[$attributeSetId][$attribute['attribute_id']] = array('attribute_id' => $attribute['attribute_id'],
                                                                                                    'attribute_code' => $attribute['attribute_code'],
                                                                                                    'frontend_input' => $attribute['frontend_input']);
                    
                }

                if (!isset($this->attributes_options_collection[$attributeSetId][$attribute['attribute_id']]['options'])){

                    if (!empty($this->store_view_ids)){

                        $store_view_ids = $this->store_view_ids;
                        if (!in_array(0, $store_view_ids)){ $store_view_ids[] = 0; }

                        foreach ($store_view_ids as $store_view_id) {
                
                            $optioncollection = clone $this->collectionOption;

                            $options = $optioncollection
                                ->setAttributeFilter($attribute['attribute_id'])
                                ->setStoreFilter($store_view_id,false)
                                ->load();

                            if (!empty($options->getData())){   

                                $this->attributes_options_collection[$attributeSetId][$attribute['attribute_id']]['options'][$store_view_id] = [];

                                foreach ($options->getData() as $option) {

                                    $this->attributes_options_collection[$attributeSetId][$attribute['attribute_id']]['options'][$store_view_id][strtolower($option['value'])] = $option['option_id'];

                                }

                            }

                        }

                    }

                }

            }

        }

        return $attribute;
    }

    /**
     * Function to get attribute, if it's set in the attribute set id without loading it's options
     * @param string $code                              attribute code to search
     * @param int $entityTypeId                         entity type id of item
     * @param int $attributeSetId                       attribute set id
     * @return boolean|array                            attribute found
     */
    private function getAttributeInSetById($attributeId, $entityTypeId, $attributeSetId){
        
        $attribute = $this->connection->fetchRow(
            $this->connection->select()
                ->from(
                    ['ea' => $this->getTable('eav_attribute')],
                    [
                        \Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_ID,
                        \Magento\Eav\Api\Data\AttributeInterface::ENTITY_TYPE_ID,
                        \Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_CODE,
                        \Magento\Eav\Api\Data\AttributeInterface::FRONTEND_INPUT,
                        \Magento\Eav\Api\Data\AttributeInterface::FRONTEND_LABEL,
                        \Magento\Eav\Api\Data\AttributeInterface::BACKEND_TYPE
                    ]
                )
                ->where('ea.'.\Magento\Eav\Api\Data\AttributeInterface::ENTITY_TYPE_ID . ' = ?', $entityTypeId)
                ->where('ea.'.\Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_ID . ' = ?', $attributeId)
                ->where('eas.'.\Magento\Eav\Api\Data\AttributeGroupInterface::ATTRIBUTE_SET_ID . ' = ?', $attributeSetId)
                ->joinLeft(['eas' => $this->getTable('eav_attribute_set')],
                            'ea.entity_type_id = eas.entity_type_id',
                            [\Magento\Eav\Api\Data\AttributeGroupInterface::ATTRIBUTE_SET_ID]
                    )
                ->joinRight(['eea' => $this->getTable('eav_entity_attribute')],
                            'ea.entity_type_id = eea.entity_type_id AND eas.attribute_set_id = eea.attribute_set_id AND ea.attribute_id = eea.attribute_id',
                            []
                    )
                ->limit(1)
        );

        if (empty($attribute)){
        
            return false;
        
        }

        return $attribute;

    }

    /**
     * Function to get attribute
     * @param string $code                          attribute code to search
     * @param int $entityTypeId                     entity type id of item
     * @return boolean|array                        attribute found
     */
    private function getAttribute($code, $entityTypeId){
        
        $attribute = $this->connection->fetchRow(
            $this->connection->select()
                ->from(
                    $this->getTable('eav_attribute'),
                    [
                        \Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_ID,
                        \Magento\Eav\Api\Data\AttributeInterface::BACKEND_TYPE,
                    ]
                )
                ->where(\Magento\Eav\Api\Data\AttributeInterface::ENTITY_TYPE_ID . ' = ?', $entityTypeId)
                ->where(\Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_CODE . ' = ?', $code)
                ->limit(1)
        );

        if (empty($attribute)) {

            return false;

        }

        return $attribute;
    }

    /**
     * Function to get attribute by its id
     * @param int $attributeId                      attribute id to search
     * @param int $entityTypeId                     entity type id of item
     * @return boolean|array                        attribute found
     */
    private function getAttributeById($attributeId, $entityTypeId)
    {
        
        $attribute = $this->connection->fetchRow(
            $this->connection->select()
                ->from(
                    $this->getTable('eav_attribute'),
                    [
                        \Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_CODE,
                        \Magento\Eav\Api\Data\AttributeInterface::BACKEND_TYPE,
                    ]
                )
                ->where(\Magento\Eav\Api\Data\AttributeInterface::ENTITY_TYPE_ID . ' = ?', $entityTypeId)
                ->where(\Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_ID . ' = ?', $attributeId)
                ->limit(1)
        );

        if (empty($attribute)) {
        
            return false;
        
        }

        return $attribute;
    }

    /**
     * Function to get if row id column exists
     * @param string $table             table to check
     * @param string $identifier        identifier to check
     * @return string $identifier       identifier of table
     */
    private function getColumnIdentifier($table, $identifier = 'entity_id'){

        // SHOW KEYS FROM table_name WHERE Key_name = 'PRIMARY'
        
        if ($this->connection->tableColumnExists($table, 'row_id')) {
            $identifier = 'row_id';
        }

        return $identifier;

    }

    /**
     * Function to get collection of attribute sets
     * @return array $attribute_set_collection       attribute set collection
     */
    private function getAttributeSetCollection(){

        $attribute_set_collection = [];

        $mg_attribute_set_collection = $this->attribute_set->getCollection()->setEntityTypeFilter($this->productModel->getResource()->getTypeId()); 
        
        foreach ($mg_attribute_set_collection as $mg_attribute_model) {

            $attribute_set_collection[$mg_attribute_model->getId()] = $mg_attribute_model->getAttributeSetName();

        }

        return $attribute_set_collection;
    }

    /**
     * Function to get Default attribute set id, if not found, will return first. 
     * @param  array $attrib_collection         attribute set collection
     * @return int                              attribute set id
     */
    private function getAttributeSetId($attrib_collection){
        
        $default_attribute_set_id = '';

        if (count($attrib_collection) > 0){
            
            $attrib_collection = array_flip($attrib_collection);
            
            if (isset($attrib_collection['Default'])){

                $default_attribute_set_id = $attrib_collection['Default'];

            }else{

                $default_attribute_set_id = reset($attrib_collection);

            }
            
        }

        return $default_attribute_set_id;

    }

    /**
     * Function to get organized image sizes of field
     * @param  array $fields            Connector fields
     * @param  string $field_name       field name to check image sizes
     * @param  string $item_index       item index
     * @return array $images_sizes      ordered images sizes
     */
    private function getImgSizes($fields, $field_name, $index){

        $images_sizes = [];

        if (!empty($fields[$field_name]['image_sizes'])) {

            $field_images_sizes = $fields[$field_name]['image_sizes'];
            $ordered_image_sizes = $this->order_array_img($field_images_sizes);

            foreach ($ordered_image_sizes as $img_size => $img_dimensions) {
                $images_sizes[] = $img_size;
            }

        } else if (!empty($fields['image_sizes'])) {

            $field_images_sizes = $fields['image_sizes'];
            $ordered_image_sizes = $this->order_array_img($field_images_sizes);

            foreach ($ordered_image_sizes as $img_size => $img_dimensions) {

                $images_sizes[] = $img_size;
            }

        } else {

            $images_sizes[] = 'IMD';
            $images_sizes[] = 'THM';
            $images_sizes[] = 'TH';
            
        }

        if ($this->sl_DEBBUG > 1) $this->debbug($index.' image sizes: '.implode(', ', (array)$images_sizes));
        return $images_sizes;

    }

    /**
     * Function to validate if product has name, sku and categories.
     * @param  array $product               product data
     * @return boolean                      result of validation
     */
    private function validateProduct($product){

        if (!isset($product['data'][$this->product_field_name]) || (isset($product['data'][$this->product_field_name]) && $product['data'][$this->product_field_name] == '')){

            $this->debbug('## Error. Product with SL ID: '.$product[$this->product_field_id].' has no name.');

            return false;

        }

        if (!isset($product['data'][$this->product_field_sku]) || (isset($product['data'][$this->product_field_sku]) && $product['data'][$this->product_field_sku] == '')){

            $this->debbug('## Error. Product with SL ID: '.$product[$this->product_field_id].' has no SKU.');
            
            return false;

        }

        if (empty($product[$this->product_field_catalogue_id])){

            $this->debbug('## Error. Product '.$product['data'][$this->product_field_name].' with SL ID: '.$product[$this->product_field_id].' has no categories.');
            
            return false;

        }

        return true;

    }

    /**
     * Function to get attribute set id by string or numeric.
     * @param  int $sl_attribute_set_id_value       Sales Layer attribute set id value
     * @param  array $product_data_to_store         array with attribute set collection
     * @param  int $default_attribute_set_id        value of default attribute set id, in case Sales Layer value is not found
     * @return int $sl_attribute_set_id_value       attribute set id value to update
     */
    private function correctAttributeId($sl_attribute_set_id_value, $product_data_to_store, $default_attribute_set_id ){

        if (is_array($sl_attribute_set_id_value)){

            if (empty($sl_attribute_set_id_value)){

                return $default_attribute_set_id;

            }else{

                $sl_attribute_set_id_value = reset($sl_attribute_set_id_value);

            }
            
        }

        if (is_numeric($sl_attribute_set_id_value)){
            
            if (!isset($product_data_to_store['attribute_set_collection'][$sl_attribute_set_id_value])){
                
                $sl_attribute_set_id_value = $default_attribute_set_id;
            
            }
            
        }else{
            
            $sl_attribute_set_id_value = array_search(strtolower($sl_attribute_set_id_value), array_map('strtolower', $product_data_to_store['attribute_set_collection']));
            
            if (!is_numeric($sl_attribute_set_id_value)){
                
                $sl_attribute_set_id_value = $default_attribute_set_id;
            
            }
            
        }

        return $sl_attribute_set_id_value;

    }

    /**
     * Function to check and unset attribute that are not in attribute set
     * @param  array $arrayProducts             products to update
     * @param  array $product_data_to_store     data to store
     * @param  int $default_attribute_set_id    default attribute set id
     * @return array                            array of products to update or false if no one is valid
     */
    private function checkAttributes($arrayProducts, $product_data_to_store, $default_attribute_set_id){
        
        $time_ini = microtime(1);
        $attributes_checked = $attributes_in_set = []; 

        foreach ($arrayProducts as $keyProd => $product) {

            if(!$this->validateProduct($product)){
                $this->products_not_synced[$product[$this->product_field_id]] = 0;
                unset($arrayProducts[$keyProd]);
                continue;
            }

            
            if (isset($product['data'][$this->product_field_attribute_set_id])){

                $sl_attribute_set_id_value = $this->correctAttributeId($product['data'][$this->product_field_attribute_set_id], $product_data_to_store, $default_attribute_set_id);
                
                $arrayProducts[$keyProd]['data'][$this->product_field_attribute_set_id] = $sl_attribute_set_id_value;
            
                if (!empty($product_data_to_store['product_additional_fields'])){
                    
                    foreach ($product_data_to_store['product_additional_fields'] as $field_name => $field_name_value){

                        if (!isset($attributes_checked[$sl_attribute_set_id_value][$field_name])){

                            $result_check = $this->checkAttributeInSetId($field_name, $this->product_entity_type_id, $sl_attribute_set_id_value);
                            $attributes_checked[$sl_attribute_set_id_value][$field_name] = 0;
                            
                            if ($result_check){

                                $attributes_in_set[$sl_attribute_set_id_value][$field_name] = 0;
                                
                            }

                        }

                        if (!isset($attributes_in_set[$sl_attribute_set_id_value][$field_name])){
                            
                            unset($arrayProducts[$keyProd]['data'][$field_name_value]);

                        }

                    }

                }

            }

        }

        if ($this->sl_DEBBUG > 2) $this->debbug('### time_check_attribute_set_id: ', 'timer', (microtime(1) - $time_ini));
        
        if (!empty($arrayProducts)){

            return $arrayProducts;

        }

        return false;
    }

    /**
     * Function to get grouping reference fields
     * @param  array $schema            Sales Layer data schema
     * @return array $grouped           grouping reference fields
     */
    private function getGroupingRefs($schema){

        $grouped = [];
        $grouping_product_reference_fields = preg_grep('/grouping_product_reference_\+?\d+$/', array_keys($schema['fields']));

        if (!empty($grouping_product_reference_fields)){

            foreach ($grouping_product_reference_fields as $grouping_product_reference_field){
                
                $grouped[] = $grouping_product_reference_field;
                
            }

        }

        return $grouped;

    }

    /**
     * Function to get grouping quantity fields
     * @param  array $schema            Sales Layer data schema
     * @return array $grouped           grouping quantity fields
     */
    private function getGroupingQty($schema){

        $grouped = [];
        $grouping_product_quantity_fields = preg_grep('/grouping_product_quantity_\+?\d+$/', array_keys($schema['fields']));

        if (!empty($grouping_product_quantity_fields)){

            foreach ($grouping_product_quantity_fields as $grouping_product_quantity_field){

                $grouped[] = $grouping_product_quantity_field;
                    
            }

        }

        return $grouped;

    }

    /**
     * Function to get field with multilingual extension.
     * @param string $field_name                field name to check
     * @param array $fields                     fields schema
     * @return string $this->$field_name        field name class param
     */
    private function setDataStoreFields($field_name, $fields){

        if (isset($fields[$this->$field_name]) && $fields[$this->$field_name]['has_multilingual']){

            $this->$field_name .= '_'.$this->sl_language;

        }

        return $this->$field_name;

    }

    /**
     * Function to get additional fields from Sales Layer schema.
     * @param array $fields                             fields schema
     * @param array $fixed_product_fields               Magento internal fields
     * @return array $product_additional_fields         additional fields to synchronize
     */
    private function setAdditionalFields($fields, $fixed_product_fields){

        $product_additional_fields = [];

        foreach ($fields as $field_name => $field_props){

            if (!in_array($field_name, $fixed_product_fields)){

                if ($field_props['has_multilingual']){

                    $product_additional_fields[$field_name] = $field_name.'_'.$this->sl_language;

                } else {

                    $product_additional_fields[$field_name] = $field_name;

                }

            }

        }

        return $product_additional_fields;

    }

    /**
     * Function to get product format configurable valid attributes
     * @return array $format_configurable_attributes               attributes that exist in Magento
     */
    private function getConfigurableAttrs(){

        $format_configurable_attributes_codes = [];

        foreach ($this->format_configurable_attributes as $format_configurable_attribute_id) {

            $configurable_attribute = $this->getAttributeById($format_configurable_attribute_id, $this->product_entity_type_id);

            if (empty($configurable_attribute)){
                continue;
            }

            if (!isset($configurable_attribute[\Magento\Eav\Api\Data\AttributeInterface::BACKEND_TYPE])) {
                continue;
            }

            $format_configurable_attributes_codes[$format_configurable_attribute_id] = strtolower($configurable_attribute[\Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_CODE]);

        }

        return $format_configurable_attributes_codes;

    }

    /**
     * Function to prepare product format configurable attributes
     * @param  array $arrayFormats          product formats to check
     * @param  array $schema                Sales Layer schema
     * @return array $arrayFormats          product formats checked
     */
    private function prepareConfigurableAttrs($arrayFormats, $schema){

        $parent_product_attributes = [];
        $format_configurable_attributes_codes = $this->getConfigurableAttrs();

        //cargamos los parent_product_attributes cuyos formatos tengan valores válidos
        $parent_product_attributes = $this->getParentAttrs($arrayFormats, $schema, $format_configurable_attributes_codes);

        foreach ($arrayFormats as $keyForm => $format) {
             
            $sl_parent_product_id   = $format[$this->format_field_products_id];
            $format_data            = $format['data'];

            $attribute_data_empty = [];

            if($this->isFormatOK($parent_product_attributes, $sl_parent_product_id, $attribute_data_empty)){            

                if (isset($parent_product_attributes[$sl_parent_product_id]) && !empty($parent_product_attributes[$sl_parent_product_id])){

                    foreach ($parent_product_attributes[$sl_parent_product_id] as $format_configurable_data) {
                        
                        if (!isset($arrayFormats[$keyForm]['parent_product_attributes_ids'])){
                            $arrayFormats[$keyForm]['parent_product_attributes_ids'] = [];
                        }

                        $arrayFormats[$keyForm]['parent_product_attributes_ids'][] = $format_configurable_data['mg_attribute_id'];
                    }

                }

            }else{

                $error_message = $format_data[$this->format_field_sku]." - The format attribute data is empty/wrong.";
                
                if (!empty($attribute_data_empty)){

                    $this->debbug('## Error. '.print_r($error_message,1));

                    foreach ($attribute_data_empty as $error_msg) {

                        $this->debbug('## Error. '.$format_data[$this->format_field_sku]." - ".print_r($error_msg,1));

                    }

                }
            
            }

            
        }

        return $arrayFormats;

    }

    /**
     * Function to get produt format parent attributes
     * @param  array $arrayFormats                                  product formats to check
     * @param  array $schema                                        Sales Layer schema
     * @param  array $format_configurable_attributes_codes          product format configurable attribute codes
     * @return array $parent_product_attributes                     parent product attributes found
     */
    /* private function getParentAttrs($arrayFormats, $schema, $format_configurable_attributes_codes){
        
        $parent_product_attributes = [];

        foreach ($arrayFormats as $format) {
                
            $sl_parent_product_id   = $format[$this->format_field_products_id];
            $format_data            = $format['data'];
            
            foreach ($format_configurable_attributes_codes as $format_configurable_attribute_id => $format_configurable_attribute_code){

                $format_configurable_attribute_code_lan = $format_configurable_attribute_code;

                if (isset($schema['fields'][$format_configurable_attribute_code]) && $schema['fields'][$format_configurable_attribute_code]['has_multilingual']) {

                    $format_configurable_attribute_code_lan .= '_'.$this->sl_language;

                }
                
                if (isset($format_data[$format_configurable_attribute_code_lan])){

                    $sl_format_value = $this->getCodeValue($format_data, $format_configurable_attribute_code,  $format_configurable_attribute_code_lan);

                    if ((!is_array($sl_format_value) && $sl_format_value !== '') || (is_array($sl_format_value) && !empty($sl_format_value))){
                    
                        if (!isset($parent_product_attributes[$sl_parent_product_id])){ $parent_product_attributes[$sl_parent_product_id] = []; }
                        if (!isset($parent_product_attributes[$sl_parent_product_id][$format_configurable_attribute_code])){

                            $parent_product_attributes[$sl_parent_product_id][$format_configurable_attribute_code] = array('format_configurable_attribute_code_lan' => $format_configurable_attribute_code_lan, 'mg_attribute_id' => $format_configurable_attribute_id);

                        }

                    }

                }

            }

        }

        return $parent_product_attributes;

    } */

    /**
     * Function to get produt format parent attributes
     * @param  array $arrayFormats                                  product formats to check
     * @param  array $schema                                        Sales Layer schema
     * @param  array $format_configurable_attributes_codes          product format configurable attribute codes
     * @return array $parent_product_attributes                     parent product attributes found
     */
    private function getParentAttrs($arrayFormats, $schema, $format_configurable_attributes_codes){
        
        $parent_product_attributes = [];

        foreach ($arrayFormats as $format) {
                
            $sl_parent_product_id   = $format[$this->format_field_products_id];
            $format_data            = $format['data'];
            
            foreach ($format_configurable_attributes_codes as $format_configurable_attribute_id => $format_configurable_attribute_code){
                
                if (isset($format_data[$format_configurable_attribute_code])){

                    $sl_format_value = $this->getCodeValue($format_data, $format_configurable_attribute_code,  $format_configurable_attribute_code);

                    if (
                        (! is_array($sl_format_value) && $sl_format_value !== '') ||
                        (is_array($sl_format_value) && !empty($sl_format_value))
                    ) {
                    
                        if (!isset($parent_product_attributes[$sl_parent_product_id])) {
                            $parent_product_attributes[$sl_parent_product_id] = [];
                        }

                        if (!isset($parent_product_attributes[$sl_parent_product_id][$format_configurable_attribute_code])){
                            
                            $parent_product_attributes[$sl_parent_product_id][$format_configurable_attribute_code] = [
                                'format_configurable_attribute_code_lan' => $format_configurable_attribute_code,
                                'mg_attribute_id' => $format_configurable_attribute_id
                            ];
                            
                        }

                    }

                }

            }

        }

        return $parent_product_attributes;

    }

    /**
     * Function to get product format field value
     * @param  array $format_data                                   product format data
     * @param  string $format_configurable_attribute_code           format configurable attribute code
     * @param  string $format_configurable_attribute_code_lan       format configurable attribute code with language extension
     * @return string|int $sl_format_value                          format field value
     */
    private function getCodeValue($format_data, $format_configurable_attribute_code, $format_configurable_attribute_code_lan){
        
        $sl_format_value = $format_data[$format_configurable_attribute_code_lan];

        if (is_array($sl_format_value) && !empty($sl_format_value)){
                                
            $sl_format_value = reset($sl_format_value);
            
            if (is_array($sl_format_value) && !empty($sl_format_value)){
                    
                $sl_format_value = reset($sl_format_value);
                    
            }
            
        }

        if (isset($this->media_field_names[$format_configurable_attribute_code])){

            $sl_format_value = urldecode($sl_format_value);

        }

        return $sl_format_value;

    }

    /**
     * Function to validate if product format has valid configurable attributes
     * @param  array $parent_product_attributes         parent product attributes
     * @param  int $sl_parent_product_id                product format parent id
     * @param  array &$attribute_data_empty             errors due to invalid or empty values
     * @return boolean                                  result of product format validation
     */
    private function isFormatOK($parent_product_attributes, $sl_parent_product_id, &$attribute_data_empty){
        
        $format_ok = true;

        if (isset($parent_product_attributes[$sl_parent_product_id]) && !empty($parent_product_attributes[$sl_parent_product_id])){
            
            foreach ($parent_product_attributes[$sl_parent_product_id] as $format_configurable_attribute_code => $format_configurable_data) {
                
                $format_configurable_attribute_code_lan = $format_configurable_data['format_configurable_attribute_code_lan'];

                if (isset($format_data[$format_configurable_attribute_code_lan])){
                
                    $sl_format_value = $this->getCodeValue($format_data, $format_configurable_attribute_code, $format_configurable_attribute_code_lan);

                    if ((!is_array($sl_format_value) && $sl_format_value === '') || (is_array($sl_format_value) && empty($sl_format_value))){

                        $attribute_data_empty[] = 'The format attribute '.$format_configurable_attribute_code.' is empty.'; 
                        
                        $format_ok = false;

                    }

                }

            }

        }else{

            $format_ok = false;

        }

        return $format_ok;

    }

    /**
     * Function to check if there are items processing
     * @return boolean|int $items_processing        items processing quantity or false if there are no pending item tp process.
     */
    private function isProcessing(){
        
        $sql_processing = $this->connection->query(" SELECT count(*) as count FROM ".$this->saleslayer_syncdata_table);

        $items_processing = $sql_processing->fetch();

        if (isset($items_processing['count']) && $items_processing['count'] > 0){

            $this->debbug("There are still ".$items_processing['count']." items processing, wait until is finished and synchronize again.");
            
            return $items_processing;

        }
        return false;

    }

    /**
     * Function to update last synchronization datetime to a connector
     * @param  datetime $last_sync          last synchronization date to synchronize
     * @param  int $connector_id            connector row id
     * @return void
     */
    private function updateLastSync($last_sync, $connector_id){
        
        if ($last_sync == null){ $last_sync = date('Y-m-d H:i:s'); }

        $config_record = $this->load($connector_id, 'connector_id');
        $config_record->setLastSync($last_sync);
        $config_record->save();

    }

    /**
     * Function to set into class parameter lenguage used by connector.
     * @param  object $slconn       Sales Layer connector object.
     * @return void
     */
    private function getResponseLanguages($slconn){

        if ($slconn->get_response_languages_used()){

            $get_response_default_language = $slconn->get_response_default_language();
            $get_response_languages_used   = $slconn->get_response_languages_used();
            $get_response_language         = (is_array($get_response_languages_used) ? reset($get_response_languages_used) : $get_response_default_language);
        
            $this->sl_language = $get_response_language;
            
        }

    }

    /**
     * Function to process response of data storage
     * @param  array $get_response_table_data           Sales Layer connector call data return
     * @param  int $connector_id                        connector row id
     * @param  array $sync_params                       Synchronization parameters to store
     * @return array $arrayReturn                       response of storage
     */
    private function processResponse($get_response_table_data, $connector_id, $sync_params){
        
        $arrayReturn = [];

        $this->loadStoreViewIds($connector_id);
        $sync_params['conn_params']['store_view_ids'] = $this->store_view_ids;
        $sync_params['conn_params']['website_ids'] = $this->website_ids;
        $this->default_category_id = $this->get_conn_field($connector_id, 'default_cat_id');
        $this->load_products_previous_categories($connector_id);
        $this->avoid_stock_update = $this->get_conn_field($connector_id, 'avoid_stock_update');
        $this->load_format_configurable_attributes($connector_id);
        $this->load_media_field_names();
        $this->category_is_anchor = $this->get_conn_field($connector_id, 'category_is_anchor');
        $this->category_page_layout = $this->get_conn_field($connector_id, 'category_page_layout');

        if (!$this->sl_language) { $this->sl_language = $this->get_conn_field($connector_id, 'languages'); }
        
        $this->checkFrmAsPrd($get_response_table_data);

        foreach ($get_response_table_data as $nombre_tabla => $data_tabla) {

            if (count($data_tabla['deleted'] ?? []) > 0) {
                $arrayReturn = $this->processDeleted($data_tabla['deleted'], $sync_params, $arrayReturn, $nombre_tabla);
                unset($get_response_table_data[$nombre_tabla]['deleted']);
            }

            $arrayReturn = $this->processModified($data_tabla['modified'], $sync_params, $arrayReturn, $nombre_tabla);
           
            if (!empty($this->storage_process_errors)){

                $arrayReturn = $this->storage_process_errors;
                $arrayReturn['storage_error'] = true;
                $this->deleteSLRegs();
                break;

            }

            if (isset($get_response_table_data[$nombre_tabla]['modified'])){
                unset($get_response_table_data[$nombre_tabla]['modified']);
            }

        }

        return $arrayReturn;

    }

    /**
     * Function to check if variants as products option is set, and if so, convert variants to products.
     * @param  array $get_response_table_data   Sales Layer connector call data return
     * @return void
     */
    private function checkFrmAsPrd(array &$get_response_table_data): void
    {
        $data_schema = json_decode($this->sl_data_schema, 1);
        
        if (!isset($data_schema['products'])){
            $this->debbug('## Error. The schema does not has the products structure.');
            return;
        }

        if (!isset($data_schema['product_formats'])){
            $this->debbug('## Warning. The schema does not has the variants structure.');
            return;
        }

        if (isset($data_schema['product_formats']['fields']['frm_prd_fields'])){

            if ($this->sl_DEBBUG > 1) $this->debbug('Option to process variants as products active. Reorganizing data.');

            //Field relation between products and variants
            $field_relations = array(
            'sku'                               => 'format_sku',
            'product_name'                      => 'format_name',
            'product_description'               => 'format_description',
            'product_description_short'         => 'format_description_short',
            'product_image'                     => 'format_image',
            'product_price'                     => 'format_price',
            'product_special_price'             => 'format_special_price',
            'product_special_from_date'         => 'format_special_from_date',
            'product_special_to_date'           => 'format_special_to_date',
            'qty'                               => 'format_quantity',
            'product_inventory_backorders'      => 'format_inventory_backorders',
            'product_inventory_min_sale_qty'    => 'format_inventory_min_sale_qty',
            'product_inventory_max_sale_qty'    => 'format_inventory_max_sale_qty',
            'attribute_set_id'                  => 'format_attribute_set_id',
            'product_meta_title'                => 'format_meta_title',
            'product_meta_keywords'             => 'format_meta_keywords',
            'product_meta_description'          => 'format_meta_description',
            'product_length'                    => 'format_length',
            'product_width'                     => 'format_width',
            'product_height'                    => 'format_height',
            'product_weight'                    => 'format_weight',
            'product_status'                    => 'format_status',
            'product_visibility'                => 'format_visibility',
            'product_tax_class_id'              => 'format_tax_class_id',
            'product_country_of_manufacture'    => 'format_country_of_manufacture',
            'grouping_product_reference_1'      => 'grouping_format_reference_1',
            'grouping_product_quantity_1'       => 'grouping_format_quantity_1',
            'related_products_references'       => 'related_formats_references',
            'upsell_products_references'        => 'upsell_formats_references',
            'crosssell_products_references'     => 'crosssell_formats_references');

            //Checking if there are additional grouping format fields
            $grouping_indexes = array('reference', 'quantity');

            foreach ($grouping_indexes as $grouping_index) {
                
                $format_fields = array('format', 'variant');

                foreach ($format_fields as $format_field) {

                    $array_grouping_format = preg_grep('/grouping_'.$format_field.'_'.$grouping_index.'_\+?\d+$/', array_keys($data_schema['product_formats']['fields']));
                    
                    if (!empty($array_grouping_format)){

                        foreach ($array_grouping_format as $grouping_format) {
                            
                            $field_relations[str_replace('grouping_'.$format_field.'_'.$grouping_index.'_', 'grouping_product_'.$grouping_index.'_', $grouping_format)] = $grouping_format;
                            
                        }

                    }

                }


            }

            //We check extra fields
            $fixed_fields = array_merge(array($this->format_field_id, $this->format_field_products_id), $field_relations);
            
            foreach ($data_schema['product_formats']['fields'] as $field_name => $field_data) {

                if (!in_array($field_name, $fixed_fields) && $field_name !== 'frm_prd_fields'){

                    $field_relations[$field_name] = $field_name;

                }

            }

            $product_field_ID_key = $product_field_ID_catalogue_key = ''; 
            $product_field_ID_cont = $product_field_ID_catalogue_cont = [];
            foreach ($data_schema['products']['fields'] as $key => $key_data){
                if ($product_field_ID_key !== '' && $product_field_ID_catalogue_key !== '') break;
                if ($product_field_ID_key == '' and $this->product_field_id == strtolower($key)){
                    $product_field_ID_key = $key;
                    $product_field_ID_cont = $key_data;
                    continue;
                }
                if ($product_field_ID_catalogue_key == '' and 
                        ($this->product_field_catalogue_id == strtolower($key) or 
                        'id_catalogue' == strtolower($key)
                        )
                    ){
                    $product_field_ID_catalogue_key = $key;
                    $product_field_ID_catalogue_cont = $key_data;
                    continue;
                }
            }
            
            $data_schema['products']['fields'] = array($product_field_ID_key => $product_field_ID_cont, $product_field_ID_catalogue_key => $product_field_ID_catalogue_cont);

            foreach ($field_relations as $product_field => $format_field){
                
                if (isset($data_schema['product_formats']['fields'][$format_field])){

                    if ($data_schema['product_formats']['fields'][$format_field]['has_multilingual'] == 1){

                        $data_schema['product_formats']['fields'][$format_field]['multilingual_name'] = $product_field.'_'.$this->sl_language;

                    }

                    $data_schema['products']['fields'][$product_field] = $data_schema['product_formats']['fields'][$format_field];

                }


            }

            $data_schema['product_formats']['fields'] = [];
            
            //We load the category tree
            $category_tree = [];

            if (isset($get_response_table_data['catalogue']['modified']) && !empty($get_response_table_data['catalogue']['modified'])){

                foreach ($get_response_table_data['catalogue']['modified'] as $category) {
                    
                    $category_tree[$category[$this->category_field_id]] = $category[$this->category_field_catalogue_parent_id];

                }

            }

            //We extract product ids and their category ids
            $product_categories = $categories_used_by_products = [];

            foreach ($get_response_table_data['products']['modified'] as $product) {
                
                if (isset($product[$this->product_field_catalogue_id]) && !empty($product[$this->product_field_catalogue_id])){

                    $product_categories[$product[$this->product_field_id]] = $product[$this->product_field_catalogue_id];

                    foreach ($product[$this->product_field_catalogue_id] as $category_id) {

                        $category_id_to_check = $category_id;

                        do {
                            
                            if (!isset($categories_used_by_products[$category_id_to_check])  && $category_id_to_check != 0){
                                
                                $categories_used_by_products[$category_id_to_check] = 0;

                            }

                            if (isset($category_tree[$category_id_to_check])){
                                
                                $category_id_to_check = $category_tree[$category_id_to_check];
                            
                            }else{

                                break;

                            }

                        } while ($category_id_to_check != 0);

                    }

                }

            }

            $get_response_table_data['products']['modified'] = [];
            $get_response_table_data['products']['deleted'] = $get_response_table_data['product_formats']['deleted'];

            $format_categories = $categories_used_by_formats = [];

            foreach ($get_response_table_data['product_formats']['modified'] as $format) {
                
                if (!isset($product_categories[$format[$this->format_field_products_id]])){

                    if (isset($format['data']['format_sku'])){

                        $format_index = 'SKU '.$format['data']['format_sku'];

                    }else{

                        $format_index = 'SL ID '.$format[$this->format_field_id];

                    }

                    $this->debbug("## Error. The product format with ".$format_index." doesn't has a parent product to get information from. Cannot convert.");
                    
                }else{

                    $new_format = array($this->product_field_id => $format[$this->format_field_id], $this->product_field_catalogue_id => $product_categories[$format[$this->format_field_products_id]], 'data' => []);

                    foreach ($field_relations as $new_format_field => $old_format_field) {
                        
                        if (isset($data_schema['products']['fields'][$new_format_field])){

                            if ($data_schema['products']['fields'][$new_format_field]['has_multilingual'] == 1){

                                $old_format_field = $old_format_field.'_'.$this->sl_language;
                                $new_format_field = $data_schema['products']['fields'][$new_format_field]['multilingual_name'];

                            }

                            if (isset($format['data'][$old_format_field])){

                                $new_format['data'][$new_format_field] = $format['data'][$old_format_field];

                            }

                        }

                    }

                    $get_response_table_data['products']['modified'][] = $new_format;

                    $format_categories[$new_format[$this->format_field_id]] = $new_format[$this->product_field_catalogue_id];

                    foreach ($new_format[$this->product_field_catalogue_id] as $category_id) {
                        
                        $category_id_to_check = $category_id;

                        do {
                            
                            if (!isset($categories_used_by_formats[$category_id_to_check]) && $category_id_to_check != 0){
                                
                                $categories_used_by_formats[$category_id_to_check] = 0;

                            }

                            if (isset($category_tree[$category_id_to_check])){
                                
                                $category_id_to_check = $category_tree[$category_id_to_check];
                    
                            }else{

                                break;

                            }

                        } while ($category_id_to_check != 0);

                    }

                }

            }

            $get_response_table_data['product_formats']['modified'] = [];   
            $get_response_table_data['product_formats']['deleted'] = [];           

            //We search for empty categories (not used by products or formats)
            $empty_categories = [];

            foreach ($category_tree as $category_id => $category_parent_id) {
                
                if (!isset($categories_used_by_products[$category_id]) && !isset($categories_used_by_formats[$category_id])){

                    $category_id_to_add = $category_id;

                    do {

                        if (!isset($empty_categories[$category_id_to_add]) && $category_id_to_add != 0){
                            
                            $empty_categories[$category_id_to_add] = 0;

                        }

                        if (isset($category_tree[$category_id_to_add])){
                            
                            $category_id_to_add = $category_tree[$category_id_to_add];
                        
                        }else{

                            break;

                        }

                    } while ($category_id_to_add != 0);

                }

            }

            $categories_to_process = $empty_categories + $categories_used_by_formats;

            if (!empty($categories_to_process)){

                foreach ($get_response_table_data['catalogue']['modified'] as $keyCat => $category) {

                    if (!isset($categories_to_process[$category[$this->category_field_id]])){

                        unset($get_response_table_data['catalogue']['modified'][$keyCat]);

                    }

                }

            }

            $this->sl_data_schema = json_encode($data_schema);
        }
    }

    /**
     * Function to prepare items to delete
     * @param  array $deleted_data          items to delete
     * @param  array $sync_params           Synchronization parameters to store
     * @param  array $arrayReturn           data to fill with counters to return
     * @param  string $nombre_tabla         type of items to delete
     * @return array $arrayReturn           data with counters to return
     */
    private function processDeleted($deleted_data, $sync_params, $arrayReturn, $nombre_tabla){

            $time_ini_delete = microtime(1);

            switch ($nombre_tabla) {
                case 'catalogue':

                    $arrayReturn['categories_to_delete'] = $this->storeDeletedItems('category', $deleted_data, $sync_params);
                    break;
                case 'products':

                    $arrayReturn['products_to_delete'] = $this->storeDeletedItems('product', $deleted_data, $sync_params);
                    break;
                case 'product_formats':

                    $arrayReturn['product_formats_to_delete'] = $this->storeDeletedItems('product_format', $deleted_data, $sync_params);
                    break;
                default:

                    $this->debbug('## Error. Deleting, table '.$nombre_tabla.' not recognized.');
                    break;
            }

            if ($this->sl_DEBBUG > 1) $this->debbug('#### time_store_items_delete - '.$nombre_tabla.': ', 'timer', (microtime(1) - $time_ini_delete));

            $this->insert_syncdata_sql(true);

            return $arrayReturn;
    }

    /**
     * Function to store items to delete
     * @param  string $item_type                    type of item to delete
     * @param  array $deleted_data                  deleted items ids to store
     * @param  array $sync_params                   Synchronization parameters to store
     * @return int $delete_data_count               count of items to delete
     */
    private function storeDeletedItems($item_type, $deleted_data, $sync_params){

        $delete_data_count = count($deleted_data);
        $this->debbug('Total count of deleted '.$item_type.' to store: '.$delete_data_count);
        if ($this->sl_DEBBUG > 1) $this->debbug('Deleted '.$item_type.' data to store: '.print_r($deleted_data,1));
        
        $this->createSQLs($deleted_data, 'delete', $item_type, $sync_params);

        return $delete_data_count;
    }

    /**
     * Function to prepare items to update
     * @param  array $modified_data         items to update
     * @param  array $sync_params           Synchronization parameters to store
     * @param  array $arrayReturn           data to fill with counters to return
     * @param  string $nombre_tabla         type of items to update
     * @return array $arrayReturn           data with counters to return
     */
    private function processModified($modified_data, $sync_params, $arrayReturn, $nombre_tabla){

        $time_ini_store_items_update = microtime(1);

        switch ($nombre_tabla) {
            case 'catalogue':

                $arrayReturn['categories_to_sync'] = $this->storeModifiedCategories($modified_data, $sync_params);
                break;
            case 'products':

                $arrayReturn['products_to_sync'] = $this->storeModifiedProducts($modified_data, $sync_params);
                break;
            case 'product_formats':
                
                $arrayReturn['product_formats_to_sync'] = $this->storeModifiedFormats($modified_data, $sync_params);
                break;
            default:

                $this->debbug('## Error. Synchronizing, table '.$nombre_tabla.' not recognized.');
                break;
        }

        
        $this->insert_syncdata_sql(true);

        if ($this->sl_DEBBUG > 1) $this->debbug('#### time_store_items_update - '.$nombre_tabla.': ', 'timer', (microtime(1) - $time_ini_store_items_update));
        return $arrayReturn;

    }

    /**
     * Function to store categories to update
     * @param  array $modified_data                 categories data to store
     * @param  array $sync_params                   Synchronization parameters to store
     * @return int $categories_to_sync_count        count of categories to update
     */
    private function storeModifiedCategories($modified_data, $sync_params){
        
        $time_ini = microtime(1);
        
        $categories_to_sync_count = count($modified_data);

        if ($this->sl_DEBBUG > 1) $this->debbug('Total count of modified categories to store initial: '.$categories_to_sync_count);
        if ($this->sl_DEBBUG > 1) $this->debbug('Modified categories data to store initial: '.print_r($modified_data,1));

        if ($categories_to_sync_count > 0){

            $category_data_to_store = $this->prepare_category_data_to_store($modified_data);
            if ($category_data_to_store === false) return 0;

            unset($modified_data);

            if (isset($category_data_to_store['category_data']) && !empty($category_data_to_store['category_data'])){

                $categories_to_sync = $category_data_to_store['category_data'];
                unset($category_data_to_store['category_data']);

                $category_params = array_merge($category_data_to_store, $sync_params);

                $this->createSQLs($categories_to_sync, 'update', 'category', $category_params);
                
            }

        }

        $this->debbug('Total count of modified categories to store: '.count($categories_to_sync));
        if ($this->sl_DEBBUG > 1) $this->debbug('Modified categories data to store final: '.print_r($categories_to_sync,1));

        if ($this->sl_DEBBUG > 1) $this->debbug('### time_insert_categories: ', 'timer', (microtime(1) - $time_ini));
        return count($categories_to_sync);

    }

    /**
     * Function to store products to update
     * @param  array $modified_data                 products data to store
     * @param  array $sync_params                   Synchronization parameters to store
     * @return int $products_to_sync_count        count of products to update
     */
    private function storeModifiedProducts($modified_data, $sync_params){

        $time_ini_insert_products = microtime(1);

        $product_to_sync_count = count($modified_data);
        
        if ($this->sl_DEBBUG > 1) $this->debbug('Total count of modified products to store initial: '.$product_to_sync_count);
        if ($this->sl_DEBBUG > 1) $this->debbug('Modified products data to store initial: '.print_r($modified_data,1));

        if ($product_to_sync_count > 0){

            $time_ini_prepare_product_data_to_store = microtime(1);
            $product_data_to_store = $this->prepare_product_data_to_store($modified_data);
            if ($this->sl_DEBBUG > 1) $this->debbug('## time_prepare_product_data_to_store: ', 'timer', (microtime(1) - $time_ini_prepare_product_data_to_store));
            if ($product_data_to_store === false) return 0;

            unset($modified_data);

            if (isset($product_data_to_store['product_data']) && !empty($product_data_to_store['product_data'])){

                $product_to_sync_count = count($product_data_to_store['product_data']);

                $products_to_sync = $product_data_to_store['product_data'];
                unset($product_data_to_store['product_data']);
                $product_params = array_merge($product_data_to_store, $sync_params);

                $time_ini_insert_products_into_db = microtime(1);

                $this->createSQLs($products_to_sync, 'update', 'product', $product_params);

                if ($this->sl_DEBBUG > 1) $this->debbug('## time_insert_products: ', 'timer', (microtime(1) - $time_ini_insert_products_into_db));
                
            }else{

                $arrayReturn['products_to_sync'] = $product_to_sync_count = 0;

            }

        }

        
        $this->debbug('Total count of modified products to store: '.count($products_to_sync));
        if ($this->sl_DEBBUG > 1) $this->debbug('Modified products data to store final: '.print_r($products_to_sync,1));

        if ($this->sl_DEBBUG > 1) $this->debbug('### time_insert_products: ', 'timer', (microtime(1) - $time_ini_insert_products));
        return count($products_to_sync);

    }

    /**
     * Function to store product formats to update
     * @param  array $modified_data                 product formats data to store
     * @param  array $sync_params                   Synchronization parameters to store
     * @return int $product_formats_to_sync_count   count of product formats to update
     */
    private function storeModifiedFormats($modified_data, $sync_params){

        $time_ini_insert_formats = microtime(1);

        $product_formats_to_sync_count = count($modified_data);
        if (!empty($this->products_not_synced) && $product_formats_to_sync_count > 0){

            foreach ($modified_data as $keyForm => $format) {
        
                if (isset($this->products_not_synced[$format[$this->format_field_products_id]])){

                    $this->debbug('## Error. The Format with SL ID '.$format[$this->format_field_id].' has no product parent to synchronize.');
                    unset($modified_data[$keyForm]);
                    
                }

            }

        }

        if ($this->sl_DEBBUG > 1) $this->debbug('Total count of modified product formats to store initial: '.$product_formats_to_sync_count);
        if ($this->sl_DEBBUG > 1) $this->debbug('Modified product formats data to store initial: '.print_r($modified_data,1));
        
        if ($product_formats_to_sync_count > 0){

            $product_format_data_to_store = $this->prepare_product_format_data_to_store($modified_data);
            if ($product_format_data_to_store === false) return 0;

            unset($modified_data);

            if (isset($product_format_data_to_store['product_format_data']) && !empty($product_format_data_to_store['product_format_data'])){

                $product_formats_to_sync = $product_format_data_to_store['product_format_data'];
                unset($product_format_data_to_store['product_format_data']);

                $product_format_params = array_merge($product_format_data_to_store, $sync_params);

                $this->createSQLs($product_formats_to_sync, 'update', 'product_format', $product_format_params);
                
            }

        }

        $this->debbug('Total count of modified product formats to store: '.count($product_formats_to_sync));
        if ($this->sl_DEBBUG > 1) $this->debbug('Modified product formats data to store final: '.print_r($product_formats_to_sync,1));

        if ($this->sl_DEBBUG > 1) $this->debbug('### time_insert_formats: ', 'timer', (microtime(1) - $time_ini_insert_formats));
        return count($product_formats_to_sync);

    }

    /**
     * Function to create sql to insert into database
     * @param  array $items             items to insert
     * @param  string $sync_type        sync type
     * @param  string $item_type        item type
     * @param  array $params            items params
     * @return void
     */
    private function createSQLs($items, $sync_type, $item_type, $params){

        foreach ($items as $keyItem => $item) {
                    
            $item_encoded = json_encode($item);
            $params_encoded = json_encode($params);
            
            $this->sql_to_insert[] = ['sync_type' => $sync_type,
                                        'item_type' => $item_type, 
                                        'item_data' => $item_encoded,
                                        'sync_params' => $params_encoded];
            
            $this->insert_syncdata_sql();
            unset($items[$keyItem]); 

        }

    }

    /**
     * Function to update product Sku and attribute set id
     * @param  array $sl_data           product data
     * @return void
     */
    private function updateProductDB($sl_data){

        $product_table = $this->getTable('catalog_product_entity');
        $mg_product_core_data = $this->get_product_core_data($this->mg_product_id);
        
        $mg_product_data_to_update = [];

        if ($mg_product_core_data[$this->product_field_sku] != $sl_data[$this->product_field_sku]){
            
            $mg_product_data_to_update[$this->product_field_sku] = $sl_data[$this->product_field_sku];
                
        }

        if (isset($sl_data[$this->product_field_attribute_set_id])){

            if ($mg_product_core_data['attribute_set_id'] != $sl_data[$this->product_field_attribute_set_id]){

                $mg_product_data_to_update['attribute_set_id'] = $sl_data[$this->product_field_attribute_set_id];

            }

        }

        if (!empty($mg_product_data_to_update)){

            try{

                foreach ($this->mg_product_row_ids as $mg_product_row_id) {
                
                    $this->connection->update($product_table, $mg_product_data_to_update, $this->tables_identifiers[$product_table].' = ' . $mg_product_row_id);

                }

            }catch(\Exception $e){

                $this->debbug('## Error. Updating product core data: '.$e->getMessage());

            }

        }
    }

    /**
     * Function to update item websites
     * @param  integer $mg_item_id          item id to update
     * @param  array $item_data             item data
     * @param  string $website_field        website field
     * @return void
     */
    private function updateItemWebsite($mg_item_id, $item_data, $website_field){

        $catalog_product_website_table = $this->getTable('catalog_product_website');
        
        $sl_website_ids = array(1);

        if (isset($item_data[$website_field])){

            $sl_product_website_values = $item_data[$website_field];
        
            if (!is_array($sl_product_website_values)) $sl_product_website_values = array($sl_product_website_values);
					
            $all_sl_product_website_values = [];
					
            foreach ($sl_product_website_values as $sl_product_website_value) {

                if (strpos($sl_product_website_value, ',') !== false){
					    
                    $all_sl_product_website_values = array_merge(explode(',', $sl_product_website_value), $all_sl_product_website_values);
                    $merged_values = true;

                }else{

                    $all_sl_product_website_values[] = $sl_product_website_value;

                }
						

			}
				
            if (!empty($all_sl_product_website_values)){
                
                $all_sl_product_website_values = array_unique($all_sl_product_website_values);
                
                $this->loadWebsitesCollection();

                $sl_product_website_values_to_update = [];
    
                foreach ($all_sl_product_website_values as $sl_product_website_value){
    
                    if (is_numeric($sl_product_website_value)){
                
                        if (isset($this->websites_collection[$sl_product_website_value])){
                            
                            $sl_product_website_values_to_update[] = $sl_product_website_value;
                        
                        }
                        
                    }else{
                        
                        foreach ($this->websites_collection as $website_id => $website_data){

                            if (strtolower($sl_product_website_value) == strtolower($website_data['code']) ||
                                strtolower($sl_product_website_value) == strtolower($website_data['name'])){

                                $sl_product_website_values_to_update[] = $website_id;
                                break;

                            }

                        } 
                        
                    }
    
                }
                
                $sl_website_ids = array_unique($sl_product_website_values_to_update);
                
            }else{

                $sl_website_ids = [];
                
            }
				
        }else{

            if (!empty($this->website_ids)){ $sl_website_ids = $this->website_ids; }
            
        }

        $mg_product_website_ids = $this->connection->fetchAll(
            $this->connection->select()
                ->from(
                    $catalog_product_website_table,
                    ['website_id']
                )
                ->where('product_id = ?', $mg_item_id)
        );

        if (!empty($mg_product_website_ids)){

            foreach ($mg_product_website_ids as $mg_product_website_id) {
                
                if (in_array($mg_product_website_id['website_id'], $sl_website_ids)){

                    unset($sl_website_ids[array_search($mg_product_website_id['website_id'], $sl_website_ids)]);
                    continue;

                }else{
                    
                    $this->connection->delete(
                        $catalog_product_website_table,
                        ['product_id = ?' => $mg_item_id, 'website_id = ?' => $mg_product_website_id['website_id']]
                    );

                }

            }

        }

        if (!empty($sl_website_ids)){
                    
            foreach ($sl_website_ids as $sl_website_id) {
                
                $values_to_insert = [
                    'product_id' => $mg_item_id,
                    'website_id' => $sl_website_id
                ];

                $this->connection->insertOnDuplicate(
                    $catalog_product_website_table,
                    $values_to_insert,
                    array_keys($values_to_insert)
                );

            }

        }

    }

    /**
     * Function to update product categories
     * @return void
     */
    private function updateProductCategory(){

        $catalog_category_product_table = $this->getTable('catalog_category_product');

        $mg_existing_category_product_ids = $this->connection->fetchAll(
            $this->connection->select()
                ->from(
                    $catalog_category_product_table
                )
                ->where('product_id = ?', $this->mg_product_id)
        );

        if (!empty($mg_existing_category_product_ids)){

            foreach ($mg_existing_category_product_ids as $mg_existing_category_product_id) {
                
                if (in_array($mg_existing_category_product_id['category_id'], $this->sl_product_mg_category_ids)){

                    unset($this->sl_product_mg_category_ids[array_search($mg_existing_category_product_id['category_id'], $this->sl_product_mg_category_ids)]);
                    continue;

                }else{
                    
                    $query_delete = " DELETE FROM ".$catalog_category_product_table." WHERE product_id = ".$this->mg_product_id." AND category_id = ".$mg_existing_category_product_id['category_id'];
                    $this->sl_connection_query($query_delete);

                }

            }

        }

        if (!empty($this->sl_product_mg_category_ids)){
                    
            foreach ($this->sl_product_mg_category_ids as $mg_category_id) {
                
                $query_insert = " INSERT INTO ".$catalog_category_product_table."(`product_id`,`category_id`) values (?,?);";
                $this->sl_connection_query($query_insert,array($this->mg_product_id, $mg_category_id));

            }

        }

    }

    /**
     * Function to update product stock
     * @param  array $sl_data               product data
     * @return void
     */
    private function updateProductStock($sl_data){

        $this->debbug('updateProductStock() - sl_data: '.print_r($sl_data,1));

        if ($this->product_created === true || $this->avoid_stock_update == '0'){

            $sl_inventory_data = [];

            $inventory_fields = [
                'sl_qty' => $this->product_field_qty,
                'backorders' => $this->product_field_inventory_backorders,
                'min_sale_qty' => $this->product_field_inventory_min_sale_qty,
                'max_sale_qty' => $this->product_field_inventory_max_sale_qty
            ];

            foreach ($inventory_fields as $field_to_update => $sl_field) {
                
                if (isset($sl_data[$sl_field])){

                    if (is_array($sl_data[$sl_field])){

                        $sl_inventory_data[$field_to_update] = reset($sl_data[$sl_field]);
                    
                    }else{

                        $sl_inventory_data[$field_to_update] = $sl_data[$sl_field];
                    
                    }

                }else if ($this->product_created === true){

                    switch ($field_to_update) {

                        case 'sl_qty':
                            $sl_inventory_data[$field_to_update] = 0;
                            break;
                        
                        case 'backorders':
                            $sl_inventory_data[$field_to_update] = $this->config_backorders;
                            break;

                        case 'min_sale_qty':
                            $sl_inventory_data[$field_to_update] = $this->config_min_sale_qty;
                            break;

                        case 'max_sale_qty':
                            $sl_inventory_data[$field_to_update] = $this->config_max_sale_qty;
                            break;

                        default:

                            break;

                    }

                }

            }

            $this->debbug('updateProductStock() - sl_inventory_data: '.print_r($sl_inventory_data,1));

            if (!empty($sl_inventory_data)){

                $this->update_item_stock($this->mg_product_id, $sl_inventory_data);

            }

        }

    }

    /**
     * Function to pepare and store product grouping info
     * @param  array $sl_data               product data
     * @return void
     */
    private function groupProduct($sl_data){

        $linked_product_data = [];

        if ($this->grouping_ref_field_linked === 1){

            $processed_grouping_ids = [];

            $array_grouping_product = preg_grep('/grouping_product_reference_\+?\d+$/', array_keys($sl_data));
       
            $mg_product_core_data = $this->get_product_core_data($this->mg_product_id);
            
            if (!empty($array_grouping_product)){

                foreach ($array_grouping_product as $grouping_product) {
                    
                    $grouping_id = str_replace('grouping_product_reference_', '', $grouping_product);
                    $grouping_quantity = 0;
                    $grouping_product_ref = '';

                    if (is_array($sl_data[$grouping_product]) && !empty($sl_data[$grouping_product])){
                        
                        $grouping_product_ref = reset($sl_data[$grouping_product]);

                    }else if (!is_array($sl_data[$grouping_product]) && $sl_data[$grouping_product] != ''){

                        if (strpos($sl_data[$grouping_product], ',')){
                        
                            $grouping_field_data = explode(',', $sl_data[$grouping_product]);
                            $grouping_product_ref = $grouping_field_data[0];

                        }else{
                        
                            $grouping_product_ref = $sl_data[$grouping_product];
                        
                        }

                    }

                    if (isset($processed_grouping_ids[$grouping_id]) || $grouping_product_ref == ''){ 
                        
                        continue;

                    }else{

                        if ($grouping_product_ref == $mg_product_core_data['sku']){

                            $this->debbug('## Error. Product reference '.$grouping_product_ref.' is the same as the current product: '.$mg_product_core_data['sku']);
                            continue;

                        }

                        if (isset($sl_data['grouping_product_quantity_'.$grouping_id]) && is_numeric($sl_data['grouping_product_quantity_'.$grouping_id])){

                            $grouping_quantity = $sl_data['grouping_product_quantity_'.$grouping_id];

                        }

                        $linked_product_data[$this->mg_product_id][] = array('linked_type' => $this->product_link_type_grouped_db, 'linked_reference' => $grouping_product_ref, 'linked_qty' => $grouping_quantity);
                        $processed_grouping_ids[$grouping_id] = 0;

                    }

                }

            }

            if (empty($processed_grouping_ids)){

                if ($mg_product_core_data['type_id'] == $this->product_type_grouped){

                    $this->clean_associated_product_db();

                }

            }

        }

        $linked_fields = array($this->product_field_related_references => $this->product_link_type_related_db, $this->product_field_upsell_references => $this->product_link_type_upsell_db, $this->product_field_crosssell_references => $this->product_link_type_crosssell_db);

        foreach ($linked_fields as $field_sales => $linked_type) {

            if (isset($sl_data[$field_sales])){

                $linked_references = [];

                if (is_array($sl_data[$field_sales]) && !empty($sl_data[$field_sales])){
                    
                    $linked_references = $sl_data[$field_sales];

                }else if (!is_array($sl_data[$field_sales]) && $sl_data[$field_sales] != ''){

                    if (strpos($sl_data[$field_sales], ',')){
                    
                        $linked_references = explode(',', $sl_data[$field_sales]);

                    }else{
                    
                        $linked_references = array($sl_data[$field_sales]);
                    
                    }

                }

                foreach ($linked_references as $linked_reference) {
                    
                    $linked_product_data[$this->mg_product_id][] = array('linked_type' => $linked_type, 'linked_reference' => $linked_reference);

                }

            }

        }

        if (!empty($linked_product_data)){

            $sql_query_to_insert = " INSERT INTO ".$this->saleslayer_syncdata_table.
                                    " ( sync_type, item_type, item_data, sync_params ) VALUES ".
                                    " ('update', 'product_links', '".addslashes(json_encode($linked_product_data))."', '')";

            $this->sl_connection_query($sql_query_to_insert);

        }

    }

    /**
     * Function to update multiconn row
     * @param  int $sl_id                item id
     * @return void
     */
    private function saveProductCons($sl_id){        

        if (isset($this->sl_multiconn_table_data['product'][$sl_id]) && !empty($this->sl_multiconn_table_data['product'][$sl_id]['sl_connectors'])){

            $conn_found = array_search($this->processing_connector_id, $this->sl_multiconn_table_data['product'][$sl_id]['sl_connectors']);

            if (!is_numeric($conn_found)){

                $this->sl_multiconn_table_data['product'][$sl_id]['sl_connectors'][] = $this->processing_connector_id;

                $new_connectors_data = json_encode($this->sl_multiconn_table_data['product'][$sl_id]['sl_connectors']);

                $query_update  = " UPDATE ".$this->saleslayer_multiconn_table." SET sl_connectors = ?  WHERE id = ? ";

                $this->sl_connection_query($query_update,array($new_connectors_data,$this->sl_multiconn_table_data['product'][$sl_id]['id']));

            }

            return;

        }
    
        $connectors_data = json_encode(array($this->processing_connector_id));

        $query_insert = " INSERT INTO ".$this->saleslayer_multiconn_table."(`item_type`,`sl_id`,`sl_comp_id`,`sl_connectors`) values (?,?,?,?);";

        $this->sl_connection_query($query_insert,array('product', $sl_id, $this->comp_id, $connectors_data));

        return;
        
    }

    /**
     * Function to insert multiple rows into database
     * @param  array $tables_insert_values              values to insert
     * @return void
     */
    private function insertNewAttributes($tables_insert_values){

        if (!empty($tables_insert_values)){

            $time_ini_insert_values = microtime(1);

            foreach ($tables_insert_values as $table_name => $table_values) {

                try{

                    $this->connection->insertMultiple($table_name, $table_values);
                
                }catch(\Exception $e){

                    $this->debbug('## Error. Inserting multiple attributes values: '.$e->getMessage());

                }

            }

            if ($this->sl_DEBBUG > 2) $this->debbug('# time_insert_values: ', 'timer', (microtime(1) - $time_ini_insert_values));

        }

    }

    /**
     * Function to update attribute value in database
     * @param  array $datos                 item data in database
     * @param  array $attribute             Magento attribute data
     * @param  string $value                value to update
     * @param  string $attribute_table      attribute table to update
     * @return boolean                      result of update
     */
    private function updateAttribute($datos, $attribute, $value, $attribute_table){

        $time_ini_update_value = microtime(1);

        $datos_value = $datos['value'];
        
        $field_value_matches = $this->compareValue($attribute, $datos['value'], $value);
            
        if (!$field_value_matches){

            try{

                $this->connection->update($attribute_table, ['value' => $value], 'value_id = ' . $datos['value_id']);

            }catch(\Exception $e){

                $this->debbug('## Error. Updating attribute value: '.print_r($e->getMessage(),1));
                return false;

            }

        }

        if ($this->sl_DEBBUG > 2) $this->debbug('# time_update_value: ', 'timer', (microtime(1) - $time_ini_update_value));
        return true;

    }

    /**
     * Function to compare values
     * @param  array $attribute         Magento attribute data
     * @param  string $db_value         Magento database vale
     * @param  string $value            Sales Layer value
     * @return boolean                  result of compare
     */
    private function compareValue($attribute, $db_value, $value){


        if (isset($attribute[\Magento\Catalog\Api\Data\EavAttributeInterface::IS_WYSIWYG_ENABLED]) && $attribute[\Magento\Catalog\Api\Data\EavAttributeInterface::IS_WYSIWYG_ENABLED] == 1){ 
        
            if (trim(strip_tags($db_value)) === trim(strip_tags($value))){

                return true;

            }

        }else if (is_integer($value) && $db_value == $value){

            return true;
            
        }else if (!is_integer($value) && $db_value === $value){

            return true;

        }

        return false;

    }

    /**
     * Function to delete values from global attributes if they haven't been processed.
     * @param  boolean $store_global_attributes             determines if global attribute has been stored previously or always will delete
     * @param  int $attribute_id                            attribute id
     * @param  string $attribute_table                      attribute table to delete
     * @param  string $identifier                           identifier
     * @param  int $entityId                                entity id
     * @return boolean                                      result of check/delete
     */
    private function globalizeAttribute($store_global_attributes, $attribute_id, $attribute_table, $identifier, $entityId ){

        if ($store_global_attributes && isset($this->processed_global_attributes[$attribute_id])){

            //The attribute has already been processed.
            return false;

        }

        $time_delete = microtime(1);

        try{

            //If the attribute is global, we eliminate values from other stores.
            $query_delete = " DELETE FROM ".$attribute_table." WHERE store_id != 0 AND attribute_id = ".$attribute_id." AND ".$identifier . ' = '.$entityId;

            $this->sl_connection_query($query_delete);

        }catch(\Exception $e){

            $this->debbug('## Error. Deleting global attribute in other stores than 0: '.print_r($e->getMessage(),1));

        }

        
        if ($this->sl_DEBBUG > 2) $this->debbug('# time_delete_attribute_global: ', 'timer', (microtime(1) - $time_delete));

        return true;

    }

    /**
     * Function to manage and reindex indexers
     * @param  array $indexLists            indexers to reindex
     * @param  int $item_id                 item id to reindex
     * @return void
     */
    private function manageIndexes($indexLists, $item_id = null){

        $time_ini_index_all = microtime(1);

        foreach($indexLists as $indexList) {
            
            try{

                $time_ini_index_row = microtime(1);
                $categoryIndexer = $this->indexer->load($indexList);
           
                //table mview_state
                if (!$categoryIndexer->isScheduled()) {
                
                    if (null !== $item_id) {

                        $categoryIndexer->reindexRow($item_id);
                        
                    }else{

                        $categoryIndexer->reindexAll();

                    }
                
                }

            }catch(\Exception $e){

                $this->debbug('## Error. Updating index row '.$indexList.' : '.print_r($e->getMessage(),1));

            }

            if ($this->sl_DEBBUG > 2) $this->debbug('## time_index_row '.$indexList.': ', 'timer', (microtime(1) - $time_ini_index_row));

        }

        if ($this->sl_DEBBUG > 1) $this->debbug('### time_index_all: ', 'timer', (microtime(1) - $time_ini_index_all));

    }

    /**
     * Function to set attributes into MG entity
     * @param object $entity            entity to set attribute values
     * @param array $attributes_values  attributes values to set
     * @return void
     */
    private function setAttributes(&$entity, array $attributes_values)
    {
        foreach ($attributes_values as $attrK => $attrV) {

            if (is_array($attrV) && isset($attrV[0])) {
                $attrV = $attrV[0];
            }

            if ($attrV) {
                $attribute = $entity->getResource()->getAttribute($attrK);
                if ($attribute !== false) {
                    if ($attribute->usesSource()) {
                        $entity->setData($attrK, $this->synccatalogDataHelper->createOrGetOptionIdByValue($attribute, $attrV));
                        /* $option_id = $attribute->getSource()->getOptionId($attrV);
                        if ($option_id !== null) {
                            $entity->setData($attrK, $option_id);
                        } */
                    } else {
                        $entity->setData($attrK, $attrV);
                    }
                }
            }
            
        }
    }

    /**
     * Function to sync product data by store
     * @param  array $store_view_ids                        store view ids in which the product will be updated
     * @param  array $sl_product_data_to_sync               product data to sync
     * @param  array $sl_product_additional_data_to_sync    product additional data to sync
     * @param  string $sku                                  product sku
     * @return void
     */
    private function syncProdStoreAllData($store_view_ids, $sl_product_data_to_sync, $sl_product_additional_data_to_sync, $sku = ''){

        foreach ($store_view_ids as $store_view_id) {
            
            $time_ini_all_data = microtime(1);

            try {
                $product = $this->_productRepository->get($sku, true, $store_view_id);
            } catch (NoSuchEntityException $e) {
                $product = null;
                if ($this->sl_DEBBUG > 2) $this->debbug('## Error.' . $e->getMessage() . ' (SKU: ' . $sku, '), timer', (microtime(1) - $time_ini_all_data));
            }

            if ($product !== null) {

                if (!empty($sl_product_data_to_sync)){

                    $this->debbug(" > SL product data to sync: ".print_r($sl_product_data_to_sync,1));
                    $time_ini_sync_data = microtime(1);
                    
                    /* foreach ($this->mg_product_row_ids as $mg_product_row_id) {
                        $this->setValues($mg_product_row_id, 'catalog_product_entity', $sl_product_data_to_sync, $this->product_entity_type_id, $store_view_id, true, false, $this->mg_product_row_ids);
                    
                    } */

                    $this->setAttributes($product, $sl_product_data_to_sync);
                    
                    if ($this->sl_DEBBUG > 1) $this->debbug('## sync_product_data store_view_id: '.$store_view_id.': ', 'timer', (microtime(1) - $time_ini_sync_data));

                }

                if (!empty($sl_product_additional_data_to_sync)){

                    $this->debbug(" > SL product additional data to sync: ".print_r($sl_product_additional_data_to_sync,1));
                    $time_ini_additional_data = microtime(1);
                
                    /* foreach ($this->mg_product_row_ids as $mg_product_row_id) {
                    
                        $this->setValues($mg_product_row_id, 'catalog_product_entity', $sl_product_additional_data_to_sync, $this->product_entity_type_id, $store_view_id, true, true, $this->mg_product_row_ids);              
                    } */

                    $this->setAttributes($product, $sl_product_additional_data_to_sync);

                    /** Set Brand **/
                    /* if(isset($sl_product_additional_data_to_sync['brand']))
                    {
                        $this->setBrand($product,$sl_product_additional_data_to_sync['brand']);
                    }*/

                    

                    if ($this->sl_DEBBUG > 1) $this->debbug('## sync_product_additional_data store_view_id: '.$store_view_id.': ', 'timer', (microtime(1) - $time_ini_additional_data));

                }

                try{

                    $this->_productRepository->save($product);
                
                }catch(\Exception $e){

                    $this->debbug('## Error. Updating product attributes with SKU '.$sku.' for store_view_id '.$store_view_id.': '.$e->getMessage());
                
                }

                $this->debbug(" > In store view id: ".$store_view_id);

                if ($this->sl_DEBBUG > 2) $this->debbug('## time_sync_product_store_all_data store_view_id: '.$store_view_id.': ', 'timer', (microtime(1) - $time_ini_all_data));

            }

        }

    }

    /**
     * Set Brand to product
     * @param string   $sku
     * @param string   $brand_value   Brand Name
     */
    /* private function setBrand($product,$brand_value)
    {
	    $sky_attributeF = 'brand';
        $attrF = $product->getResource()->getAttribute($sky_attributeF);
        if (!empty($attrF)) {
            $avidF = $attrF->getSource()->getOptionId($brand_value);
            $avidF = empty($avidF) ? $brand_value : $avidF;
	        $product->setData($sky_attributeF, $avidF);
            $sku = $product->getSku();
	        $this->debbug(" > SL set BRAND: ".print_r("$sky_attributeF - $brand_value ($avidF) - SKU: $sku",1));
	    } else {
	       $this->debbug(" > SL set BRAND: Empty Attribute");
	    }
    } */


    /**
     * Function to sync format data by store
     * @param  array $store_view_ids                        store view ids in which the format will be updated
     * @param  array $sl_format_data_to_sync                format data to sync
     * @param  array $sl_format_additional_data_to_sync     format additional data to sync
     * @param  string $sku                                  format sku
     * @return void
     */
    private function syncFormatStoreAllData($store_view_ids, $sl_format_data_to_sync, $sl_format_additional_data_to_sync, $sku = ''){

        foreach ($store_view_ids as $store_view_id) {
            
            $time_ini_all_data = microtime(1);

            try {
                $format = $this->_productRepository->get($sku, true, $store_view_id);
            } catch (NoSuchEntityException $e) {
                $format = null;
                if ($this->sl_DEBBUG > 2) $this->debbug('## Error.' . $e->getMessage() . ' (SKU: ' . $sku, '), timer', (microtime(1) - $time_ini_all_data));
            }

            if ($format !== null) {

                if (!empty($sl_format_data_to_sync)){

                    $this->debbug(" > SL format data to sync: ".print_r($sl_format_data_to_sync,1));
                    $time_ini_sync_data = microtime(1);

                    /* foreach ($this->mg_format_row_ids as $mg_format_row_id) {
                        $this->setValues($mg_format_row_id, 'catalog_product_entity', $sl_format_data_to_sync, $this->product_entity_type_id, $store_view_id, true, false, $this->mg_format_row_ids);
                    } */

                    $this->setAttributes($format, $sl_format_data_to_sync);
                    
                    if ($this->sl_DEBBUG > 1) $this->debbug('## sync_format_data store_view_id: '.$store_view_id.': ', 'timer', (microtime(1) - $time_ini_sync_data));

                }

                if (!empty($sl_format_additional_data_to_sync)){

                    $this->debbug(" > SL format additional data to sync: ".print_r($sl_format_additional_data_to_sync,1));
                    $time_ini_additional_data = microtime(1);
                
                    /* foreach ($this->mg_format_row_ids as $mg_format_row_id) {
                        $this->setValues($mg_format_row_id, 'catalog_product_entity', $sl_format_additional_data_to_sync, $this->product_entity_type_id, $store_view_id, true, true, $this->mg_format_row_ids);  
                    } */

                    $this->setAttributes($format, $sl_format_additional_data_to_sync);

                    if ($this->sl_DEBBUG > 1) $this->debbug('## sync_format_additional_data store_view_id: '.$store_view_id.': ', 'timer', (microtime(1) - $time_ini_additional_data));

                }

                try{

                    $this->_productRepository->save($format);
                
                }catch(\Exception $e){
                    
                    $this->debbug('## Error. Updating product format attributes with SKU '.$sku.' for store_view_id '.$store_view_id.': '.$e->getMessage());
                
                }

                $this->debbug(" > In store view id: ".$store_view_id);

                if ($this->sl_DEBBUG > 2) $this->debbug('## time_sync_format_store_all_data store_view_id: '.$store_view_id.': ', 'timer', (microtime(1) - $time_ini_all_data));

            }

        }

    }

    /**
     * Function to prepare product additional fields to sync
     * @param  array $product                               product data 
     * @return array sl_product_additional_data_to_sync     product additional data to sync
     */
    private function prepareAllAdditionalFields($product){

        $time_additional_fields = microtime(1);
        $sl_product_additional_data_to_sync = [];

        if (count($this->product_additional_fields) > 0) {
            
            if (null === $this->mg_product_attribute_set_id){

                $this->debbug('## Error. Product does not have attribute set id. Cannot update product additional attribute values.');

            }else{

                foreach ($this->product_additional_fields as $field_name => $field_name_value){
                    
                    $time_additional_field = microtime(1);
                    if (!isset($product['data'][$field_name_value])){

                        if ($this->sl_DEBBUG > 2) $this->debbug('# time_prepare_additional_field: ', 'timer', (microtime(1) - $time_additional_field));
                        continue;

                    }

                    $time_attribute_additional = microtime(1);
                    $result_check = $this->checkAttributeInSetId($field_name, $this->product_entity_type_id, $this->mg_product_attribute_set_id);
                    if ($this->sl_DEBBUG > 2) $this->debbug('# time_get_attribute_additional: ', 'timer', (microtime(1) - $time_attribute_additional));

                    if (!$result_check){

                        if ($this->sl_DEBBUG > 2) $this->debbug('# time_prepare_additional_field: ', 'timer', (microtime(1) - $time_additional_field));
                        continue;

                    }

                    if ((is_array($product['data'][$field_name_value]) && empty($product['data'][$field_name_value])) || (!is_array($product['data'][$field_name_value]) && $product['data'][$field_name_value] == '')){

                        $sl_product_additional_data_to_sync[$field_name] = '';

                    }else{

                        $sl_product_additional_data_to_sync[$field_name] = $product['data'][$field_name_value];

                    }

                    if ($this->sl_DEBBUG > 2) $this->debbug('# time_prepare_additional_field: ', 'timer', (microtime(1) - $time_additional_field));

                }

            }

        }

        if ($this->sl_DEBBUG > 2) $this->debbug('# time_prepare_all_additional_fields: ', 'timer', (microtime(1) - $time_additional_fields));
        return $sl_product_additional_data_to_sync;

    }

    /**
     * Function to prepare product internal fields to process
     * @param  array $mg_product_fields             product internal fields
     * @param  array $product                       product data
     * @param  array $sl_product_data_to_sync       product internal data to fill
     * @param  array $mg_product_core_data          Magento product core data             
     * @return array $sl_product_data_to_sync       product internal data to sync
     */
    private function prepareAllFields($mg_product_fields, $product, $sl_product_data_to_sync, $mg_product_core_data){

        $time_ini_prepare_all_fields = microtime(1);

        foreach ($mg_product_fields as $sl_product_field => $mg_product_field) {
            
            $time_ini_prepare_field = microtime(1);

            if (isset($product['data'][$sl_product_field])){

                switch ($mg_product_field){
                    case 'description':
                    case 'short_description':

                        $time_check_html_text = microtime(1);
                        $sl_product_data_to_sync[$mg_product_field] = $this->sl_check_html_text($product['data'][$sl_product_field]);
                        if ($this->sl_DEBBUG > 2) $this->debbug('# time_prepare_field: ', 'timer', (microtime(1) - $time_check_html_text));

                        break;

                    case 'name':

                        $time_ini_format_url_key = microtime(1);
                        $sl_product_data_to_sync['url_key'] = $this->productModel->formatUrlKey($product['data'][$sl_product_field].'-'.$mg_product_core_data['sku']);
                        if ($this->sl_DEBBUG > 2) $this->debbug('# time_format_url_key: ', 'timer', (microtime(1) - $time_ini_format_url_key));
                        $sl_product_data_to_sync[$mg_product_field] = $product['data'][$sl_product_field];

                        break;

                    case 'status':
                        
                        $time_ini_validate_status_value = microtime(1);
                        if (!$this->SLValidateStatusValue($product['data'][$sl_product_field])){

                            $sl_product_data_to_sync[$mg_product_field] = $this->status_disabled;

                        }else{

                            $sl_product_data_to_sync[$mg_product_field] = $this->status_enabled;

                        }
                        if ($this->sl_DEBBUG > 2) $this->debbug('# time_validate_status_value: ', 'timer', (microtime(1) - $time_ini_validate_status_value));

                        break;

                    case 'visibility':

                        if (isset($product['data'][$sl_product_field])){

                            if (is_array($product['data'][$sl_product_field])){

                                $sl_product_visibility = reset($product['data'][$sl_product_field]);
                            
                            }else{

                                $sl_product_visibility = $product['data'][$sl_product_field];
                            
                            }

                            if ($return_visibility = $this->SLValidateVisibilityValue($sl_product_visibility)){

                                $sl_product_data_to_sync[$mg_product_field] = $return_visibility;

                            }

                        }

                        break;

                    case 'tax_class_id':

                        if (isset($product['data'][$sl_product_field])){

                            $sl_tax_class_id = '';

                            if (is_array($product['data'][$sl_product_field])){

                                if (!empty($product['data'][$sl_product_field])) $sl_tax_class_id = reset($product['data'][$sl_product_field]);
                            
                            }else{

                                $sl_tax_class_id = $product['data'][$sl_product_field];
                            
                            }
                            
                            if ($sl_tax_class_id !== '') $sl_product_data_to_sync[$mg_product_field] = $this->findTaxClassId($sl_tax_class_id);

                        }

                        break;

                    case 'country_of_manufacture':

                        if (isset($product['data'][$sl_product_field])){

                            if (is_array($product['data'][$sl_product_field])){

                                $sl_country_of_manufacture = reset($product['data'][$sl_product_field]);
                            
                            }else{

                                $sl_country_of_manufacture = $product['data'][$sl_product_field];
                            
                            }

                            $sl_product_data_to_sync[$mg_product_field] = $this->findCountryOfManufacture($sl_country_of_manufacture);

                        }

                        break;

                    case 'special_from_date':
                    case 'special_to_date':

                        if (is_numeric($product['data'][$sl_product_field])){

                            $sl_product_data_to_sync[$mg_product_field] = date('Y/m/d H:i:s', $product['data'][$sl_product_field]);

                        }else{

                            if ($product['data'][$sl_product_field] == '0000-00-00 00:00:00') $product['data'][$sl_product_field] = null;

                            if (null !== $product['data'][$sl_product_field] && $product['data'][$sl_product_field] !== ''){

                                if (strpos($product['data'][$sl_product_field], ':') === false) $product['data'][$sl_product_field] .= ' 00:00:00';

                                $sl_time = strtotime($product['data'][$sl_product_field]);

                                if ($sl_time != ''){
                                    
                                    $product['data'][$sl_product_field] = date('Y/m/d H:i:s',$sl_time);
                                    
                                }

                            }

                            $sl_product_data_to_sync[$mg_product_field] = $product['data'][$sl_product_field];
                            
                        }

                        break;

                    case 'price':
                    case 'special_price':
                    
                        $price_value = '';
                        
                        (is_array($product['data'][$sl_product_field])) ? $price_value = reset($product['data'][$sl_product_field]) : $price_value = $product['data'][$sl_product_field];
                        
                        if (!is_numeric($price_value)){
                            
                            if (strpos($price_value, ',') !== false){
                                
                                $price_value = str_replace(',', '.', $price_value);
                                
                            } 
                            
                            if (filter_var($price_value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)){
                                
                                $price_value = filter_var($price_value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                                
                            }
                            
                            if (!is_numeric($price_value) || $price_value === ''){
                                         
                                $price_value = null;
                            
                            }
                            
                        }else if ($price_value <= 0 && $mg_product_field == 'special_price'){
                                                    
                            $price_value = null;
                            
                        }else if ($price_value < 0 && $mg_product_field == 'price'){
                                                    
                            $price_value = null;
                            
                        }

                        if ((null !== $price_value) || (null === $price_value && $mg_product_field == 'special_price')){

                            $sl_product_data_to_sync[$mg_product_field] = $price_value;

                        }else if (null === $price_value && $mg_product_field == 'price'){

                            if (isset($product['data'][$this->product_field_sku])){

                                $product_index = 'SKU '.$product['data'][$this->product_field_sku];

                            }else{

                                $product_index = 'SL ID '.$product[$this->product_field_id];

                            }

                            $this->debbug('## Error. Product with '.$product_index.' has a price that does not have a valid format, it will not be updated. Original value: '.print_r($product['data'][$sl_product_field],1));

                        }

                        break;
                    
                    default:
                    
                        $sl_product_data_to_sync[$mg_product_field] = $product['data'][$sl_product_field];

                        break;
                }

            }

            if ($this->sl_DEBBUG > 2) $this->debbug('# time_prepare_field: ', 'timer', (microtime(1) - $time_ini_prepare_field));
        }
        if ($this->sl_DEBBUG > 1) $this->debbug('# time_prepare_all_fields: ', 'timer', (microtime(1) - $time_ini_prepare_all_fields));

        return $sl_product_data_to_sync;

    }

    /**
     * Function to load tax classes collection into a class variable.
     * @return void
     */
    private function loadTaxClassesCollection(){

        if (!$this->tax_class_collection_loaded){

            $tax_classes_collection = $this->connection->fetchAll(
                $this->connection->select()
                    ->from(
                        $this->getTable('tax_class'),
                        [
                            \Magento\Tax\Model\ClassModel::KEY_ID,
                            \Magento\Tax\Model\ClassModel::KEY_NAME
                        ]
                    )
            );

            if (!empty($tax_classes_collection)){

                foreach ($tax_classes_collection as $tax_class) {
                    
                    $this->tax_class_collection[$tax_class[\Magento\Tax\Model\ClassModel::KEY_ID]] = array('class_id' => $tax_class[\Magento\Tax\Model\ClassModel::KEY_ID],
                                                                                        'class_name' => $tax_class[\Magento\Tax\Model\ClassModel::KEY_NAME]);

                }

            }

            $this->tax_class_collection_loaded = true;
            
        }

    }

    /**
     * Function to find tax class id by Sales Layer value.
     * @param  int|string $sl_tax_class_id_value            Sales Layer tax class id or name value
     * @return int $sl_tax_class_id_found                   If found, tax class id, if not, default tax class id
     */
    private function findTaxClassId($sl_tax_class_id_value = ''){

        $sl_tax_class_id_found = '';
        
        if (null !== $sl_tax_class_id_value && $sl_tax_class_id_value != ''){

            $this->loadTaxClassesCollection();

            foreach ($this->tax_class_collection as $tax_id => $tax) {
            
                if (is_numeric($sl_tax_class_id_value)){
            
                    if ($tax_id == $sl_tax_class_id_value){
            
                        $sl_tax_class_id_found = $tax_id;
                        break;
            
                    }
            
                }else{
            
                    if (strtolower($tax['class_name']) == strtolower($sl_tax_class_id_value)){
            
                        $sl_tax_class_id_found = $tax_id;
                        break;
            
                    }
            
                }
            
            }
        
        }

        if (null === $sl_tax_class_id_found || $sl_tax_class_id_found == ''){

            $sl_tax_class_id_found = $this->config_default_product_tax_class;

        }

        return $sl_tax_class_id_found;

    }

    /**
     * Function to find country of manufacture class code by Sales Layer value.
     * @param string $sl_country_of_manufacture            Sales Layer country of manufacture code or name
     * @return int $country_of_manufacture_found           If found, country of manufacture code, if not, empty value
     */
    private function findCountryOfManufacture($sl_country_of_manufacture = ''){

        $country_of_manufacture_found = '';
        
        if (null !== $sl_country_of_manufacture && $sl_country_of_manufacture != ''){
        
            $sl_country_of_manufacture = str_replace(' ', '_', trim(strtolower($sl_country_of_manufacture)));
            
            if (empty($this->countries_of_manufacture)){

                $countries_of_manufacture = $this->countryOfManufacture->getAllOptions();
                
                if (!empty($countries_of_manufacture)){
                
                    foreach ($countries_of_manufacture as $country_of_manufacture) {
                        
                        if ($country_of_manufacture['value'] == '') continue;

                        if (!isset($this->countries_of_manufacture[$country_of_manufacture['value']])){

                            $this->countries_of_manufacture[$country_of_manufacture['value']] = $country_of_manufacture['label'];

                        }else{
                        
                            $this->debbug('## Error. Loading Country of Manufacture options, duplicated value in database: '.$country_of_manufacture['value'].'. Please, correct this, as country codes must by unique.');
                        }

                    }
                     
                }
        
            }
        
            if (!empty($this->countries_of_manufacture)){

                foreach ($this->countries_of_manufacture as $country_of_manufacture_value => $country_of_manufacture_label) {
                    
                    if (strtolower($sl_country_of_manufacture) == trim(strtolower($country_of_manufacture_value)) || $sl_country_of_manufacture == str_replace(' ', '_', trim(strtolower($country_of_manufacture_label)))){

                        $country_of_manufacture_found = $country_of_manufacture_value;
                        break;

                    }

                }

            }

        }

        return $country_of_manufacture_found;

    }

    /**
     * Function to check category image
     * @param  array $value             category image data
     * @return array                    image info
     */
    private function checkSlImages($value){

        $time_ini_check_sl_images = microtime(1);
        
        $sl_category_image_url = $sl_category_image_size = $sl_category_image_name = '';

        if (empty($value)) {
            return array(
                'sl_category_image_url' =>$sl_category_image_url,
                'sl_category_image_size'=>$sl_category_image_size,
                'sl_category_image_name'=>$sl_category_image_name,
            );
        }
            
        $sl_category_image = reset($value);
        $count_images_sizes = count($this->category_images_sizes);
        $n_image = 0;

        do{

            if (isset($this->category_images_sizes[$n_image])){

                $img_format = $this->category_images_sizes[$n_image];

                if (!empty($sl_category_image[$img_format])){
                    
                    $sl_category_image_url = $sl_category_image[$img_format];

                    $time_ini_category_size = microtime(1);
                    $sl_category_image_size = $this->sl_get_file_size($sl_category_image_url);
                    if ($this->sl_DEBBUG > 2) $this->debbug('# time_category_size: ', 'timer', (microtime(1) - $time_ini_category_size));
                    
                    if (!$sl_category_image_size){ 
                
                        $sl_category_image_url = '';

                    }else{

                        $image_info = pathinfo($sl_category_image_url);

                        if (strpos($sl_category_image_url, '%') !== false) {

                            $image_filename = rawurldecode($image_info['filename']);

                        }else{

                            $image_filename = $image_info['filename'];

                        }

                        $sl_category_image_name = preg_replace('/[^a-z0-9_\\-\\.]+/i', '_', $image_filename).'.'.$image_info['extension'];

                    }

                }

            }

            $n_image++;

        }while($sl_category_image_url == '' && ($n_image < $count_images_sizes));

        if ($this->sl_DEBBUG > 2) $this->debbug('## time_check_sl_images: ', 'timer', (microtime(1) - $time_ini_check_sl_images));

        return array(
            'sl_category_image_url' =>$sl_category_image_url,
            'sl_category_image_size'=>$sl_category_image_size,
            'sl_category_image_name'=>$sl_category_image_name,
        );

    }

    /**
     * Function to prepare category image to insert
     * @param  array $image_data            image data to sync
     * @param  array $attribute             Magento attribute data
     * @param  array $storeId               store view id to sync image
     * @param  string $identifier           table identificator
     * @param  int $entityId                entity id
     * @param  array $tables_insert_values  array to fill with values to insert
     * @param  string $attribute_table      attribute table
     * @return array $tables_insert_values  images info to insert
     */
    private function prepareImageInsert( $image_data, $attribute, $storeId, $identifier, $entityId, $tables_insert_values, $attribute_table ){
        
        $mg_category_image_path_check = $this->category_path_base.$image_data['sl_category_image_name'];
        $mg_category_image_size_check = false;

        if (file_exists($mg_category_image_path_check)){

            $time_ini_mg_category_size = microtime(1);
            $mg_category_image_size_check = $this->sl_get_file_size($mg_category_image_path_check);
            if ($this->sl_DEBBUG > 2) $this->debbug('# time_mg_category_size: ', 'timer', (microtime(1) - $time_ini_mg_category_size));
        
        }

        if ($mg_category_image_size_check && $mg_category_image_size_check == $image_data['sl_category_image_size']){

            $img_filename = $image_data['sl_category_image_name'];

        }else{
            
            $img_filename = $this->prepareImage($image_data['sl_category_image_url'], $this->category_path_base, false);

        }
                                                    
        if ($img_filename) {
        
            if (!isset($tables_insert_values[$attribute_table])){ $tables_insert_values[$attribute_table] = []; }
            $values = array('attribute_id' => $attribute[\Magento\Eav\Api\Data\AttributeInterface::ATTRIBUTE_ID],
                            'store_id' => $storeId,
                            $identifier => $entityId,
                            'value' => $img_filename);
            $tables_insert_values[$attribute_table][] = $values;

        }

        return $tables_insert_values;

    }

    /**
     * Function to update category image
     * @param  array $datos                         image database data
     * @param  array $image_data                    image data to update
     * @param  string $attribute_table              attribute table
     * @param  double $time_ini_check_mg_image      timer of image process initiation
     * @return void
     */
    private function updateCategoryImage($datos, $image_data, $attribute_table, $time_ini_check_mg_image ){

        $mg_category_image_name = $datos['value'];
               
        $mg_category_image_path = $this->category_path_base.$mg_category_image_name;

        $mg_category_image_size = false;

        if (file_exists($mg_category_image_path)){

            $time_ini_mg_category_size = microtime(1);
            $mg_category_image_size = $this->sl_get_file_size($mg_category_image_path);
            if ($this->sl_DEBBUG > 2) $this->debbug('# time_mg_category_size: ', 'timer', (microtime(1) - $time_ini_mg_category_size));
        
        }

        if ($mg_category_image_size){ 

            if ($image_data['sl_category_image_name'] == $mg_category_image_name && $image_data['sl_category_image_size'] == $mg_category_image_size){
                
                if ($this->sl_DEBBUG > 2) $this->debbug('## time_check_mg_image: ', 'timer', (microtime(1) - $time_ini_check_mg_image));
                return;

            }

        }else{

            $this->debbug('## Error. Reading local image file size, we insert and update the new image.');

        }

        $mg_category_image_path_check = $this->category_path_base.$image_data['sl_category_image_name'];
        $mg_category_image_size_check = false;

        if (file_exists($mg_category_image_path_check)){

            $time_ini_mg_category_size = microtime(1);
            $mg_category_image_size_check = $this->sl_get_file_size($mg_category_image_path_check);
            if ($this->sl_DEBBUG > 2) $this->debbug('# time_mg_category_size_check: ', 'timer', (microtime(1) - $time_ini_mg_category_size));
        
        }

        if ($mg_category_image_size_check && $mg_category_image_size_check == $image_data['sl_category_image_size']){

            $img_filename = $image_data['sl_category_image_name'];

        }else{
            
            $img_filename = $this->prepareImage($image_data['sl_category_image_url'], $this->category_path_base, false);

        }

        if ($img_filename) {
        
            try{

                $this->connection->update($attribute_table, ['value' => $img_filename], 'value_id = ' . $datos['value_id']);

            }catch(\Exception $e){

                $this->debbug('## Error. Updating category image: '.print_r($e->getMessage(),1));

            }
            if ($this->sl_DEBBUG > 2) $this->debbug('## time_process_mg_image: ', 'timer', (microtime(1) - $time_ini_check_mg_image));

        }

    }

    /**
     * Function to delete existing product links
     * @param  array $existing_links_data           existing link data to delete 
     * @param  string $product_link_table           product link table
     * @return void
     */
    private function deleteLinks($product_link_table){

        $time_ini_delete_links = microtime(1);

        if (!empty($this->existing_links_data)){

            $link_ids_to_delete = [];

            foreach ($this->existing_links_data as $existing_link_data){
                
                $link_ids_to_delete[] = $existing_link_data['link_id'];

            }

            if (!empty($link_ids_to_delete)){

                $query_delete = " DELETE FROM ".$product_link_table." WHERE link_id IN  (".implode(',', $link_ids_to_delete).")";
                $this->sl_connection_query($query_delete);

                $query_delete = " DELETE FROM ".$this->getTable('catalog_product_link_attribute_int')." WHERE link_id IN  (".implode(',', $link_ids_to_delete).")";
                $this->sl_connection_query($query_delete);
    
                $query_delete = " DELETE FROM ".$this->getTable('catalog_product_link_attribute_decimal')." WHERE link_id IN  (".implode(',', $link_ids_to_delete).")";
                $this->sl_connection_query($query_delete);
                
            }

        }

        if ($this->sl_DEBBUG > 2) $this->debbug('# time_delete_links: ', 'timer', (microtime(1) - $time_ini_delete_links));

    }

    /**
     * Function to generate a new product link
     * @param  string $product_link_table               product link table
     * @param  int $product_id                          product id
     * @param  int $link_product_id                     link product id
     * @param  string $link_type                        link type
     * @param  array $link_attributes_data              link attributes data to generate
     * @param  int $link_qty                            link quantity
     * @return void
     */
    private function generateLink($product_link_table, $product_id, $link_product_id, $link_type, $link_attributes_data, $link_qty ){
        
        $time_ini_generate_link = microtime(1);

        $table_status = $this->connection->showTableStatus($product_link_table);
            
        $link_id = $table_status['Auto_increment'];

        $link_values = [
            'link_id' => $link_id,
            'product_id' => $product_id,
            'linked_product_id' => $link_product_id,
            'link_type_id' => $link_type
        ];

        $result_create = $this->connection->insertOnDuplicate(
            $product_link_table,
            $link_values,
            array_keys($link_values)
        );

        if ($result_create){

            if (isset($link_attributes_data[$link_type]['position']) && !empty($link_attributes_data[$link_type]['position'])){

                $link_ids_filter = $this->connection->fetchOne(
                    $this->connection->select()
                        ->from(
                            [$product_link_table],
                            [new Expr('GROUP_CONCAT(link_id SEPARATOR ",")')]
                        )
                        ->where('product_id' . ' = ?', $product_id)
                        ->where('link_type_id' . ' = ?', $link_type)
                );
        
                if (null === $link_ids_filter || $link_ids_filter == ''){

                    $position = 1;

                }else{

                    $position = $this->connection->fetchOne(
                        $this->connection->select()
                            ->from(
                                [$link_attributes_data[$link_type]['position']['table']],
                                [new Expr('MAX(`value`) + 1')]
                            )
                            ->where('product_link_attribute_id' . ' = ?', $link_attributes_data[$link_type]['position']['product_link_attribute_id'])
                            ->where('link_id IN ('.$link_ids_filter.')')
                    );
                
                    if (!$position) $position = 1;

                }
            
                $position_values = [
                    'product_link_attribute_id' => $link_attributes_data[$link_type]['position']['product_link_attribute_id'],
                    'link_id' => $link_id,
                    'value' => $position
                ];
                
                $result_create = $this->connection->insertOnDuplicate(
                    $link_attributes_data[$link_type]['position']['table'],
                    $position_values,
                    array_keys($position_values)
                );
                
            }

            if ($link_type == $this->product_link_type_grouped_db){

                if (isset($link_attributes_data[$this->product_link_type_grouped_db]['qty']['product_link_attribute_id'])){
                    
                    $qty_values = [
                        'product_link_attribute_id' => $link_attributes_data[$this->product_link_type_grouped_db]['qty']['product_link_attribute_id'],
                        'link_id' => $link_id,
                        'value' => $link_qty
                    ];
                
                    $result_create = $this->connection->insertOnDuplicate(
                        $link_attributes_data[$this->product_link_type_grouped_db]['qty']['table'],
                        $qty_values,
                        array_keys($qty_values)
                    );

                }
            
            }

        }
    
        if ($this->sl_DEBBUG > 2) $this->debbug('# time_generate_link: ', 'timer', (microtime(1) - $time_ini_generate_link));

    }

    /**
     * Function to process a product link
     * @param  int $product_id                          product id
     * @param  array $linked_product_data               produc link data
     * @param  string $product_link_table               product link table
     * @param  string $product_table                    product table
     * @param  array $link_attributes_data              link attributes data
     * @return void
     */
    private function processProductLink($product_id, $linked_product_data, $product_link_table, $product_table, $link_attributes_data ){
        
        $time_ini_link_product = microtime(1);

        $mg_product_current_row_id = $this->getEntityCurrentRowId($product_id, 'product');
        
        $parent_product_core_data = $this->get_product_core_data($product_id);
        
        if (null === $parent_product_core_data){

            $this->debbug('## Error. Product parent with MG ID: '.$product_id.' does not exist. Cannot process linked items.');
            return 'item_not_updated';

        }

        $this->existing_links_data = $this->connection->fetchAll(
            $this->connection->select()
                ->from(
                    [$product_link_table]
                )
                ->where('product_id' . ' = ?', $mg_product_current_row_id)
        );
        
        $time_ini_link_all_data_update = microtime(1);

        foreach ($linked_product_data as $link_data) {
            
            $this->linkDataUpdate($link_data, $mg_product_current_row_id, $parent_product_core_data, $link_attributes_data,$product_link_table, $product_table);
        
        }
        
        if ($this->sl_DEBBUG > 2) $this->debbug('# time_link_all_data_update: ', 'timer', (microtime(1) - $time_ini_link_all_data_update));

        $this->deleteLinks($product_link_table);

        if ($this->sl_DEBBUG > 2) $this->debbug('## time_link_product: ', 'timer', (microtime(1) - $time_ini_link_product));

    }

    /**
     * Function to update existing link data
     * @param  array $link_data                             link data
     * @param  array $parent_product_core_data              parent product core data
     * @param  array $link_attributes_data                  link attributes data
     * @param  string $product_link_table                   product link table
     * @param  string $product_table                        product table
     * @return void
     */
    private function linkDataUpdate($link_data, $product_id, $parent_product_core_data, $link_attributes_data, $product_link_table, $product_table ){

        $time_ini_link_data_update = microtime(1);

        $link_type = $link_data['linked_type'];
        $link_reference = $link_data['linked_reference'];
        
        $link_product_id = $this->get_product_id_by_sku_db($link_reference);
        
        if (null === $link_product_id) {
            if ($this->sl_DEBBUG > 2) $this->debbug('# time_link_data_update: ', 'timer', (microtime(1) - $time_ini_link_data_update));
            return;
        }

        $link_product_core_data = $this->get_product_core_data($link_product_id);    
        $link_qty = 0;
        
        if ($link_type == $this->product_link_type_grouped_db){

            if (!in_array($link_product_core_data['type_id'], array($this->product_type_simple, $this->product_type_virtual, $this->product_type_downloadable))){

                $this->debbug('## Error. Product reference '.$link_reference.' type not valid: '.$link_product_core_data['type_id']);
                return;

            }

            if (isset($link_data['linked_qty'])){

                $link_qty = $link_data['linked_qty'];

            }                            

            if ($parent_product_core_data['type_id'] != $this->product_type_grouped){

                $this->connection->update($product_table, ['type_id' => $this->product_type_grouped], $this->tables_identifiers[$product_table].' = ' . $product_id);

            }

        }

        $generate_link = true;
        
        if (!empty($this->existing_links_data)){

            foreach ($this->existing_links_data as $keyELD => $existing_link_data) {
                
                if ($existing_link_data['linked_product_id'] == $link_product_id && $existing_link_data['link_type_id'] == $link_type){
                    
                    if ($link_type == $this->product_link_type_grouped_db){
                    
                        if (isset($link_attributes_data[$this->product_link_type_grouped_db]['qty']['product_link_attribute_id'])){
                            
                            $qty_link_data = $this->connection->fetchRow(
                                        $this->connection->select()
                                        ->from(
                                            $link_attributes_data[$this->product_link_type_grouped_db]['qty']['table'],
                                            ['value_id', 'value']
                                        )->where('product_link_attribute_id' . ' = ?', $link_attributes_data[$this->product_link_type_grouped_db]['qty']['product_link_attribute_id'])
                                        ->where('link_id' . ' = ?', $existing_link_data['link_id'])
                                        ->limit(1)
                                    );
                            
                            if (empty($qty_link_data) || (!empty($qty_link_data) && !isset($qty_link_data['value_id']))){
                                
                                $qty_values = [
                                    'product_link_attribute_id' => $link_attributes_data[$this->product_link_type_grouped_db]['qty']['product_link_attribute_id'],
                                    'link_id' => $existing_link_data['link_id'],
                                    'value' => $link_qty
                                ];
                                
                                $result_create = $this->connection->insertOnDuplicate(
                                    $link_attributes_data[$this->product_link_type_grouped_db]['qty']['table'],
                                    $qty_values,
                                    array_keys($qty_values)
                                );
                                
                            }else{
                                
                                if ($qty_link_data['value'] != $link_qty){
                                    
                                    $this->connection->update($link_attributes_data[$this->product_link_type_grouped_db]['qty']['table'], ['value' => $link_qty], 'value_id = ' . $qty_link_data['value_id']);

                                }

                            }

                        }

                    }
                    
                    $generate_link = false;
                    unset($this->existing_links_data[$keyELD]);

                }

            }

        }
        
        if ($generate_link){
            
            $this->generateLink($product_link_table, $product_id, $link_product_id, $link_type, $link_attributes_data, $link_qty );

        }

        if ($this->sl_DEBBUG > 2) $this->debbug('# time_link_data_update: ', 'timer', (microtime(1) - $time_ini_link_data_update));
        
    }

    /**
     * Function to update conns with multiconn info
     * @param  array $sl_multiconn_reg              multiconn info
     * @param  string $item_type                    item type
     * @param  int $comp_id                         comp id
     * @param  int $item_id                         item id
     * @param  array $sl_item_connectors            current item connectors info
     * @return boolean                              result of update
     */
    private function updateConns($sl_multiconn_reg, $item_type, $comp_id, $item_id, $sl_item_connectors){
        
        if ($sl_multiconn_reg['item_type'] == $item_type && $sl_multiconn_reg['sl_comp_id'] == $comp_id && $sl_multiconn_reg['sl_id'] == $item_id){

            try{

                $connectors_data = json_decode($sl_multiconn_reg['sl_connectors'],1);

                if (!is_array($connectors_data) || (is_array($connectors_data) && empty($connectors_data))){ $connectors_data = []; }

                $new_connectors_data = json_encode(array_unique(array_merge($connectors_data, $sl_item_connectors)));

                if ($new_connectors_data != $connectors_data){

                    $query_update  = " UPDATE ".$this->saleslayer_multiconn_table.
                        " SET sl_connectors =  ? ".
                        " WHERE id =  ? ";

                    $this->sl_connection_query($query_update, array($new_connectors_data, $sl_multiconn_reg['id']));
                    
                }

            }catch(\Exception $e){

                $this->debbug('## Error. Updating multiconn table: '.$e->getMessage());
            
            }

            return true;

        }

        return false;

    }

    /**
     * Function to process conns with multiconn info
     * @param  array $sl_data      sl_data to update
     * @return void
     */
    private function saveConns($sl_data){

        $sl_multiconn_table_data = $this->connection->fetchAll(" SELECT * FROM ".$this->saleslayer_multiconn_table);

        foreach ($sl_data as $comp_id => $sl_data_regs) {

            foreach ($sl_data_regs as $item_type => $sl_item_data) {

                foreach ($sl_item_data as $item_id => $sl_item_connectors) {

                    $found = false;

                    if (!empty($sl_multiconn_table_data)){

                        foreach ($sl_multiconn_table_data as $sl_multiconn_reg) {

                            if ($this->updateConns($sl_multiconn_reg, $item_type, $comp_id, $item_id, $sl_item_connectors)){
                                $found = true;
                            }
                      
                        }
                    }

                    if (!$found){

                        try{

                            $connectors_data = json_encode($sl_item_connectors);

                            $query_insert = " INSERT INTO ".$this->saleslayer_multiconn_table.
                                "(`item_type`,`sl_id`,`sl_comp_id`,`sl_connectors`) ".
                                "values ( ? , ? , ? , ? );";

                            $this->sl_connection_query($query_insert, array($item_type, $item_id, $comp_id, $connectors_data));

                        }catch(\Exception $e){
                        
                            $this->debbug('## Error. Inserting multiconn table: '.$e->getMessage());
                        
                        }

                    }

                }

            }

        }

    }

    /**
     * Function to prepare multiconn info
     * @param  array $data_tabla            Sales Layer response data
     * @param  string $nombre_tabla         item index
     * @param  array $sl_data               data to fill
     * @param  int $comp_id                 comp id
     * @param  int $connector_id            connector id
     * @return array $sl_data               data filled
     */
    private function prepareConnTableData($data_tabla, $nombre_tabla, $sl_data, $comp_id, $connector_id){

        $modified_data = $data_tabla['modified'];

        switch ($nombre_tabla) {
            case 'catalogue':

                // $this->debbug('Count total categories: '.count($modified_data));
                foreach ($modified_data as $category) {

                    if (!isset($sl_data[$comp_id]['category'][$category[$this->category_field_id]])){
                        $sl_data[$comp_id]['category'][$category[$this->category_field_id]] = [];
                    }

                    $sl_data[$comp_id]['category'][$category[$this->category_field_id]][] = $connector_id;

                }

                break;
            case 'products':

                // $this->debbug('Count total products: '.count($modified_data));
                foreach ($modified_data as  $product) {

                    if (!isset($sl_data[$comp_id]['product'][$product[$this->product_field_id]])){
                        $sl_data[$comp_id]['product'][$product[$this->product_field_id]] = [];
                    }

                    $sl_data[$comp_id]['product'][$product[$this->product_field_id]][] = $connector_id;

                }

                break;
            case 'product_formats':

                // $this->debbug('Count total product formats: '.count($modified_data));
                foreach ($modified_data as $format) {

                    if (!isset($sl_data[$comp_id]['format'][$format[$this->format_field_id]])){
                        $sl_data[$comp_id]['format'][$format[$this->format_field_id]] = [];
                    }

                    $sl_data[$comp_id]['format'][$format[$this->format_field_id]][] = $connector_id;

                }

                break;
            default:

                $this->debbug('## Error. Updating multiconn table, table '.$nombre_tabla.' not recognized.');

                break;
        }

        return $sl_data;

    }

    /**
     * Function to load connectors data for load multiconn process
     * @param  array $connector                 connector data
     * @param  array $sl_data                   multiconn data to fill
     * @return array $sl_data                   multiconn data filled
     */
    private function loadConnItems($connector, $sl_data){

        $connector_id = $connector['connector_id'];
        $secret_key = $connector['secret_key'];

        $slconn = new SalesLayerConn ($connector_id, $secret_key);

        $slconn->set_API_version(self::sl_API_version);
        $slconn->set_group_multicategory(true);
        $slconn->get_info();

        if ($slconn->has_response_error()) { 
            return; 
        }

        if ($response_connector_schema = $slconn->get_response_connector_schema()) {

            $response_connector_type = $response_connector_schema['connector_type'];
            if ($response_connector_type != self::sl_connector_type) { 
                return;
            }

        }

        $comp_id = $slconn->get_response_company_ID();

        $get_response_table_data  = $slconn->get_response_table_data();

        $get_data_schema = self::get_data_schema($slconn);

        if (!$get_data_schema){
            return;
        }

        $products_schema = $get_data_schema['products'];

        if (!empty($products_schema['fields'][strtolower($this->product_field_sku)])){
            $this->product_field_sku = strtolower($this->product_field_sku);
        }else if (!empty($products_schema['fields'][strtoupper($this->product_field_sku)])){
            $this->product_field_sku = strtoupper($this->product_field_sku);
        }

        if ($get_response_table_data) {

            if (!isset($sl_data[$comp_id])){ $sl_data[$comp_id] = []; }

            foreach ($get_response_table_data as $nombre_tabla => $data_tabla) {

                $sl_data = $this->prepareConnTableData($data_tabla, $nombre_tabla, $sl_data, $comp_id, $connector_id);

            }
        }

        return $sl_data;

    }

    /**
     * Function to update multiconn category data
     * @param  int $sl_id                           Sales Layer category id
     * @param  string $sl_category_name             category name
     * @return void
     */
    private function saveMultiConnCategory($sl_id, $sl_category_name){

        try{

            $conn_insert = true;
            if (isset($this->sl_multiconn_table_data['category'][$sl_id]) && !empty($this->sl_multiconn_table_data['category'][$sl_id]['sl_connectors'])){

                $conn_found = array_search($this->processing_connector_id, $this->sl_multiconn_table_data['category'][$sl_id]['sl_connectors']);

                if (!is_numeric($conn_found)){

                    $this->sl_multiconn_table_data['category'][$sl_id]['sl_connectors'][] = $this->processing_connector_id;
                    
                    $new_connectors_data = json_encode($this->sl_multiconn_table_data['category'][$sl_id]['sl_connectors']);
                    
                    $query_update  = " UPDATE ".$this->saleslayer_multiconn_table." SET sl_connectors = ?  WHERE id = ? ";
                    
                    $this->sl_connection_query($query_update, array($new_connectors_data , $this->sl_multiconn_table_data['category'][$sl_id]['id']));

                }

                $conn_insert = false;

            }

            if ($conn_insert){

                $connectors_data = json_encode(array($this->processing_connector_id));

                $query_insert = " INSERT INTO ".$this->saleslayer_multiconn_table."(`item_type`,`sl_id`,`sl_comp_id`,`sl_connectors`) values ( ? , ? , ? , ? );";

                $this->sl_connection_query($query_insert, array('category', $sl_id, $this->comp_id, $connectors_data));
                
            }

        } catch (\Exception $e) {

            $this->debbug('## Error. Updating core category '.$sl_category_name.' SL multiconn data.');

        }

    }

}