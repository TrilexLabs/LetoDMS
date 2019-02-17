<?php
//    LetoDMS. Document Management System
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
include("../inc/inc.DBInit.php");
include("../inc/inc.ClassUI.php");
include("../inc/inc.Authentication.php");

/* Check if the form data comes from a trusted request */
if(!checkFormKey('clearcache')) {
	UI::exitError(getMLText("admin_tools"),getMLText("invalid_request_token"));
}

/* Make sure the cacheDir exists and isn't manipulated for executing
 * commands when system() is being called.
 */
if(!is_dir($settings->_cacheDir)) {
	UI::exitError(getMLText("admin_tools"),getMLText("error_cleared_cache"));
}

if(!empty($_POST['preview'])) {
	$cmd = 'rm -rf '.$settings->_cacheDir.'/[1-9]*';
	$ret = null;
	system($cmd, $ret);
}

if(!empty($_POST['js'])) {
	$cmd = 'rm -rf '.$settings->_cacheDir.'/js/*';
	$ret = null;
	system($cmd, $ret);
}

if($ret)
	$session->setSplashMsg(array('type'=>'error', 'msg'=>getMLText('error_cleared_cache')));
else
	$session->setSplashMsg(array('type'=>'success', 'msg'=>getMLText('splash_cleared_cache')));

add_log_line("");

header("Location:../out/out.AdminTools.php");

