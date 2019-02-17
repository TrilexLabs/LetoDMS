<?php
/**
 * Implementation of MoveFolder view
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
 * Class which outputs the html page for MoveFolder view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_MoveFolder extends LetoDMS_Bootstrap_Style {

	function js() { /* {{{ */
		header('Content-Type: application/javascript');

		$this->printFolderChooserJs("form1");
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$folder = $this->params['folder'];
		$target = $this->params['target'];

		$this->htmlStartPage(getMLText("folder_title", array("foldername" => htmlspecialchars($folder->getName()))));
		$this->globalNavigation($folder);
		$this->contentStart();
		$this->pageNavigation($this->getFolderPathHTML($folder, true), "view_folder", $folder);
		$this->contentHeading(getMLText("move_folder"));
		$this->contentContainerStart();

?>
<form class="form-horizontal" action="../op/op.MoveFolder.php" name="form1">
	<input type="hidden" name="folderid" value="<?php print $folder->getID();?>">
	<input type="hidden" name="showtree" value="<?php echo showtree();?>">
<?php
		$this->formField(getMLText("choose_target_folder"), $this->getFolderChooserHtml("form1", M_READWRITE, $folder->getID(), $target));
		$this->formSubmit(getMLText('move_folder'));
?>
</form>


<?php
		$this->contentContainerEnd();
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
