<?php

namespace WPDataAccess\Premium\WPDAPRO_FullText_Search {

	use WPDataAccess\Connection\WPDADB;
	use WPDataAccess\Plugin_Table_Models\WPDA_Publisher_Model;
	use WPDataAccess\Plugin_Table_Models\WPDA_Table_Settings_Model;
	use WPDataAccess\Plugin_Table_Models\WPDP_Project_Design_Table_Model;
	use WPDataAccess\WPDA;

	class WPDAPRO_Search {

		const WPDA_FULLTEXT_INDEX_IN_PROGRESS = 'wpda_fulltext_index_in_progress';

		public static function add_search_settings( $schema_name, $table_name, $table_structure ) {
			$search_type   = 'normal';
			$fulltext_type = 'natural';

			// Connect to database
			$wpdadb	= WPDADB::get_db_connection( $schema_name );
			if ( null === $wpdadb ) {
				wp_die( sprintf( __( 'ERROR - Remote database %s not available', 'wp-data-access' ), $schema_name ) );
			}

			// Check if fulltext search is available for this table
			$fulltext_available               = false;
			$view_columns_with_fulltext_index = [];
			// Table must be InnoDB or MyISAM
			$query  = "select engine as engine, table_type as table_type from information_schema.tables where table_schema = %s and table_name = %s";
			$engine = $wpdadb->get_results( $wpdadb->prepare( $query, [ $wpdadb->dbname, $table_name ] ), 'ARRAY_A' );

			$table_name_sanitized  = str_replace( '`', '', $table_name );
			if ( 1 === $wpdadb->num_rows ) {
				if ( 'InnoDB' === $engine[0]['engine'] || 'MyISAM' === $engine[0]['engine'] ) {
					// Does this table have CHAR, VARCHAR or TEXT columns?
					foreach ( $table_structure as $key => $value ) {
						if (
							false !== stripos( $value['Type'], 'char' ) ||
							false !== stripos( $value['Type'], 'text' )
						) {
							$fulltext_available = true;
							break;
						}
					}
				} elseif ( stripos( $engine[0]['table_type'], 'view' ) !== false ) {
					// Get view columns
					$columns = $wpdadb->get_results(
						$wpdadb->prepare("
							  SELECT column_name AS column_name
								FROM information_schema.columns 
							   WHERE table_schema = %s
								 AND table_name   = %s
							",
							[
								$wpdadb->dbname,
								$table_name,
							]
						), 'ARRAY_A'
					);

					$suppress_errors = $wpdadb->suppress_errors( true );
					foreach ( $columns as $column ) {
						$column_name_sanitized = str_replace( '`', '', $column['column_name'] );
						$column_fulltext = $wpdadb->get_results("
							explain 
								select 'x' from `{$table_name_sanitized}`
								where match (`{$column_name_sanitized}`) against ('x')
							", 'ARRAY_A'
						);
						if ( isset( $column_fulltext[0]['type'] ) && 'fulltext' === $column_fulltext[0]['type'] ) {
							$view_columns_with_fulltext_index[ $column['column_name'] ] = true;
						}
					}
					$wpdadb->suppress_errors( $suppress_errors );

					if ( sizeof( $view_columns_with_fulltext_index ) > 0 ) {
						$fulltext_available = true;
					}
				}
			}

			// Get table indexes (check if a fulltext indexes is available for this table
			$query          = "show indexes from `{$wpdadb->dbname}`.`{$table_name_sanitized}` where index_type = 'FULLTEXT'";
			$indexes        = $wpdadb->get_results( $query, 'ARRAY_A' );
			$indexes_remove = [];
			foreach ( $indexes as $index ) {
				if ( $index['Seq_in_index'] > 1 ) {
					$indexes_remove[] = $index['Key_name'];
				}
			}
			$fulltext_index = [];
			foreach ( $indexes as $index ) {
				$remove_index = false;
				foreach ( $indexes_remove as $item ) {
					if ( $item === $index['Key_name'] ) {
						$remove_index = true;
					}
				}
				if ( ! $remove_index ) {
					$fulltext_index[ $index['Column_name'] ] = true;
				}
			}

			if ( sizeof( $fulltext_index ) === 0 && sizeof( $view_columns_with_fulltext_index ) > 0 ) {
				$fulltext_index = $view_columns_with_fulltext_index; // View
			}

			$wp_nonce_action_settings     = "wpda-settings-{$table_name}";
			$wp_nonce_settings            = wp_create_nonce( $wp_nonce_action_settings );
			$settings_table_form_id       = 'search_settings_table_form_' . $table_name;
			$settings_table_form_settings = 'search_settings_table_form_settings_' . $table_name;
			$settings_table_form          =
				"<form" .
				" id='" . $settings_table_form_id . "'" .
				" action='?page=" . esc_attr( \WP_Data_Access_Admin::PAGE_MAIN ) . "'" .
				" method='post'>" .
				"<input type='hidden' name='action' value='settings-table' />" .
				"<input type='hidden' name='settings_table_name' value='" . $table_name . "' />" .
				"<input type='hidden' name='settings' id='" . esc_attr( $settings_table_form_settings ) . "' value='' />" .
				"<input type='hidden' name='_wpnonce' value='" . esc_attr( $wp_nonce_settings ) . "' />" .
				"</form>";

			$settings_db            = WPDA_Table_Settings_Model::query( $table_name, $schema_name );
			$no_search_no_rows      = false;
			$column_specific_search = false;
			if ( isset( $settings_db[0]['wpda_table_settings'] ) ) {
				$sql_dml = 'UPDATE';
				$settings_object = json_decode( $settings_db[0]['wpda_table_settings'] );
				if ( isset( $settings_object->search_settings->search_type ) ) {
					$search_type = $settings_object->search_settings->search_type;
				}
				if ( isset( $settings_object->search_settings->full_text_modifier ) ) {
					$fulltext_type = $settings_object->search_settings->full_text_modifier;
				}
				if ( isset( $settings_object->search_settings->no_search_no_rows ) ) {
					$no_search_no_rows = $settings_object->search_settings->no_search_no_rows;
				}
				if ( isset( $settings_object->search_settings->column_specific_search ) ) {
					$column_specific_search = $settings_object->search_settings->column_specific_search;
				}
				if ( isset( $settings_object->search_settings->search_columns ) ) {
					$search_columns = $settings_object->search_settings->search_columns;
				}
				if ( isset( $settings_object->search_settings->individual_search_columns ) ) {
					$individual_search_columns = $settings_object->search_settings->individual_search_columns;
				}
				if ( isset( $settings_object->search_settings->listbox_columns ) ) {
					$listbox_columns = $settings_object->search_settings->listbox_columns;
				}
			} else {
				$sql_dml = 'INSERT';
			}
			?>
			<li>
				<span class="wpda_table_settings_caret"><?php echo __( 'Search Settings', 'wp-data-access' ); ?></span>
				<a href="https://wpdataaccess.com/docs/documentation/data-explorer/search-settings/" target="_blank">
					<span class="dashicons dashicons-editor-help wpda_tooltip"
						  title="<?php echo __( 'Define search columns [help opens in a new tab or window]', 'wp-data-access' ); ?>"
						  style="cursor:pointer"></span>
				</a>
				<ul class="wpda_table_settings_nested">
					<style type="text/css">
						.wpda_table_settings_search tr:nth-child(2) td {
							border-top: 1px solid #555;
							padding-top: 5px;
						}
					</style>
					<script type="text/javascript">
						var <?php echo $table_name; ?>_fullindex_columns = [];
						<?php
						foreach ( $fulltext_index as $key => $val ) {
							?>
							<?php echo $table_name; ?>_fullindex_columns.push('<?php echo $key; ?>');
							<?php
						}
						?>

						function <?php echo $table_name; ?>_toggle_search_type() {
							search_type = jQuery("input[name='<?php echo esc_attr( $table_name ); ?>_search_type']:checked").val();
							if (search_type==='fulltext') {
								jQuery('#<?php echo $table_name; ?>_switch_to_full_text_search').show();
							} else {
								jQuery('#<?php echo $table_name; ?>_switch_to_full_text_search').hide();
							}
							<?php
							foreach ( $table_structure as $key => $value ) {
								?>
								if (search_type==='fulltext') {
									if (!<?php echo $table_name; ?>_fullindex_columns.includes('<?php echo esc_attr( $value['Field'] ); ?>')) {
										jQuery('#has_fulltext_index_<?php echo esc_attr( $table_name ); ?>_<?php echo esc_attr( $value['Field'] ); ?>').hide();
										var msg = jQuery('#create_fulltext_index_msg_<?php echo esc_attr( $table_name ); ?>_<?php echo esc_attr( $value['Field'] ); ?>').attr('data-status');
										if (msg==='' || msg==='undefined') {
											jQuery('#create_fulltext_index_<?php echo esc_attr( $table_name ); ?>_<?php echo esc_attr( $value['Field'] ); ?>').show();
										}
									} else {
										jQuery('#has_fulltext_index_<?php echo esc_attr( $table_name ); ?>_<?php echo esc_attr( $value['Field'] ); ?>').show();
										jQuery('#create_fulltext_index_<?php echo esc_attr( $table_name ); ?>_<?php echo esc_attr( $value['Field'] ); ?>').hide();
									}
								} else {
									jQuery('#has_fulltext_index_<?php echo esc_attr( $table_name ); ?>_<?php echo esc_attr( $value['Field'] ); ?>').hide();
									jQuery('#create_fulltext_index_<?php echo esc_attr( $table_name ); ?>_<?php echo esc_attr( $value['Field'] ); ?>').hide();
								}
								<?php
							}
							?>
						}

						jQuery(function () {
							jQuery("#wpda_invisible_container").append("<?php echo $settings_table_form; ?>");

							jQuery('#wpda_<?php echo $table_name; ?>_save_search_settings').click( function() {
								search_columns = [];
								individual_search_columns = [];
								listbox_columns = [];

								var column_specific_search = String(jQuery("#<?php echo esc_attr( $table_name ); ?>_column_specific_search").is(':checked'));
								var column_specific_search_old = jQuery("#<?php echo esc_attr( $table_name ); ?>_column_specific_search_old").val();
								<?php
									foreach ( $table_structure as $key => $value ) {
										?>
											if (jQuery('#cb_<?php echo esc_attr( $table_name ); ?>_<?php echo esc_attr( $value['Field'] ); ?>').is(':checked')) {
												search_columns.push('<?php echo esc_attr( $value['Field'] ); ?>');
											}
											if (jQuery('#id_<?php echo esc_attr( $table_name ); ?>_<?php echo esc_attr( $value['Field'] ); ?>').is(':checked')) {
												individual_search_columns.push('<?php echo esc_attr( $value['Field'] ); ?>');
											}
											if (jQuery('#lb_<?php echo esc_attr( $table_name ); ?>_<?php echo esc_attr( $value['Field'] ); ?>').is(':checked')) {
												listbox_columns.push('<?php echo esc_attr( $value['Field'] ); ?>');
											}
										<?php
									}
								?>

								return submit_search_settings('<?php echo $table_name; ?>','<?php echo $sql_dml; ?>','<?php echo $settings_table_form_settings; ?>','<?php echo $settings_table_form_id; ?>',search_columns,<?php echo $table_name; ?>_fullindex_columns,listbox_columns,individual_search_columns);
							});

							jQuery('#wpda_<?php echo $table_name; ?>_cancel_search_settings').click( function() {
								jQuery('#wpda_admin_menu_actions_<?php echo esc_attr( $_REQUEST['rownum'] ); ?>').toggle();
								wpda_toggle_row_actions('<?php echo esc_attr( $_REQUEST['rownum'] ); ?>');
							});
						});
					</script>
					<table>
						<tr>
							<td style="padding-left:0;">
								<label class="wpda_action_font">
									<input type="radio" name="<?php echo esc_attr( $table_name ); ?>_search_type" value="normal"
										<?php echo 'normal'===$search_type?'checked':''; ?>
									    onchange="<?php echo $table_name; ?>_toggle_search_type();"
									/>
									Normal wildcard search
								</label>
								<span
									class="dashicons dashicons-editor-help wpda_tooltip"
									title="<?php echo __( 'Wildcards are automatically added to CHAR, VARCHAR and TEXT columns. Users can add additional specific wildcards. All other columns must exactly match the search condition.', 'wp-data-access' ); ?>"
									style="cursor:pointer">
								</span>
								<br/>
								<label class="wpda_action_font">
									<input type="radio" name="<?php echo esc_attr( $table_name ); ?>_search_type" value="explicit"
										<?php echo 'explicit'===$search_type?'checked':''; ?>
										   onchange="<?php echo $table_name; ?>_toggle_search_type();"
									/>
									Normal exact search
								</label>
								<span
										class="dashicons dashicons-editor-help wpda_tooltip"
										title="<?php echo __( 'Performs an exact search on all columns. Allows users to add wildcards to CHAR, VARCHAR and TEXT columns.', 'wp-data-access' ); ?>"
										style="cursor:pointer">
								</span>
								<br/>
								<label class="wpda_action_font">
									<input type="radio" name="<?php echo esc_attr( $table_name ); ?>_search_type" value="fulltext"
										<?php echo 'fulltext'===$search_type?'checked':''; ?>
									    onchange="<?php echo $table_name; ?>_toggle_search_type();"
										<?php echo $fulltext_available ? '' : 'disabled="true"'; ?>
									/>
									Full-text search
								</label>
								<span
									class="dashicons dashicons-editor-help wpda_tooltip"
									title="<?php echo __( 'Only available for:
- InnoDB and MyISAM tables and columns of views containing a full-text index 
- CHAR, VARCHAR and TEXT columns', 'wp-data-access' ); ?>"
									style="cursor:pointer">
								</span>
							</td>
							<td>&nbsp;&nbsp;&nbsp;</td>
							<td>
								<div id="<?php echo esc_attr( $table_name ); ?>_switch_to_full_text_search" <?php echo 'fulltext'===$search_type?'':'style="display: none;"'; ?>>
									<label style="padding-top: 10px;">
										<input type="radio" name="<?php echo esc_attr( $table_name ); ?>_full_text_modifier" value="natural"
											<?php echo 'natural'===$fulltext_type?'checked':''; ?>
										/>
										in natural language mode
									</label>
									<br/>
									<label>
										<input type="radio" name="<?php echo esc_attr( $table_name ); ?>_full_text_modifier" value="boolean"
											<?php echo 'boolean'===$fulltext_type?'checked':''; ?>
										/>
										in boolean mode
									</label>
									<br/>
									<label>
										<input type="radio" name="<?php echo esc_attr( $table_name ); ?>_full_text_modifier" value="expansion"
											<?php echo 'expansion'===$fulltext_type?'checked':''; ?>
										/>
										with query expansion
									</label>
								</div>
							</td>
						</tr>
					</table>
					<table class="wpda_table_settings wpda_table_settings_search" style="border-collapse: inherit;">
						<tr>
							<th style="padding-left:0;vertical-align:bottom;"><?php echo __( 'Column', 'wp-data-access' ); ?></th>
							<th style="vertical-align: bottom;"><?php echo __( 'Type', 'wp-data-access' ); ?></th>
							<th>
								<?php echo __( 'Queryable', 'wp-data-access' ); ?>
								<span
									class="dashicons dashicons-editor-help wpda_tooltip"
									title="<?php echo __( 'Make column to queryable.', 'wp-data-access' ); ?>"
									style="cursor:pointer;vertical-align:bottom;">
								</span>
							</th>
							<th>
								<?php echo __( 'Individual', 'wp-data-access' ); ?>
								<span
										class="dashicons dashicons-editor-help wpda_tooltip"
										title="<?php echo __( 'Enable/disable individual column search.', 'wp-data-access' ); ?>"
										style="cursor:pointer;vertical-align:bottom;">
								</span>
							</th>
							<th>
								<?php echo __( 'Listbox', 'wp-data-access' ); ?>
								<span
										class="dashicons dashicons-editor-help wpda_tooltip"
										title="<?php echo __( 'Adds a listbox containing all available column values if allow column specific search is enabled. Increases load time! Do not use this options for columns with a large number of different values.', 'wp-data-access' ); ?>"
										style="cursor:pointer;vertical-align:bottom;">
								</span>
							</th>
						</tr>
						<?php
						foreach ( $table_structure as $key => $value ) {
							$find_rb = strpos( $value['Type'], '(' );
							if ( false === $find_rb ) {
								$column_type = $value['Type'];
							} else {
								$column_type = substr( $value['Type'], 0, $find_rb );
							}
							$data_type = WPDA::get_type( $column_type );
							if ( isset( $search_columns ) && is_array( $search_columns ) ) {
								$default = in_array( $value['Field'], $search_columns ) ? 'checked' : '';
							} else {
								$default = 'string' === $data_type ? 'checked' : '';
							}
							if ( ! $column_specific_search ) {
								$default_individual = 'disabled';
							} else {
								if ( isset( $individual_search_columns ) && is_array( $individual_search_columns ) ) {
									if ( '' === $default ) {
										$default_individual = 'disabled';
									} else {
										$default_individual = in_array( $value['Field'], $individual_search_columns ) ? 'checked' : '';
									}
								} else {
									$default_individual = 'string' === $data_type ? 'checked' : '';
								}
							}
							if ( isset( $listbox_columns ) && is_array( $listbox_columns ) ) {
								$default_listbox = in_array( $value['Field'], $listbox_columns ) ? 'checked' : '';
							} else {
								$default_listbox = '';
							}
							?>
							<tr>
								<td style="padding-left:0;"><?php echo esc_attr( $value['Field'] ); ?></td>
								<td><?php echo esc_attr( $value['Type'] ); ?></td>
								<td>
									<input type="checkbox"
										   id="cb_<?php echo esc_attr( $table_name ); ?>_<?php echo esc_attr( $value['Field'] ); ?>"
										   <?php echo $default; ?>
									/>
									<input type="hidden"
										   id="dt_<?php echo esc_attr( $table_name ); ?>_<?php echo esc_attr( $value['Field'] ); ?>"
										   value="<?php echo esc_attr( $data_type ); ?>"
										   class="wpda_check_data_type_<?php echo esc_attr( $table_name ); ?>"
									/>
									<?php
									if ( 'string' === $data_type ) {
										$wpda_fulltext_index_in_progress = get_option( self::WPDA_FULLTEXT_INDEX_IN_PROGRESS );
										$msg = '';
										if ( false !== $wpda_fulltext_index_in_progress && is_array( $wpda_fulltext_index_in_progress ) ) {
											$sql_schema_name = '' === $schema_name ? '' : "`$schema_name`.";
											if ( isset( $wpda_fulltext_index_in_progress[ "{$sql_schema_name}`{$table_name}`.`{$value['Field']}`" ] ) ) {
												if ( self::fulltext_index_exists( $schema_name, $table_name, $value['Field'] ) ) {
													if ( ( $key = array_search( "{$sql_schema_name}`{$table_name}`.`{$value['Field']}`", $wpda_fulltext_index_in_progress ) ) !== false ) {
														unset( $wpda_fulltext_index_in_progress[ $key ] );
														update_option( self::WPDA_FULLTEXT_INDEX_IN_PROGRESS, $wpda_fulltext_index_in_progress );
													}
												} else {
													$msg =
														'Building full-text index... ' .
														'<a href="javascript:void(0)" onclick="fulltext_index_check_status(\'' . $schema_name . '\',\'' . $table_name . '\',\'' . $value['Field'] . '\',\'' . $wp_nonce_settings . '\')" class="dashicons dashicons-image-rotate wpda_tooltip" title="Refresh"></a>' .
														'<a href="javascript:void(0)" onclick="if (confirm(\'Cancel operation?\')) { fulltext_index_cancel(\'' . $schema_name . '\',\'' . $table_name . '\',\'' . $value['Field'] . '\',\'' . $wp_nonce_settings . '\'); }" class="dashicons dashicons-dismiss wpda_tooltip" title="Cancel"></a>';
												}
											}
										}
										?>
										<span
											id="has_fulltext_index_<?php echo esc_attr( $table_name ); ?>_<?php echo esc_attr( $value['Field'] ); ?>"
											class="dashicons dashicons-thumbs-up wpda_tooltip"
											title="<?php echo __( 'Ready to go! A full-text index for this column is available.', 'wp-data-access' ); ?>"
											style="cursor:pointer;height:14px;<?php echo ( isset( $fulltext_index[ $value['Field'] ] ) && 'fulltext' === $search_type ) ? '' : 'display:none;'; ?>"
										>
										</span>
										<span
											id="create_fulltext_index_<?php echo esc_attr( $table_name ); ?>_<?php echo esc_attr( $value['Field'] ); ?>"
											class="dashicons dashicons-admin-generic wpda_tooltip"
											title="<?php echo __( 'A full-text index for this column must be created to support full-text search. Click icon to create a full-text index for this column.', 'wp-data-access' ); ?>"
											style="cursor:pointer;height:14px;<?php echo ( ! isset( $fulltext_index[ $value['Field'] ] ) && 'fulltext' === $search_type ) ? '' : 'display:none;'; ?>"
											onclick="create_fulltext_index('<?php echo esc_attr( $schema_name ); ?>', '<?php echo esc_attr( $table_name ); ?>', '<?php echo esc_attr( $value['Field'] ); ?>', '<?php echo $wp_nonce_settings; ?>', <?php echo $table_name; ?>_fullindex_columns)"
										>
										</span>
										<span id="create_fulltext_index_msg_<?php echo esc_attr( $table_name ); ?>_<?php echo esc_attr( $value['Field'] ); ?>"
											  style="vertical-align:middle;padding-left:3px;"
											  data-status="<?php echo ''===$msg ? '' : 'building';?>"
										>
											<?php echo $msg; ?>
										</span>
										<?php
									}
									?>
								</td>
								<td>
									<input type="checkbox"
										   class="wpda_individual_column_search_selection"
										   id="id_<?php echo esc_attr( $table_name ); ?>_<?php echo esc_attr( $value['Field'] ); ?>"
										   <?php echo $default_individual; ?>
									/>
								</td>
								<td>
									<input type="checkbox"
										   id="lb_<?php echo esc_attr( $table_name ); ?>_<?php echo esc_attr( $value['Field'] ); ?>"
										<?php echo $default_listbox; ?>
									/>
									<a href="javascript:void(0)"
									   class="wpda_tooltip"
									   style="font-weight:bold;vertical-align:text-top;"
									   title="<?php echo __( 'Click to calculate the number of items shown in the listbox for this column.', 'wp-data-access' ); ?>"
									   onclick="calculate_no_listbox_items('<?php echo esc_attr( $schema_name ); ?>', '<?php echo esc_attr( $table_name ); ?>', '<?php echo esc_attr( $value['Field'] ); ?>', '<?php echo $wp_nonce_settings; ?>')"
									>&Sigma;</a>
									&nbsp;
									<span id="lb_msg_<?php echo esc_attr( $table_name ); ?>_<?php echo esc_attr( $value['Field'] ); ?>"
										  style="vertical-align:text-top;"
									>
									</span>
								</td>
							</tr>
							<?php
						}
						?>
					</table>
					<br/>
					<div>
						<label class="wpda_action_font">
							<input type="checkbox"
								   id="<?php echo esc_attr( $table_name ); ?>_no_search_no_rows"
								   <?php echo $no_search_no_rows ? 'checked' : ''; ?>
							/>
							No search condition = show no rows
						</label>
						<span
							class="dashicons dashicons-editor-help wpda_tooltip"
							title="<?php echo __( 'No rows are shown on startup. Table only shows rows that fulfill the search condition.', 'wp-data-access' ); ?>"
							style="cursor:pointer">
						</span>
						<br/>
						<label class="wpda_action_font">
							<input type="checkbox"
								   id="<?php echo esc_attr( $table_name ); ?>_column_specific_search"
									<?php echo $column_specific_search ? 'checked' : ''; ?>
									onchange="jQuery('.wpda_individual_column_search_selection').prop('disabled', !jQuery(this).is(':checked'))"
							/>
							<input type="hidden"
								   id="<?php echo esc_attr( $table_name ); ?>_column_specific_search_old"
								   value="<?php echo $column_specific_search ? 'true' : 'false'; ?>"
							/>
							Allow column specific search
						</label>
						<span
							class="dashicons dashicons-editor-help wpda_tooltip"
							title="<?php echo __( 'Adds an individual search item to every queryable column.', 'wp-data-access' ); ?>"
							style="cursor:pointer">
						</span>
						<div id="wpda_pro_search_messages_<?php echo esc_attr( $table_name ); ?>" style="display: none;">
							<br/>
							<span id="wpda_pro_search_messages_text_<?php echo esc_attr( $table_name ); ?>"></span>
						</div>
					</div>
					<br/>
					<div>
						<button type="button"
							   	id="wpda_<?php echo $table_name; ?>_save_search_settings"
							   	class="button button-primary wpda_action_font">
							<i class="fas fa-check wpda_icon_on_button"></i>
							<?php echo __( 'Save Search Settings', 'wp-data-access' ); ?>
						</button>
						<button type="button"
							   	id="wpda_<?php echo $table_name; ?>_cancel_search_settings"
							   	class="button button-secondary wpda_action_font">
							<i class="fas fa-times-circle wpda_icon_on_button"></i>
							<?php echo __( 'Cancel', 'wp-data-access' ); ?>
						</button>
					</div>
				</ul>
			</li>
			<?php
		}

		public static function add_search_actions( $schema_name, $table_name, $table_settings, $wpda_list_columns ) {
			if ( isset( $table_settings->search_settings->column_specific_search ) && $table_settings->search_settings->column_specific_search ) {
				?>
				<input type="submit"
					   href="javascript:void(0)"
					   style="float:right;margin-left:4px;"
					   class="button wpda_tooltip"
					   value="<?php echo __( '...', 'wp-data-access' ); ?>"
					   title="<?php echo __( 'Search on individual columns.', 'wp-data-access' ); ?>"
					   onclick="jQuery('#wpda_search_<?php echo esc_attr( $table_name ); ?>').toggle(); return false;"
				/>
				<?php
			}
		}

		public static function add_search_filter( $schema_name, $table_name, $table_settings, $wpda_list_columns ) {
			if (
				isset( $table_settings->search_settings->search_columns ) &&
				is_array( $table_settings->search_settings->search_columns ) &&
				isset( $table_settings->search_settings->column_specific_search ) &&
				$table_settings->search_settings->column_specific_search
			) {
				$listbox_columns = isset( $table_settings->search_settings->listbox_columns ) ? $table_settings->search_settings->listbox_columns : [];
				?>
				<style>
					.wpda_table_settings_search {
						background-color: #fff;
						border: 1px solid #ccd0d4;
						padding: 20px;
					}
					.wpda_table_settings_search_items {
						display: flex;
						flex-direction: row;
						flex-wrap: wrap;
					}
					.wpda_table_settings_search_label {
						font-weight: bold;
						padding-left: 5px;
						padding-bottom: 10px;
					}
					.wpda_table_settings_search_bar {
						text-align: right;
					}
				</style>
				<div id="wpda_search_<?php echo esc_attr( $table_name ); ?>"
					 style="display:none;clear:both;padding-top:10px;">
					<div class="wpda_table_settings_search">
						<div class="wpda_table_settings_search_items">
							<?php
							$columns_visible = $wpda_list_columns->get_table_column_headers();
							if (
								isset( $table_settings->search_settings->individual_search_columns ) &&
								is_array( $table_settings->search_settings->individual_search_columns )
							) {
								$search_columns = $table_settings->search_settings->individual_search_columns;
							} else {
								$search_columns = $table_settings->search_settings->search_columns;
							}

							$search_values_found = false;
							foreach ( $search_columns as $search_column ) {
								if ( isset( $columns_visible[ $search_column ] ) ) {
									$search_column_value =
										isset( $_REQUEST[ "wpda_search_{$search_column}" ] ) ?
											sanitize_text_field( wp_unslash( $_REQUEST[ "wpda_search_{$search_column}" ] ) ) : ''; // input var okay.

									$search_values_found = $search_values_found || '' !== $search_column_value;
									?>
									<span>
										<label class="wpda_table_settings_search_label">
											<?php echo esc_attr( $wpda_list_columns->get_column_label($search_column) ); ?>
										</label>
										<br/>
									<?php
									if ( isset( $table_settings->search_settings->listbox_columns ) && in_array( $search_column, $table_settings->search_settings->listbox_columns ) ) {
										$lookup        = null;
										$lookup_column = null;

										global $wpda_project_mode;
										if ( null !== $wpda_project_mode ) {
											// We are on a project page
											$relationships = WPDP_Project_Design_Table_Model::get_column_options( $table_name, 'relationships', $wpda_project_mode['setname'], $schema_name );
											if ( isset( $relationships['relationships'] ) ) {
												foreach ( $relationships['relationships'] as $relationship ) {
													if (
														'lookup' === $relationship->relation_type &&
														$relationship->source_column_name[0] === $search_column
													) {
														$listtable_lookups = WPDP_Project_Design_Table_Model::get_column_options( $table_name, 'listtable', $wpda_project_mode['setname'], $schema_name );
														foreach ( $listtable_lookups as $listtable_lookup ) {
															if ( $listtable_lookup->column_name === $search_column ) {
																$lookup        = $relationship;
																$lookup_column = $listtable_lookup->lookup;
															}
														}
													}
												}
											}
										}
										// create listbox containing all available column values
										$resultset = self::get_column_values( $schema_name, $table_name, $search_column, $lookup, $lookup_column );
										?>
										<select name="wpda_search_<?php echo esc_attr( $search_column ); ?>">
											<option value="" <?php echo $search_column_value==='' ? 'selected' : ''; ?>></option>
											<?php
											foreach ( $resultset as $value ) {
												$column_value = $value['column_value'];
												$column_text  = isset( $value['column_lookup'] ) ? $value['column_lookup'] : $column_value;
												$selected     = $search_column_value===$column_value ? ' selected' : '';
												?>
												<option value="<?php echo $column_value; ?>"<?php echo $selected; ?>><?php echo $column_text; ?></option>
											<?php
											}
											?>
										</select>
										<?php
									} else {
										// create standard search item
										?>
										<input name="wpda_search_<?php echo esc_attr( $search_column ); ?>"
											   value="<?php echo esc_attr( $search_column_value ); ?>"
											   type="text"
											   class="wpda_individual_search_item"
										/>
										<?php
									}
									?>
									</span>
									<?php
								}
							}
							?>
						</div>
						<div class="wpda_table_settings_search_bar">
							<input type="submit" class="button" value="search" style="vertical-align:baseline;"/>
							<input type="button" class="button" value="clear" style="vertical-align:baseline;"
								   onclick="jQuery('.wpda_individual_search_item').val('');"
							/>
						</div>
					</div>
				</div>
				<?php
				if ( $search_values_found ) {
					?>
					<script type="text/javascript">
						jQuery(function() {
							jQuery('#wpda_search_<?php echo esc_attr( $table_name ); ?>').toggle();
						});
					</script>
					<?php
				}
				?>
				<script type="text/javascript">
					jQuery(function() {
						jQuery(".wpda_individual_search_item").on("keypress", function(event) {
							if (event.which === 13) {
								event.preventDefault();
								jQuery("#wpda_main_form").submit();
							}
						});
					});
				</script>
				<?php
			}
		}

		public static function create_fulltext_index() {
			ignore_user_abort(true);
			set_time_limit(0);
			
			header('Content-Type: text/plain; charset=utf-8');
			if (
				isset( $_REQUEST['wpdaschema_name'] ) &&
				isset( $_REQUEST['table_name'] ) &&
				isset( $_REQUEST['column_name'] ) &&
				isset( $_REQUEST['wpnonce'] )
			) {
				// Get arguments needed to check authorization
				$schema_name = sanitize_text_field( wp_unslash( $_REQUEST['wpdaschema_name'] ) ); // input var okay.
				$table_name  = str_replace( '`', '', sanitize_text_field( wp_unslash( $_REQUEST['table_name'] ) ) ); // input var okay.
				$column_name = str_replace( '`', '', sanitize_text_field( wp_unslash( $_REQUEST['column_name'] ) ) ); // input var okay.

				// Check if actions is allowed
				$wp_nonce = isset( $_REQUEST['wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpnonce'] ) ) : ''; // input var okay.
				if ( ! wp_verify_nonce( $wp_nonce, "wpda-settings-{$table_name}" ) ) {
					echo 'INV-Not authorized';
					return;
				}

				// Start create index
				$wpdadb	= WPDADB::get_db_connection( $schema_name );
				if ( null === $wpdadb ) {
					wp_die( sprintf( __( 'ERROR - Remote database %s not available', 'wp-data-access' ), $schema_name ) );
				}

				$wpdadb->suppress_errors( true );

				$sql_schema_name = '' === $schema_name ? '' : "`$schema_name`.";
				$sql             = "alter table `{$wpdadb->dbname}`.`{$table_name}` add fulltext(`{$column_name}`)";

				$wpda_fulltext_index_in_progress = get_option( self::WPDA_FULLTEXT_INDEX_IN_PROGRESS );
				if ( ! $wpda_fulltext_index_in_progress ) {
					$wpda_fulltext_index_in_progress = [];
				}
				$wpda_fulltext_index_in_progress[ "{$sql_schema_name}`{$table_name}`.`{$column_name}`" ] = true;
				update_option( self::WPDA_FULLTEXT_INDEX_IN_PROGRESS, $wpda_fulltext_index_in_progress );

				$result = $wpdadb->query( $sql );
				if ( false === $result ) {
					echo 'ERR-' . $wpdadb->last_error;
				} else {
					$wpda_fulltext_index_in_progress = get_option( self::WPDA_FULLTEXT_INDEX_IN_PROGRESS );
					if ( false !== $wpda_fulltext_index_in_progress && is_array( $wpda_fulltext_index_in_progress ) ) {
						if ( ( $key = array_search( "{$sql_schema_name}`{$table_name}`.`{$column_name}`", $wpda_fulltext_index_in_progress ) ) !== false ) {
							unset( $wpda_fulltext_index_in_progress[ $key ] );
							update_option( self::WPDA_FULLTEXT_INDEX_IN_PROGRESS, $wpda_fulltext_index_in_progress );
						}
					}

					echo 'DONE';
				}
			} else {
				echo 'INV-Wrong arguments';
			}
		}

		protected static function fulltext_index_exists( $schema_name, $table_name, $column_name ) {
			$query = '
					select * 
					from information_schema.statistics
					where table_schema = %s
					  and table_name = %s
					  and column_name = %s
					  and index_type = \'FULLTEXT\'
					  and seq_in_index = 1
					  and not exists (
					  		select *
					  		from information_schema.statistics
							where table_schema = %s
							  and table_name = %s
							  and column_name = %s
							  and index_type = \'FULLTEXT\'
							  and seq_in_index > 1
					  	) 
					';

			$wpdadb = WPDADB::get_db_connection( $schema_name );
			if ( null === $wpdadb ) {
				wp_die( sprintf( __( 'ERROR - Remote database %s not available', 'wp-data-access' ), $schema_name ) );
			}

			$wpdadb->get_results(
				$wpdadb->prepare(
					$query,
					[
						$schema_name,
						$table_name,
						$column_name,
						$schema_name,
						$table_name,
						$column_name,
					]
				),
				'ARRAY_A'
			); // phpcs:ignore Standard.Category.SniffName.ErrorCode

			return ( 1 === $wpdadb->num_rows );
		}

		public static function check_fulltext_index() {
			header('Content-Type: text/plain; charset=utf-8');
			if (
				isset( $_REQUEST['wpdaschema_name'] ) &&
				isset( $_REQUEST['table_name'] ) &&
				isset( $_REQUEST['column_name'] ) &&
				isset( $_REQUEST['wpnonce'] )
			) {
				// Get arguments needed to check authorization
				$schema_name = sanitize_text_field( wp_unslash( $_REQUEST['wpdaschema_name'] ) ); // input var okay.
				$table_name  = sanitize_text_field( wp_unslash( $_REQUEST['table_name'] ) ); // input var okay.
				$column_name = sanitize_text_field( wp_unslash( $_REQUEST['column_name'] ) ); // input var okay.

				// Check if actions is allowed
				$wp_nonce = isset( $_REQUEST['wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpnonce'] ) ) : ''; // input var okay.
				if ( ! wp_verify_nonce( $wp_nonce, "wpda-settings-{$table_name}" ) ) {
					echo 'INV-Not authorized';
					return;
				}

				if ( self::fulltext_index_exists( $schema_name, $table_name, $column_name ) ) {
					$wpda_fulltext_index_in_progress = get_option( self::WPDA_FULLTEXT_INDEX_IN_PROGRESS );
					if ( false !== $wpda_fulltext_index_in_progress && is_array( $wpda_fulltext_index_in_progress ) ) {
						$sql_schema_name = '' === $schema_name ? '' : "`$schema_name`.";
						if ( ( $key = array_search( "{$sql_schema_name}`{$table_name}`.`{$column_name}`", $wpda_fulltext_index_in_progress ) ) !== false ) {
							unset( $wpda_fulltext_index_in_progress[ $key ] );
							update_option( self::WPDA_FULLTEXT_INDEX_IN_PROGRESS, $wpda_fulltext_index_in_progress );
						}
					}

					echo 'OK';
				} else {
					echo 'NOTFOUND';
				}
			} else {
				echo 'INV-Wrong arguments';
			}
		}

		protected static function get_column_values( $schema_name, $table_name, $column_name, $lookup = null, $lookup_column = null ) {
			$wpdadb	= WPDADB::get_db_connection( $schema_name );
			if ( null === $wpdadb ) {
				if ( is_admin() ) {
					wp_die( sprintf( __( 'ERROR - Remote database %s not available', 'wp-data-access' ), $schema_name ) );
				} else {
					die( sprintf( __( 'ERROR - Remote database %s not available', 'wp-data-access' ), $schema_name ) );
				}
			}

			$base_table = "`{$wpdadb->dbname}`.`" . str_replace( '`', '', $table_name ) . "`";
			if ( null === $lookup || null === $lookup_column ) {
				$query = "
					select distinct `" . str_replace( '`', '', $column_name ) . "` as column_value
					from {$base_table}
					order by `" . str_replace( '`', '', $column_name ) . "`
				";
			} else {
				$lookup_table = "`{$lookup->target_schema_name}`.`{$lookup->target_table_name}`";
				$query = "
					select distinct `" . str_replace( '`', '', $table_name ) . "`.`" . str_replace( '`', '', $column_name ) . "` as column_value,
					`" . str_replace( '`', '', $lookup_column ) . "` as column_lookup
					from {$base_table}, {$lookup_table}
					where `{$lookup->target_table_name}`.`{$lookup->target_column_name[0]}` = `" . str_replace( '`', '', $table_name ) . "`.`{$lookup->source_column_name[0]}`
					order by `" . str_replace( '`', '', $table_name ) . "`.`" . str_replace( '`', '', $column_name ) . "`
				";
			}

			return $wpdadb->get_results( $query, 'ARRAY_A' );
		}

		public static function no_listbox_items() {
			header('Content-Type: text/plain; charset=utf-8');
			if (
				isset( $_REQUEST['wpdaschema_name'] ) &&
				isset( $_REQUEST['table_name'] ) &&
				isset( $_REQUEST['column_name'] ) &&
				isset( $_REQUEST['wpnonce'] )
			) {
				// Get arguments needed to check authorization
				$schema_name = sanitize_text_field( wp_unslash( $_REQUEST['wpdaschema_name'] ) ); // input var okay.
				$table_name  = sanitize_text_field( wp_unslash( $_REQUEST['table_name'] ) ); // input var okay.
				$column_name = sanitize_text_field( wp_unslash( $_REQUEST['column_name'] ) ); // input var okay.

				// Check if actions is allowed
				$wp_nonce = isset( $_REQUEST['wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpnonce'] ) ) : ''; // input var okay.
				if ( ! wp_verify_nonce( $wp_nonce, "wpda-settings-{$table_name}" ) ) {
					echo 'INV-Not authorized';
					return;
				}

				$resultset = self::get_column_values( $schema_name, $table_name, $column_name );

				echo sizeof( $resultset ) . ' rows';
			} else {
				echo 'INV-Wrong arguments';
			}
		}

		public static function cancel_fulltext_index() {
			header('Content-Type: text/plain; charset=utf-8');
			if (
				isset( $_REQUEST['wpdaschema_name'] ) &&
				isset( $_REQUEST['table_name'] ) &&
				isset( $_REQUEST['column_name'] ) &&
				isset( $_REQUEST['wpnonce'] )
			) {
				// Get arguments needed to check authorization
				$schema_name = sanitize_text_field( wp_unslash( $_REQUEST['wpdaschema_name'] ) ); // input var okay.
				$table_name  = sanitize_text_field( wp_unslash( $_REQUEST['table_name'] ) ); // input var okay.
				$column_name = sanitize_text_field( wp_unslash( $_REQUEST['column_name'] ) ); // input var okay.

				// Check if actions is allowed
				$wp_nonce = isset( $_REQUEST['wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpnonce'] ) ) : ''; // input var okay.
				if ( ! wp_verify_nonce( $wp_nonce, "wpda-settings-{$table_name}" ) ) {
					echo 'INV-Not authorized';
					return;
				}

				$wpda_fulltext_index_in_progress = get_option( self::WPDA_FULLTEXT_INDEX_IN_PROGRESS );
				if ( false !== $wpda_fulltext_index_in_progress && is_array( $wpda_fulltext_index_in_progress ) ) {
					$sql_schema_name = '' === $schema_name ? '' : "`$schema_name`.";
					if ( ( $key = array_search( "{$sql_schema_name}`{$table_name}`.`{$column_name}`", $wpda_fulltext_index_in_progress ) ) !== false ) {
						unset( $wpda_fulltext_index_in_progress[ $key ] );
						update_option( self::WPDA_FULLTEXT_INDEX_IN_PROGRESS, $wpda_fulltext_index_in_progress );
					}
				}

				echo 'OK';
			} else {
				echo 'INV-Wrong arguments';
			}
		}

		public static function construct_where_clause( $where, $schema_name, $table_name, $columns, $search ) {
			if ( '' !== $where ) {
				return $where;
			}

			$table_settings = WPDA_Table_Settings_Model::query( $table_name, $schema_name );
			if ( 0 === sizeof( $table_settings ) || ! isset( $table_settings[0]['wpda_table_settings'] ) ) {
				return null;
			}

			$table_settings_json = json_decode( $table_settings[0]['wpda_table_settings'] );
			if ( isset( $table_settings_json->search_settings ) ) {
				if (
					isset( $table_settings_json->search_settings->search_type ) ||
					isset( $table_settings_json->search_settings->full_text_modifier ) ||
					isset( $table_settings_json->search_settings->no_search_no_rows ) ||
					isset( $table_settings_json->search_settings->search_columns )
				) {
					$search_type            = $table_settings_json->search_settings->search_type;
					$full_text_modifier     = $table_settings_json->search_settings->full_text_modifier;
					$no_search_no_rows      = $table_settings_json->search_settings->no_search_no_rows;
					$search_columns         = $table_settings_json->search_settings->search_columns;
					if ( isset( $table_settings_json->search_settings->listbox_columns ) ) {
						$listbox_columns = $table_settings_json->search_settings->listbox_columns;
					} else {
						$listbox_columns = [];
					}
					$column_specific_search =
						isset( $table_settings_json->search_settings->column_specific_search ) ?
						$table_settings_json->search_settings->column_specific_search : false;

					global $wpda_search_args;
					if ( '' !== $wpda_search_args ) {
						$no_search_no_rows = false;
					}

					if (
						! $column_specific_search &&
						$no_search_no_rows &&
						( '' === trim( $search ) || null === $search )
					) {
						// No search criteria entered + no search no rows
						return ' 1=3 '; // Show no data
					}

					if (
						! $column_specific_search &&
						( '' === trim( $search ) || null === $search )
					) {
						return ''; // No search criteria entered: empty where clause
					}

					if ( ! is_array( $search_columns ) ) {
						return null; // No column info available: perform standard search (should not happen: ERROR)
					}

					$column_specific_search_columns = [];
					if ( $column_specific_search ) {
						// Check if individual column search criteria was entered
						foreach ( $search_columns as $search_column ) {
							if (
								isset( $_REQUEST[ "wpda_search_{$search_column}" ] ) &&
								'' !== trim( $_REQUEST[ "wpda_search_{$search_column}" ] )
							) {
								$column_specific_search_columns[ $search_column ] =
									wp_unslash( $_REQUEST[ "wpda_search_{$search_column}" ] ); // input var okay.
							}
						}

						if (
							0 === sizeof( $column_specific_search_columns ) &&
							$no_search_no_rows &&
							( '' === trim( $search ) || null === $search )
						) {
							// No search criteria entered + no search no rows
							return ' 1=3 '; // Show no data
						}

						if (
							0 === sizeof( $column_specific_search_columns ) &&
							( '' === trim( $search ) || null === $search )
						) {
							// No search criteria for individual columns + no global criteria
							return '';
						}
					}

					global $wpdb;

					$where_columns                    = [];
					$fulltext_mode                    = self::fulltext_mode( $full_text_modifier );
					$where_columns_individual_columns = [];
					$search_individual_colums         = 0 !== sizeof( $column_specific_search_columns );

					foreach ( $search_columns as $search_column ) {
						$search_column_value =
							isset( $column_specific_search_columns[ $search_column ] ) ?
								$column_specific_search_columns[ $search_column ] : '';

						if ( 'fulltext' === $search_type ) {
							if ( '' !== trim( $search ) ) {
								$where_columns[] =
									$wpdb->prepare(
										"match (`{$search_column}`) against ('%s' {$fulltext_mode})",
										esc_sql( str_replace( '- ', '', trim( $search ) . ' ' ) )
									); // phpcs:ignore Standard.Category.SniffName.ErrorCode
							}

							if ( $search_individual_colums && '' !== $search_column_value ) {
								if ( in_array( $search_column, $listbox_columns ) ) {
									// listbox must exactly match
									$where_columns_individual_columns[] =
										$wpdb->prepare(
											"`{$search_column}` = '%s'",
											$search_column_value
										); // phpcs:ignore Standard.Category.SniffName.ErrorCode

								} else {
									$where_columns_individual_columns[] =
										$wpdb->prepare(
											"match (`{$search_column}`) against ('%s' {$fulltext_mode})",
											esc_sql( str_replace( '- ', '', trim( $search_column_value ) . ' ' ) )
										); // phpcs:ignore Standard.Category.SniffName.ErrorCode
								}
							}

						} else {
							$column_data_type = 'string';
							$i                = 0;

							while ( $i < sizeof( $columns) ) {
								if ( $columns[ $i ]['column_name'] === $search_column ) {
									$column_data_type = WPDA::get_type( $columns[ $i ]['data_type'] );
									break;
								}
								$i++;
							}
							if ( '' !== trim( $search ) ) {
								switch ( $column_data_type ) {
									case ('number'):
									case('time'):
										$where_columns[] = $wpdb->prepare( "`{$search_column}` = '%s'", esc_sql( trim( $search ) ) ); // phpcs:ignore Standard.Category.SniffName.ErrorCode
										break;
									case('date'):
										$where_columns[] = $wpdb->prepare( "`{$search_column}` like '%s'", esc_sql( trim( $search ) ) . '%' ); // phpcs:ignore Standard.Category.SniffName.ErrorCode
										break;
									default:
										if ( 'explicit' === $search_type ) {
											$where_columns[] = $wpdb->prepare( "`{$search_column}` like '%s'", esc_sql( $search ) ); // phpcs:ignore Standard.Category.SniffName.ErrorCode
										} else {
											$where_columns[] = $wpdb->prepare( "`{$search_column}` like '%s'", '%' . esc_sql( $search ) . '%' ); // phpcs:ignore Standard.Category.SniffName.ErrorCode
										}
								}
							}

							if ( $search_individual_colums && '' !== $search_column_value ) {
								if ( in_array( $search_column, $listbox_columns ) ) {
									// listbox must exactly match
									$where_columns_individual_columns[] =
										$wpdb->prepare(
											"`{$search_column}` = '%s'",
											$search_column_value
										); // phpcs:ignore Standard.Category.SniffName.ErrorCode

								} else {
									global $wpda_project_mode;
									$lookup        = null;
									$lookup_column = null;
									if ( null !== $wpda_project_mode ) {
										$listtable_lookups = WPDP_Project_Design_Table_Model::get_column_options( $table_name, 'listtable', $wpda_project_mode['setname'], $schema_name );
										foreach ( $listtable_lookups as $listtable_lookup ) {
											if ( $listtable_lookup->column_name === $search_column ) {
												$relationships = WPDP_Project_Design_Table_Model::get_column_options( $table_name, 'relationships', $wpda_project_mode['setname'], $schema_name );
												if ( isset( $relationships['relationships'] ) ) {
													foreach ( $relationships['relationships'] as $relationship ) {
														if (
															'lookup' === $relationship->relation_type &&
															$relationship->source_column_name[0] === $search_column
														) {
															$listtable_lookups = WPDP_Project_Design_Table_Model::get_column_options( $table_name, 'listtable', $wpda_project_mode['setname'], $schema_name );
															foreach ( $listtable_lookups as $listtable_lookup ) {
																if ( $listtable_lookup->column_name === $search_column ) {
																	$lookup        = $relationship;
																	$lookup_column = $listtable_lookup->lookup;
																	$where_columns_individual_columns[] = $wpdb->prepare(
																		"`" . str_replace( '`', '', $table_name ) . "`.`{$search_column}` in 
																			(
																				select `{$lookup->target_column_name[0]}` 
																				from `{$lookup->target_schema_name}`.`{$lookup->target_table_name}` 
																				where `{$lookup->target_table_name}`.`{$lookup->target_column_name[0]}` = `" . str_replace( '`', '', $table_name ) . "`.`{$lookup->source_column_name[0]}`
																				  and `{$lookup->target_table_name}`.`{$lookup_column}` = '%s'
																			)
																		"
																		, esc_sql( trim( $search_column_value ) )
																	); // phpcs:ignore Standard.Category.SniffName.ErrorCode
																}
															}
														}
													}
												}
											}
										}
									}
									if ( null === $lookup && null === $lookup_column ) {
										switch ( $column_data_type ) {
											case ( 'number' ):
											case( 'time' ):
												$where_columns_individual_columns[] = $wpdb->prepare( "`{$search_column}` = '%s'", esc_sql( trim( $search_column_value ) ) ); // phpcs:ignore Standard.Category.SniffName.ErrorCode
												break;
											case( 'date' ):
												$where_columns_individual_columns[] = $wpdb->prepare( "`{$search_column}` like '%s'", esc_sql( trim( $search_column_value ) ) . '%' ); // phpcs:ignore Standard.Category.SniffName.ErrorCode
												break;
											default:
												if ( 'explicit' === $search_type ) {
													$where_columns_individual_columns[] = $wpdb->prepare( "`{$search_column}` like '%s'", esc_sql( $search_column_value ) ); // phpcs:ignore Standard.Category.SniffName.ErrorCode
												} else {
													$where_columns_individual_columns[] = $wpdb->prepare( "`{$search_column}` like '%s'", '%' . esc_sql( $search_column_value ) . '%' ); // phpcs:ignore Standard.Category.SniffName.ErrorCode
												}
										}
									}
								}
							}
						}
					}

					if ( 0 === sizeof( $where_columns ) && 0 === sizeof( $where_columns_individual_columns ) ) {
						return ' 1=2 ';
					}

					if ( 0 !== sizeof( $where_columns ) ) {
						$where_clause_returned = ' (' . implode( " or ", $where_columns ) . ') ';
						if ( 0 !== sizeof( $where_columns_individual_columns ) ) {
							$where_clause_returned .= ' and (' . implode( " and ", $where_columns_individual_columns ) . ') ';
						}
					} else {
						$where_clause_returned = ' (' . implode( " and ", $where_columns_individual_columns ) . ') ';
					}

					return $where_clause_returned;
				}

				return null;
			}

			return null; // Use default plugin search logic for all other tables
		}

		public static function fulltext_mode( $fulltext_modifier ) {
			switch ( $fulltext_modifier ) {
				case('boolean'):
					return 'in boolean mode';
				case('expansion'):
					return 'with query expansion';
				default:
					return 'in natural language mode';
			}

		}

		public static function add_column_search_to_publication( $html, $schema_name, $table_name, $pub_id, $columns, $table_settings ) {
			$search_placeholder_prefix = 'Search ';
			$search_placeholder_icon   = '';

			if ( null === $table_settings ) {
				return $html;
			}

			if (
				! isset( $table_settings->search_settings->column_specific_search ) ||
				! isset( $table_settings->search_settings->search_columns )
			) {
				return $html;
			}

			if ( ! $table_settings->search_settings->column_specific_search ) {
				return $html;
			}
			if ( ! is_array( $table_settings->search_settings->search_columns ) ) {
				return $html;
			}

			$search_columns = [];
			if (
				isset( $table_settings->search_settings->individual_search_columns ) &&
				is_array( $table_settings->search_settings->individual_search_columns )
			) {
				foreach ( $table_settings->search_settings->individual_search_columns as $search_column ) {
					$search_columns[ $search_column ] = true;
				}
			} else {
				foreach ( $table_settings->search_settings->search_columns as $search_column ) {
					$search_columns[ $search_column ] = true;
				}
			}

			$listbox_columns = [];
			if ( isset( $table_settings->search_settings->listbox_columns ) ) {
				if ( is_array( $table_settings->search_settings->listbox_columns ) ) {
					foreach ( $table_settings->search_settings->listbox_columns  as $listbox_column ) {
						$listbox_columns[ $listbox_column ] = true;
					}
				}
			}

			$search_placeholder  = true;
			$wpda_data_publisher = WPDA_Publisher_Model::get_publication( $pub_id );
			if (
					isset(
						$wpda_data_publisher[0]['pub_table_options_searching'],
						$wpda_data_publisher[0]['pub_table_options_advanced']
					)
			) {
				if (
					'on' !== $wpda_data_publisher[0]['pub_table_options_searching']
				) {
					// Searching disabled for this publication
					return;
				}

				try {
					$json = json_decode( $wpda_data_publisher[0]['pub_table_options_advanced'] );

					if (
						isset( $json, $json->serverSide ) &&
						( "false" === $json->serverSide || false === $json->serverSide )
					) {
						return;
					}

					if ( isset( $json->wpda_searchbox ) ) {
						$wpda_searchbox = $json->wpda_searchbox;
					} else {
						$wpda_searchbox = '';
					}

					$check_placeholder_prefix  = true;
					if ( isset( $json->wpda_search_placeholder ) ) {
						switch( strtolower( $json->wpda_search_placeholder ) ) {
							case 'icon':
								$search_placeholder_prefix = '&#x1f50d; ';
								$search_placeholder        = false;
								$check_placeholder_prefix  = false;
								break;
							case 'false':
								$search_placeholder_prefix = '';
								$search_placeholder        = false;
								$check_placeholder_prefix  = false;
								break;
							default:
								// All other cases: normal behaviour
						}
					}
					if ( $check_placeholder_prefix ) {
						if ( isset( $json->wpda_search_placeholder_prefix ) ) {
							if ( 'icon' === strtolower( $json->wpda_search_placeholder_prefix ) ) {
								$search_placeholder_prefix = '&#x1f50d; '; // Add icon to search box (Charles)
							} elseif ( '' !== $json->wpda_search_placeholder_prefix ) {
								$search_placeholder_prefix = rtrim( $json->wpda_search_placeholder_prefix ) . ' ';
							} else {
								$search_placeholder_prefix = $json->wpda_search_placeholder_prefix;
							}
						}
					}
				} catch (\Exception $e) {
					$wpda_searchbox = '';
				}
			} else {
				$wpda_searchbox = '';
			}

			ob_start();
			?>
			<script type='text/javascript'>
				var <?php echo esc_attr( $table_name); ?>search_columns = new Object();
				<?php
				foreach ( $search_columns as $key => $value ) {
					echo esc_attr( $table_name) . "search_columns['$key'] = " . json_encode( $value ) . ";";
				}
				?>

				var <?php echo esc_attr( $table_name); ?>listbox_columns = new Object();
				<?php
				foreach ( $listbox_columns as $key => $value ) {
					$resultset    = self::get_column_values( $schema_name, $table_name, $key );
					$resultstring = '';
					$firstelement = true;
					foreach ( $resultset as $value ) {
						if ( ! $firstelement ) {
							$resultstring .= ',';
						}
						$resultstring .= "'" . str_replace( "'", "\'", $value['column_value'] ) . "'";
						$firstelement = false;
					}
					echo esc_attr( $table_name) . "listbox_columns['$key'] = [" . $resultstring . "];";
				}
				?>

				function wpda_<?php echo esc_attr( $table_name); ?>_advanced_<?php echo esc_attr( $pub_id); ?>() {
					<?php
					if ( 'header' === $wpda_searchbox || 'both' === $wpda_searchbox ) {
						echo "elem = 'thead';";
					} else {
						echo "elem = 'tfoot';";
					}
					?>
					if ( document.activeElement.id != '' ) {
						if (
							'thead' === document.activeElement.id.substr(-5) ||
							'tfoot' === document.activeElement.id.substr(-5)
						) {
							elem = document.activeElement.id.substr(-5);
						}
					}
					var return_value = [];
					jQuery("#<?php echo esc_attr( $table_name) .  esc_attr( $pub_id); ?> tfoot th").each(function () {
						var column_name = jQuery(this).attr("data-column_name_search");
						var column_id = '<?php echo esc_attr( $table_name) .  esc_attr( $pub_id); ?>' + column_name + '_' + elem;
						if (jQuery('#' + column_id).val()!=='') {
							return_value['wpda_search_' + column_name] = jQuery('#' + column_id).val();
						}
					});
					return return_value;
				}

				function wpda_<?php echo esc_attr( $table_name); ?>_add_searchbox_<?php echo esc_attr( $pub_id); ?>(elem) {
					var thORtd = elem==="thead" ? "td" : "th";
					jQuery("#<?php echo esc_attr( $table_name) .  esc_attr( $pub_id); ?> " + elem + " " + thORtd).each(function () {
						var title = jQuery(this).text();
						if (thORtd==="td") {
							title = jQuery(this).data("column_name_label");
						}
						var column_name = jQuery(this).attr("data-column_name_search");
						if (title==='') {
							for (var i=0; i<wpdaDbColumns<?php echo esc_attr( preg_replace( '/[^a-zA-Z0-9]/', '', $table_name ) ) .  esc_attr( $pub_id); ?>.length; i++) {
								var curObj = wpdaDbColumns<?php echo esc_attr( preg_replace( '/[^a-zA-Z0-9]/', '', $table_name ) ) .  esc_attr( $pub_id); ?>[i];
								if (curObj.className===column_name) {
									title = curObj.label;
								}
							}
						}
						<?php
						if ( ! $search_placeholder ) {
							echo 'title = "";';
						}
						?>
						var column_id = '<?php echo esc_attr( $table_name) .  esc_attr( $pub_id); ?>' + column_name + '_' + elem;
						if( <?php echo esc_attr( $table_name); ?>search_columns[column_name] !== undefined ) {
							if ( <?php echo esc_attr( $table_name); ?>listbox_columns[column_name] !== undefined ) {
								var html = '<?php echo $search_placeholder_icon; ?><select id="' + column_id + '" class="wpda_search_listbox wpda_search_boxes ' + column_name + '">';
								html += '<option value=""><?php echo esc_attr( $search_placeholder_prefix ); ?>' + title + '</option>';
								for (var i = 0; i < <?php echo esc_attr( $table_name); ?>listbox_columns[column_name].length; i++) {
									var value = <?php echo esc_attr( $table_name); ?>listbox_columns[column_name][i];
									html += '<option value="' + value + '">' + value + '</option>';
								}
								html += '</select>';
								jQuery(this).html(html);
								jQuery('#' + column_id).attr('data-id', column_name);
								jQuery('#' + column_id).change(function() {
									jQuery("#<?php echo esc_attr( $table_name) .  esc_attr( $pub_id); ?>").DataTable().draw();
								});
							} else {
								jQuery(this).html(
									'<?php echo $search_placeholder_icon; ?>
									<input type="search" id="' + column_id + '" class="wpda_search_textbox wpda_search_boxes ' + column_name + '" placeholder="<?php echo esc_attr( $search_placeholder_prefix ); ?>' + title + '" />'
								);
								jQuery('#' + column_id).attr('data-id', column_name);
								jQuery('#' + column_id).keyup(function(event) {
									jQuery("#<?php echo esc_attr( $table_name) .  esc_attr( $pub_id); ?>").DataTable().draw();
								});
								jQuery('#' + column_id).click(function(event) {
									event.stopPropagation();
									event.preventDefault();
									return false;
								});

							}
						}
					});
				}

				jQuery(function () {
					<?php
					if ( 'none' !== $wpda_searchbox ) {
						if ( 'header' !== $wpda_searchbox ) {
							echo 'wpda_' . esc_attr( $table_name ) . '_add_searchbox_' . esc_attr( $pub_id ) . "('tfoot');";
						}
						if ( 'header' === $wpda_searchbox || 'both' === $wpda_searchbox ) {
							echo 'wpda_' . esc_attr( $table_name ) . '_add_searchbox_' . esc_attr( $pub_id ) . "('thead');";
						}
					}
					?>
				});
			</script>
			<?php
			return $html . ob_get_clean();
		}

	}

}
