<?php
/**
 * Implementation of Tasks view
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
 * Include class to preview documents
 */
require_once("LetoDMS/Preview.php");

/**
 * Class which outputs the html page for clipboard view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_Tasks extends LetoDMS_Bootstrap_Style {

	private function __myTasks() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$workflowmode = $this->params['workflowmode'];
		if($workflowmode == 'traditional')
			$tasks['review'] = array();
		elseif($workflowmode == 'traditional' || $workflowmode == 'traditional_only_approval')
			$tasks['approval'] = array();
		elseif($workflowmode == 'advanced')
			$tasks['workflow'] = array();

		if($workflowmode == 'traditional' || $workflowmode == 'traditional_only_approval') {
			$resArr = $dms->getDocumentList('ApproveByMe', $user);
			if($resArr) {
				foreach ($resArr as $res) {
					$document = $dms->getDocument($res["id"]);
					if($document->getAccessMode($user) >= M_READ && $document->getLatestContent()) {
						$tasks['approval'][] = array('id'=>$res['id'], 'name'=>$res['name']);
					}
				}
			}
		} elseif($workflowmode == 'traditional') {
			$resArr = $dms->getDocumentList('ReviewByMe', $user);
			if($resArr) {
				foreach ($resArr as $res) {
					$document = $dms->getDocument($res["id"]);
					if($document->getAccessMode($user) >= M_READ && $document->getLatestContent()) {
						$tasks['review'][] = array('id'=>$res['id'], 'name'=>$res['name']);
					}
				}
			}
		} elseif($workflowmode == 'advanced') {
			$resArr = $dms->getDocumentList('WorkflowByMe', $user);
			if($resArr) {
				foreach ($resArr as $res) {
					$document = $dms->getDocument($res["id"]);
					if($document->getAccessMode($user) >= M_READ && $document->getLatestContent()) {
						$tasks['workflow'][] = array('id'=>$res['id'], 'name'=>$res['name']);
					}
				}
			}
		}
		return $tasks;
	} /* }}} */

	/**
	 * Returns the html needed for the task list in the menu
	 *
	 * This function renders the tasks in a way suitable to be
	 * used as a menu
	 *
	 * @param array $clipboard clipboard containing two arrays for both
	 *        documents and folders.
	 * @return string html code
	 */
	function myTasks() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$startts = microtime(true);

		$tasks = $this->__myTasks();

		header('Content-Type: application/json');
		echo json_encode(array('error'=>0, 'data'=>$tasks, 'processing_time'=>microtime(true)-$startts));
	} /* }}} */

	/**
	 * Returns the html needed for the task list in the menu
	 *
	 * This function renders the tasks in a way suitable to be
	 * used as a menu
	 *
	 * @param array $clipboard clipboard containing two arrays for both
	 *        documents and folders.
	 * @return string html code
	 */
	function menuTasks() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];

		$tasks = $this->__myTasks();

		$content = '';
//		$content .= "   <ul id=\"main-menu-tasks\" class=\"nav pull-right\">\n";
//		$content .= "    <li class=\"dropdown\">\n";
		$content .= "     <a href=\"#\" class=\"dropdown-toggle\" data-toggle=\"dropdown\">".getMLText('tasks')." (";
		$ct = array();
		if(isset($tasks['review']))
			$ct[] = count($tasks['review']);
		if(isset($tasks['approval']))
			$ct[] = count($tasks['approval']);
		if(isset($tasks['workflow']))
			$ct[] = count($tasks['workflow']);
		$content .= implode('/', $ct);
		$content .= ")";
		if(!empty($tasks['review']) || !empty($tasks['approval']) || !empty($tasks['workflow'])) {
		$content .= " <i class=\"icon-caret-down\"></i></a>\n";
		$content .= "     <ul class=\"dropdown-menu\" role=\"menu\">\n";
		if(!empty($tasks['review'])) {
		$content .= "      <li class=\"dropdown-submenu\">\n";
		$content .=	"       <a href=\"#\" class=\"dropdown-toggle\" data-toggle=\"dropdown\">".getMLText("documents_to_review")."</a>\n";
		$content .= "       <ul class=\"dropdown-menu\" role=\"menu\">\n";
		foreach($tasks['review'] as $t) {
			$doc = $dms->getDocument($t['id']);
			$content .= "      <li><a href=\"../out/out.ViewDocument.php?documentid=".$doc->getID()."&currenttab=revapp\">".$doc->getName()."</a></li>";
		}
		$content .= "       </ul>\n";
		$content .= "      </li>\n";
		}
		if(!empty($tasks['approval'])) {
		$content .= "      <li class=\"dropdown-submenu\">\n";
		$content .=	"       <a href=\"#\" class=\"dropdown-toggle\" data-toggle=\"dropdown\">".getMLText("documents_to_approve")."</a>\n";
		$content .= "         <ul class=\"dropdown-menu\" role=\"menu\">\n";
		foreach($tasks['approval'] as $t) {
			$doc = $dms->getDocument($t['id']);
			$content .= "       <li><a href=\"../out/out.ViewDocument.php?documentid=".$doc->getID()."&currenttab=revapp\">".$doc->getName()."</a></li>";
		}
		$content .= "       </ul>\n";
		$content .= "      </li>\n";
		}
		if(!empty($tasks['workflow'])) {
		$content .= "      <li class=\"dropdown-submenu\">\n";
		$content .=	"       <a href=\"#\" class=\"dropdown-toggle\" data-toggle=\"dropdown\">".getMLText("documents_to_trigger_workflow")."</a>\n";
		$content .= "         <ul class=\"dropdown-menu\" role=\"menu\">\n";
		foreach($tasks['workflow'] as $t) {
			$doc = $dms->getDocument($t['id']);
			$content .= "       <li><a href=\"../out/out.ViewDocument.php?documentid=".$doc->getID()."&currenttab=workflow\">".$doc->getName()."</a></li>";
		}
		$content .= "       </ul>\n";
		$content .= "      </li>\n";
		}
		$content .= "    <li class=\"divider\"></li>\n";
		$content .= "    <li><a href=\"../out/out.MyDocuments.php\">".getMLText("my_documents")."</a></li>\n";
		$content .= "     </ul>\n";
		}
//		$content .= "    </li>\n";
//		$content .= "   </ul>\n";
		echo $content;
	} /* }}} */

	/**
	 * Returns the html needed for the clipboard list in the menu
	 *
	 * This function renders the clipboard in a way suitable to be
	 * used as a menu
	 *
	 * @param array $clipboard clipboard containing two arrays for both
	 *        documents and folders.
	 * @return string html code
	 */
	public function menuClipboard() { /* {{{ */
		$clipboard = $this->params['session']->getClipboard();
		if ($this->params['user']->isGuest() || (count($clipboard['docs']) + count($clipboard['folders'])) == 0) {
			return '';
		}
		$content = '';
		$content .= "   <ul id=\"main-menu-clipboard\" class=\"nav pull-right\">\n";
		$content .= "    <li class=\"dropdown add-clipboard-area\">\n";
		$content .= "     <a href=\"#\" class=\"dropdown-toggle\" data-toggle=\"dropdown\" class=\"add-clipboard-area\">".getMLText('clipboard')." (".count($clipboard['folders'])."/".count($clipboard['docs']).") <i class=\"icon-caret-down\"></i></a>\n";
		$content .= "     <ul class=\"dropdown-menu\" role=\"menu\">\n";
		foreach($clipboard['folders'] as $folderid) {
			if($folder = $this->params['dms']->getFolder($folderid))
				$content .= "    <li><a href=\"../out/out.ViewFolder.php?folderid=".$folder->getID()."\"><i class=\"icon-folder-close-alt\"></i> ".htmlspecialchars($folder->getName())."</a></li>\n";
		}
		foreach($clipboard['docs'] as $docid) {
			if($document = $this->params['dms']->getDocument($docid))
				$content .= "    <li><a href=\"../out/out.ViewDocument.php?documentid=".$document->getID()."\"><i class=\"icon-file\"></i> ".htmlspecialchars($document->getName())."</a></li>\n";
		}
		$content .= "    <li class=\"divider\"></li>\n";
		if(isset($this->params['folder']) && $this->params['folder']->getAccessMode($this->params['user']) >= M_READWRITE) {
			$content .= "    <li><a href=\"../op/op.MoveClipboard.php?targetid=".$this->params['folder']->getID()."&refferer=".urlencode($this->params['refferer'])."\">".getMLText("move_clipboard")."</a></li>\n";
		}
//		$content .= "    <li><a href=\"../op/op.ClearClipboard.php?refferer=".urlencode($this->params['refferer'])."\">".getMLText("clear_clipboard")."</a><a class=\"ajax-click\" data-href=\"../op/op.Ajax.php\" data-param1=\"command=clearclipboard\">kkk</a> </li>\n";
		$content .= "    <li><a class=\"ajax-click\" data-href=\"../op/op.Ajax.php\" data-param1=\"command=clearclipboard\">".getMLText("clear_clipboard")."</a></li>\n";
		$content .= "     </ul>\n";
		$content .= "    </li>\n";
		$content .= "   </ul>\n";
		echo $content;
	} /* }}} */

	/**
	 * Return clipboard content rendered as html
	 *
	 * @param array clipboard
	 * @return string rendered html content
	 */
	public function mainClipboard() { /* {{{ */
		$dms = $this->params['dms'];
		$clipboard = $this->params['session']->getClipboard();
		$cachedir = $this->params['cachedir'];
		$previewwidth = $this->params['previewWidthList'];
		$timeout = $this->params['timeout'];

		$previewer = new LetoDMS_Preview_Previewer($cachedir, $previewwidth, $timeout);
		$content = '';
		$foldercount = $doccount = 0;
		if($clipboard['folders']) {
			foreach($clipboard['folders'] as $folderid) {
				/* FIXME: check for access rights, which could have changed after adding the folder to the clipboard */
				if($folder = $dms->getFolder($folderid)) {
					$comment = $folder->getComment();
					if (strlen($comment) > 150) $comment = substr($comment, 0, 147) . "...";
					$content .= "<tr draggable=\"true\" rel=\"folder_".$folder->getID()."\" class=\"folder table-row-folder\" formtoken=\"".createFormKey('movefolder')."\">";
					$content .= "<td><a draggable=\"false\" href=\"out.ViewFolder.php?folderid=".$folder->getID()."&showtree=".showtree()."\"><img draggable=\"false\" src=\"".$this->imgpath."folder.png\" width=\"24\" height=\"24\" border=0></a></td>\n";
					$content .= "<td><a draggable=\"false\" href=\"out.ViewFolder.php?folderid=".$folder->getID()."&showtree=".showtree()."\">" . htmlspecialchars($folder->getName()) . "</a>";
					if($comment) {
						$content .= "<br /><span style=\"font-size: 85%;\">".htmlspecialchars($comment)."</span>";
					}
					$content .= "</td>\n";
					$content .= "<td>\n";
					$content .= "<div class=\"list-action\"><a class=\"removefromclipboard\" rel=\"F".$folderid."\" msg=\"".getMLText('splash_removed_from_clipboard')."\" _href=\"../op/op.RemoveFromClipboard.php?folderid=".(isset($this->params['folder']) ? $this->params['folder']->getID() : '')."&id=".$folderid."&type=folder\" title=\"".getMLText('rm_from_clipboard')."\"><i class=\"icon-remove\"></i></a></div>";
					$content .= "</td>\n";
					$content .= "</tr>\n";
					$foldercount++;
				}
			}
		}
		if($clipboard['docs']) {
			foreach($clipboard['docs'] as $docid) {
				/* FIXME: check for access rights, which could have changed after adding the document to the clipboard */
				if($document = $dms->getDocument($docid)) {
					$comment = $document->getComment();
					if (strlen($comment) > 150) $comment = substr($comment, 0, 147) . "...";
					if($latestContent = $document->getLatestContent()) {
						$previewer->createPreview($latestContent);
						$version = $latestContent->getVersion();
						$status = $latestContent->getStatus();
						
						$content .= "<tr draggable=\"true\" rel=\"document_".$docid."\" class=\"table-row-document\" formtoken=\"".createFormKey('movedocument')."\">";

						if (file_exists($dms->contentDir . $latestContent->getPath())) {
							$content .= "<td><a draggable=\"false\" href=\"../op/op.Download.php?documentid=".$docid."&version=".$version."\">";
							if($previewer->hasPreview($latestContent)) {
								$content .= "<img draggable=\"false\" class=\"mimeicon\" width=\"40\"src=\"../op/op.Preview.php?documentid=".$document->getID()."&version=".$latestContent->getVersion()."&width=40\" title=\"".htmlspecialchars($latestContent->getMimeType())."\">";
							} else {
								$content .= "<img draggable=\"false\" class=\"mimeicon\" src=\"".$this->getMimeIcon($latestContent->getFileType())."\" title=\"".htmlspecialchars($latestContent->getMimeType())."\">";
							}
							$content .= "</a></td>";
						} else
							$content .= "<td><img draggable=\"false\" class=\"mimeicon\" src=\"".$this->getMimeIcon($latestContent->getFileType())."\" title=\"".htmlspecialchars($latestContent->getMimeType())."\"></td>";
						
						$content .= "<td><a draggable=\"false\" href=\"out.ViewDocument.php?documentid=".$docid."&showtree=".showtree()."\">" . htmlspecialchars($document->getName()) . "</a>";
						if($comment) {
							$content .= "<br /><span style=\"font-size: 85%;\">".htmlspecialchars($comment)."</span>";
						}
						$content .= "</td>\n";
						$content .= "<td>\n";
						$content .= "<div class=\"list-action\"><a class=\"removefromclipboard\" rel=\"D".$docid."\" msg=\"".getMLText('splash_removed_from_clipboard')."\" _href=\"../op/op.RemoveFromClipboard.php?folderid=".(isset($this->params['folder']) ? $this->params['folder']->getID() : '')."&id=".$docid."&type=document\" title=\"".getMLText('rm_from_clipboard')."\"><i class=\"icon-remove\"></i></a></div>";
						$content .= "</td>\n";
						$content .= "</tr>";
						$doccount++;
					}
				}
			}
		}

		/* $foldercount or $doccount will only count objects which are
		 * actually available
		 */
		if($foldercount || $doccount) {
			$content = "<table class=\"table\">".$content;
			$content .= "</table>";
		} else {
		}
		$content .= "<div class=\"alert add-clipboard-area\">".getMLText("drag_icon_here")."</div>";
		echo $content;
	} /* }}} */

}
