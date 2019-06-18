#!/usr/bin/php
<?php
$handle = fopen('php://stdin', 'r+');
file_put_contents("/tmp/tempmail.eml", stream_get_contents($handle));
chmod("/tmp/tempmail.eml", 0777);
fclose($handle);