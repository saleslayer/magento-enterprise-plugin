<?php
/**
 * Synccatalog Resource Collection
 */
namespace Saleslayer\Synccatalog\Model\ResourceModel\Synccatalog;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * Resource initialization
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('Saleslayer\Synccatalog\Model\Synccatalog', 'Saleslayer\Synccatalog\Model\ResourceModel\Synccatalog');
    }
}
