<?php

namespace WPDataAccess\Premium\WPDAPRO_Inline_Editing {

	use WPDataAccess\Connection\WPDADB;
	use WPDataAccess\Plugin_Table_Models\WPDP_Project_Design_Table_Model;
	use WPDataAccess\Plugin_Table_Models\WPDP_Project_Model;
	use WPDataAccess\Plugin_Table_Models\WPDP_Page_Model;
	use WPDataAccess\Plugin_Table_Models\WPDA_Table_Settings_Model;
	use WPDataAccess\WPDA;

	class WPDAPRO_Inline_Editing {

		const INLINE_EDITING_ITEM_NAME_PREFIX = 'inline_editing_';
		const ELEMENT_DEMILIMETER             = ',,,;;;,,,';

		protected static $cache       = [];
		protected static $initialized = false;

		public static function init() {
			global $wpdb;

			if ( WPDP_Project_Model::table_exists() ) {
				// Add inline editing to column add_to_menu on project page
				$plugin_table_name = WPDP_Project_Model::get_base_table_name();
				self::$cache[ "`{$wpdb->dbname}`.`{$plugin_table_name}`.`add_to_menu`" ] = true;
				self::$cache[ "`{$wpdb->dbname}`.`{$plugin_table_name}`" ]               = true; // prevent a query for every column
			}

			if ( WPDP_Page_Model::table_exists() ) {
				// Add inline editing to columns add_to_menu and page_mode on project page
				$plugin_table_name = WPDP_Page_Model::get_base_table_name();
				self::$cache[ "`{$wpdb->dbname}`.`{$plugin_table_name}`.`add_to_menu`" ] = true;
				self::$cache[ "`{$wpdb->dbname}`.`{$plugin_table_name}`.`page_mode`" ]   = true;
				self::$cache[ "`{$wpdb->dbname}`.`{$plugin_table_name}`" ]               = true; // prevent a query for every column
			}

		}

		public static function add_inline_editing_to_column(
			$value,
			$item,
			$column_name,
			$table_name,
			$schema_name,
			$wpda_list_columns,
			$list_number,
			$self
		) {
			if ( '' !== $value ) {
				return $value;
			}

			global $wpda_project_mode;
			if ( isset( $wpda_project_mode['mode'] ) && 'view' === $wpda_project_mode['mode'] ) {
				// Inline editing not allowed in view mode
				return null;
			}

			if ( isset( $wpda_project_mode['setname'] ) ) {
				// Check if column si defined as read-only
				$tableform = WPDP_Project_Design_Table_Model::get_column_options(
					$table_name,
					'tableform',
					$wpda_project_mode['setname'],
					$schema_name
				);

				if ( is_array( $tableform ) ) {
					foreach ( $tableform as $col ) {
						if ( isset( $col->column_name, $col->readonly ) ) {
							if ( $column_name === $col->column_name && 'on' === $col->readonly ) {
								// Column is defined as read-ony for this project
								self::$cache["`{$schema_name}`.`{$table_name}`"] = true; // prevent a query for every column
								return null;
							}
						}
					}
				}
			}

			if ( ! self::$initialized ) {
				self::init();
			}

			if ( '' === $schema_name ) {
				global $wpdb;
				$schema_name = $wpdb->dbname;
			}

			if ( ! isset( self::$cache[ "`{$schema_name}`.`{$table_name}`" ] ) ) {
				$table_settings = WPDA_Table_Settings_Model::query( $table_name, $schema_name );

				$disabled_columns = [];
				if ( isset( $wpda_project_mode['setname'] ) ) {
					$project_table_settings =
						WPDP_Project_Design_Table_Model::static_query(
							$schema_name,
							$table_name,
							isset( $wpda_project_mode['setname'] ) ? $wpda_project_mode['setname'] : 'default'
						);

					if ( isset( $project_table_settings->tableinfo->custom_table_settings->inline_editing ) ) {
						// Check if inline editing is disabled for this project setname
						$disabled_columns_all = $project_table_settings->tableinfo->custom_table_settings->inline_editing;
					}
				}

				if ( 0 < sizeof( $table_settings ) && isset( $table_settings[0]['wpda_table_settings'] ) ) {
					$settings = json_decode( $table_settings[0]['wpda_table_settings'] );
					if ( isset( $settings->custom_settings ) ) {
						$custom_settings = $settings->custom_settings;
						foreach ( $custom_settings as $key => $value ) {
							if ( property_exists( $self, 'is_child') ) {
								// Child list table
								if ( isset( $disabled_columns_all->child ) ) {
									$disabled_columns = $disabled_columns_all->child;
								}
							} else {
								// Parent or stand-alone list table
								if ( isset( $disabled_columns_all->parent ) ) {
									$disabled_columns = $disabled_columns_all->parent;
								}
							}

							if ( substr( $key, 0, 15 ) === self::INLINE_EDITING_ITEM_NAME_PREFIX ) {
								$column_name_tmp = substr( $key, 15 );
								if ( isset( $disabled_columns->$column_name_tmp ) ) {
									$value = $disabled_columns->$column_name_tmp;
								}
								self::$cache[ "`{$schema_name}`.`{$table_name}`.`{$column_name_tmp}`" ] = $value;

								if (
									isset( $project_table_settings->relationships ) &&
									is_array( $project_table_settings->relationships )
								) {
									$relationships = $project_table_settings->relationships;
									foreach ( $relationships as $relationship ) {
										// TODO What's going on here?
										if (
											isset( $relationship->relation_type ) &&
											(
												'lookup' === $relationship->relation_type ||
												'autocomplete' === $relationship->relation_type
											)
										) {
											if (
												isset( $relationship->source_column_name[0] ) &&
												$relationship->source_column_name[0] === $column_name_tmp
											) {
												self::$cache[ "`{$schema_name}`.`{$table_name}`.`{$column_name_tmp}`.relationship" ] =
													$relationship;
											}
										}
									}
								}
							}
						}
					}
				}
				self::$cache[ "`{$schema_name}`.`{$table_name}`" ] = true; // prevent a query for every column
			}

			if (
				isset( self::$cache[ "`{$schema_name}`.`{$table_name}`.`{$column_name}`" ] ) &&
				self::$cache[ "`{$schema_name}`.`{$table_name}`.`{$column_name}`" ]
			) {
				// Add inline editing support to column
				$pk        = $wpda_list_columns->get_table_primary_key();
				$pk_values = [];
				foreach ( $pk as $value ) {
					if ( isset( $item[ $value ] ) ) {
						array_push( $pk_values, $item[ $value ] );
					} else {
						return null;
					}
				}
				$pk_string        = implode( self::ELEMENT_DEMILIMETER, $pk );
				$pk_values_string = implode( self::ELEMENT_DEMILIMETER, $pk_values );

				$column_type      = $wpda_list_columns->get_column_type( $column_name );
				$column_data_type = $wpda_list_columns->get_column_data_type( $column_name );

				$ajax_path = admin_url( 'admin-ajax.php' );

				if ( 'enum' === $column_data_type ) {
					return self::inline_enum( $schema_name, $table_name, $pk_string, $pk_values_string, $column_name, $item[ $column_name ], $list_number, $column_data_type, $column_type, $ajax_path );
				} elseif ( 'set' === $column_data_type ) {
					return self::inline_set( $schema_name, $table_name, $pk_string, $pk_values_string, $column_name, $item[ $column_name ], $list_number, $column_data_type, $column_type, $ajax_path );
				} elseif ( 'date' ===  WPDA::get_type( $column_data_type ) || 'time' ===  WPDA::get_type( $column_data_type ) ) {
					return self::inline_datetime( $schema_name, $table_name, $pk_string, $pk_values_string, $column_name, $item[ $column_name ], $list_number, $column_data_type, $column_type, $ajax_path );
				} elseif ( 'tinyint(1)' === $column_type ) {
					return self::checkbox( $schema_name, $table_name, $pk_string, $pk_values_string, $column_name, $item[ $column_name ], $list_number, $column_data_type, $column_type, $ajax_path );
				} else {
					if ( isset( self::$cache[ "`{$schema_name}`.`{$table_name}`.`{$column_name}`.relationship" ] ) ) {
						$item_value         = isset( $item[ "lookup_id_{$column_name}" ] ) ? $item[ "lookup_id_{$column_name}" ] : $item[ $column_name ];
						$lookup_column_name = isset( $item[ "lookup_column_{$column_name}" ] ) ? $item[ "lookup_column_{$column_name}" ] : $column_name;
						return self::lookup( $schema_name, $table_name, $pk_string, $pk_values_string, $column_name, $item[ $column_name ], $item_value, $lookup_column_name, $list_number, $column_data_type, $column_type, $ajax_path, self::$cache[ "`{$schema_name}`.`{$table_name}`.`{$column_name}`.relationship" ] );
					} else {
						return self::inline_text( $schema_name, $table_name, $pk_string, $pk_values_string, $column_name, $item[ $column_name ], $list_number, $column_data_type, $column_type, $ajax_path );
					}
				}
			}

			return null;
		}

		protected static function get_lookup_values( $schema_name, $table_name, $column_name, $lookup_column_name, $relationship ) {
			if ( isset( self::$cache[ "`{$schema_name}`.`{$table_name}`.`{$column_name}`.lookup" ] ) ) {
				return self::$cache[ "`{$schema_name}`.`{$table_name}`.`{$column_name}`.lookup" ];
			}

			if ( isset( $relationship->target_schema_name ) ) {
				$target_schema_name = $relationship->target_schema_name;
			} else {
				global $wpdb;
				$target_schema_name = $wpdb->dbname;
			}
			$target_table_name  = str_replace( '`', '', $relationship->target_table_name );
			$target_column_name = str_replace( '`', '', $relationship->target_column_name[0] );

			$wpdadb       = WPDADB::get_db_connection( $target_schema_name );
			$lookup_query = "select `{$target_column_name}`, `" . str_replace( '`', '', $lookup_column_name ) . "` from `{$wpdadb->dbname}`.`{$target_table_name}` ";
			if ( isset( $relationship->target_column_name[1] ) ) {
				// Add parent check
				$parent_column = $relationship->target_column_name[1];
				if ( isset( $_REQUEST[ $parent_column ] ) ) {
					$parent_value = str_replace( '`', '', sanitize_text_field( wp_unslash( $_REQUEST[ $parent_column ] ) ) ); // input var okay.
					$lookup_query .= $wpdadb->prepare( "where `{$parent_column}` = %s ", $parent_value );
				}
			}
			$rows = $wpdadb->get_results( $lookup_query, 'ARRAY_A' );

			self::$cache[ "`{$schema_name}`.`{$table_name}`.`{$column_name}`.lookup" ] = $rows;

			return self::$cache[ "`{$schema_name}`.`{$table_name}`.`{$column_name}`.lookup" ];
		}

		protected static function lookup( $schema_name, $table_name, $pk, $pk_values, $column_name, $column_value, $column_value_lookup_id, $lookup_column_name, $column_index, $column_data_type, $column_type, $ajax_path, $relationship ) {
			$lookup_values      = self::get_lookup_values( $schema_name, $table_name, $column_name, $lookup_column_name, $relationship );
			$target_column_name = $relationship->target_column_name[0];
			$options            = '';
			if ( is_array( $lookup_values ) ) {
				foreach ( $lookup_values as $lookup_value ) {
					$value    = $lookup_value[$target_column_name];
					$text     = $lookup_value[$lookup_column_name];
					$selected = $column_value_lookup_id === $lookup_value[$target_column_name] ? 'selected' : '';

					$options .= "<option value='{$value}' {$selected}>{$text}</option>";
				}
			}
			$select = "<select id='{$column_name}_{$column_index}'>{$options}</select>";

			$wpnonce = self::create_wpnonce( $schema_name, $table_name, $column_name );

			return "<div class='wpdapro_inline_element'>{$select}</div>
					<div id='{$column_name}_{$column_index}_msg' class='wpdapro_inline_element_message' style='display:none;'></div>
					<script type='text/javascript'>
						jQuery(function () {
							jQuery('#{$column_name}_{$column_index}').change(function() {
								wpdapro_inline_editor('{$schema_name}','{$table_name}','{$pk}','{$pk_values}','{$column_name}',jQuery('#{$column_name}_{$column_index}').val(), '{$wpnonce}', jQuery('#{$column_name}_{$column_index}_msg'), '{$ajax_path}'); event.preventDefault();
							});
						});
					</script>";
		}

		protected static function checkbox( $schema_name, $table_name, $pk, $pk_values, $column_name, $column_value, $column_index, $column_data_type, $column_type, $ajax_path ) {
			$wpnonce = self::create_wpnonce( $schema_name, $table_name, $column_name );
			$checked = $column_value ? 'checked' : '';

			return "<div class='wpdapro_inline_element'>
						<label>
							<input type='checkbox' id='{$column_name}_{$column_index}' class='wpdapro_inline_element_select' {$checked} />
						</label>
					</div>
					<div id='{$column_name}_{$column_index}_msg' class='wpdapro_inline_element_message' style='display:none;'></div>
					<script type='text/javascript'>
						jQuery(function () {
							jQuery('#{$column_name}_{$column_index}').change(function() {
								if (jQuery('#{$column_name}_{$column_index}').is(':checked')) {
									val = 1;
								} else {
									val = 0;
								}
								wpdapro_inline_editor('{$schema_name}','{$table_name}','{$pk}','{$pk_values}','{$column_name}',val, '{$wpnonce}', jQuery('#{$column_name}_{$column_index}_msg'), '{$ajax_path}'); event.preventDefault();
							});
						});
					</script>";
		}

		protected static function inline_set( $schema_name, $table_name, $pk, $pk_values, $column_name, $column_value, $column_index, $column_data_type, $column_type, $ajax_path ) {
			$item_enum = explode(
				',',
				str_replace(
					'\'',
					'',
					substr( substr( $column_type, 4 ), 0, - 1 )
				)
			);
			$options = '';
			foreach ( $item_enum as $value ) {
				if ( false !== strpos( $column_value, $value ) ) {
					$selected = 'selected';
				} else {
					$selected = '';
				}
				$options .= "<option value='{$value}' {$selected}>{$value}</option>";
			}
			$select = "<select id='{$column_name}_{$column_index}' class='wpdapro_inline_element_select' multiple size=3>{$options}</select>";

			$wpnonce = self::create_wpnonce( $schema_name, $table_name, $column_name );

			return "<div class='wpdapro_inline_element'>{$select}</div>
					<div id='{$column_name}_{$column_index}_msg' class='wpdapro_inline_element_message' style='display:none;'></div>
					<script type='text/javascript'>
						jQuery(function () {
							jQuery('#{$column_name}_{$column_index}').change(function() {
								if (!jQuery('#{$column_name}_{$column_index}').val()) {
									var set_selection = '';
								} else {
									var set_selection = jQuery('#{$column_name}_{$column_index}').val().join();
								}
								wpdapro_inline_editor('{$schema_name}','{$table_name}','{$pk}','{$pk_values}','{$column_name}',set_selection, '{$wpnonce}', jQuery('#{$column_name}_{$column_index}_msg'), '{$ajax_path}'); event.preventDefault();
							});
						});
					</script>";
		}

		protected static function inline_enum( $schema_name, $table_name, $pk, $pk_values, $column_name, $column_value, $column_index, $column_data_type, $column_type, $ajax_path ) {
			$item_enum = explode(
				',',
				str_replace(
					'\'',
					'',
					substr( substr( $column_type, 5 ), 0, - 1 )
				)
			);
			$options = '';
			foreach ( $item_enum as $value ) {
				$selected = $value === $column_value ? 'selected' : '';
				$options .= "<option value='{$value}' {$selected}>{$value}</option>";
			}
			$select = "<select id='{$column_name}_{$column_index}' class='wpdapro_inline_element_select'>{$options}</select>";

			$wpnonce = self::create_wpnonce( $schema_name, $table_name, $column_name );

			return "<div class='wpdapro_inline_element'>{$select}</div>
					<div id='{$column_name}_{$column_index}_msg' class='wpdapro_inline_element_message' style='display:none;'></div>
					<script type='text/javascript'>
						jQuery(function () {
							jQuery('#{$column_name}_{$column_index}').change(function() {
								wpdapro_inline_editor('{$schema_name}','{$table_name}','{$pk}','{$pk_values}','{$column_name}',jQuery('#{$column_name}_{$column_index}').val(), '{$wpnonce}', jQuery('#{$column_name}_{$column_index}_msg'), '{$ajax_path}'); event.preventDefault();
							});
						});
					</script>";
		}

		protected static function inline_text( $schema_name, $table_name, $pk, $pk_values, $column_name, $column_value, $column_index, $column_data_type, $column_type, $ajax_path ) {
			$item_class = '';
			$max_length = '';
			if ( 'number' === WPDA::get_type($column_data_type) ) {
				if ( false === strpos( $column_type, ',' ) ) {
					if ( 'float' === substr( $column_data_type, 0, 5 ) || 'double' === substr( $column_data_type, 0, 6 ) ) {
						$item_class = 'wpda_data_type_float';
					} else {
						$item_class = 'wpda_data_type_number';
					}
				} else {
					$item_class = 'wpda_data_type_float';
				}
			} else {
				$pos_open  = strpos( $column_type, '(' );
				$pos_close = strpos( $column_type, ')' );
				if ( false !== $pos_open && false !== $pos_close ) {
					$max_length = 'maxlength="' . substr( $column_type, $pos_open + 1, $pos_close - $pos_open - 1 ) . '"';
				}
			}

			$wpnonce = self::create_wpnonce( $schema_name, $table_name, $column_name );
			$column_value = esc_attr( $column_value );

			return
				"<div class='wpdapro_inline_element'>
					<input id='{$column_name}_{$column_index}' value='{$column_value}' type='text'
						 class='wpdapro_inline_element_input {$item_class}'
						 onfocus='wpdapro_inline_editor_focus(event, jQuery(this))' 
						 onblur='wpdapro_inline_editor_blur(event, jQuery(this))'
						 {$max_length}
					/>
					<input id='{$column_name}_{$column_index}_old' value='{$column_value}' type='hidden'/>
					<div class='wpdapro_inline_element_wrapper'>
						<div class='wpdapro_inline_element_toolbar' style='display:none;'>
							<a href='javascript:void(0);' class='wpdapro_inline_element_toolbar_icon'
								 onclick=\"wpdapro_inline_editor('{$schema_name}','{$table_name}','{$pk}','{$pk_values}','{$column_name}',jQuery('#{$column_name}_{$column_index}').val(), '{$wpnonce}', jQuery('#{$column_name}_{$column_index}_msg'), '{$ajax_path}', '{$column_data_type}')\">
								<span class='dashicons dashicons-yes wpdapro_inline_element_toolbar_icon_save'></span>
							</a>
							<a href='javascript:void(0);' class='wpdapro_inline_element_toolbar_icon'
								 onclick=\"wpdapro_inline_editor_reset(jQuery('#{$column_name}_{$column_index}_old'), jQuery('#{$column_name}_{$column_index}'))\">
								<span class='dashicons dashicons-undo wpdapro_inline_element_toolbar_icon_cancel'></span>
							</a>
						</div>
						<div id='{$column_name}_{$column_index}_msg' class='wpdapro_inline_element_message' style='display:none;'></div>
					</div>
				</div>
				<script type='text/javascript'>
					jQuery(function () {
						jQuery('#{$column_name}_{$column_index}').keypress(function() {
							if (event.which == '13') { wpdapro_inline_editor('{$schema_name}','{$table_name}','{$pk}','{$pk_values}','{$column_name}',jQuery('#{$column_name}_{$column_index}').val(), '{$wpnonce}', jQuery('#{$column_name}_{$column_index}_msg'), '{$ajax_path}'); event.preventDefault(); }
						});
					});
				</script>";
		}

		protected static function inline_datetime( $schema_name, $table_name, $pk, $pk_values, $column_name, $column_value, $column_index, $column_data_type, $column_type, $ajax_path ) {
			// Get date and time formats
			$date_format = WPDA::get_option( WPDA::OPTION_PLUGIN_DATE_FORMAT );
			$time_format = WPDA::get_option( WPDA::OPTION_PLUGIN_TIME_FORMAT );

			// Get date and time placeholders
			$date_placeholder = WPDA::get_option( WPDA::OPTION_PLUGIN_DATE_PLACEHOLDER );
			$time_placeholder = WPDA::get_option( WPDA::OPTION_PLUGIN_TIME_PLACEHOLDER );

			$locale = substr( get_locale(), 0, 2 );

			switch ( $column_type ) {
				case 'time':
					$date_format      = $time_format;
					$date_picker      = 'false';
					$time_picker      = 'true';
					$item_placeholder = $time_placeholder;
					$db_format        = WPDA::DB_TIME_FORMAT;
					break;
				case 'date':
					$date_picker      = 'true';
					$time_picker      = 'false';
					$item_placeholder = $date_placeholder;
					$db_format        = WPDA::DB_DATE_FORMAT;
					break;
				default:
					$date_format      = $date_format . ' ' . $time_format;
					$date_picker      = 'true';
					$time_picker      = 'true';
					$item_placeholder = $date_placeholder . ' ' . $time_placeholder;
					$db_format        = WPDA::DB_DATETIME_FORMAT;
			}

			if ( null !== $column_value && '' !== $column_value ) {
				$convert_date = \DateTime::createFromFormat( $db_format, $column_value );
				$column_value   = $convert_date->format( $date_format );
			}

			$wpnonce = self::create_wpnonce( $schema_name, $table_name, $column_name );

			return
				"<div class='wpdapro_inline_element'>
					<input id='{$column_name}_{$column_index}' value='{$column_value}' type='text'
						 class='wpdapro_inline_element_input'
						 placeholder='{$item_placeholder}'
						 onfocus='wpdapro_inline_editor_focus(event, jQuery(this))'
						 onblur='wpdapro_inline_editor_blur(event, jQuery(this))'
					/>
					<input id='{$column_name}_{$column_index}_old' value='{$column_value}' type='hidden'/>
					<div class='wpdapro_inline_element_wrapper'>
						<div id='{$column_name}_{$column_index}_msg' class='wpdapro_inline_element_message' style='display:none;'></div>
					</div>
				</div>
				<script type='text/javascript'>
					jQuery(function () {
						jQuery.datetimepicker.setLocale('{$locale}');
						jQuery('#{$column_name}_{$column_index}').datetimepicker( {
							format: '{$date_format}',
							datepicker: {$date_picker},
							timepicker: {$time_picker}
					});
					jQuery('#{$column_name}_{$column_index}').attr('autocomplete', 'off');
					jQuery('#{$column_name}_{$column_index}').change(function() {
						wpdapro_inline_editor('{$schema_name}','{$table_name}','{$pk}','{$pk_values}','{$column_name}',jQuery('#{$column_name}_{$column_index}').val(), '{$wpnonce}', jQuery('#{$column_name}_{$column_index}_msg'), '{$ajax_path}', '{$column_data_type}'); event.preventDefault();
						});
					});
				</script>";
		}

		protected static function create_wpnonce( $schema_name, $table_name, $column_name ) {
			return wp_create_nonce( "wpdapro-line-edit-{$schema_name}-{$table_name}-{$column_name}" );
		}

		public static function wpdapro_inline_editor() {
			WPDA::sent_header('text/plain; charset=utf-8');
			if (
				isset( $_REQUEST['wpdaschema_name'] ) &&
				isset( $_REQUEST['table_name'] ) &&
				isset( $_REQUEST['primary_key'] ) &&
				isset( $_REQUEST['primary_key_values'] ) &&
				isset( $_REQUEST['column_name'] ) &&
				isset( $_REQUEST['column_value'] ) &&
				isset( $_REQUEST['wpnonce'] )
			) {
				// Get arguments needed to check authorization
				$schema_name = sanitize_text_field( wp_unslash( $_REQUEST['wpdaschema_name'] ) ); // input var okay.
				$table_name  = sanitize_text_field( wp_unslash( $_REQUEST['table_name'] ) ); // input var okay.
				$column_name = sanitize_text_field( wp_unslash( $_REQUEST['column_name'] ) ); // input var okay.

				// Check if actions is allowed
				$wp_nonce = isset( $_REQUEST['wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpnonce'] ) ) : ''; // input var okay.
				if ( ! wp_verify_nonce( $wp_nonce, "wpdapro-line-edit-{$schema_name}-{$table_name}-{$column_name}" ) ) {
					echo 'INV-Not authorized';
					return;
				}

				// Get additional arguments
				$req_primary_key        = sanitize_text_field( wp_unslash( $_REQUEST['primary_key'] ) );
				$req_primary_key_values = sanitize_text_field( wp_unslash( $_REQUEST['primary_key_values'] ) );
				$column_value           = wp_unslash( $_REQUEST['column_value'] ); // input var okay.

				$primary_key        = explode( WPDAPRO_Inline_Editing::ELEMENT_DEMILIMETER, $req_primary_key );
				$primary_key_values = explode( WPDAPRO_Inline_Editing::ELEMENT_DEMILIMETER, $req_primary_key_values );
				// Check if primary key arrays are valid
				if (
					! is_array( $primary_key ) ||
					! is_array( $primary_key_values ) ||
					sizeof( $primary_key ) != sizeof( $primary_key_values )
				) {
					echo 'INV-Missing primary key';
					return;
				}

				// Prepare primary key array
				$pk = [];
				for ( $i = 0; $i < sizeof( $primary_key ); $i++ ) {
					$pk[ $primary_key[ $i ] ] = $primary_key_values[ $i ];
				}

				// Check numeric and date columns
				$column_data_type =
					isset( $_REQUEST['column_data_type'] ) ?
						sanitize_text_field( wp_unslash( $_REQUEST['column_data_type'] ) ) : '';
				if ( 'number' === WPDA::get_type( $column_data_type ) && '' === $column_value ) {
					$column_value = null;
				} else {
					if (
						'time' === WPDA::get_type( $column_data_type ) ||
						'date' === WPDA::get_type( $column_data_type )
					) {
						if ( '' === $column_value ) {
							$column_value = null;
						} else {
							switch ( $column_data_type ) {
								case 'time':
									$date_format = WPDA::get_option( WPDA::OPTION_PLUGIN_TIME_FORMAT );
									$db_format   = WPDA::DB_TIME_FORMAT;
									break;
								case 'date':
									$date_format = WPDA::get_option( WPDA::OPTION_PLUGIN_DATE_FORMAT );
									$db_format   = WPDA::DB_DATE_FORMAT;
									break;
								default:
									$date_format = WPDA::get_option( WPDA::OPTION_PLUGIN_DATE_FORMAT ) . ' ' . WPDA::get_option( WPDA::OPTION_PLUGIN_TIME_FORMAT );
									$db_format   = WPDA::DB_DATETIME_FORMAT;
							}

							$convert_date = \DateTime::createFromFormat( $date_format, $column_value );
							if ( false !== $convert_date ) {
								$column_value = $convert_date->format( $db_format );
							}
						}
					}
				}

				// Perform database update
				$wpdadb = WPDADB::get_db_connection( $schema_name );
				$wpdadb->suppress_errors( true );
				$rows_update = $wpdadb->update(
					$table_name,
					[
						$column_name => $column_value
					],
					$pk
				);

				echo '' === $wpdadb->last_error ? "UPD-{$rows_update}" : 'ERR-' . $wpdadb->last_error;
			} else {
				echo 'INV-Wrong arguments';
			}
		}

	}

}