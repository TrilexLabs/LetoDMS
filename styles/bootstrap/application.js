/* Template function which outputs an option in a chzn-select */
chzn_template_func =  function (state) {
	var subtitle = $(state.element).data('subtitle');
	var warning = $(state.element).data('warning');
	var html = '<span>'+state.text+'';
	if(subtitle)
		html += '<br /><i>'+subtitle+'</i>';
	if(warning)
		html += '<br /><span class="label label-warning"><i class="icon-warning-sign"></i></span> '+warning+'';
	html += '</span>';
	var $newstate = $(html);
	return $newstate;
};
$(document).ready( function() {
	/* close popovers when clicking somewhere except in the popover or the
	 * remove icon
	 */
	$('html').on('click', function(e) {
		if (typeof $(e.target).data('original-title') == 'undefined' && !$(e.target).parents().is('.popover.in') && !$(e.target).is('.icon-remove')) {
			$('[data-original-title]').popover('hide');
		}
	});

	$('body').on('hidden', '.modal', function () {
		$(this).removeData('modal');
	});

	$('body').on('touchstart.dropdown', '.dropdown-menu', function (e) { e.stopPropagation(); });

	$('.datepicker, #expirationdate, #fromdate, #todate, #createstartdate, #createenddate, #expirationstartdate, #expirationenddate')
		.datepicker()
		.on('changeDate', function(ev){
			if(ev.date && $(ev.target).data('selectmenu')) {
				$("#"+$(ev.target).data('selectmenu')).val('date');
			}
			$(ev.currentTarget).datepicker('hide');
		});

	$(".chzn-select").select2({
		width: '100%',
		templateResult: chzn_template_func
	});

	/* change the color and length of the bar graph showing the password
	 * strength on each change to the passwod field.
	 */
	$(".pwd").passStrength({ /* {{{ */
		url: "../op/op.Ajax.php",
		onChange: function(data, target) {
			pwsp = 100*data.score;
			$('#'+target+' div.bar').width(pwsp+'%');
			if(data.ok) {
				$('#'+target+' div.bar').removeClass('bar-danger');
				$('#'+target+' div.bar').addClass('bar-success');
			} else {
				$('#'+target+' div.bar').removeClass('bar-success');
				$('#'+target+' div.bar').addClass('bar-danger');
			}
		}
	}); /* }}} */

	/* The typeahead functionality useѕ the rest api */
	$("#searchfield").typeahead({ /* {{{ */
		minLength: 3,
		source: function(query, process) {
			$.get('../restapi/index.php/search', { query: query, limit: 8, mode: 'typeahead' }, function(data) {
					process(data);
			});
		},
		/* updater is called when the item in the list is clicked. It is
		 * actually provided to update the input field, but here we use
		 * it to set the document location. */
		updater: function (item) {
			document.location = "../out/out.Search.php?query=" + encodeURIComponent(item.substring(1));
			return item;
		},
		/* Set a matcher that allows any returned value */
		matcher : function (item) {
			return true;
		},
		highlighter : function (item) {
			if(item.charAt(0) == 'D')
				return '<i class="icon-file"></i> ' + item.substring(1);
			else if(item.charAt(0) == 'F')
				return '<i class="icon-folder-close-alt"></i> ' + item.substring(1);
			else
				return '<i class="icon-search"></i> ' + item.substring(1);
		}
	}); /* }}} */

	/* Document chooser */
	$("[id^=choosedocsearch]").typeahead({ /* {{{ */
		minLength: 3,
		source: function(query, process) {
//		console.log(this.options);
			$.get('../op/op.Ajax.php', { command: 'searchdocument', query: query, limit: 8 }, function(data) {
					process(data);
			});
		},
		/* updater is called when the item in the list is clicked. It is
		 * actually provided to update the input field where you type, but here
		 * we use it to update a second input field with the doc id. */
		updater: function (item) {
			strarr = item.split("#");
			target = this.$element.data('target');
			$('#'+target).attr('value', strarr[0]);
			return strarr[1];
		},
		/* Set a matcher that allows any returned value */
		matcher : function (item) {
			return true;
		},
		highlighter : function (item) {
			strarr = item.split("#");
			return '<i class="icon-file"></i> ' + strarr[1];
		}
	}); /* }}} */

	/* Folder chooser */
	$("[id^=choosefoldersearch]").typeahead({ /* {{{ */
		minLength: 3,
		source: function(query, process) {
//		console.log(this.options);
			$.get('../op/op.Ajax.php', { command: 'searchfolder', query: query, limit: 8 }, function(data) {
					process(data);
			});
		},
		/* updater is called when the item in the list is clicked. It is
		 * actually provided to update the input field, but here we use
		 * it to set the document location. */
		updater: function (item) {
			strarr = item.split("#");
			//console.log(this.$element.data('target'));
			target = this.$element.data('target');
			$('#'+target).attr('value', strarr[0]);
			return strarr[1];
		},
		/* Set a matcher that allows any returned value */
		matcher : function (item) {
			return true;
		},
		highlighter : function (item) {
			strarr = item.split("#");
			return '<i class="icon-folder-close-alt"></i> ' + strarr[1];
		}
	}); /* }}} */

	$('body').on('click', 'a.addtoclipboard', function(ev) { /* {{{ */
		ev.preventDefault();
		attr_rel = $(ev.currentTarget).attr('rel');
		attr_msg = $(ev.currentTarget).attr('msg');
		type = attr_rel.substring(0, 1) == 'F' ? 'folder' : 'document';
		id = attr_rel.substring(1);
		$.get('../op/op.Ajax.php',
			{ command: 'addtoclipboard', type: type, id: id },
			function(data) {
				if(data.success) {
//					$("#main-clipboard").html('Loading').load('../op/op.Ajax.php?command=view&view=mainclipboard')
					$("#main-clipboard").html('Loading').load('../out/out.Clipboard.php?action=mainclipboard')
					//$("#menu-clipboard").html('Loading').load('../op/op.Ajax.php?command=view&view=menuclipboard')
					$("#menu-clipboard").html('Loading').load('../out/out.Clipboard.php?action=menuclipboard')
					noty({
						text: attr_msg,
						type: 'success',
						dismissQueue: true,
						layout: 'topRight',
						theme: 'defaultTheme',
						timeout: 1500,
					});
				} else {
					noty({
						text: data.message,
						type: 'error',
						dismissQueue: true,
						layout: 'topRight',
						theme: 'defaultTheme',
						timeout: 3500,
					});
				}
			},
			'json'
		);
	}); /* }}} */

	$('body').on('click', 'a.removefromclipboard', function(ev){ /* {{{ */
		ev.preventDefault();
		attr_rel = $(ev.currentTarget).attr('rel');
		attr_msg = $(ev.currentTarget).attr('msg');
		type = attr_rel.substring(0, 1) == 'F' ? 'folder' : 'document';
		id = attr_rel.substring(1);
		$.get('../op/op.Ajax.php',
			{ command: 'removefromclipboard', type: type, id: id },
			function(data) {
				if(data.success) {
//					$("#main-clipboard").html('Loading').load('../op/op.Ajax.php?command=view&view=mainclipboard')
					$("#main-clipboard").html('Loading').load('../out/out.Clipboard.php?action=mainclipboard')
					//$("#menu-clipboard").html('Loading').load('../op/op.Ajax.php?command=view&view=menuclipboard')
					$("#menu-clipboard").html('Loading').load('../out/out.Clipboard.php?action=menuclipboard')
					noty({
						text: attr_msg,
						type: 'success',
						dismissQueue: true,
						layout: 'topRight',
						theme: 'defaultTheme',
						timeout: 1500,
					});
				} else {
					noty({
						text: data.message,
						type: 'error',
						dismissQueue: true,
						layout: 'topRight',
						theme: 'defaultTheme',
						timeout: 3500,
					});
				}
			},
			'json'
		);
	}); /* }}} */

	$('body').on('click', 'a.lock-document-btn', function(ev){ /* {{{ */
		ev.preventDefault();
		attr_rel = $(ev.currentTarget).attr('rel');
		attr_msg = $(ev.currentTarget).attr('msg');
		id = attr_rel;
		$.get('../op/op.Ajax.php',
			{ command: 'tooglelockdocument', id: id },
			function(data) {
				if(data.success) {
					//$("#table-row-document-"+id).html('Loading').load('../op/op.Ajax.php?command=view&view=documentlistrow&id='+id)
					$("#table-row-document-"+id).html('Loading').load('../out/out.ViewDocument.php?action=documentlistitem&documentid='+id)
					noty({
						text: attr_msg,
						type: 'success',
						dismissQueue: true,
						layout: 'topRight',
						theme: 'defaultTheme',
						timeout: 1500,
					});
				} else {
					noty({
						text: data.message,
						type: 'error',
						dismissQueue: true,
						layout: 'topRight',
						theme: 'defaultTheme',
						timeout: 3500,
					});
				}
			},
			'json'
		);
	}); /* }}} */

	$('a.movefolder').click(function(ev){ /* {{{ */
		ev.preventDefault();
		attr_source = $(ev.currentTarget).attr('source');
		attr_dest = $(ev.currentTarget).attr('dest');
		attr_msg = $(ev.currentTarget).attr('msg');
		attr_formtoken = $(ev.currentTarget).attr('formtoken');
		$.get('../op/op.Ajax.php',
			{ command: 'movefolder', folderid: attr_source, targetfolderid: attr_dest, formtoken: attr_formtoken },
			function(data) {
				if(data.success) {
					$('#table-row-folder-'+attr_source).hide('slow');
					noty({
						text: data.msg,
						type: data.success ? 'success' : 'error',
						dismissQueue: true,
						layout: 'topRight',
						theme: 'defaultTheme',
						timeout: 1500,
					});
				}
			},
			'json'
		);
	}); /* }}} */

	$('a.movedocument').click(function(ev){ /* {{{ */
		ev.preventDefault();
		attr_source = $(ev.currentTarget).attr('source');
		attr_dest = $(ev.currentTarget).attr('dest');
		attr_msg = $(ev.currentTarget).attr('msg');
		attr_formtoken = $(ev.currentTarget).attr('formtoken');
		$.get('../op/op.Ajax.php',
			{ command: 'movedocument', docid: attr_source, targetfolderid: attr_dest, formtoken: attr_formtoken },
			function(data) {
				if(data.success) {
					$('#table-row-document-'+attr_source).hide('slow');
					noty({
						text: data.msg,
						type: data.success ? 'success' : 'error',
						dismissQueue: true,
						layout: 'topRight',
						theme: 'defaultTheme',
						timeout: 1500,
					});
				}
			},
			'json'
		);
	}); /* }}} */

	$('.send-missing-translation a').click(function(ev){ /* {{{ */
//		console.log($(ev.target).parent().children('[name=missing-lang-key]').val());
//		console.log($(ev.target).parent().children('[name=missing-lang-lang]').val());
//		console.log($(ev.target).parent().children('[name=missing-lang-translation]').val());
		$.ajax('../op/op.Ajax.php', {
			type:"POST",
			async:true,
			dataType:"json",
			data: {
				command: 'submittranslation',
				key: $(ev.target).parent().children('[name=missing-lang-key]').val(),
				lang: $(ev.target).parent().children('[name=missing-lang-lang]').val(),
				phrase: $(ev.target).parent().children('[name=missing-lang-translation]').val()
			},
			success: function(data, textStatus) {
				noty({
					text: data.message,
					type: data.success ? 'success' : 'error',
					dismissQueue: true,
					layout: 'topRight',
					theme: 'defaultTheme',
					timeout: 1500,
				});
			}
		});
	}); /* }}} */

	$('div.ajax').each(function(index) { /* {{{ */
		var element = $(this);
		var url = '';
		var href = element.data('href');
		var base = element.data('base');
		if(typeof base == 'undefined')
			base = '';
		var view = element.data('view');
		var action = element.data('action');
		var query = element.data('query');
		if(view && action) {
			url = LetoDMS_webroot+base+"out/out."+view+".php?action="+action;
			if(query) {
				url += "&"+query;
			}
		} else
			url = href;
		if(!element.data('no-spinner'))
			element.prepend('<div style="position: _absolute; overflow: hidden; background: #f7f7f7; z-index: 1000; height: 200px; width: '+element.width()+'px; opacity: 0.7; display: table;"><div style="display: table-cell;text-align: center; vertical-align: middle; "><img src="../views/bootstrap/images/ajax-loader.gif"></div>');
		$.get(url, function(data) {
			element.html(data);
			$(".chzn-select").select2({
				width: '100%',
				templateResult: chzn_template_func
			});
			$(".pwd").passStrength({ /* {{{ */
				url: "../op/op.Ajax.php",
				onChange: function(data, target) {
					pwsp = 100*data.score;
					$('#'+target+' div.bar').width(pwsp+'%');
					if(data.ok) {
						$('#'+target+' div.bar').removeClass('bar-danger');
						$('#'+target+' div.bar').addClass('bar-success');
					} else {
						$('#'+target+' div.bar').removeClass('bar-success');
						$('#'+target+' div.bar').addClass('bar-danger');
					}
				}
			}); /* }}} */
		});
	}); /* }}} */

	$('div.ajax').on('update', function(event, param1, callback) { /* {{{ */
		var element = $(this);
		var url = '';
		var href = element.data('href');
		var base = element.data('base');
		if(typeof base == 'undefined')
			base = '';
		var view = element.data('view');
		var action = element.data('action');
		if(view && action)
			url = LetoDMS_webroot+base+"out/out."+view+".php?action="+action;
		else
			url = href;
		if(typeof param1 === 'object') {
			for(var key in param1) {
				if(key == 'callback')
					callback = param1[key];
				else
					url += "&"+key+"="+param1[key];
			}
		} else {
			url += "&"+param1;
		}
		element.prepend('<div style="position: absolute; overflow: hidden; background: #f7f7f7; z-index: 1000; height: '+element.height()+'px; width: '+element.width()+'px; opacity: 0.7; display: table;"><div style="display: table-cell;text-align: center; vertical-align: middle; "><img src="../views/bootstrap/images/ajax-loader.gif"></div>');
		$.get(url, function(data) {
			element.html(data);
			$(".chzn-select").select2({
				width: '100%',
				templateResult: chzn_template_func
			});
			$(".pwd").passStrength({ /* {{{ */
				url: "../op/op.Ajax.php",
				onChange: function(data, target) {
					pwsp = 100*data.score;
					$('#'+target+' div.bar').width(pwsp+'%');
					if(data.ok) {
						$('#'+target+' div.bar').removeClass('bar-danger');
						$('#'+target+' div.bar').addClass('bar-success');
					} else {
						$('#'+target+' div.bar').removeClass('bar-success');
						$('#'+target+' div.bar').addClass('bar-danger');
					}
				}
			}); /* }}} */
			if(callback)
				callback.call();
		});
	}); /* }}} */

	$("body").on("click", ".ajax-click", function() { /* {{{ */
		var element = $(this);
		var url = element.data('href')+"?"+element.data('param1');
		$.ajax({
			type: 'GET',
			url: url,
			dataType: 'json',
			success: function(data){
				if(data.success) {
					if(element.data('param1') == 'command=clearclipboard') {
//						$("#main-clipboard").html('Loading').load('../op/op.Ajax.php?command=view&view=mainclipboard')
						$("#main-clipboard").html('Loading').load('../out/out.Clipboard.php?action=mainclipboard')
						//$("#menu-clipboard").html('Loading').load('../op/op.Ajax.php?command=view&view=menuclipboard')
						$("#menu-clipboard").html('Loading').load('../out/out.Clipboard.php?action=menuclipboard')
					}
					noty({
						text: data.message,
						type: 'success',
						dismissQueue: true,
						layout: 'topRight',
						theme: 'defaultTheme',
						timeout: 1500,
					});
				} else {
					noty({
						text: data.message,
						type: 'error',
						dismissQueue: true,
						layout: 'topRight',
						theme: 'defaultTheme',
						timeout: 3500,
					});
				}
			}
		});
	}); /* }}} */

	$('button.history-back').on('click', function(event) { /* {{{ */
		window.history.back();
	}); /* }}} */
});

function onAddClipboard(ev) { /* {{{ */
	ev.preventDefault();
	var source_info = JSON.parse(ev.originalEvent.dataTransfer.getData("text"));
	source_type = source_info.type;
	source_id = source_info.id;
	formtoken = source_info.formtoken;
//	source_type = ev.originalEvent.dataTransfer.getData("type");
//	source_id = ev.originalEvent.dataTransfer.getData("id");
	if(source_type == 'document' || source_type == 'folder') {
		$.get('../op/op.Ajax.php',
			{ command: 'addtoclipboard', type: source_type, id: source_id },
			function(data) {
				if(data.success) {
//					$("#main-clipboard").html('Loading').load('../op/op.Ajax.php?command=view&view=mainclipboard')
					$("#main-clipboard").html('Loading').load('../out/out.Clipboard.php?action=mainclipboard')
					//$("#menu-clipboard").html('Loading').load('../op/op.Ajax.php?command=view&view=menuclipboard')
					$("#menu-clipboard").html('Loading').load('../out/out.Clipboard.php?action=menuclipboard')
					noty({
						text: data.message,
						type: 'success',
						dismissQueue: true,
						layout: 'topRight',
						theme: 'defaultTheme',
						timeout: 1500,
					});
				} else {
					noty({
						text: data.message,
						type: 'error',
						dismissQueue: true,
						layout: 'topRight',
						theme: 'defaultTheme',
						timeout: 3500,
					});
				}
			},
			'json'
		);
		//url = "../op/op.AddToClipboard.php?id="+source_id+"&type="+source_type;
		//document.location = url;
	}
} /* }}} */

(function( LetoDMSUpload, $, undefined ) { /* {{{ */
	var ajaxurl = "../op/op.Ajax.php";
	var editBtnLabel = "Edit";
	var abortBtnLabel = "Abort";
	var maxFileSize = 100000;
	var maxFileSizeMsg = 'File too large';
	var rowCount=0;

	LetoDMSUpload.setUrl = function(url)  {
		ajaxurl = url;
	}

	LetoDMSUpload.setAbortBtnLabel = function(label)  {
		abortBtnLabel = label;
	}

	LetoDMSUpload.setEditBtnLabel = function(label)  {
		editBtnLabel = label;
	}

	LetoDMSUpload.setMaxFileSize = function(size)  {
		maxFileSize = size;
	}

	LetoDMSUpload.setMaxFileSizeMsg = function(msg)  {
		maxFileSizeMsg = msg;
	}

	function sendFileToServer(formData,status) {
		formData.append('command', 'uploaddocument');
		var uploadURL = ajaxurl; //Upload URL
		var extraData ={}; //Extra Data.
		var jqXHR=$.ajax({
			xhr: function() {
			var xhrobj = $.ajaxSettings.xhr();
			if (xhrobj.upload) {
				xhrobj.upload.addEventListener('progress', function(event) {
						var percent = 0;
						var position = event.loaded || event.position;
						var total = event.total;
						if (event.lengthComputable) {
								percent = Math.ceil(position / total * 100);
						}
						//Set progress
						status.setProgress(percent);
					}, false);
				}
				return xhrobj;
			},
			url: uploadURL,
			type: "POST",
			contentType: false,
			dataType:"json",
			processData: false,
			cache: false,
			data: formData,
			success: function(data){
				status.setProgress(100);
				if(data.success) {
					noty({
						text: data.message,
						type: 'success',
						dismissQueue: true,
						layout: 'topRight',
						theme: 'defaultTheme',
						timeout: 1500,
					});
					status.statusbar.after($('<a href="../out/out.EditDocument.php?documentid=' + data.data + '" class="btn btn-mini btn-primary">' + editBtnLabel + '</a>'));
				} else {
					noty({
						text: data.message,
						type: 'error',
						dismissQueue: true,
						layout: 'topRight',
						theme: 'defaultTheme',
						timeout: 3500,
					});
				}
			}
		});

		status.setAbort(jqXHR);
	}

	function createStatusbar(obj) {
		rowCount++;
		var row="odd";
		this.obj = obj;
		if(rowCount %2 ==0) row ="even";
		this.statusbar = $("<div class='statusbar "+row+"'></div>");
		this.filename = $("<div class='filename'></div>").appendTo(this.statusbar);
		this.size = $("<div class='filesize'></div>").appendTo(this.statusbar);
		this.progressBar = $("<div class='progress'><div class='bar bar-success'></div></div>").appendTo(this.statusbar);
		this.abort = $("<div class='btn btn-mini btn-danger'>" + abortBtnLabel + "</div>").appendTo(this.statusbar);
//		$('.statusbar').empty();
		obj.after(this.statusbar);
		this.setFileNameSize = function(name,size) {
			var sizeStr="";
			var sizeKB = size/1024;
			if(parseInt(sizeKB) > 1024) {
				var sizeMB = sizeKB/1024;
				sizeStr = sizeMB.toFixed(2)+" MB";
			} else {
				sizeStr = sizeKB.toFixed(2)+" KB";
			}

			this.filename.html(name);
			this.size.html(sizeStr);
		}
		this.setProgress = function(progress) {
			var progressBarWidth =progress*this.progressBar.width()/ 100;
			this.progressBar.find('div').animate({ width: progressBarWidth }, 10).html(progress + "% ");
			if(parseInt(progress) >= 100) {
				this.abort.hide();
			}
		}
		this.setAbort = function(jqxhr) {
			var sb = this.statusbar;
			this.abort.click(function() {
				jqxhr.abort();
				sb.hide();
			});
		}
	}

	LetoDMSUpload.handleFileUpload = function(files,obj) {
		var target = obj.data('target');
		if(target) {
			for (var i = 0; i < files.length; i++) {
				if(files[i].size <= maxFileSize) {
					var fd = new FormData();
					fd.append('folderid', target);
					fd.append('formtoken', obj.data('formtoken'));
					fd.append('userfile', files[i]);
//					fd.append('path', files[i].webkitRelativePath);

					var status = new createStatusbar(obj);
					status.setFileNameSize(files[i].name,files[i].size);
					sendFileToServer(fd,status);
				} else {
					noty({
						text: maxFileSizeMsg + '<br /><em>' + files[i].name + ' (' + files[i].size + ' Bytes)</em>',
						type: 'error',
						dismissQueue: true,
						layout: 'topRight',
						theme: 'defaultTheme',
						timeout: 5000,
					});
				}
			}
		}
	}
}( window.LetoDMSUpload = window.LetoDMSUpload || {}, jQuery )); /* }}} */

$(document).ready(function() { /* {{{ */
	var obj = $("#dragandrophandler");
	obj.on('dragenter', function (e) {
		e.stopPropagation();
		e.preventDefault();
		$(this).css('border', '2px dashed #0B85A1');
	});
	obj.on('dragleave', function (e) {
		$(this).css('border', '0px solid white');
	});
	obj.on('dragover', function (e) {
		e.stopPropagation();
		e.preventDefault();
	});
	obj.on('drop', function (e) {
		$(this).css('border', '0px dotted #0B85A1');
		e.preventDefault();
		var files = e.originalEvent.dataTransfer.files;

		//We need to send dropped files to Server
		LetoDMSUpload.handleFileUpload(files,obj);
	});

	$(document).on('dragenter', '.table-row-folder', function (e) {
		e.stopPropagation();
		e.preventDefault();
		$(e.currentTarget).css('border', '2px dashed #0B85A1');
	});
	$(document).on('dragleave', '.table-row-folder', function (e) {
		e.stopPropagation();
		e.preventDefault();
		$(e.currentTarget).css('border', '0px solid white');
	});
	$(document).on('dragover', '.table-row-folder', function (e) {
		e.stopPropagation();
		e.preventDefault();
	});
	$(document).on('drop', '.table-row-folder', function (e) {
		e.preventDefault();
		e.stopPropagation();
		$(e.currentTarget).css('border', '0px solid white');
		attr_rel = $(e.currentTarget).attr('rel');
		target_type = attr_rel.split("_")[0];
		target_id = attr_rel.split("_")[1];
		console.log(e.originalEvent.dataTransfer.getData("text"));
		var source_info = JSON.parse(e.originalEvent.dataTransfer.getData("text"));
		source_type = source_info.type;
		source_id = source_info.id;
		formtoken = source_info.formtoken;
//		source_type = e.originalEvent.dataTransfer.getData("type");
//		source_id = e.originalEvent.dataTransfer.getData("id");
//		formtoken = e.originalEvent.dataTransfer.getData("formtoken");
		if(source_type == 'document') {
			bootbox.dialog(trans.confirm_move_document, [{
				"label" : "<i class='icon-remove'></i> "+trans.move_document,
				"class" : "btn-danger",
				"callback": function() {
					$.get('../op/op.Ajax.php',
						{ command: 'movedocument', docid: source_id, targetfolderid: target_id, formtoken: formtoken },
						function(data) {
							if(data.success) {
								$('#table-row-document-'+source_id).hide('slow');
								noty({
									text: data.message,
									type: 'success',
									dismissQueue: true,
									layout: 'topRight',
									theme: 'defaultTheme',
									timeout: 1500,
								});
							} else {
								noty({
									text: data.message,
									type: 'error',
									dismissQueue: true,
									layout: 'topRight',
									theme: 'defaultTheme',
									timeout: 3500,
								});
							}
						},
						'json'
					);
				}
			}, {
				"label" : trans.cancel,
				"class" : "btn-cancel",
				"callback": function() {
				}
			}]);

			url = "../out/out.MoveDocument.php?documentid="+source_id+"&targetid="+target_id;
//			document.location = url;
		} else if(source_type == 'folder' && source_id != target_id) {
			bootbox.dialog(trans.confirm_move_folder, [{
				"label" : "<i class='icon-remove'></i> "+trans.move_folder,
				"class" : "btn-danger",
				"callback": function() {
					$.get('../op/op.Ajax.php',
						{ command: 'movefolder', folderid: source_id, targetfolderid: target_id, formtoken: formtoken },
						function(data) {
							if(data.success) {
								$('#table-row-folder-'+source_id).hide('slow');
								noty({
									text: data.message,
									type: 'success',
									dismissQueue: true,
									layout: 'topRight',
									theme: 'defaultTheme',
									timeout: 1500,
								});
							} else {
								noty({
									text: data.message,
									type: 'error',
									dismissQueue: true,
									layout: 'topRight',
									theme: 'defaultTheme',
									timeout: 3500,
								});
							}
						},
						'json'
					);
				}
			}, {
				"label" : trans.cancel,
				"class" : "btn-cancel",
				"callback": function() {
				}
			}]);

			url = "../out/out.MoveFolder.php?folderid="+source_id+"&targetid="+target_id;
//			document.location = url;
		}
	});
	$(document).on('dragstart', '.table-row-folder', function (e) {
		attr_rel = $(e.target).attr('rel');
		if(typeof attr_rel == 'undefined')
			return;
		var dragStartInfo = {
			id : attr_rel.split("_")[1],
			type : "folder",
			formtoken : $(e.target).attr('formtoken')
		};
		e.originalEvent.dataTransfer.setData("text", JSON.stringify(dragStartInfo));
//		e.originalEvent.dataTransfer.setData("id", attr_rel.split("_")[1]);
//		e.originalEvent.dataTransfer.setData("type","folder");
//		e.originalEvent.dataTransfer.setData("formtoken", $(e.target).attr('formtoken'));
	});

	$(document).on('dragstart', '.table-row-document', function (e) {
		attr_rel = $(e.target).attr('rel');
		if(typeof attr_rel == 'undefined')
			return;
		var dragStartInfo = {
			id : attr_rel.split("_")[1],
			type : "document",
			formtoken : $(e.target).attr('formtoken')
		};
		e.originalEvent.dataTransfer.setData("text", JSON.stringify(dragStartInfo));
//		e.originalEvent.dataTransfer.setData("id", attr_rel.split("_")[1]);
//		e.originalEvent.dataTransfer.setData("type","document");
//		e.originalEvent.dataTransfer.setData("formtoken", $(e.target).attr('formtoken'));
	});

	/* Dropping item on alert below clipboard */
	$(document).on('dragenter', '.add-clipboard-area', function (e) {
		e.stopPropagation();
		e.preventDefault();
		$(this).css('border', '2px dashed #0B85A1');
	});
	$(document).on('dragleave', '.add-clipboard-area', function (e) {
		$(this).css('border', '0px solid white');
	});
	$(document).on('dragover', '.add-clipboard-area', function (e) {
		e.preventDefault();
	});
	$(document).on('drop', '.add-clipboard-area', function (e) {
		$(this).css('border', '0px dotted #0B85A1');
		onAddClipboard(e);
	});

	$("#jqtree").on('dragenter', function (e) {
		attr_rel = $(e.srcElement).attr('rel');
		if(typeof attr_rel == 'undefined')
			return;
		$(e.target).parent().css('border', '2px dashed #0B85A1');
		e.stopPropagation();
		e.preventDefault();
	});
	$("#jqtree").on('dragleave', function (e) {
		attr_rel = $(e.srcElement).attr('rel');
		if(typeof attr_rel == 'undefined')
			return;
		$(e.target).parent().css('border', '0px solid white');
		e.stopPropagation();
		e.preventDefault();
	});
	$("#jqtree").on('dragover', function (e) {
		e.stopPropagation();
		e.preventDefault();
	});
	$("#jqtree").on('drop', function (e) {
		e.stopPropagation();
		e.preventDefault();
		attr_rel = $(e.target).attr('rel');
		if(typeof attr_rel == 'undefined')
			return;
		$(e.target).parent().css('border', '0px solid white');
		target_type = attr_rel.split("_")[0];
		target_id = attr_rel.split("_")[1];
		var source_info = JSON.parse(e.originalEvent.dataTransfer.getData("text"));
		source_type = source_info.type;
		source_id = source_info.id;
		formtoken = source_info.formtoken;
//		source_type = e.originalEvent.dataTransfer.getData("type");
//		source_id = e.originalEvent.dataTransfer.getData("id");
//		formtoken = e.originalEvent.dataTransfer.getData("formtoken");
		if(source_type == 'document') {
			bootbox.dialog(trans.confirm_move_document, [{
				"label" : "<i class='icon-remove'></i> "+trans.move_document,
				"class" : "btn-danger",
				"callback": function() {
					$.get('../op/op.Ajax.php',
						{ command: 'movedocument', docid: source_id, targetfolderid: target_id, formtoken: formtoken },
						function(data) {
							if(data.success) {
								$('#table-row-document-'+source_id).hide('slow');
								noty({
									text: data.message,
									type: 'success',
									dismissQueue: true,
									layout: 'topRight',
									theme: 'defaultTheme',
									timeout: 1500,
								});
							} else {
								noty({
									text: data.message,
									type: 'error',
									dismissQueue: true,
									layout: 'topRight',
									theme: 'defaultTheme',
									timeout: 3500,
								});
							}
						},
						'json'
					);
				}
			}, {
				"label" : trans.cancel,
				"class" : "btn-cancel",
				"callback": function() {
				}
			}]);

			url = "../out/out.MoveDocument.php?documentid="+source_id+"&targetid="+target_id;
//			document.location = url;
		} else if(source_type == 'folder' && source_id != target_id) {
			bootbox.dialog(trans.confirm_move_folder, [{
				"label" : "<i class='icon-remove'></i> "+trans.move_folder,
				"class" : "btn-danger",
				"callback": function() {
					$.get('../op/op.Ajax.php',
						{ command: 'movefolder', folderid: source_id, targetfolderid: target_id, formtoken: formtoken },
						function(data) {
							if(data.success) {
								$('#table-row-folder-'+source_id).hide('slow');
								noty({
									text: data.message,
									type: 'success',
									dismissQueue: true,
									layout: 'topRight',
									theme: 'defaultTheme',
									timeout: 1500,
								});
							} else {
								noty({
									text: data.message,
									type: 'error',
									dismissQueue: true,
									layout: 'topRight',
									theme: 'defaultTheme',
									timeout: 3500,
								});
							}
						},
						'json'
					);
				}
			}, {
				"label" : trans.cancel,
				"class" : "btn-cancel",
				"callback": function() {
				}
			}]);

			url = "../out/out.MoveFolder.php?folderid="+source_id+"&targetid="+target_id;
//			document.location = url;
		}
	});

	$('div.splash').each(function(index) {
		var element = $(this);
		var msgtype = element.data('type');
		var timeout = element.data('timeout');
		var msg = element.text();
		noty({
			text: msg,
			type: msgtype,
			dismissQueue: true,
			layout: 'topRight',
			theme: 'defaultTheme',
			timeout: (typeof timeout == 'undefined' ? 1500 : timeout),
		});
	});

	$("body").on("click", "span.openpopupbox", function(e) {
		$(""+$(e.target).data("href")).toggle();
	});
	$("body").on("click", "span.openpopupbox i", function(e) {
		$(e.target).parent().click();
	});
	$("body").on("click", "span.closepopupbox", function(e) {
		$(this).parent().hide();
	});
}); /* }}} */

	var approval_count, review_count, workflow_count;
	var checkTasks = function() {
		$.ajax({url: '../out/out.Tasks.php',
			type: 'GET',
			dataType: "json",
			data: {action: 'mytasks'},
			success: function(data) {
				if(data) {
					if((typeof data.data.approval != 'undefined' && approval_count != data.data.approval.length) ||
						 (typeof data.data.review != 'undefined' && review_count != data.data.review.length) ||
						 (typeof data.data.workflow != 'undefined' && workflow_count != data.data.workflow.length)) {
						$("#menu-tasks > ul > li").html('Loading').hide().load('../out/out.Tasks.php?action=menutasks').fadeIn('500')
						approval_count = typeof data.data.approval != 'undefined' ? data.data.approval.length : 0;
						review_count = typeof data.data.review != 'undefined' ? data.data.review.length : 0;
						workflow_count = typeof data.data.workflow != 'undefined' ? data.data.workflow.length : 0;
					}
				}
			},
			timeout: 3000
		}); 
		timeOutId = setTimeout(checkTasks, 30000);
	}

