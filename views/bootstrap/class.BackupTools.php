<?php
/**
 * Implementation of BackupTools view
 *
 * @category   DMS
 * @package    LetoDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Include parent class
 */
require_once("class.Bootstrap.php");

/**
 * Class which outputs the html page for BackupTools view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_BackupTools extends LetoDMS_Bootstrap_Style {

	function js() { /* {{{ */
		header('Content-Type: application/javascript');

		$this->printFolderChooserJs("form1");
		$this->printFolderChooserJs("form2");
		$this->printFolderChooserJs("form3");
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$backupdir = $this->params['backupdir'];

		$this->htmlStartPage(getMLText("backup_tools"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("admin_tools"), "admin_tools");

		/* Calculating the size of the backup dir is only reasonable if
		 * it is not part of the content dir. Otherwise the content will
		 * be counted as well.
		 */
		if($this->params['hasbackupdir']) {
			$this->contentHeading(getMLText("backup_tools"));
			$this->contentContainerStart();
			print getMLText("space_used_on_data_folder")." : ".LetoDMS_Core_File::format_filesize(dskspace($backupdir));
			$this->contentContainerEnd();
		}

		// versioning file creation ////////////////////////////////////////////////////

		$this->contentHeading(getMLText("versioning_file_creation"));
		print "<p>".getMLText("versioning_file_creation_warning")."</p>\n";
		$this->contentContainerStart();
		print "<form class=\"form-inline\" action=\"../op/op.CreateVersioningFiles.php\" name=\"form1\">";
		$this->printFolderChooserHtml("form1",M_READWRITE);
		print "<input type='submit' class='btn' name='' value='".getMLText("versioning_file_creation")."'/>";
		print "</form>\n";

		$this->contentContainerEnd();

		// archive creation ////////////////////////////////////////////////////////////

		$this->contentHeading(getMLText("archive_creation"));
		print "<p>".getMLText("archive_creation_warning")."</p>\n";
		$this->contentContainerStart();
		print "<form action=\"../op/op.CreateFolderArchive.php\" name=\"form2\">";
		$this->printFolderChooserHtml("form2",M_READWRITE);
		print "<label class=\"checkbox\"><input type=\"checkbox\" name=\"human_readable\" value=\"1\">".getMLText("human_readable")."</label>";
		print "<input type='submit' class='btn' name='' value='".getMLText("archive_creation")."'/>";
		print "</form>\n";

		// list backup files

		$handle = opendir($backupdir);
		$entries = array();
		while ($e = readdir($handle)){
			if (is_dir($backupdir.$e)) continue;
			if (strpos($e,".tar.gz")==FALSE) continue;
			$entries[] = $e;
		}
		closedir($handle);

		sort($entries);
		$entries = array_reverse($entries);

		if($entries) {
			$this->contentSubHeading(getMLText("backup_list"));
			print "<table class=\"table-condensed\">\n";
			print "<thead>\n<tr>\n";
			print "<th></th>\n";
			print "<th>".getMLText("folder")."</th>\n";
			print "<th>".getMLText("creation_date")."</th>\n";
			print "<th>".getMLText("file_size")."</th>\n";
			print "<th></th>\n";
			print "</tr>\n</thead>\n<tbody>\n";

			foreach ($entries as $entry){

				$folderid=substr($entry,strpos($entry,"_")+1);
				$folder=$dms->getFolder((int)$folderid);
						
				print "<tr>\n";
				print "<td><a href=\"../op/op.Download.php?arkname=".$entry."\">".$entry."</a></td>\n";
				if (is_object($folder)) print "<td>".htmlspecialchars($folder->getName())."</td>\n";
				else print "<td>".getMLText("unknown_id")."</td>\n";
				print "<td>".getLongReadableDate(filectime($backupdir.$entry))."</td>\n";
				print "<td>".LetoDMS_Core_File::format_filesize(filesize($backupdir.$entry))."</td>\n";
				print "<td>";
				print "<a href=\"out.RemoveArchive.php?arkname=".$entry."\" class=\"btn btn-mini\"><i class=\"icon-remove\"></i> ".getMLText("backup_remove")."</a>";
				print "</td>\n";	
				print "</tr>\n";
			}
			print "</table>\n";
		}

		$this->contentContainerEnd();

		// dump creation ///////////////////////////////////////////////////////////////

		$this->contentHeading(getMLText("dump_creation"));
		print "<p>".getMLText("dump_creation_warning")."</p>\n";
		$this->contentContainerStart();

		print "<form action=\"../op/op.CreateDump.php\" name=\"form4\">";
		print "<input type='submit' class='btn' name='' value='".getMLText("dump_creation")."'/>";
		print "</form>\n";

		// list backup files
		$handle = opendir($backupdir);
		$entries = array();
		while ($e = readdir($handle)){
			if (is_dir($backupdir.$e)) continue;
			if (strpos($e,".sql.gz")==FALSE) continue;
			$entries[] = $e;
		}
		closedir($handle);

		sort($entries);
		$entries = array_reverse($entries);

		if($entries) {
			$this->contentSubHeading(getMLText("dump_list"));
			print "<table class=\"table-condensed\">\n";
			print "<thead>\n<tr>\n";
			print "<th></th>\n";
			print "<th>".getMLText("creation_date")."</th>\n";
			print "<th>".getMLText("file_size")."</th>\n";
			print "<th></th>\n";
			print "</tr>\n</thead>\n<tbody>\n";

			foreach ($entries as $entry){
				print "<tr>\n";
				print "<td>";
				print "<a href=\"../op/op.Download.php?dumpname=".$entry."\">".$entry."</a>";
				print "</td>\n";
				print "<td>".getLongReadableDate(filectime($backupdir.$entry))."</td>\n";
				print "<td>".LetoDMS_Core_File::format_filesize(filesize($backupdir.$entry))."</td>\n";
				print "<td>";
				print "<a href=\"out.RemoveDump.php?dumpname=".$entry."\" class=\"btn btn-mini\"><i class=\"icon-remove\"></i> ".getMLText("dump_remove")."</a>";
				print "</td>\n";	
				print "</tr>\n";
			}
			print "</table>\n";
		}

		$this->contentContainerEnd();

		// files deletion //////////////////////////////////////////////////////////////
		/*
		$this->contentHeading(getMLText("files_deletion"));
		$this->contentContainerStart();
		print "<p>".getMLText("files_deletion_warning")."</p>\n";

		print "<form class=\"form-inline\" action=\"../out/out.RemoveFolderFiles.php\" name=\"form3\">";
		$this->printFolderChooserHtml("form3",M_READWRITE);
		print "<input type='submit' class='btn' name='' value='".getMLText("files_deletion")."'/>";
		print "</form>\n";

		$this->contentContainerEnd();
		*/

		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
