<?php
/**
 * Implementation of UpdateDocument view
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
 * Class which outputs the html page for UpdateDocument view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_UpdateDocument extends LetoDMS_Bootstrap_Style {

	function js() { /* {{{ */
		$strictformcheck = $this->params['strictformcheck'];
		$dropfolderdir = $this->params['dropfolderdir'];
		$enablelargefileupload = $this->params['enablelargefileupload'];
		$partitionsize = $this->params['partitionsize'];
		$maxuploadsize = $this->params['maxuploadsize'];
		header('Content-Type: application/javascript');
		$this->printDropFolderChooserJs("form1");
		$this->printSelectPresetButtonJs();
		$this->printInputPresetButtonJs();
		$this->printCheckboxPresetButtonJs();
		if($enablelargefileupload)
			$this->printFineUploaderJs('../op/op.UploadChunks.php', $partitionsize, $maxuploadsize, false);
		$this->printFileChooserJs();
?>
$(document).ready( function() {
	jQuery.validator.addMethod("alternatives", function(value, element, params) {
		if(value == '' && params.val() == '')
			return false;
		return true;
	}, "<?php printMLText("js_no_file");?>");
	/* The fineuploader validation is actually checking all fields that can contain
	 * a file to be uploaded. First checks if an alternative input field is set,
	 * second loops through the list of scheduled uploads, checking if at least one
	 * file will be submitted.
	 */
	jQuery.validator.addMethod("fineuploader", function(value, element, params) {
		if(params[1].val() != '')
			return true;
		uploader = params[0];
		arr = uploader.getUploads();
		for(var i in arr) {
			if(arr[i].status == 'submitted')
				return true;
		}
		return false;
	}, "<?php printMLText("js_no_file");?>");
	$("#form1").validate({
		debug: false,
		ignore: ":hidden:not(.do_validate)",
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
<?php
		if($enablelargefileupload) {
?>
		submitHandler: function(form) {
			/* fileuploader may not have any files if drop folder is used */
			if(userfileuploader.getUploads().length)
				userfileuploader.uploadStoredFiles();
			else
				form.submit();
		},
<?php
		}
?>
		rules: {
<?php
		if($enablelargefileupload) {
?>
			'userfile-fine-uploader-uuids': {
				fineuploader: [ userfileuploader, $('#dropfolderfileform1') ]
			}
<?php
		} else {
?>
			userfile: {
				alternatives: $('#dropfolderfileform1')
			},
			dropfolderfileform1: {
				 alternatives: $('#userfile')
			}
<?php
		}
?>
		},
		messages: {
			comment: "<?php printMLText("js_no_comment");?>",
		},
		errorPlacement: function( error, element ) {
			if ( element.is( ":file" ) ) {
				error.appendTo( element.parent().parent().parent());
console.log(element);
			} else {
				error.appendTo( element.parent());
			}
		}
	});
	$('#presetexpdate').on('change', function(ev){
		if($(this).val() == 'date')
			$('#control_expdate').show();
		else
			$('#control_expdate').hide();
	});
});
<?php
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$folder = $this->params['folder'];
		$document = $this->params['document'];
		$strictformcheck = $this->params['strictformcheck'];
		$enablelargefileupload = $this->params['enablelargefileupload'];
		$maxuploadsize = $this->params['maxuploadsize'];
		$enableadminrevapp = $this->params['enableadminrevapp'];
		$enableownerrevapp = $this->params['enableownerrevapp'];
		$enableselfrevapp = $this->params['enableselfrevapp'];
		$dropfolderdir = $this->params['dropfolderdir'];
		$workflowmode = $this->params['workflowmode'];
		$presetexpiration = $this->params['presetexpiration'];
		$documentid = $document->getId();

		$this->htmlAddHeader('<script type="text/javascript" src="../styles/'.$this->theme.'/validate/jquery.validate.js"></script>'."\n", 'js');
		if($enablelargefileupload) {
			$this->htmlAddHeader('<script type="text/javascript" src="../styles/'.$this->theme.'/fine-uploader/jquery.fine-uploader.min.js"></script>'."\n", 'js');
			$this->htmlAddHeader($this->getFineUploaderTemplate(), 'js');
		}

		$this->htmlStartPage(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))));
		$this->globalNavigation($folder);
		$this->contentStart();
		$this->pageNavigation($this->getFolderPathHTML($folder, true, $document), "view_document", $document);
		$this->contentHeading(getMLText("update_document"));

		if ($document->isLocked()) {

			$lockingUser = $document->getLockingUser();

			print "<div class=\"alert alert-warning\">";
			
			printMLText("update_locked_msg", array("username" => htmlspecialchars($lockingUser->getFullName()), "email" => $lockingUser->getEmail()));
			
			if ($lockingUser->getID() == $user->getID())
				printMLText("unlock_cause_locking_user");
			else if ($document->getAccessMode($user) == M_ALL)
				printMLText("unlock_cause_access_mode_all");
			else
			{
				printMLText("no_update_cause_locked");
				print "</div>";
				$this->contentEnd();
				$this->htmlEndPage();
				exit;
			}

			print "</div>";
		}

		$latestContent = $document->getLatestContent();
		$reviewStatus = $latestContent->getReviewStatus();
		$approvalStatus = $latestContent->getApprovalStatus();
		if($workflowmode == 'advanced') {
			if($status = $latestContent->getStatus()) {
				if($status["status"] == S_IN_WORKFLOW) {
					$this->warningMsg("The current version of this document is in a workflow. This will be interrupted and cannot be completed if you upload a new version.");
				}
			}
		}

		if($enablelargefileupload) {
			if($maxuploadsize) {
				$msg = getMLText("max_upload_size").": ".LetoDMS_Core_File::format_filesize($maxuploadsize);
			} else {
				$msg = '';
			}
		} else {
			$msg = getMLText("max_upload_size").": ".ini_get( "upload_max_filesize");
		}
		if(0 && $enablelargefileupload) {
			$msg .= "<p>".sprintf(getMLText('link_alt_updatedocument'), "out.AddMultiDocument.php?folderid=".$folder->getID()."&showtree=".showtree())."</p>";
		}
		if($msg)
			$this->warningMsg($msg);
		$this->contentContainerStart();
?>

<form class="form-horizontal" action="../op/op.UpdateDocument.php" enctype="multipart/form-data" method="post" name="form1" id="form1">
	<?php echo createHiddenFieldWithKey('updatedocument'); ?>
	<input type="hidden" name="documentid" value="<?php print $document->getID(); ?>">
<?php
		$this->formField(
			getMLText("local_file"),
			$enablelargefileupload ? $this->getFineUploaderHtml() : $this->getFileChooserHtml('userfile', false)
		);
		if($dropfolderdir) {
			$this->formField(
				getMLText("dropfolder_file"),
				$this->getDropFolderChooserHtml("form1")
			);
		}
		$this->formField(
			getMLText("comment"),
			array(
				'element'=>'textarea',
				'name'=>'comment',
				'rows'=>4,
				'cols'=>80
			)
		);
		if($presetexpiration) {
			if(!($expts = strtotime($presetexpiration)))
				$expts = false;
		} else {
			$expts = false;
		}
		$options = array();
		$options[] = array('never', getMLText('does_not_expire'));
		$options[] = array('date', getMLText('expire_by_date'), $expts);
		$options[] = array('1w', getMLText('expire_in_1w'));
		$options[] = array('1m', getMLText('expire_in_1m'));
		$options[] = array('1y', getMLText('expire_in_1y'));
		$options[] = array('2y', getMLText('expire_in_2y'));
		$this->formField(
			getMLText("preset_expires"),
			array(
				'element'=>'select',
				'id'=>'presetexpdate',
				'name'=>'presetexpdate',
				'options'=>$options
			)
		);
		$this->formField(
			getMLText("expires"),
			$this->getDateChooser(($expts ? date('Y-m-d', $expts) : ''), "expdate", $this->params['session']->getLanguage())
		);
		$attrdefs = $dms->getAllAttributeDefinitions(array(LetoDMS_Core_AttributeDefinition::objtype_documentcontent, LetoDMS_Core_AttributeDefinition::objtype_all));
		if($attrdefs) {
			foreach($attrdefs as $attrdef) {
				$arr = $this->callHook('editDocumentContentAttribute', $document, $attrdef);
				if(is_array($arr)) {
					if($arr)
						$this->formField($arr[0], $arr[1]);
				} else {
					$presetbtnhtml = '';
					if($latestContent->getAttributeValue($attrdef)) {
						switch($attrdef->getType()) {
						case LetoDMS_Core_AttributeDefinition::type_string:
						case LetoDMS_Core_AttributeDefinition::type_date:
						case LetoDMS_Core_AttributeDefinition::type_int:
						case LetoDMS_Core_AttributeDefinition::type_float:
							$presetbtnhtml = $this->getInputPresetButtonHtml('attributes_version_'.$attrdef->getID(), $latestContent->getAttributeValue($attrdef), $attrdef->getValueSetSeparator());
							break;
						case LetoDMS_Core_AttributeDefinition::type_boolean:
							$presetbtnhtml = $this->gettCheckboxPresetButtonHtml('attributes_version_'.$attrdef->getID(), $latestContent->getAttributeValue($attrdef));
							break;
						}
					}
					$this->formField(htmlspecialchars($attrdef->getName()), $this->getAttributeEditField($attrdef, '', 'attributes_version')." ".$presetbtnhtml);
				}
			}
		}
		$arrs = $this->callHook('addDocumentContentAttributes', $folder);
		if(is_array($arrs)) {
			foreach($arrs as $arr) {
				$this->formField($arr[0], $arr[1]);
			}
		}

		if($workflowmode == 'advanced') {
			$mandatoryworkflows = $user->getMandatoryWorkflows();
			if($mandatoryworkflows) {
				if(count($mandatoryworkflows) == 1) {
					$this->formField(
						getMLText("workflow"),
						htmlspecialchars($mandatoryworkflows[0]->getName()).'<input type="hidden" name="workflow" value="'.$mandatoryworkflows[0]->getID().'">'
					);
				} else {
					$options = array();
					$curworkflow = $latestContent->getWorkflow();
					foreach ($mandatoryworkflows as $workflow) {
						$options[] = array($workflow->getID(), htmlspecialchars($workflow->getName()), $curworkflow && $curworkflow->getID() == $workflow->getID());
					}
					$this->formField(
						getMLText("workflow"),
						array(
							'element'=>'select',
							'id'=>'workflow',
							'name'=>'workflow',
							'class'=>'chzn-select',
							'attributes'=>array(array('data-placeholder', getMLText('select_workflow'))),
							'options'=>$options
						)
					);
				}
			} else {
				$options = array();
				$options[] = array('', '');
				$workflows=$dms->getAllWorkflows();
				foreach ($workflows as $workflow) {
					$options[] = array($workflow->getID(), htmlspecialchars($workflow->getName()));
				}
				$this->formField(
					getMLText("workflow"),
					array(
						'element'=>'select',
						'id'=>'workflow',
						'name'=>'workflow',
						'class'=>'chzn-select',
						'attributes'=>array(array('data-allow-clear', 'true'), array('data-placeholder', getMLText('select_workflow'))),
						'options'=>$options
					)
				);
			}
			$this->warningMsg(getMLText("add_doc_workflow_warning"));
		} else {
			$docAccess = $document->getReadAccessList($enableadminrevapp, $enableownerrevapp);
			if($workflowmode == 'traditional') {
				$this->contentSubHeading(getMLText("assign_reviewers"));
				$res=$user->getMandatoryReviewers();
				$options = array();
				foreach ($docAccess["users"] as $usr) {
					if (!$enableselfrevapp && $usr->getID()==$user->getID()) continue; 
					$mandatory=false;
					foreach ($res as $r) if ($r['reviewerUserID']==$usr->getID()) $mandatory=true;

					$option = array($usr->getID(), htmlspecialchars($usr->getLogin()." - ".$usr->getFullName()), null);
					if ($mandatory) $option[] = array(array('disabled', 'disabled'));
					$options[] = $option;
				}
				$tmp = array();
				foreach($reviewStatus as $r) {
					if($r['type'] == 0) {
						if($res) {
							$mandatory=false;
							foreach ($res as $rr)
								if ($rr['reviewerUserID']==$r['required']) {
									$mandatory=true;
								}
							if(!$mandatory)
								$tmp[] = $r['required'];
						} else {
							$tmp[] = $r['required'];
						}
					}
				}
				$fieldwrap = array();
				if($tmp) {
					$fieldwrap = array('', $this->getSelectPresetButtonHtml("IndReviewers", $tmp));
				}
				/* List all mandatory reviewers */
				if($res) {
					$tmp = array();
					foreach ($res as $r) {
						if($r['reviewerUserID'] > 0) {
							$u = $dms->getUser($r['reviewerUserID']);
							$tmp[] =  htmlspecialchars($u->getFullName().' ('.$u->getLogin().')');
						}
					}
					if($tmp) {
						$fieldwrap[1] .= '<div class="mandatories"><span>'.getMLText('mandatory_reviewers').':</span> '.implode(', ', $tmp)."</div>\n";
					}
				}

				$this->formField(
					getMLText("individuals"),
					array(
						'element'=>'select',
						'name'=>'indReviewers[]',
						'id'=>'IndReviewers',
						'class'=>'chzn-select',
						'attributes'=>array(array('data-placeholder', getMLText('select_ind_reviewers'))),
						'multiple'=>true,
						'options'=>$options
					),
					array('field_wrap'=>$fieldwrap)
				);

				/* Check for mandatory reviewer without access */
				foreach($res as $r) {
					if($r['reviewerUserID']) {
						$hasAccess = false;
						foreach ($docAccess["users"] as $usr) {
							if ($r['reviewerUserID']==$usr->getID())
								$hasAccess = true;
						}
						if(!$hasAccess) {
							$noAccessUser = $dms->getUser($r['reviewerUserID']);
							$this->warningMsg(getMLText("mandatory_reviewer_no_access", array('user'=>htmlspecialchars($noAccessUser->getFullName()." (".$noAccessUser->getLogin().")"))));
						}
					}
				}
				$options = array();
				foreach ($docAccess["groups"] as $grp) {
				
					$mandatory=false;
					foreach ($res as $r) if ($r['reviewerGroupID']==$grp->getID()) $mandatory=true;	

					$option = array($grp->getID(), htmlspecialchars($grp->getName()), null);
					if ($mandatory || !$grp->getUsers()) $option[] = array(array('disabled', 'disabled'));
					$options[] = $option;
				}
				$tmp = array();
				foreach($reviewStatus as $r) {
					if($r['type'] == 1) {
						if($res) {
							$mandatory=false;
							foreach ($res as $rr)
								if ($rr['reviewerGroupID']==$r['required']) {
									$mandatory=true;
								}
							if(!$mandatory)
								$tmp[] = $r['required'];
						} else {
							$tmp[] = $r['required'];
						}
					}
				}
				$fieldwrap = array();
				if($tmp) {
					$fieldwrap = array('', $this->getSelectPresetButtonHtml("GrpReviewers", $tmp));
				}
				/* List all mandatory groups of reviewers */
				if($res) {
					$tmp = array();
					foreach ($res as $r) {
						if($r['reviewerGroupID'] > 0) {
							$u = $dms->getGroup($r['reviewerGroupID']);
							$tmp[] =  htmlspecialchars($u->getName());
						}
					}
					if($tmp) {
						$fieldwrap[1] .= '<div class="mandatories"><span>'.getMLText('mandatory_reviewergroups').':</span> '.implode(', ', $tmp)."</div>\n";
					}
				}
				$this->formField(
					getMLText("groups"),
					array(
						'element'=>'select',
						'name'=>'grpReviewers[]',
						'id'=>'GrpReviewers',
						'class'=>'chzn-select',
						'attributes'=>array(array('data-placeholder', getMLText('select_grp_reviewers'))),
						'multiple'=>true,
						'options'=>$options
					),
					array('field_wrap'=>$fieldwrap)
				);

				/* Check for mandatory reviewer group without access */
				foreach($res as $r) {
					if ($r['reviewerGroupID']) {
						$hasAccess = false;
						foreach ($docAccess["groups"] as $grp) {
							if ($r['reviewerGroupID']==$grp->getID())
								$hasAccess = true;
						}
						if(!$hasAccess) {
							$noAccessGroup = $dms->getGroup($r['reviewerGroupID']);
							$this->warningMsg(getMLText("mandatory_reviewergroup_no_access", array('group'=>htmlspecialchars($noAccessGroup->getName()))));
						}
					}
				}
			}

			$this->contentSubHeading(getMLText("assign_approvers"));
			$options = array();
			$res=$user->getMandatoryApprovers();
			foreach ($docAccess["users"] as $usr) {
				if (!$enableselfrevapp && $usr->getID()==$user->getID()) continue; 

				$mandatory=false;
				foreach ($res as $r) if ($r['approverUserID']==$usr->getID()) $mandatory=true;
				
				$option = array($usr->getID(), htmlspecialchars($usr->getLogin()." - ".$usr->getFullName()), null);
				if ($mandatory) $option[] = array(array('disabled', 'disabled'));
				$options[] = $option;
			}
			$tmp = array();
			foreach($approvalStatus as $r) {
				if($r['type'] == 0) {
					if($res) {
						$mandatory=false;
						foreach ($res as $rr)
							if ($rr['approverUserID']==$r['required']) {
								$mandatory=true;
							}
						if(!$mandatory)
							$tmp[] = $r['required'];
					} else {
						$tmp[] = $r['required'];
					}
				}
			}
			$fieldwrap = array();
			if($tmp) {
				$fieldwrap = array('', $this->getSelectPresetButtonHtml("IndApprovers", $tmp));
			}
			/* List all mandatory approvers */
			if($res) {
				$tmp = array();
				foreach ($res as $r) {
					if($r['approverUserID'] > 0) {
						$u = $dms->getUser($r['approverUserID']);
						$tmp[] =  htmlspecialchars($u->getFullName().' ('.$u->getLogin().')');
					}
				}
				if($tmp) {
					$fieldwrap[1] .= '<div class="mandatories"><span>'.getMLText('mandatory_approvers').':</span> '.implode(', ', $tmp)."</div>\n";
				}
			}

			$this->formField(
				getMLText("individuals"),
				array(
					'element'=>'select',
					'name'=>'indApprovers[]',
					'id'=>'IndApprovers',
					'class'=>'chzn-select',
					'attributes'=>array(array('data-placeholder', getMLText('select_ind_approvers'))),
					'multiple'=>true,
					'options'=>$options
				),
				array('field_wrap'=>$fieldwrap)
			);

				/* Check for mandatory approvers without access */
				foreach($res as $r) {
					if($r['approverUserID']) {
						$hasAccess = false;
						foreach ($docAccess["users"] as $usr) {
							if ($r['approverUserID']==$usr->getID())
								$hasAccess = true;
						}
						if(!$hasAccess) {
							$noAccessUser = $dms->getUser($r['approverUserID']);
							$this->warningMsg(getMLText("mandatory_approver_no_access", array('user'=>htmlspecialchars($noAccessUser->getFullName()." (".$noAccessUser->getLogin().")"))));
						}
					}
				}

				$options = array();
				foreach ($docAccess["groups"] as $grp) {
				
					$mandatory=false;
					foreach ($res as $r) if ($r['approverGroupID']==$grp->getID()) $mandatory=true;	

					$option = array($grp->getID(), htmlspecialchars($grp->getName()), null);
					if ($mandatory || !$grp->getUsers()) $option[] = array(array('disabled', 'disabled'));

					$options[] = $option;
				}
				$tmp = array();
				foreach($approvalStatus as $r) {
					if($r['type'] == 1) {
						if($res) {
							$mandatory=false;
							foreach ($res as $rr)
								if ($rr['approverGroupID']==$r['required']) {
									$mandatory=true;
								}
							if(!$mandatory)
								$tmp[] = $r['required'];
						} else {
							$tmp[] = $r['required'];
						}
					}
				}
				$fieldwrap = array();
				if($tmp) {
					$fieldwrap = array('', $this->getSelectPresetButtonHtml("GrpApprovers", $tmp));
				}
				/* List all mandatory groups of approvers */
				if($res) {
					$tmp = array();
					foreach ($res as $r) {
						if($r['approverGroupID'] > 0) {
							$u = $dms->getGroup($r['approverGroupID']);
							$tmp[] =  htmlspecialchars($u->getName());
						}
					}
					if($tmp) {
						$fieldwrap[1] .= '<div class="mandatories"><span>'.getMLText('mandatory_approvergroups').':</span> '.implode(', ', $tmp)."</div>\n";
					}
				}

				$this->formField(
					getMLText("groups"),
					array(
						'element'=>'select',
						'name'=>'grpApprovers[]',
						'id'=>'GrpApprovers',
						'class'=>'chzn-select',
						'attributes'=>array(array('data-placeholder', getMLText('select_grp_approvers'))),
						'multiple'=>true,
						'options'=>$options
					),
					array('field_wrap'=>$fieldwrap)
				);

				/* Check for mandatory approver groups without access */
				foreach($res as $r) {
					if ($r['approverGroupID']) {
						$hasAccess = false;
						foreach ($docAccess["groups"] as $grp) {
							if ($r['approverGroupID']==$grp->getID())
								$hasAccess = true;
						}
						if(!$hasAccess) {
							$noAccessGroup = $dms->getGroup($r['approverGroupID']);
							$this->warningMsg(getMLText("mandatory_approvergroup_no_access", array('group'=>htmlspecialchars($noAccessGroup->getName()))));
						}
					}
				}
			$this->warningMsg(getMLText("add_doc_reviewer_approver_warning"));
		}
		$this->formSubmit(getMLText('update_document'));
?>
</form>

<?php
		$this->contentContainerEnd();
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
