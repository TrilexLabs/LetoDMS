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

/**
 * Include class to preview documents
 */
require_once("LetoDMS/Preview.php");

if (!isset($_GET["filename"])) {
	exit;
}
$filename = $_GET["filename"];

if(substr($settings->_dropFolderDir, -1, 1) == DIRECTORY_SEPARATOR)
	$dropfolderdir = substr($settings->_dropFolderDir, 0, -1);
else
	$dropfolderdir = $settings->_dropFolderDir;
$dir = $dropfolderdir.'/'.$user->getLogin();

if(!file_exists($dir.'/'.$filename))
	exit;

if(!empty($_GET["width"]))
	$previewer = new LetoDMS_Preview_Previewer($settings->_cacheDir, $_GET["width"]);
else
	$previewer = new LetoDMS_Preview_Previewer($settings->_cacheDir);
if(!$previewer->hasRawPreview($dir.'/'.$filename, 'dropfolder/'))
	$previewer->createRawPreview($dir.'/'.$filename, 'dropfolder/');
header('Content-Type: image/png');
$previewer->getRawPreview($dir.'/'.$filename, 'dropfolder/');

?>
