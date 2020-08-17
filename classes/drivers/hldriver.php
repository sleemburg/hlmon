<?php
/** -----------------------------------------------------------------------------
 * PDX-License-Identifier: GPL-2.0-or-later
 *
 * @author      Stephan Leemburg <stephan@it-functions.nl>
 * @copyright   Copyright (c) 2020, IT Functions
 * ------------------------------------------------------------------------------
 */

require_once __DIR__.'/../../interfaces/Http.php';
require_once __DIR__.'/../../interfaces/iCurl.php';
require_once __DIR__.'/../../traits/tCurl.php';

abstract class Hldriver implements Http, iCurl
{
    use tCurl;

    const VERSION               = '0.1';    
    const DEFAULT_API_VERSION   = '1';
    const SCHEME                = 'http';

    const ENDPOINTS    = [
        1 => [
             'sesTokInfo'   => 'api/webserver/SesTokInfo'
            ,'smsCount'     => 'api/sms/sms-count'
            ,'smsList'      => 'api/sms/sms-list'
            ,'deleteSMS'    => 'api/sms/delete-sms'
            ,'sendSMS'      => 'api/sms/send-sms'
            ,'statistics'   => '/api/monitoring/traffic-statistics'
            ,'status'       => '/api/monitoring/status'
            ,'dataSwitch'   => '/api/dialup/mobile-dataswitch'
        ],
    ];

    const HDR_X_COOKIE  = 1;
    const HDR_X_TOKEN   = 2;
    const HDR_X_REQWITH = 3;
    const HDR_X_CONTYPE = 4;
    const HDR_X_RESPONSE= 5;

    const HEADERS = [
         self::HDR_X_COOKIE     => 'Cookie: %s'
        ,self::HDR_X_RESPONSE   => '_ResponseSource: Broswer'
        ,self::HDR_X_TOKEN      => '__RequestVerificationToken: %s'
        ,self::HDR_X_REQWITH    => 'X-Requested-With: XMLHttpRequest'
        ,self::HDR_X_CONTYPE    => 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8'
    ];

    protected $debug      = FALSE;
    protected $cookie     = NULL;
    protected $token      = NULL;

    public function __construct($options=NULL)
    {
        $this->setApiVersion(self::DEFAULT_API_VERSION);
    }

    public function smsCount()
    {
        if (($data = $this->getCmd(__FUNCTION__)) === FALSE)
            return FALSE;

        return $data['LocalInbox'] ?? 0;
    }

    public function dataSwitch($state='on')
    {
        switch ($state)
        {
        case '1':
        case 'on':
            $state=1;
            break;

        case '0':
        case 'off':
            $state=0;
            break;

        default:
            return FALSE;
        }

        return $this->postCmd(__FUNCTION__,
                '<?xml version="1.0" encoding="UTF-8"?>'
                .'<request>'
                    .'<dataswitch>'.$state.'</dataswitch>'
                .'</request>');
    }

    public function smsList($count=20)
    {
        return $this->postCmd(__FUNCTION__,
                '<?xml version="1.0" encoding="UTF-8"?>'
                .'<request>'
                    .'<PageIndex>1</PageIndex>'
                    .'<ReadCount>'.$count.'</ReadCount>'
                    .'<BoxType>1</BoxType>'
                    .'<SortType>0</SortType>'
                    .'<Ascending>0</Ascending>'
                    .'<UnreadPreferred>0</UnreadPreferred>'
                .'</request>');
    }

    public function sendSMS($recipient, $msg)
    {
        $msg=trim($msg);
        return $this->postCmd(__FUNCTION__,
                '<?xml version="1.0" encoding="UTF-8"?>'
                .'<request>'
                    .'<Index>-1</Index>'
                    .'<Phones>'
                        .'<Phone>'.$recipient.'</Phone>'
                    .'</Phones>'
                    .'<Sca></Sca>'
                    .'<Content>'.$msg.'</Content>'
                    .'<Length>'.strlen($msg).'</Length>'
                    .'<Reserved>1</Reserved>'
                    .'<Date>'.date('Y-m-d H:i:s').'</Date>'
                    .'<SendType>0</SendType>'
                 .'</request>');
    }

    public function deleteSMS($id)
    {
        return $this->postCmd(__FUNCTION__,
                '<?xml version="1.0" encoding="UTF-8"?>'
                .'<request>'
                    .'<Index>'.$id.'</Index>'
                .'</request>');
    }

    public function statistics()
    {
        return $this->getCmd(__FUNCTION__);
    }

    public function status()
    {
        return $this->getCmd(__FUNCTION__);
    }

    /**
     * Returns the version of this library
     *
     * @return String version
    */
    public function getVersion()
    {
        return self::VERSION;
    }

    /**
     * Sets debug on or off
     * 
     * @param Boolean
    */
    public function setDebug($on=TRUE)
    {
        $this->debug = $on ? TRUE : FALSE;
    }

    /**
     * Returns debug state
     * 
     * @return Boolean
    */
    public function getDebug()
    {
        return $this->debug;
    }
    
    // required by iCurl interface
    public function validateState()
    {
        return    $this->domain !== NULL;
    }

    // required by iCurl interface
    public function setHeaders($method, $uri, $data=NULL)
    {
        if (!$this->validateState())
        {
            $this->headers = NULL;
            return FALSE;
        }

        if ($method == self::GET 
        ||  $method == self::PATCH)
        {
            if (is_array($data))
                $data = NULL;
        }

        $this->headers = [ 
            sprintf(self::HEADERS[self::HDR_X_REQWITH])
            ,sprintf(self::HEADERS[self::HDR_X_CONTYPE])
            ,sprintf(self::HEADERS[self::HDR_X_RESPONSE])
            ];
    
        if ($this->cookie !== NULL)
            $this->headers[] = sprintf(self::HEADERS[self::HDR_X_COOKIE]
                            , $this->cookie);
        if ($this->token !== NULL)
            $this->headers[] = sprintf(self::HEADERS[self::HDR_X_TOKEN]
                            , $this->token);
    }

    public function setCookie($data)
    {
        $this->cookie = $data;
    }

    public function setToken($data)
    {
        $this->token = $data;
    }

    public function getSesInfo()
    {
        $api = static::ENDPOINTS[$this->apiVersion]['sesTokInfo'];
        if (($data = $this->curl(self::GET, self::SCHEME, $api)) === FALSE)
            return FALSE;

        $data = $this->xml2array($data);
        if (is_array($data))
        {
            $this->setCookie($data['SesInfo'] ?? NULL);
            $this->setToken($data['TokInfo'] ?? NULL);
        }
    }

    /* -----------------------------------------------------------------------
     * Private methods
     * -----------------------------------------------------------------------
     */

    protected function getCmd($cmd)
    {
        $api = static::ENDPOINTS[$this->apiVersion][$cmd] ?? NULL;
        if ($api === NULL)
            return FALSE;

        $this->getSesInfo();

        if (($data = $this->curl(self::GET, self::SCHEME, $api)) === FALSE)
            return FALSE;

        $data = $this->xml2array($data);

        return $data;
    }

    protected function postCmd($cmd, $data)
    {
        $api = static::ENDPOINTS[$this->apiVersion][$cmd] ?? NULL;
        if ($api === NULL)
            return FALSE;

        $this->getSesInfo();

        if (($data = $this->curl(self::POST, self::SCHEME, $api, $data)) === FALSE)
            return FALSE;

        return $this->xml2array($data);
    }

    protected function xml2array($data)
    {
        if (!substr($data ?? '', 0, 6) == '<?xml ')
            return $data;
        
        $xml = @simplexml_load_string($data
                        ,'SimpleXMLElement'
                        ,LIBXML_NOCDATA
                        );

        // convert xml to json and then to an associative array
        return json_decode(json_encode((array)$xml), TRUE);
    }

    protected function dbg($msg)
    {
        if ($this->debug)
            echo "{$msg}".PHP_EOL;
    }
}
