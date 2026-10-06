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
	// Locale used by the browser for day and month names
	'TZC_LOCALE'		=> 'it-IT',

	// Bar
	'TZC_JS_TITLE'		=> 'Orologi dal mondo',
	'TZC_JS_CITIES'		=> 'Città',
	'TZC_JS_TODAY'		=> 'Oggi',
	'TZC_JS_TOMORROW'	=> 'Domani',
	'TZC_JS_YESTERDAY'	=> 'Ieri',
	'TZC_JS_DST'		=> 'Ora legale',
	'TZC_JS_HOME'		=> 'La tua ora',
	'TZC_JS_SAME'		=> 'Stessa ora',
	'TZC_JS_HOURS'		=> 'h',
	'TZC_JS_PREV'		=> 'Città precedenti',
	'TZC_JS_NEXT'		=> 'Città successive',
	'TZC_JS_PAUSE'		=> 'Ferma lo scorrimento',
	'TZC_JS_PLAY'		=> 'Avvia lo scorrimento',
	'TZC_JS_COLLAPSE'	=> 'Comprimi la barra',
	'TZC_JS_EXPAND'		=> 'Espandi la barra',
	'TZC_JS_SETTINGS'	=> 'Personalizza gli orologi',
	'TZC_JS_AM'			=> ' am',
	'TZC_JS_PM'			=> ' pm',
	'TZC_JS_DAY'		=> 'Giorno',
	'TZC_JS_NIGHT'		=> 'Notte',
	'TZC_JS_DAWN'		=> 'Alba',
	'TZC_JS_DUSK'		=> 'Tramonto',
	'TZC_JS_PAGE'		=> 'Pagina %d',

	// City finder in the bar
	'TZC_JS_FIND'				=> 'Cerca una città',
	'TZC_JS_FIND_TITLE'			=> 'Quale città cerchi?',
	'TZC_JS_FIND_PLACEHOLDER'	=> 'Cerca una città o un paese…',
	'TZC_JS_FIND_RECENT'		=> 'Cercate di recente',
	'TZC_JS_FIND_ALL'			=> 'Tutte le città',
	'TZC_JS_FIND_NONE'			=> 'Nessuna città trovata.',
	'TZC_JS_FIND_KEYS'			=> '↑↓ per scegliere, Invio per mostrare, Esc per chiudere',
	'TZC_JS_CLOSE'				=> 'Chiudi',
	'TZC_JS_REGION_EUROPE'		=> 'Europa',
	'TZC_JS_REGION_AMERICA'		=> 'Americhe',
	'TZC_JS_REGION_ASIA'		=> 'Asia',
	'TZC_JS_REGION_AFRICA'		=> 'Africa',
	'TZC_JS_REGION_OCEANIA'		=> 'Oceania',
	'TZC_JS_REGION_ATLANTIC'	=> 'Oceano Atlantico',
	'TZC_JS_REGION_INDIAN'		=> 'Oceano Indiano',
	'TZC_JS_REGION_ANTARCTICA'	=> 'Antartide',
	'TZC_JS_REGION_OTHER'		=> 'Altri fusi',

	// Preview (ACP and UCP)
	'TZC_PREVIEW'			=> 'Anteprima',
	'TZC_PREVIEW_LIVE'		=> '(si aggiorna mentre modifichi, salva per renderla definitiva)',
	'TZC_PREVIEW_EMPTY'		=> 'Nessuna città nella barra. <a href="%s">Aggiungine qualcuna</a>.',
	'TZC_PREVIEW_NOCITIES'	=> 'Nessuna città da mostrare: aggiungine una con la ricerca.',

	// City picker (ACP and UCP)
	'TZC_PICKER_PLACEHOLDER'	=> 'Cerca una città, un paese o un fuso orario (es. Mosca, Brasile, Europe/Rome)…',
	'TZC_PICKER_HELP'			=> 'Trascina le righe per riordinarle; il nome si può modificare.',
	'TZC_PICKER_SEARCHING'		=> 'Ricerca in corso…',
	'TZC_PICKER_ERROR'			=> 'Errore durante la ricerca, riprova.',
	'TZC_PICKER_NONE'			=> 'Nessun risultato.',
	'TZC_PICKER_MAX'			=> 'Hai raggiunto il numero massimo di città (%d).',
	'TZC_PICKER_DRAG'			=> 'Trascina per spostare',
	'TZC_PICKER_RENAME'			=> 'Nome mostrato nella barra',
	'TZC_PICKER_MOBILE'			=> 'su mobile',
	'TZC_PICKER_UP'				=> 'Sposta su',
	'TZC_PICKER_DOWN'			=> 'Sposta giù',
	'TZC_PICKER_REMOVE'			=> 'Rimuovi',
	'TZC_PICKER_INHABITANTS'	=> 'abitanti',
	'TZC_PICKER_TYPE_CITY'		=> 'città',
	'TZC_PICKER_TYPE_ZONE'		=> 'fuso',
	'TZC_PICKER_ADD'			=> 'Aggiungi',
	'TZC_PICKER_STEPS'			=> 'Per aggiungere una città: 1) scrivine il nome (o un paese, es. “Brasile”) nella casella; 2) clicca il risultato oppure premi Aggiungi / Invio; 3) premi Invia in fondo alla pagina per salvare.',
	'TZC_CONFIRM_TITLE'			=> 'Conferma',
	'TZC_OK'					=> 'OK',
]);
