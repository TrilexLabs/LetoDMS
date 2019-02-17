<?php
/**
 * Implementation of EditDocumentFile view
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
 * Class which outputs the html page for EditDocumentFile view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_EditDocumentFile extends LetoDMS_Bootstrap_Style {

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$folder = $this->params['folder'];
		$document = $this->params['document'];
		$file = $this->params['file'];

		$this->htmlStartPage(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))));
		$this->globalNavigation($folder);
		$this->contentStart();
		$this->pageNavigation($this->getFolderPathHTML($folder, true, $document), "view_document", $document);
		$this->contentHeading(getMLText("edit"));
		$this->contentContainerStart();

?>
<form action="../op/op.EditDocumentFile.php" class="form-horizontal" name="form1" method="post">
  <?php echo createHiddenFieldWithKey('editdocumentfile'); ?>
	<input type="hidden" name="documentid" value="<?php echo $document->getID()?>">
	<input type="hidden" name="fileid" value="<?php echo $file->getID()?>">
<?php
		$options = array();
		$options[] = array("", getMLText('document'));
		$versions = $document->getContent();
		foreach($versions as $version)
			$options[] = array($version->getVersion(), getMLText('version')." ".$version->getVersion(), $version->getVersion() == $file->getVersion());
		$this->formField(
			getMLText("version"),
			array(
				'element'=>'select',
				'name'=>'version',
				'id'=>'version',
				'options'=>$options,
			)
		);
		$this->formField(
			getMLText("name"),
			array(
				'element'=>'input',
				'type'=>'text',
				'name'=>'name',
				'value'=>htmlspecialchars($file->getName()),
			)
		);
		$this->formField(
			getMLText("comment"),
			array(
				'element'=>'textarea',
				'name'=>'comment',
				'rows'=>4,
				'cols'=>80,
				'value'=>htmlspecialchars($file->getComment())
			)
		);
		$this->formField(
			getMLText("document_link_public"),
			array(
				'element'=>'input',
				'type'=>'checkbox',
				'name'=>'public',
				'value'=>'true',
				'checked'=>$file->isPublic()
			)
		);
?>
<?php
		$this->formSubmit("<i class=\"icon-save\"></i> ".getMLText('save'));
?>
</form>
<?php
		$this->contentContainerEnd();
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
