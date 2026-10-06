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
 * ACP "Check-up": tests every part of the extension and reports problems
 */
class checkup
{
	const OK = 'ok';
	const WARN = 'warn';
	const ERROR = 'error';
	const INFO = 'info';

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\db\tools\tools_interface */
	protected $db_tools;

	/** @var zones */
	protected $zones;

	/** @var bar_builder */
	protected $builder;

	/** @var updater */
	protected $updater;

	/** @var downloader */
	protected $downloader;

	/** @var string */
	protected $root_path;

	/** @var array */
	protected $tables;

	/** @var array */
	protected $groups = [];

	public function __construct(\phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\db\tools\tools_interface $db_tools, zones $zones, bar_builder $builder, updater $updater, downloader $downloader, $root_path, $cities_table, $zones_table, $geo_table, $users_table)
	{
		$this->config = $config;
		$this->db = $db;
		$this->db_tools = $db_tools;
		$this->zones = $zones;
		$this->builder = $builder;
		$this->updater = $updater;
		$this->downloader = $downloader;
		$this->root_path = $root_path;
		$this->tables = [
			'cities'	=> $cities_table,
			'zones'		=> $zones_table,
			'geo'		=> $geo_table,
			'users'		=> $users_table,
		];
	}

	protected function add($group, $status, $title, $detail, array $args = [])
	{
		$this->groups[$group][] = ['status' => $status, 'title' => $title, 'detail' => $detail, 'args' => $args];
	}

	/**
	 * Steps of the check-up, executed one per AJAX request by the ACP
	 *
	 * @return array step id => [group, label language key]
	 */
	public static function steps($network = true)
	{
		$steps = [
			'system'		=> ['system', 'TZC_CHK_GROUP_SYSTEM'],
			'database'		=> ['database', 'TZC_CHK_GROUP_DATABASE'],
			'tzdata'		=> ['tzdata', 'TZC_CHK_GROUP_TZDATA'],
			'bar'			=> ['bar', 'TZC_CHK_GROUP_BAR'],
			'updates'		=> ['updates', 'TZC_CHK_GROUP_UPDATES'],
		];

		if ($network)
		{
			$steps += [
				'net_registry'	=> ['network', 'TZC_CHK_NET_REGISTRY'],
				'net_primary'	=> ['network', 'TZC_CHK_NET_PRIMARY'],
				'net_alt'		=> ['network', 'TZC_CHK_NET_ALT'],
				'net_geo'		=> ['network', 'TZC_CHK_NET_GEO'],
				'net_clock'		=> ['network', 'TZC_CHK_CLOCK'],
			];
		}

		$steps['files'] = ['files', 'TZC_CHK_GROUP_FILES'];

		return $steps;
	}

	/**
	 * Run a single step
	 *
	 * @return array|false [group, items[]]
	 */
	public function run_step($step)
	{
		$steps = self::steps(true);
		if (!isset($steps[$step]))
		{
			return false;
		}

		$this->groups = [];
		if (strpos($step, 'net_') === 0)
		{
			$this->network_step(substr($step, 4));
		}
		else
		{
			$this->$step();
		}

		$group = $steps[$step][0];

		return [$group, isset($this->groups[$group]) ? $this->groups[$group] : []];
	}

	/**
	 * @param bool $network include the connectivity tests
	 * @return array group => items
	 */
	public function run($network = true)
	{
		$this->groups = [];
		$this->system();
		$this->database();
		$this->tzdata();
		$this->bar();
		$this->updates();
		if ($network)
		{
			$this->network();
		}
		$this->files();

		return $this->groups;
	}

	public static function summary(array $groups)
	{
		$sum = [self::OK => 0, self::WARN => 0, self::ERROR => 0, self::INFO => 0];
		foreach ($groups as $items)
		{
			foreach ($items as $item)
			{
				$sum[$item['status']]++;
			}
		}

		return $sum;
	}

	protected function system()
	{
		$ok = version_compare(PHP_VERSION, '7.4.0', '>=');
		$this->add('system', $ok ? self::OK : self::ERROR, 'TZC_CHK_PHP', $ok ? 'TZC_CHK_PHP_OK' : 'TZC_CHK_PHP_OLD', [PHP_VERSION]);

		$ok = phpbb_version_compare($this->config['version'], '3.3.0', '>=');
		$this->add('system', $ok ? self::OK : self::ERROR, 'TZC_CHK_PHPBB', 'TZC_CHK_VERSION_IS', [$this->config['version']]);

		$method = downloader::method();
		$this->add('system', $method ? self::OK : self::ERROR, 'TZC_CHK_HTTP', $method ? 'TZC_CHK_HTTP_' . strtoupper($method) : 'TZC_CHK_HTTP_NONE');

		$zip = class_exists('ZipArchive');
		$this->add('system', $zip ? self::OK : self::WARN, 'TZC_CHK_ZIP', $zip ? 'TZC_CHK_AVAILABLE' : 'TZC_CHK_ZIP_MISSING');

		$gz = function_exists('gzdecode');
		$this->add('system', $gz ? self::OK : self::ERROR, 'TZC_CHK_ZLIB', $gz ? 'TZC_CHK_AVAILABLE' : 'TZC_CHK_ZLIB_MISSING');

		$this->add('system', self::INFO, 'TZC_CHK_PHP_TZDB', 'TZC_CHK_PHP_TZDB_INFO', [timezone_version_get()]);

		$limit = (int) ini_get('max_execution_time');
		$step = options::get($this->config, 'step_time');
		$ok = ($limit === 0 || $limit > $step + 5);
		$this->add('system', $ok ? self::OK : self::WARN, 'TZC_CHK_TIME_LIMIT', $ok ? 'TZC_CHK_TIME_LIMIT_OK' : 'TZC_CHK_TIME_LIMIT_LOW', [(string) ($limit ?: '∞'), $step]);
	}

	protected function database()
	{
		foreach ($this->tables as $key => $table)
		{
			$ok = $this->db_tools->sql_table_exists($table);
			$this->add('database', $ok ? self::OK : self::ERROR, 'TZC_CHK_TABLE', $ok ? 'TZC_CHK_TABLE_OK' : 'TZC_CHK_TABLE_MISSING', [$table]);
		}

		$missing = [];
		foreach (array_keys(options::all()) as $key)
		{
			if (!isset($this->config['tzc_' . $key]))
			{
				$missing[] = 'tzc_' . $key;
			}
		}
		$this->add('database', $missing ? self::ERROR : self::OK, 'TZC_CHK_CONFIG', $missing ? 'TZC_CHK_CONFIG_MISSING' : 'TZC_CHK_CONFIG_OK', [implode(', ', $missing), count(options::all())]);

		$invalid = [];
		foreach (options::all() as $key => $def)
		{
			if (isset($this->config['tzc_' . $key]) && options::sanitize($key, $this->config['tzc_' . $key]) === null)
			{
				$invalid[] = 'tzc_' . $key;
			}
		}
		$this->add('database', $invalid ? self::WARN : self::OK, 'TZC_CHK_CONFIG_VALUES', $invalid ? 'TZC_CHK_CONFIG_INVALID' : 'TZC_CHK_CONFIG_VALID', [implode(', ', $invalid)]);

		if ($this->db_tools->sql_table_exists($this->tables['users']))
		{
			$result = $this->db->sql_query('SELECT COUNT(user_id) AS total FROM ' . $this->tables['users']);
			$total = (int) $this->db->sql_fetchfield('total');
			$this->db->sql_freeresult($result);
			$this->add('database', self::INFO, 'TZC_CHK_USERS', 'TZC_CHK_USERS_INFO', [$total]);
		}
	}

	protected function tzdata()
	{
		if (!$this->db_tools->sql_table_exists($this->tables['zones']))
		{
			return;
		}

		list($canonical, $aliases) = $this->zones->count();
		$ok = $canonical >= 300;
		$this->add('tzdata', $ok ? self::OK : self::ERROR, 'TZC_CHK_ZONES', $ok ? 'TZC_CHK_ZONES_OK' : 'TZC_CHK_ZONES_FEW', [$canonical, $aliases]);

		$version = (string) $this->config['tzc_tzdata_version'];
		$this->add('tzdata', $version !== '' ? self::OK : self::ERROR, 'TZC_CHK_TZ_VERSION', 'TZC_CHK_VERSION_IS', [$version ?: '-']);

		$updated = (int) $this->config['tzc_tzdata_updated'];
		$age = $updated ? (int) floor((time() - $updated) / 86400) : 9999;
		$status = $age <= 45 ? self::OK : ($age <= 120 ? self::WARN : self::ERROR);
		$this->add('tzdata', $status, 'TZC_CHK_TZ_AGE', $updated ? 'TZC_CHK_TZ_AGE_DAYS' : 'TZC_CHK_TZ_NEVER', [$age]);

		// Coverage: every canonical zone must know its offset for the next 12 months
		$now = tzdata::now_ms();
		$horizon = $now + 365 * 86400000;
		$broken = [];
		foreach ($this->zones->all() as $name => $row)
		{
			if ($row['zone_alias'] !== '')
			{
				continue;
			}
			$t = json_decode($row['zone_data'], true);
			if (!is_array($t) || !$t)
			{
				$broken[] = $name;
				continue;
			}
			$last = end($t);
			if ($last[0] !== null && $last[0] < $horizon)
			{
				$broken[] = $name;
			}
		}
		$this->add('tzdata', $broken ? self::WARN : self::OK, 'TZC_CHK_COVERAGE', $broken ? 'TZC_CHK_COVERAGE_KO' : 'TZC_CHK_COVERAGE_OK', [implode(', ', array_slice($broken, 0, 15))]);

		$gen = (int) $this->config['tzc_geo_gen'];
		$count = (int) $this->config['tzc_geo_count'];
		$this->add('tzdata', $gen ? self::OK : self::INFO, 'TZC_CHK_GEO', $gen ? 'TZC_CHK_GEO_OK' : 'TZC_CHK_GEO_NONE', [
			number_format($count, 0, ',', '.'), (string) $this->config['tzc_geo_loaded'],
			$this->config['tzc_geo_updated'] ? date('d/m/Y', (int) $this->config['tzc_geo_updated']) : '-',
		]);
	}

	protected function bar()
	{
		if (!$this->db_tools->sql_table_exists($this->tables['cities']))
		{
			return;
		}

		$cities = $this->builder->admin_cities();
		$this->add('bar', $cities ? self::OK : self::WARN, 'TZC_CHK_CITIES', $cities ? 'TZC_CHK_CITIES_OK' : 'TZC_CHK_CITIES_NONE', [count($cities)]);

		$this->add('bar', options::get($this->config, 'enable') ? self::OK : self::WARN, 'TZC_CHK_ENABLED', options::get($this->config, 'enable') ? 'TZC_CHK_ENABLED_ON' : 'TZC_CHK_ENABLED_OFF');

		$this->zones->preload(array_column($cities, 'z'));
		$now = tzdata::now_ms();
		$php_mismatch = [];
		foreach ($cities as $c)
		{
			$t = $this->zones->transitions($c['z']);
			if (!$t)
			{
				$this->add('bar', self::ERROR, 'TZC_CHK_CITY_ZONE', 'TZC_CHK_CITY_ZONE_KO', [$c['n'], $c['z']]);
				continue;
			}

			$ours = tzdata::entry_at($t, $now)[1];
			try
			{
				$php = new \DateTimeZone($c['z']);
				$php_off = (int) round($php->getOffset(new \DateTime('now', new \DateTimeZone('UTC'))) / 60);
				if ($php_off !== (int) $ours)
				{
					$php_mismatch[] = ['TZC_CHK_PHP_COMPARE_ITEM', $c['n'], 'UTC' . tzdata::format_offset($ours), 'UTC' . tzdata::format_offset($php_off)];
				}
			}
			catch (\Exception $e)
			{
				$php_mismatch[] = ['TZC_CHK_PHP_COMPARE_UNKNOWN', $c['n'], $c['z']];
			}
		}

		if ($cities)
		{
			// list of [key, args...] translated by the controller
			$this->add('bar', $php_mismatch ? self::WARN : self::OK, 'TZC_CHK_PHP_COMPARE', $php_mismatch ? 'TZC_CHK_PHP_COMPARE_KO' : 'TZC_CHK_PHP_COMPARE_OK', [$php_mismatch, timezone_version_get()]);
		}

		$payload = $cities ? $this->builder->build(options::get_all($this->config) + ['source' => 'admin', 'show' => 1], $cities, $this->config['board_timezone']) : null;
		$size = $payload ? strlen(json_encode($payload)) : 0;
		$this->add('bar', $payload ? self::OK : self::WARN, 'TZC_CHK_PAYLOAD', $payload ? 'TZC_CHK_PAYLOAD_OK' : 'TZC_CHK_PAYLOAD_KO', [get_formatted_filesize($size)]);

		$board_tz = $this->config['board_timezone'];
		$ok = $this->zones->exists($board_tz);
		$this->add('bar', $ok ? self::OK : self::WARN, 'TZC_CHK_BOARD_TZ', $ok ? 'TZC_CHK_BOARD_TZ_OK' : 'TZC_CHK_BOARD_TZ_KO', [$board_tz]);
	}

	protected function updates()
	{
		$enabled = options::get($this->config, 'cron_enable');
		$last = (int) $this->config['tzc_cron_last'];
		$days = options::get($this->config, 'cron_days');

		if (!$enabled)
		{
			$this->add('updates', self::WARN, 'TZC_CHK_CRON', 'TZC_CHK_CRON_OFF');
		}
		else
		{
			$overdue = $last && (time() - $last) > ($days * 86400 + 2 * 86400);
			$this->add('updates', $overdue ? self::WARN : self::OK, 'TZC_CHK_CRON', $overdue ? 'TZC_CHK_CRON_OVERDUE' : 'TZC_CHK_CRON_OK', [
				$days, $last ? date('d/m/Y H:i', $last) : '-', $last ? date('d/m/Y H:i', $last + $days * 86400) : '-',
			]);
		}

		$system = (bool) $this->config['use_system_cron'];
		$this->add('updates', self::INFO, 'TZC_CHK_SYSTEM_CRON', $system ? 'TZC_CHK_SYSTEM_CRON_ON' : 'TZC_CHK_SYSTEM_CRON_OFF');

		$error = json_decode((string) $this->config['tzc_last_error'], true);
		if (is_array($error) && !empty($error['key']))
		{
			$this->add('updates', self::WARN, 'TZC_CHK_LAST_ERROR', 'TZC_CHK_LAST_ERROR_INFO', [
				date('d/m/Y H:i', (int) $error['time']),
				$this->updater->error_text($error['key'], (array) $error['args']),
				'TZC_ORIGIN_' . strtoupper(isset($error['origin']) ? $error['origin'] : 'acp'),
			]);
		}

		if ($this->updater->is_stale())
		{
			$this->add('updates', self::WARN, 'TZC_CHK_JOB', 'TZC_CHK_JOB_STALE');
		}
		else if ($this->updater->is_running())
		{
			$this->add('updates', self::INFO, 'TZC_CHK_JOB', 'TZC_CHK_JOB_RUNNING');
		}
		else
		{
			$this->add('updates', self::OK, 'TZC_CHK_JOB', 'TZC_CHK_JOB_IDLE');
		}
	}

	protected function network_targets()
	{
		$pkg = (string) $this->config['tzc_tz_pkg'] ?: 'latest';

		return [
			'registry'	=> ['TZC_CHK_NET_REGISTRY', options::get($this->config, 'tz_registry')],
			'primary'	=> ['TZC_CHK_NET_PRIMARY', str_replace('{version}', $pkg, options::get($this->config, 'tz_source'))],
			'alt'		=> ['TZC_CHK_NET_ALT', str_replace('{version}', $pkg, options::get($this->config, 'tz_source_alt'))],
			'geo'		=> ['TZC_CHK_NET_GEO', rtrim(options::get($this->config, 'geo_source'), '/') . '/' . options::get($this->config, 'geo_dataset') . '.zip'],
		];
	}

	/**
	 * One connectivity test ('clock' = server clock compared with the remote servers)
	 */
	protected function network_step($target)
	{
		$this->downloader->set_timeout(8);
		$targets = $this->network_targets();

		if ($target === 'clock')
		{
			foreach ($targets as $t)
			{
				$res = $this->downloader->probe($t[1]);
				$remote = !empty($res['headers']['date']) ? strtotime($res['headers']['date']) : 0;
				if ($res['ok'] && $remote)
				{
					$skew = time() - $remote;
					$this->add('network', abs($skew) <= 60 ? self::OK : self::WARN, 'TZC_CHK_CLOCK', abs($skew) <= 60 ? 'TZC_CHK_CLOCK_OK' : 'TZC_CHK_CLOCK_KO', [$skew]);
					return;
				}
			}
			$this->add('network', self::INFO, 'TZC_CHK_CLOCK', 'TZC_CHK_CLOCK_NA');
			return;
		}

		if (!isset($targets[$target]))
		{
			return;
		}

		list($title, $url) = $targets[$target];
		$res = $this->downloader->probe($url);
		if ($res['ok'])
		{
			$ranges = ($res['status'] == 206);
			$this->add('network', $ranges ? self::OK : self::INFO, $title, $ranges ? 'TZC_CHK_NET_OK' : 'TZC_CHK_NET_NORANGE', [$url, $res['time_ms']]);
		}
		else
		{
			$this->add('network', $target === 'alt' ? self::WARN : self::ERROR, $title, 'TZC_CHK_NET_KO', [$url, $res['error']]);
		}
	}

	protected function network()
	{
		$this->downloader->set_timeout(8);
		$pkg = (string) $this->config['tzc_tz_pkg'] ?: 'latest';
		$targets = [
			'TZC_CHK_NET_REGISTRY'	=> options::get($this->config, 'tz_registry'),
			'TZC_CHK_NET_PRIMARY'	=> str_replace('{version}', $pkg, options::get($this->config, 'tz_source')),
			'TZC_CHK_NET_ALT'		=> str_replace('{version}', $pkg, options::get($this->config, 'tz_source_alt')),
			'TZC_CHK_NET_GEO'		=> rtrim(options::get($this->config, 'geo_source'), '/') . '/' . options::get($this->config, 'geo_dataset') . '.zip',
		];

		$skew_checked = false;
		foreach ($targets as $title => $url)
		{
			$res = $this->downloader->probe($url);
			if ($res['ok'])
			{
				$ranges = ($res['status'] == 206);
				$this->add('network', $ranges ? self::OK : self::INFO, $title, $ranges ? 'TZC_CHK_NET_OK' : 'TZC_CHK_NET_NORANGE', [$url, $res['time_ms']]);

				if (!$skew_checked && !empty($res['headers']['date']))
				{
					$remote = strtotime($res['headers']['date']);
					if ($remote)
					{
						$skew_checked = true;
						$skew = time() - $remote;
						$this->add('network', abs($skew) <= 60 ? self::OK : self::WARN, 'TZC_CHK_CLOCK', abs($skew) <= 60 ? 'TZC_CHK_CLOCK_OK' : 'TZC_CHK_CLOCK_KO', [$skew]);
					}
				}
			}
			else
			{
				$this->add('network', $title === 'TZC_CHK_NET_ALT' ? self::WARN : self::ERROR, $title, 'TZC_CHK_NET_KO', [$url, $res['error']]);
			}
		}
	}

	protected function files()
	{
		$base = $this->root_path . 'ext/salvocortesiano/timezoneclock/';
		$required = [
			'styles/all/template/js/tzc.js',
			'styles/all/template/js/tzc_editor.js',
			'styles/all/theme/tzc.css',
			'styles/all/theme/tzc_editor.css',
			'adm/style/tzc_acp.js',
			'adm/style/tzc_acp.css',
			'styles/all/theme/flags/IT.svg',
			'data/tzdata.json.gz',
			'data/zone_coords.json',
			'language/it/common.php',
			'language/en/common.php',
		];
		$missing = [];
		foreach ($required as $file)
		{
			if (!is_readable($base . $file))
			{
				$missing[] = $file;
			}
		}
		$this->add('files', $missing ? self::ERROR : self::OK, 'TZC_CHK_FILES', $missing ? 'TZC_CHK_FILES_KO' : 'TZC_CHK_FILES_OK', [implode(', ', $missing)]);

		$dir = $this->updater->temp_dir();
		if (!is_dir($dir))
		{
			@mkdir($dir, 0755, true);
		}
		$ok = is_dir($dir) && is_writable($dir);
		$this->add('files', $ok ? self::OK : self::ERROR, 'TZC_CHK_TEMP', $ok ? 'TZC_CHK_TEMP_OK' : 'TZC_CHK_TEMP_KO', ['store/tzc/']);
	}
}
