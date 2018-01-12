<?php

require 'vendor/autoload.php';

$sync = new Synchroversion\Synchroversion(dirname(__FILE__), 'syslog');

echo $sync->latest();
