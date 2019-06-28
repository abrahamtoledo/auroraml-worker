#!/bin/bash

apt --reinstall install -y php7.2 libapache2-mod-php php-mysql php-mbstring php-xml php-curl
a2enmod -q php7.2 2> /dev/null

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
