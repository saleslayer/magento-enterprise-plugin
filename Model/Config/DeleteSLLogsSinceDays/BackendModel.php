<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Saleslayer\Synccatalog\Model\Config\DeleteSLLogsSinceDays;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

/**
 * Backend model for the Syncdata delete logs since X days option field. Validates integer value.
 * @api
 * @since 100.1.0
 */
class BackendModel extends Value
{
    /** Maximum days*/
    const MAX_LIMIT = 31;

    /** Minimum days */
    const MIN_LIMIT = 0;

    public function beforeSave()
    {
        $value = (int) $this->getValue();
        if ($value > self::MAX_LIMIT || $value < self::MIN_LIMIT) {
            throw new LocalizedException(
                __('Days value must be between 0 and 31.')
            );
        }
        return parent::beforeSave();
    }
}