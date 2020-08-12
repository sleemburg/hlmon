<?php
/**
* PDX-License-Identifier: GPL-2.0-or-later
*
* @author      Stephan Leemburg <stephan@it-functions.nl>
* @copyright   Copyright (c) 2020, IT Functions
*/

require_once __DIR__.'/hilink.php';
require_once __DIR__.'/hlbase.php';

class Hlmon extends Hlbase
{
    private $state          = self::STATE_VOID;
    private $lastCommand    = '';
    private $process        = NULL;
    private $pipes          = [];
    private $route_ret      = 0;

    const STATE_VOID        = 0;
    const STATE_IDLE        = 1;
    const STATE_CONNECTED   = 2;

    const ACT_CONNECT       = 1;
    const ACT_DISCONNECT    = 2;
    const ACT_STATUS        = 3;
    const ACT_NOOP          = 4;
    const ACT_IGNORE        = 5;
    const ACT_RESEND        = 6;

    const STR_CONNECT       = 'connect';
    const STR_DISCONNECT    = 'disconnect';
    const STR_STATUS        = 'status';
    const STR_SMS           = 'sms';

    const cmdmap = [
         self::STR_CONNECT      => self::ACT_CONNECT
        ,self::STR_DISCONNECT   => self::ACT_DISCONNECT
        ,self::STR_STATUS       => self::ACT_STATUS
    ];

    public function __construct($host='192.168.8.1')
    {
        parent::__construct(strtolower(basename(__FILE__)), $host);
    
        $this->setState(self::STATE_IDLE);
    }

    public function run()
    {
        while (!$this->stopRunning)
        {
            if ($this->state == self::STATE_CONNECTED
            && ($ret = $this->checkCommand() !== TRUE))
            {
                $this->dbg('Command stopped with exitcode: '.$ret);
                $this->startCommand();
            }

            if (($count = $this->hl->smsCount()) < 1)
            {
                $this->dbg('No sms ('.$this->hl->smsCount()
                    .') sleeping for '.$this->timeout.' seconds');

                sleep($this->timeout);
                continue;
            }

            $this->dbg('Found '.$count.' sms messages');

            if (($data = $this->hl->smsList($count)) === FALSE
            || !is_array($data))
            {
                $this->error('no smsList data');
                continue;
            }

            $i = $data['Count'];
            $msg =& $data['Messages']['Message'];

            while (--$i >= 0)
            {
                $action = $this->handleSMS($i, $count > 1 ? $msg[$i] : $msg);

                $this->handleRequest($action);
            }
            sleep($this->timeout);
        }
    }

    protected function handleSMS($seq, $msg)
    {
        $action = $this->determineAction($seq, $msg);
        if ($action == self::ACT_IGNORE)
            $this->log('Ignoring message '.$msg['Index']);

        if (($msg['Phone'] ?? NULL) !== NULL)
            $this->lastPhonenumber = $msg['Phone'];

        if (($msg['Content'] ?? NULL) !== NULL)
            $this->lastCommand = $msg['Content'];

        // msg can be enriched with processing data
        $this->saveSMS($msg);

        $this->deleteSMS($msg['Index']);

        return $action;
    }

    protected function handleRequest($action)
    {
        $state = $this->getState();

        switch ($action)
        {
        case self::ACT_IGNORE:
            break;

        case self::ACT_CONNECT:
            if ($state == self::STATE_CONNECTED)
            {
                $this->log('Already connected, ignoring request.');
                break;
            }
            $this->connect();
            break;

        case self::ACT_DISCONNECT:
            if ($state != self::STATE_CONNECTED)
            {
                $this->log('Already disconnected, ignoring request.');
                break;
            }
            $this->disconnect();
            break;

        case self::ACT_STATUS:
            ; // ok
            break;
        }
        $this->dbg('Prev state: '.$state.', current state: '.$this->getState());
    }

    protected function determineAction($seq, &$msg)
    {
        $errors = [];
        $required = ['Index', 'Phone', 'Date', 'Content'];
        foreach ($required as $key)
            if (!array_key_exists($key, $msg))
                $errors[] = 'Missing key: '.$key;
        
        if (count($errors) > 0)
        {
            $msg['X-Errors'] = $errors;
            $this->log('Errors found, ignoring: ' .print_r($errors, true));
            return self::ACT_IGNORE;
        }

        foreach ($this->config['phonebook'] as $phone => $options)
            if (preg_match('/^\\'.$phone.'$/', $msg['Phone']))
            {
                $this->log('Authorized sender: '.$msg['Phone']
                    .' on '.$msg['Date'].' with message ['
                    .$msg['Index'].']['
                    .$msg['Content'].'] determining action');

                foreach ($options as $cmd => $val)
                {
                    $val = strtolower($val);
                    $req = strtolower(trim($msg['Content'] ?? ''));

                    $this->dbg('Comparing ['.$req .'] With ['
                                .$val.'] for cmd [' .$cmd.']');

                    $cmd = self::cmdmap[$req] ?? FALSE;
                    if ($cmd !== FALSE)
                    {
                        $this->log('Action: '.$req);
                        $msg['X-Command'] = $req;

                        return $cmd;
                    }
                }
                $this->log('Unknown or unauthorized command: '.$msg['Content']);
            }

        // no relay numbers, then ignore the message
        if (!isset($this->config['relaybook']))
        {
            $this->log('Unauthorized sender: '.$msg['Phone']
                .' on '.$msg['Date'].' with message ['
                .$msg['Index'].']['
                .$msg['Content'].'] ignoring and deleting message');

            $msg['X-Command'] = 'Ignore';
            return self::ACT_IGNORE;
        }

        $m = '['.$msg['Content'].'] From '.$msg['Phone'].' on '.$msg['Date'];
        foreach ($this->config['relaybook'] as $phone => $options)
        {
            if ($phone == $msg['Phone'] 
            && ($this->config['relaysender'] ?? FALSE) === FALSE)
            {
                $this->log('Not relaying to sender '.$phone);
                continue;
            }
            foreach ($options['methods'] as $method)
                switch (strtolower(trim($method)))
                {
                case self::STR_SMS:
                    $msg['X-Actions'][] = 'Relay via sms to '.$phone;
                    $this->log('Relaying message via sms to: '.$phone);
                    $this->sendSMS($phone, $m);
                    break;

                default:
                    foreach (($this->config['methods'] ?? []) as $method => $cmd)
                        if ($method == strtolower(trim($method)))
                        {
                            $this->log('Relaying message via '.$method.' to: '.$phone);
                            // XXX: TODO command pipe with message as stdin to avoid
                            //      backtick and other security implications
                            $this->log('TO BE IMPLEMENTED');
                            break;
                        }
                    break;
                }
        }
        return self::ACT_IGNORE;
    }

    protected function connect()
    {
        $this->dbg(__FUNCTION__);

        if (($this->config['switchdata'] ?? TRUE)
        && !$this->hl->dataSwitch('on'))
        {
            $this->error('Could not enable mobile data on the modem');
            return;
        }

        if (isset($this->config['route']) 
        && isset($this->config['route']['up']))
        {
            $r = system($this->config['route']['up'], $this->route_ret);
            if ($r === FALSE)
            {
                $this->error('command '.$this->config['route']['up'].' failed');
                $this->disconnect();
                return;
            }
        }

        // get statistics
        // XXX print_r($this->hl->status());

        // spawn and monitor the connection command
        if ($this->startCommand())
        {
            $this->setState(self::STATE_CONNECTED);
            return;
        }

        $this->error('Starting the command ['.$this->command.'] failed');

        $this->disconnect();
    }

    protected function disconnect()
    {
        $this->dbg(__FUNCTION__);
        if (is_resource($this->process))
            $this->stopCommand();

        if (isset($this->config['route']) 
        && isset($this->config['route']['down']))
        {
            $r = system($this->config['route']['down'], $this->route_ret);
            if ($r === FALSE)
                $this->error('command '.$this->config['route']['down'].' failed');
        }

        // disconnect the modem

        if (($this->config['switchdata'] ?? TRUE)
        &&  !$this->hl->dataSwitch('off'))
            $this->error('Could not disable mobile data on the modem');

        $this->setState(self::STATE_IDLE);

        // get statistics

        // XXX print_r($this->hl->statistics());
        // XXX print_r($this->hl->status());
    }

    protected function status()
    {
        $this->dbg(__FUNCTION__);
        // create a small report
        $msg = 'State: '.($this->getState() == self::STATE_CONNECTED
            ? 'connected' : 'disconnected').', last nr: '.
            $this->lastPhonenumber.' last cmd: '.$this->lastCommand;

        $this->hl->sendSMS($this->lastPhonenumber, $msg);
    }

    protected function getState()
    {
        return $this->state;
    }

    protected function setState($state)
    {
        $this->state = $state;
    }

    protected function startCommand()
    {
        // no command always succeeds
        if (!isset($this->config['command']) 
        || $this->config['command'] == NULL
        || trim($this->config['command']) == '')
            return TRUE;

        $descriptorspec = [
             0 => ["pipe", "r"]
            ,1 => ["pipe", "w"]
            ,2 => ["pipe", "w"]
             ];

        $this->process = proc_open($this->config['command']
                    , $descriptorspec, $this->pipes);

        return is_resource($this->process);
    }

    protected function checkCommand()
    {
        // no command always succeeds
        if (!isset($this->config['command']) 
        || $this->config['command'] == NULL
        || trim($this->config['command']) == '')
            return TRUE;

        if (!is_resource($this->process))
            return -1;

        $status = proc_get_status($this->process);
        if ($status === FALSE)
            return -1;

        if ($status['running'])
            return TRUE;

        return $status['exitcode'];
    }

    protected function stopCommand()
    {
        if (!is_resource($this->process))

        for($i = 0; $i < count($this->pipes); $i++)
            fclose($this->pipes[$i]);

        $this->pipes = [];

        $ret = proc_terminate($this->process);

        $this->process = NULL;

        return $ret;
    }
}
