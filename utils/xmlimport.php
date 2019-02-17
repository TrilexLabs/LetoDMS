<?php
if(isset($_SERVER['LetoDMS_HOME'])) {
	require_once($_SERVER['LetoDMS_HOME']."/inc/inc.ClassSettings.php");
} else {
	require_once("../inc/inc.ClassSettings.php");
}
require("Log.php");

function usage() { /* {{{ */
	echo "Usage:\n";
	echo "  LetoDMS-xmlimport [-h] [-v] [--config <file>]\n";
	echo "\n";
	echo "Description:\n";
	echo "  This program imports an xml dump into the dms.\n";
	echo "\n";
	echo "Options:\n";
	echo "  -h, --help: print usage information and exit.\n";
	echo "  -v, --version: print version and exit.\n";
	echo "  --config <config file>: set alternative config file.\n";
	echo "  --folder <folder id>: set import folder.\n";
	echo "  --file <file>: file containing the dump.\n";
	echo "  --sections <sections>: comma seperated list of sections to read from dump.\n";
	echo "     can be: users, groups, documents, folders, keywordcategories, or\n";
	echo "     documentcategories\n";
	echo "  --contentdir <dir>: directory where all document versions are stored\n";
	echo "    which are not included in the xml file.\n";
	echo "  --default-user <user id>: use this user if user could not be found.\n";
	echo "  --export-mapping <file>: write object mapping into file\n";
	echo "  --debug: turn debug output on\n";
} /* }}} */

function dateToTimestamp($date, $format='Y-m-d H:i:s') { /* {{{ */
	$p = date_parse_from_format($format, $date);
	return mktime($p['hour'], $p['minute'], $p['second'], $p['month'], $p['day'], $p['year']);
} /* }}} */

function getRevAppLog($reviews) { /* {{{ */
	global $logger, $dms, $objmap;

	$newreviews = array();
	foreach($reviews as $i=>$review) {
		$newreview = array('type'=>$review['attributes']['type']);
		if($review['attributes']['type'] == 1) {
			if(isset($objmap['groups'][(int) $review['attributes']['required']]))
				$newreview['required'] = $dms->getGroup($objmap['groups'][(int) $review['attributes']['required']]);
			else
				$logger->warning("Group ".(int) $review['attributes']['required']." for Log cannot be mapped");
		} else {
			if(isset($objmap['users'][(int) $review['attributes']['required']]))
				$newreview['required'] = $dms->getUser($objmap['users'][(int) $review['attributes']['required']]);
			else
				$logger->warning("User ".(int) $review['attributes']['required']." for Log cannot be mapped");
		}
		if(isset($newreview['required'])) {
		$newreview['logs'] = array();
		foreach($review['logs'] as $j=>$log) {
			if(!array_key_exists($log['attributes']['user'], $objmap['users'])) {
				$logger->warning("User for review log cannot be mapped");
			} else {
				$newlog = array();
				$newlog['user'] = $dms->getUser($objmap['users'][$log['attributes']['user']]);
				$newlog['status'] = $log['attributes']['status'];
				$newlog['comment'] = $log['attributes']['comment'];
				$newlog['date'] = $log['attributes']['date'];
				if(!empty($log['data'])) {
					$filecontents = base64_decode($log['data']);
					$filename = tempnam('/tmp', 'FOO-revapp');
					file_put_contents($filename, $filecontents);
					$newlog['file'] = $filename;
				}
				$newreview['logs'][] = $newlog;
			}
		}
		$newreviews[] = $newreview;
		}
	}
	return $newreviews;
} /* }}} */

function getWorkflowLog($workflowlogs) { /* {{{ */
	global $logger, $dms, $objmap;

	$newlogs = array();
	foreach($workflowlogs as $i=>$log) {
		if(!array_key_exists($log['attributes']['user'], $objmap['users'])) {
			unset($initversion['workflowlogs'][$i]);
			$logger->warning("User for workflow log cannot be mapped");
		} else {
			$log['attributes']['user'] = $dms->getUser($objmap['users'][$log['attributes']['user']]);
			if(!array_key_exists($log['attributes']['workflow'], $objmap['workflows'])) {
				unset($initversion['workflowlogs'][$i]);
				$logger->warning("Workflow for workflow log cannot be mapped");
			} else {
				$log['attributes']['workflow'] = $dms->getWorkflow($objmap['workflows'][$log['attributes']['workflow']]);
				if(!array_key_exists($log['attributes']['transition'], $objmap['workflowtransitions'])) {
					unset($initversion['workflowlogs'][$i]);
					$logger->warning("Workflow transition for workflow log cannot be mapped");
				} else {
					$log['attributes']['transition'] = $dms->getWorkflowTransition($objmap['workflowtransitions'][$log['attributes']['transition']]);
					$newlogs[] = $log['attributes'];
				}
			}
		}
	}
	return $newlogs;
} /* }}} */

function insert_user($user) { /* {{{ */
	global $logger, $dms, $debug, $sections, $defaultUser, $objmap;

	if($debug) print_r($user);

	if ($newUser = $dms->getUserByLogin($user['attributes']['login'])) {
		$logger->warning("User '".$user['attributes']['login']."' already exists");
	} else {
		if(in_array('users', $sections)) {
			if(substr($user['attributes']['pwdexpiration'], 0, 10) == '0000-00-00')
				$user['attributes']['pwdexpiration'] = '';
			$newUser = $dms->addUser(
				$user['attributes']['login'],
				$user['attributes']['pwd'],
				$user['attributes']['fullname'],
				$user['attributes']['email'],
				$user['attributes']['language'],
				$user['attributes']['theme'],
				$user['attributes']['comment'],
				$user['attributes']['role'],
				$user['attributes']['hidden'],
				$user['attributes']['disabled'],
				$user['attributes']['pwdexpiration']);
			if(!$newUser) {
				$logger->err("Could not add user");
				$logger->debug($dms->getDB()->getErrorMsg());
				return false;
			} else {
				$logger->info("Added user '".$user['attributes']['login']."'");
				if(isset($user['image']) && $user['image']) {
					$filecontents = base64_decode($user['image']['data']);
					$filename = tempnam('/tmp', 'FOO-User-Img');
					file_put_contents($filename, $filecontents);
					$newUser->setImage($filename, $user['image']['mimetype']);
					unlink($filename);
				}
			}
		} else {
			$newUser = $defaultUser;
		}
	}
	if($newUser)
		$objmap['users'][$user['id']] = $newUser->getID();
	return $newUser;
} /* }}} */

function set_homefolders() { /* {{{ */
	global $logger, $dms, $debug, $defaultUser, $users, $objmap;

	foreach($users as $user) {
		if(isset($user['attributes']['homefolder']) && $user['attributes']['homefolder']) {
			if(array_key_exists($user['id'], $objmap['users'])) {
				$userobj = $dms->getUser($objmap['users'][$user['id']]);
				if(!array_key_exists((int) $user['attributes']['homefolder'], $objmap['folders'])) {
					$logger->warning("homefolder ".$user['attributes']['homefolder']." cannot be found");
				} else {
					$userobj->setHomeFolder($objmap['folders'][(int) $user['attributes']['homefolder']]);
				}
			}
		}
	}
} /* }}} */

function insert_group($group) { /* {{{ */
	global $logger, $dms, $debug, $objmap, $sections, $users;

	if($debug) print_r($group);

	if ($newGroup = $dms->getGroupByName($group['attributes']['name'])) {
		$logger->warning("Group already exists");
	} else {
		if(in_array('groups', $sections)) {
			$newGroup = $dms->addGroup($group['attributes']['name'], $group['attributes']['comment']);
			if($newGroup) {
				$logger->info("Added group '".$group['attributes']['name']."'");
				foreach($group['users'] as $guser) {
					/* Check if user is in array of users which has been previously filled
					 * by the users in the xml file. Alternative, we could check if the
					 * id is a key of $objmap['users'] and use the new id in that array.
					 */
					if(isset($users[$guser])) {
						$user = $users[$guser];
						if($newMember = $dms->getUserByLogin($user['attributes']['login'])) {
							if($newGroup->addUser($newMember)) {
								$logger->info("Added user '".$newMember->getLogin()."' to group '".$group['attributes']['name']."'");
							}
						} else {
							$logger->err("Could not find member of group");
							return false;
						}
					} else {
						$logger->err("Group member is not contained in xml file");
						return false;
					}
				}
			} else {
				$logger->err("Could not add group");
				return false;
			}
		} else {
			$newGroup = null;
		}
	}
	if($newGroup)
		$objmap['groups'][$group['id']] = $newGroup->getID();
	return $newGroup;
} /* }}} */

function insert_attributedefinition($attrdef) { /* {{{ */
	global $logger, $dms, $debug, $objmap, $sections;

	if($debug)
		print_r($attrdef);
	if($newAttrdef = $dms->getAttributeDefinitionByName($attrdef['attributes']['name'])) {
		$logger->warning("Attribute definition already exists");
	} else {
		if(in_array('attributedefinitions', $sections)) {
			$objtype = ($attrdef['objecttype'] == 'folder' ? LetoDMS_Core_AttributeDefinition::objtype_folder : ($attrdef['objecttype'] == 'document' ? LetoDMS_Core_AttributeDefinition::objtype_document : ($attrdef['objecttype'] == 'documentcontent' ? LetoDMS_Core_AttributeDefinition::objtype_documentcontent : 0)));
			if(!$newAttrdef = $dms->addAttributeDefinition($attrdef['attributes']['name'], $objtype, $attrdef['attributes']['type'], $attrdef['attributes']['multiple'], $attrdef['attributes']['minvalues'], $attrdef['attributes']['maxvalues'], $attrdef['attributes']['valueset'], $attrdef['attributes']['regex'])) {
				$logger->err("Could not add attribute definition");
				$logger->debug($dms->getDB()->getErrorMsg());
				return false;
			} else {
				$logger->info("Added attribute definition '".$attrdef['attributes']['name']."'");
			}
		} else {
			$newAttrdef = null;
		}
	}
	if($newAttrdef)
		$objmap['attributedefs'][$attrdef['id']] = $newAttrdef->getID();
	return $newAttrdef;
} /* }}} */

function insert_documentcategory($documentcat) { /* {{{ */
	global $logger, $dms, $debug, $objmap, $sections;

	if($debug) print_r($documentcat);

	if($newCategory = $dms->getDocumentCategoryByName($documentcat['attributes']['name'])) {
		$logger->warning("Document category already exists");
	} else {
		if(in_array('documentcategories', $sections)) {
			if(!$newCategory = $dms->addDocumentCategory($documentcat['attributes']['name'])) {
				$logger->err("Error: could not add document category");
				$logger->debug($dms->getDB()->getErrorMsg());
				return false;
			} else {
				$logger->info("Added document category '".$documentcat['attributes']['name']."'");
			}
		} else {
			$newCategory = null;
		}
	}

	if($newCategory)
		$objmap['documentcategories'][$documentcat['id']] = $newCategory->getID();
	return $newCategory;
} /* }}} */

function insert_keywordcategory($keywordcat) { /* {{{ */
	global $logger, $dms, $debug, $objmap, $sections;

	if($debug) print_r($keywordcat);

	if(!array_key_exists((int) $keywordcat['attributes']['owner'], $objmap['users'])) {
		$logger->err("Owner of keyword category cannot be found");
		return false;
	}
	$owner = $objmap['users'][(int) $keywordcat['attributes']['owner']];

	if($newCategory = $dms->getKeywordCategoryByName($keywordcat['attributes']['name'], $owner)) {
		$logger->warning("Keyword category already exists");
	} else {
		if(in_array('keywordcategories', $sections)) {
			if(!$newCategory = $dms->addKeywordCategory($owner, $keywordcat['attributes']['name'])) {
				$logger->err("Could not add keyword category");
				$logger->debug($dms->getDB()->getErrorMsg());
				return false;
			} else {
				$logger->info("Added keyword category '".$keywordcat['attributes']['name']."'");
			}
			foreach($keywordcat['keywords'] as $keyword) {
				if(!$newCategory->addKeywordList($keyword['attributes']['name'])) {
					$logger->err("Could not add keyword to keyword category");
					$logger->debug($dms->getDB()->getErrorMsg());
					return false;
				}
			}
		} else {
			$newCategory = null;
		}
	}

	if($newCategory)
		$objmap['keywordcategories'][$keywordcat['id']] = $newCategory->getID();
	return $newCategory;
} /* }}} */

function insert_workflow($workflow) { /* {{{ */
	global $logger, $dms, $debug, $objmap, $sections;

	if($debug)
		print_r($workflow);
	if($newWorkflow = $dms->getWorkflowByName($workflow['attributes']['name'])) {
		$logger->warning("Workflow already exists");
	} else {
		if(in_array('workflows', $sections)) {
			if(!$initstate = $dms->getWorkflowState($objmap['workflowstates'][(int)$workflow['attributes']['initstate']])) {
				$logger->err("Could not add workflow because initial state is missing");
				return false;
			}
			if(!$newWorkflow = $dms->addWorkflow($workflow['attributes']['name'], $initstate)) {
				$logger->err("Could not add workflow");
				$logger->debug($dms->getDB()->getErrorMsg());
				return false;
			} else {
				$logger->info("Added workflow '".$workflow['attributes']['name']."'");
			}
			if($workflow['transitions']) {
				foreach($workflow['transitions'] as $transition) {
					if(!$state = $dms->getWorkflowState($objmap['workflowstates'][(int) $transition['attributes']['startstate']])) {
						$logger->err("Could not add workflow because start state of transition is missing");
						$logger->debug($dms->getDB()->getErrorMsg());
						return false;
					}
					if(!$nextstate = $dms->getWorkflowState($objmap['workflowstates'][(int) $transition['attributes']['nextstate']])) {
						$logger->err("Could not add workflow because end state of transition is missing");
						$logger->debug($dms->getDB()->getErrorMsg());
						return false;
					}
					if(!$action = $dms->getWorkflowAction($objmap['workflowactions'][(int) $transition['attributes']['action']])) {
						$logger->err("Could not add workflow because end state of transition is missing");
						$logger->debug($dms->getDB()->getErrorMsg());
						return false;
					}
					$tusers = array();
					if($transition['users']) {
						foreach($transition['users'] as $tuserid) {
							if(!$tusers[] = $dms->getUser($objmap['users'][(int) $tuserid])) {
								$logger->err("Could not add workflow because user of transition is missing");
								$logger->debug($dms->getDB()->getErrorMsg());
								return false;
							}
						}
					}
					$tgroups = array();
					if($transition['groups']) {
						foreach($transition['groups'] as $tgroupid) {
							if(!$tgroups[] = $dms->getGroup($objmap['groups'][(int) $tgroupid])) {
								$logger->err("Could not add workflow because group of transition is missing");
								return false;
							}
						}
					}
					if(!($newWorkflowTransition = $newWorkflow->addTransition($state, $action, $nextstate, $tusers, $tgroups))) {
						$logger->err("Could not add workflow because transition could not be added");
						return false;
					}
					if($newWorkflowTransition)
						$objmap['workflowtransitions'][$transition['id']] = $newWorkflowTransition->getID();
				}
			}
		} else {
			$newWorkflow = null;
		}
	}
	if($newWorkflow)
		$objmap['workflows'][$workflow['id']] = $newWorkflow->getID();
	return $newWorkflow;
} /* }}} */

function insert_workflowstate($workflowstate) { /* {{{ */
	global $logger, $dms, $debug, $objmap, $sections;

	if($debug)
		print_r($workflowstate);
	if($newWorkflowstate = $dms->getWorkflowStateByName($workflowstate['attributes']['name'])) {
		$logger->warning("Workflow state already exists");
	} else {
		if(in_array('workflows', $sections)) {
			if(!$newWorkflowstate = $dms->addWorkflowState($workflowstate['attributes']['name'], isset($workflowstate['attributes']['documentstate']) ? $workflowstate['attributes']['documentstate'] : 0)) {
				$logger->err("Could not add workflow state");
				return false;
			} else {
				$logger->info("Added workflow state '".$workflowstate['attributes']['name']."'");
			}
		} else {
			$newWorkflowstate = null;
		}
	}
	if($newWorkflowstate)
		$objmap['workflowstates'][$workflowstate['id']] = $newWorkflowstate->getID();
	return $newWorkflowstate;
} /* }}} */

function insert_workflowaction($workflowaction) { /* {{{ */
	global $logger, $dms, $debug, $objmap, $sections;

	if($debug)
		print_r($workflowaction);
	if($newWorkflowaction = $dms->getWorkflowActionByName($workflowaction['attributes']['name'])) {
		$logger->warning("Workflow action already exists");
	} else {
		if(in_array('workflows', $sections)) {
			if(!$newWorkflowaction = $dms->addWorkflowAction($workflowaction['attributes']['name'])) {
				$logger->err("Could not add workflow action");
				return false;
			} else {
				$logger->info("Added workflow action '".$workflowaction['attributes']['name']."'");
			}
		} else {
			$newWorkflowaction = null;
		}
	}
	if($newWorkflowaction)
		$objmap['workflowactions'][$workflowaction['id']] = $newWorkflowaction->getID();
	return $newWorkflowaction;
} /* }}} */

function insert_document($document) { /* {{{ */
	global $logger, $dms, $debug, $defaultUser, $objmap, $sections, $rootfolder, $contentdir;

	if($debug)
	 	print_r($document);

	if(!array_key_exists((int) $document['attributes']['owner'], $objmap['users'])) {
		$logger->warning("Owner of document cannot be mapped using default user");
		$owner = $defaultUser;
	} else {
		$owner = $dms->getUser($objmap['users'][(int) $document['attributes']['owner']]);
	}

	$attributes = array();
	if(isset($document['user_attributes'])) {
		foreach($document['user_attributes'] as $orgid=>$value) {
			if(array_key_exists((int) $orgid, $objmap['attributedefs'])) {
				$attributes[$objmap['attributedefs'][$orgid]] = $value;
			} else {
				$logger->warning("User attribute ".$orgid." cannot be mapped");
			}
		}
	}
	$categories = array();
	if(isset($document['categories'])) {
		foreach($document['categories'] as $catid) {
			if(array_key_exists((int) $catid, $objmap['documentcategories'])) {
				$categories[$objmap['documentcategories'][$catid]] = $dms->getDocumentCategory($objmap['documentcategories'][$catid]);
			} else {
				$logger->warning("Category ".$catid." cannot be mapped");
			}
		}
	}

	if(isset($document['folder']) && $document['folder']) {
		if(array_key_exists($document['folder'], $objmap['folders'])) {
			$folder = $dms->getFolder($objmap['folders'][$document['folder']]);
		} else {
			$logger->err("Folder ".$document['folder']." cannot be mapped");
			return false;
		}
	} else
		$folder = $rootfolder;

	if(in_array('documents', $sections)) {
		$initversion = array_shift($document['versions']);
		if(!$initversion) {
			$logger->err("Document '".$document['attributes']['name']."' missing initial version");
			return false;
		}
		/* Rewriting the review/approval log will set reviewers/approvers */
		$reviews = array('i'=>array(), 'g'=>array());
		$approvals = array('i'=>array(), 'g'=>array());
		$workflow = null;
		$workflowstate = null;
		if(isset($initversion['workflow']) && $initversion['workflow']) {
			if(array_key_exists((int) $initversion['workflow']['id'], $objmap['workflows'])) {
				$workflow = $dms->getWorkflow($objmap['workflows'][(int) $initversion['workflow']['id']]);
				if(!$workflow) {
					$logger->warning("Workflow ".$initversion['workflow']['id']." cannot be mapped");
				}
			} else {
				$logger->warning("Workflow ".$initversion['workflow']['id']." cannot be mapped");
			}
			if(array_key_exists((int) $initversion['workflow']['state'], $objmap['workflowstates'])) {
				$workflowstate = $dms->getWorkflowState($objmap['workflowstates'][(int) $initversion['workflow']['state']]);
				if(!$workflowstate) {
					$logger->warning("Workflowstate ".$initversion['workflow']['state']." cannot be mapped");
				}
			} else {
				$logger->warning("Workflowstate ".$initversion['workflow']['state']." cannot be mapped");
			}
		}

		$version_attributes = array();
		if(isset($initversion['user_attributes'])) {
			foreach($initversion['user_attributes'] as $orgid=>$value) {
				if(array_key_exists((int) $orgid, $objmap['attributedefs'])) {
					$version_attributes[$objmap['attributedefs'][$orgid]] = $value;
				} else {
					$logger->warning("User attribute ".$orgid." cannot be mapped");
				}
			}
		}
		if(!empty($initversion['fileref'])) {
			$filename = tempnam('/tmp', 'FOO');
			copy($contentdir.$initversion['fileref'], $filename);
		} else {
			if(!isset($initversion['data']))
				echo $document['attributes']['name']."\n";
			$filecontents = base64_decode($initversion['data']);
			if(strlen($filecontents) != $initversion['data_length']) {
				$logger->warning("File length (".strlen($filecontents).") doesn't match expected length (".$initversion['data_length'].").");
			}
			$filename = tempnam('/tmp', 'FOO');
			file_put_contents($filename, $filecontents);
		}
		if(!$result = $folder->addDocument(
			$document['attributes']['name'],
			$document['attributes']['comment'],
			isset($document['attributes']['expires']) ? dateToTimestamp($document['attributes']['expires']) : 0,
			$owner,
			isset($document['attributes']['keywords']) ? $document['attributes']['keywords'] : '',
			$categories,
			$filename,
			$initversion['attributes']['orgfilename'],
			$initversion['attributes']['filetype'],
			$initversion['attributes']['mimetype'],
			$document['attributes']['sequence'],
			$reviews, //reviewers
			$approvals, //approvers
			$initversion['version'],
			isset($initversion['attributes']['comment']) ? $initversion['attributes']['comment'] : '',
			$attributes,
			$version_attributes,
			$workflow
			)
		) {
			unlink($filename);
			$logger->err("Could not add document '".$document['attributes']['name']."'");
			return false;
		}

		/* The document and its initial version was added */
		$logger->info("Added document '".$document['attributes']['name']."'");
		$newDocument = $result[0];
		unlink($filename);

		if(isset($document['attributes']['lockedby'])) {
			if(!array_key_exists($document['attributes']['lockedby'], $objmap['users'])) {
				$logger->warning("User for document lock cannot be mapped");
			} else {
				if($lockuser = $dms->getUser($objmap['users'][$document['attributes']['lockedby']])) {
					$newDocument->setLocked($lockuser);
				}
			}
		}

		$newVersion = $result[1]->getContent();
		$newVersion->setDate(dateToTimestamp($initversion['attributes']['date']));
		if($workflowstate)
			$newVersion->setWorkflowState($workflowstate);
		$newlogs = array();
		foreach($initversion['statuslogs'] as $i=>$log) {
			if(!array_key_exists($log['attributes']['user'], $objmap['users'])) {
				unset($initversion['statuslogs'][$i]);
				$logger->warning("User for status log cannot be mapped");
			} else {
				$log['attributes']['user'] = $dms->getUser($objmap['users'][$log['attributes']['user']]);
				$newlogs[] = $log['attributes'];
			}
		}
		$newVersion->rewriteStatusLog($newlogs);

		/* Set reviewers and review log */
		if($initversion['reviews']) {
//			print_r($initversion['reviews']);
			$newreviews = getRevAppLog($initversion['reviews']);
			$newVersion->rewriteReviewLog($newreviews);
		}
		if($initversion['approvals']) {
			$newapprovals = getRevAppLog($initversion['approvals']);
			$newVersion->rewriteApprovalLog($newapprovals);
		}

		if($initversion['workflowlogs']) {
			$newworkflowlogs = getWorkflowLog($initversion['workflowlogs']);
			if(!$newVersion->rewriteWorkflowLog($newworkflowlogs)) {
				$logger->err("Could not rewrite workflow log of version '".$newVersion->getVersion()."' of document '".$newDocument->getName()."'");
				$logger->debug($dms->getDB()->getErrorMsg());
				return false;
			}
		}

		$newDocument->setDate(dateToTimestamp($document['attributes']['date']));
		$newDocument->setDefaultAccess($document['attributes']['defaultaccess']);
		$newDocument->setInheritAccess($document['attributes']['inheritaccess']);
		foreach($document['versions'] as $version) {
			if(!array_key_exists((int) $version['attributes']['owner'], $objmap['users'])) {
				$logger->err("Owner of document cannot be mapped");
				return false;
			}
			$owner = $dms->getUser($objmap['users'][(int) $version['attributes']['owner']]);

			/* Rewriting the review/approval log will set reviewers/approvers */
			$reviews = array('i'=>array(), 'g'=>array());
			$approvals = array('i'=>array(), 'g'=>array());
			$workflow = null;
			$workflowstate = null;
			if(isset($version['workflow']) && $version['workflow']) {
				if(array_key_exists((int) $version['workflow']['id'], $objmap['workflows'])) {
					$workflow = $dms->getWorkflow($objmap['workflows'][(int) $version['workflow']['id']]);
					if(!$workflow) {
						$logger->warning("Workflow ".$version['workflow']['id']." cannot be mapped");
					}
				} else {
					$logger->warning("Workflow ".$version['workflow']['id']." cannot be mapped");
				}
				if(array_key_exists((int) $version['workflow']['state'], $objmap['workflowstates'])) {
					$workflowstate = $dms->getWorkflowState($objmap['workflowstates'][(int) $version['workflow']['state']]);
					if(!$workflowstate) {
						$logger->warning("Workflowstate ".$version['workflow']['state']." cannot be mapped");
					}
				} else {
					$logger->warning("Workflowstate ".$version['workflow']['state']." cannot be mapped");
				}
			}

			$version_attributes = array();
			if(isset($version['user_attributes'])) {
				foreach($version['user_attributes'] as $orgid=>$value) {
					if(array_key_exists((int) $orgid, $objmap['attributedefs'])) {
						$version_attributes[$objmap['attributedefs'][$orgid]] = $value;
					} else {
						$logger->warning("User attribute ".$orgid." cannot be mapped");
					}
				}
			}
			if(!empty($version['fileref'])) {
				$filename = tempnam('/tmp', 'FOO');
				copy($contentdir.$version['fileref'], $filename);
			} else {
				$filecontents = base64_decode($version['data']);
				if(strlen($filecontents) != $version['data_length']) {
					$logger->warning("File length (".strlen($filecontents).") doesn't match expected length (".$version['data_length'].").");
				}
				$filename = tempnam('/tmp', 'FOO');
				file_put_contents($filename, $filecontents);
			}
			if(!($result = $newDocument->addContent(
				$version['attributes']['comment'],
				$owner,
				$filename,
				$version['attributes']['orgfilename'],
				$version['attributes']['filetype'],
				$version['attributes']['mimetype'],
				$reviews, //reviewers
				$approvals, //approvers
				$version['version'],	
				$version_attributes,
				$workflow
			))) {
				unlink($filename);
				$logger->err("Could not add version '".$version['version']."' of document '".$document['attributes']['name']."'");
				$logger->debug($dms->getDB()->getErrorMsg());
				return false;
			}

			$logger->info("Added version '".$version['version']."' of document '".$document['attributes']['name']."'");
			$newVersion = $result->getContent();
			unlink($filename);
			if($workflowstate)
				$newVersion->setWorkflowState($workflowstate);
			$newVersion->setDate(dateToTimestamp($version['attributes']['date']));
			$newlogs = array();
			foreach($version['statuslogs'] as $i=>$log) {
				if(!array_key_exists($log['attributes']['user'], $objmap['users'])) {
					unset($version['statuslogs'][$i]);
					$logger->warning("User for status log cannot be mapped");
				} else {
					$log['attributes']['user'] = $dms->getUser($objmap['users'][$log['attributes']['user']]);
					$newlogs[] = $log['attributes'];
				}
			}
			$newVersion->rewriteStatusLog($newlogs);

			if($version['reviews']) {
				$newreviews = getRevAppLog($version['reviews']);
				$newVersion->rewriteReviewLog($newreviews);
			}
			if($version['approvals']) {
				$newapprovals = getRevAppLog($version['approvals']);
				$newVersion->rewriteApprovalLog($newapprovals);
			}

			if($version['workflowlogs']) {
				$newworkflowlogs = getWorkflowLog($version['workflowlogs']);
				if(!$newVersion->rewriteWorkflowLog($newworkflowlogs)) {
					$logger->err("Could not rewrite workflow log of version '".$newVersion->getVersion()."' of document '".$newDocument->getName()."'");
					$logger->debug($dms->getDB()->getErrorMsg());
					return false;
				}
			}
		}	

		if(isset($document['notifications']['users']) && $document['notifications']['users']) {
			foreach($document['notifications']['users'] as $userid) {
				if(!array_key_exists($userid, $objmap['users'])) {
					$logger->warning("User for notification cannot be mapped");
				} else {
					$newDocument->addNotify($objmap['users'][$userid], 1);
				}
			}
		}
		if(isset($document['notifications']['groups']) && $document['notifications']['groups']) {
			foreach($document['notifications']['groups'] as $groupid) {
				if(!array_key_exists($groupid, $objmap['groups'])) {
					$logger->warning("User for notification cannot be mapped");
				} else {
					$newDocument->addNotify($objmap['groups'][$groupid], 0);
				}
			}
		}
		if(isset($document['acls']) && $document['acls']) {
			foreach($document['acls'] as $acl) {
				if($acl['type'] == 'user') {
					if(!array_key_exists($acl['user'], $objmap['users'])) {
						$logger->warning("User for notification cannot be mapped");
					} else {
						$newDocument->addAccess($acl['mode'], $objmap['users'][$acl['user']], 1);
					}
				} elseif($acl['type'] == 'group') {
					if(!array_key_exists($acl['group'], $objmap['groups'])) {
						$logger->warning("Group for notification cannot be mapped");
					} else {
						$newDocument->addAccess($acl['mode'], $objmap['groups'][$acl['group']], 0);
					}
				}
			}
		}
		if(isset($document['files']) && $document['files']) {
			foreach($document['files'] as $file) {
				if(!array_key_exists($file['attributes']['owner'], $objmap['users'])) {
					$logger->warning("User for file cannot be mapped");
					$owner = $defaultUser;
				} else {
					$owner = $dms->getUser($objmap['users'][$file['attributes']['owner']]);
				}
				if(!empty($file['fileref'])) {
					$filename = tempnam('/tmp', 'FOO');
					copy($contentdir.$file['fileref'], $filename);
				} else {
					$filecontents = base64_decode($file['data']);
					if(strlen($filecontents) != $file['data_length']) {
						$logger->warning("File length (".strlen($filecontents).") doesn't match expected length (".$file['data_length'].").");
					}
					$filename = tempnam('/tmp', 'FOO');
					file_put_contents($filename, $filecontents);
				}
				$newfile = $newDocument->addDocumentFile(
					$file['attributes']['name'],
					$file['attributes']['comment'],
					$owner,
					$filename,
					$file['attributes']['orgfilename'],
					$file['attributes']['filetype'],
					$file['attributes']['mimetype'],
					$file['attributes']['version'],
					$file['attributes']['public']
				);
				$newfile->setDate(dateToTimestamp($file['attributes']['date']));
				unlink($filename);
			}
		}
	} else {
		$newDocument = null;
	}

	if($newDocument)
		$objmap['documents'][$document['id']] = $newDocument->getID();
	return $newDocument;
} /* }}} */

function insert_folder($folder) { /* {{{ */
	global $logger, $dms, $debug, $objmap, $defaultUser, $sections, $rootfolder;

	if($debug) print_r($folder);

	if(in_array('folders', $sections)) {
		if(!array_key_exists($folder['attributes']['owner'], $objmap['users'])) {
			$logger->warning("Owner of folder cannot be mapped using default user");
			$owner = $defaultuser;
		} else {
			$owner = $dms->getUser($objmap['users'][(int) $folder['attributes']['owner']]);
		}

		$attributes = array();
		if(isset($folder['user_attributes'])) {
			foreach($folder['user_attributes'] as $orgid=>$value) {
				if(array_key_exists((int) $orgid, $objmap['attributedefs'])) {
					$attributes[$objmap['attributedefs'][$orgid]] = $value;
				} else {
					$logger->warning("User attribute ".$orgid." cannot be mapped");
				}
			}
		}

		if(isset($folder['folder']) && $folder['folder']) {
			if(array_key_exists($folder['folder'], $objmap['folders'])) {
				$parent = $dms->getFolder($objmap['folders'][$folder['folder']]);
			} else {
				$logger->err("Folder ".$folder['folder']." cannot be mapped");
				exit;
			}
		} else
			$parent = $rootfolder;

		if(!$newFolder = $parent->addSubFolder($folder['attributes']['name'], $folder['attributes']['comment'], $owner, $folder['attributes']['sequence'], $attributes)) {
			$logger->err("Could not add folder");
			$logger->debug($dms->getDB()->getErrorMsg());
			return false;
		} else {
			$logger->info("Added folder '".$folder['attributes']['name']."'");
		}

		$newFolder->setDate(dateToTimestamp($folder['attributes']['date']));
		$newFolder->setDefaultAccess($folder['attributes']['defaultaccess']);
		$newFolder->setInheritAccess($folder['attributes']['inheritaccess']);
		if(isset($folder['notifications']['users']) && $folder['notifications']['users']) {
			foreach($folder['notifications']['users'] as $userid) {
				if(!array_key_exists($userid, $objmap['users'])) {
					$logger->warning("User for notification cannot be mapped");
				} else {
					$newFolder->addNotify($objmap['users'][$userid], 1);
				}
			}
		}
		if(isset($folder['notifications']['groups']) && $folder['notifications']['groups']) {
			foreach($folder['notifications']['groups'] as $groupid) {
				if(!array_key_exists($groupid, $objmap['groups'])) {
					$logger->warning("User for notification cannot be mapped");
				} else {
					$newFolder->addNotify($objmap['groups'][$groupid], 0);
				}
			}
		}
		if(isset($folder['acls']) && $folder['acls']) {
			foreach($folder['acls'] as $acl) {
				if($acl['type'] == 'user') {
					if(!array_key_exists($acl['user'], $objmap['users'])) {
						$logger->warning("User for notification cannot be mapped");
					} else {
						$newFolder->addAccess($acl['mode'], $objmap['users'][$acl['user']], 1);
					}
				} elseif($acl['type'] == 'group') {
					if(!array_key_exists($acl['group'], $objmap['groups'])) {
						$logger->warning("Group for notification cannot be mapped");
					} else {
						$newFolder->addAccess($acl['mode'], $objmap['groups'][$acl['group']], 0);
					}
				}
			}
		}
	} else {
		$newFolder = null;
	}

	if($newFolder)
		$objmap['folders'][$folder['id']] = $newFolder->getID();
	return $newFolder;
} /* }}} */

function resolve_links() { /* {{{ */
	global $logger, $dms, $debug, $defaultUser, $links, $objmap;

	if(!$links)
		return;

	if($debug)
		print_r($links);
	foreach($links as $documentid=>$doclinks) {
		if(array_key_exists($documentid, $objmap['documents'])) {
			if($doc = $dms->getDocument($objmap['documents'][$documentid])) {
				foreach($doclinks as $doclink) {
							if(array_key_exists($doclink['attributes']['target'], $objmap['documents'])) {
								if($target = $dms->getDocument($objmap['documents'][$doclink['attributes']['target']])) {
									if(!array_key_exists($doclink['attributes']['owner'], $objmap['users'])) {
										$logger->warning("User for link cannot be mapped using default user");
										$owner = $defaultUser;
									} else {
										$owner = $dms->getUser($objmap['users'][$doclink['attributes']['owner']]);
									}
									if(!$doc->addDocumentLink($target->getID(), $owner->getID(), $doclink['attributes']['public'])) {
										$logger->err("Could not add document link from ".$doc->getID()." to ".$target->getID());
										$logger->debug($dms->getDB()->getErrorMsg());
									}
								} else {
									$logger->warning("Target document not found in database");
								}
							} else {
								$logger->warning("Target document not found in object mapping");
							}
				}
			} else {
				$logger->warning("Document not found in database");
			}
		} else {
			$logger->warning("Document not found in object mapping");
		}
	}
} /* }}} */

function set_mandatory() { /* {{{ */
	global $logger, $dms, $users, $objmap;

	if(!$users)	
		return;

	foreach($users as $user) {
		if ($newUser = $dms->getUserByLogin($user['attributes']['login'])) {
			if($user['individual']['reviewers']) {
				foreach($user['individual']['reviewers'] as $u) {
					if($uobj = $dms->getUser($objmap['users'][$u])) {
						$newUser->setMandatoryReviewer($uobj->getID(), false);
					}
				}
			}
			if($user['individual']['approvers']) {
				foreach($user['individual']['approvers'] as $u) {
					if($uobj = $dms->getUser($objmap['users'][$u])) {
						$newUser->setMandatoryApprover($uobj->getID(), false);
					}
				}
			}
			if($user['group']['reviewers']) {
				foreach($user['group']['reviewers'] as $u) {
					if($uobj = $dms->getGroup($objmap['groups'][$u])) {
						$newUser->setMandatoryReviewer($uobj->getID(), true);
					}
				}
			}
			if($user['group']['approvers']) {
				foreach($user['group']['approvers'] as $u) {
					if($uobj = $dms->getGroup($objmap['groups'][$u])) {
						$newUser->setMandatoryApprover($uobj->getID(), true);
					}
				}
			}
		}
	}
} /* }}} */

function startElement($parser, $name, $attrs) { /* {{{ */
	global $logger, $dms, $noversioncheck, $elementstack, $objmap, $cur_user, $cur_group, $cur_folder, $cur_document, $cur_version, $cur_statuslog, $cur_workflowlog, $cur_approval, $cur_approvallog, $cur_review, $cur_reviewlog, $cur_attrdef, $cur_documentcat, $cur_keyword, $cur_keywordcat, $cur_file, $cur_link, $cur_workflow, $cur_workflowtransition, $cur_workflowaction, $cur_workflowstate, $cur_transition;

	$parent = end($elementstack);
	array_push($elementstack, array('name'=>$name, 'attributes'=>$attrs));
	switch($name) {
		case "DMS":
			if(!$noversioncheck) {
				$xdbversion = explode('.', $attrs['DBVERSION']);
				$dbversion = $dms->getDBVersion();
				if(($xdbversion[0] != $dbversion['major']) || ($xdbversion[1] != $dbversion['minor'])) {
					$logger->crit("Database version (".implode('.', array($dbversion['major'], $dbversion['minor'], $dbversion['subminor'])).") doesn't match version in input file (".implode('.', $xdbversion).").");
					exit(1);
				}
			}
			break;
		case "USER":
			/* users can be the users data, the member of a group, a mandatory
			 * reviewer or approver, a workflow transition
			 */
			$first = $elementstack[1];
			$second = $elementstack[2];
			if($first['name'] == 'USERS') {
				if($parent['name'] == 'MANDATORY_REVIEWERS') {
					$cur_user['individual']['reviewers'][] = (int) $attrs['ID'];
				} elseif($parent['name'] == 'MANDATORY_APPROVERS') {
					$cur_user['individual']['approvers'][] = (int) $attrs['ID'];
				} else {
					$cur_user = array();
					$cur_user['id'] = (int) $attrs['ID'];
					$cur_user['attributes'] = array();
					$cur_user['individual']['reviewers'] = array();
					$cur_user['individual']['approvers'] = array();
					$cur_user['group']['reviewers'] = array();
					$cur_user['group']['approvers'] = array();
					$cur_user['workflows'] = array();
				}
			} elseif($first['name'] == 'GROUPS') {
				$cur_group['users'][] = (int) $attrs['USER'];
			} elseif($parent['name'] == 'NOTIFICATIONS') {
				if($first['name'] == 'FOLDER') {
					$cur_folder['notifications']['users'][] = (int) $attrs['ID'];
				} elseif($first['name'] == 'DOCUMENT') {
					$cur_document['notifications']['users'][] = (int) $attrs['ID'];
				}
			} elseif($second['name'] == 'WORKFLOW') {
				$cur_transition['users'][] = (int) $attrs['ID'];
			}
			break;
		case "GROUP":
			$first = $elementstack[1];
			$second = $elementstack[2];
			if($first['name'] == 'GROUPS') {
				$cur_group = array();
				$cur_group['id'] = (int) $attrs['ID'];
				$cur_group['attributes'] = array();
				$cur_group['users'] = array();
			} elseif($first['name'] == 'USERS') {
				if($parent['name'] == 'MANDATORY_REVIEWERS') {
					$cur_user['group']['reviewers'][] = (int) $attrs['ID'];
				} elseif($parent['name'] == 'MANDATORY_APPROVERS') {
					$cur_user['group']['approvers'][] = (int) $attrs['ID'];
				}
			} elseif($parent['name'] == 'NOTIFICATIONS') {
				if($first['name'] == 'FOLDER') {
					$cur_folder['notifications']['groups'][] = (int) $attrs['ID'];
				} elseif($first['name'] == 'DOCUMENT') {
					$cur_document['notifications']['groups'][] = (int) $attrs['ID'];
				}
			} elseif($second['name'] == 'WORKFLOW') {
				$cur_transition['groups'][] = (int) $attrs['ID'];
			}
			break;
		case "DOCUMENT":
			$cur_document = array();
			$cur_document['id'] = (int) $attrs['ID'];

			if(isset($attrs['FOLDER']))
				$cur_document['folder'] = (int) $attrs['FOLDER'];
			if(isset($attrs['LOCKED']) && $attrs['LOCKED'] == 'true')
				$cur_document['locked'] = true;
			$cur_document['attributes'] = array();
			$cur_document['versions'] = array();
			break;
		case "FOLDER":
			$cur_folder = array();
			$cur_folder['id'] = (int) $attrs['ID'];
			if(isset($attrs['PARENT']))
				$cur_folder['folder'] = (int) $attrs['PARENT'];
			$cur_folder['attributes'] = array();
			break;
		case "VERSION":
			$cur_version = array();
			$cur_version['version'] = (int) $attrs['VERSION'];
			$cur_version['attributes'] = array();
			$cur_version['approvals'] = array();
			$cur_version['reviews'] = array();
			$cur_version['statuslogs'] = array();
			$cur_version['workflowlogs'] = array();
			break;
		case "STATUSLOG":
			$cur_statuslog = array();
			$cur_statuslog['attributes'] = array();
			break;
		case "WORKFLOWLOGS":
			$cur_version['workflowlogs'] = array();
			break;
		case "WORKFLOWLOG":
			$cur_workflowlog = array();
			$cur_workflowlog['attributes'] = array();
			break;
		case "APPROVAL":
			$cur_approval = array();
			$cur_approval['attributes'] = array();
			$cur_approval['logs'] = array();
			break;
		case "APPROVALLOG":
			$cur_approvallog = array();
			$cur_approvallog['attributes'] = array();
			break;
		case "REVIEW":
			$cur_review = array();
			$cur_review['attributes'] = array();
			$cur_review['logs'] = array();
			break;
		case "REVIEWLOG":
			$cur_reviewlog = array();
			$cur_reviewlog['attributes'] = array();
			break;
		case 'ATTRIBUTEDEFINITION':
			$cur_attrdef = array();
			$cur_attrdef['id'] = (int) $attrs['ID'];
			$cur_attrdef['attributes'] = array();
			$cur_attrdef['objecttype'] = $attrs['OBJTYPE'];
			break;
		case "ATTR":
			if($parent['name'] == 'DOCUMENT') {
				if(isset($attrs['TYPE']) && $attrs['TYPE'] == 'user') {
					$cur_document['user_attributes'][$attrs['ATTRDEF']] = '';
				} else {
					$cur_document['attributes'][$attrs['NAME']] = '';
				}
			} elseif($parent['name'] == 'VERSION') {
				if(isset($attrs['TYPE']) && $attrs['TYPE'] == 'user') {
					$cur_version['user_attributes'][$attrs['ATTRDEF']] = '';
				} else {
					$cur_version['attributes'][$attrs['NAME']] = '';
				}
			} elseif($parent['name'] == 'STATUSLOG') {
				$cur_statuslog['attributes'][$attrs['NAME']] = '';
			} elseif($parent['name'] == 'WORKFLOWLOG') {
				$cur_workflowlog['attributes'][$attrs['NAME']] = '';
			} elseif($parent['name'] == 'APPROVAL') {
				$cur_approval['attributes'][$attrs['NAME']] = '';
			} elseif($parent['name'] == 'APPROVALLOG') {
				$cur_approvallog['attributes'][$attrs['NAME']] = '';
			} elseif($parent['name'] == 'REVIEW') {
				$cur_review['attributes'][$attrs['NAME']] = '';
			} elseif($parent['name'] == 'REVIEWLOG') {
				$cur_reviewlog['attributes'][$attrs['NAME']] = '';
			} elseif($parent['name'] == 'FOLDER') {
				if(isset($attrs['TYPE']) && $attrs['TYPE'] == 'user') {
					$cur_folder['user_attributes'][$attrs['ATTRDEF']] = '';
				} else {
					$cur_folder['attributes'][$attrs['NAME']] = '';
				}
			} elseif($parent['name'] == 'USER') {
				$cur_user['attributes'][$attrs['NAME']] = '';
			} elseif($parent['name'] == 'GROUP') {
				$cur_group['attributes'][$attrs['NAME']] = '';
			} elseif($parent['name'] == 'KEYWORD') {
				$cur_keyword['attributes'][$attrs['NAME']] = '';
			} elseif($parent['name'] == 'ATTRIBUTEDEFINITION') {
				$cur_attrdef['attributes'][$attrs['NAME']] = '';
			} elseif($parent['name'] == 'FILE') {
				$cur_file['attributes'][$attrs['NAME']] = '';
			} elseif($parent['name'] == 'LINK') {
				$cur_link['attributes'][$attrs['NAME']] = '';
			} elseif($parent['name'] == 'WORKFLOW') {
				$cur_workflow['attributes'][$attrs['NAME']] = '';
			} elseif($parent['name'] == 'WORKFLOWTRANSITION') {
				$cur_workflowtransition['attributes'][$attrs['NAME']] = '';
			} elseif($parent['name'] == 'WORKFLOWACTION') {
				$cur_workflowaction['attributes'][$attrs['NAME']] = '';
			} elseif($parent['name'] == 'TRANSITION') {
				$cur_transition['attributes'][$attrs['NAME']] = '';
			}
			break;
		case "CATEGORIES":
			if($parent['name'] == 'DOCUMENT') {
				$cur_document['categories'] = array();
			}
			break;
		case "CATEGORY":
			$first = $elementstack[1];
			if($first['name'] == 'DOCUMENT') {
				$cur_document['categories'][] = (int) $attrs['ID'];
			}
			break;
		case "ACLS":
			if($parent['name'] == 'DOCUMENT') {
				$cur_document['acls'] = array();
			} elseif($parent['name'] == 'FOLDER') {
				$cur_folder['acls'] = array();
			}
			break;
		case "ACL":
			$first = $elementstack[1];
			if($first['name'] == 'FOLDER') {
				$acl = array('type'=>$attrs['TYPE'], 'mode'=>$attrs['MODE']);
				if($attrs['TYPE'] == 'user') {
					$acl['user'] = $attrs['USER'];
				} elseif($attrs['TYPE'] == 'group') { 
					$acl['group'] = $attrs['GROUP'];
				}
				$cur_folder['acls'][] = $acl;
			} elseif($first['name'] == 'DOCUMENT') {
				$acl = array('type'=>$attrs['TYPE'], 'mode'=>$attrs['MODE']);
				if($attrs['TYPE'] == 'user') {
					$acl['user'] = $attrs['USER'];
				} elseif($attrs['TYPE'] == 'group') { 
					$acl['group'] = $attrs['GROUP'];
				}
				$cur_document['acls'][] = $acl;
			}
			break;
		case "DATA":
			if($parent['name'] == 'IMAGE') {
				$cur_user['image']['id'] = $parent['attributes']['ID'];
				$cur_user['image']['data'] = "";
			} elseif($parent['name'] == 'VERSION') {
				$cur_version['data_length'] = (int) $attrs['LENGTH'];
				if(isset($attrs['FILEREF']))
					$cur_version['fileref'] = $attrs['FILEREF'];
				else
					$cur_version['data'] = "";
			} elseif($parent['name'] == 'FILE') {
				$cur_file['data_length'] = (int) $attrs['LENGTH'];
				if(isset($attrs['FILEREF']))
					$cur_file['fileref'] = $attrs['FILEREF'];
				else
					$cur_file['data'] = "";
			} elseif($parent['name'] == 'REVIEWLOG') {
				$cur_reviewlog['data_length'] = (int) $attrs['LENGTH'];
				if(isset($attrs['FILEREF']))
					$cur_reviewlog['fileref'] = $attrs['FILEREF'];
				else
					$cur_reviewlog['data'] = "";
			}
			break;
		case "KEYWORD":
			$cur_keyword = array();
			$cur_keyword['id'] = (int) $attrs['ID'];
			$cur_keyword['attributes'] = array();
			break;
		case "KEYWORDCATEGORY":
			$cur_keywordcat = array();
			$cur_keywordcat['id'] = (int) $attrs['ID'];
			$cur_keywordcat['attributes'] = array();
			$cur_keywordcat['keywords'] = array();
			break;
		case "DOCUMENTCATEGORY":
			$cur_documentcat = array();
			$cur_documentcat['id'] = (int) $attrs['ID'];
			$cur_documentcat['attributes'] = array();
			break;
		case "NOTIFICATIONS":
			$first = $elementstack[1];
			if($first['name'] == 'FOLDER') {
				$cur_folder['notifications'] = array('users'=>array(), 'groups'=>array());
			} elseif($first['name'] == 'DOCUMENT') {
				$cur_document['notifications'] = array('users'=>array(), 'groups'=>array());
			}
			break;
		case "FILES":
			$first = $elementstack[1];
			if($first['name'] == 'DOCUMENT') {
				$cur_document['files'] = array();
			}
			break;
		case "FILE":
			$first = $elementstack[1];
			if($first['name'] == 'DOCUMENT') {
				$cur_file = array();
				$cur_file['id'] = (int) $attrs['ID'];
			}
			break;
		case "LINKS":
			$first = $elementstack[1];
			if($first['name'] == 'DOCUMENT') {
				$cur_document['links'] = array();
			}
			break;
		case "LINK":
			$first = $elementstack[1];
			if($first['name'] == 'DOCUMENT') {
				$cur_link = array();
				$cur_link['id'] = (int) $attrs['ID'];
			}
			break;
		case "TRANSITIONS":
			$first = $elementstack[2];
			if($first['name'] == 'WORKFLOW') {
				$cur_workflow['transitions'] = array();
			}
			break;
		case "TRANSITION":
			$first = $elementstack[2];
			if($first['name'] == 'WORKFLOW') {
				$cur_transition = array();
				$cur_transition['id'] = (int) $attrs['ID'];
				$cur_transition['users'] = array();
				$cur_transition['groups'] = array();
			}
			break;
		case "WORKFLOW":
			$first = $elementstack[1];
			if($first['name'] == 'WORKFLOWS') {
				$cur_workflow = array();
				$cur_workflow['id'] = (int) $attrs['ID'];
			} elseif($parent['name'] == 'MANDATORY_WORKFLOWS') {
				$cur_user['workflows'][] = (int) $attrs['ID'];
			} elseif($parent['name'] == 'VERSION') {
				$cur_version['workflow'] = array('id'=>(int) $attrs['ID'], 'state'=>(int) $attrs['STATE']);
			}
			break;
		case "WORKFLOWACTION":
			$first = $elementstack[1];
			if($first['name'] == 'WORKFLOWACTIONS') {
				$cur_workflowaction = array();
				$cur_workflowaction['id'] = (int) $attrs['ID'];
			}
			break;
		case "WORKFLOWSTATE":
			$first = $elementstack[1];
			if($first['name'] == 'WORKFLOWSTATES') {
				$cur_workflowstate = array();
				$cur_workflowstate['id'] = (int) $attrs['ID'];
			}
			break;
	}
} /* }}} */

function endElement($parser, $name) { /* {{{ */
	global $logger, $dms, $sections, $rootfolder, $objmap, $elementstack, $users, $groups, $links,$cur_user, $cur_group, $cur_folder, $cur_document, $cur_version, $cur_statuslog, $cur_approval, $cur_approvallog, $cur_review, $cur_reviewlog, $cur_attrdef, $cur_documentcat, $cur_keyword, $cur_keywordcat, $cur_file, $cur_link, $cur_workflow, $cur_workflowlog, $cur_workflowtransition, $cur_workflowaction, $cur_workflowstate, $cur_transition;

	array_pop($elementstack);
	$parent = end($elementstack);
	switch($name) {
		case "DOCUMENT":
			insert_document($cur_document);
			if(!empty($cur_document['links']))
			$links[$cur_document['id']] = $cur_document['links'];
			break;
		case "FOLDER":
			insert_folder($cur_folder);
			break;
		case "VERSION":
			$cur_document['versions'][] = $cur_version;
			break;
		case "STATUSLOG":
			$cur_version['statuslogs'][] = $cur_statuslog;
			break;
		case "WORKFLOWLOG":
			$cur_version['workflowlogs'][] = $cur_workflowlog;
			break;
		case "APPROVAL":
			$cur_version['approvals'][] = $cur_approval;
			break;
		case "APPROVALLOG":
			$cur_approval['logs'][] = $cur_approvallog;
			break;
		case "REVIEW":
			$cur_version['reviews'][] = $cur_review;
			break;
		case "REVIEWLOG":
			$cur_review['logs'][] = $cur_reviewlog;
			break;
		case "USER":
			/* users can be the users data or the member of a group */
			$first = $elementstack[1];
			if($first['name'] == 'USERS' && $parent['name'] == 'USERS') {
				$users[$cur_user['id']] = $cur_user;
				insert_user($cur_user);
			}
			break;
		case "GROUP":
			$first = $elementstack[1];
			if($first['name'] == 'GROUPS') {
				$groups[$cur_group['id']] = $cur_group;
				insert_group($cur_group);
			}
			break;
		case 'ATTRIBUTEDEFINITION':
			insert_attributedefinition($cur_attrdef);
			break;
		case 'KEYWORD':
			$cur_keywordcat['keywords'][] = $cur_keyword;
			break;
		case 'KEYWORDCATEGORY':
			insert_keywordcategory($cur_keywordcat);
			break;
		case 'DOCUMENTCATEGORY':
			insert_documentcategory($cur_documentcat);
			break;
		case "FILE":
			$first = $elementstack[1];
			if($first['name'] == 'DOCUMENT') {
				$cur_document['files'][] = $cur_file;
			}
			break;
		case "LINK":
			$first = $elementstack[1];
			if($first['name'] == 'DOCUMENT') {
				$cur_document['links'][] = $cur_link;
			}
			break;
		case "TRANSITION":
			$second = $elementstack[2];
			if($second['name'] == 'WORKFLOW') {
				$cur_workflow['transitions'][] = $cur_transition;
			}
			break;
		case 'WORKFLOW':
			$first = $elementstack[1];
			if($first['name'] == 'WORKFLOWS') {
				insert_workflow($cur_workflow);
			}
			break;
		case 'WORKFLOWACTION':
			insert_workflowaction($cur_workflowaction);
			break;
		case 'WORKFLOWSTATE':
			insert_workflowstate($cur_workflowstate);
			break;
		case 'WORKFLOWS':
			/* Workflows has all been added. It's time to set the mandatory workflows
			 * of each user.
			 */
			foreach($users as $tuser) {
				if($tuser['workflows']) {
					if(!$user = $dms->getUser($objmap['users'][$tuser['id']])) {
						$logger->err("Cannot find user for adding mandatory workflows");
						exit;
					}
					foreach($tuser['workflows'] as $tworkflowid) {
						if(!$wk = $dms->getWorkflow($objmap['workflows'][$tworkflowid])) {
							$logger->err("Cannot find workflow for adding mandatory workflows");
							exit;
						}
						$user->setMandatoryWorkflow($wk);
					}
					foreach($tuser['individual']['reviewers'] as $userid) {
						$user->setMandatoryReviewer($objmap['users'][$userid], false);
					}
					foreach($tuser['individual']['approvers'] as $userid) {
						$user->setMandatoryApprover($objmap['users'][$userid], false);
					}
					foreach($tuser['group']['reviewers'] as $groupid) {
						$user->setMandatoryReviewer($objmap['groups'][$groupid], true);
					}
					foreach($tuser['group']['approvers'] as $userid) {
						$user->setMandatoryApprover($objmap['groups'][$groupid], true);
					}
				}
			}
			break;
	}
} /* }}} */

function characterData($parser, $data) { /* {{{ */
	global $elementstack, $objmap, $cur_user, $cur_group, $cur_folder, $cur_document, $cur_version, $cur_statuslog, $cur_approval, $cur_approvallog, $cur_review, $cur_reviewlog, $cur_attrdef, $cur_documentcat, $cur_keyword, $cur_keywordcat, $cur_file, $cur_link, $cur_workflow, $cur_workflowlog, $cur_workflowtransition, $cur_workflowaction, $cur_workflowstate, $cur_transition;

	$current = end($elementstack);
	$parent = prev($elementstack);
	switch($current['name']) {
		case 'ATTR':
			switch($parent['name']) {
				case 'DOCUMENT':
					if(isset($current['attributes']['TYPE']) && $current['attributes']['TYPE'] == 'user') {
						if(isset($cur_document['user_attributes'][$current['attributes']['ATTRDEF']]))
							$cur_document['user_attributes'][$current['attributes']['ATTRDEF']] .= $data;
						else
							$cur_document['user_attributes'][$current['attributes']['ATTRDEF']] = $data;
					} else {
						if(isset($cur_document['attributes'][$current['attributes']['NAME']]))
							$cur_document['attributes'][$current['attributes']['NAME']] .= $data;
						else
							$cur_document['attributes'][$current['attributes']['NAME']] = $data;
					}
					break;
				case 'FOLDER':
					if(isset($current['attributes']['TYPE']) && $current['attributes']['TYPE']  == 'user') {
						if(isset($cur_folder['user_attributes'][$current['attributes']['ATTRDEF']]))
							$cur_folder['user_attributes'][$current['attributes']['ATTRDEF']] .= $data;
						else
							$cur_folder['user_attributes'][$current['attributes']['ATTRDEF']] = $data;
					} else {
						if(isset($cur_folder['attributes'][$current['attributes']['NAME']]))
							$cur_folder['attributes'][$current['attributes']['NAME']] .= $data;
						else
							$cur_folder['attributes'][$current['attributes']['NAME']] = $data;
					}
					break;
				case 'VERSION':
					if(isset($current['attributes']['TYPE']) && $current['attributes']['TYPE']  == 'user') {
						if(isset($cur_version['user_attributes'][$current['attributes']['ATTRDEF']]))
							$cur_version['user_attributes'][$current['attributes']['ATTRDEF']] .= $data;
						else
							$cur_version['user_attributes'][$current['attributes']['ATTRDEF']] = $data;
					} else {
						if(isset($cur_version['attributes'][$current['attributes']['NAME']]))
							$cur_version['attributes'][$current['attributes']['NAME']] .= $data;
						else
							$cur_version['attributes'][$current['attributes']['NAME']] = $data;
					}
					break;
				case 'STATUSLOG':
					if(isset($cur_statuslog['attributes'][$current['attributes']['NAME']]))
						$cur_statuslog['attributes'][$current['attributes']['NAME']] .= $data;
					else
						$cur_statuslog['attributes'][$current['attributes']['NAME']] = $data;
					break;
				case 'APPROVAL':
					$cur_approval['attributes'][$current['attributes']['NAME']] = $data;
					break;
				case 'APPROVALLOG':
					if(isset($cur_approvallog['attributes'][$current['attributes']['NAME']]))
						$cur_approvallog['attributes'][$current['attributes']['NAME']] .= $data;
					else
						$cur_approvallog['attributes'][$current['attributes']['NAME']] = $data;
					break;
				case 'REVIEW':
					$cur_review['attributes'][$current['attributes']['NAME']] = $data;
					break;
				case 'REVIEWLOG':
					if(isset($cur_reviewlog['attributes'][$current['attributes']['NAME']]))
						$cur_reviewlog['attributes'][$current['attributes']['NAME']] .= $data;
					else
						$cur_reviewlog['attributes'][$current['attributes']['NAME']] = $data;
					break;
				case 'WORKFLOWLOG':
					if(isset($cur_workflowlog['attributes'][$current['attributes']['NAME']]))
						$cur_workflowlog['attributes'][$current['attributes']['NAME']] .= $data;
					else
						$cur_workflowlog['attributes'][$current['attributes']['NAME']] = $data;
					break;
				case 'USER':
					if(isset($cur_user['attributes'][$current['attributes']['NAME']]))
						$cur_user['attributes'][$current['attributes']['NAME']] .= $data;
					else
						$cur_user['attributes'][$current['attributes']['NAME']] = $data;
					break;
				case 'GROUP':
					if(isset($cur_group['attributes'][$current['attributes']['NAME']]))
						$cur_group['attributes'][$current['attributes']['NAME']] .= $data;
					else
						$cur_group['attributes'][$current['attributes']['NAME']] = $data;
					break;
				case 'ATTRIBUTEDEFINITION':
					if(isset($cur_attrdef['attributes'][$current['attributes']['NAME']]))
						$cur_attrdef['attributes'][$current['attributes']['NAME']] .= $data;
					else
						$cur_attrdef['attributes'][$current['attributes']['NAME']] = $data;
					break;
				case 'DOCUMENTCATEGORY':
					if(isset($cur_documentcat['attributes'][$current['attributes']['NAME']]))
						$cur_documentcat['attributes'][$current['attributes']['NAME']] .= $data;
					else
						$cur_documentcat['attributes'][$current['attributes']['NAME']] = $data;
					break;
				case 'KEYWORDCATEGORY':
					if(isset($cur_keywordcat['attributes'][$current['attributes']['NAME']]))
						$cur_keywordcat['attributes'][$current['attributes']['NAME']] .= $data;
					else
						$cur_keywordcat['attributes'][$current['attributes']['NAME']] = $data;
					break;
				case 'KEYWORD':
					if(isset($cur_keyword['attributes'][$current['attributes']['NAME']]))
						$cur_keyword['attributes'][$current['attributes']['NAME']] .= $data;
					else
						$cur_keyword['attributes'][$current['attributes']['NAME']] = $data;
					break;
				case 'IMAGE':
					$cur_user['image']['mimetype'] = $data;
					break;
				case 'FILE':
					if(isset($cur_file['attributes'][$current['attributes']['NAME']]))
						$cur_file['attributes'][$current['attributes']['NAME']] .= $data;
					else
						$cur_file['attributes'][$current['attributes']['NAME']] = $data;
					break;
				case 'LINK':
					$cur_link['attributes'][$current['attributes']['NAME']] = $data;
					break;
				case 'WORKFLOW':
					if(isset($cur_workflow['attributes'][$current['attributes']['NAME']]))
						$cur_workflow['attributes'][$current['attributes']['NAME']] .= $data;
					else
						$cur_workflow['attributes'][$current['attributes']['NAME']] = $data;
					break;
				case 'WORKFLOWSTATE':
					if(isset($cur_workflowstate['attributes'][$current['attributes']['NAME']]))
						$cur_workflowstate['attributes'][$current['attributes']['NAME']] .= $data;
					else
						$cur_workflowstate['attributes'][$current['attributes']['NAME']] = $data;
					break;
				case 'WORKFLOWACTION':
					if(isset($cur_workflowaction['attributes'][$current['attributes']['NAME']]))
						$cur_workflowaction['attributes'][$current['attributes']['NAME']] .= $data;
					else
						$cur_workflowaction['attributes'][$current['attributes']['NAME']] = $data;
					break;
				case 'TRANSITION':
					$cur_transition['attributes'][$current['attributes']['NAME']] = $data;
					break;
			}
			break;
		case 'DATA':
			switch($parent['name']) {
				case 'IMAGE':
					$cur_user['image']['data'] .= $data;
					break;
				case 'VERSION':
					$cur_version['data'] .= $data;
					break;
				case 'FILE':
					$cur_file['data'] .= $data;
					break;
				case 'REVIEWLOG':
					$cur_reviewlog['data'] .= $data;
					break;
			}
			break;
		case 'USER':
			$first = $elementstack[1];
			if($first['name'] == 'GROUPS') {
				$cur_group['users'][] = $data;
			}
			break;
	}
	
} /* }}} */

$version = "0.0.1";
$shortoptions = "hv";
$longoptions = array('help', 'version', 'debug', 'config:', 'sections:', 'folder:', 'file:', 'contentdir:', 'default-user:', 'export-mapping:', 'no-version-check');
if(false === ($options = getopt($shortoptions, $longoptions))) {
	usage();
	exit(0);
}

/* Print help and exit */
if(isset($options['h']) || isset($options['help'])) {
	usage();
	exit(0);
}

/* Print version and exit */
if(isset($options['v']) || isset($options['verѕion'])) {
	echo $version."\n";
	exit(0);
}

$logfile = "xmlimport.log";
$logconf = array();
$logconf['timeformat'] = '%Y-%m-%d %H:%M:%S';
$logconf['lineFormat'] = '%{timestamp} %{priority} xmlimport: %{ident} %{message}';
$logger = Log::factory('file', $logfile, '', $logconf);

/* Check for debug mode */
$debug = false;
if(isset($options['debug'])) {
	$debug = true;
}

/* Set alternative config file */
if(isset($options['config'])) {
	$settings = new Settings($options['config']);
} else {
	$settings = new Settings();
}

if(isset($options['folder'])) {
	$folderid = intval($options['folder']);
} else {
	$folderid = $settings->_rootFolderID;
}

if(isset($options['contentdir'])) {
	if(file_exists($options['contentdir'])) {
		$contentdir = $options['contentdir'];
		if(substr($contentdir, -1, 1) != DIRECTORY_SEPARATOR)
			$contentdir .= DIRECTORY_SEPARATOR;
	} else {
		$logger->crit("Directory ".$options['contentdir']." does not exists");
		exit(1);
	}
} else {
	$contentdir = '';
}

if(isset($options['default-user'])) {
	$defaultuserid = intval($options['default-user']);
} else {
	$defaultuserid = 0;
}

$filename = '';
if(isset($options['file'])) {
	$filename = $options['file'];
} else {
	usage();
	exit(1);
}

$exportmapping = '';
if(isset($options['export-mapping'])) {
	$exportmapping = $options['export-mapping'];
}

$noversioncheck = false;
if(isset($options['no-version-check'])) {
	$noversioncheck = true;
}

$sections = array('documents', 'folders', 'groups', 'users', 'keywordcategories', 'documentcategories', 'attributedefinitions', 'workflows');
if(isset($options['sections'])) {
	$sections = explode(',', $options['sections']);
}

if(isset($settings->_extraPath))
	ini_set('include_path', $settings->_extraPath. PATH_SEPARATOR .ini_get('include_path'));

require_once("LetoDMS/Core.php");

$db = new LetoDMS_Core_DatabaseAccess($settings->_dbDriver, $settings->_dbHostname, $settings->_dbUser, $settings->_dbPass, $settings->_dbDatabase);
$db->connect() or die ("Could not connect to db-server \"" . $settings->_dbHostname . "\"");

$dms = new LetoDMS_Core_DMS($db, $settings->_contentDir.$settings->_contentOffsetDir);
if(!$settings->_doNotCheckDBVersion && !$dms->checkVersion()) {
	$logger->crit("Database update needed.");
	exit;
}
$dms->setRootFolderID($settings->_rootFolderID);

$rootfolder = $dms->getFolder($folderid);
if(!$rootfolder) {
	exit(1);
}

if($defaultuserid) {
	if(!$defaultUser = $dms->getUser($defaultuserid)) {
		$logger->crit("Could not find default user with id ".$defaultuserid);
		exit(1);
	}
} else {
	$defaultUser = null;
}

$users = array();
$elementstack = array();
$objmap = array(
	'attributedefs' => array(),
	'keywordcategories' => array(),
	'documentcategories' => array(),
	'users' => array(),
	'groups' => array(),
	'folders' => array(),
	'documents' => array(),
	'workflows' => array(),
	'workflowstates' => array(),
	'workflowactions' => array(),
);

$xml_parser = xml_parser_create("UTF-8");
xml_parser_set_option($xml_parser, XML_OPTION_SKIP_WHITE, true);
xml_set_element_handler($xml_parser, "startElement", "endElement");
xml_set_character_data_handler($xml_parser, "characterData");
if (!($fp = fopen($filename, "r"))) {
    die("could not open XML input");
}
while ($data = fread($fp, 65535)) {
	if (!xml_parse($xml_parser, $data, feof($fp))) {
		die(sprintf("XML error: %s at line %d",
			xml_error_string(xml_get_error_code($xml_parser)),
			xml_get_current_line_number($xml_parser)));
	}
}

resolve_links();
set_mandatory();

set_homefolders();

if($exportmapping) {
	if($fp = fopen($exportmapping, 'w')) {
		fputcsv($fp, array('object type', 'old id', 'new id'));
		foreach($objmap as $section=>$map) {
			foreach($map as $old=>$new) {
				fputcsv($fp, array($section, $old, $new));
			}
		}
		fclose($fp);
	} else {
		$logger->err("Could not open mapping file '".$exportmapping."'");
	}
}
?>
