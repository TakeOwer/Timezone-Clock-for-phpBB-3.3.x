<?php
/**
 *
 * Timezone Clock. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano <https://netshadows.de>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace salvocortesiano\timezoneclock\core;

/**
 * Builds the ACP and UCP option forms from the option registry
 */
class form
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\template\template */
	protected $template;

	public function __construct(\phpbb\config\config $config, \phpbb\language\language $language, \phpbb\template\template $template)
	{
		$this->config = $config;
		$this->language = $language;
		$this->template = $template;
	}

	/**
	 * Generate the form fields from the option registry
	 *
	 * @param array $values current values ('' = board default in the UCP)
	 * @param bool  $ucp    UCP form (only user options, with a "default" choice)
	 */
	public function assign_fields(array $values, $ucp)
	{
		$all = options::all();

		foreach (options::sections() as $section)
		{
			$fields = [];
			foreach ($all as $key => $def)
			{
				if ($def['section'] === $section && (!$ucp || !empty($def['ucp'])))
				{
					$fields[$key] = $def;
				}
			}
			if (!$fields)
			{
				continue;
			}

			$this->template->assign_block_vars('tzc_sections', [
				'ID'		=> $section,
				'TITLE'		=> $this->language->lang('TZC_SEC_' . strtoupper($section)),
				'EXPLAIN'	=> $this->language->is_set('TZC_SEC_' . strtoupper($section) . '_EXPLAIN') ? $this->language->lang('TZC_SEC_' . strtoupper($section) . '_EXPLAIN') : '',
			]);

			foreach ($fields as $key => $def)
			{
				$label_key = 'TZC_OPT_' . strtoupper($key);
				$value = isset($values[$key]) ? $values[$key] : $def['default'];
				$default_value = options::get($this->config, $key);

				$this->template->assign_block_vars('tzc_sections.fields', [
					'KEY'		=> $key,
					'NAME'		=> 'tzc_' . $key,
					'TYPE'		=> $def['type'],
					'LABEL'		=> $this->language->lang($label_key),
					'EXPLAIN'	=> $this->language->is_set($label_key . '_EXPLAIN') ? $this->language->lang($label_key . '_EXPLAIN') : '',
					'VALUE'		=> $value,
					'MIN'		=> isset($def['min']) ? $def['min'] : '',
					'MAX'		=> isset($def['max']) ? $def['max'] : '',
					'S_UCP'		=> !empty($def['ucp']),
					'S_INT'		=> in_array($def['type'], ['int', 'bool'], true),
					'DEFAULT_TEXT'	=> $ucp ? $this->language->lang('TZC_UCP_DEFAULT', $this->value_label($key, $default_value)) : '',
					'S_PREVIEW'	=> in_array($key, bar_builder::CLIENT_OPTS, true) || $key === 'sort',
				]);

				if ($def['type'] === 'enum' || $def['type'] === 'multi' || ($ucp && $def['type'] === 'bool'))
				{
					$choices = ($def['type'] === 'bool') ? [1, 0] : $def['values'];
					$selected = ($def['type'] === 'multi') ? explode(',', (string) $value) : [(string) $value];

					foreach ($choices as $choice)
					{
						$this->template->assign_block_vars('tzc_sections.fields.choices', [
							'VALUE'		=> $choice,
							'LABEL'		=> $this->value_label($key, $choice),
							'S_SELECTED'=> in_array((string) $choice, $selected, true),
						]);
					}
				}
			}
		}
	}

	public function value_label($key, $value)
	{
		$all = options::all();
		$def = $all[$key];

		if ($def['type'] === 'bool')
		{
			return $this->language->lang($value ? 'YES' : 'NO');
		}

		if ($def['type'] === 'enum' || $def['type'] === 'multi')
		{
			$lang = 'TZC_OPT_' . strtoupper($key) . '_' . strtoupper((string) $value);
			return $this->language->is_set($lang) ? $this->language->lang($lang) : (string) $value;
		}

		return (string) $value;
	}
}
