//Tracking code


  var formedit ="";
(function(i, s, o, g, r, a, m) {
i['GoogleAnalyticsObject'] = r;
i[r] = i[r] || function() {
(i[r].q = i[r].q || []).push(arguments)
}, i[r].l = 1 * new Date();
a = s.createElement(o),
m = s.getElementsByTagName(o)[0];
a.async = 1;
a.src = g;
m.parentNode.insertBefore(a, m)
})(window, document, 'script', '//www.google-analytics.com/analytics.js', 'ga');
ga('create', 'UA-11462831-1', 'auto');
ga('send', 'pageview');




// AJAX FUNCTIONS
function getXmlHttpObject()
{
	var oXmlHttp;
	try
	{
		oXmlHttp = new XMLHttpRequest();
	}
	catch (e)
	{
		try
		{
			oXmlHttp = new ActiveXObject("Msxml2.XMLHTTP");
		}
		catch (e)
		{
			oXmlHttp = new ActiveXObject("Microsoft.XMLHTTP");
		}
	}

	return oXmlHttp;
}

function sendRequestAndGetResponse(sUrl, oElement, sMsg, sErrMsg, sMethod, sParams , extraurl)
{
  extraurl = typeof extraurl !== 'undefined' ? extraurl : '';
	var oXmlHttp = getXmlHttpObject();

	if(typeof(oXmlHttp) == "object")
	{
		oXmlHttp.onreadystatechange = function()
		{
			if(oXmlHttp.readyState == 4)
			{
				if(oXmlHttp.status == 200)
				{
					//prompt("", oXmlHttp.responseText);

					oElement.innerHTML = oXmlHttp.responseText;
          if(extraurl != '' && oXmlHttp.responseText.search("Successful") != -1){
            var win = window.open(extraurl,'_blank');
		if (win) {
                      win.focus();
    		  } else {
                  alert('Please allow popups for this website');
          	 }
          }
				}
				else
				{

					oElement.innerHTML = sErrMsg;
				}

			}
			else
			{

				oElement.innerHTML = sMsg;


			}

		}

		if(sMethod == "POST")
		{
			oXmlHttp.open("POST", sUrl, true);
			oXmlHttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
			oXmlHttp.setRequestHeader("Content-length", sParams.length);
			oXmlHttp.setRequestHeader("Connection", "close");
			oXmlHttp.send(sParams);
		}
		else
		{
			oXmlHttp.open("GET", sUrl, true);
			oXmlHttp.send(null);
		}

	} // EO if(oXmlHttp != null)
	else
	{
		oElement.innerHTML = sErrMsg;
	}

} // EO sendRequestAndGetResponse()


//Drop down menu
<!-- hide
        function doit(box) {
          val = box.options[box.selectedIndex].value;
          window.open (val,'_top');
        }
        function pause(box) {
          val = box.options[box.selectedIndex].value;
          window.open (val,'_top');
        }
// end hide -->

var ver='3.0.1D'
var m1=new Object
m1.name='m1'
m1.fnm='submenu-top-nav_m1'
if(!window.lastm||window.lastm<1)lastm=1
m1.v17=null
m1.v17Timeout=''
var maxZ=1000
m1.v18
m1.targetFrame
var docLoaded=false
m1.bIncBorder=true
m1.v29=null
m1.v29Str=''
m1.v55=50
m1.scrollStep=10
m1.fadingSteps=3
m1.itemOverDelay=0
m1.transTLO=0
m1.fixSB=0
m1.v21="."
m1.maxlev=2
m1.v22=0
m1.sepH=10
m1.bHlNL=1
m1.showA=1
m1.bVarWidth=0
m1.bShowDel=50
m1.scrDel=0
m1.v23=170
m1.levelOffset=-1
m1.levelOffsety=2
m1.bord=1
m1.vertSpace=3
m1.sep=1
m1.v19=false
m1.bkv=0
m1.rev=0
m1.shs=0
m1.xOff=0
m1.yOff=9
m1.v20=false
m1.cntFrame=""
m1.menuFrame=""
m1.v24=""
m1.mout=true
m1.iconSize=8
m1.closeDelay=1000
m1.tlmOrigBg="#ffffff" //first frame mouseout color//
m1.tlmOrigCol="Black"
m1.v25=false
m1.v52=false
m1.v60=0
m1.v11=false
m1.v10=0
m1.ppLeftPad=5
m1.v54=0
m1.v01=2
m1.tlmHlBg="#F4F4F4" //first frame mouseover color//
m1.tlmHlCol="white"
m1.borderCol="#0c6774"
m1.menuHorizontal=true
m1.scrollHeight=6
m1.attr=new Array("11px",false,false,"#ffffff","#035a67","#000000","Arial","#F0F8D0","Black","#FEE9A6")

admin2=new Array
(
"Master User","/member.php",0,"",""
,"Master-Subuser","/membersubuser.php",0,"",""
,"Credit Approval","/credit.php",0,"",""
,"Client Ledger","/report_credit.php",0,"",""
,"Intelligence Report","/report/index.php?com=agents&opt=report",0,"",""
,"Alert and Notifications","/manage-notification-alert.php",0,"",""
,"Newsletter Settings","/newsletter-settings.php",0,"",""
)

admin3=new Array
(
"Members","/affiliate_user.php",0,"",""
,"Generate Payment","/affiliate_payment.php",0,"",""
,"Payment Details","/affiliatepage.php",0,"",""
)

admin5=new Array
(
"Website Master","/website.php",0,"",""
,"Website to Subuser","/websitetouser.php",0,"",""
,"Serveform Stats","/serveform/index.php?com=stats",0,"",""
)
admin6=new Array
(
"Search Alias","/manage-alias.php",0,"",""
,"New Pricing Engine","/pricing-engine.php",0,"",""
)
admin7=new Array
(
"SMS Alert Subscription","/report_sms.php",0,"",""
//,"Clientwise Credit Usage","/report_clientwise_usage.php",0,"",""
,"Sales (Client Wise)","/report_client.php",0,"",""
,"Sales (Enquiry Wise)","/report_lead.php",0,"",""
,"Generation & Sales (Portal)","/report_gen.php",0,"",""
,"Generation & Sales (Date)","/report_gen_date.php",0,"",""
,"Credit Sales & Revenue","/serveform/index.php?com=report&opt=revenue",0,"",""
,"Credit Allotment","/report_credit_allotment.php",0,"",""
,"Lost Clients","/report-lost-client.php",0,"",""
,"Client Ledger","/report_credit.php",0,"",""
,"Preferred Report","/report-preferred.php",0,"",""
,"Lead Quality Report","/report-lead-quality.php",0,"",""
,"Client Opening/Closing Balance","/report-opening-closing-balance.php",0,"",""
,"Autobuy","/report-autobuy.php",0,"",""
,"Clientwise Monthly Status","/report-clientwise-monthly-status.php",0,"",""
,"Clientwise Segmentation","/report-clientwise-segmentation.php",0,"",""
,"Country Wise(Generation & Sales)","/report-country-wise-lead-generation.php",0,"",""
,"Manual Email to Client","/report-enquiry-email-to-client.php",0,"",""
)
admin8=new Array
(
"New Signup","/member-listing.php?newsignup=true",0,"",""
,"Pending STS","/member-listing.php?pendingsts=true",0,"",""
,"Win Back","/report-clientwise-monthly-status.php?winback=yes",0,"",""
,"Refill","/report-clientwise-monthly-status.php?refill=yes",0,"",""
)
report=new Array
(
"SMS Alert Subscription","/report_sms.php",0,"",""
//,"Clientwise Credit Usage","/report_clientwise_usage.php",0,"",""
,"Sales (Client Wise)","/report_client.php",0,"",""
,"Sales (Enquiry Wise)","/report_lead.php",0,"",""
,"Generation & Sales (Portal)","/report_gen.php",0,"",""
,"Generation & Sales (Date)","/report_gen_date.php",0,"",""
,"Credit Sales & Revenue","/serveform/index.php?com=report&opt=revenue",0,"",""
,"Credit Allotment","/report_credit_allotment.php",0,"",""
,"Lost Clients","/report-lost-client.php",0,"",""
,"Client Ledger","/report_credit.php",0,"",""
,"Lead Quality Report","/report-lead-quality.php",0,"",""
,"Client Opening/Closing Balance","/report-opening-closing-balance.php",0,"",""
,"Autobuy","/report-autobuy.php",0,"",""
,"Clientwise Monthly Status","/report-clientwise-monthly-status.php",0,"",""
,"Clientwise Segmentation","/report-clientwise-segmentation.php",0,"",""
,"Manual Email to Client","/report-enquiry-email-to-client.php",0,"",""
)


master4=new Array
(
"Buy More Credits","/subuser/buyplan.php",0,"",""
,"Allocate Credits to Users","/master/allocatecredits.php",0,"",""
,"Credit Purchase History","/master/credit-purchase-history.php",0,"",""

)
master5=new Array
(
"View / Edit Profile","/master/profile.php",0,"",""
,"Create / Edit Users","/master/subuser.php",0,"",""
,"Account Activity Report","/master/account-activities.php",0,"",""
,"Alert and Notifications","/master/email-alert-notif.php",0,"",""
,"Auto Refresh Setting","/master/autorefresh-setting.php",0,"",""
)
master6=new Array
(
"AutoBuy Settings","/master/autobuy-settings.php",0,"",""
,"AutoBuy Reports","/master/autobuy-reports.php",0,"",""
)
subuser6=new Array
(
"AutoBuy Settings","/subuser/autobuy-settings.php",0,"",""
,"AutoBuy Reports","/subuser/autobuy-reports.php",0,"",""
)
subuser5=new Array
(
"View / Edit Profile","/subuser/profile.php",0,"",""
,"Alert and Notifications","/subuser/email-alert-notif.php",0,"",""
,"Auto Refresh Setting","/subuser/autorefresh-setting.php",0,"",""
)

cc1=new Array
(
"Search","/member.php",0,"",""
,"New Signup","/member-listing.php?newsignup=true",0,"",""
,"Pending STS","/member-listing.php?pendingsts=true",0,"",""
)

cc2=new Array
(
"Client Ledger","/report_credit.php",0,"",""
,"Sales(Client Wise)","/report_client.php",0,"",""
,"Client Monthly Status","/report-clientwise-monthly-status.php",0,"",""
,"Alert & Notifications","/manage-notification-alert.php",0,"",""
,"Manual Email to Client","/report-enquiry-email-to-client.php",0,"",""
,"Win Back","/report-clientwise-monthly-status.php?winback=yes",0,"",""
,"Refill","/report-clientwise-monthly-status.php?refill=yes",0,"",""
)

affiliate=new Array
(
"Members","/affiliate_user.php",0,"",""
,"Generate Payment","/affiliate_payment.php",0,"",""
,"Payment Details","/affiliatepage.php",0,"",""
,"Generaton & Sales","/report_gen.php",0,"",""
,"Serveform Stats","/serveform/index.php?com=stats",0,"",""
)

qc1=new Array
(
		"Master User","/member.php",0,"",""
		,"Credit Approval","/credit.php",0,"",""
		,"Client Ledger","/report_credit.php",0,"",""
		,"Sales (Clientwise)","/report_client.php",0,"",""
		,"Credit Allotment","/report_credit_allotment.php",0,"",""
		,"Generation & Sales (Date)","/report_gen_date.php",0,"",""
)


absPath=""
if(m1.v19&&!m1.v20){
if(window.location.href.lastIndexOf("\\")>window.location.href.lastIndexOf("/")) {sepCh = "\\" ;} else {sepCh = "/" ;}
absPath=window.location.href.substring(0,window.location.href.lastIndexOf(sepCh)+1)}
m1.v61=0
m1.v02=m1.v23
document.write("<style type='text/css'>\n.m1CL0,.m1CL0:link{text-decoration:none;width:100%;color:Black; }\n.m1CL0:visited{color:Black}\n.m1CL0:hover{text-decoration:underline}\n.m1mit{padding-left:15px;padding-right:15px;color:Black; font-family:Arial; font-size:11px; }\n"+"</"+"style>")
document.write("<script language='JavaScript1.2' src='https://agents.hellotravel.com/javascript/menu_dom-top-nav.js'></"+"script>")

//drop down menu end

var prevShowDivId;
//to close the popup
function MM_showHideLayers() { //v9.0

	var i,p,v,obj,args=MM_showHideLayers.arguments;
	var prevShowDivObj;
	for (i=0; i<(args.length-2); i+=3)
	with (document) if (getElementById && ((obj=getElementById(args[i]))!=null)) {
		v=args[i+2];
		if (obj.style) {
			obj=obj.style; v=(v=="show")?"visible":(v=="hide")?"hidden":v;
		}
		prevShowDivObj = getElementById(prevShowDivId);
		if (v == "visible" && prevShowDivObj != null) {
			prevShowDivObj.style.visibility = "hidden";
		}
		obj.visibility=v;
		if (v == "visible") {
			prevShowDivId = args[0];
		}
	}

}

function countryupdate(country,countryname)
{

	expiredays=365;
	var exdate=new Date();
	exdate.setDate(exdate.getDate()+expiredays);
	document.cookie="cookie_user_selected_destination_country_iso"+ "=" +escape(country)+
	((expiredays==null) ? "" : ";expires="+exdate.toGMTString());

	document.cookie="cookie_user_selected_destination_country_name"+ "=" +escape(countryname)+
	((expiredays==null) ? "" : ";expires="+exdate.toGMTString());
	location.replace('/index.php');

}
function hidebanner(id)
{
	document.getElementById(id).style.display='none';


	expiredays=30;
	var exdate=new Date();
	exdate.setDate(exdate.getDate()+expiredays);
	document.cookie="__C"+ "=" +"Y"+
	((expiredays==null) ? "" : ";expires="+exdate.toGMTString());

	expiredays=-30;
	var exdate=new Date();
	exdate.setDate(exdate.getDate()+expiredays);
	document.cookie="__B"+ "=" +"Y"+
	((expiredays==null) ? "" : ";expires="+exdate.toGMTString());

}

//new design by rashid



/*------cstomize select box----------*/

// Javascript originally by Patrick Griffiths and Dan Webb.
// //htmldog.com/articles/suckerfish/dropdowns/
sfHover = function() {
	var sfEls = document.getElementById("navbar").getElementsByTagName("li");
	for (var i=0; i<sfEls.length; i++) {
		sfEls[i].onmouseover=function() {
			this.className+=" hover";
		}
		sfEls[i].onmouseout=function() {
			this.className=this.className.replace(new RegExp(" hover\\b"), "");
		}
	}
}
if (window.attachEvent) window.attachEvent("onload", sfHover);

// for select box
var selectWidth = "170";
document.write('<style type="text/css">input.styled { display: none; } select.styled { position: relative; width: ' + selectWidth + 'px; opacity: 0; filter: alpha(opacity=0); z-index: 5; } .disabled { opacity: 0.5; filter: alpha(opacity=50); }</style>');

var Custom = {
	init: function() {
		var inputs = document.getElementsByTagName("input"), span = Array(), textnode, option, active;
		for(a = 0; a < inputs.length; a++) {
			if(inputs[a].className == "styled") {
				span[a] = document.createElement("span");
				span[a].className = inputs[a].type;
				}
		}
		inputs = document.getElementsByTagName("select");
		for(a = 0; a < inputs.length; a++) {
			if(inputs[a].className == "styled") {
				option = inputs[a].getElementsByTagName("option");
				active = option[0].childNodes[0].nodeValue;
				textnode = document.createTextNode(active);
				for(b = 0; b < option.length; b++) {
					if(option[b].selected == true) {
						textnode = document.createTextNode(option[b].childNodes[0].nodeValue);
					}
				}
				span[a] = document.createElement("span");
				span[a].className = "select";
				span[a].id = "select" + inputs[a].name;
				span[a].appendChild(textnode);
				inputs[a].parentNode.insertBefore(span[a], inputs[a]);
// 				if(!inputs[a].getAttribute("disabled")) {
// 					//inputs[a].onchange = Custom.choose;
// 				} else {
// 					inputs[a].previousSibling.className = inputs[a].previousSibling.className += " disabled";
// 				}
			}
		}
		document.onmouseup = Custom.clear;
	},
	choose: function() {
		option = this.getElementsByTagName("option");
		for(d = 0; d < option.length; d++) {
			if(option[d].selected == true) {
				document.getElementById("select" + this.name).childNodes[0].nodeValue = option[d].childNodes[0].nodeValue;
			}
		}
	}
}
window.onload = Custom.init;

/*-------toggle----------*/

function toggle(b)
    {
        document.getElementById(b).style.display = 'block';
    }
    function hide(n){
    document.getElementById(n).style.display = 'none';
    }

/*--------for fixed menu-------*/

 function getScrollTop() {
        if(typeof pageYOffset!= 'undefined') {
            //most browsers
			//alert(pageYOffset);
            return pageYOffset;
        }
        else {
            var b = document.body; //IE 'quirks'
            var d = document.documentElement; //IE with doctype
            d = (d.clientHeight) ? d : b;
           return d.scrollTop;

        }
    }


    function onScroll() {
        var menu = document.getElementById('divMyMenu');
        var headerAndNavHeight = document.getElementById('divHeader').clientHeight
            + document.getElementById('tsMain').clientHeight;
			//alert(headerAndNavHeight);
		if (getScrollTop() < headerAndNavHeight) {
           // menu.style.top = headerAndNavHeight + 'px';
            menu.style.position = 'relative';
			menu.style.width = '769px';
			document.getElementById('navdiv').style.display = 'none';
        }
        else {
            menu.style.top = '0px';
            menu.style.position = 'fixed';
			menu.style.width = '769px';
			document.getElementById('navdiv').style.display = 'block';
        }
    }

function change_to_search()
{
  document.getElementById('IODI').style.display='none';
  document.getElementById("mainnav1").setAttribute("class", "");
  document.getElementById("mainnav2").setAttribute("class", "");
  document.getElementById("mainnav3").setAttribute("class", "");
  document.getElementById("mainnav4").setAttribute("class", "");
  document.getElementById("mainnav5").setAttribute("class", "curent");

  if(document.getElementById('mainnav6')){document.getElementById("mainnav6").setAttribute("class", "");}

  document.getElementById('searchDiv').style.display='';
  document.qsearch.q.focus();

}




// function showPageDiv(id)
// {
//   document.getElementById(id).style.display='block';
//
// }
// function makeMouseOutFn(elem){
//     var list = traverseChildren(elem);
//     return function onMouseOut(event) {
//         var e = event.toElement || event.relatedTarget;
//         if (!!~list.indexOf(e)) {
//             return;
//         }
//         this.style.display='none';
//         // handle mouse event here!
// };
// }
//
//
//
//
// function traverseChildren(elem){
//     var children = [];
//     var q = [];
//     q.push(elem);
//     while (q.length>0)
//     {
//         var elem = q.pop();
//         children.push(elem);
//         pushAll(elem.children);
//     }
//         function pushAll(elemArray){
//             for(var i=0;i<elemArray.length;i++)
//             {
//                 q.push(elemArray[i]);
//             }
//
//         }
//         return children;
// }
