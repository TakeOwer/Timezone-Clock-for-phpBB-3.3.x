<?php
/**
 *
 * Timezone Clock. An extension for the phpBB Forum Software package.
 * [English]
 *
 * @copyright (c) 2026 Salvo Cortesiano <https://netshadows.de>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

$lang = array_merge($lang, [
	'TZC_LOCALE'		=> 'en-GB',

	'TZC_JS_TITLE'		=> 'World clocks',
	'TZC_JS_CITIES'		=> 'Cities',
	'TZC_JS_TODAY'		=> 'Today',
	'TZC_JS_TOMORROW'	=> 'Tomorrow',
	'TZC_JS_YESTERDAY'	=> 'Yesterday',
	'TZC_JS_DST'		=> 'DST',
	'TZC_JS_HOME'		=> 'Your time',
	'TZC_JS_SAME'		=> 'Same time',
	'TZC_JS_HOURS'		=> 'h',
	'TZC_JS_PREV'		=> 'Previous cities',
	'TZC_JS_NEXT'		=> 'Next cities',
	'TZC_JS_PAUSE'		=> 'Pause scrolling',
	'TZC_JS_PLAY'		=> 'Start scrolling',
	'TZC_JS_COLLAPSE'	=> 'Collapse the bar',
	'TZC_JS_EXPAND'		=> 'Expand the bar',
	'TZC_JS_SETTINGS'	=> 'Customise the clocks',
	'TZC_JS_AM'			=> ' am',
	'TZC_JS_PM'			=> ' pm',
	'TZC_JS_DAY'		=> 'Day',
	'TZC_JS_NIGHT'		=> 'Night',
	'TZC_JS_DAWN'		=> 'Dawn',
	'TZC_JS_DUSK'		=> 'Dusk',
	'TZC_JS_PAGE'		=> 'Page %d',

	'TZC_JS_FIND'				=> 'Find a city',
	'TZC_JS_FIND_TITLE'			=> 'Which city are you looking for?',
	'TZC_JS_FIND_PLACEHOLDER'	=> 'Search a city or a country…',
	'TZC_JS_FIND_RECENT'		=> 'Recently searched',
	'TZC_JS_FIND_ALL'			=> 'All cities',
	'TZC_JS_FIND_NONE'			=> 'No city found.',
	'TZC_JS_FIND_KEYS'			=> '↑↓ to choose, Enter to show, Esc to close',
	'TZC_JS_CLOSE'				=> 'Close',
	'TZC_JS_REGION_EUROPE'		=> 'Europe',
	'TZC_JS_REGION_AMERICA'		=> 'Americas',
	'TZC_JS_REGION_ASIA'		=> 'Asia',
	'TZC_JS_REGION_AFRICA'		=> 'Africa',
	'TZC_JS_REGION_OCEANIA'		=> 'Oceania',
	'TZC_JS_REGION_ATLANTIC'	=> 'Atlantic Ocean',
	'TZC_JS_REGION_INDIAN'		=> 'Indian Ocean',
	'TZC_JS_REGION_ANTARCTICA'	=> 'Antarctica',
	'TZC_JS_REGION_OTHER'		=> 'Other zones',

	'TZC_PREVIEW'			=> 'Preview',
	'TZC_PREVIEW_LIVE'		=> '(updates while you edit, save to make it permanent)',
	'TZC_PREVIEW_EMPTY'		=> 'There are no cities in the bar. <a href="%s">Add some</a>.',
	'TZC_PREVIEW_NOCITIES'	=> 'No city to show: add one with the search box.',

	'TZC_PICKER_PLACEHOLDER'	=> 'Search a city, a country or a time zone (e.g. Moscow, Brazil, Europe/Rome)…',
	'TZC_PICKER_HELP'			=> 'Drag the rows to reorder them; names can be edited.',
	'TZC_PICKER_SEARCHING'		=> 'Searching…',
	'TZC_PICKER_ERROR'			=> 'Search failed, please try again.',
	'TZC_PICKER_NONE'			=> 'No results.',
	'TZC_PICKER_MAX'			=> 'You reached the maximum number of cities (%d).',
	'TZC_PICKER_DRAG'			=> 'Drag to move',
	'TZC_PICKER_RENAME'			=> 'Name shown in the bar',
	'TZC_PICKER_MOBILE'			=> 'on mobile',
	'TZC_PICKER_UP'				=> 'Move up',
	'TZC_PICKER_DOWN'			=> 'Move down',
	'TZC_PICKER_REMOVE'			=> 'Remove',
	'TZC_PICKER_INHABITANTS'	=> 'inhabitants',
	'TZC_PICKER_TYPE_CITY'		=> 'city',
	'TZC_PICKER_TYPE_ZONE'		=> 'zone',
	'TZC_PICKER_ADD'			=> 'Add',
	'TZC_PICKER_STEPS'			=> 'To add a city: 1) type its name (or a country, e.g. “Brazil”) in the box; 2) click the result or press Add / Enter; 3) press Submit at the bottom of the page to save.',
	'TZC_CONFIRM_TITLE'			=> 'Confirm',
	'TZC_OK'					=> 'OK',
]);
