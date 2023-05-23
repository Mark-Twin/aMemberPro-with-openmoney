<?php
/**
 * Class represents records from table debug_log
 * {autogenerated}
 * @property int $log_id
 * @property int $user_id
 * @property datetime $time
 * @property string $url
 * @property string $remote_addr
 * @property string $referrer
 * @property string $error
 * @property string $trace
 * @see Am_Table
 */
class DebugLog extends ErrorLog
{
}

class DebugLogTable extends ErrorLogTable
{
    protected $_key = 'log_id';
    protected $_table = '?_debug_log';
    protected $_recordClass = 'DebugLog';
    
    function clearOld($date)
    {
        $this->_db->query("DELETE FROM ?_debug_log
            WHERE `time` < ? ", $date . ' 00:00:00');
    }
    
}

