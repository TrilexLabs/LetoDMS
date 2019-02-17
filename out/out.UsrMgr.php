<?php
//    MyDMS. Document Management System
//    Copyright (C) 2002-2005 Markus Westphal
//    Copyright (C) 2006-2008 Malcolm Cowe
//    Copyright (C) 2010 Matteo Lucarelli
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
include("../inc/inc.Language.php");
include("../inc/inc.Init.php");
include("../inc/inc.Extension.php");
include("../inc/inc.DBInit.php");
include("../inc/inc.ClassUI.php");
include("../inc/inc.Authentication.php");

$tmp = explode('.', basename($_SERVER['SCRIPT_FILENAME']));
$view = UI::factory($theme, $tmp[1], array('dms'=>$dms, 'user'=>$user));
if (!$user->isAdmin()) {
	UI::exitError(getMLText("admin_tools"),getMLText("access_denied"));
}

$users = $dms->getAllUsers($settings->_sortUsersInList);
if (is_bool($users)) {
	UI::exitError(getMLText("admin_tools"),getMLText("internal_error"));
}

$groups = $dms->getAllGroups();
if (is_bool($groups)) {
	UI::exitError(getMLText("admin_tools"),getMLText("internal_error"));
}

if(isset($_GET['userid']) && $_GET['userid']) {
	$seluser = $dms->getUser($_GET['userid']);
} else {
	$seluser = null;
}

if($view) {
	$view->setParam('seluser', $seluser);
	$view->setParam('allusers', $users);
	$view->setParam('allgroups', $groups);
	$view->setParam('passwordstrength', $settings->_passwordStrength);
	$view->setParam('passwordexpiration', $settings->_passwordExpiration);
	$view->setParam('httproot', $settings->_httpRoot);
	$view->setParam('enableuserimage', $settings->_enableUserImage);
	$view->setParam('undeluserids', explode(',', $settings->_undelUserIds));
	$view->setParam('workflowmode', $settings->_workflowMode);
	$view->setParam('quota', $settings->_quota);
	$view->setParam('strictformcheck', $settings->_strictFormCheck);
	$view->setParam('enableemail', $settings->_enableEmail);
	$view($_GET);
}
