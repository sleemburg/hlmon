<?php
/* 
| ------------------------------------------------------------------------------
| host, the ip address of the HiLink modem to connect to
| ------------------------------------------------------------------------------
*/
$config['host'] = '192.168.8.1';

/* 
| ------------------------------------------------------------------------------
| modem, which modem type are we using
| ------------------------------------------------------------------------------
| Currently supported modems:
| - E3372
| - E3372h-320
| - E3531
| 
| If no modem is specified then the DEFAULT is used, which is E3372
*/
$config['modem'] = 'DEFAULT';

/* 
| ------------------------------------------------------------------------------
| countryprefix, the telephony country prefix including the starting +
| ------------------------------------------------------------------------------
*/
$config['countryprefix'] = '+31';

/* 
| ------------------------------------------------------------------------------
| switchdata, should the mobile connection of the modem be turned on and off
| ------------------------------------------------------------------------------
| can be TRUE or FALSE, if not set then it defaults to TRUE
*/
$config['mobiledata'] = TRUE;

/* 
| ------------------------------------------------------------------------------
| route, the commands to manipulate the routing table
| ------------------------------------------------------------------------------
| Note that this command must run in the foreground, so that hlmon can 
| monitor the command and check the return code.
| 
| If the routing command exits with a status other than 0, the (optional)
| command is not run and - if configured - the mobile link is torn down.
*/
/*
$config['route'] = [
         'up'   => [ '/sbin/ip route add 1.2.3.4 via 192.168.8.1'
                    ,'/sbin/ip route add 1.2.3.5 via 192.168.8.1' ]
        ,'down' => [ '/sbin/ip route del 1.2.3.4 via 192.168.8.1'
                    ,'/sbin/ip route del 1.2.3.5 via 192.168.8.1' ]
    ];
*/
$config['route'] = [
         'up'   =>  '/sbin/ip route add 1.2.3.4 via 192.168.8.1'
        ,'down' =>  '/sbin/ip route del 1.2.3.4 via 192.168.8.1'
    ];

/* 
| ------------------------------------------------------------------------------
| command, the command to execute and monitor to start a connection
| ------------------------------------------------------------------------------
| Note that this command must run in the foreground, so that hlmon can 
| monitor the command.
| 
| The command may be NULL, for example if only the routing table needs to
| be manipulated. It may be a single command or an array of commands.
*/
$config['command'] = NULL;

/* 
| ------------------------------------------------------------------------------
| phonebook, a list of allowed phonenumbers to control the modem
| ------------------------------------------------------------------------------
| 
| Each phonenumber can have an array of elements.
| If the element is present, then the action is allowed for that phonenumber
| when the 'magic keyword' for that action is send as the SMS body. 
|
| Note that the 'magic keywords' must be unique in order to identify the action.
|      and that they are compared case insensitive
|
| Currently supported actions:
| - connect        : start the Cellulair connection and start the 
|                    'command' (global)
| - disconnect     : stop the Cellulair connection modus
| - status         : Send a SMS with the current status back
| - command        : see the global 'command'. If this is
|                    set, then it will overrule the global setting
*/
$config['phonebook'] = [
	'+316XXXXXXXX' => [
		'connect' => 'connect'
		,'disconnect' => 'disconnect'
		,'status' => 'status'
	]
];

/* 
| ------------------------------------------------------------------------------
| relaybook, a list of phonenumbers to relay non-command messages to
| ------------------------------------------------------------------------------
| 
| Each phonenumber must have a 'methods' array of methods.
| The only default supported method is (native) sms.
| other methods may be introduced, like sending with signal-cli
*/
$config['relaybook'] = [
	 '+316XXXXXXXX' => [ 'methods' => [ 'sms', 'signal' ] ]
];

/* 
| ------------------------------------------------------------------------------
| relaysender, a boolean indicating if a message should also be relayed back
| ------------------------------------------------------------------------------
*/
$config['relaysender'] = TRUE;

/* 
| ------------------------------------------------------------------------------
| methods, a list of methods and the commands for use in the relaybook
| ------------------------------------------------------------------------------
*/
$config['methods'] = [
	'signal' => [ '/usr/bin/mail -s %f %r' ]
];

/* 
| ------------------------------------------------------------------------------
| timeout, sleeping time for rescanning for new sms messages
| ------------------------------------------------------------------------------
*/
$config['timeout'] = 10;

/* 
| ------------------------------------------------------------------------------
| debug, turn debugging on or off
| ------------------------------------------------------------------------------
*/
$config['debug'] = FALSE;

/* 
| ------------------------------------------------------------------------------
| nondestructive, never remove SMS messages
| ------------------------------------------------------------------------------
*/
$config['nondestructive'] = FALSE;

/* 
| ------------------------------------------------------------------------------
| storage, where to save received sms messages
| ------------------------------------------------------------------------------
| default is in the hlmon/sms directory
*/
// $config['storage'] = '/path/to/sms/storage';
