<?php
//    MyDMS. Document Management System
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
include("../inc/inc.ClassCalendar.php");
include("../inc/inc.Language.php");
include("../inc/inc.Init.php");
include("../inc/inc.Extension.php");
include("../inc/inc.DBInit.php");
include("../inc/inc.ClassUI.php");
include("../inc/inc.Authentication.php");

$tmp = explode('.', basename($_SERVER['SCRIPT_FILENAME']));
$view = UI::factory($theme, $tmp[1], array('dms'=>$dms, 'user'=>$user));

if (isset($_GET["start"])) $start=$_GET["start"];
else $start = '';
if (isset($_GET["end"])) $end=$_GET["end"];
else $end = '';

if(isset($_GET['documentid']) && $_GET['documentid'] && is_numeric($_GET['documentid'])) {
	$document = $dms->getDocument($_GET["documentid"]);
	if (!is_object($document)) {
		UI::exitError(getMLText("document_title", array("documentname" => getMLText("invalid_doc_id"))),getMLText("invalid_doc_id"));
	}
} else
	$document = null;

$calendar = new LetoDMS_Calendar($dms->getDB(), $user);

if(isset($_GET['eventid']) && $_GET['eventid'] && is_numeric($_GET['eventid'])) {
	$event = $calendar->getEvent($_GET["eventid"]);
} else
	$event = null;

if(isset($_GET['version']) && $_GET['version'] && is_numeric($_GET['version'])) {
	$content = $document->getContentByVersion($_GET['version']);
} else
	$content = null;

if(isset($_GET['eventtype']) && $_GET['eventtype']) {
	$eventtype = $_GET['eventtype'];
} else
	$eventtype = 'regular';

if($view) {
	$view->setParam('calendar', $calendar);
	$view->setParam('start', $start);
	$view->setParam('end', $end);
	$view->setParam('document', $document);
	$view->setParam('version', $content);
	$view->setParam('event', $event);
	$view->setParam('showtree', showtree());
	$view->setParam('strictformcheck', $settings->_strictFormCheck);
	$view->setParam('eventtype', $eventtype);
	$view->setParam('cachedir', $settings->_cacheDir);
	$view->setParam('previewWidthList', $settings->_previewWidthList);
	$view->setParam('previewWidthDetail', $settings->_previewWidthDetail);
	$view->setParam('timeout', $settings->_cmdTimeout);
	$view($_GET);
	exit;
}

?>
