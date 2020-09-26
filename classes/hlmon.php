<?php
/**
* PDX-License-Identifier: GPL-2.0-or-later
*
* @author      Stephan Leemburg <stephan@it-functions.nl>
* @copyright   Copyright (c) 2020, IT Functions
*/

require_once __DIR__.'/hilink.php';
require_once __DIR__.'/hlbase.php';
require_once __DIR__.'/argparser.php';

class Hlmon extends Hlbase
{
    private $state          = self::STATE_VOID;
    private $lastCommand    = '';
    private $process        = NULL;
    private $pipes          = [];

    const STATE_VOID        = 0;
    const STATE_IDLE        = 1;
    const STATE_CONNECTED   = 2;

    const ACT_CONNECT       = 1;
    const ACT_DISCONNECT    = 2;
    const ACT_STATUS        = 3;
    const ACT_NOOP          = 4;
    const ACT_IGNORE        = 5;
    const ACT_RESEND        = 6;
    const ACT_RESET         = 7;

    const STR_CONNECT       = 'connect';
    const STR_DISCONNECT    = 'disconnect';
    const STR_RESET         = 'reset';
    const STR_STATUS        = 'status';
    const STR_SMS           = 'sms';

    const CMD_FILE          = __DIR__.'/../commands.txt';

    const cmdmap = [
         self::STR_CONNECT      => self::ACT_CONNECT
        ,self::STR_DISCONNECT   => self::ACT_DISCONNECT
        ,self::STR_RESET        => self::ACT_RESET
        ,self::STR_STATUS       => self::ACT_STATUS
    ];

    public function __construct($host='192.168.8.1')
    {
        parent::__construct(strtolower(basename(__FILE__)), $host);
    
        $this->setState(self::STATE_IDLE);

        // on startup disconnect from any leftover connected state
        $this->disconnect();
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

            // collect all exit statuses of the once that passed away..
            $this->reap();

            // are there manual commands to perform?
            if (is_file(self::CMD_FILE))
            {
                if (($fh = fopen(self::CMD_FILE, 'r')) !== FALSE)
                {
                    // remove the file 
                    unlink(self::CMD_FILE);

                    // read the commands and execute them
                    while (($cmd = fgets($fh)) !== FALSE)
                    {
                        $cmd = trim($cmd);
                        foreach (self::cmdmap as $key => $action)
                            if ($cmd == $key)
                            {
                                $this->log("Handling command file cmd {$cmd}");
                                $this->handleRequest($action);
                            }
                    }
                    fclose($fh);
                }
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

        case self::ACT_RESET:
        case self::ACT_DISCONNECT:
            if ($action != self::ACT_RESET && $state != self::STATE_CONNECTED)
            {
                $this->log('Already disconnected, ignoring request.');
                break;
            }
            $this->disconnect();
            break;
    
        case self::ACT_STATUS:
            $this->status();
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

        $m = $msg['Content'].' [From '.$msg['Phone'].' on '.$msg['Date'].']';
        foreach ($this->config['relaybook'] as $phone => $options)
        {
            if ($phone == $msg['Phone'] 
            && ($this->config['relaysender'] ?? FALSE) === FALSE)
            {
                $this->log('Not relaying to sender '.$phone);
                continue;
            }
            // only relay for some senders?
            if (is_array($options['senders'] ?? NULL))
            {
                $match = FALSE;
                foreach($options['senders'] as $sender)
                {
                    $pattern = trim($sender);
                    if (($negate = $pattern{0} === '!'))
                        $pattern = substr($pattern, 1);

                    if ($match = $pattern === '*'
                    || ($match = preg_match('/^\\'.$pattern.'$/', $msg['Phone'])))
                    {
                        $match = $match && !$negate;
                        break;
                    }
                }
                
                if (!$match)
                {
                    $this->log('Not relaying to '.$phone
                              .' from '.$msg['Phone'].' based on sender pattern');
                    continue;
                }
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
            $cmds = $this->config['route']['up'];
            if (!is_array($cmds))
                $cmds = [ $cmds ];

            foreach($cmds as $cmd)
            {
                if ($this->system($cmd) === FALSE)
                {
                    $this->disconnect();
                    return;
                }
            }
        }

        // spawn and monitor the connection command
        if ($this->startCommand())
        {
            $this->setState(self::STATE_CONNECTED);
            return;
        }

        $this->error('Starting the command failed');

        $this->disconnect();
    }

    protected function disconnect()
    {
        $this->dbg(__FUNCTION__);

        $this->stopCommand();

        if (isset($this->config['route']) 
        && isset($this->config['route']['down']))
        {
            $cmds = $this->config['route']['down'];
            if (!is_array($cmds))
                $cmds = [ $cmds ];

            foreach($cmds as $cmd)
                $this->system($cmd);
        }

        // disconnect the modem
        if (($this->config['switchdata'] ?? TRUE)
        &&  !$this->hl->dataSwitch('off'))
            $this->error('Could not disable mobile data on the modem');

        $this->setState(self::STATE_IDLE);
    }

    protected function status()
    {
        $this->dbg(__FUNCTION__);
        // create a small report
        $msg = 'State: '.($this->getState() == self::STATE_CONNECTED
            ? 'connected' : 'disconnected').', last nr: '.
            $this->lastPhonenumber.' last cmd: '.$this->lastCommand;

        $this->sendSMS($this->lastPhonenumber, $msg);
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
        || !is_array($this->config['command'])
        && trim($this->config['command']) == '')
            return TRUE;

        $pid = pcntl_fork();
        switch ($pid)
        {
        case 0: // child
            // wait for modem and routes to settle...

            sleep(5);

            $cmds = $this->config['command'];

            $this->pexec($cmds);
            
            // the child will only reach this point on exec failure
            exit(255);

        default: // parent
            $this->process = $pid;

            return $this->checkCommand();
            break;
        }
    }

    protected function checkCommand()
    {
        // no command always succeeds
        if ($this->process === NULL)
            return TRUE;

        return posix_kill($this->process, 0);
    }

    protected function stopCommand()
    {
        if ($this->process === NULL)
            return TRUE;

        $ret = posix_kill($this->process, SIGTERM);

        $this->process = NULL;

        return $ret;
    }

    protected function system($cmd)
    {
        $this->dbg("executing {$cmd}");
        $r = system($cmd, $return);
        if ($r === FALSE)
        {
            $this->error("command {$cmd} failed with return value {$return}");
            return FALSE;
        }
        $this->dbg("command returned {$return}");
        return $return;
    }

    protected function pexec($cmd)
    {
        // just a string, then compose path and args
        if (!isset($cmd['path']))
        {
            $o = new ArgvParser($cmd);
            $a = $o->parse();
            $path = $a[0];
            $args = array_splice($a, -(count($a)-1));
            $cmd = [ 'path' => $path, 'args' => $args ];

            // garbage collect
            $o = NULL;
        }
        $this->dbg("Spawning {$cmd['path']}");

        if(($cmd['args'] ?? NULL) !== NULL
        && ($cmd['envs'] ?? NULL) !== NULL)
            pcntl_exec($cmd['path'], $cmd['args'], $cmd['envs']);
                        
        else if(($cmd['args'] ?? NULL) !== NULL)
            pcntl_exec($cmd['path'], $cmd['args']);
        else 
            pcntl_exec($cmd['path']);

        // failed to exec
        exit(255);
    }

    protected function reap()
    {
        // any children dead?
        while (($pid = pcntl_waitpid(-1, $status, WNOHANG)) > 0)
        {
            $this->log("Child process {$pid} exited with {$status}");
            // remove the pid from the process list slots
        }
    }
}
