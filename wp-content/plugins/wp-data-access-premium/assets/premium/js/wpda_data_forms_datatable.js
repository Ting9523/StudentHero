/**
 * Generate responsive tables for Data Projects using jQuery DataTables
 *
 * @author  Peter Schulz
 * @since   4.0.0
 */

if (typeof Object.assign != 'function') {
	// IE
	Object.assign = function(target, varArgs) { // .length of function is 2
		'use strict';
		var to = Object(target);
		for (var index = 1; index < arguments.length; index++) {
			var nextSource = arguments[index];
			if (nextSource != null) { // Skip over if undefined or null
				for (var nextKey in nextSource) {
					// Avoid bugs when hasOwnProperty is shadowed
					if (Object.prototype.hasOwnProperty.call(nextSource, nextKey)) {
						to[nextKey] = nextSource[nextKey];
					}
				}
			}
		}
		return to;
	};
}

function wpdadataforms_table(
	pageId,
	schemaName,
	tableName,
	isChild = false,
	parentRow = null,
	isLov = false
) {
	var schemaNameJS = schemaName;
	var isRemoteDBS  = false;
	if (schemaName.substr(0, 4)==="rdb:") {
		schemaNameJS = schemaName.substr(4);
		isRemoteDBS  = true;
	}

	var tableSelector = getTableSelector(pageId, schemaNameJS, tableName);
	if (isLov) {
		tableSelector = getLovSelector(pageId, schemaNameJS, tableName);
	}

	var controllerName = getControllerName(pageId, schemaNameJS, tableName, "_form");
	if (
		wpdaDataFormsProjectInfo[pageId]["page_type"]==="parent/child" &&
		!isChild &&
		Object.keys(wpdaDataFormsProjectChildTables[pageId]).length > 0 &&
		!isLov
	) {
		// Add parent/child view instead of form
		controllerName = getControllerName(
			pageId,
			schemaNameJS,
			tableName,
			"_view"
		);
	}

	if (!isLov) {
		// Create filter object
		if (wpdaDataFormsChildFilter[pageId] === undefined) {
			wpdaDataFormsChildFilter[pageId] = {};
		}
		if (wpdaDataFormsChildFilter[pageId][schemaName] === undefined) {
			wpdaDataFormsChildFilter[pageId][schemaName] = {};
		}
		if (wpdaDataFormsChildFilter[pageId][schemaName][tableName] === undefined) {
			wpdaDataFormsChildFilter[pageId][schemaName][tableName] = {};
		}
	}

	if (isChild && wpdaDataFormsProjectChildTables[pageId][tableName]!=undefined) {
		// Add filter to show only child rows
		if (wpdaDataFormsProjectChildTables[pageId][tableName]["relation_1n"]!=undefined) {
			parentKeys = wpdaDataFormsProjectChildTables[pageId][tableName]["relation_1n"]["parent_key"];
			childKeys = wpdaDataFormsProjectChildTables[pageId][tableName]["relation_1n"]["child_key"];

			for (var i=0; i<parentKeys.length; i++) {
				childCol = childKeys[i];
				parentVal = parentRow[parentKeys[i]];
				wpdaDataFormsChildFilter[pageId][schemaName][tableName][childCol] = parentVal;
			}
		} else if (wpdaDataFormsProjectChildTables[pageId][tableName]["relation_nm"]!=undefined) {
			var args = {};
			args.parent_column = wpdaDataFormsProjectChildTables[pageId][tableName]["relation_nm"]["parent_key"];
			args.select_column = wpdaDataFormsProjectChildTables[pageId][tableName]["relation_nm"]["child_table_select"];
			args.select_table = wpdaDataFormsProjectChildTables[pageId][tableName]["child_table"]["target_table_name"];
			args.select_where = wpdaDataFormsProjectChildTables[pageId][tableName]["child_table"]["target_column_name"];
			args.select_value = [];
			for (var i=0; i<wpdaDataFormsProjectChildTables[pageId][tableName]["child_table"]["source_column_name"].length; i++) {
				args.select_value.push(parentRow[wpdaDataFormsProjectChildTables[pageId][tableName]["child_table"]["source_column_name"][i]]);
			}
			wpdaDataFormsChildFilter[pageId][schemaName][tableName]["relnm"] = Object.assign({}, args);
		}
	}

	if ( jQuery.fn.dataTable.isDataTable(tableSelector) ) {
		// Requery DataTable
		table = jQuery(tableSelector).DataTable();
		table.ajax.reload();
	} else {
		// Init DataTable
		var table = wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table"];
		var hidden = wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table_hidden"];
		var noColumnsSelected = table.length;

		var columnDefs = [];
		var columnsSelected = "";
		for (var i=0; i<noColumnsSelected; i++) {
			if (hidden.includes(i)) {
				// Hide column
				columnDefs.push(
					{
						targets: i,
						visible: false,
						searchable: false
					}
				);
			} else {
				// Add WPDA Data Forms column renderer to all non hidden columns
				columnDefs.push(
					{
						targets: i,
						render: function (data, type, row, meta) {
							return wpdadataformsColumnRenderer(pageId, schemaName, tableName, meta.col, data);
						}
					}
				);
			}

			if (table[i]!==null && table[i]["column_name"]!==undefined) {
				if (columnsSelected !== "") {
					columnsSelected += ",";
				}
				columnsSelected += table[i]["column_name"];
			} else {
				// Dummy columns are not added to query
			}
		}

		// Add lookup column indexes to request for searching and ordering
		var lookups = [];
		if (wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table_lookups"]!==undefined) {
			if (jQuery.isArray(wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table_lookups"])) {
				for (var i=0; i<wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table_lookups"].length; i++) {
					lookups.push({column_index: i, column_name: table[i]["column_name"]});
				}
			} else {
				for (var prop in wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table_lookups"]) {
					lookups.push({column_index: parseInt(prop), column_name: table[parseInt(prop)]["column_name"]});
				}
			}
		}

		var defaultContent = "";
		var hasPrimaryKey = false;

		for (var prop in wpdaDataFormsProjectPages[pageId][schemaName][tableName]["primary_key"]) {
			hasPrimaryKey = true;
		}

		var orderby = [];
		if (!isChild) {
			default_orderby = wpdaDataFormsProjectInfo[pageId].page_orderby;
		} else {
			default_orderby = wpdaDataFormsProjectChildTables[pageId][tableName]["default_orderby"];
		}
		if (default_orderby !== null && default_orderby !== "") {
			orderby_array = default_orderby.replace("order by ", "").split(",");
			for (var i = 0; i < orderby_array.length; i++) {
				column_orderby = orderby_array[i].trim().split(" ");
				if (column_orderby[1] === undefined) {
					column_orderby[1] = "asc";
				}
				for (var columnObj in wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table"]) {
					if (
						wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table"][columnObj]!==null &&
						wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table"][columnObj]!==undefined &&
						column_orderby[0] === wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table"][columnObj]["column_name"]
					) {
						orderby.push([columnObj, column_orderby[1]]);
					}
				}
			}
		}

		if (hasPrimaryKey) {
			if (!isLov) {
				if (wpdaDataFormsProjectInfo[pageId]["mode"]==="edit") {
					if (isChild || wpdaDataFormsProjectInfo[pageId]["page_type"]==="table") {
						icon = "dashicons-edit-large";
						title = "Edit";
					} else {
						icon = "dashicons-database-view";
						title = "View relationships";
					}

					defaultContent =
						'<button type="button" onclick="wpdaDataFormsAngular[\'' + controllerName + '\'].editData(this, ' + isChild + ')" class="wpdadataforms-icon dashicons ' + icon + ' wpdaforms-tooltip" title="' + title + '"></button>';

					if (wpdaDataFormsProjectInfo[pageId]['allow_delete']==='yes') {
						defaultContent +=
							'<button type="button" onclick="wpdaDataFormsAngular[\'' + controllerName + '\'].deleteData(this, ' + isChild + ')" class="wpdadataforms-icon dashicons dashicons-database-remove wpdaforms-tooltip" title="Delete"></button>';
					}
				} else {
					defaultContent =
						'<button type="button" onclick="wpdaDataFormsAngular[\'' + controllerName + '\'].editData(this, ' + isChild + ')" class="wpdadataforms-icon dashicons dashicons-database-view wpdaforms-tooltip" title="View"></button>';
				}
			} else {
				// List of value: add checkbox to allow selection
				defaultContent = '<input type="checkbox" class="wpdaDataFormsLovCheckbox" />';
			}

			columnDefs.push(
				{
					targets: noColumnsSelected,
					orderable: false,
					className: "wpdadataforms-selectable",
					defaultContent:
						'<span class="wpdadataforms-icons">' + defaultContent + '</span>'
				}
			);
		}

		// Add responsive support
		var childrow = {
			details: {
				display: jQuery.fn.dataTable.Responsive.display.childRow,
				renderer: function (api, rowIdx, columns) {
					var responsiveIndexes = [];
					var responsiveColumns = [];
					var responsiveValues = [];
					var data = jQuery.map(
						columns, function (col, i) {
							if (hidden.includes(i)) {
								return "";
							}

							if (!col.hidden) {
								return "";
							}

							columnLabel = "";
							if (table[i]!=undefined && table[i].label!=undefined) {
								columnLabel = table[i].label;
							}

							columnName = "";
							if (
								wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table"]!==undefined &&
								wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table"]!==null &&
								wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table"][i]!==undefined &&
								wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table"][i]!==null &&
								wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table"][i]["column_name"]!==undefined &&
								wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table"][i]["column_name"]!==null
							) {
								columnName = wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table"][i]["column_name"];
							}

							columnValue = wpdadataformsColumnRenderer(pageId, schemaName, tableName, i, col.data);
							if (columnValue===col.data) {
								// Prepare check for auto complete and conditional lookups
								responsiveIndexes.push(i);
								responsiveColumns.push(columnName);
								responsiveValues.push(col.data);
							}

							return	'<tr class="wpdadataforms-child-row ' + columnName + '" data-column-name="' + columnName + '">' +
								'<td class="wpdadataforms-child-label">' +
									columnLabel +
								'</td>' +
								'<td class="wpdadataforms-child-value">' +
									columnValue +
								'</td>' +
								'</tr>';
						}
					).join("");
					responsiveTable = jQuery('<table id="' + pageId + '_' + tableName + '_' + rowIdx + '" class="wpdadataforms-child-table"/>').append(data);

					for (var i=0; i<responsiveIndexes.length; i++) {
						responsiveIndex  = responsiveIndexes[i];
						responsiveColumn = responsiveColumns[i];
						responsiveValue  = responsiveValues[i];

						var lookup = null;
						if (wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table_autocomplete"]!==undefined) {
							lookup = wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table_autocomplete"][responsiveIndex];
							if (lookup!==undefined) {
								// Lookups of type auto complete (needs ajax request)
								lookup.wpdadataforms_lookup_column_value = responsiveValue;
								setTimeout(wpdaDataFormsResponsiveAutocompleteLookup, 100, lookup, pageId, tableName, rowIdx, responsiveColumn);
							}
						}

						if (lookup===undefined || lookup===null) {
							if (wpdaDataFormsProjectPages[pageId][schemaName][tableName]["conditional_lookups"] !== undefined) {
								lookup = wpdaDataFormsProjectPages[pageId][schemaName][tableName]["conditional_lookups"][responsiveIndex];
								if (lookup !== undefined) {
									// Conditional lookups (needs ajax request)
									lookup.wpdadataforms_filter_column_value = responsiveValue;
									setTimeout(wpdaDataFormsResponsiveAuwpdaDataFormsResponsiveConditionalLookuptocompleteLookup, 100, lookup, pageId, tableName, rowIdx, responsiveColumn);
								}
							}
						}
					}

					return responsiveTable;
				},
				type: "inline"
			}
		};

		// Define DataTable options
		var jQueryDataTablesDefaultOptions = {
			processing: true,
			serverSide: true,
			autoWidth: false,
			responsive: childrow,
			columnDefs: columnDefs,
			order: orderby,
			pageLength: 10,
			pagingType: "full_numbers",
			dom: "lfrtip",
			select: {
				selector: "td:not(.wpdadataforms-selectable, .dtr-control)",
				style: "multi"
			},
			language: {
				url: "https://cdn.datatables.net/plug-ins/1.10.21/i18n/" + wpdaDataFormsLanguage + ".json"
			},
			ajax: {
				url: wpdaDataFormsAjaxUrl,
				method: "POST",
				data: function(data) {
					data.action = "wpdadataforms_get_list";
					data.wpdadataforms_wp_nonce = wpdaDataFormsProjectPages[pageId][schemaName][tableName]["_get_list"];
					data.wpdadataforms_page_id = pageId;
					data.wpdadataforms_schema_name = schemaName;
					data.wpdadataforms_table_name = tableName;
					data.wpdadataforms_columns = columnsSelected;
					data.wpdadataforms_child_filter = wpdaDataFormsChildFilter[pageId][schemaName][tableName];
					data.wpdadataforms_lookup_columns = lookups;
					data.wpdadataforms_set_name = wpdaDataFormsProjectInfo[pageId]["page_setname"];
					data.wpdadataforms_is_lov = isLov;
					data.wpdadataforms_is_child = isChild;
					data.wpdadataforms_referer = window.location.href;
					data.wpdadataforms_embedded = wpdaDataFormsIsEmbedded;
					if (isLov) {
						data.drawCallback = function() {
							wpdaDataFormsLovUnselectAll();
						};
					}
				}
			},
			infoCallback: function( settings, start, end, max, total, pre ) {
				prefix = '';
				if (
					wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table_settings"] !== undefined &&
					wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table_settings"]["row_count_estimate"] !== undefined &&
					wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table_settings"]["row_count_estimate"] === true
				) {
					prefix = '~';
				}
				return prefix +  pre;
			},
			drawCallback: function() {
				if (wpdaDataFormsIsFrontEnd) {
					jQuery(".wpdadataforms-button-panel").find(".dt-button").not("ui-button").button();
				}
				jQuery(".wpdaforms-tooltip").tooltip();
			},
			createdRow: function (row, data, index) {
				wpdadataformsLoadLookupValues(row, data, index, pageId, schemaName, tableName);
			},
			lengthMenu: [[5, 10, 25, 50, 100], [5, 10, 25, 50, 100]]
		};

		// Add table options
		if (wpdaDataFormsTableOptions[pageId]!==undefined && wpdaDataFormsTableOptions[pageId].trim()!=="") {
			try {
				var tableOptions = JSON.parse(jQuery("<textarea/>").html(wpdaDataFormsTableOptions[pageId]).text());
				wpdadataformsConvertStringToFunction(tableOptions);
				if ( typeof Object.assign !== "function" ) {
					console.log("WP Data Access ERROR: Invalid table options " + tableName);
				} else {
					jQueryDataTablesDefaultOptions = Object.assign(jQueryDataTablesDefaultOptions, tableOptions);
				}
			}
			catch(err) {
				console.log("WP Data Access ERROR: Invalid table options " + tableName);
				console.log(err);
			}
		}

		// Update dom to support jQuery UI
		jQueryDataTablesDefaultOptions.dom = jQueryDataTablesDefaultOptions.dom.replace("B", ""); // Remove buttons
		if (wpdaDataFormsProjectInfo[pageId]["allow_bulk"]==="yes") {
			// Add export buttons
			htmlButtons  = '<"wpdadataforms-button-panel"B>';
		} else {
			htmlButtons  = "";
		}
		index = jQueryDataTablesDefaultOptions.dom.indexOf("t");
		if (index > -1) {
			if (wpdaDataFormsIsFrontEnd) {
				wpdaButtonClass = "ui-widget-header";
			} else {
				wpdaButtonClass = "";
			}
			jQueryDataTablesDefaultOptions.dom =
				htmlButtons +
				'<"fg-toolbar ui-toolbar ' + wpdaButtonClass + ' ui-helper-clearfix ui-corner-tl ui-corner-tr"' + jQueryDataTablesDefaultOptions.dom.substr(0, index) + '>' +
				't' +
				'<"fg-toolbar ui-toolbar ' + wpdaButtonClass + ' ui-helper-clearfix ui-corner-bl ui-corner-br"' + jQueryDataTablesDefaultOptions.dom.substr(index + 1) + '>';
		}

		if (isChild || isLov) {
			jQueryDataTablesDefaultOptions.pageLength = 5;
		}

		// Create DataTable
		jQuery(tableSelector).DataTable(jQueryDataTablesDefaultOptions);
	}
}

// WPDA Data Forms column renderer
function wpdadataformsColumnRenderer(pageId, schemaName, tableName, columnNumber, data) {
	if (
		wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table_lookups"]!==undefined &&
		wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table_lookups"][columnNumber] !== undefined &&
		wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table_lookups"][columnNumber][data] !== undefined
	) {
		// Get lookup value from cache
		return wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table_lookups"][columnNumber][data];
	}

	// TODO Add user defined renderer

	return data; // no rendering
}

// Handle conditional and auto complete lookups
function wpdadataformsLoadLookupValues(row, data, index, pageId, schemaName, tableName) {
	for (var i=0; i<data.length; i++) {
		if (data[i]!==null && data[i]!=='') {
			if (
				wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table"]!==undefined &&
				wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table"]!==null &&
				wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table"][i]!==undefined &&
				wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table"][i]!==null &&
				wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table"][i]["column_name"]!==undefined &&
				wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table"][i]["column_name"]!==null
			) {
				// Add column name ass class and data attribute
				column_name = wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table"][i]["column_name"];
				jQuery('td', row).eq(i).attr('data-column-name', column_name).addClass(column_name);
			}

			if (wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table_autocomplete"]!==undefined) {
				lookup = wpdaDataFormsProjectPages[pageId][schemaName][tableName]["table_autocomplete"][i];
				if (lookup!==undefined) {
					// Lookups of type auto complete (needs ajax request)
					lookup.wpdadataforms_lookup_column_value = data[i];
					lookupColumn = jQuery('td', row).eq(i);
					lookupColumn.text("Loading...");
					wpdaDataFormsAutocompleteLookup(lookup, lookupColumn);
				}
			}

			if (wpdaDataFormsProjectPages[pageId][schemaName][tableName]["conditional_lookups"]!==undefined) {
				lookup = wpdaDataFormsProjectPages[pageId][schemaName][tableName]["conditional_lookups"][i];
				if (lookup!==undefined) {
					// Conditional lookups (needs ajax request)
					lookup.wpdadataforms_filter_column_value = data[i];
					lookupColumn = jQuery('td', row).eq(i);
					lookupColumn.text("Loading...");
					wpdaDataFormsConditionalLookup(lookup, lookupColumn);
				}
			}
		}
	}
}

function wpdaDataFormsResponsiveAutocompleteLookup(lookup, pageId, tableName, rowIdx, responsiveColumn) {
	lookupColumn = jQuery('#' + pageId + '_' + tableName + '_' + rowIdx + ' tr.' + responsiveColumn + ' td.wpdadataforms-child-value');
	lookupColumn.text("Loading...");
	wpdaDataFormsAutocompleteLookup(lookup, lookupColumn);
}

function wpdaDataFormsResponsiveConditionalLookup(lookup, pageId, tableName, rowIdx, responsiveColumn) {
	lookupColumn = jQuery('#' + pageId + '_' + tableName + '_' + rowIdx + ' tr.' + responsiveColumn + ' td.wpdadataforms-child-value');
	lookupColumn.text("Loading...");
	wpdaDataFormsConditionalLookup(lookup, lookupColumn);
}

function wpdadataformsConvertStringToFunction(obj) {
	for (var prop in obj) {
		if (typeof obj[prop]=="string") {
			if (obj[prop].substr(0,8)=="function") {
				fnc = obj[prop];
				delete obj[prop];
				var f = new Function("return " + fnc);
				obj[prop] = f();
			}
		} else {
			wpdadataformsConvertStringToFunction(obj[prop]);
		}
	}
}
