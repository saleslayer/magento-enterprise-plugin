<?php
namespace Saleslayer\Synccatalog\Block\Adminhtml\Synccatalog;

/**
 * Adminhtml synccatalog pages grid
 */
class Grid extends \Magento\Backend\Block\Widget\Grid\Extended
{
    /**
     * @var \Saleslayer\Synccatalog\Model\ResourceModel\Synccatalog\CollectionFactory
     */
    protected $_collectionFactory;

    /**
     * @var \Saleslayer\Synccatalog\Model\Synccatalog
     */
    protected $_synccatalog;

        /**
     * @var \Magento\Cron\Model\Schedule
     */
    protected $_cronSchedule;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $_connection;

    /**
     * @var \Magento\Framework\App\DeploymentConfig
     */
    protected $_deploymentConfig;

    protected $tablePrefix = null;

    /**
     * @param \Magento\Backend\Block\Template\Context                                   $context
     * @param \Magento\Backend\Helper\Data                                              $backendHelper
     * @param \Saleslayer\Synccatalog\Model\Synccatalog                                 $synccatalog
     * @param \Magento\Cron\Model\Schedule                                              $cronSchedule
     * @param \Magento\Framework\App\DeploymentConfig                                   $deploymentConfig
     * @param \Magento\Framework\App\ResourceConnection                                 $resourceConnection
     * @param \Saleslayer\Synccatalog\Model\ResourceModel\Synccatalog\CollectionFactory $collectionFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Backend\Helper\Data $backendHelper,
        \Saleslayer\Synccatalog\Model\Synccatalog $synccatalog,
        \Magento\Cron\Model\Schedule $cronSchedule,
        \Magento\Framework\App\DeploymentConfig $deploymentConfig,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Saleslayer\Synccatalog\Model\ResourceModel\Synccatalog\CollectionFactory $collectionFactory,
        array $data = []
    ) {
        $this->_collectionFactory = $collectionFactory;
        $this->_synccatalog = $synccatalog;
        $this->_cronSchedule = $cronSchedule;
        $this->_deploymentConfig = $deploymentConfig;
        $this->_connection = $resourceConnection->getConnection();
        parent::__construct($context, $backendHelper, $data);
    }

    /**
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId('synccatalogGrid');
        $this->setDefaultSort('id');
        $this->setDefaultDir('DESC');
        $this->setUseAjax(true);
        $this->setSaveParametersInSession(true);
    }

    /**
     * Prepare collection
     *
     * @return \Magento\Backend\Block\Widget\Grid
     */
    protected function _prepareCollection()
    {
        $collection = $this->_collectionFactory->create();
        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

        /**
     * After load collection
     * 
     * @return \Magento\Backend\Block\Widget\Grid
     */
    protected function _afterLoadCollection()
    {
        parent::_afterLoadCollection();

        $collection = $this->getCollection();
        
        foreach ($collection as $collection_item) {
            
            $like_param = '%"connector_id":"'.$collection_item->getData('connector_id').'"%';
            
            $items_processing = $this->_connection->fetchAll(
                $this->_connection->select()
                    ->from($this->getTable('saleslayer_synccatalog_syncdata'),
                    ['total' => new \Zend_Db_Expr('COUNT(*)'), 'item_type'])
                    ->where("sync_params LIKE '".$like_param."'")
                    ->group('item_type')
                   
            );

            if (!empty($items_processing)){
                
                $items_processing_message = $cron_error_message = '';
                $total_items_processing = 0;
                
                $item_types_names = [
                    'category' => 'categories',
                    'product' => 'products',
                    'product_format' => 'product formats',
                    'product_links' => 'product links',
                    'product__images' => 'product images',

                ];
                foreach ($items_processing as $items_processing_by_type){

                    $total_items_processing += $items_processing_by_type['total'];

                    if ($items_processing_message === ''){
                        $items_processing_message = "\n".$items_processing_by_type['total'].' '.$item_types_names[$items_processing_by_type['item_type']];
                    }else{
                        $items_processing_message .= "\n".$items_processing_by_type['total'].' '.$item_types_names[$items_processing_by_type['item_type']];
                    }

                }

                $collection_item_message = '<b>Connector processing</b>: '.$total_items_processing.' items total.';
                $collection_item_message .= $items_processing_message;

                $sl_last_syncdata_cron = $this->_connection->fetchRow(
                    $this->_connection->select()
                        ->from(
                           $this->getTable('cron_schedule')
                        )
                        ->where("job_code = 'Saleslayer_Synccatalog_Syncdatacron'")
                        ->where("status NOT IN (?)", ['missed', 'pending'])
                        ->where('executed_at IS NOT NULL')
                        ->order('scheduled_at DESC')
                        ->limit(1)
                );

                if (is_array($sl_last_syncdata_cron) && $sl_last_syncdata_cron['status'] == 'error'){
                    $cron_error_message .= "<b>CRON ERROR</b>: ".$sl_last_syncdata_cron['messages']."\n";
                }

                if ($cron_error_message !== '') $collection_item_message = $cron_error_message.$collection_item_message;
                $collection_item->setData('connector_status', $collection_item_message);
            }

        }

        return $this;
    }

    /**
     * Prepare columns
     *
     * @return \Magento\Backend\Block\Widget\Grid\Extended
     */
    protected function _prepareColumns()
    {
        $this->addColumn(
            'id', 
            [
                'header'    => __('ID'),
                'index'     => 'id',
                'header_css_class' => 'grid-header-class grid-col-id-class',
                'column_css_class' => 'grid-col-id-class'
            ]
        );
        
        $this->addColumn(
            'connector_id', 
            [
                'header' => __('Connector ID'), 
                'index' => 'connector_id',
                'header_css_class' => 'grid-header-class grid-col-conn-class',
                'column_css_class' => 'grid-col-conn-class'
            ]
        );

        $this->addColumn(
            'connector_status', 
            [
                'header' => __('Connector Status'), 
                'index' => 'connector_status',
                'type' => 'text',
                'filter' => false,
                'sortable' => false,
                'renderer' => 'Saleslayer\Synccatalog\Block\Adminhtml\Renderer\CustomGridText',
                'header_css_class' => 'grid-header-class grid-col-status-class',
                'column_css_class' => 'grid-col-status-class'
            ]
        );
        
        $this->addColumn(
            'last_update',
            [
                'header' => __('Last Update'),
                'index' => 'last_update',
                'type' => 'datetime',
                'header_css_class' => 'grid-header-class grid-col-update-class col-date',
                'column_css_class' => 'grid-col-update-class col-date'
            ]
        );
            
        return parent::_prepareColumns();
    }

    /**
     * Prepare layout
     * 
     * @return \Magento\Backend\Block\Widget\Grid\Extended
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        $this->pageConfig->addPageAsset(
            'Saleslayer_Synccatalog::js/grid-reload.js'
        );

        $this->pageConfig->addPageAsset(
            'Saleslayer_Synccatalog::css/grid.css'
        );
        
        return $this;
    }

    /**
     * Function to get table name with prefix.
     * @param string $tableName               table to search
     * @return string                         table in database with prefix
     */
    private function getTable($tableName){
        
        $tableNameReturn = $this->_connection->getTableName($tableName);

        if ($this->_connection->isTableExists($tableNameReturn)){

            $tablePrefix = $this->getTablePrefix();

            if ($tablePrefix && strpos($tableNameReturn, $tablePrefix) !== 0) {

                $tableNameReturn = $tablePrefix . $tableNameReturn;

            }

            return $tableNameReturn;

        }

        return null;

    }

    /**
     * Function to get table prefix
     * @return string                        configuration table prefix
     */
    private function getTablePrefix(){

        if (null === $this->tablePrefix) {
                
            $this->tablePrefix = (string)$this->_deploymentConfig->get(
                \Magento\Framework\Config\ConfigOptionsListConstants::CONFIG_PATH_DB_PREFIX
            );
        
        }
        
        return $this->tablePrefix;
    }

    /**
     * Row click url
     *
     * @param \Magento\Framework\Object $row
     * @return string
     */
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/edit', ['id' => $row->getId()]);
    }

    /**
     * Get grid url
     *
     * @return string
     */
    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', ['_current' => true]);
    }
}
