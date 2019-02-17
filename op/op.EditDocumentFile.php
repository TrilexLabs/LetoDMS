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
include("../inc/inc.ClassUI.php");
include("../inc/inc.ClassController.php");
include("../inc/inc.Authentication.php");

$tmp = explode('.', basename($_SERVER['SCRIPT_FILENAME']));
$controller = Controller::factory($tmp[1], array('dms'=>$dms, 'user'=>$user));

/* Check if the form data comes from a trusted request */
if(!checkFormKey('editdocumentfile')) {
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

if (!isset($_POST["fileid"]) || !is_numeric($_POST["fileid"]) || intval($_POST["fileid"])<1) {
	UI::exitError(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))),getMLText("invalid_file_id"));
}

$file = $document->getDocumentFile($_POST["fileid"]);

if (!is_object($file)) {
	UI::exitError(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))),getMLText("invalid_file_id"));
}

if (($document->getAccessMode($user, 'editDocumentFile') < M_ALL)&&($user->getID()!=$file->getUserID())) {
	UI::exitError(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))),getMLText("access_denied"));
}

$controller->setParam('document', $document);
$controller->setParam('file', $file);
$controller->setParam('name', isset($_POST['name']) ? $_POST['name'] : '');
$controller->setParam('comment', isset($_POST['comment']) ? $_POST['comment'] : '');
$controller->setParam('version', isset($_POST['version']) ? $_POST['version'] : '');
$controller->setParam('public', isset($_POST['public']) ? $_POST['public'] : '');
if(!$controller->run()) {
	if($controller->getErrorMsg()) {
		UI::exitError(getMLText("document_title", array("documentname" => $document->getName())), $controller->getErrorMsg());
	}
}

add_log_line("?documentid=".$documentid);

header("Location:../out/out.ViewDocument.php?documentid=".$documentid."&currenttab=attachments");


