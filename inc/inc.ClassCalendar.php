<?php
/**
 * Implementation of calendar
 *
 * @category   DMS
 * @package    LetoDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010 Matteo Lucarelli,
 *             2017 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Include parent class
 */
require_once("inc.ClassNotify.php");

/**
 * Class to manage events
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010 Matteo Lucarelli,
 *             2017 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Calendar {
	/**
	 * Instanz of database
	 */
	protected $db;

	function __construct($db, $user) {
		$this->db = $db;
		$this->user = $user;
	}

	function getEvents($day, $month, $year) { /* {{{ */
		$date = mktime(12,0,0, $month, $day, $year);
		
		$queryStr = "SELECT * FROM `tblEvents` WHERE `start` <= " . $date . " AND `stop` >= " . $date;
		$ret = $this->db->getResultArray($queryStr);	
		return $ret;
	} /* }}} */

	function getEventsInInterval($start, $stop) { /* {{{ */
		$queryStr = "SELECT * FROM `tblEvents` WHERE ( `start` <= " . (int) $start . " AND `stop` >= " . (int) $start . " ) ".
																					 "OR ( `start` <= " . (int) $stop . " AND `stop` >= " . (int) $stop . " ) ".
																					 "OR ( `start` >= " . (int) $start . " AND `stop` <= " . (int) $stop . " )";
		$ret = $this->db->getResultArray($queryStr);	
		return $ret;
	} /* }}} */

	function addEvent($from, $to, $name, $comment ) { /* {{{ */
		$queryStr = "INSERT INTO `tblEvents` (`name`, `comment`, `start`, `stop`, `date`, `userID`) VALUES ".
			"(".$this->db->qstr($name).", ".$this->db->qstr($comment).", ".(int) $from.", ".(int) $to.", ".$this->db->getCurrentTimestamp().", ".$this->user->getID().")";
		
		$ret = $this->db->getResult($queryStr);
		return $ret;
	} /* }}} */

	function getEvent($id) { /* {{{ */
		if (!is_numeric($id)) return false;

		$queryStr = "SELECT * FROM `tblEvents` WHERE `id` = " . (int) $id;
		$ret = $this->db->getResultArray($queryStr);
		
		if (is_bool($ret) && $ret == false) return false;
		else if (count($ret) != 1) return false;
			
		return $ret[0];	
	} /* }}} */

	function editEvent($id, $from, $to=null, $name=null, $comment=null) { /* {{{ */
		if (!is_numeric($id)) return false;
		
		$queryStr = "UPDATE `tblEvents` SET `start` = " . (int) $from . ($to !== null ? ", `stop` = " . (int) $to : '') . ($name !== null ? ", `name` = " . $this->db->qstr($name) : '') . ($comment !== null ? ", `comment` = " . $this->db->qstr($comment) : '') . ", `date` = " . $this->db->getCurrentTimestamp() . " WHERE `id` = ". (int) $id;
		$ret = $this->db->getResult($queryStr);	
		return $ret;
	} /* }}} */

	function delEvent($id) { /* {{{ */
		if (!is_numeric($id)) return false;
		
		$queryStr = "DELETE FROM `tblEvents` WHERE `id` = " . (int) $id;
		$ret = $this->db->getResult($queryStr);	
		return $ret;
	} /* }}} */
}
?>
