<?php

namespace Saleslayer\Synccatalog\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $setup->startSetup();

        $version = $context->getVersion();
        $connection = $setup->getConnection();

        $saleslayer_synccatalog_apiconfig_table = $setup->getTable('saleslayer_synccatalog_apiconfig');
        
        if (version_compare($version, '1.0.2') < 0) {

            $connection->addColumn(
                $setup->getTable('saleslayer_synccatalog_apiconfig'),
                'store_view_ids',
                [
                    'type' => Table::TYPE_TEXT,
                    'length' => 255,
                    'nullable' => false,
                    'comment' => 'Magento Store View IDs',
                ]
            );
        }

        if (version_compare($version, '1.0.3') < 0) {

            $connection->addColumn(
                $setup->getTable('saleslayer_synccatalog_apiconfig'),
                'format_configurable_attributes',
                [
                    'type' => Table::TYPE_TEXT,
                    'length' => 255,
                    'nullable' => true,
                    'comment' => 'Magento Format Configurable Attributes IDs',
                ]
            );
        }

        if (version_compare($version, '1.0.5') < 0) {
            $connection->addColumn(
                $setup->getTable('saleslayer_synccatalog_apiconfig'),
                'products_previous_categories',
                [
                    'type' => Table::TYPE_BOOLEAN,
                    'default' => 1,
                    'nullable' => false,
                    'comment' => 'Magento Connector Products Previous Categories',
                ]
            );
        }

        if (version_compare($version, '2.1.7') < 0) {

            $connection->addColumn(
                $saleslayer_synccatalog_apiconfig_table,
                'avoid_stock_update',
                [
                    'type' => Table::TYPE_BOOLEAN,
                    'default' => 0,
                    'nullable' => false,
                    'comment' => 'Connector Avoid Stock Update Option',
                ]
            );

            $connection->addColumn(
                $saleslayer_synccatalog_apiconfig_table,
                'auto_sync',
                [
                    'type' => Table::TYPE_INTEGER,
                    'length' => 3,
                    'nullable' => true,
                    'comment' => 'Connector Auto Sync Option',
                ]
            );

            $connection->addColumn(
                $saleslayer_synccatalog_apiconfig_table,
                'last_sync',
                [
                    'type' => Table::TYPE_DATETIME,
                    'nullable' => true,
                    'comment' => 'Connector Last Synchronization Date',
                ]
            );

            $multiconn_table = $connection->newTable(
                $installer->getTable('saleslayer_synccatalog_multiconn')
            )->addColumn(
                'id',
                Table::TYPE_INTEGER,
                null,
                ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                'Id'
            )->addColumn(
                'item_type',
                Table::TYPE_TEXT,
                30,
                ['nullable' => false],
                'Item Type'
            )->addColumn(
                'sl_id',
                Table::TYPE_INTEGER,
                32,
                ['nullable' => false],
                'SL ID'
            )->addColumn(
                'sl_comp_id',
                Table::TYPE_INTEGER,
                null,
                ['nullable' => false],
                'SL Company'
            )->addColumn(
                'sl_connectors',
                Table::TYPE_TEXT,
                '2M',
                ['nullable' => true],
                'Sales Layer Connectors'
            )->addIndex(
                $installer->getIdxName(
                    'saleslayer_synccatalog_multiconn',
                    ['id'],
                    \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_INDEX
                ),
                ['id'],
                ['type' => \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_INDEX]
            )->setComment(
                'Sales Layer Items Multi-connector Table'
            );

            $connection->createTable($multiconn_table);

        }

        if (version_compare($version, '2.1.8') < 0) {
            
            $connection->addColumn(
                $saleslayer_synccatalog_apiconfig_table,
                'category_is_anchor',
                [
                    'type' => Table::TYPE_BOOLEAN,
                    'default' => 0,
                    'nullable' => false,
                    'comment' => 'Magento Category is anchor',
                ]
            );
            
            $connection->addColumn(
                $saleslayer_synccatalog_apiconfig_table,
                'category_page_layout',
                [
                    'type' => Table::TYPE_TEXT,
                    'length' => 255,
                    'default' => '1column',
                    'nullable' => false,
                    'comment' => 'Magento Category page layout',
                ]
            );

            $slyr_sync_table = $connection->newTable(
                $installer->getTable('saleslayer_synccatalog_syncdata')
            )->addColumn(
                'id',
                Table::TYPE_BIGINT,
                null,
                ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                'Id'
            )->addColumn(
                'sync_type',
                Table::TYPE_TEXT,
                10,
                ['nullable' => false],
                'Sync Type'
            )->addColumn(
                'item_type',
                Table::TYPE_TEXT,
                30,
                ['nullable' => false],
                'Item Type'
            )->addColumn(
                'sync_tries',
                Table::TYPE_INTEGER,
                null,
                ['nullable' => false, 'default' => 0],
                'Sync Tries'
            )->addColumn(
                'item_data',
                Table::TYPE_TEXT,
                '2M',
                ['nullable' => true],
                'Item Data'
            )->addColumn(
                'sync_params',
                Table::TYPE_TEXT,
                '2M',
                ['nullable' => true],
                'Sync Parameters'
            )->addIndex(
                $installer->getIdxName(
                    'saleslayer_synccatalog_syncdata',
                    ['id'],
                    \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_INDEX
                ),
                ['id'],
                ['type' => \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_INDEX]
            )->setComment(
                'Sales Layer Sync Data Table'
            );

            $connection->createTable($slyr_sync_table);

        }

        if (version_compare($version, '2.1.9') < 0) {

            $slyr_sync_flag_table = $connection->newTable(
                $installer->getTable('saleslayer_synccatalog_syncdata_flag')
            )->addColumn(
                'id',
                Table::TYPE_BIGINT,
                null,
                ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                'Id'
            )->addColumn(
                'syncdata_pid',
                Table::TYPE_BIGINT,
                null,
                ['nullable' => false, 'default' => 0],
                'Sync Data Pid'
            )->addColumn(
                'syncdata_last_date',
                Table::TYPE_DATETIME,
                null,
                ['nullable' => false],
                'Sync Data Last Update'
            )->addIndex(
                $installer->getIdxName(
                    'saleslayer_synccatalog_syncdata_flag',
                    ['id'],
                    \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_INDEX
                ),
                ['id'],
                ['type' => \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_INDEX]
            )->setComment(
                'Sales Layer Sync Data Flag Table'
            );

            $connection->createTable($slyr_sync_flag_table);

        }        

        if (version_compare($version, '2.2.0') < 0) {

            $connection->addColumn(
                $saleslayer_synccatalog_apiconfig_table,
                'auto_sync_hour',
                [
                    'type' => Table::TYPE_INTEGER,
                    'length' => 2,
                    'nullable' => false,
                    'default'=> '0',
                    'comment' => 'Connector preferred Auto-Sync Hour Option',
                ]
            );

        }
 
        if (version_compare($version, '2.3.0') < 0) {

            $attributes_tables = array('catalog_category_entity_decimal', 'catalog_category_entity_int', 'catalog_category_entity_text', 'catalog_category_entity_varchar',
                                        'catalog_product_entity_decimal', 'catalog_product_entity_int', 'catalog_product_entity_text', 'catalog_product_entity_varchar');

            foreach ($attributes_tables as $attribute_table) {

                if (strpos($attribute_table, '_text') !== false){

                    try{

                        $sql_create_idx = "CREATE INDEX SLYR_CREDENTIALS ON ".$attribute_table." (attribute_id, store_id, value(50));";
                        $setup->getConnection()->query($sql_create_idx);
                    
                    }catch(\Exception $e){

                        file_put_contents(BP.'/var/log/sl_logs/_upgrade_eschema_error_'.date('Y-m-d').'.dat', 'Error creating index: '.$e->getMessage()."\r\n", FILE_APPEND);

                    }

                }else{

                    $setup->getConnection()->addIndex(
                        $installer->getTable($attribute_table),
                        'SLYR_CREDENTIALS',
                        ['attribute_id', 'store_id', 'value'],
                        \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_INDEX
                    );

                }

            }

        }

        if (version_compare($version, '2.6.1') < 0) {

            $connection->modifyColumn(
                $saleslayer_synccatalog_apiconfig_table,
                'format_configurable_attributes',
                [
                    'type' => Table::TYPE_TEXT,
                    'length' => '2M'
                ]
            );

        }

        if (version_compare($version, '2.6.2') < 0) {

            $connection->addColumn(
                $setup->getTable('saleslayer_synccatalog_syncdata'),
                'level',
                [
                    'type' => Table::TYPE_INTEGER,
                    'length' => 3,
                    ['nullable' => false, 'default' => 0],
                    'comment' => 'Item level',
                ]
            );
            
        }
    
        $setup->endSetup();

    }

}
