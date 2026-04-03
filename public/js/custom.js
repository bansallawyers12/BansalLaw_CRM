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
				alert('Please select ID to update the record.');
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
				alert('Please select ID to update the record.');
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
				alert('Please select ID to update the record.');
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
				alert('Please select ID to update the record.');
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
				alert('Please select ID to delete the record.');
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
				alert('Please select ID to delete the record.');
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
	
	function successMessage(msg){
		var html = '<div class="alert alert-success alert-dismissible fade show" role="alert"><div class="alert-body">';
		html +=	'<button type="button" class="close" data-dismiss="alert" aria-label="Close">&times;</button>';
		html += '<strong>'+msg+'</strong>';
		html += '</div></div>';
		
		return html;
	}
	function errorMessage(msg){
		var html = '<div class="alert alert-danger alert-dismissible fade show"><div class="alert-body">';
		html += '<button type="button" class="close" data-dismiss="alert" aria-label="Close">&times;</button>'+msg;	
		html += '</div></div>';
		
		return html;	
	}