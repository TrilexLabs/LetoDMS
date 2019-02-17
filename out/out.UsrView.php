<?php
//    MyDMS. Document Management System
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

if ($user->isGuest()) {
	UI::exitError(getMLText("my_account"),getMLText("access_denied"));
}

if (!$settings->_enableUsersView) {
	UI::exitError(getMLText("my_account"),getMLText("access_denied"));
}

$users = $dms->getAllUsers();
if (is_bool($users)) {
	UI::exitError(getMLText("my_account"),getMLText("internal_error"));
}

if($view) {
	$view->setParam('allusers', $users);
	$view->setParam('enableuserimage', $settings->_enableUserImage);
	$view->setParam('httproot', $settings->_httpRoot);
	$view($_GET);
	exit;
}

?>
