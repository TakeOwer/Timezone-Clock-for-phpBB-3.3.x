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

class main_module
{
	/** @var string */
	public $u_action;

	/** @var string */
	public $tpl_name;

	/** @var string */
	public $page_title;

	public function main($id, $mode)
	{
		global $phpbb_container;

		/** @var \salvocortesiano\timezoneclock\controller\ucp_controller $controller */
		$controller = $phpbb_container->get('salvocortesiano.timezoneclock.controller.ucp');
		$language = $phpbb_container->get('language');

		$controller->set_page_url($this->u_action);
		$this->tpl_name = $controller->handle();
		$this->page_title = $language->lang('UCP_TZC_SETTINGS');
	}
}
