<?php
/**
 * Synccatalog Module helper
 */
namespace Saleslayer\Synccatalog\Helper;

use \Magento\Framework\Module\ModuleResource as moduleResource;

class slModule extends \Magento\Framework\App\Helper\AbstractHelper
{

    protected $moduleResource;

    /**
     * Module constructor.
     *
     * @param \Magento\Framework\App\Helper\Context        $context
     * @param \Magento\Framework\Module\ModuleResource $moduleResource
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        ModuleResource $moduleResource
    ) {
        parent::__construct($context);
        $this->moduleResource = $moduleResource;

    }

    /**
     * Function to get module name
     *
     * @return string|boolean       module name, if not, false
     */
    public function getModuleName()
    {

        $registrationFile = __DIR__ . '/../registration.php';

        if (file_exists($registrationFile)) {
            
            $content = file_get_contents($registrationFile);
            
            if (preg_match("/ComponentRegistrar::MODULE,\s*'([^']+)'\s*,/", $content, $matches)) {
            
                if (isset($matches[1])) return $matches[1];
            
            }

        }

        return false;

    }

    /**
     * Function to get module version
     *
     * @return string       module version, if not, undefined
     */
    public function getModuleVersion()
    {

        $moduleName = $this->getModuleName();

        if ($moduleName){
            
            $moduleVersion = $this->moduleResource->getDataVersion($moduleName);
            
            if ($moduleVersion) return $moduleVersion;

        }

        return 'undefined';
        
    }

}
