function wpdapro_inline_editor(schema_name, table_name, primary_key, primary_key_values, column_name, column_value, wpnonce, elem_msg, path, column_data_type = '') {
	jQuery.ajax({
		type: "POST",
		url: path + '?action=wpdapro_inline_editor',
		data: {
			'wpdaschema_name': schema_name,
			'table_name': table_name,
			'primary_key': primary_key,
			'primary_key_values': primary_key_values,
			'column_name': column_name,
			'column_value': column_value,
			'column_data_type': column_data_type,
			'wpnonce': wpnonce
		}
	}).done(
		function (msg) {
			if ( msg.substr(0, 3) === 'UPD' ) {
				if ( msg.substr(4) == '0' ) {
					wpdapro_show_msg(elem_msg, 'No update')
				} else {
					wpdapro_show_msg(elem_msg, 'Saved')
				}
			} else {
				wpdapro_show_msg(elem_msg, 'Failed')
			}
		}
	).fail(
		function (msg) {
			console.log(msg);
			wpdapro_show_msg(elem_msg, 'Failed')
		}
	);
}

function wpdapro_show_msg(elem_msg, msg) {
	elem_msg.html(msg);
	elem_msg.show();
	setTimeout(function(){
		elem_msg.html('');
		elem_msg.hide();
	}, 2000);
}

function wpdapro_inline_editor_focus(ev, elem) {
	elem.parent().find('.wpdapro_inline_element_toolbar').show();
}

function wpdapro_inline_editor_blur(ev, elem) {
	setTimeout(function(){
		if (!jQuery(':focus').hasClass('wpdapro_inline_element_toolbar_icon')) {
			elem.parent().find('.wpdapro_inline_element_toolbar').hide();
		} else {
			elem.focus();
		}
	}, 200);
}

function wpdapro_inline_editor_reset(source, target) {
	target.val(source.val());
}

jQuery(function () {
	jQuery('.wpda_data_type_number').on('keyup paste', function () {
		this.value = this.value.replace(/[^\d\-]/g, ''); // Allow only 0-9
		if (isNaN(this.value)) {
			jQuery(this).addClass('wpda_input_error');
		} else {
			jQuery(this).removeClass('wpda_input_error');
		}
	});

	jQuery('.wpda_data_type_float').on('keyup paste', function () {
		this.value = this.value.replace(/[^\d\-\.\,]/g, ''); // Allow only 0-9 . ,
		if (isNaN(this.value)) {
			jQuery(this).addClass('wpda_input_error');
		} else {
			jQuery(this).removeClass('wpda_input_error');
		}
	});
});