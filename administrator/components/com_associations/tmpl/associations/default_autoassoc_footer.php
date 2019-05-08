<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_content
 *
 * @copyright   Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

HTMLHelper::_('script', 'com_associations/admin-associations-default-autoassoc-footer.min.js', ['relative' => true, 'version' => 'auto']);
?>
<button class="btn btn-secondary" type="button" data-dismiss="modal">
	<?php echo Text::_('JCANCEL'); ?>
</button>
<button id='autoassoc-submit-button-id' class="btn btn-success" type="submit" data-submit-task='autoassoc.autocreate'>
	<?php echo Text::_('Create'); ?>
</button>
