<?php
/** -----------------------------------------------------------------------------
 * PDX-License-Identifier: GPL-2.0-or-later
 *
 * @author      Stephan Leemburg <stephan@it-functions.nl>
 * @copyright   Copyright (c) 2020, IT Functions
 * ------------------------------------------------------------------------------
 */

require_once __DIR__.'/hldriver.php';

class E3531 extends Hldriver
{
    const VERSION      = '0.1';    
    const ENDPOINTS    = [
        1 => [
             'sesTokInfo'       => 'api/webserver/SesTokInfo'
            ,'smsCount'         => 'api/sms/sms-count'
            ,'smsList'          => 'api/sms/sms-list'
            ,'deleteSMS'        => 'api/sms/delete-sms'
            ,'sendSMS'          => 'api/sms/send-sms'
            ,'statistics'       => '/api/monitoring/traffic-statistics'
            ,'status'           => '/api/monitoring/status'
            ,'dataSwitch'       => '/api/dialup/dial'
            ,'monthStatistics'  => '/api/monitoring/month_statistics'
        ],
    ];


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
				.'<Action>'.$state.'</Action>'
			.'</request>');
    }
}
