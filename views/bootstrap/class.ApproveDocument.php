<?php
/**
 * Implementation of ApproveDocument view
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
 * Class which outputs the html page for ApproveDocument view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_ApproveDocument extends LetoDMS_Bootstrap_Style {

	function js() { /* {{{ */
		header('Content-Type: application/javascript; charset=UTF-8');
?>
function checkIndForm()
{
	msg = new Array();
	if (document.formind.approvalStatus.value == "") msg.push("<?php printMLText("js_no_approval_status");?>");
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
//	if (document.formgrp.approvalGroup.value == "") msg.push("<?php printMLText("js_no_approval_group");?>");
	if (document.formgrp.approvalStatus.value == "") msg.push("<?php printMLText("js_no_approval_status");?>");
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

		$latestContent = $document->getLatestContent();
		$approvals = $latestContent->getApprovalStatus();

		foreach($approvals as $approval) {
			if($approval['approveID'] == $_GET['approveid']) {
				$approvalStatus = $approval;
				break;
			}
		}

		$this->htmlStartPage(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))));
		$this->globalNavigation($folder);
		$this->contentStart();
		$this->pageNavigation($this->getFolderPathHTML($folder, true, $document), "view_document", $document);
		$this->contentHeading(getMLText("add_approval"));

		$this->contentContainerStart();

		// Display the Approval form.
		$approvaltype = ($approvalStatus['type'] == 0) ? 'ind' : 'grp';
		if($approvalStatus["status"]!=0) {

			print "<table class=\"folderView\"><thead><tr>";
			print "<th>".getMLText("status")."</th>";
			print "<th>".getMLText("comment")."</th>";
			print "<th>".getMLText("last_update")."</th>";
			print "</tr></thead><tbody><tr>";
			print "<td>";
			printApprovalStatusText($approvalStatus["status"]);
			print "</td>";
			print "<td>".htmlspecialchars($approvalStatus["comment"])."</td>";
			$indUser = $dms->getUser($approvalStatus["userID"]);
			print "<td>".$approvalStatus["date"]." - ". htmlspecialchars($indUser->getFullname()) ."</td>";
			print "</tr></tbody></table><br>\n";
		}
?>
	<form class="form-horizontal" method="post" action="../op/op.ApproveDocument.php" id="form<?= $approvaltype ?>" name="form<?= $approvaltype ?>" enctype="multipart/form-data">
	<?php echo createHiddenFieldWithKey('approvedocument'); ?>
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
			getMLText("approval_file"),
			$this->getFileChooserHtml('approvalfile', false)
		);
		$options = array();
		if($approvalStatus['status'] != 1)
			$options[] = array('1', getMLText("status_approved"));
		if($approvalStatus['status'] != -1)
			$options[] = array('-1', getMLText("rejected"));
		$this->formField(
			getMLText("approval_status"),
			array(
				'element'=>'select',
				'name'=>'approvalStatus',
				'options'=>$options,
			)
		);
		$this->formSubmit(getMLText('submit_approval'), $approvaltype.'Approval');
?>
	<input type='hidden' name='approvalType' value='<?= $approvaltype ?>'/>
	<?php if($approvaltype == 'grp'): ?>
	<input type='hidden' name='approvalGroup' value="<?php echo $approvalStatus['required']; ?>" />
	<?php endif; ?>
	<input type='hidden' name='documentid' value='<?php echo $document->getId() ?>'/>
	<input type='hidden' name='version' value='<?php echo $latestContent->getVersion(); ?>'/>
	</form>
<?php

		$this->contentContainerEnd();
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
