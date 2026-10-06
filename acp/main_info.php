<?php
/**
 *
 * Timezone Clock. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano <https://netshadows.de>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace salvocortesiano\timezoneclock\acp;

class main_info
{
	public function module()
	{
		$auth = 'ext_salvocortesiano/timezoneclock && acl_a_board';

		return [
			'filename'	=> '\salvocortesiano\timezoneclock\acp\main_module',
			'title'		=> 'ACP_TZC_TITLE',
			'modes'		=> [
				'settings'	=> ['title' => 'ACP_TZC_SETTINGS', 'auth' => $auth, 'cat' => ['ACP_TZC_TITLE']],
				'cities'	=> ['title' => 'ACP_TZC_CITIES', 'auth' => $auth, 'cat' => ['ACP_TZC_TITLE']],
				'database'	=> ['title' => 'ACP_TZC_DATABASE', 'auth' => $auth, 'cat' => ['ACP_TZC_TITLE']],
				'checkup'	=> ['title' => 'ACP_TZC_CHECKUP', 'auth' => $auth, 'cat' => ['ACP_TZC_TITLE']],
			],
		];
	}
}
