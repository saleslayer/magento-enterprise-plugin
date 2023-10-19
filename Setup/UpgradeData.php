<?php

namespace Saleslayer\Synccatalog\Setup;

use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class UpgradeData implements UpgradeDataInterface
{
    
    private $eavSetupFactory;
    
    public function __construct(\Magento\Eav\Setup\EavSetupFactory $eavSetupFactory)
    {
        $this->eavSetupFactory = $eavSetupFactory;
    }
    
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        
        if (version_compare($context->getVersion(), '1.0.1') < 0) {

            $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
            
            $eavSetup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            'saleslayer_format_id',
            [
                'type' => 'int',
                'backend' => '',
                'frontend' => '',
                'label' => 'Sales Layer Product Format Identification',
                'input' => 'text',
                'class' => '',
                'source' => '',
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'required' => false,
                'user_defined' => false,
                'default' => 0,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => true,
                'unique' => false,
                'apply_to' => '',
                'note' => "Don't modify or delete this field."
            ]
            );
            
        }

    }
}