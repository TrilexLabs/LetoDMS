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
include("../inc/inc.Utils.php");
include("../inc/inc.Init.php");
include("../inc/inc.Extension.php");
include("../inc/inc.DBInit.php");
include("../inc/inc.ClassUI.php");
include("../inc/inc.Authentication.php");

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

if ($document->getAccessMode($user, 'editOnline') < M_READWRITE) {
	UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("access_denied"));
}

if($document->isLocked()) {
	$lockingUser = $document->getLockingUser();
	if (($lockingUser->getID() != $user->getID()) && ($document->getAccessMode($user, 'editOnline') != M_ALL)) {
		UI::exitError(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))),getMLText("lock_message", array("email" => $lockingUser->getEmail(), "username" => htmlspecialchars($lockingUser->getFullName()))));
	}
}

$tmpfname = tempnam(sys_get_temp_dir(), 'FOO');
file_put_contents($tmpfname, $_POST['data']);

/* Check if the uploaded file is identical to last version */
$lc = $document->getLatestContent();
if($lc->getChecksum() == LetoDMS_Core_File::checksum($tmpfname)) {
	echo json_encode(array('success'=>false, 'message'=>getMLText('identical_version')));
} else {
	if($document->replaceContent(0, $user, $tmpfname, $lc->getOriginalFileName(), $lc->getFileType(), $lc->getMimeType())) {
		if($notifier) {
			$notifyList = $folder->getNotifyList();

			$subject = "replace_content_email_subject";
			$message = "replace_content_email_body";
			$params = array();
			$params['name'] = $document->getName();
			$params['folder_name'] = $folder->getName();
			$params['folder_path'] = $folder->getFolderPathPlain();
			$params['username'] = $user->getFullName();
			$params['comment'] = $document->getComment();
			$params['version'] = $lc->getVersion();
			$params['version_comment'] = $lc->getComment();
			$params['url'] = "http".((isset($_SERVER['HTTPS']) && (strcmp($_SERVER['HTTPS'],'off')!=0)) ? "s" : "")."://".$_SERVER['HTTP_HOST'].$settings->_httpRoot."out/out.ViewDocument.php?documentid=".$document->getID();
			$params['sitename'] = $settings->_siteName;
			$params['http_root'] = $settings->_httpRoot;
			$notifier->toList($user, $notifyList["users"], $subject, $message, $params);
			foreach ($notifyList["groups"] as $grp) {
				$notifier->toGroup($user, $grp, $subject, $message, $params);
			}
		}
		echo json_encode(array('success'=>true, 'message'=>getMLText('splash_saved_file')));
	} else {
		echo json_encode(array('success'=>false, 'message'=>getMLText('splash_error_saving_file')));
	}
}
unlink($tmpfname);
