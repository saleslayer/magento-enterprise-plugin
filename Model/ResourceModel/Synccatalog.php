<?php
namespace Saleslayer\Synccatalog\Model\ResourceModel;

/**
 * Synccatalog Resource Model
 */
class Synccatalog extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('saleslayer_synccatalog_apiconfig', 'id');
    }
}
