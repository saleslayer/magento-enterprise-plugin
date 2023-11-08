<?php

namespace Saleslayer\Synccatalog\Controller\Adminhtml\Ajax;
use Magento\Framework\Controller\ResultFactory;

class Syncajaxcommands extends \Magento\Framework\App\Action\Action
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

        $command = $this->getRequest()->getParam('command');
        // $permited_command = array('unlinkelements','infopreload','removelogs','removeindexes','deleteregs','deleteunusedimages');
        $permited_command = array('removelogs','removeindexes','deleteregs','deleteunusedimages');
        /** @var \Magento\Framework\Controller\Result\Raw $response */
        $response = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $array_return = array();

        if(in_array($command, $permited_command)){

            $return_message = '';

            switch ($command){
                // case 'unlinkelements':
                //     $this->modelo->unlinkOldItems();
                //     $response->setHeader('Content-type', 'text/plain');
                //     $result_update = true;
                //     break;
                // case 'infopreload':
                //     $this->modelo->loadMulticonnItems();
                //     $response->setHeader('Content-type', 'text/plain');
                //     $result_update = true;
                //     break;
                case 'removelogs':
                    $this->modelo->deleteSLLogs();
                    $response->setHeader('Content-type', 'text/plain');
                    $result_update = true;
                    break;
                case 'removeindexes':
                    $this->modelo->deleteSLIndexes();
                    $response->setHeader('Content-type', 'text/plain');
                    $result_update = true;
                    break;
                case 'deleteregs':
                    $this->modelo->deleteSLRegs();
                    $response->setHeader('Content-type', 'text/plain');
                    $result_update = true;
                    break;
                case 'deleteunusedimages':
                    $return_message = $this->modelo->deleteUnusedImages();
                    $response->setHeader('Content-type', 'text/plain');
                    $result_update = true;
                    break;
                default:
                $result_update = false;
                     break;
            }

            $field_names = array('unlinkelements'       => 'Old elements unlink',
                                 'infopreload'          => 'Multi-connector information load',
                                 'removelogs'           => 'Sales Layer logs delete',
                                 'removeindexes'        => 'Sales Layer indexes delete',
                                 'deleteregs'           => 'Sales Layer regs delete',
                                 'deleteunusedimages'   => 'Delete unused Images'
                                 );
            $message =  (isset($field_names[$command])) ? $field_names[$command] : $command;

            if ($result_update){
                $array_return['message_type'] = 'success';
                $array_return['message'] = $message .' executed successfully.';
            }else{
                $array_return['message_type'] = 'error';
                $array_return['message'] = 'Error executing '.strtolower($message);
            }

            if ($return_message !== '') $array_return['message'] .= $return_message;

        }else{
            $array_return['message_type'] = 'error';
            $array_return['message'] = 'It is not allowed to run this command.';
        }

        $response->setContents($this->jsonHelper->jsonEncode( $array_return));

        return $response;

    }
    
}