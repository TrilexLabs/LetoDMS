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
include("../inc/inc.ClassCalendar.php");
include("../inc/inc.Authentication.php");

if ($user->isGuest()) {
	UI::exitError(getMLText("edit_event"),getMLText("access_denied"));
}

/* Check if the form data comes from a trusted request */
if(!checkFormKey('editevent')) {
	UI::exitError(getMLText("edit_event"),getMLText("invalid_request_token"));
}

if (!isset($_POST["from"]) && !(isset($_POST["frommonth"]) && isset($_POST["fromday"]) && isset($_POST["fromyear"])) ) {
	UI::exitError(getMLText("edit_event"),getMLText("error_occured"));
}

$calendar = new LetoDMS_Calendar($dms->getDB(), $user);

if (!isset($_POST["eventid"]) || !($event = $calendar->getEvent($_POST["eventid"]))) {
	UI::exitError(getMLText("edit_event"),getMLText("error_occured"));
}

/* Not setting name or comment will leave them untouched */
if (!isset($_POST["name"]))
	$name = null;
else
	$name = $_POST["name"];

if (!isset($_POST["comment"]))
	$comment = null;
else
	$comment = $_POST["comment"];

if(isset($_POST["from"])) {
	$from = explode('T', $_POST["from"]);
	$tmp = explode('-', $from[0]);
	$from = mktime(0,0,0, $tmp[1], $tmp[2], $tmp[0]);
} else {
	UI::exitError(getMLText("edit_event"),getMLText("error_occured"));
}
if(isset($_POST["to"])) {
	$tmp = explode('-', $_POST["to"]);
	$to = mktime(23,59,59, $tmp[1], $tmp[2], $tmp[0]);
} else {
	$to = $event['stop'] - $event['start'] + $from;;
}

if ($to !== null && $to<=$from){
	$to = $from + 86400 -1;
}

$res = $calendar->editEvent($_POST["eventid"], $from, $to, $name, $comment );

if(isset($_POST["ajax"]) && $_POST["ajax"]) {
	header('Content-Type: application/json');
	if (is_bool($res) && !$res)
		echo json_encode(array('success'=>false, 'message'=>getMLText('error_occured')));
	else {
		echo json_encode(array('success'=>true, 'message'=>getMLText('splash_edit_event')));
		add_log_line("?eventid=".$_POST["eventid"]."&name=".$name."&from=".$from."&to=".$to);
	}
} else {
	if (is_bool($res) && !$res) {
		UI::exitError(getMLText("edit_event"),getMLText("error_occured"));
	}

	$session->setSplashMsg(array('type'=>'success', 'msg'=>getMLText('splash_edit_event')));

	add_log_line("?eventid=".$_POST["eventid"]."&name=".$name."&from=".$from."&to=".$to);

	header("Location:../out/out.Calendar.php?mode=w&day=".$_POST["fromday"]."&year=".$_POST["fromyear"]."&month=".$_POST["frommonth"]);
}

?>
