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
 * Read access to the stored IANA zones
 */
class zones
{
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var string */
	protected $zones_table;

	/** @var array name => row */
	protected $rows = [];

	public function __construct(\phpbb\db\driver\driver_interface $db, $zones_table)
	{
		$this->db = $db;
		$this->zones_table = $zones_table;
	}

	/**
	 * Load (and memoise) the given zones, following aliases
	 */
	protected function fetch(array $names)
	{
		$missing = array_values(array_diff(array_unique($names), array_keys($this->rows)));
		if (!$missing)
		{
			return;
		}

		sort($missing);
		$sql = 'SELECT * FROM ' . $this->zones_table . ' WHERE ' . $this->db->sql_in_set('zone_name', $missing);
		$result = $this->db->sql_query($sql, 3600);
		$aliases = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$this->rows[$row['zone_name']] = $row;
			if ($row['zone_alias'] !== '')
			{
				$aliases[] = $row['zone_alias'];
			}
		}
		$this->db->sql_freeresult($result);

		foreach ($missing as $name)
		{
			if (!isset($this->rows[$name]))
			{
				$this->rows[$name] = false;
			}
		}

		if ($aliases)
		{
			$this->fetch($aliases);
		}
	}

	/**
	 * Row of a zone (aliases resolved to their target, keeping the alias coordinates)
	 *
	 * @return array|false
	 */
	public function get($name)
	{
		$this->fetch([$name]);
		$row = $this->rows[$name];
		if (!$row)
		{
			return false;
		}

		if ($row['zone_alias'] !== '')
		{
			$target = $this->rows[$row['zone_alias']] ?? false;
			if (!$target || $target['zone_alias'] !== '')
			{
				return false;
			}
			$target['zone_lat'] = $row['zone_lat'] !== '' ? $row['zone_lat'] : $target['zone_lat'];
			$target['zone_lon'] = $row['zone_lon'] !== '' ? $row['zone_lon'] : $target['zone_lon'];
			$target['zone_countries'] = $row['zone_countries'] !== '' ? $row['zone_countries'] : $target['zone_countries'];
			$target['requested'] = $name;
			return $target;
		}

		$row['requested'] = $name;
		return $row;
	}

	/**
	 * Preload several zones with a single query
	 */
	public function preload(array $names)
	{
		if ($names)
		{
			$this->fetch($names);
		}
	}

	/**
	 * Transitions of a zone, or false if unknown
	 */
	public function transitions($name)
	{
		$row = $this->get($name);
		if (!$row)
		{
			return false;
		}

		$t = json_decode($row['zone_data'], true);
		return is_array($t) && $t ? $t : false;
	}

	public function exists($name)
	{
		return (bool) $this->get($name);
	}

	/**
	 * All rows (for the ACP zone browser and the city search fallback)
	 */
	public function all()
	{
		$out = [];
		$sql = 'SELECT * FROM ' . $this->zones_table . ' ORDER BY zone_name ASC';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$out[$row['zone_name']] = $row;
			$this->rows[$row['zone_name']] = $row;
		}
		$this->db->sql_freeresult($result);

		return $out;
	}

	/**
	 * @return array [canonical, aliases]
	 */
	public function count()
	{
		$canonical = $aliases = 0;
		$sql = "SELECT SUM(CASE WHEN zone_alias = '' THEN 1 ELSE 0 END) AS c,
				SUM(CASE WHEN zone_alias <> '' THEN 1 ELSE 0 END) AS a
			FROM " . $this->zones_table;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if ($row)
		{
			$canonical = (int) $row['c'];
			$aliases = (int) $row['a'];
		}

		return [$canonical, $aliases];
	}
}
