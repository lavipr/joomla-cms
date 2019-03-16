<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_privacy
 *
 * @copyright   Copyright (C) 2005 - 2018 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * Request view class
 *
 * @since  __DEPLOY_VERSION__
 */
class PrivacyViewRequest extends JViewLegacy
{
	/**
	 * The form object
	 *
	 * @var    JForm
	 * @since  __DEPLOY_VERSION__
	 */
	protected $form;

	/**
	 * The item record
	 *
	 * @var    JObject
	 * @since  __DEPLOY_VERSION__
	 */
	protected $item;

	/**
	 * The state information
	 *
	 * @var    JObject
	 * @since  __DEPLOY_VERSION__
	 */
	protected $state;

	/**
	 * Execute and display a template script.
	 *
	 * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
	 *
	 * @return  mixed  A string if successful, otherwise an Error object.
	 *
	 * @see     JViewLegacy::loadTemplate()
	 * @since   __DEPLOY_VERSION__
	 * @throws  Exception
	 */
	public function display($tpl = null)
	{
		// Initialise variables.
		$this->item  = $this->get('Item');
		$this->state = $this->get('State');

		// Variables only required for the edit layout
		if ($this->getLayout() === 'edit')
		{
			$this->form = $this->get('Form');
		}

		// Check for errors.
		if (count($errors = $this->get('Errors')))
		{
			throw new Exception(implode("\n", $errors), 500);
		}

		$this->addToolbar();

		return parent::display($tpl);
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
		JFactory::getApplication('administrator')->set('hidemainmenu', true);

		// Set the title and toolbar based on the layout
		if ($this->getLayout() === 'edit')
		{
			JToolbarHelper::title(JText::_('COM_PRIVACY_VIEW_REQUEST_ADD_REQUEST'), 'dashboard');

			JToolbarHelper::apply('request.save');
			JToolbarHelper::cancel('request.cancel');
		}
		else
		{
			JToolbarHelper::title(JText::_('COM_PRIVACY_VIEW_REQUEST_SHOW_REQUEST'), 'dashboard');

			$bar = JToolbar::getInstance('toolbar');

			// Add transition buttons based on item status
			switch ($this->item->status)
			{
				case '0':
					$bar->appendButton('Standard', 'cancel-circle', 'COM_PRIVACY_TOOLBAR_INVALIDATE', 'request.invalidate', false);

					break;

				case '1':
					$bar->appendButton('Standard', 'apply', 'COM_PRIVACY_TOOLBAR_COMPLETE', 'request.complete', false);
					$bar->appendButton('Standard', 'cancel-circle', 'COM_PRIVACY_TOOLBAR_INVALIDATE', 'request.invalidate', false);

					break;

				// Item is in a "locked" state and cannot transition
				default:
					break;
			}

			JToolbarHelper::cancel('request.cancel', 'JTOOLBAR_CLOSE');
		}
	}
}
