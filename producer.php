<?php

require 'vendor/autoload.php';

$sync = new Synchroversion\Synchroversion(dirname(__FILE__), 'syslog');

// 0022 is the default umask anyway, this is just to illustrate the usage
$sync->setUmask(0022);

// 3 is the default retain state, this is just to illustrate the usage
$sync->retainState(3);

$sync->exec(function () {
    return file_get_contents('/var/log/system.log');
});
