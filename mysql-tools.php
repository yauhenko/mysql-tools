<?php

error_reporting(0);

require __DIR__ . '/vendor/autoload.php';

define('VERSION', json_decode(file_get_contents(__DIR__ . '/composer.json'))->version);

use Console\Dump;
use Console\Install;
use Console\Restore;
use Console\SelfUpgrade;
use Console\Uninstall;
use Symfony\Component\Console\Application;

$app = new Application('mysql-tools', VERSION);
$app->add(new Dump);
$app->add(new Restore);

if(Phar::running()) {
	$app->add(new Install);
	$app->add(new Uninstall);
	$app->add(new SelfUpgrade);
}

$app->run();
