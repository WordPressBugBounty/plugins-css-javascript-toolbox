<?php
/**
* 
*/

// Disallow direct access.
defined('ABSPATH') or die("Access denied");

/**
* 
*/
abstract class CJTPageAccessPoint extends CJTAccessPoint {
	
	/**
	* put your comment there...
	* 
	*/
	public function getPage() {
		// If not installed always run the installer.
		if (!CJTPlugin::getInstance()->isInstalled()) {
			$installedAccessPoint =CJTPlugin::getInstance()->getAccessPoint('installer');
			// Redirect menu call back to the installer access point!
			$this->controller = $installedAccessPoint->installationPage();
			// Stop not installed admin notice!
			$installedAccessPoint->stopNotices();
		}
		else { // If installed work like a pages proxy!
			// Set as the connected object!
			$this->connected();
			// Process the request!
			$this->route();
		}
	}
	
	/**
	* Render the page by delegating to the routed controller.
	* 
	* Menu callbacks must be registered as array($this, 'renderPage') rather than
	* array(&$this->controller, '_doAction'). $this->controller is still NULL when
	* add_menu_page()/add_submenu_page() run -- it is only populated later by
	* getPage() on the load-{$pageHookId} hook. Since WordPress 7.1,
	* _wp_filter_build_unique_id() returns NULL for a callback whose object is NULL
	* and WP_Hook::add_filter() silently discards it, so the page hook ends up with
	* no callbacks and menu-header.php falls back to linking the bare menu slug
	* (e.g /wp-admin/cjtoolbox instead of /wp-admin/admin.php?page=cjtoolbox).
	* 
	* @return void
	*/
	public function renderPage() {
		// getPage() runs on load-{$pageHookId}, before the page hook fires.
		if ($this->controller) {
			$this->controller->_doAction();
		}
	}
	
} // End class.

// Hookable!
CJTPageAccessPoint::define('CJTPageAccessPoint', array('hookType' => CJTWordpressEvents::HOOK_FILTER));