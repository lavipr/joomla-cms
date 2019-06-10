<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.languagefilter
 *
 * @copyright   Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Association\AssociationServiceInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\BeforeExecuteEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Router\Router;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Menus\Administrator\Helper\MenusHelper;
use Joomla\Registry\Registry;
use Joomla\String\StringHelper;

/**
 * Joomla! Language Filter Plugin.
 *
 * @since  1.6
 */
class PlgSystemLanguageFilter extends CMSPlugin
{
	/**
	 * The routing mode.
	 *
	 * @var    boolean
	 * @since  2.5
	 */
	protected $mode_sef;

	/**
	 * Available languages by sef.
	 *
	 * @var    array
	 * @since  1.6
	 */
	protected $sefs;

	/**
	 * Available languages by language codes.
	 *
	 * @var    array
	 * @since  2.5
	 */
	protected $lang_codes;

	/**
	 * The current language code.
	 *
	 * @var    string
	 * @since  3.4.2
	 */
	protected $current_lang;

	/**
	 * The default language code.
	 *
	 * @var    string
	 * @since  2.5
	 */
	protected $default_lang;

	/**
	 * The logged user language code.
	 *
	 * @var    string
	 * @since  3.3.1
	 */
	private $user_lang_code;

	/**
	 * Application object.
	 *
	 * @var    JApplicationCms
	 * @since  3.3
	 */
	protected $app;

	/**
	 * Constructor.
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array    $config    An optional associative array of configuration settings.
	 *
	 * @since   1.6
	 */
	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);

		$this->app = Factory::getApplication();

		// Setup language data.
		$this->mode_sef     = $this->app->get('sef', 0);
		$this->sefs         = LanguageHelper::getLanguages('sef');
		$this->lang_codes   = LanguageHelper::getLanguages('lang_code');
		$this->default_lang = ComponentHelper::getParams('com_languages')->get('site', 'en-GB');

		// If language filter plugin is executed in a site page.
		if ($this->app->isClient('site'))
		{
			$levels = Factory::getUser()->getAuthorisedViewLevels();

			foreach ($this->sefs as $sef => $language)
			{
				// @todo: In Joomla 2.5.4 and earlier access wasn't set. Non modified Content Languages got 0 as access value
				// we also check if frontend language exists and is enabled
				if (($language->access && !in_array($language->access, $levels))
					|| (!array_key_exists($language->lang_code, LanguageHelper::getInstalledLanguages(0))))
				{
					unset($this->lang_codes[$language->lang_code], $this->sefs[$language->sef]);
				}
			}
		}
		// If language filter plugin is executed in an admin page (ex: JRoute site).
		else
		{
			// Set current language to default site language, fallback to en-GB if there is no content language for the default site language.
			$this->current_lang = isset($this->lang_codes[$this->default_lang]) ? $this->default_lang : 'en-GB';

			foreach ($this->sefs as $sef => $language)
			{
				if (!array_key_exists($language->lang_code, LanguageHelper::getInstalledLanguages(0)))
				{
					unset($this->lang_codes[$language->lang_code]);
					unset($this->sefs[$language->sef]);
				}
			}
		}
	}

	/**
	 * After initialise.
	 *
	 * @return  void
	 *
	 * @since   1.6
	 */
	public function onAfterInitialise()
	{
		$this->app->item_associations = $this->params->get('item_associations', 0);

		// We need to make sure we are always using the site router, even if the language plugin is executed in admin app.
		$router = CMSApplication::getInstance('site')->getRouter('site');

		// Attach build rules for language SEF.
		$router->attachBuildRule(array($this, 'preprocessBuildRule'), Router::PROCESS_BEFORE);
		$router->attachBuildRule(array($this, 'buildRule'), Router::PROCESS_BEFORE);

		if ($this->mode_sef)
		{
			$router->attachBuildRule(array($this, 'postprocessSEFBuildRule'), Router::PROCESS_AFTER);
		}
		else
		{
			$router->attachBuildRule(array($this, 'postprocessNonSEFBuildRule'), Router::PROCESS_AFTER);
		}

		// Attach parse rule.
		$router->attachParseRule(array($this, 'parseRule'), Router::PROCESS_BEFORE);
	}

	/**
	 * After route.
	 *
	 * @return  void
	 *
	 * @since   3.4
	 */
	public function onAfterRoute()
	{
		// Add custom site name.
		if ($this->app->isClient('site') && isset($this->lang_codes[$this->current_lang]) && $this->lang_codes[$this->current_lang]->sitename)
		{
			$this->app->set('sitename', $this->lang_codes[$this->current_lang]->sitename);
		}
	}

	/**
	 * Listener for the onBeforeExecute event
	 *
	 * @param   BeforeExecuteEvent  $event  The Event object
	 *
	 * @return  void
	 *
	 * @since   4.0
	 */
	public function onBeforeExecute(BeforeExecuteEvent $event)
	{
		/** @var \Joomla\CMS\Application\SiteApplication $app */
		$app = $event->getApplication();

		if (!$app->isClient('site'))
		{
			return;
		}

		// If a language was specified it has priority, otherwise use user or default language settings
		$app->setLanguageFilter(true);
		$app->setDetectBrowser($this->params->get('detect_browser', '1') == '1');
	}

	/**
	 * Add build preprocess rule to router.
	 *
	 * @param   JRouter  &$router  JRouter object.
	 * @param   JUri     &$uri     JUri object.
	 *
	 * @return  void
	 *
	 * @since   3.4
	 */
	public function preprocessBuildRule(&$router, &$uri)
	{
		$lang = $uri->getVar('lang', $this->current_lang);
		$uri->setVar('lang', $lang);

		if (isset($this->sefs[$lang]))
		{
			$lang = $this->sefs[$lang]->lang_code;
			$uri->setVar('lang', $lang);
		}
	}

	/**
	 * Add build rule to router.
	 *
	 * @param   JRouter  &$router  JRouter object.
	 * @param   JUri     &$uri     JUri object.
	 *
	 * @return  void
	 *
	 * @since   1.6
	 */
	public function buildRule(&$router, &$uri)
	{
		$lang = $uri->getVar('lang');

		if (isset($this->lang_codes[$lang]))
		{
			$sef = $this->lang_codes[$lang]->sef;
		}
		else
		{
			$sef = $this->lang_codes[$this->current_lang]->sef;
		}

		if ($this->mode_sef
			&& (!$this->params->get('remove_default_prefix', 0)
			|| $lang !== $this->default_lang
			|| $lang !== $this->current_lang))
		{
			$uri->setPath($uri->getPath() . '/' . $sef . '/');
		}
	}

	/**
	 * postprocess build rule for SEF URLs
	 *
	 * @param   JRouter  &$router  JRouter object.
	 * @param   JUri     &$uri     JUri object.
	 *
	 * @return  void
	 *
	 * @since   3.4
	 */
	public function postprocessSEFBuildRule(&$router, &$uri)
	{
		$uri->delVar('lang');
	}

	/**
	 * postprocess build rule for non-SEF URLs
	 *
	 * @param   JRouter  &$router  JRouter object.
	 * @param   JUri     &$uri     JUri object.
	 *
	 * @return  void
	 *
	 * @since   3.4
	 */
	public function postprocessNonSEFBuildRule(&$router, &$uri)
	{
		$lang = $uri->getVar('lang');

		if (isset($this->lang_codes[$lang]))
		{
			$uri->setVar('lang', $this->lang_codes[$lang]->sef);
		}
	}

	/**
	 * Add parse rule to router.
	 *
	 * @param   JRouter  &$router  JRouter object.
	 * @param   JUri     &$uri     JUri object.
	 *
	 * @return  void
	 *
	 * @since   1.6
	 */
	public function parseRule(&$router, &$uri)
	{
		// Did we find the current and existing language yet?
		$found = false;

		// Are we in SEF mode or not?
		if ($this->mode_sef)
		{
			$path = $uri->getPath();
			$parts = explode('/', $path);

			$sef = $parts[0];

			// Do we have a URL Language Code ?
			if (!isset($this->sefs[$sef]))
			{
				// Check if remove default URL language code is set
				if ($this->params->get('remove_default_prefix', 0))
				{
					if ($parts[0])
					{
						// We load a default site language page
						$lang_code = $this->default_lang;
					}
					else
					{
						// We check for an existing language cookie
						$lang_code = $this->getLanguageCookie();
					}
				}
				else
				{
					$lang_code = $this->getLanguageCookie();
				}

				// No language code. Try using browser settings or default site language
				if (!$lang_code && $this->params->get('detect_browser', 0) == 1)
				{
					$lang_code = LanguageHelper::detectLanguage();
				}

				if (!$lang_code)
				{
					$lang_code = $this->default_lang;
				}

				if ($lang_code === $this->default_lang && $this->params->get('remove_default_prefix', 0))
				{
					$found = true;
				}
			}
			else
			{
				// We found our language
				$found = true;
				$lang_code = $this->sefs[$sef]->lang_code;

				// If we found our language, but its the default language and we don't want a prefix for that, we are on a wrong URL.
				// Or we try to change the language back to the default language. We need a redirect to the proper URL for the default language.
				if ($lang_code === $this->default_lang && $this->params->get('remove_default_prefix', 0))
				{
					// Create a cookie.
					$this->setLanguageCookie($lang_code);

					$found = false;
					array_shift($parts);
					$path = implode('/', $parts);
				}

				// We have found our language and the first part of our URL is the language prefix
				if ($found)
				{
					array_shift($parts);

					// Empty parts array when "index.php" is the only part left.
					if (count($parts) === 1 && $parts[0] === 'index.php')
					{
						$parts = array();
					}

					$uri->setPath(implode('/', $parts));
				}
			}
		}
		// We are not in SEF mode
		else
		{
			$lang_code = $this->getLanguageCookie();

			if (!$lang_code && $this->params->get('detect_browser', 1))
			{
				$lang_code = LanguageHelper::detectLanguage();
			}

			if (!isset($this->lang_codes[$lang_code]))
			{
				$lang_code = $this->default_lang;
			}
		}

		$lang = $uri->getVar('lang', $lang_code);

		if (isset($this->sefs[$lang]))
		{
			// We found our language
			$found = true;
			$lang_code = $this->sefs[$lang]->lang_code;
		}

		// We are called via POST or the nolangfilter url parameter was set. We don't care about the language
		// and simply set the default language as our current language.
		if ($this->app->input->getMethod() === 'POST'
			|| $this->app->input->get('nolangfilter', 0) == 1
			|| count($this->app->input->post) > 0
			|| count($this->app->input->files) > 0)
		{
			$found = true;

			if (!isset($lang_code))
			{
				$lang_code = $this->getLanguageCookie();
			}

			if (!$lang_code && $this->params->get('detect_browser', 1))
			{
				$lang_code = LanguageHelper::detectLanguage();
			}

			if (!isset($this->lang_codes[$lang_code]))
			{
				$lang_code = $this->default_lang;
			}
		}

		// We have not found the language and thus need to redirect
		if (!$found)
		{
			// Lets find the default language for this user
			if (!isset($lang_code) || !isset($this->lang_codes[$lang_code]))
			{
				$lang_code = false;

				if ($this->params->get('detect_browser', 1))
				{
					$lang_code = LanguageHelper::detectLanguage();

					if (!isset($this->lang_codes[$lang_code]))
					{
						$lang_code = false;
					}
				}

				if (!$lang_code)
				{
					$lang_code = $this->default_lang;
				}
			}

			if ($this->mode_sef)
			{
				// Use the current language sef or the default one.
				if ($lang_code !== $this->default_lang
					|| !$this->params->get('remove_default_prefix', 0))
				{
					$path = $this->lang_codes[$lang_code]->sef . '/' . $path;
				}

				$uri->setPath($path);

				if (!$this->app->get('sef_rewrite'))
				{
					$uri->setPath('index.php/' . $uri->getPath());
				}

				$redirectUri = $uri->base() . $uri->toString(array('path', 'query', 'fragment'));
			}
			else
			{
				$uri->setVar('lang', $this->lang_codes[$lang_code]->sef);
				$redirectUri = $uri->base() . 'index.php?' . $uri->getQuery();
			}

			// Set redirect HTTP code to "302 Found".
			$redirectHttpCode = 302;

			// If selected language is the default language redirect code is "301 Moved Permanently".
			if ($lang_code === $this->default_lang)
			{
				$redirectHttpCode = 301;

				// We cannot cache this redirect in browser. 301 is cachable by default so we need to force to not cache it in browsers.
				$this->app->setHeader('Expires', 'Wed, 17 Aug 2005 00:00:00 GMT', true);
				$this->app->setHeader('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT', true);
				$this->app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0', false);
				$this->app->setHeader('Pragma', 'no-cache');
				$this->app->sendHeaders();
			}

			// Redirect to language.
			$this->app->redirect($redirectUri, $redirectHttpCode);
		}

		// We have found our language and now need to set the cookie and the language value in our system
		$array = array('lang' => $lang_code);
		$this->current_lang = $lang_code;

		// Set the request var.
		$this->app->input->set('language', $lang_code);
		$this->app->set('language', $lang_code);
		$language = Factory::getLanguage();

		if ($language->getTag() !== $lang_code)
		{
			$language_new = Language::getInstance($lang_code);

			foreach ($language->getPaths() as $extension => $files)
			{
				if (strpos($extension, 'plg_system') !== false)
				{
					$extension_name = substr($extension, 11);

					$language_new->load($extension, JPATH_ADMINISTRATOR)
					|| $language_new->load($extension, JPATH_PLUGINS . '/system/' . $extension_name);

					continue;
				}

				$language_new->load($extension);
			}

			Factory::$language = $language_new;
			$this->app->loadLanguage($language_new);
		}

		// Create a cookie.
		if ($this->getLanguageCookie() !== $lang_code)
		{
			$this->setLanguageCookie($lang_code);
		}

		return $array;
	}

	/**
	 * Reports the privacy related capabilities for this plugin to site administrators.
	 *
	 * @return  array
	 *
	 * @since   3.9.0
	 */
	public function onPrivacyCollectAdminCapabilities()
	{
		$this->loadLanguage();

		return array(
			Text::_('PLG_SYSTEM_LANGUAGEFILTER') => array(
				Text::_('PLG_SYSTEM_LANGUAGEFILTER_PRIVACY_CAPABILITY_LANGUAGE_COOKIE'),
			)
		);
	}

	/**
	 * Before store user method.
	 *
	 * Method is called before user data is stored in the database.
	 *
	 * @param   array    $user   Holds the old user data.
	 * @param   boolean  $isnew  True if a new user is stored.
	 * @param   array    $new    Holds the new user data.
	 *
	 * @return  void
	 *
	 * @since   1.6
	 */
	public function onUserBeforeSave($user, $isnew, $new)
	{
		if (array_key_exists('params', $user) && $this->params->get('automatic_change', 1) == 1)
		{
			$registry = new Registry($user['params']);
			$this->user_lang_code = $registry->get('language');

			if (empty($this->user_lang_code))
			{
				$this->user_lang_code = $this->current_lang;
			}
		}
	}

	/**
	 * After store user method.
	 *
	 * Method is called after user data is stored in the database.
	 *
	 * @param   array    $user     Holds the new user data.
	 * @param   boolean  $isnew    True if a new user is stored.
	 * @param   boolean  $success  True if user was successfully stored in the database.
	 * @param   string   $msg      Message.
	 *
	 * @return  void
	 *
	 * @since   1.6
	 */
	public function onUserAfterSave($user, $isnew, $success, $msg)
	{
		if ($success && array_key_exists('params', $user) && $this->params->get('automatic_change', 1) == 1)
		{
			$registry = new Registry($user['params']);
			$lang_code = $registry->get('language');

			if (empty($lang_code))
			{
				$lang_code = $this->current_lang;
			}

			if ($lang_code === $this->user_lang_code || !isset($this->lang_codes[$lang_code]))
			{
				if ($this->app->isClient('site'))
				{
					$this->app->setUserState('com_users.edit.profile.redirect', null);
				}
			}
			else
			{
				if ($this->app->isClient('site'))
				{
					$this->app->setUserState('com_users.edit.profile.redirect', 'index.php?Itemid='
						. $this->app->getMenu()->getDefault($lang_code)->id . '&lang=' . $this->lang_codes[$lang_code]->sef
					);

					// Create a cookie.
					$this->setLanguageCookie($lang_code);
				}
			}
		}
	}

	/**
	 * Method to handle any login logic and report back to the subject.
	 *
	 * @param   array  $user     Holds the user data.
	 * @param   array  $options  Array holding options (remember, autoregister, group).
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   1.5
	 */
	public function onUserLogin($user, $options = array())
	{
		$menu = $this->app->getMenu();

		if ($this->app->isClient('site'))
		{
			if ($this->params->get('automatic_change', 1))
			{
				$assoc = Associations::isEnabled();
				$lang_code = $user['language'];

				// If no language is specified for this user, we set it to the site default language
				if (empty($lang_code))
				{
					$lang_code = $this->default_lang;
				}

				// The language has been deleted/disabled or the related content language does not exist/has been unpublished
				// or the related home page does not exist/has been unpublished
				if (!array_key_exists($lang_code, $this->lang_codes)
					|| !array_key_exists($lang_code, Multilanguage::getSiteHomePages())
					|| !Folder::exists(JPATH_SITE . '/language/' . $lang_code))
				{
					$lang_code = $this->current_lang;
				}

				// Try to get association from the current active menu item
				$active = $menu->getActive();

				$foundAssociation = false;

				/**
				 * Looking for associations.
				 * If the login menu item form contains an internal URL redirection,
				 * This will override the automatic change to the user preferred site language.
				 * In that case we use the redirect as defined in the menu item.
				 *  Otherwise we redirect, when available, to the user preferred site language.
				 */
				if ($active && !$active->params['login_redirect_url'])
				{
					if ($assoc)
					{
						$associations = MenusHelper::getAssociations($active->id);
					}

					// Retrieves the Itemid from a login form.
					$uri = new Uri($this->app->getUserState('users.login.form.return'));

					if ($uri->getVar('Itemid'))
					{
						// The login form contains a menu item redirection. Try to get associations from that menu item.
						// If any association set to the user preferred site language, redirect to that page.
						if ($assoc)
						{
							$associations = MenusHelper::getAssociations($uri->getVar('Itemid'));
						}

						if (isset($associations[$lang_code]) && $menu->getItem($associations[$lang_code]))
						{
							$associationItemid = $associations[$lang_code];
							$this->app->setUserState('users.login.form.return', 'index.php?Itemid=' . $associationItemid);
							$foundAssociation = true;
						}
					}
					elseif (isset($associations[$lang_code]) && $menu->getItem($associations[$lang_code]))
					{
						/**
						 * The login form does not contain a menu item redirection.
						 * The active menu item has associations.
						 * We redirect to the user preferred site language associated page.
						 */
						$associationItemid = $associations[$lang_code];
						$this->app->setUserState('users.login.form.return', 'index.php?Itemid=' . $associationItemid);
						$foundAssociation = true;
					}
					elseif ($active->home)
					{
						// We are on a Home page, we redirect to the user preferred site language Home page.
						$item = $menu->getDefault($lang_code);

						if ($item && $item->language !== $active->language && $item->language !== '*')
						{
							$this->app->setUserState('users.login.form.return', 'index.php?Itemid=' . $item->id);
							$foundAssociation = true;
						}
					}
				}

				if ($foundAssociation && $lang_code !== $this->current_lang)
				{
					// Change language.
					$this->current_lang = $lang_code;

					// Create a cookie.
					$this->setLanguageCookie($lang_code);

					// Change the language code.
					Factory::getContainer()->get(\Joomla\CMS\Language\LanguageFactoryInterface::class)->createLanguage($lang_code);
				}
			}
			else
			{
				if ($this->app->getUserState('users.login.form.return'))
				{
					$this->app->setUserState('users.login.form.return', Route::_($this->app->getUserState('users.login.form.return'), false));
				}
			}
		}
	}

	/**
	 * Method to add alternative meta tags for associated menu items.
	 *
	 * @return  void
	 *
	 * @since   1.7
	 */
	public function onAfterDispatch()
	{
		$doc = Factory::getDocument();

		if ($this->app->isClient('site') && $this->params->get('alternate_meta', 1) && $doc->getType() === 'html')
		{
			$languages             = $this->lang_codes;
			$homes                 = Multilanguage::getSiteHomePages();
			$menu                  = $this->app->getMenu();
			$active                = $menu->getActive();
			$levels                = Factory::getUser()->getAuthorisedViewLevels();
			$remove_default_prefix = $this->params->get('remove_default_prefix', 0);
			$server                = Uri::getInstance()->toString(array('scheme', 'host', 'port'));
			$is_home               = false;
			$currentInternalUrl    = 'index.php?' . http_build_query($this->app->getRouter()->getVars());

			if ($active)
			{
				$active_link  = Route::_($active->link . '&Itemid=' . $active->id);
				$current_link = Route::_($currentInternalUrl);

				// Load menu associations
				if ($active_link === $current_link)
				{
					$associations = MenusHelper::getAssociations($active->id);
				}

				// Check if we are on the home page
				$is_home = ($active->home
					&& ($active_link === $current_link || $active_link === $current_link . 'index.php' || $active_link . '/' === $current_link));
			}

			// Load component associations.
			$option = $this->app->input->get('option');

			$component = $this->app->bootComponent($option);

			if ($component instanceof AssociationServiceInterface)
			{
				$cassociations = $component->getAssociationsExtension()->getAssociationsForItem();
			}
			else
			{
				$cName = StringHelper::ucfirst(StringHelper::str_ireplace('com_', '', $option)) . 'HelperAssociation';
				JLoader::register($cName, JPath::clean(JPATH_COMPONENT_SITE . '/helpers/association.php'));

				if (class_exists($cName) && is_callable(array($cName, 'getAssociations')))
				{
					$cassociations = call_user_func(array($cName, 'getAssociations'));
				}
			}

			// For each language...
			foreach ($languages as $i => &$language)
			{
				switch (true)
				{
					// Language without frontend UI || Language without specific home menu || Language without authorized access level
					case (!array_key_exists($i, LanguageHelper::getInstalledLanguages(0))):
					case (!isset($homes[$i])):
					case (isset($language->access) && $language->access && !in_array($language->access, $levels)):
						unset($languages[$i]);
						break;

					// Home page
					case ($is_home):
						$language->link = Route::_('index.php?lang=' . $language->sef . '&Itemid=' . $homes[$i]->id);
						break;

					// Current language link
					case ($i === $this->current_lang):
						$language->link = Route::_($currentInternalUrl);
						break;

					// Component association
					case (isset($cassociations[$i])):
						$language->link = Route::_($cassociations[$i]);
						break;

					// Menu items association
					// Heads up! "$item = $menu" here below is an assignment, *NOT* comparison
					case (isset($associations[$i]) && ($item = $menu->getItem($associations[$i]))):

						$language->link = Route::_('index.php?Itemid=' . $item->id . '&lang=' . $language->sef);
						break;

					// Too bad...
					default:
						unset($languages[$i]);
				}
			}

			// If there are at least 2 of them, add the rel="alternate" links to the <head>
			if (count($languages) > 1)
			{
				// Remove the sef from the default language if "Remove URL Language Code" is on
				if ($remove_default_prefix && isset($languages[$this->default_lang]))
				{
					$languages[$this->default_lang]->link
									= preg_replace('|/' . $languages[$this->default_lang]->sef . '/|', '/', $languages[$this->default_lang]->link, 1);
				}

				foreach ($languages as $i => &$language)
				{
					$doc->addHeadLink($server . $language->link, 'alternate', 'rel', array('hreflang' => $i));
				}

				// Add x-default language tag
				if ($this->params->get('xdefault', 1))
				{
					$xdefault_language = $this->params->get('xdefault_language', $this->default_lang);
					$xdefault_language = ($xdefault_language === 'default') ? $this->default_lang : $xdefault_language;

					if (isset($languages[$xdefault_language]))
					{
						// Use a custom tag because addHeadLink is limited to one URI per tag
						$doc->addCustomTag('<link href="' . $server . $languages[$xdefault_language]->link . '" rel="alternate" hreflang="x-default">');
					}
				}
			}
		}
	}

	/**
	 * Set the language cookie
	 *
	 * @param   string  $languageCode  The language code for which we want to set the cookie
	 *
	 * @return  void
	 *
	 * @since   3.4.2
	 */
	private function setLanguageCookie($languageCode)
	{
		// If is set to use language cookie for a year in plugin params, save the user language in a new cookie.
		if ((int) $this->params->get('lang_cookie', 0) === 1)
		{
			// Create a cookie with one year lifetime.
			$this->app->input->cookie->set(
				ApplicationHelper::getHash('language'),
				$languageCode,
				time() + 365 * 86400,
				$this->app->get('cookie_path', '/'),
				$this->app->get('cookie_domain', ''),
				$this->app->isHttpsForced(),
				true
			);
		}
		// If not, set the user language in the session (that is already saved in a cookie).
		else
		{
			$this->app->getSession()->set('plg_system_languagefilter.language', $languageCode);
		}
	}

	/**
	 * Get the language cookie
	 *
	 * @return  string
	 *
	 * @since   3.4.2
	 */
	private function getLanguageCookie()
	{
		// Is is set to use a year language cookie in plugin params, get the user language from the cookie.
		if ((int) $this->params->get('lang_cookie', 0) === 1)
		{
			$languageCode = $this->app->input->cookie->get(ApplicationHelper::getHash('language'));
		}
		// Else get the user language from the session.
		else
		{
			$languageCode = $this->app->getSession()->get('plg_system_languagefilter.language');
		}

		// Let's be sure we got a valid language code. Fallback to null.
		if (!array_key_exists($languageCode, $this->lang_codes))
		{
			$languageCode = null;
		}

		return $languageCode;
	}

	// Events for Masterlanguage:

	/**
	 * Before Saving extensions
	 * Method is called when an extension is going to be saved
	 * change parameters for master language because there depends on other parameters
	 *
	 * @param   string   $context  The extension
	 * @param   JTable   $table    DataBase Table object
	 * @param   boolean  $isNew    If the extension is new or not
	 *
	 * @return  void
	 *
	 * @since   4.0.0
	 */
	public function onExtensionBeforeSave($context, $table, $isNew)
	{
		// Get the params to save
		$params          = json_decode($table->params);
		$tableElement    = $table->element;
		$pluginStatus    = $table->enabled;
		$itemAssocStatus = $params->item_associations;

		if ($context != 'com_plugins.plugin' && $tableElement != 'languagefilter')
		{
			return true;
		}

		// If the plugin and the parameter item associations are enabled then set the correct value for the global master language
		if ($pluginStatus && $itemAssocStatus)
		{
			$globalMasterLanguage           = ($params->use_master_language === '1')
				? $params->global_master_language
				: '';
			$params->global_master_language = $globalMasterLanguage;
		}
		// Reset parameters for master language
		else
		{
			$params->use_master_language    = '';
			$params->global_master_language = '';
		}

		return $table->params = json_encode($params);
	}

	/**
	 * After save extensions
	 * Method is called when an extension has been saved
	 *
	 * @param   string   $context  The extension
	 * @param   JTable   $table    DataBase Table object
	 * @param   boolean  $isNew    If the extension is new or not
	 *
	 * @return  void
	 *
	 * @since   4.0.0
	 */
	public function onExtensionAfterSave($context, $table, $isNew)
	{
		// get the params that have been saved
		$params       = json_decode($table->params);
		$tableElement = $table->element;

		if ($context != 'com_plugins.plugin' && $tableElement != 'languagefilter')
		{
			return true;
		}

		$this->_setMasterItem($params->global_master_language);
	}

	/**
	 * Method to set the master item of an association as parent and the children get the parent id
	 * Also reset the modified date of the master item. Master and children will be up-to-date, as they get the same modified date
	 *
	 * @param   string  $language  The global master language
	 *
	 * @return  void
	 *
	 * @since   4.0.0
	 */
	private function _setMasterItem($language)
	{
		$db             = Factory::getDbo();
		$masterLanguage = $language;

		// TODO add warning when master language changed

		// if there is no global masterlanguage set, set all parent_ids to -1 and assocParams to null
		if (!$masterLanguage)
		{
			$resetQuery = $db->getQuery(true)
				->update($db->quoteName('#__associations'))
				->set($db->quoteName('parent_id') . ' = ' . -1)
				->set($db->quoteName('assocParams') . ' = ' . $db->quote(null));
			$db->setQuery($resetQuery);

			try
			{
				$db->execute();
			}
			catch (ExecutionFailureException $e)
			{
				$this->_message .= Text::_(' , failed to update the master with his childs');

				return;
			}
		}
		else
		{
			// TODO Question? Maybe if the language hasn't changed it isn't necessary to run this again. But no possibility to check that?

			// get every different key
			$keyQuery  = $db->getQuery(true)
				->select($db->quoteName('key'))
				->from($db->quoteName('#__associations'))
				->group($db->quoteName('key'))
				->having('COUNT(*) > 1');
			$assocKeys = $db->setQuery($keyQuery)->loadColumn();

			foreach ($assocKeys as $value)
			{
				//get the context of the association with the current key
				$contextQuery = $db->getQuery(true)
					->select($db->quoteName('context'))
					->from($db->quoteName('#__associations'))
					->where($db->quoteName('key') . ' = ' . $db->quote($value));
				$assocContext = $db->setQuery($contextQuery)->loadResult();

				// get the correct table to look in depending on the context
				switch ($assocContext)
				{
					case 'com_content.item':
						$fromTable = $db->quoteName('#__content', 'e');
						$modified  = $db->quoteName('e.modified');
						break;

					case 'com_menus.item' :
						$fromTable = $db->quoteName('#__menu', 'e');
						$modified  = '';
						break;

					case 'com_categories.item':
						$fromTable = $db->quoteName('#__categories', 'e');
						$modified  = $db->quoteName('e.modified_time');
						break;

					case 'com_contact.item':
						$fromTable = $db->quoteName('#__contact_details', 'e');
						$modified  = $db->quoteName('e.modified');
						break;

					case 'com_newsfeeds.item':
						$fromTable = $db->quoteName('#__newsfeeds', 'e');
						$modified  = $db->quoteName('e.modified');
						break;
				}

				// get items with the global master language
				$subQuery = $db->getQuery(true)
					->select($db->quoteName('e.id'))
					->from($fromTable)
					->where($db->quoteName('e.language') . ' = ' . $db->quote($masterLanguage));

				// get master id of an item that has the global master language
				$masterQuery = $db->getQuery(true)
					->select($db->quoteName('id'))
					->from('#__associations')
					->where($db->quoteName('id') . ' IN (' . $subQuery . ')')
					->where($db->quoteName('key') . ' = ' . $db->quote($value));
				$masterId    = $db->setQuery($masterQuery)->loadResult();

				// Get master modified date
				if ($modified)
				{
					$masterModQuery = $db->getQuery(true)
						->select($modified)
						->from($fromTable)
						->where($db->quoteName('id') . ' = ' . $db->quote($masterId));
					$masterModified = $db->setQuery($masterModQuery)->loadResult();
				}

				$masterModified = $modified ? $masterModified : null;
				$masterId       = $masterId ?? -1;

				// Set the master item as parent and set his modified date
				$query = $db->getQuery(true)
					->update($db->quoteName('#__associations'))
					->set($db->quoteName('parent_id') . ' = ' . $db->quote(0))
					->set($db->quoteName('assocParams') . ' = ' . $db->quote($masterModified))
					->where($db->quoteName('id') . ' = ' . $db->quote($masterId))
					->where($db->quoteName('key') . ' = ' . $db->quote($value))
					->where($db->quoteName('context') . ' = ' . $db->quote($assocContext));
				$db->setQuery($query);

				try
				{
					$db->execute();
				}
				catch (ExecutionFailureException $e)
				{
					$this->_message .= Text::_(' , failed to update the master with his childs');

					return;
				}

				// Set the master id to the children
				$query = $db->getQuery(true)
					->update($db->quoteName('#__associations'))
					->set($db->quoteName('parent_id') . ' = ' . $db->quote($masterId))
					->set($db->quoteName('assocParams') . ' = ' . $db->quote($masterModified))
					->where($db->quoteName('id') . ' <> ' . $db->quote($masterId))
					->where($db->quoteName('key') . ' = ' . $db->quote($value))
					->where($db->quoteName('context') . ' = ' . $db->quote($assocContext));
				$db->setQuery($query);

				try
				{
					$db->execute();
				}
				catch (ExecutionFailureException $e)
				{
					$this->_message .= Text::_(' , failed to update the master with his childs');

					return;
				}

			}
		}
	}
}
