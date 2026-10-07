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
	'TZC_UCP_DEFAULT'	=> 'Predefinito del forum (%s)',
	'TZC_UCP_TAG'		=> 'UCP',
	'TZC_UCP_TAG_EXPLAIN'	=> 'Gli utenti possono personalizzare questa opzione dal pannello utente; qui imposti il valore predefinito.',

	// Sections
	'TZC_SEC_GENERAL'			=> 'Generale',
	'TZC_SEC_GENERAL_EXPLAIN'	=> 'Dove e a chi mostrare la barra.',
	'TZC_SEC_DISPLAY'			=> 'Contenuto delle schede',
	'TZC_SEC_DISPLAY_EXPLAIN'	=> 'Cosa mostra ogni orologio.',
	'TZC_SEC_MOTION'			=> 'Scorrimento',
	'TZC_SEC_MOTION_EXPLAIN'	=> 'Comportamento del carosello e del ticker.',
	'TZC_SEC_APPEARANCE'		=> 'Aspetto',
	'TZC_SEC_USERS'				=> 'Utenti (UCP)',
	'TZC_SEC_USERS_EXPLAIN'		=> 'Cosa possono personalizzare gli utenti registrati.',
	'TZC_SEC_UPDATES'			=> 'Aggiornamenti automatici',
	'TZC_SEC_UPDATES_EXPLAIN'	=> 'Le regole dei fusi orari (ora legale, cambi di fuso decisi dai governi) vengono scaricate dal database ufficiale IANA. Le città del mondo provengono da GeoNames.',

	// General
	'TZC_OPT_ENABLE'			=> 'Mostra la barra degli orologi',
	'TZC_OPT_PAGES'				=> 'Pagine',
	'TZC_OPT_PAGES_INDEX'		=> 'Solo l’indice del forum',
	'TZC_OPT_PAGES_ALL'			=> 'Tutte le pagine',
	'TZC_OPT_PAGES_CUSTOM'		=> 'Pagine scelte',
	'TZC_OPT_PAGES_LIST'		=> 'Pagine scelte',
	'TZC_OPT_PAGES_LIST_EXPLAIN'	=> 'Usato quando “Pagine” è impostato su “Pagine scelte”.',
	'TZC_OPT_PAGES_LIST_INDEX'		=> 'Indice',
	'TZC_OPT_PAGES_LIST_VIEWFORUM'	=> 'Forum',
	'TZC_OPT_PAGES_LIST_VIEWTOPIC'	=> 'Argomenti',
	'TZC_OPT_PAGES_LIST_SEARCH'		=> 'Ricerca',
	'TZC_OPT_PAGES_LIST_MEMBERLIST'	=> 'Utenti e profili',
	'TZC_OPT_PAGES_LIST_UCP'		=> 'Pannello utente',
	'TZC_OPT_PAGES_LIST_MCP'		=> 'Pannello moderatori',
	'TZC_OPT_PAGES_LIST_POSTING'	=> 'Scrittura messaggi',
	'TZC_OPT_PAGES_LIST_FAQ'		=> 'FAQ',
	'TZC_OPT_PAGES_LIST_VIEWONLINE'	=> 'Chi c’è in linea',
	'TZC_OPT_PAGES_LIST_APP'		=> 'Pagine delle estensioni (app.php)',
	'TZC_OPT_POSITION'			=> 'Posizione',
	'TZC_OPT_POSITION_EXPLAIN'	=> 'Punto della pagina in cui appare la barra.',
	'TZC_OPT_POSITION_ABOVE'	=> 'Sopra il contenuto, subito sotto l’intestazione',
	'TZC_OPT_POSITION_TOP'		=> 'In cima al contenuto',
	'TZC_OPT_POSITION_BOTTOM'	=> 'In fondo al contenuto',
	'TZC_OPT_POSITION_FOOTER'	=> 'Sopra il piè di pagina',
	'TZC_OPT_GUESTS'			=> 'Mostra agli ospiti',
	'TZC_OPT_BOTS'				=> 'Mostra ai bot',
	'TZC_OPT_MOBILE'			=> 'Mostra sugli schermi piccoli',
	'TZC_OPT_MOBILE_EXPLAIN'	=> 'Su smartphone la barra diventa scorrevole con il dito. Ogni città può comunque essere esclusa dal mobile nella pagina “Città della barra”.',
	'TZC_OPT_COLLAPSIBLE'		=> 'Barra comprimibile',
	'TZC_OPT_COLLAPSIBLE_EXPLAIN'	=> 'Aggiunge un pulsante per ridurre la barra a una riga; la scelta viene ricordata dal browser.',

	// Display
	'TZC_OPT_MODE'				=> 'Modalità',
	'TZC_OPT_MODE_CAROUSEL'		=> 'Carosello (frecce, puntini, scorrimento automatico)',
	'TZC_OPT_MODE_TICKER'		=> 'Ticker (scorrimento continuo)',
	'TZC_OPT_MODE_GRID'			=> 'Griglia (tutte le città visibili)',
	'TZC_OPT_DENSITY'			=> 'Dimensione delle schede',
	'TZC_OPT_DENSITY_NORMAL'	=> 'Normale',
	'TZC_OPT_DENSITY_COMPACT'	=> 'Compatta (una riga)',
	'TZC_OPT_FORMAT'			=> 'Formato dell’ora',
	'TZC_OPT_FORMAT_24'			=> '24 ore',
	'TZC_OPT_FORMAT_12'			=> '12 ore (am/pm)',
	'TZC_OPT_SECONDS'			=> 'Mostra i secondi',
	'TZC_OPT_SHOW_DATE'			=> 'Mostra la data',
	'TZC_OPT_SHOW_DATE_EXPLAIN'	=> 'Con l’indicazione “Domani” o “Ieri” quando la città è in un altro giorno.',
	'TZC_OPT_SHOW_FLAG'			=> 'Mostra la bandiera',
	'TZC_OPT_FLAG_STYLE'		=> 'Tipo di bandiera',
	'TZC_OPT_FLAG_STYLE_EXPLAIN'	=> 'Le immagini SVG si vedono ovunque; le emoji non vengono mostrate da Windows.',
	'TZC_OPT_FLAG_STYLE_SVG'	=> 'Immagine SVG',
	'TZC_OPT_FLAG_STYLE_EMOJI'	=> 'Emoji',
	'TZC_OPT_SHOW_COUNTRY'		=> 'Mostra il nome del paese',
	'TZC_OPT_SHOW_DAYNIGHT'		=> 'Icona giorno/notte',
	'TZC_OPT_SHOW_DAYNIGHT_EXPLAIN'	=> 'Sole, alba, tramonto o luna, calcolati sulla posizione reale del sole nella città.',
	'TZC_OPT_DAYNIGHT_BG'		=> 'Sfondo giorno/notte',
	'TZC_OPT_DAYNIGHT_BG_EXPLAIN'	=> 'Il colore della scheda cambia con l’ora del giorno nella città.',
	'TZC_OPT_ANALOG'			=> 'Orologio analogico',
	'TZC_OPT_SHOW_DIFF'			=> 'Differenza con la tua ora',
	'TZC_OPT_SHOW_DIFF_EXPLAIN'	=> 'Ad esempio “+8 h”, calcolata sul fuso orario impostato nel profilo dell’utente.',
	'TZC_OPT_SHOW_DST'			=> 'Indica l’ora legale',
	'TZC_OPT_SHOW_ABBR'			=> 'Sigla del fuso (CET, EDT…)',
	'TZC_OPT_SHOW_UTC'			=> 'Scostamento da UTC',
	'TZC_OPT_HIGHLIGHT_HOME'	=> 'Evidenzia “La tua ora”',
	'TZC_OPT_HIGHLIGHT_HOME_EXPLAIN'	=> 'Mette in risalto la città che ha lo stesso fuso orario dell’utente.',
	'TZC_OPT_ADD_HOME'			=> 'Aggiungi sempre “La tua ora”',
	'TZC_OPT_ADD_HOME_EXPLAIN'	=> 'Se il fuso dell’utente non è già tra le città, aggiunge in testa una scheda con la sua ora.',
	'TZC_OPT_SORT'				=> 'Ordine delle città',
	'TZC_OPT_SORT_MANUAL'		=> 'Come impostato',
	'TZC_OPT_SORT_EAST'			=> 'Da est a ovest',
	'TZC_OPT_SORT_WEST'			=> 'Da ovest a est',
	'TZC_OPT_SORT_NAME'			=> 'Alfabetico',
	'TZC_OPT_TOOLTIP'			=> 'Suggerimento al passaggio del mouse',
	'TZC_OPT_TOOLTIP_EXPLAIN'	=> 'Riquadro con città, ora, data, scostamento da UTC, ora legale e differenza dalla tua ora. Solo su computer: su smartphone si usa la scheda informativa.',
	'TZC_OPT_INFO'				=> 'Scheda informativa al clic',
	'TZC_OPT_INFO_EXPLAIN'		=> 'Clic (o tocco) su una città: ora con i secondi, data completa, fuso orario, ora legale, prossimo cambio d’ora, alba e tramonto. Su smartphone si apre dal basso.',
	'TZC_OPT_SEARCH'			=> 'Ricerca delle città nella barra',
	'TZC_OPT_SEARCH_EXPLAIN'	=> 'Pulsante con la lente che apre un riquadro per trovare al volo una città: digiti il nome, la barra scorre fino alla sua scheda e la evidenzia.',
	'TZC_OPT_SEARCH_AUTO'		=> 'Solo quando le città sono molte',
	'TZC_OPT_SEARCH_ALWAYS'		=> 'Sempre',
	'TZC_OPT_SEARCH_NEVER'		=> 'Mai',
	'TZC_OPT_SEARCH_MIN'		=> 'Città minime per mostrare la ricerca',
	'TZC_OPT_SEARCH_MIN_EXPLAIN'	=> 'Usato con “Solo quando le città sono molte”.',

	// Motion
	'TZC_OPT_AUTOPLAY'			=> 'Scorrimento automatico',
	'TZC_OPT_AUTOPLAY_EXPLAIN'	=> 'Si ferma da solo quando il mouse è sopra la barra e per chi ha attivato “riduci movimento” nel sistema.',
	'TZC_OPT_AUTOPLAY_DELAY'	=> 'Pausa tra uno scatto e l’altro (secondi)',
	'TZC_OPT_AUTOPLAY_DELAY_EXPLAIN'	=> 'Solo per il carosello.',
	'TZC_OPT_TICKER_SPEED'		=> 'Velocità del ticker (pixel al secondo)',
	'TZC_OPT_PAUSE_HOVER'		=> 'Pausa al passaggio del mouse',
	'TZC_OPT_ARROWS'			=> 'Frecce di navigazione',
	'TZC_OPT_DOTS'				=> 'Indicatori di pagina (puntini)',

	// Appearance
	'TZC_OPT_THEME'				=> 'Tema',
	'TZC_OPT_THEME_EXPLAIN'		=> '“Automatico” usa colori trasparenti che si adattano allo stile del forum, chiaro o scuro.',
	'TZC_OPT_THEME_AUTO'		=> 'Automatico',
	'TZC_OPT_THEME_LIGHT'		=> 'Chiaro',
	'TZC_OPT_THEME_DARK'		=> 'Scuro',
	'TZC_OPT_THEME_GLASS'		=> 'Vetro colorato',
	'TZC_OPT_ACCENT'			=> 'Colore principale',
	'TZC_OPT_ACCENT_EXPLAIN'	=> 'Usato per “La tua ora”, i puntini, le lancette e lo sfondo del tema “Vetro colorato”.',
	'TZC_OPT_CARD_WIDTH'		=> 'Larghezza delle schede (pixel)',
	'TZC_OPT_RADIUS'			=> 'Arrotondamento degli angoli (pixel)',

	// Users
	'TZC_OPT_UCP_ENABLE'		=> 'Permetti la personalizzazione dal pannello utente',
	'TZC_OPT_UCP_ENABLE_EXPLAIN'	=> 'Abilita la pagina “Orologi dal mondo” nelle preferenze del pannello utente.',
	'TZC_OPT_UCP_HIDE'			=> 'Permetti di nascondere la barra',
	'TZC_OPT_UCP_CITIES'		=> 'Permetti agli utenti di scegliere le proprie città',
	'TZC_OPT_UCP_MAX'			=> 'Numero massimo di città per utente',

	// Updates
	'TZC_OPT_CRON_ENABLE'		=> 'Aggiorna i fusi orari automaticamente',
	'TZC_OPT_CRON_ENABLE_EXPLAIN'	=> 'Usa il cron di phpBB. I lavori lunghi vengono divisi su più esecuzioni.',
	'TZC_OPT_CRON_DAYS'			=> 'Controlla gli aggiornamenti ogni (giorni)',
	'TZC_OPT_CRON_CITIES'		=> 'Aggiorna automaticamente anche il catalogo delle città',
	'TZC_OPT_CRON_CITIES_EXPLAIN'	=> 'Il catalogo è grande e cambia poco: un aggiornamento ogni uno o due mesi è sufficiente.',
	'TZC_OPT_CRON_CITIES_DAYS'	=> 'Aggiorna le città ogni (giorni)',
	'TZC_OPT_TZ_SOURCE'			=> 'Sorgente dei fusi orari',
	'TZC_OPT_TZ_SOURCE_EXPLAIN'	=> 'File “packed” di moment-timezone (database IANA). {version} viene sostituito con l’ultima versione pubblicata.',
	'TZC_OPT_TZ_SOURCE_ALT'		=> 'Sorgente alternativa',
	'TZC_OPT_TZ_SOURCE_ALT_EXPLAIN'	=> 'Usata se la prima non risponde.',
	'TZC_OPT_TZ_REGISTRY'		=> 'Controllo della versione',
	'TZC_OPT_TZ_REGISTRY_EXPLAIN'	=> 'Indirizzo che indica l’ultima versione pubblicata; se è già installata il download viene saltato.',
	'TZC_OPT_GEO_DATASET'		=> 'Catalogo delle città',
	'TZC_OPT_GEO_DATASET_EXPLAIN'	=> 'Più città significa ricerche più complete ma un database più grande.',
	'TZC_OPT_GEO_DATASET_CITIES15000'	=> 'Città sopra 15.000 abitanti (circa 33.000, consigliato)',
	'TZC_OPT_GEO_DATASET_CITIES5000'	=> 'Città sopra 5.000 abitanti (circa 68.000)',
	'TZC_OPT_GEO_DATASET_CITIES1000'	=> 'Città sopra 1.000 abitanti (circa 165.000)',
	'TZC_OPT_GEO_DATASET_CITIES500'		=> 'Città sopra 500 abitanti (circa 230.000)',
	'TZC_OPT_GEO_SOURCE'		=> 'Sorgente del catalogo città',
	'TZC_OPT_CHUNK_KB'			=> 'Dimensione dei blocchi di download (KB)',
	'TZC_OPT_CHUNK_KB_EXPLAIN'	=> 'I file vengono scaricati a pezzi per mostrare la percentuale reale e non superare i limiti di tempo del server.',
	'TZC_OPT_STEP_TIME'			=> 'Durata massima di ogni passo (secondi)',
	'TZC_OPT_STEP_TIME_EXPLAIN'	=> 'Abbassalo se il tuo hosting interrompe le richieste lunghe.',
]);
