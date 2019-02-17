<?php
/**
 * Implementation of ExpiredDocuments view
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
 * Class which outputs the html page for ExpiredDocuments view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_ExpiredDocuments extends LetoDMS_Bootstrap_Style {

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$orderby = $this->params['orderby'];
		$cachedir = $this->params['cachedir'];
		$previewwidth = $this->params['previewWidthList'];
		$timeout = $this->params['timeout'];

		$db = $dms->getDB();
		$previewer = new LetoDMS_Preview_Previewer($cachedir, $previewwidth, $timeout);

		$this->htmlStartPage(getMLText("expired_documents"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("expired_documents"), "admin_tools");

		$this->contentHeading(getMLText("expired_documents"));
		$this->contentContainerStart();

		if($docs = $dms->getDocumentsExpired(-1400)) {

			print "<table class=\"table table-condensed\">";
			print "<thead>\n<tr>\n";
			print "<th></th>";
			print "<th><a href=\"../out/out.ExpiredDocuments.php?orderby=n\">".getMLText("name")."</a></th>\n";
			print "<th><a href=\"../out/out.ExpiredDocuments.php?orderby=s\">".getMLText("status")."</a></th>\n";
			print "<th>".getMLText("version")."</th>\n";
//				print "<th><a href=\"../out/out.ExpiredDocuments.php?orderby=u\">".getMLText("last_update")."</a></th>\n";
			print "<th><a href=\"../out/out.ExpiredDocuments.php?orderby=e\">".getMLText("expires")."</a></th>\n";
			print "</tr>\n</thead>\n<tbody>\n";

			$previewer = new LetoDMS_Preview_Previewer($cachedir, $previewwidth, $timeout);
			foreach ($docs as $document) {
				print "<tr>\n";
				$latestContent = $document->getLatestContent();
				$previewer->createPreview($latestContent);
				print "<td><a href=\"../op/op.Download.php?documentid=".$document->getID()."&version=".$latestContent->getVersion()."\">";
				if($previewer->hasPreview($latestContent)) {
					print "<img class=\"mimeicon\" width=\"".$previewwidth."\" src=\"../op/op.Preview.php?documentid=".$document->getID()."&version=".$latestContent->getVersion()."&width=".$previewwidth."\" title=\"".htmlspecialchars($latestContent->getMimeType())."\">";
				} else {
					print "<img class=\"mimeicon\" width=\"".$previewwidth."\" src=\"".$this->getMimeIcon($latestContent->getFileType())."\" title=\"".htmlspecialchars($latestContent->getMimeType())."\">";
				}
				print "</a></td>";
				print "<td><a href=\"out.ViewDocument.php?documentid=".$document->getID()."\">" . htmlspecialchars($document->getName()) . "</a></td>\n";
				$status = $latestContent->getStatus();
				print "<td>".getOverallStatusText($status["status"])."</td>";
				print "<td>".$latestContent->getVersion()."</td>";
//					print "<td>".$status["statusDate"]." ". htmlspecialchars($status["statusName"])."</td>";
				print "<td>".(!$document->getExpires() ? "-":getReadableDate($document->getExpires()))."</td>";				
				print "</tr>\n";
			}
			print "</tbody></table>";
		}
		else printMLText("empty_notify_list");
		
		$this->contentContainerEnd();

		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
