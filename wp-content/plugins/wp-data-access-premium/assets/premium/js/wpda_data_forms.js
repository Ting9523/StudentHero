/**
 * Variable and functions to support Data Forms for Data Projects
 *
 * @author  Peter Schulz
 * @since   4.0.0
 */

var wpdaDataFormsAjaxUrl = '';
var wpdaDataFormsAngularBootstrapped = null;
var wpdaDataFormsAngular = {};
var wpdaDataFormsProjectInfo = {};
var wpdaDataFormsProjectPages = {};
var wpdaDataFormsProjectTables = {};
var wpdaDataFormsProjectMedia = {};
var wpdaDataFormsProjectChildTables = {};
var wpdaDataFormsProjectLookupTables = {};
var wpdaDataFormsTableOptions = {};
var wpdaDataFormsLanguage = 'English';
var wpdaDataFormsChildFilter = {};
var wpdaDataFormsIsFrontEnd = true;
var wpdaDataFormsIsEmbedded = false;

function getTableSelector(pageId, schemaName, tableName) {
	return	'#wpdadataforms_table_' + getInstanceName(pageId, schemaName, tableName);
}

function getFormSelector(pageId, schemaName, tableName, mode = '') {
	return	'#wpdadataforms_modal_' + getInstanceName(pageId, schemaName, tableName, mode);
}

function getControllerName(pageId, schemaName, tableName, mode = '') {
	return 'wpdadataforms_controller_' + getInstanceName(pageId, schemaName, tableName, mode);
}

function getLovId(pageId, schemaName, tableName) {
	return	'wpdadataforms_table_' + getInstanceName(pageId, schemaName, tableName) + '_lov';
}

function getLovSelector(pageId, schemaName, tableName) {
	return "#" + getLovId(pageId, schemaName, tableName);
}

function getInstanceName(pageId, schemaName, tableName, mode = '') {
	return pageId + '_' + schemaName + '_' + tableName + mode;
}

function wpdaDataFormsLovSelect(elem) {
	if (jQuery(elem).prop("checked")) {
		wpdaDataFormsLovSelectAll();
	} else {
		wpdaDataFormsLovUnselectAll();
	}
}

function wpdaDataFormsLovSelectAll() {
	jQuery(".wpdaDataFormsLovCheck").prop("checked", true);
	jQuery(".wpdaDataFormsLovCheckbox").prop("checked", true);
}

function wpdaDataFormsLovUnselectAll() {
	jQuery(".wpdaDataFormsLovCheck").prop("checked", false);
	jQuery(".wpdaDataFormsLovCheckbox").prop("checked", false);
}

function wpdaDataFormsAutocompleteLookup(lookup, lookupColumn) {
	jQuery.ajax({
		method: "POST",
		url: wpdaDataFormsAjaxUrl + "?action=wpdadataforms_autocomplete_get",
		data: lookup
	}).then(
		function(data) {
			if (data.status!=="error") {
				if (false!==data.lookup) {
					lookupColumn.text(data.lookup);
				} else {
					lookupColumn.text("No data found");
				}
			} else {
				lookupColumn.text("No data found");
				console.log("ERROR", data);
			}
		},
		function(data) {
			lookupColumn.text("No data found");
			console.log("ERROR", data);
		}
	);
}

function wpdaDataFormsConditionalLookup(tableSelector, lookupColumn) {
	jQuery.ajax({
		method: "POST",
		url: wpdaDataFormsAjaxUrl + "?action=wpdadataforms_conditional_lookup_get",
		data: lookup
	}).then(
		function(data) {
			if (data.status!=="error") {
				lookupColumn.text(data.rows[0].lookup_label);
			} else {
				lookupColumn.text("No data found");
			}
		},
		function(data) {
			lookupColumn.text("No data found");
			console.log("ERROR", data);
		}
	);
}

jQuery(document).on("click", function() {
	jQuery(".wpdaforms-tooltip").tooltip(); // Add dynamically added tooltips before closing
	jQuery(".wpdaforms-tooltip").tooltip("close");
});
