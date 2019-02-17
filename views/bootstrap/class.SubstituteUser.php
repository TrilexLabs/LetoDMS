<?php
/**
 * Implementation of SubstituteUser view
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
 * Class which outputs the html page for SubstituteUser view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_SubstituteUser extends LetoDMS_Bootstrap_Style {

	function js() { /* {{{ */
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$allUsers = $this->params['allusers'];

		$this->htmlStartPage(getMLText("substitute_user"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("admin_tools"), "admin_tools");

		$this->contentHeading(getMLText("substitute_user"));
?>
	<table class="table table-condensed">
		<thead>
		<tr><th><?php printMLText('name'); ?></th><th><?php printMLText('email');?></th><th><?php printMLText('groups'); ?></th><th><?php printMLText('role'); ?></th><th></th></tr>
		</thead>
		<tbody>
<?php
		foreach ($allUsers as $currUser) {
			echo "<tr".($currUser->isDisabled() ? " class=\"error\"" : "").">";
			echo "<td>";
			echo htmlspecialchars($currUser->getFullName())." (".htmlspecialchars($currUser->getLogin()).")<br />";
			echo "<small>".htmlspecialchars($currUser->getComment())."</small>";
			echo "</td>";
			echo "<td>";
			echo "<a href=\"mailto:".htmlspecialchars($currUser->getEmail())."\">".htmlspecialchars($currUser->getEmail())."</a><br />";
			echo "</td>";
			echo "<td>";
			$groups = $currUser->getGroups();
			if (count($groups) != 0) {
				for ($j = 0; $j < count($groups); $j++)	{
					print htmlspecialchars($groups[$j]->getName());
					if ($j +1 < count($groups))
						print ", ";
				}
			}
			echo "</td>";
			echo "<td>";
			switch($currUser->getRole()) {
			case LetoDMS_Core_User::role_user:
				printMLText("role_user");
				break;
			case LetoDMS_Core_User::role_admin:
				printMLText("role_admin");
				break;
			case LetoDMS_Core_User::role_guest:
				printMLText("role_guest");
				break;
			}
			echo "</td>";
			echo "<td>";
			if($currUser->getID() != $user->getID()) {
				echo "<a class=\"btn\" href=\"../op/op.SubstituteUser.php?userid=".((int) $currUser->getID())."&formtoken=".createFormKey('substituteuser')."\"><i class=\"icon-exchange\"></i> ".getMLText('substitute_user')."</a> ";
			}
			echo "</td>";
			echo "</tr>";
		}
		echo "</tbody>";
		echo "</table>";

		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
