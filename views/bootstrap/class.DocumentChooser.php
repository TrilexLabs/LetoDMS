<?php
/**
 * Implementation of DocumentChooser view
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
 * Class which outputs the html page for DocumentChooser view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_DocumentChooser extends LetoDMS_Bootstrap_Style {

	function js() { /* {{{ */
		$folder = $this->params['folder'];
		$form = $this->params['form'];

		header('Content-Type: application/javascript');
		$this->printNewTreeNavigationJs($folder->getID(), M_READ, 1, $form);
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$folder = $this->params['folder'];
		$form = $this->params['form'];

//		$this->htmlStartPage(getMLText("choose_target_document"));
//		$this->contentContainerStart();
//		$this->printNewTreeNavigationHtml($folder->getID(), M_READ, 1, $form);
		$this->printNewTreeNavigationHtml($folder->getID(), M_READ, 1, $form);
		echo '<script src="../out/out.DocumentChooser.php?action=js&'.$_SERVER['QUERY_STRING'].'"></script>'."\n";
//		$this->contentContainerEnd();
//		$this->htmlEndPage(true);
	} /* }}} */
}
?>
