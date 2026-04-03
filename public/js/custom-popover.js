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
        var datearray = datetitle.split("-");

        var dateinput = datearray[0]+"/"+datearray[1]+"/"+datearray[2];
        var timestring = formatAMPM(datearray[3] , datearray[4]);

        document.getElementById("popoverdate").value = dateinput;
        document.getElementById("popovertime").value = timestring;
        // Update Flatpickr date programmatically
        if (typeof flatpickr !== 'undefined' && $('#embeddingDatePicker').data('flatpickr')) {
            $('#embeddingDatePicker').data('flatpickr').setDate(dateinput, false);
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
        minutes = minutes < 10 ? '0'+minutes : minutes;
        var ampm = hours >= 12 ? 'pm' : 'am';
        hours = hours % 12;
        hours = hours ? hours : 12; // the hour '0' should be '12'
        hours = hours < 10 ? '0'+hours : hours;
        var strTime = hours + ':' + minutes + ' ' + ampm;
        return strTime;
    }
    function changDatepickerDate(data){
        var id = data.id;
        var val = data.value;

        switch(id)
        {

            case "popoverdate":
                if(val === "")
                {
                    toastr.error("Date can not be empty");
                    return false;
                }
                var valspilt = val.split("/");
                var updatedate = new Date(valspilt[2],valspilt[1]-1,valspilt[0]);
                if(checkvaliddate(updatedate))
                {
                    var d = new  Date();

                    if(updatedate.setHours(0,0,0,0) < d.setHours(0,0,0,0))
                    {
                        toastr.error("Date can not be less than today's date");
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
                        var inputdateString = convertDateFormat(updatedate,"dd/mm/yyyy");
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
    function removeattachment(data , key)
    {
        var $lead_id = '6227310';
        var $agent_id = '88014';
        var $username = 'Demo@gmail.com';
        var filename = $("#filename"+data).html();
        var id = data;
        if(id != "")
        {
            $.ajax({
                url : '/ajax/unimail-ajax.php',
                type : 'POST',
                data : {lead_id : $lead_id,
                    agent_id : $agent_id,
                    username : $username,
                    filename : filename,
                    draftkey : key,
                    removeattach : 'removeattachment',
                    id : id},
                success: function(data){

                },
                error: function(data){

                }


            });
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
                ga('send', 'event','HOOK','Lead Detail','Reminder_Presets_2hours');
                break;
            case 'hrs4':
                d.setHours(d.getHours()+4);
                ga('send', 'event','HOOK','Lead Detail','Reminder_Presets_4hours');
                break;
            case 'tom_mor':
                ga('send', 'event','HOOK','Lead Detail','Reminder_Presets_tmrw_morning');
                d.setDate(d.getDate()+1);
                d.setHours(10);
                d.setMinutes(0);

                break;
            case 'tom_eve':
                ga('send', 'event','HOOK','Lead Detail','Reminder_Presets_tmrw_evening');
                d.setDate(d.getDate()+1);
                d.setHours(17);
                d.setMinutes(0);
                break;
            case 'tow_day':
                ga('send', 'event','HOOK','Lead Detail','Reminder_Presets_2days');
                d.setDate(d.getDate()+2);
                d.setHours(10);
                d.setMinutes(0);
                break;
            case 'in_week':
                ga('send', 'event','HOOK','Lead Detail','Reminder_Presets_1week');
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


        function activity_reminder()
        {
	    var note_act = document.getElementById("activity_type1").value;
	    var rem_act = document.getElementById("activityReminder1").value;
	    if(note_act == '' && rem_act == '')
	    {
		toastr.error("Please select option");
		document.getElementById("activity_type1").focus();
		return false;
	    }
	    else{

	    }
	    var $lead_id = '6227310';
	    var $agent_id = '88014';
	    var $username = 'Demo@gmail.com';

	     ga('send', 'event','HOOK','Lead Detail','Reminder_activity');
	    $.ajax({
		type : "POST",
		url  : '/ajax/unimail-ajax.php',
		data : {add_note : note_act ,  rem_day : rem_act , lead_id : $lead_id , user_id : $agent_id , username : $username , reminder_activity : 'reminder'},
		success : function(data){
// 		    console.log(data);
		    toastr.success("Success");
        location.reload();
		},
		error: function(error){
		    console.log(error);
		}
	    });
        }


    $(document).on('click', "#setreminder" , function(e){
        e.preventDefault();
        var remindernote = $("#remindernote").val();
        var remiderdate =  $("#popoverrealdate").val();
        var remiderinputdate = $("#popoverdate").val();
        var regardless= $("#rem_cat").val();
        var $lead_id = '6227310';
        var $agent_id = '88014';
        var $username = 'Demo@gmail.com';
        var remiderdatearray = remiderinputdate.split("/");
        var d = new Date(remiderdatearray[2], remiderdatearray[1]-1, remiderdatearray[0]);
        var current = new Date();
        if(!checkvaliddate(d))
        {
            toastr.error("Please enter a valid date");
            return false;
        }
        else if(d.setHours(0,0,0,0) < current.setHours(0,0,0,0))
        {
            toastr.error("Date can not be less than today's date");
            return false;
        }
        var remiderinputtime = $("#popovertime").val();
        var regex = /([01]\d|2[0-3]):([0-5]\d)\s(am|pm)/;
        if(!regex.test(remiderinputtime))
        {
            toastr.error("Please enter a valid reminder time");
            return false;
        }
        ga('send', 'event','HOOK','Lead Detail','Reminder_set_button');
        $.ajax({
            type : "POST",
            url  : '/ajax/unimail-ajax.php',
            data : {add_note : remindernote ,  rem_time : remiderdate , regval_condition : regardless, lead_id : $lead_id , user_id : $agent_id , username : $username , reminder : 'reminder'},
            success : function(data){
                console.log(data);
                toastr.success("Success");
                $('[data-role=popover]').popover('hide');
            },
            error: function(error){
                console.log(error);
            }
        });

    });
    $(document).on('click', "#sendlateremail" , function(e){
        e.preventDefault();
        var remiderdate =  $("#popoverrealdate").val();
        var remiderinputdate = $("#popoverdate").val();

        var $lead_id = '6227310';
        var $agent_id = '88014';
        var $username = 'Demo@gmail.com';
        var remiderdatearray = remiderinputdate.split("/");
        var d = new Date(remiderdatearray[2], remiderdatearray[1]-1, remiderdatearray[0]);
        var current = new Date();
        var $lead_email = $("#lead_email").val();
        var $mail_content = CKEDITOR.instances['editor1'].getData();
        var $lead_cc = $("#lead_cc").val();
        var $lead_subject = $("#lead_subject").val();
        var $lead_bcc = $("#lead_bcc").val();
        var $fromemail = 'EQ9kh8SKDKRhiC5UCQJtKIbtNz5KJ5k3h8GElreTj4q5Wg9k391tCtQ79AaUtpBv@hooklms.com';
        var $replytoemail = 'EQ9kh8SKDKRhiC5UCQJtKIbtNz5KJ5k3h8GElreTj4q5Wg9k391tCtQ79AaUtpBv@hooklms.com';
        var $fromname = 'Suren singh';
         if ($('#check_id').is(":checked"))
        {
          var $set_reminder=1;

        }
        else
        {
           var $set_reminder=0;
        }
        if($.trim($lead_email) === "" && !ValidateEmail($.trim($lead_email)) )
        {

            toastr.error('Please correct traveler email');

            return false;
        }
        if($.trim($lead_subject) === "")
        {

            toastr.error('Please provide subject');

            return false;
        }

        if($.trim($mail_content) === "")
        {

            toastr.error('Please provide mail content');

            return false;
        }


        if($("#lead_cc").is(":visible"))
        {

            var $lead_ccarray =  $lead_cc.split(",");
            for(var i =0 ; i < $lead_ccarray.length ; i++)
            {
                if(!ValidateEmail($.trim($lead_ccarray[i])))
                {

                    toastr.error('Please correct to cc email"');

                    return false;
                    break;
                }
            }
            $lead_cc = $lead_ccarray.join("|");
        }
        if($("#lead_bcc").is(":visible"))
        {
            var $lead_bccarray =  $lead_bcc.split(",");
            for(var i =0 ; i < $lead_bccarray.length ; i++)
            {
                if(!ValidateEmail($.trim($lead_bccarray[i])))
                {

                    toastr.error('Please correct to bcc email"');

                    return false;
                    break;
                }
            }
            $lead_bcc = $lead_bccarray.join("|");
        }


        if(!checkvaliddate(d))
        {
            toastr.error("Please enter a valid date");
            return false;
        }
        else if(d.setHours(0,0,0,0) < current.setHours(0,0,0,0))
        {
            toastr.error("Date can not be less than today's date");
            return false;
        }
        var remiderinputtime = $("#popovertime").val();
        var regex = /([01]\d|2[0-3]):([0-5]\d)\s(am|pm)/;
        if(!regex.test(remiderinputtime))
        {
            toastr.error("Please enter a valid reminder time");
            return false;
        }

        ga('send', 'event','HOOK','Lead Detail','send_later_button');
        $('[data-role=popover]').popover('hide');
        activity_email();
        $.ajax({
            type : "POST",
            url  : '/ajax/unimail-ajax.php',
            data : {rem_time : remiderdate , leademail : $lead_email,
                mail_content : $mail_content,
                fromemail : $fromemail,
                replytoemail : $replytoemail,
                fromname : $fromname,
                lead_cc : $lead_cc,
                lead_bcc : $lead_bcc,
                set_reminder:$set_reminder,


                lead_subject : $lead_subject, lead_id : $lead_id , user_id : $agent_id , username : $username , sendlater : 'sendlater'},
            success : function(data){
                toastr.success('Mail has been scheduled successfully');
                setTimeout(function(){ window.location.reload(); }, 1000);

            },
            error: function(error){
                console.log(error);
            }
        });

    });
    $("[data-role=popover]").on('shown.bs.popover', function(){
        $("#popoverdate").inputmask("dd/mm/yyyy", {
            "placeholder": "dd/mm/yyyy"
        });
        $("#popovertime").inputmask("h:s t", {
            "placeholder": "hh:mm am"
        });
        var today = new Date();
        var realdateinput = convertDateFormat(today , "yyyy-mm-dd");
        var popoverdateinput = convertDateFormat(today,'dd/mm/yyyy');
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
            $('#embeddingDatePicker').data('flatpickr').setDate(popoverdateinput, false);
        }
        $("#hrs2").wrap("<b></b>");
    });
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
            toastr.error("Maximum charactor count(500) ");
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
    $("#send_email").click(function(e){
        e.preventDefault();
        send_email_to_traveler();
    });

    /*Itinerary handlers removed - legacy code */
    function sendmailcontent(data)
    {
        var $mail_data =  data;
        var $lead_id = '6227310';
        var $agent_id = '88014';
        var $username = 'Demo@gmail.com';

        $.ajax({
            type : "POST",
            data : {
                mail_content : $mail_data,
                lead_id  : $lead_id,
                user_id : $agent_id,
                username : $username,
                savemail : 'save_mail'
            },
            url  : '/ajax/unimail-ajax.php',
            success : function(data){
                //return false;
            },
            error : function(error){

                console.log(error);
            }
        });


    }
    function request_payment($lid , $parentuser){
	var $amount = $("#payment_amount").val();
	var $lead_name = 'Punitraina38';
	var $lead_phone = $("#lpn").val();
	$lead_phone = $lead_phone.replace("+", "" )
	$lead_phone = $lead_phone.replace("-", "" );

	if($lead_name == '')
	$lead_name = 'User';
	if($amount.trim() == ""){
		alert("Please enter valid amount");
		$("#payment_amount").focus();
		return false;
	}else{
	
  	$.ajax({
            type : "POST",
            data : {lead_id  : $lid,
                user_id : $parentuser,
		lead_name : $lead_name,
		lead_phone : $lead_phone,
                amount : $amount,
                request_payment : 'request_payment',
            },
            url  : '/ajax/unimail-ajax.php',
            beforeSend: function(jqXHR, settings) {
                toastr.options.hideMethod = 'noop';
                toastr.info("Requesting Payment"); // Assuming you have some kind of spinner object.
            },
            success : function(data){

                toastr.success('Request Sent');
                setTimeout(function(){ window.location.reload(); }, 1000);


                //return false;
            },
            error : function(error){
        
            }
       	 });
	}
}
    function send_email_to_traveler(){

        var $lead_email = $("#lead_email").val();
        var $mail_content = CKEDITOR.instances['editor1'].getData();
        var $lead_cc = $("#lead_cc").val();
        var $lead_subject = $("#lead_subject").val();
        var $lead_bcc = $("#lead_bcc").val();
        var $fromemail = 'EQ9kh8SKDKRhiC5UCQJtKIbtNz5KJ5k3h8GElreTj4q5Wg9k391tCtQ79AaUtpBv@hooklms.com';
        var $lead_id = '6227310';
        var $agent_id = '88014';
        var $username = 'Demo@gmail.com';
        var $replytoemail = 'EQ9kh8SKDKRhiC5UCQJtKIbtNz5KJ5k3h8GElreTj4q5Wg9k391tCtQ79AaUtpBv@hooklms.com';
        var $fromname = 'Suren singh';
        if ($('#check_id').is(":checked"))
        {
          var $set_reminder=1;

        }
        else
        {
           var $set_reminder=0;
        }
        if($.trim($lead_email) === "" && !ValidateEmail($.trim($lead_email)) )
        {

            toastr.error('Please correct traveler email');

            return false;
        }
        if($.trim($lead_subject) === "")
        {

            toastr.error('Please provide subject');

            return false;
        }

        if($.trim($mail_content) === "")
        {

            toastr.error('Please provide mail content');

            return false;
        }


        if($("#lead_cc").is(":visible"))
        {

            var $lead_ccarray =  $lead_cc.split(",");
            for(var i =0 ; i < $lead_ccarray.length ; i++)
            {
                if(!ValidateEmail($.trim($lead_ccarray[i])))
                {

                    toastr.error('Please correct to cc email"');

                    return false;
                    break;
                }
            }
            $lead_cc = $lead_ccarray.join("|");
        }
        if($("#lead_bcc").is(":visible"))
        {
            var $lead_bccarray =  $lead_bcc.split(",");
            for(var i =0 ; i < $lead_bccarray.length ; i++)
            {
                if(!ValidateEmail($.trim($lead_bccarray[i])))
                {

                    toastr.error('Please correct to bcc email"');

                    return false;
                    break;
                }
            }
            $lead_bcc = $lead_bccarray.join("|");
        }

        activity_email();
        $.ajax({
            type : "POST",
            data : {leademail : $lead_email,
                mail_content : $mail_content,
                fromemail : $fromemail,
                replytoemail : $replytoemail,
                fromname : $fromname,
                lead_cc : $lead_cc,
                lead_bcc : $lead_bcc,
                lead_id  : $lead_id,
                user_id : $agent_id,
                username : $username,
                lead_subject : $lead_subject,
                set_reminder : $set_reminder,
                send_mail : 'send_mail',
            },
            url  : '/ajax/unimail-ajax.php',
            beforeSend: function(jqXHR, settings) {
                toastr.options.hideMethod = 'noop';
                toastr.info("Sending your mail"); // Assuming you have some kind of spinner object.
            },
            success : function(data){

                toastr.success('Mail has been sent to traveler');
                setTimeout(function(){ window.location.reload(); }, 1000);


                //return false;
            },
            error : function(error){
                activity_email();
                console.log(error);
            }
        });
    }
    function ValidateEmail(inputText)
    {

        var mailformat = /^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/;
        if(inputText.match(mailformat))
        {
            return true;
        }
        else{
            return false;
        }
    }

    function myClickAll()
    {
	var val = document.getElementById("activity_type").value.trim();
	if(val == "sentmail" || val == "quotesent" || val == "latercall"  || val == "other" || val == "pay-follow" || val == "hadcall" || val == "interested"  )
	{
	    document.getElementById("noteAdd").style.display="block";
	    document.getElementById("noteAdd_pos").style.display="none";
	    document.getElementById("add_note_normal").style.display="none";
	}
        else if(val == "postponed")
        {
	     document.getElementById("noteAdd").style.display="none";
	    document.getElementById("noteAdd_pos").style.display="block";
	    document.getElementById("add_note_normal").style.display="none";
        }
	else
	{
	    document.getElementById("noteAdd").style.display="none";
	    document.getElementById("noteAdd_pos").style.display="none";
	    document.getElementById("add_note_normal").style.display="block";
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
                showPopover.call(this);
                if (this.options.showCallback) {
                    this.options.showCallback.call(this); 
                }
            } 

	 
		$("[data-role=popover]").popover({

                html: true,
                showCallback: function(){
                    var date = new Date();
                    date.setDate(date.getDate() - 0);


                    // Initialize Flatpickr for embeddingDatePicker
                    if (typeof flatpickr !== 'undefined') {
                        const embeddingDatePicker = document.getElementById('embeddingDatePicker');
                        if (embeddingDatePicker && !$(embeddingDatePicker).data('flatpickr')) {
                            flatpickr(embeddingDatePicker, {
                                dateFormat: 'd/m/Y',
                                allowInput: true,
                                clickOpens: true,
                                minDate: date,
                                defaultDate: date,
                                locale: { firstDayOfWeek: 1 },
                                onChange: function(selectedDates, dateStr, instance) {
                                    // Set the value for the date input
                                    $("#popoverdate").val(dateStr);
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
                    /* $('#embeddingDatePicker')
                        .datepicker({
                            format: 'dd/mm/yyyy',
                            startDate: date,
                        })
                        .on('changeDate', function(e) {
                            // ... old code ...
                        }); */
                }
            });
		
	});		
	
	