<?php
/**
 *
 * Timezone Clock. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano <https://netshadows.de>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace salvocortesiano\timezoneclock\ucp;

class main_info
{
	public function module()
	{
		return [
			'filename'	=> '\salvocortesiano\timezoneclock\ucp\main_module',
			'title'		=> 'UCP_TZC_TITLE',
			'modes'		=> [
				'settings'	=> [
					'title'	=> 'UCP_TZC_SETTINGS',
					'auth'	=> 'ext_salvocortesiano/timezoneclock && cfg_tzc_ucp_enable',
					'cat'	=> ['UCP_PREFS'],
				],
			],
		];
	}
}
