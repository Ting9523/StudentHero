function export_publication_selection_to_sql(table_name, pub_id, wp_nonce, primary_key) {
	// Get table data
	table = jQuery('#' + table_name + pub_id ).DataTable();
	rows = table.rows({selected:true});
	if (table.rows({selected:true}).count()===0) {
		rows = table.rows();
	}
	data = rows.data();

	// Add key values to array
	ids = [];
	rows.every(function ( rowIdx, tableLoop, rowLoop ) {
		ids.push(jQuery(this.node()).find('td').eq(jQuery(this.node()).find("td." + primary_key).prop("cellIndex")).text());
	});

	// Define target url and add arguments
	url =
		wpda_publication_vars.wpda_ajaxurl +
		"?action=wpda_export&type=row&mysql_set=off&show_create=off&show_comments=off&format_type=sql" +
		"&pub_id=" + pub_id +
		"&table_names=" + table_name +
		"&_wpnonce=" + wp_nonce;

	// Add keys to url
	for (i=0; i<ids.length; i++) {
		for (var pk in primary_key) {
			url += '&' + primary_key[pk] + '[' + i + ']=' + encodeURIComponent(ids[i]);
		}
	}

	window.location.href = url;
}