<?php
/**
 * Setup 2-factor authentication
 *
 * @category   DMS
 * @package    LetoDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2016 Uwe Steinmann
 * @version    Release: @package_version@
 */

include("../inc/inc.Settings.php");
include("../inc/inc.Language.php");
include("../inc/inc.Init.php");
include("../inc/inc.Extension.php");
include("../inc/inc.DBInit.php");
include("../inc/inc.ClassUI.php");
include("../inc/inc.Authentication.php");

$tmp = explode('.', basename($_SERVER['SCRIPT_FILENAME']));
$view = UI::factory($theme, $tmp[1], array('dms'=>$dms, 'user'=>$user));
$accessop = new LetoDMS_AccessOperation($dms, $user, $settings);

if ($user->isGuest()) {
	UI::exitError(getMLText("2_factor_auth"),getMLText("access_denied"));
}

if($view) {
	$view->setParam('sitename', $settings->_siteName);
	$view->setParam('enable2factauth', $settings->_enable2FactorAuthentication);
	$view->setParam('accessobject', $accessop);
	$view($_GET);
	exit;
}

