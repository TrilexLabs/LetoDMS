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
include("../inc/inc.Utils.php");
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
$rootfolder = $dms->getFolder($settings->_rootFolderID);

if(isset($_GET['skip']))
	$skip = $_GET['skip'];
else
	$skip = array();

$document = null;
$content = null;
if(isset($_GET['documentid']) && $_GET['documentid'] && is_numeric($_GET['documentid'])) {
	if($document = $dms->getDocument($_GET["documentid"])) {
		if(isset($_GET['version']) && $_GET['version'] && is_numeric($_GET['version'])) {
			$content = $document->getContentByVersion($_GET['version']);
		}
	}
}

if($view) {
	$view->setParam('dms', $dms);
	$view->setParam('user', $user);
	$view->setParam('showtree', showtree());
	$view->setParam('fromdate', isset($_GET['fromdate']) ? $_GET['fromdate'] : '');
	$view->setParam('todate', isset($_GET['todate']) ? $_GET['todate'] : '');
	$view->setParam('skip', $skip);
	$view->setParam('document', $document);
	$view->setParam('version', $content);
	$view->setParam('cachedir', $settings->_cacheDir);
	$view->setParam('previewWidthList', $settings->_previewWidthList);
	$view->setParam('previewWidthDetail', $settings->_previewWidthDetail);
	$view->setParam('timeout', $settings->_cmdTimeout);
	$view($_GET);
	exit;
}

?>
