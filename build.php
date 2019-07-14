<?php

@unlink('mysql-tools.phar');
@unlink('mysql-tools');

$ph = new Phar('mysql-tools.phar');
$ph->buildFromDirectory('.', '/^\.\/(src|vendor|app\.php)/');
$stub = $ph->createDefaultStub('app.php');
$ph->setStub("#!/usr/bin/php\n{$stub}");

chmod('mysql-tools.phar', 0755);
rename('mysql-tools.phar', 'mysql-tools');
