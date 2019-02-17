<?php
/**
 * Implementation of UsrMgr view
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
 * Class which outputs the html page for UsrMgr view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_UsrMgr extends LetoDMS_Bootstrap_Style {

	function js() { /* {{{ */
		$seluser = $this->params['seluser'];
		$strictformcheck = $this->params['strictformcheck'];

		header('Content-Type: application/javascript');
?>
function checkForm()
{
	msg = new Array();

	if($("#login").val() == "") msg.push("<?php printMLText("js_no_login");?>");
	if(($("#userid").val() == "0") && ($("#pwd").val() == "")) msg.push("<?php printMLText("js_no_pwd");?>");
	if(($("#pwd").val() != $("#pwdconf").val())&&($("#pwd").val() != "")&&($("#pwdconf").val() != "")) msg.push("<?php printMLText("js_pwd_not_conf");?>");
	if($("#name").val() == "") msg.push("<?php printMLText("js_no_name");?>");
	if($("#email").val() == "") msg.push("<?php printMLText("js_no_email");?>");
<?php
	if ($strictformcheck) {
?>
	if($("#comment").val() == "") msg.push("<?php printMLText("js_no_comment");?>");
<?php
	}
?>
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
	$('body').on('submit', '#form', function(ev){
		if(checkForm()) return;
		ev.preventDefault();
	});
	$( "#selector" ).change(function() {
		$('div.ajax').trigger('update', {userid: $(this).val()});
	});
});
<?php

		$this->printFileChooserJs();
	} /* }}} */

	function info() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$seluser = $this->params['seluser'];
		$quota = $this->params['quota'];
		$workflowmode = $this->params['workflowmode'];

		if($seluser) {
			$sessionmgr = new LetoDMS_SessionMgr($dms->getDB());

			$this->contentHeading(getMLText("user_info"));
			echo "<table class=\"table table-condensed\">\n";
			echo "<tr><td>".getMLText('discspace')."</td><td>";
			if($quota) {
				$qt = $seluser->getQuota() ? $seluser->getQuota() : $quota;
				echo LetoDMS_Core_File::format_filesize($seluser->getUsedDiskSpace())." / ".LetoDMS_Core_File::format_filesize($qt)."<br />";
				echo $this->getProgressBar($seluser->getUsedDiskSpace(), $qt);
			} else {
				echo LetoDMS_Core_File::format_filesize($seluser->getUsedDiskSpace())."<br />";
			}
			echo "</td></tr>\n";
			$documents = $seluser->getDocuments();
			echo "<tr><td>".getMLText('documents')."</td><td>".count($documents)."</td></tr>\n";
			$documents = $seluser->getDocumentsLocked();
			echo "<tr><td>".getMLText('documents_locked')."</td><td>".count($documents)."</td></tr>\n";
			$categories = $seluser->getKeywordCategories();
			echo "<tr><td>".getMLText('personal_default_keywords')."</td><td>".count($categories)."</td></tr>\n";
			$dnot = $seluser->getNotifications(T_DOCUMENT);
			echo "<tr><td>".getMLText('documents_with_notification')."</td><td>".count($dnot)."</td></tr>\n";
			$fnot = $seluser->getNotifications(T_FOLDER);
			echo "<tr><td>".getMLText('folders_with_notification')."</td><td>".count($fnot)."</td></tr>\n";

			if($workflowmode == "traditional") {
				$resArr = $dms->getDocumentList('ReviewByMe', $seluser);
				if($resArr) {
					foreach ($resArr as $res) {
						$document = $dms->getDocument($res["id"]);
						if($document->getAccessMode($user) >= M_READ && $document->getLatestContent()) {
							$tasks['review'][] = array('id'=>$res['id'], 'name'=>$res['name']);
						}
					}
					echo "<tr><td>".getMLText('pending_reviews')."</td><td>".count($tasks['review'])."</td></tr>\n";
				}
			}
			if($workflowmode == "traditional" || $workflowmode == 'traditional_only_approval') {
				$resArr = $dms->getDocumentList('ApproveByMe', $seluser);
				if($resArr) {
					foreach ($resArr as $res) {
						$document = $dms->getDocument($res["id"]);
						if($document->getAccessMode($user) >= M_READ && $document->getLatestContent()) {
							$tasks['approval'][] = array('id'=>$res['id'], 'name'=>$res['name']);
						}
					}
					echo "<tr><td>".getMLText('pending_approvals')."</td><td>".count($tasks['approval'])."</td></tr>\n";
				}
				$resArr = $seluser->isMandatoryReviewerOf();
				if($resArr) {
					echo "<tr><td>".getMLText('mandatory_reviewers')."</td><td>".count($resArr)."</td></tr>\n";
				}
				$resArr = $seluser->isMandatoryApproverOf();
				if($resArr) {
					echo "<tr><td>".getMLText('mandatory_approvers')."</td><td>".count($resArr)."</td></tr>\n";
				}
			}
			if($workflowmode == 'advanced') {
				$workflows = $seluser->getWorkflowsInvolved();
				echo "<tr><td>".getMLText('workflows_involded')."</td><td>".count($workflows)."</td></tr>\n";
				$workflowStatus = $seluser->getWorkflowStatus();
				if($workflowStatus['u'])
					echo "<tr><td>".getMLText('pending_workflows')."</td><td>".count($workflowStatus['u'])."</td></tr>\n";
			}
			$sessions = $sessionmgr->getUserSessions($seluser);
			if($sessions) {
				$session = array_shift($sessions);
				echo "<tr><td>".getMLText('lastaccess')."</td><td>".getLongReadableDate($session->getLastAccess())."</td></tr>\n";
			}
			echo "</table>";

		}
	} /* }}} */

	function actionmenu() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$seluser = $this->params['seluser'];
		$quota = $this->params['quota'];
		$workflowmode = $this->params['workflowmode'];
		$undeluserids = $this->params['undeluserids'];
		$enableemail = $this->params['enableemail'];

		if($seluser) {
?>
<div class="btn-group">
  <a class="btn dropdown-toggle" data-toggle="dropdown" href="#">
		<?php echo getMLText('action'); ?>
    <span class="caret"></span>
  </a>
  <ul class="dropdown-menu">
<?php
			if(!in_array($seluser->getID(), $undeluserids)) {
				echo '<li><a href="../out/out.RemoveUser.php?userid='.$seluser->getID().'"><i class="icon-remove"></i> '.getMLText("rm_user").'</a><li>';
			}
			echo '<li><a href="../out/out.RemoveUserFromProcesses.php?userid='.$seluser->getID().'"><i class="icon-remove"></i> '.getMLText("rm_user_from_processes").'</a></li>';
			echo '<li><a href="../out/out.TransferObjects.php?userid='.$seluser->getID().'"><i class="icon-share-alt"></i> '.getMLText("transfer_objects").'</a></li>';
			if($user->isAdmin() && $seluser->getID() != $user->getID())
				echo "<li><a href=\"../op/op.SubstituteUser.php?userid=".$seluser->getID()."&formtoken=".createFormKey('substituteuser')."\"><i class=\"icon-exchange\"></i> ".getMLText("substitute_user")."</a></li>\n";
			if($enableemail)
				echo '<li><a href="../out/out.SendLoginData.php?userid='.$seluser->getID().'"><i class="icon-envelope-alt"></i> '.getMLText("send_login_data").'</a></li>';
?>
	</ul>
</div>
<?php
		}
	} /* }}} */

	function form() { /* {{{ */
		$seluser = $this->params['seluser'];

		$this->showUserForm($seluser);
	} /* }}} */

	function showUserForm($currUser) { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$users = $this->params['allusers'];
		$groups = $this->params['allgroups'];
		$passwordstrength = $this->params['passwordstrength'];
		$passwordexpiration = $this->params['passwordexpiration'];
		$httproot = $this->params['httproot'];
		$enableuserimage = $this->params['enableuserimage'];
		$undeluserids = $this->params['undeluserids'];
		$workflowmode = $this->params['workflowmode'];
		$quota = $this->params['quota'];
?>
	<form class="form-horizontal" action="../op/op.UsrMgr.php" method="post" enctype="multipart/form-data" name="form" id="form">
<?php
		if($currUser) {
			echo createHiddenFieldWithKey('edituser');
?>
	<input type="hidden" name="userid" id="userid" value="<?php print $currUser->getID();?>">
	<input type="hidden" name="action" value="edituser">
<?php
		} else {
			echo createHiddenFieldWithKey('adduser');
?>
	<input type="hidden" id="userid" value="0">
	<input type="hidden" name="action" value="adduser">
<?php
		}
		$this->formField(
			getMLText("user_login"),
			array(
				'element'=>'input',
				'type'=>'text',
				'id'=>'login',
				'name'=>'login',
				'value'=>($currUser ? htmlspecialchars($currUser->getLogin()) : '')
			)
		);
		$this->formField(
			getMLText("password"),
			'<input type="password" class="pwd form-control" rel="strengthbar'.($currUser ? $currUser->getID() : "0").'" name="pwd" id="pwd">'.(($currUser && $currUser->isGuest()) ? ' <input type="checkbox" name="clearpwd" value="1" /> '.getMLText('clear_password') : '')
		);
		if($passwordstrength > 0) {
			$this->formField(
				getMLText("password_strength"),
				'<div id="strengthbar'.($currUser ? $currUser->getID() : "0").'" class="progress" style="width: 220px; height: 30px; margin-bottom: 8px;"><div class="bar bar-danger" style="width: 0%;"></div></div>'
			);
		}
		$this->formField(
			getMLText("confirm_pwd"),
			array(
				'element'=>'input',
				'type'=>'password',
				'id'=>'pwdconf',
				'name'=>'pwdconf',
			)
		);
		if($passwordexpiration > 0) {
			$options = array();
			if($currUser)
				$options[] = array('', getMLText("keep"));
			$options[] = array('now', getMLText('now'));
			$options[] = array(date('Y-m-d H:i:s', time()+$passwordexpiration*86400), getMLText("according_settings"));
			$options[] = array('never', getMLText("never"));
			$this->formField(
				getMLText("password_expiration"),
				array(
					'element'=>'select',
					'name'=>'pwdexpiration',
					'options'=>$options
				)
			);
		}
		$this->formField(
			getMLText("user_name"),
			array(
				'element'=>'input',
				'type'=>'text',
				'id'=>'name',
				'name'=>'name',
				'value'=>($currUser ? htmlspecialchars($currUser->getFullName()) : '')
			)
		);
		$this->formField(
			getMLText("email"),
			array(
				'element'=>'input',
				'type'=>'text',
				'id'=>'email',
				'name'=>'email',
				'value'=>($currUser ? htmlspecialchars($currUser->getEmail()) : '')
			)
		);
		$this->formField(
			getMLText("comment"),
			array(
				'element'=>'textarea',
				'name'=>'comment',
				'id'=>'comment',
				'rows'=>4,
				'cols'=>50,
				'value'=>($currUser ? htmlspecialchars($currUser->getComment()) : '')
			)
		);
		$options = array();
		$options[] = array(LetoDMS_Core_User::role_user, getMLText('role_user'));
		$options[] = array(LetoDMS_Core_User::role_admin, getMLText('role_admin'), $currUser && $currUser->getRole() == LetoDMS_Core_User::role_admin);
		$options[] = array(LetoDMS_Core_User::role_guest, getMLText('role_guest'), $currUser && $currUser->getRole() == LetoDMS_Core_User::role_guest);
		$this->formField(
			getMLText("role"),
			array(
				'element'=>'select',
				'name'=>'role',
				'options'=>$options
			)
		);
		$options = array();
		foreach($groups as $group) {
			$options[] = array($group->getID(), $group->getName(), ($currUser && $group->isMember($currUser)));
		}
		$this->formField(
			getMLText("groups"),
			array(
				'element'=>'select',
				'name'=>'groups[]',
				'class'=>'chzn-select',
				'multiple'=>true,
				'attributes'=>array(array('data-placeholder', getMLText('select_groups'))),
				'options'=>$options
			)
		);
		$this->formField(getMLText("home_folder"), $this->getFolderChooserHtml("form".($currUser ? $currUser->getId() : '0'), M_READ, -1, $currUser ? $dms->getFolder($currUser->getHomeFolder()) : 0, 'homefolder'));
		echo '<script language="JavaScript">';
		$this->printFolderChooserJs("form".($currUser ? $currUser->getId() : '0'));
		echo '</script>';

		$this->formField(
			getMLText("quota"),
			array(
				'element'=>'input',
				'type'=>'text',
				'id'=>'quota',
				'name'=>'quota',
				'value'=>($currUser ? $currUser->getQuota() : '')
			)
		);
		if($quota > 0)
			$this->warningMsg(getMLText('current_quota', array('quota'=>LetoDMS_Core_File::format_filesize($quota))));
		else
			$this->warningMsg(getMLText('quota_is_disabled'));
		$this->formField(
			getMLText("is_hidden"),
			array(
				'element'=>'input',
				'type'=>'checkbox',
				'name'=>'ishidden',
				'value'=>1,
				'checked'=>$currUser && $currUser->isHidden()
			)
		);
		$this->formField(
			getMLText("is_disabled"),
			array(
				'element'=>'input',
				'type'=>'checkbox',
				'name'=>'isdisabled',
				'value'=>1,
				'checked'=>$currUser && $currUser->isDisabled()
			)
		);
		if ($enableuserimage) {
			if ($currUser) {
				$this->formField(
					getMLText("user_image"),
					($currUser->hasImage() ? "<img src=\"".$httproot."out/out.UserImage.php?userid=".$currUser->getId()."\">" : getMLText('no_user_image'))
				);
				$this->formField(
					getMLText("new_user_image"),
					$this->getFileChooserHtml('userfile', false, "image/jpeg")
				);
			} else {
				$this->formField(
					getMLText("user_image"),
					$this->getFileChooserHtml('userfile', false, "image/jpeg")
				);
			}
		}
		if($workflowmode == "traditional" || $workflowmode == 'traditional_only_approval') {
			if($workflowmode == "traditional") {
				$this->contentSubHeading(getMLText("mandatory_reviewers"));
				$options = array();
				if($currUser)
					$res=$currUser->getMandatoryReviewers();
				else
					$res = array();
				foreach ($users as $usr) {
					if ($usr->isGuest() || ($currUser && $usr->getID() == $currUser->getID()))
						continue;

					$checked=false;
					foreach ($res as $r) if ($r['reviewerUserID']==$usr->getID()) $checked=true;

					$options[] = array($usr->getID(), htmlspecialchars($usr->getLogin()." - ".$usr->getFullName()), $checked);
				}
				$this->formField(
					getMLText("individuals"),
					array(
						'element'=>'select',
						'name'=>'usrReviewers[]',
						'class'=>'chzn-select',
						'attributes'=>array(array('data-placeholder', getMLText('select_users'))),
						'multiple'=>true,
						'options'=>$options
					)
				);
				$options = array();
				foreach ($groups as $grp) {

					$checked=false;
					foreach ($res as $r) if ($r['reviewerGroupID']==$grp->getID()) $checked=true;

					$options[] = array($grp->getID(), htmlspecialchars($grp->getName()), $checked);
				}
				$this->formField(
					getMLText("groups"),
					array(
						'element'=>'select',
						'name'=>'grpReviewers[]',
						'class'=>'chzn-select',
						'attributes'=>array(array('data-placeholder', getMLText('select_groups'))),
						'multiple'=>true,
						'options'=>$options
					)
				);
			}

			$this->contentSubHeading(getMLText("mandatory_approvers"));
			$options = array();
			if($currUser)
				$res=$currUser->getMandatoryApprovers();
			else
				$res = array();
			foreach ($users as $usr) {
				if ($usr->isGuest() || ($currUser && $usr->getID() == $currUser->getID()))
					continue;

				$checked=false;
				foreach ($res as $r) if ($r['approverUserID']==$usr->getID()) $checked=true;

				$options[] = array($usr->getID(), htmlspecialchars($usr->getLogin()." - ".$usr->getFullName()), $checked);
			}
			$this->formField(
				getMLText("individuals"),
				array(
					'element'=>'select',
					'name'=>'usrApprovers[]',
					'class'=>'chzn-select',
					'attributes'=>array(array('data-placeholder', getMLText('select_users'))),
					'multiple'=>true,
					'options'=>$options
				)
			);
			$options = array();
			foreach ($groups as $grp) {

				$checked=false;
				foreach ($res as $r) if ($r['approverGroupID']==$grp->getID()) $checked=true;

				$options[] = array($grp->getID(), htmlspecialchars($grp->getName()), $checked);
			}
			$this->formField(
				getMLText("groups"),
				array(
					'element'=>'select',
					'name'=>'grpApprovers[]',
					'class'=>'chzn-select',
					'attributes'=>array(array('data-placeholder', getMLText('select_groups'))),
					'multiple'=>true,
					'options'=>$options
				)
			);
		} else {
			$workflows = $dms->getAllWorkflows();
			if($workflows) {
				$this->contentSubHeading(getMLText("workflow"));
				$options = array();
				$mandatoryworkflows = $currUser ? $currUser->getMandatoryWorkflows() : array();
				foreach ($workflows as $workflow) {
					$checked = false;
					if($mandatoryworkflows) foreach($mandatoryworkflows as $mw) if($mw->getID() == $workflow->getID()) $checked = true;
					$options[] = array($workflow->getID(), htmlspecialchars($workflow->getName()), $checked);
				}
				$this->formField(
					getMLText("workflow"),
					array(
						'element'=>'select',
						'name'=>'workflows[]',
						'class'=>'chzn-select',
						'attributes'=>array(array('data-placeholder', getMLText('select_workflow'))),
						'multiple'=>true,
						'options'=>$options
					)
				);
			}
		}
		$this->formSubmit("<i class=\"icon-save\"></i> ".getMLText($currUser ? "save" : "add_user"));
?>
	</form>
<?php
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$seluser = $this->params['seluser'];
		$users = $this->params['allusers'];
		$groups = $this->params['allgroups'];
		$passwordstrength = $this->params['passwordstrength'];
		$passwordexpiration = $this->params['passwordexpiration'];
		$httproot = $this->params['httproot'];
		$enableuserimage = $this->params['enableuserimage'];
		$undeluserids = $this->params['undeluserids'];
		$workflowmode = $this->params['workflowmode'];
		$quota = $this->params['quota'];

		$this->htmlStartPage(getMLText("admin_tools"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("admin_tools"), "admin_tools");

		$this->contentHeading(getMLText("user_management"));
?>
<div class="row-fluid">
<div class="span4">
<form class="form-horizontal">
<?php
		$options = array();
		$options[] = array("-1", getMLText("choose_user"));
		$options[] = array("0", getMLText("add_user"));
		foreach ($users as $currUser) {
			$options[] = array($currUser->getID(), htmlspecialchars($currUser->getLogin()), $seluser && $currUser->getID()==$seluser->getID(), array(array('data-subtitle', htmlspecialchars($currUser->getFullName()))));
		}
		$this->formField(
			null, //getMLText("selection"),
			array(
				'element'=>'select',
				'id'=>'selector',
				'class'=>'chzn-select',
				'options'=>$options
			)
		);
?>
</form>
	<div class="ajax" style="margin-bottom: 15px;" data-view="UsrMgr" data-action="actionmenu" <?php echo ($seluser ? "data-query=\"userid=".$seluser->getID()."\"" : "") ?>></div>
	<div class="ajax" data-view="UsrMgr" data-action="info" <?php echo ($seluser ? "data-query=\"userid=".$seluser->getID()."\"" : "") ?>></div>
</div>

<div class="span8">
	<?php	$this->contentContainerStart(); ?>
		<div class="ajax" data-view="UsrMgr" data-action="form" <?php echo ($seluser ? "data-query=\"userid=".$seluser->getID()."\"" : "") ?>></div>
	</div>
	<?php	$this->contentContainerEnd(); ?>
</div>

<?php
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
