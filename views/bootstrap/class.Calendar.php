<?php
/**
 * Implementation of Calendar view
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
 * Class which outputs the html page for Calendar view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_Calendar extends LetoDMS_Bootstrap_Style {

	function iteminfo() { /* {{{ */
		$dms = $this->params['dms'];
		$document = $this->params['document'];
		$version = $this->params['version'];
		$event = $this->params['event'];
		$strictformcheck = $this->params['strictformcheck'];
		$cachedir = $this->params['cachedir'];
		$previewwidthlist = $this->params['previewWidthList'];
		$previewwidthdetail = $this->params['previewWidthDetail'];
		$timeout = $this->params['timeout'];

		if($document) {
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
		if($event) {
//			print_r($event);
			$this->contentHeading(getMLText('edit_event'));
			$this->contentContainerStart();
?>

<form class="form-horizontal" action="../op/op.EditEvent.php" id="form1" name="form1" method="post">
  <?php echo createHiddenFieldWithKey('editevent'); ?>
	<input type="hidden" name="eventid" value="<?php echo (int) $event["id"]; ?>">
<?php
			$this->formField(
				getMLText("from"),
				$this->getDateChooser(date('Y-m-d', $event["start"]), "from")
			);
			$this->formField(
				getMLText("to"),
				$this->getDateChooser(date('Y-m-d', $event["stop"]), "to")
			);
			$this->formField(
				getMLText("name"),
				array(
					'element'=>'input',
					'type'=>'text',
					'name'=>'name',
					'value'=>htmlspecialchars($event["name"])
				)
			);
			$this->formField(
				getMLText("comment"),
				array(
					'element'=>'textarea',
					'name'=>'comment',
					'rows'=>4,
					'cols'=>80,
					'value'=>htmlspecialchars($event["comment"]),
					'required'=>$strictformcheck
				)
			);
			$this->formSubmit("<i class=\"icon-save\"></i> ".getMLText('save'));
?>
</form>
<?php
			$this->contentContainerEnd();
			$this->contentHeading(getMLText('rm_event'));
		$this->contentContainerStart();
?>
<form action="../op/op.RemoveEvent.php" name="form2" method="post">
  <?php echo createHiddenFieldWithKey('removeevent'); ?>
	<input type="hidden" name="eventid" value="<?php echo intval($event["id"]); ?>">
	<p><?php printMLText("confirm_rm_event", array ("name" => htmlspecialchars($event["name"])));?></p>
	<button class="btn" type="submit"><i class="icon-remove"></i> <?php printMLText("delete");?></button>
</form>
<?php
			$this->contentContainerEnd();
		}
	} /* }}} */

	function itemsperday() { /* {{{ */
		$dms = $this->params['dms'];
		$start = explode('-', $this->params['start']);
		$cachedir = $this->params['cachedir'];
		$previewwidthlist = $this->params['previewWidthList'];
		$previewwidthdetail = $this->params['previewWidthDetail'];
		$timeout = $this->params['timeout'];

		if($this->params['start']) {
			$from = makeTsFromLongDate($this->params['start'].' 00:00:00');
		} else {
			$from = time();
		}

		if($data = $dms->getTimeline($from)) {
			$this->contentHeading(getReadableDate($from));
			print "<table id=\"viewfolder-table\" class=\"table table-condensed\">";
			print "<thead>\n<tr>\n";
			print "<th></th>\n";	
			print "<th>".getMLText("name")."</th>\n";
			print "<th>".getMLText("status")."</th>\n";
			print "<th>".getMLText("action")."</th>\n";
			print "</tr>\n</thead>\n<tbody>\n";
			$previewer = new LetoDMS_Preview_Previewer($cachedir, $previewwidthdetail, $timeout);
			foreach($data as $i=>$item) {
				/* Filter out timeline events for the documents not happened on the
				 * selected day
				 */
				if(substr($item['date'], 0, 10) == $this->params['start'])
					if($item['document']) {
						echo $this->documentListRow($item['document'], $previewer);
				}
			}
			echo "</tbody>\n</table>\n";
		}
	} /* }}} */

	function events() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$calendar = $this->params['calendar'];
		$eventtype = $this->params['eventtype'];
		$start = explode('-', $this->params['start']);
		$end = explode('-', $this->params['end']);

		$arr = array();
		switch($eventtype) {
		case 'regular':
			$events = $calendar->getEventsInInterval(mktime(0,0,0, $start[1], $start[2], $start[0]), mktime(23,59,59, $end[1], $end[2], $end[0]));
			foreach ($events as $event){
				$arr[] = array('start'=>date('Y-m-d', $event["start"]), 'end'=>date('Y-m-d', $event["stop"]), 'title'=>$event["name"].($event['comment'] ? "\n".$event['comment'] : ''), 'eventid'=>$event["id"]);
			}
			break;
		case 'action':
			if($this->params['start']) {
				$from = makeTsFromLongDate($this->params['start'].' 00:00:00');
			} else {
				$from = time()-7*86400;
			}

			if($this->params['end']) {
				$to = makeTsFromLongDate($this->params['end'].' 23:59:59');
			} else {
				$to = time();
			}

			if($data = $dms->getTimeline($from, $to)) {
				foreach($data as $i=>$item) {
					switch($item['type']) {
					case 'add_version':
						$color = '#20a820';
						break;
					case 'add_file':
						$color = '#a82020';
						break;
					case 'status_change':
						$color = '#a8a8a8';
						break;
					default:
						$color = '#20a8a8';
					}
					if ($item['document']->getAccessMode($user) >= M_READ)
					$arr[] = array(
						'start'=>$item['date'],
						'title'=>$item['document']->getName()."\n".$item['msg'],
						'allDay'=>false,
						'color'=>$color,
						'type'=>$item['type'],
						'documentid'=> (int) $item['document']->getID(),
						'version'=> isset($item['version']) ? (int) $item['version'] : '',
						'statusid'=> isset($item['statusid']) ? (int) $item['statusid'] : '',
						'statuslogid'=> isset($item['statuslogid']) ? (int) $item['statuslogid'] : '',
						'fileid'=> isset($item['fileid']) ? (int) $item['fileid'] : ''
					);
				}
			}
			break;
		}

		header('Content-Type: application/json');
		echo json_encode($arr);
	} /* }}} */

	function js() { /* {{{ */
		$strictformcheck = $this->params['strictformcheck'];
		header('Content-Type: application/javascript');
?>
	$(document).ready(function() {
		
		$('#calendar').fullCalendar({
			height: $(window).height()-210,
			locale: '<?php echo substr($this->params['session']->getLanguage(), 0, 2); ?>',
			customButtons: {
				addEventButton: {
					text: '<?php printMLText('add_event'); ?>',
					click: function() {
//						alert('clicked the custom button!');
						document.location.href = '../out/out.AddEvent.php';
					}
				}
			},
			header: {
				left: 'prev,next today addEventButton',
				center: 'title',
				right: 'month,agendaWeek,agendaDay,listWeek'
			},
			defaultDate: '<?php echo date('Y-m-d'); ?>',
			navLinks: true, // can click day/week names to navigate views
			editable: false,
			weekNumbers: true,
			eventLimit: true, // allow "more" link when too many events
			eventDrop: function(event, delta, revertFunc) {
//				if (!confirm("Are you sure about this change?")) {
//						revertFunc();
//				}
	$.post("../op/op.EditEvent.php", "formtoken=<?php echo createFormKey('editevent'); ?>&eventid="+event.eventid+"&from="+event.start.format()+"&ajax=1", function(response) {
									noty({
										text: response.message,
										type: response.success === true ? 'success' : 'error',
										dismissQueue: true,
										layout: 'topRight',
										theme: 'defaultTheme',
										timeout: 1500,
									});
									$('#calendar').fullCalendar('refetchEvents');
								}, "json");

			},
			eventSources: [
				{
					url: 'out.Calendar.php?action=events',
					editable: true,
					eventStartEditable: false
				},
				{
					url: 'out.Calendar.php?action=events&eventtype=action',
					editable: false
				}
			],
			eventClick: function(event, element) {
				$('div.ajax.iteminfo').trigger('update', {
					documentid: event.documentid,
					version: event.version,
					eventid: event.eventid,
					statusid: event.statusid,
					statuslogid: event.statuslogid,
					fileid: event.fileid,
					callback: function() {
						$("#form1").validate({
							debug: false,
							submitHandler: function(form) {
								$.post("../op/op.EditEvent.php", $(form).serialize()+"&ajax=1", function(response) {
									noty({
										text: response.message,
										type: response.success === true ? 'success' : 'error',
										dismissQueue: true,
										layout: 'topRight',
										theme: 'defaultTheme',
										timeout: 1500,
									});
									$('#calendar').fullCalendar('refetchEvents');
								}, "json");
							},
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
							messages: {
								name: "<?php printMLText("js_no_name");?>",
								comment: "<?php printMLText("js_no_comment");?>"
							},
						});
						$('#fromdate, #todate')
							.datepicker()
							.on('changeDate', function(ev){
								$(ev.currentTarget).datepicker('hide');
							});
					}
				});
				$('div.ajax.itemsperday').html('');

			},
			dayClick: function(date, jsEvent, view) {
				$('div.ajax.itemsperday').trigger('update', {start: date.format()});
				$('div.ajax.iteminfo').html('');
			}
		});
		
	});

/*
function checkForm()
{
	msg = new Array()
	if (document.form1.name.value == "") msg.push("<?php printMLText("js_no_name");?>");
<?php
	if ($strictformcheck) {
?>
	if (document.form1.comment.value == "") msg.push("<?php printMLText("js_no_comment");?>");
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
$(document).ready(function() {
	$('body').on('submit', '#form1', function(ev){
		if(checkForm()) return;
		ev.preventDefault();
	});
});
*/
<?php
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];

		$this->htmlAddHeader('<script type="text/javascript" src="../styles/'.$this->theme.'/validate/jquery.validate.js"></script>'."\n", 'js');
		$this->htmlAddHeader('<script type="text/javascript" src="../styles/'.$this->theme.'/fullcalendar/lib/moment.min.js"></script>'."\n", 'js');
		$this->htmlAddHeader('<script type="text/javascript" src="../styles/'.$this->theme.'/fullcalendar/fullcalendar.min.js"></script>'."\n", 'js');
		$this->htmlAddHeader('<script type="text/javascript" src="../styles/'.$this->theme.'/fullcalendar/locale-all.js"></script>'."\n", 'js');
		$this->htmlAddHeader('<link href="../styles/'.$this->theme.'/fullcalendar/fullcalendar.min.css" rel="stylesheet"></link>'."\n", 'css');
		$this->htmlAddHeader('<link href="../styles/'.$this->theme.'/fullcalendar/fullcalendar.print.min.css" rel="stylesheet" media="print"></link>'."\n", 'css');

		$this->htmlStartPage(getMLText("calendar"));
		$this->globalNavigation();
		$this->contentStart();
//		$this->pageNavigation("", "calendar", array());
?>
<div class="row-fluid" style="margin-bottom: 20px;">
	<div id="calendar" class="span8"></div>
	<div id="docinfo" class="span4">
		<div class="ajax iteminfo" data-view="Calendar" data-action="iteminfo" ></div>
		<div class="ajax itemsperday" data-view="Calendar" data-action="itemsperday" ></div>
	</div>
</div>
<?php
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */

}
?>
