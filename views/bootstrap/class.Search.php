<?php
/**
 * Implementation of Search result view
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
 * Class which outputs the html page for Search result view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_Search extends LetoDMS_Bootstrap_Style {

	/**
	 * Mark search query sting in a given string
	 *
	 * @param string $str mark this text
	 * @param string $tag wrap the marked text with this html tag
	 * @return string marked text
	 */
	function markQuery($str, $tag = "b") { /* {{{ */
		$querywords = preg_split("/ /", $this->query);
		
		foreach ($querywords as $queryword)
			$str = str_ireplace("($queryword)", "<" . $tag . ">\\1</" . $tag . ">", $str);
		
		return $str;
	} /* }}} */

	function js() { /* {{{ */
		header('Content-Type: application/javascript; charset=UTF-8');

		parent::jsTranslations(array('cancel', 'splash_move_document', 'confirm_move_document', 'move_document', 'splash_move_folder', 'confirm_move_folder', 'move_folder'));

		$this->printFolderChooserJs("form1");
		$this->printDeleteFolderButtonJs();
		$this->printDeleteDocumentButtonJs();
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$fullsearch = $this->params['fullsearch'];
		$totaldocs = $this->params['totaldocs'];
		$totalfolders = $this->params['totalfolders'];
		$attrdefs = $this->params['attrdefs'];
		$allCats = $this->params['allcategories'];
		$allUsers = $this->params['allusers'];
		$mode = $this->params['mode'];
		$resultmode = $this->params['resultmode'];
		$workflowmode = $this->params['workflowmode'];
		$enablefullsearch = $this->params['enablefullsearch'];
		$enableclipboard = $this->params['enableclipboard'];
		$attributes = $this->params['attributes'];
		$categories = $this->params['categories'];
		$owner = $this->params['owner'];
		$startfolder = $this->params['startfolder'];
		$startdate = $this->params['startdate'];
		$stopdate = $this->params['stopdate'];
		$expstartdate = $this->params['expstartdate'];
		$expstopdate = $this->params['expstopdate'];
		$creationdate = $this->params['creationdate'];
		$expirationdate = $this->params['expirationdate'];
		$status = $this->params['status'];
		$this->query = $this->params['query'];
		$entries = $this->params['searchhits'];
		$totalpages = $this->params['totalpages'];
		$pageNumber = $this->params['pagenumber'];
		$searchTime = $this->params['searchtime'];
		$urlparams = $this->params['urlparams'];
		$searchin = $this->params['searchin'];
		$cachedir = $this->params['cachedir'];
		$previewwidth = $this->params['previewWidthList'];
		$timeout = $this->params['timeout'];

		$this->htmlAddHeader('<script type="text/javascript" src="../styles/'.$this->theme.'/bootbox/bootbox.min.js"></script>'."\n", 'js');

		$this->htmlStartPage(getMLText("search_results"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("search_results"), "");

		echo "<div class=\"row-fluid\">\n";
		echo "<div class=\"span4\">\n";
//echo "<pre>";print_r($_GET);echo "</pre>";
?>
  <ul class="nav nav-tabs" id="searchtab">
	  <li <?php echo ($fullsearch == false) ? 'class="active"' : ''; ?>><a data-target="#database" data-toggle="tab"><?php printMLText('databasesearch'); ?></a></li>
<?php
		if($enablefullsearch) {
?>
	  <li <?php echo ($fullsearch == true) ? 'class="active"' : ''; ?>><a data-target="#fulltext" data-toggle="tab"><?php printMLText('fullsearch'); ?></a></li>
<?php
		}
?>
	</ul>
	<div class="tab-content">
	  <div class="tab-pane <?php echo ($fullsearch == false) ? 'active' : ''; ?>" id="database">
<form action="../out/out.Search.php" name="form1">
<?php
// Database search Form {{{
		$this->contentContainerStart();
?>
<table class="table-condensed">
<tr>
<td><?php printMLText("search_query");?>:</td>
<td>
<input type="text" name="query" value="<?php echo htmlspecialchars($this->query); ?>" />
<select name="mode">
<option value="1" <?php echo ($mode=='AND') ? "selected" : ""; ?>><?php printMLText("search_mode_and");?>
<option value="0"<?php echo ($mode=='OR') ? "selected" : ""; ?>><?php printMLText("search_mode_or");?>
</select>
</td>
</tr>
<tr>
<td><?php printMLText("search_in");?>:</td>
<td>
<label class="checkbox" for="keywords"><input type="checkbox" id="keywords" name="searchin[]" value="1" <?php if(in_array('1', $searchin)) echo " checked"; ?>><?php printMLText("keywords");?> (<?php printMLText('documents_only'); ?>)</label>
<label class="checkbox" for="searchName"><input type="checkbox" name="searchin[]" id="searchName" value="2" <?php if(in_array('2', $searchin)) echo " checked"; ?>><?php printMLText("name");?></label>
<label class="checkbox" for="comment"><input type="checkbox" name="searchin[]" id="comment" value="3" <?php if(in_array('3', $searchin)) echo " checked"; ?>><?php printMLText("comment");?></label>
<label class="checkbox" for="attributes"><input type="checkbox" name="searchin[]" id="attributes" value="4" <?php if(in_array('4', $searchin)) echo " checked"; ?>><?php printMLText("attributes");?></label>
<label class="checkbox" for="id"><input type="checkbox" name="searchin[]" id="id" value="5" <?php if(in_array('5', $searchin)) echo " checked"; ?>><?php printMLText("id");?></label>
</td>
</tr>
<tr>
<td><?php printMLText("owner");?>:</td>
<td>
<select class="chzn-select" name="ownerid" data-allow-clear="true" data-placeholder="<?php printMLText('select_users'); ?>" data-no_results_text="<?php printMLText('unknown_owner'); ?>">
<option value="-1"></option>
<?php
		foreach ($allUsers as $userObj) {
			if ($userObj->isGuest() || ($userObj->isHidden() && $userObj->getID() != $user->getID() && !$user->isAdmin()))
				continue;
			print "<option value=\"".$userObj->getID()."\" ".(($owner && $userObj->getID() == $owner->getID()) ? "selected" : "").">" . htmlspecialchars($userObj->getLogin()." - ".$userObj->getFullName()) . "</option>\n";
		}
?>
</select>
</td>
</tr>
<tr>
<td><?php printMLText("search_resultmode");?>:</td>
<td>
<select name="resultmode">
<option value="3" <?php echo ($resultmode=='3') ? "selected" : ""; ?>><?php printMLText("search_resultmode_both");?>
<option value="2"<?php echo ($resultmode=='2') ? "selected" : ""; ?>><?php printMLText("search_mode_folders");?>
<option value="1"<?php echo ($resultmode=='1') ? "selected" : ""; ?>><?php printMLText("search_mode_documents");?>
</select>
</td>
</tr>
<tr>
<td><?php printMLText("under_folder")?>:</td>
<td><?php $this->printFolderChooserHtml("form1", M_READ, -1, $startfolder);?></td>
</tr>
<tr>
<td><?php printMLText("creation_date");?>:</td>
<td>
        <label class="checkbox inline">
				  <input type="checkbox" name="creationdate" value="true" <?php if($creationdate) echo "checked"; ?>/><?php printMLText("between");?>
        </label><br />
        <span class="input-append date" style="display: inline;" id="createstartdate" data-date="<?php echo date('Y-m-d'); ?>" data-date-format="yyyy-mm-dd" data-date-language="<?php echo str_replace('_', '-', $this->params['session']->getLanguage()); ?>">
          <input class="span4" size="16" name="createstart" type="text" value="<?php if($startdate) printf("%04d-%02d-%02d", $startdate['year'], $startdate['month'], $startdate['day']); else echo date('Y-m-d'); ?>">
          <span class="add-on"><i class="icon-calendar"></i></span>
        </span>&nbsp;
				<?php printMLText("and"); ?>
        <span class="input-append date" style="display: inline;" id="createenddate" data-date="<?php echo date('Y-m-d'); ?>" data-date-format="yyyy-mm-dd" data-date-language="<?php echo str_replace('_', '-', $this->params['session']->getLanguage()); ?>">
          <input class="span4" size="16" name="createend" type="text" value="<?php if($stopdate) printf("%04d-%02d-%02d", $stopdate['year'], $stopdate['month'], $stopdate['day']); else echo date('Y-m-d'); ?>">
          <span class="add-on"><i class="icon-calendar"></i></span>
        </span>
</td>
</tr>

<?php
		if($attrdefs) {
			foreach($attrdefs as $attrdef) {
				$attricon = '';
				if($attrdef->getObjType() == LetoDMS_Core_AttributeDefinition::objtype_all) {
?>
<tr>
	<td><?php echo htmlspecialchars($attrdef->getName()); ?>:</td>
	<td><?php $this->printAttributeEditField($attrdef, isset($attributes[$attrdef->getID()]) ? $attributes[$attrdef->getID()] : '', 'attributes', true) ?></td>
</tr>

<?php
				}
			}
		}
?>

<tr>
<td></td><td><button type="submit" class="btn"><i class="icon-search"></i> <?php printMLText("search"); ?></button></td>
</tr>

</table>
<?php
		$this->contentContainerEnd();
// }}}

		/* First check if any of the folder filters are set. If it is,
		 * open the accordion.
		 */
		$openfilterdlg = false;
		if($attrdefs) {
			foreach($attrdefs as $attrdef) {
				$attricon = '';
				if($attrdef->getObjType() == LetoDMS_Core_AttributeDefinition::objtype_document || $attrdef->getObjType() == LetoDMS_Core_AttributeDefinition::objtype_documentcontent) {
					if(!empty($attributes[$attrdef->getID()]))
						$openfilterdlg = true;
				}
			}
		}
		if($categories)
			$openfilterdlg = true;
		if($status)
			$openfilterdlg = true;
		if($expirationdate)
			$openfilterdlg = true;
?>
<div class="accordion" id="accordion2">
  <div class="accordion-group">
    <div class="accordion-heading">
      <a class="accordion-toggle" data-toggle="collapse" data-parent="#accordion2" href="#collapseOne">
        <?php printMLText('filter_for_documents'); ?>
      </a>
    </div>
    <div id="collapseOne" class="accordion-body <?php if(!$openfilterdlg) echo "collapse";?>" style="_height: 0px;">
      <div class="accordion-inner">
<table class="table-condensed">
<tr>
<td><?php printMLText("category");?>:</td>
<td>
<select class="chzn-select" name="categoryids[]" multiple="multiple" data-placeholder="<?php printMLText('select_category'); ?>" data-no_results_text="<?php printMLText('unknown_document_category'); ?>">
<!--
<option value="-1"><?php printMLText("all_categories");?>
-->
<?php
		$tmpcatids = array();
		foreach($categories as $tmpcat)
			$tmpcatids[] = $tmpcat->getID();
		foreach ($allCats as $catObj) {
			print "<option value=\"".$catObj->getID()."\" ".(in_array($catObj->getID(), $tmpcatids) ? "selected" : "").">" . htmlspecialchars($catObj->getName()) . "\n";
		}
?>
</select>
</td>
</tr>
<tr>
<td><?php printMLText("status");?>:</td>
<td>
<?php if($workflowmode == 'traditional' || $workflowmode == 'traditional_only_approval') { ?>
<?php if($workflowmode == 'traditional') { ?>
<label class="checkbox" for='pendingReview'><input type="checkbox" id="pendingReview" name="pendingReview" value="1" <?php echo in_array(S_DRAFT_REV, $status) ? "checked" : ""; ?>><?php printOverallStatusText(S_DRAFT_REV);?></label>
<?php } ?>
<label class="checkbox" for='pendingApproval'><input type="checkbox" id="pendingApproval" name="pendingApproval" value="1" <?php echo in_array(S_DRAFT_APP, $status) ? "checked" : ""; ?>><?php printOverallStatusText(S_DRAFT_APP);?></label>
<?php } else { ?>
<label class="checkbox" for='inWorkflow'><input type="checkbox" id="inWorkflow" name="inWorkflow" value="1" <?php echo in_array(S_IN_WORKFLOW, $status) ? "checked" : ""; ?>><?php printOverallStatusText(S_IN_WORKFLOW);?></label>
<?php } ?>
<label class="checkbox" for='released'><input type="checkbox" id="released" name="released" value="1" <?php echo in_array(S_RELEASED, $status) ? "checked" : ""; ?>><?php printOverallStatusText(S_RELEASED);?></label>
<label class="checkbox" for='rejected'><input type="checkbox" id="rejected" name="rejected" value="1" <?php echo in_array(S_REJECTED, $status) ? "checked" : ""; ?>><?php printOverallStatusText(S_REJECTED);?></label>
<label class="checkbox" for='obsolete'><input type="checkbox" id="obsolete" name="obsolete" value="1" <?php echo in_array(S_OBSOLETE, $status) ? "checked" : ""; ?>><?php printOverallStatusText(S_OBSOLETE);?></label>
<label class="checkbox" for='expired'><input type="checkbox" id="expired" name="expired" value="1" <?php echo in_array(S_EXPIRED, $status) ? "checked" : ""; ?>><?php printOverallStatusText(S_EXPIRED);?></label>
</td>
</tr>
<tr>
<td><?php printMLText("expires");?>:</td>
<td>
        <label class="checkbox inline">
				  <input type="checkbox" name="expirationdate" value="true" <?php if($expirationdate) echo "checked"; ?>/><?php printMLText("between");?>
        </label><br />
        <span class="input-append date" style="display: inline;" id="expirationstartdate" data-date="<?php echo date('Y-m-d'); ?>" data-date-format="yyyy-mm-dd" data-date-language="<?php echo str_replace('_', '-', $this->params['session']->getLanguage()); ?>">
          <input class="span4" size="16" name="expirationstart" type="text" value="<?php if($expstartdate) printf("%04d-%02d-%02d", $expstartdate['year'], $expstartdate['month'], $expstartdate['day']); else echo date('Y-m-d'); ?>">
          <span class="add-on"><i class="icon-calendar"></i></span>
        </span>&nbsp;
				<?php printMLText("and"); ?>
        <span class="input-append date" style="display: inline;" id="expirationenddate" data-date="<?php echo date('Y-m-d'); ?>" data-date-format="yyyy-mm-dd" data-date-language="<?php echo str_replace('_', '-', $this->params['session']->getLanguage()); ?>">
          <input class="span4" size="16" name="expirationend" type="text" value="<?php if($expstopdate) printf("%04d-%02d-%02d", $expstopdate['year'], $expstopdate['month'], $expstopdate['day']); else echo date('Y-m-d'); ?>">
          <span class="add-on"><i class="icon-calendar"></i></span>
        </span>
</td>
</tr>
<?php
		if($attrdefs) {
			foreach($attrdefs as $attrdef) {
				$attricon = '';
				if($attrdef->getObjType() == LetoDMS_Core_AttributeDefinition::objtype_document || $attrdef->getObjType() == LetoDMS_Core_AttributeDefinition::objtype_documentcontent) {
?>
<tr>
	<td><?php echo htmlspecialchars($attrdef->getName()); ?>:</td>
	<td><?php $this->printAttributeEditField($attrdef, isset($attributes[$attrdef->getID()]) ? $attributes[$attrdef->getID()] : '', 'attributes', true) ?></td>
</tr>

<?php
				}
			}
		}
?>
</table>
      </div>
    </div>
  </div>
</div>
<?php
		/* First check if any of the folder filters are set. If it is,
		 * open the accordion.
		 */
		$openfilterdlg = false;
		if($attrdefs) {
			foreach($attrdefs as $attrdef) {
				$attricon = '';
				if($attrdef->getObjType() == LetoDMS_Core_AttributeDefinition::objtype_folder) {
					if(!empty($attributes[$attrdef->getID()]))
						$openfilterdlg = true;
				}
			}
		}
?>
<div class="accordion" id="accordion3">
  <div class="accordion-group">
    <div class="accordion-heading">
      <a class="accordion-toggle" data-toggle="collapse" data-parent="#accordion3" href="#collapseFolder">
        <?php printMLText('filter_for_folders'); ?>
      </a>
    </div>
    <div id="collapseFolder" class="accordion-body <?php if(!$openfilterdlg) echo "collapse";?>" style="_height: 0px;">
      <div class="accordion-inner">
<table class="table-condensed">
<?php
		if($attrdefs) {
			foreach($attrdefs as $attrdef) {
				$attricon = '';
				if($attrdef->getObjType() == LetoDMS_Core_AttributeDefinition::objtype_folder) {
?>
<tr>
	<td><?php echo htmlspecialchars($attrdef->getName()); ?>:</td>
	<td><?php $this->printAttributeEditField($attrdef, isset($attributes[$attrdef->getID()]) ? $attributes[$attrdef->getID()] : '', 'attributes', true) ?></td>
</tr>
<?php
				}
			}
		}
?>
</table>
      </div>
    </div>
  </div>
</div>
</form>
		</div>
<?php
		if($enablefullsearch) {
	  	echo "<div class=\"tab-pane ".(($fullsearch == true) ? 'active' : '')."\" id=\"fulltext\">\n";
	$this->contentContainerStart();
?>
<form action="../out/out.Search.php" name="form2" style="min-height: 330px;">
<input type="hidden" name="fullsearch" value="1" />
<table class="table-condensed">
<tr>
<td><?php printMLText("search_query");?>:</td>
<td>
<input type="text" name="query" value="<?php echo htmlspecialchars($this->query); ?>" />
<!--
<select name="mode">
<option value="1" selected><?php printMLText("search_mode_and");?>
<option value="0"><?php printMLText("search_mode_or");?>
</select>
-->
</td>
</tr>
<tr>
<td><?php printMLText("owner");?>:</td>
<td>
<select class="chzn-select" name="ownerid" data-allow-clear="true" data-placeholder="<?php printMLText('select_users'); ?>" data-no_results_text="<?php printMLText('unknown_owner'); ?>">
<option value="-1"></option>
<?php
			foreach ($allUsers as $userObj) {
				if ($userObj->isGuest() || ($userObj->isHidden() && $userObj->getID() != $user->getID() && !$user->isAdmin()))
					continue;
				print "<option value=\"".$userObj->getID()."\" ".(($owner && $userObj->getID() == $owner->getID()) ? "selected" : "").">" . htmlspecialchars($userObj->getLogin()." - ".$userObj->getFullName()) . "</option>\n";
			}
?>
</select>
</td>
</tr>
<tr>
<td><?php printMLText("category_filter");?>:</td>
<td>
<select class="chzn-select" name="categoryids[]" multiple="multiple" data-placeholder="<?php printMLText('select_category'); ?>" data-no_results_text="<?php printMLText('unknown_document_category'); ?>">
<!--
<option value="-1"><?php printMLText("all_categories");?>
-->
<?php
		$tmpcatids = array();
		foreach($categories as $tmpcat)
			$tmpcatids[] = $tmpcat->getID();
		foreach ($allCats as $catObj) {
			print "<option value=\"".$catObj->getID()."\" ".(in_array($catObj->getID(), $tmpcatids) ? "selected" : "").">" . htmlspecialchars($catObj->getName()) . "\n";
		}
?>
</select>
</td>
</tr>
<tr>
<td></td><td><button type="submit" class="btn"><i class="icon-search"></i> <?php printMLText("search"); ?></button></td>
</tr>
</table>

</form>
<?php
			$this->contentContainerEnd();
			echo "</div>\n";
		}
?>
	</div>
<?php
		echo "</div>\n";
		echo "<div class=\"span8\">\n";
// Search Result {{{
		$foldercount = $doccount = 0;
		if($entries) {
			/*
			foreach ($entries as $entry) {
				if(get_class($entry) == $dms->getClassname('document')) {
					$doccount++;
				} elseif(get_class($entry) == $dms->getClassname('document')) {
					$foldercount++;
				}
			}
			 */
			print "<div class=\"alert\">".getMLText("search_report", array("doccount" => $totaldocs, "foldercount" => $totalfolders, 'searchtime'=>$searchTime))."</div>";
			$this->pageList($pageNumber, $totalpages, "../out/out.Search.php", $urlparams);
//			$this->contentContainerStart();

			$txt = $this->callHook('searchListHeader');
			if(is_string($txt))
				echo $txt;
			else {
				print "<table class=\"table table-hover\">";
				print "<thead>\n<tr>\n";
				print "<th></th>\n";
				print "<th>".getMLText("name")."</th>\n";
				print "<th>".getMLText("attributes")."</th>\n";
				print "<th>".getMLText("status")."</th>\n";
				print "<th>".getMLText("action")."</th>\n";
				print "</tr>\n</thead>\n<tbody>\n";
			}

			$previewer = new LetoDMS_Preview_Previewer($cachedir, $previewwidth, $timeout);
			foreach ($entries as $entry) {
				if(get_class($entry) == $dms->getClassname('document')) {
					$txt = $this->callHook('documentListItem', $entry, $previewer, false, 'search');
					if(is_string($txt))
						echo $txt;
					else {
						$document = $entry;
						$owner = $document->getOwner();
						$lc = $document->getLatestContent();
						$version = $lc->getVersion();
						$previewer->createPreview($lc);

						if (in_array(3, $searchin))
							$comment = $this->markQuery(htmlspecialchars($document->getComment()));
						else
							$comment = htmlspecialchars($document->getComment());
						if (strlen($comment) > 150) $comment = substr($comment, 0, 147) . "...";
						print "<tr id=\"table-row-document-".$document->getID()."\" class=\"table-row-document\" rel=\"document_".$document->getID()."\" formtoken=\"".createFormKey('movedocument')."\" draggable=\"true\">";
						if (in_array(2, $searchin)) {
							$docName = $this->markQuery(htmlspecialchars($document->getName()), "i");
						} else {
							$docName = htmlspecialchars($document->getName());
						}
						print "<td><a class=\"standardText\" href=\"../out/out.ViewDocument.php?documentid=".$document->getID()."\">";
						if($previewer->hasPreview($lc)) {
							print "<img class=\"mimeicon\" width=\"".$previewwidth."\" src=\"../op/op.Preview.php?documentid=".$document->getID()."&version=".$lc->getVersion()."&width=".$previewwidth."\" title=\"".htmlspecialchars($lc->getMimeType())."\">";
						} else {
							print "<img class=\"mimeicon\" width=\"".$previewwidth."\" src=\"".$this->getMimeIcon($lc->getFileType())."\" title=\"".htmlspecialchars($lc->getMimeType())."\">";
						}
						print "</a></td>";
						print "<td><a class=\"standardText\" href=\"../out/out.ViewDocument.php?documentid=".$document->getID()."\">";
						print $docName;
						print "</a>";
						print "<br /><span style=\"font-size: 85%;\">".getMLText('in_folder').": /";
						$folder = $document->getFolder();
						$path = $folder->getPath();
						for ($i = 1; $i  < count($path); $i++) {
							print htmlspecialchars($path[$i]->getName())."/";
						}
						print "</span>";
					print "<br /><span style=\"font-size: 85%; font-style: italic; color: #666; \">".getMLText('owner').": <b>".htmlspecialchars($owner->getFullName())."</b>, ".getMLText('creation_date').": <b>".date('Y-m-d', $document->getDate())."</b>, ".getMLText('version')." <b>".$version."</b> - <b>".date('Y-m-d', $lc->getDate())."</b></span>";
						if($comment) {
							print "<br /><span style=\"font-size: 85%;\">".htmlspecialchars($comment)."</span>";
						}
						print "</td>";

						print "<td>";
						print "<ul class=\"unstyled\">\n";
						$lcattributes = $lc->getAttributes();
						if($lcattributes) {
							foreach($lcattributes as $lcattribute) {
								$attrdef = $lcattribute->getAttributeDefinition();
								print "<li>".htmlspecialchars($attrdef->getName()).": ".htmlspecialchars(implode(', ', $lcattribute->getValueAsArray()))."</li>\n";
							}
						}
						print "</ul>\n";
						print "<ul class=\"unstyled\">\n";
						$docttributes = $document->getAttributes();
						if($docttributes) {
							foreach($docttributes as $docttribute) {
								$attrdef = $docttribute->getAttributeDefinition();
								print "<li>".htmlspecialchars($attrdef->getName()).": ".htmlspecialchars(implode(', ', $docttribute->getValueAsArray()))."</li>\n";
							}
						}
						print "</ul>\n";
						print "</td>";

						$display_status=$lc->getStatus();
						print "<td>".getOverallStatusText($display_status["status"]). "</td>";
						print "<td>";
						print "<div class=\"list-action\">";
						if($document->getAccessMode($user) >= M_ALL) {
							$this->printDeleteDocumentButton($document, 'splash_rm_document');
						} else {
	?>
			 <span style="padding: 2px; color: #CCC;"><i class="icon-remove"></i></span>
	<?php
						}
						if($document->getAccessMode($user) >= M_READWRITE) {
	?>
			 <a href="../out/out.EditDocument.php?documentid=<?php echo $document->getID(); ?>"><i class="icon-edit"></i></a>
	<?php
						} else {
	?>
			 <span style="padding: 2px; color: #CCC;"><i class="icon-edit"></i></span>
	<?php
						}
						if($enableclipboard) {
	?>
			 <a class="addtoclipboard" rel="<?php echo "D".$document->getID(); ?>" msg="<?php printMLText('splash_added_to_clipboard'); ?>" _href="../op/op.AddToClipboard.php?documentid=<?php echo $document->getID(); ?>&type=document&id=<?php echo $document->getID(); ?>&refferer=<?php echo urlencode($this->params['refferer']); ?>" title="<?php printMLText("add_to_clipboard");?>"><i class="icon-copy"></i></a>
<?php
						}
						print "</div>";
						print "</td>";
						print "</tr>\n";
					}
				} elseif(get_class($entry) == $dms->getClassname('folder')) {
					$folder = $entry;
					$owner = $folder->getOwner();
					if (in_array(2, $searchin)) {
						$folderName = $this->markQuery(htmlspecialchars($folder->getName()), "i");
					} else {
						$folderName = htmlspecialchars($folder->getName());
					}
					print "<tr id=\"table-row-folder-".$folder->getID()."\" draggable=\"true\" rel=\"folder_".$folder->getID()."\" class=\"folder table-row-folder\" formtoken=\"".createFormKey('movefolder')."\">";
					print "<td><a class=\"standardText\" href=\"../out/out.ViewFolder.php?folderid=".$folder->getID()."\"><img src=\"".$this->imgpath."folder.png\" width=\"24\" height=\"24\" border=0></a></td>";
					print "<td><a class=\"standardText\" href=\"../out/out.ViewFolder.php?folderid=".$folder->getID()."\">";
					print $folderName;
					print "</a>";
					print "<br /><span style=\"font-size: 85%;\">".getMLText('in_folder').": /";
					$path = $folder->getPath();
					for ($i = 1; $i  < count($path)-1; $i++) {
						print htmlspecialchars($path[$i]->getName())."/";
					}
					print "</span>";
					print "<br /><span style=\"font-size: 85%; font-style: italic; color: #666;\">".getMLText('owner').": <b>".htmlspecialchars($owner->getFullName())."</b>, ".getMLText('creation_date').": <b>".date('Y-m-d', $folder->getDate())."</b></span>";
					if (in_array(3, $searchin)) $comment = $this->markQuery(htmlspecialchars($folder->getComment()));
					else $comment = htmlspecialchars($folder->getComment());
					if (strlen($comment) > 50) $comment = substr($comment, 0, 47) . "...";
					if($comment) {
						print "<br /><span style=\"font-size: 85%;\">".htmlspecialchars($comment)."</span>";
					}
					print "</td>";
					print "<td>";
					print "<ul class=\"unstyled\">\n";
					$folderattributes = $folder->getAttributes();
					if($folderattributes) {
						foreach($folderattributes as $folderattribute) {
							$attrdef = $folderattribute->getAttributeDefinition();
							print "<li>".htmlspecialchars($attrdef->getName()).": ".htmlspecialchars(implode(', ', $folderattribute->getValueAsArray()))."</li>\n";
						}
					}
					print "</td>";
					print "<td></td>";
					print "<td nowrap>";
					print "<div class=\"list-action\">";
					if($folder->getAccessMode($user) >= M_ALL) {
						$this->printDeleteFolderButton($folder, 'splash_rm_folder');
					} else {
?>
     <span style="padding: 2px; color: #CCC;"><i class="icon-remove"></i></span>
<?php
					}
					if($folder->getAccessMode($user) >= M_READWRITE) {
?>
     <a class_="btn btn-mini" href="../out/out.EditFolder.php?folderid=<?php echo $folder->getID(); ?>"><i class="icon-edit"></i></a>
<?php
					} else {
?>
     <span style="padding: 2px; color: #CCC;"><i class="icon-edit"></i></span>
<?php
					}
					if($enableclipboard) {
?>
     <a class="addtoclipboard" rel="<?php echo "F".$folder->getID(); ?>" msg="<?php printMLText('splash_added_to_clipboard'); ?>" _href="../op/op.AddToClipboard.php?folderid=<?php echo $folder->getID(); ?>&type=folder&id=<?php echo $folder->getID(); ?>&refferer=<?php echo urlencode($this->params['refferer']); ?>" title="<?php printMLText("add_to_clipboard");?>"><i class="icon-copy"></i></a>
<?php
					}
					print "</div>";
					print "</td>";
					print "</tr>\n";
				}
			}
			print "</tbody></table>\n";
//			$this->contentContainerEnd();
			$this->pageList($pageNumber, $totalpages, "../out/out.Search.php", $_GET);
		} else {
			$numResults = $totaldocs + $totalfolders;
			if ($numResults == 0) {
				print "<div class=\"alert alert-error\">".getMLText("search_no_results")."</div>";
			}
		}
// }}}
		echo "</div>";
		echo "</div>";
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
