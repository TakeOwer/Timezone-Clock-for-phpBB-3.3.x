<?php
/**
 *
 * Timezone Clock. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano <https://netshadows.de>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace salvocortesiano\timezoneclock\core;

/**
 * Search engine of the city picker. It searches, in this order:
 *  - the GeoNames catalogue (when imported): every city of the world,
 *    also by Italian/alternative name ("Mosca", "Pechino"...)
 *  - countries: "Brasile" returns every zone of Brazil
 *  - the IANA zones themselves ("Sao Paulo", "Europe/Rome")
 */
class catalog
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var zones */
	protected $zones;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var string */
	protected $geo_table;

	public function __construct(\phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, zones $zones, \phpbb\language\language $language, $geo_table)
	{
		$this->config = $config;
		$this->db = $db;
		$this->zones = $zones;
		$this->language = $language;
		$this->geo_table = $geo_table;
	}

	public function country_name($cc)
	{
		$key = 'TZC_CC_' . $cc;

		return ($cc !== '' && $this->language->is_set($key)) ? $this->language->lang($key) : $cc;
	}

	/**
	 * @return array list of results
	 */
	public function search($query, $limit = 25)
	{
		$this->language->add_lang('countries', 'salvocortesiano/timezoneclock');

		$query = trim(preg_replace('/\s+/u', ' ', (string) $query));
		if (utf8_strlen($query) < 2)
		{
			return [];
		}

		$results = [];
		$seen = [];
		$now = tzdata::now_ms();

		// 1) GeoNames cities
		$gen = (int) $this->config['tzc_geo_gen'];
		if ($gen > 0)
		{
			$any = $this->db->get_any_char();
			$like_start = $this->db->sql_like_expression($query . $any);
			$like_alt = $this->db->sql_like_expression($any . ',' . $query . $any);

			$sql = 'SELECT * FROM ' . $this->geo_table . '
				WHERE geo_gen = ' . $gen . '
					AND (geo_name ' . $like_start . ' OR geo_ascii ' . $like_start . ' OR geo_alt ' . $like_alt . ')
				ORDER BY geo_pop DESC';
			$result = $this->db->sql_query_limit($sql, $limit);
			while ($row = $this->db->sql_fetchrow($result))
			{
				$results[] = [
					'type'	=> 'city',
					'n'		=> $this->best_name($row, $query),
					'cc'	=> $row['geo_cc'],
					'cn'	=> $this->country_name($row['geo_cc']),
					'z'		=> $row['geo_zone'],
					'lat'	=> $row['geo_lat'],
					'lon'	=> $row['geo_lon'],
					'pop'	=> (int) $row['geo_pop'],
				];
				$seen[$row['geo_zone'] . '|' . utf8_strtolower($row['geo_name'])] = true;
			}
			$this->db->sql_freeresult($result);
		}

		// 2) countries and 3) IANA zones
		$needle = utf8_strtolower($query);
		$zone_hits = [];
		$all = $this->zones->all();

		$country_hits = [];
		foreach ($this->country_codes() as $cc)
		{
			$name = utf8_strtolower($this->country_name($cc));
			if ($cc === strtoupper($query) || strpos($name, $needle) === 0 || ($needle !== '' && utf8_strlen($needle) > 3 && strpos($name, $needle) !== false))
			{
				$country_hits[$cc] = true;
			}
		}

		foreach ($all as $name => $row)
		{
			$city = utf8_strtolower(tzdata::zone_city($name));
			$codes = $row['zone_countries'] !== '' ? explode(',', $row['zone_countries']) : [];
			$by_country = array_intersect_key($country_hits, array_flip($codes));
			$by_name = (strpos($city, $needle) === 0 || (strlen($query) >= 3 && stripos($name, $query) !== false));

			if (!$by_name && !$by_country)
			{
				continue;
			}

			// aliases only when they match by name (Europe/Vatican), not by country
			if ($row['zone_alias'] !== '' && !$by_name)
			{
				continue;
			}

			$key = $name . '|' . $city;
			if (isset($seen[$key]))
			{
				continue;
			}

			$cc = $by_country ? key($by_country) : ($codes ? $codes[0] : '');
			$zone_hits[] = [
				'type'	=> 'zone',
				'n'		=> tzdata::zone_city($name),
				'cc'	=> $cc,
				'cn'	=> $cc !== '' ? $this->country_name($cc) : '',
				'z'		=> $name,
				'lat'	=> $row['zone_lat'],
				'lon'	=> $row['zone_lon'],
				'pop'	=> (int) $row['zone_population'],
			];
		}

		usort($zone_hits, function ($a, $b) {
			return $b['pop'] - $a['pop'];
		});

		$results = array_merge($results, array_slice($zone_hits, 0, max(10, $limit - count($results))));

		// Current offset of each result
		$this->zones->preload(array_column($results, 'z'));
		foreach ($results as $i => $r)
		{
			$t = $this->zones->transitions($r['z']);
			if (!$t)
			{
				unset($results[$i]);
				continue;
			}
			$e = tzdata::entry_at($t, $now);
			$results[$i]['off'] = 'UTC' . tzdata::format_offset($e[1]);
			$results[$i]['abbr'] = $e[2];
			// lets the live preview show the city before saving
			$results[$i]['t'] = tzdata::cap(tzdata::trim($t, $now - 86400000), $now + 400 * 86400000);
		}

		return array_values($results);
	}

	/**
	 * Prefer the alternative name typed by the user ("Mosca" rather than "Moscow")
	 */
	protected function best_name(array $row, $query)
	{
		$needle = utf8_strtolower($query);
		if (strpos(utf8_strtolower($row['geo_name']), $needle) === 0 || strpos(utf8_strtolower($row['geo_ascii']), $needle) === 0)
		{
			return $row['geo_name'];
		}

		foreach (explode(',', trim($row['geo_alt'], ',')) as $alt)
		{
			if (strpos(utf8_strtolower($alt), $needle) === 0)
			{
				return $alt;
			}
		}

		return $row['geo_name'];
	}

	protected function country_codes()
	{
		$codes = [];
		foreach (array_keys($this->language->get_lang_array()) as $key)
		{
			if (strpos($key, 'TZC_CC_') === 0)
			{
				$codes[] = substr($key, 7);
			}
		}

		return $codes;
	}
}
