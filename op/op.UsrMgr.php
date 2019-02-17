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
include("../inc/inc.Authentication.php");
include("../inc/inc.ClassPasswordStrength.php");

if (!$user->isAdmin()) {
	UI::exitError(getMLText("admin_tools"),getMLText("access_denied"));
}

if (isset($_POST["action"])) $action=$_POST["action"];
else $action=NULL;

// add new user ---------------------------------------------------------
if ($action == "adduser") {
	
	/* Check if the form data comes from a trusted request */
	if(!checkFormKey('adduser')) {
		UI::exitError(getMLText("admin_tools"),getMLText("invalid_request_token"));
	}

	$login   = $_POST["login"];
	$pwd     = $_POST["pwd"];
	if(!isset($_POST["pwdexpiration"]))
		$pwdexpiration = '';
	else
		$pwdexpiration = $_POST["pwdexpiration"];
	if(!isset($_POST["quota"]))
		$quota = 0;
	else
		$quota = (int) $_POST["quota"];
	$name    = $_POST["name"];
	$email   = $_POST["email"];
	$comment = $_POST["comment"];
	$role    = preg_replace('/[^0-2]+/', '', $_POST["role"]);
	$isHidden = (isset($_POST["ishidden"]) && $_POST["ishidden"]==1 ? 1 : 0);
	$isDisabled = (isset($_POST["isdisabled"]) && $_POST["isdisabled"]==1 ? 1 : 0);
	$homefolder = (isset($_POST["homefolder"]) ? $_POST["homefolder"] : 0);
	$quota = (isset($_POST["quota"]) ? (int) $_POST["quota"] : 0);

	if (is_object($dms->getUserByLogin($login))) {
		UI::exitError(getMLText("admin_tools"),getMLText("user_exists"));
	}

	$newUser = $dms->addUser($login, md5($pwd), $name, $email, $settings->_language, $settings->_theme, $comment, $role, $isHidden, $isDisabled, $pwdexpiration, $quota, $homefolder);
	if ($newUser) {

		/* Set user image if uploaded */
		if (isset($_FILES["userfile"]) && is_uploaded_file($_FILES["userfile"]["tmp_name"]) && $_FILES["userfile"]["size"] > 0 && $_FILES['userfile']['error']==0)
		{
			$userfiletype = $_FILES["userfile"]["type"];
			$userfilename = $_FILES["userfile"]["name"];
			$fileType = ".".pathinfo($userfilename, PATHINFO_EXTENSION);
			if ($fileType != ".jpg" && $filetype != ".jpeg") {
				UI::exitError(getMLText("admin_tools"),getMLText("only_jpg_user_images"));
			} else {
				resizeImage($_FILES["userfile"]["tmp_name"]);
				$newUser->setImage($_FILES["userfile"]["tmp_name"], $userfiletype);
			}
		}

		/* Set groups if set */
		if(isset($_POST["groups"]) && $_POST["groups"]) {
			foreach($_POST["groups"] as $groupid) {
				$group = $dms->getGroup($groupid);
				$group->addUser($newUser);
			}
		}
	}
	else UI::exitError(getMLText("admin_tools"),getMLText("access_denied"));
	
	if(isset($_POST["workflows"]) && $_POST["workflows"]) {
		$workflows = array();
		foreach($_POST["workflows"] as $workflowid)
			if($tmp = $dms->getWorkflow($workflowid))
				$workflows[] = $tmp;
		if($workflows)
			$newUser->setMandatoryWorkflows($workflows);
	}

	if (isset($_POST["usrReviewers"])){
		foreach ($_POST["usrReviewers"] as $revID) 
			$newUser->setMandatoryReviewer($revID,false);
	}
	
	if (isset($_POST["grpReviewers"])){
		foreach ($_POST["grpReviewers"] as $revID) 
			$newUser->setMandatoryReviewer($revID,true);
	}
		
	if (isset($_POST["usrApprovers"])){
		foreach ($_POST["usrApprovers"] as $appID) 
			$newUser->setMandatoryApprover($appID,false);
	}
			
	if (isset($_POST["grpApprovers"])){
		foreach ($_POST["grpApprovers"] as $appID) 
			$newUser->setMandatoryApprover($appID,true);
	}
	
	$userid=$newUser->getID();
	
	$session->setSplashMsg(array('type'=>'success', 'msg'=>getMLText('splash_add_user')));

	add_log_line(".php&action=adduser&login=".$login);
}

// delete user ------------------------------------------------------------
else if ($action == "removeuser") {

	/* Check if the form data comes from a trusted request */
	if(!checkFormKey('removeuser')) {
		UI::exitError(getMLText("admin_tools"),getMLText("invalid_request_token"));
	}

	if (isset($_POST["userid"])) {
		$userid = $_POST["userid"];
	}

	if (!isset($userid) || !is_numeric($userid) || intval($userid)<1) {
		UI::exitError(getMLText("admin_tools"),getMLText("invalid_user_id"));
	}

	if(in_array($userid, explode(',', $settings->_undelUserIds))) {
		UI::exitError(getMLText("admin_tools"),getMLText("cannot_delete_user"));
	}

	/* This used to be a check if an admin is deleted. Now it checks if one
	 * wants to delete herself.
	 */
	if ($userid==$user->getID()) {
		UI::exitError(getMLText("admin_tools"),getMLText("cannot_delete_yourself"));
	}

	$userToRemove = $dms->getUser($userid);
	if (!is_object($userToRemove)) {
		UI::exitError(getMLText("admin_tools"),getMLText("invalid_user_id"));
	}

	$userToAssign = $dms->getUser($_POST["assignTo"]);
	if (!$userToRemove->remove($user, $userToAssign)) {
		UI::exitError(getMLText("admin_tools"),getMLText("error_occured"));
	}
		
	add_log_line(".php&action=removeuser&userid=".$userid);
	
	$session->setSplashMsg(array('type'=>'success', 'msg'=>getMLText('splash_rm_user')));
	$userid=-1;
}

// remove user from all processes (approval, review)
else if ($action == "removefromprocesses") {

	/* Check if the form data comes from a trusted request */
	if(!checkFormKey('removefromprocesses')) {
		UI::exitError(getMLText("admin_tools"),getMLText("invalid_request_token"));
	}

	if (isset($_POST["userid"])) {
		$userid = $_POST["userid"];
	}

	if (!isset($userid) || !is_numeric($userid) || intval($userid)<1) {
		UI::exitError(getMLText("admin_tools"),getMLText("invalid_user_id"));
	}

	/* This used to be a check if an admin is deleted. Now it checks if one
	 * wants to delete herself.
	 */
	if ($userid==$user->getID()) {
		UI::exitError(getMLText("admin_tools"),getMLText("cannot_delete_yourself"));
	}

	$userToRemove = $dms->getUser($userid);
	if (!is_object($userToRemove)) {
		UI::exitError(getMLText("admin_tools"),getMLText("invalid_user_id"));
	}

	if(isset($_POST["status"]) && is_array($_POST["status"]) && $_POST["status"]) {
		if(!isset($_POST["status"]["review"]))
			$_POST["status"]["review"] = array();
		if(!isset($_POST["status"]["approval"]))
			$_POST["status"]["approval"] = array();
		if (!$userToRemove->removeFromProcesses($user, $_POST['status'])) {
			UI::exitError(getMLText("admin_tools"),getMLText("error_occured"));
		}

		add_log_line(".php&action=removefromprocesses&userid=".$userid);
		
		$session->setSplashMsg(array('type'=>'success', 'msg'=>getMLText('splash_rm_user_processes')));
	}
}

// transfer all objects from one user to another one
else if ($action == "transferobjects") {

	/* Check if the form data comes from a trusted request */
	if(!checkFormKey('transferobjects')) {
		UI::exitError(getMLText("admin_tools"),getMLText("invalid_request_token"));
	}

	if (isset($_POST["userid"])) {
		$userid = $_POST["userid"];
	}

	if (!isset($userid) || !is_numeric($userid) || intval($userid)<1) {
		UI::exitError(getMLText("admin_tools"),getMLText("invalid_user_id"));
	}

	/* Check if one  wants to transfer his/her own objects.
	 */
	if ($userid==$user->getID()) {
		UI::exitError(getMLText("admin_tools"),getMLText("cannot_transfer_your_objects"));
	}

	$userToRemove = $dms->getUser($userid);
	if (!is_object($userToRemove)) {
		UI::exitError(getMLText("admin_tools"),getMLText("invalid_user_id"));
	}

	$userToAssign = $dms->getUser($_POST["assignTo"]);
		
//	if(isset($_POST["status"]) && is_array($_POST["status"]) && $_POST["status"]) {
		if (!$userToRemove->transferDocumentsFolders($userToAssign)) {
			UI::exitError(getMLText("admin_tools"),getMLText("error_occured"));
		}

		if (!$userToRemove->transferEvents($userToAssign)) {
			UI::exitError(getMLText("admin_tools"),getMLText("error_occured"));
		}

		add_log_line(".php&action=transferobjects&userid=".$userid);
		
		$session->setSplashMsg(array('type'=>'success', 'msg'=>getMLText('splash_transfer_objects')));
//	}
}

// send login data to user
else if ($action == "sendlogindata" && $settings->_enableEmail) {
	/* Check if the form data comes from a trusted request */
	if(!checkFormKey('sendlogindata')) {
		UI::exitError(getMLText("admin_tools"),getMLText("invalid_request_token"));
	}

	if (isset($_POST["userid"])) {
		$userid = $_POST["userid"];
	}

	$comment = '';
	if (isset($_POST["comment"])) {
		$comment = $_POST["comment"];
	}

	if (!isset($userid) || !is_numeric($userid) || intval($userid)<1) {
		UI::exitError(getMLText("admin_tools"),getMLText("invalid_user_id"));
	}

	$newuser = $dms->getUser($userid);
	if (!is_object($newuser)) {
		UI::exitError(getMLText("admin_tools"),getMLText("invalid_user_id"));
	}

	if($notifier) {
		$subject = "send_login_data_subject";
		$message = "send_login_data_body";
		$params = array();
		$params['username'] = $newuser->getFullName();
		$params['login'] = $newuser->getLogin();
		$params['comment'] = $comment;
		$params['url'] = "http".((isset($_SERVER['HTTPS']) && (strcmp($_SERVER['HTTPS'],'off')!=0)) ? "s" : "")."://".$_SERVER['HTTP_HOST'].$settings->_httpRoot."out/out.ViewFolder.php";
		$params['sitename'] = $settings->_siteName;
		$params['http_root'] = $settings->_httpRoot;
		$notifier->toIndividual($user, $newuser, $subject, $message, $params);
	}
	add_log_line(".php&action=sendlogindata&userid=".$userid);
		
	$session->setSplashMsg(array('type'=>'success', 'msg'=>getMLText('splash_send_login_data')));
}

// modify user ------------------------------------------------------------
else if ($action == "edituser") {

	/* Check if the form data comes from a trusted request */
	if(!checkFormKey('edituser')) {
		UI::exitError(getMLText("admin_tools"),getMLText("invalid_request_token"));
	}

	if (!isset($_POST["userid"]) || !is_numeric($_POST["userid"]) || intval($_POST["userid"])<1) {
		UI::exitError(getMLText("admin_tools"),getMLText("invalid_user_id"));
	}
	
	$userid=$_POST["userid"];
	$editedUser = $dms->getUser($userid);
	
	if (!is_object($editedUser)) {
		UI::exitError(getMLText("admin_tools"),getMLText("invalid_user_id"));
	}

	$login   = $_POST["login"];
	$pwd     = $_POST["pwd"];
	if(isset($_POST['clearpwd']) && $_POST['clearpwd'])
		$clearpwd = 1;
	else
		$clearpwd = 0;
	if(isset($_POST["pwdexpiration"]))
		$pwdexpiration = $_POST["pwdexpiration"];
	else
		$pwdexpiration = '';
	if(!isset($_POST["quota"]))
		$quota = 0;
	else
		$quota = (int) $_POST["quota"];
	$name    = $_POST["name"];
	$email   = $_POST["email"];
	$comment = $_POST["comment"];
	$role    = preg_replace('/[^0-2]+/', '', $_POST["role"]);
	$isHidden = (isset($_POST["ishidden"]) && $_POST["ishidden"]==1 ? 1 : 0);
	$isDisabled = (isset($_POST["isdisabled"]) && $_POST["isdisabled"]==1 ? 1 : 0);
	$homefolder = (isset($_POST["homefolder"]) ? $_POST["homefolder"] : 0);
	$quota = (isset($_POST["quota"]) ? (int) $_POST["quota"] : 0);
	
	if (isset($pwd) && ($pwd != "")) {
		if($settings->_passwordStrength) {
			$ps = new Password_Strength();
			$ps->set_password($pwd);
			if($settings->_passwordStrengthAlgorithm == 'simple')
				$ps->simple_calculate();
			else
				$ps->calculate();
			$score = $ps->get_score();
			if($score < $settings->_passwordStrength) {
				UI::exitError(getMLText("set_password"),getMLText("password_strength_insuffient"));
			}
		}
	}
	if ($editedUser->getLogin() != $login)
		$editedUser->setLogin($login);
	if($pwdexpiration)
		$editedUser->setPwdExpiration($pwdexpiration);
	if(($role == LetoDMS_Core_User::role_guest) && $clearpwd) {
		$editedUser->setPwd('');
	} else {
		if (isset($pwd) && ($pwd != "")) {
			$editedUser->setPwd(md5($pwd));
		}
	}
	if ($editedUser->getFullName() != $name)
		$editedUser->setFullName($name);
	if ($editedUser->getEmail() != $email)
		$editedUser->setEmail($email);
	if ($editedUser->getComment() != $comment)
		$editedUser->setComment($comment);
	if ($editedUser->getRole() != $role)
		$editedUser->setRole($role);
	if ($editedUser->getQuota() != $quota)
		$editedUser->setQuota($quota);
	if ($editedUser->isHidden() != $isHidden)
		$editedUser->setHidden($isHidden);
	if ($editedUser->isDisabled() != $isDisabled) {
		$editedUser->setDisabled($isDisabled);
		if(!$isDisabled)
			$editedUser->clearLoginFailures();
	}
	if ($editedUser->getHomeFolder() != $homefolder)
		$editedUser->setHomeFolder($homefolder);
	if ($editedUser->getQuota() != $quota)
		$editedUser->setQuota($quota);
	if(isset($_POST["workflows"]) && $_POST["workflows"]) {
		$workflows = array();
		foreach($_POST["workflows"] as $workflowid) {
			if($tmp = $dms->getWorkflow($workflowid))
				$workflows[] = $tmp;
		}
		if($workflows)
			$editedUser->setMandatoryWorkflows($workflows);
	} else {
		$editedUser->delMandatoryWorkflow();
	}

	if (isset($_FILES['userfile']) && is_uploaded_file($_FILES["userfile"]["tmp_name"]) && $_FILES["userfile"]["size"] > 0 && $_FILES['userfile']['error']==0)
	{
		$userfiletype = $_FILES["userfile"]["type"];
		$userfilename = $_FILES["userfile"]["name"];
		$fileType = ".".pathinfo($userfilename, PATHINFO_EXTENSION);
		if ($fileType != ".jpg" && $filetype != ".jpeg") {
			UI::exitError(getMLText("admin_tools"),getMLText("only_jpg_user_images"));
		}
		else {
			resizeImage($_FILES["userfile"]["tmp_name"]);
			$editedUser->setImage($_FILES["userfile"]["tmp_name"], $userfiletype);
		}
	}
	
	$editedUser->delMandatoryReviewers();
	
	if (isset($_POST["usrReviewers"])) foreach ($_POST["usrReviewers"] as $revID) 
		$editedUser->setMandatoryReviewer($revID,false);
			
	if (isset($_POST["grpReviewers"])) foreach ($_POST["grpReviewers"] as $revID) 
		$editedUser->setMandatoryReviewer($revID,true);

	$editedUser->delMandatoryApprovers();
	
	if (isset($_POST["usrApprovers"])) foreach ($_POST["usrApprovers"] as $appID) 
		$editedUser->setMandatoryApprover($appID,false);
			
	if (isset($_POST["grpApprovers"])) foreach ($_POST["grpApprovers"] as $appID) 
		$editedUser->setMandatoryApprover($appID,true);
	
	/* Updates groups */
	if(isset($_POST["groups"]))
		$newgroups = $_POST["groups"];
	else
		$newgroups = array();
	$oldgroups = array();
	foreach($editedUser->getGroups() as $k)
		$oldgroups[] = $k->getID();

	$addgroups = array_diff($newgroups, $oldgroups);
	foreach($addgroups as $groupid) {
		$group = $dms->getGroup($groupid);
		$group->addUser($editedUser);
	}
	$delgroups = array_diff($oldgroups, $newgroups);
	foreach($delgroups as $groupid) {
		$group = $dms->getGroup($groupid);
		$group->removeUser($editedUser);
	}

	$session->setSplashMsg(array('type'=>'success', 'msg'=>getMLText('splash_edit_user')));
	add_log_line(".php&action=edituser&userid=".$userid);
}
else UI::exitError(getMLText("admin_tools"),getMLText("unknown_command"));


function resizeImage($imageFile) {

	// Not perfect. Creates a new image even if the old one is acceptable,
	// and the output quality is low. Now uses the function imagecreatetruecolor(),
	// though, so at least the pictures are in colour.
	
	// read original image
	$origImg = imagecreatefromjpeg($imageFile);
	$width = imagesx($origImg);
	$height = imagesy($origImg);
	// Create thumbnail in memory
	$newHeight = 150;
	$newWidth = ($width/$height) * $newHeight;
	$newImg = imagecreatetruecolor($newWidth, $newHeight);
	// resize
	imagecopyresized($newImg, $origImg, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
	// save to file
	imagejpeg($newImg, $imageFile);
	// Clean up
	imagedestroy($origImg);
	imagedestroy($newImg);
	
	return true;
}

header("Location:../out/out.UsrMgr.php?userid=".$userid);

?>
