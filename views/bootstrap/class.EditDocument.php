<?php
/**
 * Implementation of EditDocument view
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
 * Class which outputs the html page for EditDocument view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_EditDocument extends LetoDMS_Bootstrap_Style {

	function js() { /* {{{ */
		$strictformcheck = $this->params['strictformcheck'];
		header('Content-Type: application/javascript');
		$this->printKeywordChooserJs('form1');
?>
$(document).ready( function() {
	$("#form1").validate({
		invalidHandler: function(e, validator) {
			noty({
				text:  (validator.numberOfInvalids() == 1) ? "<?php printMLText("js_form_error");?>".replace('#', validator.numberOfInvalids()) : "<?php printMLText("js_form_errors");?>".replace('#', validator.numberOfInvalids()),
				type: 'error',
				dismissQueue: true,
				layout: 'topRight',
				theme: 'defaultTheme',
				timeout: 1500,
			});
		},
		messages: {
			name: "<?php printMLText("js_no_name");?>",
			comment: "<?php printMLText("js_no_comment");?>",
			keywords: "<?php printMLText("js_no_keywords");?>"
		}
	});
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
		$attrdefs = $this->params['attrdefs'];
		$strictformcheck = $this->params['strictformcheck'];
		$orderby = $this->params['orderby'];

		$this->htmlAddHeader('<script type="text/javascript" src="../styles/'.$this->theme.'/validate/jquery.validate.js"></script>'."\n", 'js');

		$this->htmlStartPage(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))));
		$this->globalNavigation($folder);
		$this->contentStart();
		$this->pageNavigation($this->getFolderPathHTML($folder, true, $document), "view_document", $document);

		$this->contentHeading(getMLText("edit_document_props"));
		$this->contentContainerStart();

		if($document->expires())
			$expdate = date('Y-m-d', $document->getExpires());
		else
			$expdate = '';
?>
<form class="form-horizontal" action="../op/op.EditDocument.php" name="form1" id="form1" method="post">
	<input type="hidden" name="documentid" value="<?php echo $document->getID() ?>">
<?php
		$this->formField(
			getMLText("name"),
			array(
				'element'=>'input',
				'type'=>'text',
				'name'=>'name',
				'value'=>htmlspecialchars($document->getName()),
				'required'=>true
			)
		);
		$this->formField(
			getMLText("comment"),
			array(
				'element'=>'textarea',
				'name'=>'comment',
				'rows'=>4,
				'cols'=>80,
				'value'=>htmlspecialchars($document->getComment()),
				'required'=>$strictformcheck
			)
		);
		$this->formField(
			getMLText("keywords"),
			$this->getKeywordChooserHtml('form1', $document->getKeywords())
		);
		$options = array();
		$categories = $dms->getDocumentCategories();
		foreach($categories as $category) {
			$options[] = array($category->getID(), $category->getName(), in_array($category, $document->getCategories()));
		}
		$this->formField(
			getMLText("categories"),
			array(
				'element'=>'select',
				'class'=>'chzn-select',
				'name'=>'categories[]',
				'multiple'=>true,
				'attributes'=>array(array('data-placeholder', getMLText('select_category'), array('data-no_results_text', getMLText('unknown_document_category')))),
				'options'=>$options
			)
		);
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
		if ($folder->getAccessMode($user) > M_READ) {
			$this->formField(getMLText("sequence"), $this->getSequenceChooser($folder->getDocuments('s'), $document->getID()).($orderby != 's' ? "<br />".getMLText('order_by_sequence_off') : ''));
		}
		if($attrdefs) {
			foreach($attrdefs as $attrdef) {
				$arr = $this->callHook('editDocumentAttribute', $document, $attrdef);
				if(is_array($arr)) {
					if($arr) {
						$this->formField($arr[0], $arr[1]);
					}
				} else {
					$this->formField(htmlspecialchars($attrdef->getName()), $this->getAttributeEditField($attrdef, $document->getAttribute($attrdef)));
				}
			}
		}
		$arrs = $this->callHook('addDocumentAttributes', $folder);
		if(is_array($arrs)) {
			foreach($arrs as $arr) {
				$this->formField($arr[0], $arr[1]);
			}
		}
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
