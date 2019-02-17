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

if (!isset($_POST["presetexpdate"]) || $_POST["presetexpdate"] == "") {
	UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("invalid_expiration_date"));
}

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

if(isset($GLOBALS['LetoDMS_HOOKS']['setExpires'])) {
	foreach($GLOBALS['LetoDMS_HOOKS']['setExpires'] as $hookObj) {
		if (method_exists($hookObj, 'preSetExpires')) {
			$hookObj->preSetExpires(null, array('document'=>$document, 'expires'=>&$expires));
		}
	}
}

if (!$document->setExpires($expires)){
	UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("error_occured"));
}

$document->verifyLastestContentExpriry();

if(isset($GLOBALS['LetoDMS_HOOKS']['setExpires'])) {
	foreach($GLOBALS['LetoDMS_HOOKS']['setExpires'] as $hookObj) {
		if (method_exists($hookObj, 'postSetExpires')) {
			$hookObj->postSetExpires(null, array('document'=>$document, 'expires'=>$expires));
		}
	}
}

add_log_line("?documentid=".$documentid);

header("Location:../out/out.ViewDocument.php?documentid=".$documentid);

?>
