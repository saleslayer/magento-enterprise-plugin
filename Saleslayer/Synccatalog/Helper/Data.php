<?php
/**
 * Synccatalog data helper
 */
namespace Saleslayer\Synccatalog\Helper;

use Magento\Framework\App\Filesystem\DirectoryList;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{

    /**
     * Path to store config where count of connectors per page is stored
     *
     * @var string
     */
    const XML_PATH_CONNECTORS_PER_PAGE     = 'synccatalog/view/connectors_per_page';

    /**
     * @var \Magento\Catalog\Api\ProductAttributeRepositoryInterface
     */
    protected $attributeRepository;

    /**
     * @var array
     */
    protected $attributeValues;

    /**
     * @var \Magento\Eav\Model\Entity\Attribute\Source\TableFactory
     */
    protected $tableFactory;
    
    /**
     * @var \Magento\Eav\Api\Data\AttributeOption
     */
    protected $optionModel;

    /**
     * @var array
     */
    protected $store_ids;

    /**
     * @var \Magento\Catalog\Model\Product\Attribute\Repository
     */
    protected $repositoryAttribute;

    /**
     * @var \Magento\Swatches\Helper\Data
     */
    protected $swatchHelper;

    /**
     * @var \Magento\Swatches\Model\Swatch
     */
    protected $swatchModel;

    /**
     * @var \Magento\Swatches\Model\ResourceModel\Swatch\CollectionFactory
     */
    protected $swatchCollectionFactory;

    /**
     * Data constructor.
     *
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Catalog\Api\ProductAttributeRepositoryInterface $attributeRepository
     * @param \Magento\Eav\Model\Entity\Attribute\Source\TableFactory $tableFactory
     * @param \Magento\Eav\Model\Entity\Attribute\Option $optionModel
     * @param \Magento\Catalog\Model\Product\Attribute\Repository $repositoryAttribute
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Catalog\Api\ProductAttributeRepositoryInterface $attributeRepository,
        \Magento\Eav\Model\Entity\Attribute\Source\TableFactory $tableFactory,
        \Magento\Eav\Model\Entity\Attribute\Option $optionModel,
        \Magento\Catalog\Model\Product\Attribute\Repository $repositoryAttribute,
        \Magento\Swatches\Helper\Data $swatchHelper,
        \Magento\Swatches\Model\Swatch $swatchModel,
        \Magento\Swatches\Model\ResourceModel\Swatch\CollectionFactory $swatchCollectionFactory,
        \Magento\Eav\Api\Data\AttributeOptionLabelInterfaceFactory $optionLabelFactory,
        \Magento\Eav\Api\Data\AttributeOptionInterfaceFactory $optionFactory,
        \Magento\Eav\Api\AttributeOptionManagementInterface $attributeOptionManagement
    ) {
        parent::__construct($context);
        $this->attributeRepository = $attributeRepository;
        $this->tableFactory = $tableFactory;
        $this->optionModel = $optionModel;
        $this->repositoryAttribute = $repositoryAttribute;
        $this->swatchHelper = $swatchHelper;
        $this->swatchModel = $swatchModel;
        $this->swatchCollectionFactory = $swatchCollectionFactory;
        $this->optionLabelFactory = $optionLabelFactory;
        $this->optionFactory = $optionFactory;
        $this->attributeOptionManagement = $attributeOptionManagement;
    }


    /**
     * Function to Updatate attribute option
     * @param array $option_stores array of values of stores view to update
     * @param string $attributeCode attribute code  in string  ('color' or 'size'....)
     * @param int $optionId Option_id
     * @param string $optionDefaultValue value to save  if $option_stores view is null
     * return booleano
     */
    // public function updateAttributeOption($attribute_code, $option_id, $option_value, $option_data){
    public function updateAttributeOption($attribute_code, $option_id, $option_data){
        
        // $this->debbug_data('updateAttributeOption - attribute_code: '.$attribute_code.' - option_id: '.$option_id.' - option_value: '.$option_value.' - option_data: '.print_R($option_data,1));
     
        try{

            $attribute = $this->getAttribute($attribute_code);

            $swatch_input_type = 'dropdown';
            
            if ($this->swatchHelper->isSwatchAttribute($attribute)){

                if ($this->swatchHelper->isVisualSwatch($attribute)) {

                    $swatch_input_type = 'visual';

                }else if ($this->swatchHelper->isTextSwatch($attribute)){

                    $swatch_input_type = 'text';

                }

            }

            $option = $this->optionModel->load($option_id);

            $sort_order = $option->getSortOrder();

            $attribute->setData('option', array('value' => array($option_id => $option_data)));
            $attribute->save();

        }catch(\Exception $e){

            // $this->debbug_data('## Error on saving cloned option from attribute: '.print_R($e->getMessage(),1));
            return false;

        }

        try{
        
            $option = $this->optionModel->load($option_id);
            $option->setSortOrder($sort_order)->save();
        
        }catch(\Exception $e){
        
            // $this->debbug_data('## Error on saving setSortOrder: '.print_R($e->getMessage(),1));

        }

        if ($swatch_input_type == 'text'){
            
            try{

               foreach ($option_data as $store_view_id => $value) {

                   $swatchCollection = $this->swatchCollectionFactory->create();
                   $swatch = $swatchCollection
                           ->addFieldToFilter('option_id', $option_id)
                           ->addFieldToFilter('store_id', $store_view_id)
                           ->setPageSize(1)
                           ->getFirstItem();
            
                   if (empty($swatch->getData())){   

                       $new_swatch = clone $this->swatchModel;
                       $new_swatch->setOptionId($option_id);
                       $new_swatch->setStoreId($store_view_id);
                       $new_swatch->setValue($value);
                       $new_swatch->setType($this->swatchModel::SWATCH_TYPE_TEXTUAL);
                       $new_swatch->save();    

                   }

               }

           }catch(\Exception $e){

               // $this->debbug_data('## Error creating new swatch: '.$e->getMessage());

           }
        
        }

        return true;

    }

    /**
     * Get attribute by code.
     *
     * @param string $attributeCode
     * @return \Magento\Catalog\Api\Data\ProductAttributeInterface
     */
    public function getAttribute($attributeCode){

        return $this->attributeRepository->get($attributeCode);
    
    }

    /**
     * Find or create a matching attribute option
     *
     * @param string $attributeCode Attribute the option should exist in
     * @param string $label Label to find or add
     * @return int
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function createOrGetId($attributeCode, $label, $store_ids){

        // $this->debbug_data('createOrGetId - attributeCode: '.$attributeCode.' - label: '.$label.' - store_ids: '.print_R($store_ids,1));

        if (strlen($label) < 1) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Label for %1 must not be empty.', $attributeCode)
            );
        }

        if (empty($store_ids)){
            $this->store_ids = array(0);
        }else{
            $this->store_ids = $store_ids;
            if (!in_array(0, $this->store_ids)){ $this->store_ids[] = 0; }
        }

        
        // Does it already exist?
        $optionId = $this->getOptionId($attributeCode, $label);
        
        if (!$optionId){
            // If no, add it.
            $attribute = $this->getAttribute($attributeCode);

            $swatch_input_type = 'dropdown';
            
            if ($this->swatchHelper->isSwatchAttribute($attribute)){

                if ($this->swatchHelper->isVisualSwatch($attribute)) {

                    $swatch_input_type = 'visual';

                }else if ($this->swatchHelper->isTextSwatch($attribute)){

                    $swatch_input_type = 'text';

                }

            }

            $sort_order = $this->getAttributeLastSortOrder($attributeCode);

            try{

                $optionLabel = $this->optionLabelFactory->create();
                $optionLabel->setStoreId(0);
                $optionLabel->setLabel($label);
                
                $option_labels = array($optionLabel);

                foreach ($this->store_ids as $store_id) {
                    
                    if ($store_id == 0){ continue; }
                    $option_label_store = clone $optionLabel;
                    $option_label_store->setStoreId($store_id);
                    $option_label_store->setLabel($label);

                    $option_labels[] = $option_label_store;

                }

                $option = $this->optionFactory->create();
                $option->setLabel($label);
                $option->setStoreLabels($option_labels);
                $option->setSortOrder($sort_order);
                $option->setIsDefault(false);
                
                $this->attributeOptionManagement->add(
                    \Magento\Catalog\Model\Product::ENTITY,
                    $attribute->getAttributeId(),
                    $option
                );

                $optionId = $this->getOptionId($attributeCode, $label, true);

            }catch(\Exception $e){

                // $this->debbug_data('## Error creating new option: '.$e->getMessage());

            }

            if ($swatch_input_type == 'text'){

                try{

                    foreach ($this->store_ids as $store_id) {
                        
                        $swatchCollection = $this->swatchCollectionFactory->create();
                        $swatch = $swatchCollection
                               ->addFieldToFilter('option_id', $optionId)
                               ->addFieldToFilter('store_id', $store_id)
                               ->setPageSize(1)
                               ->getFirstItem();
                      
                        if (empty($swatch->getData())){   

                           $new_swatch = clone $this->swatchModel;
                           $new_swatch->setOptionId($optionId);
                           $new_swatch->setStoreId($store_id);
                           $new_swatch->setValue($label);
                           $new_swatch->setType($this->swatchModel::SWATCH_TYPE_TEXTUAL);
                           $new_swatch->save();
                           
                       }

                   }

               }catch(\Exception $e){

                   // $this->debbug_data('## Error creating new swatch: '.$e->getMessage());

               }
            
            }

        }

        return $optionId;

    }

    /**
     * Find the ID of an option matching $label, if any.
     *
     * @param string $attributeCode Attribute code
     * @param string $label Label to find
     * @param bool $force If true, will fetch the options even if they're already cached.
     * @return int|false
     */
    public function getOptionId($attributeCode, $label, $force = false){
        
        /** @var \Magento\Catalog\Model\ResourceModel\Eav\Attribute $attribute */
        $attribute = $this->getAttribute($attributeCode);

        // Build option array if necessary
        if ($force === true || !isset($this->attributeValues[ $attribute->getAttributeId() ])) {
            $this->attributeValues[ $attribute->getAttributeId() ] = [];

            // We have to generate a new sourceModel instance each time through to prevent it from
            // referencing its _options cache. No other way to get it to pick up newly-added values.

            /** @var \Magento\Eav\Model\Entity\Attribute\Source\Table $sourceModel */
            $sourceModel = $this->tableFactory->create();
            $sourceModel->setAttribute($attribute);

            foreach ($sourceModel->getAllOptions() as $option) {
                $this->attributeValues[ $attribute->getAttributeId() ][ $option['label'] ] = $option['value'];
            }
        }

        // Return option ID if exists
        if (isset($this->attributeValues[ $attribute->getAttributeId() ][ $label ])) {
            return $this->attributeValues[ $attribute->getAttributeId() ][ $label ];
        }

        // Return false if does not exist
        return false;
    }

    /**
     * Find the last sort order of an attribute options and return the next.
     *
     * @param string $attributeCode Attribute code
     * @return int
     */
    public function getAttributeLastSortOrder($attributeCode){

        $last_sort_order = $this->optionModel->getCollection()
                                ->addFieldToSelect('sort_order')
                                ->addFieldToFilter('attribute_id', $attributeCode)
                                ->setOrder('sort_order','DESC')
                                ->getFirstItem()
                                ->getData();

        $sort_order = 1;

        if (!empty($last_sort_order)){

            $sort_order = $last_sort_order['sort_order'] + 1;
        
        }

        return $sort_order;

    }

    /**
     * Function to debbug into a Sales Layer log.
     * @param  string $msg      message to save
     * @return void
     */
    // public function debbug_data($str){

    //     file_put_contents(BP.'/var/log/_debbug_log_saleslayer_'.date('Y-m-d').'.dat', "$str\r\n", FILE_APPEND);

    // }

}