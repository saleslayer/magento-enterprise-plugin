<?php

namespace Saleslayer\Synccatalog\Helper;

use \Magento\Framework\App\Helper\AbstractHelper;

class Config extends AbstractHelper
{
    /** Config params */
    const CONFIG_SYNCCATALOG_GENERAL_ACTIVATE_DEBUG_LOGS  = 'synccatalog/general/activate_debug_logs';
    const CONFIG_SYNCCATALOG_GENERAL_MANAGE_INDEXERS = 'synccatalog/general/manage_indexers';
    const CONFIG_SYNCCATALOG_GENERAL_SQL_TO_INSERT_LIMIT  = 'synccatalog/general/sql_to_insert_limit';
    const CONFIG_SYNCCATALOG_GENERAL_AVOID_IMAGES_UPDATES = 'synccatalog/general/avoid_images_updates';
    const CONFIG_SYNCCATALOG_GENERAL_SYNC_DATA_HOUR_FROM = 'synccatalog/general/sync_data_hour_from';
    const CONFIG_SYNCCATALOG_GENERAL_SYNC_DATA_HOUR_UNTIL = 'synccatalog/general/sync_data_hour_until';
    const CONFIG_SYNCCATALOG_GENERAL_FORMAT_TYPE_CREATION  = 'synccatalog/general/format_type_creation';
    const CONFIG_SYNCCATALOG_GENERAL_DELETE_SL_LOGS_SINCE_DAYS = 'synccatalog/general/delete_sl_logs_since_days';

    /**
     * Activate debug logs debuger levels
     * 0 and 1 will be only visible at the moment.
     */
    const ADL_DEBUGER_LEVEL_DISABLED = 0;
    const ADL_DEBUGER_LEVEL_ENABLED = 1;
    const ADL_DEBUGER_LEVEL_CONF_FIELDS = 2;
    const ADL_DEBUGER_LEVEL_ALL_DATA = 3;

    /**
     * Retrieve debuger level option
     * @return int
     */
    public function getDebugerLevel(){

        return (int) $this->scopeConfig->getValue(self::CONFIG_SYNCCATALOG_GENERAL_ACTIVATE_DEBUG_LOGS);
    
    }

    /**
     * Retrieve manage indexers option
     * @return int
     */
    public function getManageIndexers(){

        return (int) $this->scopeConfig->getValue(self::CONFIG_SYNCCATALOG_GENERAL_MANAGE_INDEXERS);
    
    }

    /**
     * Retrieve SQL to insert limit option
     * @return int
     */
    public function getSqlToInsertLimit(){

        return (int) $this->scopeConfig->getValue(self::CONFIG_SYNCCATALOG_GENERAL_SQL_TO_INSERT_LIMIT);
    
    }

    /**
     * Retrieve avoid images updates option
     * @return int
     */
    public function getAvoidImagesUpdates(){

        return (int) $this->scopeConfig->getValue(self::CONFIG_SYNCCATALOG_GENERAL_AVOID_IMAGES_UPDATES);
    
    }

    /**
     * Retrieve sync data hour from option
     * @return int
     */
    public function getSyncDataHourFrom(){

        return (int) $this->scopeConfig->getValue(self::CONFIG_SYNCCATALOG_GENERAL_SYNC_DATA_HOUR_FROM);
    
    }

    /**
     * Retrieve sync data hour until option
     * @return int
     */
    public function getSyncDataHourUntil(){

        return (int) $this->scopeConfig->getValue(self::CONFIG_SYNCCATALOG_GENERAL_SYNC_DATA_HOUR_UNTIL);
    
    }

    /**
     * Retrieve format type creation option
     * @return string
     */
    public function getFormatTypeCreation(){

        return (string) $this->scopeConfig->getValue(self::CONFIG_SYNCCATALOG_GENERAL_FORMAT_TYPE_CREATION);
    
    }

    /**
     * Retrieve delete SL logs since days option
     * @return int
     */
    public function getDeleteSLLogsSinceDays(){

        return (int) $this->scopeConfig->getValue(self::CONFIG_SYNCCATALOG_GENERAL_DELETE_SL_LOGS_SINCE_DAYS);
    
    }

}