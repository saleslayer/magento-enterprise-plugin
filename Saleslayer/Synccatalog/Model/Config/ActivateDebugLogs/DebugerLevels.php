<?php

namespace Saleslayer\Synccatalog\Model\Config\ActivateDebugLogs;

class DebugerLevels implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Retrieve Load method Option array
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => \Saleslayer\Synccatalog\Helper\Config::ADL_DEBUGER_LEVEL_DISABLED, 'label' => __('Disabled')],
            ['value' => \Saleslayer\Synccatalog\Helper\Config::ADL_DEBUGER_LEVEL_ENABLED, 'label' => __('Enabled')]
        ];
    }
}