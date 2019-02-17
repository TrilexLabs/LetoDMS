<?php
/**
 * Implementation of OverrideContentStatus view
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
 * Class which outputs the html page for OverrideContentStatus view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_OverrideContentStatus extends LetoDMS_Bootstrap_Style {

	function js() { /* {{{ */
		header('Content-Type: application/javascript; charset=UTF-8');
?>
function checkForm()
{
	msg = new Array();
	if (document.form1.overrideStatus.value == "") msg.push("<?php printMLText("js_no_override_status");?>");
	if (document.form1.comment.value == "") msg.push("<?php printMLText("js_no_comment");?>");
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
$(document).ready(function() {
	$('body').on('submit', '#form1', function(ev){
		if(checkForm()) return;
		ev.preventDefault();
	});
});
<?php
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$folder = $this->params['folder'];
		$document = $this->params['document'];
		$content = $this->params['version'];

		$overallStatus = $content->getStatus();
		$reviewStatus = $content->getReviewStatus();
		$approvalStatus = $content->getApprovalStatus();

		$this->htmlStartPage(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))));
		$this->globalNavigation($folder);
		$this->contentStart();
		$this->pageNavigation($this->getFolderPathHTML($folder, true, $document), "view_document", $document);

		$this->contentHeading(getMLText("change_status"));

		$this->contentContainerStart();

// Display the Review form.
?>
<form class="form-horizontal" method="post" action="../op/op.OverrideContentStatus.php" id="form1" name="form1">
	<input type='hidden' name='documentid' value='<?php echo $document->getID() ?>'/>
	<input type='hidden' name='version' value='<?php echo $content->getVersion() ?>'/>
<?php
		$this->formField(
			getMLText("comment"),
			array(
				'element'=>'textarea',
				'name'=>'comment',
				'rows'=>4,
			)
		);
		$options = array();
		$options[] = array('', '');
		if ($overallStatus["status"] == S_OBSOLETE)
			$options[] = array(S_RELEASED, getOverallStatusText(S_RELEASED));
		if ($overallStatus["status"] == S_RELEASED)
			$options[] = array(S_OBSOLETE, getOverallStatusText(S_OBSOLETE));
		$this->formField(
			getMLText("status"),
			array(
				'element'=>'select',
				'name'=>'overrideStatus',
				'options'=>$options,
			)
		);
		$this->formSubmit("<i class=\"icon-save\"></i> ".getMLText('update'));
?>
</form>
<?php
		$this->contentContainerEnd();
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
