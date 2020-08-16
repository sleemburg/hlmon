cat << EOD > /etc/systemd/system/hlmon.service
[Unit]
Description=HiLink Monitor
DefaultDependencies=no
After=network.target

[Service]
User=root
Type=simple
KillMode=process
Restart=on-failure
ExecStart=/usr/bin/php /opt/hlmon/hlmon.php

[Install]
WantedBy=multi-user.target
EOD

systemctl daemon-reload
systemctl enable hlmon.service
systemctl start hlmon.service
