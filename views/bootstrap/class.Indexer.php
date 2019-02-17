<?php
/**
 * Implementation of Indexer view
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
 * Class which outputs the html page for Indexer view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_Indexer extends LetoDMS_Bootstrap_Style {

	function js() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];

		header('Content-Type: application/javascript');
?>
var queue_count = 0;          // Number of functions being called
var funcArray = [];     // Array of functions waiting
var MAX_REQUESTS = 5;   // Max requests
var CALL_WAIT = 100;        // 100ms
var docstoindex = 0; // total number of docs to index

function check_queue() {
		// Check if count doesn't exceeds or if there aren't any functions to call
		console.log('Queue has ' + funcArray.length + '/' + docstoindex + ' items');
		console.log('Currently processing ' + queue_count + ' requests (' + $.active + ')');
    if(queue_count >= MAX_REQUESTS) {
			setTimeout(function() { check_queue() }, CALL_WAIT);
			return;
		}
		if(funcArray.length == 0) {
			return;
		}
		docid = funcArray.pop();
		$('#status_'+docid).html('Processsing ...');
		$.ajax({url: '../op/op.Ajax.php',
			type: 'GET',
			dataType: "json",
			data: {command: 'indexdocument', id: docid},
			beforeSend: function() {
				queue_count++;            // Add request to the counter
				$('.queue-bar').css('width', (queue_count*100/MAX_REQUESTS)+'%');
			},
			error: function(xhr, textstatus) {
				noty({
					text: textstatus,
					type: 'error',
					dismissQueue: true,
					layout: 'topRight',
					theme: 'defaultTheme',
					timeout: 5000,
				});
			},
			success: function(data) {
				// console.log('success ' + data.data);
				if(data.success) {
					if(data.cmd)
						$('#status_'+data.data).html('<?php printMLText('index_done'); ?>');
					else
						$('#status_'+data.data).html('<?= getMLText('index_done').' ('.getMLText('index_no_content').')'; ?>');
				} else {
					$('#status_'+data.data).html('<?php printMLText('index_error'); ?>');
					noty({
						text: '<p><strong>Docid: ' + data.data + ' (' + data.mimetype + ')</strong></p>' + '<p>Cmd: ' + data.cmd + '</p>' + data.message,
						type: 'error',
						dismissQueue: true,
						layout: 'topRight',
						theme: 'defaultTheme',
						timeout: 25000,
					});
				}
			},
			complete: function(xhr, textstatus) {
				queue_count--;        // Substract request to the counter
				$('.queue-bar').css('width', (queue_count*100/MAX_REQUESTS)+'%');
				$('.total-bar').css('width', (100 - (funcArray.length+queue_count)*100/docstoindex)+'%');
				$('.total-bar').text(Math.round(100 - (funcArray.length+queue_count)*100/docstoindex)+' %');
				if(funcArray.length+queue_count == 0)
					$('.total-bar').addClass('bar-success');
			}
		}); 
		setTimeout(function() { check_queue() }, CALL_WAIT);
}

$(document).ready( function() {
	$('.tree-toggle').click(function () {
		$(this).parent().children('ul.tree').toggle(200);
	});

	$('.indexme').each(function(index) {
		var element = $(this);
		var docid = element.data('docid');
		element.html('<?php printMLText('index_pending'); ?>');
    funcArray.push(docid);
	});
	docstoindex = funcArray.length;
	check_queue();  // First call to start polling. It will call itself each 100ms
});
<?php
	} /* }}} */

	protected function tree($dms, $index, $indexconf, $folder, $indent='') { /* {{{ */
		$forceupdate = $this->params['forceupdate'];

		set_time_limit(30);
//		echo $indent."D ".htmlspecialchars($folder->getName())."\n";
		echo '<ul class="nav nav-list"><li><label class="tree-toggle nav-header">'.htmlspecialchars($folder->getName()).'</label>'."\n";
		$subfolders = $folder->getSubFolders();
		foreach($subfolders as $subfolder) {
			$this->tree($dms, $index, $indexconf, $subfolder, $indent.'  ');
		}
		$documents = $folder->getDocuments();
		if($documents) {
		echo '<ul class="nav nav-list">'."\n";
		foreach($documents as $document) {
//			echo $indent."  ".$document->getId().":".htmlspecialchars($document->getName());
			echo "<li class=\"document\">".$document->getId().":".htmlspecialchars($document->getName());
			/* If the document wasn't indexed before then just add it */
			$lucenesearch = new $indexconf['Search']($index);
			if(!($hit = $lucenesearch->getDocument($document->getId()))) {
				echo " <span id=\"status_".$document->getID()."\" class=\"indexme indexstatus\" data-docid=\"".$document->getID()."\">".getMLText('index_waiting')."</span>";
				/*
				try {
					$index->addDocument(new $indexconf['IndexedDocument']($dms, $document, $this->converters ? $this->converters : null, false, $this->timeout));
					echo "(document added)";
				} catch(Exception $e) {
					echo $indent."(adding document failed '".$e->getMessage()."')";
				}
				 */
			} else {
				/* Check if the attribute created is set or has a value older
				 * than the lasted content. Documents without such an attribute
				 * where added when a new document was added to the dms. In such
				 * a case the document content  wasn't indexed.
				 */
				try {
					$created = (int) $hit->getDocument()->getFieldValue('created');
				} catch (/* Zend_Search_Lucene_ */Exception $e) {
					$created = 0;
				}
				$content = $document->getLatestContent();
				if($created >= $content->getDate() && !$forceupdate) {
					echo $indent."<span id=\"status_".$document->getID()."\" class=\"indexstatus\" data-docid=\"".$document->getID()."\">document unchanged</span>";
				} else {
					$index->delete($hit->id);
					echo " <span id=\"status_".$document->getID()."\" class=\"indexme indexstatus\" data-docid=\"".$document->getID()."\">".getMLText('index_waiting')."</span>";
					/*
					try {
						$index->addDocument(new $indexconf['IndexedDocument']($dms, $document, $this->converters ? $this->converters : null, false, $this->timeout));
						echo $indent."(document updated)";
					} catch(Exception $e) {
						echo $indent."(updating document failed)";
					}
					 */
				}
			}
			echo "</li>";
			echo "\n";
		}
		echo "</ul>\n";
		}
		echo "</li></ul>\n";
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$index = $this->params['index'];
		$indexconf = $this->params['indexconf'];
		$forceupdate = $this->params['forceupdate'];
		$folder = $this->params['folder'];
		$this->converters = $this->params['converters'];
		$this->timeout = $this->params['timeout'];

		$this->htmlStartPage(getMLText("admin_tools"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("admin_tools"), "admin_tools");
		$this->contentHeading(getMLText("update_fulltext_index"));
?>
<style type="text/css">
li {line-height: 20px;}
.nav-header {line-height: 19px; margin-bottom: 0px;}
.nav-list {padding-right: 0px;}
.nav-list>li.document:hover {background-color: #eee;}
.indexstatus {font-weight: bold; float: right;}
.progress {margin-bottom: 2px;}
.bar-legend {text-align: right; font-size: 85%; margin-bottom: 15px;}
</style>
		<div style="max-width: 900px;">
		<div>
			<div class="progress">
				<div class="bar total-bar" role="progressbar" style="width: 100%;"></div>
			</div>
			<div class="bar-legend"><?php printMLText('overall_indexing_progress'); ?></div>
		</div>
		<div>
			<div class="progress">
				<div class="bar queue-bar" role="progressbar" style="width: 100%;"></div>
			</div>
			<div class="bar-legend"><?php printMLText('indexing_tasks_in_queue'); ?></div>
		</div>
<?php
		$this->tree($dms, $index, $indexconf, $folder);
		echo "</div>";

		$index->commit();
		$index->optimize();

		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
