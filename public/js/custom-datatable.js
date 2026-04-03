$(function () {
	$('#destintable').DataTable({
	  "paging": true,
	  "lengthChange": false,
	  "searching": false,
	  "ordering": true,
	  "info": true,
	  "autoWidth": false,
	  "columnDefs": [ {
          "targets": 'no-sort',
          "orderable": false,
    } ]
	});

	$('#hoteltable').DataTable({  
	  "paging": true,
	  "pageLength": 20,
	  "lengthChange": false,
	  "searching": false,
	  "ordering": true,
	  "info": true,
	  "autoWidth": false,
	  "columnDefs": [ {
          "targets": 'no-sort',
          "orderable": false,
    } ]
	});
	$('#flight_details').DataTable({  
	  "paging": true,
	  "pageLength": 50,
	  "lengthChange": false,
	  "searching": false,
	  "ordering": true,
	  "info": true,
	  "autoWidth": false,
	  "columnDefs": [ {
          "targets": 'no-sort',
          "orderable": false,
    } ]
	});
	$('#invoicetable').DataTable({  
	  "paging": true,
	  "lengthChange": false,
	  "searching": false,
	  "ordering": true,
	  "info": true,
	  "autoWidth": false,
	  "columnDefs": [ {
          "targets": 'no-sort',
          "orderable": false,
    } ]
	});
	$('#holidaypackage_table').DataTable({  
	  "paging": true,
	  "lengthChange": false,
	  "searching": false,
	  "ordering": true,
	  "info": true,
	  "autoWidth": false,
	  "columnDefs": [ {
          "targets": 'no-sort',
          "orderable": false,
    } ]
	});
	$('#inclusiontable').DataTable({  
	  "paging": true,
	  "lengthChange": false,
	  "searching": false,
	  "ordering": true,
	  "info": true,
	  "autoWidth": false,
	  "columnDefs": [ {
          "targets": 'no-sort',
          "orderable": false,
    } ]
	});
	$('#exclustiontable').DataTable({  
	  "paging": true,
	  "lengthChange": false,
	  "searching": false,
	  "ordering": true,
	  "info": true,
	  "autoWidth": false,
	  "columnDefs": [ {
          "targets": 'no-sort',
          "orderable": false,
    } ]
	});
	$('#amenitytable').DataTable({  
	  "paging": true,
	  "lengthChange": false,
	  "searching": false,
	  "ordering": true,
	  "info": true,
	  "autoWidth": false,
	  "columnDefs": [ {
          "targets": 'no-sort',
          "orderable": false,
    } ]
	});
	$('#departurecity_table').DataTable({  
	  "paging": true,
	  "lengthChange": false,
	  "searching": false,
	  "ordering": true,
	  "info": true,
	  "autoWidth": false,
	  "columnDefs": [ {
          "targets": 'no-sort',
          "orderable": false,
    } ]
	});
	$('#flight_detail').DataTable({  
	  "paging": true,
	  "pageLength": 50,
	  "lengthChange": false,
	  "searching": false,
	  "ordering": true,
	  "info": true,
	  "autoWidth": false,
	  "columnDefs": [ {
          "targets": 'no-sort',
          "orderable": false,
    } ]
	});
}); 
