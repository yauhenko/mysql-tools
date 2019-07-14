<?php

namespace Console;

use Exception;
use Framework\DB\Client;
use Framework\Utils;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

class Restore extends Command {

	protected function configure() {
		$this->setName('restore')->setDescription('Restore dump');
		$this->addArgument('uri', InputArgument::REQUIRED, 'Connection string');
		$this->addArgument('dump', InputArgument::REQUIRED, 'Source dump file');
		$this->addOption('only-data', 'd', InputOption::VALUE_OPTIONAL, 'Only data', false);
		$this->addOption('only-struct', 's', InputOption::VALUE_OPTIONAL, 'Only structure', false);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$io = new SymfonyStyle($input, $output);
		$uri = $input->getArgument('uri');
		$dump = $input->getArgument('dump');

		$onlyData = $input->getOption('only-data') !== false;
		$onlyStruct = $input->getOption('only-struct') !== false;

		if($onlyData && $onlyStruct)
			throw new Exception('Invalid command');

		$start = microtime(true);

		try {

			$db = Client::createFromUri($uri);

			if(!is_file($dump)) throw new Exception('Dump not found: ' . $dump);

			$f = fopen($dump, 'r');
			$meta = json_decode(fgets($f));

			if(!$sig = $meta->begin) throw new Exception("Invalid dump format");

			if(!$onlyStruct && !$meta->data)
				throw new Exception('There are no data in dump. Try --only-struct option');

			if(!$onlyData && !$meta->struct)
				throw new Exception('There is no structure information in dump. Try --only-data option');

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
						$io->success('Done in ' . sprintf('%.2f', $time) . 's');
						$db->commit();
						$db->query('SET FOREIGN_KEY_CHECKS=1');
						exit;
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
