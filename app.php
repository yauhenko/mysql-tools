<?php

error_reporting(0);

require __DIR__ . '/vendor/autoload.php';

use Console\Dump;
use Console\Restore;
use Symfony\Component\Console\Application;

$app = new Application('MySQL Tools', '1.0.0');
$app->add(new Dump);
$app->add(new Restore);
$app->run();
