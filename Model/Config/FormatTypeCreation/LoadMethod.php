<?php

namespace Saleslayer\Synccatalog\Model\Config\FormatTypeCreation;

class LoadMethod implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Retrieve Load method Option array
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE, 'label' => __('Simple')],
            ['value' => \Magento\Catalog\Model\Product\Type::TYPE_VIRTUAL, 'label' => __('Virtual')]
        ];
    }
}