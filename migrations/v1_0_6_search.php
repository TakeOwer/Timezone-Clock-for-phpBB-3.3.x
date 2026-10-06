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
 * 1.0.6: city search in the bar
 */
class v1_0_6_search extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['tzc_search']) && isset($this->config['tzc_search_min']);
	}

	public static function depends_on()
	{
		return ['\salvocortesiano\timezoneclock\migrations\v1_0_0_data'];
	}

	public function update_data()
	{
		return [
			['if', [!isset($this->config['tzc_search']), ['config.add', ['tzc_search', 'auto']]]],
			['if', [!isset($this->config['tzc_search_min']), ['config.add', ['tzc_search_min', 8]]]],
		];
	}

	public function revert_data()
	{
		return [
			['if', [isset($this->config['tzc_search']), ['config.remove', ['tzc_search']]]],
			['if', [isset($this->config['tzc_search_min']), ['config.remove', ['tzc_search_min']]]],
		];
	}
}
