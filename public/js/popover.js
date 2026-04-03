function convertDateFormat(calchangedate, format) {
        var monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun",
            "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"
        ];
        var eventdate = calchangedate.toString();
        var ck_start = eventdate.split('GMT');
        var date = new Date(ck_start[0]);

        var start_month = date.getMonth() + 1;
        var monthstring = date.getMonth();
        if (start_month < 10) {
            start_month = "0" + start_month;
        }
        var start_year = date.getFullYear();
        var start_date = date.getDate();
        if (start_date < 10) {
            start_date = "0" + start_date;
        }
        if (format === "yyyy-mm-dd") {
            return newdate = start_year + "-" + start_month + "-" + start_date;
        }
        else if (format === "dd/mm/yyyy") {
            return newdate = start_date + "/" + start_month + "/" + start_year;
        }
        else if (format === "mm/dd/yyyy") {
            return newdate = start_month + "/" + start_date + "/" + start_year;
        }
        else if (format === "dd NNN yyyy") {
            return newdate = start_date + " " + monthNames[monthstring] + " " + start_year;
        }

    }



        function checkVal() {

            if (document.getElementById('activity_type').value == "") {
                alert("Select Activity");
                return false;
            }

            else {
                return true;
            }
        }

        function checkVals() {
            if (document.getElementById('from1').value == "") {
                alert("Select Date");
                return false;
            } else {
                return true;
            }
        }

function isNumberKey1(evt)
    {
        var charCode = (evt.which) ? evt.which : event.keyCode;
        if (charCode > 31 && (charCode < 48 || charCode > 57))
            return false;

        return true;
    }

    function setTitledate(data){
        var id  = data.getAttribute('id');
        var datetitle = "";

        datetitle= getDateforpopover(id)
        var datearray = datetitle.split('-');
        datetitle = datearray[0]+"-"+datearray[1]+"-"+datearray[2]+" "+datearray[3]+":"+datearray[4];
        document.getElementById(id).setAttribute('title', datetitle);
    }
    function setDateTimeInput(data)
    {

        var id  = data.getAttribute('id');
        $("#timegroup a").each(function()
        {
            if($(this).parent().is("b"))
            {
                $(this).unwrap();
            }
        });

        $("#"+id).wrap('<b></b>');
        var datetitle = "";

        datetitle= getDateforpopover(id)
		alert(datetitle);
        var datearray = datetitle.split("-");

        var dateinput = datearray[2]+"-"+datearray[1]+"-"+datearray[0];
        var datinput = datearray[1]+"/"+datearray[0]+"/"+datearray[2];
        var timestring = formatAMPM(datearray[3] , datearray[4]);
console.log(timestring);
        document.getElementById("popoverdate").value = dateinput;
        document.getElementById("popovertime").value = timestring;
        // Update Flatpickr date programmatically
        if (typeof flatpickr !== 'undefined' && $('#embeddingDatePicker').data('flatpickr')) {
            $('#embeddingDatePicker').data('flatpickr').setDate(datinput, false);
        }

        var d = new Date(datearray[2], datearray[1], datearray[0] , datearray[3] ,datearray[4] );
        var realdate = convertDateFormat(d , "yyyy-mm-dd");

        var hours = d.getHours();
        var minutes = d.getMinutes();
        var hours = hours < 10 ? '0'+hours : hours;
        var minutes = minutes < 10 ? '0'+minutes : minutes;
        $("#popoverrealdate").val(realdate+" "+hours+":"+minutes+":00");


    }

    function formatAMPM(hours, minute) {
        var hours = hours
        var minutes = minute;
        minutes = minutes < 10 ? ''+minutes : minutes;
        var ampm = hours >= 12 ? 'pm' : 'am';
        hours = hours % 12;
        hours = hours ? hours : 12; // the hour '0' should be '12'
        hours = hours < 10 ? '0'+hours : hours;
        var strTime = hours + ':' + minutes + ' ' + ampm;
        return strTime;
    }
    function changDatepickerDate(data){
		 console.log(data.value);
        var id = data.id;
        var val = data.value;

        switch(id)
        {

            case "popoverdate":
                if(val === "")
                {
                    console.log("Date can not be empty");
                    return false;
                }
                var valspilt = val.split("/");
                var updatedate = new Date(valspilt[2],valspilt[1]-1,valspilt[0]);
                if(checkvaliddate(updatedate))
                {
                    var d = new  Date();

                    if(updatedate.setHours(0,0,0,0) < d.setHours(0,0,0,0))
                    {
                         console.log("Date can not be less than today's date");
                        return false;
                    }
                    // Update Flatpickr date programmatically
                    if (typeof flatpickr !== 'undefined' && $('#embeddingDatePicker').data('flatpickr')) {
                        $('#embeddingDatePicker').data('flatpickr').setDate(val, false);
                    }


                    var currentdateformt = convertDateFormat(updatedate , "yyyy-mm-dd");
                    var curenttimeinput =  $("#popovertime").val();
                    var timesplit = curenttimeinput.split(" ");
                    var timearray = timesplit[0].split(":");
                    var hours = timearray[0];
                    var minutes = timearray[1];
                    var ampm = timesplit[1];
                    if(ampm === "pm")
                    {
                        hours = 12+parseInt(hours%12,10);

                        var datevalue = currentdateformt+" "+hours+":"+minutes+":00";
                        $("#popoverrealdate").val(datevalue);
                    }
                    else
                    {
                        var datevalue = currentdateformt+" "+hours+":"+minutes+":00";
                        $("#popoverrealdate").val(datevalue);
                    }
                }

                break;
            case "popovertime":
                var timesplit = val.split(" ");
                var timearray = timesplit[0].split(":");
                var hours = timearray[0];
                var minutes = timearray[1];
                var ampm = timesplit[1];
                var datevalue = $("#popoverdate").val();
                datevalue = datevalue.split("/")
                if(ampm === "pm")
                {

                    hours = 12+parseInt(hours%12,10);
                    var d = new Date(datevalue[2],datevalue[1]-1,datevalue[0]);
                    datestring = convertDateFormat(d, 'yyyy-mm-dd');
                    var datevalue = datestring+" "+hours+":"+minutes+":00";
                    $("#popoverrealdate").val(datevalue);
                }
                else
                {
                    var d = new Date();
                    var hourscurrent = d.getHours();
                    var datecurrent = d.getDate();
                    datecurrent = datecurrent < 10 ? '0'+datecurrent : datecurrent;
                    var currentappm = hourscurrent >= 12 ? 'pm' : 'am';
                    if(currentappm  !== "am" && datevalue[0] === datecurrent )
                    {
                        var updatedate = new Date(datevalue[2],datevalue[1]-1,datevalue[0]);
                        updatedate.setDate(updatedate.getDate()+1);
                        var realdatestring = convertDateFormat(updatedate , "yyyy-mm-dd");
                        var inputdateString = convertDateFormat(updatedate,"ddyyyy-mm-dd");
                        $("#popoverdate").val(inputdateString);
                        // Update Flatpickr date programmatically
                        if (typeof flatpickr !== 'undefined' && $('#embeddingDatePicker').data('flatpickr')) {
                            $('#embeddingDatePicker').data('flatpickr').setDate(inputdateString, false);
                        }
                        var datevalue = realdatestring+" "+hours+":"+minutes+":00";
                        $("#popoverrealdate").val(datevalue);
                    }
                    else
                    {
                        if(val === "" || val =="12:00 am")
                        {
                            hours = "10";
                            minutes = "00";
                        }
                        var updatedate = new Date(datevalue[2],datevalue[1]-1,datevalue[0]);
                        var realdatestring = convertDateFormat(updatedate , "yyyy-mm-dd");
                        var datevalue = realdatestring+" "+hours+":"+minutes+":00";
                        $("#popoverrealdate").val(datevalue);
                    }

                }


                break;
        }
    }

    function getDateforpopover(id)
    {
        var d = new Date();
        var datetitle = ""
        switch(id)
        {
            case 'hrs2':
                d.setHours(d.getHours()+2);

                break;
            case 'hrs4':
                d.setHours(d.getHours()+4);

                break;
            case 'tom_mor':

                d.setDate(d.getDate()+1);
                d.setHours(10);
                d.setMinutes(0);

                break;
            case 'tom_eve':

                d.setDate(d.getDate()+1);
                d.setHours(17);
                d.setMinutes(0);
                break;
            case 'tow_day':

                d.setDate(d.getDate()+2);
                d.setHours(10);
                d.setMinutes(0);
                break;
            case 'in_week':

                d.setDate(d.getDate()+7);
                d.setHours(10);
                d.setMinutes(0);
                break;
            default:
                date.setDate(date.getDate() - 0);
                break;
        }

        var year = d.getFullYear();
        var month = d.getMonth()+1;
        if(month < 10)
        {
            month = "0"+month;
        }
        var day  = d.getDate();
        if(day < 10)
        {
            day = "0"+day;
        }
        var hours = d.getHours();
        if(hours < 10)
        {
            hours = "0"+hours;
        }
        var minute = d.getMinutes();
        if(minute < 10)
        {
            minute = "0"+minute;
        }

        datetitle = day+"-"+month+"-"+year+"-"+hours+"-"+minute;
        return  datetitle;
    }


    //var CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');
    $("[data-role=popover]").on('shown.bs.popover', function(){

        /*$("#rem_cat").select2({
            ajax: {
                url: "/clients/getAllStaff",
                type: 'POST',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        _token: CSRF_TOKEN,
                        q: params.term, // search term
                        page: params.current_page
                    };
                },
                processResults: function(data, params) {
                    params.current_page = params.current_page || 1;

                    return {
                        results: data.data,
                        pagination: {
                            more: (params.current_page * 30) < data.total
                        }
                    };
                },
                autoWidth: true,
                cache: true
            },
            placeholder: 'Search Assignee',
            minimumInputLength: 2,
            templateResult: formatProduct,
            templateSelection: formatProductSelection
        });*/



        $('.js-data-example-ajaxccsearch__addmytask').select2({
            closeOnSelect: true,
            ajax: {
                url: '/clients/get-allclients',
                dataType: 'json',
                processResults: function (data) {
                    // Transforms the top-level key of the response object from 'items' to 'results'
                    return {
                    results: data.items
                    };
                },
                cache: true
            },
            templateResult: formatRepomain_addmytask,
            templateSelection: formatRepoSelectionmain_addmytask
        });

        /* $("#popoverdate").inputmask("dd/mm/yyyy", {
            "placeholder": "dd/mm/yyyy"
        });
        $("#popovertime").inputmask("h:s t", {
            "placeholder": "hh:mm am"
        });  */
        var today = new Date();

        var realdateinput = convertDateFormat(today , "yyyy-mm-dd");
        var popoverdateinput = convertDateFormat(today,'yyyy-mm-dd');
        var popoverdatinput = convertDateFormat(today,'mm/dd/yyyy');

        today.setHours(today.getHours()+2);
        var hoursval = today.getHours();
        var minutesval  = today.getMinutes();

        var timeformat = formatAMPM(hoursval , minutesval);
        hoursval = hoursval < 10 ? '0'+hoursval : hoursval;
        minutesval = minutesval < 10 ? '0'+minutesval : minutesval ;
        $("#popoverdate").val(popoverdateinput);
        $("#popovertime").val(timeformat);
        $("#popoverrealdate").val(realdateinput+" "+hoursval+":"+minutesval+":00");

        // Update Flatpickr date programmatically
        if (typeof flatpickr !== 'undefined' && $('#embeddingDatePicker').data('flatpickr')) {
            $('#embeddingDatePicker').data('flatpickr').setDate(popoverdatinput, false);
        }
        $("#hrs2").wrap("<b></b>");

        var date = new Date();
        date.setDate(date.getDate() - 0);

        // Initialize Flatpickr for embeddingDatePicker
        if (typeof flatpickr !== 'undefined') {
            const embeddingDatePicker = document.getElementById('embeddingDatePicker');
            if (embeddingDatePicker && !$(embeddingDatePicker).data('flatpickr')) {
                flatpickr(embeddingDatePicker, {
                    dateFormat: 'm/d/Y', // mm/dd/yyyy format for this popover
                    allowInput: true,
                    clickOpens: true,
                    minDate: date,
                    defaultDate: date,
                    locale: { firstDayOfWeek: 1 },
                    onChange: function(selectedDates, dateStr, instance) {
                        // Set the value for the date input
                        var datestring = dateStr.split("/");
                        $("#popoverdate").val(datestring[2]+"-"+datestring[0]+"-"+datestring[1]);
                        var d = new Date();
                        var selectedDate = selectedDates[0];
                        var realdate = convertDateFormat(selectedDate, "yyyy-mm-dd");
                        var hours = d.getHours();
                        var minutes = d.getMinutes();

                        $("#popovertime").val(formatAMPM(hours,minutes));
                        hours = hours < 10 ? '0'+hours : hours;
                        minutes = minutes < 10 ? '0'+minutes : minutes;
                        $("#popoverrealdate").val(realdate+" "+hours+":"+minutes+":00");
                    }
                });
            }
        }
        
        // Old datepicker code removed - using Flatpickr above
        /* $('#embeddingDatePicker').datepicker({startDate: date,})
            .on('changeDate', function(e) {
                // ... old code ...
            }); */
    });


    /*function formatUser(user) {
        if (user.loading) {
            return user.text;
        }
        var $container = $(
            "<div class='select2-result-product clearfix'>" +
            "<div class='select2-result-product__title'></div>" +
            "</div>" +
            "</div>" +
            "</div>"
        );

        $container.find(".select2-result-product__title").text(user.first_name);
        //$container.find(".select2-result-product__description").text(user.description);

        return $container;
    }

    function formatUserSelection(user) {
        return user.first_name || user.text;
    }*/


    /*function formatProduct(product) {
        if (product.loading) {
            return product.text;
        }

        var $container = $(
            "<div class='select2-result-product clearfix'>" +
            "<div class='select2-result-product__title'></div>" +
            "<div class='select2-result-product__description'></div>" +
            "</div>" +
            "</div>" +
            "</div>"
        );

        $container.find(".select2-result-product__title").text(product.first_name);
        //$container.find(".select2-result-product__description").text(product.description);

        return $container;
    }

    function formatProductSelection(product) {
        return product.first_name || product.text;
    }*/





    function formatRepomain_addmytask (repo) {
        if (repo.loading) {
            return repo.text;
        }

        var $container = $(
            "<div dataid="+repo.cid+" class='selectclient select2-result-repository ag-flex ag-space-between ag-align-center')'>" +

            "<div  class='ag-flex ag-align-start'>" +
                "<div  class='ag-flex ag-flex-column col-hr-1'><div class='ag-flex'><span  class='select2-result-repository__title text-semi-bold'></span>&nbsp;</div>" +
                "<div class='ag-flex ag-align-center'><small class='select2-result-repository__description'></small ></div>" +

            "</div>" +
            "</div>" +
            "<div class='ag-flex ag-flex-column ag-align-end'>" +

                "<span class='select2resultrepositorystatistics'>" +

                "</span>" +
            "</div>" +
            "</div>"
        );

        $container.find(".select2-result-repository__title").text(repo.name);
        $container.find(".select2-result-repository__description").text(repo.email);
        if(repo.status == 'Archived'){
            $container.find(".select2resultrepositorystatistics").append('<span class="ui label  select2-result-repository__statistics">'+repo.status+'</span>');
        }else{
            $container.find(".select2resultrepositorystatistics").append('<span class="ui label yellow select2-result-repository__statistics">'+repo.status+'</span>');
        }
        return $container;
    }

    function formatRepoSelectionmain_addmytask (repo) {
        return repo.name || repo.text;
    }




    function getUrlVars()
    {
        var vars = [], hash;
        var hashes = window.location.href.slice(window.location.href.indexOf('?') + 1).split('&');
        for(var i = 0; i < hashes.length; i++)
        {
            hash = hashes[i].split('=');
            vars.push(hash[0]);
            vars[hash[0]] = hash[1];
        }
        return vars;
    }

    $("#lreq").keyup(function (e) {
        autoheight(this);
    });
    $("#lreq").keypress(function(e){
        var tval = $(this).val(),
            tlength = tval.length,

            set = 500,
            remain = parseInt(set - tlength);

        if (remain <= 0 && e.which !== 0 && e.charCode !== 0) {
            $(this).val((tval).substring(0, tlength - 1));
            console.log("Maximum charactor count(500) ");
        }
    });
    function autoheight(a) {
        if (!$(a).prop('scrollTop')) {
            do {
                var b = $(a).prop('scrollHeight');
                var h = $(a).height();
                $(a).height(h - 5);
            }
            while (b && (b != $(a).prop('scrollHeight')));
        };
        $(a).height($(a).prop('scrollHeight') + 5);
    }
    function checkvaliddate(d)
    {
        if ( Object.prototype.toString.call(d) === "[object Date]" ) {
            // it is a date
            if ( isNaN( d.getTime() ) ) {  // d.valueOf() could also work
                return false
            }
            else {
                return true
            }
        }
        else {
            return false;
        }
    }





    $("body").on('click', function (e) {
        $("[data-role=popover]").each(function(){
            if (!$(this).is(e.target) && $(this).has(e.target).length === 0 && $('.popover').has(e.target).length === 0 && !$(e.target).hasClass('day') && !$(e.target).hasClass('year') && !$(e.target).hasClass('month')) {
                (($(this).popover('hide').data('bs.popover')||{}).inState||{}).click = false  // fix for BS 3.3.6
            }
        });
    });


	$(document).ready(function(){
		 var showPopover = $.fn.popover.Constructor.prototype.show;
            $.fn.popover.Constructor.prototype.show = function () {
                // Use apply to pass all original arguments to the show method
                showPopover.apply(this, arguments);
                if (this.options && this.options.showCallback) {
                    this.options.showCallback.call(this);
                }
            }

		$("[data-role=popover]").popover({
				sanitize: false,
                html: true,
                showCallback: function(){
                }
            });

	});


