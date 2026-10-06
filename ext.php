<?php
/**
 *
 * Timezone Clock. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano <https://netshadows.de>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace salvocortesiano\timezoneclock;

class ext extends \phpbb\extension\base
{
	/**
	 * phpBB 3.3.x and PHP 7.4 or newer
	 */
	public function is_enableable()
	{
		$config = $this->container->get('config');

		$ok = phpbb_version_compare($config['version'], '3.3.0', '>=')
			&& phpbb_version_compare($config['version'], '4.0.0-dev', '<')
			&& version_compare(PHP_VERSION, '7.4.0', '>=')
			&& function_exists('gzdecode');

		if (!$ok)
		{
			$language = $this->container->get('language');
			$language->add_lang('info_acp_timezoneclock', 'salvocortesiano/timezoneclock');

			return [$language->lang('TZC_NOT_ENABLEABLE')];
		}

		return true;
	}

	/**
	 * Remove the temporary download folder when the data is deleted
	 */
	public function purge_step($old_state)
	{
		if ($old_state === false)
		{
			$dir = $this->container->getParameter('core.root_path') . 'store/tzc/';
			if (is_dir($dir))
			{
				foreach ((array) glob($dir . '*') as $file)
				{
					if (is_file($file))
					{
						@unlink($file);
					}
				}
				@rmdir($dir);
			}
		}

		return parent::purge_step($old_state);
	}
}
