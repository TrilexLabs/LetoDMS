<?php
//    LetoDMS. Document Management System
//    Copyright (C) 2013 Uwe Steinmann
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
include("../inc/inc.Language.php");
include("../inc/inc.Init.php");
include("../inc/inc.LogInit.php");
include("../inc/inc.DBInit.php");
include("../inc/inc.Extension.php");
include("../inc/inc.ClassUI.php");
include("../inc/inc.ClassController.php");
include("../inc/inc.Authentication.php");

$tmp = explode('.', basename($_SERVER['SCRIPT_FILENAME']));
$controller = Controller::factory($tmp[1], array('dms'=>$dms, 'user'=>$user));
if (!$user->isAdmin()) {
	UI::exitError(getMLText("admin_tools"),getMLText("access_denied"));
}

/* Check if the form data comes for a trusted request */
if(!checkFormKey('extensionmgr')) {
	UI::exitError(getMLText("admin_tools"),getMLText("invalid_request_token"));
}

if (isset($_POST["action"])) $action=$_POST["action"];
else $action=NULL;

if (isset($_POST["currenttab"])) $currenttab=$_POST["currenttab"];
else $currenttab=NULL;

// add new attribute definition ---------------------------------------------
if ($action == "download") {
	if (!isset($_POST["extname"])) {
		UI::exitError(getMLText("admin_tools"),getMLText("unknown_id"));
	}
	$extname = trim($_POST["extname"]);
	if (!file_exists($settings->_rootDir.'/ext/'.$extname) ) {
		UI::exitError(getMLText("admin_tools"),getMLText("missing_extension"));
	}
//	$extMgr = new LetoDMS_Extension_Mgr($settings->_rootDir."/ext", $settings->_cacheDir);
	$controller->setParam('extmgr', $extMgr);
	$controller->setParam('extname', $extname);
	if (!$controller($_POST)) {
		echo json_encode(array('success'=>false, 'error'=>'Could not download extension'));
	}
	add_log_line();
} /* }}} */
elseif ($action == "refresh") { /* {{{ */
//	$extMgr = new LetoDMS_Extension_Mgr($settings->_rootDir."/ext", $settings->_cacheDir);
	$extMgr->createExtensionConf();
	$controller->setParam('extmgr', $extMgr);
	if (!$controller($_POST)) {
		UI::exitError(getMLText("admin_tools"),getMLText("error_occured"));
	}
	$session->setSplashMsg(array('type'=>'success', 'msg'=>getMLText('splash_extension_refresh')));
	add_log_line();
	header("Location:../out/out.ExtensionMgr.php?currenttab=".$currenttab);
} /* }}} */
elseif ($action == "upload") { /* {{{ */
	if(!$extMgr->isWritableExtDir()) {
		UI::exitError(getMLText("admin_tools"),getMLText("extension_mgr_no_upload"));
	}
	if($_FILES['userfile']['error']) {
		UI::exitError(getMLText("admin_tools"),getMLText("error_occured"));
	}
	if($_FILES['userfile']['type'] != 'application/zip') {
		UI::exitError(getMLText("admin_tools"),getMLText("error_occured"));
	}
//	$extMgr = new LetoDMS_Extension_Mgr($settings->_rootDir."/ext", $settings->_cacheDir);
	$controller->setParam('extmgr', $extMgr);
	$controller->setParam('file', $_FILES['userfile']['tmp_name']);
	if (!$controller($_POST)) {
		UI::exitError(getMLText("admin_tools"),getMLText("error_occured"));
	}
	$session->setSplashMsg(array('type'=>'success', 'msg'=>getMLText('splash_extension_import')));
	add_log_line();
	header("Location:../out/out.ExtensionMgr.php?currenttab=".$currenttab);
} /* }}} */
elseif ($action == "import") { /* {{{ */
	if(!$_POST['url']) {
		UI::exitError(getMLText("admin_tools"),getMLText("error_occured"));
	}
	$reposurl = $settings->_repositoryUrl;
	$content = file_get_contents($reposurl."/".$_POST['url']);
	$file = tempnam(sys_get_temp_dir(), '');
	file_put_contents($file, $content);

//	$extMgr = new LetoDMS_Extension_Mgr($settings->_rootDir."/ext", $settings->_cacheDir);
	$controller->setParam('extmgr', $extMgr);
	$controller->setParam('file', $file);
	$_POST['action'] = 'upload';
	if (!$controller($_POST)) {
		unlink($file);
		UI::exitError(getMLText("admin_tools"),getMLText("error_occured"));
	}
	unlink($file);
	$session->setSplashMsg(array('type'=>'success', 'msg'=>getMLText('splash_extension_upload')));
	add_log_line();
	header("Location:../out/out.ExtensionMgr.php?currenttab=".$currenttab);
} /* }}} */
elseif ($action == "getlist") { /* {{{ */
	$v = new LetoDMS_Version();
	$controller->setParam('extmgr', $extMgr);
	$controller->setParam('forceupdate', (isset($_POST['forceupdate']) && $_POST['forceupdate']) ? true : false);
	$controller->setParam('version', $v->version());
	if (!$controller($_POST)) {
		$session->setSplashMsg(array('type'=>'error', 'msg'=>getMLText('error_extension_getlist').$controller->getErrorMsg(), 'timeout'=>5000));
	} else {
		$session->setSplashMsg(array('type'=>'success', 'msg'=>getMLText('splash_extension_getlist')));
	}
	add_log_line();
	header("Location:../out/out.ExtensionMgr.php?currenttab=".$currenttab);
} /* }}} */


?>
