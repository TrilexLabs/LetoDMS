<?php
//    MyDMS. Document Management System
//    Copyright (C) 2002-2005 Markus Westphal
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
include("../inc/inc.Language.php");
include("../inc/inc.Init.php");
include("../inc/inc.Extension.php");
include("../inc/inc.DBInit.php");
include("../inc/inc.ClassUI.php");
include("../inc/inc.Authentication.php");

if(isset($_GET["form"]))
	$form = preg_replace('/[^A-Za-z0-9_]+/', '', $_GET["form"]);
else
	$form = '';

if(substr($settings->_dropFolderDir, -1, 1) == DIRECTORY_SEPARATOR)
	$dropfolderdir = substr($settings->_dropFolderDir, 0, -1);
else
	$dropfolderdir = $settings->_dropFolderDir;

if(isset($_GET['showfolders']) && $_GET['showfolders'])
	$showfolders = true;
else
	$showfolders = false;

if (isset($_GET["folderid"]) && is_numeric($_GET["folderid"])) {
	$folderid = intval($_GET["folderid"]);
	$folder = $dms->getFolder($folderid);
} else {
	$folder = null;
}

$tmp = explode('.', basename($_SERVER['SCRIPT_FILENAME']));
$view = UI::factory($theme, $tmp[1], array('dms'=>$dms, 'user'=>$user));
if($view) {
	$view->setParam('dropfolderdir', $dropfolderdir);
	$view->setParam('dropfolderfile', isset($_GET["dropfolderfile"]) ? $_GET["dropfolderfile"] : '');
	$view->setParam('form', $form);
	$view->setParam('cachedir', $settings->_cacheDir);
	$view->setParam('previewWidthMenuList', $settings->_previewWidthMenuList);
	$view->setParam('previewWidthList', $settings->_previewWidthDropFolderList);
	$view->setParam('timeout', $settings->_cmdTimeout);
	$view->setParam('showfolders', $showfolders);
	$view->setParam('folder', $folder);
	$view($_GET);
	exit;
}

?>
