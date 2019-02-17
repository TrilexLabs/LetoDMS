<?php
/**
 * Implementation of a simple session management.
 *
 * LetoDMS uses its own simple session management, storing sessions
 * into the database. A session holds the currently logged in user,
 * the theme and the language.
 *
 * @category   DMS
 * @package    LetoDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  2011 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Class to represent a session
 *
 * This class provides some very basic methods to load, save and delete
 * sessions. It does not set or retrieve a cockie. This is up to the
 * application. The class basically provides access to the session database
 * table.
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  2011 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Session {
	/**
	 * @var object $db reference to database object. This must be an instance
	 *      of {@link LetoDMS_Core_DatabaseAccess}.
	 * @access protected
	 */
	protected $db;

	/**
	 * @var array $data session data
	 * @access protected
	 */
	protected $data;

	/**
	 * @var string $id session id
	 * @access protected
	 */
	protected $id;

	/**
	 * Create a new instance of the session handler
	 *
	 * @param object $db object to access the underlying database
	 * @return object instance of LetoDMS_Session
	 */
	function __construct($db) { /* {{{ */
		$this->db = $db;
		$this->id = false;
	} /* }}} */

	/**
	 * Load session by its id from database
	 *
	 * @param string $id id of session
	 * @return boolean true if successful otherwise false
	 */
	function load($id) { /* {{{ */
		$queryStr = "SELECT * FROM `tblSessions` WHERE `id` = ".$this->db->qstr($id);
		$resArr = $this->db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;
		if (count($resArr) == 0)
			return false;
		$this->id = $id;
		$this->data = array('userid'=>$resArr[0]['userID'], 'theme'=>$resArr[0]['theme'], 'lang'=>$resArr[0]['language'], 'id'=>$resArr[0]['id'], 'lastaccess'=>$resArr[0]['lastAccess'], 'su'=>$resArr[0]['su']);
		if($resArr[0]['clipboard'])
			$this->data['clipboard'] = json_decode($resArr[0]['clipboard'], true);
		else
			$this->data['clipboard'] = array('docs'=>array(), 'folders'=>array());
		if($resArr[0]['splashmsg'])
			$this->data['splashmsg'] = json_decode($resArr[0]['splashmsg'], true);
		else
			$this->data['splashmsg'] = array();
		return $resArr[0];
	} /* }}} */

	/**
	 * Create a new session and saving the given data into the database
	 *
	 * @param array $data data saved in session (the only fields supported
	 *        are userid, theme, language, su)
	 * @return string/boolean id of session of false in case of an error
	 */
	function create($data) { /* {{{ */
		$id = "" . rand() . '-'.microtime() . '-'.rand() . "";
		$id = md5($id);
		$lastaccess = time();
		$queryStr = "INSERT INTO `tblSessions` (`id`, `userID`, `lastAccess`, `theme`, `language`, `su`) ".
		  "VALUES ('".$id."', ".$data['userid'].", ".$lastaccess.", '".$data['theme']."', '".$data['lang']."', 0)";
		if (!$this->db->getResult($queryStr)) {
			return false;
		}
		$this->id = $id;
		$this->data = $data;
		$this->data['id'] = $id;
		$this->data['lastaccess'] = $lastaccess;
		$this->data['su'] = 0;
		$this->data['clipboard'] = array('docs'=>array(), 'folders'=>array());
		$this->data['clipboard'] = array('type'=>'', 'msg'=>'');
		$this->data['splashmsg'] = array();
		return $id;
	} /* }}} */

	/**
	 * Update last access time of session
	 *
	 * This function should be called, when the last access time of the
	 * session must be updated. This should be done at least after login,
	 * but can also be done at any other time. Sessions that are never
	 * updated will be deleted when deleteByTime() is called and the session
	 * life time has exceeded the cookie life time or 1 week.
	 *
	 * @param string $id id of session
	 * @return boolean true if successful otherwise false
	 */
	function updateAccess($id) { /* {{{ */
		$queryStr = "UPDATE `tblSessions` SET `lastAccess` = " . time() . " WHERE `id` = " . $this->db->qstr($id);
		if (!$this->db->getResult($queryStr))
			return false;
		return true;
	} /* }}} */

	/**
	 * Delete sessions older than a given time from the database
	 *
	 * @param integer $sec maximum number of seconds a session may live
	 * @return boolean true if successful otherwise false
	 */
	function deleteByTime($sec) { /* {{{ */
		$queryStr = "DELETE FROM `tblSessions` WHERE " . time() . " - `lastAccess` > ".$sec;
		if (!$this->db->getResult($queryStr)) {
			return false;
		}
		return true;
	} /* }}} */

	/**
	 * Delete session by its id
	 *
	 * @param string $id id of session
	 * @return boolean true if successful otherwise false
	 */
	function delete($id) { /* {{{ */
		$queryStr = "DELETE FROM `tblSessions` WHERE `id` = " . $this->db->qstr($id);
		if (!$this->db->getResult($queryStr)) {
			return false;
		}
		$this->id = false;
		return true;
	} /* }}} */

	/**
	 * Get session id
	 *
	 * @return string session id
	 */
	function getId() { /* {{{ */
		return $this->id;
	} /* }}} */

	/**
	 * Get user id of session
	 *
	 * @return integer user id
	 */
	function getUser() { /* {{{ */
		return $this->data['userid'];
	} /* }}} */

	/**
	 * Set user of session
	 *
	 * @param integer $userid id of user
	 */
	function setUser($userid) { /* {{{ */
		/* id is only set if load() was called before */
		if($this->id) {
			$queryStr = "UPDATE `tblSessions` SET `userID` = " . $this->db->qstr($userid) . " WHERE `id` = " . $this->db->qstr($this->id);
			if (!$this->db->getResult($queryStr))
				return false;
			$this->data['userid'] = $userid;	
		}
		return true;
	} /* }}} */

	/**
	 * Set language of session
	 *
	 * @param string $lang language
	 */
	function setLanguage($lang) { /* {{{ */
		/* id is only set if load() was called before */
		if($this->id) {
			$queryStr = "UPDATE `tblSessions` SET `language` = " . $this->db->qstr($lang) . " WHERE `id` = " . $this->db->qstr($this->id);
			if (!$this->db->getResult($queryStr))
				return false;
			$this->data['lang'] = $lang;	
		}
		return true;
	} /* }}} */

	/**
	 * Get language of session
	 *
	 * @return string language
	 */
	function getLanguage() { /* {{{ */
		return $this->data['lang'];
	} /* }}} */

	/**
	 * Substitute user of session
	 *
	 * @param integer $su user id
	 */
	function setSu($su) { /* {{{ */
		/* id is only set if load() was called before */
		if($this->id) {
			$queryStr = "UPDATE `tblSessions` SET `su` = " . (int) $su . " WHERE `id` = " . $this->db->qstr($this->id);
			if (!$this->db->getResult($queryStr))
				return false;
			$this->data['su'] = (int) $su;	
		}
		return true;
	} /* }}} */

	/**
	 * Reset substitute user of session
	 *
	 * @param integer $su user id
	 */
	function resetSu() { /* {{{ */
		/* id is only set if load() was called before */
		if($this->id) {
			$queryStr = "UPDATE `tblSessions` SET `su` = 0 WHERE `id` = " . $this->db->qstr($this->id);
			if (!$this->db->getResult($queryStr))
				return false;
			$this->data['su'] = 0;	
		}
		return true;
	} /* }}} */

	/**
	 * Get substituted user id of session
	 *
	 * @return integer substituted user id
	 */
	function getSu() { /* {{{ */
		return $this->data['su'];
	} /* }}} */

	/**
	 * Set clipboard of session
	 *
	 * @param array $clipboard list of folders and documents
	 */
	function setClipboard($clipboard) { /* {{{ */
		/* id is only set if load() was called before */
		if($this->id) {
			$queryStr = "UPDATE `tblSessions` SET `clipboard` = " . $this->db->qstr(json_encode($clipboard)) . " WHERE `id` = " . $this->db->qstr($this->id);
			if (!$this->db->getResult($queryStr))
				return false;
			$this->data['clipboard'] = $clipboard;	
		}
		return true;
	} /* }}} */

	/**
	 * Get clipboard of session
	 *
	 * @return array list of clipboard entries
	 */
	function getClipboard() { /* {{{ */
		return (array) $this->data['clipboard'];
	} /* }}} */

	/**
	 * Add to clipboard of session
	 *
	 * @param object $object Document or folder
	 */
	function addToClipboard($object) { /* {{{ */
		/* id is only set if load() was called before */
		if($this->id) {
			$dms = $object->_dms;
			if(get_class($object) == $dms->getClassname('document')) {
				if(!in_array($object->getID(), $this->data['clipboard']['docs']))
					array_push($this->data['clipboard']['docs'], $object->getID());
			} elseif(get_class($object) == $dms->getClassname('folder')) {
				if(!in_array($object->getID(), $this->data['clipboard']['folders']))
					array_push($this->data['clipboard']['folders'], $object->getID());
			}
			$queryStr = "UPDATE `tblSessions` SET `clipboard` = " . $this->db->qstr(json_encode($this->data['clipboard'])) . " WHERE `id` = " . $this->db->qstr($this->id);
			if (!$this->db->getResult($queryStr))
				return false;
		}
		return true;
	} /* }}} */

	/**
	 * Remove from clipboard
	 *
	 * @param object $object Document or folder to remove
	 */
	function removeFromClipboard($object) { /* {{{ */
		/* id is only set if load() was called before */
		if($this->id) {
			$dms = $object->_dms;
			if(get_class($object) == $dms->getClassname('document')) {
				$key = array_search($object->getID(), $this->data['clipboard']['docs']);
				if($key !== false)
					unset($this->data['clipboard']['docs'][$key]);
			} elseif(get_class($object) == $dms->getClassname('folder')) {
				$key = array_search($object->getID(), $this->data['clipboard']['folders']);
				if($key !== false)
					unset($this->data['clipboard']['folders'][$key]);
			}
			$queryStr = "UPDATE `tblSessions` SET `clipboard` = " . $this->db->qstr(json_encode($this->data['clipboard'])) . " WHERE `id` = " . $this->db->qstr($this->id);
			if (!$this->db->getResult($queryStr))
				return false;
		}
		return true;
	} /* }}} */

	/**
	 * Clear clipboard
	 *
	 */
	function clearClipboard() { /* {{{ */
		$this->data['clipboard']['docs'] = array();
		$this->data['clipboard']['folders'] = array();
		$queryStr = "UPDATE `tblSessions` SET `clipboard` = " . $this->db->qstr(json_encode($this->data['clipboard'])) . " WHERE `id` = " . $this->db->qstr($this->id);
		if (!$this->db->getResult($queryStr))
			return false;
		return true;
	} /* }}} */

	/**
	 * Set splash message of session
	 *
	 * @param array $msg contains 'typ' and 'msg'
	 */
	function setSplashMsg($msg) { /* {{{ */
		/* id is only set if load() was called before */
		if($this->id) {
			$queryStr = "UPDATE `tblSessions` SET `splashmsg` = " . $this->db->qstr(json_encode($msg)) . " WHERE `id` = " . $this->db->qstr($this->id);
			if (!$this->db->getResult($queryStr))
				return false;
			$this->data['splashmsg'] = $msg;	
		}
		return true;
	} /* }}} */

	/**
	 * Set splash message of session
	 *
	 * @param array $msg contains 'typ' and 'msg'
	 */
	function clearSplashMsg() { /* {{{ */
		/* id is only set if load() was called before */
		if($this->id) {
			$queryStr = "UPDATE `tblSessions` SET `splashmsg` = '' WHERE `id` = " . $this->db->qstr($this->id);
			if (!$this->db->getResult($queryStr))
				return false;
			$this->data['splashmsg'] = '';	
		}
		return true;
	} /* }}} */

	/**
	 * Get splash message of session
	 *
	 * @return array last splash message
	 */
	function getSplashMsg() { /* {{{ */
		return (array) $this->data['splashmsg'];
	} /* }}} */

	/**
	 * Get timestamp of last access
	 *
	 * @return int last access time
	 */
	function getLastAccess() { /* {{{ */
		return (int) $this->data['lastaccess'];
	} /* }}} */

}

/**
 * Class for managing sessions
 *
 * This class is for retrieving sessions.
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  2014 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_SessionMgr {
	/**
	 * @var object $db reference to database object. This must be an instance
	 *      of {@link LetoDMS_Core_DatabaseAccess}.
	 * @access protected
	 */
	protected $db;

	/**
	 * Create a new instance of the session manager
	 *
	 * @param object $db object to access the underlying database
	 * @return object instance of LetoDMS_SessionMgr
	 */
	function __construct($db) { /* {{{ */
		$this->db = $db;
	} /* }}} */

	/**
	 * Create a new session and saving the given data into the database
	 *
	 * @param array $data data saved in session (the only fields supported
	 *        are userid, theme, language, su)
	 * @return string/boolean id of session of false in case of an error
	 */
	function create($data) { /* {{{ */
		$id = "" . rand() . time() . rand() . "";
		$id = md5($id);
		$lastaccess = time();
		$queryStr = "INSERT INTO `tblSessions` (`id`, `userID`, `lastAccess`, `theme`, `language`, `su`) ".
		  "VALUES ('".$id."', ".$data['userid'].", ".$lastaccess.", '".$data['theme']."', '".$data['lang']."', 0)";
		if (!$this->db->getResult($queryStr)) {
			return false;
		}

		return $id;
	} /* }}} */

	/**
	 * Get list of all active sessions
	 *
	 * @return array list of sessions
	 */
	function getAllSessions() { /* {{{ */
		$queryStr = "SELECT * FROM `tblSessions`";
		$resArr = $this->db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;
		$sessions = array();
		foreach($resArr as $rec) {
			$session = new LetoDMS_Session($this->db);
			$session->load($rec['id']);
			$sessions[] = $session;
		}
		return $sessions;

	} /* }}} */

	/**
	 * Get list of active sessions for a given user
	 *
	 * @return array list of sessions
	 */
	function getUserSessions($user) { /* {{{ */
		$queryStr = "SELECT * FROM `tblSessions` WHERE `userID`=".$user->getID();
		$resArr = $this->db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;
		$sessions = array();
		foreach($resArr as $rec) {
			$session = new LetoDMS_Session($this->db);
			$session->load($rec['id']);
			$sessions[] = $session;
		}
		return $sessions;

	} /* }}} */

	/**
	 * Get list of active sessions with a given time
	 *
	 * @return array list of sessions
	 */
	function getLastAccessedSessions($datetime) { /* {{{ */
		if(!$ts = makeTsFromLongDate($datetime))
			return false;
		$queryStr = "SELECT a.* FROM `tblSessions` AS a LEFT OUTER JOIN `tblSessions` AS b ON a.`userID`=b.`userID` AND a.`lastAccess`< b.`lastAccess` WHERE b.`userID` IS NULL AND a.`lastAccess` >=".$ts;
		$resArr = $this->db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;
		$sessions = array();
		foreach($resArr as $rec) {
			$session = new LetoDMS_Session($this->db);
			$session->load($rec['id']);
			$sessions[] = $session;
		}
		return $sessions;

	} /* }}} */
}
?>
