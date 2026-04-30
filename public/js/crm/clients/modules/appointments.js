/**
 * Appointments module - Booking, time slots, getDisabledDateTime, calendar UI
 * Extracted from detail-main.js - Phase 3g refactoring.
 * Requires: jQuery, ClientDetailConfig, safeParseJsonResponse (from detail-main.js)
 */
(function($) {
    'use strict';
    if (!$) return;

    function parseTime(s) {
        var c = s.split(':');
        return parseInt(c[0], 10) * 60 + parseInt(c[1], 10);
    }

    function parseTimeLatest(s) {
        var c = s.split(':');
        var c11 = c[1] ? c[1].split(' ') : [];
        if (c11[1] == 'PM') {
            if (parseInt(c[0], 10) != 12) {
                return (parseInt(c[0], 10) + 12) * 60 + parseInt(c11[0] || c[1], 10);
            } else {
                return parseInt(c[0], 10) * 60 + parseInt(c11[0] || c[1], 10);
            }
        } else {
            return parseInt(c[0], 10) * 60 + parseInt(c11[0] || c[1], 10);
        }
    }

    function pad(str, max) {
        str = str.toString();
        return str.length < max ? pad("0" + str, max) : str;
    }

    function convertHours(mins) {
        var hour = Math.floor(mins / 60);
        var m = mins % 60;
        return pad(hour, 2) + ':' + pad(m, 2);
    }

    /** 1=Adelaide, 2=Melbourne — supports radio UI or single hidden field (CRM modal). */
    function inpersonAddressDataVal() {
        var $checked = $("input[name='inperson_address']:radio:checked");
        if ($checked.length) {
            return String($checked.attr('data-val') || $checked.val() || '');
        }
        var $h = $("input[name='inperson_address'][type='hidden']");
        if ($h.length) {
            return String($h.attr('data-val') || $h.val() || '');
        }
        return '';
    }

    function inpersonAddressField() {
        var $checked = $("input[name='inperson_address']:radio:checked");
        if ($checked.length) {
            return $checked.first();
        }
        return $("input[name='inperson_address'][type='hidden']").first();
    }

    function isLabelInDisabledList(timeLabel, disabledList) {
        if (!disabledList || !disabledList.length) {
            return false;
        }
        var normT = String(timeLabel).replace(/\s+/g, ' ').trim().toLowerCase();
        for (var j = 0; j < disabledList.length; j++) {
            if (String(disabledList[j]).replace(/\s+/g, ' ').trim().toLowerCase() === normT) {
                return true;
            }
        }
        return false;
    }

    function ValidateEmail(inputText) {
        var mailformat = /^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/;
        return inputText && inputText.match(mailformat);
    }

    $(document).ready(function() {
        var duration, daysOfWeek, starttime, endtime, disabledtimeslotes, timeslotLabelsFromBackend;
        var safeParse = typeof window.safeParseJsonResponse === 'function' ? window.safeParseJsonResponse : function(r) {
            if (typeof r === 'object' && r !== null) return r;
            if (typeof r === 'string' && r.trim()) { try { return JSON.parse(r); } catch (e) { return null; } }
            return null;
        };

        $(document).delegate('.enquiry_item', 'change', function(){

            var id = $(this).val();

            if(id != ""){

                var v = 'services';

                if(id == 8){  //If nature of service == INDIA/UK/CANADA/EUROPE TO AUSTRALIA

                    $('#serviceval_2').hide();

                } else {

                    $('#serviceval_2').show();

                }



                $('.services_row').show();

                // Legacy appointment system code - nature_of_enquiry tab removed
                // $('#myTab .nav-item #nature_of_enquiry-tab').addClass('disabled');

                $('#myTab .nav-item #services-tab').removeClass('disabled');

                $('#myTab a[href="#'+v+'"]').trigger('click');



                $('.services_item').prop('checked', false);

                $('.appointment_row').hide();

                $('.info_row').hide();

                $('.confirm_row').hide();



                $('.timeslots').html('');

                $('.showselecteddate').html('');



                $('#timeslot_col_date').val("");

                $('#timeslot_col_time').val(""); //Do blank Timeslot selected date and time

            } else {

                // Legacy appointment system code - nature_of_enquiry functionality removed
                // var v = 'nature_of_enquiry';

                $('.services_row').hide();

                $('.appointment_row').hide();

                $('.info_row').hide();

                $('.confirm_row').hide();



                $('#myTab .nav-item #services-tab').addClass('disabled');

                // Legacy appointment system code - nature_of_enquiry tab removed
                // $('#myTab .nav-item #nature_of_enquiry-tab').removeClass('disabled');

                // $('#myTab a[href="#'+v+'"]').trigger('click');

            }

            // Legacy appointment system - noe_id field removed
            // $('input[name="noe_id"]').val(id);

        });



        $(document).on('change', '.inperson_address', function() {

            var id = inpersonAddressDataVal();

            if(id != ""){

                var v = 'info';

                $('.info_row').show();

                $('.appointment_details_cls').show();



                $('#myTab .nav-item #appointment_details-tab').addClass('disabled');

                $('#myTab .nav-item #info-tab').removeClass('disabled');

                $('#myTab a[href="#'+v+'"]').trigger('click');

            } else {

                var v = 'appointment_details';

                $('.info_row').hide();

                $('.appointment_details_cls').hide();

                $('.confirm_row').hide();



                $('#myTab .nav-item #info-tab').addClass('disabled');

                $('#myTab .nav-item #appointment_details-tab').removeClass('disabled');

                $('#myTab a[href="#'+v+'"]').trigger('click');

            }

            //console.log($("input[name='radioGroup']:checked").val());



            var $inLoc = inpersonAddressField();
            if ($inLoc.length) {
                $inLoc.val(id).attr('data-val', id);
            }

            $('.timeslots').html('');

            if(id != ""){

                var enquiry_item  = $('.enquiry_item').val(); //alert(enquiry_item);

                var service_id = $("input[name='radioGroup']:checked").val(); //alert(service_id);

                var inperson_address = inpersonAddressDataVal();
                
                var slot_overwrite = $('#slot_overwrite_hidden').val() == 1 ? 1 : 0; // Get slot overwrite value

                // Initialize datepicker when location is selected
                // Destroy existing datepicker instance if it exists
                if ($('#datetimepicker').data('datepicker')) {
                    $('#datetimepicker').datepicker('destroy');
                }

                // Fetch appointment settings from backend
                $.ajax({

                    url:window.ClientDetailConfig.urls.getDateTimeBackend,

                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},

                    type:'POST',

                    data:{id:service_id, enquiry_item:enquiry_item, inperson_address:inperson_address, slot_overwrite:slot_overwrite },

                    dataType:'json',

                    success:function(res){

                        try {
                            var obj = safeParse(res);
                            if (!obj) return;
                            if(obj.success){

                                duration = obj.duration;

                                daysOfWeek =  obj.weeks;

                                starttime =  obj.start_time;

                                endtime =  obj.end_time;

                                disabledtimeslotes = obj.disabledtimeslotes;

                                timeslotLabelsFromBackend = (Array.isArray(obj.timeslot_labels) && obj.timeslot_labels.length)
                                    ? obj.timeslot_labels.slice()
                                    : null;

                                var datesForDisable = obj.disabledatesarray;

                                // Destroy existing datepicker instance if it exists (before reinitializing)
                                if ($('#datetimepicker').data('datepicker')) {
                                    $('#datetimepicker').datepicker('destroy');
                                }

                                // Initialize datepicker and attach changeDate handler
                                $('#datetimepicker').datepicker({

                                    inline: true,

                                    startDate: new Date(),

                                    datesDisabled: datesForDisable,

                                    daysOfWeekDisabled: daysOfWeek,

                                    format: 'dd/mm/yyyy'

                                }).off('changeDate').on('changeDate', function(e) {

                                    var date = e.format();

                                    var checked_date=e.date.toLocaleDateString('en-US');



                                    $('.showselecteddate').html(date);

                                    $('input[name="date"]').val(date);

                                    $('#timeslot_col_date').val(date);



                                    // If slot overwrite is enabled, don't fetch/show time slots

                                    if( $('#slot_overwrite_hidden').val() == 1){

                                        // User will select time from dropdown, not from slots

                                        return false;

                                    }



                                    $('.timeslots').html('');

                                    var start_time = parseTime(starttime),

                                    end_time = parseTime(endtime),

                                    interval = parseInt(duration);

                                    var service_id = $("input[name='radioGroup']:checked").val(); //alert(service_id);

                                    var inperson_address = inpersonAddressDataVal();

                                    var enquiry_item  = $('.enquiry_item').val(); //alert(enquiry_item);

                                    var slot_overwrite = $('#slot_overwrite_hidden').val() == 1 ? 1 : 0; // Get slot overwrite value

                                    // Fetch disabled time slots for selected date
                                    $.ajax({
                                        url:window.ClientDetailConfig.urls.getDisabledDateTime,
                                        headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                                        type:'POST',
                                        data:{service_id:service_id,sel_date:date, enquiry_item:enquiry_item,inperson_address:inperson_address,slot_overwrite:slot_overwrite},
                                        dataType:'json',
                                        success:function(res){

                                            $('.timeslots').html('');

                                            var obj = safeParse(res);
                                            if (!obj) return;
                                            if(obj.success){

                                                

                                                // If slot overwrite is enabled, don't generate time slots

                                                if( $('#slot_overwrite_hidden').val() == 1){

                                                    // Slot overwrite enabled - user will use dropdown, don't show time slots

                                                    return false;

                                                }



                                                var objdisable = Array.isArray(obj.disabledtimeslotes) ? obj.disabledtimeslotes : [];

                                                var today_date = new Date();
                                                today_date = today_date.toLocaleDateString('en-US');
                                                var now = new Date();
                                                var nowTime = new Date('1/1/1900 ' + now.toLocaleTimeString(navigator.language, {
                                                    hour: '2-digit',
                                                    minute: '2-digit',
                                                    hour12: true
                                                }));
                                                var current_time = nowTime.toLocaleTimeString('en-US');

                                                function tryAppendSlot(timeString12hr, timetoString12hr) {
                                                    if (isLabelInDisabledList(timeString12hr, objdisable)) {
                                                        return;
                                                    }
                                                    if ((checked_date == today_date) && (current_time > timeString12hr || current_time > timetoString12hr)) {
                                                        return;
                                                    }
                                                    $('.timeslots').append('<div data-fromtime="' + timeString12hr + '" data-totime="' + timetoString12hr + '" style="cursor: pointer;" class="timeslot_col"><span>' + timeString12hr + '</span></div>');
                                                }

                                                if (timeslotLabelsFromBackend && timeslotLabelsFromBackend.length) {
                                                    for (var tli = 0; tli < timeslotLabelsFromBackend.length; tli++) {
                                                        var timeString12hr = String(timeslotLabelsFromBackend[tli]).replace(/\s+/g, ' ').trim();
                                                        if (!timeString12hr) {
                                                            continue;
                                                        }
                                                        var startMins = parseTimeLatest(timeString12hr);
                                                        if (isNaN(startMins)) {
                                                            var _try = new Date('1/1/2000 ' + timeString12hr);
                                                            if (!isNaN(_try.getTime())) {
                                                                startMins = _try.getHours() * 60 + _try.getMinutes();
                                                            }
                                                        }
                                                        if (isNaN(startMins)) {
                                                            continue;
                                                        }
                                                        var endMins = startMins + interval;
                                                        var timeStringMins = endMins;
                                                        var timetoString12hr = new Date('1970-01-01T' + convertHours(timeStringMins) + 'Z')
                                                            .toLocaleTimeString('en-US', { timeZone: 'UTC', hour12: true, hour: 'numeric', minute: 'numeric' });
                                                        tryAppendSlot(timeString12hr, timetoString12hr);
                                                    }
                                                } else {
                                                    var start_timer = start_time;
                                                    for (var i = start_time; i < end_time; i = i + interval) {
                                                        var timeString = start_timer + interval;
                                                        const timeString12hr = new Date('1970-01-01T' + convertHours(start_timer) + 'Z')
                                                            .toLocaleTimeString('en-US', { timeZone: 'UTC', hour12: true, hour: 'numeric', minute: 'numeric' });
                                                        const timetoString12hr = new Date('1970-01-01T' + convertHours(timeString) + 'Z')
                                                            .toLocaleTimeString('en-US', { timeZone: 'UTC', hour12: true, hour: 'numeric', minute: 'numeric' });
                                                        tryAppendSlot(timeString12hr, timetoString12hr);
                                                        start_timer = timeString;
                                                    }
                                                }

                                            }else{



                                            }

                                        },
                                        error: function(xhr, status, error) {
                                            console.error('getDisabledDateTime error:', error);
                                            console.error('Response:', xhr.responseText);
                                        }

                                    });
                                    // End of getDisabledDateTime AJAX call

                                });

                                // Modal was hidden during init — refresh inline calendar so it paints (Bootstrap datepicker)
                                $('.info_row').show();
                                setTimeout(function () {
                                    var $cal = $('#datetimepicker');
                                    if ($cal.length && $cal.data('datepicker')) {
                                        try {
                                            $cal.datepicker('update');
                                        } catch (ignore) {}
                                    }
                                }, 150);

                            if(id != ""){

                                var v = 'appointment_details';

                                $('#myTab .nav-item #services-tab').addClass('disabled');

                                $('#myTab .nav-item #appointment_details-tab').removeClass('disabled');

                                $('#myTab a[href="#'+v+'"]').trigger('click');

                            } else {

                                var v = 'services';

                                $('#myTab .nav-item #services-tab').removeClass('disabled');

                                $('#myTab .nav-item #appointment_details-tab').addClass('disabled');

                                $('#myTab a[href="#'+v+'"]').trigger('click');

                            }

                            $('input[name="service_id"]').val($("input[name='radioGroup']:checked").val());

                        } else {

                            $('input[name="service_id"]').val('');

                            var v = 'services';

                            var errorMessage = obj.message || 'There is a problem in our system. please try again';
                            alert(errorMessage);

                            $('#myTab .nav-item #services-tab').removeClass('disabled');

                            $('#myTab .nav-item #appointment_details-tab').addClass('disabled');

                        }
                    } catch (e) {
                        console.error('Error parsing response:', e);
                        console.error('Response text:', res);
                        $('input[name="service_id"]').val('');
                        var v = 'services';
                        alert('There is a problem in our system. please try again');
                        $('#myTab .nav-item #services-tab').removeClass('disabled');
                        $('#myTab .nav-item #appointment_details-tab').addClass('disabled');
                    }

                    },
                    error: function(xhr, status, error) {
                        console.error('getDateTimeBackend AJAX error:', error);
                        console.error('Status:', xhr.status);
                        console.error('Response:', xhr.responseText);
                        
                        var errorMessage = 'There is a problem in our system. please try again';
                        
                        // Try to parse error response if available (guard against empty/invalid to prevent "Unexpected end of input")
                        try {
                            var rt = xhr.responseText;
                            if (rt && typeof rt === 'string' && rt.trim()) {
                                var errorObj = JSON.parse(rt);
                                if (errorObj && errorObj.message) {
                                    errorMessage = errorObj.message;
                                }
                            }
                        } catch (e) {
                            // If parsing fails, use default message
                        }
                        
                        $('input[name="service_id"]').val('');
                        var v = 'services';
                        alert(errorMessage);
                        $('#myTab .nav-item #services-tab').removeClass('disabled');
                        $('#myTab .nav-item #appointment_details-tab').addClass('disabled');
                        $('#myTab a[href="#'+v+'"]').trigger('click');
                    }

                });
                    // End of getDateTimeBackend AJAX call

            }

        });



        $(document).delegate('.appointment_item', 'change', function(){

            var id = $(this).val();

            if(id != ""){

                $('input[name="appointment_details"]').val(id);

            } else {

                $('input[name="appointment_details"]').val("");

            }

        });





        $(document).delegate('.services_item', 'change', function(){

            $('.info_row').hide();

            $('.confirm_row').hide();

            $("input[name='inperson_address']:radio").prop("checked", false);
            var $locRadios = $("input[name='inperson_address']:radio");
            if ($locRadios.length === 1) {
                $locRadios.first().prop("checked", true).trigger("change");
            } else {
                var $locHidden = $("input[name='inperson_address'][type='hidden']");
                if ($locHidden.length) {
                    $locHidden.first().trigger("change");
                }
            }

            $('.appointment_item').val("");



            $('#timeslot_col_date').val("");

            $('#timeslot_col_time').val(""); //Do blank Timeslot selected date and time



            var id = $(this).val();

            if (id !== "") {
                $('.appointment_details_cls').show();
            } else {
                $('.appointment_details_cls').hide();
            }

            if ($("input[name='radioGroup'][value='" + id + "']").prop("checked")) {

                $('#service_id').val(id);

            }

            //console.log($('#service_id').val());

            if( $('#service_id').val() === 'paid' ){

                $('.submitappointment_paid').show();

                $('.submitappointment').hide();

            } else {

                $('.submitappointment').show();

                $('.submitappointment_paid').hide();

            }



            if(id != ""){

                var v = 'appointment_details';

                if( id === 'paid' ){

                    // Show the "Zoom / Google Meeting" option

                    $('select[name="appointment_details"] option[value="zoom_google_meeting"]').show();

                } else {

                    // Hide the "Zoom / Google Meeting" option

                    $('select[name="appointment_details"] option[value="zoom_google_meeting"]').hide();

                }

                $('.appointment_row').show();

            } else {

                var v = 'services';

                $('.appointment_row').hide();

            }

            $('.timeslots').html('');

            $('.showselecteddate').html('');

        });
        $('.slot_overwrite_time_dropdown').change(function() {

            $('#timeslot_col_time').val("");

            var currentSelVal = $(this).val();

            $('#timeslot_col_time').val(currentSelVal);

        });



        $('#slot_overwrite').change(function() {

            $('#timeslot_col_date').val("");

            $('#timeslot_col_time').val("");

            if ($(this).is(':checked')) { 

                $('#slot_overwrite_hidden').val(1);

                $('.timeslotDivCls').hide();

                $('.slotTimeOverwriteDivCls').show();

            } else { 

                $('#slot_overwrite_hidden').val(0);

                $('.timeslotDivCls').show();

                $('.slotTimeOverwriteDivCls').hide();

            }
            
            // Destroy existing datepicker and reload with new slot_overwrite value
            $('#datetimepicker').datepicker('destroy');
            $('.timeslots').html(''); // Clear time slots
            
            // Trigger location change to reload calendar with new settings
            var selectedLocation = inpersonAddressDataVal();
            if (selectedLocation) {
                inpersonAddressField().trigger('change');
            }

        });





        $(document).delegate('.nextbtn', 'click', function(){

            var v = $(this).attr('data-steps');

            $(".custom-error").remove();

            var flag = 1;

            if(v == 'confirm'){ //datetime

                $('#sendCodeBtn_txt').html("");

                $('#sendCodeBtn_txt').hide();

                var fullname = $('.fullname').val();

                var email = $('.email').val();

                //var title = $('.title').val();

                var phone = $('.phone').val();

                var description = $('.description').val();

                var timeslot_col_date = $('#timeslot_col_date').val();

                var timeslot_col_time = $('#timeslot_col_time').val();



                // Standardized phone validation regex

                var phoneRegex = /^[0-9]{10,15}$/;

                // Regular expression to allow only letters and spaces (no special characters)

                var nameRegex = /^[a-zA-Z\s]+$/;



                var appointment_item = $('.appointment_item').val();

                if( !$.trim(appointment_item) ){

                    flag = 0;

                    $('.appointment_item').after('<span class="custom-error" role="alert">Appointment detail is required</span>');

                }

                if( !$.trim(fullname) ){

                    flag = 0;

                    $('.fullname').after('<span class="custom-error" role="alert">Fullname is required</span>');

                }

                else if (!nameRegex.test(fullname)) {

                    flag = 0;

                    // Show error message if fullname contains special characters

                    $('.fullname').after('<span class="custom-error" role="alert">Full name must not contain special characters</span>');

                }

                if( !ValidateEmail(email) ){

                    flag = 0;

                    if(!$.trim(email)){

                        $('.email').after('<span class="custom-error" role="alert">Email is required.</span>');

                    }else{

                        $('.email').after('<span class="custom-error" role="alert">You have entered an invalid email address!</span>');

                    }

                }



                if( !$.trim(phone) ){

                    flag = 0;

                    $('#sendCodeBtn').after('<span class="custom-error" role="alert">Phone number is required</span>');

                } else if (!phoneRegex.test(phone)) {

                    flag = 0;

                    // Show standardized error message

                    $('#sendCodeBtn').after('<span class="custom-error" role="alert">Phone number must be 10-15 digits and contain only numbers</span>');

                } else if( $('#phone_verified_bit').val() != "1" ){

                    flag = 0;

                    $('#sendCodeBtn').after('<span class="custom-error" role="alert">Phone number is not verified</span>');

                }



                if( !$.trim(description) ){

                    flag = 0;

                    $('.description').after('<span class="custom-error" role="alert">Description is required</span>');

                }

                if( !$.trim(description) ){

                    flag = 0;

                    $('.description').after('<span class="custom-error" role="alert">Description is required</span>');

                }

                if( !$.trim(timeslot_col_date) || !$.trim(timeslot_col_time)  ){

                    flag = 0;

                    $('.timeslot_col_date_time').after('<span class="custom-error" role="alert">Date and Time is required</span>');

                }

            }/*else if(v == 'confirm'){



            }*/

            //alert('flag=='+flag+'---v=='+v);

            if(flag == 1 && v == 'confirm'){

                $('.confirm_row').show();

                $('#myTab .nav-item .nav-link').addClass('disabled');

                $('#myTab .nav-item #'+v+'-tab').removeClass('disabled');

                $('#myTab a[href="#'+v+'"]').trigger('click');



                $('.full_name').text($('.fullname').val());

                $('.email').text($('.email').val());

                //$('.title').text($('.title').val());

                $('.phone').text($('.phone').val());

                $('.description').text($('.description').val());

                $('.date').text($('input[name="date"]').val());

                $('.time').text($('input[name="time"]').val());

                //$('.date').text($('#timeslot_col_date').val());

                //$('.time').text($('#timeslot_col_time').val());



                if(  $("input[name='radioGroup']:checked").val() === 'paid' ){

                    $('.submitappointment_paid').show();

                    $('.submitappointment').hide();

                } else {

                    $('.submitappointment').show();

                    $('.submitappointment_paid').hide();

                }

            } else {

                $('.confirm_row').hide();

            }

        });



        $(document).delegate('.timeslot_col', 'click', function(){

            $('.timeslot_col').removeClass('active');

            $(this).addClass('active');

            var service_id_val = $("input[name='radioGroup']:checked").val(); //alert(service_id_val);

            var fromtime = $(this).attr('data-fromtime');

            if(service_id_val === 'promo_free'){

                var fromtime11 = parseTimeLatest(fromtime);

                var interval11 = 15;

                var timeString11 = fromtime11 + interval11;

                var totime = new Date('1970-01-01T' + convertHours(timeString11) + 'Z')

                .toLocaleTimeString('en-US',

                    {timeZone:'UTC',hour12:true,hour:'numeric',minute:'numeric'}

                );

            } else {

                var totime = $(this).attr('data-totime');

            }

            //alert('totime='+totime);

            $('input[name="time"]').val(fromtime+'-'+totime);

            $('#timeslot_col_time').val(fromtime+'-'+totime);

        });

    }); // end $(document).ready

})(typeof jQuery !== 'undefined' ? jQuery : null);