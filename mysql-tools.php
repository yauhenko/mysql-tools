<?php

error_reporting(0);

require __DIR__ . '/vendor/autoload.php';

use Console\Dump;
use Console\Install;
use Console\Restore;
use Console\Uninstall;
use Symfony\Component\Console\Application;

$app = new Application('mysql-tools', '1.1.0');
$app->add(new Dump);
$app->add(new Restore);

if(Phar::running()) {
	$app->add(new Install);
	$app->add(new Uninstall);
}

$app->run();
