<?php
/**
* PDX-License-Identifier: GPL-2.0-or-later
*
* @author      Stephan Leemburg <stephan@it-functions.nl>
* @copyright   Copyright (c) 2020, IT Functions
*/

require_once __DIR__.'/hilink.php';

abstract class Hlbase
{
    protected $stopRunning      = FALSE; // volatile
    protected $debug            = FALSE;
    protected $hl               = NULL;
    protected $timeout          = 10; // seconds
    protected $config           = [];
    protected $lastPhonenumber  = '';
    protected $storage          = __DIR__.'/../sms/';

    public function __construct($conffile='hlconf.php', $host='192.168.8.1')
    {
        if (PHP_SAPI !== 'cli' && !defined('STDIN'))
            throw new RuntimeException('CLI Only');
        /*
        if (self::already_running())
            throw new RuntimeException('I am already instantiated..');

         */
        $conffile = __DIR__.'/../config/'.$conffile;
        if (file_exists($conffile))
        {
            require_once $conffile;
            $this->config =& $config;
        }
        if ($host === NULL && array_key_exists('host', $this->config))
            $host = $this->config['host'];

        if (($this->config['timeout'] ?? NULL) !== NULL)
            $this->timeout = $this->config['timeout'];

        if (($this->config['storage'] ?? '') != '')
            $this->storage = $this->config['storage'];

        if (!is_dir($this->storage) && !mkdir($this->storage, 02775, TRUE))
            throw new RuntimeException('Cannot create '.$this->storage);

        $this->hl = new Hilink($options['modem'] ?? 'DEFAULT');
        $this->hl->setDomain($host);

        if (getenv("CURL_DEBUG") !== FALSE)
            $this->hl->setDebugCurl(TRUE);

        if (($this->config['debug'] ?? NULL) !== NULL)
            $this->setDebug($this->config['debug']);
    
        $this->hl->getSesInfo();

        openlog($this->config['logname'] ?? 'hlmon', LOG_NDELAY|LOG_PID, LOG_DAEMON);
    }

    abstract public function run();

    public function sendSMS($recipient, $msg)
    {
        if (($msg = trim($msg)) == '')
        {
            $this->error('Empty message, will not send');
            return FALSE;
        }

        // verify recipient XXX regex may be not correct for all countries
        // but it is for Dutch phone numbers ;-)
        $recipient = trim($recipient);
        if (!preg_match('/^\+[1-9][0-9][1-9][0-9]{8}$/', $recipient))
        {
            $this->error('Invalid phonenumber: ('.$recipient.')');
            return FALSE;
        }
        
        return $this->hl->sendSMS($recipient, $msg);
    }

    public function setDebug($enable=TRUE)
    {
        $this->hl->setDebug(($this->debug = $enable));
    }

    protected function saveSMS($msg)
    {
        $dir = $this->storage.'/'.date('Ymd');
        if (!is_dir($dir) && !mkdir($dir, 02775, TRUE))
        {
            $this->error("Cannot create directory {$dir}");
            $dir = $this->storage;
        }
        
        $name = date('U').'-'.$msg['Index'].'.json';
        $this->log("store sms {$msg['Index']} as {$name} in {$dir}");
        
        if (($fh = fopen($dir.'/'.$name, 'a+')) === FALSE)
        {
            $this->error("Cannot open file {$name} in {$dir}");
            return;
        }
        $data = json_encode($msg, JSON_PRETTY_PRINT).PHP_EOL;
        fwrite($fh, $data);
        fclose($fh);
    }

    protected function deleteSMS($seq)
    {
        if (($this->config['nondestructive'] ?? FALSE) !== TRUE)
        {
            $this->dbg('Removing message '.$seq);
            $this->hl->deleteSMS($seq);
        }
    }

    protected function error($msg)
    {
        $this->log($msg, LOG_ALERT);
    }

    protected function log($msg, $prio=LOG_NOTICE)
    {
        if ($this->debug)
            echo $msg.PHP_EOL;

        syslog($prio, $msg);
    }

    protected function dbg($msg)
    {
        if ($this->debug)
            echo "$msg".PHP_EOL;
    }
}
