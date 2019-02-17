<?php
/**
 * Implementation of AddEvent view
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
 * Class which outputs the html page for AddEvent view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_AddEvent extends LetoDMS_Bootstrap_Style {

	function js() { /* {{{ */
		$strictformcheck = $this->params['strictformcheck'];
		header('Content-Type: application/javascript; charset=UTF-8');
?>
function checkForm()
{
	msg = new Array();
	if (document.form1.name.value == "") msg.push("<?php printMLText("js_no_name");?>");
<?php
	if ($strictformcheck) {
?>
	if (document.form1.comment.value == "") msg.push("<?php printMLText("js_no_comment");?>");
<?php
	}
?>
	if (msg != "") {
  	noty({
  		text: msg.join('<br />'),
  		type: 'error',
      dismissQueue: true,
  		layout: 'topRight',
  		theme: 'defaultTheme',
			_timeout: 1500,
  	});
		return false;
	}
	else
		return true;
}

$(document).ready(function() {
	$('body').on('submit', '#form1', function(ev){
		if(checkForm()) return;
		ev.preventDefault();
	});
});
<?php
	} /* }}} */

	function show() { /* {{{ */

		$this->htmlStartPage(getMLText("calendar"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation("", "calendar");

		$this->contentHeading(getMLText("add_event"));
		$this->contentContainerStart();

		$expdate = date('Y-m-d');
?>

<form class="form-horizontal" action="../op/op.AddEvent.php" id="form1" name="form1" method="post">

<?php
		$this->formField(
			getMLText("from"),
			$this->getDateChooser($expdate, "from", $this->params['session']->getLanguage())
		);
		$this->formField(
			getMLText("to"),
			$this->getDateChooser($expdate, "to", $this->params['session']->getLanguage())
		);
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
				'cols'=>80
			)
		);
		$this->formSubmit(getMLText('add_event'));
?>

</form>

<?php
		$this->contentContainerEnd();
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
