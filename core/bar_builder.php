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
 * Builds the JSON payload rendered by tzc.js. The payload carries the
 * transitions of every zone shown, so the browser does not need an
 * up-to-date time zone database of its own.
 */
class bar_builder
{
	/** Keys of the options sent to the browser */
	const CLIENT_OPTS = [
		'mode', 'density', 'format', 'seconds', 'show_date', 'show_flag', 'flag_style', 'show_country',
		'show_daynight', 'daynight_bg', 'analog', 'show_diff', 'show_dst', 'show_abbr', 'show_utc',
		'highlight_home', 'autoplay', 'autoplay_delay', 'ticker_speed', 'pause_hover', 'arrows', 'dots',
		'theme', 'accent', 'card_width', 'radius', 'mobile', 'collapsible', 'search', 'search_min', 'tooltip', 'info',
	];

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\cache\driver\driver_interface */
	protected $cache;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var zones */
	protected $zones;

	/** @var prefs */
	protected $prefs;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var string */
	protected $cities_table;

	public function __construct(\phpbb\config\config $config, \phpbb\cache\driver\driver_interface $cache, \phpbb\db\driver\driver_interface $db, zones $zones, prefs $prefs, \phpbb\language\language $language, $cities_table)
	{
		$this->config = $config;
		$this->cache = $cache;
		$this->db = $db;
		$this->zones = $zones;
		$this->prefs = $prefs;
		$this->language = $language;
		$this->cities_table = $cities_table;
	}

	/**
	 * Cities configured by the administrator
	 */
	public function admin_cities()
	{
		$cities = [];
		$sql = 'SELECT * FROM ' . $this->cities_table . ' ORDER BY city_order ASC, city_id ASC';
		$result = $this->db->sql_query($sql, 3600);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$cities[] = [
				'id'	=> (int) $row['city_id'],
				'n'		=> $row['city_name'],
				'cc'	=> $row['city_cc'],
				'z'		=> $row['city_zone'],
				'lat'	=> $row['city_lat'],
				'lon'	=> $row['city_lon'],
				'm'		=> (int) $row['city_mobile'],
			];
		}
		$this->db->sql_freeresult($result);

		return $cities;
	}

	/**
	 * Cities shown to a user according to his preferences
	 */
	public function cities_for(array $opts, $user_id)
	{
		$admin = $this->admin_cities();
		if ($opts['source'] === 'admin' || !$opts['ucp_cities'])
		{
			return $admin;
		}

		$stored = $this->prefs->load($user_id);
		$mine = prefs::clean_cities($stored['cities'], (int) $opts['ucp_max']);

		if ($opts['source'] === 'mine')
		{
			return $mine ? $mine : $admin;
		}

		// both: personal first, then the board cities not already present
		$seen = [];
		foreach ($mine as $c)
		{
			$seen[$c['z'] . '|' . utf8_strtolower($c['n'])] = true;
		}
		foreach ($admin as $c)
		{
			if (!isset($seen[$c['z'] . '|' . utf8_strtolower($c['n'])]))
			{
				$mine[] = $c;
			}
		}

		return $mine;
	}

	/**
	 * Payload for the current user (or null when nothing must be shown)
	 */
	public function for_user(array $user_data, $ucp_url = '')
	{
		$opts = $this->prefs->effective($user_data['user_id']);
		if (!$opts['show'])
		{
			return null;
		}

		$cities = $this->cities_for($opts, $user_data['user_id']);
		$home = !empty($user_data['user_timezone']) ? $user_data['user_timezone'] : $this->config['board_timezone'];

		$payload = $this->build($opts, $cities, $home);
		if ($payload && $ucp_url && $opts['ucp_enable'] && $user_data['user_id'] != ANONYMOUS && empty($user_data['is_bot']))
		{
			$payload['ucp'] = $ucp_url;
		}

		return $payload;
	}

	/**
	 * Build the payload
	 *
	 * @param array  $opts   effective options
	 * @param array  $cities list of ['n','cc','z','lat','lon','m']
	 * @param string $home   IANA zone of the viewer
	 * @return array|null
	 */
	public function build(array $opts, array $cities, $home)
	{
		$this->language->add_lang(['common', 'countries'], 'salvocortesiano/timezoneclock');

		$names = array_column($cities, 'z');
		$names[] = $home;
		$names[] = 'Etc/UTC';
		$this->zones->preload($names);

		$home_row = $this->zones->get($home);
		if (!$home_row)
		{
			$home = 'Etc/UTC';
			$home_row = $this->zones->get($home);
		}
		$home_target = $home_row ? $home_row['zone_name'] : '';

		$now = tzdata::now_ms();
		$from = $now - 86400000;
		$this->window_end = $now + 400 * 86400000;
		$out = [];
		$has_home = false;

		foreach ($cities as $c)
		{
			$row = $this->zones->get($c['z']);
			$t = $row ? json_decode($row['zone_data'], true) : false;
			if (!$t)
			{
				continue;
			}

			$is_home = ($row['zone_name'] === $home_target);
			$has_home = $has_home || $is_home;
			$out[] = $this->city_item($c, $row, $this->window($t, $from), $is_home);
		}

		if ($opts['add_home'] && !$has_home && $home_row)
		{
			$t = json_decode($home_row['zone_data'], true);
			$countries = $home_row['zone_countries'] !== '' ? explode(',', $home_row['zone_countries']) : [];
			array_unshift($out, $this->city_item([
				'n'		=> tzdata::zone_city($home),
				'cc'	=> $countries ? $countries[0] : '',
				'z'		=> $home,
				'lat'	=> $home_row['zone_lat'],
				'lon'	=> $home_row['zone_lon'],
				'm'		=> 1,
			], $home_row, $this->window((array) $t, $from), true));
		}

		if (!$out)
		{
			return null;
		}

		$this->sort($out, $opts['sort'], $now);

		$client = [];
		foreach (self::CLIENT_OPTS as $key)
		{
			$client[$key] = $opts[$key];
		}

		$home_t = $home_row ? $this->window((array) json_decode($home_row['zone_data'], true), $from) : [];

		return [
			'opt'		=> $client,
			'locale'	=> $this->language->lang('TZC_LOCALE'),
			'flags'		=> generate_board_url() . '/ext/salvocortesiano/timezoneclock/styles/all/theme/flags/',
			'home'		=> $home_t ?: [[null, 0, 'UTC', false]],
			'cities'	=> $out,
			'i18n'		=> $this->strings(),
		];
	}

	/** @var float */
	protected $window_end;

	/**
	 * Only the transitions useful while a page stays open (about a year)
	 */
	protected function window(array $t, $from)
	{
		return tzdata::cap(tzdata::trim($t, $from), $this->window_end);
	}

	protected function city_item(array $c, array $row, array $t, $is_home)
	{
		$cc = $c['cc'] !== '' ? $c['cc'] : '';
		if ($cc === '' && $row['zone_countries'] !== '')
		{
			$cc = explode(',', $row['zone_countries'])[0];
		}

		$lat = $c['lat'] !== '' ? $c['lat'] : $row['zone_lat'];
		$lon = $c['lon'] !== '' ? $c['lon'] : $row['zone_lon'];

		return [
			'n'		=> $c['n'],
			'cc'	=> $cc,
			'cn'	=> $cc !== '' && $this->language->is_set('TZC_CC_' . $cc) ? $this->language->lang('TZC_CC_' . $cc) : '',
			'z'		=> $c['z'],
			'lat'	=> $lat !== '' ? (float) $lat : null,
			'lon'	=> $lon !== '' ? (float) $lon : null,
			'm'		=> isset($c['m']) ? (int) $c['m'] : 1,
			'home'	=> $is_home ? 1 : 0,
			't'		=> $t,
		];
	}

	protected function sort(array &$items, $mode, $now)
	{
		if ($mode === 'manual')
		{
			return;
		}

		usort($items, function ($a, $b) use ($mode, $now) {
			if ($mode === 'name')
			{
				return strcasecmp($a['n'], $b['n']);
			}

			$oa = tzdata::entry_at($a['t'], $now)[1];
			$ob = tzdata::entry_at($b['t'], $now)[1];

			if ($oa === $ob)
			{
				return strcasecmp($a['n'], $b['n']);
			}

			return ($mode === 'west') ? $oa - $ob : $ob - $oa;
		});
	}

	/**
	 * Strings used by the JavaScript
	 */
	public function strings()
	{
		$keys = [
			'title'		=> 'TZC_JS_TITLE',
			'today'		=> 'TZC_JS_TODAY',
			'tomorrow'	=> 'TZC_JS_TOMORROW',
			'yesterday'	=> 'TZC_JS_YESTERDAY',
			'dst'		=> 'TZC_JS_DST',
			'home'		=> 'TZC_JS_HOME',
			'same'		=> 'TZC_JS_SAME',
			'hours'		=> 'TZC_JS_HOURS',
			'prev'		=> 'TZC_JS_PREV',
			'next'		=> 'TZC_JS_NEXT',
			'pause'		=> 'TZC_JS_PAUSE',
			'play'		=> 'TZC_JS_PLAY',
			'collapse'	=> 'TZC_JS_COLLAPSE',
			'expand'	=> 'TZC_JS_EXPAND',
			'settings'	=> 'TZC_JS_SETTINGS',
			'am'		=> 'TZC_JS_AM',
			'pm'		=> 'TZC_JS_PM',
			'day'		=> 'TZC_JS_DAY',
			'night'		=> 'TZC_JS_NIGHT',
			'dawn'		=> 'TZC_JS_DAWN',
			'dusk'		=> 'TZC_JS_DUSK',
			'page'		=> 'TZC_JS_PAGE',
			'cities'	=> 'TZC_JS_CITIES',
			'find'			=> 'TZC_JS_FIND',
			'find_title'	=> 'TZC_JS_FIND_TITLE',
			'find_ph'		=> 'TZC_JS_FIND_PLACEHOLDER',
			'find_recent'	=> 'TZC_JS_FIND_RECENT',
			'find_all'		=> 'TZC_JS_FIND_ALL',
			'find_none'		=> 'TZC_JS_FIND_NONE',
			'find_keys'		=> 'TZC_JS_FIND_KEYS',
			'close'			=> 'TZC_JS_CLOSE',
			'tip_more'		=> 'TZC_JS_TIP_MORE',
			'your_zone'		=> 'TZC_JS_YOUR_ZONE',
			'diff_same'		=> 'TZC_JS_DIFF_SAME',
			'diff_ahead'	=> 'TZC_JS_DIFF_AHEAD',
			'diff_behind'	=> 'TZC_JS_DIFF_BEHIND',
			'info_diff'		=> 'TZC_JS_INFO_DIFF',
			'info_offset'	=> 'TZC_JS_INFO_OFFSET',
			'info_zone'		=> 'TZC_JS_INFO_ZONE',
			'info_next'		=> 'TZC_JS_INFO_NEXT',
			'info_phase'	=> 'TZC_JS_INFO_PHASE',
			'info_sun'		=> 'TZC_JS_INFO_SUN',
			'info_note'		=> 'TZC_JS_INFO_NOTE',
			'dst_on'		=> 'TZC_JS_DST_ON',
			'dst_off'		=> 'TZC_JS_DST_OFF',
			'dst_none'		=> 'TZC_JS_DST_NONE',
			'next_fmt'		=> 'TZC_JS_NEXT_FMT',
			'next_none'		=> 'TZC_JS_NEXT_NONE',
			'next_dst_start'	=> 'TZC_JS_NEXT_DST_START',
			'next_dst_end'	=> 'TZC_JS_NEXT_DST_END',
			'sun_fmt'		=> 'TZC_JS_SUN_FMT',
			'sun_polar_day'	=> 'TZC_JS_SUN_POLAR_DAY',
			'sun_polar_night'	=> 'TZC_JS_SUN_POLAR_NIGHT',
			'region_europe'		=> 'TZC_JS_REGION_EUROPE',
			'region_america'	=> 'TZC_JS_REGION_AMERICA',
			'region_asia'		=> 'TZC_JS_REGION_ASIA',
			'region_africa'		=> 'TZC_JS_REGION_AFRICA',
			'region_oceania'	=> 'TZC_JS_REGION_OCEANIA',
			'region_atlantic'	=> 'TZC_JS_REGION_ATLANTIC',
			'region_indian'		=> 'TZC_JS_REGION_INDIAN',
			'region_antarctica'	=> 'TZC_JS_REGION_ANTARCTICA',
			'region_other'		=> 'TZC_JS_REGION_OTHER',
		];

		$out = [];
		foreach ($keys as $js => $lang)
		{
			$out[$js] = $this->language->lang($lang);
		}

		return $out;
	}
}
