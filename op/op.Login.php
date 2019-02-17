<?php
//    MyDMS. Document Management System
//    Copyright (C) 2002-2005  Markus Westphal
//    Copyright (C) 2006-2008 Malcolm Cowe
//    Copyright (C) 2010-2016 Uwe Steinmann
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

include("../inc/inc.Settings.php");
include("../inc/inc.LogInit.php");
include("../inc/inc.Utils.php");
include("../inc/inc.Language.php");
include("../inc/inc.Init.php");
include("../inc/inc.Extension.php");
include("../inc/inc.ClassSession.php");
include("../inc/inc.DBInit.php");
include("../inc/inc.ClassUI.php");
include("../inc/inc.ClassController.php");

include $settings->_rootDir . "languages/" . $settings->_language . "/lang.inc";

function _printMessage($heading, $message) { /* {{{ */
	global $session, $dms, $theme;

	header("Location:../out/out.Login.php?msg=".urlencode($message));
	exit;

	UI::exitError($heading, $message, true);
	return;
} /* }}} */

$tmp = explode('.', basename($_SERVER['SCRIPT_FILENAME']));
$controller = Controller::factory($tmp[1], array('dms'=>$dms));

if (isset($_REQUEST["sesstheme"]) && strlen($_REQUEST["sesstheme"])>0 && is_numeric(array_search($_REQUEST["sesstheme"],UI::getStyles())) ) {
	$theme = $_REQUEST["sesstheme"];
}

if (isset($_REQUEST["login"])) {
	$login = $_REQUEST["login"];
	$login = str_replace("*", "", $login);
}

if (!isset($login) || strlen($login)==0) {
	_printMessage(getMLText("login_error_title"),	getMLText("login_not_given")."\n");
	exit;
}

$pwd = '';
if(isset($_POST['pwd'])) {
	$pwd = (string) $_POST["pwd"];
	if (get_magic_quotes_gpc()) {
		$pwd = stripslashes($pwd);
	}
}

/* Initialy set $user to false. It will contain a valid user record
 * if the user is a guest user or authentication will succeed.
 */
$user = false;

/* The password may only be empty if the guest user tries to log in.
 * There is just one guest account with id $settings->_guestID which
 * is allowed to log in without a password. All other guest accounts
 * are treated like regular logins
 */
if($settings->_enableGuestLogin && (int) $settings->_guestID) {
	$guestUser = $dms->getUser((int) $settings->_guestID);
	if(($login != $guestUser->getLogin())) {
		if ((!isset($pwd) || strlen($pwd)==0)) {
			_printMessage(getMLText("login_error_title"),	getMLText("login_error_text")."\n");
			exit;
		}
	} else {
		$user = $guestUser;
	}
}

if(!$user && isset($GLOBALS['LetoDMS_HOOKS']['authentication'])) {
	foreach($GLOBALS['LetoDMS_HOOKS']['authentication'] as $authObj) {
		if(!$user && method_exists($authObj, 'authenticate')) {
			$user = $authObj->authenticate($dms, $settings, $login, $pwd);
		}
	}
}

/* Authenticate against LDAP server {{{ */
if (!$user && isset($settings->_ldapHost) && strlen($settings->_ldapHost)>0) {
	require_once("../inc/inc.ClassLdapAuthentication.php");
	$authobj = new LetoDMS_LdapAuthentication($dms, $settings);
	$user = $authobj->authenticate($login, $pwd);
} /* }}} */

/* Authenticate against LetoDMS database {{{ */
if(!$user) {
	require_once("../inc/inc.ClassDbAuthentication.php");
	$authobj = new LetoDMS_DbAuthentication($dms, $settings);
	$user = $authobj->authenticate($login, $pwd);
} /* }}} */

if(!$user) {
	_printMessage(getMLText("login_error_title"),	getMLText("login_error_text"));
	exit;
}

$userid = $user->getID();
if (($userid == $settings->_guestID) && (!$settings->_enableGuestLogin)) {
	_printMessage(getMLText("login_error_title"),	getMLText("guest_login_disabled"));
	exit;
}

// Check if account is disabled
if($user->isDisabled()) {
	_printMessage(getMLText("login_disabled_title"), getMLText("login_disabled_text"));
	exit;
}

// control admin IP address if required
if ($user->isAdmin() && ($_SERVER['REMOTE_ADDR'] != $settings->_adminIP ) && ( $settings->_adminIP != "") ){
	_printMessage(getMLText("login_error_title"),	getMLText("invalid_user_id"));
	exit;
}

/* Clear login failures if login was successful */
$user->clearLoginFailures();

// Capture the user's language and theme settings.
if (isset($_REQUEST["lang"]) && strlen($_REQUEST["lang"])>0 && is_numeric(array_search($_REQUEST["lang"],getLanguages())) ) {
	$lang = $_REQUEST["lang"];
	$user->setLanguage($lang);
}
else {
	$lang = $user->getLanguage();
	if (strlen($lang)==0) {
		$lang = $settings->_language;
		$user->setLanguage($lang);
	}
}
if (isset($_REQUEST["sesstheme"]) && strlen($_REQUEST["sesstheme"])>0 && is_numeric(array_search($_REQUEST["sesstheme"],UI::getStyles())) ) {
	$sesstheme = $_REQUEST["sesstheme"];
	$user->setTheme($sesstheme);
}
else {
	$sesstheme = $user->getTheme();
	if (strlen($sesstheme)==0) {
		$sesstheme = $settings->_theme;
//		$user->setTheme($sesstheme);
	}
}

$session = new LetoDMS_Session($db);

// Delete all sessions that are more than 1 week or the configured
// cookie lifetime old. Probably not the most
// reliable place to put this check -- move to inc.Authentication.php?
if($settings->_cookieLifetime)
	$lifetime = intval($settings->_cookieLifetime);
else
	$lifetime = 7*86400;
if(!$session->deleteByTime($lifetime)) {
	_printMessage(getMLText("login_error_title"), getMLText("error_occured").": ".$db->getErrorMsg());
	exit;
}

if (isset($_COOKIE["mydms_session"])) {
	/* This part will never be reached unless the session cookie is kept,
	 * but op.Logout.php deletes it. Keeping a session could be a good idea
	 * for retaining the clipboard data, but the user id in the session should
	 * be set to 0 which is not possible due to foreign key constraints.
	 * So for now op.Logout.php will delete the cookie as always
	 */
	/* Load session */
	$dms_session = $_COOKIE["mydms_session"];
	if(!$resArr = $session->load($dms_session)) {
		/* Turn off http only cookies if jumploader is enabled */
		setcookie("mydms_session", $dms_session, time()-3600, $settings->_httpRoot, null, null, !$settings->_enableLargeFileUpload); //delete cookie
		header("Location: " . $settings->_httpRoot . "out/out.Login.php?referuri=".$refer);
		exit;
	} else {
		$session->updateAccess($dms_session);
		$session->setUser($userid);
	}
} else {
	// Create new session in database
	if(!$id = $session->create(array('userid'=>$userid, 'theme'=>$sesstheme, 'lang'=>$lang))) {
		_printMessage(getMLText("login_error_title"), getMLText("error_occured").": ".$db->getErrorMsg());
		exit;
	}

	// Set the session cookie.
	if($settings->_cookieLifetime)
		$lifetime = time() + intval($settings->_cookieLifetime);
	else
		$lifetime = 0;
	setcookie("mydms_session", $id, $lifetime, $settings->_httpRoot, null, null, !$settings->_enableLargeFileUpload);
}

// TODO: by the PHP manual: The superglobals $_GET and $_REQUEST are already decoded.
// Using urldecode() on an element in $_GET or $_REQUEST could have unexpected and dangerous results.

if (isset($_POST["referuri"]) && strlen($_POST["referuri"])>0) {
	$referuri = trim(urldecode($_POST["referuri"]));
}
else if (isset($_GET["referuri"]) && strlen($_GET["referuri"])>0) {
	$referuri = trim(urldecode($_GET["referuri"]));
}

add_log_line();

$controller->setParam('user', $user);
$controller->setParam('session', $session);
$controller->run();

if (isset($referuri) && strlen($referuri)>0) {
//	header("Location: http".((isset($_SERVER['HTTPS']) && (strcmp($_SERVER['HTTPS'],'off')!=0)) ? "s" : "")."://".$_SERVER['HTTP_HOST'] . $referuri);
	header("Location: " . $referuri);
}
else {
	header("Location: ".$settings->_httpRoot.(isset($settings->_siteDefaultPage) && strlen($settings->_siteDefaultPage)>0 ? $settings->_siteDefaultPage : "out/out.ViewFolder.php?folderid=".($user->getHomeFolder() ? $user->getHomeFolder() : $settings->_rootFolderID)));
}

?>
