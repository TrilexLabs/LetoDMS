<?php
/**
 * Implementation of ClearCache view
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
 * Class which outputs the html page for ClearCache view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_ClearCache extends LetoDMS_Bootstrap_Style {

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$cachedir = $this->params['cachedir'];

		$this->htmlStartPage(getMLText("admin_tools"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("admin_tools"), "admin_tools");
		$this->contentHeading(getMLText("clear_cache"));
		$this->contentContainerStart('warning');

?>
<form action="../op/op.ClearCache.php" name="form1" method="post">
<?php echo createHiddenFieldWithKey('clearcache'); ?>
<p>
<?php printMLText("confirm_clear_cache", array('cache_dir'=>$cachedir));?>
</p>
<p>
<input type="checkbox" name="preview" value="1" checked> <?php printMLText('preview_images'); ?>
</p>
<p>
<input type="checkbox" name="js" value="1" checked> <?php printMLText('temp_jscode'); ?>
<p><button type="submit" class="btn"><i class="icon-remove"></i> <?php printMLText("clear_cache");?></button></p>
</form>
<?php
		$this->contentContainerEnd();
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
