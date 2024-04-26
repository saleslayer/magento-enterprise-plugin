<?php

namespace Saleslayer\Synccatalog\Block\Adminhtml\Renderer;

use Magento\Backend\Block\Widget\Grid\Column\Renderer\AbstractRenderer;

class CustomGridText extends AbstractRenderer
{
    public function render(\Magento\Framework\DataObject $row)
    {
        if (($row_data = $row->getData($this->getColumn()->getIndex())) != null){
            return nl2br($row_data);
        }
        return '';
    }
}
