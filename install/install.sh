#!/bin/bash

. ./functions.sh

apt install -y apache2

. ./php.sh

. ./rsyslog.sh

. ./auroraml.sh

. ./cpu-check.sh

echo "DONE !"
