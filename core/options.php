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
 * Single source of truth for every setting: type, allowed values,
 * default, ACP section and whether the user can override it in the UCP.
 * The ACP and UCP forms, the validation, the migrations and the bar
 * payload are all generated from this list.
 *
 * Config name: 'tzc_' . key
 * Language:    TZC_OPT_<KEY>, TZC_OPT_<KEY>_EXPLAIN, TZC_OPT_<KEY>_<VALUE>
 */
class options
{
	const PAGES = ['index', 'viewforum', 'viewtopic', 'search', 'memberlist', 'ucp', 'mcp', 'posting', 'faq', 'viewonline', 'app'];
	const POSITIONS = ['above', 'top', 'bottom', 'footer'];
	const DATASETS = ['cities15000', 'cities5000', 'cities1000', 'cities500'];

	/**
	 * @return array key => definition
	 */
	public static function all()
	{
		return [
			// --- General ---------------------------------------------------
			'enable'			=> ['section' => 'general', 'type' => 'bool', 'default' => 1],
			'pages'				=> ['section' => 'general', 'type' => 'enum', 'values' => ['index', 'all', 'custom'], 'default' => 'index'],
			'pages_list'		=> ['section' => 'general', 'type' => 'multi', 'values' => self::PAGES, 'default' => 'index,viewforum,viewtopic'],
			'position'			=> ['section' => 'general', 'type' => 'enum', 'values' => self::POSITIONS, 'default' => 'top'],
			'guests'			=> ['section' => 'general', 'type' => 'bool', 'default' => 1],
			'bots'				=> ['section' => 'general', 'type' => 'bool', 'default' => 0],
			'mobile'			=> ['section' => 'general', 'type' => 'bool', 'default' => 1],
			'collapsible'		=> ['section' => 'general', 'type' => 'bool', 'default' => 1],

			// --- Display (user overridable) --------------------------------
			'mode'				=> ['section' => 'display', 'type' => 'enum', 'values' => ['carousel', 'ticker', 'grid'], 'default' => 'carousel', 'ucp' => true],
			'density'			=> ['section' => 'display', 'type' => 'enum', 'values' => ['normal', 'compact'], 'default' => 'normal', 'ucp' => true],
			'format'			=> ['section' => 'display', 'type' => 'enum', 'values' => ['24', '12'], 'default' => '24', 'ucp' => true],
			'seconds'			=> ['section' => 'display', 'type' => 'bool', 'default' => 0, 'ucp' => true],
			'show_date'			=> ['section' => 'display', 'type' => 'bool', 'default' => 1, 'ucp' => true],
			'show_flag'			=> ['section' => 'display', 'type' => 'bool', 'default' => 1, 'ucp' => true],
			'flag_style'		=> ['section' => 'display', 'type' => 'enum', 'values' => ['svg', 'emoji'], 'default' => 'svg', 'ucp' => true],
			'show_country'		=> ['section' => 'display', 'type' => 'bool', 'default' => 1, 'ucp' => true],
			'show_daynight'		=> ['section' => 'display', 'type' => 'bool', 'default' => 1, 'ucp' => true],
			'daynight_bg'		=> ['section' => 'display', 'type' => 'bool', 'default' => 1, 'ucp' => true],
			'analog'			=> ['section' => 'display', 'type' => 'bool', 'default' => 0, 'ucp' => true],
			'show_diff'			=> ['section' => 'display', 'type' => 'bool', 'default' => 1, 'ucp' => true],
			'show_dst'			=> ['section' => 'display', 'type' => 'bool', 'default' => 1, 'ucp' => true],
			'show_abbr'			=> ['section' => 'display', 'type' => 'bool', 'default' => 0, 'ucp' => true],
			'show_utc'			=> ['section' => 'display', 'type' => 'bool', 'default' => 0, 'ucp' => true],
			'highlight_home'	=> ['section' => 'display', 'type' => 'bool', 'default' => 1, 'ucp' => true],
			'add_home'			=> ['section' => 'display', 'type' => 'bool', 'default' => 0, 'ucp' => true],
			'sort'				=> ['section' => 'display', 'type' => 'enum', 'values' => ['manual', 'east', 'west', 'name'], 'default' => 'manual', 'ucp' => true],
			'search'			=> ['section' => 'display', 'type' => 'enum', 'values' => ['auto', 'always', 'never'], 'default' => 'auto', 'ucp' => true],
			'search_min'		=> ['section' => 'display', 'type' => 'int', 'min' => 2, 'max' => 50, 'default' => 8],

			// --- Motion (user overridable) ---------------------------------
			'autoplay'			=> ['section' => 'motion', 'type' => 'bool', 'default' => 1, 'ucp' => true],
			'autoplay_delay'	=> ['section' => 'motion', 'type' => 'int', 'min' => 2, 'max' => 30, 'default' => 4, 'ucp' => true],
			'ticker_speed'		=> ['section' => 'motion', 'type' => 'int', 'min' => 10, 'max' => 200, 'default' => 40, 'ucp' => true],
			'pause_hover'		=> ['section' => 'motion', 'type' => 'bool', 'default' => 1, 'ucp' => true],
			'arrows'			=> ['section' => 'motion', 'type' => 'bool', 'default' => 1, 'ucp' => true],
			'dots'				=> ['section' => 'motion', 'type' => 'bool', 'default' => 1, 'ucp' => true],

			// --- Appearance ------------------------------------------------
			'theme'				=> ['section' => 'appearance', 'type' => 'enum', 'values' => ['auto', 'light', 'dark', 'glass'], 'default' => 'auto', 'ucp' => true],
			'accent'			=> ['section' => 'appearance', 'type' => 'color', 'default' => '#105289'],
			'card_width'		=> ['section' => 'appearance', 'type' => 'int', 'min' => 110, 'max' => 300, 'default' => 168],
			'radius'			=> ['section' => 'appearance', 'type' => 'int', 'min' => 0, 'max' => 24, 'default' => 12],

			// --- Users / UCP -----------------------------------------------
			'ucp_enable'		=> ['section' => 'users', 'type' => 'bool', 'default' => 1],
			'ucp_hide'			=> ['section' => 'users', 'type' => 'bool', 'default' => 1],
			'ucp_cities'		=> ['section' => 'users', 'type' => 'bool', 'default' => 1],
			'ucp_max'			=> ['section' => 'users', 'type' => 'int', 'min' => 1, 'max' => 50, 'default' => 12],

			// --- Updates ---------------------------------------------------
			'cron_enable'		=> ['section' => 'updates', 'type' => 'bool', 'default' => 1],
			'cron_days'			=> ['section' => 'updates', 'type' => 'int', 'min' => 1, 'max' => 90, 'default' => 7],
			'cron_cities'		=> ['section' => 'updates', 'type' => 'bool', 'default' => 0],
			'cron_cities_days'	=> ['section' => 'updates', 'type' => 'int', 'min' => 7, 'max' => 365, 'default' => 60],
			'tz_source'			=> ['section' => 'updates', 'type' => 'url', 'default' => 'https://cdn.jsdelivr.net/npm/moment-timezone@{version}/data/packed/latest.json'],
			'tz_source_alt'		=> ['section' => 'updates', 'type' => 'url', 'default' => 'https://unpkg.com/moment-timezone@{version}/data/packed/latest.json'],
			'tz_registry'		=> ['section' => 'updates', 'type' => 'url', 'default' => 'https://registry.npmjs.org/moment-timezone/latest'],
			'geo_dataset'		=> ['section' => 'updates', 'type' => 'enum', 'values' => self::DATASETS, 'default' => 'cities15000'],
			'geo_source'		=> ['section' => 'updates', 'type' => 'url', 'default' => 'https://download.geonames.org/export/dump/'],
			'chunk_kb'			=> ['section' => 'updates', 'type' => 'int', 'min' => 64, 'max' => 4096, 'default' => 512],
			'step_time'			=> ['section' => 'updates', 'type' => 'int', 'min' => 2, 'max' => 25, 'default' => 5],
		];
	}

	public static function sections()
	{
		return ['general', 'display', 'motion', 'appearance', 'users', 'updates'];
	}

	/**
	 * Options a user may override
	 */
	public static function ucp_keys()
	{
		$keys = [];
		foreach (self::all() as $key => $def)
		{
			if (!empty($def['ucp']))
			{
				$keys[] = $key;
			}
		}

		return $keys;
	}

	/**
	 * Validate and normalise a raw value. Returns null when invalid.
	 */
	public static function sanitize($key, $value)
	{
		$all = self::all();
		if (!isset($all[$key]))
		{
			return null;
		}
		$def = $all[$key];

		switch ($def['type'])
		{
			case 'bool':
				return ((int) $value) ? 1 : 0;

			case 'int':
				if (!is_numeric($value))
				{
					return null;
				}
				return max($def['min'], min($def['max'], (int) $value));

			case 'enum':
				$value = (string) $value;
				return in_array($value, $def['values'], true) ? $value : null;

			case 'multi':
				$list = is_array($value) ? $value : explode(',', (string) $value);
				$list = array_values(array_intersect($def['values'], array_map('strval', $list)));
				return implode(',', $list);

			case 'color':
				$value = trim((string) $value);
				return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : null;

			case 'url':
				$value = trim((string) $value);
				return preg_match('#^https?://[^\s"\'<>]+$#i', $value) ? $value : null;
		}

		return null;
	}

	/**
	 * Typed value read from the config
	 */
	public static function get(\phpbb\config\config $config, $key)
	{
		$all = self::all();
		$def = $all[$key];
		$value = isset($config['tzc_' . $key]) ? $config['tzc_' . $key] : $def['default'];
		$clean = self::sanitize($key, $value);

		return $clean === null ? $def['default'] : $clean;
	}

	/**
	 * All the configured values
	 */
	public static function get_all(\phpbb\config\config $config)
	{
		$out = [];
		foreach (array_keys(self::all()) as $key)
		{
			$out[$key] = self::get($config, $key);
		}

		return $out;
	}
}
