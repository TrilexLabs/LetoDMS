<?php
/**
 * Implementation of Timeline view
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
 * Class which outputs the html page for Timeline view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_Timeline extends LetoDMS_Bootstrap_Style {

	function iteminfo() { /* {{{ */
		$dms = $this->params['dms'];
		$document = $this->params['document'];
		$version = $this->params['version'];
		$cachedir = $this->params['cachedir'];
		$previewwidthlist = $this->params['previewWidthList'];
		$previewwidthdetail = $this->params['previewWidthDetail'];
		$timeout = $this->params['timeout'];

		if($document && $version) {
		//	$this->contentHeading(getMLText("timeline_selected_item"));
				print "<table id=\"viewfolder-table\" class=\"table table-condensed\">";
				print "<thead>\n<tr>\n";
				print "<th></th>\n";	
				print "<th>".getMLText("name")."</th>\n";
				print "<th>".getMLText("status")."</th>\n";
				print "<th>".getMLText("action")."</th>\n";
				print "</tr>\n</thead>\n<tbody>\n";
				$previewer = new LetoDMS_Preview_Previewer($cachedir, $previewwidthdetail, $timeout);
				echo $this->documentListRow($document, $previewer);

				echo "</tbody>\n</table>\n";
		}
	} /* }}} */

	function data() { /* {{{ */
		$dms = $this->params['dms'];
		$skip = $this->params['skip'];
		$fromdate = $this->params['fromdate'];
		$todate = $this->params['todate'];

		if($fromdate) {
			$from = makeTsFromLongDate($fromdate.' 00:00:00');
		} else {
			$from = time()-7*86400;
		}

		if($todate) {
			$to = makeTsFromLongDate($todate.' 23:59:59');
		} else {
			$to = time()-7*86400;
		}

		if($data = $dms->getTimeline($from, $to)) {
			foreach($data as $i=>$item) {
				switch($item['type']) {
				case 'add_version':
					$msg = getMLText('timeline_full_'.$item['type'], array('document'=>htmlspecialchars($item['document']->getName()), 'version'=> $item['version']));
					break;
				case 'add_file':
					$msg = getMLText('timeline_full_'.$item['type'], array('document'=>htmlspecialchars($item['document']->getName())));
					break;
				case 'status_change':
					$msg = getMLText('timeline_full_'.$item['type'], array('document'=>htmlspecialchars($item['document']->getName()), 'version'=> $item['version'], 'status'=> getOverallStatusText($item['status'])));
					break;
				default:
					$msg = '???';
				}
				$data[$i]['msg'] = $msg;
			}
		}

		$jsondata = array();
		foreach($data as $item) {
			if($item['type'] == 'status_change')
				$classname = $item['type']."_".$item['status'];
			else
				$classname = $item['type'];
			if(!$skip || !in_array($classname, $skip)) {
				$d = makeTsFromLongDate($item['date']);
				$jsondata[] = array(
					'start'=>date('c', $d),
					'content'=>$item['msg'],
					'className'=>$classname,
					'docid'=>$item['document']->getID(),
					'version'=>isset($item['version']) ? $item['version'] : '',
					'statusid'=>isset($item['statusid']) ? $item['statusid'] : '',
					'statuslogid'=>isset($item['statuslogid']) ? $item['statuslogid'] : '',
					'fileid'=>isset($item['fileid']) ? $item['fileid'] : ''
				);
			}
		}
		header('Content-Type: application/json');
		echo json_encode($jsondata);
	} /* }}} */

	function js() { /* {{{ */
		$fromdate = $this->params['fromdate'];
		$todate = $this->params['todate'];
		$skip = $this->params['skip'];

		if($fromdate) {
			$from = makeTsFromLongDate($fromdate.' 00:00:00');
		} else {
			$from = time()-7*86400;
		}

		if($todate) {
			$to = makeTsFromLongDate($todate.' 23:59:59');
		} else {
			$to = time();
		}

		header('Content-Type: application/javascript');
?>
$(document).ready(function () {
	$('#update').click(function(ev){
		ev.preventDefault();
		$.getJSON(
			'out.Timeline.php?action=data&' + $('#form1').serialize(), 
			function(data) {
				$.each( data, function( key, val ) {
					val.start = new Date(val.start);
				});
				timeline.setData(data);
				timeline.redraw();
//				timeline.setVisibleChartRange(0,0);
			}
		);
	});
});
<?php
		$this->printDeleteDocumentButtonJs();
		$timelineurl = 'out.Timeline.php?action=data&fromdate='.date('Y-m-d', $from).'&todate='.date('Y-m-d', $to).'&skip='.urldecode(http_build_query(array('skip'=>$skip)));
		$this->printTimelineJs($timelineurl, 550, ''/*date('Y-m-d', $from)*/, ''/*date('Y-m-d', $to+1)*/, $skip);
	} /* }}} */

	function css() { /* {{{ */
?>
#timeline {
	font-size: 12px;
	line-height: 14px;
}
div.timeline-event-content {
	margin: 3px 5px;
}
div.timeline-frame {
	border-radius: 4px;
	border-color: #e3e3e3;
}

div.status_change_2 {
	background-color: #DAF6D5;
	border-color: #AAF897;
}

div.status_change_-1 {
	background-color: #F6D5D5;
	border-color: #F89797;
}

div.timeline-event-selected {
	background-color: #fff785;
	border-color: #ffc200;
	z-index: 999;
}
<?php
		header('Content-Type: text/css');
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$fromdate = $this->params['fromdate'];
		$todate = $this->params['todate'];
		$skip = $this->params['skip'];

		$this->htmlAddHeader('<script type="text/javascript" src="../styles/'.$this->theme.'/bootbox/bootbox.min.js"></script>'."\n", 'js');

		if($fromdate) {
			$from = makeTsFromLongDate($fromdate.' 00:00:00');
		} else {
			$from = time()-7*86400;
		}

		if($todate) {
			$to = makeTsFromLongDate($todate.' 23:59:59');
		} else {
			$to = time();
		}

		$this->htmlAddHeader('<link href="../styles/'.$this->theme.'/timeline/timeline.css" rel="stylesheet">'."\n", 'css');
		$this->htmlAddHeader('<script type="text/javascript" src="../styles/'.$this->theme.'/timeline/timeline-min.js"></script>'."\n", 'js');
		$this->htmlAddHeader('<script type="text/javascript" src="../styles/'.$this->theme.'/timeline/timeline-locales.js"></script>'."\n", 'js');

		$this->htmlStartPage(getMLText("timeline"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("admin_tools"), "admin_tools");
?>

<?php
		echo "<div class=\"row-fluid\">\n";

		echo "<div class=\"span3\">\n";
		$this->contentHeading(getMLText("timeline"));
		$this->contentContainerStart();
?>
<form action="../out/out.Timeline.php" class="form form-inline" name="form1" id="form1">
<?php
		/*
		$html = '
       <span class="input-append date" style="display: inline;" id="fromdate" data-date="'.date('Y-m-d', $from).'" data-date-format="yyyy-mm-dd" data-date-language="'.str_replace('_', '-', $this->params['session']->getLanguage()).'">
			 <input type="text" class="input-small" name="fromdate" value="'.date('Y-m-d', $from).'"/>
				<span class="add-on"><i class="icon-calendar"></i></span>
			</span> -
      <span class="input-append date" style="display: inline;" id="todate" data-date="'.date('Y-m-d', $to).'" data-date-format="yyyy-mm-dd" data-date-language="'.str_replace('_', '-', $this->params['session']->getLanguage()).'">
			<input type="text" class="input-small" name="todate" value="'.date('Y-m-d', $to).'"/>
				<span class="add-on"><i class="icon-calendar"></i></span>
			</span>';
		$this->formField(
			getMLText("date"),
			$html
		);
*/
		$this->formField(
			getMLText("from"),
			$this->getDateChooser(date('Y-m-d', $from), 'fromdate', $this->params['session']->getLanguage())
		);
		$this->formField(
			getMLText("to"),
			$this->getDateChooser(date('Y-m-d', $to), 'todate', $this->params['session']->getLanguage())
		);
		$html = '
			<input type="checkbox" name="skip[]" value="add_file" '.(($skip &&  in_array('add_file', $skip)) ? 'checked' : '').'> '.getMLText('timeline_skip_add_file').'<br />
			<input type="checkbox" name="skip[]" value="status_change_0" '.(($skip && in_array('status_change_0', $skip)) ? 'checked' : '').'> '.getMLText('timeline_skip_status_change_0').'<br />
			<input type="checkbox" name="skip[]" value="status_change_1" '.(($skip && in_array('status_change_1', $skip)) ? 'checked' : '').'> '.getMLText('timeline_skip_status_change_1').'<br />
			<input type="checkbox" name="skip[]" value="status_change_2" '.(($skip && in_array('status_change_2', $skip)) ? 'checked' : '').'> '.getMLText('timeline_skip_status_change_2').'<br />
			<input type="checkbox" name="skip[]" value="status_change_3" '.(($skip && in_array('status_change_3', $skip)) ? 'checked' : '').'> '.getMLText('timeline_skip_status_change_3').'<br />
			<input type="checkbox" name="skip[]" value="status_change_-1" '.(($skip && in_array('status_change_-1', $skip)) ? 'checked' : '').'> '.getMLText('timeline_skip_status_change_-1').'<br />
			<input type="checkbox" name="skip[]" value="status_change_-3" '.(($skip && in_array('status_change_-3', $skip)) ? 'checked' : '').'> '.getMLText('timeline_skip_status_change_-3').'<br />';
		$this->formField(
			getMLText("exclude_items"),
			$html
		);
		$this->formSubmit('<i class="icon-search"></i> '.getMLText('update'), 'update');
?>
</form>
<?php
		$this->contentContainerEnd();
		echo "<div class=\"ajax\" data-view=\"Timeline\" data-action=\"iteminfo\" ></div>";
		echo "</div>\n";

		echo "<div class=\"span9\">\n";
		$this->contentHeading(getMLText("timeline"));
		$this->printTimelineHtml(550);
		echo "</div>\n";
		echo "</div>\n";

		$this->contentContainerEnd();
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
