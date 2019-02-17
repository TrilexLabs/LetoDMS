<?php
/**
 * Implementation of ReviewDocument view
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
 * Class which outputs the html page for ReviewDocument view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_ReviewDocument extends LetoDMS_Bootstrap_Style {

	function js() { /* {{{ */
		header('Content-Type: application/javascript; charset=UTF-8');
?>
function checkIndForm()
{
	msg = new Array();
	if (document.formind.reviewStatus.value == "") msg.push("<?php printMLText("js_no_review_status");?>");
	if (document.formind.comment.value == "") msg.push("<?php printMLText("js_no_comment");?>");
	if (msg != "") {
  	noty({
  		text: msg.join('<br />'),
  		type: 'error',
      dismissQueue: true,
  		layout: 'topRight',
  		theme: 'defaultTheme',
			_timeout: 1500,
  	});
		return false;
	}
	else
		return true;
}
function checkGrpForm()
{
	msg = new Array();
//	if (document.formgrp.reviewGroup.value == "") msg.push("<?php printMLText("js_no_review_group");?>");
	if (document.formgrp.reviewStatus.value == "") msg.push("<?php printMLText("js_no_review_status");?>");
	if (document.formgrp.comment.value == "") msg.push("<?php printMLText("js_no_comment");?>");
	if (msg != "")
	{
  	noty({
  		text: msg.join('<br />'),
  		type: 'error',
      dismissQueue: true,
  		layout: 'topRight',
  		theme: 'defaultTheme',
			_timeout: 1500,
  	});
		return false;
	}
	else
		return true;
}
$(document).ready(function() {
	$('body').on('submit', '#formind', function(ev){
		if(checkIndForm()) return;
		ev.preventDefault();
	});
	$('body').on('submit', '#formgrp', function(ev){
		if(checkGrpForm()) return;
		ev.preventDefault();
	});
});
<?php

		$this->printFileChooserJs();
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$folder = $this->params['folder'];
		$document = $this->params['document'];
		$content = $this->params['version'];

		$reviews = $content->getReviewStatus();
		foreach($reviews as $review) {
			if($review['reviewID'] == $_GET['reviewid']) {
				$reviewStatus = $review;
				break;
			}
		}

		$this->htmlStartPage(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))));
		$this->globalNavigation($folder);
		$this->contentStart();
		$this->pageNavigation($this->getFolderPathHTML($folder, true, $document), "view_document", $document);
		$this->contentHeading(getMLText("submit_review"));
		$this->contentContainerStart();

		// Display the Review form.
		$reviewtype = ($reviewStatus['type'] == 0) ? 'ind' : 'grp';
		if($reviewStatus["status"]!=0) {

			print "<table class=\"folderView\"><thead><tr>";
			print "<th>".getMLText("status")."</th>";
			print "<th>".getMLText("comment")."</th>";
			print "<th>".getMLText("last_update")."</th>";
			print "</tr></thead><tbody><tr>";
			print "<td>";
			printReviewStatusText($reviewStatus["status"]);
			print "</td>";
			print "<td>".htmlspecialchars($reviewStatus["comment"])."</td>";
			$indUser = $dms->getUser($reviewStatus["userID"]);
			print "<td>".$reviewStatus["date"]." - ". htmlspecialchars($indUser->getFullname()) ."</td>";
			print "</tr></tbody></table><br>\n";
		}
?>
	<form class="form-horizontal" method="post" action="../op/op.ReviewDocument.php" id="form<?= $reviewtype ?>" name="form<?= $reviewtype ?>" enctype="multipart/form-data">
	<?php echo createHiddenFieldWithKey('reviewdocument'); ?>
<?php
		$this->formField(
			getMLText("comment"),
			array(
				'element'=>'textarea',
				'name'=>'comment',
				'rows'=>4,
				'cols'=>80
			)
		);
		$this->formField(
			getMLText("review_file"),
			$this->getFileChooserHtml('review_file', false)
		);
		$options = array();
		if($reviewStatus['status'] != 1)
			$options[] = array('1', getMLText('status_reviewed'));
		if($reviewStatus['status'] != -1)
			$options[] = array('-1', getMLText('rejected'));
		$this->formField(
			getMLText("review_status"),
			array(
				'element'=>'select',
				'name'=>'reviewStatus',
				'options'=>$options
			)
		);
		$this->formSubmit(getMLText('submit_review'), $reviewtype.'Review');
?>
	<input type='hidden' name='reviewType' value='<?= $reviewtype ?>'/>
	<?php if($reviewtype == 'grp'): ?>
	<input type='hidden' name='reviewGroup' value='<?php echo $reviewStatus['required']; ?>'/>
	<?php endif; ?>
	<input type='hidden' name='documentid' value='<?php echo $document->getID() ?>'/>
	<input type='hidden' name='version' value='<?php echo $content->getVersion() ?>'/>
	</form>
<?php

		$this->contentContainerEnd();
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
