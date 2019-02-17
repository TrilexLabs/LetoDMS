<?php
//    MyDMS. Document Management System
//    Copyright (C) 2002-2005  Markus Westphal
//    Copyright (C) 2006-2008 Malcolm Cowe
//    Copyright (C) 2010 Matteo Lucarelli
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

require_once('inc.ClassUI_Default.php');
require_once('inc.ClassViewCommon.php');

/* $theme was possibly set in inc.Authentication.php */
if (!isset($theme) || strlen($theme)==0) {
	$theme = $settings->_theme;
}
if (strlen($theme)==0) {
	$theme="bootstrap";
}

/* Sooner or later the parent will be removed, because all output will
 * be done by the new view classes.
 */
class UI extends UI_Default {
	/**
	 * Create a view from a class in the given theme
	 *
	 * This method will check for a class file in the theme directory
	 * and returns an instance of it.
	 *
	 * @param string $theme theme
	 * @param string $class name of view class
	 * @param array $params parameter passed to constructor of view class
	 * @return object an object of a class implementing the view
	 */
	static function factory($theme, $class='', $params=array()) { /* {{{ */
		global $settings, $session, $EXT_CONF;
		if(!$class) {
			$class = 'Bootstrap';
			$classname = "LetoDMS_Bootstrap_Style";
		} else {
			$classname = "LetoDMS_View_".$class;
		}
		/* Do not check for class file anymore but include it relative
		 * to rootDir or an extension dir if it has set the include path
		 */
		$filename = '';
		$httpbasedir = '';
		foreach($EXT_CONF as $extname=>$extconf) {
			if(!isset($extconf['disable']) || $extconf['disable'] == false) {
				$filename = $settings->_rootDir.'ext/'.$extname.'/views/'.$theme."/class.".$class.".php";
				if(file_exists($filename)) {
					$httpbasedir = 'ext/'.$extname.'/';
					break;
				}
				$filename = '';
				if(isset($extconf['views'][$class])) {
					$filename = $settings->_rootDir.'ext/'.$extname.'/views/'.$theme."/".$extconf['views'][$class]['file'];
					if(file_exists($filename)) {
						$httpbasedir = 'ext/'.$extname.'/';
						$classname = $extconf['views'][$class]['name'];
						break;
					}
				}
			}
		}
		if(!$filename)
			$filename = $settings->_rootDir."views/".$theme."/class.".$class.".php";
		if(!file_exists($filename))
			$filename = '';
		if($filename) {
			require($filename);
			$view = new $classname($params, $theme);
			/* Set some configuration parameters */
			$view->setParam('refferer', $_SERVER['REQUEST_URI']);
			$view->setParam('absbaseprefix', $settings->_httpRoot.$httpbasedir);
			$view->setParam('class', $class);
			$view->setParam('session', $session);
			$view->setParam('settings', $settings);
			$view->setParam('sitename', $settings->_siteName);
			$view->setParam('rootfolderid', $settings->_rootFolderID);
			$view->setParam('disableselfedit', $settings->_disableSelfEdit);
			$view->setParam('enableusersview', $settings->_enableUsersView);
			$view->setParam('enablecalendar', $settings->_enableCalendar);
			$view->setParam('calendardefaultview', $settings->_calendarDefaultView);
			$view->setParam('enablefullsearch', $settings->_enableFullSearch);
			$view->setParam('enablehelp', $settings->_enableHelp);
			$view->setParam('enablelargefileupload', $settings->_enableLargeFileUpload);
			$view->setParam('printdisclaimer', $settings->_printDisclaimer);
			$view->setParam('footnote', $settings->_footNote);
			$view->setParam('logfileenable', $settings->_logFileEnable);
			$view->setParam('expandfoldertree', $settings->_expandFolderTree);
			$view->setParam('enablefoldertree', $settings->_enableFolderTree);
			$view->setParam('enablelanguageselector', $settings->_enableLanguageSelector);
			$view->setParam('enableclipboard', $settings->_enableClipboard);
			$view->setParam('enablemenutasks', $settings->_enableMenuTasks);
			$view->setParam('enabledropfolderlist', $settings->_enableDropFolderList);
			$view->setParam('dropfolderdir', $settings->_dropFolderDir);
			$view->setParam('enablesessionlist', $settings->_enableSessionList);
			$view->setParam('workflowmode', $settings->_workflowMode);
			$view->setParam('partitionsize', (int) $settings->_partitionSize);
			$view->setParam('maxuploadsize', (int) $settings->_maxUploadSize);
			$view->setParam('showmissingtranslations', $settings->_showMissingTranslations);
			$view->setParam('defaultsearchmethod', $settings->_defaultSearchMethod);
			$view->setParam('cachedir', $settings->_cacheDir);
			return $view;
		}
		return null;
	} /* }}} */

	static function getStyles() { /* {{{ */
		global $settings;

		$themes = array();
		$path = $settings->_rootDir . "views/";
		$handle = opendir($path);

		while ($entry = readdir($handle) ) {
			if ($entry == ".." || $entry == ".")
				continue;
			else if (is_dir($path . $entry) || is_link($path . $entry))
				array_push($themes, $entry);
		}
		closedir($handle);
		return $themes;
	} /* }}} */

	static function exitError($pagetitle, $error, $noexit=false, $plain=false) {
		global $theme, $dms, $user;
		$view = UI::factory($theme, 'ErrorDlg');
		$view->setParam('dms', $dms);
		$view->setParam('user', $user);
		$view->setParam('pagetitle', $pagetitle);
		$view->setParam('errormsg', $error);
		$view->setParam('plain', $plain);
		$view();
		if($noexit)
			return;
		exit;
	}
}

?>
