<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Lets CodeIgniter's own log_message() write to the process's standard error
 * instead of a file when LOG_CHANNEL=stderr is set in .env. Containers and
 * most hosting platforms collect stderr and ship it to one place, which is the
 * only sane way to read the logs of several app servers; files on each server
 * are not. Without the setting nothing changes.
 */
class MY_Log extends CI_Log
{
    public function write_log($level, $msg)
    {
        if (strtolower((string) getenv('LOG_CHANNEL')) !== 'stderr') {
            return parent::write_log($level, $msg);
        }

        $level = strtoupper($level);
        if ((!isset($this->_levels[$level]) || ($this->_levels[$level] > $this->_threshold))
            && !isset($this->_threshold_array[$this->_levels[$level]])) {
            return false;
        }

        $line = $level.' - '.date($this->_date_fmt).' --> '.$msg.PHP_EOL;
        $stderr = @fopen('php://stderr', 'wb');
        if ($stderr === false) {
            return false;
        }
        fwrite($stderr, $line);
        fclose($stderr);

        return true;
    }
}
