<?php
define("LetoDMS_INSTALL", "on");
include("../inc/inc.Settings.php");
$settings = new Settings();
$rootDir = realpath ("..");
$settings->_rootDir = $rootDir.'/';

$theme = "bootstrap";
include("../inc/inc.Language.php");
include("../inc/inc.ClassUI.php");

UI::htmlStartPage("INSTALL");
UI::globalBanner();
UI::contentStart();
UI::contentHeading("LetoDMS Installation...");
UI::contentContainerStart();
echo "<h2>".getMLText('settings_install_welcome_title')."</h2>";
echo "<div style=\"width: 600px;\">".getMLText('settings_install_welcome_text')."</div>";
echo '<p><a href="install.php">' . getMLText("settings_start_install") . '</a></p>';
UI::contentContainerEnd();
UI::contentEnd();
UI::htmlEndPage();
?>
