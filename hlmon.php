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
    case 'monitor':
        break;

    case 'connect':
    case 'disconnect':
    case 'reset':
        $cmd = 'echo '.strtolower(trim($options['c'])).' > '.__DIR__.'/commands.txt';

        // put the command in the commands file, so that hlmon can pick it up
        // and update it's stat accordingly
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
