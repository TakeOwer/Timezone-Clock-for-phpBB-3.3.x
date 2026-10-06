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
 * Writes the IANA zones into the database. It has no service dependencies
 * so it can also be used by the migrations while the extension is being
 * enabled.
 */
class zone_importer
{
	/** Transitions older than this are not stored (ms) */
	const KEEP_PAST_MS = 63072000000; // 2 years
	/** Transitions further in the future are not stored (ms) */
	const KEEP_FUTURE_MS = 631152000000; // 20 years

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var string */
	protected $zones_table;

	/** @var string */
	protected $ext_path;

	/** @var array|null */
	protected $coords;

	public function __construct(\phpbb\db\driver\driver_interface $db, $zones_table, $ext_path)
	{
		$this->db = $db;
		$this->zones_table = $zones_table;
		$this->ext_path = rtrim($ext_path, '/') . '/';
	}

	/**
	 * Decode and validate a packed data file
	 *
	 * @return array|string decoded data or an error language key
	 */
	public function decode($raw)
	{
		if (substr($raw, 0, 2) === "\x1f\x8b")
		{
			$raw = function_exists('gzdecode') ? @gzdecode($raw) : false;
			if ($raw === false)
			{
				return 'TZC_ERR_GZIP';
			}
		}

		$data = json_decode($raw, true);
		if (!is_array($data) || empty($data['version']) || empty($data['zones']) || !is_array($data['zones']))
		{
			return 'TZC_ERR_TZDATA_INVALID';
		}

		if (count($data['zones']) < 100)
		{
			return 'TZC_ERR_TZDATA_INVALID';
		}

		// Sanity check: the data must decode correctly
		if (tzdata::unpack_zone($data['zones'][0]) === false)
		{
			return 'TZC_ERR_TZDATA_INVALID';
		}

		$data['links'] = isset($data['links']) && is_array($data['links']) ? $data['links'] : [];
		$data['countries'] = isset($data['countries']) && is_array($data['countries']) ? $data['countries'] : [];

		return $data;
	}

	/**
	 * zone => list of country codes
	 */
	public function zone_countries(array $data)
	{
		$map = [];
		foreach ($data['countries'] as $line)
		{
			$parts = explode('|', $line);
			if (count($parts) < 2)
			{
				continue;
			}
			foreach (explode(' ', $parts[1]) as $zone)
			{
				if ($zone !== '')
				{
					$map[$zone][] = $parts[0];
				}
			}
		}

		return $map;
	}

	protected function coords()
	{
		if ($this->coords === null)
		{
			$file = $this->ext_path . 'data/zone_coords.json';
			$this->coords = is_readable($file) ? (array) json_decode(file_get_contents($file), true) : [];
		}

		return $this->coords;
	}

	/**
	 * Current stored rows: name => [alias, data]
	 */
	public function load_existing()
	{
		$existing = [];
		$sql = 'SELECT zone_name, zone_alias, zone_data FROM ' . $this->zones_table;
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$existing[$row['zone_name']] = $row;
		}
		$this->db->sql_freeresult($result);

		return $existing;
	}

	/**
	 * Import a slice of the zones
	 *
	 * @param array $data       decoded data
	 * @param int   $from       first index
	 * @param int   $count      how many zones
	 * @param array $changes    collects [added[], changed[], seen[]]
	 * @return int  next index
	 */
	public function import_zones(array $data, $from, $count, array &$changes)
	{
		$now = tzdata::now_ms();
		$keep_from = $now - self::KEEP_PAST_MS;
		$countries = $this->zone_countries($data);
		$coords = $this->coords();
		$existing = $this->load_existing();
		$total = count($data['zones']);
		$end = min($total, $from + $count);

		for ($i = $from; $i < $end; $i++)
		{
			$zone = tzdata::unpack_zone($data['zones'][$i]);
			if ($zone === false)
			{
				continue;
			}

			$name = $zone['name'];
			$transitions = tzdata::build_transitions($zone, $keep_from, $now + self::KEEP_FUTURE_MS);
			$row = [
				'zone_alias'		=> '',
				'zone_countries'	=> isset($countries[$name]) ? implode(',', array_unique($countries[$name])) : '',
				'zone_lat'			=> isset($coords[$name]) ? (string) $coords[$name][0] : '',
				'zone_lon'			=> isset($coords[$name]) ? (string) $coords[$name][1] : '',
				'zone_population'	=> (int) $zone['population'],
				'zone_data'			=> json_encode($transitions),
				'zone_updated'		=> time(),
			];

			$changes['seen'][] = $name;

			if (isset($existing[$name]))
			{
				$old = $existing[$name];
				if ($old['zone_alias'] === '')
				{
					$old_t = json_decode($old['zone_data'], true);
					if (is_array($old_t) && tzdata::fingerprint($old_t, $now) !== tzdata::fingerprint($transitions, $now))
					{
						$changes['changed'][] = $name;
					}
				}

				$sql = 'UPDATE ' . $this->zones_table . '
					SET ' . $this->db->sql_build_array('UPDATE', $row) . "
					WHERE zone_name = '" . $this->db->sql_escape($name) . "'";
				$this->db->sql_query($sql);
			}
			else
			{
				$changes['added'][] = $name;
				$row['zone_name'] = $name;
				$this->db->sql_query('INSERT INTO ' . $this->zones_table . ' ' . $this->db->sql_build_array('INSERT', $row));
			}
		}

		return $end;
	}

	/**
	 * Import the links (aliases) such as Europe/Vatican -> Europe/Rome
	 */
	public function import_links(array $data, array &$changes)
	{
		$countries = $this->zone_countries($data);
		$coords = $this->coords();
		$existing = $this->load_existing();
		$canonical = array_flip(isset($changes['seen']) ? $changes['seen'] : []);

		foreach ($data['links'] as $line)
		{
			$parts = explode('|', $line);
			if (count($parts) !== 2 || !isset($canonical[$parts[0]]) || isset($canonical[$parts[1]]))
			{
				continue;
			}

			list($target, $alias) = $parts;
			$row = [
				'zone_alias'		=> $target,
				'zone_countries'	=> isset($countries[$alias]) ? implode(',', array_unique($countries[$alias])) : '',
				'zone_lat'			=> isset($coords[$alias]) ? (string) $coords[$alias][0] : '',
				'zone_lon'			=> isset($coords[$alias]) ? (string) $coords[$alias][1] : '',
				'zone_population'	=> 0,
				'zone_data'			=> '',
				'zone_updated'		=> time(),
			];

			$changes['seen'][] = $alias;

			if (isset($existing[$alias]))
			{
				$sql = 'UPDATE ' . $this->zones_table . '
					SET ' . $this->db->sql_build_array('UPDATE', $row) . "
					WHERE zone_name = '" . $this->db->sql_escape($alias) . "'";
			}
			else
			{
				$row['zone_name'] = $alias;
				$sql = 'INSERT INTO ' . $this->zones_table . ' ' . $this->db->sql_build_array('INSERT', $row);
			}
			$this->db->sql_query($sql);
		}
	}

	/**
	 * Remove zones that no longer exist in the new data
	 *
	 * @return array removed zone names
	 */
	public function remove_missing(array $seen)
	{
		$seen = array_flip($seen);
		$removed = [];

		foreach (array_keys($this->load_existing()) as $name)
		{
			if (!isset($seen[$name]))
			{
				$removed[] = $name;
			}
		}

		if ($removed)
		{
			$sql = 'DELETE FROM ' . $this->zones_table . '
				WHERE ' . $this->db->sql_in_set('zone_name', $removed);
			$this->db->sql_query($sql);
		}

		return $removed;
	}

	/**
	 * One-shot import (used by the migration with the bundled data)
	 *
	 * @return string|array version or error key
	 */
	public function import_bundled()
	{
		$file = $this->ext_path . 'data/tzdata.json.gz';
		if (!is_readable($file))
		{
			return 'TZC_ERR_TZDATA_INVALID';
		}

		$data = $this->decode(file_get_contents($file));
		if (!is_array($data))
		{
			return $data;
		}

		$changes = ['added' => [], 'changed' => [], 'seen' => []];
		$this->import_zones($data, 0, count($data['zones']), $changes);
		$this->import_links($data, $changes);
		$this->remove_missing($changes['seen']);

		return ['version' => $data['version'], 'zones' => count($data['zones'])];
	}
}
