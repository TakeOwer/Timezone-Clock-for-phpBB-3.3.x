<?php
/**
 *
 * Timezone Clock. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano <https://netshadows.de>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace salvocortesiano\timezoneclock\migrations;

use salvocortesiano\timezoneclock\core\options;

class v1_0_0_config extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['tzc_enable']);
	}

	public static function depends_on()
	{
		return ['\salvocortesiano\timezoneclock\migrations\v1_0_0_schema'];
	}

	public function update_data()
	{
		$data = [];

		// every setting of the option registry
		foreach (options::all() as $key => $def)
		{
			$data[] = ['config.add', ['tzc_' . $key, $def['default']]];
		}

		// internal state
		$internal = [
			'tzc_tzdata_version'	=> '',
			'tzc_tz_pkg'			=> '',
			'tzc_tzdata_updated'	=> 0,
			'tzc_tz_checked'		=> 0,
			'tzc_cron_last'			=> 0,
			'tzc_cron_cities_last'	=> 0,
			'tzc_geo_gen'			=> 0,
			'tzc_geo_count'			=> 0,
			'tzc_geo_updated'		=> 0,
			'tzc_geo_loaded'		=> '',
			'tzc_cache_gen'			=> 0,
			'tzc_last_error'		=> '',
		];
		foreach ($internal as $name => $value)
		{
			$data[] = ['config.add', [$name, $value]];
		}
		$data[] = ['config.add', ['tzc_job_lock', '0', true]];

		$data[] = ['config_text.add', ['tzc_job', '']];
		$data[] = ['config_text.add', ['tzc_changelog', '[]']];

		// ACP
		$data[] = ['module.add', ['acp', 'ACP_CAT_DOT_MODS', 'ACP_TZC_TITLE']];
		$data[] = ['module.add', ['acp', 'ACP_TZC_TITLE', [
			'module_basename'	=> '\salvocortesiano\timezoneclock\acp\main_module',
			'modes'				=> ['settings', 'cities', 'database', 'checkup'],
		]]];

		// UCP: Board preferences
		$data[] = ['module.add', ['ucp', 'UCP_PREFS', [
			'module_basename'	=> '\salvocortesiano\timezoneclock\ucp\main_module',
			'modes'				=> ['settings'],
		]]];

		return $data;
	}

	public function revert_data()
	{
		$data = [];
		foreach (array_keys(options::all()) as $key)
		{
			$data[] = ['config.remove', ['tzc_' . $key]];
		}
		foreach (['tzc_tzdata_version', 'tzc_tz_pkg', 'tzc_tzdata_updated', 'tzc_tz_checked', 'tzc_cron_last', 'tzc_cron_cities_last', 'tzc_geo_gen', 'tzc_geo_count', 'tzc_geo_updated', 'tzc_geo_loaded', 'tzc_cache_gen', 'tzc_last_error', 'tzc_job_lock'] as $name)
		{
			$data[] = ['config.remove', [$name]];
		}
		$data[] = ['config_text.remove', ['tzc_job']];
		$data[] = ['config_text.remove', ['tzc_changelog']];

		return $data;
	}
}
