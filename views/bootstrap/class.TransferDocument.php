<?php
/**
 * Implementation of TransferDocument view
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
 * Class which outputs the html page for TransferDocument view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2017 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_TransferDocument extends LetoDMS_Bootstrap_Style {

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$allusers = $this->params['allusers'];
		$document = $this->params['document'];
		$folder = $this->params['folder'];

		$this->htmlStartPage(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))));
		$this->globalNavigation($folder);
		$this->contentStart();
		$this->pageNavigation($this->getFolderPathHTML($folder, true, $document), "view_document", $document);
		$this->contentHeading(getMLText("transfer_document"));
		$this->contentContainerStart();
?>
<form class="form-horizontal" action="../op/op.TransferDocument.php" name="form1" method="post">
<input type="hidden" name="documentid" value="<?php print $document->getID();?>">
<?php echo createHiddenFieldWithKey('transferdocument'); ?>
<?php
		$html = '<select name="userid" class="chzn-select">';
		$owner = $document->getOwner();
		foreach ($allusers as $currUser) {
			if ($currUser->isGuest() || ($currUser->getID() == $owner->getID()))
				continue;

			$html .= "<option value=\"".$currUser->getID()."\"";
			if($folder->getAccessMode($currUser) < M_READ)
				$html .= " disabled data-warning=\"".getMLText('transfer_no_read_access')."\"";
			elseif($folder->getAccessMode($currUser) < M_READWRITE)
				$html .= " data-warning=\"".getMLText('transfer_no_write_access')."\"";
			$html .= ">" . htmlspecialchars($currUser->getLogin()." - ".$currUser->getFullName());
		}
		$html .= '</select>';
		$this->formField(
			getMLText("transfer_to_user"),
			$html
		);
		$this->formSubmit("<i class=\"icon-exchange\"></i> ".getMLText('transfer_document'));
?>
</form>
<?php
		$this->contentContainerEnd();
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
