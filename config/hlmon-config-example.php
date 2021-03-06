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
$config['switchdata'] = TRUE;

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
| be manipulated. Else it must be an array with 'path', an optional args array
| and an optional envs array
*/
$config['command'] = NULL;
/*
$config['command'] = [
    'path' => '/usr/bin/autossh',
    'args' => [ '-M', 0
                , '-o', 'ServerAliveInterval 15'
                , '-o', 'ServerAliveCountMax 3'
                , '-o', 'IdentitiesOnly yes'
                , '-o', 'IdentityFile /root/.ssh/id_ed25519' 
                , '-qNR', '4444:127.0.0.1:22'
                , 'autossh@1.2.3.4'
            ],
    'envs'   => NULL
];
*/

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
| - reset          : stop the Cellulair connection modus whatever state hlmon
|                    is in. This can be needed after a restart while still
|                    connected.
| - status         : Send a SMS with the current status back
| - command        : see the global 'command'. If this is
|                    set, then it will overrule the global setting
*/
$config['phonebook'] = [
	'+316XXXXXXXX' => [
		'connect' => 'connect'
		,'disconnect' => 'disconnect'
		,'reset' => 'reset'
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
|
| An optional 'senders' array may be supplied with patterns. A pattern can be
| negated by prefixing it with an exclamation mark (!). The list is travelled
| and the first match wins. Remember if negations are used an those need to be
| excluded, but the rest should be relayed to add a last '*' element.
*/
$config['relaybook'] = [
	 '+316XXXXXXXX' => [ 'methods' => [ 'sms', 'signal' ] ]

        # Do not relay messages from +31681234567, but relay all others
	,'+31688XXXXXX' => [ 'methods' => [ 'sms' ]
                           , 'senders' => [ '!+31681234567', '*' ] ]

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
