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
    protected $_systemStore;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Backend\Helper\Data $backendHelper
     * @param \Saleslayer\Synccatalog\Model\Synccatalog $synccatalog
     * @param \Saleslayer\Synccatalog\Model\ResourceModel\Synccatalog\CollectionFactory $collectionFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Backend\Helper\Data $backendHelper,
        \Saleslayer\Synccatalog\Model\Synccatalog $synccatalog,
        \Saleslayer\Synccatalog\Model\ResourceModel\Synccatalog\CollectionFactory $collectionFactory,
        \Magento\Store\Model\System\Store $systemStore,
        array $data = []
    ) {
        $this->_collectionFactory = $collectionFactory;
        $this->_synccatalog = $synccatalog;
        $this->_systemStore = $systemStore;
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
        /* @var $collection \Saleslayer\Synccatalog\Model\ResourceModel\Synccatalog\Collection */

        // $file = BP.'/var/log/sl_logs/_debbug_log_saleslayer_test.txt';

        // // $stores_data = $this->_systemStore->getStoreValuesForForm(false, true);

        // // $store_data = $this->_systemStore->getStoreName(2);
        // // file_put_contents($file, 'store_data: '.print_r($store_data,1)."\n", FILE_APPEND);

        
        // // $storeCollection = $this->_scopeInterface->_loadStoreCollection();
        // // file_put_contents($file, 'storeCollection: '.print_r($storeCollection,1)."\n", FILE_APPEND);
        // // $collectionData = $collection->getData();
        // // // file_put_contents($file, 'test: '.print_R($collectionData,1)."\n", FILE_APPEND);

        // foreach ($collection as $keyConn => $connector) {
        //     // file_put_contents($file, 'key: '.$keyConn."\n", FILE_APPEND);
        //     // file_put_contents($file, 'connector: '.print_r($connector->getData(),1)."\n", FILE_APPEND);
        //     $connectorData = $connector->getData();
        //     $store_view_ids = json_decode($connectorData['store_view_ids'],1);
        //     file_put_contents($file, 'store_view_ids: '.print_r($store_view_ids,1)."\n", FILE_APPEND);
        //     $store_names = array();
        //     foreach ($store_view_ids as $store_view_id) {
        // // file_put_contents($file, 'store_view_id: '.print_r($store_view_id,1)."\n", FILE_APPEND);
        //         if ($store_view_id == 0){
        //             $store_name = 'All Store Views';
        //         }else{
        //             $store_name = $this->_systemStore->getStoreName($store_view_id);
        //         }
        //         array_push($store_names, $store_name);
        //     }
        //     // if (count($store_names) > 1){
        //         $store_names = implode(',', $store_names);
        //         $connectorData['store_view_id'] = $store_names;
        //     // }else{
        //     //     $store_names = $store_names[0];
        //     // }
        //     // file_put_contents($file, 'store_names: '.$store_names."\n", FILE_APPEND);
        //     // // $collection[$keyConn['store_view_ids']] = $store_names;
        //     // $collection->setStoreViewIds('');
        //     // $collectionData[$keyConn]['store_view_ids'] = $store_names;
        //     // $name_test = $this->_scopeConfig->getStore()->getName();
        //     // file_put_contents($file, 'name_test: '.print_r($name_test,1)."\n", FILE_APPEND);
        //     // file_put_contents($file, 'col id: '.print_r($col->getId(),1)."\n", FILE_APPEND);
        //     // file_put_contents($file, 'col index: '.print_r($col->getIndex(),1)."\n", FILE_APPEND);
        //     // file_put_contents($file, 'store_names: '.print_r($store_names,1)."\n", FILE_APPEND);
        //     $connector->setStoreViewIds($store_names);
        //     file_put_contents($file, 'connector data before add: '.print_r($connector->getData(),1)."\n", FILE_APPEND);
        //     // $connector->setData($connectorData);
        //     // $collection->updateObject($connector);
        //     $collection->removeItemByKey($keyConn);
        //     $collection->addItem($connector);
        //     // $collection->save();
        // }

        // foreach ($collection as $keyConn => $connector) {
        

        // }
        
        // file_put_contents($file, 'test data: '.print_r($collection->getData(),1)."\n", FILE_APPEND);

        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    /**
     * Prepare columns
     *
     * @return \Magento\Backend\Block\Widget\Grid\Extended
     */
    protected function _prepareColumns()
    {
        $this->addColumn('id', [
            'header'    => __('ID'),
            'index'     => 'id',
        ]);
        
        $this->addColumn('connector_id', ['header' => __('Connector ID'), 'index' => 'connector_id']);

        // $this->addColumn('store_view_ids', ['header' => __('Store View IDs'), 'index' => 'store_view_ids']);
        // $this->addColumn('secret_key', ['header' => __('Secret Key'), 'index' => 'secret_key']);
        
        $this->addColumn(
            'last_update',
            [
                'header' => __('Last Update'),
                'index' => 'last_update',
                'type' => 'datetime',
                'header_css_class' => 'col-date',
                'column_css_class' => 'col-date'
            ]
        );

        // $this->addColumn(
        //     'sync_data',
        //     [
        //         'header' => __('Synchronization Data'),
        //         'index' => 'sync_ata',
        //         'header_css_class' => 'col-sync',
        //         'column_css_class' => 'col-sync'
        //     ]
        // );

        $test = parent::_prepareColumns();

        // $file = BP.'/_debbug_log_saleslayer_test.txt';

        // foreach ($test as $key => $t) {
        //     // file_put_contents($file, 'test_data: '.print_r($test->getMultipleRows($test),1), FILE_APPEND);
        //     file_put_contents($file, 'test_data: '.print_r($t,1), FILE_APPEND);
        // }

        return $test;
        // return parent::_prepareColumns();
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
