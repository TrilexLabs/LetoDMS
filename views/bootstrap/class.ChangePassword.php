<?php
/**
 * Implementation of ChangePassword view
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
 * Class which outputs the html page for ChangePassword view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_ChangePassword extends LetoDMS_Bootstrap_Style {

	function js() { /* {{{ */
		header('Content-Type: application/javascript; charset=UTF-8');
?>
document.form1.newpassword.focus();
<?php
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$referuri = $this->params['referuri'];
		$hash = $this->params['hash'];
		$passwordstrength = $this->params['passwordstrength'];

		$this->htmlStartPage(getMLText("change_password"), "login");
		$this->globalBanner();
		$this->contentStart();
		$this->pageNavigation(getMLText("change_password"));
		$this->contentContainerStart();
?>
<form class="form-horizontal" action="../op/op.ChangePassword.php" method="post" name="form1">
<?php
		if ($referuri) {
			echo "<input type='hidden' name='referuri' value='".$referuri."'/>";
		}
		if ($hash) {
			echo "<input type='hidden' name='hash' value='".$hash."'/>";
		}
		$this->formField(
			getMLText("password"),
			'<input class="pwd" type="password" rel="strengthbar" name="newpassword" id="password">'
		);
		if($passwordstrength > 0) {
?>
		<div class="control-group">
			<label class="control-label"><?php printMLText("password_strength");?>:</label>
			<div class="controls">
				<div id="strengthbar" class="progress" style="width: 220px; height: 30px; margin-bottom: 8px;"><div class="bar bar-danger" style="width: 0%;"></div></div>
			</div>
		</div>
<?php
		}
		$this->formField(
			getMLText("confirm_pwd"),
			array(
				'element'=>'input',
				'type'=>'password',
				'id'=>'passwordrepeat',
				'name'=>'newpasswordrepeat',
				'autocomplete'=>'off',
			)
		);
		$this->formSubmit(getMLText('submit_password'));
?>

</form>
<?php $this->contentContainerEnd(); ?>
<p><a href="../out/out.Login.php"><?php echo getMLText("login"); ?></a></p>
<?php
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
