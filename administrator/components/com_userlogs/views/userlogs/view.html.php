<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_userlogs
 *
 * @copyright   Copyright (C) 2005 - 2018 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\Registry\Registry;
use Joomla\CMS\Plugin\PluginHelper;

/**
 * View class for a list of logs.
 *
 * @since  __DEPLOY_VERSION__
 */
class UserlogsViewUserlogs extends JViewLegacy
{
	/**
	 * An array of items.
	 *
	 * @var  array
	 *
	 * @since __DEPLOY_VERSION__
	 */
	protected $items;

	/**
	 * The model state
	 *
	 * @var  object
	 *
	 * @since __DEPLOY_VERSION__
	 */
	protected $state;

	/**
	 * The pagination object
	 *
	 * @var  JPagination
	 *
	 * @since __DEPLOY_VERSION__
	 */
	protected $pagination;

	/**
	 * The active search filters
	 *
	 * @var  array
	 *
	 * @since __DEPLOY_VERSION__
	 */
	public $activeFilters;

	/**
	 * Method to display the view.
	 *
	 * @param   string  $tpl  A template file to load. [optional]
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function display($tpl = null)
	{
		if (PluginHelper::isEnabled('system', 'userlogs'))
		{
			$params   = new Registry(PluginHelper::getPlugin('system', 'userlogs')->params);
			$this->ip = (bool) $params->get('ip_logging', 0);
		}

		$this->items         = $this->get('Items');
		$this->state         = $this->get('State');
		$this->filterForm    = $this->get('FilterForm');
		$this->activeFilters = $this->get('ActiveFilters');
		$this->pagination    = $this->get('Pagination');

		if (!empty($this->items))
		{
			$app = JFactory::getApplication();

			foreach ($this->items as $item)
			{
				$app->triggerEvent('onLogMessagePrepare', array (&$item->message, $item->extension));
			}
		}

		if (count($errors = $this->get('Errors')))
		{
			JError::raiseError(500, implode("\n", $errors));

			return false;
		}

		$this->addToolBar();

		parent::display($tpl);
	}

	/**
	 * Add the page title and toolbar.
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function addToolbar()
	{
		JToolbarHelper::title(JText::_('COM_USERLOGS_MANAGER_USERLOGS'));

		if (JFactory::getUser()->authorise('core.delete', 'com_userlogs'))
		{
			JToolbarHelper::deleteList('JGLOBAL_CONFIRM_DELETE', 'userlogs.delete');
			$bar = JToolbar::getInstance('toolbar');
			$bar->appendButton('Confirm', 'COM_USERLOGS_PURGE_CONFIRM', 'delete', 'COM_USERLOGS_TOOLBAR_PURGE', 'userlogs.purge', false);
		}

		if (JFactory::getUser()->authorise('core.admin', 'com_userlogs') || JFactory::getUser()->authorise('core.options', 'com_userlogs'))
		{
			JToolbarHelper::preferences('com_userlogs');
		}

		JToolBarHelper::custom('userlogs.exportSelectedLogs', 'download', '', 'COM_USERLOGS_EXPORT_CSV', true);
		JToolBarHelper::custom('userlogs.exportLogs', 'download', '', 'COM_USERLOGS_EXPORT_ALL_CSV', false);
	}
}
