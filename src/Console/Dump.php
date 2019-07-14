<?php

namespace Console;

use Exception;
use Framework\DB\Client;
use Framework\DB\Pagination\Pager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

class Dump extends Command {

	protected function configure() {
		$this->setName('dump')->setDescription('Create dump');
		$this->addArgument('uri', InputArgument::REQUIRED, 'Connection string');
		$this->addArgument('dump', InputArgument::OPTIONAL, 'Output dump file', 'output.dump');
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
			$sig = md5(time());

			$meta = [
				'begin' => $sig,
				'version' => 1,
				'created' => date('Y-m-d H:i:s'),
				'data' => !$onlyStruct,
				'struct' => !$onlyData
			];

			$f = fopen($dump . '.tmp', 'w');
			fwrite($f, json_encode($meta) . PHP_EOL);

			$tables = $db->getTables();

			foreach ($tables as $table) {

				$io->text('Processing table: ' . $table);

				fwrite($f, json_encode([
					'table' => $table,
					'struct' => !$onlyData ? $db->showCreateTable($table) : null
				]) . PHP_EOL);

				if(!$onlyStruct) {

					$pager = Pager::create($db, 1, 1000);
					$pager->sql('SELECT ** FROM ' . $db->escapeId($table));
					$pager->order('ORDER BY 1');
					$pager->init();

					foreach ($pager as $page => $pagedData) {
						foreach ($pagedData->data as $idx => $item) {
							if($page === 1 && $idx === 0) {
								fwrite($f, json_encode([
									'keys' => array_keys($item),
								]) . PHP_EOL);
							}
							fwrite($f, json_encode(array_values($item)) . PHP_EOL);
						}
					}
				}

			}

			fwrite($f, json_encode(['end' => $sig]));
			fclose($f);

			rename("{$dump}.tmp", $dump);

			$time = microtime(true) - $start;
			$io->success('Done in ' . sprintf('%.2f', $time) . 's');
			exit;

		} catch (Throwable $e) {
			$io->error($e->getMessage());
			exit(1);
		}


	}

}