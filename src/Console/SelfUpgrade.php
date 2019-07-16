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

		$json = json_decode(file_get_contents('https://api.github.com/repos/yauhenko/mysql-tools/releases/latest',
			false, stream_context_create(['http' => ['header' => "User-Agent: Yauhenko\r\n"]])
		));

		if(!$json) {
			$io->error('Failed to fetch version info');
			exit(1);
		}

		$version = ltrim($json->tag_name, 'v');

		if(!$version) {
			$io->error('Failed to resolve latest version');
			exit(1);
		}

		if(version_compare($version, VERSION, '>')) {
			$io->success('Found new version: v' . $version);

			$asset = null;
			foreach ($json->assets as $a) {
				if($a->name === 'mysql-tools.phar') {
					$asset = $a;
					break;
				}
			}

			if(!$asset) {
				$io->error('Download link unavailable now');
				exit(2);
			}

			$url = $asset->browser_download_url;
			$io->text('Downloading ' . $url);
			$phar = file_get_contents($url);

			$tmp = '/tmp/mysql-tools-v' . $version . '.phar';
			if(file_put_contents($tmp, $phar) !== $asset->size) {
				$io->error('Failed to create temporary file: ' . $tmp);
				exit(1);
			}
			unset($phar);
			$source = realpath($_SERVER['argv'][0]);
			$io->text('Replacing: ' . $source);
			if(chmod($tmp, 0755) && rename($tmp, $source)) {
				$io->success('Upgraded: v' . VERSION . ' → v' . $version);
				exit(0);
			} else {
				$io->error('Upgrade failed');
				exit(1);
			}
		} else {
			$io->success('Latest version already installed');
			exit;
		}

	}

}
