<?php
/**
 * Implementation of AddSubFolder controller
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
class LetoDMS_Controller_AddSubFolder extends LetoDMS_Controller_Common {

	public function run() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$folder = $this->params['folder'];

		/* Call preAddSubFolder early, because it might need to modify some
		 * of the parameters.
		 */
		if(false === $this->callHook('preAddSubFolder')) {
			if(empty($this->errormsg))
				$this->errormsg = 'hook_preAddSubFolder_failed';
			return null;
		}

		$name = $this->getParam('name');
		$comment = $this->getParam('comment');
		$sequence = $this->getParam('sequence');
		$attributes = $this->getParam('attributes');
		$notificationgroups = $this->getParam('notificationgroups');
		$notificationusers = $this->getParam('notificationusers');

		$result = $this->callHook('addSubFolder');
		if($result === null) {
			$subFolder = $folder->addSubFolder($name, $comment, $user, $sequence, $attributes);
			if (!is_object($subFolder)) {
				$this->errormsg = "error_occured";
				return false;
			}
			/* Check if additional notification shall be added */
			foreach($notificationusers as $notuser) {
				if($subFolder->getAccessMode($user) >= M_READ)
					$res = $subFolder->addNotify($notuser->getID(), true);
			}
			foreach($notificationgroups as $notgroup) {
				if($subFolder->getGroupAccessMode($notgroup) >= M_READ)
					$res = $subFolder->addNotify($notgroup->getID(), false);
			}

			if(!$this->callHook('postAddSubFolder', $subFolder)) {
			}
			$result = $subFolder;
		}

		return $result;
	} /* }}} */
}

