<?php
/**
 *
 * Timezone Clock. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano <https://netshadows.de>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace salvocortesiano\timezoneclock\cron\task;

use salvocortesiano\timezoneclock\core\options;

/**
 * Automatic update of the time zone rules (and optionally of the cities).
 * Long jobs are split across several cron runs.
 */
class update extends \phpbb\cron\task\base
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \salvocortesiano\timezoneclock\core\updater */
	protected $updater;

	public function __construct(\phpbb\config\config $config, $updater, \phpbb\language\language $language)
	{
		$this->config = $config;
		$this->updater = $updater;
		$updater->set_language($language);
	}

	public function is_runnable()
	{
		return (bool) options::get($this->config, 'cron_enable');
	}

	public function should_run()
	{
		return $this->updater->cron_due();
	}

	public function run()
	{
		// a web-triggered cron must stay short, the system cron can work longer
		$budget = $this->config['use_system_cron'] || PHP_SAPI === 'cli' ? 50 : 20;
		$this->updater->run_cron($budget);
	}
}
