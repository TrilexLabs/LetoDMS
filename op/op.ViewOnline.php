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

$documentid = $_GET["documentid"];
if (!isset($documentid) || !is_numeric($documentid) || intval($documentid)<1) {
	UI::exitError(getMLText("document_title", array("documentname" => getMLText("invalid_doc_id"))),getMLText("invalid_doc_id"));
}

$document = $dms->getDocument($documentid);

if (!is_object($document)) {
	UI::exitError(getMLText("document_title", array("documentname" => getMLText("invalid_doc_id"))),getMLText("invalid_doc_id"));
}

if ($document->getAccessMode($user) < M_READ) {
	UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("access_denied"));
}

if(isset($_GET["version"])) {
	$version = $_GET["version"];

	if (!is_numeric($version)) {
		UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("invalid_version"));
	}
	
	if(intval($version)<1)
		$content = $document->getLatestContent();
	else
		$content = $document->getContentByVersion($version);

	if (!is_object($content)) {
		UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("invalid_version"));
	}

	$controller->setParam('content', $content);
	$controller->setParam('type', 'version');
	$controller->run();
} elseif(isset($_GET["file"])) {
	$fileid = $_GET["file"];

	if (!is_numeric($fileid) || intval($fileid)<1) {
		UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("invalid_version"));
	}

	$file = $document->getDocumentFile($fileid);

	if (!is_object($file)) {
		UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("invalid_file_id"));
	}

	if (isset($settings->_viewOnlineFileTypes) && is_array($settings->_viewOnlineFileTypes) && in_array(strtolower($file->getFileType()), $settings->_viewOnlineFileTypes)) {
		header("Content-Type: " . $file->getMimeType());
	}
	header("Content-Disposition: filename=\"" . $file->getOriginalFileName() . "\"");
	header("Content-Length: " . filesize($dms->contentDir . $file->getPath() ));
	header("Expires: 0");
	header("Cache-Control: no-cache, must-revalidate");
	header("Pragma: no-cache");

	ob_clean();
	readfile($dms->contentDir . $file->getPath());
}

add_log_line();
exit;
?>
