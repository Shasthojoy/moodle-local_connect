var Connect = (function() {

	Connect.prototype.push_timeout = null;
	Connect.prototype.merge_timeout = null;
	Connect.prototype.ui_timeout = null;

	Connect.prototype.selectedDeliveries = [];
	Connect.prototype.complete_selected = [];
	Connect.prototype.delivery_list = '';
	Connect.prototype.count = 0;

	function Connect(options) {

		this.json = options.tabledata;
		this.buttons = options.buttons;
		this.formEl = options.formEl;

		//First check if we have courses and error if not.
		if (this.json.length === 0){
				jQuery.unblockUI();
				$('#dialog_error').html('You do not have access to any deliveries at this time.');
				$("#dialog_error").dialog("open");
				return
		}

    var existing_courses = _.map(
        _.filter( this.json,
          function(e) {
            return e.mid != null && e.mid != 0;
          } ),
        function(e) {
          return e.module_code.replace(/(.*)\s.*/,'$1');
        });

		// Taking json and mapping into usable data
		this.tabledata = _.map(this.json, function(val) {
			var state_zero = val.mid != null && val.mid != 0  ? 'created_in_moodle' : 'unprocessed';
			var end = parseInt(val.module_week_beginning, 10) + parseInt(val.module_length, 10) - 1;
			var duration = val.module_week_beginning + '-' + end;
			var name = state_zero.split('_').join(' ');
			var sink_deleted = val.deleted == '1';
			var toolbar = ' ';
			var same_module_code_created = false;

			if (state_zero === 'created_in_moodle') {

				if ((typeof val.children != "undefined") && val.children.length > 0) {
					sink_deleted = sink_deleted || _.any(val.children, function(i) {
						return i.sink_deleted;
					});
					toolbar += '<div class="child_expand open toolbar_link"></div>';
					val.student_count = _.reduce(val.children, function(memo,child) {
						return memo + child.student_count;
					}, 0 );
				}

				toolbar += '<div class="unlink_row toolbar_link"></div>';
				//toolbar += '<div class="edit_row toolbar_link"></div>'
				toolbar += '<a href=" '+ M.cfg.wwwroot + '/course/view.php?id='+ val.mid +'" target="_blank" class="created_link toolbar_link"></a>';
				
			} else {
				var prod = _.find( existing_courses, function(e) { return e == val.module_code; } );
				same_module_code_created = typeof(prod) != "undefined";
			}

			var state = '<div class="status_'+state_zero+' '+(sink_deleted?'sink_deleted':'')+' '+(same_module_code_created?'same_module_code_created':'')+'">'+name+'</div>';

			return [val.id, state, val.module_code, val.module_title, val.campus, 
					duration, val.module_version, toolbar, val.module_delivery_key, val.session_code];
		});

		//Initiate the datatable 
		this.datatableInit(options.tableEl);
		this.filtersInit(options.statusEl, options.searchEl);
		this.buttonsInit();
	}

	Connect.prototype.datatableInit = function(element) {
		var _this = this;

		//Datatables initialisation
		this.oTable = element.dataTable({
			"bProcessing": true,
			"aaData": this.tabledata,
			"bAutoWidth": false,
			"aoColumns": [
				{'sClass': 'id', "bSearchable": false},
				{'sClass': 'status'},
				{'sClass': 'code'},
				{'sClass': 'name', "sWidth": "20%"},
				{'sClass': 'campus', "sWidth": "20%"},
				{'sClass': 'duration'},
				{'sClass': 'version'},
				{'sClass': 'toolbar'}

			],
			"aoColumnDefs": [
				{ "bSearchable": true, "bVisible": false, "aTargets": [ 0 ] }
			],
			"oLanguage": {
				"sSearch": "Search all columns:"
			},
			"iDisplayLength": 20,
			"bPaginate" : true,
			"sPaginationType": "full_numbers",
			"fnCreatedRow": function( nRow, aData, iDataIndex ) {
				$(nRow).attr('ident', aData[0]);
				$(nRow).attr('deliverykey', aData[10]);
				$(nRow).attr('sessioncode', aData[11]);
				$(nRow).addClass('parent')
			},
			"fnInitComplete": function(oSettings) {
				jQuery.unblockUI();	
				
				$('#datable_wrapper').prepend('<div id="statusbox"></div>');
				$('#statusbox').hide();
			},
			"fnDrawCallback" : function() {
				_this.buttons.rowsEl = $(_this.buttons.rowsSel);
				_this.buttons.child = $(_this.buttons.childSel);
				_this.buttons.unlinkRow = $(_this.buttons.unlinkRowSel);
				_this.buttons.unlinkChild = $(_this.buttons.unlinkChildSel);
			}
		});
	};

	Connect.prototype.filtersInit = function(statusel, searchel) {

		this.oTable.columnFilter({
			aoColumns: [
				{},
				{
					type: 'checkbox',
					values: [ 'unprocessed', 'created_in_moodle', 'failed_in_moodle', 'scheduled', 'processing' ]
				},
				{},
				{},
				{},
				{},
				{}
			]
		});

		$('#unprocessed').prop("checked", true);
		$('#status_unprocessed').prop("checked", true).trigger('change');

		statusel.change(function() {
			$('#statusbox').hide();
			if($(this).is(':checked')) {
				$('#status_' + $(this).val()).prop("checked", true).trigger('change');
			} else {
				$('#status_' + $(this).val()).prop("checked", false).trigger('change');
			}
		});

		var _this = this;

		searchel.keyup(function() {
			if($(this).val().length > 1) {
				_this.oTable.fnFilter($(this).val());
			} else {
				_this.oTable.fnFilter('')
			}
		});
	};

	Connect.prototype.buttonsInit = function(buttons) {

		var _this = this;

		//Setting the click event for table rows
		this.buttons.rowsEl.live('click', function(e) {
			clearTimeout(this.push_timeout);
			clearTimeout(this.ui_timeout);
			clearTimeout(this.merge_timeout);
			if(e.target === $('.toolbar a',this)[0] || e.target === $('.toolbar div',this)[0]){
				return true;
			}
			_this.rowSelect(this);

			_this.processRowSelect();
		});

		this.buttons.selAll.click(function() {
			var rows = _this.oTable.fnGetFilteredNodes();
			_this.rowSelectAll(rows, true);
			_this.processRowSelect();
			
		});

		this.buttons.deSelAll.click(function() {
			var rows = _this.oTable.fnGetNodes();
			_this.rowSelectAll(rows, false);
			_this.processRowSelect();
		});

		this.buttons.pushBtn.click(function() {
			_this.pushDeliveries();
		});

		this.buttons.mergeBtn.click(function() {
			_this.mergeDeliveries();
		});

		$('#datable th').click(function() {
			$('#statusbox').fadeOut('fast');
		});

		this.buttons.edit.live('click', function() {
			var id = $(this).closest('tr').attr('ident');

			_this.edit_row(id, _this.json, null);
		});

		this.buttons.child.live('click', function() {
			var id = $(this).closest('tr').attr('ident');

			var row = _.filter(_this.json, function (r) { 
				return r.id === id;
			});

			var nTr = $(this).parents('tr')[0];

			if(_this.oTable.fnIsOpen(nTr)) {
				$(this).removeClass('close').addClass('open');
				_this.oTable.fnClose(nTr);
			} else {
				$(this).removeClass('open').addClass('close');
				_this.oTable.fnOpen(nTr, _this.fnFormatDetails(row[0], id), 'merged' );
				_this.buttons.unlinkChild = $(_this.buttons.unlinkChildSel);
			}
		});

		this.buttons.unlinkRow.live('click', function() {
			_this.unlink_row(this);
		});

		this.buttons.unlinkChild.live('click', function() {
			_this.unlink_child(this);
		});

		this.buttons.listToggle.click(function() {
			$(this).toggleClass('display_list_open');
			if($('#jobs ul').hasClass('visible')) {
				$('#jobs ul').slideUp('fast', function() {
					$(this).html('').removeClass('visible');
				});
				$('div', this).show();
				$('button', this).html('show deliveries');
			} else {
				$('div', this).hide();
				$('button', this).html('hide deliveries');
				if(_this.delivery_list === '') {
					_this.delivery_list = '<li class="empty_deliv">no items have been selected</li>';
				}
				$('#jobs ul').html(_this.delivery_list).hide().slideDown('fast').addClass('visible');
			}
		});
	};

	Connect.prototype.fnFormatDetails = function(row, par_chksm) {
    var _this = this;

		var sOut = '<table par_ident="'+ par_chksm +'">';
			sOut += '<tr>';
			sOut += '<th>Code</th>';
			sOut += '<th>Name</th>';
			sOut += '<th>Campus</th>';
			sOut += '<th>Duration</th>';
			sOut += '<th>Version</th>';
			sOut += '<th>Action</th>';
			sOut += '</tr>';

			$.each(row.children, function(i) {
				var child = row.children[i];
				var end = parseInt(child.module_week_beginning, 10) + parseInt(child.module_length, 10) - 1;
				var duration = child.module_week_beginning + ( isNaN(end) ? '' : '-' + end );
				sOut += '<tr ident="'+ child.id +'">';
				sOut += '<td class="code"><div class="'
									+ (child.deleted == '1' ? 'sink_deleted' : '' )
									+ '">' + child.module_code
									+ '</div></td>';
				sOut += '<td class="name">'+ child.module_title +'</td>';
				sOut += '<td class="campus">' + child.campus +'</td>';
				sOut += '<td class="duration">'+ duration +'</td>';
				sOut += '<td class="version">'+ child.module_version +'</td>';
				if(row.children.length > 1) {
					sOut += '<td class="toolbar"><div class="unlink_child"></div></td>';
				} else {
					sOut += '<td class="toolbar"></td>';
				}

				sOut += '</tr>';
			});
			sOut += '</table>';

			return sOut;
	};

	Connect.prototype.statusbox = function(el, message) {
		var position = $(el).position();
		if($('#statusbox').is(':visible') && $('#statusbox').position().top === position.top) {
			return
		}
		// kill a statusHide timeout if it exists
		if (this.statusHide) clearTimeout(this.statusHide);

		$('#statusbox').stop(true, true).html(message).css({
			'top' : position.top
		}).click(function() {
			$(this).hide();
			clearTimeout(statusHide);
		}).fadeIn('fast');//.fadeIn('fast').delay(500).fadeOut('fast');

		statusHide = setTimeout(function() {
			$('#statusbox').fadeOut('fast');
		}, 5000)
	};

	Connect.prototype.rowSelect = function(element) {

		var name = $('.status div', element).html().split('_').join(' ');

		//get the row checksum
		var ident = $(element).attr('ident');

		if($('.status div', element).html() === 'unprocessed' || $('.status div', element).html() === 'failed in moodle') {
			if($(element).hasClass('row_selected')) {
				$(element).removeClass('row_selected');
				this.selectedDeliveries = _.reject(this.selectedDeliveries, function(num) { return num === ident; });
			} else {
				$(element).addClass('row_selected');
				this.selectedDeliveries.push($(element).attr('ident'));
			}
		} else if($('.status div', element).html() === 'created in moodle') {
			if($(element).hasClass('row_selected')) {
				$(element).removeClass('row_selected');
				this.selectedDeliveries = _.reject(this.selectedDeliveries, function(num) { return num === ident; });
				this.complete_selected = _.reject(this.complete_selected, function(num) { return num === ident; });

			} else {
				$(element).addClass('row_selected');
				this.selectedDeliveries.push($(element).attr('ident'));
				this.complete_selected.push($(element).attr('ident'));
			}
		} else {
			this.statusbox(element, 'Error: you cannot push a delivery with a status of ' + name);
		}
	};

	Connect.prototype.rowSelectAll = function(els, sel_all) {

		var _this = this;

		$.each(els, function(el) {
			var ident = $(els[el]).attr('ident');
			if($('.status div', els[el]).html() === 'unprocessed' || $('.status div', els[el]).html() === 'failed in moodle') {
				if(sel_all === true && $(els[el]).hasClass('row_selected') === false) {
					$(els[el]).addClass('row_selected');
					_this.selectedDeliveries.push($(els[el]).attr('ident'));
				} else if(sel_all === false) {
					$(els[el]).removeClass('row_selected');
					_this.selectedDeliveries = _.reject(_this.selectedDeliveries, function(num) { return num === ident; });
				}
			} else if($('.status div', els[el]).html() === 'created in moodle') {
				if(sel_all === true && $(els[el]).hasClass('row_selected') === false) {
					$(els[el]).addClass('row_selected');
					_this.selectedDeliveries.push($(els[el]).attr('ident'));
					_this.complete_selected.push($(els[el]).attr('ident'));
				} else if(sel_all === false) {
					$(els[el]).removeClass('row_selected');
					_this.selectedDeliveries = _.reject(_this.selectedDeliveries, function(num) { return num === ident; });
					_this.complete_selected = _.reject(_this.complete_selected, function(num) { return num === ident; });
				}
			}
		});
	};

	Connect.prototype.processRowSelect = function() {
		//loops through the json data and finds the entries for the selected deliveries. Then grabs the short codes and appends it to a 
		//string as a list element.
		this.delivery_list = '';
		var _this = this;
		
		$(this.selectedDeliveries).each(function(index) {
			var	delivery = _this.selectedDeliveries[index];
			
			var row = _.find(_this.tabledata, function (row) {
				if(row[0] === delivery) {
					return row;
				}
			});
			
			_this.delivery_list = _this.delivery_list + '<li>' + row[2] + '</li>';
		});

		if(this.delivery_list === '') {
			this.delivery_list = '<li class="empty_deliv">no items have been selected</li>';
			
		}

		if($('#jobs ul').hasClass('visible')) {
			$('#jobs ul').html(_this.delivery_list);
		}

		//gets the number of selected deliveries and appends it to the dom  
		this.count = this.selectedDeliveries.length;
		$('#job_number').html(this.count);

		if(this.selectedDeliveries.length > 1) {
			if(this.complete_selected.length > 1) {
				this.buttons.pushBtn.prop('disabled', true).html('Can\'t push').removeClass();
				this.buttons.mergeBtn.prop('disabled', true).html('Can\'t merge').removeClass();
			}else if(this.complete_selected.length === 1) {
				this.buttons.pushBtn.prop('disabled', true).html('Can\'t push').removeClass();
				this.buttons.mergeBtn.prop('disabled', false).html('<span>Merge</span> to Moodle').removeClass();
			} else {
				this.buttons.pushBtn.prop('disabled', false).html('<span>Push</span> to Moodle').removeClass();
				this.buttons.mergeBtn.prop('disabled', false).html('<span>Merge</span> to Moodle').removeClass();
			}
		} else if(this.selectedDeliveries.length === 1) {
			if(this.complete_selected.length !== 0) {
				this.buttons.pushBtn.prop('disabled', true).html('Can\'t push').removeClass();
				this.buttons.mergeBtn.prop('disabled', true).html('Can\'t merge').removeClass();
			} else {
				this.buttons.pushBtn.prop('disabled', false).html('<span>Edit</span> to Moodle').removeClass().addClass('edit_to_moodle');
				this.buttons.mergeBtn.prop('disabled', true).html('Can\'t merge').removeClass();
			}
		} else {
			this.buttons.pushBtn.prop('disabled', true).html('No selection').removeClass();
			this.buttons.mergeBtn.prop('disabled', true).html('No selection').removeClass();
		}	
	};

	Connect.prototype.pushDeliveries = function() {
		
		var _this = this;

		if(this.buttons.pushBtn.hasClass('edit_to_moodle')) {

			//var button = new ButtonLoader(this.buttons.pushBtn, 'Saving');
			//this.buttons.pushBtn.prop('disabled', true).addClass('loading');
			//button.start();
			//button.disable(this.buttons.pushBtn);

			this.edit_row(this.selectedDeliveries[0]);

		} else {

			// callback for actually doing the push stuff
			var doPush = function() {

				// hide dialog immediately as we're not using it to feedback
				$("#dialog-confirm").dialog("close");

				var button = new ButtonLoader(_this.buttons.pushBtn, 'Saving');
				_this.buttons.pushBtn.prop('disabled', true).addClass('loading');
				button.start();
				button.disable(_this.buttons.pushBtn);

				var pushees = _.filter(_this.json, function (row) { 
					if(_.indexOf(_this.selectedDeliveries, row.id) !== -1){
						return row;
					}
				});

				var date = '(' + Date.parse(pushees[0].session_code).previous().year().toString('yyyy');
				date += '/' + Date.parse(pushees[0].session_code).toString('yyyy') + ')';

				var data = [];

				$.each(pushees, function(i) {

					//var synopsis = $.trim(pushees[i].synopsis).substring(0,500).split(" ").slice(0, -1).join(" ") + "...";
					var synopsis = $.trim(pushees[i].synopsis);

					var obj = {
						id: pushees[i].id
					};

					data.push(obj);
				});

				var status = _this.push_selected(data, button, false, function(){
					clearTimeout(_this.push_timeout);
					_this.push_timeout = setTimeout(function() {
						$(button.element[0]).removeClass();
						_this.processRowSelect();
					}, 3000); 
				}, function(xhr) { // error callback
					var problems = JSON.parse(xhr.responseText);
					var error_ids = [];
					var errors = '<div id="push_notifications" class="warn">Note: modules that have not errored have still been scheduled.</div>';
					errors += '<ul id="error_ui">';
					
					$.each(problems, function(i) {
						
						var row = _.filter(data, function (r) { 
							return r.id === problems[i].id;
						});

						error_ids.push(row[0].id);

						switch(problems[i].error_code) {
							case 'duplicate':
								errors += '<li class="warning"><span class="type">WARNING: Duplicate</span> - <span class="cours_dets">';
								errors += row[0].code + ': ' + row[0].title + '</span>. Please merge or push through individually';
								errors += '</li>';
							break;
							case 'could_not_schedule':
								errors += '<li class="error"><span class="type">ERROR: Already scheduled</span> - <span class="cours_dets">';
								errors += row[0].code + ': ' + row[0].title + '</span>. Please merge or push through individually';
								errors += '</li>';
							break;
							case 'category_is_zero':
								errors += '<li class="error"><span class="type">ERROR: Category not found</span> - <span class="cours_dets">';
								errors += row[0].code + ': ' + row[0].title + '</span>. Push through individually to choose a relevant category';
								errors += '</li>';
							break;
						}
					});

					errors += '</ul>';
												
					$('#dialog_error').html(errors);
					$("#dialog_error").dialog({
						width: 500,
						height: 400
					}).dialog("open");
					
					_this.selectedDeliveries = _.without(_this.selectedDeliveries, error_ids);
					_this.buttons.rowsEl.removeClass('row_selected');
					$(_this.selectedDeliveries).each(function(i) {
						var row = $('#datable tbody tr[ident='+_this.selectedDeliveries[i]+']');
						var aPos = _this.oTable.fnGetPosition(row[0]);
						_this.oTable.fnUpdate('<div class="status_scheduled">scheduled</div>', row[0], 1, false)
					});

					_this.oTable.fnDraw();

					_this.selectedDeliveries = [];
					_this.count = 0;
					$('#job_number').html(_this.count);
					_this.delivery_list = '<li class="empty_deliv">no items have been selected</li>';
					if($('#jobs ul').hasClass('visible')) {
						$('#jobs ul').html(_this.delivery_list);
					}

					$(error_ids).each(function(i) {
						var dom_row = $('#datable tbody tr[ident='+error_ids[i]+']')[0];
						_this.rowSelect(dom_row, false);

					});

					clearTimeout(_this.push_timeout);
					_this.push_timeout = setTimeout(function() {
						$(button.element[0]).removeClass();
						_this.processRowSelect();
					}, 2000);
				});
			};

			// show confirmation
			$('#dialog-confirm').dialog({
				title: 'Confirm push',
				buttons: {
					"OK": function() {
						doPush();
					},
					"Cancel": function() {
						$(this).dialog('close');
					}
				}
			}).dialog('open');
		}
	};

	Connect.prototype.edit_row = function(id) {
		
		var _this = this;

		var row = _.filter(this.json, function (r) { 
				return r.id === id;
		});
		if(row[0].mid == 0 || row[0].mid == null) {
			var row_unprocessed = true;
			var date = '(' + Date.parse(row[0].session_code).previous().year().toString('yyyy');
			date += '/' + Date.parse(row[0].session_code).toString('yyyy') + ')';
			var synopsis = $.trim(row[0].synopsis);

			var shortname = row[0].module_code;
			var fullname = row[0].module_title + (row[0].module_code.indexOf(date) > 0 ? '' : ' ' + date);
			var tempshortname = "";

			var prod = _.find(_this.json, function (r){
				if(parseInt(r.mid) > 0) {
					tempshortname = r.module_code;
					return tempshortname == shortname;
				}
			});

			if(typeof prod != "undefined") {
				_this.formEl.shrtNmExtTd.html('<input type="text" name="shortname_ext" id="shortname_ext" class="text ui-widget-content ui-corner-all" size="3" maxlength="3"/>');
				_this.formEl.notes.removeClass().addClass('alert alert-warning').text('Shortname may be in use. Please provide a three letter identifier.');
				_this.formEl.shortNameExt.parent().addClass('has-warning');
			}
		} else {
			var synopsis = row[0].synopsis;
			var shortname = row[0].module_code;
			var fullname = row[0].module_title;
		}

		var ui_sub;
		//Appends the data created to the form
		this.formEl.shortName.val(shortname);
		this.formEl.fullName.val(fullname);
		this.formEl.synopsis.val(synopsis);
		this.formEl.cat.val(row[0].category);
		$( "#dialog-form" ).dialog({ 
			title: 'Choose details',
			close: function(event, ui) {
				if(row_unprocessed ===true) {
					//button.stop();
					//button.updateText('<span>Edit</span> to Moodle');
					//$('#push_deliveries').removeClass().addClass('edit_to_moodle');
				}

				_this.formEl.shrtNmExtTd.html('');
				_this.formEl.notes.html('');
				_this.formEl.notes.removeClass();
			},
			open: function(event, ui) {
				ui_sub = new ButtonLoader($('.ui-dialog-buttonpane').find('button:contains("Push to moodle")'), 'Saving');
			},
			buttons: {
				"Push to moodle": function() {
					
					ui_sub.disable($('.ui-dialog-buttonpane').find('button:contains("Push to moodle")'));
					ui_sub.start();

          			if (!_.isEmpty($('#shortname_ext_td').children()) && $('#shortname_ext_td').children().length > 0) {
						if(_.isEmpty($('#shortname_ext').val())) {
							_this.formEl.notes.removeClass('alert alert-warning').addClass('error').text('Please provide a three letter identifier');
							_this.formEl.shortNameExt.parent().addClass('has-error');
							ui_sub.stop();
							return;
						}
					}

					var data = [{
						id: row[0].id,
						fullname: $('#fullname').val(),
						shortname: $('#shortname').val(),
						shortnameext: $('#shortname_ext').val(),
						synopsis: $('#synopsis').val(),
						category: $('#category').val()
					}];

					_this.push_selected(data, ui_sub, true, function() {

						_this.clear_ui_form();
						$("#dialog-form").dialog( "close" );
						//button.stop();
						//button.updateText('Success');
						_this.buttons.pushBtn.text('Success').addClass('success');
						clearTimeout(_this.push_timeout);
						_this.push_timeout = setTimeout(function() {
							//$(button.element[0]).removeClass();
							_this.processRowSelect();
						}, 3000);
					}, function(xhr) {
						var problems = JSON.parse(xhr.responseText);

						switch(problems.error_code) {
							case 'duplicate':
							case 'could_not_schedule':
								if(_this.formEl.shortNameExt.get(0)) {
									_this.formEl.notes.addClass('error').text('Please provide a three letter identifier');
									_this.formEl.shortNameExt.parent().addClass('has-error');
								} else {
									_this.formEl.shrtNmExtTd.html('<input type="text" name="shortname_ext" id="shortname_ext" class="text ui-widget-content ui-corner-all" size="3" maxlength="3"/>');
									_this.formEl.notes.removeClass().addClass('alert alert-warning').text('Shortname already in use. Please provide a three letter identifier');
									_this.formEl.shortNameExt.parent().addClass('alert alert-warning');
								}
							break;
						}

						clearTimeout(_this.ui_timeout);
						_this.ui_timeout = setTimeout(function() {
						$(ui_sub.element[0]).removeClass('error');
							ui_sub.updateText('<span class="ui-button-text">Push to Moodle<span>');
						}, 2000);
					});
																
				},
				Cancel: function() {
					_this.clear_ui_form();
					$(this).dialog( "close" );
				}
			}
		}).dialog("open" );
	};

	Connect.prototype.push_selected = function(data, button, single, callback, errorcallback) {
		
		var _this = this;

		$.ajax({
			method: 'POST',
			cache: false,
			url: M.cfg.wwwroot + '/local/connect/proxy.php',
			data: {
				action: 'schedule',
				courses: JSON.stringify(data)
			},
			dataType: 'json',
			success: function (data, status, xhr) {
				button.stop();
				_this.buttons.rowsEl.removeClass('row_selected');
				_this.buttons.pushBtn.removeClass('loading');
				$(_this.selectedDeliveries).each(function(index) {
					var row = $('#datable tbody tr[ident='+_this.selectedDeliveries[index]+']');
					var aPos = _this.oTable.fnGetPosition(row[0]);
					_this.oTable.fnUpdate('<div class="status_scheduled">scheduled</div>', row[0], 1, false)
				});

				_this.oTable.fnDraw();

				_this.selectedDeliveries = [];
				_this.count = 0;
				$('#job_number').html(_this.count);
				_this.delivery_list = '<li class="empty_deliv">no items have been selected</li>';
				if($('#jobs ul').hasClass('visible')) {
					$('#jobs ul').html(_this.delivery_list);
				}
				
				
				button.updateText('Success');
				_this.buttons.pushBtn.addClass('success');
				if(single === true) {
					$(button.element[0]).removeClass('loading');
					button.updateText('<span class="ui-button-text">Push to Moodle<span>');
				}
				_this.buttons.pageRefresh.addClass('highlight');
				// _this.buttons.pageRefresh.text('The table data has changed. Click here to reload table');
				callback();
			},
			error: function(xhr, request, settings) {
				button.stop();
				$(button.element[0]).removeClass('loading');
				button.updateText('Error');
				$(button.element[0]).addClass('error');

				errorcallback(xhr);

			}
		});
	};

	Connect.prototype.mergeDeliveries = function() {

		var _this = this;

		// Set the button appearence and functionality
		//var button = new ButtonLoader(this.buttons.mergeBtn, 'Saving');
		//this.buttons.mergeBtn.prop('disabled', true).addClass('loading');
		//button.start();
		//button.disable(this.buttons.mergeBtn);

		//Gets the data objects for all of the selected rows
		var mergers = _.chain(this.json).filter(function (row) { 
			if(_.indexOf(_this.selectedDeliveries, row.id) !== -1){
				return row;
			}
		}).sortBy(function(row) {
			return [row.module_week_beginning, row.campus, row.module_code];
		}).value();

		//Setting up our vars which control data to be sent and appears in form
		var short_name  = '';
		var mod_code = '';
		var full_name = '';
		var synopsis = '';
		var primary_child = '';
		var mergers_count = 0;

		//Finds the first synopsis in the selected deliveries and uses this
		while(synopsis === '') {
			if(mergers_count > mergers.length-1) {
				break;
			} else {
				synopsis = mergers[mergers_count].synopsis;
				var mod_code = mergers[mergers_count].module_code;
				primary_child = mergers[mergers_count].id;
				mergers_count ++;
			}
		}

		//Limits the synop length and adds a ... on the end
		//synopsis = $.trim(synopsis).substring(0,500).split(" ").slice(0, -1).join(" ") + "...";
		synopsis = $.trim(synopsis);

		//Creates the combined shorts and long names
    var fname = [];
    var sname = [];
		$.each(mergers, function(i, val) {
			var code = val.module_code.split(' ');
			if(code.length > 1) {
				code = code[0];
			} else {
				code = val.module_code;
			}

			var tmp_code = code.replace(/\s+\(\d+\/\d+\)/i, '');
			if( !_.contains(sname,tmp_code) ) {
				sname.push(code);
			}

			var tmp_full_name = val.module_title.replace(/\s+\(\d+\/\d+\)/i, '');
			if( !_.contains(fname,tmp_full_name) ) {
				fname.push(tmp_full_name);
			}

		});
    short_name += sname.join('/');
    full_name += fname.join('/');

		//Gets the year from the first delivery and creates the date string from this.
		//Then appends this to the short and full name
		var date = '(' + Date.parse(mergers[0].session_code).previous().year().toString('yyyy');
		date += '/' + Date.parse(mergers[0].session_code).toString('yyyy') + ')';
		short_name += ' ' + date;
		full_name += ' ' + date;

		//Appends the data created to the form

		this.formEl.shortName.val(short_name);
		this.formEl.fullName.val(full_name);
		this.formEl.synopsis.val(synopsis);
		this.formEl.cat.val(mergers[0].category);
		this.formEl.primary_child.val(primary_child);

		this.formEl.shortName.change(function() { $('#primary_child').val(''); });
		this.formEl.fullName.change(function() { $('#primary_child').val(''); });
		this.formEl.synopsis.change(function() { $('#primary_child').val(''); });
		this.formEl.cat.change(function() { $('#primary_child').val(''); });

		var ui_sub;

		$( "#dialog-form" ).dialog({ 
			title: 'Choose merge details',
			close: function(event, ui) {
				//button.stop();
				//button.updateText('<span>Merge</span> to Moodle');
				//_this.buttons.mergeBtn.removeClass();

				_this.formEl.shrtNmExtTd.html('');
				_this.formEl.notes.html('');
				_this.formEl.notes.removeClass();
			},
			open: function(event, ui) {
				ui_sub = new ButtonLoader($('.ui-dialog-buttonpane').find('button:contains("Merge to moodle")'), 'Saving');
			},
			buttons: {
				"Merge to moodle": function() {
					ui_sub.disable($('.ui-dialog-buttonpane').find('button:contains("Merge to moodle")'));
					ui_sub.start();

					if(_this.formEl.shortNameExt.get(0)) {
						if(_this.formEl.shortNameExt.val() === '') {
							_this.formEl.notes.removeClass('alert alert-warning').addClass('error').text('Please provide a three letter identifier');
							_this.formEl.shortNameExt.parent().addClass('has-error');
							ui_sub.stop();
							return;
						}

						short_name += ' ' + $('#shortname_ext').val();
					}

					if (!_.isEmpty($('#shortname_ext').val())) {
						short_name += ' ' + $('#shortname_ext').val();
					}

					// get full name and check it's ok (pretty lame check)
					full_name = _this.formEl.fullName.val();

					if (_.isEmpty(full_name)) {
						return alert('Please enter a full name');
					}

					$.ajax({
						type: 'POST',
						url: M.cfg.wwwroot + '/local/connect/proxy.php',
						dataType: 'json',
						data: {
							action: 'merge',
							shortnameext: $('#shortname_ext').val(),
							courses: JSON.stringify(_this.selectedDeliveries)
						},
						success: function () {
							ui_sub.stop();
							_this.buttons.rowsEl.removeClass('row_selected');
							_this.buttons.mergeBtn.removeClass('loading');
							$(_this.selectedDeliveries).each(function(index) {
								var row = $('#datable tbody tr[ident='+_this.selectedDeliveries[index]+']');
								if (row.length > 0) {
									var aPos = _this.oTable.fnGetPosition(row[0]);
									_this.oTable.fnUpdate('<div class="status_scheduled">scheduled</div>', row[0], 1, false);
								}
							});

							_this.oTable.fnDraw();

							_this.selectedDeliveries = [];
							_this.count = 0;
							$('#job_number').html(_this.count);
							_this.delivery_list = '<li class="empty_deliv">no items have been selected</li>';
							if($('#jobs ul').hasClass('visible')) {
								$('#jobs ul').html(delivery_list);
							}

							_this.buttons.pageRefresh.addClass('highlight');

							_this.clear_ui_form();
							$("#dialog-form").dialog("close");
						},
						error: function(xhr, request, settings) {
							var problems = xhr.responseText.length == 0 ? null : JSON.parse(xhr.responseText);

							if (problems) {
								switch(problems.error_code) {
									case 'duplicate':
									case 'could_not_schedule':
										if($('#shortname_ext').get(0)) {
											$('#edit_notifications').addClass('alert alert-error').text('Please provide a three letter identifier');
											$('#shortname_ext').addClass('error');
										} else {
											$('#shortname_ext_td').html('<input type="text" name="shortname_ext" id="shortname_ext" class="text ui-widget-content ui-corner-all" size="3" maxlength="3"/>');
											$('#edit_notifications').removeClass().addClass('alert alert-warning').text('Shortname already in use. Please provide a three letter identifier');
											$('#shortname_ext').addClass('alert alert-warning');
										}
									break;
								}
							} else {
								$('#edit_notifications').addClass('alert alert-error').text('A server error occured :( ... please contact an administrator');
								$('#shortname_ext').addClass('error');
							}

							ui_sub.stop();
						}
					});								 										 	
				},
				Cancel: function() {
					_this.clear_ui_form();
					$(this).dialog( "close" );
				}
			}
		}).dialog("open" );
	};

	Connect.prototype.unlink_row = function(el) {
		
		var _this = this;

		var id = $(el).closest('tr').attr('ident');

		var row = $(el).closest('tr');
		$(el).removeClass('unlink_row').addClass('ajax_loading');
		$.ajax({
			type: 'POST',
			url: M.cfg.wwwroot + '/local/connect/proxy.php',
			dataType: 'json',
			data: {
				action: 'disengage',
				course: id
			},
			success: function (data, status, xhr) {
				if(_this.oTable.fnIsOpen(row[0])) {
					row.removeClass('close').addClass('open');
					_this.oTable.fnClose(row[0]);
				}

				row.removeClass('row_selected');
				var aPos = _this.oTable.fnGetPosition(row[0]);
				_this.oTable.fnUpdate('<div class="status_scheduled">scheduled</div>', row[0], 1, false);
				_this.oTable.fnUpdate('', row[0], 6, false);

				_this.oTable.fnDraw();

				_this.selectedDeliveries = [];
				_this.count = 0;
				$('#job_number').html(_this.count);
				_this.delivery_list = '<li class="empty_deliv">no items have been selected</li>';
				if($('#jobs ul').hasClass('visible')) {
					$('#jobs ul').html(_this.delivery_list);
				}

				_this.buttons.pageRefresh.addClass('highlight');
			},
			error: function() {
				$('.ajax_loading', row).removeClass('ajax_loading').addClass('unlink_row');
				_this.statusbox(row, 'Error: we were unable to process your request at this time. Please try later');
			}
		});
	};

	Connect.prototype.unlink_child = function(el) {
		
		var _this = this;
		var id = $(el).closest('tr').attr('ident');
		var par_chksm = $(el).closest('table').attr('par_ident');
		var row_data = _.filter(_this.json, function (r) {
			return r.id === par_chksm;
		});

		$.each(row_data[0].children, function(i, c) {
			if(c.id === id) {
				row_data[0].children.splice(i,1);
				return false;
			}
		});

		var row = $(el).closest('tr');
		var children =$(row).closest('.merged tbody');

		$(el).removeClass('unlink_child').addClass('ajax_loading');

		$.ajax({
			type: 'POST',
			url: M.cfg.wwwroot + '/local/connect/proxy.php',
			dataType: 'json',
			data: {
				action: 'unlink',
				course: id
			},
			success: function () {

				var data = [
					id,
					'<div class="status_scheduled">scheduled</div>',
					$(children).find('tr[ident='+id+'] .code').text(),
					$(children).find('tr[ident='+id+'] .name').text(),
					$(children).find('tr[ident='+id+'] .campus').text(),
					$(children).find('tr[ident='+id+'] .duration').text(),
					$(children).find('tr[ident='+id+'] .version').text(),
					' '
				];
				_this.oTable.fnAddData(data);
				$(children).find('tr[ident='+id+']').remove();
				var count = $('tr', children).length;

				if(count === 2) {
					$(children).find('.unlink_child').remove();
				}

				_this.buttons.pageRefresh.addClass('highlight');

			},
			error: function() {
				$('.ajax_loading', row).removeClass('ajax_loading').addClass('unlink_child');
				_this.statusbox(row, 'Error: we were unable to process your request at this time. Please try later');
			}
		});
	};

	Connect.prototype.clear_ui_form = function() {
		this.formEl.shortName.val('');
		this.formEl.fullName.val('');
		this.formEl.synopsis.val('');
		this.formEl.primary_child.val('');

		if(this.formEl.shortNameExt.get(0)) {
			this.formEl.shortNameExt.val('');
		}
	};

	$.fn.dataTableExt.oApi.fnGetFilteredNodes = function ( oSettings )
	{
		var anRows = [];
		for ( var i=0, iLen=oSettings.aiDisplay.length ; i<iLen ; i++ )
		{
				var nRow = oSettings.aoData[ oSettings.aiDisplay[i] ].nTr;
				anRows.push( nRow );
		}
		return anRows;
	};

	return Connect;

})();
