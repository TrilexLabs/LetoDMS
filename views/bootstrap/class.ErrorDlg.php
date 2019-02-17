<?php
/**
 * Implementation of ErrorDlg view
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
 * Class which outputs the html page for ErrorDlg view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_ErrorDlg extends LetoDMS_Bootstrap_Style {

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$pagetitle = $this->params['pagetitle'];
		$errormsg = $this->params['errormsg'];
		$plain = $this->params['plain'];

		if(!$plain) {	
			$this->htmlStartPage($pagetitle);
			$this->globalNavigation();
			$this->contentStart();
		}

		print "<div class=\"alert alert-error\">";
		print "<h4>".getMLText('error')."!</h4>";
		print htmlspecialchars($errormsg);
		print "</div>";
		print "<div><button class=\"btn history-back\">".getMLText('back')."</button></div>";
		
		$this->contentEnd();
		$this->htmlEndPage();
		
		add_log_line(" UI::exitError error=".$errormsg." pagetitle=".$pagetitle, PEAR_LOG_ERR);

		return;
	} /* }}} */
}
?>
