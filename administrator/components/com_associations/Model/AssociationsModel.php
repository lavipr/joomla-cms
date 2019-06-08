<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_associations
 *
 * @copyright   Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Associations\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Table\Table;
use Joomla\Component\Associations\Administrator\Helper\AssociationsHelper;
use Joomla\Database\Exception\ExecutionFailureException;

/**
 * Methods supporting a list of article records.
 *
 * @since  3.7.0
 */
class AssociationsModel extends ListModel
{
	/**
	 * Override parent constructor.
	 *
	 * @param   array                $config   An optional associative array of configuration settings.
	 * @param   MVCFactoryInterface  $factory  The factory.
	 *
	 * @see     \Joomla\CMS\MVC\Model\BaseDatabaseModel
	 * @since   3.7
	 */
	public function __construct($config = array(), MVCFactoryInterface $factory = null)
	{
		if (empty($config['filter_fields']))
		{
			$config['filter_fields'] = array(
				'id',
				'title',
				'ordering',
				'itemtype',
				'language',
				'assocstate',
				'association',
				'menutype',
				'menutype_title',
				'level',
				'state',
				'category_id',
				'category_title',
				'access',
				'access_level',
			);
		}

		parent::__construct($config, $factory);
	}

	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @param   string  $ordering   An optional ordering field.
	 * @param   string  $direction  An optional direction (asc|desc).
	 *
	 * @return  void
	 *
	 * @since   3.7.0
	 */
	protected function populateState($ordering = 'ordering', $direction = 'asc')
	{
		$app = Factory::getApplication();

		$forcedLanguage = $app->input->get('forcedLanguage', '', 'cmd');
		$forcedItemType = $app->input->get('forcedItemType', '', 'string');

		// Set language select box to default site language or if set to the master language as default.
		$globalMasterLanguage = Associations::getGlobalMasterLanguage();
		$defaultLanguage      = !empty($globalMasterLanguage) ? $globalMasterLanguage : ComponentHelper::getParams('com_languages')->get('site');
		$defaultItemType      = 'com_content.article';

		// Adjust the context to support modal layouts.
		if ($layout = $app->input->get('layout'))
		{
			$this->context .= '.' . $layout;
		}

		// Adjust the context to support forced languages.
		if ($forcedLanguage)
		{
			$this->context .= '.' . $forcedLanguage;
		}

		// Adjust the context to support forced component item types.
		if ($forcedItemType)
		{
			$this->context .= '.' . $forcedItemType;
		}

		$this->setState('itemtype', $this->getUserStateFromRequest($this->context . '.itemtype', 'itemtype', $defaultItemType, 'string'));
		$this->setState('language', $this->getUserStateFromRequest($this->context . '.language', 'language', $defaultLanguage, 'string'));
		$this->setState('assocstate', $this->getUserStateFromRequest($this->context . '.assocstate', 'assocstate', '', 'string'));
		$this->setState('filter.search', $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string'));
		$this->setState('filter.state', $this->getUserStateFromRequest($this->context . '.filter.state', 'filter_state', '', 'cmd'));
		$this->setState('filter.category_id', $this->getUserStateFromRequest($this->context . '.filter.category_id', 'filter_category_id', '', 'cmd'));
		$this->setState('filter.menutype', $this->getUserStateFromRequest($this->context . '.filter.menutype', 'filter_menutype', '', 'string'));
		$this->setState('filter.access', $this->getUserStateFromRequest($this->context . '.filter.access', 'filter_access', '', 'string'));
		$this->setState('filter.level', $this->getUserStateFromRequest($this->context . '.filter.level', 'filter_level', '', 'cmd'));

		// List state information.
		parent::populateState($ordering, $direction);

		// Force a language.
		if (!empty($forcedLanguage))
		{
			$this->setState('language', $forcedLanguage);
		}

		// Force a component item type.
		if (!empty($forcedItemType))
		{
			$this->setState('itemtype', $forcedItemType);
		}
	}

	/**
	 * Method to get a store id based on model configuration state.
	 *
	 * This is necessary because the model is used by the component and
	 * different modules that might need different sets of data or different
	 * ordering requirements.
	 *
	 * @param   string  $id  A prefix for the store id.
	 *
	 * @return  string  A store id.
	 *
	 * @since  3.7.0
	 */
	protected function getStoreId($id = '')
	{
		// Compile the store id.
		$id .= ':' . $this->getState('itemtype');
		$id .= ':' . $this->getState('language');
		$id .= ':' . $this->getState('assocstate');
		$id .= ':' . $this->getState('filter.search');
		$id .= ':' . $this->getState('filter.state');
		$id .= ':' . $this->getState('filter.category_id');
		$id .= ':' . $this->getState('filter.menutype');
		$id .= ':' . $this->getState('filter.access');
		$id .= ':' . $this->getState('filter.level');

		return parent::getStoreId($id);
	}

	/**
	 * Build an SQL query to load the list data.
	 *
	 * @return  \JDatabaseQuery|boolean
	 *
	 * @since  3.7.0
	 */
	protected function getListQuery()
	{
		$type         = null;

		list($extensionName, $typeName) = explode('.', $this->state->get('itemtype'), 2);

		$extension = AssociationsHelper::getSupportedExtension($extensionName);
		$types     = $extension->get('types');

		if (array_key_exists($typeName, $types))
		{
			$type = $types[$typeName];
		}

		if (is_null($type))
		{
			return false;
		}

		// Create a new query object.
		$user     = Factory::getUser();
		$db       = $this->getDbo();
		$query    = $db->getQuery(true);

		$details = $type->get('details');

		if (!array_key_exists('support', $details))
		{
			return false;
		}

		$support = $details['support'];

		if (!array_key_exists('fields', $details))
		{
			return false;
		}

		$fields = $details['fields'];

		// Main query.
		$query->select($db->quoteName($fields['id'], 'id'))
			->select($db->quoteName($fields['title'], 'title'))
			->select($db->quoteName($fields['alias'], 'alias'));

		if (!array_key_exists('tables', $details))
		{
			return false;
		}

		$tables = $details['tables'];

		foreach ($tables as $key => $table)
		{
			$query->from($db->quoteName($table, $key));
		}

		if (!array_key_exists('joins', $details))
		{
			return false;
		}

		$joins = $details['joins'];

		foreach ($joins as $join)
		{
			$query->join($join['type'], $db->quoteName($join['condition']));
		}

		// Join over the language.
		$query->select($db->quoteName($fields['language'], 'language'))
			->select($db->quoteName('l.title', 'language_title'))
			->select($db->quoteName('l.image', 'language_image'))
			->join('LEFT', $db->quoteName('#__languages', 'l')
				. ' ON ' . $db->quoteName('l.lang_code') . ' = ' . $db->quoteName($fields['language'])
			);

		// Join over the associations.
		$query->select('COUNT(' . $db->quoteName('asso2.id') . ') > 1 AS ' . $db->quoteName('association'))
			->join(
				'LEFT',
				$db->quoteName('#__associations', 'asso') . ' ON ' . $db->quoteName('asso.id') . ' = ' . $db->quoteName($fields['id'])
				. ' AND ' . $db->quoteName('asso.context') . ' = ' . $db->quote($extensionName . '.item')
			)
			->join('LEFT', $db->quoteName('#__associations', 'asso2') . ' ON ' . $db->quoteName('asso2.key') . ' = ' . $db->quoteName('asso.key'));

		// Prepare the group by clause.
		$groupby = array(
			$fields['id'],
			$fields['title'],
			$fields['alias'],
			$fields['language'],
			'l.title',
			'l.image',
		);

		// Select author for ACL checks.
		if (!empty($fields['created_user_id']))
		{
			$query->select($db->quoteName($fields['created_user_id'], 'created_user_id'));

			$groupby[] = $fields['created_user_id'];
		}

		// Select checked out data for check in checkins.
		if (!empty($fields['checked_out']) && !empty($fields['checked_out_time']))
		{
			$query->select($db->quoteName($fields['checked_out'], 'checked_out'))
				->select($db->quoteName($fields['checked_out_time'], 'checked_out_time'));

			// Join over the users.
			$query->select($db->quoteName('u.name', 'editor'))
				->join('LEFT', $db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName($fields['checked_out']));

			$groupby[] = 'u.name';
			$groupby[] = $fields['checked_out'];
			$groupby[] = $fields['checked_out_time'];
		}

		// If component item type supports ordering, select the ordering also.
		if (!empty($fields['ordering']))
		{
			$query->select($db->quoteName($fields['ordering'], 'ordering'));

			$groupby[] = $fields['ordering'];
		}

		// If component item type supports state, select the item state also.
		if (!empty($fields['state']))
		{
			$query->select($db->quoteName($fields['state'], 'state'));

			$groupby[] = $fields['state'];
		}

		// If component item type supports level, select the level also.
		if (!empty($fields['level']))
		{
			$query->select($db->quoteName($fields['level'], 'level'));

			$groupby[] = $fields['level'];
		}

		// If component item type supports categories, select the category also.
		if (!empty($fields['catid']))
		{
			$query->select($db->quoteName($fields['catid'], 'catid'));

			// Join over the categories.
			$query->select($db->quoteName('c.title', 'category_title'))
				->join('LEFT', $db->quoteName('#__categories', 'c') . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName($fields['catid']));

			$groupby[] = 'c.title';
			$groupby[] = $fields['catid'];
		}

		// If component item type supports menu type, select the menu type also.
		if (!empty($fields['menutype']))
		{
			$query->select($db->quoteName($fields['menutype'], 'menutype'));

			// Join over the menu types.
			$query->select($db->quoteName('mt.title', 'menutype_title'))
				->select($db->quoteName('mt.id', 'menutypeid'))
				->join('LEFT', $db->quoteName('#__menu_types', 'mt')
					. ' ON ' . $db->quoteName('mt.menutype') . ' = ' . $db->quoteName($fields['menutype'])
				);

			$groupby[] = 'mt.title';
			$groupby[] = 'mt.id';
			$groupby[] = $fields['menutype'];
		}

		// If component item type supports access level, select the access level also.
		if (array_key_exists('acl', $support) && $support['acl'] == true && !empty($fields['access']))
		{
			$query->select($db->quoteName($fields['access'], 'access'));

			// Join over the access levels.
			$query->select($db->quoteName('ag.title', 'access_level'))
				->join('LEFT', $db->quoteName('#__viewlevels', 'ag') . ' ON ' . $db->quoteName('ag.id') . ' = ' . $db->quoteName($fields['access']));

			$groupby[] = 'ag.title';
			$groupby[] = $fields['access'];

			// Implement View Level Access.
			if (!$user->authorise('core.admin', $extensionName))
			{
				$query->where($fields['access'] . ' IN (' . implode(',', $user->getAuthorisedViewLevels()) . ')');
			}
		}

		// If component item type is menus we need to remove the root item and the administrator menu.
		if ($extensionName === 'com_menus')
		{
			$query->where($db->quoteName($fields['id']) . ' > 1')
				->where($db->quoteName('a.client_id') . ' = 0');
		}

		// If component item type is category we need to remove all other component categories.
		if ($typeName === 'category')
		{
			$query->where($db->quoteName('a.extension') . ' = ' . $db->quote($extensionName));
		}
		elseif ($typeNameExploded = explode('.', $typeName))
		{
			if (count($typeNameExploded) > 1 && array_pop($typeNameExploded) === 'category')
			{
				$section = implode('.', $typeNameExploded);
				$query->where($db->quoteName('a.extension') . ' = ' . $db->quote($extensionName . '.' . $section));
			}
		}

		// Filter on the language.
		if ($language = $this->getState('language'))
		{
			$query->where($db->quoteName($fields['language']) . ' = ' . $db->quote($language));
		}

		// Filter by item state.
		$state = $this->getState('filter.state');

		if (is_numeric($state))
		{
			$query->where($db->quoteName($fields['state']) . ' = ' . (int) $state);
		}
		elseif ($state === '')
		{
			$query->where($db->quoteName($fields['state']) . ' IN (0, 1)');
		}

		// Filter on the category.
		$baselevel = 1;

		if ($categoryId = $this->getState('filter.category_id'))
		{
			$categoryTable = Table::getInstance('Category', 'JTable');
			$categoryTable->load($categoryId);
			$baselevel = (int) $categoryTable->level;

			$query->where($db->quoteName('c.lft') . ' >= ' . (int) $categoryTable->lft)
				->where($db->quoteName('c.rgt') . ' <= ' . (int) $categoryTable->rgt);
		}

		// Filter on the level.
		if ($level = $this->getState('filter.level'))
		{
			$query->where($db->quoteName('a.level') . ' <= ' . ((int) $level + (int) $baselevel - 1));
		}

		// Filter by menu type.
		if ($menutype = $this->getState('filter.menutype'))
		{
			$query->where($fields['menutype'] . ' = ' . $db->quote($menutype));
		}

		// Filter by access level.
		if ($access = $this->getState('filter.access'))
		{
			$query->where($fields['access'] . ' = ' . (int) $access);
		}

		// Filter by search in name.
		if ($search = $this->getState('filter.search'))
		{
			if (stripos($search, 'id:') === 0)
			{
				$query->where($db->quoteName($fields['id']) . ' = ' . (int) substr($search, 3));
			}
			else
			{
				$search = $db->quote('%' . str_replace(' ', '%', $db->escape(trim($search), true) . '%'));
				$query->where('(' . $db->quoteName($fields['title']) . ' LIKE ' . $search
					. ' OR ' . $db->quoteName($fields['alias']) . ' LIKE ' . $search . ')'
				);
			}
		}

		// Add the group by clause
		$query->group($db->quoteName($groupby));

		// Add the list ordering clause
		$listOrdering  = $this->state->get('list.ordering', 'id');
		$orderDirn     = $this->state->get('list.direction', 'ASC');

		$query->order($db->escape($listOrdering) . ' ' . $db->escape($orderDirn));

		return $query;
	}

	/**
	 * Delete associations from #__associations table.
	 *
	 * @param   string  $context  The associations context. Empty for all.
	 * @param   string  $key      The associations key. Empty for all.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since  3.7.0
	 */
	public function purge($context = '', $key = '')
	{
		$app   = Factory::getApplication();
		$db    = $this->getDbo();
		$query = $db->getQuery(true)->delete($db->quoteName('#__associations'));

		// Filter by associations context.
		if ($context)
		{
			list($extensionName, $typeName) = explode('.', $context, 2);

			// The associations of the type category may only be deleted with the appropriate context,
			// therefore with the appropriate extension. Not all categories.
			if ($typeName === 'category')
			{
				// Subquery: Search for category-items with the given context
				$subQuery = $db->getQuery(true)
					->select($db->quoteName('id'))
					->from($db->quoteName('#__categories'))
					->where($db->quoteName('extension') . ' = ' . $db->quote($extensionName));

				// Delete associations of categories with the given context by comparing id of both tables
				$query->where($db->quoteName('id') . ' IN (' . $subQuery . ')')
					->where($db->quoteName('context') . ' = ' . $db->quote('com_categories.item'));
			}
			else
			{
				$query->where($db->quoteName('context') . ' = ' . $db->quote($extensionName . '.item'));
			}
		}

		// Filter by key.
		if ($key)
		{
			$query->where($db->quoteName('key') . ' = ' . $db->quote($key));
		}

		$db->setQuery($query);

		try
		{
			$db->execute();
		}
		catch (ExecutionFailureException $e)
		{
			$app->enqueueMessage(Text::_('COM_ASSOCIATIONS_PURGE_FAILED'), 'error');

			return false;
		}

		$app->enqueueMessage(
			Text::_((int) $db->getAffectedRows() > 0 ? 'COM_ASSOCIATIONS_PURGE_SUCCESS' : 'COM_ASSOCIATIONS_PURGE_NONE'),
			'message'
		);

		return true;
	}

	/**
	 * Delete orphans from the #__associations table.
	 *
	 * @param   string  $context  The associations context. Empty for all.
	 * @param   string  $key      The associations key. Empty for all.
	 *
	 * @return  boolean  True on success
	 *
	 * @since  3.7.0
	 */
	public function clean($context = '', $key = '')
	{
		$app   = Factory::getApplication();
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName('key') . ', COUNT(*)')
			->from($db->quoteName('#__associations'))
			->group($db->quoteName('key'))
			->having('COUNT(*) = 1');

		// Filter by associations context.
		if ($context)
		{
			$query->where($db->quoteName('context') . ' = ' . $db->quote($context));
		}

		// Filter by key.
		if ($key)
		{
			$query->where($db->quoteName('key') . ' = ' . $db->quote($key));
		}

		$db->setQuery($query);

		$assocKeys = $db->loadObjectList();

		$count = 0;

		// We have orphans. Let's delete them.
		foreach ($assocKeys as $value)
		{
			$query->clear()
				->delete($db->quoteName('#__associations'))
				->where($db->quoteName('key') . ' = ' . $db->quote($value->key));

			$db->setQuery($query);

			try
			{
				$db->execute();
			}
			catch (ExecutionFailureException $e)
			{
				$app->enqueueMessage(Text::_('COM_ASSOCIATIONS_DELETE_ORPHANS_FAILED'), 'error');

				return false;
			}

			$count += (int) $db->getAffectedRows();
		}

		$app->enqueueMessage(
			Text::_($count > 0 ? 'COM_ASSOCIATIONS_DELETE_ORPHANS_SUCCESS' : 'COM_ASSOCIATIONS_DELETE_ORPHANS_NONE'),
			'message'
		);

		return true;
	}
}
