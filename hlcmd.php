#!/usr/bin/php
<?php
/**
* PDX-License-Identifier: GPL-2.0-or-later
*
* @author      Stephan Leemburg <stephan@it-functions.nl>
* @copyright   Copyright (c) 2020, IT Functions
*/

require_once __DIR__.'/classes/hlcmd.php';


$options = getopt("c:r:m:dh");

if (!is_array($options) || count($options) < 1)
{
    echo 'Usage: hlcmd -c cmd'.PHP_EOL;
    exit;
}

if (!array_key_exists('c', $options))
{
    echo "Required option -c not present\n";
    exit;
}

$hlcmd = new Hlcmd();

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

    return $hlcmd->sendSMS($options['r'], $options['m']);

case 'monitor':
    break;

case 'connect':
    return $hlcmd->connect();
    
case 'disconnect':
    return $hlcmd->disconnect();

case 'month':
    return $hlcmd->monthstatistics();

case 'information':
    return $hlcmd->information();

case 'wanipv4':
    if (($ipv4 = $hlcmd->wanipv4()) === NULL)
    {
        echo "Not connected\n";
        exit(1);
    }
    else if ($ipv4 === FALSE)
    {
        echo "Error retrieving information from device\n";
        exit(2);
    }
    echo "{$ipv4}\n";
    exit(0);

default:
    echo "Unexpected value for option -c ({$options['c']})\n";
    exit;
}
