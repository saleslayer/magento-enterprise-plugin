<?php
/**
 * Synccatalog Debuger helper
 */
namespace Saleslayer\Synccatalog\Helper;

use Saleslayer\Synccatalog\Helper\Config as synccatalogConfigHelper;

class slDebuger extends \Magento\Framework\App\Helper\AbstractHelper
{

    protected $directoryListFilesystem;
    protected $synccatalogConfigHelper;
    
    protected $sl_DEBBUG                = 0;
    protected $sl_logs_folder_checked   = false;
    protected $sl_logs_path;
    protected $sl_time_ini_process;

    /**
     * Debuger constructor.
     *
     * @param \Magento\Framework\App\Helper\Context       $context
     * @param \Magento\Framework\Filesystem\DirectoryList $directoryListFilesystem
     * @param \Saleslayer\Synccatalog\Helper\Config       $synccatalogConfigHelper
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\Filesystem\DirectoryList $directoryListFilesystem,
        synccatalogConfigHelper $synccatalogConfigHelper
    ) {
        parent::__construct($context);
        $this->directoryListFilesystem = $directoryListFilesystem;
        $this->synccatalogConfigHelper = $synccatalogConfigHelper;
        $this->checkSLLogsFolder();
        $this->loadDebugerParameters();
        $this->sl_time_ini_process = microtime(1);

    }

    /**
     * Function to load Debuger parameters
     *
     * @return void
     */
    protected function loadDebugerParameters()
    {

        $this->sl_DEBBUG = $this->synccatalogConfigHelper->getDebugerLevel();

    }

    /**
     * Function to validate if SL Logs Folder exists, if not, create it
     *
     * @return void
     */
    protected function checkSLLogsFolder()
    {

        if (!$this->sl_logs_folder_checked) {

            $this->sl_logs_path = $this->directoryListFilesystem->getPath('log').'/sl_logs/';

            if (!file_exists($this->sl_logs_path)) {
                
                mkdir($this->sl_logs_path, 0777, true);
            
            }

            $this->sl_logs_folder_checked = true;

        }

    }

    /**
     * Function to debbug into a Sales Layer log.
     *
     * @param  string $msg     message to save
     * @param  string $type    type of message to save
     * @param  int    $seconds seconds for timer debbug
     * @return void
     */
    public function debug($msg, $type = '', $seconds = null)
    {
        
        if ($this->sl_DEBBUG > 0) {

            $error_write = false;
            if (strpos($msg, '## Error.') !== false) {
                $error_write = true;
                $error_file = $this->sl_logs_path.'_error_debbug_log_saleslayer_'.date('Y-m-d').'.dat';
            }

            switch ($type) {
            case 'timer':
                $file = $this->sl_logs_path.'_debbug_log_saleslayer_timers_'.date('Y-m-d').'.dat';

                if (null !== $seconds) {

                    $msg .= $seconds.' seconds.';

                }else{

                    $msg = 'ERROR - NULL SECONDS on timer debug!!! - '.$msg;

                }

                break;

            case 'autosync':
                $file = $this->sl_logs_path.'_debbug_log_saleslayer_auto_sync_'.date('Y-m-d').'.dat';
                break;

            case 'syncdata':
                $file = $this->sl_logs_path.'_debbug_log_saleslayer_sync_data_'.date('Y-m-d').'.dat';
                break;

            default:
                $file = $this->sl_logs_path.'_debbug_log_saleslayer_'.date('Y-m-d').'.dat';
                break;
            }

            $new_file = false;
            if (!file_exists($file)) { $new_file = true; 
            }

            if ($this->sl_DEBBUG > 1) {

                $mem = sprintf("%05.2f", (memory_get_usage(true)/1024)/1024);

                $pid = getmypid();

                $time_end_process = round(microtime(true) - $this->sl_time_ini_process);

                $srv = 'NonEx';

                if (function_exists('sys_getloadavg')) {
                    
                    $srv = sys_getloadavg();
                    
                    if (is_array($srv) && isset($srv[0])) {

                        $srv = $srv[0];

                    }
                    
                }
               
                $msg = "pid:{$pid} - mem:{$mem} - time:{$time_end_process} - srv:{$srv} - $msg";
            
            }

            file_put_contents($file, "$msg\r\n", FILE_APPEND);

            if ($new_file) { chmod($file, 0777); 
            }

            if ($error_write) {

                $new_error_file = false;
                
                if (!file_exists($error_file)) { $new_error_file = true; 
                }

                file_put_contents($error_file, "$msg\r\n", FILE_APPEND);

                if ($new_error_file) { chmod($error_file, 0777); 
                }

            }

        }
    
    }  

}
