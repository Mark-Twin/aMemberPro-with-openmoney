<?php
/**
 * Class represents records from table error_log
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
class ErrorLog extends Am_Record
{
}

class ErrorLogTable extends Am_Table
{
    protected $_key = 'log_id';
    protected $_table = '?_error_log';
    protected $_recordClass = 'ErrorLog';

    function clearOld($date)
    {
        $this->_db->query("DELETE FROM ?_error_log
            WHERE `time` < ? ", $date . ' 00:00:00');
    }

    static function backtraceToString(array $trace)
    {
        $out = "";
        foreach ($trace as $t) {
            if (isset($t['class'])) {
                $out .= $t['class'] . $t['type'] . $t['function'];
            } else {
                $out .= $t['function'];
            }
            if (!empty($t['file'])) {
                $file = str_replace(@ROOT_DIR . DIRECTORY_SEPARATOR, '', $t['file']);
                $out .= " [ $file";
                if (!empty($t['line']))
                    $out .= " : {$t['line']}";
                $out .= " ]";
            }
            $out .= "\n";
        }
        return $out;
    }

    function logException($e)
    {
        $error = $e->getMessage();
        if (defined('AM_TEST') && AM_TEST)
            return false;
        $values = array(
            'time' => $this->getDi()->sqlDateTime,
            'user_id' => $this->getDi()->auth->getUserId(),
            'remote_addr' => @$_SERVER['REMOTE_ADDR'],
            'url' => htmlentities(@$_SERVER['REQUEST_URI']),
            'referrer' => htmlentities(@$_SERVER['HTTP_REFERER']),
            'error' => $error,
            'trace' => "Exception " . get_class($e) . PHP_EOL . self::backtraceToString($e->getTrace())
        );
        if ($e instanceof Am_Exception_Db) {
            @$this->insert($values, false);
        } else {
            $this->insert($values, false);
        }
    }

    /**
     * Log a message
     * @param string $error
     */
    function log($error)
    {
        if ($error instanceof Exception)
            return $this->logException($error);
        if (defined('AM_TEST') && AM_TEST)
            return false;
        if (!$this->getDi()->getService('db') || !@$this->getDi()->db || !$this->getDi()->db->_isConnected())
            return;
        $trace = debug_backtrace(false);
        array_shift($trace); // remove ErrorLog->log entry
        array_shift($trace); // remove ErrorLog->log entry
        try { // necessary to avoid errors if init is not finished
            $user_id = $this->getDi()->auth->getUserId();
        } catch (Exception $e) {
            $user_id = null;
        }
        $values = array(
            'time' => $this->getDi()->sqlDateTime,
            'user_id' => $user_id,
            'remote_addr' => @$_SERVER['REMOTE_ADDR'],
            'url' => htmlentities(@$_SERVER['REQUEST_URI']),
            'referrer' => htmlentities(@$_SERVER['HTTP_REFERER']),
            'error' => $error,
            'trace' => self::backtraceToString($trace)
        );
        $this->insert($values, false);
    }
}