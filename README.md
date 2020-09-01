# License

 * PDX-License-Identifier: GPL-2.0-or-later
 * @author      Stephan Leemburg <stephan@it-functions.nl>
 * @copyright   Copyright (c) 2020, IT Functions

# Description

This code can monitor cheap Huawei HiLink USB modems. It can be used as a SMS repeater.
So any SMS received on the SIM in the modem will be repeated to a list of other 
recipients. This can be SMS, Email, Signal (or anything else that you can implement, 
even MQTT).

It can also be used as a fallback router. Sending a SMS with 'connect' will enable 
the mobile data on the modem and can then alter routes or start a program.

I use it on two different locations. One location as a SMS 'gateway' and on the other
location as both a SMS gateway and a 'emergency' remote reverse SSH access method.

The last one has a prepaid SIM, so I only want to enable mobile data when needed and
disable it as soon as it is not needed anymore

# Modems known to work

 * E3372: Huawei Technologies Co., Ltd. E33372 LTE/UMTS/GSM HiLink Modem/Networkcard
 * E3372h-320: Huawei Technologies Co., Ltd. E353/E3131
 * E3531: Huawei Technologies Co., 

# Requirements

  apt install php-cli php-curl php-xml php-json

# Why PHP?

Because I am rather fluent in writing php code. I am also fluent in C, but I prefer
the higher level languages that relieve me from memory management issues and 
performance is not an issue with this code and setup.

As I want to become a fluent python coder, I will rewrite the code in python as
soon as I get to it.

# Installation

Clone this repository to the system you want to run it on. It can run as any
user, as long as the user is able to run certain commands, for example with the
help of sudo.

Note that I run it as root within unpriviliged, dedicated Proxmox containers.

Then copy the hlmon/config/hlmon-config-example.php file to hlmon/config/hlmon.php
and edit the hlmon/config/hlmon.php so it reflects your situation. The example
config file is annotated and should be self documenting.

The hlmon/examples contains some example scripts that may be of use.

So in short:

    [ -d /opt ] || mkdir -m02775 /opt
    cd /opt && git clone https://github.com/sleemburg/hlmon.git
    cd hlmon
    cp config/hlmon-config-example.php config/hlmon.php
    vi config/hlmon.php # adjust to you liking

After that the systemd hlmon unit file can be installed and enabled with

    bash examples/hlmon-unit.sh

# Recomended setup

 * Run autossh to connect to your reverse shell target
 * Run hlmon to enable mobile data and change the routing
 * Use ip addresses if DNS can become unavailable when an outage occurs

# Modem mode of operation

The modem is used in the 'network device modus'. In this mode it manifests itself
as a USB Ethernet device.

It may be nescessary to use usb_modeswitch to set the modem in this mode. On debian
this can be found in the usb-modeswitch package.

For the below 'remote modem', the command for this is:

`usb_modeswitch -J -W -v 0x12d1 -V 0x12d  -p 0x1f01 -P 0x14dc`

For the 'Local backup modem', I used:

`usb_modeswitch -N -v 0x12d1 -V 0x12d1  -p 0x1465  -P 0x14dc`

# Tested devices

I have tested the code on my own 2 Huawei USB modems, these are:

 * Remote modem. 

    This one has a prepaid SIM in it, so mobile data needs to be
   switched on and off every time a connection is required.

    Device name:        E3372
   
    Hardware version:   CL2E3372HM
   
    Software version:   22.317.01.00.778

    lsusb output: ID 12d1:14dc Huawei Technologies Co., Ltd. E33372 LTE/UMTS/GSM HiLink Modem/Networkcard

 * Local backup modem. 

    This one has an unlimited data SIM, so mobile data can
   remain on. No route switching or commands are required. Only sms gateway.

    Device name: E3372h-320

    Hardwareversion: CL4E3372HM
    
    Softwareversion: 10.0.3.1(H192SP1C983)
    
    lsusb output: ID 12d1:14db Huawei Technologies Co., Ltd. E353/E3131

 * Modem of a friend

    Device name: E3531

# Testing without sending a sms

The basic commands: {connect, disconnect, reset} can be tested by echoing the
command to the hlmon directory into a file named commands.txt. For example to force
a connect:

    echo connect > /opt/hlmon/commands.txt

And to have it disconnect again:

    echo disconnect > /opt/hlmon/commands.txt

# Usage scenarios

For the both setups, I use autossh. See the examples below.

On the prepaid SIM modem, when the connection is activated, the mobile data is
switched on. Then a route specific to the reverse SSH tunnel address is 
inserted. No command is being run, as I have autossh always running.

On the unlimited data SIM, no connection activation takes place. The system has
it's default route via the modem. So the autossh connections are already in 
place. My firewall switches it's default routes over my main line and if that
drops over the modem. 

The modem on the unlimited data SIM is for what hlmon is concerned only used
as a SMS gateway to enable multiple receivers for SMS data (or signal or mail).

In the autossh configuration ip addresses are used, in stead of FQDN. This is 
to avoid resolving issues when a failover connection is required and maybe DNS
is disfunctional at that moment.

# Proxmox container installation

For proxmox unpriviliged based containers, it is nescessary to give the device to
the container. In your `/etc/pve/lxc/$CTID.conf` you should add:

    lxc.net.1.type: phys
    lxc.net.1.link: eth1
    lxc.net.1.name: eth1
    lxc.net.1.flags: up


Also, I noticed that one of the modems - the E3372h-320 - sometimes restarts and 
then 'vanishes' from the container. So I have a udev rule for that:

    cat /etc/udev/rules.d/10-dynamic-network.rules
    KERNEL=="eth1", SUBSYSTEM=="net", ACTION=="add", RUN+="/root/bin/restart-gw3.sh"

    cat /root/bin/restart-gw3
    #!/bin/bash

    export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

    pct stop 102
    pct start 102

    echo "Restarted gw3 due to modem reset" | mail -s "GW3 restart" root@my.domain

    exit 0


# See also

In the examples directory are various example scripts and configurations.
