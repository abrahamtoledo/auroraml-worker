#!/bin/bash

# Install prerequisites
apt install -y apache2 php libapache2-mod-php php-mysql
a2enmod php

# Install and config app
