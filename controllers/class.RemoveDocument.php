<?php
/**
 * Implementation of RemoveDocument controller
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
class LetoDMS_Controller_RemoveDocument extends LetoDMS_Controller_Common {

	public function run() {
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$settings = $this->params['settings'];
		$document = $this->params['document'];
		$index = $this->params['index'];
		$indexconf = $this->params['indexconf'];

		$folder = $document->getFolder();

		/* Get the document id and name before removing the document */
		$docname = $document->getName();
		$documentid = $document->getID();

		if(false === $this->callHook('preRemoveDocument')) {
			if(empty($this->errormsg))
				$this->errormsg = 'hook_preRemoveDocument_failed';
			return null;
		}

		$result = $this->callHook('removeDocument', $document);
		if($result === null) {
			if (!$document->remove()) {
				$this->errormsg = "error_occured";
				return false;
			} else {

				if(!$this->callHook('postRemoveDocument')) {
				}

				/* Remove the document from the fulltext index */
				if($index) {
					$lucenesearch = new $indexconf['Search']($index);
					if($hit = $lucenesearch->getDocument($documentid)) {
						$index->delete($hit->id);
						$index->commit();
					}
				}
			}
		}

		return true;
	}
}
