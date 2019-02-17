<?php
/**
 * Implementation of EditFolder view
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
 * Class which outputs the html page for EditFolder view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_EditFolder extends LetoDMS_Bootstrap_Style {

	function js() { /* {{{ */
		$strictformcheck = $this->params['strictformcheck'];
		header('Content-Type: application/javascript; charset=UTF-8');
?>
$(document).ready(function() {
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
		$attrdefs = $this->params['attrdefs'];
		$rootfolderid = $this->params['rootfolderid'];
		$strictformcheck = $this->params['strictformcheck'];
		$orderby = $this->params['orderby'];

		$this->htmlAddHeader('<script type="text/javascript" src="../styles/'.$this->theme.'/validate/jquery.validate.js"></script>'."\n", 'js');

		$this->htmlStartPage(getMLText("folder_title", array("foldername" => htmlspecialchars($folder->getName()))));
		$this->globalNavigation($folder);
		$this->contentStart();
		$this->pageNavigation($this->getFolderPathHTML($folder, true), "view_folder", $folder);
		$this->contentHeading(getMLText("edit_folder_props"));
		$this->contentContainerStart();
?>
<form class="form-horizontal" action="../op/op.EditFolder.php" id="form1" name="form1" method="post">
		<input type="hidden" name="folderid" value="<?php print $folder->getID();?>">
		<input type="hidden" name="showtree" value="<?php echo showtree();?>">
<?php
		$this->formField(
			getMLText("name"),
			array(
				'element'=>'input',
				'type'=>'text',
				'name'=>'name',
				'value'=>htmlspecialchars($folder->getName()),
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
				'value'=>htmlspecialchars($folder->getComment()),
				'required'=>$strictformcheck
			)
		);
		$parent = ($folder->getID() == $rootfolderid) ? false : $folder->getParent();
		if ($parent && $parent->getAccessMode($user) > M_READ) {
			$this->formField(getMLText("sequence"), $this->getSequenceChooser($parent->getSubFolders('s'), $folder->getID()).($orderby != 's' ? "<br />".getMLText('order_by_sequence_off') : ''));
		}

		if($attrdefs) {
			foreach($attrdefs as $attrdef) {
				$arr = $this->callHook('editFolderAttribute', $folder, $attrdef);
				if(is_array($arr)) {
					if($arr) {
						$this->formField($arr[0], $arr[1]);
					}
				} else {
					$this->formField(htmlspecialchars($attrdef->getName()), $this->getAttributeEditField($attrdef, $folder->getAttribute($attrdef)));
				}
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
