<?php
/**
 *
 * Timezone Clock. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano <https://netshadows.de>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace salvocortesiano\timezoneclock\migrations;

/**
 * 1.0.11: city tooltip and information popup
 */
class v1_0_11_info extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['tzc_tooltip']) && isset($this->config['tzc_info']);
	}

	public static function depends_on()
	{
		return ['\salvocortesiano\timezoneclock\migrations\v1_0_6_search'];
	}

	public function update_data()
	{
		return [
			['if', [!isset($this->config['tzc_tooltip']), ['config.add', ['tzc_tooltip', 1]]]],
			['if', [!isset($this->config['tzc_info']), ['config.add', ['tzc_info', 1]]]],
		];
	}

	public function revert_data()
	{
		return [
			['if', [isset($this->config['tzc_tooltip']), ['config.remove', ['tzc_tooltip']]]],
			['if', [isset($this->config['tzc_info']), ['config.remove', ['tzc_info']]]],
		];
	}
}
