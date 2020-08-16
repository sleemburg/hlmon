#!/usr/bin/php
<?php
/**
* PDX-License-Identifier: GPL-2.0-or-later
*
* @author      Stephan Leemburg <stephan@it-functions.nl>
* @copyright   Copyright (c) 2020, IT Functions
*/

require_once __DIR__.'/classes/hlmon.php';


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

        $mon = new Hlmon();
        return $mon->sendSMS($options['r'], $options['m']);

    case 'monitor':
        break;

    case 'connect':
    case 'disconnect':
    case 'reset':
        $cmd = 'echo '.strtolower(trim($options['c'])).' > '.__DIR__.'/commands.txt';

        // put the command in the commands file
        `{$cmd}`;

        exit;
        break;
    
    default:
        echo "Unexpected value for option -c ({$options['c']})\n";
        exit;
    }
}

$mon = new Hlmon();
$mon->run();
