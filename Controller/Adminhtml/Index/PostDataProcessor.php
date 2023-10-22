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
        $inputFilter = new FilterInput(
            ['last_update' => $this->dateFilter],
            [],
            $data
        );
        $data = $inputFilter->getUnescaped();
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
