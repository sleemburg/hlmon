<?php
/**
* PDX-License-Identifier: GPL-2.0-or-later
*
* @author      Stephan Leemburg <stephan@it-functions.nl>
* @copyright   Copyright (c) 2020, IT Functions
*/

require_once __DIR__.'/hilink.php';
require_once __DIR__.'/hlbase.php';

class Hlcmd extends Hlbase
{
    public function __construct($host='192.168.8.1')
    {
        parent::__construct('hlmon', $host);
    }

    public function run() { /* void */ }
        
    public function connect()
    {
        $this->dbg(__FUNCTION__);

        if (!$this->hl->dataSwitch('on'))
            $this->error('Could not enable mobile data on the modem');
    }

    public function disconnect()
    {
        $this->dbg(__FUNCTION__);

        if (!$this->hl->dataSwitch('off'))
            $this->error('Could not disable mobile data on the modem');
    }

    public function monthstatistics()
    {
        $this->dbg(__FUNCTION__);

        if (!$this->hl->monthStatistics())
            $this->error('Could not get month statistics from the modem');
    }

    public function information()
    {
        $this->dbg(__FUNCTION__);

        if (!$this->hl->information())
            $this->error('Could not get information from the modem');
    }

    public function wanipv4()
    {
        $this->dbg(__FUNCTION__);

        if (!($ipv4 = $this->hl->wanipv4()))
            $this->error('Could not get wan ipv4 address from the modem');

        return $ipv4;
    }
}
