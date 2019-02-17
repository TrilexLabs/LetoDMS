<?php
/**
 * Implementation of RemoveFolder controller
 *
 * @category   DMS
 * @package    LetoDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010-2013 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Class which does the busines logic for downloading a document
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010-2013 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Controller_RemoveFolder extends LetoDMS_Controller_Common {

	public function run() {
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$settings = $this->params['settings'];
		$folder = $this->params['folder'];
		$index = $this->params['index'];
		$indexconf = $this->params['indexconf'];

		/* Get the document id and name before removing the document */
		$foldername = $folder->getName();
		$folderid = $folder->getID();

		if(false === $this->callHook('preRemoveFolder')) {
			if(empty($this->errormsg))
				$this->errormsg = 'hook_preRemoveFolder_failed';
			return null;
		}

		$result = $this->callHook('removeFolder', $folder);
		if($result === null) {
			/* Register a callback which removes each document from the fulltext index
			 * The callback must return null other the removal will be canceled.
			 */
			function removeFromIndex($arr, $document) {
				$index = $arr[0];
				$indexconf = $arr[1];
				$lucenesearch = new $indexconf['Search']($index);
				if($hit = $lucenesearch->getDocument($document->getID())) {
					$index->delete($hit->id);
					$index->commit();
				}
				return null;
			}
			if($index)
				$dms->setCallback('onPreRemoveDocument', 'removeFromIndex', array($index, $indexconf));

			if (!$folder->remove()) {
				$this->errormsg = 'error_occured';
				return false;
			} else {

				if(!$this->callHook('postRemoveFolder')) {
				}

			}
		} else
			return $result;

		return true;
	}
}
