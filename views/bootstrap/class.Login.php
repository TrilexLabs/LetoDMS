<?php
/**
 * Implementation of Login view
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
 * Class which outputs the html page for Login view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_Login extends LetoDMS_Bootstrap_Style {

	function js() { /* {{{ */
?>
document.form1.login.focus();
function checkForm()
{
	msg = new Array()
	if($("#login").val() == "") msg.push("<?php printMLText("js_no_login");?>");
	if($("#pwd").val() == "") msg.push("<?php printMLText("js_no_pwd");?>");
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

function guestLogin()
{
	theme = $("#themeselector").val();
	lang = $("#languageselector").val();
	url = "../op/op.Login.php?login=guest";
	if(theme)
		url += "&sesstheme=" + theme;
	if(lang)
		url += "&lang=" + lang;
	if (document.form1.referuri) {
		url += "&referuri=" + escape(document.form1.referuri.value);
	}
	document.location.href = url;
}
$(document).ready( function() {
/*
	$('body').on('submit', '#form', function(ev){
		if(checkForm()) return;
		ev.preventDefault();
	});
*/
	$('body').on('click', '#guestlogin', function(ev){
		ev.preventDefault();
		guestLogin();
	});
	$("#form").validate({
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
			login: "<?php printMLText("js_no_login");?>",
			pwd: "<?php printMLText("js_no_pwd");?>"
		},
	});
});
<?php
	} /* }}} */

	function show() { /* {{{ */
		$enableguestlogin = $this->params['enableguestlogin'];
		$enablepasswordforgotten = $this->params['enablepasswordforgotten'];
		$refer = $this->params['referrer'];
		$themes = $this->params['themes'];
		$msg = $this->params['msg'];
		$languages = $this->params['languages'];
		$enableLanguageSelector = $this->params['enablelanguageselector'];
		$enableThemeSelector = $this->params['enablethemeselector'];

		$this->htmlAddHeader('<script type="text/javascript" src="../styles/'.$this->theme.'/validate/jquery.validate.js"></script>'."\n", 'js');

		$this->htmlStartPage(getMLText("sign_in"), "login");
		$this->globalBanner();
		$this->contentStart();
		$this->pageNavigation(getMLText("sign_in"));
		if($msg)
			$this->errorMsg(htmlspecialchars($msg));
?>
<?php $this->contentContainerStart(); ?>
<form class="form-horizontal" action="../op/op.Login.php" method="post" name="form1" id="form">
<?php
		if ($refer) {
			echo "<input type='hidden' name='referuri' value='".sanitizeString($refer)."'/>";
		}
		$this->formField(
			getMLText("user_login"),
			array(
				'element'=>'input',
				'type'=>'text',
				'id'=>'login',
				'name'=>'login',
				'placeholder'=>'login',
				'autocomplete'=>'off',
				'required'=>true
			)
		);
		$this->formField(
			getMLText("password"),
			array(
				'element'=>'input',
				'type'=>'password',
				'id'=>'pwd',
				'name'=>'pwd',
				'autocomplete'=>'off',
				'required'=>true
			)
		);
		if($enableLanguageSelector) {
			$html = "<select id=\"languageselector\" name=\"lang\">";
			$html .= "<option value=\"\">-";
			foreach ($languages as $currLang) {
				$html .= "<option value=\"".$currLang."\">".getMLText($currLang)."</option>";
			}
			$html .= "</select>";
			$this->formField(
				getMLText("language"),
				$html
			);
		}
		if($enableThemeSelector) {
			$html = "<select id=\"themeselector\" name=\"sesstheme\">";
			$html .= "<option value=\"\">-";
			foreach ($themes as $currTheme) {
				$html .= "<option value=\"".$currTheme."\">".$currTheme;
			}
			$html .= "</select>";
			$this->formField(
				getMLText("theme"),
				$html
			);
		}
		$this->formSubmit(getMLText('submit_login'));
?>
</form>
<?php
		$this->contentContainerEnd();
		$tmpfoot = array();
		if ($enableguestlogin)
			$tmpfoot[] = "<a href=\"\" id=\"guestlogin\">" . getMLText("guest_login") . "</a>\n";
		if ($enablepasswordforgotten)
			$tmpfoot[] = "<a href=\"../out/out.PasswordForgotten.php\">" . getMLText("password_forgotten") . "</a>\n";
		if($tmpfoot) {
			print "<p>";
			print implode(' | ', $tmpfoot);
			print "</p>\n";
		}
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
