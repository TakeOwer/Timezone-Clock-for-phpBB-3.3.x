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

class ucp_controller
{
	const EXT = 'salvocortesiano/timezoneclock';

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var \salvocortesiano\timezoneclock\core\prefs */
	protected $prefs;

	/** @var \salvocortesiano\timezoneclock\core\bar_builder */
	protected $builder;

	/** @var \salvocortesiano\timezoneclock\core\catalog */
	protected $catalog;

	/** @var \salvocortesiano\timezoneclock\core\zones */
	protected $zones;

	/** @var \salvocortesiano\timezoneclock\core\form */
	protected $form;

	/** @var string */
	protected $u_action;

	public function __construct(\phpbb\config\config $config, \phpbb\language\language $language, \phpbb\request\request $request, \phpbb\template\template $template, \phpbb\user $user, $prefs, $builder, $catalog, $zones, $form)
	{
		$this->config = $config;
		$this->language = $language;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->prefs = $prefs;
		$this->builder = $builder;
		$this->catalog = $catalog;
		$this->zones = $zones;
		$this->form = $form;
	}

	public function set_page_url($u_action)
	{
		$this->u_action = $u_action;
	}

	public function handle()
	{
		$this->language->add_lang(['ucp', 'common', 'countries', 'options'], self::EXT);

		if (!options::get($this->config, 'ucp_enable'))
		{
			trigger_error('TZC_UCP_DISABLED');
		}

		$user_id = (int) $this->user->data['user_id'];
		$allow_cities = (bool) options::get($this->config, 'ucp_cities');
		$max = (int) options::get($this->config, 'ucp_max');

		if ($this->request->variable('action', '') === 'search' && $this->request->is_ajax())
		{
			$json = new \phpbb\json_response();
			$json->send(['results' => $allow_cities ? $this->catalog->search($this->request->variable('q', '', true)) : []]);
		}

		add_form_key('tzc_ucp');
		$stored = $this->prefs->load($user_id);

		if ($this->request->is_set_post('submit') || $this->request->is_set_post('reset'))
		{
			if (!check_form_key('tzc_ucp'))
			{
				trigger_error('FORM_INVALID');
			}

			if ($this->request->is_set_post('reset'))
			{
				$this->prefs->save($user_id, [], []);
			}
			else
			{
				$prefs = [];
				foreach (options::ucp_keys() as $key)
				{
					$raw = $this->request->variable('tzc_' . $key, '');
					if ($raw === '')
					{
						continue;
					}
					$clean = options::sanitize($key, $raw);
					if ($clean !== null)
					{
						$prefs[$key] = $clean;
					}
				}

				if (options::get($this->config, 'ucp_hide'))
				{
					$prefs['show'] = $this->request->variable('tzc_show', 1) ? 1 : 0;
				}

				$cities = $stored['cities'];
				if ($allow_cities)
				{
					$source = $this->request->variable('tzc_source', 'admin');
					$prefs['source'] = in_array($source, ['admin', 'mine', 'both'], true) ? $source : 'admin';

					$raw = json_decode(htmlspecialchars_decode($this->request->variable('tzc_cities_json', '', true), ENT_COMPAT), true);
					$cities = prefs::clean_cities(is_array($raw) ? $raw : [], $max);
					$this->zones->preload(array_column($cities, 'z'));
					$cities = array_values(array_filter($cities, function ($c) {
						return $this->zones->exists($c['z']);
					}));
				}

				$this->prefs->save($user_id, $prefs, $cities);
			}

			meta_refresh(3, $this->u_action);
			trigger_error($this->language->lang('TZC_UCP_SAVED') . '<br><br>' . $this->language->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>'));
		}

		// Current values ('' = board default)
		$prefs = $stored['prefs'];
		$values = [];
		foreach (options::ucp_keys() as $key)
		{
			$values[$key] = isset($prefs[$key]) ? (string) $prefs[$key] : '';
		}
		$this->form->assign_fields($values, true);

		// Preview: board defaults as base, the form applies the user choices
		$opts = options::get_all($this->config);
		$opts['source'] = 'admin';
		$opts['show'] = 1;
		$home = $this->user->data['user_timezone'] ?: $this->config['board_timezone'];
		$admin = $this->builder->admin_cities();
		$payload = $this->builder->build($opts, $admin, $home);
		if (!$payload)
		{
			$payload = $this->builder->build($opts, [['n' => 'UTC', 'cc' => '', 'z' => 'Etc/UTC', 'lat' => '', 'lon' => '', 'm' => 1]], $home);
			$payload['cities'] = [];
		}

		$mine = $this->editor_items(prefs::clean_cities($stored['cities'], $max));

		$this->template->assign_vars([
			'S_TZC_HIDE'		=> (bool) options::get($this->config, 'ucp_hide'),
			'S_TZC_CITIES'		=> $allow_cities,
			'TZC_SHOW'			=> isset($prefs['show']) ? (int) $prefs['show'] : 1,
			'TZC_SOURCE'		=> isset($prefs['source']) ? $prefs['source'] : 'admin',
			'TZC_MAX'			=> $max,
			'TZC_PREVIEW'		=> htmlspecialchars(json_encode($payload), ENT_QUOTES, 'UTF-8'),
			'TZC_INITIAL'		=> htmlspecialchars(json_encode($mine), ENT_QUOTES, 'UTF-8'),
			'TZC_PICKER_I18N'	=> htmlspecialchars(json_encode($this->picker_strings()), ENT_QUOTES, 'UTF-8'),
			'TZC_FLAGS_URL'		=> generate_board_url() . '/ext/salvocortesiano/timezoneclock/styles/all/theme/flags/',
			'TZC_HOME_ZONE'		=> $home,
			'S_TZC_GEO'			=> (bool) $this->config['tzc_geo_gen'],
			'U_TZC_SEARCH'		=> $this->u_action,
			'U_ACTION'			=> $this->u_action,
		]);

		return '@salvocortesiano_timezoneclock/ucp_tzc_settings';
	}

	protected function editor_items(array $cities)
	{
		$this->zones->preload(array_column($cities, 'z'));
		$now = tzdata::now_ms();
		$items = [];

		foreach ($cities as $c)
		{
			$t = $this->zones->transitions($c['z']);
			if (!$t)
			{
				continue;
			}
			$items[] = [
				'n'		=> $c['n'],
				'cc'	=> $c['cc'],
				'cn'	=> $c['cc'] !== '' ? $this->catalog->country_name($c['cc']) : '',
				'z'		=> $c['z'],
				'lat'	=> $c['lat'],
				'lon'	=> $c['lon'],
				'm'		=> 1,
				't'		=> tzdata::cap(tzdata::trim($t, $now - 86400000), $now + 400 * 86400000),
				'off'	=> 'UTC' . tzdata::format_offset(tzdata::entry_at($t, $now)[1]),
			];
		}

		return $items;
	}

	protected function picker_strings()
	{
		$out = [];
		foreach (['searching', 'error', 'none', 'max', 'drag', 'rename', 'mobile', 'up', 'down', 'remove', 'inhabitants', 'type_city', 'type_zone'] as $key)
		{
			$out[$key] = $this->language->lang('TZC_PICKER_' . strtoupper($key));
		}

		return $out;
	}
}
