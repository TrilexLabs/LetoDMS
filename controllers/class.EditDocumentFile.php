<?php
/**
 * Implementation of EditDocumentFile controller
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
 * Class which does the busines logic for editing a document
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010-2013 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Controller_EditDocumentFile extends LetoDMS_Controller_Common {

	public function run() {
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$settings = $this->params['settings'];
		$document = $this->params['document'];
		$file = $this->params['file'];

		if(false === $this->callHook('preEditDocumentFile')) {
			if(empty($this->errormsg))
				$this->errormsg = 'hook_preEditDocumentFile_failed';
			return null;
		}

		$result = $this->callHook('editDocumentFile', $document);
		if($result === null) {
			$name = $this->params['name'];
			$oldname = $file->getName();
			if($oldname != $name)
				if(!$file->setName($name))
					return false;

			$comment = $this->params['comment'];
			if(($oldcomment = $file->getComment()) != $comment)
				if(!$file->setComment($comment))
					return false;

			$version = $this->params["version"];
			$oldversion = $file->getVersion();
			if ($oldversion != $version)
				if(!$file->setVersion($version))
					return false;

			$public = $this->params["public"];
			$file->setPublic($public == 'true' ? 1 : 0);

			if(!$this->callHook('postEditDocumentFile')) {
			}

		} else
			return $result;

		return true;
	}
}
