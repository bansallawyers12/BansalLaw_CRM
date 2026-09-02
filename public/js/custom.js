/**
 *
 * You can write your JS code here, DO NOT touch the default style file
 * because it will make it harder for you to update.
 * 
 */

"use strict";

//Delete Function Start
	function declineAction( id, table ) {
			var conf = confirm('Do you want to change status to declined?');
		if(conf){	 
			if(id == '') {
				crmAlert('Please select ID to update the record.');
				return false;	
			} else {
				$(".server-error").html(''); //remove server error.
				$(".custom-error-msg").html(''); //remove custom error.
				$.ajax({
					type:'post',
					headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
					url:site_url+'/declined_action',
					data:{'id': id, 'table' : table},
					success:function(resp) {
						var obj = $.parseJSON(resp);
						if(obj.status == 1) {
							var html = successMessage(obj.message);
								$(".custom-error-msg").html(html);
							location.reload();
							
							//show count
								
							
						} else{
							var html = errorMessage(obj.message);
							$(".custom-error-msg").html(html);
						}
						$("#loader").hide();
					},
					beforeSend: function() {
						$("#loader").show();
					}
				});
				$('html, body').animate({scrollTop:0}, 'slow');
			}
		} else{
			$("#loader").hide();
		}
	}
	
	function approveAction( id, table ) {
			var conf = confirm('Do you want to change status to Approve?');
		if(conf){	 
			if(id == '') {
				crmAlert('Please select ID to update the record.');
				return false;	
			} else {
				$(".server-error").html(''); //remove server error.
				$(".custom-error-msg").html(''); //remove custom error.
				$.ajax({
					type:'post',
					headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
					url:site_url+'/approved_action',
					data:{'id': id, 'table' : table},
					success:function(resp) {
						var obj = $.parseJSON(resp);
						if(obj.status == 1) {
							var html = successMessage(obj.message);
								$(".custom-error-msg").html(html);
							location.reload();
							
								
							
							//show count
								
							
						} else{
							var html = errorMessage(obj.message);
							$(".custom-error-msg").html(html);
						}
						$("#loader").hide();
					},
					beforeSend: function() {
						$("#loader").show();
					}
				});
				$('html, body').animate({scrollTop:0}, 'slow');
			}
		} else{
			$("#loader").hide();
		}
	}
	
	function processedAction( id, table ) {
			var conf = confirm('Do you want to change status to Process?');
		if(conf){	 
			if(id == '') {
				crmAlert('Please select ID to update the record.');
				return false;	
			} else {
				$(".server-error").html(''); //remove server error.
				$(".custom-error-msg").html(''); //remove custom error.
				$.ajax({
					type:'post',
					headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
					url:site_url+'/process_action',
					data:{'id': id, 'table' : table},
					success:function(resp) {
						var obj = $.parseJSON(resp);
						if(obj.status == 1) {
								var html = successMessage(obj.message);
								$(".custom-error-msg").html(html);
							location.reload();
							
							
							
							
							//show count
								
							
						} else{
							var html = errorMessage(obj.message);
							$(".custom-error-msg").html(html);
						}
						$("#loader").hide();
					},
					beforeSend: function() {
						$("#loader").show();
					}
				});
				$('html, body').animate({scrollTop:0}, 'slow');
			}
		} else{
			$("#loader").hide();
		}
	}
	
	function archiveAction( id, table ) {
			var conf = confirm('Do you want to change status to Archive?');
		if(conf){	 
			if(id == '') {
				crmAlert('Please select ID to update the record.');
				return false;	
			} else {
				$(".server-error").html(''); //remove server error.
				$(".custom-error-msg").html(''); //remove custom error.
				$.ajax({
					type:'post',
					headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
					url:site_url+'/archive_action',
					data:{'id': id, 'table' : table},
					success:function(resp) {
						var obj = $.parseJSON(resp);
						if(obj.status == 1) {
							$("#quid_"+id+' .statusupdate').html(obj.astatus);
							//show success msg 
								var html = successMessage(obj.message);
								$(".custom-error-msg").html(html);
							
							//show count
								
							
						} else{
							var html = errorMessage(obj.message);
							$(".custom-error-msg").html(html);
						}
						$("#loader").hide();
					},
					beforeSend: function() {
						$("#loader").show();
					}
				});
				$('html, body').animate({scrollTop:0}, 'slow');
			}
		} else{
			$("#loader").hide();
		}
	}
	
	function deleteAction( id, table ) {
		var conf = confirm('Are you sure, you would like to delete this record. Remember all Related data would be deleted.');
		if(conf){	 
			if(id == '') {
				crmAlert('Please select ID to delete the record.');
				return false;	
			} else {
				$('.popuploader').show();
				$(".server-error").html(''); //remove server error.
				$(".custom-error-msg").html(''); //remove custom error.
				$.ajax({
					type:'post',
					headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
					url:site_url+'/delete_action',
					data:{'id': id, 'table' : table},
					success:function(resp) {
						$('.popuploader').hide();
						var obj = $.parseJSON(resp);
						if(obj.status == 1) {
							$("#id_"+id).remove();
							$("#quid_"+id).remove();
							//show success msg 
								var html = successMessage(obj.message);
								$(".custom-error-msg").html(html);
							
							//show count
								var old_count = $(".count").text();
								var new_count = old_count - 1;
								$(".count").text(new_count);
							
							//when all data has been deleted
								if(new_count == 0){
									$(".tdata").html('<tr><td colspan="6">There are no data in this table.</td></tr>');
								}
							
								location.reload();
								// setTimeout(function(){
								// 	location.reload();
								// }, 3000);
						} else{
							var html = errorMessage(obj.message);
							$(".custom-error-msg").html(html);
						}
						$("#loader").hide();
					},
					beforeSend: function() {
						$("#loader").show();
					}
				});
				$('html, body').animate({scrollTop:0}, 'slow');
			}
		} else{
			$("#loader").hide();
		}
	}
	
	
	
	function movetoclientAction( id, table, col ) {
		var conf = confirm('Are you sure, you would like to move this record.');
		if(conf){	 
			if(id == '') {
				crmAlert('Please select ID to delete the record.');
				return false;	
			} else {
				$('.popuploader').show();
				$(".server-error").html(''); //remove server error.
				$(".custom-error-msg").html(''); //remove custom error.
				$.ajax({
					type:'post',
					headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
					url:site_url+'/move_action',
					data:{'id': id, 'table' : table, 'col' : col},
					success:function(resp) {
						$('.popuploader').hide();
						var obj = $.parseJSON(resp);
						if(obj.status == 1) {
							$("#id_"+id).remove();
							
							//show success msg 
								var html = successMessage(obj.message);
								$(".custom-error-msg").html(html);
							
							
							
						} else{
							var html = errorMessage(obj.message);
							$(".custom-error-msg").html(html);
						}
						$("#loader").hide();
					},
					beforeSend: function() {
						$("#loader").show();
					}
				});
				$('html, body').animate({scrollTop:0}, 'slow');
			}
		} else{
			$("#loader").hide();
		}
	}


	$('.change-status').on('change', function (event, state) {
		
		var id = $.trim($(this).attr('data-id'));
		var current_status = $.trim($(this).attr('data-status'));
		var table = $.trim($(this).attr('data-table'));
		var col = $.trim($(this).attr('data-col'));
		
		if(id != "" && current_status != "" && table != ""){
			updateStatus(id, current_status, table, col);
		}
	});
	
	function updateStatus( id, current_status, table,col ) {
		$(".server-error").html(''); //remove server error.	
		$(".custom-error-msg").html(''); //remove custom error.
		$.ajax({
			type:'post',
			headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
			url:site_url+'/update_action',
			data:{'id': id, 'current_status' : current_status, 'table': table, 'colname':col},
			success:function(resp) {
				var obj = $.parseJSON(resp);
				if(obj.status == 1) {
					//show success msg 
						var html = successMessage(obj.message);
						$(".custom-error-msg").html(html);
					
					//change status
						if(current_status == 1){
							var updated_status = 0;
						} else {
							var updated_status = 1;
						}
					
						$(".change-status[data-id="+id+"]").attr('data-status', updated_status);
					$('#id_'+id).remove();
				} else{
					var html = errorMessage(obj.message);
					$(".custom-error-msg").html(html);
					
					//not change status
						if(current_status == 1){
							$(".change-status[data-id="+id+"]").prop('checked', true);
						} else {
							$(".change-status[data-id="+id+"]").prop('checked', false);
						}
				}
				$(".popuploader").hide();
			},
			beforeSend: function() {
				$(".popuploader").show();
			}
		});
		$('html, body').animate({scrollTop:0}, 'slow');
	}
	
	function crmFlashHtml(type, msg, title, autoDismiss) {
		type = type || 'info';
		if (type === 'error') {
			type = 'danger';
		}
		var icons = {
			success: 'fa-circle-check',
			danger: 'fa-circle-xmark',
			warning: 'fa-triangle-exclamation',
			info: 'fa-circle-info'
		};
		var titles = {
			success: 'Success',
			danger: 'Error',
			warning: 'Warning',
			info: 'Information'
		};
		var icon = icons[type] || icons.info;
		var heading = title || titles[type] || 'Notice';
		var dismissAttr = autoDismiss === false || autoDismiss === 0
			? ''
			: ' data-auto-dismiss="' + (autoDismiss || 6000) + '"';

		return '<div class="alert crm-flash crm-flash-' + type + ' alert-' + type + ' alert-dismissible fade show" role="alert"' + dismissAttr + '>' +
			'<div class="crm-flash__inner">' +
			'<div class="crm-flash__icon" aria-hidden="true"><i class="fa-solid ' + icon + '"></i></div>' +
			'<div class="crm-flash__content">' +
			'<div class="crm-flash__title">' + heading + '</div>' +
			'<div class="crm-flash__message">' + (msg || '') + '</div>' +
			'</div>' +
			'<button type="button" class="btn-close crm-flash__close" data-bs-dismiss="alert" aria-label="Close"></button>' +
			'</div></div>';
	}

	function initCrmFlashAlerts(root) {
		var $root = root ? $(root) : $(document);
		$root.find('.crm-flash[data-auto-dismiss]').each(function () {
			var $el = $(this);
			if ($el.data('crm-flash-init')) {
				return;
			}
			$el.data('crm-flash-init', true);
			var delay = parseInt($el.attr('data-auto-dismiss'), 10) || 6000;
			setTimeout(function () {
				if (!$el.hasClass('show')) {
					return;
				}
				$el.removeClass('show');
				setTimeout(function () {
					$el.remove();
				}, 300);
			}, delay);
		});
	}

	function observeCrmFlashContainers() {
		if (typeof MutationObserver === 'undefined') {
			return;
		}
		$('.server-error, .custom-error-msg').each(function () {
			if (this._crmFlashObserved) {
				return;
			}
			this._crmFlashObserved = true;
			var target = this;
			var observer = new MutationObserver(function () {
				initCrmFlashAlerts(target);
			});
			observer.observe(target, { childList: true, subtree: true });
		});
	}

	function showCrmFlash(msg, type, $container) {
		type = type || 'success';
		if (type === 'error') {
			type = 'danger';
		}
		$container = ($container && $container.length) ? $container : $('.server-error').first();
		if (!$container.length) {
			$container = $('.custom-error-msg').first();
		}
		if (!$container.length) {
			return;
		}
		$container.html(crmFlashHtml(type, msg)).show();
		initCrmFlashAlerts($container[0]);
		var offset = $container.offset();
		if (offset) {
			$('html, body').animate({ scrollTop: Math.max(0, offset.top - 80) }, 300);
		}
	}

	function successMessage(msg) {
		return crmFlashHtml('success', msg);
	}

	function errorMessage(msg) {
		return crmFlashHtml('danger', msg);
	}

	window.crmFlashHtml = crmFlashHtml;
	window.initCrmFlashAlerts = initCrmFlashAlerts;
	window.showCrmFlash = showCrmFlash;

	$(function () {
		initCrmFlashAlerts(document);
		observeCrmFlashContainers();
	});