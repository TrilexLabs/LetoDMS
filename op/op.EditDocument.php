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

if (!isset($_POST["documentid"]) || !is_numeric($_POST["documentid"]) || intval($_POST["documentid"])<1) {
	UI::exitError(getMLText("document_title", array("documentname" => getMLText("invalid_doc_id"))),getMLText("invalid_doc_id"));
}

$documentid = $_POST["documentid"];
$document = $dms->getDocument($documentid);

if (!is_object($document)) {
	UI::exitError(getMLText("document_title", array("documentname" => getMLText("invalid_doc_id"))),getMLText("invalid_doc_id"));
}

$folder = $document->getFolder();
$docPathHTML = getFolderPathHTML($folder, true). " / <a href=\"../out/out.ViewDocument.php?documentid=".$documentid."\">".$document->getName()."</a>";

if ($document->getAccessMode($user, 'editDocument') < M_READWRITE) {
	UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("access_denied"));
}

if($document->isLocked()) {
	$lockingUser = $document->getLockingUser();
	if (($lockingUser->getID() != $user->getID()) && ($document->getAccessMode($user, 'editDocument') != M_ALL)) {
		UI::exitError(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))),getMLText("lock_message", array("email" => $lockingUser->getEmail(), "username" => htmlspecialchars($lockingUser->getFullName()))));
	}
}

$name =     isset($_POST['name']) ? trim($_POST["name"]) : "";
$comment =  isset($_POST['comment']) ? trim($_POST["comment"]) : "";
$keywords = isset($_POST["keywords"]) ? trim($_POST["keywords"]) : "";
if(isset($_POST['categoryidform1'])) {
	$categories = explode(',', preg_replace('/[^0-9,]+/', '', $_POST["categoryidform1"]));
} elseif(isset($_POST["categories"])) { 
	$categories = $_POST["categories"];
} else {
	$categories = array();
}
$sequence = isset($_POST["sequence"]) ? $_POST["sequence"] : "keep";
$sequence = str_replace(',', '.', $_POST["sequence"]);
if (!is_numeric($sequence)) {
	$sequence="keep";
}
if(isset($_POST["attributes"]))
	$attributes = $_POST["attributes"];
else
	$attributes = array();

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

$oldname = $document->getName();
$oldcomment = $document->getComment();
$oldcategories = $document->getCategories();
$oldkeywords = $document->getKeywords();
$oldexpires = $document->getExpires();
/* Make a real copy of each attribute because setting a new attribute value
 * will just update the old attribute object in array attributes[] and hence
 * also update the old value
 */
$oldattributes = array();
foreach($document->getAttributes() as $ai=>$aa)
	$oldattributes[$ai] = clone $aa;
//$oldattributes = $document->getAttributes();

$controller->setParam('document', $document);
$controller->setParam('name', $name);
$controller->setParam('comment', $comment);
$controller->setParam('keywords', $keywords);
$controller->setParam('categories', $categories);
$controller->setParam('expires', $expires);
$controller->setParam('sequence', $sequence);
$controller->setParam('attributes', $attributes);
if(!$controller->run()) {
	if($controller->getErrorMsg()) {
		UI::exitError(getMLText("document_title", array("documentname" => $document->getName())), $controller->getErrorMsg());
	}
}

if ($oldname != $name) {
		// Send notification to subscribers.
		if($notifier) {
			$notifyList = $document->getNotifyList();
			$folder = $document->getFolder();
			$subject = "document_renamed_email_subject";
			$message = "document_renamed_email_body";
			$params = array();
			$params['name'] = $document->getName();
			$params['old_name'] = $oldname;
			$params['folder_path'] = $folder->getFolderPathPlain();
			$params['username'] = $user->getFullName();
			$params['url'] = "http".((isset($_SERVER['HTTPS']) && (strcmp($_SERVER['HTTPS'],'off')!=0)) ? "s" : "")."://".$_SERVER['HTTP_HOST'].$settings->_httpRoot."out/out.ViewDocument.php?documentid=".$document->getID();
			$params['sitename'] = $settings->_siteName;
			$params['http_root'] = $settings->_httpRoot;

			// if user is not owner send notification to owner
			if ($user->getID() != $document->getOwner()->getID() &&
				false === LetoDMS_Core_DMS::inList($document->getOwner(), $notifyList['users'])) {
				$notifyList['users'][] = $document->getOwner();
			}
			$notifier->toList($user, $notifyList["users"], $subject, $message, $params);
			foreach ($notifyList["groups"] as $grp) {
				$notifier->toGroup($user, $grp, $subject, $message, $params);
			}
		}
}

if ($oldcomment != $comment) {
		// Send notification to subscribers.
		if($notifier) {
			$notifyList = $document->getNotifyList();
			$folder = $document->getFolder();
			$subject = "document_comment_changed_email_subject";
			$message = "document_comment_changed_email_body";
			$params = array();
			$params['name'] = $document->getName();
			$params['folder_path'] = $folder->getFolderPathPlain();
			$params['old_comment'] = $oldcomment;
			$params['new_comment'] = $comment;
			$params['username'] = $user->getFullName();
			$params['url'] = "http".((isset($_SERVER['HTTPS']) && (strcmp($_SERVER['HTTPS'],'off')!=0)) ? "s" : "")."://".$_SERVER['HTTP_HOST'].$settings->_httpRoot."out/out.ViewDocument.php?documentid=".$document->getID();
			$params['sitename'] = $settings->_siteName;
			$params['http_root'] = $settings->_httpRoot;

			// if user is not owner send notification to owner
			if ($user->getID() != $document->getOwner()->getID() &&
				false === LetoDMS_Core_DMS::inList($document->getOwner(), $notifyList['users'])) {
				$notifyList['users'][] = $document->getOwner();
			}
			$notifier->toList($user, $notifyList["users"], $subject, $message, $params);
			foreach ($notifyList["groups"] as $grp) {
				$notifier->toGroup($user, $grp, $subject, $message, $params);
			}
		}
}

if ($expires != $oldexpires) {
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

			// if user is not owner send notification to owner
			if ($user->getID() != $document->getOwner()->getID() &&
				false === LetoDMS_Core_DMS::inList($document->getOwner(), $notifyList['users'])) {
				$notifyList['users'][] = $document->getOwner();
			}
			$notifier->toList($user, $notifyList["users"], $subject, $message, $params);
			foreach ($notifyList["groups"] as $grp) {
				$notifier->toGroup($user, $grp, $subject, $message, $params);
			}
		}
}

if ($oldkeywords != $keywords) {
}

$newattributes = $document->getAttributes();
if($oldattributes) {
	foreach($oldattributes as $attrdefid=>$attribute) {
		if(!isset($newattributes[$attrdefid]) || $newattributes[$attrdefid]->getValueAsArray() !== $oldattributes[$attrdefid]->getValueAsArray()) {
			if($notifier) {
				$notifyList = $document->getNotifyList();
				$subject = "document_attribute_changed_email_subject";
				$message = "document_attribute_changed_email_body";
				$params = array();
				$params['name'] = $document->getName();
				$params['attribute_name'] = $attribute->getAttributeDefinition()->getName();
				$params['attribute_old_value'] = $oldattributes[$attrdefid]->getValue();
				$params['attribute_new_value'] = isset($newattributes[$attrdefid]) ? $newattributes[$attrdefid]->getValue() : '';
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
	}
}
/* Check for new attributes which didn't have a value before */
if($newattributes) {
	foreach($newattributes as $attrdefid=>$attribute) {
		if(!isset($oldattributes[$attrdefid]) && $attribute) {
			if($notifier) {
				$notifyList = $document->getNotifyList();
				$subject = "document_attribute_changed_email_subject";
				$message = "document_attribute_changed_email_body";
				$params = array();
				$params['name'] = $document->getName();
				$params['attribute_name'] = $dms->getAttributeDefinition($attrdefid)->getName();
				$params['attribute_old_value'] = '';
				$params['attribute_new_value'] = $attribute->getValue();
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
	}
}

$session->setSplashMsg(array('type'=>'success', 'msg'=>getMLText('splash_document_edited')));

add_log_line("?documentid=".$documentid);
header("Location:../out/out.ViewDocument.php?documentid=".$documentid);

?>
