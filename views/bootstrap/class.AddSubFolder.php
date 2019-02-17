<?php
/**
 * Implementation of AddSubFolder view
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
 * Class which outputs the html page for AddSubFolder view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_AddSubFolder extends LetoDMS_Bootstrap_Style {

	function js() { /* {{{ */
		$strictformcheck = $this->params['strictformcheck'];
		header('Content-Type: application/javascript');
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
		highlight: function(e, errorClass, validClass) {
			$(e).parent().parent().removeClass(validClass).addClass(errorClass);
		},
		unhighlight: function(e, errorClass, validClass) {
			$(e).parent().parent().removeClass(errorClass).addClass(validClass);
		},
		messages: {
			name: "<?php printMLText("js_no_name");?>",
			comment: "<?php printMLText("js_no_comment");?>"
		},
	});
});
<?php
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$folder = $this->params['folder'];
		$strictformcheck = $this->params['strictformcheck'];
		$orderby = $this->params['orderby'];

		$this->htmlAddHeader('<script type="text/javascript" src="../styles/'.$this->theme.'/validate/jquery.validate.js"></script>'."\n", 'js');

		$this->htmlStartPage(getMLText("folder_title", array("foldername" => htmlspecialchars($folder->getName()))));
		$this->globalNavigation($folder);
		$this->contentStart();
		$this->pageNavigation($this->getFolderPathHTML($folder, true), "view_folder", $folder);
		$this->contentHeading(getMLText("add_subfolder"));
		$this->contentContainerStart();
?>

<form class="form-horizontal" action="../op/op.AddSubFolder.php" id="form1" name="form1" method="post">
	<?php echo createHiddenFieldWithKey('addsubfolder'); ?>
	<input type="hidden" name="folderid" value="<?php print $folder->getId();?>">
	<input type="hidden" name="showtree" value="<?php echo showtree();?>">
<?php	
		$this->formField(
			getMLText("name"),
			array(
				'element'=>'input',
				'type'=>'text',
				'id'=>'name',
				'name'=>'name',
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
				'required'=>$strictformcheck
			)
		);
		$this->formField(getMLText("sequence"), $this->getSequenceChooser($folder->getSubFolders('s')).($orderby != 's' ? "<br />".getMLText('order_by_sequence_off') : ''));

		$attrdefs = $dms->getAllAttributeDefinitions(array(LetoDMS_Core_AttributeDefinition::objtype_folder, LetoDMS_Core_AttributeDefinition::objtype_all));
		if($attrdefs) {
			foreach($attrdefs as $attrdef) {
				$arr = $this->callHook('addFolderAttribute', null, $attrdef);
				if(is_array($arr)) {
					if($arr) {
						$this->formField($arr[0], $arr[1]);
					}
				} else {
					$this->formField(htmlspecialchars($attrdef->getName()), $this->getAttributeEditField($attrdef, ''));
				}
			}
		}
		$this->formSubmit("<i class=\"icon-save\"></i> ".getMLText('add_subfolder'));
?>
</form>
<?php
		$this->contentContainerEnd();
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
