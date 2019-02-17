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
include("../inc/inc.Language.php");
include("../inc/inc.Init.php");
include("../inc/inc.Extension.php");
include("../inc/inc.DBInit.php");
include("../inc/inc.ClassUI.php");
include("../inc/inc.ClassController.php");
include("../inc/inc.Authentication.php");

$tmp = explode('.', basename($_SERVER['SCRIPT_FILENAME']));
$controller = Controller::factory($tmp[1], array('dms'=>$dms, 'user'=>$user));

/* Check if the form data comes from a trusted request */
if(!checkFormKey('removefolder')) {
	UI::exitError(getMLText("folder_title", array("foldername" => getMLText("invalid_request_token"))),getMLText("invalid_request_token"));
}

if (!isset($_POST["folderid"]) || !is_numeric($_POST["folderid"]) || intval($_POST["folderid"])<1) {
	UI::exitError(getMLText("folder_title", array("foldername" => getMLText("invalid_folder_id"))),getMLText("invalid_folder_id"));
}
$folderid = $_POST["folderid"];
$folder = $dms->getFolder($folderid);

if (!is_object($folder)) {
	UI::exitError(getMLText("folder_title", array("foldername" => getMLText("invalid_folder_id"))),getMLText("invalid_folder_id"));
}

if ($folderid == $settings->_rootFolderID || !$folder->getParent()) {
	UI::exitError(getMLText("folder_title", array("foldername" => $folder->getName())),getMLText("cannot_rm_root"));
}

if ($folder->getAccessMode($user, 'removeFolder') < M_ALL) {
	UI::exitError(getMLText("folder_title", array("foldername" => $folder->getName())),getMLText("access_denied"));
}

if($settings->_enableFullSearch) {
	$index = $indexconf['Indexer']::open($settings->_luceneDir);
	$indexconf['Indexer']::init($settings->_stopWordsFile);
} else {
	$index = null;
}

function removePreviews($arr, $document) {
	$previewer = $arr[0];

	$previewer->deleteDocumentPreviews($document);
	return true;
}
require_once("LetoDMS/Preview.php");
$previewer = new LetoDMS_Preview_Previewer($settings->_cacheDir);
$dms->addCallback('onPreRemoveDocument', 'removePreviews', array($previewer));

/* Get the notify list before removing the folder
 * Also inform the users/groups of the parent folder
 */
$parent=$folder->getParent();
$foldername = $folder->getName();
$fnl =	$folder->getNotifyList();
$pnl =	$parent->getNotifyList();
$nl = array(
	'users'=>array_unique(array_merge($fnl['users'], $pnl['users']), SORT_REGULAR),
	'groups'=>array_unique(array_merge($fnl['groups'], $pnl['groups']), SORT_REGULAR)
);

$controller->setParam('folder', $folder);
$controller->setParam('index', $index);
$controller->setParam('indexconf', $indexconf);
if(!$controller->run()) {
	UI::exitError(getMLText("folder_title", array("foldername" => htmlspecialchars($foldername))),getMLText("error_remove_folder"));
}

if ($notifier) {
	$subject = "folder_deleted_email_subject";
	$message = "folder_deleted_email_body";
	$params = array();
	$params['name'] = $foldername;
	$params['folder_path'] = $parent->getFolderPathPlain();
	$params['username'] = $user->getFullName();
	$params['sitename'] = $settings->_siteName;
	$params['http_root'] = $settings->_httpRoot;
	$params['url'] = "http".((isset($_SERVER['HTTPS']) && (strcmp($_SERVER['HTTPS'],'off')!=0)) ? "s" : "")."://".$_SERVER['HTTP_HOST'].$settings->_httpRoot."out/out.ViewFolder.php?folderid=".$parent->getID();
	$notifier->toList($user, $nl["users"], $subject, $message, $params);
	foreach ($nl["groups"] as $grp) {
		$notifier->toGroup($user, $grp, $subject, $message, $params);
	}
}

add_log_line("?folderid=".$folderid."&name=".$foldername);

header("Location:../out/out.ViewFolder.php?folderid=".$parent->getID()."&showtree=".$_POST["showtree"]);

?>
