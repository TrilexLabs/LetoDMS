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

if (!$user->isAdmin()) {
	UI::exitError(getMLText("document"),getMLText("access_denied"));
}

/* Check if the form data comes from a trusted request */
if(!checkFormKey('transferdocument')) {
	UI::exitError(getMLText("document_title", array("documentname" => getMLText("invalid_request_token"))),getMLText("invalid_request_token"));
}

if (!isset($_POST["documentid"]) || !is_numeric($_POST["documentid"]) || intval($_POST["documentid"])<1) {
	UI::exitError(getMLText("document_title", array("documentname" => getMLText("invalid_doc_id"))),getMLText("invalid_doc_id"));
}
$documentid = $_POST["documentid"];
$document = $dms->getDocument($documentid);

if (!is_object($document)) {
	UI::exitError(getMLText("document_title", array("documentname" => getMLText("invalid_doc_id"))),getMLText("invalid_doc_id"));
}

if (!isset($_POST["userid"]) || !is_numeric($_POST["userid"]) || intval($_POST["userid"])<1) {
	UI::exitError(getMLText("document_title", array("documentname" => getMLText("invalid_doc_id"))),getMLText("invalid_doc_id"));
}
$userid = $_POST["userid"];
$newuser = $dms->getUser($userid);

if (!is_object($newuser)) {
	UI::exitError(getMLText("document_title", array("documentname" => getMLText("invalid_doc_id"))),getMLText("invalid_doc_id"));
}

$folder = $document->getFolder();

$controller->setParam('document', $document);
$controller->setParam('newuser', $newuser);
if(!$controller->run()) {
	UI::exitError(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))),getMLText("error_transfer_document"));
}

if ($notifier){
	/* Get the notify list before removing the document */
	$nl =	$document->getNotifyList();
	$subject = "document_transfered_email_subject";
	$message = "document_transfered_email_body";
	$params = array();
	$params['name'] = $document->getName();
	$params['newuser'] = $newuser->getFullName();
	$params['folder_path'] = $folder->getFolderPathPlain();
	$params['username'] = $user->getFullName();
	$params['sitename'] = $settings->_siteName;
	$params['http_root'] = $settings->_httpRoot;
	$params['url'] = "http".((isset($_SERVER['HTTPS']) && (strcmp($_SERVER['HTTPS'],'off')!=0)) ? "s" : "")."://".$_SERVER['HTTP_HOST'].$settings->_httpRoot."out/out.ViewDocument.php?documentid=".$document->getID();
	$notifier->toList($user, $nl["users"], $subject, $message, $params);
	foreach ($nl["groups"] as $grp) {
		$notifier->toGroup($user, $grp, $subject, $message, $params);
	}
}

$session->setSplashMsg(array('type'=>'success', 'msg'=>getMLText('splash_transfer_document')));

add_log_line("?documentid=".$documentid);

header("Location:../out/out.ViewFolder.php?folderid=".$folder->getID());

?>

