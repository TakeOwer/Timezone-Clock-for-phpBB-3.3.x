# Timezone Clock 1.0.6 — Guida completa

Barra degli orologi dal mondo per phpBB 3.3.x. Mostra l'ora delle città che scegli in un **carosello**, in un **ticker** a scorrimento continuo o in una **griglia**. I fusi orari restano sempre corretti grazie all'aggiornamento automatico dal database ufficiale **IANA**.

- **Pacchetto:** `salvocortesiano/timezoneclock`
- **Autore:** Salvo Cortesiano — <https://netshadows.de> — support@netshadows.de
- **Licenza:** GPL-2.0
- **Ispirata a:** "Timezone Clock" di HiFiKabin & ctrstudio, riscritta da zero.

---

## Indice

1. [Requisiti](#1-requisiti)
2. [Installazione](#2-installazione)
3. [Come funziona](#3-come-funziona)
4. [ACP — Impostazioni](#4-acp--impostazioni)
5. [ACP — Città della barra](#5-acp--città-della-barra)
6. [ACP — Fusi orari e aggiornamenti](#6-acp--fusi-orari-e-aggiornamenti)
7. [ACP — Check-up](#7-acp--check-up)
8. [Pannello utente (UCP)](#8-pannello-utente-ucp)
9. [Aggiornamenti automatici: cron e riga di comando](#9-aggiornamenti-automatici-cron-e-riga-di-comando)
10. [La barra sul forum](#10-la-barra-sul-forum)
11. [Risoluzione dei problemi](#11-risoluzione-dei-problemi)
12. [Disinstallazione](#12-disinstallazione)
13. [Struttura dei file](#13-struttura-dei-file)
14. [Fonti dei dati e licenze](#14-fonti-dei-dati-e-licenze)
15. [Novità delle versioni](#15-novità-delle-versioni)

---

## 1. Requisiti

| Requisito | Note |
|---|---|
| phpBB 3.3.0 o superiore (serie 3.3.x) | Il tuo forum è alla 3.3.19 |
| PHP 7.4 o superiore | Consigliato 8.2 |
| Estensione PHP **zlib** | Obbligatoria: serve a leggere i dati dei fusi |
| **cURL** oppure `allow_url_fopen` | Serve per gli aggiornamenti online |
| Estensione PHP **ZipArchive** | Solo per importare il catalogo delle città del mondo |
| Cartella `store/` scrivibile | Già scrivibile in ogni installazione di phpBB |

Gli orologi funzionano anche senza connessione a Internet, perché l'estensione contiene già i dati IANA (versione 2026e). Gli aggiornamenti online servono a restare al passo con i cambi decisi dai governi.

---

## 2. Installazione

È un'**installazione pulita**: crea le proprie tabelle e non dipende da altre estensioni.

1. Carica via FTP la cartella `salvocortesiano/timezoneclock` in `ext/`, in modo da avere il percorso `ext/salvocortesiano/timezoneclock/composer.json`.
2. Vai in **ACP → Personalizza → Gestione estensioni** e attiva **Timezone Clock**.
3. Svuota la cache: **ACP → Generale → Svuota la cache**.

All'attivazione l'estensione:
- crea 4 tabelle: `phpbb_tzc_cities`, `phpbb_tzc_zones`, `phpbb_tzc_geo` e `phpbb_tzc_users`;
- carica i **344 fusi orari IANA** e i **253 nomi alternativi** inclusi nel pacchetto;
- aggiunge **11 città di partenza**: Roma, Londra, New York, Los Angeles, San Paolo, Mosca, Dubai, Nuova Delhi, Pechino, Tokyo e Sydney. I nomi sono in italiano se la lingua predefinita del forum è l'italiano;
- crea la categoria ACP **Timezone Clock** (in *Estensioni*) con 4 pagine;
- aggiunge la pagina UCP **Orologi dal mondo** nelle *Preferenze del forum*.

Al primo giro del cron, l'estensione controlla online se esiste una versione più recente dei fusi orari.

**Consigliato subito dopo l'installazione:**
1. Apri **Check-up** e verifica che sia tutto verde.
2. In **Fusi orari e aggiornamenti** premi **Importa / aggiorna le città**. Così la ricerca troverà qualsiasi città del mondo, anche per nome italiano ("Mosca", "Pechino", "L'Avana").

### Aggiornare l'estensione in futuro

1. Disattiva l'estensione, **senza** cancellarne i dati.
2. Sostituisci i file via FTP.
3. Svuota la cache.
4. Riattivala.
5. Svuota di nuovo la cache e la cartella `cache/production/twig`.

---

## 3. Come funziona

Le vecchie estensioni calcolavano l'ora legale con regole scritte a mano nel JavaScript (per esempio "Europa: ultima domenica di marzo"). Quelle regole invecchiano: il Brasile ha abolito l'ora legale nel 2019, il Messico nel 2022, il Paraguay nel 2024, e un orologio con regole vecchie sbaglia di un'ora.

Timezone Clock fa così:

1. Scarica il **database IANA**, lo stesso usato da Linux, Android, iOS, Java e PHP, nel formato compatto pubblicato da *moment-timezone*.
2. Per ogni fuso calcola sul server **tutte le transizioni** (inizio e fine dell'ora legale, cambi di fuso) come istanti UTC assoluti e le salva nel database.
3. A ogni pagina invia al browser solo le transizioni utili per le città mostrate, circa 3 KB.
4. Il browser calcola l'ora con quei dati, **senza usare** il database dei fusi del computer dell'utente né quello di PHP sul server.

Il risultato: quando un paese cambia le regole, basta un aggiornamento (automatico o manuale) e tutti gli orologi sono corretti. Vale anche per un visitatore con Windows non aggiornato o per un server con PHP vecchio.

---

## 4. ACP — Impostazioni

In cima trovi l'**anteprima live**: si aggiorna mentre cambi le opzioni, prima ancora di salvare. Le opzioni con l'etichetta verde **UCP** possono essere personalizzate dagli utenti; qui imposti il loro valore predefinito.

### Generale

| Opzione | Descrizione |
|---|---|
| Mostra la barra degli orologi | Accende o spegne la barra per tutti. |
| Pagine | Solo l'indice, tutte le pagine o pagine scelte. |
| Pagine scelte | Indice, forum, argomenti, ricerca, utenti, UCP, MCP, scrittura, FAQ, chi c'è in linea, pagine delle estensioni (`app.php`). |
| Posizione | Sotto l'intestazione, in cima al contenuto, in fondo al contenuto o sopra il piè di pagina. |
| Mostra agli ospiti / ai bot | Visibilità per i visitatori non registrati e per i motori di ricerca. |
| Mostra sugli schermi piccoli | Su smartphone la barra si scorre col dito. |
| Barra comprimibile | Pulsante per ridurla a una riga, che mostra le prime città. Il browser ricorda la scelta. |

### Contenuto delle schede (personalizzabile dagli utenti)

| Opzione | Descrizione |
|---|---|
| Modalità | **Carosello** (frecce, puntini, scorrimento a scatti), **Ticker** (scorrimento continuo, trascinabile) o **Griglia** (tutte visibili). |
| Dimensione | Normale oppure compatta (una riga per città). |
| Formato | 24 ore o 12 ore am/pm. |
| Secondi | Mostra i secondi. |
| Data | Giorno e data, con "Domani" o "Ieri" quando la città è in un altro giorno rispetto all'utente. |
| Bandiera | Immagine SVG (si vede ovunque) oppure emoji (Windows non le mostra). |
| Nome del paese | Sotto il nome della città. |
| Icona giorno/notte | Sole, alba, tramonto o luna, calcolati sulla **posizione reale del sole** nella città. |
| Sfondo giorno/notte | Il colore della scheda cambia con l'ora locale. |
| Orologio analogico | Quadrante con lancette accanto all'ora digitale. |
| Differenza con la tua ora | Per esempio "+8 h", calcolata sul fuso del profilo dell'utente. |
| Ora legale / Sigla / UTC | Badge "Ora legale", sigla (CET, EDT…) e scostamento da UTC. |
| Evidenzia "La tua ora" | Mette in risalto la città con lo stesso fuso dell'utente. |
| Aggiungi sempre "La tua ora" | Aggiunge in testa una scheda con il fuso dell'utente, se manca. |
| Ordine | Come impostato, da est a ovest, da ovest a est, oppure alfabetico. |
| Ricerca delle città nella barra | *Solo quando le città sono molte* (predefinito), *Sempre* oppure *Mai*. Aggiunge il pulsante con la lente. |
| Città minime per mostrare la ricerca | Con "Solo quando le città sono molte", la lente compare da questo numero di città in su (predefinito: 8). Solo ACP. |

### Scorrimento (personalizzabile dagli utenti)

- **Scorrimento automatico** e **pausa tra uno scatto e l'altro** (carosello).
- **Velocità del ticker** in pixel al secondo.
- **Pausa al passaggio del mouse**.
- **Frecce** e **puntini**.

Lo scorrimento si ferma da solo quando la scheda del browser non è visibile, quando il mouse è sopra la barra e per chi ha attivato "riduci movimento" nel sistema operativo.

### Aspetto

- **Tema:** *Automatico* (colori trasparenti che si adattano a stili chiari e scuri, come ForumUS e prosilver), *Chiaro*, *Scuro* o *Vetro colorato*.
- **Colore principale**, **larghezza delle schede** e **arrotondamento degli angoli**.

### Utenti (UCP)

- Permetti la personalizzazione dal pannello utente.
- Permetti di nascondere la barra.
- Permetti agli utenti di scegliere le proprie città, con un **numero massimo** per utente.

### Aggiornamenti automatici

- Attiva o disattiva il cron e scegli ogni quanti giorni controllare (predefinito: 7).
- Aggiornamento automatico del catalogo città (predefinito: spento) e relativo intervallo.
- Sorgenti: principale, alternativa e controllo della versione. `{version}` viene sostituito con l'ultima versione pubblicata.
- Catalogo città da importare: città sopra 15.000, 5.000, 1.000 o 500 abitanti.
- **Dimensione dei blocchi di download** e **durata massima di ogni passo**: abbassali se l'hosting interrompe le richieste lunghe.

Il pulsante **Ripristina predefiniti** riporta tutto ai valori iniziali, tranne le impostazioni degli aggiornamenti.

---

## 5. ACP — Città della barra

Qui si gestiscono le città mostrate a tutti. In cima a ogni pagina ACP il badge **Città nella barra** indica quante città sono configurate; **Catalogo città** indica invece quante città del mondo sono disponibili nella ricerca (0 finché non importi il catalogo GeoNames).

**Per aggiungere una città:**
1. Scrivi il nome nella casella di ricerca, oppure un paese ("Brasile") o un fuso ("Europe/Rome").
2. Clicca il risultato, oppure premi **Aggiungi** o Invio: viene aggiunto il risultato evidenziato, che di solito è il primo.
3. Premi **Invia** in fondo alla pagina per salvare.

- **Ricerca:** scrivi almeno 2 lettere. Funziona in tre modi:
  - per **città**, anche con il nome italiano o alternativo (richiede il catalogo città importato): "Mosca", "Monaco di Baviera", "L'Avana";
  - per **paese**: "Brasile" restituisce tutti i fusi del Brasile, e funziona anche il codice ISO, come "IT";
  - per **fuso IANA**: "Europe/Rome", "Kolkata".
  Nei risultati vedi bandiera, paese, fuso, ora attuale (UTC±) e popolazione. Si naviga con le frecce della tastiera e si conferma con Invio.
- **Rinomina:** clicca sul nome e scrivi quello che vuoi far apparire, per esempio "Washington DC" con il fuso America/New_York.
- **Riordina:** trascina le righe oppure usa le frecce ▲ ▼.
- **Su mobile:** togli la spunta per nascondere quella città sugli schermi piccoli.
- **Rimuovi:** pulsante ✕.
- L'**anteprima** in alto si aggiorna subito, anche con le città appena aggiunte.

Premi **Invia** per salvare. Le città con un fuso inesistente vengono scartate e segnalate.

---

## 6. ACP — Fusi orari e aggiornamenti

### Riquadri di stato

- **Database fusi orari:** versione IANA installata (es. 2026e), numero di fusi e nomi alternativi, ultimo aggiornamento, ultimo controllo, pacchetto.
- **Catalogo città:** numero di città importate, catalogo installato, data.
- **Aggiornamento automatico:** intervallo, ultima e prossima esecuzione, versione dei fusi di PHP sul server (solo per confronto).

### Aggiorna adesso (con barra di avanzamento)

| Pulsante | Cosa fa |
|---|---|
| **Aggiorna i fusi orari** | Controlla l'ultima versione. Se è già installata si ferma subito; altrimenti la scarica e la elabora. |
| **Importa / aggiorna le città** | Scarica il catalogo GeoNames scelto (zip), lo estrae e lo importa. |
| **Aggiorna tutto** | Fa entrambe le cose in sequenza. |

Le conferme (per esempio prima di importare le città, ripristinare i predefiniti, svuotare il registro o sbloccare gli aggiornamenti) usano il riquadro di conferma di phpBB, non la finestra del browser.
| *Forza anche se già aggiornato* | Riscarica anche se la versione è uguale. |

La **barra di avanzamento mostra la percentuale reale**:
- ogni richiesta dell'ACP esegue un solo pezzo di lavoro, quindi la barra avanza a piccoli passi;
- i file vengono scaricati **a blocchi** di 128 KB (HTTP Range), quindi la percentuale segue i byte ricevuti;
- l'elaborazione avanza zona per zona (fusi) o riga per riga (città);
- sotto la barra vedi la fase in corso, il tempo trascorso e la stima del tempo rimanente;
- puoi **annullare** in qualsiasi momento;
- se ricarichi la pagina durante un aggiornamento, **riprende da dove era arrivato**;
- se la sorgente principale non risponde, passa da sola a quella **alternativa**;
- in caso di problemi di rete temporanei riprova automaticamente.

Durante l'import delle città la ricerca continua a usare il catalogo precedente. Il passaggio al nuovo avviene solo alla fine, quindi un import interrotto non lascia la ricerca vuota.

Al termine vedi il resoconto:
- la versione nuova e quella precedente;
- le **zone le cui regole sono cambiate**, confrontando i prossimi 5 anni;
- le zone nuove e quelle eliminate.

### Registro delle modifiche

Conserva gli ultimi 30 aggiornamenti: data, origine (manuale, cron o riga di comando) e dettagli. Le voci vengono anche scritte nel **log amministratore** di phpBB. Gli errori finiscono nel **log errori**.

### Tutti i fusi orari

Tabella dei fusi installati con:
- paesi e bandiera;
- **scostamento attuale** e sigla;
- se è in **ora legale** adesso;
- data e ora del **prossimo cambio** e nuovo scostamento.

Puoi filtrare per nome, paese o scostamento (es. "UTC+05:30") e mostrare solo i fusi in ora legale.

**Aggiungere una città da qui:** ogni riga ha il pulsante **Aggiungi alla barra**.
- Un clic aggiunge subito il fuso alle città della barra, senza ricaricare la pagina. Il nome è quello del fuso, per esempio "Lima".
- Il pulsante diventa **Nella barra** e il badge "Città nella barra" si aggiorna.
- I fusi già presenti nella barra mostrano **Nella barra** e un pulsante **Rimuovi "nome città"** per **ogni** città che usa quel fuso.
  - Per esempio, America/New_York con "New York" e "Washington DC" mostra due pulsanti: *Rimuovi "New York"* e *Rimuovi "Washington DC"*.
  - Ogni pulsante chiede conferma con il riquadro di phpBB e toglie **solo la città indicata**; le altre restano.
  - Quando non resta nessuna città su quel fuso, ricompare "Aggiungi alla barra".
- Nome, ordine e visibilità su mobile si cambiano poi nella pagina **Città della barra**.

---

## 7. ACP — Check-up

Esegue circa 30 controlli e riassume il risultato: tutto ok, avvisi oppure errori.

I controlli partono da soli all'apertura della pagina e vengono eseguiti **uno alla volta**. Mentre lavora vedi:
- una **barra di avanzamento con percentuale reale**;
- il controllo in corso, per esempio "Controllo in corso: Connessione (6 di 11)";
- i risultati che compaiono man mano.

I pulsanti **Esegui di nuovo** e **Check-up rapido (senza test di rete)** rilanciano i controlli senza ricaricare la pagina, sempre con la barra. Il check-up completo ha 11 passi, quello rapido 6. Nel riepilogo i conteggi sono scritti come "OK: 29 · Avvisi: 1 · Errori: 0 · Informazioni: 4".

| Gruppo | Controlli |
|---|---|
| Sistema | Versioni di PHP e phpBB; cURL o `allow_url_fopen`; ZipArchive; zlib; versione dei fusi di PHP; `max_execution_time` rispetto alla durata dei passi. |
| Database e configurazione | Presenza delle 4 tabelle; presenza e validità delle 51 opzioni; utenti con preferenze personali. |
| Dati dei fusi e delle città | Numero di fusi; versione IANA; età dei dati; **copertura delle regole** per almeno 12 mesi; stato del catalogo città. |
| Barra degli orologi | Città configurate; barra attiva; fuso valido per ogni città; **confronto con PHP** (segnala se il server ha fusi più vecchi); peso dei dati inviati al browser; fuso del forum riconosciuto. |
| Aggiornamenti automatici | Cron attivo e non in ritardo; cron di sistema; ultimo errore; aggiornamento bloccato. |
| Connessione alle sorgenti | Raggiungibilità e tempo di risposta di controllo versione, sorgente principale, alternativa e GeoNames; supporto del download a blocchi; **orologio del server** confrontato con l'ora dei server remoti. |
| File e cartelle | File dell'estensione presenti; cartella `store/tzc/` scrivibile. |
| Il tuo browser | Ora del tuo computer rispetto al server; per ogni città, confronto tra l'ora dell'estensione e quella del tuo browser. |

**Strumenti di riparazione:**
- **Ripara la configurazione:** ricrea le opzioni mancanti o non valide con il valore predefinito.
- **Sblocca gli aggiornamenti:** annulla un aggiornamento rimasto bloccato, per esempio dopo un timeout del server.

Il **check-up rapido** salta i test di rete.

---

## 8. Pannello utente (UCP)

In **UCP → Preferenze del forum → Orologi dal mondo** ogni utente può:

- **nascondere** la barra, se l'amministratore lo permette;
- scegliere **quali città vedere**: quelle del forum, solo le sue, oppure le sue seguite da quelle del forum;
- aggiungere **le proprie città** con la stessa ricerca dell'ACP, rinominarle e riordinarle, fino al massimo stabilito;
- cambiare **tutte le opzioni di visualizzazione e scorrimento**. Ogni opzione parte da "Predefinito del forum (…)", che segue le scelte dell'amministratore anche quando queste cambiano;
- vedere l'**anteprima live**;
- **ripristinare i predefiniti** con un clic.

La differenza oraria e la scheda "La tua ora" usano il **fuso orario impostato nel profilo** dell'utente (UCP → Preferenze → Impostazioni globali). Per gli ospiti si usa il fuso del forum.

Nella barra, l'icona ⚙ porta direttamente a questa pagina.

Quando un utente viene eliminato, le sue preferenze vengono cancellate.

---

## 9. Aggiornamenti automatici: cron e riga di comando

### Cron di phpBB

Il task `cron.task.salvocortesiano.timezoneclock.update` parte quando sono passati i giorni impostati (predefinito: 7).

- Prima chiede la versione più recente e **scarica i dati solo se è cambiata**, quindi di solito impiega meno di un secondo.
- I lavori lunghi (download e import delle città) vengono **divisi su più esecuzioni**: circa 20 secondi per esecuzione col cron "web", circa 50 col cron di sistema.
- In caso di errore riprova dopo circa 6 ore, senza aspettare l'intero intervallo.

Sul forum il cron di sistema è già attivo nel crontab ogni 5 minuti (`bin/phpbbcli.php cron:run`), quindi non serve altro.

### Riga di comando

```bash
cd /home/uf2626rc/domains/netshadows.de/public_html/ombra

# fusi orari (salta il download se già aggiornati)
/opt/alt/php82/usr/bin/php bin/phpbbcli.php tzc:update

# solo il catalogo delle città
/opt/alt/php82/usr/bin/php bin/phpbbcli.php tzc:update --cities

# tutto, forzando il download
/opt/alt/php82/usr/bin/php bin/phpbbcli.php tzc:update --all --force
```

Il comando mostra una barra di avanzamento con percentuale e, alla fine, il resoconto (versione e zone cambiate).

---

## 10. La barra sul forum

- **Carosello:** frecce ai lati, puntini sotto, scorrimento automatico a scatti. Su PC si trascina col mouse, su smartphone si scorre col dito, con la tastiera si usano le frecce ← →. Quando arriva alla fine riparte dall'inizio.
- **Ticker:** scorrimento continuo e fluido che rallenta quando passi col mouse. Si può trascinare.
- **Griglia:** tutte le città visibili, in più righe se serve.
- **Pulsanti a destra:** 🔍 cerca una città, pausa/avvio dello scorrimento, ⚙ personalizza (utenti registrati) e comprimi/espandi.
- **Ricerca delle città 🔍**, nello stesso stile del riquadro "Dove vuoi aprire l'argomento?" di New Topic:
  - in cima la casella **Cerca una città o un paese…**;
  - **Cercate di recente**: le ultime 4 città scelte, ricordate dal browser;
  - **Tutte le città**, raggruppate per continente (Europa, Americhe, Asia, Africa, Oceania…), con il numero di città per gruppo. Ogni gruppo si apre e si chiude con un clic;
  - ogni città mostra bandiera, paese, **ora attuale** e differenza con l'ora dell'utente, aggiornate in tempo reale;
  - la ricerca ignora maiuscole e accenti ("san paolo", "sao", "brasile");
  - con la tastiera: ↑↓ per scegliere, Invio per mostrare, Esc per chiudere;
  - scelta la città, il riquadro si chiude, la barra **scorre fino alla sua scheda** (carosello, ticker o griglia) e la **evidenzia** per un paio di secondi. Lo scorrimento automatico si ferma per 8 secondi, così hai il tempo di leggerla;
  - su smartphone il riquadro si apre a tutta larghezza.
- Se le città stanno tutte nello spazio disponibile, frecce e puntini spariscono e le schede vengono centrate.
- **Responsive:** sotto i 700 px le frecce lasciano il posto allo scorrimento col dito e le schede si adattano alla larghezza dello schermo.
- **Accessibilità:** etichette ARIA, navigazione da tastiera e rispetto di "riduci movimento".
- Senza JavaScript la barra non viene mostrata e non lascia spazi vuoti.
- I dati sono calcolati una volta per pagina; l'orologio si aggiorna ogni minuto, oppure ogni secondo se mostri i secondi.

---

## 11. Risoluzione dei problemi

| Problema | Soluzione |
|---|---|
| La barra non appare | Controlla nel check-up che "Stato della barra" sia attivo e che ci siano città. Controlla anche "Pagine" e "Posizione" nelle impostazioni. Svuota la cache. |
| La barra non appare in una certa posizione | Alcuni stili non hanno tutti gli eventi template di prosilver: prova un'altra "Posizione". |
| Aggiornamento bloccato o "C'è già un aggiornamento in corso" | Check-up → **Sblocca gli aggiornamenti**. |
| Download fallito | Guarda il gruppo "Connessione alle sorgenti" del check-up: il server potrebbe bloccare le connessioni in uscita. |
| La percentuale salta da 0 a 100 | La sorgente non supporta il download a blocchi: è solo estetico, il download funziona lo stesso. |
| "Confronto con PHP" in giallo | Normale se il server ha fusi PHP più vecchi: gli orologi usano i dati dell'estensione, più recenti. |
| L'import delle città si interrompe | Abbassa "Durata massima di ogni passo" e "Dimensione dei blocchi", poi riprova. |
| Ricerca città limitata | Importa il catalogo città dalla pagina **Fusi orari e aggiornamenti**. |
| Le bandiere non si vedono su Windows | Imposta "Tipo di bandiera" su **Immagine SVG**. |

---

## 12. Disinstallazione

- **Disattiva:** la barra sparisce e dati e impostazioni restano.
- **Elimina i dati:** rimuove tabelle, opzioni, moduli ACP e UCP, le preferenze degli utenti e la cartella temporanea `store/tzc/`.

---

## 13. Struttura dei file

```
salvocortesiano/timezoneclock/
├── acp/                  moduli ACP (4 pagine)
├── adm/style/            template, CSS e JS dell'ACP
├── config/               servizi e nomi delle tabelle
├── console/command/      comando tzc:update
├── controller/           controller ACP e UCP
├── core/
│   ├── tzdata.php        motore dei fusi (decodifica IANA, transizioni, ora legale)
│   ├── zone_importer.php scrittura dei fusi nel database
│   ├── zones.php         lettura dei fusi
│   ├── updater.php       aggiornamenti a passi (ACP, cron, CLI)
│   ├── downloader.php    download a blocchi (cURL o stream)
│   ├── catalog.php       ricerca di città, paesi e fusi
│   ├── bar_builder.php   dati della barra inviati al browser
│   ├── prefs.php         preferenze degli utenti
│   ├── options.php       registro di tutte le opzioni
│   ├── form.php          moduli ACP e UCP generati dal registro
│   └── checkup.php       check-up
├── cron/task/            task cron
├── data/                 fusi IANA inclusi, coordinate dei fusi, città iniziali
├── event/                listener
├── language/it, en/      tutti i testi (nessun testo nel codice)
├── migrations/           installazione pulita
├── styles/all/           barra (JS/CSS), editor città, template UCP ed eventi, bandiere
├── ucp/                  modulo UCP
├── composer.json
├── ext.php
├── license.txt
└── GUIDA.md
```

---

## 14. Fonti dei dati e licenze

- **Fusi orari:** IANA Time Zone Database (pubblico dominio), nel formato di [moment-timezone](https://momentjs.com/timezone/) (MIT). Sorgenti predefinite: jsDelivr, con unpkg come alternativa; versione controllata sul registro npm.
- **Coordinate dei fusi:** `zone1970.tab` / `zone.tab` di IANA.
- **Città del mondo:** [GeoNames](https://www.geonames.org) — licenza CC BY 4.0.
- **Bandiere:** [country-flag-icons](https://gitlab.com/catamphetamine/country-flag-icons) — MIT (vedi `styles/all/theme/flags/LICENSE.txt`).
- **Codice dell'estensione:** GPL-2.0.

---

## 15. Novità delle versioni

### 1.0.6
- **Ricerca delle città nella barra** (pulsante 🔍) in stile New Topic: casella di ricerca, città cercate di recente, gruppi per continente apribili, ora attuale di ogni città, navigazione da tastiera. Scegliendo una città la barra scorre fino alla sua scheda e la evidenzia.
- Nuove opzioni "Ricerca delle città nella barra" (personalizzabile anche dall'utente) e "Città minime per mostrare la ricerca". Vengono aggiunte da una migration anche sulle installazioni già presenti.
- Nelle anteprime ACP/UCP la barra non si ricostruisce più mentre si scrive nella sua casella di ricerca.

### 1.0.5
- Corretto "Rimuovi dalla barra": toglieva tutte le città che usavano lo stesso fuso. Ora ogni città ha il suo pulsante (*Rimuovi "New York"*, *Rimuovi "Washington DC"*) e viene tolta solo quella cliccata.

### 1.0.4
- Pulsante di rimozione nella tabella "Tutti i fusi orari" per i fusi già presenti nella barra, con conferma tramite il riquadro di phpBB e aggiornamento immediato del badge.

### 1.0.3
- **Aggiungi alla barra** su ogni riga della tabella "Tutti i fusi orari": aggiunge il fuso alle città della barra con un clic, via AJAX, e aggiorna subito il badge.
- **Check-up con barra di avanzamento e percentuale reale**, sia all'apertura della pagina sia con "Esegui di nuovo" e "Check-up rapido". I test di rete sono divisi in passi separati: controllo versione, sorgente fusi, sorgente alternativa, catalogo città, orologio del server.
- Singolare e plurale corretti con le forme plurali di phpBB: "1 zona / 2 zone", "Aggiornati oggi / 1 giorno fa / 2 giorni fa", "1 utente / 2 utenti", "Ogni giorno / Ogni 7 giorni", "1 valore / 2 valori" e così via, in italiano e in inglese.
- Riepilogo del check-up scritto come "Avvisi: 1" invece di "1 avvisi".
- Lo scarto dell'orologio è indicato in secondi abbreviati ("scarto: 0 s").

### 1.0.2
Controllo completo di link e lingue (italiano e inglese) su tutte le pagine ACP e UCP.
- Verificati tutti i link: tab, "Modifica le impostazioni degli aggiornamenti" (apre Impostazioni direttamente alla sezione "Aggiornamenti automatici"), "Importa il catalogo delle città", strumenti del check-up.
- Tradotti gli ultimi testi che non passavano dai file di lingua:
  - tempo trascorso e rimanente sotto la barra di avanzamento;
  - i messaggi di errore degli aggiornamenti, anche nel log e nel check-up ("Ultimo errore");
  - il dettaglio del "Confronto con PHP" nel check-up;
  - l'origine degli aggiornamenti nel log amministratore (manuale, cron, riga di comando);
  - il pulsante OK dei riquadri di avviso.
- I numeri nella ricerca città (abitanti) usano il formato della lingua del forum.

### 1.0.1
- Corrette le tab in cima alle pagine ACP: da qualsiasi pagina portavano alla pagina in cui ci si trovava già.
- La barra di avanzamento ora avanza davvero a passi (blocchi da 128 KB, 25 fusi o 2.500 città per richiesta), invece di passare subito da 0 a 100.
- Le conferme usano il riquadro di phpBB al posto della finestra `confirm()` del browser, sia in ACP sia in UCP; anche gli avvisi usano quello di phpBB.
- Aggiunta delle città più semplice: pulsante **Aggiungi**, Invio che aggiunge il primo risultato, istruzioni in tre passi sotto la casella.
- Badge separati: **Città nella barra** e **Catalogo città** (prima un unico badge "Città" mostrava il catalogo e poteva sembrare che la barra fosse vuota).
- Date corrette: "Prossimo aggiornamento" non mostra più "meno di un minuto fa", e il check-up indica la data esatta.

### 1.0.0

Prima versione della fork, riscritta completamente rispetto all'originale *hifikabin/timezoneclock*.

**Correzioni rispetto all'originale:**
- ora legale calcolata dai dati IANA invece che da 8 regole scritte a mano, che erano sbagliate per Brasile, Messico, Paraguay e altri;
- Brasília non è più impostata erroneamente a GMT-6 con ora legale;
- risolto il bug dell'UCP per cui il clic su "No" selezionava "Sì";
- rimosso il listener che chiamava un metodo inesistente;
- salvataggio delle città in transazione, con validazione dei dati;
- tutti i testi nei file di lingua, con l'italiano aggiunto.

**Nuove funzioni:**
- carosello, ticker e griglia, con scorrimento manuale e automatico;
- design responsive e accessibile;
- 4 temi; giorno/notte calcolato sulla posizione del sole; orologio analogico;
- "La tua ora" e differenza oraria per ogni utente;
- ricerca di città, paesi e fusi, con catalogo GeoNames;
- tutti i 344 fusi IANA, con +13, +14, +12:45 e gli altri fusi mancanti nell'originale;
- aggiornamento automatico (cron), manuale con barra di avanzamento reale, e da riga di comando;
- registro delle modifiche e visualizzatore di tutti i fusi;
- oltre 50 opzioni ACP; personalizzazione UCP con città personali;
- check-up completo con strumenti di riparazione.
