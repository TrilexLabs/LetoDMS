<?php
/**
 * Implementation of UpdateDocument controller
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
class LetoDMS_Controller_UpdateDocument extends LetoDMS_Controller_Common {

	public function run() { /* {{{ */
		$name = $this->getParam('name');
		$comment = $this->getParam('comment');

		/* Call preUpdateDocument early, because it might need to modify some
		 * of the parameters.
		 */
		if(false === $this->callHook('preUpdateDocument', $this->params['document'])) {
			if(empty($this->errormsg))
				$this->errormsg = 'hook_preUpdateDocument_failed';
			return null;
		}

		$name = $this->getParam('name');
		$comment = $this->getParam('comment');
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$document = $this->params['document'];
		$settings = $this->params['settings'];
		$index = $this->params['index'];
		$indexconf = $this->params['indexconf'];
		$folder = $this->params['folder'];
		$userfiletmp = $this->getParam('userfiletmp');
		$userfilename = $this->getParam('userfilename');
		$filetype = $this->getParam('filetype');
		$userfiletype = $this->getParam('userfiletype');
		$reviewers = $this->getParam('reviewers');
		$approvers = $this->getParam('approvers');
		$reqversion = $this->getParam('reqversion');
		$comment = $this->getParam('comment');
		$attributes = $this->getParam('attributes');
		$workflow = $this->getParam('workflow');
		$maxsizeforfulltext = $this->getParam('maxsizeforfulltext');

		$result = $this->callHook('updateDocument');
		if($result === null) {
			$filesize = LetoDMS_Core_File::fileSize($userfiletmp);
			if($contentResult=$document->addContent($comment, $user, $userfiletmp, utf8_basename($userfilename), $filetype, $userfiletype, $reviewers, $approvers, $version=0, $attributes, $workflow)) {

				if ($this->hasParam('expires')) {
					if($document->setExpires($this->getParam('expires'))) {
					} else {
					}
				}

				if($index) {
					$lucenesearch = new $indexconf['Search']($index);
					if($hit = $lucenesearch->getDocument((int) $document->getId())) {
						$index->delete($hit->id);
					}
					$idoc = new $indexconf['IndexedDocument']($dms, $document, isset($settings->_converters['fulltext']) ? $settings->_converters['fulltext'] : null, !($filesize < $settings->_maxSizeForFullText));
					if(!$this->callHook('preIndexDocument', $document, $idoc)) {
					}
					$index->addDocument($idoc);
					$index->commit();
				}

				if(!$this->callHook('postUpdateDocument', $document, $contentResult->getContent())) {
				}
				$result = $contentResult->getContent();
			} else {
				$this->errormsg = 'error_update_document';
				$result = false;
			}
		}

		return $result;
	} /* }}} */
}

