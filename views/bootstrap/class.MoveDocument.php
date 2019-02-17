<?php
/**
 * Implementation of MoveDocument view
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
 * Class which outputs the html page for MoveDocument view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_MoveDocument extends LetoDMS_Bootstrap_Style {

	function js() { /* {{{ */
		header('Content-Type: application/javascript');

		$this->printFolderChooserJs("form1");
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$folder = $this->params['folder'];
		$document = $this->params['document'];
		$target = $this->params['target'];

		$this->htmlStartPage(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))));
		$this->globalNavigation($folder);
		$this->contentStart();
		$this->pageNavigation($this->getFolderPathHTML($folder, true, $document), "view_document", $document);
		$this->contentHeading(getMLText("move_document"));
		$this->contentContainerStart('warning');
?>
<form class="form-horizontal" action="../op/op.MoveDocument.php" name="form1">
	<input type="hidden" name="documentid" value="<?php print $document->getID();?>">
<?php
		$this->formField(getMLText("choose_target_folder"), $this->getFolderChooserHtml("form1", M_READWRITE, -1, $target));
		$this->formSubmit(getMLText('move'));
?>
</form>
<?php
		$this->contentContainerEnd();
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
