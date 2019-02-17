<?php
/**
 * Implementation of Hooks view
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
 * Class which outputs the html page for Hooks view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2016 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_Hooks extends LetoDMS_Bootstrap_Style {

	/**
	 * List all registered hooks
	 *
	 */
	function list_hooks() { /* {{{ */
		if(!isset($GLOBALS['LetoDMS_HOOKS']))
			return;

		echo "<table class=\"table\">\n";
		echo "<thead>";
		echo "<tr><th>Type</th><th>Name of hook</th><th>Name of class</th><th>File</th></tr>\n";
		echo "</thead>";
		echo "<tbody>";
		foreach(array('controller', 'view') as $type) {
			if(isset($GLOBALS['LetoDMS_HOOKS'][$type])) {
				foreach($GLOBALS['LetoDMS_HOOKS'][$type] as $name=>$objects) {
					$first = true;
					foreach($objects as $object) {
						$reflector = new ReflectionClass(get_class($object));
						$methods = $reflector->getMethods();
						array_walk($methods, function (&$v) { $v = $v->getName()."();"; });
						if($first)
							echo "<tr><td>".$type."</td><td>".$name."</td><td>".get_class($object)."<p>Methods: ".implode(" ", $methods)."</p></td><td>".$reflector->getFilename()."</td></tr>";
						else
							echo "<tr><td colspan=\"2\"></td><td>".get_class($object)."<p>Methods: ".implode("; ", $methods)."</p></td><td>".$reflector->getFilename()."</td></tr>";
						$first = false;
					}
				}
			}
		}
		echo "</tbody>";
		echo "</table>\n";
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];

		$this->htmlStartPage(getMLText("admin_tools"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("admin_tools"), "admin_tools");
		$this->contentHeading("Hooks");

		self::list_hooks();

		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}

