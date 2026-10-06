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
 * Resumable update job. The same engine is driven by:
 *  - the ACP (one AJAX request per step, real percentage in the progress bar)
 *  - the phpBB cron (steps until a time budget, resumes on the next run)
 *  - the CLI command "tzc:update"
 *
 * Tasks: 'tzdata' (IANA rules) and 'cities' (GeoNames catalogue).
 */
class updater
{
	const STATE_KEY = 'tzc_job';
	const LOG_KEY = 'tzc_changelog';
	const STALE_AFTER = 900;
	const MAX_TZ_BYTES = 20971520;
	const MAX_GEO_BYTES = 157286400;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\config\db_text */
	protected $config_text;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\cache\driver\driver_interface */
	protected $cache;

	/** @var \phpbb\log\log_interface */
	protected $log;

	/** @var downloader */
	protected $downloader;

	/** @var zone_importer */
	protected $importer;

	/** @var string */
	protected $root_path;

	/** @var string */
	protected $zones_table;

	/** @var string */
	protected $geo_table;

	/** @var \phpbb\lock\db */
	protected $lock;

	/** @var array */
	protected $actor = [ANONYMOUS, ''];

	/** @var \phpbb\language\language|null */
	protected $language;

	/** @var array current state */
	protected $state;

	/** @var float */
	protected $deadline;

	/** @var bool one unit of work per request (ACP progress bar) */
	protected $single = false;

	/** @var int units of work done in this request */
	protected $units = 0;

	public function __construct(\phpbb\config\config $config, \phpbb\config\db_text $config_text, \phpbb\db\driver\driver_interface $db, \phpbb\cache\driver\driver_interface $cache, \phpbb\log\log_interface $log, downloader $downloader, $root_path, $zones_table, $geo_table)
	{
		$this->config = $config;
		$this->config_text = $config_text;
		$this->db = $db;
		$this->cache = $cache;
		$this->log = $log;
		$this->downloader = $downloader;
		$this->root_path = $root_path;
		$this->zones_table = $zones_table;
		$this->geo_table = $geo_table;
		$this->importer = new zone_importer($db, $zones_table, $root_path . 'ext/salvocortesiano/timezoneclock/');
		$this->lock = new \phpbb\lock\db('tzc_job_lock', $config, $db);
	}

	/**
	 * Language used for the texts written in the logs
	 */
	public function set_language(\phpbb\language\language $language)
	{
		$this->language = $language;
	}

	/**
	 * Translate an error key with its arguments (arguments that are
	 * language keys themselves are translated too)
	 */
	public function error_text($key, array $args = [])
	{
		if (!$this->language)
		{
			return $key . ($args ? ' (' . implode(' | ', $args) . ')' : '');
		}

		$this->language->add_lang('acp', 'salvocortesiano/timezoneclock');
		foreach ($args as $i => $arg)
		{
			if (is_string($arg) && preg_match('/^TZC_[A-Z_]+$/', $arg) && $this->language->is_set($arg))
			{
				$args[$i] = $this->language->lang($arg);
			}
		}

		return $this->language->lang($key, ...array_map('strval', array_values($args)));
	}

	public function set_actor($user_id, $ip)
	{
		$this->actor = [(int) $user_id, (string) $ip];
	}

	public function temp_dir()
	{
		return $this->root_path . 'store/tzc/';
	}

	/* ------------------------------------------------------------------
	 * State helpers
	 * ------------------------------------------------------------------ */

	public function get_state()
	{
		$state = json_decode((string) $this->config_text->get(self::STATE_KEY), true);

		return is_array($state) ? $state : null;
	}

	protected function save_state()
	{
		$this->state['updated'] = time();
		$this->config_text->set(self::STATE_KEY, json_encode($this->state));
	}

	/**
	 * A job is running and not abandoned
	 */
	public function is_running()
	{
		$state = $this->get_state();

		return $state && empty($state['done']) && (time() - (int) $state['updated']) < self::STALE_AFTER;
	}

	public function is_stale()
	{
		$state = $this->get_state();

		return $state && empty($state['done']) && (time() - (int) $state['updated']) >= self::STALE_AFTER;
	}

	/**
	 * Data sent to the ACP / CLI
	 */
	public function public_state($state = null)
	{
		$state = $state ?: $this->get_state();
		if (!$state)
		{
			return ['active' => false];
		}

		return [
			'active'	=> empty($state['done']),
			'done'		=> !empty($state['done']),
			'type'		=> $state['type'],
			'origin'	=> $state['origin'],
			'task'		=> isset($state['queue'][$state['qi']]) ? $state['queue'][$state['qi']] : '',
			'phase'		=> $state['phase'],
			'percent'	=> round((float) $state['percent'], 1),
			'message'	=> $state['message'],
			'args'		=> $state['args'],
			'error'		=> $state['error'],
			'error_args'=> $state['error_args'],
			'results'	=> $state['results'],
			'started'	=> (int) $state['started'],
			'updated'	=> (int) $state['updated'],
		];
	}

	/* ------------------------------------------------------------------
	 * Public API
	 * ------------------------------------------------------------------ */

	/**
	 * @param string $type   tzdata|cities|all
	 * @param string $origin acp|cron|cli
	 * @return array public state, or ['error' => key]
	 */
	public function start($type, $origin, $force = false)
	{
		if ($this->is_running())
		{
			return ['active' => true, 'error' => 'TZC_ERR_BUSY', 'error_args' => []];
		}

		$queue = ($type === 'all') ? ['tzdata', 'cities'] : [$type === 'cities' ? 'cities' : 'tzdata'];

		$this->cleanup_files();
		$this->state = [
			'id'		=> unique_id(),
			'type'		=> $type,
			'origin'	=> $origin,
			'force'		=> (bool) $force,
			'queue'		=> $queue,
			'qi'		=> 0,
			'phase'		=> 'init',
			'started'	=> time(),
			'updated'	=> time(),
			'done'		=> false,
			'percent'	=> 0,
			'message'	=> 'TZC_PHASE_INIT',
			'args'		=> [],
			'error'		=> '',
			'error_args'=> [],
			'results'	=> [],
			'd'			=> [],
		];
		$this->save_state();

		return $this->public_state($this->state);
	}

	public function cancel()
	{
		$this->config_text->set(self::STATE_KEY, '');
		$this->cleanup_files();
		$this->lock->release();
		// force-release a lock left behind by an interrupted request
		$this->config->set('tzc_job_lock', '0', false);
	}

	/**
	 * Run the current job for at most $budget seconds
	 */
	public function step($budget = null, $single = false)
	{
		$this->single = (bool) $single;
		$this->units = 0;
		$this->state = $this->get_state();
		if (!$this->state || !empty($this->state['done']))
		{
			return $this->public_state($this->state);
		}

		if (!$this->lock->acquire())
		{
			return ['active' => true, 'busy' => true] + $this->public_state($this->state);
		}

		@set_time_limit(0);
		$budget = $budget ?: (int) options::get($this->config, 'step_time');
		$this->deadline = microtime(true) + $budget;

		try
		{
			do
			{
				$task = $this->state['queue'][$this->state['qi']];
				$finished = ($task === 'tzdata') ? $this->run_tzdata() : $this->run_cities();

				if ($this->state['error'] !== '')
				{
					$this->state['done'] = true;
					$this->cleanup_files();
					break;
				}

				if ($finished)
				{
					$this->state['qi']++;
					$this->state['d'] = [];
					$this->state['phase'] = 'init';

					if ($this->state['qi'] >= count($this->state['queue']))
					{
						$this->state['done'] = true;
						$this->state['phase'] = 'done';
						$this->state['percent'] = 100;
						$this->state['message'] = 'TZC_PHASE_DONE';
						$this->state['args'] = [];
						$this->state['qi'] = count($this->state['queue']) - 1;
						break;
					}
				}
			}
			while (!$this->out_of_time());
		}
		catch (\Exception $e)
		{
			$this->fail('TZC_ERR_EXCEPTION', [$e->getMessage()]);
			$this->state['done'] = true;
		}

		$this->save_state();
		$this->lock->release();

		return $this->public_state($this->state);
	}

	/**
	 * Run a job to the end (CLI) or until $max_seconds (cron)
	 *
	 * @param callable|null $progress called after each step with the public state
	 */
	public function run($max_seconds = 0, callable $progress = null)
	{
		$end = $max_seconds ? microtime(true) + $max_seconds : 0;
		$state = $this->public_state();

		while (!empty($state['active']))
		{
			$state = $this->step(5);
			if ($progress)
			{
				$progress($state);
			}
			if (!empty($state['busy']))
			{
				sleep(1);
			}
			if ($end && microtime(true) >= $end)
			{
				break;
			}
		}

		return $state;
	}

	/* ------------------------------------------------------------------
	 * Cron
	 * ------------------------------------------------------------------ */

	public function cron_due()
	{
		if (!options::get($this->config, 'cron_enable'))
		{
			return false;
		}

		$state = $this->get_state();
		if ($state && empty($state['done']) && $state['origin'] === 'cron')
		{
			return true;
		}

		return (bool) $this->cron_type();
	}

	protected function cron_type()
	{
		$tz_due = (time() - (int) $this->config['tzc_cron_last']) >= options::get($this->config, 'cron_days') * 86400;
		$geo_due = options::get($this->config, 'cron_cities')
			&& (time() - (int) $this->config['tzc_cron_cities_last']) >= options::get($this->config, 'cron_cities_days') * 86400;

		if ($tz_due && $geo_due)
		{
			return 'all';
		}

		return $tz_due ? 'tzdata' : ($geo_due ? 'cities' : '');
	}

	/**
	 * Called by the cron task
	 */
	public function run_cron($max_seconds = 25)
	{
		$state = $this->get_state();

		if ($this->is_running() && $state['origin'] !== 'cron')
		{
			// a manual update is in progress in the ACP
			return;
		}

		if (!$state || !empty($state['done']) || $this->is_stale())
		{
			$type = $this->cron_type();
			if (!$type)
			{
				return;
			}
			$this->start($type, 'cron');
		}

		$this->run($max_seconds);
	}

	/* ------------------------------------------------------------------
	 * Task: tzdata
	 * ------------------------------------------------------------------ */

	protected function run_tzdata()
	{
		$d = &$this->state['d'];

		switch ($this->state['phase'])
		{
			case 'init':
				$this->progress(0.02, 'TZC_PHASE_CHECK');
				$version = 'latest';
				$registry = options::get($this->config, 'tz_registry');
				$this->downloader->set_timeout(15);
				$res = $this->downloader->get($registry);
				if ($res['ok'])
				{
					$meta = json_decode($res['body'], true);
					if (!empty($meta['version']) && preg_match('/^[0-9A-Za-z.\-]+$/', $meta['version']))
					{
						$version = $meta['version'];
					}
				}

				$this->config->set('tzc_tz_checked', time());

				if (!$this->state['force'] && $version !== 'latest' && $version === (string) $this->config['tzc_tz_pkg'] && $this->config['tzc_tzdata_version'] !== '')
				{
					$this->state['results'][] = ['task' => 'tzdata', 'status' => 'uptodate', 'version' => $this->config['tzc_tzdata_version'], 'pkg' => $version];
					$this->finish_cron_marker('tzdata');
					return true;
				}

				$urls = [];
				foreach (['tz_source', 'tz_source_alt'] as $key)
				{
					$url = str_replace('{version}', $version, options::get($this->config, $key));
					if ($url && !in_array($url, $urls, true))
					{
						$urls[] = $url;
					}
				}

				$d = [
					'pkg'	=> $version,
					'urls'	=> $urls,
					'src'	=> 0,
					'file'	=> $this->temp_dir() . 'tzdata.json.part',
					'pos'	=> 0,
					'total'	=> 0,
					'max'	=> self::MAX_TZ_BYTES,
				];
				$this->state['phase'] = 'download';
				return false;

			case 'download':
				if ($this->download_chunk(0.05, 0.45))
				{
					$this->state['phase'] = 'parse';
				}
				return false;

			case 'parse':
				$data = $this->importer->decode((string) @file_get_contents($d['file']));
				if (!is_array($data))
				{
					// corrupted file from the primary source: try the alternative once
					if ($this->switch_source())
					{
						$this->state['phase'] = 'download';
						return false;
					}
					$this->fail($data);
					return false;
				}

				if (!isset($d['idx']))
				{
					$d['idx'] = 0;
					$d['zones_total'] = count($data['zones']);
					$d['version'] = $data['version'];
					$d['changes'] = ['added' => [], 'changed' => [], 'seen' => []];
				}

				while ($d['idx'] < $d['zones_total'] && !$this->out_of_time())
				{
					$d['idx'] = $this->importer->import_zones($data, $d['idx'], $this->single ? 25 : 40, $d['changes']);
					$this->units++;
					$this->progress(0.5 + 0.45 * ($d['idx'] / max(1, $d['zones_total'])), 'TZC_PHASE_ZONES', [$d['idx'], $d['zones_total']]);
				}

				if ($d['idx'] >= $d['zones_total'])
				{
					$this->progress(0.97, 'TZC_PHASE_LINKS');
					$this->importer->import_links($data, $d['changes']);
					$this->state['phase'] = 'finalize';
				}
				return false;

			case 'finalize':
				$removed = $this->importer->remove_missing($d['changes']['seen']);
				$old_version = (string) $this->config['tzc_tzdata_version'];

				$this->config->set('tzc_tzdata_version', $d['version']);
				$this->config->set('tzc_tz_pkg', $d['pkg']);
				$this->config->set('tzc_tzdata_updated', time());
				$this->config->increment('tzc_cache_gen', 1);
				$this->cache->destroy('sql', $this->zones_table);

				$result = [
					'task'		=> 'tzdata',
					'status'	=> 'updated',
					'from'		=> $old_version,
					'version'	=> $d['version'],
					'pkg'		=> $d['pkg'],
					'zones'		=> $d['zones_total'],
					'added'		=> $old_version === '' ? [] : array_slice($d['changes']['added'], 0, 100),
					'changed'	=> array_slice($d['changes']['changed'], 0, 100),
					'removed'	=> array_slice($removed, 0, 100),
				];
				$this->state['results'][] = $result;

				if ($old_version !== $d['version'] || $result['changed'] || $result['added'] || $result['removed'])
				{
					$this->add_changelog($result);
				}

				$this->log->add('admin', $this->actor[0], $this->actor[1], 'LOG_TZC_TZDATA_UPDATED_' . strtoupper($this->state['origin']), false, [
					$d['version'], count($result['changed']),
				]);

				@unlink($d['file']);
				$this->finish_cron_marker('tzdata');
				$this->progress(1, 'TZC_PHASE_DONE');
				return true;
		}

		return true;
	}

	/* ------------------------------------------------------------------
	 * Task: cities (GeoNames)
	 * ------------------------------------------------------------------ */

	protected function run_cities()
	{
		$d = &$this->state['d'];

		switch ($this->state['phase'])
		{
			case 'init':
				$this->progress(0.01, 'TZC_PHASE_INIT');
				if (!class_exists('ZipArchive'))
				{
					$this->fail('TZC_ERR_NO_ZIP');
					return false;
				}

				$dataset = options::get($this->config, 'geo_dataset');
				$d = [
					'dataset'	=> $dataset,
					'urls'		=> [rtrim(options::get($this->config, 'geo_source'), '/') . '/' . $dataset . '.zip'],
					'src'		=> 0,
					'file'		=> $this->temp_dir() . $dataset . '.zip.part',
					'pos'		=> 0,
					'total'		=> 0,
					'max'		=> self::MAX_GEO_BYTES,
				];
				$this->state['phase'] = 'download';
				return false;

			case 'download':
				if ($this->download_chunk(0.01, 0.49))
				{
					$this->state['phase'] = 'extract';
				}
				return false;

			case 'extract':
				$this->progress(0.51, 'TZC_PHASE_EXTRACT');
				$zip = new \ZipArchive();
				if ($zip->open($d['file']) !== true)
				{
					$this->fail('TZC_ERR_ZIP_OPEN');
					return false;
				}

				$entry = $d['dataset'] . '.txt';
				if ($zip->locateName($entry) === false || !$zip->extractTo($this->temp_dir(), $entry))
				{
					$zip->close();
					$this->fail('TZC_ERR_ZIP_OPEN');
					return false;
				}
				$zip->close();
				@unlink($d['file']);

				$d['txt'] = $this->temp_dir() . $entry;
				$d['size'] = (int) filesize($d['txt']);
				$d['pos'] = 0;
				$d['rows'] = 0;
				$d['gen'] = (int) $this->config['tzc_geo_gen'] + 1;

				// leftovers of an aborted import with the same generation
				$this->db->sql_query('DELETE FROM ' . $this->geo_table . ' WHERE geo_gen = ' . (int) $d['gen']);

				$this->state['phase'] = 'import';
				return false;

			case 'import':
				$fp = @fopen($d['txt'], 'rb');
				if (!$fp)
				{
					$this->fail('TZC_ERR_TEMP_WRITE', [$this->temp_dir()]);
					return false;
				}
				fseek($fp, $d['pos']);

				$batch = [];
				$lines = 0;
				while (!$this->out_of_time() && ($line = fgets($fp)) !== false)
				{
					if ($this->single && ++$lines >= 2500)
					{
						$this->units++;
					}
					$row = $this->parse_geo_line($line, $d['gen']);
					if ($row)
					{
						$batch[] = $row;
						$d['rows']++;
					}

					if (count($batch) >= 500)
					{
						$this->db->sql_multi_insert($this->geo_table, $batch);
						$batch = [];
					}
				}

				if ($batch)
				{
					$this->db->sql_multi_insert($this->geo_table, $batch);
				}

				$eof = feof($fp);
				$d['pos'] = ftell($fp);
				fclose($fp);

				$this->progress(0.55 + 0.43 * ($d['pos'] / max(1, $d['size'])), 'TZC_PHASE_CITIES', [number_format($d['rows'], 0, ',', '.')]);

				if ($eof)
				{
					$this->state['phase'] = 'finalize';
				}
				return false;

			case 'finalize':
				$this->progress(0.99, 'TZC_PHASE_FINALIZE');
				$old_count = (int) $this->config['tzc_geo_count'];

				$this->config->set('tzc_geo_gen', $d['gen']);
				$this->db->sql_query('DELETE FROM ' . $this->geo_table . ' WHERE geo_gen <> ' . (int) $d['gen']);
				$this->config->set('tzc_geo_count', $d['rows']);
				$this->config->set('tzc_geo_updated', time());
				$this->config->set('tzc_geo_loaded', $d['dataset']);
				$this->cache->destroy('sql', $this->geo_table);
				@unlink($d['txt']);

				$result = [
					'task'		=> 'cities',
					'status'	=> 'updated',
					'dataset'	=> $d['dataset'],
					'count'		=> $d['rows'],
					'diff'		=> $d['rows'] - $old_count,
				];
				$this->state['results'][] = $result;
				$this->add_changelog($result);
				$this->log->add('admin', $this->actor[0], $this->actor[1], 'LOG_TZC_CITIES_UPDATED_' . strtoupper($this->state['origin']), false, [
					$d['dataset'], $d['rows'],
				]);

				$this->finish_cron_marker('cities');
				$this->progress(1, 'TZC_PHASE_DONE');
				return true;
		}

		return true;
	}

	/**
	 * One line of a GeoNames dump -> table row
	 */
	public function parse_geo_line($line, $gen)
	{
		$f = explode("\t", rtrim($line, "\r\n"));
		if (count($f) < 18 || $f[17] === '' || !is_numeric($f[0]))
		{
			return false;
		}

		$alt = [];
		$length = 0;
		foreach (explode(',', $f[3]) as $name)
		{
			$name = trim($name);
			// only latin-script names (Italian, English, Spanish...) and no codes/URLs
			if ($name === '' || utf8_strlen($name) > 60 || !preg_match('/^[\p{Latin}\p{M}\s\'’\-\.()]+$/u', $name))
			{
				continue;
			}
			$key = utf8_strtolower($name);
			if (isset($alt[$key]) || $key === utf8_strtolower($f[1]))
			{
				continue;
			}
			$length += strlen($name) + 1;
			if ($length > 1500)
			{
				break;
			}
			$alt[$key] = $name;
		}

		return [
			'geo_gen'		=> (int) $gen,
			'geo_ref'		=> (int) $f[0],
			'geo_name'		=> utf8_substr($f[1], 0, 100),
			'geo_ascii'		=> substr($f[2], 0, 100),
			'geo_alt'		=> $alt ? ',' . implode(',', $alt) . ',' : '',
			'geo_lat'		=> (string) round((float) $f[4], 4),
			'geo_lon'		=> (string) round((float) $f[5], 4),
			'geo_cc'		=> substr($f[8], 0, 2),
			'geo_admin'		=> substr($f[10], 0, 20),
			'geo_pop'		=> (int) $f[14],
			'geo_zone'		=> substr($f[17], 0, 64),
		];
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Download the next chunk(s). Returns true when the file is complete.
	 */
	protected function download_chunk($start_fraction, $span)
	{
		$d = &$this->state['d'];
		$chunk = options::get($this->config, 'chunk_kb') * 1024;
		if ($this->single)
		{
			// small blocks in the ACP so that the bar moves smoothly
			$chunk = min($chunk, 131072);
		}
		$this->downloader->set_timeout(60);

		if (!is_dir($this->temp_dir()) && !@mkdir($this->temp_dir(), 0755, true))
		{
			$this->fail('TZC_ERR_TEMP_WRITE', [$this->temp_dir()]);
			return false;
		}

		while (!$this->out_of_time())
		{
			$res = $this->downloader->get_range($d['urls'][$d['src']], $d['pos'], $d['pos'] + $chunk - 1);

			if (!$res['ok'])
			{
				if ($this->switch_source())
				{
					continue;
				}
				$this->fail('TZC_ERR_DOWNLOAD', [$d['urls'][$d['src']], $res['error']]);
				return false;
			}

			$mode = ($res['full'] || $d['pos'] == 0) ? 'wb' : 'ab';
			if (@file_put_contents($d['file'], $res['body'], $mode === 'ab' ? FILE_APPEND : 0) === false)
			{
				$this->fail('TZC_ERR_TEMP_WRITE', [$this->temp_dir()]);
				return false;
			}

			$length = strlen($res['body']);
			if ($res['full'])
			{
				$d['pos'] = $d['total'] = $length;
			}
			else
			{
				$d['pos'] += $length;
				if ($res['total'])
				{
					$d['total'] = $res['total'];
				}
			}

			if ($d['pos'] > $d['max'])
			{
				$this->fail('TZC_ERR_TOO_BIG');
				return false;
			}

			$this->units++;
			$fraction = $d['total'] ? min(1, $d['pos'] / $d['total']) : 0;
			$this->progress($start_fraction + $span * $fraction, 'TZC_PHASE_DOWNLOAD', [
				get_formatted_filesize($d['pos']), $d['total'] ? get_formatted_filesize($d['total']) : '?',
			]);

			if ($res['full'] || $length == 0 || ($d['total'] && $d['pos'] >= $d['total']) || ($length < $chunk && !$d['total']))
			{
				return true;
			}
		}

		return false;
	}

	protected function switch_source()
	{
		$d = &$this->state['d'];
		if ($d['src'] + 1 < count($d['urls']))
		{
			$d['src']++;
			$d['pos'] = 0;
			$d['total'] = 0;
			unset($d['idx']);
			@unlink($d['file']);
			return true;
		}

		return false;
	}

	protected function progress($fraction, $message, array $args = [])
	{
		$tasks = count($this->state['queue']);
		$this->state['percent'] = min(100, (($this->state['qi'] + min(1, max(0, $fraction))) / $tasks) * 100);
		$this->state['message'] = $message;
		$this->state['args'] = $args;
	}

	protected function fail($key, array $args = [])
	{
		$this->state['error'] = $key;
		$this->state['error_args'] = $args;

		$this->config->set('tzc_last_error', json_encode(['key' => $key, 'args' => $args, 'time' => time(), 'origin' => $this->state['origin']]));

		if ($this->state['origin'] === 'cron')
		{
			// retry in about 6 hours instead of waiting the whole interval
			$retry = time() - options::get($this->config, 'cron_days') * 86400 + 21600;
			$this->config->set('tzc_cron_last', max((int) $this->config['tzc_cron_last'], $retry));
		}

		$this->log->add('critical', $this->actor[0], $this->actor[1], 'LOG_TZC_UPDATE_FAILED', false, [$this->error_text($key, $args)]);
	}

	protected function finish_cron_marker($task)
	{
		$this->config->set($task === 'tzdata' ? 'tzc_cron_last' : 'tzc_cron_cities_last', time());
		$this->config->set('tzc_last_error', '');
	}

	protected function out_of_time()
	{
		return microtime(true) >= $this->deadline || ($this->single && $this->units > 0);
	}

	protected function cleanup_files()
	{
		$dir = $this->temp_dir();
		if (is_dir($dir))
		{
			foreach ((array) glob($dir . '*') as $file)
			{
				if (is_file($file) && basename($file) !== 'index.htm')
				{
					@unlink($file);
				}
			}
		}
	}

	/* ------------------------------------------------------------------
	 * Changelog
	 * ------------------------------------------------------------------ */

	public function get_changelog()
	{
		$log = json_decode((string) $this->config_text->get(self::LOG_KEY), true);

		return is_array($log) ? $log : [];
	}

	protected function add_changelog(array $result)
	{
		$log = $this->get_changelog();
		$result['time'] = time();
		$result['origin'] = $this->state['origin'];
		array_unshift($log, $result);
		$this->config_text->set(self::LOG_KEY, json_encode(array_slice($log, 0, 30)));
	}

	public function clear_changelog()
	{
		$this->config_text->set(self::LOG_KEY, '[]');
	}
}
