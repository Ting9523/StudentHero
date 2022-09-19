let rebuildVisualQuery = {};
let rebuildVisualQueryCount = {};

function addVisual(activeIndex) {
	visualContent = `
		<div id="visualComponent${activeIndex}" class="visualComponent" data-index="${activeIndex}">
			<div class="visualComponentButton">
				<button class="button button-primary" onclick="jQuery('#visualTables${activeIndex}').toggle()">
					<i class="fas fa-plus"></i>&nbsp;
					Add table or view&nbsp;&nbsp;&nbsp;
				</button>
			</div>
			<div id="visualTables${activeIndex}" class="visualTables ui-widget">
				<div class="visualTablesHeader ui-widget-header">
					TABLES & VIEWS
					<i class="fas fa-window-close wpda-widget-close wpda_tooltip" title="Close" onclick="jQuery('#visualTables${activeIndex}').toggle()"></i>
				</div>
				<div id="visualTableList${activeIndex}" class="visualTableList ui-widget-content"></div>
			</div>
			<div id="visualQuery${activeIndex}" class="visualQuery ui-widget-content">
				<svg id="visualSvg${activeIndex}" xmlns="http://www.w3.org/2000/svg" class="visualSvg"></svg>
			</div>
			<div id="visualSettings${activeIndex}" class="visualSettings">
				<div id="visualSelection${activeIndex}" class="visualSelection ui-widget-content">
					<table id="visualSelection${activeIndex}table">
						<thead>
							<tr>
								<th></th>
								<th><i class="fas fa-eye"></i></th>
								<th>Column</th>
								<th>Alias</th>
								<th>Sort</th>
								<th>Group</th>
							</tr>
						</thead>
						<tbody>
						</tbody>
					</table>
				</div>
				<div id="visualFilter${activeIndex}" class="visualFilter ui-widget-content">
					<div class="visualFilterHeader">
						Filters
						<span class="wpda_icon">
							<a href="javascript:void(0)" class="wpda-icon-delete wpda-tooltip" title="Delete all filters">
								<i class="fas fa-trash"></i>
							</a>
						</span>
					</div>
					<div id="visualFilterContainer${activeIndex}" class="visualFilterContainer"></div>
					<div class="visualFilterButtons">
						<button class="button button-primary wpda_add_filter_button" disabled="disabled">
							<i class="fas fa-plus"></i>&nbsp;
							Add filter group
						</button>
					</div>
				</div>
			</div>
		</div>
		<div id="visualOutputContainer${activeIndex}" class="visualOutputContainer">
			<ul>
				<li><a href="#visualOutputContainer${activeIndex}tab1">Query</a></li>
				<li><a href="#visualOutputContainer${activeIndex}tab2">Output</a></li>
				<div id="visualOutputContainer${activeIndex}tab1"></div>
				<div id="visualOutputContainer${activeIndex}tab2"></div>
			</ul>
		</div>
	`;

	jQuery("#wpda_query_builder_" + activeIndex + " .wpda_query_builder_taskbar").after(visualContent);

	jQuery("#visualQuery" + activeIndex).resizable({
		minHeight: 100,
		maxHeight: jQuery("#visualComponent" + activeIndex).height()-140,
		handles: "s"
	});

	jQuery("#visualQuery" + activeIndex).on("scroll", function() {
		// prevent svg scrolling out of focus
		jQuery(this).find(".visualSvg").offset({
			top: jQuery(this).offset().top,
			left: jQuery(this).offset().left
		});

		jQuery("#visualQuery" + activeIndex + " .wpda_visual_table_widget ").each(function() {
			redrawLink(activeIndex, jQuery(this).data("alias"));
		});
	});

	jQuery("#visualSelection" + activeIndex).resizable({
		minWidth: jQuery("#visualSettings" + activeIndex).width()*0.3,
		maxWidth: jQuery("#visualSettings" + activeIndex).width()*0.7,
		handles: "e"
	});

	updateVisual(activeIndex);
	isVisual[activeIndex] = true;

	// Reset height query container: overwrite css
	jQuery("#visualQuery" + activeIndex).css("height", (jQuery("#visualComponent" + activeIndex).height()*0.7) + "px");
	jQuery("#visualSelection" + activeIndex).css("width", (jQuery("#visualSettings" + activeIndex).width()*0.5) + "px");

	jQuery("#visualComponent" + activeIndex + " .wpda_add_filter_button").on("click", function() {
		addVisualFilterContainer(activeIndex);
		updateQuery(activeIndex);
	});

	const sqlOutputContainerTab1 = jQuery("#visualOutputContainer" + activeIndex + "tab1");
	const sqlOutputContainerTab2 = jQuery("#visualOutputContainer" + activeIndex + "tab2");

	jQuery("#wpda_query_builder_sql_container_" + activeIndex).appendTo(sqlOutputContainerTab1);
	jQuery("#wpda_query_builder_tabs_" + activeIndex).appendTo(sqlOutputContainerTab2);
	jQuery("#wpda_query_builder_menubar_" + activeIndex).appendTo(sqlOutputContainerTab2);
	jQuery("#wpda_query_builder_result_" + activeIndex).appendTo(sqlOutputContainerTab2);
	jQuery("#wpda_query_builder_statusbar_" + activeIndex).appendTo(sqlOutputContainerTab2);
	jQuery("#wpda_query_builder_viewer_" + activeIndex).appendTo(sqlOutputContainerTab2);

	jQuery("#wpda_query_builder_result_" + activeIndex).html("Press the <strong>Execute</strong> button to view output");

	jQuery("#visualOutputContainer" + activeIndex).tabs({
		active: 1
	});

	jQuery("#visualOutputContainer" + activeIndex + " ul li:first-child a").on("click", function() {
		jQuery("#visualOutputContainer" + activeIndex).tabs("option", "active", 0);

		const cm = editors['tab' + activeIndex].codemirror;
		cm.refresh();

		return true;
	});

	jQuery("#visualFilter" + activeIndex + " .wpda-icon-delete").on("click", function() {
		if (confirm("Delete all filters?")) {
			jQuery("#visualFilterContainer" + activeIndex).empty();
		}
	});

	jQuery("#visualFilter" + activeIndex + " .wpda-tooltip").tooltip({
		tooltipClass: "wpda_tooltip_dashboard"
	});

	jQuery("#wpda_query_builder_" + activeIndex + " .wpda_vqb_button").hide();
}

function updateVisual(activeIndex) {
	var tablesAndViews = jQuery("<ul class='visualTableUlist'/>");
	var schema = jQuery("#wpda_query_builder_dbs_" + activeIndex).val();
	var tables = dbHints[schema];
	for (var table in tables) {
		if (tables[table].length>0) {
			var li =
				jQuery("<li/>")
				.data("schema", schema)
				.data("table", table)
				.html("<span class='dashicons dashicons-menu'></span>" + table);
			li.appendTo(tablesAndViews); // Add table
		}
	}

	jQuery("#visualTableList" + activeIndex).empty().append(tablesAndViews);
	jQuery("#visualTableList" + activeIndex + " li").on("click", function() {
		var schema = jQuery(this).data("schema");
		var table = jQuery(this).data("table");

		addVisualTable(schema, table, activeIndex);
		jQuery("#visualTables" + activeIndex).toggle();
	});
}

function addVisualTable(schema, table, activeIndex, rebuild = false) {
	getColumns(schema, table, activeIndex, addTableWidget, rebuild);
}

function setColumnLink(e, activeIndex, elem) {
	e.stopPropagation();

	const widget = elem.closest(".wpda_visual_table_widget");
	const parent = elem.closest("tr");

	const tableAlias = widget.data("alias");
	const columnName = parent.data("column");

	if (Object.keys(columnLink).length===0) {
		columnLink = {
			id: parent.attr("id"),
			x: parent.offset().left,
			y: parent.offset().top,
			width: parent.width(),
			height: parent.height(),
			startElement: elem,
			tableAlias: tableAlias,
			columnName: columnName
		};

		jQuery(elem).find(".link_closed").hide();
		jQuery(elem).find(".link_open").show();
	} else {
		var id = parent.attr("id");
		if (id===columnLink.id) {
			// source element = destination element
			jQuery(columnLink.startElement).find(".link_closed").show();
			jQuery(columnLink.startElement).find(".link_open").hide();

			columnLink = {};

			return;
		}

		var container = jQuery("#visualQuery" + activeIndex);

		x1 = columnLink.x - container.offset().left;
		y1 = columnLink.y - container.offset().top + columnLink.height/2;

		x2 = parent.offset().left - container.offset().left;
		y2 = parent.offset().top - container.offset().top + parent.height()/2;

		if (x1<x2) {
			x1 += columnLink.width;
		} else {
			x2 += parent.width();
		}

		var linkFrom = columnLink.tableAlias + "_" + columnLink.columnName;
		var linkTo = tableAlias + "_" + columnName;
		var linkSelector = linkFrom + "_" + linkTo;
		var linkSelectorProperties = linkSelector + "_properties";

		var line = document.createElementNS("http://www.w3.org/2000/svg", "line");
		line.setAttribute("class", linkSelector + " " + tableAlias + " " + columnLink.tableAlias);
		line.setAttribute("data-alias-from", columnLink.tableAlias);
		line.setAttribute("data-alias-to", tableAlias);
		line.setAttribute("data-from", linkFrom);
		line.setAttribute("data-to", linkTo);
		line.setAttribute("x1", x1);
		line.setAttribute("y1", y1);
		line.setAttribute("x2", x2);
		line.setAttribute("y2", y2);
		jQuery("#visualSvg" + activeIndex).append(line);
		jQuery("#visualSvg" + activeIndex).on('scroll', function() {
			return false;
		});

		var lineProperties = document.createElementNS("http://www.w3.org/2000/svg", "circle");
		lineProperties.setAttribute("class", linkSelectorProperties + " " + tableAlias + " " + columnLink.tableAlias + " wpda-tooltip");
		lineProperties.setAttribute("data-from", linkFrom);
		lineProperties.setAttribute("data-to", linkTo);
		lineProperties.setAttribute("data-joinfrom", true);
		lineProperties.setAttribute("data-jointo", true);
		lineProperties.setAttribute("title", "Edit join");
		lineProperties.setAttribute("cx", (x2 + x1) / 2);
		lineProperties.setAttribute("cy", (y2 + y1) / 2);
		lineProperties.setAttribute("r", 10);
		jQuery("#visualSvg" + activeIndex).append(lineProperties);

		var tableAliasFrom = columnLink.tableAlias;

		jQuery("#visualSvg" + activeIndex + " ." + linkSelectorProperties).on("click", function() {
			linkProperties(event, tableAliasFrom, tableAlias, activeIndex);
		});

		jQuery(elem).find(".link_closed").hide();
		jQuery(elem).find(".link_open").show();

		setTimeout(function(startElement) {
			jQuery(elem).find(".link_closed").show();
			jQuery(elem).find(".link_open").hide();

			jQuery(startElement).find(".link_closed").show();
			jQuery(startElement).find(".link_open").hide();
		}, 2000, columnLink.startElement);

		columnLink = {};

		jQuery('.wpda_tooltip').tooltip();

		updateQuery(activeIndex);
	}
}

function linkProperties(e, tableFrom, tableTo, activeIndex) {
	const propertiesFrom = jQuery(e.currentTarget).data("from");
	const propertiesTo = jQuery(e.currentTarget).data("to");
	const linkSelector = propertiesFrom + "_" + propertiesTo;
	const propertiesSelector = linkSelector + "_properties";

	const joinFrom = jQuery(e.currentTarget).attr("data-joinfrom")==="true" ? "checked" : "";
	const joinTo = jQuery(e.currentTarget).attr("data-jointo")==="true" ? "checked" : "";

	const popupHtml = `
		<table class="visualPopup">
			<thead>
				<tr class="quit">
					<th colspan="2" class="ui-widget-header">
						<span>
							Relationship properties
						</span>
						<span class="icons">
							<i class="fas fa-window-close wpda-widget-close wpda_tooltip2" title="Close"></i>
						</span>
					</th>
				</tr>
			</thead>
			<tbody>
				<tr class="from"><td><input id="popup_from_${activeIndex}" type="checkbox" ${joinFrom} /></td><td><label for="popup_from_${activeIndex}">Select all rows from ${tableFrom}</label></td></tr>
				<tr class="to"><td><input id="popup_to_${activeIndex}" type="checkbox" ${joinTo} /></td><td><label for="popup_to_${activeIndex}">Select all rows from ${tableTo}</label></td></tr>
				<tr class="delete"><td><span class="fas fa-trash"></span></td><td>Delete relationship</td></tr>
			</tbody>
		</table>
	`;

	const popup = jQuery(popupHtml).dialog({
		dialogClass: "wpda_visual_link_popup",
		position: {
			my: "left",
			at: "right",
			of: e
		},
		resizable: false,
		draggable: false,
		close: function(event, ui) {
			popup.dialog("destroy");
		},
		width: "auto",
		height: "auto",
		minHeight: 0,
		modal: true
	});

	popup.parent().draggable();

	jQuery(".visualPopup tr").off();
	jQuery(".visualPopup tr").on("click", function(e) {
		// Set links
		if (jQuery(e.currentTarget).hasClass("from") || jQuery(e.currentTarget).hasClass("to")) {
			jQuery("#visualSvg" + activeIndex + " ." + propertiesSelector).attr("data-joinfrom", jQuery("#popup_from_" + activeIndex).is(":checked"));
			jQuery("#visualSvg" + activeIndex + " ." + propertiesSelector).attr("data-jointo", jQuery("#popup_to_" + activeIndex).is(":checked"));
		}

		// Remove link
		if (jQuery(e.currentTarget).hasClass("delete")) {
			if (confirm("Delete relationship?")) {
				jQuery("#visualSvg" + activeIndex + " ." + linkSelector).remove();
				jQuery("#visualSvg" + activeIndex + " ." + propertiesSelector).remove();
				popup.dialog("close");
			}
		}

		// Quit popup
		if (jQuery(e.currentTarget).hasClass("quit")) {
			if (jQuery(e.originalEvent.target).hasClass("wpda-widget-close")) {
				popup.dialog("close");
			}
		}

		updateQuery(activeIndex);
	});
}

function redrawLinkPosition(elem, container) {
	return {
		x: elem.offset().left - container.offset().left,
		y: elem.offset().top - container.offset().top + elem.height()/2
	};
}

function redrawLink(activeIndex, tableAlias) {
	jQuery("#visualSvg" + activeIndex + " line." + tableAlias).each(function(i, obj) {
		// redraw line
		const container = jQuery("#visualQuery" + activeIndex);
		const dataFrom = jQuery(obj).data("from");
		const dataTo = jQuery(obj).data("to");

		const elemFrom = jQuery("#tab" + activeIndex + "_" + dataFrom);
		const posFrom = redrawLinkPosition(elemFrom, container);
		let x1 = posFrom.x;
		let y1 = posFrom.y;

		const elemTo = jQuery("#tab" + activeIndex + "_" + dataTo);
		const posTo = redrawLinkPosition(elemTo, container);
		let x2 = posTo.x;
		let y2 = posTo.y;

		if (x1<x2) {
			x1 += elemFrom.width();
		} else {
			x2 += elemTo.width();
		}

		jQuery("#visualSvg" + activeIndex + " ." + dataFrom + "_" + dataTo)
			.attr("x1", x1)
			.attr("y1", y1)
			.attr("x2", x2)
			.attr("y2", y2);

		// redraw properties circle
		const cx = (x2 + x1) / 2;
		const cy = (y2 + y1) / 2;
		jQuery("#visualSvg" + activeIndex + " ." + dataFrom + "_" + dataTo + "_properties")
			.attr("cx", cx)
			.attr("cy", cy);
	});
}

function addTableWidget(schema, table, activeIndex, data, rebuild = false) {
	var columnRows = "";
	for (var col in data.columns) {
		columnName = data.columns[col]["column_name"];
		columnType = data.columns[col]["column_type"];

		indexes = "";
		for (var index in data.indexes) {
			if (data.indexes[index]["column_name"]==columnName) {
				if (data.indexes[index]["non_unique"]==="0") {
					indexes = "<span class='dashicons dashicons-admin-network wpda_tooltip2' title='Unique index'></span>";
				} else {
					if (data.indexes[index]["index_type"]==="FULLTEXT") {
						indexes = "<span class='dashicons dashicons-superhero wpda_tooltip2' title='Fulltext index'></span>";
					} else {
						indexes = "<span class='dashicons dashicons-search wpda_tooltip2' title='Non-unique index'></span>";
					}
				}
			}
		}

		var tableUsed  = jQuery("#visualQuery" + activeIndex + " .visualTable." + table).length;
		var tableAlias = table;
		if (tableUsed>0) {
			tableAlias += tableUsed+1;
		}

		var rowId = `tab${activeIndex}_${tableAlias}_${columnName}`; // !!!SYNCED!!! see: addColumnToSelection
		columnRows +=`
			<tr id="${rowId}"
				class="visualSelectedColumn" 
				data-schema="${schema}"
				data-table="${table}"
				data-column="${columnName}"
			>
				<td class="columnName">
					<input type="checkbox"/> <span>${columnName}</span>
				</td>
				<td>
					<span data-title="Add additional ${columnName} column" class="wpda_extra_column">
						<i class="fas fa-plus-square"></i>
					</span>
				</td>
				<td class="columnType">${columnType}</td>
				<td>${indexes}</td>
				<td onclick="setColumnLink(event, jQuery(this).closest('.visualComponent').data('index'), jQuery(this))">
					<span class='link_closed far fa-circle wpda_tooltip' title="Click to add a join"></span>
					<span class='link_open fas fa-circle' style="display: none"></span>
				</td>
			</tr>
		`;
	}

	var title = table===tableAlias ? table : tableAlias + " (" + table + ")";
	var widget = `
		<div id="wpda-widget${activeIndex}_${tableAlias}" data-table="${table}" data-alias="${tableAlias}" class="wpda_visual_table_widget ui-widget">
			<div class="wpda_visual_widget_content">
				<div class="ui-widget-header">
					<span>${title}</span>
					<span class="icons">
						<i class='fas fa-window-close wpda-widget-close wpda_tooltip2' title='Close'></i>
					</span>
				</div>
				<div class="ui-widget-content">
					<div id="tab${activeIndex}_${tableAlias}" class="visualTable ${table}">
						<table class="tableWidget">
							${columnRows}
						</table>
					</div>
				</div>
			</div>
		</div>
	`;

	var widgetElement = jQuery(widget);
	var widgets = getWidgets(activeIndex);
	var top = 40 + 15 * widgets.widgets.length;
	var left = 10 + 15 * widgets.widgets.length;
	if (rebuildVisualQuery[activeIndex]) {
		var rebuildingWidget = getRebuildingWidget(tabIndex, tableAlias);
		top = rebuildingWidget.top;
		left = rebuildingWidget.left;
	}
	widgetElement.css("top", top + "px");
	widgetElement.css("left", left + "px");
	widgetElement.css("z-index", maxWidgetZindex());

	jQuery("#visualQuery" + activeIndex).append(widgetElement);
	jQuery("#wpda-widget" + activeIndex + "_" + tableAlias).draggable({
		opacity: 1.0,
		drag: function(event, ui) {
			if (ui.position.top<0 || ui.position.left<0) {
				return false;
			}
			redrawLink(activeIndex, tableAlias);
		},
		start: function(event, ui) {
			jQuery(event.target).css("z-index", maxWidgetZindex());
			jQuery(event.target).css("boxShadow", "0 0 50px gray");
		},
		stop: function(event, ui) {
			jQuery(event.target).css("boxShadow", "");
		}
	});
	jQuery("#tab" + activeIndex + "_" + tableAlias).on("scroll", function() {
		redrawLink(activeIndex, tableAlias);
	});

	jQuery("#wpda-widget" + activeIndex + "_" + tableAlias + " .ui-widget-content").resizable({
		handles: "e,s",
		resize: function( event, ui ) {
			redrawLink(activeIndex, tableAlias);
		}
	});

	var panel = jQuery("#wpda-widget" + activeIndex + "_" + tableAlias + " .ui-widget-content");
	if (panel.height()>200) {
		panel.height(200); // max initial height
		panel.width(panel.width()+20); // scroll bar
	}

	jQuery(".wpda-widget-close").off();
	jQuery(".wpda-widget-close").on("click", function(event) {
		var elem = jQuery(this).closest(".wpda_visual_table_widget");

		var alias = elem.data("alias"); // click event handlers multiple elements: get correct alias

		// Remove related links
		jQuery("#visualSvg" + activeIndex + " line." + alias).remove();
		jQuery("#visualSvg" + activeIndex + " circle." + alias).remove();

		// Remove selected columns
		jQuery("#visualSelection" + activeIndex + "table .wpda_alias_" + alias).remove();

		// Remove table/view
		elem.remove();

		updateQuery(activeIndex);
		if (jQuery("#visualQuery" + activeIndex + " .wpda_visual_table_widget").length===0) {
			updateFilterContainer(activeIndex, true);
		}
		removeColumnFromFilters(activeIndex, tableAlias);
		updateColumnLists(activeIndex);
	});

	jQuery("#tab" + activeIndex + "_" + tableAlias + " .visualSelectedColumn").on("click", function(e) {
		var checkbox = jQuery(this).find("input");

		if (e.target.type!=="checkbox") {
			// Update checkbox
			if (checkbox.prop("checked")) {
				checkbox.prop("checked", false);
			} else {
				checkbox.prop("checked", true);
			}
		}

		var column = jQuery(this).data("column");
		var extraColumn = jQuery(this).find(".wpda_extra_column");
		if (checkbox.prop("checked")) {
			checkbox.closest("tr").addClass("selectedRow");

			extraColumn.attr("title", extraColumn.data("title"));
			extraColumn.tooltip({
				tooltipClass: "wpda_tooltip_dashboard"
			});

			addColumnToSelection(activeIndex, tableAlias, column);
		} else {
			checkbox.closest("tr").removeClass("selectedRow");

			extraColumn.tooltip({
				show: false
			});
			extraColumn.removeAttr("title");

			removeColumnFromSelection(activeIndex, tableAlias, column);
			updateGroupByList(jQuery("#visualSelection" + activeIndex + "table input[name='columnGroup']").first());
		}

		updateQuery(activeIndex);
	});

	jQuery('div.wpda_visual_table_widget .wpda_tooltip').tooltip({
		tooltipClass: "wpda_tooltip_dashboard",
		position: { my: "right bottom", at: "right top" }
	});
	jQuery('div.wpda_visual_table_widget .wpda_tooltip2').tooltip({
		tooltipClass: "wpda_tooltip_dashboard"
	});

	jQuery("#tab" + activeIndex + "_" + tableAlias + " .visualSelectedColumn .wpda_extra_column").on("click", function(e) {
		e.preventDefault();
		e.stopPropagation();

		var column = jQuery(this).closest("tr").data("column");
		addColumnToSelection(activeIndex, tableAlias, column);
	});

	rebuildVisualQueryBuilder(activeIndex);
	updateQuery(activeIndex);
	updateFilterContainer(activeIndex, false);
	updateColumnLists(activeIndex);

	if (rebuild) {
		isChanged[tabIndex] = false;
	}
}

function maxWidgetZindex() {
	var maxIndex = 0;
	jQuery(".wpda_visual_table_widget").each(function() {
		if (!isNaN(jQuery(this).css("z-index"))) {
			maxIndex = Math.max(maxIndex, jQuery(this).css("z-index"));
		}
	});
	return ++maxIndex;
}

function addColumnToSelection(activeIndex, tableAlias, column) {
	var origAlias = initcap(column);
	var columnAlias = origAlias;
	var i = 2;

	var availAlias = jQuery("#visualSelection" + activeIndex + "table td.column_alias." + columnAlias);
	while (availAlias.length>0) {
		columnAlias = origAlias + i++;
		availAlias = jQuery("#visualSelection" + activeIndex + "table td.column_alias." + columnAlias);
	}

	var rowId = `sel_tab${activeIndex}_${tableAlias}_${columnAlias}`;
	var rowClass = `sel_tab${activeIndex}_${tableAlias}_${column}`; // !!!SYNCED!!! see: addTableWidget
	var newColumn = `
		<tr id="${rowId}" class="wpda_alias_${tableAlias} ${rowClass}" data-column="${column}">
			<td>
				<span class="dashicons dashicons-move"></span>
			</td>
			<td class="column_visibility">
				<input type="checkbox" checked/>
			</td>
			<td class="column_definition">${tableAlias}.${column}</td>
			<td class="column_alias ${columnAlias}">
				<input type="text" value="${columnAlias}" name="columnAlias"/>
			</td>
			<td class="column_sort">
				<div>
					<input type="checkbox" name="columnSort"/>
					<input type="number" name="columnSortSequence" style="visibility:hidden" min="1" onkeydown="return false" />
					<select name="columnSortOrder" style="visibility:hidden" >
						<option value="asc">Ascending</option>
						<option value="desc">Descending</option>
					</select>
				</div>
			</td>
			<td class="column_group">
				<div>
					<input type="checkbox" name="columnGroup"/>
					<input type="number" name="columnGroupSequence" style="visibility:hidden" min="1" onkeydown="return false" />
					<select name="columnGroupFunction" style="visibility:hidden" >
						<option value=""></option>
						<option value="count">Count</option>
						<option value="sum">Sum</option>
						<option value="avg">Average</option>
						<option value="min">Minimum</option>
						<option value="max">Maximum</option>
					</select>
				</div>
			</td>
		</tr>
	`;

	jQuery("#visualSelection" + activeIndex + " tbody").append(newColumn);

	jQuery("#visualSelection" + activeIndex + "table").sortable({
		items: "tr",
		stop: function( event, ui ) {
			updateQuery(activeIndex);
		}
	});

	jQuery("#" + rowId + " td.column_visibility input[type='checkbox']").on("change", {tabIndex: activeIndex}, function() {
		updateQuery(tabIndex);
	});

	jQuery("#" + rowId + " input[name='columnAlias']").on("keyup", {tabIndex: activeIndex}, function() {
		updateQuery(tabIndex);
	});

	jQuery("#" + rowId + " input[name='columnSort']").on("change", {tabIndex: activeIndex}, function() {
		if (!jQuery(this).is(":checked")) {
			jQuery(this).parent().find("input[type='number']").val("").css("visibility", "hidden");
			jQuery(this).parent().find("select").val("").css("visibility", "hidden");
		} else {
			var maxSequence = 0;
			jQuery(this).closest("table").find("input[name='columnSortSequence']").each(function(value){
				maxSequence = Math.max(maxSequence, jQuery(this).val());
			});
			maxSequence++;

			jQuery(this).parent().find("input[type='number']").val(maxSequence).css("visibility", "visible");
			jQuery(this).parent().find("select").val("asc").css("visibility", "visible");
		}

		updateQuery(tabIndex);
	});

	jQuery("#" + rowId + " input[name='columnSortSequence']").on("change", {tabIndex: activeIndex}, function() {
		updateQuery(tabIndex);
	});

	jQuery("#" + rowId + " select[name='columnSortOrder']").on("change", {tabIndex: activeIndex}, function() {
		updateQuery(tabIndex);
	});

	jQuery("#" + rowId + " input[name='columnGroup']").on("change", {tabIndex: activeIndex}, function() {
		if (!jQuery(this).is(":checked")) {
			jQuery(this).parent().find("input[type='number']").val("").css("visibility", "hidden");
			updateGroupByList(jQuery(this));
		} else {
			var maxSequence = 0;
			jQuery(this).closest("table").find("input[name='columnGroupSequence']").each(function(value){
				maxSequence = Math.max(maxSequence, jQuery(this).val());
			});
			maxSequence++;

			jQuery(this).parent().find("input[type='number']").val(maxSequence).css("visibility", "visible");
			updateGroupByList(jQuery(this));
		}

		updateQuery(tabIndex);
	});

	jQuery("#" + rowId + " input[name='columnGroupSequence']").on("change", {tabIndex: activeIndex}, function() {
		updateQuery(tabIndex);
	});

	jQuery("#" + rowId + " select[name='columnGroupFunction']").on("change", {tabIndex: activeIndex}, function() {
		updateQuery(tabIndex);
	});

	updateGroupByList(jQuery("#visualSelection" + activeIndex + "table input[name='columnGroup']"));
	updateQuery(activeIndex);
}

function updateGroupByList(elem) {
	if (hasGroupBy(elem)) {
		elem.closest("table").find(".column_group").each(function() {
			if (jQuery(this).find("input[type='checkbox']").is(":checked")) {
				jQuery(this).find("select").css("visibility", "hidden");
			} else {
				jQuery(this).find("select").css("visibility", "visible");
			}
		});
	} else {
		elem.closest("table").find(".column_group").each(function() {
			jQuery(this).find("select").css("visibility", "hidden");
		});
	}
}

function removeColumnFromSelection(activeIndex, tableAlias, column) {
	// Remove column
	jQuery(".sel_tab" + activeIndex + "_" + tableAlias + "_" + column).remove();
}

function removeColumnFromFilters(activeIndex, tableAlias) {
	jQuery("#visualFilterContainer" + activeIndex + " .filterColumnName").each(function() {
		const columnValue = jQuery(this).val();
		if (columnValue.startsWith(tableAlias + ".")) {
			if (jQuery(this).closest(".filterGroup").find(".filterCondition").length>1) {
				// Remove condition only
				jQuery(this).closest(".filterCondition").remove();
			} else {
				// Remove entire group
				jQuery(this).closest(".filterGroup").remove();
			}
		}
	});
}

function initcap(txt) {
	return txt.charAt(0).toUpperCase() + txt.slice(1);
}

function updateQuery(activeIndex, isExecutingQuery = false) {
	if (!isVisualQueryBuilderActive(activeIndex)) {
		// Visual Query Builder not active
		return true;
	}

	var tables = jQuery("#visualQuery" + activeIndex + " .wpda_visual_table_widget");
	if (tables.length===0) {
		jQuery("#wpda_query_builder_result_" + activeIndex).html("No table selected");
		clearEditor(activeIndex)
		return false;
	}

	var sqlQuery = addColumns(activeIndex);
	sqlQuery += addJoins(activeIndex, tables);
	sqlQuery += addFilter(activeIndex, isExecutingQuery);
	sqlQuery += addGroupBy(activeIndex, isExecutingQuery);
	sqlQuery += addOrderBy(activeIndex, isExecutingQuery);

	var cm = editors["tab" + activeIndex].codemirror;
	cm.setValue(sqlQuery);
	cm.save();

	return true;
}

function addColumns(activeIndex) {
	var selectedColumns = jQuery("#visualSelection" + activeIndex + "table tbody tr");
	var hasGroupByColumn = hasGroupBy(jQuery("#visualSelection" + activeIndex + "table"));
	var sqlQuery = "SELECT\t";
	var hasVisbleColumns = false;

	for (var i=0; i<selectedColumns.length; i++) {
		if (!jQuery(selectedColumns[i]).find(".column_visibility input[type='checkbox']").is(":checked")) {
			break;
		}

		var selectedColumn = jQuery(selectedColumns[i]);
		var columnName = selectedColumn.find(".column_definition").text();
		var columnAlias = selectedColumn.find(".column_alias input[type='text']").val();
		var columnGroupFunction = selectedColumn.find("select[name='columnGroupFunction']").val();

		if (hasVisbleColumns) {
			sqlQuery += ",\t\t";
		}

		var columnNames = columnName.split(".");
		var sqlColumnName = "`" + columnNames[0] + "`.`" + columnNames[1] + "`";
		if (hasGroupByColumn && columnGroupFunction!=="") {
			if (columnGroupFunction==="count") {
				sqlQuery += columnGroupFunction.toUpperCase() + "(" + sqlColumnName + ")";
			} else {
				sqlQuery += columnGroupFunction.toUpperCase() + "(IFNULL(" + sqlColumnName + ",0))";
			}
		} else {
			sqlQuery += sqlColumnName;
		}

		if (columnAlias.trim() != "") {
			sqlQuery += " as '" + columnAlias + "'";
		}

		sqlQuery += "\n";

		hasVisbleColumns = true;
	}

	if (!hasVisbleColumns) {
		sqlQuery += "*\n";
	}

	sqlQuery += "FROM\t";

	return sqlQuery;
}

function hasGroupBy(elem) {
	var hasGroupBy = false
	elem.closest("table").find(".column_group").each(function() {
		if (jQuery(this).find("input[type='checkbox']").is(":checked")) {
			hasGroupBy = true;
		}
	});
	return hasGroupBy;
}

function addGroupBy(activeIndex, isExecutingQuery) {
	if (!hasGroupBy(jQuery("#visualSelection" + activeIndex + "table"))) {
		return "";
	}

	var group = jQuery("#visualSelection" + activeIndex + "table td.column_group");
	var groupColumns = [];
	var sequenceNumbers = [];

	group.each(function() {
		if (jQuery(this).find("input[type='checkbox']").is(":checked")) {
			var columnName = jQuery(this).closest("tr").find(".column_definition").text();
			var sequenceNumber = jQuery(this).find("input[name='columnGroupSequence']").val();

			if (isExecutingQuery) {
				if (sequenceNumbers.includes(sequenceNumber)) {
					var msg = "Invalid grouping! Each column must have a unique sequence number.";
					alert(msg);
					throw msg;
				}
			}

			groupColumns.push({
				columnName: columnName,
				sequenceNumber: sequenceNumber
			});

			sequenceNumbers.push(sequenceNumber);
		}
	});
	groupColumns.sort((a, b) => (a.sequenceNumber > b.sequenceNumber) ? 1 : -1);

	var groupby = "";
	for (var i=0; i<groupColumns.length; i++) {
		var columnNameSplit = groupColumns[i].columnName.split(".");
		var columnName = "`" + columnNameSplit[0] + "`.`" + columnNameSplit[1] + "`";

		groupby += i>0 ? ", " : "GROUP BY ";
		groupby += columnName;
	}

	return groupby==="" ? "" : groupby + "\n";
}

function addOrderBy(activeIndex, isExecutingQuery) {
	var sort = jQuery("#visualSelection" + activeIndex + "table td.column_sort");
	var sortColumns = [];
	var sequenceNumbers = [];

	sort.each(function() {
		if (jQuery(this).find("input[type='checkbox']").is(":checked")) {
			var columnName = jQuery(this).closest("tr").find(".column_definition").text();
			var sequenceNumber = jQuery(this).find("input[name='columnSortSequence']").val();
			var sortDirection = jQuery(this).find("select[name='columnSortOrder']").val();

			if (isExecutingQuery) {
				if (sequenceNumbers.includes(sequenceNumber)) {
					var msg = "Invalid sorting! Each column must have a unique sequence number.";
					alert(msg);
					throw msg;
				}
			}

			sortColumns.push({
				columnName: columnName,
				sequenceNumber: sequenceNumber,
				sortDirection: sortDirection
			});

			sequenceNumbers.push(sequenceNumber);
		}
	});
	sortColumns.sort((a, b) => (a.sequenceNumber > b.sequenceNumber) ? 1 : -1);

	var orderby = "";
	for (var i=0; i<sortColumns.length; i++) {
		var columnNameSplit = sortColumns[i].columnName.split(".");
		var columnName = "`" + columnNameSplit[0] + "`.`" + columnNameSplit[1] + "`";

		orderby += i>0 ? ", " : "ORDER BY ";
		orderby += columnName + " " + sortColumns[i].sortDirection;
	}

	return orderby==="" ? "" : orderby + "\n";
}

function addFilter(activeIndex, isExecutingQuery) {
	var filterContainer = jQuery("#visualFilterContainer" + activeIndex);
	var sql = '';

	filterContainer.find("> div.filterGroup").each(function(index) {
		var filterOperator = "\t";
		if (index>0) {
			filterOperator = "\t" + jQuery(this).find("> div.filterOperator .button-primary").text() + "\t";
		}

		sql += filterOperator + addFilterGroup(jQuery(this), isExecutingQuery) + "\n";
	});

	return sql==="" ? "" : `WHERE${sql}`;
}

function addFilterGroup(filterElement, isExecutingQuery) {
	var sql = "";

	filterElement.find("> div > div.filterConditions > div").each(function(index) {
		if (jQuery(this).hasClass("filterGroup")) {
			var filterOperator = "";
			if (sql!=="") {
				filterOperator = " " + jQuery(this).find("> div.filterOperator .button-primary").text() + " ";
			}
			sql += filterOperator + addFilterGroup(jQuery(this), isExecutingQuery);
		} else if (jQuery(this).hasClass("filterCondition")) {
			sql += addFilterCondition(jQuery(this), isExecutingQuery, index);
		}
	});

	return `(${sql})`;
}

function addFilterCondition(filterElement, isExecutingQuery, index) {
	var filterConditionOperator = "";
	if (index>0) {
		filterConditionOperator = " " + filterElement.find(".filterConditionOperator .button-primary").text() + " ";
	}
	var filterColumnName = filterElement.find(".filterColumnName").val();
	var filterColumnDataType = filterElement.find(".filterColumnName option:selected").data("type");
	var filterColumnOperator = filterElement.find(".filterColumnOperator").val();
	var filterColumnValue = filterElement.find(".filterColumnValue").val();

	if (isExecutingQuery) {
		if (
			filterColumnName === "" ||
			filterColumnOperator === "" ||
			( filterColumnValue === "" && !filterColumnOperator.includes("null") )
		) {
			var msg = "Invalid filter! Fields: " + filterColumnName;
			if (filterColumnName==="") {
				msg = "Invalid filter! Please enter all fields.";
			}
			alert(msg);
			throw msg;
		}
	}

	var columnNameSplit = filterColumnName.split(".");
	var columnName = "`" + columnNameSplit[0] + "`.`" + columnNameSplit[1] + "`";

	var value = filterColumnValue;
	if (filterColumnOperator.includes("null")) {
		value = "";
		if (filterColumnDataType==="string") {
			columnName = "NULLIF(" + columnName + ", '')";
		}
	} else if (filterColumnOperator.includes("*")) {
		value = filterColumnValue;
	} else if (filterColumnOperator.includes("like")) {
		value = `'%${filterColumnValue}%'`;
	} else {
		if (filterColumnDataType==="string") {
			value = `'${filterColumnValue}'`;
		} else if (filterColumnDataType==="date") {
			value = convertDate(filterColumnValue);
		}
	}

	var filterOperator = filterColumnOperator==="*" ? "" : " " + filterColumnOperator;
	return `${filterConditionOperator}${columnName}${filterOperator} ${value}`;
}

function convertDate(dateValue) {
	return dateValue.slice(0,10).replace(/-/g,"");
}

function addJoins(activeIndex, tables) {
	let sqlQuery = "";

	let tableAliasses = [];
	let addedAliasses = [];
	for (let i=0; i<tables.length; i++) {
		tableAliasses.push({
			tableName: jQuery(tables[i]).data("table"),
			tableAlias: jQuery(tables[i]).data("alias")
		});
	}

	for (let i=0; i<tableAliasses.length; i++) {
		const tableName = tableAliasses[i].tableName;
		const tableAlias = tableAliasses[i].tableAlias;

		if (i===0) {
			if (tableName == tableAlias) {
				sqlQuery += "`" + jQuery(tables[0]).data("table") + "`";
			} else {
				sqlQuery += "`" + jQuery(tables[0]).data("table") + "` " + jQuery(tables[0]).data("alias");
			}
		} else {
			let innerJoinTable = "`" + tableName + "`";
			if (tableName!==tableAlias) {
				innerJoinTable += " " + tableAlias;
			}

			if (jQuery("#visualSvg" + activeIndex + " line." + tableAlias).length>0) {
				for (var z=0; z<i; z++) {
					const prevTableAlias = tableAliasses[z].tableAlias;
					const joins = jQuery("#visualSvg" + activeIndex + " line." + prevTableAlias + "." + tableAlias);

					if (joins.length>0) {
						for (let j = 0; j < joins.length; j++) {
							const fromTable = jQuery(joins[j]).data("from");
							const fromTableColumn = jQuery("#tab" + activeIndex + "_" + fromTable + " td.columnName span").text();
							const fromTableAlias = jQuery("#tab" + activeIndex + "_" + fromTable).closest(".wpda_visual_table_widget").data("alias");

							const toTable = jQuery(joins[j]).data("to");
							const toTableColumn = jQuery("#tab" + activeIndex + "_" + toTable + " td.columnName span").text();
							const toTableAlias = jQuery("#tab" + activeIndex + "_" + toTable).closest(".wpda_visual_table_widget").data("alias");

							if (j === 0) {
								const propertiesSelector = fromTable + "_" + toTable + "_properties";
								const properties = jQuery("#visualSvg" + activeIndex + " circle." + propertiesSelector)
								const joinFrom = properties.attr("data-joinfrom");
								const joinTo = properties.attr("data-jointo");

								let joinType = "";
								if (joinFrom==="true") {
									if (joinTo==="true") {
										joinType = "INNER";
									} else {
										joinType = "RIGHT";
									}
								} else {
									if (joinTo==="true") {
										joinType = "LEFT";
									} else {
										// MySQL does not support full join
										joinType = "INNER";
									}
								}

								sqlQuery += joinType + " JOIN " + innerJoinTable + " ON ";
							} else {
								sqlQuery += "\n\tAND ";
							}

							sqlQuery += "`" + fromTableAlias + "`.`" + fromTableColumn + "` = `" + toTableAlias + "`.`" + toTableColumn + "`";
						}
					}
				}
			} else {
				if (!addedAliasses.includes(tableAlias)) {
					sqlQuery += ",\t\t" + innerJoinTable + "\n";
				}
			}
		}

		addedAliasses.push(tableAlias);
		sqlQuery += "\n";
	}

	return sqlQuery;
}

function isVisualQueryBuilderActive(activeIndex) {
	return jQuery("#visualComponent" + activeIndex).length>0;
}

function clearEditor(activeIndex) {
	var cm = editors["tab" + activeIndex].codemirror;
	cm.setValue("");
	cm.save();
}

function deleteFilter(activeIndex, elem) {
	var parentElement = null;
	if (elem.closest(".filterGroup").find(".filterCondition").length>1) {
		parentElement = elem.closest(".filterCondition").parent();
		elem.closest(".filterCondition").remove();
	} else {
		parentElement = elem.closest(".filterGroup").parent();
		elem.closest(".filterGroup").remove();
	}

	var noConditionInGroup = parentElement.closest(".filterGroup").find(".filterCondition").length;
	if (noConditionInGroup===0) {
		var grandParent = parentElement.closest(".filterGroup").parent();
		parentElement.closest(".filterGroup").remove();
		deleteEmptyParentFilter(grandParent);
	} else if (noConditionInGroup>1) {
		deleteFilter(activeIndex, parentElement);
	} else {
		updateQuery(activeIndex);
	}
}

function deleteEmptyParentFilter(elem) {
	// delete empty parent groups
	if (elem.closest(".filterGroup").length>0 && elem.closest(".filterGroup").find(".filterCondition").length===0) {
		var grandParent = elem.closest(".filterGroup").parent();
		elem.closest(".filterGroup").remove();
		// recursive call to cleanup empty parent groups
		deleteEmptyParentFilter(grandParent);
	}
}

function addCondition(activeIndex, elem) {
	var condition = getCondition(activeIndex);
	elem.closest(".filterConditions").append(condition);
	addAndOrEvents(activeIndex);
	elem.parent().toggle();
	updateQuery(activeIndex);
}

function toggleDropDownMenu(elem) {
	jQuery(".wpda_menu_drop_down_content").hide();
	elem.parent().find(".wpda_menu_drop_down_content").toggle();
}

function getCondition(activeIndex) {
	var select = getSelectColumnsList(activeIndex);
	var optionSelect = optionSelectAColumn();
	return `
		<div class="filterCondition">
			<div class="filterConditionOperator">
				<div class="button button-primary operator_and">AND</div><div class="button button-secondary operator_or">OR</div>
			</div>
			${select}
			<select class="filterColumnOperator" onchange="selectColumnOperator(jQuery(this).closest('.visualComponent').data('index'), jQuery(this))">
				${optionSelect}
			</select>
			<input type="text" class="filterColumnValue" onkeyup="updateQuery(jQuery(this).closest('.visualComponent').data('index'))"/>
			<div class="wpda_menu_drop_down">
				<button class="button button-primary wpda_menu_icon" onclick="toggleDropDownMenu(jQuery(this))"><i class="fas fa-bars"></i></button>
				<div class="wpda_menu_drop_down_content">
					<a href="javascript:void(0)" class="wpda_vqb_icon" onclick="deleteFilter(jQuery(this).closest('.visualComponent').data('index'), jQuery(this))"><i class="fas fa-trash wpda_icon_on_button"></i> Delete condition</a>
					<a href="javascript:void(0)" class="wpda_vqb_icon wpda_icon_add" onclick="addCondition(jQuery(this).closest('.visualComponent').data('index'), jQuery(this))"><i class="fas fa-plus-circle wpda_icon_on_button"></i> Add condition</a>
					<a href="javascript:void(0)" class="wpda_vqb_icon wpda_icon_add" onclick="addVisualFilterContainer(jQuery(this).closest('.visualComponent').data('index'), jQuery(this).closest('.filterConditions')); jQuery(this).parent().toggle();"><i class="fas fa-plus-square wpda_icon_on_button"></i> Add filter group</a>
					<a href="javascript:void(0)" class="wpda_vqb_icon wpda_icon_add" onclick="jQuery(this).parent().toggle()"><i class="fas fa-times-circle wpda_icon_on_button"></i> Quit</a>
				</div>
			</div>
		</div>
	`;
}

function optionSelectAColumn() {
	return '<option value="">Select a column...</option>';
}

function addVisualFilterContainer(activeIndex, elem = null) {
	var condition = getCondition(activeIndex);
	var html = `
		<div class="filterGroup">
			<div class="filterOperator">
				<div>
					<div class="button button-primary operator_and">AND</div><div class="button button-secondary operator_or">OR</div>
				</div>
			</div>
			<div>
				<div class="filterConditions">
					${condition}
				</div>
			</div>
		</div>
	`;

	if (elem===null) {
		jQuery("#visualFilterContainer" + activeIndex).append(html);
	} else {
		elem.append(html);
	}

	addAndOrEvents(activeIndex);

	jQuery("#visualFilter" + activeIndex + " .wpda_tooltip").tooltip({
		tooltipClass: "wpda_tooltip_dashboard"
	});
}

function addAndOrEvents(activeIndex) {
	jQuery(".operator_and").off();
	jQuery(".operator_and").on("click", function() {
		jQuery(this).addClass("button-primary").removeClass("button-secondary");
		jQuery(this).parent().find(".operator_or").removeClass("button-primary").addClass("button-secondary");
		updateQuery(activeIndex);
	});

	jQuery(".operator_or").off();
	jQuery(".operator_or").on("click", function() {
		jQuery(this).addClass("button-primary").removeClass("button-secondary");
		jQuery(this).parent().find(".operator_and").removeClass("button-primary").addClass("button-secondary");
		updateQuery(activeIndex);
	});
}

function selectColumnValue(activeIndex, elem) {
	var dataType = elem.find("option:selected").data("type");
	var options = "";

	if (elem.val()==="") {
		options = optionSelectAColumn();
	} else {
		switch (dataType) {
			case "number":
				options = `
					<option value="=">Equal</option>
					<option value="!=">Not equal</option>
					<option value="<">Less than</option>
					<option value="<=">Less than or equal to</option>
					<option value=">">Greater than</option>
					<option value=">=">Greater than or equal to</option>
					<option value="is null">Empty</option>
					<option value="is not null">Not empty</option>
					<option value="*">Custom</option>
				`;
				elem.parent().find(".filterColumnValue").prop("type", "number");
				break;
			case "date":
				options = `
					<option value="=">Equal</option>
					<option value="!=">Not equal</option>
					<option value="<">Before</option>
					<option value=">">After</option>
					<option value="is null">Empty</option>
					<option value="is not null">Not empty</option>
				`;
				elem.parent().find(".filterColumnValue").prop("type", "date");
				break;
			default:
				// string
				options = `
					<option value="=">Equal</option>
					<option value="!=">Unequal</option>
					<option value="like">Contains</option>
					<option value="not like">Not contains</option>
					<option value="is null">Empty</option>
					<option value="is not null">Not empty</option>
					<option value="*">Custom</option>
				`;
				elem.parent().find(".filterColumnValue").prop("type", "text");
		}
	}

	elem.parent().find(".filterColumnOperator option").remove();
	elem.parent().find(".filterColumnOperator").append(options);
	elem.parent().find(".filterColumnValue").val("");

	updateQuery(activeIndex);
}

function selectColumnOperator(activeIndex, elem) {
	var dataType = elem.parent().find(".filterColumnName option:selected").data("type");

	if (dataType==="number") {
		if (elem.val()==="*") {
			elem.parent().find(".filterColumnValue").prop("type", "text");
		} else {
			elem.parent().find(".filterColumnValue").prop("type", "number");
		}
	}

	updateQuery(activeIndex);
}

function getSelectColumnsList(activeIndex) {
	var options = getOptionsColumnsList(activeIndex);
	var optionSelect = optionSelectAColumn();

	return `
		<select class="filterColumnName" onchange="selectColumnValue(jQuery(this).closest('.visualComponent').data('index'), jQuery(this))">
			${optionSelect}
			${options}
		</select>
	`;
}

function getOptionsColumnsList(activeIndex) {
	var selectedColumns = getColumnsList(activeIndex);
	var options = "";

	for (var i=0; i<selectedColumns.length; i++) {
		options += `
			<option value="${selectedColumns[i].columnName}" data-type="${selectedColumns[i].dataType}">
				${selectedColumns[i].columnName}
			</option>
		`;
	}

	return options;
}

function getColumnsList(activeIndex) {
	var selectedColumns = [];

	jQuery("#visualQuery" + activeIndex + " .wpda_visual_table_widget").each(function() {
		var alias = jQuery(this).data("alias");
		jQuery(this).find("tr").each(function() {
			var column = jQuery(this).data("column");
			var dataType = jQuery(this).find("td.columnType").text();
			var basicDataType = convertDataType(dataType);
			var colObject = {
				columnName: alias + "." + column,
				dataType: basicDataType
			};
			selectedColumns.push(colObject);
		});
	});

	return selectedColumns;
}

function convertDataType(columnType) {
	var dataType = "";

	var basicColumnType = columnType;
	if (columnType.includes("(")) {
		basicColumnType = columnType.substr(0, columnType.indexOf('(')).toLowerCase();
	}

	switch (basicColumnType) {
		case "bit":
		case "bool":
		case "boolean":
		case "tinyint":
		case "smallint":
		case "int":
		case "integer":
		case "mediumint":
		case "bigint":
		case "float":
		case "double":
		case "decimal":
		case "dec":
			dataType = "number";
			break;
		case "date":
		case "datetime":
		case "timestamp":
			dataType = "date";
			break;
		case "time":
			dataType = "time";
			break;
		default:
			dataType = "string";
			break;
	}

	return dataType;
}

function updateFilterContainer(activeIndex, disabled) {
	if (disabled) {
		jQuery("#visualFilterContainer" + activeIndex + " .filterGroup").remove();
	}

	jQuery("#visualFilter" + activeIndex + " .wpda_add_filter_button").attr("disabled", disabled);
}

function updateColumnLists(activeIndex) {
	var options = getOptionsColumnsList(activeIndex);

	jQuery("#visualFilterContainer" + activeIndex + " .filterColumnName").each(function() {
		var currentValue = jQuery(this).val();

		jQuery(this).find("option").remove();
		jQuery(this).append(options);
		jQuery(this).val(currentValue);
	});
}

function getWidgets(tabIndex) {
	var visualQuery= {};

	// get column selection
	var columns = [];
	jQuery("#visualSelection" + tabIndex + " tbody tr").each(function() {
		// add column
		columns.push({
			columnName: jQuery(this).find(".column_definition").text(),
			columnVisibility: jQuery(this).find(".column_visibility input[type='checkbox']").is(":checked"),
			columnAlias: jQuery(this).find("input[name='columnAlias']").val(),
			columnSort: jQuery(this).find("input[name='columnSort']").is(":checked"),
			columnSortSequence: jQuery(this).find("input[name='columnSortSequence']").val(),
			columnSortOrder: jQuery(this).find("select[name='columnSortOrder']").val(),
			columnGroup: jQuery(this).find("input[name='columnGroup']").is(":checked"),
			columnGroupSequence: jQuery(this).find("input[name='columnGroupSequence']").val(),
			columnGroupFunction: jQuery(this).find("select[name='columnGroupFunction']").val()
		});
	});
	visualQuery.columns = columns;

	// get widget info
	var widgets = [];
	jQuery("#visualQuery" + tabIndex + " .wpda_visual_table_widget").each(function() {
		// add widget
		var obj = {};

		obj.tableAlias = jQuery(this).data("alias");
		obj.tableName = jQuery(this).data("table");
		obj.top = jQuery(this).css("top").replace("px", "");
		obj.left = jQuery(this).css("left").replace("px", "");

		widgets.push(obj);
	});
	visualQuery.widgets = widgets;

	// add relationships
	visualQuery.relationships = jQuery("#visualSvg" + tabIndex).html()

	// add filters
	visualQuery.filters = jQuery("#visualFilterContainer" + tabIndex).html();
	visualQuery.filterContent = [];
	jQuery("#visualFilterContainer" + tabIndex + " .filterCondition").each(function() {
		// store content of each filter separately
		var content = {
			filterColumnName: jQuery(this).find(".filterColumnName").val(),
			filterColumnOperator: jQuery(this).find(".filterColumnOperator").val(),
			filterColumnValue: jQuery(this).find(".filterColumnValue").val()
		};
		visualQuery.filterContent.push(content);
	});

	return visualQuery;
}

function getVisualQueryBuilder(tabIndex, queryName) {
	jQuery.ajax({
		method: 'POST',
		url: wpda_home_url + "?action=wpda_query_builder_get_vqb",
		data: {
			wpda_wpnonce: wpda_wpnonce,
			wpda_sqlqueryname: queryName
		}
	}).done(
		function (msg) {
			if (msg.status==="OK") {
				rebuildVisualQuery[tabIndex] = msg.content;
				rebuildVisualQueryCount[tabIndex] = msg.content.widgets.length;

				var schema = jQuery("#wpda_query_builder_dbs_" + tabIndex).val();
				var visualQuery = rebuildVisualQuery[tabIndex];

				addVisual(tabIndex); // add widgets to visual canvas
				for (var i=0; i<visualQuery.widgets.length; i++) {
					// add columns to visual selection
					addVisualTable(schema, visualQuery.widgets[i].tableName, tabIndex, true);
				}
			} else {
				alert(msg.status);
				throw msg.status;
			}
		}
	).fail(
		function (msg) {
			throw msg;
		}
	);

}

function getRebuildingWidget(tabIndex, tableAlias) {
	if (rebuildVisualQuery[tabIndex]) {
		var visualQuery = rebuildVisualQuery[tabIndex];
		for (var i=0; i<visualQuery.widgets.length; i++) {
			if (visualQuery.widgets[i].tableAlias===tableAlias) {
				return visualQuery.widgets[i];
			}
		}
	}
	return null;
}

function rebuildVisualQueryBuilder(tabIndex) {
	if (!rebuildVisualQueryCount[tabIndex]) {
		// nothing to rebuild
		return;
	}

	rebuildVisualQueryCount[tabIndex]--;
	if (rebuildVisualQueryCount[tabIndex]>0) {
		// postpone rebuild until last table is loaded
		return;
	}

	// restore selected columns and filters
	var visualQuery = rebuildVisualQuery[tabIndex];
	if (!visualQuery) {
		return;
	}


	// restore selected columns
	var selectedColumnsProcessed = [];
	for (var i=0; i<visualQuery.columns.length; i++) {
		var fullColumnName = visualQuery.columns[i].columnName;

		var tableAlias = fullColumnName.split(".")[0];
		var columnName = fullColumnName.split(".")[1];

		var column = jQuery("#tab" + tabIndex + "_" + tableAlias + "_" + columnName);
		if (selectedColumnsProcessed.indexOf(fullColumnName) === -1) {
			// restore selected column
			column.find(".columnName input[type='checkbox']").click();
		} else {
			// restore additional column
			column.find(".wpda_extra_column").click();
		}

		selectedColumnsProcessed.push(fullColumnName);
	}

	// update selected columns
	for (var i=0; i<visualQuery.columns.length; i++) {
		const currentRowArr = visualQuery.columns[i]; // database row
		const currentRowDom = jQuery("#visualSelection" + tabIndex + "table tbody tr:nth-child(" + (i+1) + ")"); // dom row

		if (currentRowArr.columnVisibility==="false") {
			currentRowDom.find("td.column_visibility input[type='checkbox']").removeAttr("checked");
		}
		currentRowDom.find("input[name='columnAlias']").val(currentRowArr.columnAlias);
		if (currentRowArr.columnSort==="true") {
			currentRowDom.find("input[name='columnSort']").click();
			currentRowDom.find("input[name='columnSortSequence']").val(currentRowArr.columnSortSequence);
			currentRowDom.find("select[name='columnSortOrder']").val(currentRowArr.columnSortOrder);
		}
		if (currentRowArr.columnGroup==="true") {
			currentRowDom.find("input[name='columnGroup']").click();
			currentRowDom.find("input[name='columnGroupSequence']").val(currentRowArr.columnGroupSequence);
		}
		currentRowDom.find("select[name='columnGroupFunction']").val(currentRowArr.columnGroupFunction);
	}

	// restore relationships
	jQuery("#visualSvg" + tabIndex).html(visualQuery.relationships);
	// add relationship events
	jQuery("#visualSvg" + tabIndex + " line").each(function() {
		const aliasFrom = jQuery(this).data("aliasFrom");
		const aliasTo = jQuery(this).data("aliasTo");

		const tableAliasFrom = jQuery(this).data("from");
		const tableAliasTo = jQuery(this).data("to");
		const linkSelector = tableAliasFrom + "_" + tableAliasTo + "_properties";

		jQuery("#visualSvg" + tabIndex + " ." + linkSelector).on("click", function () {
			linkProperties(event, aliasFrom, aliasTo, tabIndex);
		});
	});

	// restore filters
	jQuery("#visualFilterContainer" + tabIndex).append(visualQuery.filters);

	// restore filter contents
	jQuery("#visualFilterContainer" + tabIndex + " .filterCondition").each(function(index) {
		if (visualQuery.filterContent[index]) {
			jQuery(this).find(".filterColumnName").val(visualQuery.filterContent[index].filterColumnName);
			jQuery(this).find(".filterColumnOperator").val(visualQuery.filterContent[index].filterColumnOperator);
			jQuery(this).find(".filterColumnValue").val(visualQuery.filterContent[index].filterColumnValue);
		}

		// (re)enable events
		addAndOrEvents(tabIndex);
	});

	// finished rebuilding visual query
	delete rebuildVisualQuery[tabIndex];
	delete rebuildVisualQueryCount[tabIndex];
}
