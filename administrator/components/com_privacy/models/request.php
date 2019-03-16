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
 * Request item model class.
 *
 * @since  __DEPLOY_VERSION__
 */
class PrivacyModelRequest extends JModelAdmin
{
	/**
	 * Clean the cache
	 *
	 * @param   string   $group      The cache group
	 * @param   integer  $client_id  The ID of the client
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function cleanCache($group = 'com_privacy', $client_id = 1)
	{
		parent::cleanCache('com_privacy', 1);
	}

	/**
	 * Method for getting the form from the model.
	 *
	 * @param   array    $data      Data for the form.
	 * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
	 *
	 * @return  JForm|boolean  A JForm object on success, false on failure
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function getForm($data = array(), $loadData = true)
	{
		// Get the form.
		$form = $this->loadForm('com_privacy.request', 'request', array('control' => 'jform', 'load_data' => $loadData));

		if (empty($form))
		{
			return false;
		}

		return $form;
	}

	/**
	 * Method to get a table object, load it if necessary.
	 *
	 * @param   string  $name     The table name. Optional.
	 * @param   string  $prefix   The class prefix. Optional.
	 * @param   array   $options  Configuration array for model. Optional.
	 *
	 * @return  JTable  A JTable object
	 *
	 * @since   __DEPLOY_VERSION__
	 * @throws  \Exception
	 */
	public function getTable($name = 'Request', $prefix = 'PrivacyTable', $options = array())
	{
		return parent::getTable($name, $prefix, $options);
	}

	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return  array  The default data is an empty array.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function loadFormData()
	{
		// Check the session for previously entered form data.
		$data = JFactory::getApplication()->getUserState('com_privacy.edit.request.data', array());

		if (empty($data))
		{
			$data = $this->getItem();
		}

		return $data;
	}

	/**
	 * Notifies the user that an information request has been created by a site administrator.
	 *
	 * Because confirmation tokens are stored in the database as a hashed value, this method will generate a new confirmation token
	 * for the request.
	 *
	 * @param   integer  $id  The ID of the request to process.
	 *
	 * @return  boolean
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function notifyUserAdminCreatedRequest($id)
	{
		/** @var PrivacyTableRequest $table */
		$table = $this->getTable();

		if (!$table->load($id))
		{
			$this->setError($table->getError());

			return false;
		}

		$app = JFactory::getApplication();

		/*
		 * If there is an associated user account, we will attempt to send this email in the user's preferred language.
		 * Because of this, it is expected that Language::_() is directly called and that the Text class is NOT used
		 * for translating all messages.
		 *
		 * Error messages will still be displayed to the administrator, so those messages should continue to use the Text class.
		 */

		$lang = JFactory::getLanguage();

		if ($table->user_id)
		{
			$receiver = JUser::getInstance($table->user_id);

			/*
			 * We don't know if the user has admin access, so we will check if they have an admin language in their parameters,
			 * falling back to the site language, falling back to the currently active language
			 */

			$langCode = $receiver->getParam('admin_language', '');

			if (!$langCode)
			{
				$langCode = $receiver->getParam('language', $lang->getTag());
			}

			$lang = JLanguage::getInstance($langCode, $lang->getDebug());
		}

		// Ensure the right language files have been loaded
		$lang->load('com_privacy', JPATH_ADMINISTRATOR, null, false, true)
			|| $lang->load('com_privacy', JPATH_ADMINISTRATOR . '/components/com_privacy', null, false, true);

		// Regenerate the confirmation token
		$token       = JApplicationHelper::getHash(JUserHelper::genRandomPassword());
		$hashedToken = JUserHelper::hashPassword($token);

		$table->confirm_token            = $hashedToken;
		$table->confirm_token_created_at = JFactory::getDate()->toSql();

		try
		{
			$table->store();
		}
		catch (JDatabaseException $exception)
		{
			$this->setError($exception->getMessage());

			return false;
		}

		// The mailer can be set to either throw Exceptions or return boolean false, account for both
		try
		{
			// TODO - These URLs should be JRoute'd once the cross-app routing PR is available to this branch
			$substitutions = array(
				'[SITENAME]' => $app->get('sitename'),
				'[URL]'      => JUri::root(),
				'[TOKENURL]' => 'TODO',
				'[FORMURL]'  => 'TODO',
				'[TOKEN]'    => $token,
				'\\n'        => "\n",
			);

			$emailSubject = $lang->_('COM_PRIVACY_EMAIL_ADMIN_REQUEST_SUBJECT');

			switch ($table->request_type)
			{
				case 'export':
					$emailBody = $lang->_('COM_PRIVACY_EMAIL_ADMIN_REQUEST_BODY_EXPORT_REQUEST');

					break;

				case 'remove':
					$emailBody = $lang->_('COM_PRIVACY_EMAIL_ADMIN_REQUEST_BODY_REMOVE_REQUEST');

					break;

				default:
					$this->setError(JText::_('COM_PRIVACY_ERROR_UNKNOWN_REQUEST_TYPE'));

					return false;
			}

			foreach ($substitutions as $k => $v)
			{
				$emailSubject = str_replace($k, $v, $emailSubject);
				$emailBody    = str_replace($k, $v, $emailBody);
			}

			$mailer = JFactory::getMailer();
			$mailer->setSubject($emailSubject);
			$mailer->setBody($emailBody);
			$mailer->addRecipient($table->email);

			$mailResult = $mailer->Send();

			if ($mailResult instanceof JException)
			{
				// JError was already called so we just need to return now
				return false;
			}
			elseif ($mailResult === false)
			{
				$this->setError($mailer->ErrorInfo);

				return false;
			}

			return true;
		}
		catch (phpmailerException $exception)
		{
			$this->setError($exception->getMessage());

			return false;
		}
	}

	/**
	 * Method to validate the form data.
	 *
	 * @param   JForm   $form   The form to validate against.
	 * @param   array   $data   The data to validate.
	 * @param   string  $group  The name of the field group to validate.
	 *
	 * @return  array|boolean  Array of filtered data if valid, false otherwise.
	 *
	 * @see     JFormRule
	 * @see     JFilterInput
	 * @since   __DEPLOY_VERSION__
	 */
	public function validate($form, $data, $group = null)
	{
		$validatedData = parent::validate($form, $data, $group);

		// If parent validation failed there's no point in doing our extended validation
		if ($validatedData === false)
		{
			return false;
		}

		// The user cannot create a request for their own account
		if (strtolower(JFactory::getUser()->email) === strtolower($validatedData['email']))
		{
			$this->setError(JText::_('COM_PRIVACY_ERROR_CANNOT_CREATE_REQUEST_FOR_SELF'));

			return false;
		}

		// Check for an active request for this email address
		$db = $this->getDbo();

		$query = $db->getQuery(true)
			->select('COUNT(id)')
			->from('#__privacy_requests')
			->where('email = ' . $db->quote($validatedData['email']))
			->where('request_type = ' . $db->quote($validatedData['request_type']))
			->where('status IN (0, 1)');

		$activeRequestCount = (int) $db->setQuery($query)->loadResult();

		if ($activeRequestCount > 0)
		{
			$this->setError(JText::_('COM_PRIVACY_ERROR_ACTIVE_REQUEST_FOR_EMAIL'));

			return false;
		}

		return $validatedData;
	}
}
