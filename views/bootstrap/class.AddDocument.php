<?php
/**
 * Implementation of AddDocument view
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
 * Class which outputs the html page for AddDocument view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_AddDocument extends LetoDMS_Bootstrap_Style {

	function js() { /* {{{ */
		$dropfolderdir = $this->params['dropfolderdir'];
		$partitionsize = $this->params['partitionsize'];
		$maxuploadsize = $this->params['maxuploadsize'];
		$enablelargefileupload = $this->params['enablelargefileupload'];
		$enablemultiupload = $this->params['enablemultiupload'];
		header('Content-Type: application/javascript; charset=UTF-8');

		if($enablelargefileupload) {
			$this->printFineUploaderJs('../op/op.UploadChunks.php', $partitionsize, $maxuploadsize, $enablemultiupload);
		}
?>
$(document).ready(function() {
	$('#new-file').click(function(event) {
		tttttt = $("#userfile-upload-file").clone().appendTo("#userfile-upload-files").removeAttr("id");
		tttttt.children('div').children('input').val('');
		tttttt.children('div').children('span').children('input').val('');
	});
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
			'userfile[]': {
				alternatives: $('#dropfolderfileform1')
			},
			dropfolderfileform1: {
				 alternatives: $("#userfile") //$(".btn-file input")
			}
<?php
		}
?>
		},
		messages: {
			name: "<?php printMLText("js_no_name");?>",
			comment: "<?php printMLText("js_no_comment");?>",
			keywords: "<?php printMLText("js_no_keywords");?>"
		},
		errorPlacement: function( error, element ) {
			if ( element.is( ":file" ) ) {
				error.appendTo( element.parent().parent().parent());
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
		$this->printKeywordChooserJs("form1");
		if($dropfolderdir) {
			$this->printDropFolderChooserJs("form1");
		}
		$this->printFileChooserJs();
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$folder = $this->params['folder'];
		$enablelargefileupload = $this->params['enablelargefileupload'];
		$enablemultiupload = $this->params['enablemultiupload'];
		$enableadminrevapp = $this->params['enableadminrevapp'];
		$enableownerrevapp = $this->params['enableownerrevapp'];
		$enableselfrevapp = $this->params['enableselfrevapp'];
		$strictformcheck = $this->params['strictformcheck'];
		$dropfolderdir = $this->params['dropfolderdir'];
		$dropfolderfile = $this->params['dropfolderfile'];
		$workflowmode = $this->params['workflowmode'];
		$presetexpiration = $this->params['presetexpiration'];
		$sortusersinlist = $this->params['sortusersinlist'];
		$orderby = $this->params['orderby'];
		$folderid = $folder->getId();

		$this->htmlAddHeader('<script type="text/javascript" src="../styles/'.$this->theme.'/validate/jquery.validate.js"></script>'."\n", 'js');
		if($enablelargefileupload) {
			$this->htmlAddHeader('<script type="text/javascript" src="../styles/'.$this->theme.'/fine-uploader/jquery.fine-uploader.min.js"></script>'."\n", 'js');
			$this->htmlAddHeader($this->getFineUploaderTemplate(), 'js');
		}

		$this->htmlStartPage(getMLText("folder_title", array("foldername" => htmlspecialchars($folder->getName()))));
		$this->globalNavigation($folder);
		$this->contentStart();
		$this->pageNavigation($this->getFolderPathHTML($folder, true), "view_folder", $folder);
		
		$msg = getMLText("max_upload_size").": ".ini_get( "upload_max_filesize");
		$this->warningMsg($msg);
		$this->contentHeading(getMLText("add_document"));
		
		// Retrieve a list of all users and groups that have review / approve
		// privileges.
		$docAccess = $folder->getReadAccessList($enableadminrevapp, $enableownerrevapp);

		$txt = $this->callHook('addDocumentPreForm');
		if(is_string($txt))
			echo $txt;
		$this->contentContainerStart();
?>
		<form class="form-horizontal" action="../op/op.AddDocument.php" enctype="multipart/form-data" method="post" id="form1" name="form1">
		<?php echo createHiddenFieldWithKey('adddocument'); ?>
		<input type="hidden" name="folderid" value="<?php print $folderid; ?>">
		<input type="hidden" name="showtree" value="<?php echo showtree();?>">
<?php
		$this->contentSubHeading(getMLText("document_infos"));
		$this->formField(
			getMLText("name"),
			array(
				'element'=>'input',
				'type'=>'text',
				'id'=>'name',
				'name'=>'name',
				'required'=>true
			)
		);
		$this->formField(
			getMLText("comment"),
			array(
				'element'=>'textarea',
				'name'=>'comment',
				'rows'=>4,
				'cols'=>80,
				'required'=>$strictformcheck
			)
		);
		$this->formField(
			getMLText("keywords"),
				$this->getKeywordChooserHtml('form1')
		);
		$options = array();
		$categories = $dms->getDocumentCategories();
		foreach($categories as $category) {
			$options[] = array($category->getID(), $category->getName());
		}
		$this->formField(
			getMLText("categories"),
			array(
				'element'=>'select',
				'class'=>'chzn-select',
				'name'=>'categories[]',
				'multiple'=>true,
				'attributes'=>array(array('data-placeholder', getMLText('select_category'), array('data-no_results_text', getMLText('unknown_document_category')))),
				'options'=>$options
			)
		);
		$this->formField(getMLText("sequence"), $this->getSequenceChooser($folder->getDocuments('s')).($orderby != 's' ? "<br />".getMLText('order_by_sequence_off') : ''));
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
		if($user->isAdmin()) {
		$options = array();
		$allUsers = $dms->getAllUsers($sortusersinlist);
		foreach ($allUsers as $currUser) {
			if (!$currUser->isGuest())
				$options[] = array($currUser->getID(), htmlspecialchars($currUser->getLogin()), ($currUser->getID()==$user->getID()), array(array('data-subtitle', htmlspecialchars($currUser->getFullName()))));
		}
		$this->formField(
			getMLText("owner"),
			array(
				'element'=>'select',
				'id'=>'ownerid',
				'name'=>'ownerid',
				'class'=>'chzn-select',
				'options'=>$options
			)
		);
		}
		$attrdefs = $dms->getAllAttributeDefinitions(array(LetoDMS_Core_AttributeDefinition::objtype_document, LetoDMS_Core_AttributeDefinition::objtype_all));
		if($attrdefs) {
			foreach($attrdefs as $attrdef) {
				$arr = $this->callHook('addDocumentAttribute', null, $attrdef);
				if(is_array($arr)) {
					if($arr) {
						$this->formField($arr[0], $arr[1]);
					}
				} else {
					$this->formField(htmlspecialchars($attrdef->getName()), $this->getAttributeEditField($attrdef, ''));
				}
			}
		}
		$arrs = $this->callHook('addDocumentAttributes', $folder);
		if(is_array($arrs)) {
			foreach($arrs as $arr) {
				$this->formField($arr[0], $arr[1]);
			}
		}

		$this->contentSubHeading(getMLText("version_info"));
		$this->formField(
			getMLText("version"),
			array(
				'element'=>'input',
				'type'=>'text',
				'id'=>'reqversion',
				'name'=>'reqversion',
				'value'=>1
			)
		);
		$this->formField(
			getMLText("local_file"),
			$enablelargefileupload ? $this->getFineUploaderHtml() : $this->getFileChooserHtml('userfile[]', false).($enablemultiupload ? '<a class="" id="new-file"><?php printMLtext("add_multiple_files") ?></a>' : '')
		);
		if($dropfolderdir) {
			$this->formField(
				getMLText("dropfolder_file"),
				$this->getDropFolderChooserHtml("form1", $dropfolderfile)
			);
		}
		$this->formField(
			getMLText("comment_for_current_version"),
			array(
				'element'=>'textarea',
				'name'=>'version_comment',
				'rows'=>4,
				'cols'=>80
			)
		);
		$this->formField(
			getMLText("use_comment_of_document"),
			array(
				'element'=>'input',
				'type'=>'checkbox',
				'name'=>'use_comment',
				'value'=>1
			)
		);
		$attrdefs = $dms->getAllAttributeDefinitions(array(LetoDMS_Core_AttributeDefinition::objtype_documentcontent, LetoDMS_Core_AttributeDefinition::objtype_all));
		if($attrdefs) {
			foreach($attrdefs as $attrdef) {
				$arr = $this->callHook('addDocumentContentAttribute', null, $attrdef);
				if(is_array($arr)) {
					$this->formField($arr[0], $arr[1]);
				} else {
					$this->formField(htmlspecialchars($attrdef->getName()), $this->getAttributeEditField($attrdef, '', 'attributes_version'));
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
					foreach ($mandatoryworkflows as $workflow) {
						$options[] = array($workflow->getID(), htmlspecialchars($workflow->getName()));
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
			if($workflowmode == 'traditional') {
				$this->contentSubHeading(getMLText("assign_reviewers"));

				/* List all mandatory reviewers */
				$res=$user->getMandatoryReviewers();
				$tmp = array();
				if($res) {
					foreach ($res as $r) {
						if($r['reviewerUserID'] > 0) {
							$u = $dms->getUser($r['reviewerUserID']);
							$tmp[] =  htmlspecialchars($u->getFullName().' ('.$u->getLogin().')');
						}
					}
				}

				$options = array();
				foreach ($docAccess["users"] as $usr) {
					if (!$enableselfrevapp && $usr->getID()==$user->getID()) continue; 
					$mandatory=false;
					foreach ($res as $r) if ($r['reviewerUserID']==$usr->getID()) $mandatory=true;

					$option = array($usr->getID(), htmlspecialchars($usr->getLogin()." - ".$usr->getFullName()), null);
					if ($mandatory) $option[] = array(array('disabled', 'disabled'));
					$options[] = $option;
				}
				$this->formField(
					getMLText("individuals"),
					array(
						'element'=>'select',
						'name'=>'indReviewers[]',
						'class'=>'chzn-select',
						'attributes'=>array(array('data-placeholder', getMLText('select_ind_reviewers'))),
						'multiple'=>true,
						'options'=>$options
					),
					array('field_wrap'=>array('', ($tmp ? '<div class="mandatories"><span>'.getMLText('mandatory_reviewers').':</span> '.implode(', ', $tmp).'</div>' : '')))
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

				/* List all mandatory groups of reviewers */
				$tmp = array();
				if($res) {
					foreach ($res as $r) {
						if($r['reviewerGroupID'] > 0) {
							$u = $dms->getGroup($r['reviewerGroupID']);
							$tmp[] =  htmlspecialchars($u->getName());
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
				$this->formField(
					getMLText("groups"),
					array(
						'element'=>'select',
						'name'=>'grpReviewers[]',
						'class'=>'chzn-select',
						'attributes'=>array(array('data-placeholder', getMLText('select_grp_reviewers'))),
						'multiple'=>true,
						'options'=>$options
					),
					array('field_wrap'=>array('', ($tmp ? '<div class="mandatories"><span>'.getMLText('mandatory_reviewergroups').':</span> '.implode(', ', $tmp).'</div>' : '')))
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
			$res=$user->getMandatoryApprovers();
			/* List all mandatory approvers */
			$tmp = array();
			if($res) {
				foreach ($res as $r) {
					if($r['approverUserID'] > 0) {
						$u = $dms->getUser($r['approverUserID']);
						$tmp[] =  htmlspecialchars($u->getFullName().' ('.$u->getLogin().')');
					}
				}
			}

			$options = array();
			foreach ($docAccess["users"] as $usr) {
				if (!$enableselfrevapp && $usr->getID()==$user->getID()) continue; 

				$mandatory=false;
				foreach ($res as $r) if ($r['approverUserID']==$usr->getID()) $mandatory=true;
				
				$option = array($usr->getID(), htmlspecialchars($usr->getLogin()." - ".$usr->getFullName()), null);
				if ($mandatory) $option[] = array(array('disabled', 'disabled'));
				$options[] = $option;
			}
			$this->formField(
				getMLText("individuals"),
				array(
					'element'=>'select',
					'name'=>'indApprovers[]',
					'class'=>'chzn-select',
					'attributes'=>array(array('data-placeholder', getMLText('select_ind_approvers'))),
					'multiple'=>true,
					'options'=>$options
				),
				array('field_wrap'=>array('', ($tmp ? '<div class="mandatories"><span>'.getMLText('mandatory_approvers').':</span> '.implode(', ', $tmp).'</div>' : '')))
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

			/* List all mandatory groups of approvers */
			$tmp = array();
			if($res) {
				foreach ($res as $r) {
					if($r['approverGroupID'] > 0) {
						$u = $dms->getGroup($r['approverGroupID']);
						$tmp[] =  htmlspecialchars($u->getName());
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
			$this->formField(
				getMLText("groups"),
				array(
					'element'=>'select',
					'name'=>'grpApprovers[]',
					'class'=>'chzn-select',
					'attributes'=>array(array('data-placeholder', getMLText('select_grp_approvers'))),
					'multiple'=>true,
					'options'=>$options
				),
				array('field_wrap'=>array('', ($tmp ? '<div class="mandatories"><span>'.getMLText('mandatory_approvergroups').':</span> '.implode(', ', $tmp).'</div>' : '')))
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
		$this->contentSubHeading(getMLText("add_document_notify"));

		$options = array();
		$allUsers = $dms->getAllUsers($sortusersinlist);
		foreach ($allUsers as $userObj) {
			if (!$userObj->isGuest() && $folder->getAccessMode($userObj) >= M_READ)
				$options[] = array($userObj->getID(), htmlspecialchars($userObj->getLogin() . " - " . $userObj->getFullName()));
		}
		$this->formField(
			getMLText("individuals"),
			array(
				'element'=>'select',
				'name'=>'notification_users[]',
				'class'=>'chzn-select',
				'attributes'=>array(array('data-placeholder', getMLText('select_ind_notification'))),
				'multiple'=>true,
				'options'=>$options
			)
		);
		$options = array();
		$allGroups = $dms->getAllGroups();
		foreach ($allGroups as $groupObj) {
			if ($folder->getGroupAccessMode($groupObj) >= M_READ)
				$options[] = array($groupObj->getID(), htmlspecialchars($groupObj->getName()));
		}
		$this->formField(
			getMLText("groups"),
			array(
				'element'=>'select',
				'name'=>'notification_groups[]',
				'class'=>'chzn-select',
				'attributes'=>array(array('data-placeholder', getMLText('select_grp_notification'))),
				'multiple'=>true,
				'options'=>$options
			)
		);
		$this->formSubmit("<i class=\"icon-save\"></i> ".getMLText('add_document'));
?>
		</form>
<?php
		$this->contentContainerEnd();
		$txt = $this->callHook('addDocumentPostForm');
		if(is_string($txt))
			echo $txt;
		$this->contentEnd();
		$this->htmlEndPage();

	} /* }}} */
}
?>
