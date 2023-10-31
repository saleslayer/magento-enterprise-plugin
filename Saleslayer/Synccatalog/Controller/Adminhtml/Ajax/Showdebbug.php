<?php

namespace Saleslayer\Synccatalog\Controller\Adminhtml\Ajax;
use Magento\Framework\Controller\ResultFactory;

class Showdebbug extends \Magento\Framework\App\Action\Action
{
    protected $jsonHelper;
    protected $resultFactory;
    protected $modelo;
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

    // protected function _isAllowed()
    // {
    //     return $this->_authorization->isAllowed('Saleslayer_Synccatalog::showdebbug');
    // }

    public function execute(){

       $command = $this->getRequest()->getParam('logcommand');

       /** @var \Magento\Framework\Controller\Result\Raw $response */
       $response = $this->resultFactory->create(ResultFactory::TYPE_RAW);
       $array_return = array();

       switch ($command){
           case 'showlogfiles':
               $response_function = $this->modelo->checkFilesLogs();
               $response->setHeader('Content-type', 'text/plain');
               break;
           case 'showservertime':
               $response_function[0] = 1;
               $response_function[1] = 'Current server time: '.date('H:i');
               $response_function['seconds'] = date('s');
               $response_function['function'] = 'showservertime';
               $response->setHeader('Content-type', 'text/plain');
               break;
           default:
               $response_function = $this->modelo->showContentFile($command);
               $response->setHeader('Content-type', 'text/plain');
               break;
       }

       if ($response_function[0] == 1){
           $array_return['message_type'] = 'success';
           $array_return['function'] = $response_function['function'];
           $array_return['content'] = $response_function[1];
           if($command == 'showservertime'  ) {
               $array_return['seconds'] = $response_function['seconds'];
           }
           if($command != 'showlogfiles' && $command != 'showservertime' ){

               if($response_function[2] >= 1 ){
                   $array_return['lines'] = $response_function[2];
                   $array_return['warnings'] = $response_function[3];
                   $array_return['errors'] = $response_function[4];
               }

           }
       }else{
           $array_return['message_type'] = 'error';
           $array_return['function'] = $response_function['function'];
	       $array_return['content']  = (isset($response_function[1])?$response_function[1]:'');
	       $array_return['lines']    = (isset($response_function['info'])?$response_function['info']:0);
	       $array_return['warnings'] = (isset($response_function['warnings'])?$response_function['warnings']:0);
	       $array_return['errors']   = (isset($response_function['errors'])?$response_function['errors']:0);
       }

       $response->setContents($this->jsonHelper->jsonEncode( $array_return));

       return $response;

   }

}