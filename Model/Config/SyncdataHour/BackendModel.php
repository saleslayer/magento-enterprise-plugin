<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Saleslayer\Synccatalog\Model\Config\SyncdataHour;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

/**
 * Backend model for the Syncdata hour option field. Validates integer value.
 * @api
 * @since 100.1.0
 */
class BackendModel extends Value
{
    /** Maximum hour*/
    const MAX_LIMIT = 23;

    /** Minimum hour */
    const MIN_LIMIT = 0;

    public function beforeSave()
    {
        $value = (int) $this->getValue();
        if ($value > self::MAX_LIMIT || $value < self::MIN_LIMIT) {
            throw new LocalizedException(
                __('Hour value must be between 0 and 23.')
            );
        }
        return parent::beforeSave();
    }
}