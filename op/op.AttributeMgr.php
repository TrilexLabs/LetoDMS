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
include("../inc/inc.Language.php");
include("../inc/inc.Init.php");
include("../inc/inc.Extension.php");
include("../inc/inc.DBInit.php");
include("../inc/inc.ClassUI.php");
include("../inc/inc.ClassController.php");
include("../inc/inc.Authentication.php");

$tmp = explode('.', basename($_SERVER['SCRIPT_FILENAME']));
$controller = Controller::factory($tmp[1], array('dms'=>$dms, 'user'=>$user));
if (!$user->isAdmin()) {
	UI::exitError(getMLText("admin_tools"),getMLText("access_denied"));
}

if (isset($_POST["action"])) $action=$_POST["action"];
else $action=NULL;

if(!in_array($action, array('addattrdef', 'removeattrdef', 'editattrdef', 'removeattrvalue')))
  UI::exitError(getMLText("admin_tools"),getMLText("unknown_command"));

/* Check if the form data comes from a trusted request */
if(!checkFormKey($action)) {
	UI::exitError(getMLText("admin_tools"),getMLText("invalid_request_token"));
}

// add new attribute definition ---------------------------------------------
if ($action == "addattrdef") {

	$name = trim($_POST["name"]);
	$type = intval($_POST["type"]);
	$objtype = intval($_POST["objtype"]);
	if(isset($_POST["multiple"]))
		$multiple = trim($_POST["multiple"]);
	else
		$multiple = 0;
	$minvalues = intval($_POST["minvalues"]);
	$maxvalues = intval($_POST["maxvalues"]);
	$valueset = trim($_POST["valueset"]);
	$regex = trim($_POST["regex"]);

	if($name == '') {
		UI::exitError(getMLText("admin_tools"),getMLText("attrdef_noname"));
	}
	if (is_object($dms->getAttributeDefinitionByName($name))) {
		UI::exitError(getMLText("admin_tools"),getMLText("attrdef_exists"));
	}
	if($minvalues > 1 && $multiple == 0) {
		UI::exitError(getMLText("admin_tools"),getMLText("attrdef_must_be_multiple"));
	}
	if($minvalues > $maxvalues) {
		UI::exitError(getMLText("admin_tools"),getMLText("attrdef_min_greater_max"));
	}
	if($multiple && $valueset == '') {
		UI::exitError(getMLText("admin_tools"),getMLText("attrdef_multiple_needs_valueset"));
	}

	$controller->setParam('name', $name);
	$controller->setParam('type', $type);
	$controller->setParam('objtype', $objtype);
	$controller->setParam('multiple', $multiple);
	$controller->setParam('minvalues', $minvalues);
	$controller->setParam('maxvalues', $maxvalues);
	$controller->setParam('valueset', $valueset);
	$controller->setParam('regex', $regex);
	if (!($newAttrdef = $controller($_POST))) {
		UI::exitError(getMLText("admin_tools"),getMLText("error_occured"));
	}

	$attrdefid=$newAttrdef->getID();

	$session->setSplashMsg(array('type'=>'success', 'msg'=>getMLText('splash_add_attribute')));

	add_log_line("&action=addattrdef&name=".$name);
}

// delete attribute definition -----------------------------------------------
else if ($action == "removeattrdef") {

	if (!isset($_POST["attrdefid"]) || !is_numeric($_POST["attrdefid"]) || intval($_POST["attrdefid"])<1) {
		UI::exitError(getMLText("admin_tools"),getMLText("unknown_attrdef"));
	}
	$attrdefid = $_POST["attrdefid"];
	$attrdef = $dms->getAttributeDefinition($attrdefid);
	if (!is_object($attrdef)) {
		UI::exitError(getMLText("admin_tools"),getMLText("unknown_attrdef"));
	}

	$controller->setParam('attrdef', $attrdef);
	if (!$controller($_POST)) {
		UI::exitError(getMLText("admin_tools"),getMLText("error_occured"));
	}

	$session->setSplashMsg(array('type'=>'success', 'msg'=>getMLText('splash_rm_attribute')));

	add_log_line("&action=removeattrdef&attrdefid=".$attrdefid);

	$attrdefid=-1;
}

// edit attribute definition -----------------------------------------------
else if ($action == "editattrdef") {

	if (!isset($_POST["attrdefid"]) || !is_numeric($_POST["attrdefid"]) || intval($_POST["attrdefid"])<1) {
		UI::exitError(getMLText("admin_tools"),getMLText("unknown_attrdef"));
	}
	$attrdefid = $_POST["attrdefid"];
	$attrdef = $dms->getAttributeDefinition($attrdefid);
	if (!is_object($attrdef)) {
		UI::exitError(getMLText("admin_tools"),getMLText("unknown_attrdef"));
	}

	$name = $_POST["name"];
	$type = intval($_POST["type"]);
	$objtype = intval($_POST["objtype"]);
	if(isset($_POST["multiple"]))
		$multiple = trim($_POST["multiple"]);
	else
		$multiple = 0;
	$minvalues = intval($_POST["minvalues"]);
	$maxvalues = intval($_POST["maxvalues"]);
	$valueset = trim($_POST["valueset"]);
	$regex = trim($_POST["regex"]);

	if($minvalues > 1 && $multiple == 0) {
		UI::exitError(getMLText("admin_tools"),getMLText("attrdef_must_be_multiple"));
	}
	if($minvalues > $maxvalues) {
		UI::exitError(getMLText("admin_tools"),getMLText("attrdef_min_greater_max"));
	}
	if($multiple && $valueset == '') {
		UI::exitError(getMLText("admin_tools"),getMLText("attrdef_multiple_needs_valueset"));
	}

	$controller->setParam('name', $name);
	$controller->setParam('type', $type);
	$controller->setParam('objtype', $objtype);
	$controller->setParam('multiple', $multiple);
	$controller->setParam('minvalues', $minvalues);
	$controller->setParam('maxvalues', $maxvalues);
	$controller->setParam('valueset', $valueset);
	$controller->setParam('regex', $regex);
	$controller->setParam('attrdef', $attrdef);
	if (!$controller($_POST)) {
		UI::exitError(getMLText("admin_tools"),getMLText("error_occured"));
	}

	$session->setSplashMsg(array('type'=>'success', 'msg'=>getMLText('splash_edit_attribute')));

	add_log_line("&action=editattrdef&attrdefid=".$attrdefid);
}

// remove attribute value -----------------------------------------------
else if ($action == "removeattrvalue") {

	if (!isset($_POST["attrdefid"]) || !is_numeric($_POST["attrdefid"]) || intval($_POST["attrdefid"])<1) {
		UI::exitError(getMLText("admin_tools"),getMLText("unknown_attrdef"));
	}
	$attrdefid = $_POST["attrdefid"];
	$attrdef = $dms->getAttributeDefinition($attrdefid);
	if (!is_object($attrdef)) {
		UI::exitError(getMLText("admin_tools"),getMLText("unknown_attrdef"));
	}

	$attrval = $_POST["attrvalue"];

	$controller->setParam('attrval', $attrval);
	$controller->setParam('attrdef', $attrdef);
	if (!$controller($_POST)) {
		header('Content-Type: application/json');
		echo json_encode(array('success'=>false, 'message'=>getMLText('error_occured')));
	} else {
		header('Content-Type: application/json');
		echo json_encode(array('success'=>true, 'message'=>getMLText('splash_rm_attr_value')));
	}

	add_log_line("&action=removeattrvalue&attrdefid=".$attrdefid);
	exit;

} else {
	UI::exitError(getMLText("admin_tools"),getMLText("unknown_command"));
}

header("Location:../out/out.AttributeMgr.php?attrdefid=".$attrdefid);

?>

