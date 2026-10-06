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
 * Per-user preferences. Only the values that differ from the board
 * defaults are stored, so changing a default in the ACP still reaches
 * every user who did not personalise that option.
 */
class prefs
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var string */
	protected $users_table;

	/** @var array */
	protected $cache = [];

	public function __construct(\phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, $users_table)
	{
		$this->config = $config;
		$this->db = $db;
		$this->users_table = $users_table;
	}

	/**
	 * Raw stored row for a user
	 *
	 * @return array ['prefs' => [], 'cities' => []]
	 */
	public function load($user_id)
	{
		$user_id = (int) $user_id;
		if (isset($this->cache[$user_id]))
		{
			return $this->cache[$user_id];
		}

		$out = ['prefs' => [], 'cities' => []];

		if ($user_id > ANONYMOUS)
		{
			$sql = 'SELECT tzc_prefs, tzc_cities
				FROM ' . $this->users_table . '
				WHERE user_id = ' . $user_id;
			$result = $this->db->sql_query($sql);
			$row = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			if ($row)
			{
				$prefs = json_decode($row['tzc_prefs'], true);
				$cities = json_decode($row['tzc_cities'], true);
				$out['prefs'] = is_array($prefs) ? $prefs : [];
				$out['cities'] = is_array($cities) ? $cities : [];
			}
		}

		return $this->cache[$user_id] = $out;
	}

	public function save($user_id, array $prefs, array $cities)
	{
		$user_id = (int) $user_id;
		$row = [
			'tzc_prefs'		=> json_encode($prefs),
			'tzc_cities'	=> json_encode(array_values($cities)),
		];

		$sql = 'SELECT user_id FROM ' . $this->users_table . ' WHERE user_id = ' . $user_id;
		$result = $this->db->sql_query($sql);
		$exists = (bool) $this->db->sql_fetchfield('user_id');
		$this->db->sql_freeresult($result);

		if ($exists)
		{
			$sql = 'UPDATE ' . $this->users_table . ' SET ' . $this->db->sql_build_array('UPDATE', $row) . ' WHERE user_id = ' . $user_id;
		}
		else
		{
			$row['user_id'] = $user_id;
			$sql = 'INSERT INTO ' . $this->users_table . ' ' . $this->db->sql_build_array('INSERT', $row);
		}
		$this->db->sql_query($sql);

		unset($this->cache[$user_id]);
	}

	public function delete(array $user_ids)
	{
		if ($user_ids)
		{
			$this->db->sql_query('DELETE FROM ' . $this->users_table . ' WHERE ' . $this->db->sql_in_set('user_id', array_map('intval', $user_ids)));
		}
	}

	/**
	 * Effective options for a user: board values + allowed user overrides
	 *
	 * @return array options + 'show' + 'source'
	 */
	public function effective($user_id)
	{
		$opts = options::get_all($this->config);
		$opts['show'] = 1;
		$opts['source'] = 'admin';

		if (!$opts['ucp_enable'])
		{
			return $opts;
		}

		$stored = $this->load($user_id);
		$prefs = $stored['prefs'];

		foreach (options::ucp_keys() as $key)
		{
			if (isset($prefs[$key]))
			{
				$clean = options::sanitize($key, $prefs[$key]);
				if ($clean !== null)
				{
					$opts[$key] = $clean;
				}
			}
		}

		if ($opts['ucp_hide'] && isset($prefs['show']))
		{
			$opts['show'] = $prefs['show'] ? 1 : 0;
		}

		if ($opts['ucp_cities'] && isset($prefs['source']) && in_array($prefs['source'], ['admin', 'mine', 'both'], true))
		{
			$opts['source'] = $prefs['source'];
		}

		return $opts;
	}

	/**
	 * Normalise a list of cities coming from a form or from storage
	 */
	public static function clean_cities(array $cities, $max = 100)
	{
		$out = [];
		foreach ($cities as $c)
		{
			if (!is_array($c) || empty($c['z']) || !preg_match('#^[A-Za-z0-9_+\-/]{1,64}$#', $c['z']))
			{
				continue;
			}

			$name = isset($c['n']) ? trim((string) $c['n']) : '';
			$out[] = [
				'n'		=> utf8_substr($name !== '' ? $name : tzdata::zone_city($c['z']), 0, 100),
				'cc'	=> (isset($c['cc']) && preg_match('/^[A-Z]{2}$/', $c['cc'])) ? $c['cc'] : '',
				'z'		=> $c['z'],
				'lat'	=> (isset($c['lat']) && is_numeric($c['lat'])) ? (string) round((float) $c['lat'], 4) : '',
				'lon'	=> (isset($c['lon']) && is_numeric($c['lon'])) ? (string) round((float) $c['lon'], 4) : '',
				'm'		=> isset($c['m']) ? ((int) $c['m'] ? 1 : 0) : 1,
			];

			if (count($out) >= $max)
			{
				break;
			}
		}

		return $out;
	}
}
