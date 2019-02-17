<?php
/**
 * Implementation of a document in the document management system
 *
 * @category   DMS
 * @package    LetoDMS_Core
 * @license    GPL2
 * @author     Markus Westphal, Malcolm Cowe, Matteo Lucarelli,
 *             Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal, 2006-2008 Malcolm Cowe,
 *             2010 Matteo Lucarelli, 2010 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * The different states a document can be in
 */
/*
 * Document is in review state. A document is in review state when
 * it needs to be reviewed by a user or group.
 */
define("S_DRAFT_REV", 0);

/*
 * Document is in approval state. A document is in approval state when
 * it needs to be approved by a user or group.
 */
define("S_DRAFT_APP", 1);

/*
 * Document is released. A document is in release state either when
 * it needs no review or approval after uploaded or has been reviewed
 * and/or approved.
 */
define("S_RELEASED",  2);

/*
 * Document is in workflow. A document is in workflow if a workflow
 * has been started and has not reached a final state.
 */
define("S_IN_WORKFLOW",  3);

/*
 * Document was rejected. A document is in rejected state when
 * the review failed or approval was not given.
 */
define("S_REJECTED", -1);

/*
 * Document is obsolete. A document can be obsoleted once it was
 * released.
 */
define("S_OBSOLETE", -2);

/*
 * Document is expired. A document expires when the expiration date
 * is reached
 */
define("S_EXPIRED",  -3);

/**
 * The different states a workflow log can be in. This is used in
 * all tables tblDocumentXXXLog
 */
/*
 * workflow is in a neutral status waiting for action of user
 */
define("S_LOG_WAITING",  0);

/*
 * workflow has been successful ended. The document content has been
 * approved, reviewed, aknowledged or revised
 */
define("S_LOG_ACCEPTED",  1);

/*
 * workflow has been unsuccessful ended. The document content has been
 * rejected
 */
define("S_LOG_REJECTED",  -1);

/*
 * user has been removed from workflow. This can be for different reasons
 * 1. the user has been actively removed from the workflow, 2. the user has
 * been deleted.
 */
define("S_LOG_USER_REMOVED",  -2);

/*
 * workflow is sleeping until reactivation. The workflow has been set up
 * but not started. This is only valid for the revision workflow, which
 * may run over and over again.
 */
define("S_LOG_SLEEPING",  -3);

/**
 * Class to represent a document in the document management system
 *
 * A document in LetoDMS is similar to files in a regular file system.
 * Documents may have any number of content elements
 * ({@link LetoDMS_Core_DocumentContent}). These content elements are often
 * called versions ordered in a timely manner. The most recent content element
 * is the current version.
 *
 * Documents can be linked to other documents and can have attached files.
 * The document content can be anything that can be stored in a regular
 * file.
 *
 * @category   DMS
 * @package    LetoDMS_Core
 * @author     Markus Westphal, Malcolm Cowe, Matteo Lucarelli,
 *             Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal, 2006-2008 Malcolm Cowe,
 *             2010 Matteo Lucarelli, 2010 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Core_Document extends LetoDMS_Core_Object { /* {{{ */
	/**
	 * @var string name of document
	 */
	protected $_name;

	/**
	 * @var string comment of document
	 */
	protected $_comment;

	/**
	 * @var integer unix timestamp of creation date
	 */
	protected $_date;

	/**
	 * @var integer id of user who is the owner
	 */
	protected $_ownerID;

	/**
	 * @var integer id of folder this document belongs to
	 */
	protected $_folderID;

	/**
	 * @var integer timestamp of expiration date
	 */
	protected $_expires;

	/**
	 * @var boolean true if access is inherited, otherwise false
	 */
	protected $_inheritAccess;

	/**
	 * @var integer default access if access rights are not inherited
	 */
	protected $_defaultAccess;

	/**
	 * @var array list of notifications for users and groups
	 */
	protected $_readAccessList;

	/**
	 * @var array list of notifications for users and groups
	 */
	public $_notifyList;

	/**
	 * @var boolean true if document is locked, otherwise false
	 */
	protected $_locked;

	/**
	 * @var string list of keywords
	 */
	protected $_keywords;

	/**
	 * @var LetoDMS_Core_DocumentCategory[] list of categories
	 */
	protected $_categories;

	/**
	 * @var integer position of document within the parent folder
	 */
	protected $_sequence;

	/**
	 * @var LetoDMS_Core_DocumentContent temp. storage for latestcontent
	 */
	protected $_latestContent;

	/**
	 * @var array temp. storage for content
	 */
	protected $_content;

	/**
	 * @var LetoDMS_Core_Folder
	 */
	protected $_folder;

	/** @var array of LetoDMS_Core_UserAccess and LetoDMS_Core_GroupAccess */
	protected $_accessList;

	function __construct($id, $name, $comment, $date, $expires, $ownerID, $folderID, $inheritAccess, $defaultAccess, $locked, $keywords, $sequence) { /* {{{ */
		parent::__construct($id);
		$this->_name = $name;
		$this->_comment = $comment;
		$this->_date = $date;
		$this->_expires = $expires;
		$this->_ownerID = $ownerID;
		$this->_folderID = $folderID;
		$this->_inheritAccess = $inheritAccess;
		$this->_defaultAccess = $defaultAccess;
		$this->_locked = ($locked == null || $locked == '' ? -1 : $locked);
		$this->_keywords = $keywords;
		$this->_sequence = $sequence;
		$this->_categories = array();
		$this->_notifyList = array();
		$this->_latestContent = null;
		$this->_content = null;
	} /* }}} */

	/**
	 * Return an array of database fields which used for searching
	 * a term entered in the database search form
	 *
	 * @param LetoDMS_Core_DMS $dms
	 * @param array $searchin integer list of search scopes (2=name, 3=comment,
	 * 4=attributes)
	 * @return array list of database fields
	 */
	public static function getSearchFields($dms, $searchin) { /* {{{ */
		$db = $dms->getDB();

		$searchFields = array();
		if (in_array(1, $searchin)) {
			$searchFields[] = "`tblDocuments`.`keywords`";
		}
		if (in_array(2, $searchin)) {
			$searchFields[] = "`tblDocuments`.`name`";
		}
		if (in_array(3, $searchin)) {
			$searchFields[] = "`tblDocuments`.`comment`";
			$searchFields[] = "`tblDocumentContent`.`comment`";
		}
		if (in_array(4, $searchin)) {
			$searchFields[] = "`tblDocumentAttributes`.`value`";
			$searchFields[] = "`tblDocumentContentAttributes`.`value`";
		}
		if (in_array(5, $searchin)) {
			$searchFields[] = $db->castToText("`tblDocuments`.`id`");
		}

		return $searchFields;
	} /* }}} */

	/**
	 * Return an document by its id
	 *
	 * @param integer $id id of document
	 * @param LetoDMS_Core_DMS $dms
	 * @return bool|LetoDMS_Core_Document instance of LetoDMS_Core_Document if document exists, null
	 * if document does not exist, false in case of error
	 */
	public static function getInstance($id, $dms) { /* {{{ */
		$db = $dms->getDB();

		$queryStr = "SELECT * FROM `tblDocuments` WHERE `id` = " . (int) $id;
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;
		if (count($resArr) != 1)
			return null;
		$resArr = $resArr[0];

		// New Locking mechanism uses a separate table to track the lock.
		$queryStr = "SELECT * FROM `tblDocumentLocks` WHERE `document` = " . (int) $id;
		$lockArr = $db->getResultArray($queryStr);
		if ((is_bool($lockArr) && $lockArr==false) || (count($lockArr)==0)) {
			// Could not find a lock on the selected document.
			$lock = -1;
		}
		else {
			// A lock has been identified for this document.
			$lock = $lockArr[0]["userID"];
		}

		$classname = $dms->getClassname('document');
		/** @var LetoDMS_Core_Document $document */
		$document = new $classname($resArr["id"], $resArr["name"], $resArr["comment"], $resArr["date"], $resArr["expires"], $resArr["owner"], $resArr["folder"], $resArr["inheritAccess"], $resArr["defaultAccess"], $lock, $resArr["keywords"], $resArr["sequence"]);
		$document->setDMS($dms);
		return $document;
	} /* }}} */

	/**
	 * Return the directory of the document in the file system relativ
	 * to the contentDir
	 *
	 * @return string directory of document
	 */
	function getDir() { /* {{{ */
		if($this->_dms->maxDirID) {
			$dirid = (int) (($this->_id-1) / $this->_dms->maxDirID) + 1;
			return $dirid."/".$this->_id."/";
		} else {
			return $this->_id."/";
		}
	} /* }}} */

	/**
	 * Return the name of the document
	 *
	 * @return string name of document
	 */
	function getName() { return $this->_name; }

	/**
	 * Set the name of the document
	 *
	 * @param $newName string new name of document
	 * @return bool
	 */
	function setName($newName) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE `tblDocuments` SET `name` = ".$db->qstr($newName)." WHERE `id` = ". $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_name = $newName;
		return true;
	} /* }}} */

	/**
	 * Return the comment of the document
	 *
	 * @return string comment of document
	 */
	function getComment() { return $this->_comment; }

	/**
	 * Set the comment of the document
	 *
	 * @param $newComment string new comment of document
	 * @return bool
	 */
	function setComment($newComment) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE `tblDocuments` SET `comment` = ".$db->qstr($newComment)." WHERE `id` = ". $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_comment = $newComment;
		return true;
	} /* }}} */

	/**
	 * @return string
	 */
	function getKeywords() { return $this->_keywords; }

	/**
	 * @param string $newKeywords
	 * @return bool
	 */
	function setKeywords($newKeywords) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE `tblDocuments` SET `keywords` = ".$db->qstr($newKeywords)." WHERE `id` = ". $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_keywords = $newKeywords;
		return true;
	} /* }}} */

	/**
	 * Retrieve a list of all categories this document belongs to
	 *
	 * @return bool|LetoDMS_Core_DocumentCategory[]
	 */
	function getCategories() { /* {{{ */
		$db = $this->_dms->getDB();

		if(!$this->_categories) {
			$queryStr = "SELECT * FROM `tblCategory` where `id` in (select `categoryID` from `tblDocumentCategory` where `documentID` = ".$this->_id.")";
			$resArr = $db->getResultArray($queryStr);
			if (is_bool($resArr) && !$resArr)
				return false;

			foreach ($resArr as $row) {
				$cat = new LetoDMS_Core_DocumentCategory($row['id'], $row['name']);
				$cat->setDMS($this->_dms);
				$this->_categories[] = $cat;
			}
		}
		return $this->_categories;
	} /* }}} */

	/**
	 * Set a list of categories for the document
	 * This function will delete currently assigned categories and sets new
	 * categories.
	 *
	 * @param LetoDMS_Core_DocumentCategory[] $newCategories list of category objects
	 * @return bool
	 */
	function setCategories($newCategories) { /* {{{ */
		$db = $this->_dms->getDB();

		$db->startTransaction();
		$queryStr = "DELETE from `tblDocumentCategory` WHERE `documentID` = ". $this->_id;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		foreach($newCategories as $cat) {
			$queryStr = "INSERT INTO `tblDocumentCategory` (`categoryID`, `documentID`) VALUES (". $cat->getId() .", ". $this->_id .")";
			if (!$db->getResult($queryStr)) {
				$db->rollbackTransaction();
				return false;
			}
		}

		$db->commitTransaction();
		$this->_categories = $newCategories;
		return true;
	} /* }}} */

	/**
	 * Add a list of categories to the document
	 * This function will add a list of new categories to the document.
	 *
	 * @param array $newCategories list of category objects
	 */
	function addCategories($newCategories) { /* {{{ */
		$db = $this->_dms->getDB();

		if(!$this->_categories)
			self::getCategories();

		$catids = array();
		foreach($this->_categories as $cat)
			$catids[] = $cat->getID();

		$db->startTransaction();
		$ncat = array(); // Array containing actually added new categories
		foreach($newCategories as $cat) {
			if(!in_array($cat->getID(), $catids)) {
				$queryStr = "INSERT INTO `tblDocumentCategory` (`categoryID`, `documentID`) VALUES (". $cat->getId() .", ". $this->_id .")";
				if (!$db->getResult($queryStr)) {
					$db->rollbackTransaction();
					return false;
				}
				$ncat[] = $cat;
			}
		}
		$db->commitTransaction();
		$this->_categories = array_merge($this->_categories, $ncat);
		return true;
	} /* }}} */

	/**
	 * Remove a list of categories from the document
	 * This function will remove a list of assigned categories to the document.
	 *
	 * @param array $newCategories list of category objects
	 */
	function removeCategories($categories) { /* {{{ */
		$db = $this->_dms->getDB();

		$catids = array();
		foreach($categories as $cat)
			$catids[] = $cat->getID();

		$queryStr = "DELETE from `tblDocumentCategory` WHERE `documentID` = ". $this->_id ." AND `categoryID` IN (".implode(',', $catids).")";
		if (!$db->getResult($queryStr)) {
			return false;
		}

		$this->_categories = null;
		return true;
	} /* }}} */

	/**
	 * Return creation date of the document
	 *
	 * @return integer unix timestamp of creation date
	 */
	function getDate() { /* {{{ */
		return $this->_date;
	} /* }}} */

	/**
	 * Set creation date of the document
	 *
	 * @param integer $date timestamp of creation date. If false then set it
	 * to the current timestamp
	 * @return boolean true on success
	 */
	function setDate($date) { /* {{{ */
		$db = $this->_dms->getDB();

		if(!$date)
			$date = time();
		else {
			if(!is_numeric($date))
				return false;
		}

		$queryStr = "UPDATE `tblDocuments` SET `date` = " . (int) $date . " WHERE `id` = ". $this->_id;
		if (!$db->getResult($queryStr))
			return false;
		$this->_date = $date;
		return true;
	} /* }}} */

	/**
	 * Return the parent folder of the document
	 *
	 * @return LetoDMS_Core_Folder parent folder
	 */
	function getParent() { /* {{{ */
		return self::getFolder();
	} /* }}} */

	function getFolder() { /* {{{ */
		if (!isset($this->_folder))
			$this->_folder = $this->_dms->getFolder($this->_folderID);
		return $this->_folder;
	} /* }}} */

	/**
	 * Set folder of a document
	 *
	 * This function basically moves a document from a folder to another
	 * folder.
	 *
	 * @param LetoDMS_Core_Folder $newFolder
	 * @return boolean false in case of an error, otherwise true
	 */
	function setFolder($newFolder) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE `tblDocuments` SET `folder` = " . $newFolder->getID() . " WHERE `id` = ". $this->_id;
		if (!$db->getResult($queryStr))
			return false;
		$this->_folderID = $newFolder->getID();
		/** @noinspection PhpUndefinedFieldInspection */
		$this->_folder = $newFolder;

		// Make sure that the folder search path is also updated.
		$path = $newFolder->getPath();
		$flist = "";
		/** @var LetoDMS_Core_Folder[] $path */
		foreach ($path as $f) {
			$flist .= ":".$f->getID();
		}
		if (strlen($flist)>1) {
			$flist .= ":";
		}
		$queryStr = "UPDATE `tblDocuments` SET `folderList` = '" . $flist . "' WHERE `id` = ". $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		return true;
	} /* }}} */

	/**
	 * Return owner of document
	 *
	 * @return LetoDMS_Core_User owner of document as an instance of {@link LetoDMS_Core_User}
	 */
	function getOwner() { /* {{{ */
		if (!isset($this->_owner))
			$this->_owner = $this->_dms->getUser($this->_ownerID);
		return $this->_owner;
	} /* }}} */

	/**
	 * Set owner of a document
	 *
	 * @param LetoDMS_Core_User $newOwner new owner
	 * @return boolean true if successful otherwise false
	 */
	function setOwner($newOwner) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE `tblDocuments` set `owner` = " . $newOwner->getID() . " WHERE `id` = " . $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_ownerID = $newOwner->getID();
		/** @noinspection PhpUndefinedFieldInspection */
		$this->_owner = $newOwner;
		return true;
	} /* }}} */

	/**
	 * @return bool|int
	 */
	function getDefaultAccess() { /* {{{ */
		if ($this->inheritsAccess()) {
			$res = $this->getFolder();
			if (!$res) return false;
			return $this->_folder->getDefaultAccess();
		}
		return $this->_defaultAccess;
	} /* }}} */

	/**
	 * Set default access mode
	 *
	 * This method sets the default access mode and also removes all notifiers which
	 * will not have read access anymore.
	 *
	 * @param integer $mode access mode
	 * @param bool|string $noclean set to true if notifier list shall not be clean up
	 * @return bool
	 */
	function setDefaultAccess($mode, $noclean="false") { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE `tblDocuments` set `defaultAccess` = " . (int) $mode . " WHERE `id` = " . $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_defaultAccess = $mode;

		if(!$noclean)
			self::cleanNotifyList();

		return true;
	} /* }}} */

	/**
	 * @return bool
	 */
	function inheritsAccess() { return $this->_inheritAccess; }

	/**
	 * Set inherited access mode
	 * Setting inherited access mode will set or unset the internal flag which
	 * controls if the access mode is inherited from the parent folder or not.
	 * It will not modify the
	 * access control list for the current object. It will remove all
	 * notifications of users which do not even have read access anymore
	 * after setting or unsetting inherited access.
	 *
	 * @param boolean $inheritAccess set to true for setting and false for
	 *        unsetting inherited access mode
	 * @param boolean $noclean set to true if notifier list shall not be clean up
	 * @return boolean true if operation was successful otherwise false
	 */
	function setInheritAccess($inheritAccess, $noclean=false) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE `tblDocuments` SET `inheritAccess` = " . ($inheritAccess ? "1" : "0") . " WHERE `id` = " . $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_inheritAccess = ($inheritAccess ? "1" : "0");

		if(!$noclean)
			self::cleanNotifyList();

		return true;
	} /* }}} */

	/**
	 * Check if document expires
	 *
	 * @return boolean true if document has expiration date set, otherwise false
	 */
	function expires() { /* {{{ */
		if (intval($this->_expires) == 0)
			return false;
		else
			return true;
	} /* }}} */

	/**
	 * Get expiration time of document
	 *
	 * @return integer/boolean expiration date as unix timestamp or false
	 */
	function getExpires() { /* {{{ */
		if (intval($this->_expires) == 0)
			return false;
		else
			return $this->_expires;
	} /* }}} */

	/**
	 * Set expiration date as unix timestamp
	 *
	 * @param integer $expires unix timestamp of expiration date
	 * @return bool
	 */
	function setExpires($expires) { /* {{{ */
		$db = $this->_dms->getDB();

		$expires = (!$expires) ? 0 : $expires;

		if ($expires == $this->_expires) {
			// No change is necessary.
			return true;
		}

		$queryStr = "UPDATE `tblDocuments` SET `expires` = " . (int) $expires . " WHERE `id` = " . $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_expires = $expires;
		return true;
	} /* }}} */

	/**
	 * Check if the document has expired
	 *
	 * @return boolean true if document has expired otherwise false
	 */
	function hasExpired() { /* {{{ */
		if (intval($this->_expires) == 0) return false;
		if (time()>$this->_expires+24*60*60) return true;
		return false;
	} /* }}} */

	/**
	 * Check if the document has expired and set the status accordingly
	 * It will also recalculate the status if the current status is
	 * set to S_EXPIRED but the document isn't actually expired.
	 * The method will update the document status log database table
	 * if needed.
	 * FIXME: some left over reviewers/approvers are in the way if
	 * no workflow is set and traditional workflow mode is on. In that
	 * case the status is set to S_DRAFT_REV or S_DRAFT_APP
	 *
	 * @return boolean true if status has changed
	 */
	function verifyLastestContentExpriry(){ /* {{{ */
		$lc=$this->getLatestContent();
		if($lc) {
			$st=$lc->getStatus();

			if (($st["status"]==S_DRAFT_REV || $st["status"]==S_DRAFT_APP || $st["status"]==S_IN_WORKFLOW || $st["status"]==S_RELEASED) && $this->hasExpired()){
				return $lc->setStatus(S_EXPIRED,"", $this->getOwner());
			}
			elseif ($st["status"]==S_EXPIRED && !$this->hasExpired() ){
				$lc->verifyStatus(true, $this->getOwner());
				return true;
			}
		}
		return false;
	} /* }}} */

	/**
	 * Check if document is locked
	 *
	 * @return boolean true if locked otherwise false
	 */
	function isLocked() { return $this->_locked != -1; }

	/**
	 * Lock or unlock document
	 *
	 * @param LetoDMS_Core_User|bool $falseOrUser user object for locking or false for unlocking
	 * @return boolean true if operation was successful otherwise false
	 */
	function setLocked($falseOrUser) { /* {{{ */
		$db = $this->_dms->getDB();

		$lockUserID = -1;
		if (is_bool($falseOrUser) && !$falseOrUser) {
			$queryStr = "DELETE FROM `tblDocumentLocks` WHERE `document` = ".$this->_id;
		}
		else if (is_object($falseOrUser)) {
			$queryStr = "INSERT INTO `tblDocumentLocks` (`document`, `userID`) VALUES (".$this->_id.", ".$falseOrUser->getID().")";
			$lockUserID = $falseOrUser->getID();
		}
		else {
			return false;
		}
		if (!$db->getResult($queryStr)) {
			return false;
		}
		unset($this->_lockingUser);
		$this->_locked = $lockUserID;
		return true;
	} /* }}} */

	/**
	 * Get the user currently locking the document
	 *
	 * @return LetoDMS_Core_User|bool user have a lock
	 */
	function getLockingUser() { /* {{{ */
		if (!$this->isLocked())
			return false;

		if (!isset($this->_lockingUser))
			$this->_lockingUser = $this->_dms->getUser($this->_locked);
		return $this->_lockingUser;
	} /* }}} */

	/**
	 * @return int
	 */
	function getSequence() { return $this->_sequence; }

	/**
	 * @param $seq
	 * @return bool
	 */
	function setSequence($seq) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE `tblDocuments` SET `sequence` = " . $seq . " WHERE `id` = " . $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_sequence = $seq;
		return true;
	} /* }}} */

	/**
	 * Delete all entries for this document from the access control list
	 *
	 * @param boolean $noclean set to true if notifier list shall not be clean up
	 * @return boolean true if operation was successful otherwise false
	 */
	function clearAccessList($noclean=false) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "DELETE FROM `tblACLs` WHERE `targetType` = " . T_DOCUMENT . " AND `target` = " . $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		unset($this->_accessList);

		if(!$noclean)
			self::cleanNotifyList();

		return true;
	} /* }}} */

	/**
	 * Returns a list of access privileges
	 *
	 * If the document inherits the access privileges from the parent folder
	 * those will be returned.
	 * $mode and $op can be set to restrict the list of returned access
	 * privileges. If $mode is set to M_ANY no restriction will apply
	 * regardless of the value of $op. The returned array contains a list
	 * of {@link LetoDMS_Core_UserAccess} and
	 * {@link LetoDMS_Core_GroupAccess} objects. Even if the document
	 * has no access list the returned array contains the two elements
	 * 'users' and 'groups' which are than empty. The methode returns false
	 * if the function fails.
	 *
	 * @param int $mode access mode (defaults to M_ANY)
	 * @param int|string $op operation (defaults to O_EQ)
	 * @return bool|array
	 */
	function getAccessList($mode = M_ANY, $op = O_EQ) { /* {{{ */
		$db = $this->_dms->getDB();

		if ($this->inheritsAccess()) {
			$res = $this->getFolder();
			if (!$res) return false;
			return $this->_folder->getAccessList($mode, $op);
		}

		if (!isset($this->_accessList[$mode])) {
			if ($op!=O_GTEQ && $op!=O_LTEQ && $op!=O_EQ) {
				return false;
			}
			$modeStr = "";
			if ($mode!=M_ANY) {
				$modeStr = " AND mode".$op.(int)$mode;
			}
			$queryStr = "SELECT * FROM `tblACLs` WHERE `targetType` = ".T_DOCUMENT.
				" AND target = " . $this->_id .	$modeStr . " ORDER BY `targetType`";
			$resArr = $db->getResultArray($queryStr);
			if (is_bool($resArr) && !$resArr)
				return false;

			$this->_accessList[$mode] = array("groups" => array(), "users" => array());
			foreach ($resArr as $row) {
				if ($row["userID"] != -1)
					array_push($this->_accessList[$mode]["users"], new LetoDMS_Core_UserAccess($this->_dms->getUser($row["userID"]), $row["mode"]));
				else //if ($row["groupID"] != -1)
					array_push($this->_accessList[$mode]["groups"], new LetoDMS_Core_GroupAccess($this->_dms->getGroup($row["groupID"]), $row["mode"]));
			}
		}

		return $this->_accessList[$mode];
	} /* }}} */

	/**
	 * Add access right to folder
	 * This function may change in the future. Instead of passing the a flag
	 * and a user/group id a user or group object will be expected.
	 *
	 * @param integer $mode access mode
	 * @param integer $userOrGroupID id of user or group
	 * @param integer $isUser set to 1 if $userOrGroupID is the id of a
	 *        user
	 * @return bool
	 */
	function addAccess($mode, $userOrGroupID, $isUser) { /* {{{ */
		$db = $this->_dms->getDB();

		$userOrGroup = ($isUser) ? "`userID`" : "`groupID`";

		$queryStr = "INSERT INTO `tblACLs` (`target`, `targetType`, ".$userOrGroup.", `mode`) VALUES
					(".$this->_id.", ".T_DOCUMENT.", " . (int) $userOrGroupID . ", " .(int) $mode. ")";
		if (!$db->getResult($queryStr))
			return false;

		unset($this->_accessList);

		// Update the notify list, if necessary.
		if ($mode == M_NONE) {
			$this->removeNotify($userOrGroupID, $isUser);
		}

		return true;
	} /* }}} */

	/**
	 * Change access right of document
	 * This function may change in the future. Instead of passing the a flag
	 * and a user/group id a user or group object will be expected.
	 *
	 * @param integer $newMode access mode
	 * @param integer $userOrGroupID id of user or group
	 * @param integer $isUser set to 1 if $userOrGroupID is the id of a
	 *        user
	 * @return bool
	 */
	function changeAccess($newMode, $userOrGroupID, $isUser) { /* {{{ */
		$db = $this->_dms->getDB();

		$userOrGroup = ($isUser) ? "`userID`" : "`groupID`";

		$queryStr = "UPDATE `tblACLs` SET `mode` = " . (int) $newMode . " WHERE `targetType` = ".T_DOCUMENT." AND `target` = " . $this->_id . " AND " . $userOrGroup . " = " . (int) $userOrGroupID;
		if (!$db->getResult($queryStr))
			return false;

		unset($this->_accessList);

		// Update the notify list, if necessary.
		if ($newMode == M_NONE) {
			$this->removeNotify($userOrGroupID, $isUser);
		}

		return true;
	} /* }}} */

	/**
	 * Remove access rights for a user or group
	 *
	 * @param integer $userOrGroupID ID of user or group
	 * @param boolean $isUser true if $userOrGroupID is a user id, false if it
	 *        is a group id.
	 * @return boolean true on success, otherwise false
	 */
	function removeAccess($userOrGroupID, $isUser) { /* {{{ */
		$db = $this->_dms->getDB();

		$userOrGroup = ($isUser) ? "`userID`" : "`groupID`";

		$queryStr = "DELETE FROM `tblACLs` WHERE `targetType` = ".T_DOCUMENT." AND `target` = ".$this->_id." AND ".$userOrGroup." = " . (int) $userOrGroupID;
		if (!$db->getResult($queryStr))
			return false;

		unset($this->_accessList);

		// Update the notify list, if the user looses access rights.
		$mode = ($isUser ? $this->getAccessMode($this->_dms->getUser($userOrGroupID)) : $this->getGroupAccessMode($this->_dms->getGroup($userOrGroupID)));
		if ($mode == M_NONE) {
			$this->removeNotify($userOrGroupID, $isUser);
		}

		return true;
	} /* }}} */

	/**
	 * Returns the greatest access privilege for a given user
	 *
	 * This function returns the access mode for a given user. An administrator
	 * and the owner of the folder has unrestricted access. A guest user has
	 * read only access or no access if access rights are further limited
	 * by access control lists. All other users have access rights according
	 * to the access control lists or the default access. This function will
	 * recursive check for access rights of parent folders if access rights
	 * are inherited.
	 *
	 * The function searches the access control list for entries of
	 * user $user. If it finds more than one entry it will return the
	 * one allowing the greatest privileges, but user rights will always
	 * precede group rights. If there is no entry in the
	 * access control list, it will return the default access mode.
	 * The function takes inherited access rights into account.
	 * For a list of possible access rights see @file inc.AccessUtils.php
	 *
	 * Having access on a document does not necessarily mean the document
	 * content is accessible too. Accessing the content is checked by
	 * {@link LetoDMS_Core_DocumentContent::getAccessMode()} which calls
	 * a callback function defined by the application. If the callback
	 * function is not set, access on the content is always granted.
	 *
	 * Before checking the access in the method itself a callback 'onCheckAccessDocument'
	 * is called. If it returns a value > 0, then this will be returned by this
	 * method without any further checks. The optional paramater $context
	 * will be passed as a third parameter to the callback. It contains
	 * the operation for which the access mode is retrieved. It is for example
	 * set to 'removeDocument' if the access mode is used to check for sufficient
	 * permission on deleting a document.
	 *
	 * @param $user object instance of class LetoDMS_Core_User
	 * @param string $context context in which the access mode is requested
	 * @return integer access mode
	 */
	function getAccessMode($user, $context='') { /* {{{ */
		if(!$user)
			return M_NONE;

		/* Check if 'onCheckAccessDocument' callback is set */
		if(isset($this->_dms->callbacks['onCheckAccessDocument'])) {
			foreach($this->_dms->callbacks['onCheckAccessDocument'] as $callback) {
				if(($ret = call_user_func($callback[0], $callback[1], $this, $user, $context)) > 0) {
					return $ret;
				}
			}
		}

		/* Administrators have unrestricted access */
		if ($user->isAdmin()) return M_ALL;

		/* The owner of the document has unrestricted access */
		if ($user->getID() == $this->_ownerID) return M_ALL;

		/* Check ACLs */
		$accessList = $this->getAccessList();
		if (!$accessList) return false;

		/** @var LetoDMS_Core_UserAccess $userAccess */
		foreach ($accessList["users"] as $userAccess) {
			if ($userAccess->getUserID() == $user->getID()) {
				$mode = $userAccess->getMode();
				if ($user->isGuest()) {
					if ($mode >= M_READ) $mode = M_READ;
				}
				return $mode;
			}
		}

		/* Get the highest right defined by a group */
		if($accessList['groups']) {
			$mode = 0;
			/** @var LetoDMS_Core_GroupAccess $groupAccess */
			foreach ($accessList["groups"] as $groupAccess) {
				if ($user->isMemberOfGroup($groupAccess->getGroup())) {
					if ($groupAccess->getMode() > $mode)
						$mode = $groupAccess->getMode();
				}
			}
			if($mode) {
				if ($user->isGuest()) {
					if ($mode >= M_READ) $mode = M_READ;
				}
				return $mode;
			}
		}

		$mode = $this->getDefaultAccess();
		if ($user->isGuest()) {
			if ($mode >= M_READ) $mode = M_READ;
		}
		return $mode;
	} /* }}} */

	/**
	 * Returns the greatest access privilege for a given group
	 *
	 * This function searches the access control list for entries of
	 * group $group. If it finds more than one entry it will return the
	 * one allowing the greatest privileges. If there is no entry in the
	 * access control list, it will return the default access mode.
	 * The function takes inherited access rights into account.
	 * For a list of possible access rights see @file inc.AccessUtils.php
	 *
	 * @param LetoDMS_Core_Group $group object instance of class LetoDMS_Core_Group
	 * @return integer access mode
	 */
	function getGroupAccessMode($group) { /* {{{ */
		$highestPrivileged = M_NONE;

		//ACLs durchforsten
		$foundInACL = false;
		$accessList = $this->getAccessList();
		if (!$accessList)
			return false;

		/** @var LetoDMS_Core_GroupAccess $groupAccess */
		foreach ($accessList["groups"] as $groupAccess) {
			if ($groupAccess->getGroupID() == $group->getID()) {
				$foundInACL = true;
				if ($groupAccess->getMode() > $highestPrivileged)
					$highestPrivileged = $groupAccess->getMode();
				if ($highestPrivileged == M_ALL) // max access right -> skip the rest
					return $highestPrivileged;
			}
		}

		if ($foundInACL)
			return $highestPrivileged;

		//Standard-Berechtigung verwenden
		return $this->getDefaultAccess();
	} /* }}} */

	/**
	 * Returns a list of all notifications
	 *
	 * The returned list has two elements called 'users' and 'groups'. Each one
	 * is an array itself countaining objects of class LetoDMS_Core_User and
	 * LetoDMS_Core_Group.
	 *
	 * @param integer $type type of notification (not yet used)
	 * @param bool $incdisabled set to true if disabled user shall be included
	 * @return array|bool
	 */
	function getNotifyList($type=0, $incdisabled=false) { /* {{{ */
		if (empty($this->_notifyList)) {
			$db = $this->_dms->getDB();

			$queryStr ="SELECT * FROM `tblNotify` WHERE `targetType` = " . T_DOCUMENT . " AND `target` = " . $this->_id;
			$resArr = $db->getResultArray($queryStr);
			if (is_bool($resArr) && $resArr == false)
				return false;

			$this->_notifyList = array("groups" => array(), "users" => array());
			foreach ($resArr as $row)
			{
				if ($row["userID"] != -1) {
					$u = $this->_dms->getUser($row["userID"]);
					if($u && (!$u->isDisabled() || $incdisabled))
						array_push($this->_notifyList["users"], $u);
				} else { //if ($row["groupID"] != -1)
					$g = $this->_dms->getGroup($row["groupID"]);
					if($g)
						array_push($this->_notifyList["groups"], $g);
				}
			}
		}
		return $this->_notifyList;
	} /* }}} */

	/**
	 * Make sure only users/groups with read access are in the notify list
	 *
	 */
	function cleanNotifyList() { /* {{{ */
		// If any of the notification subscribers no longer have read access,
		// remove their subscription.
		if (empty($this->_notifyList))
			$this->getNotifyList();

		/* Make a copy of both notifier lists because removeNotify will empty
		 * $this->_notifyList and the second foreach will not work anymore.
		 */
		/** @var LetoDMS_Core_User[] $nusers */
		$nusers = $this->_notifyList["users"];
		/** @var LetoDMS_Core_Group[] $ngroups */
		$ngroups = $this->_notifyList["groups"];
		foreach ($nusers as $u) {
			if ($this->getAccessMode($u) < M_READ) {
				$this->removeNotify($u->getID(), true);
			}
		}
		foreach ($ngroups as $g) {
			if ($this->getGroupAccessMode($g) < M_READ) {
				$this->removeNotify($g->getID(), false);
			}
		}
	} /* }}} */

	/**
	 * Add a user/group to the notification list
	 * This function does not check if the currently logged in user
	 * is allowed to add a notification. This must be checked by the calling
	 * application.
	 *
	 * @param $userOrGroupID integer id of user or group to add
	 * @param $isUser integer 1 if $userOrGroupID is a user,
	 *                0 if $userOrGroupID is a group
	 * @return integer  0: Update successful.
	 *                 -1: Invalid User/Group ID.
	 *                 -2: Target User / Group does not have read access.
	 *                 -3: User is already subscribed.
	 *                 -4: Database / internal error.
	 */
	function addNotify($userOrGroupID, $isUser) { /* {{{ */
		$db = $this->_dms->getDB();

		$userOrGroup = ($isUser ? "`userID`" : "`groupID`");

		/* Verify that user / group exists. */
		$obj = ($isUser ? $this->_dms->getUser($userOrGroupID) : $this->_dms->getGroup($userOrGroupID));
		if (!is_object($obj)) {
			return -1;
		}

		/* Verify that the requesting user has permission to add the target to
		 * the notification system.
		 */
		/*
		 * The calling application should enforce the policy on who is allowed
		 * to add someone to the notification system. If is shall remain here
		 * the currently logged in user should be passed to this function
		 *
		GLOBAL $user;
		if ($user->isGuest()) {
			return -2;
		}
		if (!$user->isAdmin()) {
			if ($isUser) {
				if ($user->getID() != $obj->getID()) {
					return -2;
				}
			}
			else {
				if (!$obj->isMember($user)) {
					return -2;
				}
			}
		}
		 */

		/* Verify that target user / group has read access to the document. */
		if ($isUser) {
			// Users are straightforward to check.
			if ($this->getAccessMode($obj) < M_READ) {
				return -2;
			}
		}
		else {
			// Groups are a little more complex.
			if ($this->getDefaultAccess() >= M_READ) {
				// If the default access is at least READ-ONLY, then just make sure
				// that the current group has not been explicitly excluded.
				$acl = $this->getAccessList(M_NONE, O_EQ);
				$found = false;
				/** @var LetoDMS_Core_GroupAccess $group */
				foreach ($acl["groups"] as $group) {
					if ($group->getGroupID() == $userOrGroupID) {
						$found = true;
						break;
					}
				}
				if ($found) {
					return -2;
				}
			}
			else {
				// The default access is restricted. Make sure that the group has
				// been explicitly allocated access to the document.
				$acl = $this->getAccessList(M_READ, O_GTEQ);
				if (is_bool($acl)) {
					return -4;
				}
				$found = false;
				/** @var LetoDMS_Core_GroupAccess $group */
				foreach ($acl["groups"] as $group) {
					if ($group->getGroupID() == $userOrGroupID) {
						$found = true;
						break;
					}
				}
				if (!$found) {
					return -2;
				}
			}
		}
		/* Check to see if user/group is already on the list. */
		$queryStr = "SELECT * FROM `tblNotify` WHERE `tblNotify`.`target` = '".$this->_id."' ".
			"AND `tblNotify`.`targetType` = '".T_DOCUMENT."' ".
			"AND `tblNotify`.".$userOrGroup." = '".(int) $userOrGroupID."'";
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr)) {
			return -4;
		}
		if (count($resArr)>0) {
			return -3;
		}

		$queryStr = "INSERT INTO `tblNotify` (`target`, `targetType`, " . $userOrGroup . ") VALUES (" . $this->_id . ", " . T_DOCUMENT . ", " . (int) $userOrGroupID . ")";
		if (!$db->getResult($queryStr))
			return -4;

		unset($this->_notifyList);
		return 0;
	} /* }}} */

	/**
	 * Remove a user or group from the notification list
	 * This function does not check if the currently logged in user
	 * is allowed to remove a notification. This must be checked by the calling
	 * application.
	 *
	 * @param integer $userOrGroupID id of user or group
	 * @param boolean $isUser boolean true if a user is passed in $userOrGroupID, false
	 *        if a group is passed in $userOrGroupID
	 * @param integer $type type of notification (0 will delete all) Not used yet!
	 * @return integer 0 if operation was succesful
	 *                 -1 if the userid/groupid is invalid
	 *                 -3 if the user/group is already subscribed
	 *                 -4 in case of an internal database error
	 */
	function removeNotify($userOrGroupID, $isUser, $type=0) { /* {{{ */
		$db = $this->_dms->getDB();

		/* Verify that user / group exists. */
		/** @var LetoDMS_Core_Group|LetoDMS_Core_User $obj */
		$obj = ($isUser ? $this->_dms->getUser($userOrGroupID) : $this->_dms->getGroup($userOrGroupID));
		if (!is_object($obj)) {
			return -1;
		}

		$userOrGroup = ($isUser) ? "`userID`" : "`groupID`";

		/* Verify that the requesting user has permission to add the target to
		 * the notification system.
		 */
		/*
		 * The calling application should enforce the policy on who is allowed
		 * to add someone to the notification system. If is shall remain here
		 * the currently logged in user should be passed to this function
		 *
		GLOBAL $user;
		if ($user->isGuest()) {
			return -2;
		}
		if (!$user->isAdmin()) {
			if ($isUser) {
				if ($user->getID() != $obj->getID()) {
					return -2;
				}
			}
			else {
				if (!$obj->isMember($user)) {
					return -2;
				}
			}
		}
		 */

		/* Check to see if the target is in the database. */
		$queryStr = "SELECT * FROM `tblNotify` WHERE `tblNotify`.`target` = '".$this->_id."' ".
			"AND `tblNotify`.`targetType` = '".T_DOCUMENT."' ".
			"AND `tblNotify`.".$userOrGroup." = '".(int) $userOrGroupID."'";
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr)) {
			return -4;
		}
		if (count($resArr)==0) {
			return -3;
		}

		$queryStr = "DELETE FROM `tblNotify` WHERE `target` = " . $this->_id . " AND `targetType` = " . T_DOCUMENT . " AND " . $userOrGroup . " = " . (int) $userOrGroupID;
		/* If type is given then delete only those notifications */
		if($type)
			$queryStr .= " AND `type` = ".(int) $type;
		if (!$db->getResult($queryStr))
			return -4;

		unset($this->_notifyList);
		return 0;
	} /* }}} */

	/**
	 * Add content to a document
	 *
	 * Each document may have any number of content elements attached to it.
	 * Each content element has a version number. Newer versions (greater
	 * version number) replace older versions.
	 *
	 * @param string $comment comment
	 * @param object $user user who shall be the owner of this content
	 * @param string $tmpFile file containing the actuall content
	 * @param string $orgFileName original file name
	 * @param string $fileType
	 * @param string $mimeType MimeType of the content
	 * @param array $reviewers list of reviewers
	 * @param array $approvers list of approvers
	 * @param integer $version version number of content or 0 if next higher version shall be used.
	 * @param array $attributes list of version attributes. The element key
	 *        must be the id of the attribute definition.
	 * @param object $workflow
	 * @return bool|LetoDMS_Core_AddContentResultSet
	 */
	function addContent($comment, $user, $tmpFile, $orgFileName, $fileType, $mimeType, $reviewers=array(), $approvers=array(), $version=0, $attributes=array(), $workflow=null) { /* {{{ */
		$db = $this->_dms->getDB();

		// the doc path is id/version.filetype
		$dir = $this->getDir();

		/* The version field in table tblDocumentContent used to be auto
		 * increment but that requires the field to be primary as well if
		 * innodb is used. That's why the version is now determined here.
		 */
		if ((int)$version<1) {
			$queryStr = "SELECT MAX(`version`) as m from `tblDocumentContent` where `document` = ".$this->_id;
			$resArr = $db->getResultArray($queryStr);
			if (is_bool($resArr) && !$res)
				return false;

			$version = $resArr[0]['m']+1;
		}

		$filesize = LetoDMS_Core_File::fileSize($tmpFile);
		$checksum = LetoDMS_Core_File::checksum($tmpFile);

		$db->startTransaction();
		$queryStr = "INSERT INTO `tblDocumentContent` (`document`, `version`, `comment`, `date`, `createdBy`, `dir`, `orgFileName`, `fileType`, `mimeType`, `fileSize`, `checksum`) VALUES ".
						"(".$this->_id.", ".(int)$version.",".$db->qstr($comment).", ".$db->getCurrentTimestamp().", ".$user->getID().", ".$db->qstr($dir).", ".$db->qstr($orgFileName).", ".$db->qstr($fileType).", ".$db->qstr($mimeType).", ".$filesize.", ".$db->qstr($checksum).")";
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$contentID = $db->getInsertID('tblDocumentContent');

		// copy file
		if (!LetoDMS_Core_File::makeDir($this->_dms->contentDir . $dir)) {
			$db->rollbackTransaction();
			return false;
		}
		if($this->_dms->forceRename)
			$err = LetoDMS_Core_File::renameFile($tmpFile, $this->_dms->contentDir . $dir . $version . $fileType);
		else
			$err = LetoDMS_Core_File::copyFile($tmpFile, $this->_dms->contentDir . $dir . $version . $fileType);
		if (!$err) {
			$db->rollbackTransaction();
			return false;
		}

		$this->_content = null;
		$this->_latestContent = null;
		$content = $this->getLatestContent($contentID); /** @todo: Parameter not defined in Funktion */
		$docResultSet = new LetoDMS_Core_AddContentResultSet($content);
		$docResultSet->setDMS($this->_dms);

		if($attributes) {
			foreach($attributes as $attrdefid=>$attribute) {
				/* $attribute can be a string or an array */
				if($attribute)
					if(!$content->setAttributeValue($this->_dms->getAttributeDefinition($attrdefid), $attribute)) {
						$this->_removeContent($content);
						$db->rollbackTransaction();
						return false;
					}
			}
		}

		$queryStr = "INSERT INTO `tblDocumentStatus` (`documentID`, `version`) ".
			"VALUES (". $this->_id .", ". (int) $version .")";
		if (!$db->getResult($queryStr)) {
			$this->_removeContent($content);
			$db->rollbackTransaction();
			return false;
		}

		$statusID = $db->getInsertID('tblDocumentStatus', 'statusID');

		if($workflow)
			$content->setWorkflow($workflow, $user);

		// Add reviewers into the database. Reviewers must review the document
		// and submit comments, if appropriate. Reviewers can also recommend that
		// a document be rejected.
		$pendingReview=false;
		/** @noinspection PhpUnusedLocalVariableInspection */
		$reviewRes = array(); /** @todo unused variable */
		foreach (array("i", "g") as $i){
			if (isset($reviewers[$i])) {
				foreach ($reviewers[$i] as $reviewerID) {
					$reviewer=($i=="i" ?$this->_dms->getUser($reviewerID) : $this->_dms->getGroup($reviewerID));
					$res = ($i=="i" ? $docResultSet->getContent()->addIndReviewer($reviewer, $user, true) : $docResultSet->getContent()->addGrpReviewer($reviewer, $user, true));
					$docResultSet->addReviewer($reviewer, $i, $res);
					// If no error is returned, or if the error is just due to email
					// failure, mark the state as "pending review".
					if ($res==0 || $res=-3 || $res=-4) {
						$pendingReview=true;
					}
				}
			}
		}
		// Add approvers to the database. Approvers must also review the document
		// and make a recommendation on its release as an approved version.
		$pendingApproval=false;
		/** @noinspection PhpUnusedLocalVariableInspection */
		$approveRes = array(); /** @todo unused variable */
		foreach (array("i", "g") as $i){
			if (isset($approvers[$i])) {
				foreach ($approvers[$i] as $approverID) {
					$approver=($i=="i" ? $this->_dms->getUser($approverID) : $this->_dms->getGroup($approverID));
					$res=($i=="i" ? $docResultSet->getContent()->addIndApprover($approver, $user, true) : $docResultSet->getContent()->addGrpApprover($approver, $user, !$pendingReview));
					$docResultSet->addApprover($approver, $i, $res);
					if ($res==0 || $res=-3 || $res=-4) {
						$pendingApproval=true;
					}
				}
			}
		}

		// If there are no reviewers or approvers, the document is automatically
		// promoted to the released state.
		if ($pendingReview) {
			$status = S_DRAFT_REV;
			$comment = "";
		}
		elseif ($pendingApproval) {
			$status = S_DRAFT_APP;
			$comment = "";
		}
		elseif($workflow) {
			$status = S_IN_WORKFLOW;
			$comment = ", workflow: ".$workflow->getName();
		} else {
			$status = S_RELEASED;
			$comment = "";
		}
		$queryStr = "INSERT INTO `tblDocumentStatusLog` (`statusID`, `status`, `comment`, `date`, `userID`) ".
			"VALUES ('". $statusID ."', '". $status."', 'New document content submitted". $comment ."', ".$db->getCurrentDatetime().", '". $user->getID() ."')";
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		/** @noinspection PhpMethodParametersCountMismatchInspection */
		$docResultSet->setStatus($status,$comment,$user); /** @todo parameter count wrong */

		$db->commitTransaction();
		return $docResultSet;
	} /* }}} */

	/**
	 * Replace a version of a document
	 *
	 * Each document may have any number of content elements attached to it.
	 * This function replaces the file content of a given version.
	 * Using this function is highly discourage, because it undermines the
	 * idea of keeping all versions of a document as originally saved.
	 * Content will only be replaced if the mimetype, filetype, user and
	 * original filename are identical to the version being updated.
	 *
	 * This function was introduced for the webdav server because any saving
	 * of a document created a new version.
	 *
	 * @param object $user user who shall be the owner of this content
	 * @param string $tmpFile file containing the actuall content
	 * @param string $orgFileName original file name
	 * @param string $fileType
	 * @param string $mimeType MimeType of the content
	 * @param integer $version version number of content or 0 if next higher version shall be used.
	 * @return bool/array false in case of an error or a result set
	 */
	function replaceContent($version, $user, $tmpFile, $orgFileName, $fileType, $mimeType) { /* {{{ */
		$db = $this->_dms->getDB();

		// the doc path is id/version.filetype
		$dir = $this->getDir();

		/* If $version < 1 than replace the content of the latest version.
		 */
		if ((int) $version<1) {
			$queryStr = "SELECT MAX(`version`) as m from `tblDocumentContent` where `document` = ".$this->_id;
			$resArr = $db->getResultArray($queryStr);
			if (is_bool($resArr) && !$res) /** @todo undefined variable */
				return false;

			$version = $resArr[0]['m'];
		}

		$content = $this->getContentByVersion($version);
		if(!$content)
			return false;

		/* Check if $user, $orgFileName, $fileType and $mimetype are the same */
		if($user->getID() != $content->getUser()->getID()) {
			return false;
		}
		if($orgFileName != $content->getOriginalFileName()) {
			return false;
		}
		if($fileType != $content->getFileType()) {
			return false;
		}
		if($mimeType != $content->getMimeType()) {
			return false;
		}

		$filesize = LetoDMS_Core_File::fileSize($tmpFile);
		$checksum = LetoDMS_Core_File::checksum($tmpFile);

		$db->startTransaction();
		$queryStr = "UPDATE `tblDocumentContent` set `date`=".$db->getCurrentTimestamp().", `fileSize`=".$filesize.", `checksum`=".$db->qstr($checksum)." WHERE `id`=".$content->getID();
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		// copy file
		if (!LetoDMS_Core_File::copyFile($tmpFile, $this->_dms->contentDir . $dir . $version . $fileType)) {
			$db->rollbackTransaction();
			return false;
		}

		$this->_content = null;
		$this->_latestContent = null;
		$db->commitTransaction();

		return true;
	} /* }}} */

	/**
	 * Return all content elements of a document
	 *
	 * This functions returns an array of content elements ordered by version.
	 * Version which are not accessible because of its status, will be filtered
	 * out. Access rights based on the document status are calculated for the
	 * currently logged in user.
	 *
	 * @return bool|LetoDMS_Core_DocumentContent[]
	 */
	function getContent() { /* {{{ */
		$db = $this->_dms->getDB();

		if (!isset($this->_content)) {
			$queryStr = "SELECT * FROM `tblDocumentContent` WHERE `document` = ".$this->_id." ORDER BY `version`";
			$resArr = $db->getResultArray($queryStr);
			if (is_bool($resArr) && !$res) /** @todo undefined variable */
				return false;

			$this->_content = array();
			$classname = $this->_dms->getClassname('documentcontent');
			$user = $this->_dms->getLoggedInUser();
			foreach ($resArr as $row) {
				/** @var LetoDMS_Core_DocumentContent $content */
				$content = new $classname($row["id"], $this, $row["version"], $row["comment"], $row["date"], $row["createdBy"], $row["dir"], $row["orgFileName"], $row["fileType"], $row["mimeType"], $row['fileSize'], $row['checksum']);
				if($user) {
					if($content->getAccessMode($user) >= M_READ)
						array_push($this->_content, $content);
				} else {
					array_push($this->_content, $content);
				}
			}
		}

		return $this->_content;
	} /* }}} */

	/**
	 * Return the content element of a document with a given version number
	 *
	 * This function will check if the version is accessible and return false
	 * if not. Access rights based on the document status are calculated for the
	 * currently logged in user.
	 *
	 * @param integer $version version number of content element
	 * @return LetoDMS_Core_DocumentContent|boolean object of class {@link LetoDMS_Core_DocumentContent}
	 * or false
	 */
	function getContentByVersion($version) { /* {{{ */
		if (!is_numeric($version)) return false;

		if (isset($this->_content)) {
			foreach ($this->_content as $revision) {
				if ($revision->getVersion() == $version)
					return $revision;
			}
			return false;
		}

		$db = $this->_dms->getDB();
		$queryStr = "SELECT * FROM `tblDocumentContent` WHERE `document` = ".$this->_id." AND `version` = " . (int) $version;
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && !$res) /** @todo undefined variable */
			return false;
		if (count($resArr) != 1)
			return false;

		$resArr = $resArr[0];
		$classname = $this->_dms->getClassname('documentcontent');
		/** @var LetoDMS_Core_DocumentContent $content */
		if($content = new $classname($resArr["id"], $this, $resArr["version"], $resArr["comment"], $resArr["date"], $resArr["createdBy"], $resArr["dir"], $resArr["orgFileName"], $resArr["fileType"], $resArr["mimeType"], $resArr['fileSize'], $resArr['checksum'])) {
			$user = $this->_dms->getLoggedInUser();
			/* A user with write access on the document may always see the version */
			if($user && $content->getAccessMode($user) == M_NONE)
				return false;
			else
				return $content;
		} else {
			return false;
		}
	} /* }}} */

	/**
	 * @return bool|null|LetoDMS_Core_DocumentContent
	 */
	function __getLatestContent() { /* {{{ */
		if (!$this->_latestContent) {
			$db = $this->_dms->getDB();
			$queryStr = "SELECT * FROM `tblDocumentContent` WHERE `document` = ".$this->_id." ORDER BY `version` DESC LIMIT 1";
			$resArr = $db->getResultArray($queryStr);
			if (is_bool($resArr) && !$resArr)
				return false;
			if (count($resArr) != 1)
				return false;

			$resArr = $resArr[0];
			$classname = $this->_dms->getClassname('documentcontent');
			$this->_latestContent = new $classname($resArr["id"], $this, $resArr["version"], $resArr["comment"], $resArr["date"], $resArr["createdBy"], $resArr["dir"], $resArr["orgFileName"], $resArr["fileType"], $resArr["mimeType"], $resArr['fileSize'], $resArr['checksum']);
		}
		return $this->_latestContent;
	} /* }}} */

	/**
	 * Get the latest version of document
	 *
	 * This function returns the latest accessible version of a document.
	 * If content access has been restricted by setting
	 * {@link LetoDMS_Core_DMS::noReadForStatus} the function will go
	 * backwards in history until an accessible version is found. If none
	 * is found null will be returned.
	 * Access rights based on the document status are calculated for the
	 * currently logged in user.
	 *
	 * @return bool|LetoDMS_Core_DocumentContent object of class {@link LetoDMS_Core_DocumentContent}
	 */
	function getLatestContent() { /* {{{ */
		if (!$this->_latestContent) {
			$db = $this->_dms->getDB();
			$queryStr = "SELECT * FROM `tblDocumentContent` WHERE `document` = ".$this->_id." ORDER BY `version` DESC";
			$resArr = $db->getResultArray($queryStr);
			if (is_bool($resArr) && !$res) /** @todo: $res not defined */
				return false;

			$classname = $this->_dms->getClassname('documentcontent');
			$user = $this->_dms->getLoggedInUser();
			foreach ($resArr as $row) {
				if (!$this->_latestContent) {
				    /** @var LetoDMS_Core_DocumentContent $content */
					$content = new $classname($row["id"], $this, $row["version"], $row["comment"], $row["date"], $row["createdBy"], $row["dir"], $row["orgFileName"], $row["fileType"], $row["mimeType"], $row['fileSize'], $row['checksum']);
					if($user) {
						/* If the user may even write the document, then also allow to see all content.
						 * This is needed because the user could upload a new version
						 */
						if($content->getAccessMode($user) >= M_READ) {
							$this->_latestContent = $content;
						}
					} else {
						$this->_latestContent = $content;
					}
				}
			}
		}

		return $this->_latestContent;
	} /* }}} */

	/**
	 * Remove version of document
	 *
	 * @param LetoDMS_Core_DocumentContent $version version number of content
	 * @return boolean true if successful, otherwise false
	 */
	private function _removeContent($version) { /* {{{ */
		$db = $this->_dms->getDB();

		if (file_exists( $this->_dms->contentDir.$version->getPath() ))
			if (!LetoDMS_Core_File::removeFile( $this->_dms->contentDir.$version->getPath() ))
				return false;

		$db->startTransaction();

		$status = $version->getStatus();
		$stID = $status["statusID"];

		$queryStr = "DELETE FROM `tblDocumentContent` WHERE `document` = " . $this->getID() .	" AND `version` = " . $version->_version;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$queryStr = "DELETE FROM `tblDocumentContentAttributes` WHERE `content` = " . $version->getId();
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$queryStr = "DELETE FROM `tblDocumentStatusLog` WHERE `statusID` = '".$stID."'";
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$queryStr = "DELETE FROM `tblDocumentStatus` WHERE `documentID` = '". $this->getID() ."' AND `version` = '" . $version->_version."'";
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$status = $version->getReviewStatus();
		$stList = "";
		foreach ($status as $st) {
			$stList .= (strlen($stList)==0 ? "" : ", "). "'".$st["reviewID"]."'";
			$queryStr = "SELECT * FROM `tblDocumentReviewLog` WHERE `reviewID` = " . $st['reviewID'];
			$resArr = $db->getResultArray($queryStr);
			if ((is_bool($resArr) && !$resArr)) {
				$db->rollbackTransaction();
				return false;
			}
			foreach($resArr as $res) {
				$file = $this->_dms->contentDir . $this->getDir().'r'.$res['reviewLogID'];
				if(file_exists($file))
					LetoDMS_Core_File::removeFile($file);
			}
		}

		if (strlen($stList)>0) {
			$queryStr = "DELETE FROM `tblDocumentReviewLog` WHERE `tblDocumentReviewLog`.`reviewID` IN (".$stList.")";
			if (!$db->getResult($queryStr)) {
				$db->rollbackTransaction();
				return false;
			}
		}
		$queryStr = "DELETE FROM `tblDocumentReviewers` WHERE `documentID` = '". $this->getID() ."' AND `version` = '" . $version->_version."'";
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}
		$status = $version->getApprovalStatus();
		$stList = "";
		foreach ($status as $st) {
			$stList .= (strlen($stList)==0 ? "" : ", "). "'".$st["approveID"]."'";
			$queryStr = "SELECT * FROM `tblDocumentApproveLog` WHERE `approveID` = " . $st['approveID'];
			$resArr = $db->getResultArray($queryStr);
			if ((is_bool($resArr) && !$resArr)) {
				$db->rollbackTransaction();
				return false;
			}
			foreach($resArr as $res) {
				$file = $this->_dms->contentDir . $this->getDir().'a'.$res['approveLogID'];
				if(file_exists($file))
					LetoDMS_Core_File::removeFile($file);
			}
		}

		if (strlen($stList)>0) {
			$queryStr = "DELETE FROM `tblDocumentApproveLog` WHERE `tblDocumentApproveLog`.`approveID` IN (".$stList.")";
			if (!$db->getResult($queryStr)) {
				$db->rollbackTransaction();
				return false;
			}
		}
		$queryStr = "DELETE FROM `tblDocumentApprovers` WHERE `documentID` = '". $this->getID() ."' AND `version` = '" . $version->_version."'";
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$queryStr = "DELETE FROM `tblWorkflowDocumentContent` WHERE `document` = '". $this->getID() ."' AND `version` = '" . $version->_version."'";
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$queryStr = "DELETE FROM `tblWorkflowLog` WHERE `document` = '". $this->getID() ."' AND `version` = '" . $version->_version."'";
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		// remove document files attached to version
		$res = $this->getDocumentFiles($version->_version);
		if (is_bool($res) && !$res) {
			$db->rollbackTransaction();
			return false;
		}

		foreach ($res as $documentfile)
			if(!$this->removeDocumentFile($documentfile->getId())) {
				$db->rollbackTransaction();
				return false;
			}

		$db->commitTransaction();
		return true;
	} /* }}} */

	/**
	 * Call callback onPreRemoveDocument before deleting content
	 *
	 * @param LetoDMS_Core_DocumentContent $version version number of content
	 * @return bool|mixed
	 */
	function removeContent($version) { /* {{{ */
		/* Check if 'onPreRemoveDocument' callback is set */
		if(isset($this->_dms->callbacks['onPreRemoveContent'])) {
			foreach($this->_dms->callbacks['onPreRemoveContent'] as $callback) {
				$ret = call_user_func($callback[0], $callback[1], $this, $version);
				if(is_bool($ret))
					return $ret;
			}
		}

		if(false === ($ret = self::_removeContent($version))) {
			return false;
		}

		/* Check if 'onPostRemoveDocument' callback is set */
		if(isset($this->_dms->callbacks['onPostRemoveContent'])) {
			foreach($this->_dms->callbacks['onPostRemoveContent'] as $callback) {
				if(!call_user_func($callback[0], $callback[1], $version)) {
				}
			}
		}

		return $ret;
	} /* }}} */

	/**
	 * Return a certain document link
	 *
	 * @param integer $linkID id of link
	 * @return LetoDMS_Core_DocumentLink|bool of LetoDMS_Core_DocumentLink or false in case of
	 *         an error.
	 */
	function getDocumentLink($linkID) { /* {{{ */
		$db = $this->_dms->getDB();

		if (!is_numeric($linkID)) return false;

		$queryStr = "SELECT * FROM `tblDocumentLinks` WHERE `document` = " . $this->_id ." AND `id` = " . (int) $linkID;
		$resArr = $db->getResultArray($queryStr);
		if ((is_bool($resArr) && !$resArr) || count($resArr)==0)
			return false;

		$resArr = $resArr[0];
		$document = $this->_dms->getDocument($resArr["document"]);
		$target = $this->_dms->getDocument($resArr["target"]);
		$link = new LetoDMS_Core_DocumentLink($resArr["id"], $document, $target, $resArr["userID"], $resArr["public"]);
		$user = $this->_dms->getLoggedInUser();
		if($link->getAccessMode($user, $document, $target) >= M_READ)
			return $link;
		return null;
	} /* }}} */

	/**
	 * Return all document links
	 *
	 * The list may contain all links to other documents, even those which
	 * may not be visible by certain users, unless you pass appropriate
	 * parameters to filter out public links and those created by
	 * the given user. The application may call
	 * LetoDMS_Core_DMS::filterDocumentLinks() afterwards.
	 *
	 * @param boolean $publiconly return on publically visible links
	 * @param object $user return also private links of this user
	 * @return array list of objects of class LetoDMS_Core_DocumentLink
	 */
	function getDocumentLinks($publiconly=false, $user=null) { /* {{{ */
		if (!isset($this->_documentLinks)) {
			$db = $this->_dms->getDB();

			$queryStr = "SELECT * FROM `tblDocumentLinks` WHERE `document` = " . $this->_id;
			$tmp = array();
			if($publiconly)
				$tmp[] = "`public`=1";
			if($user)
				$tmp[] = "`userID`=".$user->getID();
			if($tmp) {
				$queryStr .= " AND (".implode(" OR ", $tmp).")";
			}

			$resArr = $db->getResultArray($queryStr);
			if (is_bool($resArr) && !$resArr)
				return false;
			$this->_documentLinks = array();

			$user = $this->_dms->getLoggedInUser();
			foreach ($resArr as $row) {
				$target = $this->_dms->getDocument($row["target"]);
				$link = new LetoDMS_Core_DocumentLink($row["id"], $this, $target, $row["userID"], $row["public"]);
				if($link->getAccessMode($user, $this, $target) >= M_READ)
					array_push($this->_documentLinks, $link);
			}
		}
		return $this->_documentLinks;
	} /* }}} */

	/**
	 * Return all document having a link on this document
	 *
	 * The list contains all documents which have a link to the current
	 * document. The list contains even those documents which
	 * may not be accessible by the user, unless you pass appropriate
	 * parameters to filter out public links and those created by
	 * the given user.
	 * This functions is basically the reverse of
	 * LetoDMS_Core_Document::getDocumentLinks()
	 *
	 * The application may call
	 * LetoDMS_Core_DMS::filterDocumentLinks() afterwards.
	 *
	 * @param boolean $publiconly return on publically visible links
	 * @param object $user return also private links of this user
	 * @return array list of objects of class LetoDMS_Core_DocumentLink
	 */
	function getReverseDocumentLinks($publiconly=false, $user=null) { /* {{{ */
			$db = $this->_dms->getDB();

			$queryStr = "SELECT * FROM `tblDocumentLinks` WHERE `target` = " . $this->_id;
			$tmp = array();
			if($publiconly)
				$tmp[] = "`public`=1";
			if($user)
				$tmp[] = "`userID`=".$user->getID();
			if($tmp) {
				$queryStr .= " AND (".implode(" OR ", $tmp).")";
			}

			$resArr = $db->getResultArray($queryStr);
			if (is_bool($resArr) && !$resArr)
				return false;

			$links = array();
			foreach ($resArr as $row) {
				$document = $this->_dms->getDocument($row["document"]);
				$link = new LetoDMS_Core_DocumentLink($row["id"], $document, $this, $row["userID"], $row["public"]);
				if($link->getAccessMode($user, $document, $this) >= M_READ)
					array_push($links, $link);
			}

		return $links;
	} /* }}} */

	function addDocumentLink($targetID, $userID, $public) { /* {{{ */
		$db = $this->_dms->getDB();

		$public = ($public) ? "1" : "0";

		$queryStr = "INSERT INTO `tblDocumentLinks` (`document`, `target`, `userID`, `public`) VALUES (".$this->_id.", ".(int)$targetID.", ".(int)$userID.", ".(int)$public.")";
		if (!$db->getResult($queryStr))
			return false;

		unset($this->_documentLinks);
		return true;
	} /* }}} */

	function removeDocumentLink($linkID) { /* {{{ */
		$db = $this->_dms->getDB();

		if (!is_numeric($linkID)) return false;

		$queryStr = "DELETE FROM `tblDocumentLinks` WHERE `document` = " . $this->_id ." AND `id` = " . (int) $linkID;
		if (!$db->getResult($queryStr)) return false;
		unset ($this->_documentLinks);
		return true;
	} /* }}} */

	/**
	 * Get attached file by its id
	 *
	 * @return object instance of LetoDMS_Core_DocumentFile, null if file is not
	 * accessible, false in case of an sql error
	 */
	function getDocumentFile($ID) { /* {{{ */
		$db = $this->_dms->getDB();

		if (!is_numeric($ID)) return false;

		$queryStr = "SELECT * FROM `tblDocumentFiles` WHERE `document` = " . $this->_id ." AND `id` = " . (int) $ID;
		$resArr = $db->getResultArray($queryStr);
		if ((is_bool($resArr) && !$resArr) || count($resArr)==0) return false;

		$resArr = $resArr[0];
		$file = new LetoDMS_Core_DocumentFile($resArr["id"], $this, $resArr["userID"], $resArr["comment"], $resArr["date"], $resArr["dir"], $resArr["fileType"], $resArr["mimeType"], $resArr["orgFileName"], $resArr["name"],$resArr["version"],$resArr["public"]);
		$user = $this->_dms->getLoggedInUser();
		if($file->getAccessMode($user) >= M_READ)
			return $file;
		return null;
	} /* }}} */

	/**
	 * Get list of files attached to document
	 *
	 * @return array list of files, false in case of an sql error
	 */
	function getDocumentFiles($version=0) { /* {{{ */
		if (!isset($this->_documentFiles)) {
			$db = $this->_dms->getDB();

			$queryStr = "SELECT * FROM `tblDocumentFiles` WHERE `document` = " . $this->_id;
			if($version) {
				$queryStr .= " AND (`version`=0 OR `version`=".(int) $version.")";
			}
			$queryStr .= " ORDER BY ";
			if($version) {
				$queryStr .= "`version` DESC,";
			}
			$queryStr .= "`date` DESC";
			$resArr = $db->getResultArray($queryStr);
			if (is_bool($resArr) && !$resArr) return false;

			$this->_documentFiles = array();

			$user = $this->_dms->getLoggedInUser();
			foreach ($resArr as $row) {
				$file = new LetoDMS_Core_DocumentFile($row["id"], $this, $row["userID"], $row["comment"], $row["date"], $row["dir"], $row["fileType"], $row["mimeType"], $row["orgFileName"], $row["name"], $row["version"], $row["public"]);
				if($file->getAccessMode($user) >= M_READ)
					array_push($this->_documentFiles, $file);
			}
		}
		return $this->_documentFiles;
	} /* }}} */

	function addDocumentFile($name, $comment, $user, $tmpFile, $orgFileName,$fileType, $mimeType,$version=0,$public=1) { /* {{{ */
		$db = $this->_dms->getDB();

		$dir = $this->getDir();

		$queryStr = "INSERT INTO `tblDocumentFiles` (`comment`, `date`, `dir`, `document`, `fileType`, `mimeType`, `orgFileName`, `userID`, `name`, `version`, `public`) VALUES ".
			"(".$db->qstr($comment).", ".$db->getCurrentTimestamp().", ".$db->qstr($dir).", ".$this->_id.", ".$db->qstr($fileType).", ".$db->qstr($mimeType).", ".$db->qstr($orgFileName).",".$user->getID().",".$db->qstr($name).", ".((int) $version).", ".($public ? 1 : 0).")";
		if (!$db->getResult($queryStr)) return false;

		$id = $db->getInsertID('tblDocumentFiles');

		$file = $this->getDocumentFile($id);
		if (is_bool($file) && !$file) return false;

		// copy file
		if (!LetoDMS_Core_File::makeDir($this->_dms->contentDir . $dir)) return false;
		if($this->_dms->forceRename)
			$err = LetoDMS_Core_File::renameFile($tmpFile, $this->_dms->contentDir . $file->getPath());
		else
			$err = LetoDMS_Core_File::copyFile($tmpFile, $this->_dms->contentDir . $file->getPath());
		if (!$err) return false;

		return $file;
	} /* }}} */

	function removeDocumentFile($ID) { /* {{{ */
		$db = $this->_dms->getDB();

		if (!is_numeric($ID)) return false;

		$file = $this->getDocumentFile($ID);
		if (is_bool($file) && !$file) return false;

		if (file_exists( $this->_dms->contentDir . $file->getPath() )){
			if (!LetoDMS_Core_File::removeFile( $this->_dms->contentDir . $file->getPath() ))
				return false;
		}

		$name=$file->getName();
		$comment=$file->getcomment();

		$queryStr = "DELETE FROM `tblDocumentFiles` WHERE `document` = " . $this->getID() . " AND `id` = " . (int) $ID;
		if (!$db->getResult($queryStr))
			return false;

		unset ($this->_documentFiles);

		return true;
	} /* }}} */

	/**
	 * Remove a document completly
	 *
	 * This methods calls the callback 'onPreRemoveDocument' before removing
	 * the document. The current document will be passed as the second
	 * parameter to the callback function. After successful deletion the
	 * 'onPostRemoveDocument' callback will be used. The current document id
	 * will be passed as the second parameter. If onPreRemoveDocument fails
	 * the whole function will fail and the document will not be deleted.
	 * The return value of 'onPostRemoveDocument' will be disregarded.
	 *
	 * @return boolean true on success, otherwise false
	 */
	function remove() { /* {{{ */
		$db = $this->_dms->getDB();

		/* Check if 'onPreRemoveDocument' callback is set */
		if(isset($this->_dms->callbacks['onPreRemoveDocument'])) {
			foreach($this->_dms->callbacks['onPreRemoveDocument'] as $callback) {
				$ret = call_user_func($callback[0], $callback[1], $this);
				if(is_bool($ret))
					return $ret;
			}
		}

		$res = $this->getContent();
		if (is_bool($res) && !$res) return false;

		$db->startTransaction();

		// remove content of document
		foreach ($this->_content as $version) {
			if (!$this->_removeContent($version)) {
				$db->rollbackTransaction();
				return false;
			}
		}

		// remove document file
		$res = $this->getDocumentFiles();
		if (is_bool($res) && !$res) {
			$db->rollbackTransaction();
			return false;
		}

		foreach ($res as $documentfile)
			if(!$this->removeDocumentFile($documentfile->getId())) {
				$db->rollbackTransaction();
				return false;
			}

		// TODO: versioning file?

		if (file_exists( $this->_dms->contentDir . $this->getDir() ))
			if (!LetoDMS_Core_File::removeDir( $this->_dms->contentDir . $this->getDir() )) {
				$db->rollbackTransaction();
				return false;
			}

		$queryStr = "DELETE FROM `tblDocuments` WHERE `id` = " . $this->_id;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}
		$queryStr = "DELETE FROM `tblDocumentAttributes` WHERE `document` = " . $this->_id;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}
		$queryStr = "DELETE FROM `tblACLs` WHERE `target` = " . $this->_id . " AND `targetType` = " . T_DOCUMENT;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}
		$queryStr = "DELETE FROM `tblDocumentLinks` WHERE `document` = " . $this->_id . " OR `target` = " . $this->_id;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}
		$queryStr = "DELETE FROM `tblDocumentLocks` WHERE `document` = " . $this->_id;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}
		$queryStr = "DELETE FROM `tblDocumentFiles` WHERE `document` = " . $this->_id;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}
		$queryStr = "DELETE FROM `tblDocumentCategory` WHERE `documentID` = " . $this->_id;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		// Delete the notification list.
		$queryStr = "DELETE FROM `tblNotify` WHERE `target` = " . $this->_id . " AND `targetType` = " . T_DOCUMENT;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$db->commitTransaction();

		/* Check if 'onPostRemoveDocument' callback is set */
		if(isset($this->_dms->callbacks['onPostRemoveDocument'])) {
			foreach($this->_dms->callbacks['onPostRemoveDocument'] as $callback) {
				if(!call_user_func($callback[0], $callback[1], $this->_id)) {
				}
			}
		}

		return true;
	} /* }}} */

	/**
	 * Get List of users and groups which have read access on the document
	 * The list will not include any guest users,
	 * administrators and the owner of the folder unless $listadmin resp.
	 * $listowner is set to true.
	 *
	 * This function is deprecated. Use
	 * {@see LetoDMS_Core_Document::getReadAccessList()} instead.
	 */
	function getApproversList() { /* {{{ */
		return $this->getReadAccessList(0, 0, 0);
	} /* }}} */

	/**
	 * Returns a list of groups and users with read access on the document
	 *
	 * @param boolean $listadmin if set to true any admin will be listed too
	 * @param boolean $listowner if set to true the owner will be listed too
	 * @param boolean $listguest if set to true any guest will be listed too
	 *
	 * @return array list of users and groups
	 */
	function getReadAccessList($listadmin=0, $listowner=0, $listguest=0) { /* {{{ */
		$db = $this->_dms->getDB();

		if (!isset($this->_readAccessList)) {
			$this->_readAccessList = array("groups" => array(), "users" => array());
			$userIDs = "";
			$groupIDs = "";
			$defAccess  = $this->getDefaultAccess();

			if ($defAccess<M_READ) {
				// Get the list of all users and groups that are listed in the ACL as
				// having read access to the document.
				$tmpList = $this->getAccessList(M_READ, O_GTEQ);
			}
			else {
				// Get the list of all users and groups that DO NOT have read access
				// to the document.
				$tmpList = $this->getAccessList(M_NONE, O_LTEQ);
			}
			foreach ($tmpList["groups"] as $groupAccess) {
				$groupIDs .= (strlen($groupIDs)==0 ? "" : ", ") . $groupAccess->getGroupID();
			}
			foreach ($tmpList["users"] as $userAccess) {
				$user = $userAccess->getUser();
				if (!$listadmin && $user->isAdmin()) continue;
				if (!$listowner && $user->getID() == $this->_ownerID) continue;
				if (!$listguest && $user->isGuest()) continue;
				$userIDs .= (strlen($userIDs)==0 ? "" : ", ") . $userAccess->getUserID();
			}

			// Construct a query against the users table to identify those users
			// that have read access to this document, either directly through an
			// ACL entry, by virtue of ownership or by having administrative rights
			// on the database.
			$queryStr="";
			/* If default access is less then read, $userIDs and $groupIDs contains
			 * a list of user with read access
			 */
			if ($defAccess < M_READ) {
				if (strlen($groupIDs)>0) {
					$queryStr = "SELECT `tblUsers`.* FROM `tblUsers` ".
						"LEFT JOIN `tblGroupMembers` ON `tblGroupMembers`.`userID`=`tblUsers`.`id` ".
						"WHERE `tblGroupMembers`.`groupID` IN (". $groupIDs .") ".
						"AND `tblUsers`.`role` != ".LetoDMS_Core_User::role_guest." UNION ";
				}
				$queryStr .=
					"SELECT `tblUsers`.* FROM `tblUsers` ".
					"WHERE (`tblUsers`.`role` != ".LetoDMS_Core_User::role_guest.") ".
					"AND ((`tblUsers`.`id` = ". $this->_ownerID . ") ".
					"OR (`tblUsers`.`role` = ".LetoDMS_Core_User::role_admin.")".
					(strlen($userIDs) == 0 ? "" : " OR (`tblUsers`.`id` IN (". $userIDs ."))").
					") ORDER BY `login`";
			}
			/* If default access is equal or greater then M_READ, $userIDs and
			 * $groupIDs contains a list of user without read access
			 */
			else {
				if (strlen($groupIDs)>0) {
					$queryStr = "SELECT `tblUsers`.* FROM `tblUsers` ".
						"LEFT JOIN `tblGroupMembers` ON `tblGroupMembers`.`userID`=`tblUsers`.`id` ".
						"WHERE `tblGroupMembers`.`groupID` NOT IN (". $groupIDs .")".
						"AND `tblUsers`.`role` != ".LetoDMS_Core_User::role_guest." ".
						(strlen($userIDs) == 0 ? "" : " AND (`tblUsers`.`id` NOT IN (". $userIDs ."))")." UNION ";
				} else {
					$queryStr .=
						"SELECT `tblUsers`.* FROM `tblUsers` ".
						"WHERE `tblUsers`.`role` != ".LetoDMS_Core_User::role_guest." ".
						(strlen($userIDs) == 0 ? "" : " AND (`tblUsers`.`id` NOT IN (". $userIDs ."))")." UNION ";
				}
				$queryStr .=
					"SELECT `tblUsers`.* FROM `tblUsers` ".
					"WHERE (`tblUsers`.`id` = ". $this->_ownerID . ") ".
					"OR (`tblUsers`.`role` = ".LetoDMS_Core_User::role_admin.") ".
//					"UNION ".
//					"SELECT `tblUsers`.* FROM `tblUsers` ".
//					"WHERE `tblUsers`.`role` != ".LetoDMS_Core_User::role_guest." ".
//					(strlen($userIDs) == 0 ? "" : " AND (`tblUsers`.`id` NOT IN (". $userIDs ."))").
					" ORDER BY `login`";
			}
			$resArr = $db->getResultArray($queryStr);
			if (!is_bool($resArr)) {
				foreach ($resArr as $row) {
					$user = $this->_dms->getUser($row['id']);
					if (!$listadmin && $user->isAdmin()) continue;
					if (!$listowner && $user->getID() == $this->_ownerID) continue;
					$this->_readAccessList["users"][] = $user;
				}
			}

			// Assemble the list of groups that have read access to the document.
			$queryStr="";
			if ($defAccess < M_READ) {
				if (strlen($groupIDs)>0) {
					$queryStr = "SELECT `tblGroups`.* FROM `tblGroups` ".
						"WHERE `tblGroups`.`id` IN (". $groupIDs .") ORDER BY `name`";
				}
			}
			else {
				if (strlen($groupIDs)>0) {
					$queryStr = "SELECT `tblGroups`.* FROM `tblGroups` ".
						"WHERE `tblGroups`.`id` NOT IN (". $groupIDs .") ORDER BY `name`";
				}
				else {
					$queryStr = "SELECT `tblGroups`.* FROM `tblGroups` ORDER BY `name`";
				}
			}
			if (strlen($queryStr)>0) {
				$resArr = $db->getResultArray($queryStr);
				if (!is_bool($resArr)) {
					foreach ($resArr as $row) {
						$group = $this->_dms->getGroup($row["id"]);
						$this->_readAccessList["groups"][] = $group;
					}
				}
			}
		}
		return $this->_readAccessList;
	} /* }}} */

	/**
	 * Get the internally used folderList which stores the ids of folders from
	 * the root folder to the parent folder.
	 *
	 * @return string column separated list of folder ids
	 */
	function getFolderList() { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "SELECT `folderList` FROM `tblDocuments` where id = ".$this->_id;
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && !$resArr)
			return false;

		return $resArr[0]['folderList'];
	} /* }}} */

	/**
	 * Checks the internal data of the document and repairs it.
	 * Currently, this function only repairs an incorrect folderList
	 *
	 * @return boolean true on success, otherwise false
	 */
	function repair() { /* {{{ */
		$db = $this->_dms->getDB();

		$curfolderlist = $this->getFolderList();

		// calculate the folderList of the folder
		$parent = $this->getFolder();
		$pathPrefix="";
		$path = $parent->getPath();
		foreach ($path as $f) {
			$pathPrefix .= ":".$f->getID();
		}
		if (strlen($pathPrefix)>1) {
			$pathPrefix .= ":";
		}
		if($curfolderlist != $pathPrefix) {
			$queryStr = "UPDATE `tblDocuments` SET `folderList`='".$pathPrefix."' WHERE `id` = ". $this->_id;
			$res = $db->getResult($queryStr);
			if (!$res)
				return false;
		}
		return true;
	} /* }}} */

	/**
	 * Calculate the disk space including all versions of the document
	 *
	 * This is done by using the internal database field storing the
	 * filesize of a document version.
	 *
	 * @return integer total disk space in Bytes
	 */
	function getUsedDiskSpace() { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "SELECT SUM(`fileSize`) sum FROM `tblDocumentContent` WHERE `document` = " . $this->_id;
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;

		return $resArr[0]['sum'];
	} /* }}} */

	/**
	 * Returns a list of events happend during the life of the document
	 *
	 * This includes the creation of new versions, approval and reviews, etc.
	 *
	 * @return array list of events
	 */
	function getTimeline() { /* {{{ */
		$db = $this->_dms->getDB();

		$timeline = array();

		/* No need to add entries for new version because the status log
		 * will generate an entry as well.
		$queryStr = "SELECT * FROM `tblDocumentContent` WHERE `document` = " . $this->_id;
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;

		foreach ($resArr as $row) {
			$date = date('Y-m-d H:i:s', $row['date']);
			$timeline[] = array('date'=>$date, 'msg'=>'Added version '.$row['version'], 'type'=>'add_version', 'version'=>$row['version'], 'document'=>$this, 'params'=>array($row['version']));
		}
		 */

		$queryStr = "SELECT * FROM `tblDocumentFiles` WHERE `document` = " . $this->_id;
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;

		foreach ($resArr as $row) {
			$date = date('Y-m-d H:i:s', $row['date']);
			$timeline[] = array('date'=>$date, 'msg'=>'Added attachment "'.$row['name'].'"', 'document'=>$this, 'type'=>'add_file', 'fileid'=>$row['id']);
		}

		$queryStr=
			"SELECT `tblDocumentStatus`.*, `tblDocumentStatusLog`.`statusLogID`,`tblDocumentStatusLog`.`status`, ".
			"`tblDocumentStatusLog`.`comment`, `tblDocumentStatusLog`.`date`, ".
			"`tblDocumentStatusLog`.`userID` ".
			"FROM `tblDocumentStatus` ".
			"LEFT JOIN `tblDocumentStatusLog` USING (`statusID`) ".
			"WHERE `tblDocumentStatus`.`documentID` = '". $this->_id ."' ".
			"ORDER BY `tblDocumentStatusLog`.`statusLogID` DESC";
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && !$resArr)
			return false;

		/* The above query will also contain entries where a document status exists
		 * but no status log entry. Those records will have no date and must be
		 * skipped.
		 */
		foreach ($resArr as $row) {
			if($row['date']) {
				$date = $row['date'];
				$timeline[] = array('date'=>$date, 'msg'=>'Version '.$row['version'].': Status change to '.$row['status'], 'type'=>'status_change', 'version'=>$row['version'], 'document'=>$this, 'status'=>$row['status'], 'statusid'=>$row['statusID'], 'statuslogid'=>$row['statusLogID']);
			}
		}
		return $timeline;
	} /* }}} */

	/**
	 * Transfers the document to a new user
	 * 
	 * This method not just sets a new owner of the document but also
	 * transfers the document links, attachments and locks to the new user.
	 *
	 * @return boolean true if successful, otherwise false
	 */
	function transferToUser($newuser) { /* {{{ */
		$db = $this->_dms->getDB();

		if($newuser->getId() == $this->_ownerID)
			return true;

		$db->startTransaction();
		$queryStr = "UPDATE `tblDocuments` SET `owner` = ".$newuser->getId()." WHERE `id` = " . $this->_id;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$queryStr = "UPDATE `tblDocumentLocks` SET `userID` = ".$newuser->getId()." WHERE `document` = " . $this->_id . " AND `userID` = ".$this->_ownerID;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$queryStr = "UPDATE `tblDocumentLinks` SET `userID` = ".$newuser->getId()." WHERE `document` = " . $this->_id . " AND `userID` = ".$this->_ownerID;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$queryStr = "UPDATE `tblDocumentFiles` SET `userID` = ".$newuser->getId()." WHERE `document` = " . $this->_id . " AND `userID` = ".$this->_ownerID;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$this->_ownerID = $newuser->getID();
		$this->_owner = $newuser;

		$db->commitTransaction();
		return true;
	} /* }}} */

} /* }}} */


/**
 * Class to represent content of a document
 *
 * Each document has content attached to it, often called a 'version' of the
 * document. The document content represents a file on the disk with some
 * meta data stored in the database. A document content has a version number
 * which is incremented with each replacement of the old content. Old versions
 * are kept unless they are explicitly deleted by
 * {@link LetoDMS_Core_Document::removeContent()}.
 *
 * @category   DMS
 * @package    LetoDMS_Core
 * @author     Markus Westphal, Malcolm Cowe, Matteo Lucarelli,
 *             Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Core_DocumentContent extends LetoDMS_Core_Object { /* {{{ */

	/**
	 * Recalculate the status of a document
	 * The methods checks the review and approval status and sets the
	 * status of the document accordingly.
	 * If status is S_RELEASED and version has workflow set status
	 * to S_IN_WORKFLOW
	 * If status is S_RELEASED and there are reviewers set status S_DRAFT_REV
	 * If status is S_RELEASED or S_DRAFT_REV and there are approvers set
	 * status S_DRAFT_APP
	 * If status is draft and there are no approver and no reviewers set
	 * status to S_RELEASED
	 * The status of a document with the current status S_OBSOLETE, S_REJECTED,
	 * or S_EXPIRED will not be changed unless the parameter
	 * $ignorecurrentstatus is set to true.
	 * This method will call {@see LetoDMS_Core_DocumentContent::setStatus()}
	 * which checks if the state has actually changed. This is, why this
	 * function can be called at any time without harm to the status log.
	 *
	 * @param boolean $ignorecurrentstatus ignore the current status and
	 *        recalculate a new status in any case
	 * @param object $user the user initiating this method
	 * @param string $msg message stored in status log when status is set
	 */
	function verifyStatus($ignorecurrentstatus=false, $user=null, $msg='') { /* {{{ */

		unset($this->_status);
		$st=$this->getStatus();

		if (!$ignorecurrentstatus && ($st["status"]==S_OBSOLETE || $st["status"]==S_REJECTED || $st["status"]==S_EXPIRED )) return;

		unset($this->_workflow); // force to be reloaded from DB
		$hasworkflow =  $this->getWorkflow() ? true : false;

		$pendingReview=false;
		unset($this->_reviewStatus);  // force to be reloaded from DB
		$reviewStatus=$this->getReviewStatus();
		if (is_array($reviewStatus) && count($reviewStatus)>0) {
			foreach ($reviewStatus as $r){
				if ($r["status"]==0){
					$pendingReview=true;
					break;
				}
			}
		}
		$pendingApproval=false;
		unset($this->_approvalStatus);  // force to be reloaded from DB
		$approvalStatus=$this->getApprovalStatus();
		if (is_array($approvalStatus) && count($approvalStatus)>0) {
			foreach ($approvalStatus as $a){
				if ($a["status"]==0){
					$pendingApproval=true;
					break;
				}
			}
		}

		unset($this->_workflow); // force to be reloaded from DB
		if ($this->getWorkflow()) $this->setStatus(S_IN_WORKFLOW,$msg,$user);
		elseif ($pendingReview) $this->setStatus(S_DRAFT_REV,$msg,$user);
		elseif ($pendingApproval) $this->setStatus(S_DRAFT_APP,$msg,$user);
		else $this->setStatus(S_RELEASED,$msg,$user);
	} /* }}} */

	function __construct($id, $document, $version, $comment, $date, $userID, $dir, $orgFileName, $fileType, $mimeType, $fileSize=0, $checksum='') { /* {{{ */
		parent::__construct($id);
		$this->_document = $document;
		$this->_version = (int) $version;
		$this->_comment = $comment;
		$this->_date = $date;
		$this->_userID = (int) $userID;
		$this->_dir = $dir;
		$this->_orgFileName = $orgFileName;
		$this->_fileType = $fileType;
		$this->_mimeType = $mimeType;
		$this->_dms = $document->_dms;
		if(!$fileSize) {
			$this->_fileSize = LetoDMS_Core_File::fileSize($this->_dms->contentDir . $this->getPath());
		} else {
			$this->_fileSize = $fileSize;
		}
		$this->_checksum = $checksum;
		$this->_workflow = null;
		$this->_workflowState = null;
	} /* }}} */

	function getVersion() { return $this->_version; }
	function getComment() { return $this->_comment; }
	function getDate() { return $this->_date; }
	function getOriginalFileName() { return $this->_orgFileName; }
	function getFileType() { return $this->_fileType; }
	function getFileName(){ return $this->_version . $this->_fileType; }
	function getDir() { return $this->_dir; }
	function getMimeType() { return $this->_mimeType; }
	function getDocument() { return $this->_document; }

	function getUser() { /* {{{ */
		if (!isset($this->_user))
			$this->_user = $this->_document->_dms->getUser($this->_userID);
		return $this->_user;
	} /* }}} */

	function getPath() { return $this->_document->getDir() . $this->_version . $this->_fileType; }

	function setDate($date = false) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		if(!$date)
			$date = time();
		else {
			if(!is_numeric($date))
				return false;
		}

		$queryStr = "UPDATE `tblDocumentContent` SET `date` = ".(int) $date." WHERE `document` = " . $this->_document->getID() .	" AND `version` = " . $this->_version;
		if (!$db->getResult($queryStr))
			return false;

		$this->_date = $date;

		return true;
	} /* }}} */

	function getFileSize() { /* {{{ */
		return $this->_fileSize;
	} /* }}} */

	/**
	 * Set file size by reading the file
	 */
	function setFileSize() { /* {{{ */
		$filesize = LetoDMS_Core_File::fileSize($this->_dms->contentDir . $this->_document->getDir() . $this->getFileName());
		if($filesize === false)
			return false;

		$db = $this->_document->_dms->getDB();
		$queryStr = "UPDATE `tblDocumentContent` SET `fileSize` = ".$filesize." where `document` = " . $this->_document->getID() .  " AND `version` = " . $this->_version;
		if (!$db->getResult($queryStr))
			return false;
		$this->_fileSize = $filesize;

		return true;
	} /* }}} */

	function getChecksum() { /* {{{ */
		return $this->_checksum;
	} /* }}} */

	/**
	 * Set checksum by reading the file
	 */
	function setChecksum() { /* {{{ */
		$checksum = LetoDMS_Core_File::checksum($this->_dms->contentDir . $this->_document->getDir() . $this->getFileName());
		if($checksum === false)
			return false;

		$db = $this->_document->_dms->getDB();
		$queryStr = "UPDATE `tblDocumentContent` SET `checksum` = ".$db->qstr($checksum)." where `document` = " . $this->_document->getID() .  " AND `version` = " . $this->_version;
		if (!$db->getResult($queryStr))
			return false;
		$this->_checksum = $checksum;

		return true;
	} /* }}} */

	function setComment($newComment) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		$queryStr = "UPDATE `tblDocumentContent` SET `comment` = ".$db->qstr($newComment)." WHERE `document` = " . $this->_document->getID() .	" AND `version` = " . $this->_version;
		if (!$db->getResult($queryStr))
			return false;

		$this->_comment = $newComment;

		return true;
	} /* }}} */

	/**
	 * Get the latest status of the content
	 *
	 * The status of the content reflects its current review, approval or workflow
	 * state. A status can be a negative or positive number or 0. A negative
	 * numbers indicate a missing approval, review or an obsolete content.
	 * Positive numbers indicate some kind of approval or workflow being
	 * active, but not necessarily a release.
	 * S_DRAFT_REV, 0
	 * S_DRAFT_APP, 1
	 * S_RELEASED, 2
	 * S_IN_WORKFLOW, 3
	 * S_REJECTED, -1
	 * S_OBSOLETE, -2
	 * S_EXPIRED, -3
	 * When a content is inserted and does not need approval nor review,
	 * then its status is set to S_RELEASED immediately. Any change of
	 * the status is monitored in the table tblDocumentStatusLog. This
	 * function will always return the latest entry for the content.
	 *
	 * @return array latest record from tblDocumentStatusLog
	 */
	function getStatus($limit=1) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		if (!is_numeric($limit)) return false;

		// Retrieve the current overall status of the content represented by
		// this object.
		if (!isset($this->_status)) {
			$queryStr=
				"SELECT `tblDocumentStatus`.*, `tblDocumentStatusLog`.`status`, ".
				"`tblDocumentStatusLog`.`comment`, `tblDocumentStatusLog`.`date`, ".
				"`tblDocumentStatusLog`.`userID` ".
				"FROM `tblDocumentStatus` ".
				"LEFT JOIN `tblDocumentStatusLog` USING (`statusID`) ".
				"WHERE `tblDocumentStatus`.`documentID` = '". $this->_document->getID() ."' ".
				"AND `tblDocumentStatus`.`version` = '". $this->_version ."' ".
				"ORDER BY `tblDocumentStatusLog`.`statusLogID` DESC LIMIT ".(int) $limit;

			$res = $db->getResultArray($queryStr);
			if (is_bool($res) && !$res)
				return false;
			if (count($res)!=1)
				return false;
			$this->_status = $res[0];
		}
		return $this->_status;
	} /* }}} */

	/**
	 * Get current and former states of the document content
	 *
	 * @param integer $limit if not set all log entries will be returned
	 * @return array list of status changes
	 */
	function getStatusLog($limit=0) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		if (!is_numeric($limit)) return false;

		$queryStr=
			"SELECT `tblDocumentStatus`.*, `tblDocumentStatusLog`.`status`, ".
			"`tblDocumentStatusLog`.`comment`, `tblDocumentStatusLog`.`date`, ".
			"`tblDocumentStatusLog`.`userID` ".
			"FROM `tblDocumentStatus` ".
			"LEFT JOIN `tblDocumentStatusLog` USING (`statusID`) ".
			"WHERE `tblDocumentStatus`.`documentID` = '". $this->_document->getID() ."' ".
			"AND `tblDocumentStatus`.`version` = '". $this->_version ."' ".
			"ORDER BY `tblDocumentStatusLog`.`statusLogID` DESC ";
		if($limit)
			$queryStr .= "LIMIT ".(int) $limit;

		$res = $db->getResultArray($queryStr);
		if (is_bool($res) && !$res)
			return false;

		return $res;
	} /* }}} */

	/**
	 * Set the status of the content
	 * Setting the status means to add another entry into the table
	 * tblDocumentStatusLog. The method returns also false if the status
	 * is already set on the value passed to the method.
	 *
	 * @param integer $status new status of content
	 * @param string $comment comment for this status change
	 * @param object $updateUser user initiating the status change
	 * @return boolean true on success, otherwise false
	 */
	function setStatus($status, $comment, $updateUser, $date='') { /* {{{ */
		$db = $this->_document->_dms->getDB();

		if (!is_numeric($status)) return false;

		/* return an error if $updateuser is not set */
		if(!$updateUser)
			return false;

		// If the supplied value lies outside of the accepted range, return an
		// error.
		if ($status < -3 || $status > 3) {
			return false;
		}

		// Retrieve the current overall status of the content represented by
		// this object, if it hasn't been done already.
		if (!isset($this->_status)) {
			$this->getStatus();
		}
		if ($this->_status["status"]==$status) {
			return true;
		}
		if($date)
			$ddate = $db->qstr($date);
		else
			$ddate = $db->getCurrentDatetime();
		$queryStr = "INSERT INTO `tblDocumentStatusLog` (`statusID`, `status`, `comment`, `date`, `userID`) ".
			"VALUES ('". $this->_status["statusID"] ."', '". (int) $status ."', ".$db->qstr($comment).", ".$ddate.", '". $updateUser->getID() ."')";
		$res = $db->getResult($queryStr);
		if (is_bool($res) && !$res)
			return false;

		unset($this->_status);
		return true;
	} /* }}} */

	/**
	 * Rewrites the complete status log
	 *
	 * Attention: this function is highly dangerous.
	 * It removes an existing status log and rewrites it.
	 * This method was added for importing an xml dump.
	 *
	 * @param array $statuslog new status log with the newest log entry first.
	 * @return boolean true on success, otherwise false
	 */
	function rewriteStatusLog($statuslog) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		$queryStr= "SELECT `tblDocumentStatus`.* FROM `tblDocumentStatus` WHERE `tblDocumentStatus`.`documentID` = '". $this->_document->getID() ."' AND `tblDocumentStatus`.`version` = '". $this->_version ."' ";
		$res = $db->getResultArray($queryStr);
		if (is_bool($res) && !$res)
			return false;

		$statusID = $res[0]['statusID'];

		$db->startTransaction();

		/* First, remove the old entries */
		$queryStr = "DELETE from `tblDocumentStatusLog` where `statusID`=".$statusID;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		/* Second, insert the new entries */
		$statuslog = array_reverse($statuslog);
		foreach($statuslog as $log) {
			if(!LetoDMS_Core_DMS::checkDate($log['date'], 'Y-m-d H:i:s')) {
				$db->rollbackTransaction();
				return false;
			}
			$queryStr = "INSERT INTO `tblDocumentStatusLog` (`statusID`, `status`, `comment`, `date`, `userID`) ".
				"VALUES ('".$statusID ."', '".(int) $log['status']."', ".$db->qstr($log['comment']) .", ".$db->qstr($log['date']).", ".$log['user']->getID().")";
			if (!$db->getResult($queryStr)) {
				$db->rollbackTransaction();
				return false;
			}
		}

		$db->commitTransaction();
		return true;
	} /* }}} */


	/**
	 * Returns the access mode similar to a document
	 *
	 * There is no real access mode for document content, so this is more
	 * like a virtual access mode, derived from the status of the document
	 * content. The function checks if {@link LetoDMS_Core_DMS::noReadForStatus}
	 * contains the status of the version and returns M_NONE if it exists and
	 * the user is not involved in a workflow or review/approval/revision.
	 * This method is called by all functions that returns the content e.g.
	 * {@link LetoDMS_Core_Document::getLatestContent()}
	 * It is also used by {@link LetoDMS_Core_Document::getAccessMode()} to
	 * prevent access on the whole document if there is no accessible version.
	 *
	 * FIXME: This function only works propperly if $u is the currently logged in
	 * user, because noReadForStatus will be set for this user.
	 * FIXED: instead of using $dms->noReadForStatus it is take from the user's role
	 *
	 * @param object $u user
	 * @return integer either M_NONE or M_READ
	 */
	function getAccessMode($u) { /* {{{ */
		$dms = $this->_document->_dms;

		/* Check if 'onCheckAccessDocumentContent' callback is set */
		if(isset($this->_dms->callbacks['onCheckAccessDocumentContent'])) {
			foreach($this->_dms->callbacks['onCheckAccessDocumentContent'] as $callback) {
				if(($ret = call_user_func($callback[0], $callback[1], $this, $u)) > 0) {
					return $ret;
				}
			}
		}

		return M_READ;

		if(!$u)
			return M_NONE;

		/* If read access isn't further restricted by status, than grant read access */
		if(!$dms->noReadForStatus)
			return M_READ;
		$noReadForStatus = $dms->noReadForStatus;

		/* If the current status is not in list of status without read access, then grant read access */
		if(!in_array($this->getStatus()['status'], $noReadForStatus))
			return M_READ;

		/* Administrators have unrestricted access */
		if ($u->isAdmin()) return M_READ;

		/* The owner of the document has unrestricted access */
		$owner = $this->_document->getOwner();
		if ($u->getID() == $owner->getID()) return M_READ;

		/* Read/Write access on the document will also grant access on the version */
		if($this->_document->getAccessMode($u) >= M_READWRITE) return M_READ;

		/* At this point the current status is in the list of status without read access.
		 * The only way to still gain read access is, if the user is involved in the
		 * process, e.g. is a reviewer, approver or an active person in the workflow.
		 */
		$s = $this->getStatus();
		switch($s['status']) {
		case S_DRAFT_REV:
			$status = $this->getReviewStatus();
			foreach ($status as $r) {
				if($r['status'] != -2) // Check if reviewer was removed
					switch ($r["type"]) {
					case 0: // Reviewer is an individual.
						if($u->getId() == $r["required"])
							return M_READ;
						break;
					case 1: // Reviewer is a group.
						$required = $dms->getGroup($r["required"]);
						if (is_object($required) && $required->isMember($u))
							return M_READ;
						break;
					}
			}
			break;
		case S_DRAFT_APP:
			$status = $this->getApprovalStatus();
			foreach ($status as $r) {
				if($r['status'] != -2) // Check if approver was removed
					switch ($r["type"]) {
					case 0: // Reviewer is an individual.
						if($u->getId() == $r["required"])
							return M_READ;
						break;
					case 1: // Reviewer is a group.
						$required = $dms->getGroup($r["required"]);
						if (is_object($required) && $required->isMember($u))
							return M_READ;
						break;
					}
			}
			break;
		case S_RELEASED:
			break;
		case S_IN_WORKFLOW:
			if(!$this->_workflow)
				$this->getWorkflow();

			if($this->_workflow) {
				if (!$this->_workflowState)
					$this->getWorkflowState();
				$transitions = $this->_workflow->getNextTransitions($this->_workflowState);
				foreach($transitions as $transition) {
					if($this->triggerWorkflowTransitionIsAllowed($u, $transition))
						return M_READ;
				}
			}
			break;
		case S_REJECTED:
			break;
		case S_OBSOLETE:
			break;
		case S_EXPIRED:
			break;
		}

		return M_NONE;
	} /* }}} */

	/**
	 * Get the current review status of the document content
	 * The review status is a list of reviews and its current status
	 *
	 * @param integer $limit the number of recent status changes per reviewer
	 * @return array list of review status
	 */
	function getReviewStatus($limit=1) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		if (!is_numeric($limit)) return false;

		// Retrieve the current status of each assigned reviewer for the content
		// represented by this object.
		// FIXME: caching was turned off to make list of review log in ViewDocument
		// possible
		if (1 || !isset($this->_reviewStatus)) {
			/* First get a list of all reviews for this document content */
			$queryStr=
				"SELECT `reviewID` FROM `tblDocumentReviewers` WHERE `version`='".$this->_version
				."' AND `documentID` = '". $this->_document->getID() ."' ";
			$recs = $db->getResultArray($queryStr);
			if (is_bool($recs) && !$recs)
				return false;
			$this->_reviewStatus = array();
			if($recs) {
				foreach($recs as $rec) {
					$queryStr=
						"SELECT `tblDocumentReviewers`.*, `tblDocumentReviewLog`.`reviewLogID`, `tblDocumentReviewLog`.`status`, ".
						"`tblDocumentReviewLog`.`comment`, `tblDocumentReviewLog`.`date`, ".
						"`tblDocumentReviewLog`.`userID`, `tblUsers`.`fullName`, `tblGroups`.`name` AS `groupName` ".
						"FROM `tblDocumentReviewers` ".
						"LEFT JOIN `tblDocumentReviewLog` USING (`reviewID`) ".
						"LEFT JOIN `tblUsers` on `tblUsers`.`id` = `tblDocumentReviewers`.`required`".
						"LEFT JOIN `tblGroups` on `tblGroups`.`id` = `tblDocumentReviewers`.`required`".
						"WHERE `tblDocumentReviewers`.`reviewID` = '". $rec['reviewID'] ."' ".
						"ORDER BY `tblDocumentReviewLog`.`reviewLogID` DESC LIMIT ".(int) $limit;

					$res = $db->getResultArray($queryStr);
					if (is_bool($res) && !$res) {
						unset($this->_reviewStatus);
						return false;
					}
					foreach($res as &$t) {
						$filename = $this->_dms->contentDir . $this->_document->getDir().'r'.$t['reviewLogID'];
						if(file_exists($filename))
							$t['file'] = $filename;
						else
							$t['file'] = '';
					}
					$this->_reviewStatus = array_merge($this->_reviewStatus, $res);
				}
			}
		}
		return $this->_reviewStatus;
	} /* }}} */

	/**
	 * Rewrites the complete review log
	 *
	 * Attention: this function is highly dangerous.
	 * It removes an existing review log and rewrites it.
	 * This method was added for importing an xml dump.
	 *
	 * @param array $reviewlog new status log with the newest log entry first.
	 * @return boolean true on success, otherwise false
	 */
	function rewriteReviewLog($reviewers) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		$queryStr= "SELECT `tblDocumentReviewers`.* FROM `tblDocumentReviewers` WHERE `tblDocumentReviewers`.`documentID` = '". $this->_document->getID() ."' AND `tblDocumentReviewers`.`version` = '". $this->_version ."' ";
		$res = $db->getResultArray($queryStr);
		if (is_bool($res) && !$res)
			return false;

		$db->startTransaction();

		if($res) {
			foreach($res as $review) {
				$reviewID = $review['reviewID'];

				/* First, remove the old entries */
				$queryStr = "DELETE from `tblDocumentReviewLog` where `reviewID`=".$reviewID;
				if (!$db->getResult($queryStr)) {
					$db->rollbackTransaction();
					return false;
				}

				$queryStr = "DELETE from `tblDocumentReviewers` where `reviewID`=".$reviewID;
				if (!$db->getResult($queryStr)) {
					$db->rollbackTransaction();
					return false;
				}
			}
		}

		/* Second, insert the new entries */
		foreach($reviewers as $review) {
			$queryStr = "INSERT INTO `tblDocumentReviewers` (`documentID`, `version`, `type`, `required`) ".
				"VALUES ('".$this->_document->getID()."', '".$this->_version."', ".$review['type'] .", ".(is_object($review['required']) ? $review['required']->getID() : (int) $review['required']).")";
			if (!$db->getResult($queryStr)) {
				$db->rollbackTransaction();
				return false;
			}
			$reviewID = $db->getInsertID('tblDocumentReviewers', 'reviewID');
			$reviewlog = array_reverse($review['logs']);
			foreach($reviewlog as $log) {
				if(!LetoDMS_Core_DMS::checkDate($log['date'], 'Y-m-d H:i:s')) {
					$db->rollbackTransaction();
					return false;
				}
				$queryStr = "INSERT INTO `tblDocumentReviewLog` (`reviewID`, `status`, `comment`, `date`, `userID`) ".
					"VALUES ('".$reviewID ."', '".(int) $log['status']."', ".$db->qstr($log['comment']) .", ".$db->qstr($log['date']).", ".(is_object($log['user']) ? $log['user']->getID() : (int) $log['user']).")";
				if (!$db->getResult($queryStr)) {
					$db->rollbackTransaction();
					return false;
				}
				$reviewLogID = $db->getInsertID('tblDocumentReviewLog', 'reviewLogID');
				if(!empty($log['file'])) {
					LetoDMS_Core_File::copyFile($log['file'], $this->_dms->contentDir . $this->_document->getDir() . 'r' . $reviewLogID);
				}
			}
		}

		$db->commitTransaction();
		return true;
	} /* }}} */

	/**
	 * Get the current approval status of the document content
	 * The approval status is a list of approvals and its current status
	 *
	 * @param integer $limit the number of recent status changes per approver
	 * @return array list of approval status
	 */
	function getApprovalStatus($limit=1) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		if (!is_numeric($limit)) return false;

		// Retrieve the current status of each assigned approver for the content
		// represented by this object.
		// FIXME: caching was turned off to make list of approval log in ViewDocument
		// possible
		if (1 || !isset($this->_approvalStatus)) {
			/* First get a list of all approvals for this document content */
			$queryStr=
				"SELECT `approveID` FROM `tblDocumentApprovers` WHERE `version`='".$this->_version
				."' AND `documentID` = '". $this->_document->getID() ."' ";
			$recs = $db->getResultArray($queryStr);
			if (is_bool($recs) && !$recs)
				return false;
			$this->_approvalStatus = array();
			if($recs) {
				foreach($recs as $rec) {
					$queryStr=
						"SELECT `tblDocumentApprovers`.*, `tblDocumentApproveLog`.`approveLogID`, `tblDocumentApproveLog`.`status`, ".
						"`tblDocumentApproveLog`.`comment`, `tblDocumentApproveLog`.`date`, ".
						"`tblDocumentApproveLog`.`userID`, `tblUsers`.`fullName`, `tblGroups`.`name` AS `groupName` ".
						"FROM `tblDocumentApprovers` ".
						"LEFT JOIN `tblDocumentApproveLog` USING (`approveID`) ".
						"LEFT JOIN `tblUsers` on `tblUsers`.`id` = `tblDocumentApprovers`.`required` ".
						"LEFT JOIN `tblGroups` on `tblGroups`.`id` = `tblDocumentApprovers`.`required`".
						"WHERE `tblDocumentApprovers`.`approveID` = '". $rec['approveID'] ."' ".
						"ORDER BY `tblDocumentApproveLog`.`approveLogID` DESC LIMIT ".(int) $limit;

					$res = $db->getResultArray($queryStr);
					if (is_bool($res) && !$res) {
						unset($this->_approvalStatus);
						return false;
					}
					foreach($res as &$t) {
						$filename = $this->_dms->contentDir . $this->_document->getDir().'a'.$t['approveLogID'];
						if(file_exists($filename))
							$t['file'] = $filename;
						else
							$t['file'] = '';
					}
					$this->_approvalStatus = array_merge($this->_approvalStatus, $res);
				}
			}
		}
		return $this->_approvalStatus;
	} /* }}} */

	/**
	 * Rewrites the complete approval log
	 *
	 * Attention: this function is highly dangerous.
	 * It removes an existing review log and rewrites it.
	 * This method was added for importing an xml dump.
	 *
	 * @param array $reviewlog new status log with the newest log entry first.
	 * @return boolean true on success, otherwise false
	 */
	function rewriteApprovalLog($reviewers) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		$queryStr= "SELECT `tblDocumentApprovers`.* FROM `tblDocumentApprovers` WHERE `tblDocumentApprovers`.`documentID` = '". $this->_document->getID() ."' AND `tblDocumentApprovers`.`version` = '". $this->_version ."' ";
		$res = $db->getResultArray($queryStr);
		if (is_bool($res) && !$res)
			return false;

		$db->startTransaction();

		if($res) {
			foreach($res as $review) {
				$reviewID = $review['reviewID'];

				/* First, remove the old entries */
				$queryStr = "DELETE from `tblDocumentApproveLog` where `approveID`=".$reviewID;
				if (!$db->getResult($queryStr)) {
					$db->rollbackTransaction();
					return false;
				}

				$queryStr = "DELETE from `tblDocumentApprovers` where `approveID`=".$reviewID;
				if (!$db->getResult($queryStr)) {
					$db->rollbackTransaction();
					return false;
				}
			}
		}

		/* Second, insert the new entries */
		foreach($reviewers as $review) {
			$queryStr = "INSERT INTO `tblDocumentApprovers` (`documentID`, `version`, `type`, `required`) ".
				"VALUES ('".$this->_document->getID()."', '".$this->_version."', ".$review['type'] .", ".(is_object($review['required']) ? $review['required']->getID() : (int) $review['required']).")";
			if (!$db->getResult($queryStr)) {
				$db->rollbackTransaction();
				return false;
			}
			$reviewID = $db->getInsertID('tblDocumentApprovers', 'approveID');
			$reviewlog = array_reverse($review['logs']);
			foreach($reviewlog as $log) {
				if(!LetoDMS_Core_DMS::checkDate($log['date'], 'Y-m-d H:i:s')) {
					$db->rollbackTransaction();
					return false;
				}
				$queryStr = "INSERT INTO `tblDocumentApproveLog` (`approveID`, `status`, `comment`, `date`, `userID`) ".
					"VALUES ('".$reviewID ."', '".(int) $log['status']."', ".$db->qstr($log['comment']) .", ".$db->qstr($log['date']).", ".(is_object($log['user']) ? $log['user']->getID() : (int) $log['user']).")";
				if (!$db->getResult($queryStr)) {
					$db->rollbackTransaction();
					return false;
				}
				$approveLogID = $db->getInsertID('tblDocumentApproveLog', 'approveLogID');
				if(!empty($log['file'])) {
					LetoDMS_Core_File::copyFile($log['file'], $this->_dms->contentDir . $this->_document->getDir() . 'a' . $approveLogID);
				}
			}
		}

		$db->commitTransaction();
		return true;
	} /* }}} */

	function addIndReviewer($user, $requestUser) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		$userID = $user->getID();

		// Get the list of users and groups with read access to this document.
		if($this->_document->getAccessMode($user) < M_READ) {
			return -2;
		}

		// Check to see if the user has already been added to the review list.
		$reviewStatus = $user->getReviewStatus($this->_document->getID(), $this->_version);
		if (is_bool($reviewStatus) && !$reviewStatus) {
			return -1;
		}
		$indstatus = false;
		if (count($reviewStatus["indstatus"]) > 0) {
			$indstatus = array_pop($reviewStatus["indstatus"]);
			if($indstatus["status"]!=-2) {
				// User is already on the list of reviewers; return an error.
				return -3;
			}
		}

		// Add the user into the review database.
		if (!$indstatus || ($indstatus && $indstatus["status"]!=-2)) {
			$queryStr = "INSERT INTO `tblDocumentReviewers` (`documentID`, `version`, `type`, `required`) ".
				"VALUES ('". $this->_document->getID() ."', '". $this->_version ."', '0', '". $userID ."')";
			$res = $db->getResult($queryStr);
			if (is_bool($res) && !$res) {
				return -1;
			}
			$reviewID = $db->getInsertID('tblDocumentReviewers', 'reviewID');
		}
		else {
			$reviewID = isset($indstatus["reviewID"]) ? $indstatus["reviewID"] : NULL;
		}

		$queryStr = "INSERT INTO `tblDocumentReviewLog` (`reviewID`, `status`, `comment`, `date`, `userID`) ".
			"VALUES ('". $reviewID ."', '0', '', ".$db->getCurrentDatetime().", '". $requestUser->getID() ."')";
		$res = $db->getResult($queryStr);
		if (is_bool($res) && !$res) {
			return -1;
		}

		// Add reviewer to event notification table.
		//$this->_document->addNotify($userID, true);

		return 0;
	} /* }}} */

	function addGrpReviewer($group, $requestUser) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		$groupID = $group->getID();

		// Get the list of users and groups with read access to this document.
		if (!isset($this->_readAccessList)) {
			// TODO: error checking.
			$this->_readAccessList = $this->_document->getReadAccessList();
		}
		$approved = false;
		foreach ($this->_readAccessList["groups"] as $appGroup) {
			if ($groupID == $appGroup->getID()) {
				$approved = true;
				break;
			}
		}
		if (!$approved) {
			return -2;
		}

		// Check to see if the group has already been added to the review list.
		$reviewStatus = $group->getReviewStatus($this->_document->getID(), $this->_version);
		if (is_bool($reviewStatus) && !$reviewStatus) {
			return -1;
		}
		if (count($reviewStatus) > 0 && $reviewStatus[0]["status"]!=-2) {
			// Group is already on the list of reviewers; return an error.
			return -3;
		}

		// Add the group into the review database.
		if (!isset($reviewStatus[0]["status"]) || (isset($reviewStatus[0]["status"]) && $reviewStatus[0]["status"]!=-2)) {
			$queryStr = "INSERT INTO `tblDocumentReviewers` (`documentID`, `version`, `type`, `required`) ".
				"VALUES ('". $this->_document->getID() ."', '". $this->_version ."', '1', '". $groupID ."')";
			$res = $db->getResult($queryStr);
			if (is_bool($res) && !$res) {
				return -1;
			}
			$reviewID = $db->getInsertID('tblDocumentReviewers', 'reviewID');
		}
		else {
			$reviewID = isset($reviewStatus[0]["reviewID"])?$reviewStatus[0]["reviewID"]:NULL;
		}

		$queryStr = "INSERT INTO `tblDocumentReviewLog` (`reviewID`, `status`, `comment`, `date`, `userID`) ".
			"VALUES ('". $reviewID ."', '0', '', ".$db->getCurrentDatetime().", '". $requestUser->getID() ."')";
		$res = $db->getResult($queryStr);
		if (is_bool($res) && !$res) {
			return -1;
		}

		// Add reviewer to event notification table.
		//$this->_document->addNotify($groupID, false);

		return 0;
	} /* }}} */

	/**
	 * Add a review to the document content
	 *
	 * This method will add an entry to the table tblDocumentReviewLog.
	 * It will first check if the user is ment to review the document version.
	 * It not the return value is -3.
	 * Next it will check if the users has been removed from the list of
	 * reviewers. In that case -4 will be returned.
	 * If the given review status has been set by the user before, it cannot
	 * be set again and 0 will be returned. Іf the review could be succesfully
	 * added, the review log id will be returned.
	 *
	 * @see LetoDMS_Core_DocumentContent::setApprovalByInd()
	 * @param object $user user doing the review
	 * @param object $requestUser user asking for the review, this is mostly
	 * the user currently logged in.
	 * @param integer $status status of review
	 * @param string $comment comment for review
	 * @return integer new review log id
	 */
	function setReviewByInd($user, $requestUser, $status, $comment, $file='') { /* {{{ */
		$db = $this->_document->_dms->getDB();

		// Check to see if the user can be removed from the review list.
		$reviewStatus = $user->getReviewStatus($this->_document->getID(), $this->_version);
		if (is_bool($reviewStatus) && !$reviewStatus) {
			return -1;
		}
		if (count($reviewStatus["indstatus"])==0) {
			// User is not assigned to review this document. No action required.
			// Return an error.
			return -3;
		}
		$indstatus = array_pop($reviewStatus["indstatus"]);
		if ($indstatus["status"]==-2) {
			// User has been deleted from reviewers
			return -4;
		}
		// Check if the status is really different from the current status
		if ($indstatus["status"] == $status)
			return 0;

		$queryStr = "INSERT INTO `tblDocumentReviewLog` (`reviewID`, `status`,
			`comment`, `date`, `userID`) ".
			"VALUES ('". $indstatus["reviewID"] ."', '".
			(int) $status ."', ".$db->qstr($comment).", ".$db->getCurrentDatetime().", '".
			$requestUser->getID() ."')";
		$res=$db->getResult($queryStr);
		if (is_bool($res) && !$res)
			return -1;

		$reviewLogID = $db->getInsertID('tblDocumentReviewLog', 'reviewLogID');
		if($file) {
			LetoDMS_Core_File::copyFile($file, $this->_dms->contentDir . $this->_document->getDir() . 'r' . $reviewLogID);
		}
		return $reviewLogID;
 } /* }}} */

	/**
	 * Add a review to the document content
	 *
	 * This method is similar to
	 * {@see LetoDMS_Core_DocumentContent::setReviewByInd()} but adds a review
	 * for a group instead of a user.
	 *
	 * @param object $group group doing the review
	 * @param object $requestUser user asking for the review, this is mostly
	 * the user currently logged in.
	 * @param integer $status status of review
	 * @param string $comment comment for review
	 * @return integer new review log id
	 */
	function setReviewByGrp($group, $requestUser, $status, $comment, $file='') { /* {{{ */
		$db = $this->_document->_dms->getDB();

		// Check to see if the user can be removed from the review list.
		$reviewStatus = $group->getReviewStatus($this->_document->getID(), $this->_version);
		if (is_bool($reviewStatus) && !$reviewStatus) {
			return -1;
		}
		if (count($reviewStatus)==0) {
			// User is not assigned to review this document. No action required.
			// Return an error.
			return -3;
		}
		if ($reviewStatus[0]["status"]==-2) {
			// Group has been deleted from reviewers
			return -4;
		}

		// Check if the status is really different from the current status
		if ($reviewStatus[0]["status"] == $status)
			return 0;

		$queryStr = "INSERT INTO `tblDocumentReviewLog` (`reviewID`, `status`,
			`comment`, `date`, `userID`) ".
			"VALUES ('". $reviewStatus[0]["reviewID"] ."', '".
			(int) $status ."', ".$db->qstr($comment).", ".$db->getCurrentDatetime().", '".
			$requestUser->getID() ."')";
		$res=$db->getResult($queryStr);
		if (is_bool($res) && !$res)
			return -1;
		else {
			$reviewLogID = $db->getInsertID('tblDocumentReviewLog', 'reviewLogID');
			if($file) {
				LetoDMS_Core_File::copyFile($file, $this->_dms->contentDir . $this->_document->getDir() . 'r' . $reviewLogID);
			}
			return $reviewLogID;
		}
 } /* }}} */

	function addIndApprover($user, $requestUser) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		$userID = $user->getID();

		// Get the list of users and groups with read access to this document.
		if($this->_document->getAccessMode($user) < M_READ) {
			return -2;
		}

		// Check to see if the user has already been added to the approvers list.
		$approvalStatus = $user->getApprovalStatus($this->_document->getID(), $this->_version);
		if (is_bool($approvalStatus) && !$approvalStatus) {
			return -1;
		}
		$indstatus = false;
		if (count($approvalStatus["indstatus"]) > 0) {
			$indstatus = array_pop($approvalStatus["indstatus"]);
			if($indstatus["status"]!=-2) {
				// User is already on the list of approverss; return an error.
				return -3;
			}
		}

		if ( !$indstatus || (isset($indstatus["status"]) && $indstatus["status"]!=-2)) {
			// Add the user into the approvers database.
			$queryStr = "INSERT INTO `tblDocumentApprovers` (`documentID`, `version`, `type`, `required`) ".
				"VALUES ('". $this->_document->getID() ."', '". $this->_version ."', '0', '". $userID ."')";
			$res = $db->getResult($queryStr);
			if (is_bool($res) && !$res) {
				return -1;
			}
			$approveID = $db->getInsertID('tblDocumentApprovers', 'approveID');
		}
		else {
			$approveID = isset($indstatus["approveID"]) ? $indstatus["approveID"] : NULL;
		}

		$queryStr = "INSERT INTO `tblDocumentApproveLog` (`approveID`, `status`, `comment`, `date`, `userID`) ".
			"VALUES ('". $approveID ."', '0', '', ".$db->getCurrentDatetime().", '". $requestUser->getID() ."')";
		$res = $db->getResult($queryStr);
		if (is_bool($res) && !$res) {
			return -1;
		}

		$approveLogID = $db->getInsertID('tblDocumentApproveLog', 'approveLogID');
		return $approveLogID;
	} /* }}} */

	function addGrpApprover($group, $requestUser) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		$groupID = $group->getID();

		// Get the list of users and groups with read access to this document.
		if (!isset($this->_readAccessList)) {
			// TODO: error checking.
			$this->_readAccessList = $this->_document->getReadAccessList();
		}
		$approved = false;
		foreach ($this->_readAccessList["groups"] as $appGroup) {
			if ($groupID == $appGroup->getID()) {
				$approved = true;
				break;
			}
		}
		if (!$approved) {
			return -2;
		}

		// Check to see if the group has already been added to the approver list.
		$approvalStatus = $group->getApprovalStatus($this->_document->getID(), $this->_version);
		if (is_bool($approvalStatus) && !$approvalStatus) {
			return -1;
		}
		if (count($approvalStatus) > 0 && $approvalStatus[0]["status"]!=-2) {
			// Group is already on the list of approvers; return an error.
			return -3;
		}

		// Add the group into the approver database.
		if (!isset($approvalStatus[0]["status"]) || (isset($approvalStatus[0]["status"]) && $approvalStatus[0]["status"]!=-2)) {
			$queryStr = "INSERT INTO `tblDocumentApprovers` (`documentID`, `version`, `type`, `required`) ".
				"VALUES ('". $this->_document->getID() ."', '". $this->_version ."', '1', '". $groupID ."')";
			$res = $db->getResult($queryStr);
			if (is_bool($res) && !$res) {
				return -1;
			}
			$approveID = $db->getInsertID('tblDocumentApprovers', 'approveID');
		}
		else {
			$approveID = isset($approvalStatus[0]["approveID"])?$approvalStatus[0]["approveID"]:NULL;
		}

		$queryStr = "INSERT INTO `tblDocumentApproveLog` (`approveID`, `status`, `comment`, `date`, `userID`) ".
			"VALUES ('". $approveID ."', '0', '', ".$db->getCurrentDatetime().", '". $requestUser->getID() ."')";
		$res = $db->getResult($queryStr);
		if (is_bool($res) && !$res) {
			return -1;
		}

		// Add approver to event notification table.
		//$this->_document->addNotify($groupID, false);

		$approveLogID = $db->getInsertID('tblDocumentApproveLog', 'approveLogID');
		return $approveLogID;
	} /* }}} */

	/**
	 * Sets approval status of a document content for a user
	 *
	 * This function can be used to approve or reject a document content, or
	 * to reset its approval state. In most cases this function will be
	 * called by an user, but  an admin may set the approval for
	 * somebody else.
	 * It is first checked if the user is in the list of approvers at all.
	 * Then it is check if the approval status is already -2. In both cases
	 * the function returns with an error.
	 *
	 * @see LetoDMS_Core_DocumentContent::setReviewByInd()
	 * @param object $user user in charge for doing the approval
	 * @param object $requestUser user actually calling this function
	 * @param integer $status the status of the approval, possible values are
	 *        0=unprocessed (maybe used to reset a status)
	 *        1=approved,
	 *       -1=rejected,
	 *       -2=user is deleted (use {link
	 *       LetoDMS_Core_DocumentContent::delIndApprover} instead)
	 * @param string $comment approval comment
	 * @return integer 0 on success, < 0 in case of an error
	 */
	function setApprovalByInd($user, $requestUser, $status, $comment, $file='') { /* {{{ */
		$db = $this->_document->_dms->getDB();

		// Check to see if the user can be removed from the approval list.
		$approvalStatus = $user->getApprovalStatus($this->_document->getID(), $this->_version);
		if (is_bool($approvalStatus) && !$approvalStatus) {
			return -1;
		}
		if (count($approvalStatus["indstatus"])==0) {
			// User is not assigned to approve this document. No action required.
			// Return an error.
			return -3;
		}
		$indstatus = array_pop($approvalStatus["indstatus"]);
		if ($indstatus["status"]==-2) {
			// User has been deleted from approvers
			return -4;
		}
		// Check if the status is really different from the current status
		if ($indstatus["status"] == $status)
			return 0;

		$queryStr = "INSERT INTO `tblDocumentApproveLog` (`approveID`, `status`,
			`comment`, `date`, `userID`) ".
			"VALUES ('". $indstatus["approveID"] ."', '".
			(int) $status ."', ".$db->qstr($comment).", ".$db->getCurrentDatetime().", '".
			$requestUser->getID() ."')";
		$res=$db->getResult($queryStr);
		if (is_bool($res) && !$res)
			return -1;

		$approveLogID = $db->getInsertID('tblDocumentApproveLog', 'approveLogID');
		if($file) {
			LetoDMS_Core_File::copyFile($file, $this->_dms->contentDir . $this->_document->getDir() . 'a' . $approveLogID);
		}
		return $approveLogID;
 } /* }}} */

	/**
	 * Sets approval status of a document content for a group
	 * The functions behaves like
	 * {link LetoDMS_Core_DocumentContent::setApprovalByInd} but does it for
	 * group instead of a user
	 */
	function setApprovalByGrp($group, $requestUser, $status, $comment, $file='') { /* {{{ */
		$db = $this->_document->_dms->getDB();

		// Check to see if the user can be removed from the approval list.
		$approvalStatus = $group->getApprovalStatus($this->_document->getID(), $this->_version);
		if (is_bool($approvalStatus) && !$approvalStatus) {
			return -1;
		}
		if (count($approvalStatus)==0) {
			// User is not assigned to approve this document. No action required.
			// Return an error.
			return -3;
		}
		if ($approvalStatus[0]["status"]==-2) {
			// Group has been deleted from approvers
			return -4;
		}

		// Check if the status is really different from the current status
		if ($approvalStatus[0]["status"] == $status)
			return 0;

		$queryStr = "INSERT INTO `tblDocumentApproveLog` (`approveID`, `status`,
			`comment`, `date`, `userID`) ".
			"VALUES ('". $approvalStatus[0]["approveID"] ."', '".
			(int) $status ."', ".$db->qstr($comment).", ".$db->getCurrentDatetime().", '".
			$requestUser->getID() ."')";
		$res=$db->getResult($queryStr);
		if (is_bool($res) && !$res)
			return -1;

		$approveLogID = $db->getInsertID('tblDocumentApproveLog', 'approveLogID');
		if($file) {
			LetoDMS_Core_File::copyFile($file, $this->_dms->contentDir . $this->_document->getDir() . 'a' . $approveLogID);
		}
		return $approveLogID;
 } /* }}} */

	function delIndReviewer($user, $requestUser, $msg='') { /* {{{ */
		$db = $this->_document->_dms->getDB();

		// Check to see if the user can be removed from the review list.
		$reviewStatus = $user->getReviewStatus($this->_document->getID(), $this->_version);
		if (is_bool($reviewStatus) && !$reviewStatus) {
			return -1;
		}
		if (count($reviewStatus["indstatus"])==0) {
			// User is not assigned to review this document. No action required.
			// Return an error.
			return -2;
		}
		$indstatus = array_pop($reviewStatus["indstatus"]);
		if ($indstatus["status"]!=0) {
			// User has already submitted a review or has already been deleted;
			// return an error.
			return -3;
		}

		$queryStr = "INSERT INTO `tblDocumentReviewLog` (`reviewID`, `status`, `comment`, `date`, `userID`) ".
			"VALUES ('". $indstatus["reviewID"] ."', '".S_LOG_USER_REMOVED."', ".$db->qstr($msg).", ".$db->getCurrentDatetime().", '". $requestUser->getID() ."')";
		$res = $db->getResult($queryStr);
		if (is_bool($res) && !$res) {
			return -1;
		}

		return 0;
	} /* }}} */

	function delGrpReviewer($group, $requestUser, $msg='') { /* {{{ */
		$db = $this->_document->_dms->getDB();

		$groupID = $group->getID();

		// Check to see if the user can be removed from the review list.
		$reviewStatus = $group->getReviewStatus($this->_document->getID(), $this->_version);
		if (is_bool($reviewStatus) && !$reviewStatus) {
			return -1;
		}
		if (count($reviewStatus)==0) {
			// User is not assigned to review this document. No action required.
			// Return an error.
			return -2;
		}
		if ($reviewStatus[0]["status"]!=0) {
			// User has already submitted a review or has already been deleted;
			// return an error.
			return -3;
		}

		$queryStr = "INSERT INTO `tblDocumentReviewLog` (`reviewID`, `status`, `comment`, `date`, `userID`) ".
			"VALUES ('". $reviewStatus[0]["reviewID"] ."', '".S_LOG_USER_REMOVED."', ".$db->qstr($msg).", ".$db->getCurrentDatetime().", '". $requestUser->getID() ."')";
		$res = $db->getResult($queryStr);
		if (is_bool($res) && !$res) {
			return -1;
		}

		return 0;
	} /* }}} */

	function delIndApprover($user, $requestUser, $msg='') { /* {{{ */
		$db = $this->_document->_dms->getDB();

		$userID = $user->getID();

		// Check to see if the user can be removed from the approval list.
		$approvalStatus = $user->getApprovalStatus($this->_document->getID(), $this->_version);
		if (is_bool($approvalStatus) && !$approvalStatus) {
			return -1;
		}
		if (count($approvalStatus["indstatus"])==0) {
			// User is not assigned to approve this document. No action required.
			// Return an error.
			return -2;
		}
		$indstatus = array_pop($approvalStatus["indstatus"]);
		if ($indstatus["status"]!=0) {
			// User has already submitted an approval or has already been deleted;
			// return an error.
			return -3;
		}

		$queryStr = "INSERT INTO `tblDocumentApproveLog` (`approveID`, `status`, `comment`, `date`, `userID`) ".
			"VALUES ('". $indstatus["approveID"] ."', '".S_LOG_USER_REMOVED."', ".$db->qstr($msg).", ".$db->getCurrentDatetime().", '". $requestUser->getID() ."')";
		$res = $db->getResult($queryStr);
		if (is_bool($res) && !$res) {
			return -1;
		}

		return 0;
	} /* }}} */

	function delGrpApprover($group, $requestUser, $msg='') { /* {{{ */
		$db = $this->_document->_dms->getDB();

		$groupID = $group->getID();

		// Check to see if the user can be removed from the approver list.
		$approvalStatus = $group->getApprovalStatus($this->_document->getID(), $this->_version);
		if (is_bool($approvalStatus) && !$approvalStatus) {
			return -1;
		}
		if (count($approvalStatus)==0) {
			// User is not assigned to approve this document. No action required.
			// Return an error.
			return -2;
		}
		if ($approvalStatus[0]["status"]!=0) {
			// User has already submitted an approval or has already been deleted;
			// return an error.
			return -3;
		}

		$queryStr = "INSERT INTO `tblDocumentApproveLog` (`approveID`, `status`, `comment`, `date`, `userID`) ".
			"VALUES ('". $approvalStatus[0]["approveID"] ."', '".S_LOG_USER_REMOVED."', ".$db->qstr($msg).", ".$db->getCurrentDatetime().", '". $requestUser->getID() ."')";
		$res = $db->getResult($queryStr);
		if (is_bool($res) && !$res) {
			return -1;
		}

		return 0;
	} /* }}} */

	/**
	 * Set state of workflow assigned to the document content
	 *
	 * @param object $state
	 */
	function setWorkflowState($state) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		if($this->_workflow) {
			$queryStr = "UPDATE `tblWorkflowDocumentContent` set `state`=". $state->getID() ." WHERE `workflow`=". intval($this->_workflow->getID()). " AND `document`=". intval($this->_document->getID()) ." AND version=". intval($this->_version) ."";
			if (!$db->getResult($queryStr)) {
				return false;
			}
			$this->_workflowState = $state;
			return true;
		}
		return false;
	} /* }}} */

	/**
	 * Get state of workflow assigned to the document content
	 *
	 * @return object/boolean an object of class LetoDMS_Core_Workflow_State
	 *         or false in case of error, e.g. the version has not a workflow
	 */
	function getWorkflowState() { /* {{{ */
		$db = $this->_document->_dms->getDB();

		if(!$this->_workflow)
			$this->getWorkflow();

		if(!$this->_workflow)
			return false;

		if (!$this->_workflowState) {
			$queryStr=
				"SELECT b.* FROM `tblWorkflowDocumentContent` a LEFT JOIN `tblWorkflowStates` b ON a.`state` = b.id WHERE `workflow`=". intval($this->_workflow->getID())
				." AND a.`version`='".$this->_version
				."' AND a.`document` = '". $this->_document->getID() ."' ";
			$recs = $db->getResultArray($queryStr);
			if (is_bool($recs) && !$recs)
				return false;
			$this->_workflowState = new LetoDMS_Core_Workflow_State($recs[0]['id'], $recs[0]['name'], $recs[0]['maxtime'], $recs[0]['precondfunc'], $recs[0]['documentstatus']);
			$this->_workflowState->setDMS($this->_document->_dms);
		}
		return $this->_workflowState;
	} /* }}} */

	/**
	 * Assign a workflow to a document
	 *
	 * @param object $workflow
	 */
	function setWorkflow($workflow, $user) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		$this->getWorkflow();
		if($workflow && is_object($workflow)) {
			$db->startTransaction();
			$initstate = $workflow->getInitState();
			$queryStr = "INSERT INTO `tblWorkflowDocumentContent` (`workflow`, `document`, `version`, `state`, `date`) VALUES (". $workflow->getID(). ", ". $this->_document->getID() .", ". $this->_version .", ".$initstate->getID().", ".$db->getCurrentDatetime().")";
			if (!$db->getResult($queryStr)) {
				$db->rollbackTransaction();
				return false;
			}
			$this->_workflow = $workflow;
			if(!$this->setStatus(S_IN_WORKFLOW, "Added workflow '".$workflow->getName()."'", $user)) {
				$db->rollbackTransaction();
				return false;
			}
			$db->commitTransaction();
			return true;
		}
		return true;
	} /* }}} */

	/**
	 * Get workflow assigned to the document content
	 *
	 * The method returns the last workflow if one was assigned.
	 * If a the document version is in a sub workflow, it will have
	 * a never date and therefore will be found first.
	 *
	 * @return object/boolean an object of class LetoDMS_Core_Workflow
	 *         or false in case of error, e.g. the version has not a workflow
	 */
	function getWorkflow() { /* {{{ */
		$db = $this->_document->_dms->getDB();

		if (!isset($this->_workflow)) {
			$queryStr=
				"SELECT b.* FROM `tblWorkflowDocumentContent` a LEFT JOIN `tblWorkflows` b ON a.`workflow` = b.id WHERE a.`version`='".$this->_version
				."' AND a.`document` = '". $this->_document->getID() ."' "
				." ORDER BY `date` DESC LIMIT 1";
			$recs = $db->getResultArray($queryStr);
			if (is_bool($recs) && !$recs)
				return false;
			if(!$recs)
				return false;
			$this->_workflow = new LetoDMS_Core_Workflow($recs[0]['id'], $recs[0]['name'], $this->_document->_dms->getWorkflowState($recs[0]['initstate']));
			$this->_workflow->setDMS($this->_document->_dms);
		}
		return $this->_workflow;
	} /* }}} */

	/**
	 * Rewrites the complete workflow log
	 *
	 * Attention: this function is highly dangerous.
	 * It removes an existing workflow log and rewrites it.
	 * This method was added for importing an xml dump.
	 *
	 * @param array $workflowlog new workflow log with the newest log entry first.
	 * @return boolean true on success, otherwise false
	 */
	function rewriteWorkflowLog($workflowlog) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		$db->startTransaction();

		/* First, remove the old entries */
		$queryStr = "DELETE FROM `tblWorkflowLog` WHERE `tblWorkflowLog`.`document` = '". $this->_document->getID() ."' AND `tblWorkflowLog`.`version` = '". $this->_version ."'";
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		/* Second, insert the new entries */
		$workflowlog = array_reverse($workflowlog);
		foreach($workflowlog as $log) {
			if(!LetoDMS_Core_DMS::checkDate($log['date'], 'Y-m-d H:i:s')) {
				$db->rollbackTransaction();
				return false;
			}
			$queryStr = "INSERT INTO `tblWorkflowLog` (`document`, `version`,	`workflow`, `transition`, `comment`, `date`, `userid`) ".
				"VALUES ('".$this->_document->getID() ."', '".(int) $this->_version."', '".(int) $log['workflow']->getID()."', '".(int) $log['transition']->getID()."', ".$db->qstr($log['comment']) .", ".$db->qstr($log['date']).", ".$log['user']->getID().")";
			if (!$db->getResult($queryStr)) {
				$db->rollbackTransaction();
				return false;
			}
		}

		$db->commitTransaction();
		return true;
	} /* }}} */

	/**
	 * Restart workflow from its initial state
	 *
	 * @return boolean true if workflow could be restarted
	 *         or false in case of error
	 */
	function rewindWorkflow() { /* {{{ */
		$db = $this->_document->_dms->getDB();

		$this->getWorkflow();

		if (!isset($this->_workflow)) {
			return true;
		}

		$db->startTransaction();
		$queryStr = "DELETE from `tblWorkflowLog` WHERE `document` = ". $this->_document->getID() ." AND `version` = ".$this->_version." AND `workflow` = ".$this->_workflow->getID();
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$this->setWorkflowState($this->_workflow->getInitState());
		$db->commitTransaction();

		return true;
	} /* }}} */

	/**
	 * Remove workflow
	 *
	 * Fully removing a workflow including entries in the workflow log is
	 * only allowed if the workflow is still its initial state.
	 * At a later point of time only unlinking the document from the
	 * workflow is allowed. It will keep any log entries.
	 * A workflow is unlinked from a document when enterNextState()
	 * succeeds.
	 *
	 * @param object $user user doing initiating the removal
	 * @param boolean $unlink if true, just unlink the workflow from the
	 *        document but do not remove the workflow log. The $unlink
	 *        flag has been added to detach the workflow from the document
	 *        when it has reached a valid end state
	          (see LetoDMS_Core_DocumentContent::enterNextState())
	 * @return boolean true if workflow could be removed
	 *         or false in case of error
	 */
	function removeWorkflow($user, $unlink=false) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		$this->getWorkflow();

		if (!isset($this->_workflow)) {
			return true;
		}

		if(LetoDMS_Core_DMS::checkIfEqual($this->_workflow->getInitState(), $this->getWorkflowState()) || $unlink == true) {
			$db->startTransaction();
			$queryStr=
				"DELETE FROM `tblWorkflowDocumentContent` WHERE "
				."`version`='".$this->_version."' "
				." AND `document` = '". $this->_document->getID() ."' "
				." AND `workflow` = '". $this->_workflow->getID() ."' ";
			if (!$db->getResult($queryStr)) {
				$db->rollbackTransaction();
				return false;
			}
			if(!$unlink) {
				$queryStr=
					"DELETE FROM `tblWorkflowLog` WHERE "
					."`version`='".$this->_version."' "
					." AND `document` = '". $this->_document->getID() ."' "
					." AND `workflow` = '". $this->_workflow->getID() ."' ";
				if (!$db->getResult($queryStr)) {
					$db->rollbackTransaction();
					return false;
				}
			}
			$this->_workflow = null;
			$this->_workflowState = null;
			$this->verifyStatus(false, $user, 'Workflow removed');
			$db->commitTransaction();
		}

		return true;
	} /* }}} */

	/**
	 * Run a sub workflow
	 *
	 * @param object $subworkflow
	 */
	function getParentWorkflow() { /* {{{ */
		$db = $this->_document->_dms->getDB();

		/* document content must be in a workflow */
		$this->getWorkflow();
		if(!$this->_workflow)
			return false;

		$queryStr=
			"SELECT * FROM `tblWorkflowDocumentContent` WHERE "
			."`version`='".$this->_version."' "
			." AND `document` = '". $this->_document->getID() ."' "
			." AND `workflow` = '". $this->_workflow->getID() ."' ";
		$recs = $db->getResultArray($queryStr);
		if (is_bool($recs) && !$recs)
			return false;
		if(!$recs)
			return false;

		if($recs[0]['parentworkflow'])
			return $this->_document->_dms->getWorkflow($recs[0]['parentworkflow']);

		return false;
	} /* }}} */

	/**
	 * Run a sub workflow
	 *
	 * @param object $subworkflow
	 */
	function runSubWorkflow($subworkflow) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		/* document content must be in a workflow */
		$this->getWorkflow();
		if(!$this->_workflow)
			return false;

		/* The current workflow state must match the sub workflows initial state */
		if($subworkflow->getInitState()->getID() != $this->_workflowState->getID())
			return false;

		if($subworkflow) {
			$initstate = $subworkflow->getInitState();
			$queryStr = "INSERT INTO `tblWorkflowDocumentContent` (`parentworkflow`, `workflow`, `document`, `version`, `state`, `date`) VALUES (". $this->_workflow->getID(). ", ". $subworkflow->getID(). ", ". $this->_document->getID() .", ". $this->_version .", ".$initstate->getID().", ".$db->getCurrentDatetime().")";
			if (!$db->getResult($queryStr)) {
				return false;
			}
			$this->_workflow = $subworkflow;
			return true;
		}
		return true;
	} /* }}} */

	/**
	 * Return from sub workflow to parent workflow.
	 * The method will trigger the given transition
	 *
	 * FIXME: Needs much better checking if this is allowed
	 *
	 * @param object $user intiating the return
	 * @param object $transtion to trigger
	 * @param string comment for the transition trigger
	 */
	function returnFromSubWorkflow($user, $transition=null, $comment='') { /* {{{ */
		$db = $this->_document->_dms->getDB();

		/* document content must be in a workflow */
		$this->getWorkflow();
		if(!$this->_workflow)
			return false;

		if (isset($this->_workflow)) {
			$db->startTransaction();

			$queryStr=
				"SELECT * FROM `tblWorkflowDocumentContent` WHERE `workflow`=". intval($this->_workflow->getID())
				. " AND `version`='".$this->_version
				."' AND `document` = '". $this->_document->getID() ."' ";
			$recs = $db->getResultArray($queryStr);
			if (is_bool($recs) && !$recs) {
				$db->rollbackTransaction();
				return false;
			}
			if(!$recs) {
				$db->rollbackTransaction();
				return false;
			}

			$queryStr = "DELETE FROM `tblWorkflowDocumentContent` WHERE `workflow` =". intval($this->_workflow->getID())." AND `document` = '". $this->_document->getID() ."' AND `version` = '" . $this->_version."'";
			if (!$db->getResult($queryStr)) {
				$db->rollbackTransaction();
				return false;
			}

			$this->_workflow = $this->_document->_dms->getWorkflow($recs[0]['parentworkflow']);
			$this->_workflow->setDMS($this->_document->_dms);

			if($transition) {
				if(false === $this->triggerWorkflowTransition($user, $transition, $comment)) {
					$db->rollbackTransaction();
					return false;
				}
			}

			$db->commitTransaction();
		}
		return $this->_workflow;
	} /* }}} */

	/**
	 * Check if the user is allowed to trigger the transition
	 * A user is allowed if either the user itself or
	 * a group of which the user is a member of is registered for
	 * triggering a transition. This method does not change the workflow
	 * state of the document content.
	 *
	 * @param object $user
	 * @return boolean true if user may trigger transaction
	 */
	function triggerWorkflowTransitionIsAllowed($user, $transition) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		if(!$this->_workflow)
			$this->getWorkflow();

		if(!$this->_workflow)
			return false;

		if(!$this->_workflowState)
			$this->getWorkflowState();

		/* Check if the user has already triggered the transition */
		$queryStr=
			"SELECT * FROM `tblWorkflowLog` WHERE `version`='".$this->_version ."' AND `document` = '". $this->_document->getID() ."' AND `workflow` = ". $this->_workflow->getID(). " AND userid = ".$user->getID();
		$queryStr .= " AND `transition` = ".$transition->getID();
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && !$resArr)
			return false;

		if(count($resArr))
			return false;

		/* Get all transition users allowed to trigger the transition */
		$transusers = $transition->getUsers();
		if($transusers) {
			foreach($transusers as $transuser) {
				if($user->getID() == $transuser->getUser()->getID())
					return true;
			}
		}

		/* Get all transition groups whose members are allowed to trigger
		 * the transition */
		$transgroups = $transition->getGroups();
		if($transgroups) {
			foreach($transgroups as $transgroup) {
				$group = $transgroup->getGroup();
				if($group->isMember($user))
					return true;
			}
		}

		return false;
	} /* }}} */

	/**
	 * Check if all conditions are met to change the workflow state
	 * of a document content (run the transition).
	 * The conditions are met if all explicitly set users and a sufficient
	 * number of users of the groups have acknowledged the content.
	 *
	 * @return boolean true if transaction maybe executed
	 */
	function executeWorkflowTransitionIsAllowed($transition) { /* {{{ */
		if(!$this->_workflow)
			$this->getWorkflow();

		if(!$this->_workflow)
			return false;

		if(!$this->_workflowState)
			$this->getWorkflowState();

		/* Get the Log of transition triggers */
		$entries = $this->getWorkflowLog($transition);
		if(!$entries)
			return false;

		/* Get all transition users allowed to trigger the transition
		 * $allowedusers is a list of all users allowed to trigger the
		 * transition
		 */
		$transusers = $transition->getUsers();
		$allowedusers = array();
		foreach($transusers as $transuser) {
			$a = $transuser->getUser();
			$allowedusers[$a->getID()] = $a;
		}

		/* Get all transition groups whose members are allowed to trigger
		 * the transition */
		$transgroups = $transition->getGroups();
		foreach($entries as $entry) {
			$loguser = $entry->getUser();
			/* Unset each allowed user if it was found in the log */
			if(isset($allowedusers[$loguser->getID()]))
				unset($allowedusers[$loguser->getID()]);
			/* Also check groups if required. Count the group membership of
			 * each user in the log in the array $gg
			 */
			if($transgroups) {
				$loggroups = $loguser->getGroups();
				foreach($loggroups as $loggroup) {
					if(!isset($gg[$loggroup->getID()]))
						$gg[$loggroup->getID()] = 1;
					else
						$gg[$loggroup->getID()]++;
				}
			}
		}
		/* If there are allowed users left, then there some users still
		 * need to trigger the transition.
		 */
		if($allowedusers)
			return false;

		if($transgroups) {
			foreach($transgroups as $transgroup) {
				$group = $transgroup->getGroup();
				$minusers = $transgroup->getNumOfUsers();
				if(!isset($gg[$group->getID()]))
					return false;
				if($gg[$group->getID()] < $minusers)
					return false;
			}
		}
		return true;
	} /* }}} */

	/**
	 * Trigger transition
	 *
	 * This method will be deprecated
	 *
	 * The method will first check if the user is allowed to trigger the
	 * transition. If the user is allowed, an entry in the workflow log
	 * will be added, which is later used to check if the transition
	 * can actually be processed. The method will finally call
	 * executeWorkflowTransitionIsAllowed() which checks all log entries
	 * and does the transitions post function if all users and groups have
	 * triggered the transition. Finally enterNextState() is called which
	 * will try to enter the next state.
	 *
	 * @param object $user
	 * @param object $transition
	 * @param string $comment user comment
	 * @return boolean/object next state if transition could be triggered and
	 *         then next state could be entered,
	 *         true if the transition could just be triggered or
	 *         false in case of an error
	 */
	function triggerWorkflowTransition($user, $transition, $comment='') { /* {{{ */
		$db = $this->_document->_dms->getDB();

		if(!$this->_workflow)
			$this->getWorkflow();

		if(!$this->_workflow)
			return false;

		if(!$this->_workflowState)
			$this->getWorkflowState();

		if(!$this->_workflowState)
			return false;

		/* Check if the user is allowed to trigger the transition.
		 */
		if(!$this->triggerWorkflowTransitionIsAllowed($user, $transition))
			return false;

		$state = $this->_workflowState;
		$queryStr = "INSERT INTO `tblWorkflowLog` (`document`, `version`, `workflow`, `userid`, `transition`, `date`, `comment`) VALUES (".$this->_document->getID().", ".$this->_version.", " . (int) $this->_workflow->getID() . ", " .(int) $user->getID(). ", ".(int) $transition->getID().", ".$db->getCurrentDatetime().", ".$db->qstr($comment).")";
		if (!$db->getResult($queryStr))
			return false;

		/* Check if this transition is processed. Run the post function in
		 * that case. A transition is processed when all users and groups
		 * have triggered it.
		 */
		if($this->executeWorkflowTransitionIsAllowed($transition)) {
			/* run post function of transition */
//			echo "run post function of transition ".$transition->getID()."<br />";
		}

		/* Go into the next state. This will only succeed if the pre condition
		 * function of that states succeeds.
		 */
		$nextstate = $transition->getNextState();
		if($this->enterNextState($user, $nextstate)) {
			return $nextstate;
		}
		return true;

	} /* }}} */

	/**
	 * Enter next state of workflow if possible
	 *
	 * The method will check if one of the following states in the workflow
	 * can be reached.
	 * It does it by running
	 * the precondition function of that state. The precondition function
	 * gets a list of all transitions leading to the state. It will
	 * determine, whether the transitions has been triggered and if that
	 * is sufficient to enter the next state. If no pre condition function
	 * is set, then 1 of n transtions are enough to enter the next state.
	 *
	 * If moving in the next state is possible and this state has a
	 * corresponding document state, then the document state will be
	 * updated and the workflow will be detached from the document.
	 *
	 * @param object $user
	 * @param object $nextstate
	 * @return boolean true if the state could be reached
	 *         false if not
	 */
	function enterNextState($user, $nextstate) { /* {{{ */

			/* run the pre condition of the next state. If it is not set
			 * the next state will be reached if one of the transitions
			 * leading to the given state can be processed.
			 */
			if($nextstate->getPreCondFunc() == '') {
				$transitions = $this->_workflow->getPreviousTransitions($nextstate);
				foreach($transitions as $transition) {
//				echo "transition ".$transition->getID()." led to state ".$nextstate->getName()."<br />";
					if($this->executeWorkflowTransitionIsAllowed($transition)) {
//					echo "stepping into next state<br />";
						$this->setWorkflowState($nextstate);

						/* Check if the new workflow state has a mapping into a
						 * document state. If yes, set the document state will
						 * be updated and the workflow will be removed from the
						 * document.
						 */
						$docstate = $nextstate->getDocumentStatus();
						if($docstate == S_RELEASED || $docstate == S_REJECTED) {
							$this->setStatus($docstate, "Workflow has ended", $user);
							/* Detach the workflow from the document, but keep the
							 * workflow log
							 */
							$this->removeWorkflow($user, true);
							return true ;
						}

						/* make sure the users and groups allowed to trigger the next
						 * transitions are also allowed to read the document
						 */
						$transitions = $this->_workflow->getNextTransitions($nextstate);
						foreach($transitions as $tran) {
//							echo "checking access for users/groups allowed to trigger transition ".$tran->getID()."<br />";
							$transusers = $tran->getUsers();
							foreach($transusers as $transuser) {
								$u = $transuser->getUser();
//								echo $u->getFullName()."<br />";
								if($this->_document->getAccessMode($u) < M_READ) {
									$this->_document->addAccess(M_READ, $u->getID(), 1);
//									echo "granted read access<br />";
								} else {
//									echo "has already access<br />";
								}
							}
							$transgroups = $tran->getGroups();
							foreach($transgroups as $transgroup) {
								$g = $transgroup->getGroup();
//								echo $g->getName()."<br />";
								if ($this->_document->getGroupAccessMode($g) < M_READ) {
									$this->_document->addAccess(M_READ, $g->getID(), 0);
//									echo "granted read access<br />";
								} else {
//									echo "has already access<br />";
								}
							}
						}
						return(true);
					} else {
//						echo "transition not ready for process now<br />";
					}
				}
				return false;
			} else {
			}

	} /* }}} */

	/**
	 * Get the so far logged operations on the document content within the
	 * workflow
	 *
	 * @return array list of operations
	 */
	function getWorkflowLog($transition = null) { /* {{{ */
		$db = $this->_document->_dms->getDB();

/*
		if(!$this->_workflow)
			$this->getWorkflow();

		if(!$this->_workflow)
			return false;
*/
		$queryStr=
			"SELECT * FROM `tblWorkflowLog` WHERE `version`='".$this->_version ."' AND `document` = '". $this->_document->getID() ."'"; // AND `workflow` = ". $this->_workflow->getID();
		if($transition)
			$queryStr .= " AND `transition` = ".$transition->getID();
		$queryStr .= " ORDER BY `date`";
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && !$resArr)
			return false;

		$workflowlogs = array();
		for ($i = 0; $i < count($resArr); $i++) {
			$workflow = $this->_document->_dms->getWorkflow($resArr[$i]["workflow"]);
			$workflowlog = new LetoDMS_Core_Workflow_Log($resArr[$i]["id"], $this->_document->_dms->getDocument($resArr[$i]["document"]), $resArr[$i]["version"], $workflow, $this->_document->_dms->getUser($resArr[$i]["userid"]), $workflow->getTransition($resArr[$i]["transition"]), $resArr[$i]["date"], $resArr[$i]["comment"]);
			$workflowlog->setDMS($this);
			$workflowlogs[$i] = $workflowlog;
		}

		return $workflowlogs;
	} /* }}} */

	/**
	 * Get the latest logged transition for the document content within the
	 * workflow
	 *
	 * @return array list of operations
	 */
	function getLastWorkflowTransition() { /* {{{ */
		$db = $this->_document->_dms->getDB();

		if(!$this->_workflow)
			$this->getWorkflow();

		if(!$this->_workflow)
			return false;

		$queryStr=
			"SELECT * FROM `tblWorkflowLog` WHERE `version`='".$this->_version ."' AND `document` = '". $this->_document->getID() ."' AND `workflow` = ". $this->_workflow->getID();
		$queryStr .= " ORDER BY `id` DESC LIMIT 1";
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && !$resArr)
			return false;

		$workflowlogs = array();
		$i = 0;
		$workflowlog = new LetoDMS_Core_Workflow_Log($resArr[$i]["id"], $this->_document->_dms->getDocument($resArr[$i]["document"]), $resArr[$i]["version"], $this->_workflow, $this->_document->_dms->getUser($resArr[$i]["userid"]), $this->_workflow->getTransition($resArr[$i]["transition"]), $resArr[$i]["date"], $resArr[$i]["comment"]);
		$workflowlog->setDMS($this);

		return $workflowlog;
	} /* }}} */

	/**
	 * Check if the document content needs an action by a user
	 *
	 * This method will return true if document content is in a transition
	 * which can be triggered by the given user.
	 *
	 * @param LetoDMS_Core_User $user
	 * @return boolean true is action is needed
	 */
	function needsWorkflowAction($user) { /* {{{ */
		$needwkflaction = false;
		if($this->_workflow) {
			if (!$this->_workflowState)
				$this->getWorkflowState();
			$workflowstate = $this->_workflowState;
			$transitions = $this->_workflow->getNextTransitions($workflowstate);
			foreach($transitions as $transition) {
				if($this->triggerWorkflowTransitionIsAllowed($user, $transition)) {
					$needwkflaction = true;
				}
			}
		}
		return $needwkflaction;
	} /* }}} */

} /* }}} */


/**
 * Class to represent a link between two document
 *
 * Document links are to establish a reference from one document to
 * another document. The owner of the document link may not be the same
 * as the owner of one of the documents.
 * Use {@link LetoDMS_Core_Document::addDocumentLink()} to add a reference
 * to another document.
 *
 * @category   DMS
 * @package    LetoDMS_Core
 * @author     Markus Westphal, Malcolm Cowe, Matteo Lucarelli,
 *             Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Core_DocumentLink { /* {{{ */
	/**
	 * @var integer internal id of document link
	 */
	protected $_id;

	/**
	 * @var LetoDMS_Core_Document reference to document this link belongs to
	 */
	protected $_document;

	/**
	 * @var object reference to target document this link points to
	 */
	protected $_target;

	/**
	 * @var integer id of user who is the owner of this link
	 */
	protected $_userID;

	/**
	 * @var integer 1 if this link is public, or 0 if is only visible to the owner
	 */
	protected $_public;

	/**
	 * LetoDMS_Core_DocumentLink constructor.
	 * @param $id
	 * @param $document
	 * @param $target
	 * @param $userID
	 * @param $public
	 */
	function __construct($id, $document, $target, $userID, $public) {
		$this->_id = $id;
		$this->_document = $document;
		$this->_target = $target;
		$this->_userID = $userID;
		$this->_public = $public;
	}

	/**
	 * @return int
	 */
	function getID() { return $this->_id; }

	/**
	 * @return LetoDMS_Core_Document
	 */
	function getDocument() {
		return $this->_document;
	}

	/**
	 * @return object
	 */
	function getTarget() {
		return $this->_target;
	}

	/**
	 * @return bool|LetoDMS_Core_User
	 */
	function getUser() {
		if (!isset($this->_user))
			$this->_user = $this->_document->_dms->getUser($this->_userID);
		return $this->_user;
	}

	/**
	 * @return int
	 */
	function isPublic() { return $this->_public; }

	/**
	 * Returns the access mode similar to a document
	 *
	 * There is no real access mode for document links, so this is just
	 * another way to add more access restrictions than the default restrictions.
	 * It is only called for public document links, not accessed by the owner
	 * or the administrator.
	 *
	 * @param LetoDMS_Core_User $u user
	 * @param $source
	 * @param $target
	 * @return int either M_NONE or M_READ
	 */
	function getAccessMode($u, $source, $target) { /* {{{ */
		$dms = $this->_document->_dms;

		/* Check if 'onCheckAccessDocumentLink' callback is set */
		if(isset($this->_dms->callbacks['onCheckAccessDocumentLink'])) {
			foreach($this->_dms->callbacks['onCheckAccessDocumentLink'] as $callback) {
				if(($ret = call_user_func($callback[0], $callback[1], $this, $u, $source, $target)) > 0) {
					return $ret;
				}
			}
		}

		return M_READ;
	} /* }}} */

} /* }}} */

/**
 * Class to represent a file attached to a document
 *
 * Beside the regular document content arbitrary files can be attached
 * to a document. This is a similar concept as attaching files to emails.
 * The owner of the attached file and the document may not be the same.
 * Use {@link LetoDMS_Core_Document::addDocumentFile()} to attach a file.
 *
 * @category   DMS
 * @package    LetoDMS_Core
 * @author     Markus Westphal, Malcolm Cowe, Matteo Lucarelli,
 *             Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Core_DocumentFile { /* {{{ */
	/**
	 * @var integer internal id of document file
	 */
	protected $_id;

	/**
	 * @var LetoDMS_Core_Document reference to document this file belongs to
	 */
	protected $_document;

	/**
	 * @var integer id of user who is the owner of this link
	 */
	protected $_userID;

	/**
	 * @var string comment for the attached file
	 */
	protected $_comment;

	/**
	 * @var string date when the file was attached
	 */
	protected $_date;

	/**
	 * @var integer version of document this file is attached to
	 */
	protected $_version;

	/**
	 * @var integer 1 if this link is public, or 0 if is only visible to the owner
	 */
	protected $_public;

	/**
	 * @var string directory where the file is stored. This is the
	 * document id with a proceding '/'.
	 * FIXME: looks like this isn't used anymore. The file path is
	 * constructed by getPath()
	 */
	protected $_dir;

	/**
	 * @var string extension of the original file name with a leading '.'
	 */
	protected $_fileType;

	/**
	 * @var string mime type of the file
	 */
	protected $_mimeType;

	/**
	 * @var string name of the file that was originally uploaded
	 */
	protected $_orgFileName;

	/**
	 * @var string name of the file as given by the user
	 */
	protected $_name;

	/**
	 * LetoDMS_Core_DocumentFile constructor.
	 * @param $id
	 * @param $document
	 * @param $userID
	 * @param $comment
	 * @param $date
	 * @param $dir
	 * @param $fileType
	 * @param $mimeType
	 * @param $orgFileName
	 * @param $name
	 * @param $version
	 * @param $public
	 */
	function __construct($id, $document, $userID, $comment, $date, $dir, $fileType, $mimeType, $orgFileName,$name,$version,$public) {
		$this->_id = $id;
		$this->_document = $document;
		$this->_userID = $userID;
		$this->_comment = $comment;
		$this->_date = $date;
		$this->_dir = $dir;
		$this->_fileType = $fileType;
		$this->_mimeType = $mimeType;
		$this->_orgFileName = $orgFileName;
		$this->_name = $name;
		$this->_version = $version;
		$this->_public = $public;
	}

	/**
	 * @return int
	 */
	function getID() { return $this->_id; }

	/**
	 * @return LetoDMS_Core_Document
	 */
	function getDocument() { return $this->_document; }

	/**
	 * @return int
	 */
	function getUserID() { return $this->_userID; }

	/**
	 * @return string
	 */
	function getComment() { return $this->_comment; }

	/*
	 * Set the comment of the document file
	 *
	 * @param string $newComment string new comment of document
	 */
	function setComment($newComment) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		$queryStr = "UPDATE `tblDocumentFiles` SET `comment` = ".$db->qstr($newComment)." WHERE `document` = ".$this->_document->getId()." AND `id` = ". $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_comment = $newComment;
		return true;
	} /* }}} */

	/**
	 * @return string
	 */
	function getDate() { return $this->_date; }

	/**
	 * Set creation date of the document file
	 *
	 * @param integer $date timestamp of creation date. If false then set it
	 * to the current timestamp
	 * @return boolean true on success
	 */
	function setDate($date) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		if(!$date)
			$date = time();
		else {
			if(!is_numeric($date))
				return false;
		}

		$queryStr = "UPDATE `tblDocumentFiles` SET `date` = " . (int) $date . " WHERE `id` = ". $this->_id;
		if (!$db->getResult($queryStr))
			return false;
		$this->_date = $date;
		return true;
	} /* }}} */

	/**
	 * @return string
	 */
	function getDir() { return $this->_dir; }

	/**
	 * @return string
	 */
	function getFileType() { return $this->_fileType; }

	/**
	 * @return string
	 */
	function getMimeType() { return $this->_mimeType; }

	/**
	 * @return string
	 */
	function getOriginalFileName() { return $this->_orgFileName; }

	/**
	 * @return string
	 */
	function getName() { return $this->_name; }

	/*
	 * Set the name of the document file
	 *
	 * @param $newComment string new name of document
	 */
	function setName($newName) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		$queryStr = "UPDATE `tblDocumentFiles` SET `name` = ".$db->qstr($newName)." WHERE `document` = ".$this->_document->getId()." AND `id` = ". $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_name = $newName;
		return true;
	} /* }}} */

	/**
	 * @return bool|LetoDMS_Core_User
	 */
	function getUser() {
		if (!isset($this->_user))
			$this->_user = $this->_document->_dms->getUser($this->_userID);
		return $this->_user;
	}

	/**
	 * @return string
	 */
	function getPath() {
		return $this->_document->getDir() . "f" .$this->_id . $this->_fileType;
	}

	/**
	 * @return int
	 */
	function getVersion() { return $this->_version; }

	/*
	 * Set the version of the document file
	 *
	 * @param $newComment string new version of document
	 */
	function setVersion($newVersion) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		if(!is_numeric($newVersion) && $newVersion != '')
			return false;

		$queryStr = "UPDATE `tblDocumentFiles` SET `version` = ".(int) $newVersion." WHERE `document` = ".$this->_document->getId()." AND `id` = ". $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_version = (int) $newVersion;
		return true;
	} /* }}} */

	/**
	 * @return int
	 */
	function isPublic() { return $this->_public; }

	/*
	 * Set the public flag of the document file
	 *
	 * @param $newComment string new comment of document
	 */
	function setPublic($newPublic) { /* {{{ */
		$db = $this->_document->_dms->getDB();

		$queryStr = "UPDATE `tblDocumentFiles` SET `public` = ".($newPublic ? 1 : 0)." WHERE `document` = ".$this->_document->getId()." AND `id` = ". $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_public = $newPublic ? 1 : 0;
		return true;
	} /* }}} */

	/**
	 * Returns the access mode similar to a document
	 *
	 * There is no real access mode for document files, so this is just
	 * another way to add more access restrictions than the default restrictions.
	 * It is only called for public document files, not accessed by the owner
	 * or the administrator.
	 *
	 * @param object $u user
	 * @return integer either M_NONE or M_READ
	 */
	function getAccessMode($u) { /* {{{ */
		$dms = $this->_document->_dms;

		/* Check if 'onCheckAccessDocumentLink' callback is set */
		if(isset($this->_dms->callbacks['onCheckAccessDocumentFile'])) {
			foreach($this->_dms->callbacks['onCheckAccessDocumentFile'] as $callback) {
				if(($ret = call_user_func($callback[0], $callback[1], $this, $u)) > 0) {
					return $ret;
				}
			}
		}

		return M_READ;
	} /* }}} */

} /* }}} */

//
// Perhaps not the cleanest object ever devised, it exists to encapsulate all
// of the data generated during the addition of new content to the database.
// The object stores a copy of the new DocumentContent object, the newly assigned
// reviewers and approvers and the status.
//
/**
 * Class to represent a list of document contents
 *
 * @category   DMS
 * @package    LetoDMS_Core
 * @author     Markus Westphal, Malcolm Cowe, Matteo Lucarelli,
 *             Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Core_AddContentResultSet { /* {{{ */

	/**
	 * @var null
	 */
	protected $_indReviewers;

	/**
	 * @var null
	 */
	protected $_grpReviewers;

	/**
	 * @var null
	 */
	protected $_indApprovers;

	/**
	 * @var null
	 */
	protected $_grpApprovers;

	/**
	 * @var
	 */
	protected $_content;

	/**
	 * @var null
	 */
	protected $_status;

	/**
	 * @var LetoDMS_Core_DMS back reference to document management system
	 */
	protected $_dms;

	/**
	 * LetoDMS_Core_AddContentResultSet constructor.
	 * @param $content
	 */
	function __construct($content) { /* {{{ */
		$this->_content = $content;
		$this->_indReviewers = null;
		$this->_grpReviewers = null;
		$this->_indApprovers = null;
		$this->_grpApprovers = null;
		$this->_status = null;
		$this->_dms = null;
	} /* }}} */

	/**
	 * Set dms this object belongs to.
	 *
	 * Each object needs a reference to the dms it belongs to. It will be
	 * set when the object is created.
	 * The dms has a references to the currently logged in user
	 * and the database connection.
	 *
	 * @param LetoDMS_Core_DMS $dms reference to dms
	 */
	function setDMS($dms) { /* {{{ */
		$this->_dms = $dms;
	} /* }}} */

	/**
	 * @param $reviewer
	 * @param $type
	 * @param $status
	 * @return bool
	 */
	function addReviewer($reviewer, $type, $status) { /* {{{ */
		$dms = $this->_dms;

		if (!is_object($reviewer) || (strcasecmp($type, "i") && strcasecmp($type, "g")) && !is_integer($status)){
			return false;
		}
		if (!strcasecmp($type, "i")) {
			if (strcasecmp(get_class($reviewer), $dms->getClassname("user"))) {
				return false;
			}
			if ($this->_indReviewers == null) {
				$this->_indReviewers = array();
			}
			$this->_indReviewers[$status][] = $reviewer;
		}
		if (!strcasecmp($type, "g")) {
			if (strcasecmp(get_class($reviewer), $dms->getClassname("group"))) {
				return false;
			}
			if ($this->_grpReviewers == null) {
				$this->_grpReviewers = array();
			}
			$this->_grpReviewers[$status][] = $reviewer;
		}
		return true;
	} /* }}} */

	/**
	 * @param $approver
	 * @param $type
	 * @param $status
	 * @return bool
	 */
	function addApprover($approver, $type, $status) { /* {{{ */
		$dms = $this->_dms;

		if (!is_object($approver) || (strcasecmp($type, "i") && strcasecmp($type, "g")) && !is_integer($status)){
			return false;
		}
		if (!strcasecmp($type, "i")) {
			if (strcasecmp(get_class($approver), $dms->getClassname("user"))) {
				return false;
			}
			if ($this->_indApprovers == null) {
				$this->_indApprovers = array();
			}
			$this->_indApprovers[$status][] = $approver;
		}
		if (!strcasecmp($type, "g")) {
			if (strcasecmp(get_class($approver), $dms->getClassname("group"))) {
				return false;
			}
			if ($this->_grpApprovers == null) {
				$this->_grpApprovers = array();
			}
			$this->_grpApprovers[$status][] = $approver;
		}
		return true;
	} /* }}} */

	/**
	 * @param $status
	 * @return bool
	 */
	function setStatus($status) { /* {{{ */
		if (!is_integer($status)) {
			return false;
		}
		if ($status<-3 || $status>3) {
			return false;
		}
		$this->_status = $status;
		return true;
	} /* }}} */

	/**
	 * @return null
	 */
	function getStatus() { /* {{{ */
		return $this->_status;
	} /* }}} */

	/**
	 * @return mixed
	 */
	function getContent() { /* {{{ */
		return $this->_content;
	} /* }}} */

	/**
	 * @param $type
	 * @return array|bool|null
	 */
	function getReviewers($type) { /* {{{ */
		if (strcasecmp($type, "i") && strcasecmp($type, "g")) {
			return false;
		}
		if (!strcasecmp($type, "i")) {
			return ($this->_indReviewers == null ? array() : $this->_indReviewers);
		}
		else {
			return ($this->_grpReviewers == null ? array() : $this->_grpReviewers);
		}
	} /* }}} */

	/**
	 * @param $type
	 * @return array|bool|null
	 */
	function getApprovers($type) { /* {{{ */
		if (strcasecmp($type, "i") && strcasecmp($type, "g")) {
			return false;
		}
		if (!strcasecmp($type, "i")) {
			return ($this->_indApprovers == null ? array() : $this->_indApprovers);
		}
		else {
			return ($this->_grpApprovers == null ? array() : $this->_grpApprovers);
		}
	} /* }}} */
} /* }}} */
