<?php
/**
 * Implementation of MyDocuments view
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
 * Class which outputs the html page for MyDocuments view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_MyDocuments extends LetoDMS_Bootstrap_Style {

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$orderby = $this->params['orderby'];
		$showInProcess = $this->params['showinprocess'];
		$cachedir = $this->params['cachedir'];
		$workflowmode = $this->params['workflowmode'];
		$previewwidth = $this->params['previewWidthList'];
		$timeout = $this->params['timeout'];

		$db = $dms->getDB();
		$previewer = new LetoDMS_Preview_Previewer($cachedir, $previewwidth, $timeout);

		$this->htmlStartPage(getMLText("my_documents"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("my_documents"), "my_documents");

		if ($showInProcess){

			if (!$db->createTemporaryTable("ttstatid") || !$db->createTemporaryTable("ttcontentid")) {
				$this->contentHeading(getMLText("warning"));
				$this->contentContainer(getMLText("internal_error_exit"));
				$this->htmlEndPage();
				exit;
			}

			if($workflowmode == 'traditional' || $workflowmode == 'traditional_only_approval') {
				// Get document list for the current user.
				$reviewStatus = $user->getReviewStatus();
				$approvalStatus = $user->getApprovalStatus();
				
				// Create a comma separated list of all the documentIDs whose information is
				// required.
				$dList = array();
				foreach ($reviewStatus["indstatus"] as $st) {
					if (!in_array($st["documentID"], $dList)) {
						$dList[] = $st["documentID"];
					}
				}
				foreach ($reviewStatus["grpstatus"] as $st) {
					if (!in_array($st["documentID"], $dList)) {
						$dList[] = $st["documentID"];
					}
				}
				foreach ($approvalStatus["indstatus"] as $st) {
					if (!in_array($st["documentID"], $dList)) {
						$dList[] = $st["documentID"];
					}
				}
				foreach ($approvalStatus["grpstatus"] as $st) {
					if (!in_array($st["documentID"], $dList)) {
						$dList[] = $st["documentID"];
					}
				}
				$docCSV = "";
				foreach ($dList as $d) {
					$docCSV .= (strlen($docCSV)==0 ? "" : ", ")."'".$d."'";
				}
				
				if (strlen($docCSV)>0) {
					// Get the document information.
					$queryStr = "SELECT `tblDocuments`.*, `tblDocumentLocks`.`userID` as `lockUser`, ".
						"`tblDocumentContent`.`version`, `tblDocumentStatus`.*, `tblDocumentStatusLog`.`status`, ".
						"`tblDocumentStatusLog`.`comment` AS `statusComment`, `tblDocumentStatusLog`.`date` as `statusDate`, ".
						"`tblDocumentStatusLog`.`userID`, `oTbl`.`fullName` AS `ownerName`, `sTbl`.`fullName` AS `statusName` ".
						"FROM `tblDocumentContent` ".
						"LEFT JOIN `tblDocuments` ON `tblDocuments`.`id` = `tblDocumentContent`.`document` ".
						"LEFT JOIN `tblDocumentStatus` ON `tblDocumentStatus`.`documentID` = `tblDocumentContent`.`document` ".
						"LEFT JOIN `tblDocumentStatusLog` ON `tblDocumentStatusLog`.`statusID` = `tblDocumentStatus`.`statusID` ".
						"LEFT JOIN `ttstatid` ON `ttstatid`.`maxLogID` = `tblDocumentStatusLog`.`statusLogID` ".
						"LEFT JOIN `ttcontentid` ON `ttcontentid`.`maxVersion` = `tblDocumentStatus`.`version` AND `ttcontentid`.`document` = `tblDocumentStatus`.`documentID` ".
						"LEFT JOIN `tblDocumentLocks` ON `tblDocuments`.`id`=`tblDocumentLocks`.`document` ".
						"LEFT JOIN `tblUsers` AS `oTbl` on `oTbl`.`id` = `tblDocuments`.`owner` ".
						"LEFT JOIN `tblUsers` AS `sTbl` on `sTbl`.`id` = `tblDocumentStatusLog`.`userID` ".
						"WHERE `ttstatid`.`maxLogID`=`tblDocumentStatusLog`.`statusLogID` ".
						"AND `ttcontentid`.`maxVersion` = `tblDocumentContent`.`version` ".
						"AND `tblDocumentStatusLog`.`status` IN (".S_DRAFT_REV.", ".S_DRAFT_APP.", ".S_EXPIRED.") ".
						"AND `tblDocuments`.`id` IN (" . $docCSV . ") ".
						"ORDER BY `statusDate` DESC";

					$resArr = $db->getResultArray($queryStr);
					if (is_bool($resArr) && !$resArr) {
						$this->contentHeading(getMLText("warning"));
						$this->contentContainer(getMLText("internal_error_exit"));
						$this->htmlEndPage();
						exit;
					}
					
					// Create an array to hold all of these results, and index the array by
					// document id. This makes it easier to retrieve document ID information
					// later on and saves us having to repeatedly poll the database every time
					// new document information is required.
					$docIdx = array();
					foreach ($resArr as $res) {
						
						// verify expiry
						if ( $res["expires"] && time()>$res["expires"]+24*60*60 ){
							if  ( $res["status"]==S_DRAFT_APP || $res["status"]==S_DRAFT_REV ){
								$res["status"]=S_EXPIRED;
							}
						}

						$docIdx[$res["id"]][$res["version"]] = $res;
					}

					// List the documents where a review has been requested.
					if($workflowmode == 'traditional') {
						$this->contentHeading(getMLText("documents_to_review"));
						$this->contentContainerStart();
						$printheader=true;
						$iRev = array();
						$dList = array();
						foreach ($reviewStatus["indstatus"] as $st) {
						
							if ( $st["status"]==0 && isset($docIdx[$st["documentID"]][$st["version"]]) && !in_array($st["documentID"], $dList) ) {
								$dList[] = $st["documentID"];
								$document = $dms->getDocument($st["documentID"]);
								$document->verifyLastestContentExpriry();
							
								if ($printheader){
									print "<table class=\"table table-condensed\">";
									print "<thead>\n<tr>\n";
									print "<th></th>\n";
									print "<th>".getMLText("name")."</th>\n";
									print "<th>".getMLText("owner")."</th>\n";
									print "<th>".getMLText("version")."</th>\n";
									print "<th>".getMLText("last_update")."</th>\n";
									print "<th>".getMLText("expires")."</th>\n";
									print "</tr>\n</thead>\n<tbody>\n";
									$printheader=false;
								}
							
								print "<tr>\n";
								$latestContent = $document->getLatestContent();
								$previewer->createPreview($latestContent);
								print "<td><a href=\"../op/op.Download.php?documentid=".$st["documentID"]."&version=".$st["version"]."\">";
								if($previewer->hasPreview($latestContent)) {
									print "<img class=\"mimeicon\" width=\"".$previewwidth."\" src=\"../op/op.Preview.php?documentid=".$document->getID()."&version=".$latestContent->getVersion()."&width=".$previewwidth."\" title=\"".htmlspecialchars($latestContent->getMimeType())."\">";
								} else {
									print "<img class=\"mimeicon\" width=\"".$previewwidth."\" src=\"".$this->getMimeIcon($latestContent->getFileType())."\" title=\"".htmlspecialchars($latestContent->getMimeType())."\">";
								}
								print "</a></td>";
								print "<td><a href=\"out.ViewDocument.php?documentid=".$st["documentID"]."&currenttab=revapp\">".htmlspecialchars($docIdx[$st["documentID"]][$st["version"]]["name"])."</a></td>";
								print "<td>".htmlspecialchars($docIdx[$st["documentID"]][$st["version"]]["ownerName"])."</td>";
								print "<td>".$st["version"]."</td>";
								print "<td>".$st["date"]." ". htmlspecialchars($docIdx[$st["documentID"]][$st["version"]]["statusName"]) ."</td>";
								print "<td".($docIdx[$st["documentID"]][$st["version"]]['status']!=S_EXPIRED?"":" class=\"warning\"").">".(!$docIdx[$st["documentID"]][$st["version"]]["expires"] ? "-":getReadableDate($docIdx[$st["documentID"]][$st["version"]]["expires"]))."</td>";				
								print "</tr>\n";
							}
						}
						foreach ($reviewStatus["grpstatus"] as $st) {
						
							if (!in_array($st["documentID"], $iRev) && $st["status"]==0 && isset($docIdx[$st["documentID"]][$st["version"]]) && !in_array($st["documentID"], $dList) /* && $docIdx[$st["documentID"]][$st["version"]]['owner'] != $user->getId() */) {
								$dList[] = $st["documentID"];
								$document = $dms->getDocument($st["documentID"]);
								$document->verifyLastestContentExpriry();

								if ($printheader){
									print "<table class=\"table table-condensed\">";
									print "<thead>\n<tr>\n";
									print "<th></th>\n";
									print "<th>".getMLText("name")."</th>\n";
									print "<th>".getMLText("owner")."</th>\n";
									print "<th>".getMLText("version")."</th>\n";
									print "<th>".getMLText("last_update")."</th>\n";
									print "<th>".getMLText("expires")."</th>\n";
									print "</tr>\n</thead>\n<tbody>\n";
									$printheader=false;
								}

								print "<tr>\n";
								$latestContent = $document->getLatestContent();
								$previewer->createPreview($latestContent);
								print "<td><a href=\"../op/op.Download.php?documentid=".$st["documentID"]."&version=".$st["version"]."\">";
								if($previewer->hasPreview($latestContent)) {
									print "<img class=\"mimeicon\" width=\"".$previewwidth."\" src=\"../op/op.Preview.php?documentid=".$document->getID()."&version=".$latestContent->getVersion()."&width=".$previewwidth."\" title=\"".htmlspecialchars($latestContent->getMimeType())."\">";
								} else {
									print "<img class=\"mimeicon\" width=\"".$previewwidth."\" src=\"".$this->getMimeIcon($latestContent->getFileType())."\" title=\"".htmlspecialchars($latestContent->getMimeType())."\">";
								}
								print "</a></td>";
								print "<td><a href=\"out.ViewDocument.php?documentid=".$st["documentID"]."&currenttab=revapp\">".htmlspecialchars($docIdx[$st["documentID"]][$st["version"]]["name"])."</a></td>";
								print "<td>".htmlspecialchars($docIdx[$st["documentID"]][$st["version"]]["ownerName"])."</td>";
								print "<td>".$st["version"]."</td>";
								print "<td>".$st["date"]." ". htmlspecialchars($docIdx[$st["documentID"]][$st["version"]]["statusName"])."</td>";
								print "<td".($docIdx[$st["documentID"]][$st["version"]]['status']!=S_EXPIRED?"":" class=\"warning\"").">".(!$docIdx[$st["documentID"]][$st["version"]]["expires"] ? "-":getReadableDate($docIdx[$st["documentID"]][$st["version"]]["expires"]))."</td>";				
								print "</tr>\n";
							}
						}
						if (!$printheader){
							echo "</tbody>\n</table>";
						}else{
							printMLText("no_docs_to_review");
						}
						$this->contentContainerEnd();
					}

					// List the documents where an approval has been requested.
					$this->contentHeading(getMLText("documents_to_approve"));
					$this->contentContainerStart();
					$printheader=true;
					
					foreach ($approvalStatus["indstatus"] as $st) {
					
						if ( $st["status"]==0 && isset($docIdx[$st["documentID"]][$st["version"]]) && $docIdx[$st["documentID"]][$st["version"]]['status'] == S_DRAFT_APP) {
							$document = $dms->getDocument($st["documentID"]);
							$document->verifyLastestContentExpriry();
						
							if ($printheader){
								print "<table class=\"table table-condensed\">";
								print "<thead>\n<tr>\n";
								print "<th></th>\n";
								print "<th>".getMLText("name")."</th>\n";
								print "<th>".getMLText("owner")."</th>\n";
								print "<th>".getMLText("version")."</th>\n";
								print "<th>".getMLText("last_update")."</th>\n";
								print "<th>".getMLText("expires")."</th>\n";
								print "</tr>\n</thead>\n<tbody>\n";
								$printheader=false;
							}

							print "<tr>\n";
							$latestContent = $document->getLatestContent();
							$previewer->createPreview($latestContent);
							print "<td><a href=\"../op/op.Download.php?documentid=".$st["documentID"]."&version=".$st["version"]."\">";
							if($previewer->hasPreview($latestContent)) {
								print "<img class=\"mimeicon\" width=\"".$previewwidth."\" src=\"../op/op.Preview.php?documentid=".$document->getID()."&version=".$latestContent->getVersion()."&width=".$previewwidth."\" title=\"".htmlspecialchars($latestContent->getMimeType())."\">";
							} else {
								print "<img class=\"mimeicon\" width=\"".$previewwidth."\" src=\"".$this->getMimeIcon($latestContent->getFileType())."\" title=\"".htmlspecialchars($latestContent->getMimeType())."\">";
							}
							print "</a></td>";
							print "<td><a href=\"out.ViewDocument.php?documentid=".$st["documentID"]."&currenttab=revapp\">".htmlspecialchars($docIdx[$st["documentID"]][$st["version"]]["name"])."</a></td>";
							print "<td>".htmlspecialchars($docIdx[$st["documentID"]][$st["version"]]["ownerName"])."</td>";
							print "<td>".$st["version"]."</td>";
							print "<td>".$st["date"]." ". htmlspecialchars($docIdx[$st["documentID"]][$st["version"]]["statusName"])."</td>";
							print "<td".($docIdx[$st["documentID"]][$st["version"]]['status']!=S_EXPIRED?"":" class=\"warning\"").">".(!$docIdx[$st["documentID"]][$st["version"]]["expires"] ? "-":getReadableDate($docIdx[$st["documentID"]][$st["version"]]["expires"]))."</td>";					
							print "</tr>\n";
						}
					}
					foreach ($approvalStatus["grpstatus"] as $st) {
					
						if (!in_array($st["documentID"], $iRev) && $st["status"]==0 && isset($docIdx[$st["documentID"]][$st["version"]]) && $docIdx[$st["documentID"]][$st["version"]]['status'] == S_DRAFT_APP /* && $docIdx[$st["documentID"]][$st["version"]]['owner'] != $user->getId() */) {
							$document = $dms->getDocument($st["documentID"]);
							$document->verifyLastestContentExpriry();
							if ($printheader){
								print "<table class=\"table table-condensed\">";
								print "<thead>\n<tr>\n";
								print "<th></th>\n";
								print "<th>".getMLText("name")."</th>\n";
								print "<th>".getMLText("owner")."</th>\n";
								print "<th>".getMLText("version")."</th>\n";
								print "<th>".getMLText("last_update")."</th>\n";
								print "<th>".getMLText("expires")."</th>\n";
								print "</tr>\n</thead>\n<tbody>\n";
								$printheader=false;
							}
							print "<tr>\n";
							$latestContent = $document->getLatestContent();
							$previewer->createPreview($latestContent);
							print "<td><a href=\"../op/op.Download.php?documentid=".$st["documentID"]."&version=".$st["version"]."\">";
							if($previewer->hasPreview($latestContent)) {
								print "<img class=\"mimeicon\" width=\"".$previewwidth."\" src=\"../op/op.Preview.php?documentid=".$document->getID()."&version=".$latestContent->getVersion()."&width=".$previewwidth."\" title=\"".htmlspecialchars($latestContent->getMimeType())."\">";
							} else {
								print "<img class=\"mimeicon\" width=\"".$previewwidth."\" src=\"".$this->getMimeIcon($latestContent->getFileType())."\" title=\"".htmlspecialchars($latestContent->getMimeType())."\">";
							}
							print "</a></td>";
							print "<td><a href=\"out.ViewDocument.php?documentid=".$st["documentID"]."&currenttab=revapp\">".htmlspecialchars($docIdx[$st["documentID"]][$st["version"]]["name"])."</a></td>";
							print "<td>".htmlspecialchars($docIdx[$st["documentID"]][$st["version"]]["ownerName"])."</td>";
							print "<td>".$st["version"]."</td>";				
							print "<td>".$st["date"]." ". htmlspecialchars($docIdx[$st["documentID"]][$st["version"]]["statusName"])."</td>";
							print "<td".($docIdx[$st["documentID"]][$st["version"]]['status']!=S_EXPIRED?"":" class=\"warning\"").">".(!$docIdx[$st["documentID"]][$st["version"]]["expires"] ? "-":getReadableDate($docIdx[$st["documentID"]][$st["version"]]["expires"]))."</td>";				
							print "</tr>\n";
						}
					}
					if (!$printheader){
						echo "</tbody>\n</table>\n";
					 }else{
						printMLText("no_docs_to_approve");
					 }
					$this->contentContainerEnd();
				}
				else {
					if($workflowmode == 'traditional') {	
						$this->contentHeading(getMLText("documents_to_review"));
						$this->contentContainerStart();
						printMLText("no_review_needed");
						$this->contentContainerEnd();
					}
					$this->contentHeading(getMLText("documents_to_approve"));
					$this->contentContainerStart();
					printMLText("no_approval_needed");
					$this->contentContainerEnd();
				}

				// Get list of documents owned by current user that are pending review or
				// pending approval.
				$queryStr = "SELECT `tblDocuments`.*, `tblDocumentLocks`.`userID` as `lockUser`, ".
					"`tblDocumentContent`.`version`, `tblDocumentStatus`.*, `tblDocumentStatusLog`.`status`, ".
					"`tblDocumentStatusLog`.`comment` AS `statusComment`, `tblDocumentStatusLog`.`date` as `statusDate`, ".
					"`tblDocumentStatusLog`.`userID`, `oTbl`.`fullName` AS `ownerName`, `sTbl`.`fullName` AS `statusName` ".
					"FROM `tblDocumentContent` ".
					"LEFT JOIN `tblDocuments` ON `tblDocuments`.`id` = `tblDocumentContent`.`document` ".
					"LEFT JOIN `tblDocumentStatus` ON `tblDocumentStatus`.`documentID` = `tblDocumentContent`.`document` ".
					"LEFT JOIN `tblDocumentStatusLog` ON `tblDocumentStatusLog`.`statusID` = `tblDocumentStatus`.`statusID` ".
					"LEFT JOIN `ttstatid` ON `ttstatid`.`maxLogID` = `tblDocumentStatusLog`.`statusLogID` ".
					"LEFT JOIN `ttcontentid` ON `ttcontentid`.`maxVersion` = `tblDocumentStatus`.`version` AND `ttcontentid`.`document` = `tblDocumentStatus`.`documentID` ".
					"LEFT JOIN `tblDocumentLocks` ON `tblDocuments`.`id`=`tblDocumentLocks`.`document` ".
					"LEFT JOIN `tblUsers` AS `oTbl` on `oTbl`.`id` = `tblDocuments`.`owner` ".
					"LEFT JOIN `tblUsers` AS `sTbl` on `sTbl`.`id` = `tblDocumentStatusLog`.`userID` ".
					"WHERE `ttstatid`.`maxLogID`=`tblDocumentStatusLog`.`statusLogID` ".
					"AND `ttcontentid`.`maxVersion` = `tblDocumentContent`.`version` ".
					"AND `tblDocuments`.`owner` = '".$user->getID()."' ".
					"AND `tblDocumentStatusLog`.`status` IN (".S_DRAFT_REV.", ".S_DRAFT_APP.") ".
					"ORDER BY `statusDate` DESC";

				$resArr = $db->getResultArray($queryStr);
				if (is_bool($resArr) && !$resArr) {
					$this->contentHeading(getMLText("warning"));
					$this->contentContainer("Internal error. Unable to complete request. Exiting.");
					$this->htmlEndPage();
					exit;
				}

				$this->contentHeading(getMLText("documents_user_requiring_attention"));
				$this->contentContainerStart();
				if (count($resArr)>0) {

					print "<table class=\"table table-condensed\">";
					print "<thead>\n<tr>\n";
					print "<th></th>";
					print "<th>".getMLText("name")."</th>\n";
					print "<th>".getMLText("status")."</th>\n";
					print "<th>".getMLText("version")."</th>\n";
					print "<th>".getMLText("last_update")."</th>\n";
					print "<th>".getMLText("expires")."</th>\n";
					print "</tr>\n</thead>\n<tbody>\n";

					foreach ($resArr as $res) {
						$document = $dms->getDocument($res["documentID"]);
						$document->verifyLastestContentExpriry();
					
						// verify expiry
						if ( $res["expires"] && time()>$res["expires"]+24*60*60 ){
							if  ( $res["status"]==S_DRAFT_APP || $res["status"]==S_DRAFT_REV ){
								$res["status"]=S_EXPIRED;
							}
						}
					
						print "<tr>\n";
						$latestContent = $document->getLatestContent();
						$previewer->createPreview($latestContent);
						print "<td><a href=\"../op/op.Download.php?documentid=".$res["documentID"]."&version=".$res["version"]."\">";
						if($previewer->hasPreview($latestContent)) {
							print "<img class=\"mimeicon\" width=\"".$previewwidth."\" src=\"../op/op.Preview.php?documentid=".$document->getID()."&version=".$latestContent->getVersion()."&width=".$previewwidth."\" title=\"".htmlspecialchars($latestContent->getMimeType())."\">";
						} else {
							print "<img class=\"mimeicon\" width=\"".$previewwidth."\" src=\"".$this->getMimeIcon($latestContent->getFileType())."\" title=\"".htmlspecialchars($latestContent->getMimeType())."\">";
						}
						print "</a></td>";
						print "<td><a href=\"out.ViewDocument.php?documentid=".$res["documentID"]."&currenttab=revapp\">" . htmlspecialchars($res["name"]) . "</a></td>\n";
						print "<td>".getOverallStatusText($res["status"])."</td>";
						print "<td>".$res["version"]."</td>";
						print "<td>".$res["statusDate"]." ".htmlspecialchars($res["statusName"])."</td>";
						print "<td>".(!$res["expires"] ? "-":getReadableDate($res["expires"]))."</td>";				
						print "</tr>\n";
					}		
					print "</tbody></table>";	
					
				}
				else printMLText("no_docs_to_look_at");
				
				$this->contentContainerEnd();

				// Get list of documents owned by current user that are pending review or
				// pending approval.
				$queryStr = "SELECT `tblDocuments`.*, `tblDocumentLocks`.`userID` as `lockUser`, ".
					"`tblDocumentContent`.`version`, `tblDocumentStatus`.*, `tblDocumentStatusLog`.`status`, ".
					"`tblDocumentStatusLog`.`comment` AS `statusComment`, `tblDocumentStatusLog`.`date` as `statusDate`, ".
					"`tblDocumentStatusLog`.`userID`, `oTbl`.`fullName` AS `ownerName`, `sTbl`.`fullName` AS `statusName` ".
					"FROM `tblDocumentContent` ".
					"LEFT JOIN `tblDocuments` ON `tblDocuments`.`id` = `tblDocumentContent`.`document` ".
					"LEFT JOIN `tblDocumentStatus` ON `tblDocumentStatus`.`documentID` = `tblDocumentContent`.`document` ".
					"LEFT JOIN `tblDocumentStatusLog` ON `tblDocumentStatusLog`.`statusID` = `tblDocumentStatus`.`statusID` ".
					"LEFT JOIN `ttstatid` ON `ttstatid`.`maxLogID` = `tblDocumentStatusLog`.`statusLogID` ".
					"LEFT JOIN `ttcontentid` ON `ttcontentid`.`maxVersion` = `tblDocumentStatus`.`version` AND `ttcontentid`.`document` = `tblDocumentStatus`.`documentID` ".
					"LEFT JOIN `tblDocumentLocks` ON `tblDocuments`.`id`=`tblDocumentLocks`.`document` ".
					"LEFT JOIN `tblUsers` AS `oTbl` on `oTbl`.`id` = `tblDocuments`.`owner` ".
					"LEFT JOIN `tblUsers` AS `sTbl` on `sTbl`.`id` = `tblDocumentStatusLog`.`userID` ".
					"WHERE `ttstatid`.`maxLogID`=`tblDocumentStatusLog`.`statusLogID` ".
					"AND `ttcontentid`.`maxVersion` = `tblDocumentContent`.`version` ".
					"AND `tblDocuments`.`owner` = '".$user->getID()."' ".
					"AND `tblDocumentStatusLog`.`status` IN (".S_REJECTED.") ".
					"ORDER BY `statusDate` DESC";

				$resArr = $db->getResultArray($queryStr);
				if (is_bool($resArr) && !$resArr) {
					$this->contentHeading(getMLText("warning"));
					$this->contentContainer("Internal error. Unable to complete request. Exiting.");
					$this->htmlEndPage();
					exit;
				}

				$this->contentHeading(getMLText("documents_user_rejected"));
				$this->contentContainerStart();
				if (count($resArr)>0) {

					print "<table class=\"table table-condensed\">";
					print "<thead>\n<tr>\n";
					print "<th></th>";
					print "<th>".getMLText("name")."</th>\n";
					print "<th>".getMLText("status")."</th>\n";
					print "<th>".getMLText("version")."</th>\n";
					print "<th>".getMLText("last_update")."</th>\n";
					print "<th>".getMLText("expires")."</th>\n";
					print "</tr>\n</thead>\n<tbody>\n";

					foreach ($resArr as $res) {
						$document = $dms->getDocument($res["documentID"]);
						$document->verifyLastestContentExpriry();
					
						// verify expiry
						if ( $res["expires"] && time()>$res["expires"]+24*60*60 ){
							if  ( $res["status"]==S_REJECTED ){
								$res["status"]=S_EXPIRED;
							}
						}
					
						print "<tr>\n";
						$latestContent = $document->getLatestContent();
						$previewer->createPreview($latestContent);
						print "<td><a href=\"../op/op.Download.php?documentid=".$res["documentID"]."&version=".$res["version"]."\">";
						if($previewer->hasPreview($latestContent)) {
							print "<img class=\"mimeicon\" width=\"".$previewwidth."\" src=\"../op/op.Preview.php?documentid=".$document->getID()."&version=".$latestContent->getVersion()."&width=".$previewwidth."\" title=\"".htmlspecialchars($latestContent->getMimeType())."\">";
						} else {
							print "<img class=\"mimeicon\" width=\"".$previewwidth."\" src=\"".$this->getMimeIcon($latestContent->getFileType())."\" title=\"".htmlspecialchars($latestContent->getMimeType())."\">";
						}
						print "</a></td>";
						print "<td><a href=\"out.ViewDocument.php?documentid=".$res["documentID"]."&currenttab=revapp\">" . htmlspecialchars($res["name"]) . "</a></td>\n";
						print "<td>".getOverallStatusText($res["status"])."</td>";
						print "<td>".$res["version"]."</td>";
						print "<td>".$res["statusDate"]." ".htmlspecialchars($res["statusName"])."</td>";
						print "<td>".(!$res["expires"] ? "-":getReadableDate($res["expires"]))."</td>";				
						print "</tr>\n";
					}		
					print "</tbody></table>";	
					
				}
				else printMLText("no_docs_to_look_at");
				
				$this->contentContainerEnd();
			} elseif($workflowmode == 'advanced') {
				// Get document list for the current user.
				$workflowStatus = $user->getWorkflowStatus();

				// Create a comma separated list of all the documentIDs whose information is
				// required.
				$dList = array();
				foreach ($workflowStatus["u"] as $st) {
					if (!in_array($st["document"], $dList)) {
						$dList[] = $st["document"];
					}
				}
				foreach ($workflowStatus["g"] as $st) {
					if (!in_array($st["document"], $dList)) {
						$dList[] = $st["document"];
					}
				}
				$docCSV = "";
				foreach ($dList as $d) {
					$docCSV .= (strlen($docCSV)==0 ? "" : ", ")."'".$d."'";
				}
				
				if (strlen($docCSV)>0) {
					// Get the document information.
					$queryStr = "SELECT `tblDocuments`.*, `tblDocumentLocks`.`userID` as `lockUser`, ".
						"`tblDocumentContent`.`version`, `tblDocumentStatus`.*, `tblDocumentStatusLog`.`status`, ".
						"`tblDocumentStatusLog`.`comment` AS `statusComment`, `tblDocumentStatusLog`.`date` as `statusDate`, ".
						"`tblDocumentStatusLog`.`userID`, `oTbl`.`fullName` AS `ownerName`, `sTbl`.`fullName` AS `statusName` ".
						"FROM `tblDocumentContent` ".
						"LEFT JOIN `tblDocuments` ON `tblDocuments`.`id` = `tblDocumentContent`.`document` ".
						"LEFT JOIN `tblDocumentStatus` ON `tblDocumentStatus`.`documentID` = `tblDocumentContent`.`document` ".
						"LEFT JOIN `tblDocumentStatusLog` ON `tblDocumentStatusLog`.`statusID` = `tblDocumentStatus`.`statusID` ".
						"LEFT JOIN `ttstatid` ON `ttstatid`.`maxLogID` = `tblDocumentStatusLog`.`statusLogID` ".
						"LEFT JOIN `ttcontentid` ON `ttcontentid`.`maxVersion` = `tblDocumentStatus`.`version` AND `ttcontentid`.`document` = `tblDocumentStatus`.`documentID` ".
						"LEFT JOIN `tblDocumentLocks` ON `tblDocuments`.`id`=`tblDocumentLocks`.`document` ".
						"LEFT JOIN `tblUsers` AS `oTbl` on `oTbl`.`id` = `tblDocuments`.`owner` ".
						"LEFT JOIN `tblUsers` AS `sTbl` on `sTbl`.`id` = `tblDocumentStatusLog`.`userID` ".
						"WHERE `ttstatid`.`maxLogID`=`tblDocumentStatusLog`.`statusLogID` ".
						"AND `ttcontentid`.`maxVersion` = `tblDocumentContent`.`version` ".
						"AND `tblDocumentStatusLog`.`status` IN (".S_IN_WORKFLOW.", ".S_EXPIRED.") ".
						"AND `tblDocuments`.`id` IN (" . $docCSV . ") ".
						"ORDER BY `statusDate` DESC";

					$resArr = $db->getResultArray($queryStr);
					if (is_bool($resArr) && !$resArr) {
						$this->contentHeading(getMLText("warning"));
						$this->contentContainer(getMLText("internal_error_exit"));
						$this->htmlEndPage();
						exit;
					}
					
					// Create an array to hold all of these results, and index the array by
					// document id. This makes it easier to retrieve document ID information
					// later on and saves us having to repeatedly poll the database every time
					// new document information is required.
					$docIdx = array();
					foreach ($resArr as $res) {
						
						// verify expiry
						if ( $res["expires"] && time()>$res["expires"]+24*60*60 ){
							if  ( $res["status"]==S_IN_WORKFLOW ){
								$res["status"]=S_EXPIRED;
							}
						}

						$docIdx[$res["id"]][$res["version"]] = $res;
					}

					// List the documents where a review has been requested.
					$this->contentHeading(getMLText("documents_to_process"));
					$this->contentContainerStart();
					$printheader=true;
					$iRev = array();
					$dList = array();
					foreach ($workflowStatus["u"] as $st) {
					
						if ( isset($docIdx[$st["document"]][$st["version"]]) && !in_array($st["document"], $dList) ) {
							$dList[] = $st["document"];
							$document = $dms->getDocument($st["document"]);
							$document->verifyLastestContentExpriry();
						
							if ($printheader){
								print "<table class=\"table table-condensed\">";
								print "<thead>\n<tr>\n";
								print "<th></th>\n";
								print "<th>".getMLText("name")."</th>\n";
								print "<th>".getMLText("owner")."</th>\n";
								print "<th>".getMLText("status")."</th>\n";
								print "<th>".getMLText("version")."</th>\n";
								print "<th>".getMLText("last_update")."</th>\n";
								print "<th>".getMLText("expires")."</th>\n";
								print "</tr>\n</thead>\n<tbody>\n";
								$printheader=false;
							}
						
							print "<tr>\n";
							$latestContent = $document->getLatestContent();
							$workflow = $latestContent->getWorkflow();
							$previewer->createPreview($latestContent);
							print "<td><a href=\"../op/op.Download.php?documentid=".$st["document"]."&version=".$st["version"]."\">";
							if($previewer->hasPreview($latestContent)) {
								print "<img class=\"mimeicon\" width=\"".$previewwidth."\" src=\"../op/op.Preview.php?documentid=".$document->getID()."&version=".$latestContent->getVersion()."&width=".$previewwidth."\" title=\"".htmlspecialchars($latestContent->getMimeType())."\">";
							} else {
								print "<img class=\"mimeicon\" width=\"".$previewwidth."\" src=\"".$this->getMimeIcon($latestContent->getFileType())."\" title=\"".htmlspecialchars($latestContent->getMimeType())."\">";
							}
							print "</a></td>";
							$workflowstate = $latestContent->getWorkflowState();
							print '<td>'.getOverallStatusText($docIdx[$st["document"]][$st["version"]]["status"]).': '.$workflow->getName().'<br />'.$workflowstate->getName().'</td>';
							print "<td><a href=\"out.ViewDocument.php?documentid=".$st["document"]."&currenttab=workflow\">".htmlspecialchars($docIdx[$st["document"]][$st["version"]]["name"])."</a></td>";
							print "<td>".htmlspecialchars($docIdx[$st["document"]][$st["version"]]["ownerName"])."</td>";
							print "<td>".$st["version"]."</td>";
							print "<td>".$st["date"]." ". htmlspecialchars($docIdx[$st["document"]][$st["version"]]["statusName"]) ."</td>";
							print "<td".($docIdx[$st["document"]][$st["version"]]['status']!=S_EXPIRED?"":" class=\"warning\"").">".(!$docIdx[$st["document"]][$st["version"]]["expires"] ? "-":getReadableDate($docIdx[$st["document"]][$st["version"]]["expires"]))."</td>";				
							print "</tr>\n";
						}
					}
					foreach ($workflowStatus["g"] as $st) {
					
						if (!in_array($st["document"], $iRev) && isset($docIdx[$st["document"]][$st["version"]]) && !in_array($st["document"], $dList) /* && $docIdx[$st["documentID"]][$st["version"]]['owner'] != $user->getId() */) {
							$dList[] = $st["document"];
							$document = $dms->getDocument($st["document"]);
							$document->verifyLastestContentExpriry();

							if ($printheader){
								print "<table class=\"table table-condensed\">";
								print "<thead>\n<tr>\n";
								print "<th></th>\n";
								print "<th>".getMLText("name")."</th>\n";
								print "<th>".getMLText("owner")."</th>\n";
								print "<th>".getMLText("status")."</th>\n";
								print "<th>".getMLText("version")."</th>\n";
								print "<th>".getMLText("last_update")."</th>\n";
								print "<th>".getMLText("expires")."</th>\n";
								print "</tr>\n</thead>\n<tbody>\n";
								$printheader=false;
							}

							print "<tr>\n";
							$latestContent = $document->getLatestContent();
							$workflow = $latestContent->getWorkflow();
							$previewer->createPreview($latestContent);
							print "<td><a href=\"../op/op.Download.php?documentid=".$st["document"]."&version=".$st["version"]."\">";
							if($previewer->hasPreview($latestContent)) {
								print "<img class=\"mimeicon\" width=\"".$previewwidth."\" src=\"../op/op.Preview.php?documentid=".$document->getID()."&version=".$latestContent->getVersion()."&width=".$previewwidth."\" title=\"".htmlspecialchars($latestContent->getMimeType())."\">";
							} else {
								print "<img class=\"mimeicon\" width=\"".$previewwidth."\" src=\"".$this->getMimeIcon($latestContent->getFileType())."\" title=\"".htmlspecialchars($latestContent->getMimeType())."\">";
							}
							print "</a></td>";
							print "<td><a href=\"out.ViewDocument.php?documentid=".$st["document"]."&currenttab=workflow\">".htmlspecialchars($docIdx[$st["document"]][$st["version"]]["name"])."</a></td>";
							print "<td>".htmlspecialchars($docIdx[$st["document"]][$st["version"]]["ownerName"])."</td>";
							$workflowstate = $latestContent->getWorkflowState();
							print '<td>'.getOverallStatusText($docIdx[$st["document"]][$st["version"]]["status"]).': '.$workflow->getName().'<br />'.$workflowstate->getName().'</td>';
							print "<td>".$st["version"]."</td>";
							print "<td>".$st["date"]." ". htmlspecialchars($docIdx[$st["document"]][$st["version"]]["statusName"])."</td>";
							print "<td".($docIdx[$st["document"]][$st["version"]]['status']!=S_EXPIRED?"":" class=\"warning\"").">".(!$docIdx[$st["document"]][$st["version"]]["expires"] ? "-":getReadableDate($docIdx[$st["document"]][$st["version"]]["expires"]))."</td>";				
							print "</tr>\n";
						}
					}
					if (!$printheader){
						echo "</tbody>\n</table>";
					}else{
						printMLText("no_docs_to_check");
					}
					$this->contentContainerEnd();
				}

				// Get list of documents owned by current user that are pending review or
				// pending approval.
				$queryStr = "SELECT `tblDocuments`.*, `tblDocumentLocks`.`userID` as `lockUser`, ".
					"`tblDocumentContent`.`version`, `tblDocumentStatus`.*, `tblDocumentStatusLog`.`status`, ".
					"`tblDocumentStatusLog`.`comment` AS `statusComment`, `tblDocumentStatusLog`.`date` as `statusDate`, ".
					"`tblDocumentStatusLog`.`userID`, `oTbl`.`fullName` AS `ownerName`, `sTbl`.`fullName` AS `statusName` ".
					"FROM `tblDocumentContent` ".
					"LEFT JOIN `tblDocuments` ON `tblDocuments`.`id` = `tblDocumentContent`.`document` ".
					"LEFT JOIN `tblDocumentStatus` ON `tblDocumentStatus`.`documentID` = `tblDocumentContent`.`document` ".
					"LEFT JOIN `tblDocumentStatusLog` ON `tblDocumentStatusLog`.`statusID` = `tblDocumentStatus`.`statusID` ".
					"LEFT JOIN `ttstatid` ON `ttstatid`.`maxLogID` = `tblDocumentStatusLog`.`statusLogID` ".
					"LEFT JOIN `ttcontentid` ON `ttcontentid`.`maxVersion` = `tblDocumentStatus`.`version` AND `ttcontentid`.`document` = `tblDocumentStatus`.`documentID` ".
					"LEFT JOIN `tblDocumentLocks` ON `tblDocuments`.`id`=`tblDocumentLocks`.`document` ".
					"LEFT JOIN `tblUsers` AS `oTbl` on `oTbl`.`id` = `tblDocuments`.`owner` ".
					"LEFT JOIN `tblUsers` AS `sTbl` on `sTbl`.`id` = `tblDocumentStatusLog`.`userID` ".
					"WHERE `ttstatid`.`maxLogID`=`tblDocumentStatusLog`.`statusLogID` ".
					"AND `ttcontentid`.`maxVersion` = `tblDocumentContent`.`version` ".
					"AND `tblDocuments`.`owner` = '".$user->getID()."' ".
					"AND `tblDocumentStatusLog`.`status` IN (".S_IN_WORKFLOW.") ".
					"ORDER BY `statusDate` DESC";

				$resArr = $db->getResultArray($queryStr);
				if (is_bool($resArr) && !$resArr) {
					$this->contentHeading(getMLText("warning"));
					$this->contentContainer("Internal error. Unable to complete request. Exiting.");
					$this->htmlEndPage();
					exit;
				}

				$this->contentHeading(getMLText("documents_user_requiring_attention"));
				$this->contentContainerStart();
				if (count($resArr)>0) {

					print "<table class=\"table table-condensed\">";
					print "<thead>\n<tr>\n";
					print "<th></th>";
					print "<th>".getMLText("name")."</th>\n";
					print "<th>".getMLText("status")."</th>\n";
					print "<th>".getMLText("version")."</th>\n";
					print "<th>".getMLText("last_update")."</th>\n";
					print "<th>".getMLText("expires")."</th>\n";
					print "</tr>\n</thead>\n<tbody>\n";

					foreach ($resArr as $res) {
						$document = $dms->getDocument($res["documentID"]);
						$document->verifyLastestContentExpriry();
					
						// verify expiry
						if ( $res["expires"] && time()>$res["expires"]+24*60*60 ){
							if  ( $res["status"]==S_IN_WORKFLOW ){
								$res["status"]=S_EXPIRED;
							}
						}
					
						print "<tr>\n";
						$latestContent = $document->getLatestContent();
						$workflow = $latestContent->getWorkflow();
						$previewer->createPreview($latestContent);
						print "<td><a href=\"../op/op.Download.php?documentid=".$res["documentID"]."&version=".$res["version"]."\">";
						if($previewer->hasPreview($latestContent)) {
							print "<img class=\"mimeicon\" width=\"".$previewwidth."\" src=\"../op/op.Preview.php?documentid=".$document->getID()."&version=".$latestContent->getVersion()."&width=".$previewwidth."\" title=\"".htmlspecialchars($latestContent->getMimeType())."\">";
						} else {
							print "<img class=\"mimeicon\" width=\"".$previewwidth."\" src=\"".$this->getMimeIcon($latestContent->getFileType())."\" title=\"".htmlspecialchars($latestContent->getMimeType())."\">";
						}
						print "</a></td>";
						print "<td><a href=\"out.ViewDocument.php?documentid=".$res["documentID"]."&currenttab=workflow\">" . htmlspecialchars($res["name"]) . "</a></td>\n";
//						print "<td>".getOverallStatusText($res["status"])."</td>";
						$workflowstate = $latestContent->getWorkflowState();
						print '<td>'.getOverallStatusText($res["status"]).': '.$workflow->getName().'<br />'.$workflowstate->getName().'</td>';
						print "<td>".$res["version"]."</td>";
						print "<td>".$res["statusDate"]." ".htmlspecialchars($res["statusName"])."</td>";
						print "<td>".(!$res["expires"] ? "-":getReadableDate($res["expires"]))."</td>";				
						print "</tr>\n";
					}		
					print "</tbody></table>";	
					
				}
				else printMLText("no_docs_to_look_at");

				$this->contentContainerEnd();
			}
			
			// Get list of documents locked by current user 
			$queryStr = "SELECT `tblDocuments`.*, `tblDocumentLocks`.`userID` as `lockUser`, ".
				"`tblDocumentContent`.`version`, `tblDocumentStatus`.*, `tblDocumentStatusLog`.`status`, ".
				"`tblDocumentStatusLog`.`comment` AS `statusComment`, `tblDocumentStatusLog`.`date` as `statusDate`, ".
				"`tblDocumentStatusLog`.`userID`, `oTbl`.`fullName` AS `ownerName`, `sTbl`.`fullName` AS `statusName` ".
				"FROM `tblDocumentContent` ".
				"LEFT JOIN `tblDocuments` ON `tblDocuments`.`id` = `tblDocumentContent`.`document` ".
				"LEFT JOIN `tblDocumentStatus` ON `tblDocumentStatus`.`documentID` = `tblDocumentContent`.`document` ".
				"LEFT JOIN `tblDocumentStatusLog` ON `tblDocumentStatusLog`.`statusID` = `tblDocumentStatus`.`statusID` ".
				"LEFT JOIN `ttstatid` ON `ttstatid`.`maxLogID` = `tblDocumentStatusLog`.`statusLogID` ".
				"LEFT JOIN `ttcontentid` ON `ttcontentid`.`maxVersion` = `tblDocumentStatus`.`version` AND `ttcontentid`.`document` = `tblDocumentStatus`.`documentID` ".
				"LEFT JOIN `tblDocumentLocks` ON `tblDocuments`.`id`=`tblDocumentLocks`.`document` ".
				"LEFT JOIN `tblUsers` AS `oTbl` on `oTbl`.`id` = `tblDocuments`.`owner` ".
				"LEFT JOIN `tblUsers` AS `sTbl` on `sTbl`.`id` = `tblDocumentStatusLog`.`userID` ".
				"WHERE `ttstatid`.`maxLogID`=`tblDocumentStatusLog`.`statusLogID` ".
				"AND `ttcontentid`.`maxVersion` = `tblDocumentContent`.`version` ".
				"AND `tblDocumentLocks`.`userID` = '".$user->getID()."' ".
				"ORDER BY `statusDate` DESC";

			$resArr = $db->getResultArray($queryStr);
			if (is_bool($resArr) && !$resArr) {
				$this->contentHeading(getMLText("warning"));
				$this->contentContainer("Internal error. Unable to complete request. Exiting.");
				$this->htmlEndPage();
				exit;
			}

			$this->contentHeading(getMLText("documents_locked_by_you"));
			$this->contentContainerStart();
			if (count($resArr)>0) {

				print "<table class=\"table table-condensed\">";
				print "<thead>\n<tr>\n";
				print "<th></th>";
				print "<th>".getMLText("name")."</th>\n";
				print "<th>".getMLText("status")."</th>\n";
				print "<th>".getMLText("version")."</th>\n";
				print "<th>".getMLText("last_update")."</th>\n";
				print "<th>".getMLText("expires")."</th>\n";
				print "</tr>\n</thead>\n<tbody>\n";

				foreach ($resArr as $res) {
					$document = $dms->getDocument($res["documentID"]);
					$document->verifyLastestContentExpriry();
				
					// verify expiry
					if ( $res["expires"] && time()>$res["expires"]+24*60*60 ){
						if  ( $res["status"]==S_DRAFT_APP || $res["status"]==S_DRAFT_REV ){
							$res["status"]=S_EXPIRED;
						}
					}
				
					print "<tr>\n";
					$latestContent = $document->getLatestContent();
					if($workflowmode == 'advanced')
						$workflow = $latestContent->getWorkflow();
					$previewer->createPreview($latestContent);
					print "<td><a href=\"../op/op.Download.php?documentid=".$res["documentID"]."&version=".$res["version"]."\">";
					if($previewer->hasPreview($latestContent)) {
						print "<img class=\"mimeicon\" width=\"".$previewwidth."\" src=\"../op/op.Preview.php?documentid=".$document->getID()."&version=".$latestContent->getVersion()."&width=".$previewwidth."\" title=\"".htmlspecialchars($latestContent->getMimeType())."\">";
					} else {
						print "<img class=\"mimeicon\" width=\"".$previewwidth."\" src=\"".$this->getMimeIcon($latestContent->getFileType())."\" title=\"".htmlspecialchars($latestContent->getMimeType())."\">";
					}
					print "</a></td>";
					print "<td><a href=\"out.ViewDocument.php?documentid=".$res["documentID"]."\">" . htmlspecialchars($res["name"]) . "</a></td>\n";
					if($workflowmode == 'advanced' && $workflow) {
						$workflowstate = $latestContent->getWorkflowState();
						print '<td>'.getOverallStatusText($res["status"]).': '.$workflow->getName().'<br />'.$workflowstate->getName().'</td>';
					} else {
						print "<td>".getOverallStatusText($res["status"])."</td>";
					}
					print "<td>".$res["version"]."</td>";
					print "<td>".$res["statusDate"]." ".htmlspecialchars($res["statusName"])."</td>";
					print "<td>".(!$res["expires"] ? "-":getReadableDate($res["expires"]))."</td>";				
					print "</tr>\n";
				}		
				print "</tbody></table>";	

			}
			else printMLText("no_docs_locked");

			$this->contentContainerEnd();

			/* Documents expired */
			if($docs = $dms->getDocumentsExpired(-3*365, $user)) {
			$this->contentHeading(getMLText("documents_expired"));
			$this->contentContainerStart();
			if (count($resArr)>0) {

				print "<table class=\"table table-condensed\">";
				print "<thead>\n<tr>\n";
				print "<th></th>";
				print "<th>".getMLText("name")."</th>\n";
				print "<th>".getMLText("status")."</th>\n";
				print "<th>".getMLText("version")."</th>\n";
				print "<th>".getMLText("last_update")."</th>\n";
				print "<th>".getMLText("expires")."</th>\n";
				print "</tr>\n</thead>\n<tbody>\n";

				foreach ($docs as $document) {
				
					print "<tr>\n";
					$latestContent = $document->getLatestContent();
					if($workflowmode == 'advanced')
						$workflow = $latestContent->getWorkflow();
					$previewer->createPreview($latestContent);
					print "<td><a href=\"../op/op.Download.php?documentid=".$document->getID()."&version=".$latestContent->getVersion()."\">";
					if($previewer->hasPreview($latestContent)) {
						print "<img class=\"mimeicon\" width=\"".$previewwidth."\" src=\"../op/op.Preview.php?documentid=".$document->getID()."&version=".$latestContent->getVersion()."&width=".$previewwidth."\" title=\"".htmlspecialchars($latestContent->getMimeType())."\">";
					} else {
						print "<img class=\"mimeicon\" width=\"".$previewwidth."\" src=\"".$this->getMimeIcon($latestContent->getFileType())."\" title=\"".htmlspecialchars($latestContent->getMimeType())."\">";
					}
					print "</a></td>";
					$status = $latestContent->getStatus();
					print "<td><a href=\"out.ViewDocument.php?documentid=".$document->getID()."\">" . htmlspecialchars($document->getName()) . "</a></td>\n";
					if($workflowmode == 'advanced' && $workflow) {
						$workflowstate = $latestContent->getWorkflowState();
						print '<td>'.getOverallStatusText($status["status"]).': '.$workflow->getName().'<br />'.$workflowstate->getName().'</td>';
					} else {
						print "<td>".getOverallStatusText($status["status"])."</td>";
					}
					print "<td>".$latestContent->getVersion()."</td>";
					print "<td>".$status["date"]." ".htmlspecialchars($dms->getUser($status["userID"])->getFullName())."</td>";
					print "<td>".(!$document->getExpires() ? "-":getReadableDate($document->getExpires()))."</td>";				
					print "</tr>\n";
				}		
				print "</tbody></table>";	

			}
			else printMLText("no_docs_locked");

			$this->contentContainerEnd();
			}

		}
		else {

			// Get list of documents owned by current user
			if (!$db->createTemporaryTable("ttstatid")) {
				$this->contentHeading(getMLText("warning"));
				$this->contentContainer(getMLText("internal_error_exit"));
				$this->htmlEndPage();
				exit;
			}

			if (!$db->createTemporaryTable("ttcontentid")) {
				$this->contentHeading(getMLText("warning"));
				$this->contentContainer(getMLText("internal_error_exit"));
				$this->htmlEndPage();
				exit;
			}
			$queryStr = "SELECT `tblDocuments`.*, `tblDocumentLocks`.`userID` as `lockUser`, ".
				"`tblDocumentContent`.`version`, `tblDocumentStatus`.*, `tblDocumentStatusLog`.`status`, ".
				"`tblDocumentStatusLog`.`comment` AS `statusComment`, `tblDocumentStatusLog`.`date` as `statusDate`, ".
				"`tblDocumentStatusLog`.`userID`, `oTbl`.`fullName` AS `ownerName`, `sTbl`.`fullName` AS `statusName` ".
				"FROM `tblDocumentContent` ".
				"LEFT JOIN `tblDocuments` ON `tblDocuments`.`id` = `tblDocumentContent`.`document` ".
				"LEFT JOIN `tblDocumentStatus` ON `tblDocumentStatus`.`documentID` = `tblDocumentContent`.`document` ".
				"LEFT JOIN `tblDocumentStatusLog` ON `tblDocumentStatusLog`.`statusID` = `tblDocumentStatus`.`statusID` ".
				"LEFT JOIN `ttstatid` ON `ttstatid`.`maxLogID` = `tblDocumentStatusLog`.`statusLogID` ".
				"LEFT JOIN `ttcontentid` ON `ttcontentid`.`maxVersion` = `tblDocumentStatus`.`version` AND `ttcontentid`.`document` = `tblDocumentStatus`.`documentID` ".
				"LEFT JOIN `tblDocumentLocks` ON `tblDocuments`.`id`=`tblDocumentLocks`.`document` ".
				"LEFT JOIN `tblUsers` AS `oTbl` on `oTbl`.`id` = `tblDocuments`.`owner` ".
				"LEFT JOIN `tblUsers` AS `sTbl` on `sTbl`.`id` = `tblDocumentStatusLog`.`userID` ".
				"WHERE `ttstatid`.`maxLogID`=`tblDocumentStatusLog`.`statusLogID` ".
				"AND `ttcontentid`.`maxVersion` = `tblDocumentContent`.`version` ".
				"AND `tblDocuments`.`owner` = '".$user->getID()."' ";
				
			if ($orderby=='e') $queryStr .= "ORDER BY `expires`";
			else if ($orderby=='u') $queryStr .= "ORDER BY `statusDate`";
			else if ($orderby=='s') $queryStr .= "ORDER BY `status`";
			else $queryStr .= "ORDER BY `name`";

			$resArr = $db->getResultArray($queryStr);
			if (is_bool($resArr) && !$resArr) {
				$this->contentHeading(getMLText("warning"));
				$this->contentContainer(getMLText("internal_error_exit"));
				$this->htmlEndPage();
				exit;
			}
			
			$this->contentHeading(getMLText("all_documents"));
			$this->contentContainerStart();

			if (count($resArr)>0) {

				print "<table class=\"table table-condensed\">";
				print "<thead>\n<tr>\n";
				print "<th></th>";
				print "<th><a href=\"../out/out.MyDocuments.php?orderby=n\">".getMLText("name")."</a></th>\n";
				print "<th><a href=\"../out/out.MyDocuments.php?orderby=s\">".getMLText("status")."</a></th>\n";
				print "<th>".getMLText("version")."</th>\n";
				print "<th><a href=\"../out/out.MyDocuments.php?orderby=u\">".getMLText("last_update")."</a></th>\n";
				print "<th><a href=\"../out/out.MyDocuments.php?orderby=e\">".getMLText("expires")."</a></th>\n";
				print "</tr>\n</thead>\n<tbody>\n";

				$previewer = new LetoDMS_Preview_Previewer($cachedir, $previewwidth, $timeout);
				foreach ($resArr as $res) {
					$document = $dms->getDocument($res["documentID"]);
					$document->verifyLastestContentExpriry();
				
					// verify expiry
					if ( $res["expires"] && time()>$res["expires"]+24*60*60 ){
						if  ( $res["status"]==S_DRAFT_APP || $res["status"]==S_DRAFT_REV ){
							$res["status"]=S_EXPIRED;
						}
					}
				
					print "<tr>\n";
					$latestContent = $document->getLatestContent();
					$previewer->createPreview($latestContent);
					print "<td><a href=\"../op/op.Download.php?documentid=".$res["documentID"]."&version=".$res["version"]."\">";
					if($previewer->hasPreview($latestContent)) {
						print "<img class=\"mimeicon\" width=\"".$previewwidth."\" src=\"../op/op.Preview.php?documentid=".$document->getID()."&version=".$latestContent->getVersion()."&width=".$previewwidth."\" title=\"".htmlspecialchars($latestContent->getMimeType())."\">";
					} else {
						print "<img class=\"mimeicon\" width=\"".$previewwidth."\" src=\"".$this->getMimeIcon($latestContent->getFileType())."\" title=\"".htmlspecialchars($latestContent->getMimeType())."\">";
					}
					print "</a></td>";
					print "<td><a href=\"out.ViewDocument.php?documentid=".$res["documentID"]."\">" . htmlspecialchars($res["name"]) . "</a></td>\n";
					print "<td>".getOverallStatusText($res["status"])."</td>";
					print "<td>".$res["version"]."</td>";
					print "<td>".$res["statusDate"]." ". htmlspecialchars($res["statusName"])."</td>";
					//print "<td>".(!$res["expires"] ? getMLText("does_not_expire"):getReadableDate($res["expires"]))."</td>";				
					print "<td>".(!$res["expires"] ? "-":getReadableDate($res["expires"]))."</td>";				
					print "</tr>\n";
				}
				print "</tbody></table>";
			}
			else printMLText("empty_notify_list");
			
			$this->contentContainerEnd();
		}

		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
