<?php
/**
* @version $ Id; blocks-ajax.php 21-03-2012 03:22:10 Ahmed Said $
*/

// Disallow direct access.
defined('ABSPATH') or die("Access denied");

// import dependencies.
cssJSToolbox::import('framework:mvc:controller-ajax.inc.php');

/**
* Serve blocks page Ajax requests.
*
* The Actions resident here is global for only the blocks page, its not
* for a specific/single block. You can find single block
* actions in block-ajax.php file.
*
* @deprecated DONT ADD MORE ACTIONS  HERE!
* @author Ahmed Said
* @version 6
*/
class CJTBlocksAjaxController extends CJTAjaxController {

	/**
	* put your comment there...
	*
	* @var mixed
	*/
	protected $controllerInfo = array('model' => 'blocks');

	/**
	* Initialize controller object.
	*
	* @see CJTController for more details
	* @return void
	*/
	public function __construct() {
		parent::__construct();
		// Register action.
		$this->registryAction('create_block');
		$this->registryAction('get_view');
		$this->registryAction('save_blocks');
		$this->registryAction('saveOrder');
		$this->registryAction('loadBlock');
	}

	/**
	* Create new block.
	*
	* Once this method is called a new block is saved into
	* the database.
	*
	* Call this method using GET method with the following parameters.
	* 	- array ids Ids for all the available blocks.
	* 	- [name] string Block name.
	* 	- [state] string Block state.
	* 	- [location] string Block hook location.
	*
	* Response body is array with the following elements.
	* 	- integer id New block id.
	* 	- string view Block HTML code.
	*
	* @return void
	*/
	public function createBlockAction($blockId = null, $blockType = null, $pinPoint = null, $viewName = null) {
		$response = array();
		// If viewName not provided read it from request vars.
		if (!$viewName) {
			$viewName = filter_input(INPUT_GET, 'viewName', FILTER_UNSAFE_RAW);
			$viewName = is_string($viewName) ? sanitize_text_field($viewName) : null;
		}
		// $viewName is interpolated into a filesystem path by CJTController::getView(),
		// which require_once's "views/blocks/{$viewName}/view.php". Restrict it to the
		// known block views so traversal sequences can never reach that include.
		$allowedBlockViews = array('cjt-block', 'block', 'metabox', 'create-metabox');
		if ($viewName && !in_array($viewName, $allowedBlockViews, true)) {
			$this->httpCode = '403 Forbidden';
			return;
		}
		// Prepare parameters.
		$defaultBlockName = 'block_' . hexdec(substr(md5(time()), 0, 6));
		$wordpressMYSQLTime = current_time('mysql');
		// Block data to insert.
		$blockData = array(
			'id' => $blockId,
			'name' => $defaultBlockName,
			'state' => null,
			'location' => null,
			'owner' => get_current_user_id(),
			'created' => $wordpressMYSQLTime,
			'lastModified' => $wordpressMYSQLTime,
			'type' => $blockType,
			'pinPoint' => $pinPoint,
		);
		// Read parameters from the request.
		foreach ($blockData as $name => $default) {
			// Use default if not supplied.
			if (array_key_exists($name, $_GET)) {
			  $blockData[$name] = $_GET[$name];
			}
		}
		// Harden the block name server-side (CVE-2025-13533 defence in depth).
		$blockData['name'] = self::sanitizeBlockName($blockData['name']);
		// Import block model.
		require_once CJTOOLBOX_MODELS_PATH . '/block.php';
		$block = new CJTBlockModel($blockData);
		// Add block.
		$blocksModel =& $this->model;
		$blockId = $blocksModel->add($block->getValues(), true);
		$blocksModel->save();
		// Read newly added block from database.
		$newBlockData = $blocksModel->getBlock($blockId, array('returnCodeFile' => true));

		if ($newBlockData === null) {
			throw new Exception('Could not add new block!!!');
		}
		else {
			$block->setValues($newBlockData);
			if ($viewName ){
				// Get block view.
				$blockView = CJTController::getView("blocks/{$viewName}");
				// Push vars into the view.
				$blockView->setBlock($block);
				$response['view'] = $blockView->getTemplate('new');
			}
			$response['id'] = $blockId;
			// Set response object.
			$this->response = $response;
		}
	}

	/**
	* Get view content through ajax request.
	*
	* The method is useful for requesting Popup forms through ajax (e.g ThickBox).
	* You can request any view specified in the $allowedViews array.
	*
	* Call this method using GET method with the following parameters.
	* 	- viewName string Name of the view.
	*
	* Response body is the view content string.
	*
	* @return void
	*/
	public function getViewAction() {
		// Some views required objects to be pushed into it before displaying
		// the controller element is a callback that a Dummy Controller from which
		// this variables should be pushed.
		$allowedViews = array(
			'blocks/new' => array(),
		);
		// Prepare parameters.
		$viewName = filter_input(INPUT_GET, 'viewName', FILTER_UNSAFE_RAW);
		$viewName = is_string($viewName) ? sanitize_text_field($viewName) : '';
		if (array_key_exists($viewName, $allowedViews) === FALSE) {
		  $this->httpCode = '403 Forbidden';
		}
		else {
			// Import view file.
			$viewInfo = $allowedViews[$viewName];
		  // Get view object.
		  $view = CJTController::getView($viewName);
		  // Push view variables.
		  foreach ((isset($viewInfo['vars']) ? $viewInfo['vars'] : array()) as $var) {
		  	$view->$var = $_GET["view.{$var}"];
			}
			// Some views required custom pushing, this is can
			// be done by the registered controller.
			if (isset($viewInfo['controller'])) {
				$viewController = $viewInfo['controller'];
			  $this->$viewController($view);
			}
			// Set Content type.
			$this->httpContentType = "text/html";
			// Get view content.
		  $this->response = $view->getTemplate('default');
		}
	}

	/**
	* put your comment there...
	*
	*/
	public function loadBlockAction() {
		// Block Id.
		$blockId = (int) $_GET['blockId'];
		// Get block content.
		$view = CJTView::getInstance('blocks/cjt-block');
		$view->setBlock(CJTModel::create('blocks')->getBlock($blockId, array('returnCodeFile' => true)));
		// Return View content.
		$view->getTemplate('default');
		$this->response = $view->structuredContent;
	}

	/**
	* put your comment there...
	*
	*/
	public function saveBlocksAction()
	{
		$response = array();

		// Blocks are sent ins single array list.
		$blocksToSave = filter_input( INPUT_POST, 'blocks', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );
		$calculatePinPoint = ( bool ) filter_input( INPUT_POST, 'calculatePinPoint', FILTER_SANITIZE_NUMBER_INT );
		$createRevision = ( bool ) filter_input( INPUT_POST, 'createRevision', FILTER_SANITIZE_NUMBER_INT );

		// For any reason that cause Client/JavaScript to send empty blocks,
		// make sure we're save.
		if ( is_array( $blocksToSave ) && ! empty( $blocksToSave ) )
		{

			foreach ( $blocksToSave as $id => $postedblockPartialData )
			{
				// Push block id into block data.
				$blockData = ( object ) $postedblockPartialData;
				$blockData->id = $id;

				// Harden the block name server-side (CVE-2025-13533 defence in depth).
				if ( isset( $blockData->name ) ) {
					$blockData->name = self::sanitizeBlockName( $blockData->name );
				}

				// Recalculate pinPoint field value.
				! $calculatePinPoint or ( CJTBlockModel::arrangePins( $blockData ) && CJTBlockModel::calculateBlockPinPoint( $blockData ) );

				// Create block revision.
				! $createRevision or $this->model->addRevision( $id, $blockData->activeFileId );

				// Set lastModified field to current time.
				$blockData->lastModified = current_time( 'mysql' );

				// Update database.
				$this->model->update( $blockData, $calculatePinPoint );
				$this->model->save();

				// Send the changes properties back to client.
                $updatedBlockData = $this->model->getBlock($id, null, array('*'), ARRAY_A);

				foreach ( $updatedBlockData as $property => $value )
				{
					$response[ $id ][ $property ][ 'value' ] = $value;
				}

			}

		}

		// Delete other blocks.
		empty( $_POST[ 'deletedBlocks' ] ) or $this->model->delete( $_POST[ 'deletedBlocks' ] );

		// Save changes.
		$this->model->save();

        // Return
		// Set response.
		$this->response = $response;

	}

	/**
	* put your comment there...
	*
	*/
	public function saveOrderAction() {
		// Read order.
		$order = array('normal' => $_GET['order']);
		// Centralized orders to be shared between all users!
		$this->model->setOrder($order);
		$this->response = array('order' => $order, 'state' => 'saved');
	}

	/**
	* Sanitize a code-block name coming from the request.
	*
	* Defence-in-depth for the stored-XSS issue (CVE-2025-13533): the client-side UI
	* already restricts block names to A-Z, 0-9, space, '-' and '_', but that check is
	* trivially bypassed (e.g. via an HTTP proxy). We enforce the exact same contract
	* server-side so characters that could break out of an HTML attribute/element -
	* such as < > " ' - can never be persisted. Output is still escaped at every sink
	* as the primary defence; this simply keeps the stored data clean.
	*
	* @param mixed $name Raw name value from the request.
	* @return string Sanitized name (never empty).
	*/
	public static function sanitizeBlockName($name) {
		// Keep only the documented allowed characters.
		$name = preg_replace('/[^A-Za-z0-9 _-]/', '', (string) $name);
		// Collapse to a trimmed value and guard against an empty result.
		$name = trim($name);
		if ($name === '') {
			$name = 'block_' . hexdec(substr(md5((string) time()), 0, 6));
		}
		// Respect the 50 char column/UI limit.
		return substr($name, 0, 50);
	}

} // End class.