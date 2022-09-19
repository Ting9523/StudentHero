function create_fulltext_index(schema_name, table_name, column_name, wpnonce, fullindex_columns) {
	if (confirm('Create full-text index for column ' + column_name + '?')) {
		jQuery.ajax({
			type: "POST",
			url: location.pathname + '?action=wpdapro_create_fulltext_index',
			data: {
				'wpdaschema_name': schema_name,
				'table_name': table_name,
				'column_name': column_name,
				'wpnonce': wpnonce
			}
		}).done(
			function (msg) {
				if (msg) {
					if ( msg.substr(0, 4) === 'DONE' ) {
						jQuery('#create_fulltext_index_msg_' + table_name + '_' + column_name).html('Fulltext index created');
						fullindex_columns.push(column_name);
						jQuery('#has_fulltext_index_' + table_name + '_' + column_name).show();
						jQuery('#create_fulltext_index_' + table_name + '_' + column_name).hide();
					} else {
						jQuery('#create_fulltext_index_msg_' + table_name + '_' + column_name).html(msg);
					}
				}
			}
		).fail(
			function (msg) {
				console.log(msg);
			}
		);
		jQuery('#create_fulltext_index_msg_' + table_name + '_' + column_name).html('Building fulltext index... <a href="javascript:void(0)" onclick="fulltext_index_check_status(\'' + schema_name + '\',\'' + table_name + '\',\'' + column_name + '\',\'' + wpnonce + '\')" class="dashicons dashicons-image-rotate wpda_tooltip" title="Refresh"></a>');
	}
}

function fulltext_index_check_status(schema_name, table_name, column_name, wpnonce) {
	jQuery.ajax({
		type: "POST",
		url: location.pathname + '?action=wpdapro_check_fulltext_index',
		data: {
			'wpdaschema_name': schema_name,
			'table_name': table_name,
			'column_name': column_name,
			'wpnonce': wpnonce
		}
	}).done(
		function (msg) {
			if (msg==="OK") {
				jQuery('#has_fulltext_index_' + table_name + '_' + column_name).show();
				jQuery('#create_fulltext_index_' + table_name + '_' + column_name).hide();
				jQuery('#create_fulltext_index_msg_' + table_name + '_' + column_name).html('').attr('data-status','');
				eval(table_name+'_fullindex_columns').push(column_name);
			}
		}
	).fail(
		function (msg) {
			console.log(msg);
		}
	);
}

function fulltext_index_cancel(schema_name, table_name, column_name, wpnonce) {
	jQuery.ajax({
		type: "POST",
		url: location.pathname + '?action=wpdapro_cancel_fulltext_index',
		data: {
			'wpdaschema_name': schema_name,
			'table_name': table_name,
			'column_name': column_name,
			'wpnonce': wpnonce
		}
	}).done(
		function (msg) {
			if (msg==="OK") {
				jQuery('#has_fulltext_index_' + table_name + '_' + column_name).hide();
				jQuery('#create_fulltext_index_' + table_name + '_' + column_name).show();
				jQuery('#create_fulltext_index_msg_' + table_name + '_' + column_name).html('').attr('data-status','');
				var index = eval(table_name+'_fullindex_columns').indexOf(column_name);
				if (index!==-1) {
					eval(table_name+'_fullindex_columns').splice(index, 1);
				}
			}
		}
	).fail(
		function (msg) {
			console.log(msg);
		}
	);
}

function submit_search_settings(table_name, sql_dml, settings_id, settings_form_id, search_columns, fullindex_columns, listbox_columns, individual_search_columns) {
	jQuery("#wpda_pro_search_messages_" + table_name).hide();
	jQuery("#wpda_pro_search_messages_text_" + table_name).html('');

	search_settings = {};
	search_settings['search_type'] = jQuery("input[name='" + table_name +  "_search_type']:checked").val();

	if (search_settings['search_type']==='fulltext') {
		var wpda_data_type_ok = true;
		var wpda_has_fulltext_index = true;
		jQuery('.wpda_check_data_type_' + table_name).each(function(index) {
			item_name = jQuery(this).attr('id').substr(3);
			item_chkd = jQuery('#cb_'+item_name).is(':checked');
			column_name = item_name.substr(table_name.length+1);
			if (item_chkd && jQuery(this).val()!=='string') {
				jQuery("#wpda_pro_search_messages_" + table_name).show();
				jQuery("#wpda_pro_search_messages_text_" + table_name).append('<strong>ERROR: Full-text search is only available for text columns (column ' + column_name + ' is not a text column)</strong><br/>');
				wpda_data_type_ok = false;
			}
			if (!fullindex_columns.includes(column_name) && jQuery(this).val()==='string') {
				jQuery("#wpda_pro_search_messages_" + table_name).show();
				jQuery("#wpda_pro_search_messages_text_" + table_name).append('<strong>ERROR: No full-text index available for column ' + column_name + '</strong><br/>');
				wpda_has_fulltext_index = false;
			}
		});
		if (!wpda_data_type_ok || !wpda_has_fulltext_index) {
			return false;
		}
	}

	search_settings['full_text_modifier'] = jQuery("input[name='" + table_name + "_full_text_modifier']:checked").val();
	search_settings['no_search_no_rows'] = jQuery("#" + table_name + "_no_search_no_rows").is(':checked');
	search_settings['column_specific_search'] = jQuery("#" + table_name + "_column_specific_search").is(':checked');
	search_settings['search_columns'] = search_columns;
	search_settings['individual_search_columns'] = individual_search_columns;
	search_settings['listbox_columns'] = listbox_columns;

	unused = {};
	unused['sql_dml'] = sql_dml;

	jsonData = {};
	jsonData['request_type'] = 'column_settings';
	jsonData['search_settings'] = search_settings;
	jsonData['unused'] = unused;

	// console.log(JSON.stringify(jsonData));
	jQuery('#' + settings_id).val(JSON.stringify(jsonData));
	jQuery('#' + settings_form_id).submit();
	return false;
}

function calculate_no_listbox_items(schema_name, table_name, column_name, wpnonce) {
	jQuery.ajax({
		type: "POST",
		url: location.pathname + '?action=wpdapro_no_listbox_items',
		data: {
			'wpdaschema_name': schema_name,
			'table_name': table_name,
			'column_name': column_name,
			'wpnonce': wpnonce
		}
	}).done(
		function (msg) {
			jQuery('#lb_msg_' + table_name + '_' + column_name).html(msg);
		}
	).fail(
		function (msg) {
			console.log(msg);
		}
	);
}