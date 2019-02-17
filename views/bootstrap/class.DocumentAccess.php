<?php
/**
 * Implementation of DocumentAccess view
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
 * Class which outputs the html page for DocumentAccess view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_DocumentAccess extends LetoDMS_Bootstrap_Style {
	function printAccessModeSelection($defMode) { /* {{{ */
		echo self::getAccessModeSelection($defMode);
	} /* }}} */

	function getAccessModeSelection($defMode) { /* {{{ */
		$content = "<select name=\"mode\">\n";
		$content .= "\t<option value=\"".M_NONE."\"" . (($defMode == M_NONE) ? " selected" : "") . ">" . getMLText("access_mode_none") . "</option>\n";
		$content .= "\t<option value=\"".M_READ."\"" . (($defMode == M_READ) ? " selected" : "") . ">" . getMLText("access_mode_read") . "</option>\n";
		$content .= "\t<option value=\"".M_READWRITE."\"" . (($defMode == M_READWRITE) ? " selected" : "") . ">" . getMLText("access_mode_readwrite") . "</option>\n";
		$content .= "\t<option value=\"".M_ALL."\"" . (($defMode == M_ALL) ? " selected" : "") . ">" . getMLText("access_mode_all") . "</option>\n";
		$content .= "</select>\n";
		return $content;
	} /* }}} */

	function js() { /* {{{ */
		header('Content-Type: application/javascript');
?>
function checkForm()
{
	msg = new Array();
	if ((document.form1.userid.options[document.form1.userid.selectedIndex].value == -1) && 
		(document.form1.groupid.options[document.form1.groupid.selectedIndex].value == -1))
			msg.push("<?php printMLText("js_select_user_or_group");?>");
	if (msg != "")
	{
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
		$document = $this->params['document'];
		$folder = $this->params['folder'];
		$allUsers = $this->params['allusers'];
		$allGroups = $this->params['allgroups'];


		$this->htmlStartPage(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))));
		$this->globalNavigation($folder);
		$this->contentStart();
		$this->pageNavigation($this->getFolderPathHTML($folder, true, $document), "view_document", $document);

		$this->contentHeading(getMLText("edit_document_access"));
		echo "<div class=\"row-fluid\">\n";
		echo "<div class=\"span4\">\n";
		$this->contentContainerStart();

		if ($user->isAdmin()) {

?>
	<form action="../op/op.DocumentAccess.php">
  <?php echo createHiddenFieldWithKey('documentaccess'); ?>
	<input type="Hidden" name="action" value="setowner">
	<input type="Hidden" name="documentid" value="<?php print $document->getId();?>">
<?php
		$owner = $document->getOwner();
		$options = array();
		foreach ($allUsers as $currUser) {
			if (!$currUser->isGuest())
				$options[] = array($currUser->getID(), htmlspecialchars($currUser->getLogin()), ($currUser->getID()==$owner->getID()), array(array('data-subtitle', htmlspecialchars($currUser->getFullName()))));
		}
		$this->formField(
			getMLText("set_owner"),
			array(
				'element'=>'select',
				'name'=>'ownerid',
				'class'=>'chzn-select',
				'options'=>$options
			)
		);
		$this->formSubmit("<i class=\"icon-save\"></i> ".getMLText('save'));
?>
	</form>
<?php
		}

		$this->contentSubHeading(getMLText("access_inheritance"));

		if ($document->inheritsAccess()) {
			printMLText("inherits_access_msg");
?>
  <p>
	<form action="../op/op.DocumentAccess.php" style="display: inline-block;">
  <?php echo createHiddenFieldWithKey('documentaccess'); ?>
	<input type="hidden" name="documentid" value="<?php print $document->getId();?>">
	<input type="hidden" name="action" value="notinherit">
	<input type="hidden" name="mode" value="copy">
	<input type="submit" class="btn" value="<?php printMLText("inherits_access_copy_msg")?>">
	</form>
	<form action="../op/op.DocumentAccess.php" style="display: inline-block;">
  <?php echo createHiddenFieldWithKey('documentaccess'); ?>
	<input type="hidden" name="documentid" value="<?php print $document->getId();?>">
	<input type="hidden" name="action" value="notinherit">
	<input type="hidden" name="mode" value="empty">
	<input type="submit" class="btn" value="<?php printMLText("inherits_access_empty_msg")?>">
	</form>
	</p>
<?php
			$this->contentContainerEnd();
			echo "</div>";
			echo "</div>";
			$this->contentEnd();
			$this->htmlEndPage();
			return;
		}
?>
	<form action="../op/op.DocumentAccess.php">
  <?php echo createHiddenFieldWithKey('documentaccess'); ?>
	<input type="hidden" name="documentid" value="<?php print $document->getId();?>">
	<input type="hidden" name="action" value="inherit">
	<input type="submit" class="btn" value="<?php printMLText("does_not_inherit_access_msg")?>">
	</form>
<?php
		$this->contentContainerEnd();
		echo "</div>";
		echo "<div class=\"span4\">";
		$this->contentContainerStart();

		$accessList = $document->getAccessList();

?>
<form class="form-horizontal" action="../op/op.DocumentAccess.php">
  <?php echo createHiddenFieldWithKey('documentaccess'); ?>
	<input type="Hidden" name="documentid" value="<?php print $document->getId();?>">
	<input type="Hidden" name="action" value="setdefault">
<?php
		$this->formField(
			getMLText("default_access"),
			$this->getAccessModeSelection($document->getDefaultAccess())
		);
		$this->formSubmit("<i class=\"icon-save\"></i> ".getMLText('save'));
?>
</form>

<form class="form-horizontal" action="../op/op.DocumentAccess.php" name="form1" id="form1">
<?php echo createHiddenFieldWithKey('documentaccess'); ?>
<input type="Hidden" name="documentid" value="<?php print $document->getId()?>">
<input type="Hidden" name="action" value="addaccess">
<?php
		$options = array();
		$options[] = array(-1, getMLText('select_one'));
		foreach ($allUsers as $currUser) {
			if (!$currUser->isGuest())
				$options[] = array($currUser->getID(), htmlspecialchars($currUser->getLogin()), ($currUser->getID()==$user->getID()), array(array('data-subtitle', htmlspecialchars($currUser->getFullName()))));
		}
		$this->formField(
			getMLText("user"),
			array(
				'element'=>'select',
				'name'=>'userid',
				'class'=>'chzn-select',
				'options'=>$options
			)
		);
		$options = array();
		$options[] = array(-1, getMLText('select_one'));
		foreach ($allGroups as $groupObj) {
			$options[] = array($groupObj->getID(), htmlspecialchars($groupObj->getName()));
		}
		$this->formField(
			getMLText("group"),
			array(
				'element'=>'select',
				'name'=>'groupid',
				'class'=>'chzn-select',
				'attributes'=>array(array('data-placeholder', getMLText('select_group'))),
				'options'=>$options
			)
		);
		$this->formField(
			getMLText("access_mode"),
			$this->getAccessModeSelection(M_READ)
		);
		$this->formSubmit("<i class=\"icon-plus\"></i> ".getMLText('add'));
?>
</form>
<?php
		$this->contentContainerEnd();
?>
	</div>
	<div class="span4">
<?php
		/* memorize users with access rights */
		$memusers = array();
		/* memorize groups with access rights */
		$memgroups = array();
		if (count($accessList["users"]) != 0 || count($accessList["groups"]) != 0) {

			print "<table class=\"table-condensed\">";

			foreach ($accessList["users"] as $userAccess) {
				$userObj = $userAccess->getUser();
				$memusers[] = $userObj->getID();
				print "<tr>\n";
				print "<td><i class=\"icon-user\"></i></td>\n";
				print "<td>". htmlspecialchars($userObj->getFullName()) . "</td>\n";
				print "<form action=\"../op/op.DocumentAccess.php\">\n";
				print "<td>\n";
				$this->printAccessModeSelection($userAccess->getMode());
				print "</td>\n";
				print "<td>\n";
				echo createHiddenFieldWithKey('documentaccess')."\n";
				print "<input type=\"Hidden\" name=\"documentid\" value=\"".$document->getId()."\">\n";
				print "<input type=\"hidden\" name=\"action\" value=\"editaccess\">\n";
				print "<input type=\"hidden\" name=\"userid\" value=\"".$userObj->getID()."\">\n";
				print "<button type=\"submit\" class=\"btn btn-mini\"><i class=\"icon-save\"></i> ".getMLText("save")."</button>";
				print "</td>\n";
				print "</form>\n";
				print "<form action=\"../op/op.DocumentAccess.php\">\n";
				print "<td><span class=\"actions\">\n";
				echo createHiddenFieldWithKey('documentaccess')."\n";
				print "<input type=\"Hidden\" name=\"documentid\" value=\"".$document->getId()."\">\n";
				print "<input type=\"hidden\" name=\"action\" value=\"delaccess\">\n";
				print "<input type=\"hidden\" name=\"userid\" value=\"".$userObj->getID()."\">\n";
				print "<button type=\"submit\" class=\"btn btn-mini\"><i class=\"icon-remove\"></i> ".getMLText("delete")."</button>";
				print "<span></td>\n";
				print "</form>\n";
				print "</tr>\n";
			}

			foreach ($accessList["groups"] as $groupAccess) {
				$groupObj = $groupAccess->getGroup();
				$memgroups[] = $groupObj->getID();
				$mode = $groupAccess->getMode();
				print "<tr>";
				print "<td><i class=\"icon-group\"></i></td>";
				print "<td>". htmlspecialchars($groupObj->getName()) . "</td>";
				print "<form action=\"../op/op.DocumentAccess.php\">";
				print "<td>";
				$this->printAccessModeSelection($groupAccess->getMode());
				print "</td>\n";
				print "<td><span class=\"actions\">\n";
				echo createHiddenFieldWithKey('documentaccess')."\n";
				print "<input type=\"hidden\" name=\"documentid\" value=\"".$document->getId()."\">";
				print "<input type=\"hidden\" name=\"action\" value=\"editaccess\">";
				print "<input type=\"hidden\" name=\"groupid\" value=\"".$groupObj->getID()."\">";
				print "<button type=\"submit\" class=\"btn btn-mini\"><i class=\"icon-save\"></i> ".getMLText("save")."</button>";
				print "</span></td>\n";
				print "</form>";
				print "<form action=\"../op/op.DocumentAccess.php\">\n";
				print "<td><span class=\"actions\">\n";
				echo createHiddenFieldWithKey('documentaccess')."\n";
				print "<input type=\"hidden\" name=\"documentid\" value=\"".$document->getId()."\">\n";
				print "<input type=\"hidden\" name=\"action\" value=\"delaccess\">\n";
				print "<input type=\"hidden\" name=\"groupid\" value=\"".$groupObj->getID()."\">\n";
				print "<button type=\"submit\" class=\"btn btn-mini\"\"><i class=\"icon-remove\"></i> ".getMLText("delete")."</button>";
				print "</form>";
				print "</span></td>\n";
				print "</tr>\n";
			}
			
			print "</table><br>";
		}
?>
	</div>
	</div>

<?php
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
