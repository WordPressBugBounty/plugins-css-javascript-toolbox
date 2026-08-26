<?php
/**
*
*/

// Disallow direct access.
defined('ABSPATH') or die("Access denied");

/**
*
*/
class CJTAjaxAccessPoint extends CJTAccessPoint {

	/**
	* put your comment there...
	*
	*/
	public function __construct() {
		// Initialize Access Point base!
		parent::__construct();
		// Set access point name!
		$this->name = 'ajax';
	}

	/**
	* put your comment there...
	*
	*/
	protected function doListen() {
		// Define CJT AJAX access point!
		add_action("wp_ajax_{$this->pageId}_api", array(&$this, 'route'), 10, 0);
	}

	/**
	* put your comment there...
	*
	*/
	public function route($loadView = null, $request = null) {
		// Initializing!
		$controller = false;
		// Controllers allowed to be Loaded if not installed
		$notInstalledAllowedControllers = array('installer', 'setup');
		// Veil access point unless CJT installed or the controller is installer (to allow instalaltion through AJAX)!
		if (CJTPlugin::getInstance()->isInstalled() || in_array($this->controllerName, $notInstalledAllowedControllers)) {
			// Connected!
			$this->connected();
			// IF Module-Prefix passed THEN Point to correct Controller path
			if (isset($_REQUEST['cjtajaxmodule'])) {
				// Module prefixes are plain identifiers. Reject anything else so the
				// request can never steer the controllers path outside the plugin.
				$module = preg_replace('/[^A-Za-z0-9_]/', '', (string) $_REQUEST['cjtajaxmodule']);

                if ($module === 'ECMEHD') {
                    // CJT PLUS environment controllers live at a fixed location.
                    $this->overrideControllersPath =  dirname(__DIR__) . '-plus/CJTEnv/controllers';
                    $this->overrideControllersPrefix = $module;
                }
                else if ($module !== '') {
                    # Resolve WITHOUT registering. autoLoad() builds a pathless loader
                    # for any unknown prefix, so every arbitrary module name used to
                    # "resolve" and then produce a bogus "/controllers" path.
                    $accessPointClassLoader = CJT_Framework_Autoload_Loader::getRegisteredLoader($module);
                    $loaderPath = $accessPointClassLoader ? $accessPointClassLoader->getPath() : null;

                    // Only override when the loader actually resolved a path.
                    if ($loaderPath) {
                        $this->overrideControllersPath = $loaderPath .  DIRECTORY_SEPARATOR . 'controllers';
                        $this->overrideControllersPrefix = $accessPointClassLoader->getPrefix();
                    }
                }
			}
			// Instantiate controller.
			$controller = parent::route($loadView, $request);
			// Dispatch the call as its originally requested from ajax action!
			if (isset($_REQUEST['CJTAjaxAction'])) {
				$ajaxAction = preg_replace('/[^A-Za-z0-9_]/', '', (string) $_REQUEST['CJTAjaxAction']);
				if ($ajaxAction !== '') {
					// Fire Ajax action.
					do_action("wp_ajax_{$this->pageId}_{$ajaxAction}");
				}
			}
		}
		return $controller;
	}

} // End class.

// Hookable!
CJTAjaxAccessPoint::define('CJTAjaxAccessPoint', array('hookType' => CJTWordpressEvents::HOOK_FILTER));
