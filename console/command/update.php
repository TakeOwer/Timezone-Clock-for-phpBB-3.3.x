<?php
/**
 *
 * Timezone Clock. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano <https://netshadows.de>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace salvocortesiano\timezoneclock\console\command;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * php bin/phpbbcli.php tzc:update [--cities|--all] [--force]
 */
class update extends \phpbb\console\command\command
{
	/** @var \salvocortesiano\timezoneclock\core\updater */
	protected $updater;

	/** @var \phpbb\language\language */
	protected $language;

	public function __construct(\phpbb\user $user, \phpbb\language\language $language, $updater)
	{
		$this->language = $language;
		$this->updater = $updater;
		$language->add_lang('acp', 'salvocortesiano/timezoneclock');
		$updater->set_language($language);
		parent::__construct($user);
	}

	protected function configure()
	{
		$this->setName('tzc:update')
			->setDescription($this->language->lang('TZC_CLI_DESCRIPTION'))
			->addOption('cities', 'c', InputOption::VALUE_NONE, $this->language->lang('TZC_CLI_OPT_CITIES'))
			->addOption('all', 'a', InputOption::VALUE_NONE, $this->language->lang('TZC_CLI_OPT_ALL'))
			->addOption('force', 'f', InputOption::VALUE_NONE, $this->language->lang('TZC_CLI_OPT_FORCE'));
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$io = new SymfonyStyle($input, $output);
		$type = $input->getOption('all') ? 'all' : ($input->getOption('cities') ? 'cities' : 'tzdata');

		$state = $this->updater->start($type, 'cli', (bool) $input->getOption('force'));
		if (!empty($state['error']))
		{
			$io->error($this->language->lang($state['error']));
			return 1;
		}

		$bar = new ProgressBar($output, 100);
		$bar->setFormat(' %current%% [%bar%] %message%');
		$bar->setMessage('');
		$bar->start();

		$language = $this->language;
		$state = $this->updater->run(0, function ($state) use ($bar, $language) {
			$bar->setProgress((int) floor($state['percent']));
			if (!empty($state['message']))
			{
				$bar->setMessage(strip_tags($language->lang($state['message'], ...array_values((array) $state['args']))));
			}
		});
		$bar->finish();
		$output->writeln('');

		if (!empty($state['error']))
		{
			$io->error(strip_tags($this->updater->error_text($state['error'], (array) $state['error_args'])));
			return 1;
		}

		foreach ((array) $state['results'] as $r)
		{
			if ($r['task'] === 'cities')
			{
				$io->success($this->language->lang('TZC_RESULT_CITIES', $r['count'], $r['dataset']));
			}
			else if ($r['status'] === 'uptodate')
			{
				$io->success($this->language->lang('TZC_RESULT_UPTODATE', $r['version'], $r['pkg']));
			}
			else
			{
				$io->success($this->language->lang('TZC_RESULT_TZDATA', $r['version'], $r['zones']) . ' ' .
					($r['changed'] ? $this->language->lang('TZC_RESULT_CHANGED', count($r['changed']), implode(', ', $r['changed'])) : $this->language->lang('TZC_RESULT_NOCHANGE')));
			}
		}

		return 0;
	}
}
