<?php
include("../inc/inc.Settings.php");
include("../inc/inc.Init.php");
include("../inc/inc.Extension.php");
include("../inc/inc.DBInit.php");
include("../inc/inc.ClassNotificationService.php");
include("../inc/inc.ClassEmailNotify.php");
include("../inc/inc.ClassController.php");
include("Log.php");

if($settings->_logFileEnable) {
	if ($settings->_logFileRotation=="h") $logname=date("YmdH", time());
	else if ($settings->_logFileRotation=="d") $logname=date("Ymd", time());
	else $logname=date("Ym", time());
	$logname = $settings->_contentDir."log/webdav-".$logname.".log";
	if(!file_exists($settings->_contentDir.'log'))
		@mkdir($settings->_contentDir.'log');
	if(file_exists($settings->_contentDir.'log') && is_dir($settings->_contentDir.'log')) {
		$log = Log::factory('file', $logname);
		$log->setMask(Log::MAX(PEAR_LOG_INFO));
	} else
		$log = null;
} else {
	$log = null;
}

include("webdav.php");
$server = new HTTP_WebDAV_Server_LetoDMS();
$server->ServeRequest($dms, $log);
//$files = array();
//$options = array('path'=>'/Test1/subdir', 'depth'=>1);
//echo $server->MKCOL(&$options);

?>
