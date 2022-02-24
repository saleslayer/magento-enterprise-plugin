<?php

namespace Saleslayer\Synccatalog\Setup;

use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Eav\Setup\EavSetupFactory;

/**
 * @codeCoverageIgnore
 */
class InstallData implements InstallDataInterface
{

    private $objectManager;
    private $eavSetupFactory;

    public function __construct(\Magento\Framework\ObjectManagerInterface $objectManager, EavSetupFactory $eavSetupFactory)
    {

        $this->objectManager = $objectManager;
        $this->eavSetupFactory = $eavSetupFactory;
    }

	/**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {

        $parentId = \Magento\Catalog\Model\Category::TREE_ROOT_ID;

        $parentCategory = $this->objectManager
                              ->create('Magento\Catalog\Model\Category')
                              ->load($parentId);
        $category = $this->objectManager
                        ->create('Magento\Catalog\Model\Category');
        
        $sl_cat = $category->getCollection()
                    ->addAttributeToFilter('name','Sales Layer')
                    ->getFirstItem();

        if($sl_cat->getId() == null) {
            $category->setPath($parentCategory->getPath())
                ->setParentId($parentId)
                ->setName('Sales Layer')
                ->setIsActive(true)
                ->setIsAnchor(false);
            $category->save();
        }

        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]); 
        
        $eavSetup->addAttribute(
        \Magento\Catalog\Model\Category::ENTITY,
        'saleslayer_id',
        [
            'group' => 'General Information',
            'type' => 'int',
            'label' => 'Sales Layer Category Identification',
            'input' => 'text',
            'source' => '',
            'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
            'visible' => true,
            'required' => false,
            'user_defined' => false,
            'sort_order' => 10,
            'default' => 0,
            'note' => "Don't modify or delete this field.",
        ]
        );

        $eavSetup->addAttribute(
        \Magento\Catalog\Model\Category::ENTITY,
        'saleslayer_comp_id',
        [
            'group' => 'General Information',
            'type' => 'int',
            'label' => 'Sales Layer Category Company Identification',
            'input' => 'text',
            'source' => '',
            'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
            'visible' => true,
            'required' => false,
            'user_defined' => false,
            'sort_order' => 20,
            'default' => 0,
            'note' => "Don't modify or delete this field.",
        ]
        );

        $eavSetup->addAttribute(
        \Magento\Catalog\Model\Product::ENTITY,
        'saleslayer_id',
        [
            'type' => 'int',
            'backend' => '',
            'frontend' => '',
            'label' => 'Sales Layer Product Identification',
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

        $eavSetup->addAttribute(
        \Magento\Catalog\Model\Product::ENTITY,
        'saleslayer_comp_id',
        [
            'type' => 'int',
            'backend' => '',
            'frontend' => '',
            'label' => 'Sales Layer Product Company Identification',
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