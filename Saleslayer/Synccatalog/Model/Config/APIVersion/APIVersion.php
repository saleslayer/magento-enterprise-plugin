<?php

namespace Saleslayer\Synccatalog\Model\Config\APIVersion;

class APIVersion implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Retrieve Load method Option array
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => \Saleslayer\Synccatalog\Helper\Config::AV_117, 'label' => __('1.17')],
            ['value' => \Saleslayer\Synccatalog\Helper\Config::AV_118, 'label' => __('1.18')]
        ];
    }
}