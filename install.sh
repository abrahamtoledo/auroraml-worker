#!/bin/bash

# Install prerequisites
apt install -y apache2 php libapache2-mod-php php-mysql php-mbstring php-xml
a2enmod php

systemctl restart apache2

# Install and config app
