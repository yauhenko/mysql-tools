<?php

namespace Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SelfUpgrade extends Command {

	protected function configure() {
		$this->setName('self-upgrade')->setDescription('Upgrade mysql-tools to latest version');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$io = new SymfonyStyle($input, $output);

		$io->text('Checking for new version...');
		$data = @file_get_contents('https://github.com/yauhenko/mysql-tools/releases/latest');

		if(!$data) {
			$io->error('Failed to fetch data');
			exit(1);
		}

		preg_match('/releases\/download\/v([0-9\.]+)\/mysql-tools\.phar/', $data, $m);

		if(!$m[1]) {
			$io->error('Failed to resolve latest version');
			exit(1);
		}

		if(version_compare($m[1], VERSION, '>')) {
			$io->success('Found new version: v' . $m[1]);
			$url = 'https://github.com/yauhenko/mysql-tools/' . $m[0];
			$io->text('Downloading ' . $url);
			$phar = file_get_contents($url);
			$tmp = '/tmp/mysql-tools-v' . $m[1] . '.phar';
			if(!@file_put_contents($tmp, $phar)) {
				$io->error('Failed to create temporary file: ' . $tmp);
				exit(1);
			}
			unset($phar);
			$source = realpath($_SERVER['argv'][0]);
			$io->text('Replacing: ' . $source);
			if(@chmod($tmp, 0755) && @rename($tmp, $source)) {
				$io->success('Upgraded: v' . VERSION . ' → v' . $m[1]);
				exit(0);
			} else {
				$io->error('Upgrade fail');
				exit(1);
			}
		} else {
			$io->success('Latest version already installed');
			exit;
		}

	}

}
