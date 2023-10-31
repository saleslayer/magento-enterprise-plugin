<?php

namespace Saleslayer\Synccatalog\Controller\Adminhtml\Ajax;
use Magento\Framework\Controller\ResultFactory;

class Syncstatuscommands extends \Magento\Framework\App\Action\Action
{
    protected $modelo;
    protected $jsonHelper;
    protected $resultFactory;
    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $resultFactory;

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

        // $file = BP.'/var/log/sl_logs/_debbug_log_saleslayer_test.txt';
        // file_put_contents($file, "entramos en execute de syncdata!\r\n", FILE_APPEND);

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resultJsonFactory = $objectManager->create('Magento\Framework\Controller\Result\JsonFactory');
        $result = $resultJsonFactory->create();
        
        if ($this->getRequest()->getParam('isAjax')) {
            $test = [
                'Firstname' => 'What is your firstname',
                'Email' => 'What is your emailId',
                'Lastname' => 'What is your lastname',
                'Country' => 'Your Country'
            ];
            
            return $result->setData($test);
        }

        // $command = $this->getRequest()->getParam('command');
        // $permited_command = array('unlinkelements','infopreload','removelogs');
        /** @var \Magento\Framework\Controller\Result\Raw $response */
        // $response = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        // $array_return = array();

        // // if(in_array($command, $permited_command)){

        // //     switch ($command){
        // //         case 'unlinkelements':
        // //             $this->modelo->unlinkOldItems();
        // //             $response->setHeader('Content-type', 'text/plain');
        // //             $result_update = true;
        // //             break;
        // //         case 'infopreload':
        // //             $this->modelo->loadMulticonnItems();
        // //             $response->setHeader('Content-type', 'text/plain');
        // //             $result_update = true;
        // //             break;
        // //         case 'removelogs':
        // //             $this->modelo->deleteSLLogs();
        // //             $response->setHeader('Content-type', 'text/plain');
        // //             $result_update = true;
        // //             break;
        // //         default:
        // //         $result_update = false;
        // //              break;
        // //     }

        // //     $field_names = array('unlinkelements'     => 'Old elements unlink',
        // //                          'infopreload'        => 'Multi-connector information load',
        // //                          'removelogs'       => 'Sales Layer logs delete'
        // //                          );
        // //     $message =  (isset($field_names[$command])) ? $field_names[$command] : $command;

        // //     if ($result_update){
        // //         $array_return['message_type'] = 'success';
        // //         $array_return['message'] = $message .' executed successfully.';
        // //     }else{
        // //         $array_return['message_type'] = 'error';
        // //         $array_return['message'] = 'Error executed '.strtolower($message);
        // //     }

        // // }else{
        // //     $array_return['message_type'] = 'error';
        // //     $array_return['message'] = 'It is not allowed to run this command.';
        // // }

        // $array_return['message'] = 'yey :)';

        // file_put_contents($file, "devolvemos array_return: ".print_R($array_return)."\r\n", FILE_APPEND);

        // $response->setContents($this->jsonHelper->jsonEncode($array_return));

        // return $response;

    }
    
}