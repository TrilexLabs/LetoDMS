<?php
/**
 * Implementation of RemoveArchive view
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
 * Class which outputs the html page for RemoveArchive view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_RemoveArchive extends LetoDMS_Bootstrap_Style {

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$arkname = $this->params['archive'];

		$this->htmlStartPage(getMLText("backup_tools"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("admin_tools"), "admin_tools");
		$this->contentHeading(getMLText("backup_remove"));
		$this->contentContainerStart();

?>
<form action="../op/op.RemoveArchive.php" name="form1" method="post">
	<input type="hidden" name="arkname" value="<?php echo htmlspecialchars($arkname); ?>">
  <?php echo createHiddenFieldWithKey('removearchive'); ?>
	<p><?php printMLText("confirm_rm_backup", array ("arkname" => htmlspecialchars($arkname)));?></p>
	<p><button type="submit" class="btn"><i class="icon-remove"></i> <?php printMLText("backup_remove");?></button></p>
</form>
<?php
		$this->contentContainerEnd();
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
