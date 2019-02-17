<?php
//    MyDMS. Document Management System
//    Copyright (C) 2002-2005  Markus Westphal
//    Copyright (C) 2006-2008 Malcolm Cowe
//    Copyright (C) 2010 Matteo Lucarelli
//
//    This program is free software; you can redistribute it and/or modify
//    it under the terms of the GNU General Public License as published by
//    the Free Software Foundation; either version 2 of the License, or
//    (at your option) any later version.
//
//    This program is distributed in the hope that it will be useful,
//    but WITHOUT ANY WARRANTY; without even the implied warranty of
//    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//    GNU General Public License for more details.
//
//    You should have received a copy of the GNU General Public License
//    along with this program; if not, write to the Free Software
//    Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.

/* deprecated! use LetoDMS_Core_File::format_filesize() instead */
function formatted_size($size_bytes) { /* {{{ */
	if ($size_bytes>1000000000) return number_format($size_bytes/1000000000,1,".","")." GBytes";
	else if ($size_bytes>1000000) return number_format($size_bytes/1000000,1,".","")." MBytes";
	else if ($size_bytes>1000) return number_format($size_bytes/1000,1,".","")." KBytes";
	return number_format($size_bytes,0,"","")." Bytes";
} /* }}} */

function getReadableDate($timestamp) { /* {{{ */
	return date("Y-m-d", $timestamp);
} /* }}} */

function getLongReadableDate($timestamp) { /* {{{ */
	return date("Y-m-d H:i:s", $timestamp);
} /* }}} */

/*
 * Converts a date/time string into a timestamp
 *
 * @param $date string date in form Y-m-d H:i:s
 * @return integer/boolean unix timestamp or false in case of an error
 */
function makeTsFromLongDate($date) { /* {{{ */
	$tmp = explode(' ', $date);
	if(count($tmp) != 2)
		return false;
	$tarr = explode(':', $tmp[1]);
	$darr = explode('-', $tmp[0]);
	if(count($tarr) != 3 || count($darr) != 3)
		return false;
	$ts = mktime($tarr[0], $tarr[1], $tarr[2], $darr[1], $darr[2], $darr[0]);
	return $ts;
} /* }}} */

function getReadableDuration($secs) { /* {{{ */
	$s = "";
	foreach ( getReadableDurationArray($secs) as $k => $v ) {
		if ( $v ) $s .= $v." ".($v==1? substr($k,0,-1) : $k).", ";
	}

	return substr($s, 0, -2);
} /* }}} */

function getReadableDurationArray($secs) { /* {{{ */
	$units = array(
		getMLText("weeks")   => 7*24*3600,
		getMLText("days")    =>   24*3600,
		getMLText("hours")   =>      3600,
		getMLText("minutes") =>        60,
		getMLText("seconds") =>         1,
	);

	foreach ( $units as &$unit ) {
		$quot  = intval($secs / $unit);
		$secs -= $quot * $unit;
		$unit  = $quot;
	}

	return $units;
} /* }}} */

//
// The original string sanitizer, kept for reference.
//function sanitizeString($string) {
//	$string = str_replace("'",  "&#0039;", $string);
//	$string = str_replace("--", "", $string);
//	$string = str_replace("<",  "&lt;", $string);
//	$string = str_replace(">",  "&gt;", $string);
//	$string = str_replace("/*", "", $string);
//	$string = str_replace("*/", "", $string);
//	$string = str_replace("\"", "&quot;", $string);
//
//	return $string;
//}

/* Deprecated, do not use anymore */
function sanitizeString($string) { /* {{{ */

	$string = (string) $string;
	if (get_magic_quotes_gpc()) {
		$string = stripslashes($string);
	}

	// The following three are against sql injection. They are not
	// needed anymore because strings are quoted propperly when saved into
	// the database.
//	$string = str_replace("\\", "\\\\", $string);
//	$string = str_replace("--", "\-\-", $string);
//	$string = str_replace(";", "\;", $string);
	// Use HTML entities to represent the other characters that have special
	// meaning in SQL. These can be easily converted back to ASCII / UTF-8
	// with a decode function if need be.
	$string = str_replace("&", "&amp;", $string);
	$string = str_replace("%", "&#0037;", $string); // percent
	$string = str_replace("\"", "&quot;", $string); // double quote
	$string = str_replace("/*", "&#0047;&#0042;", $string); // start of comment
	$string = str_replace("*/", "&#0042;&#0047;", $string); // end of comment
	$string = str_replace("<", "&lt;", $string);
	$string = str_replace(">", "&gt;", $string);
	$string = str_replace("=", "&#0061;", $string);
	$string = str_replace(")", "&#0041;", $string);
	$string = str_replace("(", "&#0040;", $string);
	$string = str_replace("'", "&#0039;", $string);
	$string = str_replace("+", "&#0043;", $string);

	return trim($string);
} /* }}} */

/* Deprecated, do not use anymore, but keep it for upgrading
 * older versions
 */
function mydmsDecodeString($string) { /* {{{ */

	$string = (string)$string;

	$string = str_replace("&amp;", "&", $string);
	$string = str_replace("&#0037;", "%", $string); // percent
	$string = str_replace("&quot;", "\"", $string); // double quote
	$string = str_replace("&#0047;&#0042;", "/*", $string); // start of comment
	$string = str_replace("&#0042;&#0047;", "*/", $string); // end of comment
	$string = str_replace("&lt;", "<", $string);
	$string = str_replace("&gt;", ">", $string);
	$string = str_replace("&#0061;", "=", $string);
	$string = str_replace("&#0041;", ")", $string);
	$string = str_replace("&#0040;", "(", $string);
	$string = str_replace("&#0039;", "'", $string);
	$string = str_replace("&#0043;", "+", $string);

	return $string;
} /* }}} */

function createVersionigFile($document) { /* {{{ */
	global $settings, $dms;

	// if directory has been removed recreate it
	if (!file_exists($dms->contentDir . $document->getDir()))
		if (!LetoDMS_Core_File::makeDir($dms->contentDir . $document->getDir())) return false;

	$handle = fopen($dms->contentDir . $document->getDir() .$settings-> _versioningFileName , "wb");

	if (is_bool($handle)&&!$handle) return false;

	$tmp = $document->getName()." (ID ".$document->getID().")\n\n";
	fwrite($handle, $tmp);

	$owner = $document->getOwner();
	$tmp = getMLText("owner")." = ".$owner->getFullName()." <".$owner->getEmail().">\n";
	fwrite($handle, $tmp);

	$tmp = getMLText("creation_date")." = ".getLongReadableDate($document->getDate())."\n";
	fwrite($handle, $tmp);

	$latestContent = $document->getLatestContent();
	$tmp = "\n### ".getMLText("current_version")." ###\n\n";
	fwrite($handle, $tmp);

	$tmp = getMLText("version")." = ".$latestContent->getVersion()."\n";
	fwrite($handle, $tmp);

	$tmp = getMLText("file")." = ".$latestContent->getOriginalFileName()." (".$latestContent->getMimeType().")\n";
	fwrite($handle, $tmp);

	$tmp = getMLText("comment")." = ". $latestContent->getComment()."\n";
	fwrite($handle, $tmp);

	$status = $latestContent->getStatus();
	$tmp = getMLText("status")." = ".getOverallStatusText($status["status"])."\n";
	fwrite($handle, $tmp);

	$reviewStatus = $latestContent->getReviewStatus();
	$tmp = "\n### ".getMLText("reviewers")." ###\n";
	fwrite($handle, $tmp);

	foreach ($reviewStatus as $r) {

		switch ($r["type"]) {
			case 0: // Reviewer is an individual.
				$required = $dms->getUser($r["required"]);
				if (!is_object($required)) $reqName = getMLText("unknown_user")." = ".$r["required"];
				else $reqName =  getMLText("user")." = ".$required->getFullName();
				break;
			case 1: // Reviewer is a group.
				$required = $dms->getGroup($r["required"]);
				if (!is_object($required)) $reqName = getMLText("unknown_group")." = ".$r["required"];
				else $reqName = getMLText("group")." = ".$required->getName();
				break;
		}

		$tmp = "\n".$reqName."\n";
		fwrite($handle, $tmp);

		$tmp = getMLText("status")." = ".getReviewStatusText($r["status"])."\n";
		fwrite($handle, $tmp);

		$tmp = getMLText("comment")." = ". $r["comment"]."\n";
		fwrite($handle, $tmp);

		$tmp = getMLText("last_update")." = ".$r["date"]."\n";
		fwrite($handle, $tmp);

	}


	$approvalStatus = $latestContent->getApprovalStatus();
	$tmp = "\n### ".getMLText("approvers")." ###\n";
	fwrite($handle, $tmp);

	foreach ($approvalStatus as $r) {

		switch ($r["type"]) {
			case 0: // Reviewer is an individual.
				$required = $dms->getUser($r["required"]);
				if (!is_object($required)) $reqName = getMLText("unknown_user")." = ".$r["required"];
				else $reqName =  getMLText("user")." = ".$required->getFullName();
				break;
			case 1: // Reviewer is a group.
				$required = $dms->getGroup($r["required"]);
				if (!is_object($required)) $reqName = getMLText("unknown_group")." = ".$r["required"];
				else $reqName = getMLText("group")." = ".$required->getName();
				break;
		}

		$tmp = "\n".$reqName."\n";
		fwrite($handle, $tmp);

		$tmp = getMLText("status")." = ".getApprovalStatusText($r["status"])."\n";
		fwrite($handle, $tmp);

		$tmp = getMLText("comment")." = ". $r["comment"]."\n";
		fwrite($handle, $tmp);

		$tmp = getMLText("last_update")." = ".$r["date"]."\n";
		fwrite($handle, $tmp);

	}

	$versions = $document->getContent();
	$tmp = "\n### ".getMLText("previous_versions")." ###\n";
	fwrite($handle, $tmp);

	for ($i = count($versions)-2; $i >= 0; $i--){

		$version = $versions[$i];
		$status = $version->getStatus();

		$tmp = "\n".getMLText("version")." = ".$version->getVersion()."\n";
		fwrite($handle, $tmp);

		$tmp = getMLText("file")." = ".$version->getOriginalFileName()." (".$version->getMimeType().")\n";
		fwrite($handle, $tmp);

		$tmp = getMLText("comment")." = ". $version->getComment()."\n";
		fwrite($handle, $tmp);

		$status = $latestContent->getStatus();
		$tmp = getMLText("status")." = ".getOverallStatusText($status["status"])."\n";
		fwrite($handle, $tmp);

	}

	fclose($handle);
	return true;
} /* }}} */

/**
 * Calculate disk space of file or directory
 *
 * original funcion by shalless at rubix dot net dot au (php.net)
 * stat() replace by filesize() to make it work on all platforms.
 *
 * @param string $dir directory or filename
 * @return integer number of bytes
 */
function dskspace($dir) { /* {{{ */
	$space = 0;
	if(is_file($dir)) {
		$space = filesize($dir);
	} elseif (is_dir($dir)) {
		if($dh = opendir($dir)) {
			while (($file = readdir($dh)) !== false)
				if ($file != "." and $file != "..")
					$space += dskspace($dir."/".$file);
			closedir($dh);
		}
	}
	return $space;
} /* }}} */

/**
 * Replacement of PHP's basename function
 *
 * Because basename is locale dependent and strips off non ascii chars
 * from the beginning of filename, it cannot be used in a environment
 * where locale is set to e.g. 'C'
 */
function utf8_basename($path, $suffix='') { /* {{{ */
	$rpos = strrpos($path, DIRECTORY_SEPARATOR);
	if($rpos === false)
		return $path;
	$file = substr($path, $rpos+1);

	$suflen = strlen($suffix);
	if($suflen && (substr($file, -$suflen) == $suffix)){
			$file = substr($file, 0, -$suflen);
	}

	return $file;
} /* }}} */

/**
 * Log a message
 *
 * This function is still here for convienice and because it is
 * used at so many places.
 *
 * @param string $msg
 * @param int $priority can be one of PEAR_LOG_EMERG, PEAR_LOG_ALERT,
 *            PEAR_LOG_CRIT, PEAR_LOG_ERR, PEAR_LOG_WARNING,
 *						PEAR_LOG_NOTICE, PEAR_LOG_INFO, and PEAR_LOG_DEBUG.
 */
function add_log_line($msg="", $priority=null) { /* {{{ */
	global $logger, $user;

	if(!$logger) return;

	if(!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
		$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
	else
		$ip = $_SERVER['REMOTE_ADDR'];
	if($user)
		$logger->log($user->getLogin()." (".$ip.") ".basename($_SERVER["REQUEST_URI"], ".php").$msg, $priority);
	else
		$logger->log("-- (".$ip.") ".basename($_SERVER["REQUEST_URI"], ".php").$msg, $priority);
} /* }}} */

function _add_log_line($msg="") { /* {{{ */
	global $settings,$user;

	if ($settings->_logFileEnable!=TRUE) return;

	if ($settings->_logFileRotation=="h") $logname=date("YmdH", time());
	else if ($settings->_logFileRotation=="d") $logname=date("Ymd", time());
	else $logname=date("Ym", time());

	if($h = fopen($settings->_contentDir.$logname.".log", "a")) {
		fwrite($h,date("Y/m/d H:i", time())." ".$user->getLogin()." (".$_SERVER['REMOTE_ADDR'].") ".basename($_SERVER["REQUEST_URI"], ".php").$msg."\n");
		fclose($h);
	}
} /* }}} */

	function getFolderPathHTML($folder, $tagAll=false, $document=null) { /* {{{ */
		$path = $folder->getPath();
		$txtpath = "";
		for ($i = 0; $i < count($path); $i++) {
			if ($i +1 < count($path)) {
				$txtpath .= "<a href=\"../out/out.ViewFolder.php?folderid=".$path[$i]->getID()."&showtree=".showtree()."\">".
					htmlspecialchars($path[$i]->getName())."</a> / ";
			}
			else {
				$txtpath .= ($tagAll ? "<a href=\"../out/out.ViewFolder.php?folderid=".$path[$i]->getID()."&showtree=".showtree()."\">".
										 htmlspecialchars($path[$i]->getName())."</a>" : htmlspecialchars($path[$i]->getName()));
			}
		}
		if($document)
			$txtpath .= " / <a href=\"../out/out.ViewDocument.php?documentid=".$document->getId()."\">".htmlspecialchars($document->getName())."</a>";

		return $txtpath;
	} /* }}} */

function showtree() { /* {{{ */
	global $settings;

	if (isset($_GET["showtree"])) return intval($_GET["showtree"]);
	else if ($settings->_expandFolderTree==0) return 0;

	return 1;
} /* }}} */

/**
 * Create a unique key which is used for form validation to prevent
 * CSRF attacks. The key is added to a any form that has to be secured
 * as a hidden field. Once the form is submitted the key is compared
 * to the current key in the session and the request is only executed
 * if both are equal. The key is derived from the session id, a configurable
 * encryption key and form identifierer.
 *
 * @param string $formid individual form identifier
 * @return string session key
 */
function createFormKey($formid='') { /* {{{ */
	global $settings, $session;

	if($id = $session->getId()) {
		return md5($id.$settings->_encryptionKey.$formid);
	} else {
		return false;
	}
} /* }}} */

/**
 * Create a hidden field with the name 'formtoken' and set its value
 * to the key returned by createFormKey()
 *
 * @param string $formid individual form identifier
 * @return string input field for html formular
 */
function createHiddenFieldWithKey($formid='') { /* {{{ */
	return '<input type="hidden" name="formtoken" value="'.createFormKey($formid).'" />';
} /* }}} */

/**
 * Check if the form key in the POST or GET request variable 'formtoken'
 * has the value of key returned by createFormKey(). Request to modify
 * data in the DMS should always use POST because it is harder to run
 * CSRF attacks using POST than GET.
 *
 * @param string $formid individual form identifier
 * @param string $method defines if the form data is pass via GET or
 * POST (default)
 * @return boolean true if key matches otherwise false
 */
function checkFormKey($formid='', $method='POST') { /* {{{ */
	switch($method) {
		case 'GET':
			if(isset($_GET['formtoken']) && $_GET['formtoken'] == createFormKey($formid))
				return true;
			break;
		default:
			if(isset($_POST['formtoken']) && $_POST['formtoken'] == createFormKey($formid))
				return true;
	}

	return false;
} /* }}} */

/**
 * Check disk usage of currently logged in user
 *
 * @return boolean/integer true if no quota is set, number of bytes until
 *         quota is reached. Negative values indicate a disk usage above quota.
 */
function checkQuota($user) { /* {{{ */
	global $settings, $dms;

	/* check if quota is turn off system wide */
	if($settings->_quota == 0)
		return true;

	$quota = 0;
	$uquota = $user->getQuota();
	if($uquota > 0)
		$quota = $uquota;
	elseif($settings->_quota > 0) {
		$quota = $settings->_quota;
	}

	if($quota == 0)
		return true;

	return ($quota - $user->getUsedDiskSpace());
} /* }}} */

function encryptData($key, $value){
  $text = $value;
  $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
  $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
  $crypttext = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $text, MCRYPT_MODE_ECB, $iv);
  return $crypttext;
}

function decryptData($key, $value){
  $crypttext = $value;
  $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
  $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
  $decrypttext = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $crypttext, MCRYPT_MODE_ECB, $iv);
  return trim($decrypttext);
}

/**
 * Return file extension for a give mimetype
 *
 * @param string $mimetype Mime-Type
 * @return string file extension including leading dot
 */
function get_extension($mimetype) { /* {{{ */
	if(empty($mimetype)) return false;
	switch($mimetype) {
		case 'image/bmp': return '.bmp';
		case 'image/cis-cod': return '.cod';
		case 'image/gif': return '.gif';
		case 'image/ief': return '.ief';
		case 'image/jpeg': return '.jpg';
		case 'image/pipeg': return '.jfif';
		case 'image/tiff': return '.tif';
		case 'image/x-cmu-raster': return '.ras';
		case 'image/x-cmx': return '.cmx';
		case 'image/x-icon': return '.ico';
		case 'image/x-portable-anymap': return '.pnm';
		case 'image/x-portable-bitmap': return '.pbm';
		case 'image/x-portable-graymap': return '.pgm';
		case 'image/x-portable-pixmap': return '.ppm';
		case 'image/x-rgb': return '.rgb';
		case 'image/x-xbitmap': return '.xbm';
		case 'image/x-xpixmap': return '.xpm';
		case 'image/x-xwindowdump': return '.xwd';
		case 'image/png': return '.png';
		case 'image/x-jps': return '.jps';
		case 'image/x-freehand': return '.fh';
		case 'image/svg+xml': return '.svg';
		case 'application/zip': return '.zip';
		case 'application/x-rar': return '.rar';
		case 'application/pdf': return '.pdf';
		case 'application/postscript': return '.ps';
		case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document': return '.docx';
		case 'application/vnd.openxmlformats-officedocument.presentationml.presentation': return '.pptx';
		case 'text/plain': return '.txt';
		case 'text/csv': return '.csv';
		default: return false;
	}
} /* }}} */

/**
 * Adds a missing front slash to a string
 *
 * This function is used for making sure a directory name has a
 * trailing directory separator
 */
function addDirSep($str) { /* {{{ */
	if(trim($str) == '')
		return '';
	if(substr(trim($str), -1, 1) != DIRECTORY_SEPARATOR)
		return trim($str).DIRECTORY_SEPARATOR;
	else
		return trim($str);
} /* }}} */

/**
 * Send a file from disk to the browser
 *
 * This function uses either readfile() or the xѕendfile apache module if
 * it is installed.
 *
 * @param string $filename
 */
function sendFile($filename) { /* {{{ */
	if(function_exists('apache_get_modules') && in_array('mod_xsendfile',apache_get_modules())) {
		header("X-Sendfile: ".$filename);
	} else {
		/* Make sure output buffering is off */
		if (ob_get_level()) {
			ob_end_clean();
		}
		readfile($filename);
	}
} /* }}} */
?>
