<?php

namespace Saleslayer\Synccatalog\Model\Config\PaginationNItems;

class ItemNumber implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Retrieve Load method Option array
     *
     * @return array
     */
    public function toOptionArray()
    {

        $pagination_numbers = [];
        
        $base_10k = 2;
        for ($n_item = 0; $n_item < 20; $n_item++){
            if ($n_item == 0){
                $pagination_numbers[] = ['value' => 500, 'label' => '500'];
            }else if ($n_item <= 10){
                $pagination_numbers[] = ['value' => $n_item * 1000, 'label' => strval($n_item * 1000)];
            }else{;
                $pagination_numbers[] = ['value' => $base_10k * 10000, 'label' => strval($base_10k * 10000)];
                $base_10k++;
            }
            
        }

        return $pagination_numbers;
        
    }

}