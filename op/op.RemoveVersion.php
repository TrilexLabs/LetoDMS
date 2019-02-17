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

/* Check if the form data comes from a trusted request */
if(!checkFormKey('removeversion')) {
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

if (!$settings->_enableVersionDeletion && !$user->isAdmin()) {
	UI::exitError(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))),getMLText("access_denied"));
}

if ($document->getAccessMode($user, 'removeVersion') < M_ALL) {
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

require_once("LetoDMS/Preview.php");
$previewer = new LetoDMS_Preview_Previewer($settings->_cacheDir);
if (count($document->getContent())==1) {
	$previewer->deleteDocumentPreviews($document);
	$nl = $document->getNotifyList();
	$docname = $document->getName();
	if (!$document->remove()) {
		UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("error_occured"));
	} else {
		/* Remove the document from the fulltext index */
		if($settings->_enableFullSearch) {
			$index = $indexconf['Indexer']::open($settings->_luceneDir);
			if($index) {
				$lucenesearch = new $indexconf['Search']($index);
				if($hit = $lucenesearch->getDocument($documentid)) {
					$index->delete($hit->id);
					$index->commit();
				}
			}
		}

		if ($notifier){
			$subject = "document_deleted_email_subject";
			$message = "document_deleted_email_body";
			$params = array();
			$params['name'] = $docname;
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
	}
}
else {
	/* Before deleting the content get a list of all users that should
	 * be informed about the removal.
	 */
	$emailUserList = array();
	$emailUserList[] = $version->_userID;
	$emailGroupList = array();
	$status = $version->getReviewStatus();
	foreach ($status as $st) {
		if ($st["status"]==0 && !in_array($st["required"], $emailUserList)) {
			if($st['type'] == 0)
				$emailUserList[] = $st["required"];
			else
				$emailGroupList[] = $st["required"];
		}
	}
	$status = $version->getApprovalStatus();
	foreach ($status as $st) {
		if ($st["status"]==0 && !in_array($st["required"], $emailUserList)) {
			if($st['type'] == 0)
				$emailUserList[] = $st["required"];
			else
				$emailGroupList[] = $st["required"];
		}
	}

	$previewer->deletePreview($version, $settings->_previewWidthDetail);
	$previewer->deletePreview($version, $settings->_previewWidthList);
	if (!$document->removeContent($version)) {
		UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("error_occured"));
	} else {
		/* Remove the document from the fulltext index and reindex latest version */
		if($settings->_enableFullSearch) {
			$index = $indexconf['Indexer']::open($settings->_luceneDir);
			if($index) {
				$lucenesearch = new $indexconf['Search']($index);
				if($hit = $lucenesearch->getDocument($document->getID())) {
					$index->delete($hit->id);
				}
				$version = $document->getLatestContent();
				$indexconf['Indexer']::init($settings->_stopWordsFile);
				$index->addDocument(new $indexconf['IndexedDocument']($dms, $document, isset($settings->_converters['fulltext']) ? $settings->_converters['fulltext'] : null, !($version->getFileSize() < $settings->_maxSizeForFullText)));
				$index->commit();
			}
		}

		// Notify affected users.
		if ($notifier){
			$nl=$document->getNotifyList();
			$userrecipients = array();
			foreach ($emailUserList as $eID) {
				$eU = $version->_document->_dms->getUser($eID);
				$userrecipients[] = $eU;
			}
			$grouprecipients = array();
			foreach ($emailGroupList as $eID) {
				$eU = $version->_document->_dms->getGroup($eID);
				$grouprecipients[] = $eU;
			}

			$subject = "version_deleted_email_subject";
			$message = "version_deleted_email_body";
			$params = array();
			$params['name'] = $document->getName();
			$params['version'] = $version->getVersion();
			$params['folder_path'] = $document->getFolder()->getFolderPathPlain();
			$params['username'] = $user->getFullName();
			$params['sitename'] = $settings->_siteName;
			$params['http_root'] = $settings->_httpRoot;
			$params['url'] = "http".((isset($_SERVER['HTTPS']) && (strcmp($_SERVER['HTTPS'],'off')!=0)) ? "s" : "")."://".$_SERVER['HTTP_HOST'].$settings->_httpRoot."out/out.ViewDocument.php?documentid=".$document->getID();
			$notifier->toList($user, $userrecipients, $subject, $message, $params);
			$notifier->toList($user, $nl["users"], $subject, $message, $params);
			foreach($grouprecipients as $grp) {
				$notifier->toGroup($user, $grp, $subject, $message, $params);
			}
			foreach ($nl["groups"] as $grp) {
				$notifier->toGroup($user, $grp, $subject, $message, $params);
			}
		}
	}
}

add_log_line("?documentid=".$documentid."&version".$version_num);

header("Location:../out/out.ViewDocument.php?documentid=".$documentid."&currenttab=previous");

?>
