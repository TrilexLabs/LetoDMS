<?php
//    MyDMS. Document Management System
//    Copyright (C) 2002-2005  Markus Westphal
//    Copyright (C) 2006-2008 Malcolm Cowe
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
include("../inc/inc.Language.php");
include("../inc/inc.Init.php");
include("../inc/inc.Extension.php");
include("../inc/inc.DBInit.php");
include("../inc/inc.ClassUI.php");
include("../inc/inc.Authentication.php");

/* Check if the form data comes from a trusted request */
if(!checkFormKey('setworkflow')) {
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

if ($document->getAccessMode($user) < M_READWRITE) {
	UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("access_denied"));
}

if (!isset($_POST["version"]) || !is_numeric($_POST["version"]) || intval($_POST["version"])<1) {
	UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("invalid_version"));
}

$version_num = $_POST["version"];
$version = $document->getContentByVersion($version_num);
if (!is_object($version)) {
	UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("invalid_version"));
}

if (!isset($_POST["workflow"]) || !is_numeric($_POST["workflow"]) || intval($_POST["workflow"])<1) {
	UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("invalid_workflow"));
}

$workflow = $dms->getWorkflow($_POST["workflow"]);
if (!is_object($workflow)) {
	UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("invalid_workflow"));
}

if (!$version->setWorkflow($workflow, $user)){
	UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("error_occured"));
}

if ($notifier) {
	$nl =	$document->getNotifyList();
	$folder = $document->getFolder();

	if($settings->_enableNotificationWorkflow) {
		$subject = "request_workflow_action_email_subject";
		$message = "request_workflow_action_email_body";
		$params = array();
		$params['name'] = $document->getName();
		$params['version'] = $version->getVersion();
		$params['workflow'] = $workflow->getName();
		$params['folder_path'] = $folder->getFolderPathPlain();
		$params['current_state'] = $workflow->getInitState()->getName();
		$params['username'] = $user->getFullName();
		$params['sitename'] = $settings->_siteName;
		$params['http_root'] = $settings->_httpRoot;
		$params['url'] = "http".((isset($_SERVER['HTTPS']) && (strcmp($_SERVER['HTTPS'],'off')!=0)) ? "s" : "")."://".$_SERVER['HTTP_HOST'].$settings->_httpRoot."out/out.ViewDocument.php?documentid=".$document->getID();

		foreach($workflow->getNextTransitions($workflow->getInitState()) as $ntransition) {
			foreach($ntransition->getUsers() as $tuser) {
				$notifier->toIndividual($user, $tuser->getUser(), $subject, $message, $params);
			}
			foreach($ntransition->getGroups() as $tuser) {
				$notifier->toGroup($user, $tuser->getGroup(), $subject, $message, $params);
			}
		}
	}
}

add_log_line("?documentid=".$documentid);

header("Location:../out/out.ViewDocument.php?documentid=".$documentid);

?>
