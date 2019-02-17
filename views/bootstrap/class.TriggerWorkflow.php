<?php
/**
 * Implementation of TriggerWorkflow view
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
 * Class which outputs the html page for TriggerWorkflow view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_TriggerWorkflow extends LetoDMS_Bootstrap_Style {

	function js() { /* {{{ */
		header('Content-Type: application/javascript; charset=UTF-8');
?>
function checkForm()
{
	msg = new Array();
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
		$transition = $this->params['transition'];

		$latestContent = $document->getLatestContent();

		$this->htmlStartPage(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))));
		$this->globalNavigation($folder);
		$this->contentStart();
		$this->pageNavigation($this->getFolderPathHTML($folder, true, $document), "view_document", $document);
		$this->contentHeading(getMLText("trigger_workflow"));
?>
<script language="JavaScript">
</script>

<?php

		$action = $transition->getAction();
		$currentstate = $latestContent->getWorkflowState();
		$wkflog = $latestContent->getWorkflowLog();
		$workflow = $latestContent->getWorkflow();

		$msg = "The document is currently in state: ".$currentstate->getName()."<br />";
		if($wkflog) {
			foreach($wkflog as $entry) {
				if($entry->getTransition()->getNextState()->getID() == $currentstate->getID()) {
					$enterdate = $entry->getDate();
					$enterts = makeTsFromLongDate($enterdate);
				}
			}
			$msg .= "The state was entered at ".$enterdate." which was ";
			$msg .= getReadableDuration((time()-$enterts))." ago.<br />";
		}
		$msg .= "The document may stay in this state for ".$currentstate->getMaxTime()." sec.";
		$this->infoMsg($msg);

		$this->contentContainerStart();
		// Display the Workflow form.
?>
	<div class="row-fluid">
	<div class="span4">
	<form class="form-horizontal" method="post" action="../op/op.TriggerWorkflow.php" id="form1" name="form1">
	<input type='hidden' name='documentid' value='<?php echo $document->getId(); ?>'/>
	<input type='hidden' name='version' value='<?php echo $latestContent->getVersion(); ?>'/>
	<input type='hidden' name='transition' value='<?php echo $transition->getID(); ?>'/>
	<?php echo createHiddenFieldWithKey('triggerworkflow'); ?>
<?php
		$this->formField(
			getMLText("comment"),
			array(
				'element'=>'textarea',
				'name'=>'comment',
				'rows'=>4
			)
		);
		$this->formSubmit(getMLText("action_".strtolower($action->getName()), array(), $action->getName()));
?>
	</form>
	</div>
	<div id="workflowgraph" class="span8">
	<iframe src="out.WorkflowGraph.php?workflow=<?php echo $workflow->getID(); ?>&transition=<?php echo $transition->getID(); ?>&documentid=<?php echo $document->getID(); ?>" width="100%" height="500" style="border: 1px solid #AAA;"></iframe>
	</div>
	</div>
<?php
		$this->contentContainerEnd();

		if($wkflog) {
			$this->contentContainerStart();
			echo "<table class=\"table-condensed\">";
			echo "<tr><th>".getMLText('action')."</th><th>Start state</th><th>End state</th><th>".getMLText('date')."</th><th>".getMLText('user')."</th><th>".getMLText('comment')."</th></tr>";
			foreach($wkflog as $entry) {
				echo "<tr>";
				echo "<td>".getMLText('action_'.strtolower($entry->getTransition()->getAction()->getName()), array(), $entry->getTransition()->getAction()->getName())."</td>";
				echo "<td>".$entry->getTransition()->getState()->getName()."</td>";
				echo "<td>".$entry->getTransition()->getNextState()->getName()."</td>";
				echo "<td>".$entry->getDate()."</td>";
				echo "<td>".$entry->getUser()->getFullname()."</td>";
				echo "<td>".$entry->getComment()."</td>";
				echo "</tr>";
			}
			echo "</table>\n";
			$this->contentContainerEnd();
		}

		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
