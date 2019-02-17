<?php
/**
 * Implementation of SetExpires view
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
 * Class which outputs the html page for SetExpires view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_SetExpires extends LetoDMS_Bootstrap_Style {

	function js() { /* {{{ */
		header('Content-Type: application/javascript');
?>
$(document).ready( function() {
	$('#presetexpdate').on('change', function(ev){
		if($(this).val() == 'date')
			$('#control_expdate').show();
		else
			$('#control_expdate').hide();
	});
});
<?php
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$folder = $this->params['folder'];
		$document = $this->params['document'];

		$this->htmlStartPage(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))));
		$this->globalNavigation($folder);
		$this->contentStart();
		$this->pageNavigation($this->getFolderPathHTML($folder, true, $document), "view_document", $document);
		$this->contentHeading(getMLText("set_expiry"));
		$this->contentContainerStart();

		if($document->expires())
			$expdate = date('Y-m-d', $document->getExpires());
		else
			$expdate = '';
?>

<form class="form-horizontal" action="../op/op.SetExpires.php" method="post">
<input type="hidden" name="documentid" value="<?php print $document->getID();?>">
<?php
		$options = array();
		$options[] = array('never', getMLText('does_not_expire'));
		$options[] = array('date', getMLText('expire_by_date'), $expdate != '');
		$options[] = array('1w', getMLText('expire_in_1w'));
		$options[] = array('1m', getMLText('expire_in_1m'));
		$options[] = array('1y', getMLText('expire_in_1y'));
		$options[] = array('2y', getMLText('expire_in_2y'));
		$this->formField(
			getMLText("preset_expires"),
			array(
				'element'=>'select',
				'id'=>'presetexpdate',
				'name'=>'presetexpdate',
				'options'=>$options
			)
		);
		$this->formField(
			getMLText("expires"),
			$this->getDateChooser($expdate, "expdate", $this->params['session']->getLanguage())
		);
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
