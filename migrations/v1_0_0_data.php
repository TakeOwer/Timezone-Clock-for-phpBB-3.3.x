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

use salvocortesiano\timezoneclock\core\zone_importer;

/**
 * Loads the IANA time zones shipped with the extension and a starter set
 * of cities, so the bar works immediately after a clean install.
 */
class v1_0_0_data extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\salvocortesiano\timezoneclock\migrations\v1_0_0_config'];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'import_zones']]],
			['custom', [[$this, 'seed_cities']]],
		];
	}

	protected function ext_path()
	{
		return $this->phpbb_root_path . 'ext/salvocortesiano/timezoneclock/';
	}

	public function import_zones()
	{
		$importer = new zone_importer($this->db, $this->table_prefix . 'tzc_zones', $this->ext_path());
		$result = $importer->import_bundled();

		if (is_array($result))
		{
			$this->config->set('tzc_tzdata_version', $result['version']);
			$this->config->set('tzc_tzdata_updated', time());

			// the online check runs at the first cron after installation
			$this->config->set('tzc_cron_last', 0);
		}
	}

	public function seed_cities()
	{
		$table = $this->table_prefix . 'tzc_cities';

		$result = $this->db->sql_query('SELECT COUNT(city_id) AS total FROM ' . $table);
		$total = (int) $this->db->sql_fetchfield('total');
		$this->db->sql_freeresult($result);
		if ($total)
		{
			return;
		}

		$file = $this->ext_path() . 'data/default_cities.json';
		$cities = is_readable($file) ? json_decode(file_get_contents($file), true) : [];
		$lang = (strpos((string) $this->config['default_lang'], 'it') === 0) ? 'it' : 'en';

		$rows = [];
		foreach ((array) $cities as $i => $c)
		{
			$rows[] = [
				'city_name'		=> isset($c[$lang]) ? $c[$lang] : $c['en'],
				'city_cc'		=> $c['cc'],
				'city_zone'		=> $c['z'],
				'city_lat'		=> '',
				'city_lon'		=> '',
				'city_mobile'	=> 1,
				'city_order'	=> $i,
			];
		}

		if ($rows)
		{
			$this->db->sql_multi_insert($table, $rows);
		}
	}
}
