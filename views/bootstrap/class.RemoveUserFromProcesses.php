<?php
/**
 * Implementation of RemoveUserFromProcesses view
 *
 * @category   DMS
 * @package    LetoDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2017 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Include parent class
 */
require_once("class.Bootstrap.php");

/**
 * Class which outputs the html page for RemoveUserFromProcesses view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2017 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_RemoveUserFromProcesses extends LetoDMS_Bootstrap_Style {

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$rmuser = $this->params['rmuser'];

		$this->htmlStartPage(getMLText("admin_tools"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("admin_tools"), "admin_tools");
		$this->contentHeading(getMLText("rm_user_from_processes"));

?>
<div class="alert">
<?php printMLText("confirm_rm_user_from_processes", array ("username" => htmlspecialchars($rmuser->getFullName())));?>
</div>
<?php
		$this->contentContainerStart();
?>
<form class="form-horizontal" action="../op/op.UsrMgr.php" name="form1" method="post">
<input type="hidden" name="userid" value="<?php print $rmuser->getID();?>">
<input type="hidden" name="action" value="removefromprocesses">
<?php echo createHiddenFieldWithKey('removefromprocesses'); ?>

<?php
		$reviewStatus = $rmuser->getReviewStatus();
		$tmpr = array();
		foreach($reviewStatus['indstatus'] as $ri) {
			if(isset($tmpr[$ri['status']]))
				$tmpr[$ri['status']][] = $ri;
			else
				$tmpr[$ri['status']] = array($ri);
		}

		$approvalStatus = $rmuser->getApprovalStatus();
		$tmpa = array();
		foreach($approvalStatus['indstatus'] as $ai) {
			if(isset($tmpa[$ai['status']]))
				$tmpa[$ai['status']][] = $ai;
			else
				$tmpa[$ai['status']] = array($ai);
		}
?>
<?php if(isset($tmpr["0"])) { ?>
<div class="control-group">
	<div class="controls">
		<label class="checkbox">
	<input type="checkbox" name="status[review][]" value="0" checked> <?php echo getMLText('reviews_not_touched', array('no_reviews' => count($tmpr["0"]))); ?>
		</label>
	</div>
</div>
<?php } ?>
<?php if(isset($tmpr["1"])) { ?>
<div class="control-group">
	<div class="controls">
		<label class="checkbox">
	<input type="checkbox" name="status[review][]" value="1"> <?php echo getMLText('reviews_accepted', array('no_reviews' => count($tmpr["1"]))); ?><br />
		</label>
	</div>
</div>
<?php } ?>
<?php if(isset($tmpr["-1"])) { ?>
<div class="control-group">
	<div class="controls">
		<label class="checkbox">
	<input type="checkbox" name="status[review][]" value="-1"> <?php echo getMLText('reviews_rejected', array('no_reviews' => count($tmpr["-1"]))); ?><br />
		</label>
	</div>
</div>
<?php } ?>

<?php if(isset($tmpa["0"])) { ?>
<div class="control-group">
	<div class="controls">
		<label class="checkbox">
	<input type="checkbox" name="status[approval][]" value="0" checked> <?php echo getMLText('approvals_not_touched', array('no_approvals' => count($tmpa["0"]))); ?>
		</label>
	</div>
</div>
<?php } ?>
<?php if(isset($tmpa["1"])) { ?>
<div class="control-group">
	<div class="controls">
		<label class="checkbox">
	<input type="checkbox" name="status[approval][]" value="1"> <?php echo getMLText('approvals_accepted', array('no_approvals' => count($tmpa["1"]))); ?><br />
		</label>
	</div>
</div>
<?php } ?>
<?php if(isset($tmpa["-1"])) { ?>
<div class="control-group">
	<div class="controls">
		<label class="checkbox">
	<input type="checkbox" name="status[approval][]" value="-1"> <?php echo getMLText('approvals_rejected', array('no_approvals' => count($tmpa["-1"]))); ?><br />
		</label>
	</div>
</div>
<?php } ?>

<div class="control-group">
	<div class="controls">
<button type="submit" class="btn"><i class="icon-remove"></i> <?php printMLText("rm_user_from_processes");?></button>
	</div>
</div>

</form>
<?php
		$this->contentContainerEnd();
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
