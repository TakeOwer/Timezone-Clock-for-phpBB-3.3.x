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
	'UCP_TZC_TITLE'		=> 'World clocks',
	'UCP_TZC_SETTINGS'	=> 'World clocks',
]);
