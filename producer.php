<?php

require 'vendor/autoload.php';

$sync = new Synchroversion\Synchroversion(dirname(__FILE__), 'syslog');

$sync->exec(function () {
    return file_get_contents('/var/log/system.log');
});
