#!/bin/bash

apt --reinstall install -y php7.0 libapache2-mod-php php-mysql php-mbstring php-xml php-curl php-mcrypt

phpver=$( php --version | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2 )
a2enmod -q "php${phpver}" 2> /dev/null

echo '; PHP directives for AuroraML - Worker.
[Auroraml]
error_log = "syslog"
log_errors = On
error_reporting = E_ALL & ~E_STRICT
display_errors = Off


; Allow the use of short open tags "<?"
short_open_tag = On

' > "$(ls -d /etc/php/7.*/apache2/conf.d | tail -n 1)/50-auroraml-worker.ini"

systemctl restart apache2
