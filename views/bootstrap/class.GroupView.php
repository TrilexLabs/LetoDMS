<?php
/**
 * Implementation of GroupView view
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
 * Class which outputs the html page for GroupView view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_GroupView extends LetoDMS_Bootstrap_Style {

	function js() { /* {{{ */
		header('Content-Type: application/javascript');
?>
$(document).ready( function() {
	$( "#selector" ).change(function() {
		$('#form').submit();
	});
});
<?php
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$allGroups = $this->params['allgroups'];
		$allUsers = $this->params['allusers'];

		$this->htmlStartPage(getMLText("my_account"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("my_account"), "my_account");

		$this->contentHeading(getMLText("groups"));
		$this->contentContainerStart();

		echo "<ul class=\"groupView\">\n";

		foreach ($allGroups as $group){

			if(!$group->isMember($user) && !$user->isAdmin())
				continue;
			$members = $group->getUsers();
			$managers = $group->getManagers();
			$ismanager = false; /* set to true if current user is manager */

			echo "<li>".htmlspecialchars($group->getName());
			if($group->getComment())
				echo " : ".htmlspecialchars($group->getComment());
			$ismanager = $group->isMember($user, 1);
			if($ismanager)
				echo " : ".getMLText("manager_of_group");
			echo "</li>";

			echo "<ul>\n";
			$memberids = array();
			foreach ($members as $member) {
				$memberids[] = $member->getId();

				echo "<li>".htmlspecialchars($member->getFullName());
				if ($member->getEmail()!="")
					echo " (<a href=\"mailto:".htmlspecialchars($member->getEmail())."\">".htmlspecialchars($member->getEmail())."</a>)";
				foreach($managers as $manager)
					if($manager->getId() == $member->getId())
						echo ", ".getMLText("manager");
				if($ismanager) {
					echo ' <a href="../op/op.GroupView.php?action=del&groupid='.$group->getId().'&userid='.$member->getId().'" class="btn btn-mini"><i class="icon-remove"></i> '.getMLText("rm_user").'</a>';
				}
				echo "</li>";
			}
			if($ismanager) {
				echo "<li>".getMLText("add_user_to_group").":";
				echo "<form id=\"form\" action=\"../op/op.GroupView.php\">";
				echo "<input type=\"hidden\" name=\"action\" value=\"add\" /><input type=\"hidden\" name=\"groupid\" value=\"".$group->getId()."\" />";
				echo "<select id=\"selector\" name=\"userid\" _onChange=\"javascript: submit();\">";
				echo "<option value=\"\"></option>";
				foreach($allUsers as $u) {
					if(!$u->isAdmin() && !$u->isGuest() && !in_array($u->getId(), $memberids))
						echo "<option value=\"".$u->getId()."\">".htmlspecialchars($u->getFullName())."</option>";
				}
				echo "</select>";
				echo "</form>";
				echo "</li>";
			}
			echo "</ul>\n";
		}
		echo "</ul>\n";

		$this->contentContainerEnd();
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
