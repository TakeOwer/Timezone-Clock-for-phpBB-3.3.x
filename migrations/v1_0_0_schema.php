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

class v1_0_0_schema extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return $this->db_tools->sql_table_exists($this->table_prefix . 'tzc_zones');
	}

	public static function depends_on()
	{
		return ['\phpbb\db\migration\data\v330\v330'];
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'tzc_cities' => [
					'COLUMNS' => [
						'city_id'		=> ['UINT', null, 'auto_increment'],
						'city_name'		=> ['VCHAR_UNI:100', ''],
						'city_cc'		=> ['VCHAR:2', ''],
						'city_zone'		=> ['VCHAR:64', ''],
						'city_lat'		=> ['VCHAR:16', ''],
						'city_lon'		=> ['VCHAR:16', ''],
						'city_mobile'	=> ['BOOL', 1],
						'city_order'	=> ['UINT', 0],
					],
					'PRIMARY_KEY' => 'city_id',
					'KEYS' => [
						'city_order' => ['INDEX', 'city_order'],
					],
				],
				$this->table_prefix . 'tzc_zones' => [
					'COLUMNS' => [
						'zone_name'			=> ['VCHAR:64', ''],
						'zone_alias'		=> ['VCHAR:64', ''],
						'zone_countries'	=> ['VCHAR:100', ''],
						'zone_lat'			=> ['VCHAR:16', ''],
						'zone_lon'			=> ['VCHAR:16', ''],
						'zone_population'	=> ['BINT', 0],
						'zone_data'			=> ['MTEXT', ''],
						'zone_updated'		=> ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => 'zone_name',
					'KEYS' => [
						'zone_alias' => ['INDEX', 'zone_alias'],
					],
				],
				$this->table_prefix . 'tzc_geo' => [
					'COLUMNS' => [
						'geo_id'		=> ['UINT', null, 'auto_increment'],
						'geo_gen'		=> ['UINT', 0],
						'geo_ref'		=> ['UINT', 0],
						'geo_name'		=> ['VCHAR_UNI:100', ''],
						'geo_ascii'		=> ['VCHAR:100', ''],
						'geo_alt'		=> ['TEXT_UNI', ''],
						'geo_lat'		=> ['VCHAR:16', ''],
						'geo_lon'		=> ['VCHAR:16', ''],
						'geo_cc'		=> ['VCHAR:2', ''],
						'geo_admin'		=> ['VCHAR:20', ''],
						'geo_pop'		=> ['BINT', 0],
						'geo_zone'		=> ['VCHAR:64', ''],
					],
					'PRIMARY_KEY' => 'geo_id',
					'KEYS' => [
						'geo_gen_pop'	=> ['INDEX', ['geo_gen', 'geo_pop']],
						'geo_name'		=> ['INDEX', 'geo_name'],
						'geo_ascii'		=> ['INDEX', 'geo_ascii'],
					],
				],
				$this->table_prefix . 'tzc_users' => [
					'COLUMNS' => [
						'user_id'		=> ['UINT', 0],
						'tzc_prefs'		=> ['TEXT_UNI', ''],
						'tzc_cities'	=> ['MTEXT_UNI', ''],
					],
					'PRIMARY_KEY' => 'user_id',
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'tzc_cities',
				$this->table_prefix . 'tzc_zones',
				$this->table_prefix . 'tzc_geo',
				$this->table_prefix . 'tzc_users',
			],
		];
	}
}
