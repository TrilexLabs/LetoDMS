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
include("../inc/inc.Authentication.php");

if (!isset($_GET["folderid"]) || !is_numeric($_GET["folderid"]) || intval($_GET["folderid"])<1) {
	UI::exitError(getMLText("folder_title", array("foldername" => getMLText("invalid_folder_id"))),getMLText("invalid_folder_id"));
}
$folderid = $_GET["folderid"];
$folder = $dms->getFolder($folderid);

if (!is_object($folder)) {
	UI::exitError(getMLText("folder_title", array("foldername" => getMLText("invalid_folder_id"))),getMLText("invalid_folder_id"));
}

if ($folderid == $settings->_rootFolderID || !$folder->getParent()) {
	UI::exitError(getMLText("folder_title", array("foldername" => $folder->getName())),getMLText("cannot_move_root"));
}

if (!isset($_GET["targetid"]) || !is_numeric($_GET["targetid"]) || intval($_GET["targetid"])<1) {
	UI::exitError(getMLText("folder_title", array("foldername" => getMLText("invalid_folder_id"))),getMLText("invalid_folder_id"));
}

$targetid = $_GET["targetid"];
$targetFolder = $dms->getFolder($targetid);

if (!is_object($targetFolder)) {
	UI::exitError(getMLText("folder_title", array("foldername" => getMLText("invalid_folder_id"))),getMLText("invalid_folder_id"));
}

if($folder->isSubFolder($targetFolder)) {
	UI::exitError(getMLText("folder_title", array("foldername" => $folder->getName())),getMLText("invalid_target_folder"));
}

if ($folder->getAccessMode($user, 'moveFolder') < M_READWRITE || $targetFolder->getAccessMode($user, 'moveFolder') < M_READWRITE) {
	UI::exitError(getMLText("folder_title", array("foldername" => $folder->getName())),getMLText("access_denied"));
}

$oldFolder = $folder->getParent();
if ($folder->setParent($targetFolder)) {
	// Send notification to subscribers.
	if($notifier) {
		$nl1 = $oldFolder->getNotifyList();
		$nl2 = $folder->getNotifyList();
		$nl3 = $targetFolder->getNotifyList();
		$nl = array(
			'users'=>array_unique(array_merge($nl1['users'], $nl2['users'], $nl3['users']), SORT_REGULAR),
			'groups'=>array_unique(array_merge($nl1['groups'], $nl2['groups'], $nl3['groups']), SORT_REGULAR)
		);
		$subject = "folder_moved_email_subject";
		$message = "folder_moved_email_body";
		$params = array();
		$params['name'] = $folder->getName();
		$params['old_folder_path'] = $oldFolder->getFolderPathPlain();
		$params['new_folder_path'] = $targetFolder->getFolderPathPlain();
		$params['username'] = $user->getFullName();
		$params['url'] = "http".((isset($_SERVER['HTTPS']) && (strcmp($_SERVER['HTTPS'],'off')!=0)) ? "s" : "")."://".$_SERVER['HTTP_HOST'].$settings->_httpRoot."out/out.ViewFolder.php?folderid=".$folder->getID();
		$params['sitename'] = $settings->_siteName;
		$params['http_root'] = $settings->_httpRoot;
		$notifier->toList($user, $nl["users"], $subject, $message, $params);
		foreach ($nl["groups"] as $grp) {
			$notifier->toGroup($user, $grp, $subject, $message, $params);
		}
		// if user is not owner send notification to owner
		if ($user->getID() != $folder->getOwner()->getID()) 
			$notifier->toIndividual($user, $folder->getOwner(), $subject, $message, $params);

	}
} else {
	UI::exitError(getMLText("folder_title", array("foldername" => $folder->getName())),getMLText("error_occured"));
}

add_log_line();
header("Location:../out/out.ViewFolder.php?folderid=".$folderid."&showtree=".$_GET["showtree"]);

?>
