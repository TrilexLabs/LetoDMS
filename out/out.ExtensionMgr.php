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

include("../inc/inc.Version.php");
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

$reposurl = $settings->_repositoryUrl;

$v = new LetoDMS_Version;
$extmgr = new LetoDMS_Extension_Mgr($settings->_rootDir."/ext", $settings->_cacheDir, $reposurl);
if(isset($_GET['currenttab']))
	$currenttab = $_GET['currenttab'];
else
	$currenttab = 'installed';
if(isset($_GET['extensionname']))
	$extname = $_GET['extensionname'];
else
	$extname = '';

if($view) {
	$view->setParam('httproot', $settings->_httpRoot);
	$view->setParam('extdir', $settings->_rootDir."/ext");
	$view->setParam('version', $v);
	$view->setParam('extmgr', $extmgr);
	$view->setParam('currenttab', $currenttab);
	$view->setParam('extname', $extname);
	$view->setParam('reposurl', $reposurl);
	$view($_GET);
	exit;
}

?>
