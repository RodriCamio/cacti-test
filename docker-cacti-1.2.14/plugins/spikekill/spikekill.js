var xmlHttp
var url
var graphChanges=1

function getfromserver(baseurl) {
	xmlHttp=GetXmlHttpObject()
	if (xmlHttp==null) {
		alert ("Get Firefox!")
		return
	}

	xmlHttp.onreadystatechange=stateChanged
	xmlHttp.open("GET",baseurl,true)
	xmlHttp.send(null)
}

function removeSpikesStdDev(local_graph_id) {
	url="plugins/spikekill/spikekill_ajax.php?method=stddev&local_graph_id="+local_graph_id+"&src="+escape(document.getElementById("graph_"+local_graph_id).src)
	document.getElementById("sk"+local_graph_id).src="plugins/spikekill/images/spikekill_busy.gif"
	getfromserver(url)
}

function removeSpikesVariance(local_graph_id) {
	url="plugins/spikekill/spikekill_ajax.php?method=variance&local_graph_id="+local_graph_id+"&src="+escape(document.getElementById("graph_"+local_graph_id).src)
	document.getElementById("sk"+local_graph_id).src="plugins/spikekill/images/spikekill_busy.gif"
	getfromserver(url)
}

function dryRunStdDev(local_graph_id) {
	url="plugins/spikekill/spikekill_ajax.php?method=stddev&dryrun=true&local_graph_id="+local_graph_id+"&src="+escape(document.getElementById("graph_"+local_graph_id).src)
	document.getElementById("sk"+local_graph_id).src="plugins/spikekill/images/spikekill_busy.gif"
	getfromserver(url)
}

function dryRunVariance(local_graph_id) {
	url="plugins/spikekill/spikekill_ajax.php?method=variance&dryrun=true&local_graph_id="+local_graph_id+"&src="+escape(document.getElementById("graph_"+local_graph_id).src)
	document.getElementById("sk"+local_graph_id).src="plugins/spikekill/images/spikekill_busy.gif"
	getfromserver(url)
}

function hideSpikeResponse() {
	div = document.getElementById("spikekill")

	div.style.visibility = "hidden";
}

function getScrollTop() {
	var scrOfY = 0
	if (typeof(window.pageYOffset) == 'number') {
		scrOfY = window.pageYOffset
	}else if (document.body && document.body.scrollTop) {
		scrOfY = document.body.scrollTop
	}else if (document.documentElement && document.documentElement.scrollTop) {
		scrOfY = document.documentElement.scrollTop
	}

	return parseInt(scrOfY)
}

function onOverTitle() {
	overTitle = true
}

function offOverTitle() {
	overTitle = false
}

function stateChanged() {
	if (xmlHttp.readyState==4 || xmlHttp.readyState=="complete") {
		reply          = xmlHttp.responseText
		reply          = reply.split("!!!!")
		local_graph_id = reply[0]
		response       = reply[1]
		graph          = reply[2]

		document.getElementById("sk"+local_graph_id).src="plugins/spikekill/images/spikekill.gif"
		width  = parseInt(document.getElementsByTagName("body")[0].clientWidth);
		height = parseInt(document.getElementsByTagName("body")[0].clientHeight);
		myscroll = getScrollTop();

		twidth = 850
		if (width-twidth > 0) {
			position_left = parseInt((width-twidth)/2)
		}else{
			position_left = 0
		}

		div    = document.getElementById("spikekill")

		document.onmousedown=startMoveMe;
		document.onmouseup=doneMoveMe;
		document.onmousemove=movingMoveMe;

		prefix = "<div id='spikekill_window' class='spikekill_window' style='width:auto;overflow:hidden;position:relative;top:0px;left:0px;z-index:0;'><table onMouseOver='onOverTitle()' onMouseOut='offOverTitle()' cellpadding=0 cellspacing=0 border=0><tr><td><table class='spikekill_header'><tr><td width='1%'><input type='button' value='Close Window' onClick='hideSpikeResponse()'></td><td style='text-align:left;'>Spike Kill Results</td></tr></table>"
		suffix = "</td></tr></table></div>"

		//alert(prefix+response+suffix)
		div.innerHTML=prefix+response+suffix
		div.style.position="absolute"
		div.style.left=position_left+"px"
		div.style.top=parseInt(myscroll+50)+"px"
		div.style.visibility="visible"
		div.style.display="block"

		document.getElementById("graph_"+local_graph_id).src=graph+"&updates="+graphChanges
		graphChanges++
	}
}

var MOUSESTART_X
var MOUSESTART_Y
var SpikeX
var SpikeY
var browser
var moving
var overTitle

function spikeKillDetectBrowser() {
	if (navigator.userAgent.indexOf('MSIE') >= 0) {
		browser = "IE";
	}else if (navigator.userAgent.indexOf('Mozilla') >= 0) {
		browser = "FF";
	}else if (navigator.userAgent.indexOf('Opera') >= 0) {
		browser = "Opera";
	}else{
		browser = "Other";
	}
}

function startMoveMe(event) {
	if (!event) event = window.event;
	if (!browser) spikeKillDetectBrowser()

	if (overTitle) {
		/* get the location of the spikekill window */
		div    = document.getElementById("spikekill")
		SpikeX = parseInt(div.style.left);
		SpikeY = parseInt(div.style.top);

		MOUSESTART_X = event.clientX;
		MOUSESTART_Y = event.clientY;

		moving = true
	}
}

function movingMoveMe(event) {
	if (!event) event = window.event;
	if (!browser) spikeKillDetectBrowser()

	if (overTitle) {
		/* let's see how wide the page is */
		deltaX = event.clientX - MOUSESTART_X;
		deltaY = event.clientY - MOUSESTART_Y;

		/* we are going to move this object */
		if (moving) {
			div    = document.getElementById("spikekill")
			if (browser != "IE") div.style.left = parseInt(SpikeX+deltaX)+"px"
			div.style.top  = parseInt(SpikeY+deltaY)+"px"
		}

		if ((browser == 'IE') && (document.selection)) {
			document.selection.empty();
		}else if (window.getSelection) {
			window.getSelection().removeAllRanges();
		}
	}
}

function doneMoveMe(event) {
	if (!event) event = window.event;
	if (!browser) spikeKillDetectBrowser()

	if ((browser == 'IE') && (document.selection)) {
		document.selection.empty();
	} else if(window.getSelection) {
		window.getSelection().removeAllRanges();
	}

	moving = false
}

function GetXmlHttpObject() {
	var objXMLHttp=null
	if (window.XMLHttpRequest) {
		objXMLHttp=new XMLHttpRequest()
	}
	else if (window.ActiveXObject) {
		objXMLHttp=new ActiveXObject("Microsoft.XMLHTTP")
	}
	return objXMLHttp
}
