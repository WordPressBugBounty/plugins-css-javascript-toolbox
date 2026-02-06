<?php
/**
* @version $ Id; ?FILE_NAME ?DATE ?TIME ?AUTHOR $
*/

// Disallow direct access.
defined('ABSPATH') or die("Access denied");

// import dependencies.
cssJSToolbox::import('framework:mvc:controller-ajax.inc.php');

/**
*
* DESCRIPTION
*
* @author ??
* @version ??
*/
class CJTTemplateController extends CJTAjaxController {

	/**
	* put your comment there...
	*
	* @var mixed
	*/
	protected $controllerInfo = array('model' => 'template');

	/**
	* put your comment there...
	*
	* @var mixed
	*/
	protected $onsave = array('parameters' => array('data'));

	/**
	*
	* Initialize new object.
	*
	* @return void
	*/
	public function __construct() {
		// Initialize parent!
		parent::__construct();
		// Add actions.
		$this->registryAction('edit');
		$this->registryAction('save');
		$this->registryAction('info');
		$this->registryAction('getTemplateBy');
	}

	/**
	* put your comment there...
	*
	*/
	protected function editAction() {
		$this->model->inputs['id'] = (int) $_REQUEST['id'];
		// Display the view.
		parent::displayAction();
	}

	/**
	* put your comment there...
	*
	*/
	protected function getTemplateByAction() {
		// Initialize.
		$returns = array_flip($_GET['returns']);
		// Set inputs.
		$inputs =& $this->model->inputs;
		$inputs['filter'] = $_GET['filter'];
		// Query Block.
		$this->response = array_intersect_key((array) $this->model->getTemplateBy(), $returns);
	}

	/**
	* put your comment there...
	*
	*/
	protected function infoAction() {
		$this->model->inputs['id'] = (int) $_REQUEST['id'];
		// Display the view.
		parent::displayAction();
	}

	/**
	* Save template data with proper sanitization to prevent XSS attacks.
	*
	* Security Fix: Sanitizes all user inputs (description, keywords, name, changeLog, version)
	* to prevent Stored XSS vulnerabilities. This addresses the security issue where malicious
	* scripts could be injected through template fields and executed when viewing the Templates
	* Manager page, Template Lookup feature, or when using the Embed/Link Templates functionality.
	*
	* @security CVE Reference: Stored XSS in template save endpoint
	*/
	protected function saveAction() {
		// Read inputs
		$item = filter_input(INPUT_POST, 'item', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY);

		// Sanitize template fields to prevent XSS
		if (isset($item['template'])) {
			// Sanitize description field - strip all HTML tags
			if (isset($item['template']['description'])) {
				$item['template']['description'] = sanitize_textarea_field($item['template']['description']);
			}

			// Sanitize keywords field - strip all HTML tags
			if (isset($item['template']['keywords'])) {
				$item['template']['keywords'] = sanitize_textarea_field($item['template']['keywords']);
			}

			// Sanitize name field
			if (isset($item['template']['name'])) {
				$item['template']['name'] = sanitize_text_field($item['template']['name']);
			}
		}

		// Sanitize revision fields
		if (isset($item['revision'])) {
			// Sanitize changeLog field
			if (isset($item['revision']['changeLog'])) {
				$item['revision']['changeLog'] = sanitize_textarea_field($item['revision']['changeLog']);
			}

			// Sanitize version field
			if (isset($item['revision']['version'])) {
				$item['revision']['version'] = sanitize_text_field($item['revision']['version']);
			}
		}

		// Posted template data is in the item array, the others is just for making the request!
		$this->model->inputs['item'] = $this->onsave($item);
		if ($revision = $this->model->save()) {
			$this->response = array('revision' => $revision);
		}
	}
} // End class.

// Hookable!
CJTTemplateController::define('CJTTemplateController', array('hookType' => CJTWordpressEvents::HOOK_FILTER));