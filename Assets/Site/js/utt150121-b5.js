/* Unreal Tournament Stats Tracker
 * JavaScript Client Something Blablabla
 * 
 * 2004, 2009, 2013-2015 namonaki14, WaldoMG
 * 
 * This piece of code is distributed under the terms of
 * "WOLVER'S GENERAL "CR@P" LICENSE"
 * that can be found at:
 * GOOGLE.COM > SRAQ23_LIC_EN.TXT
 * 
 * Changelog:
 * 
 * '14-07-20  1  Created -n
 * '14-11-05  3  client-side tablething -n
 * '14-12-28  4  compatibility with n14tablething -n
 * '15-01-21  5  TableThingObjects->static, disable XHR when accessing from different domain -n
 * 
 * TODO:
 * * credits
 * 
*/


/*------------------------*\
        TableThing
\*------------------------*/

/* JS PART IS BROKEN!! */

function TableThing(tobj){
	var tableData;
	var columns=this.columns={};
	
	var isCached=false;
	var tempArray={};
	var sortColumn;
	var sortOrder=this.SORT_ASC;
	var skipCurrentRow=false;
	
	var tableObject=tobj;
	var thead=this.thead=tableObject.getElementsByTagName("thead")[0];;
	var tbody=this.tbody=tableObject.getElementsByTagName("tbody")[0];;
	var paginationObject;
	
	var uniqId=tableObject.dataset.tableid;
	
	
	var dataLastUpdated=0;
	var htmlClass=tableObject.className;
	var htmlId=tableObject.id;
	
	var htmlIdColumn=tableObject.dataset.htmlIdColumn;
	
	var allowSorting = (tableObject.querySelectorAll("thead th a").length > 0);
	
	// |  This is how you declare the static members of classes/functions
	// v  I swear, JS is one of the most wicked languages out there
	if(!TableThing.TableThingObjects) TableThing.TableThingObjects=[]; 

	this.SORT_ASC=4;
	this.SORT_DESC=3;
	
	this.CONTENT_TEXT=0;
	this.CONTENT_NUM=1;
	this.CONTENT_HTML=2;
	this.CONTENT_NUMFLOAT=3;
	this.CONTENT_FMTNUM=4;

	var dataSourceUrl=tableObject.dataset.siteurl + "/TableThingSorter.php";

	var viewLimit;
	var totalItems;
	var viewOffset;
	
	// exports
	
	this.uniqId=uniqId;
	this.viewLimit=viewLimit;
	this.totalItems=totalItems;
	this.viewOffset=viewOffset;
	this.sortColumn=sortColumn;
	this.sortOrder=sortOrder;
	this.saveStateAfterReload=false;
	
	var ttThis=this;
	
	
	
	var xhr=XMLRequestCreate();
	
	
	//console.log(xhr);
	
	var initProperties = this.initProperties = function(){
		viewLimit = tableObject.dataset.viewLimit;
		totalItems = tableObject.dataset.totalItems;
		viewOffset = tableObject.dataset.viewOffset;
		
		sortColumn = tbody.dataset.sortBy;
		sortOrder = 4; //tbody.dataset.sortOrder;
		
		ttThis.viewLimit=viewLimit;
		ttThis.totalItems=totalItems;
		ttThis.viewOffset=viewOffset;
		ttThis.sortColumn=sortColumn;
		ttThis.sortOrder=sortOrder;
		
		console.log("TTi: " + uniqId + " items:"+totalItems + " DVL: "+viewLimit+" OFF:"+viewOffset);
	
	};
	

	var genHTML = function(){
		if (!tempArray.columninfo || !tempArray.data) return;
		
		clearTBody();
		columns=tempArray.columninfo;
		tableData=tempArray.data;
		
		
		
		var tableRow;
		var rowData;
		var colInfo;
		var colContent;
		
		var rowId, colId;
		var htmlClass;
		
		for(rowId=0; rowId<tableData.length; rowId++){
		
			rowData=tableData[rowId];
			tableRow=document.createElement("tr");
			//tableRow.dataset.rid= // todo
			tableRow.id=rowData[htmlIdColumn];
			tableRow.innerHTML="";
			for(colId=0; colId<columns.length; colId++){
				colInfo=columns[colId];
				if(colInfo.hidden==1) continue;
				
				switch(colInfo.contentType){
					case 0:
						colContent=htmlspecialchars(rowData[colInfo.displayKey]);
						break;
					case 4:
					case 3:
					case 2:
					case 1:
						colContent=rowData[colInfo.displayKey];
						break;
					
				}
				if(colInfo.htmlClass!="") htmlClass=" class=\"" + colInfo.htmlClass + "\"";
				else htmlClass="";
				
				tableRow.innerHTML+="<td data-cid='"+colInfo.displayKey+"' data-sv=\""+rowData['sortable_'+colInfo.displayKey]+"\""+htmlClass+">"+colContent+"</>";
			}
			tbody.appendChild(tableRow);
		}
		
		if(paginationObject) pagination_set_page(paginationObject,viewOffset/viewLimit+1);
	};
	
	var clearTBody = function(){
		while(tbody.firstChild) {
			tbody.removeChild(tbody.firstChild);
		}
	};

	this.loadDataFor = function(offset,sorting,sortorder){
		var url=dataSourceUrl+"?fetchJSON="+uniqId;
		if(sorting) {
			url+="&sort="+sorting;
			if(sortorder) url+="&order="+(sortorder=="3"?"d":"a");
		}
		url+="&offset="+offset;
		url+="&limit="+viewLimit;
		
		tableObject.dataset.viewOffset=offset;
		
		XMLRequestGET(xhr, false, url, this.parseData);
	};
	
	this.parseData = function(e){
		if (xhr.status === 200) {
			var jsonCode=xhr.responseText;
			//console.log(jsonCode);
			tempArray=JSON.parse(jsonCode);
			
			initProperties();
			
			//console.log(tempArray);
			genHTML();
			
			hState={};
			
			for(var i = 0; i<TableThing.TableThingObjects.length; i++){
				hState[TableThing.TableThingObjects[i].uniqId]=TableThing.TableThingObjects[i].saveState();
			}
			if(ttThis.saveStateAfterReload){
				var query = parseQuery(window.location.search.substring(1));
				query["sort"]=tbody.dataset.sortBy;
				query["order"]=(tbody.dataset.sortOrder=="3"?"d":"a");
				query["p"+tableObject.dataset.tableid]=ttThis.viewOffset/ttThis.viewLimit + 1;
				var newQueryStr = buildQuery(query);
				var newUrl=window.location.pathname + "?" + newQueryStr;
				history.pushState(hState,"",newUrl);
			}
		}
	};
	
	this.saveState = function(){
		return {"offset":ttThis.viewOffset,"sortColumn":ttThis.sortColumn,"sortOrder":ttThis.sortOrder,"tempArray":JSON.stringify(tempArray)};
	};
	
	this.restoreState = function(state){
		//var stateChanged = (tableObject.dataset.viewOffset != state.offset || tbody.dataset.sortBy != state.sortColumn || tbody.dataset.sortOrder != state.sortOrder);
		/*tableObject.dataset.viewOffset = state.offset;
		tbody.dataset.sortBy = state.sortColumn;
		tbody.dataset.sortOrder = state.sortOrder;*/
		//if(stateChanged) {
			this.saveStateAfterReload=false;
			initProperties();
			if(state.tempArray=="{}"){
				this.loadDataFor(state.offset,state.sortColumn,state.sortOrder);
			}else{
				tempArray=JSON.parse(state.tempArray);
				genHTML();
			}
			//this.loadDataFor(state.offset,state.sortColumn,state.sortOrder);
		//}
	}
	
	/*this.loadDataAndRender=function(offset,sorting,sortorder){
		this.loadDataFor(offset,sorting,sortorder);
	}*/
	
	
	this.bindXHR = function(){ 
		return; // remove when the code is fixed
		if(tableObject.dataset.noXhr) 
			return;
	
		var headCells=thead.getElementsByTagName("th");
		var cellLink;
		
		
		
		for(var x=0; x<headCells.length; x++){
			if(cellLink=headCells[x].querySelector("a")) {
				cellLink.href="javascript:ttSetSorting(\""+uniqId+"\",\""+headCells[x].dataset.cid+"\");"; //todo: addeventlistener
			}
		}
		
		// hijack the pagination element
		var pag=document.getElementById("ttpag"+uniqId);
		if(!pag) return;
		paginationObject=pag;
		pag.dataset.format="javascript:ttLoadPage(\""+uniqId+"\",%1$d);"; // dirty
		pagination_scroll(pag,0);
	};
	
	this.getColumnByKeyName = function(cid){ //todo
		console.log(ttThis.columns);
		for(colId=0; colId<ttThis.columns.length; colId++){
			console.log(ttThis.columns[colId].displayKey);
			if(ttThis.columns[colId].displayKey==cid) return ttThis.columns[colId];
		}
		//return 0;
	};
	
	this.initProperties();
}

function parseQuery(queryString){
	var queryArrTmp = queryString.split('&');
	var queryArr={};
	queryArrTmp.forEach(function(queryEl) {
		var queryElSplit = queryEl.split('=');
		queryArr[decodeURIComponent(queryElSplit[0])]=decodeURIComponent(queryElSplit[1]);
	});
	return queryArr;
}

function buildQuery(queryArr){
	var queryArrTmp=new Array();
	var queryIdx;
	for(queryIdx in queryArr) {
		if(queryIdx)
			queryArrTmp.push(encodeURIComponent(queryIdx) + "=" + encodeURIComponent(queryArr[queryIdx]));
	};
	return queryArrTmp.join("&");
	
}

function ttLoadPage(ttId,pagenum){
	var ttObj;
	for(var i = 0; i<TableThing.TableThingObjects.length; i++){
		if(TableThing.TableThingObjects[i].uniqId==ttId){
			ttObj=TableThing.TableThingObjects[i];
		}
	}
	if(!ttObj) return;
	if(!pagenum) pagenum=ttObj.viewOffset/ttObj.viewLimit + 1;
	//console.log("SO:"+ttObj.sortOrder);
	ttObj.saveStateAfterReload=true;
	ttObj.loadDataFor((pagenum-1)*ttObj.viewLimit,ttObj.sortColumn,ttObj.sortOrder);
	
	
}

function ttSetSorting(ttId,colname){
	var ttObj;
	for(var i = 0; i<TableThing.TableThingObjects.length; i++){
		if(TableThing.TableThingObjects[i].uniqId==ttId){
			ttObj=TableThing.TableThingObjects[i];
		}
	}
	if(!ttObj) return;
	
	if(ttObj.tbody.dataset.sortBy==colname){
		ttObj.tbody.dataset.sortOrder=(ttObj.tbody.dataset.sortOrder==3?"4":"3");
	}else{
		ttObj.tbody.dataset.sortBy=colname;
		ttObj.tbody.dataset.sortOrder=ttObj.SORT_ASC; //ttObj.getColumnByKeyName(colname).sortOrder;
	}
	ttObj.initProperties();
	ttLoadPage(ttId);
}

var hState={};
	



window.onpopstate = function(event) {
	//alert("location: " + document.location + ", state: " + JSON.stringify(event.state));
	var TTidx;
	for(TTidx in event.state){
		for(var i = 0; i<TableThing.TableThingObjects.length; i++){
			if(TableThing.TableThingObjects[i].uniqId==TTidx){
				TableThing.TableThingObjects[i].restoreState(event.state[TTidx]);
			}
		}
	}
}

// bind to existing ntt objects
document.addEventListener("DOMContentLoaded", function(){
	var nttEls=document.querySelectorAll(".n14table");
	var nttObj;
	hState={};
	for(var i=0; i<nttEls.length; i++){
		nttObj=new TableThing(nttEls[i]);
		
		
		//nttObj.loadDataFor(4);
		
		if(TableThing.allowXHR) nttObj.bindXHR();
		
		TableThing.TableThingObjects.push(nttObj);
		
		hState[nttObj.uniqId]=nttObj.saveState();
	}
	var newUrl=window.location.pathname + window.location.search;
	history.replaceState(hState,"",newUrl);
});






/* NemoPagination
 * Originally written for DSC CMS
 * 2009 namo
 * "drogi JA z 2013r, przepraszam za to co tu pope³ni³em" ~namo
 * (dear ME from 2013, i'm sorry for everything i've done in this file)
 */

document.addEventListener("DOMContentLoaded", function(){
	var pag_els=document.querySelectorAll(".pagination");
	for(var i=0; i<pag_els.length; i++){
		pagination_reset_events(pag_els[i]);
	}
});

function pagination_reset_events(el){
	var buttons=el.querySelectorAll("a[data-scrolldelta]");
	for(var i=0; i<buttons.length; i++){
		buttons[i].addEventListener("click",function(ev){
			ev.preventDefault();
			pagination_scroll(el,this.dataset.scrolldelta);
		});
	}
}
 
function pagination_set_page(el,page){
	var current=parseInt(el.dataset.cur);
	var viewstart=page-3;
	var delta =  (viewstart-parseInt(document.querySelector(".pagination_data").dataset.viewstart)) ;
	el.dataset.cur=page;
	
	
	
	pagination_scroll(el,delta);
}

function pagination_scroll(el,delta) {
	// there's also a php version of this function 
	// generating the same code server-side
	var pagdata=el.dataset;
	var size=parseInt(pagdata.maxp);
	var current=parseInt(pagdata.cur);
	var viewstart = parseInt(document.querySelector(".pagination_data").dataset.viewstart);

	viewstart=viewstart+parseInt(delta);

	if(viewstart > size-8) viewstart=size-8;
	if(viewstart < 1) viewstart=1;


	var format=pagdata.format;
	var res='';

	res+="<span class=\"pagination_data\" data-viewstart=\""+viewstart+"\"></>";
	if(viewstart >1){
		res+=sprintf("<a href='"+format+"' class='pagnavi' data-scrolldelta=\"-1\">&#x00AB;</a>\n", 1);
	}else{
		res+="<strong class='pagnavi'>&#x00AB;</strong>\n";
	}
	if(current==1){
		res+="<strong class='pagnavi'>&#x2039; Prev.</strong>\n";
	}else{
		res+=sprintf("<a href='"+format+"' class='pagnavi'>&#x2039; Prev.</a>\n", current-1);
	}
	
	
	for(var ii=viewstart; ii < viewstart+8; ii++)
	{
		if(ii >= 1 && ii <= size) 
		{
			if(ii==current)
				res+="<strong class='pagenum'>" + (current) + "</strong>\n";
			else
				res+=sprintf("<a href='"+format+"' class='pagenum'>"+ (ii) +"</a>\n", ii);
		}
	}
	if(current < size-1){
		res+=sprintf("<a href='"+format+"' class='pagnavi'>Next &#x203A;</a>\n", current+1);
	}else{
		res+=sprintf("<strong class='pagnavi'>Next &#x203A;</strong>\n", current+1);
	}
	
	if(viewstart < size-8){
		res+=sprintf("<a href='"+format+"' class='pagnavi' data-scrolldelta=\"1\">&#x00BB;</a>\n", size);
	}else{
		res+="<strong class='pagnavi'>&#x00BB;</strong>\n";

	}
	
	el.innerHTML=res;
	pagination_reset_events(el);
 
}



/**
sprintf() for JavaScript 0.7-beta1
http://www.diveintojavascript.com/projects/javascript-sprintf

Copyright (c) Alexandru Marasteanu <alexaholic [at) gmail (dot] com>
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:
    * Redistributions of source code must retain the above copyright
      notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.
    * Neither the name of sprintf() for JavaScript nor the
      names of its contributors may be used to endorse or promote products
      derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL Alexandru Marasteanu BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

var sprintf = (function() {
	function get_type(variable) {
		return Object.prototype.toString.call(variable).slice(8, -1).toLowerCase();
	}
	function str_repeat(input, multiplier) {
		for (var output = []; multiplier > 0; output[--multiplier] = input) {/* do nothing */}
		return output.join('');
	}

	var str_format = function() {
		if (!str_format.cache.hasOwnProperty(arguments[0])) {
			str_format.cache[arguments[0]] = str_format.parse(arguments[0]);
		}
		return str_format.format.call(null, str_format.cache[arguments[0]], arguments);
	};

	str_format.format = function(parse_tree, argv) {
		var cursor = 1, tree_length = parse_tree.length, node_type = '', arg, output = [], i, k, match, pad, pad_character, pad_length;
		for (i = 0; i < tree_length; i++) {
			node_type = get_type(parse_tree[i]);
			if (node_type === 'string') {
				output.push(parse_tree[i]);
			}
			else if (node_type === 'array') {
				match = parse_tree[i]; // convenience purposes only
				if (match[2]) { // keyword argument
					arg = argv[cursor];
					for (k = 0; k < match[2].length; k++) {
						if (!arg.hasOwnProperty(match[2][k])) {
							throw(sprintf('[sprintf] property "%s" does not exist', match[2][k]));
						}
						arg = arg[match[2][k]];
					}
				}
				else if (match[1]) { // positional argument (explicit)
					arg = argv[match[1]];
				}
				else { // positional argument (implicit)
					arg = argv[cursor++];
				}

				if (/[^s]/.test(match[8]) && (get_type(arg) != 'number')) {
					throw(sprintf('[sprintf] expecting number but found %s', get_type(arg)));
				}
				switch (match[8]) {
					case 'b': arg = arg.toString(2); break;
					case 'c': arg = String.fromCharCode(arg); break;
					case 'd': arg = parseInt(arg, 10); break;
					case 'e': arg = match[7] ? arg.toExponential(match[7]) : arg.toExponential(); break;
					case 'f': arg = match[7] ? parseFloat(arg).toFixed(match[7]) : parseFloat(arg); break;
					case 'o': arg = arg.toString(8); break;
					case 's': arg = ((arg = String(arg)) && match[7] ? arg.substring(0, match[7]) : arg); break;
					case 'u': arg = Math.abs(arg); break;
					case 'x': arg = arg.toString(16); break;
					case 'X': arg = arg.toString(16).toUpperCase(); break;
				}
				arg = (/[def]/.test(match[8]) && match[3] && arg >= 0 ? '+'+ arg : arg);
				pad_character = match[4] ? match[4] == '0' ? '0' : match[4].charAt(1) : ' ';
				pad_length = match[6] - String(arg).length;
				pad = match[6] ? str_repeat(pad_character, pad_length) : '';
				output.push(match[5] ? arg + pad : pad + arg);
			}
		}
		return output.join('');
	};

	str_format.cache = {};

	str_format.parse = function(fmt) {
		var _fmt = fmt, match = [], parse_tree = [], arg_names = 0;
		while (_fmt) {
			if ((match = /^[^\x25]+/.exec(_fmt)) !== null) {
				parse_tree.push(match[0]);
			}
			else if ((match = /^\x25{2}/.exec(_fmt)) !== null) {
				parse_tree.push('%');
			}
			else if ((match = /^\x25(?:([1-9]\d*)\$|\(([^\)]+)\))?(\+)?(0|'[^$])?(-)?(\d+)?(?:\.(\d+))?([b-fosuxX])/.exec(_fmt)) !== null) {
				if (match[2]) {
					arg_names |= 1;
					var field_list = [], replacement_field = match[2], field_match = [];
					if ((field_match = /^([a-z_][a-z_\d]*)/i.exec(replacement_field)) !== null) {
						field_list.push(field_match[1]);
						while ((replacement_field = replacement_field.substring(field_match[0].length)) !== '') {
							if ((field_match = /^\.([a-z_][a-z_\d]*)/i.exec(replacement_field)) !== null) {
								field_list.push(field_match[1]);
							}
							else if ((field_match = /^\[(\d+)\]/.exec(replacement_field)) !== null) {
								field_list.push(field_match[1]);
							}
							else {
								throw('[sprintf] huh?');
							}
						}
					}
					else {
						throw('[sprintf] huh?');
					}
					match[2] = field_list;
				}
				else {
					arg_names |= 2;
				}
				if (arg_names === 3) {
					throw('[sprintf] mixing positional and named placeholders is not (yet) supported');
				}
				parse_tree.push(match);
			}
			else {
				throw('[sprintf] huh?');
			}
			_fmt = _fmt.substring(match[0].length);
		}
		return parse_tree;
	};

	return str_format;
})();

var vsprintf = function(fmt, argv) {
	argv.unshift(fmt);
	return sprintf.apply(null, argv);
};

// Simple XMLHTTP Request
// 2004, 2009 WaldeMGoRZ
//

 function XMLRequestCreate() {
  tmpreq=false;
  if (window.XMLHttpRequest) 
   tmpreq=new XMLHttpRequest();
  else if (window.ActiveXObject) 
   tmpreq=new ActiveXObject('Microsoft.XMLHTTP');
  return tmpreq;
 }

 function XMLRequestGET(request, returnXML, url, onready) {
  request.open("GET", url, true);
  request.onreadystatechange = function() {
   if (request.readyState == 4 && request.status == 200) {
    if(returnXML) onready(request.responseXML);
    else onready(request.responseText);
   }
  };
  request.send(null);
 }
 
 function XMLRequestPOST(request, returnXML, url, values, onready) {
  request.open("POST", url, true);
  request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
  request.setRequestHeader("Content-length", values.length);
  request.setRequestHeader("Connection", "close");
  request.send(values);
  request.onreadystatechange = function() {
   if (request.readyState == 4 && request.status == 200) {
    if(returnXML) onready(request.responseXML);
    else onready(request.responseText);
   }
  };
 }
 
//http://www.larryullman.com/forums/index.php?/topic/2880-php-htmlspecialchars-in-javascript/#entry17688
function htmlspecialchars(str) {
  return str.replace('&', '&amp;').replace('"', '&quot;').replace("'", '&#039;').replace('<', '&lt;').replace('>', '&gt;');
}