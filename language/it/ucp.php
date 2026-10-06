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
	'TZC_UCP_INTRO'				=> 'Scegli come vedere la barra degli orologi. Le opzioni lasciate su “Predefinito del forum” seguono le impostazioni dell’amministratore.',
	'TZC_UCP_DISABLED'			=> 'La personalizzazione degli orologi è disattivata.',
	'TZC_UCP_SAVED'				=> 'Le tue preferenze per gli orologi sono state salvate.',
	'TZC_UCP_SHOW'				=> 'Mostra la barra degli orologi',
	'TZC_UCP_SHOW_EXPLAIN'		=> 'Scegli “No” per nasconderla del tutto.',
	'TZC_UCP_SOURCE'			=> 'Città da mostrare',
	'TZC_UCP_SOURCE_EXPLAIN'	=> 'Puoi usare le città del forum, le tue (fino a %d) oppure entrambe.',
	'TZC_UCP_SOURCE_ADMIN'		=> 'Le città scelte dal forum',
	'TZC_UCP_SOURCE_MINE'		=> 'Solo le mie città',
	'TZC_UCP_SOURCE_BOTH'		=> 'Le mie città e poi quelle del forum',
	'TZC_UCP_MY_CITIES'			=> 'Le mie città',
	'TZC_UCP_NO_CITIES'			=> 'Non hai ancora aggiunto città: cercale qui sopra.',
	'TZC_UCP_GEO_HINT'			=> 'Puoi cercare per paese o per fuso orario; la ricerca di tutte le città del mondo sarà disponibile quando l’amministratore avrà importato il catalogo.',
	'TZC_UCP_RESET'				=> 'Ripristina predefiniti',
	'TZC_UCP_RESET_CONFIRM'		=> 'Vuoi davvero cancellare tutte le tue preferenze e le tue città?',
]);
