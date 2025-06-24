<?php
/**
*
*/

// Disallow direct access.
defined('ABSPATH') or die("Access denied");

/**
* Access Point interface
*/
interface CJTIAccessPoint {

	/**
	* put your comment there...
	*
	*/
	public function listen();

}

/**
*
*/
abstract class CJTAccessPoint extends CJTHookableClass implements CJTIAccessPoint {

	/**
	* put your comment there...
	*
	* @var mixed
	*/
	protected static $connected;

	/**
	* put your comment there...
	*
	* @var mixed
	*/
	protected $controller;

	/**
	* put your comment there...
	*
	* @var mixed
	*/
	protected $controllerName;

	/**
	* put your comment there...
	*
	* @var mixed
	*/
	protected $name;

	/**
	* put your comment there...
	*
	* @var mixed
	*/
	protected $onconnected = array('parameters' => array('state'));

	/**
	* put your comment there...
	*
	* @var mixed
	*/
	protected $ongetdefaultcontrollername = array('parameters' => array('controller'));

	/**
	* put your comment there...
	*
	* @var mixed
	*/
	protected $onlisten = array('hookType' =>CJTWordpressEvents::HOOK_ACTION);

	/**
	* put your comment there...
	*
	* @var mixed
	*/
	protected $onsetcontroller = array('parameters' => array('controller'));

	/**
	* put your comment there...
	*
	* @var mixed
	*/
	protected $overrideControllersPath = null;

	/**
	* put your comment there...
	*
	* @var mixed
	*/
	protected $overrideControllersPrefix = null;

	/**
	* put your comment there...
	*
	* @var mixed
	*/
	protected $pageId = CJTPlugin::PLUGIN_REQUEST_ID;

	/**
	* put your comment there...
	*
	*/
	public function __construct($defaultController = 'blocks') {
		// Initialize Hookable.
		parent::__construct();
		// Overrides controllers path using current Access Point model class path
		$accessPointClassLoader =& CJT_Framework_Autoload_Loader::findClassLoader(get_class($this));
		if ($accessPointClassLoader) {
			$this->overrideControllersPath = $accessPointClassLoader->getPath() . DIRECTORY_SEPARATOR . 'controllers';
			$this->overrideControllersPrefix = $accessPointClassLoader->getPrefix();
		}
		// Initialize with validation!
		$requestedController = isset($_REQUEST['controller']) ? esc_html($_REQUEST['controller']) : $defaultController;
		$this->controllerName = $this->ongetdefaultcontrollername($this->sanitizeControllerName($requestedController, $defaultController));
	}

	/**
	* Sanitize controller name to prevent path traversal attacks
	*
	* @param string $controllerName The requested controller name
	* @param string $defaultController The default controller to use if validation fails
	* @return string Safe controller name
	*/
	private function sanitizeControllerName($controllerName, $defaultController) {
		// Check for null or empty string
		if (empty($controllerName) || !is_string($controllerName)) {
			return $defaultController;
		}

		// Check for path traversal attempts
		if (strpos($controllerName, '..') !== false) {
			return $defaultController;
		}

		// Check for directory separators
		if (strpos($controllerName, '/') !== false || strpos($controllerName, '\\') !== false) {
			return $defaultController;
		}

		// Only allow alphanumeric characters, hyphens, and underscores
		if (!preg_match('/^[a-zA-Z0-9_-]+$/', $controllerName)) {
			return $defaultController;
		}

		return $controllerName;
	}

	/**
	* put your comment there...
	*
	* @return Boolean TRUE if it wasn't connected! FALSE otherwise.
	*/
	protected function connected() {
		// Do connect only if not connected yet
		if ($returns = !self::$connected) {
			// Fire connected event!
			$this->onconnected(true);
			// Set current instance as the connected object!
			self::$connected = $this;
		}
		return $returns;
	}

	/**
	* put your comment there...
	*
	*/
	protected abstract function doListen();

	/**
	* put your comment there...
	*
	*/
	public function & getController() {
		return $this->controller;
	}

	/**
	* put your comment there...
	*
	*/
	public function getControllerName() {
		return $this->controllerName;
	}

	/**
	* put your comment there...
	*
	*/
	public function getName() {
		return $this->name;
	}

	/**
	* put your comment there...
	*
	*/
	public static function & isConnected() {
		return self::$connected;
	}

	/**
	* put your comment there...
	*
	*/
	public function hasAccess() {
		return current_user_can('administrator');
	}

	/**
	* put your comment there...
	*
	*/
	public function listen() {
		// Fire listen event!
		$this->onlisten();
		// Allow access points to bind their hooks
		$this->doListen();
		return $this;
	}

	/**
	* put your comment there...
	*
	* @param mixed $request
	*/
	public function route($loadView = null, $request = null) {
		// Only loading one controller is allowed.
		if (!$this->controller) {
			// Import view class.
			require_once CJTOOLBOX_MVC_FRAMEWOK . '/view.inc.php';
			// Instantiate controller!
			$this->controller = $this->onsetcontroller(
				CJTController::getInstance(
					$this->controllerName,
					$loadView,
					$request,
					$this->overrideControllersPath,
					$this->overrideControllersPrefix
					));
		}
		return $this->controller;
	}

} // End class.

// Hookable!
CJTAccessPoint::define('CJTAccessPoint', array('hookType' => CJTWordpressEvents::HOOK_FILTER));
