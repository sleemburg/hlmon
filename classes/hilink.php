<?php
/** -----------------------------------------------------------------------------
 * PDX-License-Identifier: GPL-2.0-or-later
 *
 * @author      Stephan Leemburg <stephan@it-functions.nl>
 * @copyright   Copyright (c) 2020, IT Functions
 * ------------------------------------------------------------------------------
 */

class Hilink
{
    //                  Modem            Driver
    const drivers = [ 'DEFAULT'    => 'E3372'
                     ,'E3372'      => 'E3372'
                     ,'E3372h-320' => 'E3372'
                     ,'E3531'      => 'E3531'
                     ];

    protected $driver = NULL;

    public function __construct($options=NULL)
    {
        spl_autoload_register(function ($name) { 
            include __DIR__.'/drivers/'.$name.'.php'; 
        });

        $modem = ($options['modem'] ?? 'DEFAULT');

        foreach (self::drivers as $name => $class)
            if ($name == $modem)
            {
                $this->driver = new $class;
                break;
            }
            
        if (!is_a($this->driver, 'Hldriver'))
            throw new Exception("No driver for the specified modem ({$modem}) found"); 
    }

    public function setDomain($domain)
    {
        return $this->driver->setDomain($domain);
    }

    public function getSesInfo()
    {
        return $this->driver->getSesInfo();
    }

    public function smsCount()
    {
        return $this->driver->smsCount();
    }

    public function dataSwitch($state='on')
    {
        return $this->driver->dataSwitch($state);
    }

    public function smsList($count=20)
    {
        return $this->driver->smsList($count);
    }

    public function sendSMS($recipient, $msg)
    {
        return $this->driver->sendSMS($recipient, $msg);
    }

    public function deleteSMS($id)
    {
        return $this->driver->deleteSMS($id);
    }

    public function statistics()
    {
        return $this->driver->statistics();
    }

    public function status()
    {
        return $this->driver->status();
    }

    /**
     * Returns the version of this library
     *
     * @return String version
    */
    public function getVersion()
    {
        return $this->driver->getVersion();
    }

    /**
     * Sets debug on or off
     * 
     * @param Boolean
    */
    public function setDebug($on=TRUE)
    {
        return $this->driver->setDebug($on);
    }

    /**
     * Returns debug state
     * 
     * @return Boolean
    */
    public function getDebug()
    {
        return $this->driver->getDebug();
    }
    
    /**
     * Returns the last Error msg, if any
     * 
     * @return String
    */
    private function getError()
    {
        return $this->driver->lastError();
    }
}
