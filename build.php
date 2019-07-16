<?php

@unlink('mysql-tools.phar');

$ph = new Phar('mysql-tools.phar');
$ph->buildFromDirectory('.', '/^\.\/(src|vendor|mysql-tools\.php|composer)/');
$stub = $ph->createDefaultStub('mysql-tools.php');
$ph->setStub("#!/usr/bin/php\n{$stub}");

chmod('mysql-tools.phar', 0755);
