<?php

namespace Saleslayer\Synccatalog\Controller\Adminhtml\Index;

use Magento\Framework\Filter\FilterInput;

class PostDataProcessor
{
    /**
     * @var \Magento\Framework\Stdlib\DateTime\Filter\Date
     */
    protected $dateFilter;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

    /**
     * @param \Magento\Framework\Stdlib\DateTime\Filter\Date $dateFilter
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     */
    public function __construct(
        \Magento\Framework\Stdlib\DateTime\Filter\Date $dateFilter,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        $this->dateFilter = $dateFilter;
        $this->messageManager = $messageManager;
    }

    /**
     * Filtering posted data. Converting localized data if needed
     *
     * @param array $data
     * @return array
     */
    public function filter($data)
    {
        if(class_exists('\Magento\Framework\Filter\FilterInput')){
            $inputFilter = new \Magento\Framework\Filter\FilterInput(
                ['last_update' => $this->dateFilter],
                [],
                $data
            );
        } elseif(class_exists('\Zend_Filter_Input')) {
            $inputFilter = new \Zend_Filter_Input(
                ['last_update' => $this->dateFilter],
                [],
                $data
            );
        } else {
            $inputFilter = false;
        }
        $data = $inputFilter !== false ? $inputFilter->getUnescaped() : false;
        return $data;
    }

    /**
     * Validate post data
     *
     * @param array $data
     * @return bool     Return FALSE if someone item is invalid
     */
    public function validate($data)
    {
        $errorNo = true;
        return $errorNo;
    }
}
