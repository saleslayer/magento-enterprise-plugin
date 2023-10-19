<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Saleslayer\Synccatalog\Model\Config\SqlInsertLimit;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

/**
 * Backend model for the Sql insert limit option field. Validates integer value.
 * @api
 * @since 100.1.0
 */
class BackendModel extends Value
{
    /** Maximum sql rows*/
    const MAX_LIMIT = 20;

    /** Minimum sql rows */
    const MIN_LIMIT = 1;

    public function beforeSave()
    {
        $value = (int) $this->getValue();
        if ($value > self::MAX_LIMIT) {
            throw new LocalizedException(
                __('SQL insert limit must be less than or equal to 20 rows.')
            );
        } elseif ($value < self::MIN_LIMIT) {
            throw new LocalizedException(
                __('SQL insert limit must be greater than or equal to 1 row')
            );
        }
        return parent::beforeSave();
    }
}