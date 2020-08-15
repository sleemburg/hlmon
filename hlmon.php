#!/usr/bin/php
<?php
/**
* PDX-License-Identifier: GPL-2.0-or-later
*
* @author      Stephan Leemburg <stephan@it-functions.nl>
* @copyright   Copyright (c) 2020, IT Functions
*/

require_once __DIR__.'/classes/hlmon.php';


$mon = new Hlmon();
$options = getopt("c:r:m:dh");

if (is_array($options) && count($options) > 0)
{
    if (!array_key_exists('c', $options))
    {
        echo "Required option -c not present\n";
        exit;
    }
    switch (strtolower(trim($options['c'])))
    {
    case 'sms':
        $ok = true;
        foreach (['r', 'm'] as $key)
            if (($options[$key] ?? FALSE) === FALSE)
            {
                echo "Required option -{$key} not present\n";
                $ok = false;
            }
        if (!$ok)
            exit;

        return $mon->sendSMS($options['r'], $options['m']);

    case 'monitor':
        break;

    case 'connect':
        $mon->connect();
        break;
    
    case 'reset':
    case 'disconnect':
        $mon->disconnect();
        break;
    
    default:
        echo "Unexpected value for option -c ({$options['c']})\n";
        exit;
    }
}

// default action is to monitor
$mon->run();
