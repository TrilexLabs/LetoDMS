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
include("../inc/inc.Utils.php");
include("../inc/inc.Language.php");
include("../inc/inc.Init.php");
include("../inc/inc.Extension.php");
include("../inc/inc.DBInit.php");
include("../inc/inc.ClassSession.php");
include("../inc/inc.ClassUI.php");
include("../inc/inc.ClassEmailNotify.php");

include $settings->_rootDir . "languages/" . $settings->_language . "/lang.inc";

function _printMessage($heading, $message) {

	UI::htmlStartPage($heading, "password");
	UI::globalBanner();
	UI::pageNavigation($heading);
	UI::contentContainer($message."<p><a href=\"../out/out.Login.php\">" . getMLText("login") . "</a></p>\n");
			UI::htmlEndPage();
	return;
}

if (isset($_POST["email"])) {
	$email = $_POST["email"];
}
if (isset($_POST["login"])) {
	$login = $_POST["login"];
}

if (empty($email) || empty($login)) {
	UI::exitError(getMLText("password_forgotten"),getMLText("no_email_or_login"));
}

$user = $dms->getUserByLogin($login, $email);
if($user) {
	if($hash = $dms->createPasswordRequest($user)) {
		$emailobj = new LetoDMS_EmailNotify($dms, $settings->_smtpSendFrom, $settings->_smtpServer, $settings->_smtpPort, $settings->_smtpUser, $settings->_smtpPassword);
		$subject = "password_forgotten_email_subject";
		$message = "password_forgotten_email_body";
		$params = array();
		$params['sitename'] = $settings->_siteName;
		$params['http_root'] = $settings->_httpRoot;
		$params['hash'] = $hash;
		$params['url'] = "http".((isset($_SERVER['HTTPS']) && (strcmp($_SERVER['HTTPS'],'off')!=0)) ? "s" : "")."://".$_SERVER['HTTP_HOST'].$settings->_httpRoot."out/out.ChangePassword.php?hash=".$hash;
	 	$params['url_prefix'] = "http".((isset($_SERVER['HTTPS']) && (strcmp($_SERVER['HTTPS'],'off')!=0)) ? "s" : "")."://".$_SERVER['HTTP_HOST'].$settings->_httpRoot;
		$emailobj->toIndividual($settings->_smtpSendFrom, $user, $subject, $message, $params);
	}
}

header('Location: ../out/out.PasswordSend.php');
exit;
?>
