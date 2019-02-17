<?php
//    MyDMS. Document Management System
//    Copyright (C) 2002-2005 Markus Westphal
//    Copyright (C) 2006-2008 Malcolm Cowe
//    Copyright (C) 2010-2011 Matteo Lucarelli
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

if(isset($_GET['repair']) && $_GET['repair'] == 1) {
	$repair = 1;
} else {
	$repair = 0;
}

if(isset($_GET['unlink']) && $_GET['unlink'] == 1) {
	$unlink = 1;
} else {
	$unlink = 0;
}

if(isset($_GET['setfilesize']) && $_GET['setfilesize'] == 1) {
	$setfilesize = 1;
} else {
	$setfilesize = 0;
}

if(isset($_GET['setchecksum']) && $_GET['setchecksum'] == 1) {
	$setchecksum = 1;
} else {
	$setchecksum = 0;
}

$folder = $dms->getFolder($settings->_rootFolderID);
$unlinkedversions = $dms->getUnlinkedDocumentContent();
$unlinkedfolders = $dms->checkFolders();
$unlinkeddocuments = $dms->checkDocuments();
$nofilesizeversions = $dms->getNoFileSizeDocumentContent();
$nochecksumversions = $dms->getNoChecksumDocumentContent();
$duplicateversions = $dms->getDuplicateDocumentContent();
foreach(array('review', 'approval') as $process) {
	foreach(array('user', 'group') as $ug) {
		$processwithoutusergroup[$process][$ug] = $dms->getProcessWithoutUserGroup($process, $ug);
	}
}
$rootfolder = $dms->getFolder($settings->_rootFolderID);

if($view) {
	$view->setParam('folder', $folder);
	$view->setParam('unlinkedcontent', $unlinkedversions);
	$view->setParam('unlinkedfolders', $unlinkedfolders);
	$view->setParam('unlinkeddocuments', $unlinkeddocuments);
	$view->setParam('nofilesizeversions', $nofilesizeversions);
	$view->setParam('nochecksumversions', $nochecksumversions);
	$view->setParam('duplicateversions', $duplicateversions);
	$view->setParam('processwithoutusergroup', $processwithoutusergroup);
	$view->setParam('unlink', $unlink);
	$view->setParam('setfilesize', $setfilesize);
	$view->setParam('setchecksum', $setchecksum);
	$view->setParam('repair', $repair);
	$view->setParam('rootfolder', $rootfolder);
	$view($_GET);
	exit;
}

?>
