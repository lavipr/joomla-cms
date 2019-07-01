<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_associations
 *
 * @copyright   Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Associations\Administrator\Helper;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Helper\ContentHistoryHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;

defined('_JEXEC') or die;

/**
 * Master Associations component helper.
 *
 * @since  4.0
 */
class MasterAssociationsHelper extends ContentHelper
{
	/**
	 * Method to create a link for a child item that has no master item
	 *
	 * @param   string  $globalMasterLang  The global master language
	 *
	 * @return  string  the link for the not associated master item
	 */
	public static function addNotAssociatedMasterLink($globalMasterLang)
	{
		$db    = Factory::getDbo();
		$query = $db->getQuery(true)
			->select('title, sef')
			->from('#__languages')
			->where($db->quoteName('lang_code') . ' = ' . $db->quote($globalMasterLang));
		$db->setQuery($query);
		$globalMasterLangInfos = $db->loadAssoc();

		$classes    = 'hasPopover badge badge-secondary';
		$masterInfo = '<br><br>' . Text::_('JGLOBAL_ASSOCIATIONS_MASTER_LANGUAGE');
		$text       = $globalMasterLangInfos['sef'] ? strtoupper($globalMasterLangInfos['sef']) : 'XX';
		$title      = Text::_('JGLOBAL_ASSOCIATIONS_STATE_NOT_ASSOCIATED_DESC');
		$url        = '';

		$tooltip = '<strong>' . htmlspecialchars( $globalMasterLangInfos['title'], ENT_QUOTES, 'UTF-8') . '</strong><br>'
			. htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . $masterInfo;

		$link = '<a href="' . $url . '" title="' .  $globalMasterLangInfos['title'] . '" class="' . $classes . '">' . $text . '</a>'
			. '<div role="tooltip" id="tip_no_Master">' . $tooltip . '</div>';

		return $link;
	}

	/**
	 * Method to get master dates of each item of an association.
	 *
	 * @param   array   $associations  the associations to be saved.
	 * @param   string  $context       the association context
	 *
	 * @return  array  association with master dates
	 *
	 */
	public static function getMasterDates($associations, $context)
	{
		$db = Factory::getDbo();

		foreach ($associations as $langCode => $id)
		{
			if (is_array($id))
			{
				$id = $id['id'];
			}

			$query = $db->getQuery(true)
				->select($db->quoteName('assocParams'))
				->from($db->quoteName('#__associations'))
				->where($db->quoteName('id') . ' = ' . $db->quote($id))
				->where($db->quoteName('context') . ' = ' . $db->quote($context));
			$db->setQuery($query);
			$masterDates[$id] = $db->loadResult();;
		}

		return $masterDates;
	}

	/**
	 * Method to get master_id and master_date for an association going to be saved.
	 *
	 * @param   integer  $id                Item id
	 * @param   integer  $dataId            Item id of an item that is going to be saved
	 * @param   integer  $masterId          Id of the associated master item
	 * @param   string   $masterModified    The latest modified date of the master
	 * @param   array    $assocMasterDates  Masters modified date of an associated item
	 * @param   string   $old_key           The old association key to check if it is a new association
	 *
	 * @return  array    master id and master dates for an associated item
	 */
	public static function getMasterValues($id, $dataId, $masterId, $masterModified, $assocMasterDates, $old_key) {

		if ($masterId)
		{
			// For the master item
			if ($masterId === $id)
			{
				$masterIdValue = 0;
				// set always the last modified date
				$masterDateValue = $masterModified ?? null;
			}

			// For the children
			else
			{
				$masterIdValue = $masterId;

				// If modified date isn't set to the child item, set current modified date from master.
				$masterDateValue = empty($assocMasterDates[$id])
					? $masterModified
					: $assocMasterDates[$id];

				if (!$old_key && ($dataId === $id))
				{
					// Add modified date from master to new associated item
					$masterDateValue = $masterModified ?? null;
				}
			}
		}
		else
		{
			// Default values when there is no associated master item.
			$masterIdValue   = -1;
			$masterDateValue = null;
		}

		return [(int) $masterIdValue, $masterDateValue];
	}

	/**
	 * Method to get the latest modified date of an master item
	 *
	 * @param   integer  $masterId   Id of the associated master item
	 * @param   string   $tableName  The name of the table.
	 * @param   string   $typeAlias  Alias for the content type
	 *
	 * @return  string   The modified date of the master item
	 */
	public static function getMasterModifiedDate($masterId, $tableName, $typeAlias)
	{
		// check if the content version is enabled
		$option = Factory::getApplication()->input->get('option');
		$saveHistory = ComponentHelper::getParams($option)->get('save_history', 0);

		if ($masterId)
		{
			// if versions are enabled get the save_data of the master item from history table
			if ($saveHistory)
			{
				$typeId        = Table::getInstance('ContentType')->getTypeId($typeAlias);
				$masterHistory = ContentHistoryHelper::getHistory($typeId, $masterId);

				// latest saved date of the master item
				$masterModified = $masterHistory[0]->save_date;
			}
			else
			{
				$db = Factory::getDbo();

				if ($tableName === '#__categories')
				{
					$modifiedColumn = 'modified_time';
				}
				else
				{
					$modifiedColumn = 'modified';
				}

				$masterDateQuery = $db->getQuery(true)
					->select($db->quoteName($modifiedColumn))
					->from($db->quoteName($tableName))
					->where($db->quoteName('id') . ' = ' . $db->quote($masterId));
				$db->setQuery($masterDateQuery);
				$masterModified = $db->loadResult();
			}
		}

		return $masterModified ?? '';
	}

	/**
	 * Method to set class name and information about the association state or the master item.
	 *
	 * @param   integer  $itemId            Item id
	 * @param   array    $items             The associated items for the item with the itemId
	 * @param   integer  $key               The current key from $items that is currently going through the foreach loop.
	 * @param   array    $item              The current value from $items that is currently going through the foreach loop.
	 * @param   boolean  $isMaster          If the item with $itemId is a master item.
	 * @param   integer  $masterId          Id of the associated master item.
	 * @param   array    $assocMasterDates  Master Dates of each associated item.
	 * @param   boolean  $saveHistory       If Versions are enabled or not.
	 *
	 * @return  array  the className and masterInfo for the association state
	 */
	public static function setMasterAndChildInfos($itemId, $items, $key, $item, $globalMasterLang, $isMaster, $masterId, $assocMasterDates, $saveHistory)
	{

		$addClass   = 'badge-success';
		$masterInfo = '';

		// Don't display other children if the current item is a child of the master language.
		if (($key !== $itemId) && ($globalMasterLang !== $item->lang_code) && !$isMaster)
		{
			unset($items[$key]);
		}

		if ($key === $masterId)
		{
			$addClass   .= ' master-item';
			$masterInfo  = '<br><br>' . Text::_('JGLOBAL_ASSOCIATIONS_MASTER_ITEM');
		}
		else
		{
			// get association state of child when a master exists
			if ($masterId && (array_key_exists($key, $assocMasterDates)) && (array_key_exists($masterId, $assocMasterDates)))
			{
				$associatedModifiedMaster = $assocMasterDates[$key];
				$lastModifiedMaster       = $assocMasterDates[$masterId];

				if ($associatedModifiedMaster < $lastModifiedMaster)
				{
					$addClass = 'badge-warning';
					$masterInfo = $saveHistory
						? '<br><br>' . Text::_('JGLOBAL_ASSOCIATIONS_STATE_OUTDATED_DESC')
						: '<br><br>' . Text::_('JGLOBAL_ASSOCIATIONS_STATE_MIGHT_BE_OUTDATED_DESC');
				}
				else
				{
					$addClass = 'badge-success';
					$masterInfo = '<br><br>' . Text::_('JGLOBAL_ASSOCIATIONS_STATE_UP_TO_DATE_DESC');
				}
			}
		}

		return [$addClass, $masterInfo];
	}
}
