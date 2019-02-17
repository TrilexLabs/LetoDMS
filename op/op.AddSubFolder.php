<?php
//    MyDMS. Document Management System
//    Copyright (C) 2002-2005  Markus Westphal
//    Copyright (C) 2006-2008 Malcolm Cowe
//    Copyright (C) 2010 Matteo Lucarelli
//    Copyright (C) 2010-2106 Uwe Steinmann
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
include("../inc/inc.ClassController.php");
include("../inc/inc.Authentication.php");

$tmp = explode('.', basename($_SERVER['SCRIPT_FILENAME']));
$controller = Controller::factory($tmp[1], array('dms'=>$dms, 'user'=>$user));

/* Check if the form data comes from a trusted request */
if(!checkFormKey('addsubfolder')) {
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

$folderPathHTML = getFolderPathHTML($folder, true);

if ($folder->getAccessMode($user, 'addFolder') < M_READWRITE) {
	UI::exitError(getMLText("folder_title", array("foldername" => $folder->getName())),getMLText("access_denied"));
}

$sequence = $_POST["sequence"];
$sequence = str_replace(',', '.', $_POST["sequence"]);

if (!is_numeric($sequence)) {
	UI::exitError(getMLText("folder_title", array("foldername" => $folder->getName())),getMLText("invalid_sequence"));
}

$name = $_POST["name"];
$comment = $_POST["comment"];
if(isset($_POST["attributes"]))
	$attributes = $_POST["attributes"];
else
	$attributes = array();
foreach($attributes as $attrdefid=>$attribute) {
	$attrdef = $dms->getAttributeDefinition($attrdefid);
	if($attribute) {
		if(!$attrdef->validate($attribute)) {
			$errmsg = getAttributeValidationText($attrdef->getValidationError(), $attrdef->getName(), $attribute);
			UI::exitError(getMLText("folder_title", array("foldername" => $folder->getName())), $errmsg);
		}
	} elseif($attrdef->getMinValues() > 0) {
		UI::exitError(getMLText("folder_title", array("foldername" => $document->getName())),getMLText("attr_min_values", array("attrname"=>$attrdef->getName())));
	}
}

/* Check if additional notification shall be added */
$notusers = array();
if(!empty($_POST['notification_users'])) {
	foreach($_POST['notification_users'] as $notuserid) {
		$notuser = $dms->getUser($notuserid);
		if($notuser) {
			$notusers[] = $notuser;
		}
	}
}
$notgroups = array();
if(!empty($_POST['notification_groups'])) {
	foreach($_POST['notification_groups'] as $notgroupid) {
		$notgroup = $dms->getGroup($notgroupid);
		if($notgroup) {
			$notgroups[] = $notgroup;
		}
	}
}

$controller->setParam('folder', $folder);
$controller->setParam('name', $name);
$controller->setParam('comment', $comment);
$controller->setParam('sequence', $sequence);
$controller->setParam('attributes', $attributes);
$controller->setParam('notificationgroups', $notgroups);
$controller->setParam('notificationusers', $notusers);
if(!$subFolder = $controller->run()) {
	UI::exitError(getMLText("folder_title", array("foldername" => $folder->getName())),getMLText($controller->getErrorMsg()));
} else {
	// Send notification to subscribers.
	if($notifier) {
		$fnl = $folder->getNotifyList();
		$snl = $subFolder->getNotifyList();
		$nl = array(
			'users'=>array_unique(array_merge($snl['users'], $fnl['users']), SORT_REGULAR),
			'groups'=>array_unique(array_merge($snl['groups'], $fnl['groups']), SORT_REGULAR)
		);

		$subject = "new_subfolder_email_subject";
		$message = "new_subfolder_email_body";
		$params = array();
		$params['name'] = $subFolder->getName();
		$params['folder_name'] = $folder->getName();
		$params['folder_path'] = $folder->getFolderPathPlain();
		$params['username'] = $user->getFullName();
		$params['comment'] = $comment;
		$params['url'] = "http".((isset($_SERVER['HTTPS']) && (strcmp($_SERVER['HTTPS'],'off')!=0)) ? "s" : "")."://".$_SERVER['HTTP_HOST'].$settings->_httpRoot."out/out.ViewFolder.php?folderid=".$subFolder->getID();
		$params['sitename'] = $settings->_siteName;
		$params['http_root'] = $settings->_httpRoot;
		$notifier->toList($user, $nl["users"], $subject, $message, $params);
		foreach ($nl["groups"] as $grp) {
			$notifier->toGroup($user, $grp, $subject, $message, $params);
		}
	}
}

add_log_line("?name=".$name."&folderid=".$folderid);

header("Location:../out/out.ViewFolder.php?folderid=".$folderid."&showtree=".$_POST["showtree"]);

?>
