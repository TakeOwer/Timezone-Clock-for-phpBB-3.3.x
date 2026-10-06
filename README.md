# Timezone Clock for phpBB 3.3

World clock bar for phpBB: carousel, ticker or grid, responsive, four themes, real day/night, analogue clocks.
Time zone rules come from the official IANA database and are kept up to date automatically (phpBB cron, CLI command `tzc:update` or ACP button with a real progress bar), so clocks stay correct even when the server or the browser has old time zone data.
World cities (GeoNames) can be searched by city, country or time zone. Fully configurable in the ACP; users can customise the bar and add their own cities in the UCP. Includes an ACP check-up.

* Package: `salvocortesiano/timezoneclock` — version 1.0.0
* Requirements: phpBB 3.3.x, PHP 7.4+, zlib; cURL or allow_url_fopen for updates; ZipArchive for the city catalogue
* Author: Salvo Cortesiano — https://netshadows.de — support@netshadows.de
* License: GPL-2.0 — data: IANA tz (public domain) via moment-timezone (MIT), GeoNames (CC BY 4.0), country-flag-icons (MIT)

Full Italian guide: [GUIDA.md](GUIDA.md)

## Installation

1. Upload to `ext/salvocortesiano/timezoneclock/`.
2. ACP → Customise → Manage extensions → enable *Timezone Clock*.
3. Open ACP → Extensions → Timezone Clock → Check-up.
