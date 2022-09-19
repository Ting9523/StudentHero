/**
 * Generate Data Forms for Data Projects using AngularJS
 *
 * @author  Peter Schulz
 * @since   4.0.0
 */

function wpdadataforms_add_controller(
	wpdadataformsApp,
	pageId,
	schemaName,
	tableName,
	mode = ''
) {
	var schemaNameJS = schemaName;
	if (schemaName.substr(0, 4)==="rdb:") {
		schemaNameJS = schemaName.substr(4);
	}

	var controllerName = getControllerName(
		pageId,
		schemaNameJS,
		tableName,
		mode
	);

	wpdadataformsApp.controller(controllerName, function($scope, $http, $log) {
		if (jQuery.isEmptyObject(wpdaDataFormsAngular)) {
			$log.debug("WP Data Access - Data Forms - debugging enabled");
		}
		wpdaDataFormsAngular[controllerName] = $scope;

		$scope.pageId = pageId;
		$scope.schemaName = schemaName;
		$scope.tableName = tableName;
		$scope.mode = mode;
		$scope.parentModal = "";

		$scope.schemaNameJS = schemaName;
		$scope.isRemoteDBS  = false;
		if ($scope.schemaName.substr(0, 4)==="rdb:") {
			$scope.schemaNameJS = $scope.schemaName.substr(4);
			$scope.isRemoteDBS  = true;
		}

		$scope.autocomplete = [];
		$scope.autocompleteIndex = [];
		$scope.autocompleteHide = [];
		$scope.autocompleteData = [];

		$scope.controllerName = controllerName;
		$scope.parent = null;
		$scope.parentDataTableRow = null;

		$scope.dataProjectPageInfo = wpdaDataFormsProjectInfo[pageId];
		$scope.dataProjectPage = wpdaDataFormsProjectPages[pageId];
		$scope.dataProjectPageTable = $scope.dataProjectPage[$scope.schemaName][$scope.tableName];
		$scope.wpdaDataFormsPageChildTables = wpdaDataFormsProjectChildTables[pageId];
		if (wpdaDataFormsProjectLookupTables[pageId][tableName]===undefined) {
			$scope.dataProjectLookups = {};
		} else {
			$scope.dataProjectLookups = wpdaDataFormsProjectLookupTables[pageId][tableName];
		}
		$scope.dataProjectPageMedia = null;
		if (
			wpdaDataFormsProjectMedia[pageId]!==undefined &&
			wpdaDataFormsProjectMedia[pageId][$scope.schemaName] !== undefined &&
			wpdaDataFormsProjectMedia[pageId][$scope.schemaName][$scope.tableName] !== undefined
		) {
			$scope.dataProjectPageMedia = wpdaDataFormsProjectMedia[pageId][$scope.schemaName][$scope.tableName];
		}
		$scope.selectedRow = {};
		$scope.selectedRowIndex = -1;
		$scope.action = "";

		$scope.error = {}
		$scope.error.show = false;
		$scope.error.message = "No errors";

		$scope.success = {}
		$scope.success.show = false;
		$scope.success.message = "No messages";

		if ($scope.dataProjectPageInfo.title==='') {
			$scope.modalTitle = "Show details";
		} else {
			$scope.modalTitle = $scope.dataProjectPageInfo.title;
		}
		$scope.submit_button = "Save";

		$scope.init = function (parentModal = '') {
			// $scope.debugFunction("init", arguments);

			$scope.parentModal = parentModal;
		}

		$scope.openModal = function(mode) {
			$scope.debugFunction("openModal", arguments);

			if (mode==="_view") {
				mode = "";
			}

			elem = getFormSelector(
				$scope.pageId,
				$scope.schemaNameJS,
				$scope.tableName,
				mode
			);

			dialogSettings = {
				width: "auto",
				height: "auto",
				title: $scope.modalTitle,
				resizable: false,
				draggable: true,
				modal: true,
				dialogClass: "wpdadataforms-dialog"
			};
			if (mode==="_form" && $scope.parentModal!=="") {
				parent = jQuery(
					getFormSelector(
						$scope.pageId,
						$scope.schemaNameJS,
						$scope.parentModal
					)
				);
			}

			jQuery(elem).dialog(dialogSettings);
		};

		$scope.closeModal = function() {
			$scope.debugFunction("closeModal", arguments);

			if (mode==="_view") {
				mode = "";
			}

			elem = getFormSelector(
				$scope.pageId,
				$scope.schemaNameJS,
				$scope.tableName,
				mode
			);

			$scope.success.show = false;
			$scope.error.show = false;

			jQuery(elem).dialog('close');
		};

		$scope.editDataFromView = function($parent) {
			$scope.debugFunction("editDataFromView", arguments);

			// Save parent scope to support two way binding
			$scope.parent = $parent;
			$scope.action = "update";

			// Copy selectedRow from view controller to form controller
			$scope.selectedRow = $parent.selectedRow;

			$scope.$apply();
			$scope.showControllerForm();
			$scope.openModal('_form');
		}

		$scope.editData = function(
			selectedRow,
			isChild = false
		){
			$scope.debugFunction("editData", arguments);

			$scope.parentDataTableRow = selectedRow; // Save row info (needed for n:m relationship insert)

			$scope.showControllerForm();
			$scope.getFormData($scope.getPrimaryKeyValuesDataTable(selectedRow), isChild);
		};

		$scope.showControllerForm = function() {
			if (
				$scope.wpdaDataFormsPageChildTables[$scope.tableName]!==undefined &&
				$scope.wpdaDataFormsPageChildTables[$scope.tableName].relation_nm!==undefined
			) {
				$scope.debugFunction("showControllerForm", arguments);

				// Show first tab for n:m relationship
				jQuery("#wpdadataforms-" + $scope.pageId + "_" + $scope.schemaNameJS + "_" + $scope.tableName + "-tab").tabs({active: 0});
				jQuery("#wpdadataforms-" + $scope.pageId + "_" + $scope.schemaNameJS + "_" + $scope.tableName + "-tab-0").show();
				jQuery("#wpdadataforms-" + $scope.pageId + "_" + $scope.schemaNameJS + "_" + $scope.tableName + "-tab-1").hide();
				wpdaDataFormsLovUnselectAll(); // Unselect all checkboxes
			}

			controllerName = getControllerName($scope.pageId, $scope.schemaNameJS, $scope.tableName, '_form');
			jQuery("#" + controllerName + " form").show();
		}

		$scope.hideControllerForm = function() {
			$scope.debugFunction("hideControllerForm", arguments);

			controllerName = getControllerName($scope.pageId, $scope.schemaNameJS, $scope.tableName, '_form');
			jQuery("#" + controllerName + " form").hide();
		}

		$scope.getFormData = function(wpdadataformsPrimaryKey, isChild, navigate = false) {
			$scope.debugFunction("getFormData", arguments);

			$http({
				method: "POST",
				url: wpdaDataFormsAjaxUrl + "?action=wpdadataforms_get_form_data",
				data: {
					wpdadataforms_wp_nonce: $scope.dataProjectPageTable._get_form_data,
					wpdadataforms_page_id: $scope.pageId,
					wpdadataforms_schema_name: $scope.schemaName,
					wpdadataforms_table_name: $scope.tableName,
					wpdadataforms_pk: wpdadataformsPrimaryKey,
					wpdadataforms_embedded: wpdaDataFormsIsEmbedded
				}
			}).then(
				function(data) {
					// Get selected row
					if (data.data.status==="ok") {
						$log.debug(data);

						$scope.selectedRow = data.data.rows[0];
						$scope.mysqlSetToArray();
						$scope.action = "update";

						for (var col in $scope.dataProjectPageTable.date_columns) {
							if (
								$scope.selectedRow[col] !== undefined &&
								$scope.selectedRow[col] !== null
							) {
								// Convert date values
								$scope.selectedRow[col] = new Date($scope.selectedRow[col]);
							}
						}

						if ($scope.dataProjectPageInfo.page_type==='parent/child' && !isChild) {
							// Get child rows
							$scope.getDetails();
						}

						if (navigate) {
							// Update child data
							childComponent = getControllerName(
								$scope.pageId,
								$scope.schemaNameJS,
								$scope.tableName,
								'_form'
							);
							// Copy selectedRow from view controller to form controller
							wpdaDataFormsAngular[childComponent].selectedRow = $scope.selectedRow;

							// Show data entry form
							$scope.openModal('_form');
						} else {
							// Show data entry form
							$scope.openModal($scope.mode);
						}

						// Check conditional lookups
						$scope.checkConditionalLookups($scope.selectedRow);

						// Check autocomplete lookups
						$scope.checkAutocompleteLookups($scope.selectedRow);
					} else {
						$log.error(data);

						$scope.alert("Internal error: " + data.data.message, "Error");
					}
				},
				function(data) {
					$log.error(data);

					$scope.alert("Internal error: invalid request (please get in touch with the plugin support team)", "Error");
				}
			);
		}

		$scope.checkConditionalLookups = function(selectedRow) {
			$scope.debugFunction("checkConditionalLookups", arguments);

			for (var lookup_column in $scope.dataProjectLookups) {
				lookup = $scope.dataProjectLookups[lookup_column];
				if (lookup.source_column_name.length>1) {
					// Update conditional lookup
					wpdadataforms_filter = {};
					for (i=1; i<lookup.source_column_name.length; i++) {
						// Get parent value(s) to set filter
						wpdadataforms_filter_value = selectedRow[lookup.source_column_name[i]];
						wpdadataforms_filter[lookup.source_column_name[i]] = wpdadataforms_filter_value;
					}
					$scope.conditionalLookups(lookup, lookup_column, wpdadataforms_filter);
				}
			}
		}

		$scope.checkAutocompleteLookups = function(selectedRow) {
			$scope.debugFunction("checkAutocompleteLookups", arguments);

			for (var columnName in $scope.dataProjectLookups) {
				// Add autocomplete item
				$scope.autocomplete[columnName] = '';
				$scope.autocompleteIndex[columnName] = -1;
				$scope.autocompleteHide[columnName] = true;
				$scope.autocompleteData[columnName] = [];

				if ($scope.dataProjectPageTable.lookups[columnName]!==undefined) {
					$http({
						method: "POST",
						url: wpdaDataFormsAjaxUrl + "?action=wpdadataforms_autocomplete_get",
						data: {
							wpdadataforms_wp_nonce: $scope.dataProjectPageTable._get_form_data,
							wpdadataforms_page_id: $scope.pageId,
							wpdadataforms_source_schema_name: $scope.schemaName,
							wpdadataforms_source_table_name: $scope.tableName,
							wpdadataforms_target_schema_name: $scope.dataProjectLookups[columnName].target_schema_name,
							wpdadataforms_target_table_name: $scope.dataProjectLookups[columnName].target_table_name,
							wpdadataforms_target_column_name: $scope.dataProjectLookups[columnName].target_column_name[0],
							wpdadataforms_lookup_column_name: $scope.dataProjectPageTable.lookups[columnName].lookup,
							wpdadataforms_lookup_column_value: $scope.selectedRow[columnName],
							wpdadataforms_embedded: wpdaDataFormsIsEmbedded
						}
					}).then(
						function (data) {
							$log.debug(data);

							if (data.data.status === "ok") {
								if (false === data.data.lookup) {
									$scope.autocomplete[columnName] = "No data found";
								} else {
									$scope.autocomplete[columnName] = data.data.lookup;
								}
							} else {
								$scope.errorMessage("Auto complete error: invalid response");
							}
						},
						function (data) {
							$log.error(data);

							$scope.errorMessage("Auto complete error: service not available");
						}
					);
				}
			}
		}

		$scope.getNmRelation = function(selectedRow = null) {
			$scope.debugFunction("getNmRelation", arguments);

			if (
				$scope.wpdaDataFormsPageChildTables[$scope.tableName]===undefined ||
				$scope.wpdaDataFormsPageChildTables[$scope.tableName]['relation_nm']===undefined
			) {
				return null;
			}

			relnm = null;

			// Process many to many relationship
			relationship = $scope.wpdaDataFormsPageChildTables[$scope.tableName];

			relnm = {};

			// Add N table and column names
			relnm.relationship_table = relationship.child_table.target_table_name;
			relnm.relationship_column = relationship.child_table.target_column_name;

			// Add column value(s)
			relnm.relationship_value = [];
			relationship_parent_controller = wpdaDataFormsAngular[
				getControllerName(
					$scope.pageId,
					$scope.schemaNameJS,
					$scope.parentModal,
					'_view'
				)
			];

			for (var i=0; i<relationship.child_table.source_column_name.length; i++) {
				relnm.relationship_value.push(
					relationship_parent_controller.selectedRow[relationship.child_table.source_column_name[i]]
				);
			}

			// Add M table and column names
			relnm.relationship_base_column = relationship.relation_nm.child_table_select;

			// Add column value(s) - taken from DataTable (not yet available in Angular scope)
			relnm.relationship_base_value = [];
			if (selectedRow==null) {
				// Get column values from parent data entry form (values are already available in selectedRow)
				rowData = [];
				for (i=0; i<relnm.relationship_base_column.length; i++) {
					if ($scope.selectedRow[relnm.relationship_base_column[i]]!==undefined) {
						//rowData.push($scope.selectedRow[relnm.relationship_base_column[i]]);
						rowData.push("auto_increment");
					} else {
						tableColumns = $scope.dataProjectPage[$scope.schemaName][$scope.tableName].columns;
						if ($scope.isAutoIncrement($scope.selectedRow[relnm.relationship_base_column[i]])) {
							rowData.push("auto_increment");
						}
					}
				}

				for (var i=0; i<relationship.relation_nm.parent_key.length; i++) {
					relnm.relationship_base_value.push(
						rowData[i]
					);
				}
			} else {
				// Get calumn values from DataTable
				rowData = $scope.getCurrentRowFromDataTable(selectedRow);

				for (var i=0; i<relationship.relation_nm.parent_key.length; i++) {
					for (var j=0; j<$scope.dataProjectPage[$scope.schemaName][$scope.tableName]['table'].length; j++) {
						if (
							$scope.dataProjectPage[$scope.schemaName][$scope.tableName]['table'][j]['column_name']===
							relationship.relation_nm.parent_key[i]
						) {
							relnm.relationship_base_value.push(
								rowData[j]
							);
						}
					}
				}
			}

			return relnm;
		}

		$scope.isAutoIncrement = function(columnName) {
			$scope.debugFunction("isAutoIncrement", arguments);

			tableColumns = $scope.dataProjectPage[$scope.schemaName][$scope.tableName].columns;
			for (i=0; i<tableColumns.length; i++) {
				if (
					tableColumns[i].column_name===columnName &&
					tableColumns[i].extra==="auto_increment"
				) {
					return true;
				}
			}
			return false;
		}

		$scope.getCurrentRowFromDataTable = function(selectedRow) {
			$scope.debugFunction("getCurrentRowFromDataTable", arguments);

			tableSelector = getTableSelector(
				$scope.pageId,
				$scope.schemaNameJS,
				$scope.tableName
			);

			table = jQuery(tableSelector).DataTable();
			tr = jQuery(selectedRow).closest("tr");
			if (tr.hasClass("wpda-child")) {
				// Handle responsive mode
				tr = jQuery(selectedRow).closest("tr.child").prev("tr");
			}
			data = table.row(tr).data();

			return data;
		}

		$scope.insertData = function() {
			$scope.debugFunction("insertData", arguments);

			invalidColumnNames = $scope.validateRow();
			if (invalidColumnNames.length===1) {
				$scope.errorMessage("Validation failed for column " + invalidColumnNames[0]);
				return;
			} else if (invalidColumnNames.length>1) {
				invalidColumnStart = invalidColumnNames.slice(0,invalidColumnNames.length-1).join(", ")
				$scope.errorMessage("Validation failed for columns " + invalidColumnStart + " and " + invalidColumnNames[invalidColumnNames.length-1]);
				return;
			}

			$scope.preInsertTrigger();

			// For many to many relationships insert needs to add record to relationship table as well
			relnm = $scope.getNmRelation();

			var currentRow = Object.assign({}, $scope.selectedRow);
			var autoIncrementColumn = null;
			for (var col in currentRow) {
				if ($scope.isAutoIncrement(col)) {
					autoIncrementColumn = col;
				}
			}
			if (autoIncrementColumn!==null) {
				delete currentRow[autoIncrementColumn]; // Remove auto increment column from row
			}
			$scope.arrayToMysqlSet(currentRow);

			$http({
				method: "POST",
				url: wpdaDataFormsAjaxUrl + "?action=wpdadataforms_insert_form_data",
				data: {
					wpdadataforms_wp_nonce: $scope.dataProjectPageTable._insert_form_data,
					wpdadataforms_page_id: $scope.pageId,
					wpdadataforms_schema_name: $scope.schemaName,
					wpdadataforms_table_name: $scope.tableName,
					wpdadataforms_values: currentRow,
					wpdadataforms_relnm: relnm,
					wpdadataforms_embedded: wpdaDataFormsIsEmbedded
				}
			}).then(
				function(data) {
					if (data.data.status==="ok") {
						$log.debug(data);

						$scope.action = "update"; // change status to allow updates
						if (data.data.insert_id>0) {
							// Apply value to auto increment column
							for (key in $scope.dataProjectPageTable.primary_key) {
								$scope.selectedRow[key] = data.data.insert_id;
							}
						}
						for (key in $scope.dataProjectPageTable.primary_key) {
							$scope.updateLookupCache(key); // Update lookup cache
						}
						$scope.updateDataTable(); // Update DataTable table
						$scope.successMessage(data.data.message);

						$scope.postInsertTrigger();
					} else {
						$log.error(data);

						$scope.errorMessage(data.data.message);
					}
				},
				function(data) {
					$log.error(data);

					$scope.errorMessage("Invalid request");
				}
			);
		}

		$scope.updateLookupCache = function(columnName) {
			$scope.debugFunction("updateLookupCache", arguments);

			for (var lookupPageId in wpdaDataFormsProjectLookupTables) {
				for (var lookupTableName in wpdaDataFormsProjectLookupTables[lookupPageId]) {
					for (var lookupColumnName in wpdaDataFormsProjectLookupTables[lookupPageId][lookupTableName]) {
						var lookupColumn = wpdaDataFormsProjectLookupTables[lookupPageId][lookupTableName][lookupColumnName];
						if (lookupColumn.source_column_name.length===1) {
							if (
								lookupColumn.target_table_name === $scope.tableName &&
								lookupColumn.source_column_name[0] === columnName
							) {
								lookupControllerView = wpdaDataFormsAngular[getControllerName(lookupPageId, $scope.schemaNameJS, lookupTableName, "_view")];
								if (lookupControllerView !== undefined) {
									lookupControllerView.getLookup(columnName, true);
								}
								lookupControllerForm = wpdaDataFormsAngular[getControllerName(lookupPageId, $scope.schemaNameJS, lookupTableName, "_form")];
								if (lookupControllerForm !== undefined) {
									lookupControllerForm.getLookup(columnName, true);
								}
							}
						}
					}
				}
			}
		}

		$scope.updateData = function() {
			$scope.debugFunction("updateData", arguments);

			invalidColumnNames = $scope.validateRow();
			if (invalidColumnNames.length===1) {
				$scope.errorMessage("Validation failed for column " + invalidColumnNames[0]);
				return;
			} else if (invalidColumnNames.length>1) {
				invalidColumnStart = invalidColumnNames.slice(0,invalidColumnNames.length-1).join(", ")
				$scope.errorMessage("Validation failed for columns " + invalidColumnStart + " and " + invalidColumnNames[invalidColumnNames.length-1]);
				return;
			}

			$scope.preUpdateTrigger();

			var currentRow = Object.assign({}, $scope.selectedRow);
			$scope.arrayToMysqlSet(currentRow);

			var keyValues = {};
			for (var key in $scope.dataProjectPageTable.primary_key) {
				keyValues[key] = currentRow[key];
			}

			var colValues = {};
			for (var col in $scope.dataProjectPageTable.columns) {
				if (!$scope.isKeyColumn($scope.dataProjectPageTable.columns[col]['column_name'])) {
					colValues[$scope.dataProjectPageTable.columns[col]['column_name']] = currentRow[$scope.dataProjectPageTable.columns[col]['column_name']];
				}
			}

			$http({
				method: "POST",
				url: wpdaDataFormsAjaxUrl + "?action=wpdadataforms_update_form_data",
				data: {
					wpdadataforms_wp_nonce: $scope.dataProjectPageTable._update_form_data,
					wpdadataforms_page_id: $scope.pageId,
					wpdadataforms_schema_name: $scope.schemaName,
					wpdadataforms_table_name: $scope.tableName,
					wpdadataforms_pk: keyValues,
					wpdadataforms_values: colValues,
					wpdadataforms_embedded: wpdaDataFormsIsEmbedded
				}
			}).then(
				function(data) {
					if (data.data.status==="ok") {
						$log.debug(data);

						$scope.updateDataTable();
						$scope.successMessage(data.data.message);
						$scope.postUpdateTrigger();
					} else {
						$log.error(data);

						$scope.errorMessage(data.data.message);
					}
				},
				function(data) {
					$log.error(data);

					$scope.errorMessage("Invalid request");
				}
			);
		}

		$scope.validateRow = function() {
			invalidColumnNames = [];

			for (key in $scope.selectedRow) {
				if ($scope.selectedRow[key]===undefined) {
					for (col in $scope.dataProjectPageTable.form) {
						if ($scope.dataProjectPageTable.form[col]["column_name"]===key) {
							invalidColumnNames.push($scope.dataProjectPageTable.form[col]["label"].toLowerCase());
						}
					}
				}
			}

			return invalidColumnNames;
		}

		$scope.navigate = function(next = true) {
			$scope.debugFunction("navigate", arguments);

			tableSelector = getTableSelector(
				$scope.pageId,
				$scope.schemaNameJS,
				$scope.tableName
			);

			table = jQuery(tableSelector).DataTable();
			if ($scope.selectedRowIndex===0 && !next) {
				// Het last row of previous page
				data = table.row($scope.selectedRowIndex).data();
			} else if ($scope.selectedRowIndex===table.rows().count()-1 && next) {
				// Get first row of next page
				data = table.row($scope.selectedRowIndex).data();
			} else {
				// Get previous row
				if (next) {
					data = table.row(++$scope.selectedRowIndex).data();
				} else {
					data = table.row(--$scope.selectedRowIndex).data();
				}
			}

			// Fetch row data
			$scope.getFormData($scope.getPrimaryKeyValues(data), false, true);
		}

		$scope.prevRow = function() {
			$scope.debugFunction("prevRow", arguments);

			if ($scope.parent===null) {
				$scope.navigate(false);
			} else {
				$scope.parent.navigate(false);
			}
		}

		$scope.nextRow = function() {
			$scope.debugFunction("nextRow", arguments);

			if ($scope.parent===null) {
				$scope.navigate();
			} else {
				$scope.parent.navigate();
			}
		}

		$scope.formItems = function() {
			// $scope.debugFunction("formItems", arguments);

			var items = [];
			$scope.dataProjectPageTable.form.forEach(function(table) {
				addItem = true;
				for (var i=0; i<$scope.dataProjectPageTable.form_hidden.length; i++) {
					if ($scope.dataProjectPageTable.form_hidden[i]===table.column_name) {
						addItem = false;
					}
				}
				if (addItem) {
					items.push(table);
				}
			});
			return items;
		}

		$scope.isReadOnly = function(columnName, action, extra) {
			// $scope.debugFunction("isReadOnly", arguments);
			if (extra==="auto_increment") {
				return true;
			}

			if ($scope.dataProjectPageInfo.mode==="view") {
				return true;
			}

			if (action==="update") {
				let readOnly = false;
				$scope.dataProjectPageTable.form.forEach(function (table) {
					if (table['column_name']===columnName && table.readonly===true) {
						readOnly = true;
					}
				});
				if (readOnly) {
					return true;
				}

				return $scope.isKeyColumn(columnName);
			}

			return false;
		}

		$scope.isKeyColumn = function(columnName) {
			// $scope.debugFunction("isKeyColumn", arguments);

			for (var key in $scope.dataProjectPageTable.primary_key) {
				if (key===columnName) {
					return true;
				}
			}

			return false;
		}

		$scope.successMessage = function(message) {
			$scope.debugFunction("successMessage", arguments);

			$scope.success.show = true;
			$scope.success.message = message;

			$scope.error.show = false;
		}

		$scope.errorMessage = function(message) {
			$scope.debugFunction("errorMessage", arguments);

			$scope.error.show = true;
			$scope.error.message = message;

			$scope.success.show = false;
		}

		$scope.submitForm = function() {
			$scope.debugFunction("submitForm", arguments);

			if ($scope.action==="update") {
				$scope.updateData();
			} else if ($scope.action==="insert") {
				$scope.insertData();
			}
		}

		$scope.getDetails = function() {
			$scope.debugFunction("getDetails", arguments);

			for (var schemaName in $scope.dataProjectPage) {
				for (var tableName in $scope.dataProjectPage[schemaName]) {
					if (schemaName!==$scope.schemaName || tableName!==$scope.tableName) {
						wpdadataforms_table(
							$scope.pageId,
							schemaName,
							tableName,
							true,
							$scope.selectedRow
						);
					}
				}
			}
		}

		$scope.getPageViewController = function() {
			$scope.debugFunction("getPageViewController", arguments);

			baseTable = $scope.parentModal + "_view";

			controllerName = getControllerName(
				$scope.pageId,
				$scope.schemaNameJS,
				baseTable
			);

			return wpdaDataFormsAngular[controllerName];
		}

		$scope.addData = function(isChild = false) {
			$scope.debugFunction("addData", arguments);

			parentKeys = {};
			childKeys = {};

			if (isChild) {
				// Get parent values
				$scope.parent = $scope.getPageViewController();

				if ($scope.wpdaDataFormsPageChildTables[$scope.tableName]!==undefined) {
					// Get parent keys and child keys
					if ($scope.wpdaDataFormsPageChildTables[$scope.tableName]['relation_1n']!==undefined) {
						parentKeys = $scope.wpdaDataFormsPageChildTables[tableName]['relation_1n']['parent_key'];
						childKeys = $scope.wpdaDataFormsPageChildTables[$scope.tableName]['relation_1n']['child_key'];
					}
				}
			}

			$scope.selectedRow = {};
			$scope.action = "insert";

			for (key in $scope.dataProjectPageTable.form) {
				$scope.selectedRow[$scope.dataProjectPageTable.form[key]["column_name"]] = null;
				// Add default value if available
				if ($scope.dataProjectPageTable.form[key]["column_default"]!==null) {
					columnDefault = $scope.dataProjectPageTable.form[key]["column_default"];
					switch($scope.dataProjectPageTable.form[key]["ng_data_type"]) {
						case "number":
							$scope.selectedRow[$scope.dataProjectPageTable.form[key]["column_name"]] = Number(columnDefault);
							break;
						case "date":
							// TODO
							break;
						case "time":
							// TODO
							break;
						default:
							if ($scope.dataProjectPageTable.form[key]["data_type"]==="set") {
								// Default value needs to be in array!
								if (columnDefault!=="NULL") {
									$scope.selectedRow[$scope.dataProjectPageTable.form[key]["column_name"]] = [columnDefault];
								}
							} else {
								// MariaDB stores "NULL" as default instead of null
								if (columnDefault==="NULL" || columnDefault==="null") {
									columnDefault = null;
								}
								$scope.selectedRow[$scope.dataProjectPageTable.form[key]["column_name"]] = columnDefault;
							}
					}
				}
				// ???
				// console.log($scope.getHyperlinkId($scope.dataProjectPageTable.form[key]["column_name"]));
			}

			for (key in $scope.dataProjectPageTable.form_hidden) {
				$scope.selectedRow[$scope.dataProjectPageTable.form_hidden[key]] = null;
			}

			if (isChild) {
				for (key in $scope.selectedRow) {
					for (childKey in childKeys) {
						if (
							$scope.selectedRow[childKeys[childKey]]!==undefined &&
							$scope.parent!==null &&
							$scope.parent!==undefined
						) {
							$scope.selectedRow[childKeys[childKey]] = $scope.parent.selectedRow[parentKeys[childKey]];
						}
					}
				}
				// Check conditional lookups
				$scope.checkConditionalLookups($scope.parent.selectedRow);
			} else {
				// Check conditional lookups
				$scope.checkConditionalLookups($scope.selectedRow);
			}

			$scope.$apply();
			$scope.openModal("_form");
		}

		$scope.deleteData = function(selectedRow, isChild) {
			if (
				$scope.wpdaDataFormsPageChildTables[$scope.tableName]!==undefined &&
				$scope.wpdaDataFormsPageChildTables[$scope.tableName]['relation_nm']!==undefined
			) {
				$scope.debugFunction("deleteData", arguments);

				jQuery.when(
					$scope.confirm(
						"Are you sure you want to remove this relationship? This action cannot be undone!",
						"Are you sure?"
					)
				).then(
					function() {
						$scope.preDeleteTrigger();

						// Just delete the (many to many) relationship
						relnm = $scope.getNmRelation(selectedRow);

						$http({
							method: "POST",
							url: wpdaDataFormsAjaxUrl + "?action=wpdadataforms_delrel_form_data",
							data: {
								wpdadataforms_wp_nonce: $scope.dataProjectPageTable._delete_form_data,
								wpdadataforms_page_id: $scope.pageId,
								wpdadataforms_schema_name: $scope.schemaName,
								wpdadataforms_table_name: $scope.tableName,
								wpdadataforms_relationship: relnm,
								wpdadataforms_embedded: wpdaDataFormsIsEmbedded
							}
						}).then(
							function (data) {
								$scope.updateDataTable();
								if (data.data.status === "ok") {
									$log.debug(data);

									for (key in $scope.dataProjectPageTable.primary_key) {
										$scope.updateLookupCache(key); // Update lookup cache
									}
									$scope.alert(data.data.message);
									$scope.postDeleteTrigger();
								} else {
									$log.error(data);

									$scope.alert(data.data.message, "Error");
								}
							},
							function (data) {
								$log.error(data);

								$scope.errorMessage("Invalid request");
							}
						);
					}
				);
			} else {
				if (isChild) {
					title = "Are you sure you want to remove this row? This action cannot be undone!";
				} else {
					title = "Are you sure you want to remove this row and its relationships? This action cannot be undone!";
				}

				jQuery.when(
					$scope.confirm(
						title,
						"Are you sure?"
					)
				).then(
					function() {
						$scope.preDeleteTrigger();

						wpdadataformsPrimaryKey = $scope.getPrimaryKeyValuesDataTable(selectedRow);

						// Reset row values for prev|next actions
						$scope.selectedRowIndex = -1;

						$http({
							method: "POST",
							url: wpdaDataFormsAjaxUrl + "?action=wpdadataforms_delete_form_data",
							data: {
								wpdadataforms_wp_nonce: $scope.dataProjectPageTable._delete_form_data,
								wpdadataforms_page_id: $scope.pageId,
								wpdadataforms_schema_name: $scope.schemaName,
								wpdadataforms_table_name: $scope.tableName,
								wpdadataforms_pk: wpdadataformsPrimaryKey,
								wpdadataforms_is_child: isChild,
								wpdadataforms_set_name: $scope.dataProjectPageInfo.page_setname,
								wpdadataforms_embedded: wpdaDataFormsIsEmbedded
							}
						}).then(
							function (data) {
								$scope.updateDataTable();
								if (data.data.status==="ok") {
									$log.debug(data);

									for (key in $scope.dataProjectPageTable.primary_key) {
										$scope.updateLookupCache(key); // Update lookup cache
									}
									$scope.postDeleteTrigger();
								} else {
									$log.error(data);

									$scope.alert(data.data.message, "Error");
								}
							},
							function (data) {
								$log.error(data);

								$scope.errorMessage("Invalid request");
							}
						);
					}
				);
			}
		};

		$scope.getPrimaryKeyValuesSelectedRow = function($thisScope) {
			$scope.debugFunction("getPrimaryKeyValuesSelectedRow", arguments);

			var primaryKeys = {};
			for (key in $thisScope.dataProjectPageTable.primary_key) {
				primaryKeys[key] = $thisScope.selectedRow[key];
			}
			return primaryKeys;
		}

		$scope.getPrimaryKeyValuesDataTable = function(selectedRow) {
			$scope.debugFunction("getPrimaryKeyValuesDataTable", arguments);

			tableSelector = getTableSelector(
				$scope.pageId,
				$scope.schemaNameJS,
				$scope.tableName
			);

			table = jQuery(tableSelector).DataTable();
			tr = jQuery(selectedRow).closest("tr");
			if (tr.hasClass("wpda-child")) {
				// Handle responsive mode
				tr = jQuery(selectedRow).closest("tr.child").prev("tr");
			}
			data = table.row(tr).data();

			// Save settings for prev|next actions
			$scope.selectedRowIndex = table.row(tr).index();

			return $scope.getPrimaryKeyValues(data);
		}

		$scope.getPrimaryKeyValues = function(data) {
			$scope.debugFunction("getPrimaryKeyValues", arguments);

			// Add key as key/value pairs to prevent sql injection
			primaryPos = '';
			primaryVal = '';
			wpdadataformsPrimaryKey = {};
			for (pk in $scope.dataProjectPageTable.primary_key) {
				primaryPos = $scope.dataProjectPageTable.primary_key[pk];
				primaryVal = data[primaryPos];
				wpdadataformsPrimaryKey[$scope.dataProjectPageTable.table[primaryPos].column_name] = primaryVal;
			}

			return wpdadataformsPrimaryKey;
		}

		$scope.updateDataTable = function() {
			$scope.debugFunction("updateDataTable", arguments);

			datatableSelector = getTableSelector($scope.pageId, $scope.schemaNameJS, $scope.tableName);
			datatable = jQuery(datatableSelector).DataTable();
			datatable.ajax.reload(null, false);
		}

		$scope.addExistingRows = function(elemId) {
			$scope.debugFunction("addExistingRows", arguments);

			if ($scope.wpdaDataFormsPageChildTables[$scope.tableName]!==undefined) {
				relationshipNM = $scope.wpdaDataFormsPageChildTables[$scope.tableName];
				if (relationshipNM.table_name!==undefined) {
					if ($scope.dataProjectPage[$scope.schemaName][relationshipNM.table_name]) {
						wpdadataforms_table(
							$scope.pageId,
							$scope.schemaName,
							$scope.tableName,
							false,
							null,
							true
						);
					}
				}
			}
		}

		$scope.addSelected = function() {
			$scope.debugFunction("addSelected", arguments);

			errors = false;

			if (
				$scope.wpdaDataFormsPageChildTables[$scope.tableName]!==undefined &&
				$scope.wpdaDataFormsPageChildTables[$scope.tableName]['relation_nm']!==undefined
			) {
				relationshipNM = $scope.wpdaDataFormsPageChildTables[$scope.tableName];

				parentTable = relationshipNM.table_name;
				sourceColumns = relationshipNM.relation_nm.child_table_where;
				sourceValues = [];
				for (i=0; i<sourceColumns.length; i++) {
					sourceValues.push($scope.parent.selectedRow[sourceColumns[i]]);
				}

				childTable = relationshipNM.relation_nm.child_table;
				childColumns = relationshipNM.relation_nm.child_table_select;
				childColumnPositions = [];
				for (i=0; i<childColumns.length; i++) {
					columnIndex = $scope.getDataTableColumnPosition(childColumns[i]);
					if (columnIndex!==null) {
						childColumnPositions.push(columnIndex);
					} else {
						errors = true;
					}
				}

				if (!errors) {
					tableSelector = getLovSelector(
						$scope.pageId,
						$scope.schemaNameJS,
						$scope.tableName
					);
					table = jQuery(tableSelector).DataTable();

					columnValues = [];
					jQuery(".wpdaDataFormsLovCheckbox:checked").closest("tr").each(function (key, val) {
						rowIndex = jQuery(val).index();
						data = table.row(rowIndex).data();

						row = {};
						for (i=0; i<sourceColumns.length; i++) {
							row[sourceColumns[i]] = sourceValues[i];
						}
						for (i=0; i<childColumnPositions.length; i++) {
							row[childColumns[i]] = data[childColumnPositions[i]];
						}
						columnValues.push(row);
					});

					$http({
						method: "POST",
						url: wpdaDataFormsAjaxUrl + "?action=wpdadataforms_insert_form_data_nm",
						data: {
							wpdadataforms_wp_nonce: $scope.dataProjectPageTable._insert_form_data,
							wpdadataforms_page_id: $scope.pageId,
							wpdadataforms_schema_name: $scope.schemaName,
							wpdadataforms_parent_table_name: parentTable,
							wpdadataforms_child_table_name: childTable,
							wpdadataforms_values: columnValues,
							wpdadataforms_embedded: wpdaDataFormsIsEmbedded
						}
					}).then(
						function(data) {
							if (data.data.status==="ok") {
								$log.debug(data);

								$scope.updateDataTable();
								$scope.closeModal();
								$scope.alert(data.data.message);
							} else {
								$log.error(data);

								$scope.updateDataTable();
								$scope.closeModal();
								$scope.alert(data.data.message, "Error");
							}
						},
						function(data) {
							$log.error(data);

							$scope.closeModal();
							$scope.alert("Invalid request", "Error");
						}
					);
				}
			} else {
				$scope.alert("Error inserting existing relationships", "Error");
			}
		}

		$scope.getDataTableColumnPosition = function(columnName) {
			$scope.debugFunction("getDataTableColumnPosition", arguments);

			dataTableStructure = $scope.dataProjectPage[$scope.schemaName][$scope.tableName].table;
			for (i=0; i<dataTableStructure.length; i++) {
				if (dataTableStructure[i].column_name===columnName) {
					return i;
				}
			}
			return null;
		}

		$scope.hasChildTables = function() {
			$scope.debugFunction("hasChildTables", arguments);

			return Object.keys($scope.wpdaDataFormsPageChildTables).length > 0;
		}

		$scope.mysqlSetToArray = function() {
			$scope.debugFunction("mysqlSetToArray", arguments);

			$scope.dataProjectPageTable.form.forEach(function(table) {
				if (table.data_type==="set") {
					setValue = $scope.selectedRow[table['column_name']];
					$scope.selectedRow[table['column_name']] = setValue.split(",");
				}
			});
		}

		$scope.arrayToMysqlSet = function(currentRow) {
			$scope.debugFunction("arrayToMysqlSet", arguments);

			$scope.dataProjectPageTable.form.forEach(function(table) {
				if (table.data_type==="set") {
					setValue = currentRow[table['column_name']];
					currentRow[table['column_name']] = setValue.join();
				}
			});
		}

		$scope.getEnumValues = function(enumDef) {
			// $scope.debugFunction("getEnumValues", arguments);

			return enumDef.replace("enum(", "").replace(")", "").replaceAll("'", "").split(",");
		}

		$scope.getSetValues = function(setDef) {
			$scope.debugFunction("getSetValues", arguments);

			return setDef.replace("set(", "").replace(")", "").replaceAll("'", "").split(",");
		}

		$scope.autocompleteEnter = function(columnName) {
			$scope.debugFunction("autocompleteEnter", arguments);

			if ($scope.autocompleteHide[columnName]) {
				// Submit form

				return true;
			} else {
				// Select value from list
				if ($scope.autocompleteIndex[columnName]<0) {
					$scope.autocompleteIndex[columnName] = 0;
				}

				$scope.autocomplete[columnName] = $scope.autocompleteData[columnName][$scope.autocompleteIndex[columnName]].label;
				$scope.selectedRow[columnName] = $scope.autocompleteData[columnName][$scope.autocompleteIndex[columnName]].lookup;
				$scope.autocompleteHide[columnName] = true;

				return false;
			}
		}

		$scope.autocompleteMouseOver = function(elem) {
			$scope.debugFunction("autocompleteMouseOver", arguments);

			jQuery(elem.target).parent().find("li").removeClass('wpdadataforms-list-group-item-hover')
			jQuery(elem.target).addClass('wpdadataforms-list-group-item-hover')

			// jQuery UI
			jQuery(elem.target).parent().find("li").removeClass("ui-state-active");
			jQuery(elem.target).addClass("ui-state-active");
		}

		$scope.autocompleteUpdate = function(e, columnName) {
			$scope.debugFunction("autocompleteUpdate", arguments);

			switch(e.keyCode) {
				case 13: // Enter
				case 35: // End
				case 36: // Home
				case 37: // Left
				case 39: // Right
					break;
				case 38:
					// Up
					$scope.autocompleteIndex[columnName]--;
					if ($scope.autocompleteIndex[columnName]<0) {
						$scope.autocompleteIndex[columnName] = 0;
					}

					$scope.hoverAutocompleteListItem(e, columnName);
					break;
				case 40:
					// Down
					$scope.autocompleteIndex[columnName]++;
					if ($scope.autocompleteIndex[columnName]>$scope.autocompleteData[columnName].length-1) {
						$scope.autocompleteIndex[columnName] = $scope.autocompleteData[columnName].length-1;
					}

					$scope.hoverAutocompleteListItem(e, columnName);
					break;
				default:
					if ($scope.autocomplete[columnName].trim().length>0) {
						$http({
							method: "POST",
							url: wpdaDataFormsAjaxUrl + "?action=wpdadataforms_autocomplete",
							data: {
								wpdadataforms_wp_nonce: $scope.dataProjectPageTable._get_form_data,
								wpdadataforms_page_id: $scope.pageId,
								wpdadataforms_source_schema_name: $scope.schemaName,
								wpdadataforms_source_table_name: $scope.tableName,
								wpdadataforms_target_schema_name: $scope.dataProjectLookups[columnName].target_schema_name,
								wpdadataforms_target_table_name: $scope.dataProjectLookups[columnName].target_table_name,
								wpdadataforms_target_column_name: $scope.dataProjectLookups[columnName].target_column_name[0],
								wpdadataforms_lookup_column_name: $scope.dataProjectPageTable.lookups[columnName].lookup,
								wpdadataforms_lookup_column_value: $scope.autocomplete[columnName],
								wpdadataforms_embedded: wpdaDataFormsIsEmbedded
							}
						}).then(
							function (data) {
								$log.debug(data);

								if (data.data.status === "ok") {
									output = [];
									angular.forEach(data.data.rows, function (rows) {
										output.push(rows);
									});
									$scope.autocompleteIndex[columnName] = -1;
									$scope.autocompleteData[columnName] = output;
									$scope.autocompleteHide[columnName] = false;
								} else {
									$scope.errorMessage("Auto complete error: invalid response");
									$scope.autocompleteIndex[columnName] = -1;
									$scope.autocompleteData[columnName] = [];
									$scope.autocompleteHide[columnName] = true;
								}
							},
							function (data) {
								$log.error(data);

								$scope.errorMessage("Auto complete error: service not available");
								$scope.autocompleteIndex[columnName] = -1;
								$scope.autocompleteData[columnName] = [];
								$scope.autocompleteHide[columnName] = true;
							}
						);
					} else {
						$scope.autocompleteIndex[columnName] = -1;
						$scope.autocompleteHide[columnName] = true;
					}
			}
		}

		$scope.hoverAutocompleteListItem = function(e, columnName) {
			jQuery("#" + columnName + "_list li").removeClass("wpdadataforms-list-group-item-hover");
			jQuery("#" + columnName + "_list li").eq($scope.autocompleteIndex[columnName]).addClass("wpdadataforms-list-group-item-hover");

			// jQuery UI
			jQuery("#" + columnName + "_list li").removeClass("ui-state-active");
			jQuery("#" + columnName + "_list li").eq($scope.autocompleteIndex[columnName]).addClass("ui-state-active");

			// Update lookup values
			columnValue = $scope.autocompleteData[columnName][$scope.autocompleteIndex[columnName]];
			$scope.selectedRow[columnName] = columnValue.lookup;
			$scope.autocomplete[columnName] = columnValue.value;

			// Make sure the selected item if visible
			jQuery("#" + columnName + "_list li").eq($scope.autocompleteIndex[columnName])[0].scrollIntoView(false);
		}

		$scope.autocompleteSelect = function(columnName, columnValue) {
			$scope.debugFunction("autocompleteSelect", arguments);

			$scope.selectedRow[columnName] = columnValue.lookup;
			$scope.autocomplete[columnName] = columnValue.value;
			$scope.autocompleteIndex[columnName] = -1;
			$scope.autocompleteHide[columnName] = true;
		}

		// Lookup values are taken from cache. Conditional lookups are NOT handled here, as they do not exist on
		// a view form. View forms are only shown for child tables. A parent table cannot contain a conditional lookup.
		$scope.getLookupValue = function (key, columnName) {
			// $scope.debugFunction("getLookupValue", arguments);

			if ($scope.selectedRow[columnName]!==null && $scope.lookupData[columnName]!==undefined) {
				for (i=0; i<$scope.lookupData[columnName].length; i++) {
					if ($scope.lookupData[columnName][i]['lookup_value']==$scope.selectedRow[columnName]) {
						return $scope.lookupData[columnName][i]['lookup_label'];
					}
				}
				return "No data found";
			} else {
				return null;
			}
		}

		$scope.hasLookup = function(columnName) {
			// $scope.debugFunction("hasLookup", arguments);

			if ($scope.dataProjectLookups===undefined || $scope.dataProjectLookups[columnName]===undefined) {
				return false;
			} else {
				if ($scope.dataProjectLookups[columnName].source_column_name.length>1) {
					return "conditional";
				} else {
					return $scope.dataProjectLookups[columnName].relation_type;
				}
			}
		}

		$scope.conditionalLookupData = {};
		$scope.conditionalLookups = function(lookup, columnName, wpdadataforms_filter) {
			$scope.debugFunction("conditionalLookups", arguments);

			// Perform conditional lookup
			lookup_label_column = columnName;
			lookup_hide_key = 'off';
			if ($scope.dataProjectPageTable.lookups[columnName]) {
				lookup_label_column = $scope.dataProjectPageTable.lookups[columnName].lookup;
				hide_lookup_key = $scope.dataProjectPageTable.lookups[columnName].hide_lookup_key;
			}

			$http({
				method: "POST",
				url: wpdaDataFormsAjaxUrl + "?action=wpdadataforms_conditional_lookup",
				data: {
					wpdadataforms_wp_nonce: $scope.dataProjectPageTable._get_form_data,
					wpdadataforms_page_id: $scope.pageId,
					wpdadataforms_source_schema_name: $scope.schemaName,
					wpdadataforms_source_table_name: $scope.tableName,
					wpdadataforms_target_schema_name: lookup.target_schema_name,
					wpdadataforms_target_table_name: lookup.target_table_name,
					wpdadataforms_target_column_name: lookup.target_column_name[0],
					wpdadataforms_target_text_column: lookup_label_column,
					wpdadataforms_filter: wpdadataforms_filter,
					wpdadataforms_embedded: wpdaDataFormsIsEmbedded
				}
			}).then(
				function(data) {
					$log.debug(data);

					$scope.conditionalLookupData[columnName] = data.data.rows;
				},
				function(data) {
					$log.error(data);
				}
			);
		}

		$scope.getColumnPositionInTable = function(columnName) {
			tableColumns = wpdaDataFormsProjectPages[$scope.pageId][$scope.schemaName][$scope.tableName]['table'];
			if (tableColumns===undefined) {
				return -1;
			}

			for (i=0; i<tableColumns.length; i++) {
				if (tableColumns[i]['column_name']===columnName) {
					return i;
				}
			}

			return -1;
		}

		$scope.getColumnNgDataType = function(columnName) {
			for (key in $scope.dataProjectPageTable.form) {
				if ($scope.dataProjectPageTable.form[key]["column_name"]===columnName) {
					return $scope.dataProjectPageTable.form[key]["ng_data_type"];
				}
			}
		}

		$scope.lookupData = {};
		$scope.getLookup = function(columnName, updateCache = false) {
			//$scope.debugFunction("getLookup", arguments);

			lookup = $scope.dataProjectLookups[columnName];
			lookup_label_column = columnName;
			lookup_hide_key = 'off';
			if ($scope.dataProjectPageTable.lookups[columnName]) {
				lookup_label_column = $scope.dataProjectPageTable.lookups[columnName].lookup;
				hide_lookup_key = $scope.dataProjectPageTable.lookups[columnName].hide_lookup_key;
			}
			if (lookup.target_column_name.length>1) {
				// Conditional lookups cannot be cached
				return;
			}
			return $http({
				method: "POST",
				url: wpdaDataFormsAjaxUrl + "?action=wpdadataforms_lookup",
				data: {
					wpdadataforms_wp_nonce: $scope.dataProjectPageTable._get_form_data,
					wpdadataforms_page_id: $scope.pageId,
					wpdadataforms_source_schema_name: $scope.schemaName,
					wpdadataforms_source_table_name: $scope.tableName,
					wpdadataforms_target_schema_name: lookup.target_schema_name,
					wpdadataforms_target_table_name: lookup.target_table_name,
					wpdadataforms_target_column_name: lookup.target_column_name[0],
					wpdadataforms_target_text_column: lookup_label_column,
					wpdadataforms_embedded: wpdaDataFormsIsEmbedded
				}
			}).then(
				function(data) {
					$log.debug(data);

					$scope.lookupData[columnName] = data.data.rows;
					if (updateCache) {
						// Use data to update cached lookup values for DataTable
						lookupDataTable = {};
						for (var key in data.data.rows) {
							lookupDataTable[data.data.rows[key]["lookup_value"]] = data.data.rows[key]["lookup_label"];
						}
						if ($scope.getColumnPositionInTable(columnName)>-1) {
							wpdaDataFormsProjectPages[$scope.pageId][$scope.schemaName][$scope.tableName]["table_lookups"][columnIndex] = lookupDataTable;
						}
					}
				},
				function(data) {
					$log.error(data);
				}
			);
		}
		for (var lookup_column in $scope.dataProjectLookups) {
			if (lookup_column!==undefined) {
				if ($scope.dataProjectLookups[lookup_column]['relation_type']==='lookup') {
					if (wpdaDataFormsProjectPages[$scope.pageId][$scope.schemaName][$scope.tableName]["table_lookups"]!==undefined) {
						// Try to use lookup table data from cache
						lookupColumnPosition = $scope.getColumnPositionInTable(lookup_column);
						lookupColumn = wpdaDataFormsProjectPages[$scope.pageId][$scope.schemaName][$scope.tableName]['table'][lookupColumnPosition];
						if (
							wpdaDataFormsProjectPages[$scope.pageId][$scope.schemaName][$scope.tableName]["table_lookups"][lookupColumnPosition]!==undefined &&
							wpdaDataFormsProjectPages[$scope.pageId][$scope.schemaName][$scope.tableName]["table_lookups"][lookupColumnPosition]!==null
						) {
							lookupTable = wpdaDataFormsProjectPages[$scope.pageId][$scope.schemaName][$scope.tableName]["table_lookups"][lookupColumnPosition];
							lookupTableSorted = null;
							lookupDataTable = [];

							if (
								wpdaDataFormsProjectPages[$scope.pageId][$scope.schemaName][$scope.tableName]["table_lookups_sorted"][lookupColumnPosition]!==undefined &&
								wpdaDataFormsProjectPages[$scope.pageId][$scope.schemaName][$scope.tableName]["table_lookups_sorted"][lookupColumnPosition]!==null
							) {
								lookupTableSorted = wpdaDataFormsProjectPages[$scope.pageId][$scope.schemaName][$scope.tableName]["table_lookups_sorted"][lookupColumnPosition];
							}

							if (
								$scope.getColumnNgDataType(lookup_column)==="text" ||
								$scope.getColumnNgDataType(lookup_column)==="number"
							) {
								if (lookupTableSorted!==null) {
									for (var prop in lookupTableSorted) {
										lkp = {};
										if ($scope.getColumnNgDataType(lookup_column)==="text") {
											lkp['lookup_value'] = lookupTableSorted[prop].lookup_value;
										} else {
											lkp['lookup_value'] = Number(lookupTableSorted[prop].lookup_value);
										}
										lkp['lookup_label'] = lookupTableSorted[prop].lookup_label;
										lookupDataTable.push(lkp);
									}
								} else {
									for (var prop in lookupTable) {
										lkp = {};
										if ($scope.getColumnNgDataType(lookup_column)==="text") {
											lkp['lookup_value'] = prop;
										} else {
											lkp['lookup_value'] = Number(prop);
										}
										lkp['lookup_label'] = lookupTable[prop];
										lookupDataTable.push(lkp);
									}
								}

								// Use lookup table data from cache
								$scope.lookupData[lookup_column] = lookupDataTable;
							} else {
								// Perform request to get lookup table data
								$scope.getLookup(lookup_column);
							}
						}
					} else {
						// Perform request to get lookup table data
						$scope.getLookup(lookup_column);
					}
				}
			}
		}

		$scope.columnType = function(column) {
			// $scope.debugFunction("columnType", arguments);
			if (column['column_type']==='tinyint(1)') {
				return 'checkbox';
			}
			
			if (
				$scope.dataProjectPageMedia!==null &&
				$scope.dataProjectPageMedia[column['column_name']]!==undefined
			) {
				if (
					$scope.dataProjectPageMedia[column['column_name']]==="ImageURL" ||
					$scope.dataProjectPageMedia[column['column_name']]==="text"
				) {
					return "text";
				} else if (
					$scope.dataProjectPageMedia[column['column_name']]==="Hyperlink"
				) {
					return $scope.dataProjectPageMedia[column['column_name']].toLowerCase();
				}
				
				return "media";
			}

			if (column['column_type'].toLowerCase().includes("text")) {
				return 'textarea';
			} else {
				return 'text';
			}
		}

		$scope.columnMaxLength = function(columnName) {
			// $scope.debugFunction("columnMaxLength", arguments);

			for (i=0; i<$scope.dataProjectPageTable.form.length; i++) {
				if ($scope.dataProjectPageTable.form[i].column_name===columnName) {
					return $scope.dataProjectPageTable.form[i].character_maximum_length;
				}
			}

			return null;
		}

		$scope.getNumericConstraints = function(columnName) {
			// $scope.debugFunction("getNumericConstraints", arguments);

			numericConstraints = {
				min: null,
				max: null,
				step: null
			};

			for (i=0; i<$scope.dataProjectPageTable.form.length; i++) {
				if ($scope.dataProjectPageTable.form[i].column_name===columnName) {
					if ($scope.dataProjectPageTable.form[i].numeric_precision!==null) {
						if ($scope.dataProjectPageTable.form[i].numeric_scale == 0) {
							numericConstraints.min = -Math.pow(10,parseInt($scope.dataProjectPageTable.form[i].numeric_precision))+1;
							numericConstraints.max = Math.pow(10,parseInt($scope.dataProjectPageTable.form[i].numeric_precision))-1;
							numericConstraints.step = 1;
						} else {
							numericConstraints.step = 1/Math.pow(10,parseInt($scope.dataProjectPageTable.form[i].numeric_scale));
							numericConstraints.min = -Math.pow(10,parseInt($scope.dataProjectPageTable.form[i].numeric_precision)-parseInt($scope.dataProjectPageTable.form[i].numeric_scale))+numericConstraints.step;
							numericConstraints.max = Math.pow(10,parseInt($scope.dataProjectPageTable.form[i].numeric_precision)-parseInt($scope.dataProjectPageTable.form[i].numeric_scale))-numericConstraints.step;
						}
						return numericConstraints;
					} else {
						return numericConstraints;
					}
				}
			}

			return null;
		}

		$scope.columnMinValue = function(columnName) {
			// $scope.debugFunction("columnMinValue", arguments);

			numericConstraints = $scope.getNumericConstraints(columnName);
			return numericConstraints.min;
		}

		$scope.columnMaxValue = function(columnName) {
			// $scope.debugFunction("columnMaxValue", arguments);

			numericConstraints = $scope.getNumericConstraints(columnName);
			return numericConstraints.max;
		}

		$scope.getStep = function(columnName) {
			// $scope.debugFunction("getStep", arguments);

			numericConstraints = $scope.getNumericConstraints(columnName);
			return numericConstraints.step;
		}

		$scope.confirm = function(message, title = 'Confirm') {
			$scope.debugFunction("confirm", arguments);

			var dfd = new jQuery.Deferred();
			jQuery("<div class='wpdadataforms-confirm ui-widget-content ui-corner-all'></div>").html(message).dialog({
				title: title,
				resizable: false,
				modal: true,
				dialogClass: "wpdadataforms-confirm-dialog",
				buttons: {
					"OK": {
						click: function() {
							jQuery(this).dialog("close");
							dfd.resolve();
						},
						text: "OK",
						class: "wpdadataforms-confirm-button"
					},
					"Cancel": {
						click: function() {
							jQuery(this).dialog("close");
							dfd.reject();
						},
						text: "Cancel",
						class: "wpdadataforms-confirm-button"
					}
				}
			});

			if (typeof wpda_add_icons_to_dialog_buttons === "function") {
				// Add icons to back-end
				wpda_add_icons_to_dialog_buttons();
			}

			return dfd.promise();
		}

		$scope.alert = function(message, title = 'Info') {
			$scope.debugFunction("alert", arguments);

			jQuery("<div class='wpdadataforms-alert ui-widget-content ui-corner-all'></div>").html(message).dialog({
				title: title,
				resizable: false,
				modal: true,
				dialogClass: "wpdadataforms-alert-dialog",
				buttons: {
					"OK": {
						click: function() {
							jQuery(this).dialog("close");
						},
						text: "OK",
						class: "wpdadataforms-alert-button"
					}
				}
			});

			if (typeof wpda_add_icons_to_dialog_buttons === "function") {
				// Add icons to back-end
				wpda_add_icons_to_dialog_buttons();
			}
		}

		$scope.getHyperlinkId = function(columnName) {
			// $scope.debugFunction("getHyperlinkId", arguments);

			return $scope.pageId + "_" + $scope.tableName + "_" + columnName + "_hyperlink";
		}

		$scope.getHyperlinkLabel = function(columnName) {
			// $scope.debugFunction("getHyperlinkLabel", arguments);
			hyperlink_json = $scope.getHyperlink(columnName);
			if (hyperlink_json!==null && hyperlink_json.label!==undefined) {
				jQuery("#" + $scope.getHyperlinkId(columnName) + "_display").val(hyperlink_json.label);
			} else {
				jQuery("#" + $scope.getHyperlinkId(columnName) + "_display").val("");
			}
		}

		$scope.openHyperlinkPopup = function(columnName) {
			$scope.debugFunction("openHyperlinkPopup", arguments);

			hyperlinkColumnPrefix = $scope.getHyperlinkId(columnName);

			if ($scope.selectedRow[columnName]!==null && $scope.selectedRow[columnName]!=="") {
				hyperlink_json = $scope.getHyperlink(columnName);
				if (hyperlink_json!==null && hyperlink_json.label!==undefined) {
					jQuery("#" + hyperlinkColumnPrefix + "_label").val(hyperlink_json.label);
					jQuery("#" + hyperlinkColumnPrefix + "_url").val(hyperlink_json.url);
					jQuery("#" + hyperlinkColumnPrefix + "_target").prop("checked", true);
				} else {
					jQuery("#" + hyperlinkColumnPrefix + "_label").val("");
					jQuery("#" + hyperlinkColumnPrefix + "_url").val("");
					jQuery("#" + hyperlinkColumnPrefix + "_target").prop("checked", false);
				}
			} else {
				jQuery("#" + hyperlinkColumnPrefix + "_label").val("");
				jQuery("#" + hyperlinkColumnPrefix + "_url").val("");
				jQuery("#" + hyperlinkColumnPrefix + "_target").prop("checked", false);
			}

			dialogSettings = {
				width: "auto",
				height: "auto",
				title: "Edit hyperlink",
				resizable: true,
				draggable: true,
				modal: true
			};
			jQuery("#" + hyperlinkColumnPrefix + "_popup").dialog(dialogSettings);
			jQuery("#" + hyperlinkColumnPrefix + "_popup .wpdadataforms-button").not("ui-button").button();
		}

		$scope.getHyperlink = function(columnName) {
			// $scope.debugFunction("getHyperlink", arguments);
			if (
				!jQuery.isEmptyObject($scope.selectedRow) &&
				$scope.selectedRow[columnName]!==null && $scope.selectedRow[columnName]!==""
			) {
				try {
					return JSON.parse($scope.selectedRow[columnName]);
				} catch(e) {
					$log.error(e);
					return null;
				}
			}

			return null;
		}

		$scope.closeEditHyperlink = function($event) {
			$scope.debugFunction("closeEditHyperlink", arguments);

			jQuery($event.currentTarget).closest(".wpdadataforms-hyperlink-form").dialog("close");
		}

		$scope.saveEditHyperlink = function($event) {
			$scope.debugFunction("closeEditHyperlink", arguments);

			hyperlinkId = jQuery($event.currentTarget).data("hyperlinkId");
			columnName = jQuery($event.currentTarget).data("columnName");

			hyperlink = {};
			hyperlink.label = jQuery("#" + hyperlinkId + "_label").val();
			hyperlink.url = jQuery("#" + hyperlinkId + "_url").val();
			if (jQuery("#" + hyperlinkId + "_target").is(":checked")) {
				hyperlink.target = "_blank";
			} else {
				hyperlink.target = "";
			}

			jQuery("#" + hyperlinkId).val(JSON.stringify(hyperlink)); // Update visual editor
			$scope.selectedRow[columnName] = JSON.stringify(hyperlink); // Update model

			$scope.closeEditHyperlink($event);
		}

		$scope.preInsertTrigger = function() {
			$scope.debugFunction("preInsertTrigger", arguments);
		}

		$scope.postInsertTrigger = function() {
			$scope.debugFunction("postInsertTrigger", arguments);
		}

		$scope.preUpdateTrigger = function() {
			$scope.debugFunction("preUpdateTrigger", arguments);
		}

		$scope.postUpdateTrigger = function() {
			$scope.debugFunction("postUpdateTrigger", arguments);
		}

		$scope.preDeleteTrigger = function() {
			$scope.debugFunction("preDeleteTrigger", arguments);
		}

		$scope.postDeleteTrigger = function() {
			$scope.debugFunction("postDeleteTrigger", arguments);
		}

		$scope.debugFunction = function(functionName, args = null) {
			$log.debug("> WP Data Access - Data Forms - " + functionName);
			if (args!==null) {
				$log.debug(args);
			}
		}
	});
}

function wpdadataformsFeatures(angularApp) {
	angularApp.directive('ngEnter', function () {
		return function (scope, element, attrs) {
			element.bind("keydown keypress", function (e) {
				if (e.which === 13) {
					var returnValue = false;
					scope.$apply(function () {
						returnValue = scope.$eval(attrs.ngEnter);
					});
					if (!returnValue) {
						e.preventDefault();
					}
				}
			});
		};
	})
}

function wpdadataformsNumberOfColumns(formId, noColumns, elem) {
	jQuery("#" + formId + " div.wpdadataforms-edit")
		.removeClass("wpdadataforms-1-columns")
		.removeClass("wpdadataforms-2-columns")
		.removeClass("wpdadataforms-3-columns")
		.removeClass("wpdadataforms-4-columns")
		.addClass("wpdadataforms-" + noColumns + "-columns");
	jQuery("#" + formId + " button.wpdadataforms-button-icon").removeClass("ui-state-active");
	elem.addClass("ui-state-active");
}

function wpdadataformsToggleMenu(menuId) {
	jQuery("#wpdadataproject_menu_" + menuId + " .wpdadataproject_sub_menu_item").toggle();
	if (jQuery("#wpdadataproject_menu_" + menuId + " .wpdadataproject_main_menu_icon").hasClass("dashicons-menu-alt3")) {
		jQuery("#wpdadataproject_menu_" + menuId + " .wpdadataproject_main_menu_icon")
			.removeClass("dashicons-menu-alt3")
			.addClass("dashicons-arrow-up-alt2");
	} else {
		jQuery("#wpdadataproject_menu_" + menuId + " .wpdadataproject_main_menu_icon")
			.removeClass("dashicons-arrow-up-alt2")
			.addClass("dashicons-menu-alt3");
	}
}