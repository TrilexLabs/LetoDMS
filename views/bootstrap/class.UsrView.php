<?php
/**
 * Implementation of UsrView view
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
 * Class which outputs the html page for UsrView view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_UsrView extends LetoDMS_Bootstrap_Style {

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$users = $this->params['allusers'];
		$enableuserimage = $this->params['enableuserimage'];
		$httproot = $this->params['httproot'];

		$this->htmlStartPage(getMLText("my_account"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("my_account"), "my_account");

		$this->contentHeading(getMLText("users"));
		$this->contentContainerStart();

		echo "<table class=\"table table-condensed\">\n";
		echo "<thead>\n<tr>\n";
		if($enableuserimage) echo "<th></th>\n";
		echo "<th>".getMLText("name")."</th>\n";
		echo "</tr>\n</thead>\n";

		foreach ($users as $currUser) {

			if ($currUser->isGuest())
				continue;

			if ($currUser->isHidden()=="1") continue;

			echo "<tr>";
			if($enableuserimage) {
				echo "<td>";
				if ($currUser->hasImage())
					print "<img width=\"100\" src=\"".$httproot . "out/out.UserImage.php?userid=".$currUser->getId()."\">";
				echo "</td>";
			}
			echo "<td>";
			echo htmlspecialchars($currUser->getFullName())." (".htmlspecialchars($currUser->getLogin()).")<br />";
			echo "<a href=\"mailto:".$currUser->getEmail()."\">".htmlspecialchars($currUser->getEmail())."</a><br />";
			echo "<small>".htmlspecialchars($currUser->getComment())."</small>";
			echo "</td>";
			echo "</tr>";
		}

		echo "</table>\n";

		$this->contentContainerEnd();
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
