<?php
/**
 * Implementation of EditEvent view
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
 * Class which outputs the html page for EditEvent view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_EditEvent extends LetoDMS_Bootstrap_Style {

	function js() { /* {{{ */
		$strictformcheck = $this->params['strictformcheck'];
		header('Content-Type: application/javascript; charset=UTF-8');
?>
function checkForm()
{
	msg = new Array()
	if (document.form1.name.value == "") msg.push("<?php printMLText("js_no_name");?>");
<?php
	if ($strictformcheck) {
?>
	if (document.form1.comment.value == "") msg.push("<?php printMLText("js_no_comment");?>");
<?php
	}
?>
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
		$event = $this->params['event'];
		$strictformcheck = $this->params['strictformcheck'];

		$this->htmlStartPage(getMLText("calendar"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("calendar"), "calendar");

		$this->contentHeading(getMLText("edit_event"));
		$this->contentContainerStart();
?>

<form class="form-horizontal" action="../op/op.EditEvent.php" id="form1" name="form1" method="POST">
  <?php echo createHiddenFieldWithKey('editevent'); ?>

	<input type="hidden" name="eventid" value="<?php echo (int) $event["id"]; ?>">

		<div class="control-group">
			<label class="control-label"><?php printMLText("from");?>:</label>
			<div class="controls">
				<?php //$this->printDateChooser($event["start"], "from");?>
	    		<span class="input-append date span12" id="fromdate" data-date="<?php echo date('Y-m-d', $event["start"]); ?>" data-date-format="yyyy-mm-dd">
	      		<input class="span6" size="16" name="from" type="text" value="<?php echo date('Y-m-d', $event["start"]); ?>">
	      		<span class="add-on"><i class="icon-calendar"></i>
	    		</span>
	    	</div>
		</div>
		<div class="control-group">
			<label class="control-label"><?php printMLText("to");?>:</label>
			<div class="controls">
				<?php //$this->printDateChooser($event["stop"], "to");?>
	    		<span class="input-append date span12" id="todate" data-date="<?php echo date('Y-m-d', $event["stop"]); ?>" data-date-format="yyyy-mm-dd">
	      		<input class="span6" size="16" name="to" type="text" value="<?php echo date('Y-m-d', $event["stop"]); ?>">
	      		<span class="add-on"><i class="icon-calendar"></i></span>
	    		</span>
			</div>
		</div>
		<div class="control-group">
			<label class="control-label"><?php printMLText("name");?>:</label>
			<div class="controls">
				<input type="text" name="name" value="<?php echo htmlspecialchars($event["name"]);?>" size="60">
			</div>
		</div>
		<div class="control-group">
			<label class="control-label"><?php printMLText("comment");?>:</label>
			<div class="controls">
				<textarea name="comment" rows="4" cols="80"><?php echo htmlspecialchars($event["comment"])?></textarea>
			</div>
		</div>
		<div class="controls">
			<button type="submit" class="btn"><i class="icon-save"></i> <?php printMLText("save")?></button>
		</div>
</form>
<?php
		$this->contentContainerEnd();
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
