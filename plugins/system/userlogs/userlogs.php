<?php
/**
 * @package     Joomla.Plugins
 * @subpackage  System.userlogs
 *
 * @copyright   Copyright (C) 2005 - 2018 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

JLoader::register('UserlogsHelper', JPATH_ADMINISTRATOR . '/components/com_userlogs/helpers/userlogs.php');

/**
 * Joomla! Users Actions Logging Plugin.
 *
 * @since  __DEPLOY_VERSION__
 */
class PlgSystemUserLogs extends JPlugin
{
	/**
	 * Array of loggable extensions.
	 *
	 * @var    array
	 * @since  __DEPLOY_VERSION__
	 */
	protected $loggableExtensions = array();

	/**
	 * Application object.
	 *
	 * @var    JApplicationCms
	 * @since  __DEPLOY_VERSION__
	 */
	protected $app;

	/**
	 * Database object.
	 *
	 * @var    JDatabaseDriver
	 * @since  __DEPLOY_VERSION__
	 */
	protected $db;

	/**
	 * Constructor.
	 *
	 * @param   object  &$subject  The object to observe.
	 * @param   array   $config    An optional associative array of configuration settings.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);

		if (is_array($this->params->get('loggable_extensions')))
		{
			$this->loggableExtensions = $this->params->get('loggable_extensions');

			return;
		}

		$this->loggableExtensions = explode(',', $this->params->get('loggable_extensions'));
	}

	/**
	 * Function to add logs to the database
	 * This method adds a record to #__user_logs contains (message, date, context, user)
	 *
	 * @param   array    $message  The contents of the message to be logged
	 * @param   string   $context  The context of the content passed to the plugin
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function addLogsToDb($message, $context)
	{
		$user       = JFactory::getUser();
		$date       = JFactory::getDate();
		$query      = $this->db->getQuery(true);

		if ($this->params->get('ip_logging', 0))
		{
			$ip = $this->app->input->server->get('REMOTE_ADDR');
		}
		else
		{
			$ip = JText::_('PLG_SYSTEM_USERLOGS_DISABLED');
		}

		$json_message = json_encode($message);

		$columns = array(
			'message',
			'log_date',
			'extension',
			'user_id',
			'ip_address',
		);

		$values = array(
			$this->db->quote($json_message),
			$this->db->quote($date),
			$this->db->quote($context),
			$this->db->quote($user->id),
			$this->db->quote($ip),
		);

		$query->insert($this->db->quoteName('#__user_logs'))
			->columns($this->db->quoteName($columns))
			->values(implode(',', $values));

		$this->db->setQuery($query);

		try
		{
			$this->db->execute();
		}
		catch (RuntimeException $e)
		{
			JError::raiseWarning(500, $e->getMessage());

			return;
		}

		$this->app->triggerEvent('onUserLogsAfterMessageLog', array ($json_message, $date, $context, $user->name, $ip));
	}

	/**
	 * Function to check if a component is loggable or not
	 *
	 * @param   string   $extension  The extension that triggered the event
	 *
	 * @return  boolean
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function checkLoggable($extension)
	{
		return in_array($extension, $this->loggableExtensions);
	}

	/**
	 * After save content logging method
	 * This method adds a record to #__user_logs contains (message, date, context, user)
	 * Method is called right after the content is saved
	 *
	 * @param   string   $context  The context of the content passed to the plugin
	 * @param   object   $article  A JTableContent object
	 * @param   boolean  $isNew    If the content is just about to be created
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onContentAfterSave($context, $article, $isNew)
	{
		if (!$this->checkLoggable($this->app->input->get('option')))
		{
			return;
		}

		$isNew_string = $isNew ? 'true' : 'false';
		$parameters   = UserlogsHelper::getLogMessageParams($context);
		$title_holder = '';
		$type_title   = '';
		$strContext   = (string) $context;

		if ($parameters)
		{
			$title_holder = $article->get($parameters->title_holder);
			$type_title   = $parameters->type_title;
		}

		$message = array(
			'title' => $title_holder,
			'isNew' => $isNew_string,
			'event' => 'onContentAfterSave',
			'type'  => $type_title,
		);

		$this->addLogsToDb($message, $strContext);
	}

	/**
	 * After delete content logging method
	 * This method adds a record to #__user_logs contains (message, date, context, user)
	 * Method is called right after the content is deleted
	 *
	 * @param   string   $context  The context of the content passed to the plugin
	 * @param   object   $article  A JTableContent object
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onContentAfterDelete($context, $article)
	{
		if (!$this->checkLoggable($this->app->input->get('option')))
		{
			return;
		}

		$parameters   = UserlogsHelper::getLogMessageParams($context);
		$title_holder = '';
		$type_title   = '';
		$strContext   = (string) $context;

		if ($parameters)
		{
			$title_holder = $article->get($parameters->title_holder);
			$type_title   = $parameters->type_title;
		}

		$message = array(
			'title' => $title_holder,
			'event' => 'onContentAfterDelete',
			'type'  => $type_title,
		);

		$this->addLogsToDb($message, $strContext);
	}

	/**
	 * On content change status logging method
	 * This method adds a record to #__user_logs contains (message, date, context, user)
	 * Method is called when the status of the article is changed
	 *
	 * @param   string   $context  The context of the content passed to the plugin
	 * @param   array    $pks      An array of primary key ids of the content that has changed state.
	 * @param   integer  $value    The value of the state that the content has been changed to.
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onContentChangeState($context, $pks, $value)
	{
		if (!$this->checkLoggable($this->app->input->get('option')))
		{
			return;
		}

		$parameters = UserlogsHelper::getLogMessageParams($context);
		$titles     = array();
		$strContext = (string) $context;

		if ($parameters)
		{
			$table_values = json_decode($parameters->table_values, true);
			$titles       = UserlogsHelper::getDataByPks($pks, $parameters->title_holder, $table_values['table_type'], $table_values['table_prefix']);
		}

		$message = array(
			'title' => implode('\",\"', $titles),
			'event' => 'onContentChangeState',
			'type'  => $parameters->type_title,
			'value' => (string) $value,
		);

		$this->addLogsToDb($message, $strContext);
	}

	/**
	 * On installing extensions logging method
	 * This method adds a record to #__user_logs contains (message, date, context, user)
	 * Method is called when an extension is installed
	 *
	 * @param   JInstaller  $installer  Installer object
	 * @param   integer     $eid        Extension Identifier
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onExtensionAfterInstall($installer, $eid)
	{
		$context = $this->app->input->get('option');

		if (!$this->checkLoggable($context))
		{
			return;
		}

		$manifest = $installer->get('manifest');
		$message  = array(
			'event'          => 'onExtensionAfterInstall',
			'extension_name' => (string) $manifest->name,
			'extension_type' => (string) $manifest->attributes()->type,
		);

		$this->addLogsToDb($message, $context);
	}

	/**
	 * On uninstalling extensions logging method
	 * This method adds a record to #__user_logs contains (message, date, context, user)
	 * Method is called when an extension is uninstalled
	 *
	 * @param   JInstaller  $installer  Installer instance
	 * @param   integer     $eid        Extension id
	 * @param   integer     $result     Installation result
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onExtensionAfterUninstall($installer, $eid, $result)
	{
		$context = $this->app->input->get('option');

		if (!$this->checkLoggable($context))
		{
			return;
		}

		$manifest = $installer->get('manifest');
		$message = array(
			'event'          => 'onExtensionAfterUninstall',
			'extension_name' => (string) $manifest->name,
			'extension_type' => (string) $manifest->attributes()->type,
		);

		$this->addLogsToDb($message, $context);
	}

	/**
	 * On updating extensions logging method
	 * This method adds a record to #__user_logs contains (message, date, context, user)
	 * Method is called when an extension is updated
	 *
	 * @param   JInstaller  $installer  Installer instance
	 * @param   integer     $eid        Extension id
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onExtensionAfterUpdate($installer, $eid)
	{
		$context = $this->app->input->get('option');

		if (!$this->checkLoggable($context))
		{
			return;
		}

		$manifest = $installer->get('manifest');
		$message = array(
			'event'          => 'onExtensionAfterUpdate',
			'extension_name' => (string) $manifest->name,
			'extension_type' => (string) $manifest->attributes()->type,
		);

		$this->addLogsToDb($message, $context);
	}

	/**
	 * On Saving extensions logging method
	 * Method is called when an extension is being saved
	 *
	 * @param   string   $context  The extension
	 * @param   JTable   $table    DataBase Table object
	 * @param   boolean  $isNew    If the extension is new or not
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onExtensionAfterSave($context, $table, $isNew)
	{
		if (!$this->checkLoggable($this->app->input->get('option')))
		{
			return;
		}

		$parameters   = UserlogsHelper::getLogMessageParams($context);
		$title_holder = '';
		$type_title   = '';

		if ($parameters)
		{
			$title_holder = $table->get($parameters->title_holder);
			$type_title   = $parameters->type_title;
		}

		$isNew_string = $isNew ? 'true' : 'false';

		$message = array(
			'title' => $title_holder,
			'isNew' => $isNew_string,
			'event' => 'onExtensionAfterSave',
			'type'  => $type_title,
		);

		$this->addLogsToDb($message, $context);
	}

	/**
	 * On Deleting extensions logging method
	 * Method is called when an extension is being deleted
	 *
	 * @param   string  $context  The extension
	 * @param   JTable  $table    DataBase Table object
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onExtensionAfterDelete($context, $table)
	{
		if (!$this->checkLoggable($this->app->input->get('option')))
		{
			return;
		}

		$message = array(
			'event' => 'onExtensionAfterDelete',
			'title' => $table->title,
		);

		$this->addLogsToDb($message, $context);
	}

	/**
	 * On saving user data logging method
	 *
	 * Method is called after user data is stored in the database.
	 * This method logs who created/edited any user's data
	 *
	 * @param   array    $user     Holds the new user data.
	 * @param   boolean  $isnew    True if a new user is stored.
	 * @param   boolean  $success  True if user was succesfully stored in the database.
	 * @param   string   $msg      Message.
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onUserAfterSave($user, $isnew, $success, $msg)
	{
		$context = $this->app->input->get('option');

		if (!$this->checkLoggable($context))
		{
			return;
		}

		$isNew_string   = $isnew ? 'true' : 'false';

		$message = array(
			'edited_user' => $user['name'],
			'isNew'       => $isNew_string,
			'event'       => 'onUserAfterSave',
			'user_id'     => $user['id'],
		);

		$this->addLogsToDb($message, $context);
	}

	/**
	 * On deleting user data logging method
	 *
	 * Method is called after user data is deleted from the database
	 *
	 * @param   array    $user     Holds the user data
	 * @param   boolean  $success  True if user was succesfully stored in the database
	 * @param   string   $msg      Message
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onUserAfterDelete($user, $success, $msg)
	{
		$context = $this->app->input->get('option');

		if (!$this->checkLoggable($context))
		{
			return;
		}

		$message = array(
			'deleted_user' => $user['name'],
			'event'        => 'onUserAfterDelete',
			'user_id'      => $user['id'],
		);

		$this->addLogsToDb($message, $context);
	}

	/**
	 * On after save user group data logging method
	 *
	 * Method is called after user data is deleted from the database
	 *
	 * @param   string   $context  The context
	 * @param   JTable   $table    DataBase Table object
	 * @param   boolean  $isNew    Is new or not
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onUserAfterSaveGroup($context, $table, $isNew)
	{
		$context = $this->app->input->get('option');

		if (!$this->checkLoggable($context))
		{
			return;
		}

		$isNew_string = $isNew ? 'true' : 'false';

		$message = array(
			'title' => $table->title,
			'isNew' => $isNew_string,
			'event' => 'onUserAfterSaveGroup',
		);

		$this->addLogsToDb($message, $context);
	}

	/**
	 * On deleting user group data logging method
	 *
	 * Method is called after user data is deleted from the database
	 *
	 * @param   array    $group    Holds the group data
	 * @param   boolean  $success  True if user was succesfully stored in the database
	 * @param   string   $msg      Message
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onUserAfterDeleteGroup($group, $success, $msg)
	{
		$context = $this->app->input->get('option');

		if (!$this->checkLoggable($context))
		{
			return;
		}

		$message = array(
			'deleted_group' => $group['title'],
			'event'         => 'onUserAfterDeleteGroup',
			'group_id'      => $group['id'],
		);

		$this->addLogsToDb($message, $context);
	}

	/**
	 * Method is called before writing logs message to make it more readable
	 *
	 * @param   string  $message    Message
	 * @param   string  $extension  Extension that caused this log
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onLogMessagePrepare(&$message, $extension)
	{
		// Load the language
		$this->loadLanguage();

		$extension = UserlogsHelper::translateExtensionName(strtoupper(strtok($extension, '.')));
		$extension = preg_replace('/s$/', '', $extension);
		$message_to_array = json_decode($message, true);
		$type = '';

		if (!empty($message_to_array['type']))
		{
			$type = 'PLG_SYSTEM_USERLOGS_TYPE_' . strtoupper($message_to_array['type']);
		}

		switch ($message_to_array['event'])
		{
			case 'onContentAfterSave':
				if ($message_to_array['isNew'] == 'false')
				{
					$message = JText::sprintf('PLG_SYSTEM_USERLOGS_ON_CONTENT_AFTER_SAVE_MESSAGE', ucfirst(JText::_($type)));
				}
				else
				{
					$message = JText::sprintf('PLG_SYSTEM_USERLOGS_ON_CONTENT_AFTER_SAVE_NEW_MESSAGE', JText::_($type));
				}

				if (!empty($message_to_array['title']))
				{
					$message = $message . JText::sprintf('PLG_SYSTEM_USERLOGS_TITLED', $message_to_array['title']);
				}

				break;

			case 'onContentAfterDelete':
				$message = JText::sprintf('PLG_SYSTEM_USERLOGS_ON_CONTENT_AFTER_DELETE_MESSAGE', ucfirst(JText::_($type)));

				if (!empty($message_to_array['title']))
				{
					$message = $message . JText::sprintf('PLG_SYSTEM_USERLOGS_TITLED', $message_to_array['title']);
				}

				break;

			case 'onContentChangeState':
				if ($message_to_array['value'] == 0)
				{
					$message = JText::sprintf('PLG_SYSTEM_USERLOGS_ON_CONTENT_CHANGE_STATE_UNPUBLISHED_MESSAGE', $message_to_array['title']
					);
				}
				elseif ($message_to_array['value'] == 1)
				{
					$message = JText::sprintf('PLG_SYSTEM_USERLOGS_ON_CONTENT_CHANGE_STATE_PUBLISHED_MESSAGE', $message_to_array['title']
					);
				}
				elseif ($message_to_array['value'] == 2)
				{
					$message = JText::sprintf('PLG_SYSTEM_USERLOGS_ON_CONTENT_CHANGE_STATE_ARCHIVED_MESSAGE', $message_to_array['title']
					);
				}
				elseif ($message_to_array['value'] == -2)
				{
					$message = JText::sprintf('PLG_SYSTEM_USERLOGS_ON_CONTENT_CHANGE_STATE_TRASHED_MESSAGE', $message_to_array['title']
					);
				}

				break;
			case 'onExtensionAfterInstall':
				$extension_name = array_key_exists('extension_name', $message_to_array) ? $message_to_array['extension_name'] : '';
				$message = JText::sprintf('PLG_SYSTEM_USERLOGS_ON_EXTENSION_AFTER_INSTALL_MESSAGE', UserlogsHelper::translateExtensionName($extension_name));

				break;
			case 'onExtensionAfterUninstall':
				$extension_name = array_key_exists('extension_name', $message_to_array) ? $message_to_array['extension_name'] : '';
				$message = JText::sprintf('PLG_SYSTEM_USERLOGS_ON_EXTENSION_AFTER_UNINSTALL_MESSAGE', UserlogsHelper::translateExtensionName($extension_name));

				break;
			case 'onExtensionAfterUpdate':
				$extension_name = array_key_exists('extension_name', $message_to_array) ? $message_to_array['extension_name'] : '';
				$message = JText::sprintf('PLG_SYSTEM_USERLOGS_ON_EXTENSION_AFTER_UPDATE_MESSAGE', UserlogsHelper::translateExtensionName($extension_name));

				break;
			case 'onUserAfterSave':
				if ($message_to_array['isNew'] == 'false')
				{
					$message = JText::sprintf('PLG_SYSTEM_USERLOGS_ON_USER_AFTER_SAVE_MESSAGE', $message_to_array['edited_user']);
				}
				else
				{
					$message = JText::sprintf('PLG_SYSTEM_USERLOGS_ON_USER_AFTER_SAVE_NEW_MESSAGE', $message_to_array['edited_user']);
				}

				break;
			case 'onUserAfterDelete':
				$message = JText::sprintf('PLG_SYSTEM_USERLOGS_ON_USER_AFTER_DELETE_MESSAGE', $message_to_array['deleted_user']);

				break;
			case 'onUserAfterSaveGroup':
				if ($message_to_array['isNew'] == 'false')
				{
					$message = JText::sprintf('PLG_SYSTEM_USERLOGS_ON_USER_AFTER_SAVE_GROUP_MESSAGE', $message_to_array['title']);
				}
				else
				{
					$message = JText::sprintf('PLG_SYSTEM_USERLOGS_ON_USER_AFTER_SAVE_GROUP_NEW_MESSAGE', $message_to_array['title']);
				}

				break;
			case 'onUserAfterDeleteGroup':
				$message = JText::sprintf('PLG_SYSTEM_USERLOGS_ON_USER_AFTER_DELETE_GROUP_MESSAGE', $message_to_array['deleted_group']);

				break;
			case 'onExtensionAfterSave':
				if ($message_to_array['isNew'] == 'false')
				{
					$message = JText::sprintf('PLG_SYSTEM_USERLOGS_ON_EXTENSION_AFTER_SAVE_MESSAGE', ucfirst(JText::_($type)));
				}
				else
				{
					$message = JText::sprintf('PLG_SYSTEM_USERLOGS_ON_EXTENSION_AFTER_SAVE_NEW_MESSAGE', JText::_($type));
				}

				if (!empty($message_to_array['title']))
				{
					$message = $message . JText::sprintf('PLG_SYSTEM_USERLOGS_TITLED', UserlogsHelper::translateExtensionName($message_to_array['title']));
				}

				break;
			case 'onExtensionAfterDelete':
				$message = JText::sprintf('PLG_SYSTEM_USERLOGS_ON_EXTENSION_AFTER_DELETE_MESSAGE', $extension);

				if (!empty($message_to_array['title']))
				{
					$message = $message . JText::sprintf('PLG_SYSTEM_USERLOGS_TITLED', UserlogsHelper::translateExtensionName($message_to_array['title']));
				}

				break;
		}
	}

	/**
	 * Adds additional fields to the user editing form for logs e-mail notifications
	 *
	 * @param   JForm  $form  The form to be altered.
	 * @param   mixed  $data  The associated data for the form.
	 *
	 * @return  boolean
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onContentPrepareForm($form, $data)
	{
		if (!$form instanceof JForm)
		{
			$this->subject->setError('JERROR_NOT_A_FORM');

			return false;
		}

		$formName = $form->getName();

		$allowedFormNames = array(
			'com_users.profile',
			'com_admin.profile',
		);

		if (!in_array($formName, $allowedFormNames) || !JFactory::getUser()->authorise('core.viewlogs'))
		{
			return true;
		}

		$lang = JFactory::getLanguage();
		$lang->load('plg_system_userlogs', JPATH_ADMINISTRATOR);

		JForm::addFormPath(dirname(__FILE__) . '/forms');
		$form->loadFile('userlogs', false);
	}

	/**
	 * Method called after event log is stored to database
	 *
	 * @param   array   $message   The message
	 * @param   array   $date      The Date
	 * @param   string  $context   The Context
	 * @param   array   $userName  The username
	 * @param   string  $ip        The user ip
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onUserLogsAfterMessageLog($message, $date, $context, $userName, $ip)
	{
		$query = $this->db->getQuery(true);

		$query->select('a.email, a.params')
			->from($this->db->quoteName('#__users', 'a'))
			->where($this->db->quoteName('params') . ' LIKE ' . $this->db->quote('%"logs_notification_option":"1"%'));

		$this->db->setQuery($query);

		try
		{
			$users = $this->db->loadObjectList();
		}
		catch (RuntimeException $e)
		{
			JError::raiseWarning(500, $e->getMessage());

			return;
		}

		$recipients = array();

		foreach ($users as $user)
		{
			$userParams = json_decode($user->params, true);
			$extensions = $userParams['logs_notification_extensions'];

			if (in_array(strtok($context, '.'), $extensions))
			{
				$recipients[] = $user->email;
			}
		}

		if (empty($recipients))
		{
			return;
		}

		$this->app->triggerEvent('onLogMessagePrepare', array (&$message, $context));
		$layout = new JLayoutFile('plugins.system.userlogs.layouts.logstable', JPATH_ROOT);

		$displayData = array(
			'message' => $message,
			'log_date' => $date,
			'extension' => UserlogsHelper::translateExtensionName(strtoupper(strtok($context, '.'))),
			'username' => $userName,
			'ip' => JText::_($ip)
		);

		$body = $layout->render($displayData);
		$mailer = JFactory::getMailer();
		$mailer->addRecipient($recipients);
		$mailer->setSubject(JText::_('PLG_SYSTEM_USERLOGS_EMAIL_SUBJECT'));
		$mailer->isHTML(true);
		$mailer->Encoding = 'base64';
		$mailer->setBody($body);

		if (!$mailer->Send())
		{
			$this->app->enqueueMessage(JText::_('JERROR_SENDING_EMAIL'), 'warning');
		}
	}

	/**
	 * Runs after the HTTP response has been sent to the client and delete log records older than certain days
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onAfterRespond()
	{
		$daysToDeleteAfter = (int) $this->params->get('logDeletePeriod', 0);

		if ($daysToDeleteAfter <= 0)
		{
			return;
		}

		$deleteFrequency = 3600 * 24; // The delete frequency will be once per day

		// Do we need to run? Compare the last run timestamp stored in the plugin's options with the current
		// timestamp. If the difference is greater than the cache timeout we shall not execute again.
		$now  = time();
		$last = (int) $this->params->get('lastrun', 0);

		if (abs($now - $last) < $deleteFrequency)
		{
			return;
		}

		// Update last run status
		$this->params->set('lastrun', $now);

		$db    = JFactory::getDbo();
		$query = $db->getQuery(true)
			->update($db->qn('#__extensions'))
			->set($db->qn('params') . ' = ' . $db->q($this->params->toString('JSON')))
			->where($db->qn('type') . ' = ' . $db->q('plugin'))
			->where($db->qn('folder') . ' = ' . $db->q('system'))
			->where($db->qn('element') . ' = ' . $db->q('userlogs'));

		try
		{
			// Lock the tables to prevent multiple plugin executions causing a race condition
			$db->lockTable('#__extensions');
		}
		catch (Exception $e)
		{
			// If we can't lock the tables it's too risky to continue execution
			return;
		}

		try
		{
			// Update the plugin parameters
			$result = $db->setQuery($query)->execute();

			$this->clearCacheGroups(array('com_plugins'), array(0, 1));
		}
		catch (Exception $exc)
		{
			// If we failed to execite
			$db->unlockTables();
			$result = false;
		}

		try
		{
			// Unlock the tables after writing
			$db->unlockTables();
		}
		catch (Exception $e)
		{
			// If we can't lock the tables assume we have somehow failed
			$result = false;
		}

		// Abort on failure
		if (!$result)
		{
			return;
		}

		$daysToDeleteAfter = (int) $this->params->get('logDeletePeriod', 0);

		if ($daysToDeleteAfter > 0)
		{
			$conditions = array($db->quoteName('log_date') . ' < DATE_SUB(NOW(), INTERVAL ' . $daysToDeleteAfter . ' DAY)');

			$query->clear()
				->delete($db->quoteName('#__user_logs'))->where($conditions);
			$db->setQuery($query);

			try
			{
				$db->execute();
			}
			catch (RuntimeException $e)
			{
				// Ignore it
				return;
			}
		}
	}

	/**
	 * Clears cache groups. We use it to clear the plugins cache after we update the last run timestamp.
	 *
	 * @param   array  $clearGroups   The cache groups to clean
	 * @param   array  $cacheClients  The cache clients (site, admin) to clean
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function clearCacheGroups(array $clearGroups, array $cacheClients = array(0, 1))
	{
		$conf = JFactory::getConfig();

		foreach ($clearGroups as $group)
		{
			foreach ($cacheClients as $client_id)
			{
				try
				{
					$options = array(
						'defaultgroup' => $group,
						'cachebase'    => $client_id ? JPATH_ADMINISTRATOR . '/cache' :
							$conf->get('cache_path', JPATH_SITE . '/cache')
					);

					$cache = JCache::getInstance('callback', $options);
					$cache->clean();
				}
				catch (Exception $e)
				{
					// Ignore it
				}
			}
		}
	}
}
