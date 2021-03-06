/* Author: Joaquin Vanschoren

Main scripts for managing the data flow and interaction logic
*/


$(document).ready(function() {
	$('.selectpicker').selectpicker();
});

function expdbDatasets() {
	return <?php echo array_to_js_array( $this->datasets ); ?>;
}

function expdbDatasetVersion() {
	return <?php echo array_to_js_array( $this->datasetVersion ); ?>;
}

function expdbDatasetVersionOriginal() {
	return <?php echo array_to_js_array( $this->datasetVersionOriginal ); ?>;
}

function expdbDatasetIDs() {
	return <?php echo array_to_js_array( $this->datasetIds ); ?>;
}

function expdbEvaluationMetrics() {
	return <?php echo array_to_js_array( $this->evaluationMetrics ); ?>;
}

function expdbAlgorithms() {
	return <?php echo array_to_js_array( $this->algorithms ); ?>;
}

function expdbImplementations() {
	return <?php echo array_to_js_array( $this->implementations ); ?>;
}

function expdbTaskTypes() {
	return <?php echo array_to_js_array( $this->taskTypes ); ?>;
}


var expdburl = "<?php echo BASE_URL; ?>";

var chart;
var linechart;
var table;
var numeric;
var categories;

var autocrosstabulate = false;
var show_original_data = true;
var data_orig;
var data_cross;
var series;
var redrawScatterRequest = false;
var redrawLineRequest = false;

var resultTableMaxCols = 5;

$(function() {
  var datasets = expdbDatasets();
  var evaluationmetrics = expdbEvaluationMetrics();
  var algorithms = expdbAlgorithms();
  var implementations = expdbImplementations();
  var filteredImplementations = getImplementationsWithAlgorithms( ['SVM', 'C4.5'] ); // TODO: bind to algorithm field

  makeCommaSeperatedAutoComplete( "#datasetDropdown", datasets );                                           // run search
  makeCommaSeperatedAutoComplete( "#algorithmDropdown", algorithms );                                       // run search
  makeCommaSeperatedAutoComplete( "#implementationDropdown", filteredImplementations );                     // run search
  makeCommaSeperatedAutoComplete( "#searchLearningcurvesImplementationDropdown", implementations );         // learning curve search
  makeCommaSeperatedAutoComplete( "#searchLearningcurvesDatasetDropdown", datasets );                       // learning curve search

  $( "#evaluationmetricDropdown" ).autocomplete({
    source: evaluationmetrics,
		minLength: 1
  });
});



function whenAvailable(name, callback) {
    var interval = 10; // ms
    window.setTimeout(function() {
        if (window[name]) {
            callback(window[name]);
        } else {
            window.setTimeout(arguments.callee, interval);
        }
    }, interval);
}
// launches a query
function runQuery(theQuery) {
	localStorage.query=theQuery;
	if (theQuery.length == 0){
		$('.sqlmessage').css({"display":"inline-block"});
		$('#sqlquery-btn').button('reset');
		$('#query-btn').button('reset');
		$('.sqlmessage').html('Query is empty or could not be parsed.');
	}
	var query =  encodeURI(expdburl+"api_query/?q="+encodeURIComponent(theQuery)+"&id=", "UTF-8");
	qi_waiting = true;
	window.idle = false;
	$.getJSON(query,processResult).error( jsonFailed );
}

function jsonFailed(data, textStatus) {
	qi_waiting = false;
	window.idle = true;
	$('.sqlmessage').css({"display":"inline-block"});
	$('#query-btn').button('reset');
	$('#sqlquery-btn').button('reset');
	$('#wizardquery-btn').button('reset');
	if(textStatus=="error")
		$('.sqlmessage').html('Error. Data could not be returned. Possibly, the result list is too long, try adding a LIMIT constraint (e.g., limit 0,1000) to your query. If that does not help, please contact the system administrator.');
	else
		$('.sqlmessage').html('Please contact the system administrator: ' + textStatus);
}

function showResultTab(){
$('#runresults').collapse('show');
$('#qtabs a[href="#resultstab"]').tab('show');
}

// stores returned JSON data
function processResult(data) {
	qi_waiting = false;
	window.idle = true;

	//handle errors
	if(data.status.substr(0,3)!="SQL"){
	   $('.sqlmessage').css({"display":"inline-block"});
	   $('.sqlmessage').html(data.status);
	   $('#sqlquery-btn').button('reset');
	   $('#wizardquery-btn').button('reset');
	   return;
	}
	//var btn = document.getElementById('query-btn');
	$('#query-btn').button('reset');
	$('#sqlquery-btn').button('reset');
	$('#wizardquery-btn').button('reset');

        $('#x'+localStorage.runningExample).button('reset')
	//localStorage.results=JSON.stringify(data);
	$('#qtabs a[href="#resultstab"]').tab('show');
	$('#vtabs a[href="#tabletab"]').tab('show');

	data_orig = data;

	if( data.columns.length == 3 ) {
		data_cross = crossTabulate( jQuery.extend(true, {}, data), 0, 1, 2 ); // hardcopy of data
		$('#crosstabulateBtn').css( 'display', 'block' );
	} else {
		$('#crosstabulateBtn').css( 'display', 'none' );
	}

	if( data.columns.length == 3 && autocrosstabulate ) {
		readRows( data_cross );
		show_original_data = false;
		autocrosstabulate = false;
		//$('#crosstabulateBtn').click(function() {toggleResultTables(false); });
	} else {
		show_original_data = true;
		readRows( data_orig );
		//$('#crosstabulateBtn').click(function() {toggleResultTables(true); });
	}
}

function readRows(data) {

	$('#tablemain').html(buildTable(data));

	var oDatatable = $('#datatable').dataTable( {
        "bPaginate": true,
		"aLengthMenu": [[10, 50, 100, 250, -1], [10, 50, 100, 250, "All"]],
		"iDisplayLength" : 50,
        "bLengthChange": true,
        "bFilter": false,
        "bSort": true,
		"aaSorting": [],
        "bInfo": true,
        "bAutoWidth": false,
		"fnDrawCallback": function () {
			redrawScatterRequest = true;
			redrawLineRequest = true;
		}
    } );

	// only show first 5 columns:
	for( var i = resultTableMaxCols + 1; i < data.columns.length; i++) {
		oDatatable.fnSetColumnVis( i, false );
	}

	generatePlots();
}

function toggleResults( resultgroup ) {
	var oDatatable = $('#datatable').dataTable(); // is not reinitialisation, see docs.
	var columns = ( show_original_data ) ? data_orig.columns.length : data_cross.columns.length;

	redrawScatterRequest = true;
	redrawLineRequest = true;
	for( var i = 1; i < columns; i++) {
		if( i > resultTableMaxCols * resultgroup && i <= resultTableMaxCols * (resultgroup+1) )
			oDatatable.fnSetColumnVis( i, true );
		else
			oDatatable.fnSetColumnVis( i, false );
	}
}



function runExample(i){
	if(window.idle){
	   localStorage.runningExample=i;
	   var q = JSON.parse(localStorage.examples)[i];
	   runQuery(q);
	   $('#x'+i).button('loading');
	   window.editor.setValue(q);
	}
}
function loadExamples() {
	window.idle = false;
	var theQuery = "Select * from queries order by type";
	var query =  encodeURI(expdburl+"api_query/?q="+theQuery+"&id=", "UTF-8");
	$.getJSON(query,readExamples);
}

function readExamples(data) {
	window.idle = true;
	$('#sqlquery-btn').button('reset');
	var nrRows =  data.data.length;
	var l="";
	var type="";
	var examples=[];
	for (var i=0; i<nrRows; i++) {
	    var t = data.data[i][1];
	    if(t!=type){
		type=t;
		if(i!=0)
		   l+="</ul>";
		l+="<h4>"+t+"</h4><ul class=\"nav nav-pills nav-stacked\">";
	    }
	    l+="<li><a href=\"<?php echo $_SERVER['REQUEST_URI']; ?>#\" id=\"x"+i+"\" data-loading-text=\"Querying, Loading...\" autocomplete=\"off\" onclick=\"runExample("+i+");\">"+data.data[i][2]+"</a></li>";
	    examples[i]=data.data[i][3];
	}
	localStorage.examples=JSON.stringify(examples);
	document.getElementById("examplemain").innerHTML = l;
}

function buildTable(data) {
	var nrRows =  data.data.length;
	var nrCols =  data.columns.length;

	var l = "<div><table class='table table-striped table-bordered table-condensed' id='datatable'><thead><tr>";
	var columnmenu = '';
	var columnmenutop = '';
	var columnmenubottom = '';

    for (var i =  0;  i<nrCols;  i++) {
		l += "<th>"+data.columns[i].title+"</th>";
		if(i==0)
		  localStorage.xAxis=data.columns[0].title;
	}
	l += "</thead><tbody>";
	for (var i =  0;  i<nrRows;  i++) {
		l+="<tr>";
		for (var j =  0;  j<nrCols;  j++)
			l += "<td>"+data.data[i][j]+"</td>";
		l+="</tr>";
	}
	l += "</tbody></table></div>";


	// create controlls to show other columns
	if( data.columns.length > resultTableMaxCols + 1 ) {
		columnmenutop = '<div style="float:right;margin-top: 65px;margin-bottom: -53px; position:relative;z-index:1019">Columns <div class="btn-group">';
		columnmenubottom = '<div style="float:right;margin-top: -57px;">Columns <div class="btn-group">';

		for( var i = 0; i < Math.ceil(data.columns.length - 1) / resultTableMaxCols; i++ ) {
			columnmenu += '<button type="button" class="btn btn-default" onclick="toggleResults('+i+')">'+(i*5+1)+"-"+((i+1)*5)+'</button>';
			if( (i + 1) % 20 == 20 ) l+='</div><div class="btn-group">';
		}
		columnmenu +='</div></div>';
	}


	$('.topmenu').show();
	return columnmenutop + columnmenu + l + columnmenubottom + columnmenu;
}

function createArray( len, val ) {
	var rv = new Array(len);
    while (--len >= 0) {
        rv[len] = val;
    }
    return rv;
}

function crossTabulate( data, anchorColIndex, pivotColIndex, valueColIndex ) {

	var nrOrigRows =  data.data.length;

	var newRows = new Array();
	var newContent = new Array();
	var newCols = new Array();
	var newColsObj = new Array();

	for( var i = 0; i < nrOrigRows; i++ ) {
		if( newCols.indexOf( data.data[i][pivotColIndex] ) == -1 ) {
			var column = new Object();
			column.title = data.data[i][pivotColIndex];
			column.datatype = data.columns[valueColIndex].datatype;
			newColsObj.push( column );
			newCols.push( data.data[i][pivotColIndex] );
		}
	}
	newColsObj.sort(function(a, b){return ( a.title > b.title ) ? 1 : -1 });
	//console.log( newColsObj );
	newColsObj.splice( 0, 0, data.columns[anchorColIndex] );

	newCols.sort();
	newCols.splice( 0, 0, data.columns[anchorColIndex].title );

	for( var i = 0; i < nrOrigRows; i++ ) {
		var index = newRows.indexOf( data.data[i][anchorColIndex] );
		if( index == -1 ) {
			var row = createArray( newCols.length, 'null' );
			row[0] = data.data[i][anchorColIndex];
			newContent.push( row );
			newRows.push( data.data[i][anchorColIndex] );
			index = newRows.length-1;
		}
		newContent[index][newCols.indexOf( data.data[i][pivotColIndex] )] = data.data[i][valueColIndex];
	}

	data.columns = newColsObj;
	data.data = newContent;

	return data;
}

/*function buildCSV() {
    var l =  "";
    var c=0;
    $('thead th', table).each(function(i) {
	if(c!=0)
		l += ",";
	l += this.innerHTML;
	c++;
    });
    l+="\n";

    $('tbody tr', table).each( function(i) {
	var tr = this;
	c=0;
	$('td', tr).each( function(j) {
	   if(c!=0)
		l += ",";
	   l += this.innerHTML;
	   c++;
	   });
    	l+="\n";
    });
    return l;
}*/

function cleanUp(a) {
  a.textContent = 'Downloaded';
  a.dataset.disabled = true;

  // Need a small delay for the revokeObjectURL to work properly.
  setTimeout(function() {
    window.URL.revokeObjectURL(a.href);a.style.display='none';
  }, 1500);
};


/*function downloadCSV2(){
    $('[data-toggle="dropdown"]').parent().removeClass('open');
    var uriContent = "data:application/octet-stream," + encodeURIComponent(buildCSV());
    window.open(uriContent, "ExpDB-data.csv");
}

function downloadCSV(){
  $('[data-toggle="dropdown"]').parent().removeClass('open');
  window.URL = window.webkitURL || window.URL;
  window.BlobBuilder = window.BlobBuilder || window.WebKitBlobBuilder ||
                       window.MozBlobBuilder;
  var container = document.querySelector('.topmenu');
  var output = container.querySelector('output');

  var prevLink = output.querySelector('a');
  if (prevLink) {
    window.URL.revokeObjectURL(prevLink.href);
    output.innerHTML = '';
  }

  var bb = new BlobBuilder();
  bb.append(buildCSV());

  var a = document.createElement('a');
  a.download = container.querySelector('input[type="text"]').value;
  a.href = window.URL.createObjectURL(bb.getBlob('text/plain'));
  a.textContent = 'Download';
  a.target='_blank';
  a.dataset.downloadurl = ['text/plain', a.download, a.href].join(':');
  output.appendChild(a);

  a.onclick = function(e) {
    if ('disabled' in this.dataset) {
      return false;
    }
    cleanUp(this);
  };
}*/

//graphs
 //Data in JSON -> User select 2/3 fields (or automatic, value on Y-axis) -> generate series + options file -> build chart
    //Change in settings -> new chart
   // var ChartBuilder = function(){
 // 	this.xAxis='xAxis';
  //  };

function generatePlots( ){
    table = document.getElementById( 'datatable' );

	columns = [];
	$('thead th', table).each(function(i){columns[i]= this.innerHTML;});

	localStorage.inverted="false";
	localStorage.endOnTick="false";
	localStorage.xGridBands="false";
	localStorage.yGridBands="false";
    localStorage.xIndex=0;
    localStorage.xAxis=columns[0];
	localStorage.xAxisType="linear";
	localStorage.yAxisType="linear";
    localStorage.chartHeight=500;//$('#qwindow').height();

	buildGUI( columns, 'scatter', 'topmenuScatter' );
	buildGUI( columns, 'line', 'topmenuLine' );

	redrawScatterRequest = true;
	redrawLineRequest = true;
}

function onclickScatterPlot() {
	if(redrawScatterRequest == true) {
		fullRedraw('scatter');
		redrawScatterRequest = false;
	}
}

function onclickLinePlot() {
	if(redrawLineRequest == true) {
		fullRedraw('line');
		redrawLineRequest = false;
	}
}

function buildGUI( columns, type, renderTo ){
 	gui = new dat.GUI({ autoPlace: false });
	var bool = ["false","true"];
	var axisTypes = ["linear","logarithmic","datetime"];
	var f1 = gui.addFolder('Data');
  	f1.add(localStorage, 'xAxis', columns).onFinishChange(function(value){localStorage.xAxis=value;fullRedraw(type);});
	f1.add(localStorage, 'inverted', bool).onFinishChange(function(value){localStorage.inverted=value.toString();quickRedraw(type);});
  	f1.add(localStorage, 'xAxisType', axisTypes).onFinishChange(function(value){localStorage.xAxisType=value;quickRedraw(type);});
  	f1.add(localStorage, 'yAxisType', axisTypes).onFinishChange(function(value){localStorage.yAxisType=value;quickRedraw(type);});

	var f2 = gui.addFolder('Appearance');
	f2.add(localStorage, 'chartHeight').onFinishChange(function(value){localStorage.chartHeight=value;quickRedraw(type);});
	f2.add(localStorage, 'endOnTick', bool).onFinishChange(function(value){localStorage.endOnTick=value.toString();quickRedraw(type);});
  	f2.add(localStorage, 'xGridBands', bool).onFinishChange(function(value){localStorage.xGridBands=value.toString();quickRedraw(type);});
  	f2.add(localStorage, 'yGridBands', bool).onFinishChange(function(value){localStorage.yGridBands=value.toString();quickRedraw(type);});

 	gui.close();

  	var customContainer = document.getElementById( renderTo );
	customContainer.innerHTML = ""; // reset previous GUI
  	customContainer.appendChild( gui.domElement );
	gui.domElement.style.position="relative";
}

function quickRedraw(type){
	if( type == 'scatter' ) {
		quickRedrawScatter();
	} else if( type=='line') {
		quickRedrawLine();
	}
}

function quickRedrawScatter() {
	if(typeof chart != 'undefined')
		chart.showLoading();
	var options = defineOptions('scattermain','scatter');
	options.series = series;
	Highcharts.visualize(table, options);
	chart.hideLoading();
}

function quickRedrawLine() {
	if(typeof linechart != 'undefined')
		linechart.showLoading();
	var options = defineOptions('linemain','line');
	options.series = series;
	Highcharts.visualizeLine(table, options);
	linechart.hideLoading();
}

function fullRedraw(type) {
	if( type == 'scatter' ) {
		fullRedrawScatter();
	} else if( type=='line') {
		fullRedrawLine();
	}
}

function fullRedrawScatter(){
	if(typeof chart != 'undefined')
		chart.showLoading();
	var options = defineOptions('scattermain','scatter');
	series = buildSeries();
	options.series = series;
	console.log( options );
	Highcharts.visualize(table, options);
	chart.hideLoading();
}

function fullRedrawLine(){
	if(typeof linechart != 'undefined')
		linechart.showLoading();
	var options = defineOptions('linemain','line');
	series = buildSeries();
	options.series = series;
	console.log( options );
	Highcharts.visualizeLine(table, options);
	linechart.hideLoading();
}

function buildSeries(){
	var series;
    var xi = localStorage.xIndex;

    // build the data series
    series = [];
    var s = 0;
    $('thead th', table).each(function(i) {
		if(i!=xi){
			series[s] = {
				name: this.innerHTML,
				height: localStorage.chartHeight,
				visible: true,
				data: []
			};
			s++;
		}
    });

    //first, build X-array
    var xarray=[];
    $('tbody tr', table).each( function(i) {
	var tr = this;
	$('td', tr).each( function(j) {
	    if(j==xi){
			if(!numeric[j])
				xarray.push($.inArray(this.innerHTML,categories[j]));
			else
				xarray.push(parseFloat(this.innerHTML));
	    }
	});
    });

    //then, build series in combination with each other numeric dimension
    $('tbody tr', table).each( function(i) {
	var tr = this;
	$('td', tr).each( function(j) {
	   if(j!=xi){
		var point=[];
		var addToSeries = true;

		point[0]=xarray[i];
		if(!numeric[j]) {
			point[1]= $.inArray(this.innerHTML,categories[j]);
		} else {
			if(isNaN(parseFloat(this.innerHTML)))
				addToSeries = false; // no non-numeric values to numeric column.
			else
				point[1] = parseFloat(this.innerHTML);
		}

		if(j<xi) {
			if (addToSeries) series[j].data.push(point);
		} else {
			if (addToSeries) series[j-1].data.push(point);
		}
	   }
	});
    });

	for( var i = 0; i < s; i++ ) {
		series[i].data.sort( // sorting on x property.
			function(a, b){
				if(a[0] > b[0]) return 1;
				else if(a[0] < b[0]) return -1;
				else return 0;});
	}

	console.log( series );
	return series;
}

function defineOptions( renderto, type ){
	var options = [];
	var colors = ['#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92'];

	//check which dimensions are numeric
	numeric = [];
	categories=[];

	$('tbody tr:first td', table).each( function(i) {
		  if(isNaN(this.innerHTML) && this.innerHTML != 'null') // TODO: better numeric check, as null value will fail this test.
			numeric[i]=false;
		  else
			numeric[i]=true;
		  categories[i]=[];
	    });

	//store index of selected X-axis
	$('thead th', table).each( function(i) {
		  if(this.innerHTML==localStorage.xAxis)
			localStorage.xIndex=i;
	    });

	//build categories for non-numeric dimensions
	$('tr', table).each( function(i) {
		var tr = this;
		$('td', tr).each( function(j) {
		   if(!numeric[j] && $.inArray(this.innerHTML,categories[j])==-1)
			categories[j].push(this.innerHTML);
		});
	});

        //build options
	options.chart = {};
	options.chart.renderTo=renderto;
	options.chart.type=type;
	options.chart.height = localStorage.chartHeight;
	options.chart.width = $('#resulstab_content').width();
	options.credits = {enabled: false};
	if(localStorage.inverted=="true")
	   options.chart.inverted = true;
	options.title = {text: ' '};
	options.xAxis = {};
	options.xAxis.title = {text:localStorage.xAxis};
	options.xAxis.type = localStorage.xAxisType;
	if(localStorage.xGridBands=="true")
	  options.xAxis.alternateGridColor= '#f5f5f5';
	if(!numeric[localStorage.xIndex]){
		options.xAxis.categories = JSON.parse("[\""+categories[localStorage.xIndex].join("\",\"")+"\"]");
		if(localStorage.inverted=="false")
			options.xAxis.labels = {rotation:-45,align:'right'};
	}
	options.yAxis = [];
	var c=0;
	$('thead th', table).each( function(i){
	  if(i!=localStorage.xIndex){
	    var y = {};
	    y.title = {text:this.innerHTML};
	    y.title.style = {color:colors[c]};
	    y.labels = {style:{color:colors[c]}};
	    y.type = localStorage.yAxisType;
	    if(localStorage.yGridBands=="true")
	      options.yAxis.alternateGridColor= '#f5f5f5';
	    if(localStorage.endOnTick=="false"){
	      y.endOnTick = false;
	      y.startOnTick = false;}
	    options.yAxis.push(y);
	    c++;
	  }
	});

	// disables HUGE description along yAxis
	if( options.yAxis.length > 10 ) {
		var y = {};
		y.title = {text:"value"};
		y.title.style = {color:colors[c]};
		y.labels = {style:{color:colors[c]}};
		y.type = localStorage.yAxisType;
		if(localStorage.yGridBands=="true")
		  options.yAxis.alternateGridColor= '#f5f5f5';
		if(localStorage.endOnTick=="false"){
		  y.endOnTick = false;
		  y.startOnTick = false;}

		options.yAxis = y;
	}

	options.tooltip = {formatter: function() {return '<b>'+ this.series.name +'</b><br/>'+	this.x +' '+ this.y;}};

	return options;
}

function wrapArrayValues( arr, wrapper ) {
	var arrCopy = new Array( arr.length );
	for( i = 0; i < arr.length; i++ ) {
		arrCopy[i] = wrapper.replace( /VALUE/g, arr[i] );
	}
	return arrCopy;
}

function commaSeperatedListToCleanArray( list ) {
	var arr = list.split(',');
	for( i = arr.length-1; i >= 0; i-- ) {
		arr[i] = arr[i].replace( /^ +/g, '' );
		if( arr[i] == '' ) {
			arr.splice( i, 1 );
		}
	}
	return arr;
}

function splitDatasetsFromCollections( list ) {
	var result = new Object();
	result.collections = new Array();
	result.datasets = new Array();

	for( var i = 0; i < list.length; i++ ) {
		if( list[i].substring( 0, 11 ) == "Collection:" ) {
			result.collections.push( list[i].substring( 11 ) );
		} else {
			result.datasets.push( list[i] );
		}
	}

	return result;
}

function learningCurveQuery( datasets, implementations ) {
  autocrosstabulate = true;
  datasets = commaSeperatedListToCleanArray( datasets );
  implementations = commaSeperatedListToCleanArray( implementations );

  var datasetConstraint = '';
  var implementationConstraint = '';
	if ( datasets.length > 0 ) datasetConstraint = ' AND `d`.`name` IN ("' + datasets.join('","') + '") ';
	if ( implementations.length > 0 ) implementationConstraint = ' AND `i`.`fullName` IN ("' + implementations.join('","') + '") ';

  var sql =
    'SELECT `e`.`sample_size`, CONCAT(`i`.`name`," on Task ",`r`.`task_id`, ": ", `d`.`name`) AS `name`, avg(`e`.`value`) as `score`' +
    'FROM `run` `r`, `evaluation_sample` `e`, `algorithm_setup` `a`, `implementation` `i`, `task` `t`, `task_inputs` `v`, `dataset` `d` ' +
    'WHERE `e`.`function` = "predictive_accuracy" ' +
    'AND `t`.`ttid` = 3 ' +
    datasetConstraint +
    implementationConstraint +
    'AND `r`.`rid` = `e`.`source` ' +
    'AND `r`.`setup` = `a`.`sid` ' +
    'AND `a`.`implementation_id` = `i`.`id` ' +
    'AND `r`.`task_id` = `t`.`task_id` ' +
    'AND `t`.`task_id` = `v`.`task_id` ' +
    'AND `v`.`input` = "source_data" ' +
    'AND `v`.`value` = `d`.`did` ' +
    'GROUP BY `r`.`rid`,`e`.`sample` ' +
    'ORDER BY `sample`, `name` ASC';

    runQuery( sql );
	  window.editor.setValue( sql );
}

function wizardQuery( implementations, defaultParams, datasets, evaluationMethod, evaluationMetric, crosstabulate ) {
	// TODO: only available evaluationMethod: CV . Var is not used yet.
	$('#wizardquery-btn').button('loading');

	datasets = commaSeperatedListToCleanArray( datasets );
	implementations = commaSeperatedListToCleanArray( implementations );

	var sql = '';
	var datasetConstraint = '';
	var implementationConstraint = '';
	var selectColumns = ' i.fullName, d.name, e.value ';
	if ( implementations.length > 0 ) implementationConstraint = ' AND l.implementation_id IN ("' + implementations.join('","') + '") ';
	if ( datasets.length > 0 ) {
		var c_d  = splitDatasetsFromCollections( datasets );
		if( c_d.collections.length > 0 && c_d.datasets.length > 0 ) {
			datasetConstraint = ' AND ( d.did IN ("' + c_d.datasets.join('","') + '") OR d.collection IN ("' + c_d.collections.join('","') + '") ) ';
		} else if( c_d.collections.length > 0 ) {
			datasetConstraint = ' AND d.collection IN ("' + c_d.collections.join('","') + '")  ';
		} else if( c_d.datasets.length > 0 ) {
			datasetConstraint = ' AND d.did IN ("' + c_d.datasets.join('","') + '") ';
		}
	}
	if( crosstabulate != "none" ) {
		autocrosstabulate = true;
		if( crosstabulate == 'dataset' ) selectColumns = ' d.name, i.fullName, e.value ';
	}

	if( defaultParams )
		sql = 	'SELECT ' + selectColumns +
				'FROM algorithm_setup l, evaluation e, run r, dataset d, implementation i, task_inputs ti ' +
				'WHERE r.setup = l.sid ' +
				datasetConstraint +
				implementationConstraint +
				'AND l.isDefault = "true" ' +
				'AND r.task_id = ti.task_id AND ti.input="source_data" AND ti.value=d.did '+
				'AND l.implementation_id = i.id ' +
				'AND d.isOriginal="true" ' +
				'AND e.source=r.rid ' +
				'AND e.function="' + evaluationMetric + '" ' +
				'ORDER BY l.algorithm ASC, d.name ASC;';
	else
		sql = 	'SELECT CONCAT(i.fullName, " with ", IFNULL(GROUP_CONCAT( CONCAT( p.name, "=", ps.value ) ),"") ) AS algorithm, d.name, e.value ' +
				'FROM evaluation e, run r, implementation i, task_inputs ti, dataset d, algorithm_setup l LEFT JOIN input_setting ps ON l.sid = ps.setup LEFT JOIN input p ON ps.input = CONCAT( p.implementation_id, "_", p.name)' +
				'WHERE r.setup = l.sid ' +
				datasetConstraint +
				implementationConstraint +
				'AND r.task_id = ti.task_id AND ti.input="source_data" AND ti.value=d.did '+
				'AND l.implementation_id = i.id ' +
				'AND d.isOriginal="true" ' +
				'AND e.source=r.rid ' +
				'AND e.function="' + evaluationMetric + '" ' +
				'GROUP BY l.sid, d.name ' +
				'ORDER BY l.algorithm ASC, d.name ASC;';
        console.log(sql);
	runQuery( sql );
	window.editor.setValue( sql );
}

function exportResult( filetype ) {
	$('#exportResultType').val( filetype );
	if(show_original_data)
		$('#exportResultData').val( JSON.stringify( data_orig ) );
	else
		$('#exportResultData').val( JSON.stringify( data_cross ) );
	$('#exportResultForm').submit();
}

function toggleResultTables() {
	$('#crosstabulateBtn').button('loading');
	if( show_original_data ) {
		$('#crosstabulateBtn').prop( 'value', 'Undo Crosstabulation' );
		show_original_data = false;
		readRows( data_cross );
	} else {
		$('#crosstabulateBtn').prop( 'value', 'Crosstabulate' );
		show_original_data = true;
		readRows( data_orig );
	}
	$('#crosstabulateBtn').button('reset');
}

function updateImplementations( cssSelectorImplementations, cssSelectorAlgorithms ) {
	var algorithms = commaSeperatedListToCleanArray( $( cssSelectorAlgorithms ).val() );
	var implementations = ( algorithms != "" ) ? getImplementationsWithAlgorithms( algorithms ) : expdbImplementations();
	makeCommaSeperatedAutoComplete( cssSelector, implementations );
}

function getImplementationsWithAlgorithms( requestedAlgorithms ) {
	var algorithms = expdbAlgorithms();
	var implementations = expdbImplementations();
	var resultingImplementations = new Array();
	var rai = 0; // requested algorithms index

	requestedAlgorithms.sort( );
	for( var i = 0; i < algorithms.length; i++ ) {
		while( requestedAlgorithms[rai] < algorithms[i] ) rai++;
		if( algorithms[i] == requestedAlgorithms[rai] ) {
			resultingImplementations.push( implementations[i] );
		}
	}

	return resultingImplementations;
}

function printModal(elem) {
    var domClone = elem.cloneNode(true);

    var $printSection = document.createElement("div");
    $printSection.id = "printSection";
    document.body.appendChild($printSection);
    $printSection.appendChild(domClone);
}




<?php
foreach( (array)$this->run['evaluations'] as $r ):

if(array_key_exists('array_data',$r)){
		$d = json_encode($r['array_data']);
		if (0 !== strpos($d, '[[')) {
			$d = '['.$d.']';
		}
		$columns = array();
		if(array_key_exists('target_values',$this->run['run_task'])){
			foreach($this->run['run_task']['target_values'] as $target){
				$columns[] = array("title" => $target);
			}
			if($r['evaluation_measure'] == 'confusion_matrix'){
				$d = array();
				$c = 0;
				foreach($r['array_data'] as $rr){
					array_unshift($rr, $columns[$c]['title']);
					$d[] = $rr;
					$c++;
				}
				$d = json_encode($d);
				array_unshift($columns, array("title" => 'actual\predicted'));
			}
			elseif($r['evaluation_measure'] == 'scimark_benchmark'){
				$columns = array();
				foreach($this->benchmarks as $b){
					$columns[] = array("title" => $b);
				}
			}
		}
		?>

$(function(){$('#table_<?php echo $r['evaluation_measure']; ?>').DataTable({"data":<?php echo $d;?>,"bSort":false,"sDom":'t',"aoColumns":<?php echo json_encode($columns);?>,"pageLength":50});
$('#table_<?php echo $r['evaluation_measure']; ?>').removeClass('dataTable');
$('#table_<?php echo $r['evaluation_measure']; ?>_wrapper').addClass('table-responsive');});

<?php }
if(array_key_exists('per_fold',$r) and !empty($r['per_fold'])  and !empty($r['per_fold'][0])){

if($r['evaluation_measure'] == 'number_of_instances'){
		$d = array();
		$columns = array();
		$columns[] = array("title" => "repeat\\fold");
		$c = 0;
		foreach($r['per_fold'] as $rr){
			array_unshift($rr, $c);
			$columns[] = array("title" => $c."");
			$d[] = $rr;
			$c++;
		}
		$d = json_encode($d);
		?>

$(function(){$('#cvtable_<?php echo $r['evaluation_measure']; ?>').DataTable({"data":<?php echo $d;?>,"bSort":false,"sDom":'t',"aoColumns":<?php echo json_encode($columns);?>,"pageLength":50});
$('#cvtable_<?php echo $r['evaluation_measure']; ?>').removeClass('dataTable');
$('#cvtable_<?php echo $r['evaluation_measure']; ?>_wrapper').addClass('table-responsive');});

<?php
} else {
$boxplots = array();
$sum = 0;
$cnt = 0;
foreach($r['per_fold'] as $f){
	$bp = box_plot_values($f);
	$boxplots[] = array($bp['lower_outlier'],$bp['q1'],$bp['median'],$bp['q3'],$bp['higher_outlier']);
	$sum += array_sum($f);
	$cnt += count($f);
}
$foldmean = $sum/$cnt;
?>

$(function(){$('#folds_<?php echo $r['evaluation_measure']; ?>').highcharts({
	chart:{type:'boxplot',inverted:true},title:false,legend:{enabled:false},exporting:{enabled:false},credits:{enabled:false},
	xAxis:{title:{text:'Repeat'},tickInterval:1},yAxis:{title:{text:'Score'},
	plotLines:[{value:<?php echo $foldmean;?>,color:'red',width:1,zIndex:100,label:{useHTML:true,text:'mean',style:{fontSize:'8pt'}}}]},
	series:[{name:'Box plot',data:<?php echo json_encode($boxplots);?>,tooltip:{headerFormat:'<em>Repeat {point.key}</em><br/>'}}]});});

<?php }} endforeach; ?>

var isliked;
var reason_id = -1;
var maxreason = -1;
<?php if ($this->ion_auth->logged_in()) {
    if ($this->ion_auth->user()->row()->id != $this->run['uploader_id']) {?>

getYourDownvote();
setSubmitBehaviour();

function doLike(liked){
    isliked = liked;
    if(isliked){
        meth = 'DELETE';
    }else{
        meth = 'POST';
    }
    $.ajax({
        method: meth,
        url: '<?php echo BASE_URL; ?>api_new/v1/xml/votes/up/r/<?php echo $this->id ?>'
    }).done(function(resultdata){
        if(resultdata.getElementsByTagName('like').length>0){
            //changes already done
        }else{
            //undo changes
            flipLikeHTML();
        }
    }).fail(function(resultdata){
        //undo changes
        flipLikeHTML();
    });
    //change as if the api call is succesful
    flipLikeHTML();
}

function doDownload(){
    $.ajax({
            method: 'POST',
            url: '<?php echo BASE_URL; ?>api_new/v1/xml/downloads/r/<?php echo $this->id ?>'
           }
    ).always(function(){
        refreshNrDownloads();
    });
}


function getYourDownvote(){
    $.ajax({
        method:'GET',
        url: '<?php echo BASE_URL; ?>api_new/v1/xml/votes/down/<?php echo $this->ion_auth->user()->row()->id; ?>/r/<?php echo $this->id; ?>'
    }).done(function(resultdata){
        reason_id = resultdata.getElementsByTagName('value')[0].textContent;
        if(reason_id!=-1){
            if(!$('#downvoteicon-'+reason_id).length){
                $('#downvotebutton-'+reason_id).append('<i id="downvoteicon" class="fa fa-thumbs-down"/>');
            }else{
                $('#downvoteicon-'+reason_id).removeclass("fa-thumbs-o-down").addclass("fa-thumbs-down");
            }
            $('#downvotebutton-'+reason_id).prop('title', 'Click to remove your downvote');
            $('#issueform').remove();
        }else{
            if(!$('a[id^="downvotebutton"] > a[id^="downvoteicon"]').length){
                $('a[id^="downvotebutton"]').append('<i id="downvoteicon" class="fa fa-thumbs-o-down"/>');
            }
            if(!$('#issueform').length){
            $('#issues').append(
                '<form role="form" id="issueform">'+
                    '<h5>Submit a new issue for this dataset</h5>'+
                    '<div class="form-group">'+
                      '<label for="Reason">Issue:</label>'+
                      '<input type="text" class="form-control" id="reason">'+
                    '</div>'+
                    '<button type="submit" class="btn btn-default">Submit</button>'+
                    '<div id="succes" class="text-center hidden">Issue Submitted!</div>'+
                    '<div id="fail" class="text-center hidden">Can\'t submit issue </div>'+
                '</form>');
            setSubmitBehaviour();
            }
        }
    });
}

function doDownvote(rid){
    if(reason_id==rid){
        meth= 'DELETE';
        u = '<?php echo BASE_URL?>api_new/v1/xml/votes/down/r/<?php echo $this->id ?>';
    }else{
        meth= 'POST';
        u = '<?php echo BASE_URL?>api_new/v1/xml/votes/down/r/<?php echo $this->id ?>/'+rid
    }
    $.ajax({
        method: meth,
        url: u
    }).done(function(resultdata){
        reason_id = parseInt(resultdata.getElementsByTagName('reason_id').item(0).textContent);
        getDownvotes();
    }).fail(function(resultdata){

    });
}
<?php }}?>

function refreshNrLikes(){
    $.ajax({
        method:'GET',
        url:'<?php echo BASE_URL; ?>api_new/v1/xml/votes/up/any/r/<?php echo $this->id ?>'
        }).done(function(resultdata){
            if(resultdata.getElementsByTagName('like').length>0){
                var nrlikes = resultdata.getElementsByTagName('like').length;
                $('#likecount').html(nrlikes+" likes");
            }else{
                $('#likecount').html("0 likes");
            }
        }).fail(function(resultdata){
            $('#likecount').html("0 likes");
     });
 }

 function refreshNrDownloads(){
    $.ajax({
       method:'GET',
       url:'<?php echo BASE_URL; ?>api_new/v1/xml/downloads/any/r/<?php echo $this->id ?>'
    }).done(function(resultdata){
       if(resultdata.getElementsByTagName('download').length>0){
           var nrdownloads = resultdata.getElementsByTagName('download').length;
           var totaldownloads = 0;
           for(var i=0; i<nrdownloads; i++){
               totaldownloads+=parseInt(resultdata.getElementsByTagName('download')[i].getElementsByTagName('count')[0].textContent);
           }
           $('#downloadcount').html("downloaded by "+nrdownloads+" people, "+totaldownloads+" total downloads");
       }else{
           $('#downloadcount').html("downloaded by 0 people, 0 total downloads");
       }
    }).fail(function(resultdata){
       $('#downloadcount').html("downloaded by 0 people, 0 total downloads");
    });
 }

function flipLikeHTML(){
    if(isliked){
        isliked = false;
        $('#likeicon').removeClass("fa-heart").addClass("fa-heart-o");
        $('#likebutton').prop('title', 'Click to like');
        $('#likebutton').attr('onclick', 'doLike(false)');
        var likecounthtml = $('#likecount').html();
        var nrlikes = parseInt(likecounthtml.split(" ")[0]);
        nrlikes = nrlikes-1;
        $('#likecount').html(nrlikes+" likes");
        var reachhtml = $('#reach').html();
        var reach = parseInt(reachhtml.split(" ")[0]);
        reach = reach-2;
        $('#reach').html(reach+" reach");
    }else{
        isliked = true;
        $('#likeicon').removeClass("fa-heart-o").addClass("fa-heart");
        $('#likebutton').prop('title', 'Click to unlike');
        $('#likebutton').attr('onclick', 'doLike(true)');
        var likecounthtml = $('#likecount').html();
        var nrlikes = parseInt(likecounthtml.split(" ")[0]);
        nrlikes = nrlikes+1;
        $('#likecount').html(nrlikes+" likes");
        var reachhtml = $('#reach').html();
        var reach = parseInt(reachhtml.split(" ")[0]);
        reach = reach+2;
        $('#reach').html(reach+" reach");
    }
}

function setSubmitBehaviour(){
    $("#issueform").submit(function(event){
       // cancels the form submission
       event.preventDefault();
       var reason = $("#reason").val();
       $("#reason").val('');
       $.ajax({
           type: 'POST',
           url: '<?php echo BASE_URL; ?>api_new/v1/xml/votes/down/r/<?php echo $this->id; ?>/'+reason
       }).done(function(resultdata){
           reason_id = parseInt(resultdata.getElementsByTagName('reason_id').item(0).textContent);
           getDownvotes();
           $("fail").addClass("hidden");
           $("#success").removeClass("hidden");
       }).fail(function(resultdata){
           $("fail").append(resultdata.getElementsByTagName("message")[0].textContent);
           $("fail").removeClass("hidden");
           $("#success").addClass("hidden");
       });
   });
}

function getDownvotes(){
    $('#issues_content').append('<i class="fa fa-spinner fa-pulse"></i> Refreshing issues');
    $.ajax({
        method:'GET',
        url: '<?php echo BASE_URL?>api_new/v1/xml/votes/down/r/<?php echo $this->id ?>'
    }).done(function(resultdata){
        if(resultdata.getElementsByTagName('downvotes').length>0){
            var dvotes = resultdata.getElementsByTagName('downvote');
            $('#issues_content').html("<tr><th>Issue</th><th>#Downvotes for this reason</th><th>By</th><th></th></tr>");
            for(var i=0; i<dvotes.length; i++){
                var id = dvotes[i].getElementsByTagName('reason_id')[0].textContent;
                maxreason = Math.max(id,maxreason);
                $('#issues_content').append('<tr id="issuerow-'+id+'">');
                $('#issuerow-'+id).append('<td>'+dvotes[i].getElementsByTagName('reason')[0].textContent+'</td>');
                $('#issuerow-'+id).append('<td>'+dvotes[i].getElementsByTagName('count')[0].textContent+'</td>');
                $('#issuerow-'+id).append('<td><a href="u/'+dvotes[i].getElementsByTagName('user_id')[0].textContent+'">User '+dvotes[i].getElementsByTagName('user_id')[0].textContent+'</a></td>');
                $('#issuerow-'+id).append('<td><a id="downvotebutton-'+id+'" class="loginfirst btn btn-link" onclick="doDownvote('+id+')" title="Click to agree"> </a></td>');
                $('#issues_content').append('</tr>');
            }
            if(reason_id!=-1){
                if(!$('#downvoteicon-'+reason_id).length){
                    $('#downvotebutton-'+reason_id).append('<i id="downvoteicon-'+reason_id+'" class="fa fa-thumbs-down"/>');
                }else{
                    $('#downvoteicon-'+reason_id).removeclass("fa-thumbs-o-down").addclass("fa-thumbs-down");
                }
                $('#downvotebutton-'+reason_id).prop('title', 'Click to remove your downvote');
                $('#issueform').remove();
            }else{
                for(var i=0; i<dvotes.length; i++){
                    var id = dvotes[i].getElementsByTagName('reason_id')[0].textContent;
                    if(!$('#downvotebutton-'+id).length){
                        $('#downvotebutton-'+id).append('<i id="downvoteicon-'+id+'" class="fa fa-thumbs-o-down"/>');
                    }
                }
                if(!$('#issueform').length){
                    $('#issues').append(
                        '<form role="form" id="issueform">'+
                            '<h5>Submit a new issue for this dataset</h5>'+
                            '<div class="form-group">'+
                              '<label for="Reason">Issue:</label>'+
                              '<input type="text" class="form-control" id="reason">'+
                            '</div>'+
                            '<button type="submit" class="btn btn-default">Submit</button>'+
                            '<div id="succes" class="text-center hidden">Issue Submitted!</div>'+
                            '<div id="fail" class="text-center hidden">Can\'t submit issue </div>'+
                        '</form>');
                    setSubmitBehaviour();
                }
            }
            $('#issues_content').append('<br>');
        }
    }).fail(function(resultdata){
        $('#issues_content').html("<tr><th>Issue</th><th>#Downvotes for this reason</th><th>By</th><th>Click to agree</th></tr>");
    });
    <?php
    if ($this->ion_auth->logged_in()) {
        if ($this->ion_auth->user()->row()->id != $this->run['uploader_id']) {?>
    getYourDownvote();
    <?php }} ?>
}
