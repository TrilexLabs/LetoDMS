<?php
/**
 * Implementation of DocumentNotify view
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
 * Class which outputs the html page for DocumentNotify view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_DocumentNotify extends LetoDMS_Bootstrap_Style {

	function js() { /* {{{ */
		header('Content-Type: application/javascript');
?>
function checkForm()
{
	msg = new Array();
	if ((document.form1.userid.options[document.form1.userid.selectedIndex].value == -1) && 
		(document.form1.groupid.options[document.form1.groupid.selectedIndex].value == -1))
			msg.push("<?php printMLText("js_select_user_or_group");?>");
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

$(document).ready( function() {
	$('body').on('submit', '#form1', function(ev){
		if(checkForm()) return;
		ev.preventDefault();
	});
});
<?php
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$folder = $this->params['folder'];
		$document = $this->params['document'];
		$sortusersinlist = $this->params['sortusersinlist'];

		$notifyList = $document->getNotifyList(0, true);

		$this->htmlStartPage(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))));
		$this->globalNavigation($folder);
		$this->contentStart();
		$this->pageNavigation($this->getFolderPathHTML($folder, true, $document), "view_document", $document);

		$this->contentHeading(getMLText("edit_existing_notify"));

		$userNotifyIDs = array();
		foreach ($notifyList["users"] as $userNotify) {
			$userNotifyIDs[] = $userNotify->getID();
		}
		$groupNotifyIDs = array();
		foreach ($notifyList["groups"] as $groupNotify) {
			$groupNotifyIDs[] = $groupNotify->getID();
		}

		echo "<div class=\"row-fluid\">\n";
		echo "<div class=\"span6\">\n";
		$this->contentContainerStart();

?>

<form class="form-horizontal" action="../op/op.DocumentNotify.php" method="post" name="form1" id="form1">
<?php	echo createHiddenFieldWithKey('documentnotify'); ?>
<input type="hidden" name="documentid" value="<?php print $document->getID()?>">
<input type="hidden" name="action" value="addnotify">
<?php
		$options = array();
		$options[] = array('-1', getMLText("select_one"));
		if ($user->isAdmin()) {
			$allUsers = $dms->getAllUsers($sortusersinlist);
			foreach ($allUsers as $userObj) {
				if (!$userObj->isGuest() && !$userObj->isDisabled() && ($document->getAccessMode($userObj) >= M_READ) && !in_array($userObj->getID(), $userNotifyIDs))
					$options[] = array($userObj->getID(), htmlspecialchars($userObj->getLogin() . " - " . $userObj->getFullName()));
			}
		} elseif (!$user->isGuest() && !in_array($user->getID(), $userNotifyIDs)) {
			$options[] = array($user->getID(), htmlspecialchars($user->getLogin() . " - " .$user->getFullName()));
		}
		$this->formField(
			getMLText("user"),
			array(
				'element'=>'select',
				'id'=>'userid',
				'name'=>'userid',
				'options'=>$options
			)
		);
		$options = array();
		$options[] = array('-1', getMLText("select_one"));
		$allGroups = $dms->getAllGroups();
		foreach ($allGroups as $groupObj) {
			if (($user->isAdmin() || $groupObj->isMember($user,true)) && $document->getGroupAccessMode($groupObj) >= M_READ && !in_array($groupObj->getID(), $groupNotifyIDs)) {
				$options[] =  array($groupObj->getID(), htmlspecialchars($groupObj->getName()));
			}
		}
		$this->formField(
			getMLText("group"),
			array(
				'element'=>'select',
				'id'=>'groupid',
				'name'=>'groupid',
				'options'=>$options
			)
		);
		$this->formSubmit(getMLText('add'));
?>
</form>
<?php
		$this->contentContainerEnd();
		echo "</div>\n";
		echo "<div class=\"span6\">\n";
		print "<table class=\"table-condensed\">\n";
		if ((count($notifyList["users"]) == 0) && (count($notifyList["groups"]) == 0)) {
			print "<tr><td>".getMLText("empty_notify_list")."</td></tr>";
		}
		else {
			foreach ($notifyList["users"] as $userNotify) {
				print "<tr>";
				print "<td><i class=\"icon-user\"></i></td>";
				print "<td>" . htmlspecialchars($userNotify->getLogin() . " - " . $userNotify->getFullName()) . "</td>";
				if ($user->isAdmin() || $user->getID() == $userNotify->getID()) {
					print "<form action=\"../op/op.DocumentNotify.php\" method=\"post\">\n";
					echo createHiddenFieldWithKey('documentnotify')."\n";
					print "<input type=\"hidden\" name=\"documentid\" value=\"".$document->getID()."\">\n";
					print "<input type=\"hidden\" name=\"action\" value=\"delnotify\">\n";
					print "<input type=\"hidden\" name=\"userid\" value=\"".$userNotify->getID()."\">\n";
					print "<td>";
					print "<button type=\"submit\" class=\"btn btn-mini\"><i class=\"icon-remove\"></i> ".getMLText("delete")."</button>";
					print "</td>";
					print "</form>\n";
				}else print "<td></td>";
				print "</tr>";
			}
			foreach ($notifyList["groups"] as $groupNotify) {
				print "<tr>";
				print "<td><i class=\"icon-group\"></i></td>";
				print "<td>" . htmlspecialchars($groupNotify->getName()) . "</td>";
				if ($user->isAdmin() || $groupNotify->isMember($user,true)) {
					print "<form action=\"../op/op.DocumentNotify.php\" method=\"post\">\n";
					echo createHiddenFieldWithKey('documentnotify')."\n";
					print "<input type=\"hidden\" name=\"documentid\" value=\"".$document->getID()."\">\n";
					print "<input type=\"hidden\" name=\"action\" value=\"delnotify\">\n";
					print "<input type=\"hidden\" name=\"groupid\" value=\"".$groupNotify->getID()."\">\n";
					print "<td>";
					print "<button type=\"submit\" class=\"btn btn-mini\"><i class=\"icon-remove\"></i> ".getMLText("delete")."</button>";
					print "</td>";
					print "</form>\n";
				}else print "<td></td>";
				print "</tr>";
			}
		}
		print "</table>\n";

		echo "</div>\n";
		echo "</div>\n";
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
