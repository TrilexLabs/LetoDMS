<?php
//    MyDMS. Document Management System
//    Copyright (C) 2002-2005  Markus Westphal
//    Copyright (C) 2006-2008 Malcolm Cowe
//    Copyright (C) 2010 Matteo Lucarelli
//    Copyright (C) 2010-2106 Uwe Steinmann
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
include("../inc/inc.Language.php");
include("../inc/inc.Init.php");
include("../inc/inc.Extension.php");
include("../inc/inc.DBInit.php");
include("../inc/inc.ClassUI.php");
include("../inc/inc.Authentication.php");

//print_r($_FILES);
//print_r($_POST);
//exit;

$file_param_name = 'qqfile';
$file_name = $_FILES[ $file_param_name ][ 'name' ];
$source_file_path = $_FILES[ $file_param_name ][ 'tmp_name' ];
$fileId = preg_replace('/[^0-9a-f-]+/', '', $_POST['qquuid']);
$partitionIndex = (int) $_POST['qqpartindex'];
$totalparts = (int) $_POST['qqtotalparts'];
$target_file_path =$settings->_stagingDir.$fileId."-".$partitionIndex;
if( move_uploaded_file( $source_file_path, $target_file_path ) ) {
	if($partitionIndex+1 == $totalparts) {
		if($fpnew = fopen($settings->_stagingDir.$fileId, 'w+')) {
			for($i=0; $i<$totalparts; $i++) {
				$content = file_get_contents($settings->_stagingDir.$fileId."-".$i, 'r');
				fwrite($fpnew, $content);
				unlink($settings->_stagingDir.$fileId."-".$i);
			}
			fclose($fpnew);
			header("Content-Type: text/plain");
			echo json_encode(array('success'=>true));
			exit;
		} else {
			header("Content-Type: text/plain");
			echo json_encode(array('success'=>false, 'error'=>'Could not upload file'));
			exit;
		}
	}
	header("Content-Type: text/plain");
	echo json_encode(array('success'=>true));
	exit;
}
header("Content-Type: text/plain");
echo json_encode(array('success'=>false, 'error'=>'Could not upload file'));
?>
