<?php

namespace Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Install extends Command {

	protected function configure() {
		$this->setName('install')->setDescription('Install mysql-tools to system');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$io = new SymfonyStyle($input, $output);
		if(getenv('USER') !== 'root') {
			$io->error('Run install as root');
			exit(1);
		}
		$target = '/usr/bin/mysql-tools';
		$source = realpath($_SERVER['argv'][0]);
		if(file_exists($target)) unlink($target);
		exec("ln -s {$source} {$target}");
		$io->success("Created link: {$target} → {$source}");
	}

}
