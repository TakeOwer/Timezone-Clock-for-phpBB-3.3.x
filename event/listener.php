<?php
/**
 *
 * Timezone Clock. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano <https://netshadows.de>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace salvocortesiano\timezoneclock\event;

use salvocortesiano\timezoneclock\core\options;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var \salvocortesiano\timezoneclock\core\bar_builder */
	protected $builder;

	/** @var \salvocortesiano\timezoneclock\core\prefs */
	protected $prefs;

	/** @var string */
	protected $root_path;

	/** @var string */
	protected $php_ext;

	public function __construct(\phpbb\config\config $config, \phpbb\template\template $template, \phpbb\user $user, $builder, $prefs, $root_path, $php_ext)
	{
		$this->config = $config;
		$this->template = $template;
		$this->user = $user;
		$this->builder = $builder;
		$this->prefs = $prefs;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.page_header_after'	=> 'page_header',
			'core.delete_user_after'	=> 'delete_users',
		];
	}

	/**
	 * Current script name: index, viewtopic, app...
	 */
	protected function script()
	{
		$page = isset($this->user->page['page_name']) ? (string) $this->user->page['page_name'] : '';
		$page = preg_replace('#[/?].*$#', '', $page);

		return preg_replace('#\.' . preg_quote($this->php_ext, '#') . '$#', '', $page);
	}

	protected function page_allowed()
	{
		$mode = options::get($this->config, 'pages');
		$script = $this->script();

		if ($mode === 'all')
		{
			return true;
		}

		if ($mode === 'custom')
		{
			return in_array($script, explode(',', options::get($this->config, 'pages_list')), true);
		}

		return $script === 'index';
	}

	public function page_header()
	{
		if (!options::get($this->config, 'enable') || !$this->page_allowed())
		{
			return;
		}

		$data = $this->user->data;
		if (!empty($data['is_bot']) && !options::get($this->config, 'bots'))
		{
			return;
		}
		if ($data['user_id'] == ANONYMOUS && empty($data['is_bot']) && !options::get($this->config, 'guests'))
		{
			return;
		}

		$ucp_url = append_sid($this->root_path . 'ucp.' . $this->php_ext, 'i=-salvocortesiano-timezoneclock-ucp-main_module&mode=settings', false);

		try
		{
			$payload = $this->builder->for_user($data, $ucp_url);
		}
		catch (\Exception $e)
		{
			// a clock must never break the forum
			return;
		}

		if (!$payload)
		{
			return;
		}

		$classes = [];
		if ($payload['opt']['density'] === 'compact')
		{
			$classes[] = 'tzc-dens-compact';
		}
		if (!$payload['opt']['mobile'])
		{
			$classes[] = 'tzc-hide-mobile';
		}

		$this->template->assign_vars([
			'S_TZC_SHOW'		=> true,
			'TZC_POSITION'		=> options::get($this->config, 'position'),
			'TZC_ROOT_CLASS'	=> implode(' ', $classes),
			'TZC_PAYLOAD'		=> json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
		]);
	}

	public function delete_users($event)
	{
		$this->prefs->delete((array) $event['user_ids']);
	}
}
