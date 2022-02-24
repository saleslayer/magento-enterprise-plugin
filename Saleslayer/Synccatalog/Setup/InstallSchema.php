<?php

namespace Saleslayer\Synccatalog\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

/**
 * @codeCoverageIgnore
 */
class InstallSchema implements InstallSchemaInterface
{
    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
		$installer = $setup;
		$installer->startSetup();

		/**
		 * Creating table saleslayer_synccatalog_apiconfig
		 */
		$table = $installer->getConnection()->newTable(
			$installer->getTable('saleslayer_synccatalog_apiconfig')
		)->addColumn(
			'id',
			\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
			null,
			['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
			'Entity Id'
		)->addColumn(
		    'connector_id',
		    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
		    30,
		    ['nullable' => false],
		    'Connector ID'
		)->addColumn(
		    'secret_key',
		    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
		    32,
		    ['nullable' => false],
		    'Connector Secret Key'
		)->addColumn(
		    'default_cat_id',
		    \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
		    null,
		    ['nullable' => false],
		    'Default Category ID'
		)->addColumn(
		    'last_update',
		    \Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
		    null,
		    [],
		    'Connector Last Update'
		)->addColumn(
		    'comp_id',
		    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
		    20,
		    ['nullable' => false],
		    'Connector Company ID'
		)->addColumn(
		    'default_language',
		    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
		    6,
		    ['nullable' => false],
		    'Connector Default Language'
		)->addColumn(
		    'languages',
		    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
		    '2M',
		    ['nullable' => false],
		    'Connector Languages'
		)->addColumn(
		    'updater_version',
		    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
		    10,
		    ['nullable' => false],
		    'Connector Updater Version'
		)->addColumn(
		    'conn_extra',
		    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
		    '2M',
		    ['nullable' => true],
		    'Connector Extra Information'
		)->addIndex(
			$installer->getIdxName(
				'saleslayer_synccatalog_apiconfig',
				['connector_id'],
				\Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_INDEX
			),
			['connector_id'],
			['type' => \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_INDEX]
		)->setComment(
			'Sales Layer Synchronization Catalog Table'
		);
		$installer->getConnection()->createTable($table);
		$installer->endSetup();

	}
}