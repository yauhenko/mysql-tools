<?php

namespace Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Uninstall extends Command {

	protected function configure() {
		$this->setName('uninstall')->setDescription('Uninstall mysql-tools from system');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$io = new SymfonyStyle($input, $output);
		if(getenv('USER') !== 'root') {
			$io->error('Run uninstall as root');
			exit(1);
		}
		$target = '/usr/bin/mysql-tools';
		if(file_exists($target)) {
			unlink($target);
			$io->success("Deleted link: {$target}");
		} else {
			$io->error('Not installed');
		}
	}

}
