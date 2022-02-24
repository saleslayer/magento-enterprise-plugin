<?php

namespace Saleslayer\Synccatalog\Model\Config\ActivateDebugLogs;

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
            ['value' => \Saleslayer\Synccatalog\Helper\Config::ADL_LOAD_METHOD_DISABLED, 'label' => __('Disabled')],
            ['value' => \Saleslayer\Synccatalog\Helper\Config::ADL_LOAD_METHOD_ENABLED, 'label' => __('Enabled')]
        ];
    }
}