<?php
//    MyDMS. Document Management System
//    Copyright (C) 2002-2005 Markus Westphal
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
include("../inc/inc.ClassAccessOperation.php");
include("../inc/inc.Authentication.php");

$tmp = explode('.', basename($_SERVER['SCRIPT_FILENAME']));
$view = UI::factory($theme, $tmp[1], array('dms'=>$dms, 'user'=>$user));

$documentid = $_GET["documentid"];

if (!isset($documentid) || !is_numeric($documentid) || intval($documentid)<1) {
	UI::exitError(getMLText("document_title", array("documentname" => getMLText("invalid_doc_id"))),getMLText("invalid_doc_id"));
}

$document = $dms->getDocument($documentid);

if (!is_object($document)) {
	UI::exitError(getMLText("document_title", array("documentname" => getMLText("invalid_doc_id"))),getMLText("invalid_doc_id"));
}

if ($document->getAccessMode($user) < M_READWRITE) {
	UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("access_denied"));
}

if(isset($_GET["version"])) {
	$version = $_GET["version"];

	if (!is_numeric($version)) {
		UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("invalid_version"));
	}
	
	$content = $document->getContentByVersion($version);
	$lc = $document->getLatestContent();

}	else {
	$version = 0;
	$content = $document->getLatestContent();
	$lc = $document->getLatestContent();
}

if (!is_object($content)) {
	UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("invalid_version"));
}

/* Only the latest version may be edited */
if($content->getVersion() != $lc->getVersion()) {
	UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("invalid_version"));
}

/*
if (!isset($settings->_editOnlineFileTypes) || !is_array($settings->_editOnlineFileTypes) || !in_array(strtolower($content->getFileType()), $settings->_editOnlineFileTypes)) {
	UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("invalid_version"));
}
 */

/* Create object for checking access to certain operations */
$accessop = new LetoDMS_AccessOperation($dms, $document, $user, $settings);
if(!$accessop->mayEditVersion($version)) {
	UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("access_denied"));
}

$folder = $document->getFolder();

if($view) {
	$view->setParam('document', $document);
	$view->setParam('version', $content);
	$view->setParam('folder', $folder);
	$view->setParam('accessobject', $accessop);
	$view->setParam('cachedir', $settings->_cacheDir);
	$view->setParam('previewWidthList', $settings->_previewWidthList);
	$view->setParam('previewWidthDetail', $settings->_previewWidthDetail);
	$view->setParam('timeout', $settings->_cmdTimeout);
	$view($_GET);
	exit;
}

