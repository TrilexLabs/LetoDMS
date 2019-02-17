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
include("../inc/inc.Utils.php");
include("../inc/inc.Language.php");
include("../inc/inc.Init.php");
include("../inc/inc.Extension.php");
include("../inc/inc.DBInit.php");
include("../inc/inc.ClassAccessOperation.php");
include("../inc/inc.Authentication.php");
include("../inc/inc.ClassUI.php");

/* Check if the form data comes from a trusted request */
if(!checkFormKey('reviewdocument')) {
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

if ($document->getAccessMode($user) < M_READ) {
	UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("access_denied"));
}

if (!isset($_POST["version"]) || !is_numeric($_POST["version"]) || intval($_POST["version"])<1) {
	UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("invalid_version"));
}

$version = $_POST["version"];
$content = $document->getContentByVersion($version);

if (!is_object($content)) {
	UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("invalid_version"));
}

// operation is only allowed for the last document version
$latestContent = $document->getLatestContent();
if ($latestContent->getVersion()!=$version) {
	UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("invalid_version"));
}

/* Create object for checking access to certain operations */
$accessop = new LetoDMS_AccessOperation($dms, $document, $user, $settings);

// verify if document may be reviewed
if (!$accessop->mayReview()){
	UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("access_denied"));
}

if (!isset($_POST["reviewStatus"]) || !is_numeric($_POST["reviewStatus"]) ||
		(intval($_POST["reviewStatus"])!=1 && intval($_POST["reviewStatus"])!=-1)) {
	UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("invalid_review_status"));
}

if($_FILES["reviewfile"]["tmp_name"]) {
	if (is_uploaded_file($_FILES["reviewfile"]["tmp_name"]) && $_FILES['reviewfile']['error']!=0){
		UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("uploading_failed"));
	}
}

if ($_POST["reviewType"] == "ind") {

	$comment = $_POST["comment"];
	if($_FILES["reviewfile"]["tmp_name"])
		$file = $_FILES["reviewfile"]["tmp_name"];
	else
		$file = '';
	$reviewLogID = $latestContent->setReviewByInd($user, $user, $_POST["reviewStatus"], $comment, $file);
	if(0 > $reviewLogID) {
		UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("review_update_failed"));
	}
	else {
		// Send an email notification to the document updater.
		if($notifier) {
			$nl=$document->getNotifyList();
			$folder = $document->getFolder();
			$subject = "review_submit_email_subject";
			$message = "review_submit_email_body";
			$params = array();
			$params['name'] = $document->getName();
			$params['version'] = $version;
			$params['folder_path'] = $folder->getFolderPathPlain();
			$params['status'] = getReviewStatusText($_POST["reviewStatus"]);
			$params['comment'] = $comment;
			$params['username'] = $user->getFullName();
			$params['url'] = "http".((isset($_SERVER['HTTPS']) && (strcmp($_SERVER['HTTPS'],'off')!=0)) ? "s" : "")."://".$_SERVER['HTTP_HOST'].$settings->_httpRoot."out/out.ViewDocument.php?documentid=".$document->getID();
			$params['sitename'] = $settings->_siteName;
			$params['http_root'] = $settings->_httpRoot;
			$notifier->toList($user, $nl["users"], $subject, $message, $params);
			foreach ($nl["groups"] as $grp) {
				$notifier->toGroup($user, $grp, $subject, $message, $params);
			}
//			$notifier->toIndividual($user, $content->getUser(), $subject, $message, $params);
		}
	}
}
else if ($_POST["reviewType"] == "grp") {
	$comment = $_POST["comment"];
	$group = $dms->getGroup($_POST['reviewGroup']);
	if($_FILES["reviewfile"]["tmp_name"])
		$file = $_FILES["reviewfile"]["tmp_name"];
	else
		$file = '';
	$reviewLogID = $latestContent->setReviewByGrp($group, $user, $_POST["reviewStatus"], $comment, $file);
	if(0 > $reviewLogID) {
		UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("review_update_failed"));
	}
	else {
		// Send an email notification to the document updater.
		if($notifier) {
			$nl=$document->getNotifyList();
			$folder = $document->getFolder();
			$subject = "review_submit_email_subject";
			$message = "review_submit_email_body";
			$params = array();
			$params['name'] = $document->getName();
			$params['version'] = $version;
			$params['folder_path'] = $folder->getFolderPathPlain();
			$params['status'] = getReviewStatusText($_POST["reviewStatus"]);
			$params['comment'] = $comment;
			$params['username'] = $user->getFullName();
			$params['sitename'] = $settings->_siteName;
			$params['http_root'] = $settings->_httpRoot;
			$notifier->toList($user, $nl["users"], $subject, $message, $params);
			foreach ($nl["groups"] as $grp) {
				$notifier->toGroup($user, $grp, $subject, $message, $params);
			}
//			$notifier->toIndividual($user, $content->getUser(), $subject, $message, $params);
		}
	}
}

//
// Check to see if the overall status for the document version needs to be
// updated.
//

if ($_POST["reviewStatus"]==-1){

	if($content->setStatus(S_REJECTED,$comment,$user)) {
		// Send notification to subscribers.
		if($notifier) {
			$nl=$document->getNotifyList();
			$folder = $document->getFolder();
			$subject = "document_status_changed_email_subject";
			$message = "document_status_changed_email_body";
			$params = array();
			$params['name'] = $document->getName();
			$params['folder_path'] = $folder->getFolderPathPlain();
			$params['status'] = getReviewStatusText(S_REJECTED);
			$params['username'] = $user->getFullName();
			$params['sitename'] = $settings->_siteName;
			$params['http_root'] = $settings->_httpRoot;
			$notifier->toList($user, $nl["users"], $subject, $message, $params);
			foreach ($nl["groups"] as $grp) {
				$notifier->toGroup($user, $grp, $subject, $message, $params);
			}
//			$notifier->toIndividual($user, $content->getUser(), $subject, $message, $params);
		}
	}

}else{

	$docReviewStatus = $content->getReviewStatus();
	if (is_bool($docReviewStatus) && !$docReviewStatus) {
		UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("cannot_retrieve_review_snapshot"));
	}
	$reviewCT = 0;
	$reviewTotal = 0;
	foreach ($docReviewStatus as $drstat) {
		if ($drstat["status"] == 1) {
			$reviewCT++;
		}
		if ($drstat["status"] != -2) {
			$reviewTotal++;
		}
	}
	// If all reviews have been received and there are no rejections, retrieve a
	// count of the approvals required for this document.
	if ($reviewCT == $reviewTotal) {
		$docApprovalStatus = $content->getApprovalStatus();
		if (is_bool($docApprovalStatus) && !$docApprovalStatus) {
			UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("cannot_retrieve_approval_snapshot"));
		}
		$approvalCT = 0;
		$approvalTotal = 0;
		foreach ($docApprovalStatus as $dastat) {
			if ($dastat["status"] == 1) {
				$approvalCT++;
			}
			if ($dastat["status"] != -2) {
				$approvalTotal++;
			}
		}
		// If the approvals received is less than the approvals total, then
		// change status to pending approval.
		if ($approvalCT<$approvalTotal) {
			$newStatus=S_DRAFT_APP;
		}
		else {
			// Otherwise, change the status to released.
			$newStatus=S_RELEASED;
		}
		if ($content->setStatus($newStatus, getMLText("automatic_status_update"), $user)) {
			// Send notification to subscribers.
			if($notifier) {
				$nl=$document->getNotifyList();
				$folder = $document->getFolder();
				$subject = "document_status_changed_email_subject";
				$message = "document_status_changed_email_body";
				$params = array();
				$params['name'] = $document->getName();
				$params['folder_path'] = $folder->getFolderPathPlain();
				$params['status'] = getReviewStatusText($newStatus);
				$params['username'] = $user->getFullName();
				$params['sitename'] = $settings->_siteName;
				$params['http_root'] = $settings->_httpRoot;
				$notifier->toList($user, $nl["users"], $subject, $message, $params);
				foreach ($nl["groups"] as $grp) {
					$notifier->toGroup($user, $grp, $subject, $message, $params);
				}
			}
			
			// TODO: if user os not owner send notification to owner

			// Notify approvers, if necessary.
			if ($newStatus == S_DRAFT_APP) {
				$requestUser = $document->getOwner();

				if($notifier) {
					$subject = "approval_request_email_subject";
					$message = "approval_request_email_body";
					$params = array();
					$params['name'] = $document->getName();
					$params['folder_path'] = $folder->getFolderPathPlain();
					$params['version'] = $version;
					$params['username'] = $user->getFullName();
					$params['sitename'] = $settings->_siteName;
					$params['http_root'] = $settings->_httpRoot;
					$params['url'] = "http".((isset($_SERVER['HTTPS']) && (strcmp($_SERVER['HTTPS'],'off')!=0)) ? "s" : "")."://".$_SERVER['HTTP_HOST'].$settings->_httpRoot."out/out.ViewDocument.php?documentid=".$document->getID();
					foreach ($docApprovalStatus as $dastat) {
					
						if ($dastat["status"] == 0) {
							if ($dastat["type"] == 0) {
	
								$approver = $dms->getUser($dastat["required"]);
								$notifier->toIndividual($document->getOwner(), $approver, $subject, $message, $params);
							} elseif ($dastat["type"] == 1) {
							
								$group = $dms->getGroup($dastat["required"]);
								$notifier->toGroup($document->getOwner(), $group, $subject, $message, $params);
							}
						}
					}
				}
			}
		}
	}
}

header("Location:../out/out.ViewDocument.php?documentid=".$documentid."&currenttab=revapp");

?>
