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
	'TZC_UCP_INTRO'				=> 'Choose how the clock bar looks for you. Options left on “Board default” follow the administrator settings.',
	'TZC_UCP_DISABLED'			=> 'Clock customisation is disabled.',
	'TZC_UCP_SAVED'				=> 'Your clock preferences have been saved.',
	'TZC_UCP_SHOW'				=> 'Show the clock bar',
	'TZC_UCP_SHOW_EXPLAIN'		=> 'Choose “No” to hide it completely.',
	'TZC_UCP_SOURCE'			=> 'Cities to show',
	'TZC_UCP_SOURCE_EXPLAIN'	=> 'You can use the board cities, your own (up to %d) or both.',
	'TZC_UCP_SOURCE_ADMIN'		=> 'The board cities',
	'TZC_UCP_SOURCE_MINE'		=> 'Only my cities',
	'TZC_UCP_SOURCE_BOTH'		=> 'My cities, then the board cities',
	'TZC_UCP_MY_CITIES'			=> 'My cities',
	'TZC_UCP_NO_CITIES'			=> 'You have not added any city yet: search them above.',
	'TZC_UCP_GEO_HINT'			=> 'You can search by country or time zone; searching every city of the world will be available once the administrator imports the catalogue.',
	'TZC_UCP_RESET'				=> 'Restore defaults',
	'TZC_UCP_RESET_CONFIRM'		=> 'Do you really want to delete all your preferences and cities?',
]);
