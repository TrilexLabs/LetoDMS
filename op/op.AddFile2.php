<?php
//    MyDMS. Document Management System
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
			UI::exitError(getMLText("document_title", array("documentname" => getMLText("invalid_doc_id"))),getMLText("invalid_doc_id"));
		}

		$documentid = $_POST["documentid"];
		$document = $dms->getDocument($documentid);

		if (!is_object($document)) {
			UI::exitError(getMLText("document_title", array("documentname" => getMLText("invalid_doc_id"))),getMLText("invalid_doc_id"));
		}

		$folder = $document->getFolder();

		if ($document->getAccessMode($user) < M_READWRITE) {
			UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("access_denied"));
		}

		$userfiletmp = $settings->_stagingDir.$fileId;
		$userfiletype = $_FILES[ $file_param_name ]["type"];
		$userfilename = $_FILES[ $file_param_name ]["name"];

		if(isset($_POST["name"]) && $_POST["name"])
			$name = $_POST["name"];
		else
			$name = $userfilename;
		$comment  = $_POST["comment"];

		$fileType = ".".pathinfo($userfilename, PATHINFO_EXTENSION);

		if($settings->_overrideMimeType) {
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$userfiletype = finfo_file($finfo, $userfiletmp);
		}

		$res = $document->addDocumentFile($name, $comment, $user, $userfiletmp, 
																			utf8_basename($userfilename),$fileType, $userfiletype );
																		
		if (is_bool($res) && !$res) {
			UI::exitError(getMLText("folder_title", array("foldername" => $folder->getName())),getMLText("error_occured"));
		} else {
			if($notifier) {
				$notifyList = $document->getNotifyList();
				// Send notification to subscribers.

/*
				$subject = "###SITENAME###: ".$document->getName()." - ".getMLText("new_file_email");
				$message = getMLText("new_file_email")."\r\n";
				$message .= 
					getMLText("name").": ".$name."\r\n".
					getMLText("comment").": ".$comment."\r\n".
					getMLText("user").": ".$user->getFullName()." <". $user->getEmail() .">\r\n".	
					"URL: ###URL_PREFIX###out/out.ViewDocument.php?documentid=".$document->getID()."\r\n";

				$notifier->toList($user, $document->_notifyList["users"], $subject, $message);
				foreach ($document->_notifyList["groups"] as $grp) {
					$notifier->toGroup($user, $grp, $subject, $message);
				}
*/

				$subject = "new_file_email_subject";
				$message = "new_file_email_body";
				$params = array();
				$params['name'] = $name;
				$params['document'] = $document->getName();
				$params['username'] = $user->getFullName();
				$params['comment'] = $comment;
				$params['url'] = "http".((isset($_SERVER['HTTPS']) && (strcmp($_SERVER['HTTPS'],'off')!=0)) ? "s" : "")."://".$_SERVER['HTTP_HOST'].$settings->_httpRoot."out/out.ViewDocument.php?documentid=".$document->getID();
				$params['sitename'] = $settings->_siteName;
				$params['http_root'] = $settings->_httpRoot;
				$notifier->toList($user, $notifyList["users"], $subject, $message, $params);
				foreach ($notifyList["groups"] as $grp) {
					$notifier->toGroup($user, $grp, $subject, $message, $params);
				}
			}
		}
		add_log_line("?name=".$name."&documentid=".$documentid);
	}
}

?>
