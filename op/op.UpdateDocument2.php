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

$file_param_name = 'file';
$file_name = $_FILES[ $file_param_name ][ 'name' ];
$source_file_path = $_FILES[ $file_param_name ][ 'tmp_name' ];
$fileId = basename($_POST['fileId']);
$partitionIndex = (int) $_POST['partitionIndex'];
$target_file_path =$settings->_stagingDir.$fileId."-".$partitionIndex;
if( move_uploaded_file( $source_file_path, $target_file_path ) ) {
	if($partitionIndex+1 == $_POST['partitionCount']) {
		$fpnew = fopen($settings->_stagingDir.$fileId, 'w+');
		for($i=0; $i<$_POST['partitionCount']; $i++) {
			$content = file_get_contents($settings->_stagingDir.$fileId."-".$i, 'r');
			fwrite($fpnew, $content);
			unlink($settings->_stagingDir.$fileId."-".$i);
		}
		fclose($fpnew);

		if (!isset($_POST["documentid"]) || !is_numeric($_POST["documentid"]) || intval($_POST["documentid"])<1) {
			echo getMLText("invalid_doc_id");
		}

		$documentid = $_POST["documentid"];
		$document = $dms->getDocument($documentid);
		$folder = $document->getFolder();

		if (!is_object($document)) {
			echo getMLText("invalid_doc_id");
		}

		if ($document->getAccessMode($user) < M_READWRITE) {
			echo getMLText("access_denied");
		}

		if ($document->isLocked()) {
			$lockingUser = $document->getLockingUser();
			if (($lockingUser->getID() != $user->getID()) && ($document->getAccessMode($user) != M_ALL)) {
				echo getMLText("no_update_cause_locked");
			}
			else $document->setLocked(false);
		}

		$comment  = $_POST["comment"];

		$userfiletmp = $settings->_stagingDir.$fileId;
		$userfiletype = $_FILES[ $file_param_name ]["type"];
		$userfilename = $_FILES[ $file_param_name ]["name"];

		$fileType = ".".pathinfo($userfilename, PATHINFO_EXTENSION);

		if($settings->_overrideMimeType) {
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$userfiletype = finfo_file($finfo, $userfiletmp);
		}

		// Get the list of reviewers and approvers for this document.
		$reviewers = array();
		$approvers = array();

		// Retrieve the list of individual reviewers from the form.
		$reviewers["i"] = array();
		if (isset($_POST["indReviewers"])) {
			foreach ($_POST["indReviewers"] as $ind) {
				$reviewers["i"][] = $ind;
			}
		}
		// Retrieve the list of reviewer groups from the form.
		$reviewers["g"] = array();
		if (isset($_POST["grpReviewers"])) {
			foreach ($_POST["grpReviewers"] as $grp) {
				$reviewers["g"][] = $grp;
			}
		}

		// Retrieve the list of individual approvers from the form.
		$approvers["i"] = array();
		if (isset($_POST["indApprovers"])) {
			foreach ($_POST["indApprovers"] as $ind) {
				$approvers["i"][] = $ind;
			}
		}
		// Retrieve the list of approver groups from the form.
		$approvers["g"] = array();
		if (isset($_POST["grpApprovers"])) {
			foreach ($_POST["grpApprovers"] as $grp) {
				$approvers["g"][] = $grp;
			}
		}

		// add mandatory reviewers/approvers
		$docAccess = $folder->getReadAccessList($settings->_enableAdminRevApp, $settings->_enableOwnerRevApp);
		$res=$user->getMandatoryReviewers();
		foreach ($res as $r){

			if ($r['reviewerUserID']!=0){
				foreach ($docAccess["users"] as $usr)
					if ($usr->getID()==$r['reviewerUserID']){
						$reviewers["i"][] = $r['reviewerUserID'];
						break;
					}
			}
			else if ($r['reviewerGroupID']!=0){
				foreach ($docAccess["groups"] as $grp)
					if ($grp->getID()==$r['reviewerGroupID']){
						$reviewers["g"][] = $r['reviewerGroupID'];
						break;
					}
			}
		}
		$res=$user->getMandatoryApprovers();
		foreach ($res as $r){

			if ($r['approverUserID']!=0){
				foreach ($docAccess["users"] as $usr)
					if ($usr->getID()==$r['approverUserID']){
						$approvers["i"][] = $r['approverUserID'];
						break;
					}
			}
			else if ($r['approverGroupID']!=0){
				foreach ($docAccess["groups"] as $grp)
					if ($grp->getID()==$r['approverGroupID']){
						$approvers["g"][] = $r['approverGroupID'];
						break;
					}
			}
		}

		$filesize = LetoDMS_Core_File::fileSize($userfiletmp);
		$contentResult=$document->addContent($comment, $user, $userfiletmp, basename($userfilename), $fileType, $userfiletype, $reviewers, $approvers);
		unlink($userfiletmp);
		if (is_bool($contentResult) && !$contentResult) {
			echo getMLText("error_occured");
		} else {
			if($settings->_enableFullSearch) {
				$index = $indexconf['Indexer']::open($settings->_luceneDir);
				if($index) {
					$lucenesearch = new $indexconf['Search']($index);
					if($hit = $lucenesearch->getDocument((int) $document->getId())) {
						$index->delete($hit->id);
					}
					$indexconf['Indexer']::init($settings->_stopWordsFile);
					$index->addDocument(new $indexconf['IndexedDocument']($dms, $document, isset($settings->_converters['fulltext']) ? $settings->_converters['fulltext'] : null, !($filesize < $settings->_maxSizeForFullText)));
					$index->commit();
				}
			}

			// Send notification to subscribers.
			if ($notifier){
				$notifyList = $document->getNotifyList();
				$folder = $document->getFolder();
				$subject = "document_updated_email_subject";
				$message = "document_updated_email_body";
				$params = array();
				$params['name'] = $document->getName();
				$params['folder_path'] = $folder->getFolderPathPlain();
				$params['username'] = $user->getFullName();
				$params['comment'] = $document->getComment();
				$params['version_comment'] = $contentResult->getContent()->getComment();
				$params['url'] = "http".((isset($_SERVER['HTTPS']) && (strcmp($_SERVER['HTTPS'],'off')!=0)) ? "s" : "")."://".$_SERVER['HTTP_HOST'].$settings->_httpRoot."out/out.ViewDocument.php?documentid=".$document->getID();
				$params['sitename'] = $settings->_siteName;
				$params['http_root'] = $settings->_httpRoot;
				$notifier->toList($user, $notifyList["users"], $subject, $message, $params);
				foreach ($notifyList["groups"] as $grp) {
					$notifier->toGroup($user, $grp, $subject, $message, $params);
				}
				// if user is not owner send notification to owner
//				if ($user->getID() != $document->getOwner()->getID()) 
//					$notifier->toIndividual($user, $document->getOwner(), $subject, $message, $params);
			}

			$expires = ($_POST["expires"] == "true") ? mktime(0,0,0, $_POST["expmonth"], $_POST["expday"], $_POST["expyear"]) : false;

			if ($document->setExpires($expires)) {
				if($notifier) {
					$notifyList = $document->getNotifyList();
					$folder = $document->getFolder();
					// Send notification to subscribers.
					$subject = "expiry_changed_email_subject";
					$message = "expiry_changed_email_body";
					$params = array();
					$params['name'] = $document->getName();
					$params['folder_path'] = $folder->getFolderPathPlain();
					$params['username'] = $user->getFullName();
					$params['url'] = "http".((isset($_SERVER['HTTPS']) && (strcmp($_SERVER['HTTPS'],'off')!=0)) ? "s" : "")."://".$_SERVER['HTTP_HOST'].$settings->_httpRoot."out/out.ViewDocument.php?documentid=".$document->getID();
					$params['sitename'] = $settings->_siteName;
					$params['http_root'] = $settings->_httpRoot;
					$notifier->toList($user, $notifyList["users"], $subject, $message, $params);
					foreach ($notifyList["groups"] as $grp) {
						$notifier->toGroup($user, $grp, $subject, $message, $params);
					}
				}
			} else {
				UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("error_occured"));
			}
		}
		add_log_line("?documentid=".$documentid);
	}
}

?>
