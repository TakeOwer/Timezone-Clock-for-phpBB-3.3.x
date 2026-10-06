<?php
/**
 *
 * Timezone Clock. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano <https://netshadows.de>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace salvocortesiano\timezoneclock\controller;

use salvocortesiano\timezoneclock\core\options;
use salvocortesiano\timezoneclock\core\prefs;
use salvocortesiano\timezoneclock\core\tzdata;
use salvocortesiano\timezoneclock\core\checkup;

class acp_controller
{
	const EXT = 'salvocortesiano/timezoneclock';

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\cache\driver\driver_interface */
	protected $cache;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\log\log_interface */
	protected $log;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\extension\manager */
	protected $ext_manager;

	/** @var \salvocortesiano\timezoneclock\core\bar_builder */
	protected $builder;

	/** @var \salvocortesiano\timezoneclock\core\catalog */
	protected $catalog;

	/** @var \salvocortesiano\timezoneclock\core\zones */
	protected $zones;

	/** @var \salvocortesiano\timezoneclock\core\updater */
	protected $updater;

	/** @var checkup */
	protected $checkup;

	/** @var string */
	protected $cities_table;

	/** @var \salvocortesiano\timezoneclock\core\form */
	protected $form;

	/** @var string */
	protected $u_action;

	public function __construct(\phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\cache\driver\driver_interface $cache, \phpbb\language\language $language, \phpbb\log\log_interface $log, \phpbb\request\request $request, \phpbb\template\template $template, \phpbb\user $user, \phpbb\extension\manager $ext_manager, $builder, $catalog, $zones, $updater, $checkup, $form, $cities_table)
	{
		$this->config = $config;
		$this->db = $db;
		$this->cache = $cache;
		$this->language = $language;
		$this->log = $log;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->ext_manager = $ext_manager;
		$this->builder = $builder;
		$this->catalog = $catalog;
		$this->zones = $zones;
		$this->updater = $updater;
		$this->checkup = $checkup;
		$this->form = $form;
		$this->cities_table = $cities_table;
	}

	public function set_page_url($u_action)
	{
		$this->u_action = $u_action;
	}

	/**
	 * @return string template name
	 */
	public function handle($mode)
	{
		$this->language->add_lang(['acp', 'common', 'countries', 'options'], self::EXT);
		$this->updater->set_actor($this->user->data['user_id'], $this->user->ip);
		$this->updater->set_language($this->language);

		$action = $this->request->variable('action', '');
		if ($action !== '' && $this->request->is_ajax())
		{
			$this->ajax($action);
		}

		$this->assign_common($mode);

		switch ($mode)
		{
			case 'cities':
				return $this->mode_cities();

			case 'database':
				return $this->mode_database($action);

			case 'checkup':
				return $this->mode_checkup($action);

			default:
				return $this->mode_settings();
		}
	}

	/* ------------------------------------------------------------------
	 * Common header (badges, tabs, credits)
	 * ------------------------------------------------------------------ */

	protected function ext_version()
	{
		try
		{
			$meta = $this->ext_manager->create_extension_metadata_manager(self::EXT)->get_metadata('all');
			return isset($meta['version']) ? $meta['version'] : '?';
		}
		catch (\Exception $e)
		{
			return '?';
		}
	}

	protected function mode_url($mode)
	{
		// u_action is HTML-escaped (…&amp;mode=…): replace only the value of "mode"
		return preg_replace('/(\bmode=)[a-z_]+/', '${1}' . $mode, $this->u_action);
	}

	protected function assign_common($mode)
	{
		$version = $this->ext_version();

		foreach (['settings', 'cities', 'database', 'checkup'] as $tab)
		{
			$this->template->assign_block_vars('tzc_tabs', [
				'TITLE'		=> $this->language->lang('ACP_TZC_' . strtoupper($tab)),
				'U_TAB'		=> $this->mode_url($tab),
				'S_ACTIVE'	=> $tab === $mode,
				'ICON'		=> ['settings' => 'fa-sliders', 'cities' => 'fa-map-marker', 'database' => 'fa-database', 'checkup' => 'fa-stethoscope'][$tab],
			]);
		}

		$this->template->assign_vars([
			'TZC_EXT_VERSION'		=> $version,
			'TZC_PHP_VERSION'		=> PHP_VERSION,
			'TZC_PHPBB_VERSION'		=> $this->config['version'],
			'TZC_TZDATA_VERSION'	=> $this->config['tzc_tzdata_version'] ?: '-',
			'TZC_GEO_COUNT'			=> number_format((int) $this->config['tzc_geo_count'], 0, ',', '.'),
			'TZC_BAR_COUNT'			=> count($this->builder->admin_cities()),
			'TZC_EXT_URL'			=> generate_board_url() . '/ext/salvocortesiano/timezoneclock/',
			'TZC_ASSET_VER'			=> $version . '-' . (int) $this->config['tzc_cache_gen'],
			'TZC_MODE'				=> $mode,
			'U_ACTION'				=> $this->u_action,
		]);
	}

	/* ------------------------------------------------------------------
	 * AJAX
	 * ------------------------------------------------------------------ */

	protected function ajax($action)
	{
		$json = new \phpbb\json_response();

		switch ($action)
		{
			case 'search':
				$json->send(['results' => $this->catalog->search($this->request->variable('q', '', true))]);
			break;

			case 'job_start':
			case 'job_step':
			case 'job_cancel':
			case 'job_status':
				if (!check_link_hash($this->request->variable('hash', ''), 'tzc_job'))
				{
					$json->send(['error' => 'FORM_INVALID', 'text' => $this->language->lang('FORM_INVALID')]);
				}

				if ($action === 'job_start')
				{
					$type = $this->request->variable('type', 'tzdata');
					$type = in_array($type, ['tzdata', 'cities', 'all'], true) ? $type : 'tzdata';
					$state = $this->updater->start($type, 'acp', (bool) $this->request->variable('force', 0));
				}
				else if ($action === 'job_step')
				{
					$state = $this->updater->step(null, true);
				}
				else if ($action === 'job_cancel')
				{
					$this->updater->cancel();
					$state = ['active' => false, 'cancelled' => true];
				}
				else
				{
					$state = $this->updater->public_state();
				}

				$json->send($this->describe_state($state));
			break;

			case 'checkup_step':
				if (!check_link_hash($this->request->variable('hash', ''), 'tzc_checkup'))
				{
					$json->send(['error' => 'FORM_INVALID', 'text' => $this->language->lang('FORM_INVALID')]);
				}

				$step = $this->request->variable('step', '');
				$result = $this->checkup->run_step($step);
				if ($result === false)
				{
					$json->send(['error' => 'step', 'text' => $this->language->lang('TZC_CHK_STEP_UNKNOWN', $step)]);
				}

				list($group, $items) = $result;
				$out = [];
				foreach ($items as $item)
				{
					$out[] = [
						'status'	=> $item['status'],
						'title'		=> $this->language->lang($item['title']),
						'detail'	=> $this->language->lang($item['detail'], ...$this->translate_args($item['args'])),
					];
				}

				$json->send([
					'group'			=> $group,
					'group_title'	=> $this->language->lang('TZC_CHK_GROUP_' . strtoupper($group)),
					'items'			=> $out,
					'run_at'		=> $this->language->lang('TZC_CHK_RUN_AT', $this->user->format_date(time(), false, true)),
				]);
			break;

			case 'add_city':
				if (!check_link_hash($this->request->variable('hash', ''), 'tzc_city'))
				{
					$json->send(['error' => 'FORM_INVALID', 'text' => $this->language->lang('FORM_INVALID')]);
				}
				$json->send($this->add_city_from_zone($this->request->variable('zone', '')));
			break;

			case 'remove_city':
				if (!check_link_hash($this->request->variable('hash', ''), 'tzc_city'))
				{
					$json->send(['error' => 'FORM_INVALID', 'text' => $this->language->lang('FORM_INVALID')]);
				}
				$json->send($this->remove_city_by_id($this->request->variable('city', 0)));
			break;
		}

		$json->send(['error' => 'unknown']);
	}

	/**
	 * Add translated texts to a job state
	 */
	protected function describe_state(array $state)
	{
		if (!empty($state['message']))
		{
			$state['text'] = $this->language->lang($state['message'], ...array_values((array) $state['args']));
		}
		if (!empty($state['error']))
		{
			$state['error_text'] = $this->updater->error_text($state['error'], (array) $state['error_args']);
		}
		if (!empty($state['cancelled']))
		{
			$state['text'] = $this->language->lang('TZC_JOB_CANCELLED');
		}

		$state['results_text'] = [];
		foreach ((array) ($state['results'] ?? []) as $result)
		{
			$state['results_text'][] = $this->result_text($result);
		}

		return $state;
	}

	protected function result_text(array $r)
	{
		if ($r['task'] === 'cities')
		{
			return $this->language->lang('TZC_RESULT_CITIES', number_format((int) $r['count'], 0, ',', '.'), $r['dataset']);
		}

		if ($r['status'] === 'uptodate')
		{
			return $this->language->lang('TZC_RESULT_UPTODATE', $r['version'], $r['pkg']);
		}

		$text = $this->language->lang('TZC_RESULT_TZDATA', $r['version'], (int) $r['zones']);
		if ($r['from'] !== '' && $r['from'] !== $r['version'])
		{
			$text .= ' ' . $this->language->lang('TZC_RESULT_FROM', $r['from']);
		}
		$text .= ' ' . ($r['changed']
			? $this->language->lang('TZC_RESULT_CHANGED', count($r['changed']), implode(', ', $r['changed']))
			: $this->language->lang('TZC_RESULT_NOCHANGE'));
		if ($r['added'])
		{
			$text .= ' ' . $this->language->lang('TZC_RESULT_ADDED', implode(', ', $r['added']));
		}
		if ($r['removed'])
		{
			$text .= ' ' . $this->language->lang('TZC_RESULT_REMOVED', implode(', ', $r['removed']));
		}

		return $text;
	}

	/* ------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------ */

	protected function mode_settings()
	{
		add_form_key('tzc_settings');
		$errors = [];

		if ($this->request->is_set_post('reset_defaults') || $this->request->is_set_post('submit'))
		{
			if (!check_form_key('tzc_settings'))
			{
				trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			if ($this->request->is_set_post('reset_defaults'))
			{
				foreach (options::all() as $key => $def)
				{
					if (!in_array($def['section'], ['updates'], true))
					{
						$this->config->set('tzc_' . $key, $def['default']);
					}
				}
				$this->after_change('LOG_TZC_SETTINGS_RESET');
				trigger_error($this->language->lang('TZC_DEFAULTS_RESTORED') . adm_back_link($this->u_action));
			}

			$new = [];
			foreach (options::all() as $key => $def)
			{
				$name = 'tzc_' . $key;
				switch ($def['type'])
				{
					case 'multi':
						$raw = $this->request->variable($name, ['']);
					break;

					case 'bool':
					case 'int':
						$raw = $this->request->variable($name, (int) $def['default']);
					break;

					default:
						$raw = $this->request->variable($name, (string) $def['default']);
				}

				$clean = options::sanitize($key, $raw);
				if ($clean === null)
				{
					$errors[] = $this->language->lang('TZC_ERR_INVALID_OPTION', $this->language->lang('TZC_OPT_' . strtoupper($key)));
					continue;
				}
				$new[$key] = $clean;
			}

			if ($new['pages'] === 'custom' && $new['pages_list'] === '')
			{
				$errors[] = $this->language->lang('TZC_ERR_NO_PAGES');
			}

			if (!$errors)
			{
				foreach ($new as $key => $value)
				{
					$this->config->set('tzc_' . $key, $value);
				}
				$this->after_change('LOG_TZC_SETTINGS');
				trigger_error($this->language->lang('TZC_SETTINGS_SAVED') . adm_back_link($this->u_action));
			}
		}

		$values = options::get_all($this->config);
		$this->form->assign_fields($values, false);

		$this->template->assign_vars([
			'S_TZC_ERROR'		=> (bool) $errors,
			'TZC_ERROR_MSG'		=> implode('<br>', $errors),
			'TZC_PREVIEW'		=> $this->preview_payload($this->builder->admin_cities()),
			'U_TZC_CITIES'		=> $this->mode_url('cities'),
		]);

		return '@salvocortesiano_timezoneclock/acp_tzc_settings';
	}

	protected function after_change($log_key)
	{
		$this->config->increment('tzc_cache_gen', 1);
		$this->cache->destroy('sql', $this->cities_table);
		$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, $log_key);
	}

	/**
	 * Preview payload with the current options and the given cities
	 */
	protected function preview_payload(array $cities)
	{
		$opts = options::get_all($this->config);
		$opts['source'] = 'admin';
		$opts['show'] = 1;
		$opts['add_home'] = 0;

		$payload = $this->builder->build($opts, $cities, $this->user->data['user_timezone'] ?: $this->config['board_timezone']);
		if (!$payload)
		{
			// no city yet: an empty bar with valid options
			$payload = $this->builder->build($opts, [['n' => 'UTC', 'cc' => '', 'z' => 'Etc/UTC', 'lat' => '', 'lon' => '', 'm' => 1]], 'Etc/UTC');
			$payload['cities'] = [];
		}

		return htmlspecialchars(json_encode($payload), ENT_QUOTES, 'UTF-8');
	}

	/* ------------------------------------------------------------------
	 * Cities
	 * ------------------------------------------------------------------ */

	protected function mode_cities()
	{
		add_form_key('tzc_cities');

		if ($this->request->is_set_post('submit'))
		{
			if (!check_form_key('tzc_cities'))
			{
				trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$raw = json_decode(htmlspecialchars_decode($this->request->variable('tzc_cities_json', '', true), ENT_COMPAT), true);
			$cities = prefs::clean_cities(is_array($raw) ? $raw : [], 200);

			$this->zones->preload(array_column($cities, 'z'));
			$rows = $skipped = [];
			foreach ($cities as $i => $c)
			{
				if (!$this->zones->exists($c['z']))
				{
					$skipped[] = $c['n'] . ' (' . $c['z'] . ')';
					continue;
				}
				$rows[] = [
					'city_name'		=> $c['n'],
					'city_cc'		=> $c['cc'],
					'city_zone'		=> $c['z'],
					'city_lat'		=> $c['lat'],
					'city_lon'		=> $c['lon'],
					'city_mobile'	=> $c['m'],
					'city_order'	=> $i,
				];
			}

			$this->db->sql_transaction('begin');
			$this->db->sql_query('DELETE FROM ' . $this->cities_table);
			if ($rows)
			{
				$this->db->sql_multi_insert($this->cities_table, $rows);
			}
			$this->db->sql_transaction('commit');

			$this->after_change('LOG_TZC_CITIES');

			$message = $this->language->lang('TZC_CITIES_SAVED', count($rows));
			if ($skipped)
			{
				$message .= '<br>' . $this->language->lang('TZC_CITIES_SKIPPED', implode(', ', $skipped));
			}
			trigger_error($message . adm_back_link($this->u_action));
		}

		$cities = $this->builder->admin_cities();
		$payload = $this->preview_payload($cities);

		$this->template->assign_vars([
			'TZC_PREVIEW'		=> $payload,
			'TZC_INITIAL'		=> htmlspecialchars(json_encode($this->editor_items($cities)), ENT_QUOTES, 'UTF-8'),
			'TZC_PICKER_I18N'	=> htmlspecialchars(json_encode($this->picker_strings()), ENT_QUOTES, 'UTF-8'),
			'U_TZC_SEARCH'		=> $this->u_action,
			'S_TZC_GEO'			=> (bool) $this->config['tzc_geo_gen'],
			'U_TZC_DATABASE'	=> $this->mode_url('database'),
		]);

		return '@salvocortesiano_timezoneclock/acp_tzc_cities';
	}

	/**
	 * Cities with transitions and labels for the editor
	 */
	public function editor_items(array $cities)
	{
		$this->zones->preload(array_column($cities, 'z'));
		$now = tzdata::now_ms();
		$items = [];

		foreach ($cities as $c)
		{
			$t = $this->zones->transitions($c['z']);
			$item = [
				'n'		=> $c['n'],
				'cc'	=> $c['cc'],
				'cn'	=> $c['cc'] !== '' ? $this->catalog->country_name($c['cc']) : '',
				'z'		=> $c['z'],
				'lat'	=> $c['lat'],
				'lon'	=> $c['lon'],
				'm'		=> (int) $c['m'],
			];
			if ($t)
			{
				$item['t'] = tzdata::cap(tzdata::trim($t, $now - 86400000), $now + 400 * 86400000);
				$item['off'] = 'UTC' . tzdata::format_offset(tzdata::entry_at($t, $now)[1]);
			}
			$items[] = $item;
		}

		return $items;
	}

	public function picker_strings()
	{
		$out = [];
		foreach (['searching', 'error', 'none', 'max', 'drag', 'rename', 'mobile', 'up', 'down', 'remove', 'inhabitants', 'type_city', 'type_zone'] as $key)
		{
			$out[$key] = $this->language->lang('TZC_PICKER_' . strtoupper($key));
		}

		return $out;
	}

	/* ------------------------------------------------------------------
	 * Database
	 * ------------------------------------------------------------------ */

	/**
	 * Canonical zones already used by the bar cities
	 */
	protected function bar_zones()
	{
		$cities = $this->builder->admin_cities();
		$this->zones->preload(array_column($cities, 'z'));
		$out = [];
		foreach ($cities as $c)
		{
			$row = $this->zones->get($c['z']);
			if ($row)
			{
				// a zone can be used by more cities (e.g. "New York" and "Washington DC")
				$out[$row['zone_name']][$c['id']] = $c['n'];
			}
		}

		return $out;
	}

	/**
	 * Cities of the bar that use a zone, as a list for the zone browser
	 */
	protected function zone_city_list($zone_name, array $used = null)
	{
		$used = ($used === null) ? $this->bar_zones() : $used;
		$out = [];
		foreach (isset($used[$zone_name]) ? $used[$zone_name] : [] as $id => $name)
		{
			$out[] = ['id' => (int) $id, 'name' => $name];
		}

		return $out;
	}

	/**
	 * Remove ONE city (the one clicked) from the bar
	 */
	protected function remove_city_by_id($city_id)
	{
		$city = null;
		foreach ($this->builder->admin_cities() as $c)
		{
			if ($c['id'] === (int) $city_id)
			{
				$city = $c;
				break;
			}
		}

		if (!$city)
		{
			return ['error' => 'missing', 'text' => $this->language->lang('TZC_CITY_NOT_IN_BAR')];
		}

		$this->db->sql_query('DELETE FROM ' . $this->cities_table . ' WHERE city_id = ' . (int) $city['id']);
		$this->after_change('LOG_TZC_CITIES');

		$row = $this->zones->get($city['z']);
		$zone = $row ? $row['zone_name'] : $city['z'];

		return [
			'ok'		=> true,
			'zone'		=> $zone,
			'cities'	=> $this->zone_city_list($zone),
			'count'		=> count($this->builder->admin_cities()),
			'text'		=> $this->language->lang('TZC_CITY_REMOVED', $city['n']),
		];
	}

	/**
	 * Add a city to the bar from the zone browser
	 */
	protected function add_city_from_zone($zone)
	{
		$row = $this->zones->get($zone);
		if (!$row || $row['zone_alias'] !== '')
		{
			return ['error' => 'zone', 'text' => $this->language->lang('TZC_CITY_ZONE_UNKNOWN', $zone)];
		}

		$used = $this->bar_zones();
		if (isset($used[$row['zone_name']]))
		{
			return ['error' => 'exists', 'cities' => $this->zone_city_list($row['zone_name'], $used), 'text' => $this->language->lang('TZC_CITY_ALREADY', implode(', ', $used[$row['zone_name']]), $zone)];
		}

		$cities = $this->builder->admin_cities();
		if (count($cities) >= 200)
		{
			return ['error' => 'max', 'text' => $this->language->lang('TZC_PICKER_MAX', 200)];
		}

		$codes = $row['zone_countries'] !== '' ? explode(',', $row['zone_countries']) : [];
		$name = tzdata::zone_city($zone);
		$order = count($cities);

		$this->db->sql_query('INSERT INTO ' . $this->cities_table . ' ' . $this->db->sql_build_array('INSERT', [
			'city_name'		=> $name,
			'city_cc'		=> $codes ? $codes[0] : '',
			'city_zone'		=> $zone,
			'city_lat'		=> '',
			'city_lon'		=> '',
			'city_mobile'	=> 1,
			'city_order'	=> $order,
		]));
		$this->after_change('LOG_TZC_CITIES');

		return [
			'ok'		=> true,
			'count'		=> count($cities) + 1,
			'cities'	=> $this->zone_city_list($row['zone_name']),
			'text'		=> $this->language->lang('TZC_CITY_ADDED', $name),
		];
	}

	protected function mode_database($action)
	{
		add_form_key('tzc_database');

		if ($action === 'clear_log' && $this->request->is_set_post('clear_log'))
		{
			if (!check_form_key('tzc_database'))
			{
				trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}
			$this->updater->clear_changelog();
			trigger_error($this->language->lang('TZC_LOG_CLEARED') . adm_back_link($this->u_action));
		}

		$now = tzdata::now_ms();
		list($canonical, $aliases) = $this->zones->count();

		// Zone browser
		$in_bar = $this->bar_zones();
		foreach ($this->zones->all() as $name => $row)
		{
			if ($row['zone_alias'] !== '')
			{
				continue;
			}
			$t = json_decode($row['zone_data'], true);
			if (!is_array($t) || !$t)
			{
				continue;
			}

			$entry = tzdata::entry_at($t, $now);
			$next = tzdata::next_change($t, $now);
			$codes = $row['zone_countries'] !== '' ? explode(',', $row['zone_countries']) : [];
			$names = array_map([$this->catalog, 'country_name'], $codes);

			$this->template->assign_block_vars('tzc_zones', [
				'NAME'		=> $name,
				'CC'		=> $codes ? $codes[0] : '',
				'COUNTRIES'	=> implode(', ', $names),
				'OFFSET'	=> 'UTC' . tzdata::format_offset($entry[1]),
				'OFFSET_MIN'=> $entry[1],
				'ABBR'		=> preg_match('/^[+\-]?\d/', $entry[2]) ? '' : $entry[2],
				'S_DST'		=> (bool) $entry[3],
				'NEXT'		=> $next ? $this->user->format_date((int) ($next[0] / 1000), 'd/m/Y H:i') : '',
				'NEXT_TO'	=> $next ? 'UTC' . tzdata::format_offset($next[1][1]) : '',
				'S_IN_BAR'	=> isset($in_bar[$name]),
			]);

			foreach ($this->zone_city_list($name, $in_bar) as $city)
			{
				$this->template->assign_block_vars('tzc_zones.cities', [
					'ID'	=> $city['id'],
					'NAME'	=> $city['name'],
				]);
			}
		}

		// Changelog
		foreach ($this->updater->get_changelog() as $entry)
		{
			$this->template->assign_block_vars('tzc_changelog', [
				'TIME'		=> $this->user->format_date((int) $entry['time']),
				'ORIGIN'	=> $this->language->lang('TZC_ORIGIN_' . strtoupper($entry['origin'])),
				'TYPE'		=> $entry['task'],
				'TEXT'		=> $this->result_text($entry),
			]);
		}

		$state = $this->updater->public_state();

		$this->template->assign_vars([
			'TZC_ZONES_CANONICAL'	=> $canonical,
			'TZC_ZONES_ALIASES'		=> $aliases,
			'TZC_TZ_UPDATED'		=> $this->config['tzc_tzdata_updated'] ? $this->user->format_date((int) $this->config['tzc_tzdata_updated']) : '-',
			'TZC_TZ_CHECKED'		=> $this->config['tzc_tz_checked'] ? $this->user->format_date((int) $this->config['tzc_tz_checked']) : '-',
			'TZC_TZ_PKG'			=> $this->config['tzc_tz_pkg'] ?: $this->language->lang('TZC_BUNDLED'),
			'TZC_GEO_UPDATED'		=> $this->config['tzc_geo_updated'] ? $this->user->format_date((int) $this->config['tzc_geo_updated']) : '-',
			'TZC_GEO_LOADED'		=> $this->config['tzc_geo_loaded'] ?: '-',
			'TZC_GEO_DATASET'		=> options::get($this->config, 'geo_dataset'),
			'TZC_GEO_DATASET_LABEL'	=> $this->form->value_label('geo_dataset', options::get($this->config, 'geo_dataset')),
			'TZC_CRON_STATUS'		=> $this->cron_status(),
			'TZC_PHP_TZDB'			=> timezone_version_get(),
			'TZC_JOB_STATE'			=> htmlspecialchars(json_encode($this->describe_state($state)), ENT_QUOTES, 'UTF-8'),
			'TZC_JOB_HASH'			=> generate_link_hash('tzc_job'),
			'TZC_CITY_HASH'			=> generate_link_hash('tzc_city'),
			'U_TZC_CITIES'			=> $this->mode_url('cities'),
			'S_TZC_ZIP'				=> class_exists('ZipArchive'),
			'U_TZC_SETTINGS_UPDATES'=> $this->mode_url('settings') . '#tzc-sec-updates',
			'U_TZC_CLEAR_LOG'		=> $this->u_action . '&amp;action=clear_log',
		]);

		return '@salvocortesiano_timezoneclock/acp_tzc_database';
	}

	protected function cron_status()
	{
		if (!options::get($this->config, 'cron_enable'))
		{
			return $this->language->lang('TZC_CRON_DISABLED');
		}

		$days = options::get($this->config, 'cron_days');
		$last = (int) $this->config['tzc_cron_last'];
		$next = $last ? $last + $days * 86400 : time();

		$next_text = ($next <= time()) ? $this->language->lang('TZC_CRON_NEXT_SOON') : $this->user->format_date($next, false, true);

		return $this->language->lang('TZC_CRON_STATUS', $days, $last ? $this->user->format_date($last) : '-', $next_text);
	}

	/* ------------------------------------------------------------------
	 * Check-up
	 * ------------------------------------------------------------------ */

	/**
	 * Arguments of a check-up item: language keys and lists of
	 * [key, args...] are translated, everything else is used as it is
	 */
	protected function translate_args(array $args)
	{
		$out = [];
		foreach ($args as $arg)
		{
			if (is_array($arg))
			{
				$parts = [];
				foreach ($arg as $entry)
				{
					$parts[] = is_array($entry) ? $this->language->lang(array_shift($entry), ...array_map('strval', $entry)) : (string) $entry;
				}
				$out[] = implode(', ', $parts);
			}
			else if (is_string($arg) && preg_match('/^TZC_[A-Z_]+$/', $arg) && $this->language->is_set($arg))
			{
				$out[] = $this->language->lang($arg);
			}
			else if (is_int($arg) || is_float($arg))
			{
				// numbers stay numbers: phpBB uses them to pick the plural form
				$out[] = $arg;
			}
			else
			{
				$out[] = (string) $arg;
			}
		}

		return $out;
	}

	protected function mode_checkup($action)
	{
		if ($action === 'fix_config' || $action === 'reset_job')
		{
			if (!check_link_hash($this->request->variable('hash', ''), 'tzc_checkup'))
			{
				trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			if ($action === 'fix_config')
			{
				$fixed = 0;
				foreach (options::all() as $key => $def)
				{
					$name = 'tzc_' . $key;
					if (!isset($this->config[$name]) || options::sanitize($key, $this->config[$name]) === null)
					{
						$this->config->set($name, $def['default']);
						$fixed++;
					}
				}
				$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_TZC_CONFIG_FIXED', false, [$fixed]);
				trigger_error($this->language->lang('TZC_CONFIG_FIXED', $fixed) . adm_back_link($this->u_action));
			}

			$this->updater->cancel();
			trigger_error($this->language->lang('TZC_JOB_CANCELLED') . adm_back_link($this->u_action));
		}

		// The checks run step by step via AJAX (progress bar with percentage)
		$steps = [];
		foreach ([true, false] as $network)
		{
			$list = [];
			foreach (checkup::steps($network) as $id => $def)
			{
				$list[] = ['id' => $id, 'label' => $this->language->lang($def[1])];
			}
			$steps[$network ? 'full' : 'quick'] = $list;
		}

		// Data for the browser test (offsets of the bar cities right now)
		$cities = $this->editor_items($this->builder->admin_cities());
		$browser = [];
		foreach ($cities as $c)
		{
			if (!empty($c['t']))
			{
				$browser[] = ['n' => $c['n'], 'z' => $c['z'], 'off' => tzdata::entry_at($c['t'], tzdata::now_ms())[1]];
			}
		}

		$hash = generate_link_hash('tzc_checkup');

		$this->template->assign_vars([
			'TZC_CHK_STEPS'		=> htmlspecialchars(json_encode($steps), ENT_QUOTES, 'UTF-8'),
			'TZC_CHK_HASH'		=> $hash,
			'S_TZC_CHK_QUICK'	=> (bool) $this->request->variable('quick', 0),
			'TZC_BROWSER'		=> htmlspecialchars(json_encode($browser), ENT_QUOTES, 'UTF-8'),
			'TZC_SERVER_MS'		=> sprintf('%.0f', tzdata::now_ms()),
			'U_TZC_FIX_CONFIG'	=> $this->u_action . '&amp;action=fix_config&amp;hash=' . $hash,
			'U_TZC_RESET_JOB'	=> $this->u_action . '&amp;action=reset_job&amp;hash=' . $hash,
			'U_TZC_DATABASE'	=> $this->mode_url('database'),
		]);

		return '@salvocortesiano_timezoneclock/acp_tzc_checkup';
	}
}
