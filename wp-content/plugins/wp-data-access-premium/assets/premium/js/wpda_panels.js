const CHART_DEFAULT_LEGEND = 'bottom';
const CHART_DEFAULT_WIDTH = 500;
const CHART_DEFAULT_HEIGHT = 300;

var wpdaPanels = {};
var wpdaSonce = '';
var wpdaCaller = '';
var googleChartsLoaded = false;
var googleChartsObjects = {};
var cachedChartData = {};

function addPanel(panelId, panelName, panelType, obj = {}) {
	wpdaPanels[panelId] = {};
	wpdaPanels[panelId].panelName = panelName;
	wpdaPanels[panelId].panelType = panelType;

	for (var prop in obj) {
		wpdaPanels[panelId][prop] = obj[prop];
	}
}

function getChartData(panelId) {
	wpdaDbs = wpdaPanels[panelId].chartDbs;
	wpdaQuery = wpdaPanels[panelId].chartSql;
	jQuery.ajax({
		type: "POST",
		url: wpda_panel_vars.wpda_ajaxurl + "?action=wpda_widget_chart_refresh",
		data: {
			wpda_sonce: wpdaSonce,
			wpda_action: 'get_data',
			wpda_caller: wpdaCaller,
			wpda_name: wpdaPanels[panelId].panelName,
		}
	}).done(
		function(data) {
			if (data.status==='ERROR') {
				alert("ERROR: " + data.msg);
			} else {
				if (data.error !== '') {
					alert("ERROR: " + data.error);
				} else {
					if (wpdaPanels[panelId].chartType.length > 1) {
						chartType = wpdaPanels[panelId].chartType[0];
						jQuery.each(wpdaPanels[panelId].chartType, function (i, item) {
							jQuery("#wpda_panel_selection_" + panelId).append(jQuery("<option/>", {
								value: item,
								text: item
							}));
						});
						jQuery("#wpda_panel_selection_container_" + panelId).show();
					} else {
						chartType = wpdaPanels[panelId].chartType[0];
					}

					createChart(
						chartType,
						panelId,
						data.cols,
						data.rows
					);

					jQuery("#wpda_panel_selection_" + panelId).on("change", function () {
						refreshChart(panelId);
					});
				}
			}
		}
	);
}

function createChart(outputType, panelId, columns, rows) {
	cachedChartData[panelId] = new google.visualization.DataTable({
		cols: columns,
		rows: rows
	});
	addChart(panelId, outputType);
	tableFix(panelId);
}

function refreshChart(panelId) {
	jQuery("#wpda_panel_container_" + panelId).empty();
	addChart(panelId, jQuery("#wpda_panel_selection_" + panelId).val());
	tableFix(panelId);
}

function printableVersion(url){
	let win = window.open();
	win.document.write('<iframe src="' + url  + '" frameborder="0" style="border:0; top:0px; left:0px; bottom:0px; right:0px; width:100%; height:100%;" allowfullscreen></iframe>');
}

function addChart(panelId, outputType) {
	var element = document.getElementById("wpda_panel_container_" + panelId);
	googleChartsObjects[panelId] = new google.visualization[outputType](element);

	google.visualization.events.addListener(googleChartsObjects[panelId], 'ready', function () {
		if (outputType==="Table") {
			// Disable print button for tables
			jQuery("#wpda_panel_" + panelId + " .wpda-chart-button-print")
			.hide()
			.off();
		} else {
			// Enable print button for charts
			jQuery("#wpda_panel_" + panelId + " .wpda-chart-button-print")
			.show()
			.on("click", function() {
				printableVersion(googleChartsObjects[panelId].getImageURI());
			});
		}

		// Create hyperlink with CSV from table data
		let csv = google.visualization.dataTableToCsv(cachedChartData[panelId]);
		let url = "data:application/csv;charset=utf-8," + encodeURIComponent(csv);
		jQuery("#wpda_panel_" + panelId + " .wpda-chart-button-export-link")
		.attr("href", url)
		.attr("download", "wp-data-access.csv");

		// Open hyperlink on click
		jQuery("#wpda_panel_" + panelId + " .wpda-chart-button-export").on("click", function() {
			jQuery("#wpda_panel_" + panelId + " .wpda-chart-button-export-link")[0].click();
		});
	});

	googleChartsObjects[panelId].draw(cachedChartData[panelId], chartOptions(panelId));
}

function tableFix(panelId) {
	if (wpdaPanels[panelId].chartOptions===undefined) {
		jQuery("#wpda_panel_container_" + panelId).find(".google-visualization-table").css("width", "100%");
	} else {
		if (
			wpdaPanels[panelId].chartOptions===null ||
			wpdaPanels[panelId].chartOptions.width===undefined ||
			wpdaPanels[panelId].chartOptions.width==="*"
		) {
			jQuery("#wpda_panel_container_" + panelId).find(".google-visualization-table").css("width", "100%");
		} else {
			jQuery("#wpda_panel_" + panelId).find(".wpda-panel-selection").css("width", wpdaPanels[panelId].chartOptions.width + "px");
		}
	}
}

function chartOptions(panelId) {
	if (
		wpdaPanels[panelId]!==undefined &&
		wpdaPanels[panelId].chartOptions!==undefined &&
		wpdaPanels[panelId].chartOptions!==null

	) {
		return wpdaPanels[panelId].chartOptions;
	} else {
		return {
			legend: {
				position: CHART_DEFAULT_LEGEND
			},
			width: CHART_DEFAULT_WIDTH,
			height: CHART_DEFAULT_HEIGHT
		};
	}
}

jQuery(function() {
	google.charts.load(
		"current", {
			"packages": [
				"table",
				"corechart",
				"gauge"
			]
		}
	);

	google.charts.setOnLoadCallback(function() {
		googleChartsLoaded = true;
	});
});
