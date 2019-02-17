<?php
/**
 * Implementation of access restricitions
 *
 * @category   DMS
 * @package    LetoDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Class to check certain access restrictions
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_AccessOperation {
	/**
	 * @var object $dms reference to dms
	 * @access protected
	 */
	private $dms;

	/**
	 * @var object $obj object being accessed
	 * @access protected
	 */
	private $obj;

	/**
	 * @var object $user user requesting the access
	 * @access protected
	 */
	private $user;

	/**
	 * @var object $settings LetoDMS Settings
	 * @access protected
	 */
	private $settings;

	function __construct($dms, $obj, $user, $settings) { /* {{{ */
		$this->dms = $dms;
		$this->obj = $obj;
		$this->user = $user;
		$this->settings = $settings;
	} /* }}} */

	/**
	 * Check if editing of version is allowed
	 *
	 * This check can only be done for documents. Removal of versions is
	 * only allowed if this is turned on in the settings and there are
	 * at least 2 versions avaiable. Everybody with write access on the
	 * document may delete versions. The admin may even delete a version
	 * even if is disallowed in the settings.
	 */
	function mayEditVersion($vno=0) { /* {{{ */
		if(get_class($this->obj) == $this->dms->getClassname('document')) {
			if($vno)
				$version = $this->obj->getContentByVersion($vno);
			else
				$version = $this->obj->getLatestContent();
			if (!isset($this->settings->_editOnlineFileTypes) || !is_array($this->settings->_editOnlineFileTypes) || !in_array(strtolower($version->getFileType()), $this->settings->_editOnlineFileTypes))
				return false;
			if ($this->obj->getAccessMode($this->user) == M_ALL || $this->user->isAdmin()) {
				return true;
			}
		}
		return false;
	} /* }}} */

	/**
	 * Check if removal of version is allowed
	 *
	 * This check can only be done for documents. Removal of versions is
	 * only allowed if this is turned on in the settings and there are
	 * at least 2 versions avaiable. Everybody with write access on the
	 * document may delete versions. The admin may even delete a version
	 * even if is disallowed in the settings.
	 */
	function mayRemoveVersion() { /* {{{ */
		if(get_class($this->obj) == $this->dms->getClassname('document')) {
			$versions = $this->obj->getContent();
			if ((($this->settings->_enableVersionDeletion && ($this->obj->getAccessMode($this->user) == M_ALL)) || $this->user->isAdmin() ) && (count($versions) > 1)) {
				return true;
			}
		}
		return false;
	} /* }}} */

	/**
	 * Check if document status may be overwritten
	 *
	 * This check can only be done for documents. Overwriting the document
	 * status is
	 * only allowed if this is turned on in the settings and the current
	 * status is either 'releaѕed' or 'obsoleted'.
	 * The admin may even modify the status
	 * even if is disallowed in the settings.
	 */
	function mayOverwriteStatus() { /* {{{ */
		if(get_class($this->obj) == $this->dms->getClassname('document')) {
			$latestContent = $this->obj->getLatestContent();
			$status = $latestContent->getStatus();
			if ((($this->settings->_enableVersionModification && ($this->obj->getAccessMode($this->user) == M_ALL)) || $this->user->isAdmin()) && ($status["status"]==S_RELEASED || $status["status"]==S_OBSOLETE )) {
				return true;
			}
		}
		return false;
	} /* }}} */

	/**
	 * Check if reviewers/approvers may be edited
	 *
	 * This check can only be done for documents. Overwriting the document
	 * reviewers/approvers is only allowed if version modification is turned on
	 * in the settings and the document has not been reviewed/approved by any
	 * user/group already.
	 * The admin may even set reviewers/approvers if is disallowed in the
	 * settings.
	 */
	function maySetReviewersApprovers() { /* {{{ */
		if(get_class($this->obj) == $this->dms->getClassname('document')) {
			$latestContent = $this->obj->getLatestContent();
			$status = $latestContent->getStatus();
			$reviewstatus = $latestContent->getReviewStatus();
			$hasreview = false;
			foreach($reviewstatus as $r) {
				if($r['status'] == 1 || $r['status'] == -1)
					$hasreview = true;
			}
			$approvalstatus = $latestContent->getApprovalStatus();
			$hasapproval = false;
			foreach($approvalstatus as $r) {
				if($r['status'] == 1 || $r['status'] == -1)
					$hasapproval = true;
			}
			if ((($this->settings->_enableVersionModification && ($this->obj->getAccessMode($this->user) == M_ALL)) || $this->user->isAdmin()) && (($status["status"]==S_DRAFT_REV && !$hasreview) || ($status["status"]==S_DRAFT_APP && !$hasreview && !$hasapproval))) {
				return true;
			}
		}
		return false;
	} /* }}} */

	/**
	 * Check if workflow may be edited
	 *
	 * This check can only be done for documents. Overwriting the document
	 * workflow is only allowed if version modification is turned on
	 * in the settings and the document is in it's initial status.  The
	 * admin may even set the workflow if is disallowed in the
	 * settings.
	 */
	function maySetWorkflow() { /* {{{ */
		if(get_class($this->obj) == $this->dms->getClassname('document')) {
			$latestContent = $this->obj->getLatestContent();
			$workflow = $latestContent->getWorkflow();
			if ((($this->settings->_enableVersionModification && ($this->obj->getAccessMode($this->user) == M_ALL)) || $this->user->isAdmin()) && (!$workflow || ($workflow->getInitState()->getID() == $latestContent->getWorkflowState()->getID()))) {
				return true;
			}
		}
		return false;
	} /* }}} */

	/**
	 * Check if expiration date may be set
	 *
	 * This check can only be done for documents. Setting the documents
	 * expiration date is only allowed if the document has not been obsoleted.
	 */
	function maySetExpires() { /* {{{ */
		if(get_class($this->obj) == $this->dms->getClassname('document')) {
			$latestContent = $this->obj->getLatestContent();
			$status = $latestContent->getStatus();
			if ((($this->obj->getAccessMode($this->user) == M_ALL) || $this->user->isAdmin()) && ($status["status"]!=S_OBSOLETE)) {
				return true;
			}
		}
		return false;
	} /* }}} */

	/**
	 * Check if comment may be edited
	 *
	 * This check can only be done for documents. Setting the documents
	 * comment date is only allowed if version modification is turned on in
	 * the settings and the document has not been obsoleted.
	 * The admin may set the comment even if is
	 * disallowed in the settings.
	 */
	function mayEditComment() { /* {{{ */
		if(get_class($this->obj) == $this->dms->getClassname('document')) {
			if($this->obj->isLocked()) {
				$lockingUser = $this->obj->getLockingUser();
				if (($lockingUser->getID() != $this->user->getID()) && ($this->obj->getAccessMode($this->user) != M_ALL)) {
					return false;
				}
			}
			$latestContent = $this->obj->getLatestContent();
			$status = $latestContent->getStatus();
			if ((($this->settings->_enableVersionModification && ($this->obj->getAccessMode($this->user) >= M_READWRITE)) || $this->user->isAdmin()) && ($status["status"]!=S_OBSOLETE)) {
				return true;
			}
		}
		return false;
	} /* }}} */

	/**
	 * Check if attributes may be edited
	 *
	 * Setting the object attributes
	 * is only allowed if version modification is turned on in
	 * the settings and the document has not been obsoleted.
	 * The admin may set the comment even if is
	 * disallowed in the settings.
	 */
	function mayEditAttributes() { /* {{{ */
		if(get_class($this->obj) == $this->dms->getClassname('document')) {
			$latestContent = $this->obj->getLatestContent();
			$status = $latestContent->getStatus();
			$workflow = $latestContent->getWorkflow();
			if ((($this->settings->_enableVersionModification && ($this->obj->getAccessMode($this->user) >= M_READWRITE)) || $this->user->isAdmin()) && ($status["status"]==S_DRAFT_REV || ($workflow && $workflow->getInitState()->getID() == $latestContent->getWorkflowState()->getID()))) {
				return true;
			}
		}
		return false;
	} /* }}} */

	/**
	 * Check if document content may be reviewed
	 *
	 * Reviewing a document content is only allowed if the document is in
	 * review. There are other requirements which are not taken into
	 * account here.
	 */
	function mayReview() { /* {{{ */
		if(get_class($this->obj) == $this->dms->getClassname('document')) {
			$latestContent = $this->obj->getLatestContent();
			$status = $latestContent->getStatus();
			if ($status["status"]==S_DRAFT_REV) {
				return true;
			}
		}
		return false;
	} /* }}} */

	/**
	 * Check if a review maybe edited
	 *
	 * A review may only be updated by the user who originaly addedd the
	 * review and if it is allowed in the settings
	 */
	function mayUpdateReview($updateUser) { /* {{{ */
		if(get_class($this->obj) == 'LetoDMS_Core_Document') {
			if($this->settings->_enableUpdateRevApp && ($updateUser == $this->user) && !$this->obj->hasExpired()) {
				return true;
			}
		}
		return false;
	} /* }}} */

	/**
	 * Check if document content may be approved
	 *
	 * Approving a document content is only allowed if the document is either
	 * in approval status or released. In the second case the approval can be
	 * edited.
	 * There are other requirements which are not taken into
	 * account here.
	 */
	function mayApprove() { /* {{{ */
		if(get_class($this->obj) == $this->dms->getClassname('document')) {
			$latestContent = $this->obj->getLatestContent();
			$status = $latestContent->getStatus();
			if ($status["status"]==S_DRAFT_APP) {
				return true;
			}
		}
		return false;
	} /* }}} */

	/**
	 * Check if a approval maybe edited
	 *
	 * An approval may only be updated by the user who originaly addedd the
	 * approval and if it is allowed in the settings
	 */
	function mayUpdateApproval($updateUser) { /* {{{ */
		if(get_class($this->obj) == 'LetoDMS_Core_Document') {
			if($this->settings->_enableUpdateRevApp && ($updateUser == $this->user) && !$this->obj->hasExpired()) {
				return true;
			}
		}
		return false;
	} /* }}} */
}
?>
