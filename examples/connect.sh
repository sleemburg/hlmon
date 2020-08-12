#!/bin/bash

exec /usr/bin/autossh -M 0 -Nq -o "ServerAliveInterval 60" -o "ServerAliveCountMax 3" -p 22 -l autossh@1.2.3.4 -R 2222:127.0.0.1:22 -i /root/.ssh/id_ed25519


