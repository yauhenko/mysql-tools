<?php

namespace Console;

use Framework\DB\Client;
use Framework\Utils;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Exception;
use Throwable;

class Restore extends Command {

	protected function configure() {
		$this->setName('restore')->setDescription('Restore dump');
		$this->addArgument('dumpfile', InputArgument::REQUIRED, 'Input dump file');
		$this->addOption('only-data', 'd', InputOption::VALUE_OPTIONAL, 'Only data', 'no');
		$this->addOption('only-struct', 's', InputOption::VALUE_OPTIONAL, 'Only structure', 'no');
		$this->addOption('host', null, InputOption::VALUE_OPTIONAL, 'Hostname', 'localhost');
		$this->addOption('port', null, InputOption::VALUE_OPTIONAL, 'Port', 3306);
		$this->addOption('user', 'u', InputOption::VALUE_OPTIONAL, 'Username', 'root');
		$this->addOption('pass', 'p', InputOption::VALUE_OPTIONAL, 'Password', '');
		$this->addOption('database', 'b', InputOption::VALUE_OPTIONAL, 'Database name', '');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$io = new SymfonyStyle($input, $output);
		$dump = $input->getArgument('dumpfile');
		$dump = realpath($dump) ?: $dump;

		$onlyData = $input->getOption('only-data') !== 'no';
		$onlyStruct = $input->getOption('only-struct') !== 'no';

		if($onlyData && $onlyStruct)
			throw new Exception('Invalid command');

		$start = microtime(true);

		try {

			$host = $input->getOption('host');
			$user = $input->getOption('user');
			$pass = (string)$input->getOption('pass');
			$database = (string)$input->getOption('database');
			$port = (int)$input->getOption('port');
			$db = new Client($host, $user, $pass, $database, $port);

			if(!is_file($dump)) throw new Exception('Dump not found: ' . $dump);

			$f = fopen($dump, 'r');
			$meta = json_decode(fgets($f));

			if(!$sig = $meta->begin) throw new Exception("Invalid dump format");
			if($meta->version !== 1) throw new Exception("Unsupported dump version: {$meta->version}");

			if(!$onlyStruct && !$meta->data)
				throw new Exception('There are no data in dump. Try --only-struct option');

			if(!$onlyData && !$meta->struct)
				throw new Exception('There are no structure information in dump. Try --only-data option');

			$table = $keys = $cols = null;

			$db->query('SET FOREIGN_KEY_CHECKS=0');
			$db->begin();

			if(!$onlyData || $onlyStruct) {
				foreach($db->getTables() as $table) {
					$io->text('Dropping table: ' . $table);
					$db->query('DROP TABLE ' . $db->escapeId($table));
				}
			}

			while($line = fgets($f)) {
				$chunk = json_decode($line);
				if(is_object($chunk)) {
					if($chunk->table) {
						$db->commit();
						$table = $chunk->table;
						if($onlyData) {
							$io->text('Truncating table: ' . $table);
							try {
								$db->query('TRUNCATE TABLE ' . $db->escapeId($table));
							} catch (Throwable $e) {
								$io->warning($e->getMessage());
							}
						} else {
							$io->text('Creating table: ' . $table);
							$db->query($chunk->struct);
						}

						try {
							$cols = $db->query('SHOW COLUMNS FROM {&table}', ['table' => $table]);
							array_walk($cols, function (&$val) {
								$val = $val['Field'];
							});
						} catch (Throwable $e) {
							//$io->warning($e->getMessage());
							$cols = null;
						}
						$db->begin();
					}
					if($chunk->keys && !$onlyStruct) {
						$io->text('Processing: ' . $table);
						$keys = $chunk->keys;
					}
					if($chunk->end === $sig) {
						$time = microtime(true) - $start;
						$db->commit();
						$db->query('SET FOREIGN_KEY_CHECKS=1');
						$io->success('Done in ' . sprintf('%.2f', $time) . 's');
						exit(0);
					}
				} elseif (is_array($chunk) && !$onlyStruct && $keys && $cols) {
					$data = array_combine($keys, $chunk);
					//if($keys !== $cols) $io->warning('Data truncated');
					Utils::filterArray($data, $cols);
					if($data) $db->insert($table, $data);
				}
			}

			throw new Exception('Unexpected end of file');

		} catch (Throwable $e) {
			$io->error($e->getMessage());
			exit(1);
		}


	}

}
