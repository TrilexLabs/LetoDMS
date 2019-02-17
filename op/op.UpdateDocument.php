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
include("../inc/inc.Authentication.php");
include("../inc/inc.ClassUI.php");
include("../inc/inc.ClassController.php");

$tmp = explode('.', basename($_SERVER['SCRIPT_FILENAME']));
$controller = Controller::factory($tmp[1], array('dms'=>$dms, 'user'=>$user));

/* Check if the form data comes from a trusted request */
if(!checkFormKey('updatedocument')) {
	UI::exitError(getMLText("document_title", array("documentname" => getMLText("invalid_request_token"))),getMLText("invalid_request_token"));
}

if (!isset($_POST["documentid"]) || !is_numeric($_POST["documentid"]) || intval($_POST["documentid"])<1) {
	UI::exitError(getMLText("document_title", array("documentname" => getMLText("invalid_doc_id"))),getMLText("invalid_doc_id"));
}

$documentid = $_POST["documentid"];
$document = $dms->getDocument($documentid);
$folder = $document->getFolder();

if (!is_object($document)) {
	UI::exitError(getMLText("document_title", array("documentname" => getMLText("invalid_doc_id"))),getMLText("invalid_doc_id"));
}

if ($document->getAccessMode($user, 'updateDocument') < M_READWRITE) {
	UI::exitError(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))),getMLText("access_denied"));
}

if($settings->_quota > 0) {
	$remain = checkQuota($user);
	if ($remain < 0) {
		UI::exitError(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))),getMLText("quota_exceeded", array('bytes'=>LetoDMS_Core_File::format_filesize(abs($remain)))));
	}
}

if ($document->isLocked()) {
	$lockingUser = $document->getLockingUser();
	if (($lockingUser->getID() != $user->getID()) && ($document->getAccessMode($user) != M_ALL)) {
		UI::exitError(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))),getMLText("no_update_cause_locked"));
	}
	else $document->setLocked(false);
}

$prefix = 'userfile';
if(isset($_POST[$prefix.'-fine-uploader-uuids']) && $_POST[$prefix.'-fine-uploader-uuids']) {
	$uuids = explode(';', $_POST[$prefix.'-fine-uploader-uuids']);
	$names = explode(';', $_POST[$prefix.'-fine-uploader-names']);
	$uuid = $uuids[0];
	$fullfile = $settings->_stagingDir.'/'.utf8_basename($uuid);
	if(file_exists($fullfile)) {
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mimetype = finfo_file($finfo, $fullfile);
		$_FILES["userfile"]['tmp_name'] = $fullfile;
		$_FILES["userfile"]['type'] = $mimetype;
		$_FILES["userfile"]['name'] = isset($names[0]) ? $names[0] : $uuid;
		$_FILES["userfile"]['size'] = filesize($fullfile);
		$_FILES["userfile"]['error'] = 0;
	}
}

if (isset($_FILES['userfile']) && $_FILES['userfile']['error'] == 0) {
//	if(!is_uploaded_file($_FILES["userfile"]["tmp_name"]))
//		UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("error_occured")."lsajdflk");

	if($_FILES["userfile"]["size"] == 0)
		UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("uploading_zerosize"));

	$userfiletmp = $_FILES["userfile"]["tmp_name"];
	$userfiletype = $_FILES["userfile"]["type"];
	$userfilename = $_FILES["userfile"]["name"];

	if($settings->_overrideMimeType) {
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$userfiletype = finfo_file($finfo, $userfiletmp);
	}
} elseif($settings->_dropFolderDir) {
	if($_POST['dropfolderfileform1']) {
		$fullfile = $settings->_dropFolderDir.'/'.$user->getLogin().'/'.$_POST["dropfolderfileform1"];
		if(file_exists($fullfile)) {
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$mimetype = finfo_file($finfo, $fullfile);
			$userfiletmp = $fullfile;
			$userfiletype = $mimetype;
			$userfilename= $_POST["dropfolderfileform1"];
		} else {
			UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("error_occured"));
		}
	} else {
		UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("error_occured"));
	}
} else {
	UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("uploading_failed"));
}

/* Check if the uploaded file is identical to last version */
	$lc = $document->getLatestContent();
	if($lc->getChecksum() == LetoDMS_Core_File::checksum($userfiletmp)) {
		UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("identical_version"));
	}

	$fileType = ".".pathinfo($userfilename, PATHINFO_EXTENSION);

if($settings->_enableFullSearch) {
	$index = $indexconf['Indexer']::open($settings->_luceneDir);
	$indexconf['Indexer']::init($settings->_stopWordsFile);
} else {
	$index = null;
}

if(isset($_POST["comment"]))
	$comment  = $_POST["comment"];
else
	$comment = "";

$oldexpires = $document->getExpires();
switch($_POST["presetexpdate"]) {
case "date":
	$tmp = explode('-', $_POST["expdate"]);
	$expires = mktime(0,0,0, $tmp[1], $tmp[2], $tmp[0]);
	break;
case "1w":
	$tmp = explode('-', date('Y-m-d'));
	$expires = mktime(0,0,0, $tmp[1], $tmp[2]+7, $tmp[0]);
	break;
case "1m":
	$tmp = explode('-', date('Y-m-d'));
	$expires = mktime(0,0,0, $tmp[1]+1, $tmp[2], $tmp[0]);
	break;
case "1y":
	$tmp = explode('-', date('Y-m-d'));
	$expires = mktime(0,0,0, $tmp[1], $tmp[2], $tmp[0]+1);
	break;
case "2y":
	$tmp = explode('-', date('Y-m-d'));
	$expires = mktime(0,0,0, $tmp[1], $tmp[2], $tmp[0]+2);
	break;
case "never":
default:
	$expires = null;
	break;
}

	// Get the list of reviewers and approvers for this document.
	$reviewers = array();
	$approvers = array();
	$reviewers["i"] = array();
	$reviewers["g"] = array();
	$approvers["i"] = array();
	$approvers["g"] = array();
	$workflow = null;

	if($settings->_workflowMode == 'traditional' || $settings->_workflowMode == 'traditional_only_approval') {
		if($settings->_workflowMode == 'traditional') {
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
		if($settings->_workflowMode == 'traditional') {
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
	} elseif($settings->_workflowMode == 'advanced') {
		if(!$workflows = $user->getMandatoryWorkflows()) {
			if(isset($_POST["workflow"]))
				$workflow = $dms->getWorkflow($_POST["workflow"]);
			else
				$workflow = null;
		} else {
			/* If there is excactly 1 mandatory workflow, then set no matter what has
			 * been posted in 'workflow', otherwise check if the posted workflow is in the
			 * list of mandatory workflows. If not, then take the first one.
			 */
			$workflow = array_shift($workflows);
			foreach($workflows as $mw)
				if($mw->getID() == $_POST['workflow']) {$workflow = $mw; break;}
		}
	}

	if(isset($_POST["attributes_version"]) && $_POST["attributes_version"]) {
		$attributes = $_POST["attributes_version"];
		foreach($attributes as $attrdefid=>$attribute) {
			$attrdef = $dms->getAttributeDefinition($attrdefid);
			if($attribute) {
				if(!$attrdef->validate($attribute)) {
					$errmsg = getAttributeValidationText($attrdef->getValidationError(), $attrdef->getName(), $attribute);
					UI::exitError(getMLText("document_title", array("documentname" => $document->getName())), $errmsg);
				}
			} elseif($attrdef->getMinValues() > 0) {
				UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("attr_min_values", array("attrname"=>$attrdef->getName())));
			}
		}
	} else {
		$attributes = array();
	}

	$controller->setParam('folder', $folder);
	$controller->setParam('document', $document);
	$controller->setParam('index', $index);
	$controller->setParam('indexconf', $indexconf);
	$controller->setParam('comment', $comment);
	if($oldexpires != $expires)
		$controller->setParam('expires', $expires);
	$controller->setParam('userfiletmp', $userfiletmp);
	$controller->setParam('userfilename', $userfilename);
	$controller->setParam('filetype', $fileType);
	$controller->setParam('userfiletype', $userfiletype);
	$controller->setParam('reviewers', $reviewers);
	$controller->setParam('approvers', $approvers);
	$controller->setParam('attributes', $attributes);
	$controller->setParam('workflow', $workflow);

	if(!$content = $controller->run()) {
		UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText($controller->getErrorMsg()));
	} else {
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
			$params['version_comment'] = $content->getComment();
			$params['url'] = "http".((isset($_SERVER['HTTPS']) && (strcmp($_SERVER['HTTPS'],'off')!=0)) ? "s" : "")."://".$_SERVER['HTTP_HOST'].$settings->_httpRoot."out/out.ViewDocument.php?documentid=".$document->getID();
			$params['sitename'] = $settings->_siteName;
			$params['http_root'] = $settings->_httpRoot;
			$notifier->toList($user, $notifyList["users"], $subject, $message, $params);
			foreach ($notifyList["groups"] as $grp) {
				$notifier->toGroup($user, $grp, $subject, $message, $params);
			}
			// if user is not owner send notification to owner
//			if ($user->getID() != $document->getOwner()->getID()) 
//				$notifier->toIndividual($user, $document->getOwner(), $subject, $message, $params);

			if($workflow && $settings->_enableNotificationWorkflow) {
				$subject = "request_workflow_action_email_subject";
				$message = "request_workflow_action_email_body";
				$params = array();
				$params['name'] = $document->getName();
				$params['version'] = $content->getVersion();
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

			if($settings->_enableNotificationAppRev) {
				/* Reviewers and approvers will be informed about the new document */
				if($reviewers['i'] || $reviewers['g']) {
					$subject = "review_request_email_subject";
					$message = "review_request_email_body";
					$params = array();
					$params['name'] = $document->getName();
					$params['folder_path'] = $folder->getFolderPathPlain();
					$params['version'] = $content->getVersion();
					$params['comment'] = $content->getComment();
					$params['username'] = $user->getFullName();
					$params['url'] = "http".((isset($_SERVER['HTTPS']) && (strcmp($_SERVER['HTTPS'],'off')!=0)) ? "s" : "")."://".$_SERVER['HTTP_HOST'].$settings->_httpRoot."out/out.ViewDocument.php?documentid=".$document->getID();
					$params['sitename'] = $settings->_siteName;
					$params['http_root'] = $settings->_httpRoot;

					foreach($reviewers['i'] as $reviewerid) {
						$notifier->toIndividual($user, $dms->getUser($reviewerid), $subject, $message, $params);
					}
					foreach($reviewers['g'] as $reviewergrpid) {
						$notifier->toGroup($user, $dms->getGroup($reviewergrpid), $subject, $message, $params);
					}
				}

				elseif($approvers['i'] || $approvers['g']) {
					$subject = "approval_request_email_subject";
					$message = "approval_request_email_body";
					$params = array();
					$params['name'] = $document->getName();
					$params['folder_path'] = $folder->getFolderPathPlain();
					$params['version'] = $content->getVersion();
					$params['comment'] = $content->getComment();
					$params['username'] = $user->getFullName();
					$params['url'] = "http".((isset($_SERVER['HTTPS']) && (strcmp($_SERVER['HTTPS'],'off')!=0)) ? "s" : "")."://".$_SERVER['HTTP_HOST'].$settings->_httpRoot."out/out.ViewDocument.php?documentid=".$document->getID();
					$params['sitename'] = $settings->_siteName;
					$params['http_root'] = $settings->_httpRoot;

					foreach($approvers['i'] as $approverid) {
						$notifier->toIndividual($user, $dms->getUser($approverid), $subject, $message, $params);
					}
					foreach($approvers['g'] as $approvergrpid) {
						$notifier->toGroup($user, $dms->getGroup($approvergrpid), $subject, $message, $params);
					}
				}
			}

			if($oldexpires != $document->getExpires()) {
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
		}

		if($settings->_removeFromDropFolder) {
			if(file_exists($userfiletmp)) {
				unlink($userfiletmp);
			}
		}
	}

add_log_line("?documentid=".$documentid);
header("Location:../out/out.ViewDocument.php?documentid=".$documentid);

?>
