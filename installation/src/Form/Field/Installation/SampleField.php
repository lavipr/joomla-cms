<?php
/**
 * @package    Joomla.Installation
 *
 * @copyright  Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\CMS\Installation\Form\Field\Installation;

defined('JPATH_BASE') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Form\Field\RadioField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/**
 * Install Sample Data field.
 *
 * @since  1.6
 */
class SampleField extends RadioField
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  1.6
	 */
	protected $type = 'Sample';

	/**
	 * Method to get the field options.
	 *
	 * @return  array  The field option objects.
	 *
	 * @since   1.6
	 */
	protected function getOptions()
	{
		$options = array();
		$type    = $this->form->getValue('db_type');

		// Some database drivers share DDLs; point these drivers to the correct parent
		if ($type === 'mysqli')
		{
			$type = 'mysql';
		}
		elseif ($type === 'pgsql')
		{
			$type = 'postgresql';
		}
		elseif ($type === 'pgsql')
		{
			$type = 'postgresql';
		}

		// Get a list of files in the search path with the given filter.
		$files = Folder::files(JPATH_INSTALLATION . '/sql/' . $type, '^sample.*\.sql$');

		// Add option to not install sample data.
		$options[] = HTMLHelper::_('select.option', '',
			HTMLHelper::_('tooltip', Text::_('INSTL_SITE_INSTALL_SAMPLE_NONE_DESC'), '', '', Text::_('JNO'))
		);

		// Build the options list from the list of files.
		if (is_array($files))
		{
			foreach ($files as $file)
			{
				$options[] = HTMLHelper::_('select.option', $file, Factory::getLanguage()->hasKey($key = 'INSTL_' . ($file = File::stripExt($file)) . '_SET') ?
					HTMLHelper::_('tooltip', Text::_('INSTL_' . strtoupper($file = File::stripExt($file)) . '_SET_DESC'), '', '',
						Text::_('JYES')
					) : $file
				);
			}
		}

		// Merge any additional options in the XML definition.
		$options = array_merge(parent::getOptions(), $options);

		return $options;
	}

	/**
	 * Method to get the field input markup.
	 *
	 * @return  string   The field input markup.
	 *
	 * @since   1.6
	 */
	protected function getInput()
	{
		if (!$this->value)
		{
			$conf = Factory::getConfig();

			if ($conf->get('sampledata'))
			{
				$this->value = $conf->get('sampledata');
			}
			else
			{
				$this->value = '';
			}
		}

		if (empty($this->layout))
		{
			throw new \UnexpectedValueException(sprintf('%s has no layout assigned.', $this->name));
		}

		return $this->getRenderer($this->layout)->render($this->getLayoutData());
	}
}
