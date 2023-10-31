<?php

namespace Saleslayer\Synccatalog\Controller\Adminhtml\Ajax;
use Magento\Framework\Controller\ResultFactory;

class Deletelogs extends \Magento\Framework\App\Action\Action
{
    protected $modelo;
    protected $jsonHelper;
    protected $resultFactory;
    /**
     * @var \Magento\Framework\UrlInterface
     */

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\Framework\View\Result\PageFactory $resultFactory,
        \Saleslayer\Synccatalog\Model\Synccatalog $modelo
    ) {
        $this->jsonHelper = $jsonHelper;
        $this->resultFactory = $resultFactory;
        parent::__construct($context);
        $this->modelo = $modelo;
    }

    public function execute()
    {

        $files_to_delete = $this->getRequest()->getParam('logfilesfordelete');

        /** @var \Magento\Framework\Controller\Result\Raw $response */
        $response = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        
        $result = $this->modelo->deleteSLLogFile($files_to_delete);

        $array_return = array();
        
        if ($result){

            $array_return['message_type'] = 'success';
        
        }else{
        
            $array_return['message_type'] = 'error';
        
        }

        $response->setContents($this->jsonHelper->jsonEncode($array_return));

        return $response;

    }

}