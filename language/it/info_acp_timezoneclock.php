<?php
/**
 *
 * Timezone Clock. An extension for the phpBB Forum Software package.
 * [Italiano]
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
	'ACP_TZC_SETTINGS'			=> 'Impostazioni',
	'ACP_TZC_CITIES'			=> 'Città della barra',
	'ACP_TZC_DATABASE'			=> 'Fusi orari e aggiornamenti',
	'ACP_TZC_CHECKUP'			=> 'Check-up',

	'TZC_NOT_ENABLEABLE'		=> 'Timezone Clock richiede phpBB 3.3.x, PHP 7.4 o superiore e l’estensione PHP zlib.',

	'LOG_TZC_SETTINGS'			=> '<strong>Timezone Clock:</strong> impostazioni salvate',
	'LOG_TZC_SETTINGS_RESET'	=> '<strong>Timezone Clock:</strong> impostazioni riportate ai valori predefiniti',
	'LOG_TZC_CITIES'			=> '<strong>Timezone Clock:</strong> città della barra aggiornate',
	'LOG_TZC_CONFIG_FIXED'		=> '<strong>Timezone Clock:</strong> configurazione riparata dal check-up (valori ripristinati: %s)',
	'LOG_TZC_TZDATA_UPDATED_ACP'	=> '<strong>Timezone Clock:</strong> fusi orari aggiornati manualmente alla versione %1$s (zone con regole cambiate: %2$s)',
	'LOG_TZC_TZDATA_UPDATED_CRON'	=> '<strong>Timezone Clock:</strong> fusi orari aggiornati dal cron alla versione %1$s (zone con regole cambiate: %2$s)',
	'LOG_TZC_TZDATA_UPDATED_CLI'	=> '<strong>Timezone Clock:</strong> fusi orari aggiornati da riga di comando alla versione %1$s (zone con regole cambiate: %2$s)',
	'LOG_TZC_CITIES_UPDATED_ACP'	=> '<strong>Timezone Clock:</strong> catalogo città %1$s importato manualmente (%2$s città)',
	'LOG_TZC_CITIES_UPDATED_CRON'	=> '<strong>Timezone Clock:</strong> catalogo città %1$s importato dal cron (%2$s città)',
	'LOG_TZC_CITIES_UPDATED_CLI'	=> '<strong>Timezone Clock:</strong> catalogo città %1$s importato da riga di comando (%2$s città)',
	'LOG_TZC_UPDATE_FAILED'		=> '<strong>Timezone Clock:</strong> aggiornamento non riuscito<br>» %s',
]);
