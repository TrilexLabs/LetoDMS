<?php
/**
 * Implementation of ApprovalSummary view
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
 * Include class to preview documents
 */
require_once("LetoDMS/Preview.php");

/**
 * Class which outputs the html page for ApprovalSummary view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_ApprovalSummary extends LetoDMS_Bootstrap_Style {

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$cachedir = $this->params['cachedir'];
		$previewwidth = $this->params['previewWidthList'];
		$timeout = $this->params['timeout'];

		$previewer = new LetoDMS_Preview_Previewer($cachedir, $previewwidth, $timeout);

		$this->htmlStartPage(getMLText("approval_summary"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("my_documents"), "my_documents");
		echo "<div class=\"row-fluid\">\n";
		echo "<div class=\"span6\">\n";
		$this->contentHeading(getMLText("approval_summary"));
//		$this->contentContainerStart();

		// Get document list for the current user.
		$approvalStatus = $user->getApprovalStatus();

		// reverse order
		$approvalStatus["indstatus"]=array_reverse($approvalStatus["indstatus"],true);
		$approvalStatus["grpstatus"]=array_reverse($approvalStatus["grpstatus"],true);

		$iRev = array();	
		$printheader = true;
		foreach ($approvalStatus["indstatus"] as $st) {
			$document = $dms->getDocument($st['documentID']);
			$version = $document->getContentByVersion($st['version']);
			$moduser = $dms->getUser($st['required']);

			if ($document && $version) {

				if ($printheader){
					print "<table class=\"table table-condensed\">";
					print "<thead>\n<tr>\n";
					print "<th></th>\n";
					print "<th>".getMLText("name")."</th>\n";
					print "<th>".getMLText("status")."</th>\n";
					print "<th>".getMLText("action")."</th>\n";
					print "<th>".getMLText("last_update")."</th>\n";
					print "</tr>\n</thead>\n<tbody>\n";
					$printheader = false;
				}

				$class = $st['status'] == 1 ? ' success' : ($st['status'] == -1 ? ' error' : ( $st['status'] == -2 ? ' info' : ''));
				print "<tr id=\"table-row-document-".$st['documentID']."\" class=\"table-row-document".$class."\" rel=\"document_".$st['documentID']."\" formtoken=\"".createFormKey('movedocument')."\" draggable=\"true\">";
				echo $this->documentListRow($document, $previewer, true, $st['version']);
				print "<td><small>".getApprovalStatusText($st['status'])."<br />".$st["date"]."<br />". htmlspecialchars($moduser->getFullName()) ."</small></td>";
				print "</tr>\n";
			}
			if ($st["status"]!=-2) {
				$iRev[] = $st["documentID"];
			}
		}
		if (!$printheader) {
			echo "</tbody>\n</table>\n";
		}else{
			printMLText("no_approval_needed");
		}

//		$this->contentContainerEnd();
		echo "</div>\n";
		echo "<div class=\"span6\">\n";
		$this->contentHeading(getMLText("group_approval_summary"));
//		$this->contentContainerStart();

		$printheader = true;
		foreach ($approvalStatus["grpstatus"] as $st) {
			$document = $dms->getDocument($st['documentID']);
			$version = $document->getContentByVersion($st['version']);
			$modgroup = $dms->getGroup($st['required']);

			if (!in_array($st["documentID"], $iRev) && $document && $version) {

				if ($printheader){
					print "<table class=\"table table-condensed\">";
					print "<thead>\n<tr>\n";
					print "<th></th>\n";
					print "<th>".getMLText("name")."</th>\n";
					print "<th>".getMLText("status")."</th>\n";
					print "<th>".getMLText("action")."</th>\n";
					print "<th>".getMLText("last_update")."</th>\n";
					print "</tr>\n</thead>\n<tbody>\n";
					$printheader = false;
				}	

				$class = $st['status'] == 1 ? ' success' : ($st['status'] == -1 ? ' error' : ( $st['status'] == -2 ? ' info' : ''));
				print "<tr id=\"table-row-document-".$st['documentID']."\" class=\"table-row-document".$class."\" rel=\"document_".$st['documentID']."\" formtoken=\"".createFormKey('movedocument')."\" draggable=\"true\">";
				echo $this->documentListRow($document, $previewer, true, $st['version']);
				print "<td><small>".getApprovalStatusText($st["status"])."<br />".$st["date"]."<br />". htmlspecialchars($moduser->getFullName()) ."</small></td>";
				print "</tr>\n";
			}
		}
		if (!$printheader) {
			echo "</tbody>\n</table>\n";
		}else{
			printMLText("no_approval_needed");
		}

//		$this->contentContainerEnd();
		echo "</div>\n";
		echo "</div>\n";
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
