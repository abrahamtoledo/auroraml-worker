#!/usr/bin/php
<?php
/**
 * Date: 2/13/2018
 * Time: 9:45 p.m.
 */

define("QUEUE_DIR", __DIR__ . "/queue");

$fname = QUEUE_DIR . "/" . time() . "{$argv[1]}_{$argv[3]}.eml";

$handle = fopen('php://stdin', 'r+');
file_put_contents($fname, stream_get_contents($handle));
chmod($fname, 0777);
fclose($handle);