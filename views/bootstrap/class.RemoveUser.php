<?php
/**
 * Implementation of RemoveUser view
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
 * Class which outputs the html page for RemoveUser view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_RemoveUser extends LetoDMS_Bootstrap_Style {

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$rmuser = $this->params['rmuser'];
		$allusers = $this->params['allusers'];

		$this->htmlStartPage(getMLText("admin_tools"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("admin_tools"), "admin_tools");
		$this->contentHeading(getMLText("rm_user"));

?>
<div class="alert">
<?php printMLText("confirm_rm_user", array ("username" => htmlspecialchars($rmuser->getFullName())));?>
</div>
<?php
		$this->contentContainerStart();
?>
<form class="form-horizontal" action="../op/op.UsrMgr.php" name="form1" method="post">
<input type="hidden" name="userid" value="<?php print $rmuser->getID();?>">
<input type="hidden" name="action" value="removeuser">
<?php echo createHiddenFieldWithKey('removeuser'); ?>

<div class="control-group">
	<label class="control-label" for="assignTo">
<?php printMLText("assign_user_property_to"); ?>:
	</label>
	<div class="controls">
<select name="assignTo" class="chzn-select">
<?php
		foreach ($allusers as $currUser) {
			if ($currUser->isGuest() || ($currUser->getID() == $rmuser->getID()) )
				continue;

			if ($rmuser && $currUser->getID()==$rmuser->getID()) $selected=$count;
			print "<option value=\"".$currUser->getID()."\">" . htmlspecialchars($currUser->getLogin()." - ".$currUser->getFullName());
		}
?>
</select>
	</div>
</div>

<div class="control-group">
	<div class="controls">
		<button type="submit" class="btn"><i class="icon-remove"></i> <?php printMLText("rm_user");?></button>
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
