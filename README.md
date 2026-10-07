# Timezone Clock for phpBB 3.3

World clock bar for phpBB 3.3.x. It shows the time of the cities you choose in a **carousel**, a continuously scrolling **ticker** or a **grid**. Time zones always stay correct thanks to automatic updates from the official **IANA** time zone database.

![Version](https://img.shields.io/badge/version-1.0.11-105080)
![phpBB](https://img.shields.io/badge/phpBB-3.3.x-377a33)
![PHP](https://img.shields.io/badge/PHP-%3E%3D7.4-377a33)
![License](https://img.shields.io/badge/license-GPL--2.0--only-7f7f7f)

- **Package:** `salvocortesiano/timezoneclock`
- **Version:** 1.0.11
- **Author:** Salvo Cortesiano — <https://netshadows.de> — support@netshadows.de
- **License:** [GPL-2.0](license.txt)
- **Inspired by:** "Timezone Clock" by HiFiKabin & ctrstudio, rewritten from scratch.
- **Languages:** English, Italian

---

## Table of contents

1. [Requirements](#1-requirements)
2. [Installation](#2-installation)
3. [How it works](#3-how-it-works)
4. [ACP — Settings](#4-acp--settings)
5. [ACP — Bar cities](#5-acp--bar-cities)
6. [ACP — Time zones and updates](#6-acp--time-zones-and-updates)
7. [ACP — Check-up](#7-acp--check-up)
8. [User control panel (UCP)](#8-user-control-panel-ucp)
9. [Automatic updates: cron and command line](#9-automatic-updates-cron-and-command-line)
10. [The bar on the board](#10-the-bar-on-the-board)
11. [Troubleshooting](#11-troubleshooting)
12. [Uninstalling](#12-uninstalling)
13. [File structure](#13-file-structure)
14. [Data sources and licenses](#14-data-sources-and-licenses)
15. [Changelog](#15-changelog)

---

## 1. Requirements

| Requirement | Notes |
|---|---|
| phpBB 3.3.0 or newer (3.3.x series) | |
| PHP 7.4 or newer | PHP 8.2 recommended |
| PHP **zlib** extension | Required to read the time zone data |
| **cURL** or `allow_url_fopen` | Required for online updates |
| PHP **ZipArchive** extension | Only needed to import the world city catalogue |
| Writable `store/` folder | Already writable in every phpBB installation |

The clocks also work without an Internet connection, because the extension ships with the IANA data (version 2026e). Online updates keep it in step with the changes decided by governments.

---

## 2. Installation

It is a **clean installation**: the extension creates its own tables and does not depend on other extensions.

1. Download the latest release and upload the `salvocortesiano/timezoneclock` folder into `ext/`, so that you have `ext/salvocortesiano/timezoneclock/composer.json`.
2. Go to **ACP → Customise → Manage extensions** and enable **Timezone Clock**.
3. Purge the cache: **ACP → General → Purge the cache**.

When it is enabled, the extension:
- creates 4 tables: `phpbb_tzc_cities`, `phpbb_tzc_zones`, `phpbb_tzc_geo` and `phpbb_tzc_users`;
- loads the **344 IANA time zones** and the **253 alternative names** included in the package;
- adds **11 starter cities**: Rome, London, New York, Los Angeles, São Paulo, Moscow, Dubai, New Delhi, Beijing, Tokyo and Sydney. Names are in Italian if the board default language is Italian, in English otherwise;
- creates the ACP category **Timezone Clock** (under *Extensions*) with 4 pages;
- adds the UCP page **World clocks** under *Board preferences*.

At the first cron run, the extension checks online whether a newer version of the time zone data exists.

**Recommended right after installing:**
1. Open **Check-up** and make sure everything is green.
2. In **Time zones and updates**, press **Import / update cities**. The search will then find any city in the world, also by alternative names in other languages (for example "Mosca", "München", "L'Avana").

### Updating the extension

1. Disable the extension, **without** deleting its data.
2. Replace the files via FTP.
3. Purge the cache.
4. Enable it again.
5. Purge the cache again, together with the `cache/production/twig` folder.

---

## 3. How it works

Older extensions computed daylight saving time with rules hard-coded in JavaScript (for example "Europe: last Sunday of March"). Those rules get old: Brazil abolished DST in 2019, Mexico in 2022, Paraguay in 2024, and a clock with outdated rules is one hour off.

Timezone Clock works like this:

1. It downloads the **IANA database** — the same one used by Linux, Android, iOS, Java and PHP — in the compact format published by *moment-timezone*.
2. For every zone it computes on the server **all the transitions** (start and end of DST, offset changes) as absolute UTC instants, and stores them in the database.
3. On every page it sends the browser only the transitions needed for the cities shown, about 3 KB.
4. The browser computes the time with that data, **without using** the time zone database of the visitor's computer or the one of PHP on the server.

The result: when a country changes its rules, a single update (automatic or manual) is enough and every clock is correct. This also holds for a visitor with an outdated Windows or a server with an old PHP.

---

## 4. ACP — Settings

At the top you find the **live preview**: it updates while you change the options, before you even save. Options with the green **UCP** tag can be customised by users; here you set their default value.

### General

| Option | Description |
|---|---|
| Show the clock bar | Turns the bar on or off for everyone. |
| Pages | Board index only, all pages, or selected pages. |
| Selected pages | Index, forums, topics, search, members and profiles, UCP, MCP, posting, FAQ, who is online, extension pages (`app.php`). |
| Position | Right below the header, at the top of the content, at the bottom of the content, or above the footer. |
| Show to guests / Show to bots | Visibility for unregistered visitors and search engines. |
| Show on small screens | On smartphones the bar can be swiped. |
| Collapsible bar | Button that reduces the bar to one line showing the first cities. The browser remembers the choice. |

### Card content (customisable by users)

| Option | Description |
|---|---|
| Mode | **Carousel** (arrows, dots, step scrolling), **Ticker** (continuous, draggable scrolling) or **Grid** (all visible). |
| Card size | Normal or compact (one line per city). |
| Time format | 24 hours or 12 hours am/pm. |
| Show seconds | Displays the seconds. |
| Show the date | Day and date, with "Tomorrow" or "Yesterday" when the city is on another day than the user. |
| Show the flag / Flag type | SVG image (works everywhere) or emoji (not shown by Windows). |
| Show the country name | Below the city name. |
| Day/night icon | Sun, dawn, dusk or moon, computed from the **real position of the sun** in the city. |
| Day/night background | The card colour follows the local time of day. |
| Analogue clock | Clock face with hands next to the digital time. |
| Difference from your time | For example "+8 h", computed from the time zone in the user's profile. |
| DST / abbreviation / UTC offset | "DST" badge, zone abbreviation (CET, EDT…) and UTC offset. |
| Highlight "Your time" | Highlights the city with the same time zone as the user. |
| Always add "Your time" | Adds a card with the user's time zone first, if it is missing. |
| City order | As configured, east to west, west to east, or alphabetical. |
| City search in the bar | *Only when there are many cities* (default), *Always* or *Never*. Adds the magnifier button. |
| Minimum cities to show the search | With "Only when there are many cities", the magnifier appears from this number of cities upwards (default: 8). ACP only. |

### Scrolling (customisable by users)

- **Automatic scrolling** and **pause between steps** (carousel).
- **Ticker speed** in pixels per second.
- **Pause on mouse over**.
- **Navigation arrows** and **page indicators (dots)**.

Scrolling stops by itself when the browser tab is not visible, while the mouse is over the bar, and for people who enabled "reduce motion" in their operating system.

### Appearance

- **Theme:** *Automatic* (transparent colours that adapt to light and dark styles such as prosilver and its derivatives), *Light*, *Dark* or *Coloured glass*.
- **Accent colour**, **card width** and **corner radius**.

### Users (UCP)

- Allow customisation in the user control panel.
- Allow users to hide the bar.
- Allow users to choose their own cities, with a **maximum number** per user.

### Automatic updates

- Enable or disable the cron and choose how many days between checks (default: 7).
- Automatic update of the city catalogue (default: off) and its interval.
- Sources: primary, alternative and version check. `{version}` is replaced with the latest published version.
- City catalogue to import: cities above 15,000, 5,000, 1,000 or 500 inhabitants.
- **Download block size** and **maximum duration of each step**: lower them if your hosting interrupts long requests.

The **Restore defaults** button restores every setting to its initial value, except the update settings.

---

## 5. ACP — Bar cities

Here you manage the cities shown to everyone. At the top of every ACP page, the **Cities in the bar** badge shows how many cities are configured, while **City catalogue** shows how many world cities are available in the search (0 until you import the GeoNames catalogue).

**To add a city:**
1. Type its name in the search box, or a country ("Brazil") or a time zone ("Europe/Rome").
2. Click the result, or press **Add** or Enter: the highlighted result is added, usually the first one.
3. Press **Submit** at the bottom of the page to save.

- **Search:** type at least 2 letters. It works in three ways:
  - by **city**, also with alternative names in other languages (requires the imported city catalogue): "Moscow", "Munich", "Havana";
  - by **country**: "Brazil" returns every time zone of Brazil, and the ISO code works too, for example "IT";
  - by **IANA zone**: "Europe/Rome", "Kolkata".
  Results show flag, country, zone, current time (UTC±) and population. Navigate with the arrow keys and confirm with Enter.
- **Rename:** click the name and type what you want to show, for example "Washington DC" with the America/New_York zone.
- **Reorder:** drag the rows or use the ▲ ▼ arrows.
- **On mobile:** untick it to hide that city on small screens.
- **Remove:** ✕ button.
- The **preview** at the top updates immediately, also with newly added cities.

Press **Submit** to save. Cities with a non-existent zone are discarded and reported.

---

## 6. ACP — Time zones and updates

### Status boxes

- **Time zone database:** installed IANA version (e.g. 2026e), number of zones and alternative names, last update, last check, package.
- **City catalogue:** number of imported cities, installed catalogue, date.
- **Automatic update:** interval, last and next run, version of the PHP time zones on the server (for comparison only).

### Update now (with progress bar)

| Button | What it does |
|---|---|
| **Update time zones** | Checks the latest version. If it is already installed it stops immediately; otherwise it downloads and processes it. |
| **Import / update cities** | Downloads the selected GeoNames catalogue (zip), extracts and imports it. |
| **Update everything** | Does both, one after the other. |
| *Force even if already up to date* | Downloads again even if the version is the same. |

Confirmations (for example before importing cities, restoring defaults, clearing the log or unlocking updates) use the phpBB confirmation box, not the browser window.

The **progress bar shows the real percentage**:
- every ACP request performs a single piece of work, so the bar moves in small steps;
- files are downloaded **in blocks** of 128 KB (HTTP Range), so the percentage follows the bytes received;
- processing advances zone by zone (time zones) or line by line (cities);
- below the bar you see the current phase, the elapsed time and the estimated remaining time;
- you can **cancel** at any time;
- if you reload the page during an update, it **resumes where it stopped**;
- if the primary source does not answer, it switches to the **alternative** one by itself;
- temporary network problems are retried automatically.

While cities are being imported, the search keeps using the previous catalogue. The switch to the new one only happens at the end, so an interrupted import never leaves the search empty.

At the end you see a summary:
- the new version and the previous one;
- the **zones whose rules changed**, comparing the next 5 years;
- new and removed zones.

### Change log

Keeps the last 30 updates: date, origin (manual, cron or command line) and details. Entries are also written to the phpBB **admin log**. Errors go to the **error log**.

### All time zones

Table of the installed zones with:
- countries and flag;
- **current offset** and abbreviation;
- whether it is on **DST** right now;
- date and time of the **next change** and the new offset.

You can filter by name, country or offset (e.g. "UTC+05:30") and show only the zones currently on DST.

**Adding a city from here:** every row has an **Add to the bar** button.
- One click adds the zone to the bar cities immediately, without reloading the page. The name is the zone's city, for example "Lima".
- The button becomes **In the bar** and the "Cities in the bar" badge is updated.
- Zones already in the bar show **In the bar** and a **Remove "city name"** button for **each** city using that zone.
  - For example, America/New_York with "New York" and "Washington DC" shows two buttons: *Remove "New York"* and *Remove "Washington DC"*.
  - Each button asks for confirmation with the phpBB box and removes **only the city named on it**; the others stay.
  - When no city is left on that zone, "Add to the bar" appears again.
- Name, order and mobile visibility are then changed in the **Bar cities** page.

---

## 7. ACP — Check-up

Runs about 30 checks and summarises the result: everything OK, warnings or errors.

The checks start by themselves when the page opens and run **one at a time**. While it works you see:
- a **progress bar with the real percentage**;
- the check in progress, for example "Checking: Time zone source (7 of 11)";
- the results as they come in.

The **Run again** and **Quick check-up (no network tests)** buttons run the checks again without reloading the page, always with the progress bar. The full check-up has 11 steps, the quick one 6. The summary shows the counts as "OK: 29 · Warnings: 1 · Errors: 0 · Information: 4".

| Group | Checks |
|---|---|
| System | PHP and phpBB versions; cURL or `allow_url_fopen`; ZipArchive; zlib; PHP time zone version; `max_execution_time` compared with the step duration. |
| Database and configuration | Presence of the 4 tables; presence and validity of the 53 options; users with personal preferences. |
| Time zone and city data | Number of zones; IANA version; data age; **rule coverage** for at least 12 months; city catalogue status. |
| Clock bar | Configured cities; bar enabled; valid zone for every city; **comparison with PHP** (reports when the server has older time zones); size of the data sent to the browser; board time zone recognised. |
| Automatic updates | Cron enabled and not overdue; system cron; last error; stuck update. |
| Connection to the sources | Reachability and response time of version check, primary source, alternative source and GeoNames; block download support; **server clock** compared with the time of the remote servers. |
| Files and folders | Extension files present; `store/tzc/` folder writable. |
| Your browser | Your computer's time compared with the server; for every city, the extension's time compared with your browser's. |

**Repair tools:**
- **Repair the configuration:** recreates missing or invalid options with their default value.
- **Unlock updates:** cancels an update that got stuck, for example after a server timeout.

The **quick check-up** skips the network tests.

> **About "Comparison with PHP":** a warning here usually means that the time zone database built into PHP on your server is older than the extension's. For example, PHP with time zone data 2024.2 does not know that Morocco moved to permanent UTC+00:00 on 20 September 2026. The clocks use the extension's data, so they are correct anyway.

---

## 8. User control panel (UCP)

In **UCP → Board preferences → World clocks** every user can:

- **hide** the bar, if the administrator allows it;
- choose **which cities to see**: the board cities, only their own, or their own followed by the board cities;
- add **their own cities** with the same search as the ACP, rename and reorder them, up to the maximum set by the administrator;
- change **every display and scrolling option**. Each option starts from "Board default (…)", which follows the administrator's choices even when they change;
- see the **live preview**;
- **restore the defaults** with one click.

The time difference and the "Your time" card use the **time zone set in the user's profile** (UCP → Board preferences → Edit global settings). Guests get the board time zone.

In the bar, the ⚙ icon leads directly to this page.

When a user is deleted, their preferences are deleted too.

---

## 9. Automatic updates: cron and command line

### phpBB cron

The task `cron.task.salvocortesiano.timezoneclock.update` runs when the configured number of days has passed (default: 7).

- It first asks for the latest version and **downloads the data only if it changed**, so it usually takes less than a second.
- Long jobs (download and import of the cities) are **split across several runs**: about 20 seconds per run with the "web" cron, about 50 with the system cron.
- When something fails, it retries after about 6 hours instead of waiting for the whole interval.

Using the phpBB system cron is recommended (**ACP → General → Server settings → Run periodic tasks from system cron**), with a crontab entry such as:

```bash
*/5 * * * * php /path/to/phpBB/bin/phpbbcli.php cron:run --quiet
```

### Command line

```bash
cd /path/to/phpBB

# time zones (the download is skipped if already up to date)
php bin/phpbbcli.php tzc:update

# city catalogue only
php bin/phpbbcli.php tzc:update --cities

# everything, forcing the download
php bin/phpbbcli.php tzc:update --all --force
```

Replace `php` with the full path of your PHP binary if needed. The command shows a progress bar with the percentage and, at the end, a summary (version and changed zones).

---

## 10. The bar on the board

- **Carousel:** arrows on the sides, dots below, automatic step scrolling. On a PC you can drag it with the mouse, on a smartphone swipe it, with the keyboard use the ← → arrows. When it reaches the end it starts again from the beginning.
- **Ticker:** smooth continuous scrolling that slows down when you hover it. It can be dragged.
- **Grid:** all cities visible, on several rows if needed.
- **Buttons on the right:** 🔍 find a city, pause/start scrolling, ⚙ customise (registered users) and collapse/expand.
- **City search 🔍**, a picker box like the ones used in modern forum interfaces:
  - at the top the **Search a city or a country…** box;
  - **Recently searched**: the last 4 cities chosen, remembered by the browser;
  - **All cities**, grouped by continent (Europe, Americas, Asia, Africa, Oceania…), with the number of cities per group. Each group opens and closes with a click;
  - every city shows flag, country, **current time** and difference from the user's time, updated in real time;
  - the search ignores case and accents ("sao paulo", "são", "brazil");
  - with the keyboard: ↑↓ to choose, Enter to show, Esc to close;
  - once a city is chosen, the box closes, the bar **scrolls to its card** (carousel, ticker or grid) and **highlights** it for a couple of seconds. Automatic scrolling stops for 8 seconds, so there is time to read it;
  - on smartphones the box opens full width.
- If all the cities fit in the available space, arrows and dots disappear and the cards are centred.
- **Responsive:** below 700 px the arrows give way to swiping and the cards adapt to the screen width.
- **Accessibility:** ARIA labels, keyboard navigation and support for "reduce motion".
- Without JavaScript the bar is not shown and leaves no empty space.
- The data is computed once per page; the clocks update every minute, or every second if seconds are shown.

---

## 11. Troubleshooting

| Problem | Solution |
|---|---|
| The bar does not appear | In the check-up, make sure "Bar status" is enabled and that there are cities. Also check "Pages" and "Position" in the settings. Purge the cache. |
| The bar does not appear in a certain position | Some styles do not have all the prosilver template events: try another "Position". |
| Update stuck, or "An update is already running" | Check-up → **Unlock updates**. |
| Download failed | Look at the "Connection to the sources" group of the check-up: the server may block outgoing connections. |
| The percentage jumps from 0 to 100 | The source does not support block downloads: it is only cosmetic, the download works anyway. |
| "Comparison with PHP" shows a warning | Normal when the server has older PHP time zones: the clocks use the extension's newer data. |
| The city import stops | Lower "Maximum duration of each step" and "Download block size", then try again. |
| City search is limited | Import the city catalogue from the **Time zones and updates** page. |
| Flags are not shown on Windows | Set "Flag type" to **SVG image**. |
| The bar behaves strangely | Open the browser console (F12) and type `TZC.debug()`. It shows a table with the loaded version, mode, number of cities, computed and measured round length (they must match), position and the reason of a possible pause. If the version is not the installed one, reload with Ctrl+F5. |

---

## 12. Uninstalling

- **Disable:** the bar disappears; data and settings are kept.
- **Delete data:** removes tables, options, ACP and UCP modules, user preferences and the temporary folder `store/tzc/`.

---

## 13. File structure

```
salvocortesiano/timezoneclock/
├── acp/                  ACP modules (4 pages)
├── adm/style/            ACP templates, CSS and JS
├── config/               services and table names
├── console/command/      tzc:update command
├── controller/           ACP and UCP controllers
├── core/
│   ├── tzdata.php        time zone engine (IANA decoding, transitions, DST)
│   ├── zone_importer.php writes the zones to the database
│   ├── zones.php         reads the zones
│   ├── updater.php       step-by-step updates (ACP, cron, CLI)
│   ├── downloader.php    block downloads (cURL or streams)
│   ├── catalog.php       search of cities, countries and zones
│   ├── bar_builder.php   bar data sent to the browser
│   ├── prefs.php         user preferences
│   ├── options.php       registry of every option
│   ├── form.php          ACP and UCP forms generated from the registry
│   └── checkup.php       check-up
├── cron/task/            cron task
├── data/                 bundled IANA zones, zone coordinates, starter cities
├── event/                listener
├── language/en, it/      every text (no text hard-coded in the code)
├── migrations/           clean installation and upgrades
├── styles/all/           bar (JS/CSS), city editor, UCP and event templates, flags
├── ucp/                  UCP module
├── composer.json
├── ext.php
└── license.txt
```

---

## 14. Data sources and licenses

- **Time zones:** IANA Time Zone Database (public domain), in the [moment-timezone](https://momentjs.com/timezone/) format (MIT). Default sources: jsDelivr, with unpkg as alternative; version checked on the npm registry.
- **Zone coordinates:** IANA `zone1970.tab` / `zone.tab`.
- **World cities:** [GeoNames](https://www.geonames.org) — CC BY 4.0 license.
- **Flags:** [country-flag-icons](https://gitlab.com/catamphetamine/country-flag-icons) — MIT (see `styles/all/theme/flags/LICENSE.txt`).
- **Extension code:** GPL-2.0.

---

## 15. Changelog

### 1.0.9
- **Ticker independent of when it is measured.** Version 1.0.8 measured the round length only once: if the page was hidden or incomplete at that moment (theme preloader, CSS or panels loaded late, width changing), the value stayed wrong and the bar jumped, made Rome disappear while dragging, or did not show the searched city. Now:
  - the round length is measured **on every cycle** as the exact distance between a card and its copy;
  - the bar measures itself again whenever it changes size or becomes visible (`ResizeObserver`) and when the page has finished loading;
  - the measurement is repeated before centring the city chosen in the search.
- **Manual dragging** (ticker and carousel): no more text selection or dragging of the flags instead of the cards.
- New `TZC.debug()` diagnostic command for the browser console.
- Tested in a real phpBB page also with the page hidden for 2 seconds, CSS loaded late and the page width changing, dragging in both directions with Rome as the first city and searching Rome both from the Europe group and from "Recently searched".

### 1.0.8
- **Fixed the ticker bug** that made the bar jump and left the searched city out of view. The length of one round of cards was measured while the card copies used for endless scrolling were still hidden, so it was half the real value (e.g. 1866 px instead of 3738). As a result:
  - while scrolling, the bar wrapped at the wrong point and seemed to "refresh", making cities disappear (for example Rome, the first one);
  - when choosing a city from the search, the bar stopped at the wrong position and the city stayed out of view.

  The round is now measured on the real positions of the first and last visible card (cities hidden on mobile included) and measured again once the fonts are loaded.
- Tested in a real phpBB page (jQuery, core.js, forum_fn.js and prosilver CSS) at 1152 px, 1820 px and 390 px: a full round without jumps, clicks on the cards while scrolling without jumps, mouse search of all 21 cities with the city always visible and highlighted, scrolling resuming afterwards.

### 1.0.7
- City search in **ticker** mode: the bar scrolled to a copy of the chosen card (the ticker duplicates the cards for endless scrolling) but highlighted the original one, out of view. Now every copy is highlighted and the chosen city is always visible and centred.
- **Automatic scrolling did not resume** until you clicked outside the bar: a clicked button (search, close, collapse/expand) kept the focus and kept the bar paused. Now focus only pauses the bar during **keyboard** navigation in the cards area, and the mouse position is read in real time.
- After choosing a city the scrolling stops for 8 seconds and then resumes by itself; the same happens after closing the search (X, Esc or a click outside) and after collapse/expand.
- The highlight of the found card is no longer clipped at the top.

### 1.0.6
- **City search in the bar** (🔍 button): search box, recently searched cities, collapsible groups by continent, current time of every city, keyboard navigation. Choosing a city scrolls the bar to its card and highlights it.
- New options "City search in the bar" (also customisable by users) and "Minimum cities to show the search". A migration adds them to existing installations too.
- In the ACP/UCP previews the bar is no longer rebuilt while typing in its search box.

### 1.0.5
- Fixed "Remove from the bar": it removed every city using the same zone. Now each city has its own button (*Remove "New York"*, *Remove "Washington DC"*) and only the clicked one is removed.

### 1.0.4
- Remove button in the "All time zones" table for zones already in the bar, with confirmation through the phpBB box and immediate badge update.

### 1.0.3
- **Add to the bar** on every row of the "All time zones" table: adds the zone to the bar cities with one click, via AJAX, and updates the badge immediately.
- **Check-up with progress bar and real percentage**, both when the page opens and with "Run again" and "Quick check-up". Network tests are split into separate steps: version check, time zone source, alternative source, city catalogue, server clock.
- Correct singular and plural with the phpBB plural forms ("1 zone / 2 zones", "Updated today / 1 day ago / 2 days ago", "1 user / 2 users", "Every day / Every 7 days", "1 value / 2 values" and so on), in English and Italian.
- Check-up summary written as "Warnings: 1".
- The clock offset is shown in abbreviated seconds ("offset: 0 s").

### 1.0.2
Full check of links and languages (English and Italian) on every ACP and UCP page.
- Every link verified: tabs, "Change the update settings" (opens Settings directly at the "Automatic updates" section), "Import the world city catalogue", check-up tools.
- Translated the last texts that did not come from the language files:
  - elapsed and remaining time below the progress bar;
  - update error messages, also in the log and in the check-up ("Last error");
  - details of the "Comparison with PHP" check;
  - origin of the updates in the admin log (manual, cron, command line);
  - the OK button of the alert boxes.
- Numbers in the city search (inhabitants) use the format of the board language.

### 1.0.1
- Fixed the tabs at the top of the ACP pages: from any page they led to the current page.
- The progress bar now really moves in steps (blocks of 128 KB, 25 zones or 2,500 cities per request) instead of jumping from 0 to 100.
- Confirmations use the phpBB box instead of the browser `confirm()` window, both in the ACP and in the UCP; alerts use the phpBB box too.
- Easier city adding: **Add** button, Enter adds the first result, three-step instructions below the box.
- Separate badges: **Cities in the bar** and **City catalogue** (a single "Cities" badge showed the catalogue and could make the bar look empty).
- Correct dates: "Next update" no longer shows "less than a minute ago", and the check-up shows the exact date.

### 1.0.0

First version of the fork, completely rewritten compared with the original *hifikabin/timezoneclock*.

**Fixes compared with the original:**
- DST computed from the IANA data instead of 8 hand-written rules, which were wrong for Brazil, Mexico, Paraguay and others;
- Brasília is no longer wrongly set to GMT-6 with DST;
- fixed the UCP bug where clicking "No" selected "Yes";
- removed the listener that called a non-existent method;
- cities saved in a transaction, with data validation;
- every text in the language files, with Italian added.

**New features:**
- carousel, ticker and grid, with manual and automatic scrolling;
- responsive and accessible design;
- 4 themes; day/night computed from the position of the sun; analogue clock;
- "Your time" and time difference for every user;
- search of cities, countries and zones, with the GeoNames catalogue;
- all 344 IANA zones, including +13, +14, +12:45 and the other zones missing in the original;
- automatic updates (cron), manual updates with a real progress bar, and command line updates;
- change log and browser of all time zones;
- more than 50 ACP options; UCP customisation with personal cities;
- full check-up with repair tools.
