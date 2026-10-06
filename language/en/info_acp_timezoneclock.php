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
	'ACP_TZC_TITLE'				=> 'Timezone Clock',
	'ACP_TZC_SETTINGS'			=> 'Settings',
	'ACP_TZC_CITIES'			=> 'Bar cities',
	'ACP_TZC_DATABASE'			=> 'Time zones and updates',
	'ACP_TZC_CHECKUP'			=> 'Check-up',

	'TZC_NOT_ENABLEABLE'		=> 'Timezone Clock requires phpBB 3.3.x, PHP 7.4 or newer and the PHP zlib extension.',

	'LOG_TZC_SETTINGS'			=> '<strong>Timezone Clock:</strong> settings saved',
	'LOG_TZC_SETTINGS_RESET'	=> '<strong>Timezone Clock:</strong> settings restored to defaults',
	'LOG_TZC_CITIES'			=> '<strong>Timezone Clock:</strong> bar cities updated',
	'LOG_TZC_CONFIG_FIXED'		=> '<strong>Timezone Clock:</strong> configuration repaired by the check-up (values restored: %s)',
	'LOG_TZC_TZDATA_UPDATED_ACP'	=> '<strong>Timezone Clock:</strong> time zones manually updated to version %1$s (zones with changed rules: %2$s)',
	'LOG_TZC_TZDATA_UPDATED_CRON'	=> '<strong>Timezone Clock:</strong> time zones updated by the cron to version %1$s (zones with changed rules: %2$s)',
	'LOG_TZC_TZDATA_UPDATED_CLI'	=> '<strong>Timezone Clock:</strong> time zones updated from the command line to version %1$s (zones with changed rules: %2$s)',
	'LOG_TZC_CITIES_UPDATED_ACP'	=> '<strong>Timezone Clock:</strong> city catalogue %1$s manually imported (%2$s cities)',
	'LOG_TZC_CITIES_UPDATED_CRON'	=> '<strong>Timezone Clock:</strong> city catalogue %1$s imported by the cron (%2$s cities)',
	'LOG_TZC_CITIES_UPDATED_CLI'	=> '<strong>Timezone Clock:</strong> city catalogue %1$s imported from the command line (%2$s cities)',
	'LOG_TZC_UPDATE_FAILED'		=> '<strong>Timezone Clock:</strong> update failed<br>» %s',
]);
