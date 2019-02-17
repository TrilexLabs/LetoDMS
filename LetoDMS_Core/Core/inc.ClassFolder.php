<?php
/**
 * Implementation of a folder in the document management system
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
 * Class to represent a folder in the document management system
 *
 * A folder in LetoDMS is equivalent to a directory in a regular file
 * system. It can contain further subfolders and documents. Each folder
 * has a single parent except for the root folder which has no parent.
 *
 * @category   DMS
 * @package    LetoDMS_Core
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal, 2006-2008 Malcolm Cowe,
 *             2010 Matteo Lucarelli, 2010 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Core_Folder extends LetoDMS_Core_Object {
	/**
	 * @var string name of folder
	 */
	protected $_name;

	/**
	 * @var integer id of parent folder
	 */
	protected $_parentID;

	/**
	 * @var string comment of document
	 */
	protected $_comment;

	/**
	 * @var integer id of user who is the owner
	 */
	protected $_ownerID;

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
	 * @var integer position of folder within the parent folder
	 */
	protected $_sequence;

	/**
	 * @var
	 */
	protected $_date;

	/**
	 * @var LetoDMS_Core_Folder
	 */
	protected $_parent;

	/**
	 * @var LetoDMS_Core_User
	 */
	protected $_owner;

	/**
	 * @var LetoDMS_Core_Folder[]
	 */
	protected $_subFolders;

	/**
	 * @var LetoDMS_Core_Document[]
	 */
	protected $_documents;

	/**
	 * @var LetoDMS_Core_UserAccess[]|LetoDMS_Core_GroupAccess[]
	 */
	protected $_accessList;

	/**
	 * LetoDMS_Core_Folder constructor.
	 * @param $id
	 * @param $name
	 * @param $parentID
	 * @param $comment
	 * @param $date
	 * @param $ownerID
	 * @param $inheritAccess
	 * @param $defaultAccess
	 * @param $sequence
	 */
	function __construct($id, $name, $parentID, $comment, $date, $ownerID, $inheritAccess, $defaultAccess, $sequence) { /* {{{ */
		parent::__construct($id);
		$this->_id = $id;
		$this->_name = $name;
		$this->_parentID = $parentID;
		$this->_comment = $comment;
		$this->_date = $date;
		$this->_ownerID = $ownerID;
		$this->_inheritAccess = $inheritAccess;
		$this->_defaultAccess = $defaultAccess;
		$this->_sequence = $sequence;
		$this->_notifyList = array();
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
		if (in_array(2, $searchin)) {
			$searchFields[] = "`tblFolders`.`name`";
		}
		if (in_array(3, $searchin)) {
			$searchFields[] = "`tblFolders`.`comment`";
		}
		if (in_array(4, $searchin)) {
			$searchFields[] = "`tblFolderAttributes`.`value`";
		}
		if (in_array(5, $searchin)) {
			$searchFields[] = $db->castToText("`tblFolders`.`id`");
		}
		return $searchFields;
	} /* }}} */

	/**
	 * Return a sql statement with all tables used for searching.
	 * This must be a syntactically correct left join of all tables.
	 *
	 * @return string sql expression for left joining tables
	 */
	public static function getSearchTables() { /* {{{ */
		$sql = "`tblFolders` LEFT JOIN `tblFolderAttributes` on `tblFolders`.`id`=`tblFolderAttributes`.`folder`";
		return $sql;
	} /* }}} */

	/**
	 * Return a folder by its id
	 *
	 * @param integer $id id of folder
	 * @param LetoDMS_Core_DMS $dms
	 * @return LetoDMS_Core_Folder|bool instance of LetoDMS_Core_Folder if document exists, null
	 * if document does not exist, false in case of error
	 */
	public static function getInstance($id, $dms) { /* {{{ */
		$db = $dms->getDB();

		$queryStr = "SELECT * FROM `tblFolders` WHERE `id` = " . (int) $id;
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;
		else if (count($resArr) != 1)
			return null;

		$resArr = $resArr[0];
		$classname = $dms->getClassname('folder');
		/** @var LetoDMS_Core_Folder $folder */
		$folder = new $classname($resArr["id"], $resArr["name"], $resArr["parent"], $resArr["comment"], $resArr["date"], $resArr["owner"], $resArr["inheritAccess"], $resArr["defaultAccess"], $resArr["sequence"]);
		$folder->setDMS($dms);
		return $folder;
	} /* }}} */

	/**
	 * Get the name of the folder.
	 *
	 * @return string name of folder
	 */
	public function getName() { return $this->_name; }

	/**
	 * Set the name of the folder.
	 *
	 * @param string $newName set a new name of the folder
	 * @return bool
	 */
	public function setName($newName) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE `tblFolders` SET `name` = " . $db->qstr($newName) . " WHERE `id` = ". $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_name = $newName;

		return true;
	} /* }}} */

	/**
	 * @return string
	 */
	public function getComment() { return $this->_comment; }

	/**
	 * @param $newComment
	 * @return bool
	 */
	public function setComment($newComment) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE `tblFolders` SET `comment` = " . $db->qstr($newComment) . " WHERE `id` = ". $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_comment = $newComment;
		return true;
	} /* }}} */

	/**
	 * Return creation date of folder
	 *
	 * @return integer unix timestamp of creation date
	 */
	public function getDate() { /* {{{ */
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

		$queryStr = "UPDATE `tblFolders` SET `date` = " . (int) $date . " WHERE `id` = ". $this->_id;
		if (!$db->getResult($queryStr))
			return false;
		$this->_date = $date;
		return true;
	} /* }}} */

	/**
	 * Returns the parent
	 *
	 * @return bool|LetoDMS_Core_Folder
	 */
	public function getParent() { /* {{{ */
		if ($this->_id == $this->_dms->rootFolderID || empty($this->_parentID)) {
			return false;
		}

		if (!isset($this->_parent)) {
			$this->_parent = $this->_dms->getFolder($this->_parentID);
		}
		return $this->_parent;
	} /* }}} */

	/**
	 * Check if the folder is subfolder
	 *
	 * This function checks if the passed folder is a subfolder of the current
	 * folder.
	 *
	 * @param LetoDMS_Core_Folder $subfolder
	 * @return bool true if passes folder is a subfolder
	 */
	function isSubFolder($subfolder) { /* {{{ */
		$target_path = $subfolder->getPath();
		foreach($target_path as $next_folder) {
			// the target folder contains this instance in the parent path
			if($this->getID() == $next_folder->getID()) return true;
		}
		return false;
	} /* }}} */

	/**
	 * Set a new folder
	 *
	 * This function moves a folder from one parent folder into another parent
	 * folder. It will fail if the root folder is moved.
	 *
	 * @param LetoDMS_Core_Folder $newParent new parent folder
	 * @return boolean true if operation was successful otherwise false
	 */
	public function setParent($newParent) { /* {{{ */
		$db = $this->_dms->getDB();

		if ($this->_id == $this->_dms->rootFolderID || empty($this->_parentID)) {
			return false;
		}

		/* Check if the new parent is the folder to be moved or even
		 * a subfolder of that folder
		 */
		if($this->isSubFolder($newParent)) {
			return false;
		}

		// Update the folderList of the folder
		$pathPrefix="";
		$path = $newParent->getPath();
		foreach ($path as $f) {
			$pathPrefix .= ":".$f->getID();
		}
		if (strlen($pathPrefix)>1) {
			$pathPrefix .= ":";
		}
		$queryStr = "UPDATE `tblFolders` SET `parent` = ".$newParent->getID().", `folderList`='".$pathPrefix."' WHERE `id` = ". $this->_id;
		$res = $db->getResult($queryStr);
		if (!$res)
			return false;

		$this->_parentID = $newParent->getID();
		$this->_parent = $newParent;

		// Must also ensure that any documents in this folder tree have their
		// folderLists updated.
		$pathPrefix="";
		$path = $this->getPath();
		foreach ($path as $f) {
			$pathPrefix .= ":".$f->getID();
		}
		if (strlen($pathPrefix)>1) {
			$pathPrefix .= ":";
		}

		/* Update path in folderList for all documents */
		$queryStr = "SELECT `tblDocuments`.`id`, `tblDocuments`.`folderList` FROM `tblDocuments` WHERE `folderList` LIKE '%:".$this->_id.":%'";
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;

		foreach ($resArr as $row) {
			$newPath = preg_replace("/^.*:".$this->_id.":(.*$)/", $pathPrefix."\\1", $row["folderList"]);
			$queryStr="UPDATE `tblDocuments` SET `folderList` = '".$newPath."' WHERE `tblDocuments`.`id` = '".$row["id"]."'";
			/** @noinspection PhpUnusedLocalVariableInspection */
			$res = $db->getResult($queryStr);
		}

		/* Update path in folderList for all documents */
		$queryStr = "SELECT `tblFolders`.`id`, `tblFolders`.`folderList` FROM `tblFolders` WHERE `folderList` LIKE '%:".$this->_id.":%'";
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;

		foreach ($resArr as $row) {
			$newPath = preg_replace("/^.*:".$this->_id.":(.*$)/", $pathPrefix."\\1", $row["folderList"]);
			$queryStr="UPDATE `tblFolders` SET `folderList` = '".$newPath."' WHERE `tblFolders`.`id` = '".$row["id"]."'";
			/** @noinspection PhpUnusedLocalVariableInspection */
			$res = $db->getResult($queryStr);
		}

		return true;
	} /* }}} */

	/**
	 * Returns the owner
	 *
	 * @return object owner of the folder
	 */
	public function getOwner() { /* {{{ */
		if (!isset($this->_owner))
			$this->_owner = $this->_dms->getUser($this->_ownerID);
		return $this->_owner;
	} /* }}} */

	/**
	 * Set the owner
	 *
	 * @param LetoDMS_Core_User $newOwner of the folder
	 * @return boolean true if successful otherwise false
	 */
	function setOwner($newOwner) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE `tblFolders` set `owner` = " . $newOwner->getID() . " WHERE `id` = " . $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_ownerID = $newOwner->getID();
		$this->_owner = $newOwner;
		return true;
	} /* }}} */

	/**
	 * @return bool|int
	 */
	function getDefaultAccess() { /* {{{ */
		if ($this->inheritsAccess()) {
			$res = $this->getParent();
			if (!$res) return false;
			return $this->_parent->getDefaultAccess();
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
	 * @param boolean $noclean set to true if notifier list shall not be clean up
	 * @return bool
	 */
	function setDefaultAccess($mode, $noclean=false) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE `tblFolders` set `defaultAccess` = " . (int) $mode . " WHERE `id` = " . $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_defaultAccess = $mode;

		if(!$noclean)
			self::cleanNotifyList();

		return true;
	} /* }}} */

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

		$inheritAccess = ($inheritAccess) ? "1" : "0";

		$queryStr = "UPDATE `tblFolders` SET `inheritAccess` = " . (int) $inheritAccess . " WHERE `id` = " . $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_inheritAccess = $inheritAccess;

		if(!$noclean)
			self::cleanNotifyList();

		return true;
	} /* }}} */

	function getSequence() { return $this->_sequence; }

	function setSequence($seq) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE `tblFolders` SET `sequence` = " . $seq . " WHERE `id` = " . $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_sequence = $seq;
		return true;
	} /* }}} */

	/**
	 * Check if folder has subfolders
	 * This function just checks if a folder has subfolders disregarding
	 * any access rights.
	 *
	 * @return int number of subfolders or false in case of an error
	 */
	function hasSubFolders() { /* {{{ */
		$db = $this->_dms->getDB();
		if (isset($this->_subFolders)) {
			/** @noinspection PhpUndefinedFieldInspection */
			return count($this->subFolders); /** @todo not $this->_subFolders? */
		}
		$queryStr = "SELECT count(*) as c FROM `tblFolders` WHERE `parent` = " . $this->_id;
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && !$resArr)
			return false;

		return $resArr[0]['c'];
	} /* }}} */

	/**
	 * Returns a list of subfolders
	 * This function does not check for access rights. Use
	 * {@link LetoDMS_Core_DMS::filterAccess} for checking each folder against
	 * the currently logged in user and the access rights.
	 *
	 * @param string $orderby if set to 'n' the list is ordered by name, otherwise
	 *        it will be ordered by sequence
	 * @param string $dir direction of sorting (asc or desc)
	 * @param integer $limit limit number of subfolders
	 * @param integer $offset offset in retrieved list of subfolders
	 * @return LetoDMS_Core_Folder[]|bool list of folder objects or false in case of an error
	 */
	function getSubFolders($orderby="", $dir="asc", $limit=0, $offset=0) { /* {{{ */
		$db = $this->_dms->getDB();

		if (!isset($this->_subFolders)) {
			$queryStr = "SELECT * FROM `tblFolders` WHERE `parent` = " . $this->_id;

			if ($orderby=="n") $queryStr .= " ORDER BY `name`";
			elseif ($orderby=="s") $queryStr .= " ORDER BY `sequence`";
			elseif ($orderby=="d") $queryStr .= " ORDER BY `date`";
			if($dir == 'desc')
				$queryStr .= " DESC";
			if(is_int($limit) && $limit > 0) {
				$queryStr .= " LIMIT ".$limit;
				if(is_int($offset) && $offset > 0)
					$queryStr .= " OFFSET ".$offset;
			}

			$resArr = $db->getResultArray($queryStr);
			if (is_bool($resArr) && $resArr == false)
				return false;

			$this->_subFolders = array();
			for ($i = 0; $i < count($resArr); $i++)
				$this->_subFolders[$i] = $this->_dms->getFolder($resArr[$i]["id"]);
		}

		return $this->_subFolders;
	} /* }}} */

	/**
	 * Add a new subfolder
	 *
	 * @param string $name name of folder
	 * @param string $comment comment of folder
	 * @param object $owner owner of folder
	 * @param integer $sequence position of folder in list of sub folders.
	 * @param array $attributes list of document attributes. The element key
	 *        must be the id of the attribute definition.
	 * @return bool|LetoDMS_Core_Folder
	 *         an error.
	 */
	function addSubFolder($name, $comment, $owner, $sequence, $attributes=array()) { /* {{{ */
		$db = $this->_dms->getDB();

		// Set the folderList of the folder
		$pathPrefix="";
		$path = $this->getPath();
		foreach ($path as $f) {
			$pathPrefix .= ":".$f->getID();
		}
		if (strlen($pathPrefix)>1) {
			$pathPrefix .= ":";
		}

		$db->startTransaction();

		//inheritAccess = true, defaultAccess = M_READ
		$queryStr = "INSERT INTO `tblFolders` (`name`, `parent`, `folderList`, `comment`, `date`, `owner`, `inheritAccess`, `defaultAccess`, `sequence`) ".
					"VALUES (".$db->qstr($name).", ".$this->_id.", ".$db->qstr($pathPrefix).", ".$db->qstr($comment).", ".$db->getCurrentTimestamp().", ".$owner->getID().", 1, ".M_READ.", ". $sequence.")";
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}
		$newFolder = $this->_dms->getFolder($db->getInsertID('tblFolders'));
		unset($this->_subFolders);

		if($attributes) {
			foreach($attributes as $attrdefid=>$attribute) {
				if($attribute)
					if(!$newFolder->setAttributeValue($this->_dms->getAttributeDefinition($attrdefid), $attribute)) {
						$db->rollbackTransaction();
						return false;
					}
			}
		}

		$db->commitTransaction();

		/* Check if 'onPostAddSubFolder' callback is set */
		if(isset($this->_dms->callbacks['onPostAddSubFolder'])) {
			foreach($this->_dms->callbacks['onPostAddSubFolder'] as $callback) {
					/** @noinspection PhpStatementHasEmptyBodyInspection */
					if(!call_user_func($callback[0], $callback[1], $newFolder)) {
				}
			}
		}

		return $newFolder;
	} /* }}} */

	/**
	 * Returns an array of all parents, grand parent, etc. up to root folder.
	 * The folder itself is the last element of the array.
	 *
	 * @return array|bool
	 */
	function getPath() { /* {{{ */
		if (!isset($this->_parentID) || ($this->_parentID == "") || ($this->_parentID == 0) || ($this->_id == $this->_dms->rootFolderID)) {
			return array($this);
		}
		else {
			$res = $this->getParent();
			if (!$res) return false;

			$path = $this->_parent->getPath();
			if (!$path) return false;

			array_push($path, $this);
			return $path;
		}
	} /* }}} */

	/**
	 * Returns a file system path
	 *
	 * This path contains spaces around the slashes for better readability.
	 * Run str_replace(' / ', '/', $path) on it to get a valid unix
	 * file system path.
	 *
	 * @return string path separated with ' / '
	 */
	function getFolderPathPlain() { /* {{{ */
		$path="";
		$folderPath = $this->getPath();
		for ($i = 0; $i  < count($folderPath); $i++) {
			$path .= $folderPath[$i]->getName();
			if ($i +1 < count($folderPath))
				$path .= " / ";
		}
		return $path;
	} /* }}} */

	/**
	 * Check, if this folder is a subfolder of a given folder
	 *
	 * @param object $folder parent folder
	 * @return boolean true if folder is a subfolder
	 */
	function isDescendant($folder) { /* {{{ */
		if ($this->_parentID == $folder->getID())
			return true;
		elseif (isset($this->_parentID)) {
			$res = $this->getParent();
			if (!$res) return false;

			return $this->_parent->isDescendant($folder);
		} else
			return false;
	} /* }}} */

	/**
	 * Check if folder has documents
	 * This function just checks if a folder has documents diregarding
	 * any access rights.
	 *
	 * @return int number of documents or false in case of an error
	 */
	function hasDocuments() { /* {{{ */
		$db = $this->_dms->getDB();
		if (isset($this->_documents)) {
			/** @noinspection PhpUndefinedFieldInspection */
			return count($this->documents); /** @todo not $this->_documents? */
		}
		$queryStr = "SELECT count(*) as c FROM `tblDocuments` WHERE `folder` = " . $this->_id;
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && !$resArr)
			return false;

		return $resArr[0]['c'];
	} /* }}} */

	/**
	 * Check if folder has document with given name
	 *
	 * @param string $name
	 * @return bool true if document exists, false if not or in case
	 * of an error
	 */
	function hasDocumentByName($name) { /* {{{ */
		$db = $this->_dms->getDB();
		if (isset($this->_documents)) {
			/** @noinspection PhpUndefinedFieldInspection */ /** @todo not $this->_documents? */
			return count($this->documents);
		}
		$queryStr = "SELECT count(*) as c FROM `tblDocuments` WHERE `folder` = " . $this->_id . " AND `name` = ".$db->qstr($name);
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && !$resArr)
			return false;

		return ($resArr[0]['c'] > 0);
	} /* }}} */

	/**
	 * Get all documents of the folder
	 * This function does not check for access rights. Use
	 * {@link LetoDMS_Core_DMS::filterAccess} for checking each document against
	 * the currently logged in user and the access rights.
	 *
	 * @param string $orderby if set to 'n' the list is ordered by name, otherwise
	 *        it will be ordered by sequence
	 * @param string $dir direction of sorting (asc or desc)
	 * @param integer $limit limit number of documents
	 * @param integer $offset offset in retrieved list of documents
	 * @return LetoDMS_Core_Document[]|bool list of documents or false in case of an error
	 */
	function getDocuments($orderby="", $dir="asc", $limit=0, $offset=0) { /* {{{ */
		$db = $this->_dms->getDB();

		if (!isset($this->_documents)) {
			$queryStr = "SELECT * FROM `tblDocuments` WHERE `folder` = " . $this->_id;
			if ($orderby=="n") $queryStr .= " ORDER BY `name`";
			elseif($orderby=="s") $queryStr .= " ORDER BY `sequence`";
			elseif($orderby=="d") $queryStr .= " ORDER BY `date`";
			if($dir == 'desc')
				$queryStr .= " DESC";
			if(is_int($limit) && $limit > 0) {
				$queryStr .= " LIMIT ".$limit;
				if(is_int($offset) && $offset > 0)
					$queryStr .= " OFFSET ".$offset;
			}

			$resArr = $db->getResultArray($queryStr);
			if (is_bool($resArr) && !$resArr)
				return false;

			$this->_documents = array();
			foreach ($resArr as $row) {
//				array_push($this->_documents, new LetoDMS_Core_Document($row["id"], $row["name"], $row["comment"], $row["date"], $row["expires"], $row["owner"], $row["folder"], $row["inheritAccess"], $row["defaultAccess"], isset($row["lockUser"])?$row["lockUser"]:NULL, $row["keywords"], $row["sequence"]));
				array_push($this->_documents, $this->_dms->getDocument($row["id"]));
			}
		}
		return $this->_documents;
	} /* }}} */

	/**
	 * Count all documents and subfolders of the folder
	 *
	 * This function also counts documents and folders of subfolders, so
	 * basically it works like recursively counting children.
	 *
	 * This function checks for access rights up the given limit. If more
	 * documents or folders are found, the returned value will be the number
	 * of objects available and the precise flag in the return array will be
	 * set to false. This number should not be revelead to the
	 * user, because it allows to gain information about the existens of
	 * objects without access right.
	 * Setting the parameter $limit to 0 will turn off access right checking
	 * which is reasonable if the $user is an administrator.
	 *
	 * @param LetoDMS_Core_User $user
	 * @param integer $limit maximum number of folders and documents that will
	 *        be precisly counted by taken the access rights into account
	 * @return array|bool with four elements 'document_count', 'folder_count'
	 *        'document_precise', 'folder_precise' holding
	 * the counted number and a flag if the number is precise.
	 * @internal param string $orderby if set to 'n' the list is ordered by name, otherwise
	 *        it will be ordered by sequence
	 */
	function countChildren($user, $limit=10000) { /* {{{ */
		$db = $this->_dms->getDB();

		$pathPrefix="";
		$path = $this->getPath();
		foreach ($path as $f) {
			$pathPrefix .= ":".$f->getID();
		}
		if (strlen($pathPrefix)>1) {
			$pathPrefix .= ":";
		}

		$queryStr = "SELECT id FROM `tblFolders` WHERE `folderList` like '".$pathPrefix. "%'";
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && !$resArr)
			return false;

		$result = array();

		$folders = array();
		$folderids = array($this->_id);
		$cfolders = count($resArr);
		if($cfolders < $limit) {
			foreach ($resArr as $row) {
				$folder = $this->_dms->getFolder($row["id"]);
				if ($folder->getAccessMode($user) >= M_READ) {
					array_push($folders, $folder);
					array_push($folderids, $row['id']);
				}
			}
			$result['folder_count'] = count($folders);
			$result['folder_precise'] = true;
		} else {
			foreach ($resArr as $row) {
				array_push($folderids, $row['id']);
			}
			$result['folder_count'] = $cfolders;
			$result['folder_precise'] = false;
		}

		$documents = array();
		if($folderids) {
			$queryStr = "SELECT id FROM `tblDocuments` WHERE `folder` in (".implode(',', $folderids). ")";
			$resArr = $db->getResultArray($queryStr);
			if (is_bool($resArr) && !$resArr)
				return false;

			$cdocs = count($resArr);
			if($cdocs < $limit) {
				foreach ($resArr as $row) {
					$document = $this->_dms->getDocument($row["id"]);
					if ($document->getAccessMode($user) >= M_READ)
						array_push($documents, $document);
				}
				$result['document_count'] = count($documents);
				$result['document_precise'] = true;
			} else {
				$result['document_count'] = $cdocs;
				$result['document_precise'] = false;
			}
		}

		return $result;
	} /* }}} */

	// $comment will be used for both document and version leaving empty the version_comment 
	/**
	 * Add a new document to the folder
	 * This function will add a new document and its content from a given file.
	 * It does not check for access rights on the folder. The new documents
	 * default access right is read only and the access right is inherited.
	 *
	 * @param string $name name of new document
	 * @param string $comment comment of new document
	 * @param integer $expires expiration date as a unix timestamp or 0 for no
	 *        expiration date
	 * @param object $owner owner of the new document
	 * @param LetoDMS_Core_User $keywords keywords of new document
	 * @param LetoDMS_Core_DocumentCategory[] $categories list of category objects
	 * @param string $tmpFile the path of the file containing the content
	 * @param string $orgFileName the original file name
	 * @param string $fileType usually the extension of the filename
	 * @param string $mimeType mime type of the content
	 * @param float $sequence position of new document within the folder
	 * @param array $reviewers list of users who must review this document
	 * @param array $approvers list of users who must approve this document
	 * @param int|string $reqversion version number of the content
	 * @param string $version_comment comment of the content. If left empty
	 *        the $comment will be used.
	 * @param array $attributes list of document attributes. The element key
	 *        must be the id of the attribute definition.
	 * @param array $version_attributes list of document version attributes.
	 *        The element key must be the id of the attribute definition.
	 * @param LetoDMS_Core_Workflow $workflow
	 * @return array|bool false in case of error, otherwise an array
	 *        containing two elements. The first one is the new document, the
	 * second one is the result set returned when inserting the content.
	 */
	function addDocument($name, $comment, $expires, $owner, $keywords, $categories, $tmpFile, $orgFileName, $fileType, $mimeType, $sequence, $reviewers=array(), $approvers=array(),$reqversion=0,$version_comment="", $attributes=array(), $version_attributes=array(), $workflow=null) { /* {{{ */
		$db = $this->_dms->getDB();

		$expires = (!$expires) ? 0 : $expires;

		// Must also ensure that the document has a valid folderList.
		$pathPrefix="";
		$path = $this->getPath();
		foreach ($path as $f) {
			$pathPrefix .= ":".$f->getID();
		}
		if (strlen($pathPrefix)>1) {
			$pathPrefix .= ":";
		}

		$db->startTransaction();

		$queryStr = "INSERT INTO `tblDocuments` (`name`, `comment`, `date`, `expires`, `owner`, `folder`, `folderList`, `inheritAccess`, `defaultAccess`, `locked`, `keywords`, `sequence`) VALUES ".
					"(".$db->qstr($name).", ".$db->qstr($comment).", ".$db->getCurrentTimestamp().", ".(int) $expires.", ".$owner->getID().", ".$this->_id.",".$db->qstr($pathPrefix).", 1, ".M_READ.", -1, ".$db->qstr($keywords).", " . $sequence . ")";
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$document = $this->_dms->getDocument($db->getInsertID('tblDocuments'));

//		if ($version_comment!="")
			$res = $document->addContent($version_comment, $owner, $tmpFile, $orgFileName, $fileType, $mimeType, $reviewers, $approvers, $reqversion, $version_attributes, $workflow);
//		else $res = $document->addContent($comment, $owner, $tmpFile, $orgFileName, $fileType, $mimeType, $reviewers, $approvers,$reqversion, $version_attributes, $workflow);

		if (is_bool($res) && !$res) {
			$db->rollbackTransaction();
			return false;
		}

		if($categories) {
			$document->setCategories($categories);
		}

		if($attributes) {
			foreach($attributes as $attrdefid=>$attribute) {
				/* $attribute can be a string or an array */
				if($attribute)
					if(!$document->setAttributeValue($this->_dms->getAttributeDefinition($attrdefid), $attribute)) {
						$document->remove();
						$db->rollbackTransaction();
						return false;
					}
			}
		}

		$db->commitTransaction();

		/* Check if 'onPostAddDocument' callback is set */
		if(isset($this->_dms->callbacks['onPostAddDocument'])) {
			foreach($this->_dms->callbacks['onPostAddDocument'] as $callback) {
					/** @noinspection PhpStatementHasEmptyBodyInspection */
					if(!call_user_func($callback[0], $callback[1], $document)) {
				}
			}
		}

		return array($document, $res);
	} /* }}} */

	/**
	 * Remove a single folder
	 *
	 * Removes just a single folder, but not its subfolders or documents
	 * This function will fail if the folder has subfolders or documents
	 * because of referencial integrity errors.
	 *
	 * @return boolean true on success, false in case of an error
	 */
	protected function removeFromDatabase() { /* {{{ */
		$db = $this->_dms->getDB();

		/* Check if 'onPreRemoveFolder' callback is set */
		if(isset($this->_dms->callbacks['onPreRemoveFromDatabaseFolder'])) {
			foreach($this->_dms->callbacks['onPreRemoveFromDatabaseFolder'] as $callback) {
				$ret = call_user_func($callback[0], $callback[1], $this);
				if(is_bool($ret))
					return $ret;
			}
		}

		$db->startTransaction();
		// unset homefolder as it will no longer exist
		$queryStr = "UPDATE `tblUsers` SET `homefolder`=NULL WHERE `homefolder` =  " . $this->_id;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		// Remove database entries
		$queryStr = "DELETE FROM `tblFolders` WHERE `id` =  " . $this->_id;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}
		$queryStr = "DELETE FROM `tblFolderAttributes` WHERE `folder` =  " . $this->_id;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}
		$queryStr = "DELETE FROM `tblACLs` WHERE `target` = ". $this->_id. " AND `targetType` = " . T_FOLDER;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$queryStr = "DELETE FROM `tblNotify` WHERE `target` = ". $this->_id. " AND `targetType` = " . T_FOLDER;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}
		$db->commitTransaction();

		/* Check if 'onPostRemoveFolder' callback is set */
		if(isset($this->_dms->callbacks['onPostRemoveFromDatabaseFolder'])) {
			foreach($this->_dms->callbacks['onPostRemoveFromDatabaseFolder'] as $callback) {
				/** @noinspection PhpStatementHasEmptyBodyInspection */
				if(!call_user_func($callback[0], $callback[1], $this->_id)) {
				}
			}
		}

		return true;
	} /* }}} */

	/**
	 * Remove recursively a folder
	 *
	 * Removes a folder, all its subfolders and documents
	 *
	 * @return boolean true on success, false in case of an error
	 */
	function remove() { /* {{{ */
		/** @noinspection PhpUnusedLocalVariableInspection */
		$db = $this->_dms->getDB();

		// Do not delete the root folder.
		if ($this->_id == $this->_dms->rootFolderID || !isset($this->_parentID) || ($this->_parentID == null) || ($this->_parentID == "") || ($this->_parentID == 0)) {
			return false;
		}

		/* Check if 'onPreRemoveFolder' callback is set */
		if(isset($this->_dms->callbacks['onPreRemoveFolder'])) {
			foreach($this->_dms->callbacks['onPreRemoveFolder'] as $callback) {
				$ret = call_user_func($callback[0], $callback[1], $this);
				if(is_bool($ret))
					return $ret;
			}
		}

		//Entfernen der Unterordner und Dateien
		$res = $this->getSubFolders();
		if (is_bool($res) && !$res) return false;
		$res = $this->getDocuments();
		if (is_bool($res) && !$res) return false;

		foreach ($this->_subFolders as $subFolder) {
			$res = $subFolder->remove();
			if (!$res) {
				return false;
			}
		}

		foreach ($this->_documents as $document) {
			$res = $document->remove();
			if (!$res) {
				return false;
			}
		}

		$ret = $this->removeFromDatabase();
		if(!$ret)
			return $ret;

		/* Check if 'onPostRemoveFolder' callback is set */
		if(isset($this->_dms->callbacks['onPostRemoveFolder'])) {
			foreach($this->_dms->callbacks['onPostRemoveFolder'] as $callback) {
				call_user_func($callback[0], $callback[1], $this);
			}
		}

		return $ret;
	} /* }}} */

	/**
	 * Returns a list of access privileges
	 *
	 * If the folder inherits the access privileges from the parent folder
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
	 * @return bool|LetoDMS_Core_GroupAccess|LetoDMS_Core_UserAccess
	 */
	function getAccessList($mode = M_ANY, $op = O_EQ) { /* {{{ */
		$db = $this->_dms->getDB();

		if ($this->inheritsAccess()) {
			$res = $this->getParent();
			if (!$res) return false;
			return $this->_parent->getAccessList($mode, $op);
		}

		if (!isset($this->_accessList[$mode])) {
			if ($op!=O_GTEQ && $op!=O_LTEQ && $op!=O_EQ) {
				return false;
			}
			$modeStr = "";
			if ($mode!=M_ANY) {
				$modeStr = " AND mode".$op.(int)$mode;
			}
			$queryStr = "SELECT * FROM `tblACLs` WHERE `targetType` = ".T_FOLDER.
				" AND `target` = " . $this->_id .	$modeStr . " ORDER BY `targetType`";
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
	 * Delete all entries for this folder from the access control list
	 *
	 * @param boolean $noclean set to true if notifier list shall not be clean up
	 * @return boolean true if operation was successful otherwise false
	 */
	function clearAccessList($noclean=false) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "DELETE FROM `tblACLs` WHERE `targetType` = " . T_FOLDER . " AND `target` = " . $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		unset($this->_accessList);

		if(!$noclean)
			self::cleanNotifyList();

		return true;
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
					(".$this->_id.", ".T_FOLDER.", " . (int) $userOrGroupID . ", " .(int) $mode. ")";
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
	 * Change access right of folder
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

		$queryStr = "UPDATE `tblACLs` SET `mode` = " . (int) $newMode . " WHERE `targetType` = ".T_FOLDER." AND `target` = " . $this->_id . " AND " . $userOrGroup . " = " . (int) $userOrGroupID;
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
	 * @param $userOrGroupID
	 * @param $isUser
	 * @return bool
	 */
	function removeAccess($userOrGroupID, $isUser) { /* {{{ */
		$db = $this->_dms->getDB();

		$userOrGroup = ($isUser) ? "`userID`" : "`groupID`";

		$queryStr = "DELETE FROM `tblACLs` WHERE `targetType` = ".T_FOLDER." AND `target` = ".$this->_id." AND ".$userOrGroup." = " . (int) $userOrGroupID;
		if (!$db->getResult($queryStr))
			return false;

		unset($this->_accessList);

		// Update the notify list, if necessary.
		$mode = ($isUser ? $this->getAccessMode($this->_dms->getUser($userOrGroupID)) : $this->getGroupAccessMode($this->_dms->getGroup($userOrGroupID)));
		if ($mode == M_NONE) {
			$this->removeNotify($userOrGroupID, $isUser);
		}

		return true;
	} /* }}} */

	/**
	 * Get the access mode of a user on the folder
	 *
	 * This function returns the access mode for a given user. An administrator
	 * and the owner of the folder has unrestricted access. A guest user has
	 * read only access or no access if access rights are further limited
	 * by access control lists. All other users have access rights according
	 * to the access control lists or the default access. This function will
	 * recursive check for access rights of parent folders if access rights
	 * are inherited.
	 *
	 * This function returns the access mode for a given user. An administrator
	 * and the owner of the folder has unrestricted access. A guest user has
	 * read only access or no access if access rights are further limited
	 * by access control lists. All other users have access rights according
	 * to the access control lists or the default access. This function will
	 * recursive check for access rights of parent folders if access rights
	 * are inherited.
	 *
	 * Before checking the access in the method itself a callback 'onCheckAccessFolder'
	 * is called. If it returns a value > 0, then this will be returned by this
	 * method without any further checks. The optional paramater $context
	 * will be passed as a third parameter to the callback. It contains
	 * the operation for which the access mode is retrieved. It is for example
	 * set to 'removeDocument' if the access mode is used to check for sufficient
	 * permission on deleting a document.
	 *
	 * @param object $user user for which access shall be checked
	 * @param string $context context in which the access mode is requested
	 * @return integer access mode
	 */
	function getAccessMode($user, $context='') { /* {{{ */
		if(!$user)
			return M_NONE;

		/* Check if 'onCheckAccessFolder' callback is set */
		if(isset($this->_dms->callbacks['onCheckAccessFolder'])) {
			foreach($this->_dms->callbacks['onCheckAccessFolder'] as $callback) {
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
	 * Get the access mode for a group on the folder
	 * This function returns the access mode for a given group. The algorithmn
	 * applied to get the access mode is the same as describe at
	 * {@link getAccessMode}
	 *
	 * @param LetoDMS_Core_Group $group group for which access shall be checked
	 * @return integer access mode
	 */
	function getGroupAccessMode($group) { /* {{{ */
		$highestPrivileged = M_NONE;
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
				if ($highestPrivileged == M_ALL) /* no need to check further */
					return $highestPrivileged;
			}
		}
		if ($foundInACL)
			return $highestPrivileged;

		/* Take default access */
		return $this->getDefaultAccess();
	} /* }}} */

	/** @noinspection PhpUnusedParameterInspection */
	/**
	 * Get a list of all notification
	 * This function returns all users and groups that have registerd a
	 * notification for the folder
	 *
	 * @param integer $type type of notification (not yet used)
	 * @return LetoDMS_Core_User[]|LetoDMS_Core_Group[]|bool array with a the elements 'users' and 'groups' which
	 *        contain a list of users and groups.
	 */
	function getNotifyList($type=0) { /* {{{ */
		if (empty($this->_notifyList)) {
			$db = $this->_dms->getDB();

			$queryStr ="SELECT * FROM `tblNotify` WHERE `targetType` = " . T_FOLDER . " AND `target` = " . $this->_id;
			$resArr = $db->getResultArray($queryStr);
			if (is_bool($resArr) && $resArr == false)
				return false;

			$this->_notifyList = array("groups" => array(), "users" => array());
			foreach ($resArr as $row)
			{
				if ($row["userID"] != -1) {
					$u = $this->_dms->getUser($row["userID"]);
					if($u && !$u->isDisabled())
						array_push($this->_notifyList["users"], $u);
				} else {//if ($row["groupID"] != -1)
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
		$ngroups = $this->_notifyList["groups"];
		foreach ($nusers as $u) {
			if ($this->getAccessMode($u) < M_READ) {
				$this->removeNotify($u->getID(), true);
			}
		}

		/** @var LetoDMS_Core_Group[] $ngroups */
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
	 * @param integer $userOrGroupID
	 * @param boolean $isUser true if $userOrGroupID is a user id otherwise false
	 * @return integer error code
	 *    -1: Invalid User/Group ID.
	 *    -2: Target User / Group does not have read access.
	 *    -3: User is already subscribed.
	 *    -4: Database / internal error.
	 *     0: Update successful.
	 */
	function addNotify($userOrGroupID, $isUser) { /* {{{ */
		$db = $this->_dms->getDB();

		$userOrGroup = ($isUser) ? "`userID`" : "`groupID`";

		/* Verify that user / group exists */
		/** @var LetoDMS_Core_User|LetoDMS_Core_Group $obj */
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

		//
		// Verify that user / group has read access to the document.
		//
		if ($isUser) {
			// Users are straightforward to check.
			if ($this->getAccessMode($obj) < M_READ) {
				return -2;
			}
		}
		else {
			// FIXME: Why not check the access list first and if this returns
			// not result, then use the default access?
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
		//
		// Check to see if user/group is already on the list.
		//
		$queryStr = "SELECT * FROM `tblNotify` WHERE `tblNotify`.`target` = '".$this->_id."' ".
			"AND `tblNotify`.`targetType` = '".T_FOLDER."' ".
			"AND `tblNotify`.".$userOrGroup." = '". (int) $userOrGroupID."'";
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr)) {
			return -4;
		}
		if (count($resArr)>0) {
			return -3;
		}

		$queryStr = "INSERT INTO `tblNotify` (`target`, `targetType`, " . $userOrGroup . ") VALUES (" . $this->_id . ", " . T_FOLDER . ", " .  (int) $userOrGroupID . ")";
		if (!$db->getResult($queryStr))
			return -4;

		unset($this->_notifyList);
		return 0;
	} /* }}} */

	/**
	 * Removes notify for a user or group to folder
	 * This function does not check if the currently logged in user
	 * is allowed to remove a notification. This must be checked by the calling
	 * application.
	 *
	 * @param integer $userOrGroupID
	 * @param boolean $isUser true if $userOrGroupID is a user id otherwise false
	 * @param int $type type of notification (0 will delete all) Not used yet!
	 * @return int error code
	 *    -1: Invalid User/Group ID.
	 * -3: User is not subscribed.
	 * -4: Database / internal error.
	 * 0: Update successful.
	 */
	function removeNotify($userOrGroupID, $isUser, $type=0) { /* {{{ */
		$db = $this->_dms->getDB();

		/* Verify that user / group exists. */
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
		GLOBAL  $user;
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

		//
		// Check to see if the target is in the database.
		//
		$queryStr = "SELECT * FROM `tblNotify` WHERE `tblNotify`.`target` = '".$this->_id."' ".
			"AND `tblNotify`.`targetType` = '".T_FOLDER."' ".
			"AND `tblNotify`.".$userOrGroup." = '". (int) $userOrGroupID."'";
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr)) {
			return -4;
		}
		if (count($resArr)==0) {
			return -3;
		}

		$queryStr = "DELETE FROM `tblNotify` WHERE `target` = " . $this->_id . " AND `targetType` = " . T_FOLDER . " AND " . $userOrGroup . " = " .  (int) $userOrGroupID;
		/* If type is given then delete only those notifications */
		if($type)
			$queryStr .= " AND `type` = ".(int) $type;
		if (!$db->getResult($queryStr))
			return -4;

		unset($this->_notifyList);
		return 0;
	} /* }}} */

	/**
	 * Get List of users and groups which have read access on the document
	 *
	 * This function is deprecated. Use
	 * {@see LetoDMS_Core_Folder::getReadAccessList()} instead.
	 */
	function getApproversList() { /* {{{ */
		return $this->getReadAccessList(0, 0);
	} /* }}} */

	/**
	 * Returns a list of groups and users with read access on the folder
	 * The list will not include any guest users,
	 * administrators and the owner of the folder unless $listadmin resp.
	 * $listowner is set to true.
	 *
	 * @param bool|int $listadmin if set to true any admin will be listed too
	 * @param bool|int $listowner if set to true the owner will be listed too
	 * @return array list of users and groups
	 */
	function getReadAccessList($listadmin=0, $listowner=0) { /* {{{ */
		$db = $this->_dms->getDB();

		if (!isset($this->_readAccessList)) {
			$this->_readAccessList = array("groups" => array(), "users" => array());
			$userIDs = "";
			$groupIDs = "";
			$defAccess  = $this->getDefaultAccess();

			/* Check if the default access is < read access or >= read access.
			 * If default access is less than read access, then create a list
			 * of users and groups with read access.
			 * If default access is equal or greater then read access, then
			 * create a list of users and groups without read access.
			 */
			if ($defAccess<M_READ) {
				// Get the list of all users and groups that are listed in the ACL as
				// having read access to the folder.
				$tmpList = $this->getAccessList(M_READ, O_GTEQ);
			}
			else {
				// Get the list of all users and groups that DO NOT have read access
				// to the folder.
				$tmpList = $this->getAccessList(M_NONE, O_LTEQ);
			}
			/** @var LetoDMS_Core_GroupAccess $groupAccess */
			foreach ($tmpList["groups"] as $groupAccess) {
				$groupIDs .= (strlen($groupIDs)==0 ? "" : ", ") . $groupAccess->getGroupID();
			}

			/** @var LetoDMS_Core_UserAccess $userAccess */
			foreach ($tmpList["users"] as $userAccess) {
				$user = $userAccess->getUser();
				if (!$listadmin && $user->isAdmin()) continue;
				if (!$listowner && $user->getID() == $this->_ownerID) continue;
				if ($user->isGuest()) continue;
				$userIDs .= (strlen($userIDs)==0 ? "" : ", ") . $userAccess->getUserID();
			}

			// Construct a query against the users table to identify those users
			// that have read access to this folder, either directly through an
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
			/* If default access is equal or greate then read, $userIDs and
			 * $groupIDs contains a list of user without read access
			 */
			else {
				if (strlen($groupIDs)>0) {
					$queryStr = "SELECT `tblUsers`.* FROM `tblUsers` ".
						"LEFT JOIN `tblGroupMembers` ON `tblGroupMembers`.`userID`=`tblUsers`.`id` ".
						"WHERE `tblGroupMembers`.`groupID` NOT IN (". $groupIDs .")".
						"AND `tblUsers`.`role` != ".LetoDMS_Core_User::role_guest." ".
						(strlen($userIDs) == 0 ? "" : " AND (`tblUsers`.`id` NOT IN (". $userIDs ."))")." UNION ";
				}
				$queryStr .=
					"SELECT `tblUsers`.* FROM `tblUsers` ".
					"WHERE (`tblUsers`.`id` = ". $this->_ownerID . ") ".
					"OR (`tblUsers`.`role` = ".LetoDMS_Core_User::role_admin.") ".
					"UNION ".
					"SELECT `tblUsers`.* FROM `tblUsers` ".
					"WHERE `tblUsers`.`role` != ".LetoDMS_Core_User::role_guest." ".
					(strlen($userIDs) == 0 ? "" : " AND (`tblUsers`.`id` NOT IN (". $userIDs ."))").
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

			// Assemble the list of groups that have read access to the folder.
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

		$queryStr = "SELECT `folderList` FROM `tblFolders` where `id` = ".$this->_id;
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && !$resArr)
			return false;
		return $resArr[0]['folderList'];
	} /* }}} */

	/**
	 * Checks the internal data of the folder and repairs it.
	 * Currently, this function only repairs an incorrect folderList
	 *
	 * @return boolean true on success, otherwise false
	 */
	function repair() { /* {{{ */
		$db = $this->_dms->getDB();

		$curfolderlist = $this->getFolderList();

		// calculate the folderList of the folder
		$parent = $this->getParent();
		$pathPrefix="";
		$path = $parent->getPath();
		foreach ($path as $f) {
			$pathPrefix .= ":".$f->getID();
		}
		if (strlen($pathPrefix)>1) {
			$pathPrefix .= ":";
		}
		if($curfolderlist != $pathPrefix) {
			$queryStr = "UPDATE `tblFolders` SET `folderList`='".$pathPrefix."' WHERE `id` = ". $this->_id;
			$res = $db->getResult($queryStr);
			if (!$res)
				return false;
		}
		return true;
	} /* }}} */

	/**
	 * Get the min and max sequence value for documents
	 *
	 * @return bool|array array with keys 'min' and 'max', false in case of an error
	 */
	function getDocumentsMinMax() { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "SELECT min(`sequence`) AS `min`, max(`sequence`) AS `max` FROM `tblDocuments` WHERE `folder` = " . (int) $this->_id;
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;

		return $resArr[0];
	} /* }}} */

}

?>
