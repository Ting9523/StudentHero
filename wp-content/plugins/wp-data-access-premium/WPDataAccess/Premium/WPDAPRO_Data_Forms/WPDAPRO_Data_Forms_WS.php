<?php

namespace WPDataAccess\Premium\WPDAPRO_Data_Forms {

	use WPDataAccess\Connection\WPDADB;
	use WPDataAccess\Data_Dictionary\WPDA_List_Columns_Cache;
	use WPDataAccess\List_Table\WPDA_List_Table;
	use WPDataAccess\Macro\WPDA_Macro;
	use WPDataAccess\Plugin_Table_Models\WPDA_Media_Model;
	use WPDataAccess\Plugin_Table_Models\WPDA_Table_Settings_Model;
	use WPDataAccess\Plugin_Table_Models\WPDP_Project_Design_Table_Model;
	use WPDataAccess\Plugin_Table_Models\WPDP_Page_Model;
	use WPDataAccess\Utilities\WPDA_Autocomplete;
	use WPDataAccess\WPDA;

	class WPDAPRO_Data_Forms_WS {

		protected $user_id                   = 0;
		protected $wpda_list_columns         = null;
		protected $selected_column_positions = [];
		protected $column_names              = [];
		protected $column_names_reverse      = [];

		protected function get_user_info() {
			$this->user_id = get_current_user_id();
		}

		public function get_list_anonymous() {
			if ( $this->allow_anonymous_access() ) {
				$this->get_list();
			}
		}

		public function get_list() {
			// Prepare empty response
			$response                  = (object) null;
			$response->draw            = isset( $_REQUEST['draw'] ) ? intval( $_REQUEST['draw'] ) : 0;
			$response->recordsTotal    = 0;
			$response->recordsFiltered = 0;
			$response->data            = [];
			$is_embedded               = false;

			if (
				isset(
					$_POST['wpdadataforms_wp_nonce'],
					$_POST['wpdadataforms_page_id'],
					$_POST['wpdadataforms_schema_name'],
					$_POST['wpdadataforms_table_name'],
					$_POST['wpdadataforms_set_name'],
					$_POST['wpdadataforms_columns'],
					$_POST['wpdadataforms_is_child']
				)
			) {
				// Process arguments
				$wpnonce                    = sanitize_text_field( wp_unslash( $_POST['wpdadataforms_wp_nonce'] ) ); // input var okay.
				$page_id                    = sanitize_text_field( wp_unslash( $_POST['wpdadataforms_page_id'] ) ); // input var okay.
				$schema_name                = str_replace( "`", '', sanitize_text_field( wp_unslash( $_POST['wpdadataforms_schema_name'] ) ) ); // input var okay.
				$table_name                 = str_replace( "`", '', sanitize_text_field( wp_unslash( $_POST['wpdadataforms_table_name'] ) ) ); // input var okay.
				$set_name                   = sanitize_text_field( wp_unslash( $_POST['wpdadataforms_set_name'] ) ); // input var okay.
				$columns                    = str_replace( "`", '', sanitize_text_field( wp_unslash( $_POST['wpdadataforms_columns'] ) ) ); // input var okay.
				$is_child                   = 'true' === strtolower( $_POST['wpdadataforms_is_child'] ); // input var okay.;
				$lookup_columns             = isset( $_POST['wpdadataforms_lookup_columns'] ) ? $_POST['wpdadataforms_lookup_columns'] : null; // Array of lookup column positions
				$wpdadataforms_child_filter = isset( $_POST['wpdadataforms_child_filter'] ) ? $_POST['wpdadataforms_child_filter'] : null; // Child columns and values
				$project_page               = WPDP_Page_Model::get_page_from_page_id( $page_id );
				$wpdadataforms_embedded		= isset( $_POST['wpdadataforms_embedded'] ) && 'true' === $_POST['wpdadataforms_embedded'];

				$this->get_user_info();
				if ( $wpdadataforms_embedded ) {
					// Force user ID = 0 for all embedded actions
					$this->user_id = '0';
				}
				$wpnonce_action = "wpdadataforms-get-table-{$this->user_id}-{$page_id}-{$table_name}";
				$is_embedded    = WPDA::wpda_verify_sonce( $wpnonce, $wpnonce_action );

				if (
					1 === sizeof( $project_page ) && // Project page must exist
					( // Security check
						wp_verify_nonce( $wpnonce, $wpnonce_action ) ||
						$is_embedded
					)
				) {
					$offset = 0;
					if ( isset( $_POST['start'] ) ) {
						$offset = sanitize_text_field( wp_unslash( $_POST['start'] ) ); // input var okay.
					}
					$limit = -1;
					if ( isset( $_POST['length'] ) ) {
						$limit = sanitize_text_field( wp_unslash( $_POST['length'] ) ); // input var okay.
					}

					$wpdadb = WPDADB::get_db_connection( $schema_name );
					if ( null === $wpdadb ) {
						$this->create_empty_response( 'Invalid connection' );
						wp_die(); // Database not available
					}

					// Get default where
					if ( null === $wpdadataforms_child_filter ) {
						// Get default where from page
						$default_where = $project_page[0]['page_where'];
					} else {
						// Get default where from options set
						$tableinfo     = WPDP_Project_Design_Table_Model::get_column_options( $table_name, 'tableinfo', $set_name, $schema_name );
						$default_where = isset( $tableinfo->default_where ) ? $tableinfo->default_where : '';
					}

					// Make columns positions available
					$this->selected_column_positions = explode( ',', $columns );

					$relationships     = null;
					$listtable_lookups = null;
					$orderby           = '';

					if ( isset( $_POST['order'] ) && is_array( $_POST['order'] ) ) {
						// User defined order by
						$orderby_columns = [];

						foreach ( $_POST['order'] as $order_column ) {
							$lookup_column = null;
							if ( null !== $lookup_columns ) {
								for ( $i = 0; $i < sizeof( $lookup_columns ); $i++ ) {
									if ( $order_column['column'] == $lookup_columns[ $i ]['column_index'] ) {
										$lookup_column = $lookup_columns[ $i ];
										break;
									}
								}
							}

							$lookup_added = false;
							if ( null !== $lookup_column ) {
								if ( null === $relationships ) {
									$relationships = $this->get_relationships( $table_name, $set_name, $schema_name );
								}

								if ( null !== $relationships && sizeof( $relationships ) > 0 ) {
									if ( null === $listtable_lookups ) {
										$listtable_lookups = WPDP_Project_Design_Table_Model::get_column_options( $table_name, 'listtable', $set_name, $schema_name );
									}

									foreach ( $relationships as $relationship ) {
										if ( in_array( $lookup_column['column_name'], $relationship->source_column_name ) ) {
											foreach ( $listtable_lookups as $listtable_lookup ) {
												if ( $listtable_lookup->column_name === $lookup_column['column_name'] ) {
													// Finally!!! Built order by on lookup...
													$query_select_column = $listtable_lookup->lookup;
													$query_table_name    = $relationship->target_table_name;
													$query_target_column = $relationship->target_column_name[0]; // TODO : Add multi column lookups
													$query_source_column = $relationship->source_column_name[0]; // TODO : Add multi column lookups

													$orderby_query = " (select distinct `{$query_select_column}` from `{$query_table_name}` where `{$query_target_column}` = `{$table_name}`.`$query_source_column`) ";

													$orderby_columns[] =
														$orderby_query . ' ' .
														sanitize_text_field( wp_unslash( $order_column['dir'] ) ); // input var okay.
													$lookup_added      = true;

													break;
												}
											}
										}
									}
								}
							}

							if ( ! $lookup_added ) {
								if ( is_numeric( $order_column['column'] ) ) {
									$column_name = '`' . str_replace( "`", '', $this->selected_column_positions[ $order_column['column'] ] ) . '`';

									if ( 'desc' === $order_column['dir'] ) {
										$orderby_columns[] = " {$column_name} desc ";
									} else {
										$orderby_columns[] = " {$column_name} ";
									}
								}
							}
						}

						if ( sizeof( $orderby_columns ) > 0 ) {
							$orderby = 'order by ' . implode( ',', $orderby_columns );
						}
					}

					$where = '';
					if ( null !== $wpdadataforms_child_filter ) {
						foreach ( $wpdadataforms_child_filter as $key => $val ) {
							if ( 'relnm' !== $key ) {
								if ( '' === $where ) {
									$where .= ' where ';
								} else {
									$where .= ' and ';
								}
								$where .= $wpdadb->prepare(
									"`" . str_replace( '`', '', sanitize_text_field( wp_unslash( $key ) ) ) . "` = %s",
									sanitize_text_field( wp_unslash( $val ) )
								); // phpcs:ignore Standard.Category.SniffName.ErrorCode
							} else {
								// Handle n:m relationship
								$relnm_where    = '';
								$parent_columns = '`' . implode( '`,`', str_replace( '`', '', $val['parent_column'] ) ) . '`';
								$select_columns = '`' . implode( '`,`', str_replace( '`', '', $val['select_column'] ) ) . '`';
								$select_table   = '`' . str_replace( '`', '', $val['select_table'] ) . '`';
								for ( $i = 0; $i < sizeof( $val['select_where'] ); $i++ ) {
									if ( '' !== $relnm_where ) {
										$relnm_where .= " and {$relnm_where} ";
									}
									$select_where = str_replace( '`', '', $val['select_where'][ $i ] );
									$relnm_where  .= $wpdadb->prepare(
										"`{$select_where}` = %s",
										$val['select_value'][ $i ]
									); // phpcs:ignore Standard.Category.SniffName.ErrorCode
								}
								$sub_query = 'in'; // Show n:m relationships
								if ( isset( $_POST['wpdadataforms_is_lov'] ) && 'true' === strtolower( $_POST['wpdadataforms_is_lov'] ) ) {
									$sub_query = 'not in'; // Show available relationships (for list of values)
								}
								$relnm_subquery = "({$parent_columns}) {$sub_query} (select {$select_columns} from {$select_table} where $relnm_where)";
								if ( '' === $where ) {
									$where .= " where {$relnm_subquery} ";
								} else {
									$where .= " and {$relnm_subquery} ";
								}
							}
						}
					}

					if ( '' !== $default_where && null !== $default_where ) {
						// Add default where
						if ( '' === $where ) {
							$where .= ' where ' . WPDA::substitute_environment_vars( $default_where );
						} else {
							$where .= ' and ' . WPDA::substitute_environment_vars( $default_where );
						}
					}

					// Get column info
					$this->wpda_list_columns = WPDA_List_Columns_Cache::get_list_columns( $schema_name, $table_name );

					// Add search criteria
					if ( isset( $_POST['search']['value'] ) ) {
						$search_value = sanitize_text_field( wp_unslash( $_POST['search']['value'] ) ); // input var okay.

						$where_columns = WPDA::construct_where_clause(
							$schema_name,
							$table_name,
							$this->wpda_list_columns->get_table_columns(),
							$search_value
						);

						$where_lookups = [];
						if ( '' !== $search_value && null !== $lookup_columns ) {
							foreach ( $lookup_columns as $lookup_column ) {
								if ( null === $relationships ) {
									$relationships = $this->get_relationships( $table_name, $set_name, $schema_name );
								}

								if ( null !== $relationships && sizeof( $relationships ) > 0 ) {
									if ( null === $listtable_lookups ) {
										$listtable_lookups = WPDP_Project_Design_Table_Model::get_column_options( $table_name, 'listtable', $set_name, $schema_name );
									}

									foreach ( $relationships as $relationship ) {
										if ( in_array( $lookup_column['column_name'], $relationship->source_column_name ) ) {
											foreach ( $listtable_lookups as $listtable_lookup ) {
												if ( $listtable_lookup->column_name === $lookup_column['column_name'] ) {
													// Finally!!! Built where for lookup columns...
													$query_select_column = str_replace( '`', '', $listtable_lookup->lookup );
													$query_table_name    = str_replace( '`', '', $relationship->target_table_name );
													$query_target_column = str_replace( '`', '', $relationship->target_column_name[0] ); // TODO : Add multi column lookups
													$query_source_column = str_replace( '`', '', $relationship->source_column_name[0] ); // TODO : Add multi column lookups

													$subquery        = " ( `$query_source_column` in (select `{$query_target_column}` from `{$query_table_name}` where `{$query_select_column}` like '%s' ) or `$query_source_column` is null or `$query_source_column` = '' ) ";
													$where_lookups[] = $wpdadb->prepare( $subquery, '%' . esc_sql( $search_value ) . '%' ); // phpcs:ignore Standard.Category.SniffName.ErrorCode

													break;
												}
											}
										}
									}
								}
							}
						}
						if ( sizeof( $where_lookups ) > 0 ) {
							$where_lookup = ' (' . implode( ' or ', $where_lookups ) . ') ';
						} else {
							$where_lookup = '';
						}

						if ( '' !== $where_columns && '' !== $where_lookup ) {
							if ( ' (1=2) ' === $where_columns ) {
								$where_temp = $where_lookup;
							} else {
								$where_temp = " ( {$where_columns} or {$where_lookup} ) ";
							}
						} else {
							$where_temp = $where_columns . $where_lookup;
						}

						if ( '' !== $where_temp ) {
							if ( '' === $where ) {
								$where = " where {$where_temp} ";
							} else {
								$where .= " and {$where_temp} ";
							}
						}
					}

					// Check referer for column filter arguments
					$where_columns = [];
					if ( isset( $_POST['wpdadataforms_referer'] ) ) {
						$wpdadataforms_referer = sanitize_text_field( wp_unslash( $_POST['wpdadataforms_referer'] ) ); // input var okay.
						if ( false !== ( $wpdadataforms_args_pos = strpos( $wpdadataforms_referer, "?" ) ) ) {
							$wpdadataforms_args = explode( '&', substr( $wpdadataforms_referer, $wpdadataforms_args_pos + 1 ) );
							foreach ( $wpdadataforms_args as $wpdadataforms_arg ) {
								foreach ( $this->wpda_list_columns->get_table_columns() as $column ) {
									$wpdadataforms_args_value = explode( '=', $wpdadataforms_arg );
									if ( $wpdadataforms_args_value[0] === "wpda_search_column_{$column['column_name']}" ) {
										$where_columns[] = $wpdadb->prepare( "`" . str_replace( '`', '', $column['column_name'] ) . "` like '%s'", esc_sql( $wpdadataforms_args_value[1] ) ); // phpcs:ignore Standard.Category.SniffName.ErrorCode
									}
								}
							}
						}
					}
					if ( count( $where_columns ) > 0 ) {
						$where_temp = ' (' . implode( ' and ', $where_columns ) . ') ';
						if ( '' === $where ) {
							$where = " where {$where_temp} ";
						} else {
							$where .= " and {$where_temp} ";
						}
					}

					// Define query
					$query = "select * from `{$table_name}` {$where} {$orderby}";
					if ( -1 !== $limit ) {
						$query .= " limit $limit offset $offset";
					}

					// Perform query (all columns need to be queried for value substitution)
					$rows = $wpdadb->get_results( $query, 'ARRAY_A' );

					// Make columns names available
					if ( count( $rows ) > 0 ) {
						foreach ( $rows[0] as $key => $row ) {
							$this->column_names[] = $key;
						}
					}
					$this->column_names_reverse = array_flip( $this->column_names );

					// Get table settings
					$table_settings_db = WPDA_Table_Settings_Model::query( $table_name, $schema_name );
					$table_settings    = null;
					if ( isset( $table_settings_db[0]['wpda_table_settings'] ) ) {
						$table_settings = json_decode( $table_settings_db[0]['wpda_table_settings'] );
					}

					// Get media columns
					$media_columns = [];
					for ( $i = 0; $i < sizeof( $this->selected_column_positions ); $i++ ) {
						$media_type = WPDA_Media_Model::get_column_media( $table_name, $this->selected_column_positions[ $i ], $schema_name );
						if ( false !== $media_type ) {
							$media_columns[ $i ] = $media_type;
						}
					}

					// Get table info from data project
					$this->tableinfo = WPDP_Project_Design_Table_Model::get_column_options( $table_name, 'tableinfo', $set_name, $schema_name );

					if ( sizeof( $rows ) > 0 ) {
						// Perform all necessary actions in one loop
						$response->data = [];
						for ( $i = 0; $i < sizeof( $rows ); $i++ ) {
							// Write only selected columns to output array
							$new_row = [];
							for ( $j = 0; $j < sizeof( $this->selected_column_positions ); $j++ ) {
								$new_row[] = $rows[ $i ][ $this->selected_column_positions[ $j ] ];
							}

							// Add hyperlinks
							if ( isset( $table_settings->hyperlinks ) ) {
								$this->add_hyperlinks( $new_row, $rows[ $i ], $table_settings->hyperlinks, $is_child );
							}

							// Add media columns
							foreach ( $media_columns as $column_index => $media_type ) {
								$this->add_media_column( $new_row, $column_index, $media_type, $table_settings );
							}

							// Add row to output array
							$response->data[] = $new_row;
						}
					}

					$row_count_estimate = WPDA::get_row_count_estimate( $schema_name, $table_name, $table_settings );
					$rows_estimate      = $row_count_estimate['row_count'];
					$do_real_count      = $row_count_estimate['do_real_count'];

					if ( ! $do_real_count ) {
						// Use estimate row count
						$response->recordsTotal = $rows_estimate;
					} else {
						// Perform real count
						$query2                 = "select count(*) from `{$table_name}`";
						$count_rows             = $wpdadb->get_results( $query2, 'ARRAY_N' );
						$response->recordsTotal = $count_rows[0][0];
					}

					if ( '' !== $where ) {
						if ( sizeof( $rows ) > 0 ) {
							$query3                    = "select count(*) from `{$table_name}` $where";
							$count_rows_filtered       = $wpdadb->get_results( $query3, 'ARRAY_N' ); // phpcs:ignore Standard.Category.SniffName.ErrorCode
							$response->recordsFiltered = $count_rows_filtered[0][0]; // Number of rows in table.
						} else {
							$response->recordsFiltered = 0;
						}
					} else {
						$response->recordsFiltered = $response->recordsTotal;
					}

					$response->error = $wpdadb->last_error;
					if ( 'on' === WPDA::get_option( WPDA::OPTION_PLUGIN_DEBUG ) ) {
						$response->debug = [
							'query'   => $query,
							'where'   => $where,
							'orderby' => $orderby,
						];
					}

					if ( ! $is_embedded ) {
						WPDA::sent_header( 'application/json' );
					} else {
						WPDA::sent_header( 'application/json', '*' );
					}
					echo json_encode( $response );
				} else {
					$this->create_empty_response( 'Token expired, please refresh page' );
				}
			} else {
				$this->create_empty_response( 'Wrong argument' );
			}

			wp_die();
		}

		protected function get_column_position_from_row( $row, $column_index ) {
			return $this->column_names_reverse[ $this->selected_column_positions[ $column_index ] ];
		}

		protected function add_hyperlinks( &$row, $column_values, $hyperlinks, $is_child ) {
			$array_size = sizeof( $row );
			foreach ( $hyperlinks as $hyperlink ) {
				if ( WPDAPRO_Data_Forms::is_hyperlink_enabled( $hyperlink, $this->tableinfo, $is_child ) ) {
					if ( isset( $hyperlink->hyperlink_list ) && $hyperlink->hyperlink_list ) {
						$hyperlink_label = isset( $hyperlink->hyperlink_label ) ? $hyperlink->hyperlink_label : '';;
						$hyperlink_target = isset( $hyperlink->hyperlink_target ) ? $hyperlink->hyperlink_target : false;
						$hyperlink_html   = isset( $hyperlink->hyperlink_html ) ? $hyperlink->hyperlink_html : '';

						if ( '' !== $hyperlink_html ) {
							foreach ( $this->column_names as $key => $value ) {
								$hyperlink_html = str_replace( "\$\${$value}\$\$", $column_values[ $value ], $hyperlink_html );
							}
						}

						$macro          = new WPDA_Macro( $hyperlink_html );
						$hyperlink_html = $macro->exe_macro();

						if ( '' !== $hyperlink_html ) {
							if ( false !== strpos( ltrim( $hyperlink_html ), '&lt;' ) ) {
								$hyperlink = html_entity_decode( $hyperlink_html );
							} else {
								$target    = true === $hyperlink_target ? "target='_blank'" : '';
								$hyperlink = "<a href='" . str_replace( ' ', '+', $hyperlink_html ) . "' {$target}>{$hyperlink_label}</a>";
							}

							// Add hyperlink
							$row[ $array_size++ ] = $hyperlink;
						} else {
							$row[ $array_size++ ] = '';
						}
					}
				}
			}
		}

		protected function add_media_column( &$row, $column_index, $media_type, $table_settings ) {
			switch ( $media_type ) {
				case 'Attachment':
					$media_ids   = explode( ',', $row[ $column_index ] );
					$media_links = '';

					foreach ( $media_ids as $media_id ) {
						$url = wp_get_attachment_url( esc_attr( $media_id ) );
						if ( false !== $url ) {
							$mime_type = get_post_mime_type( $media_id );
							if ( false !== $mime_type ) {
								$title       = get_the_title( esc_attr( $media_id ) );
								$media_links .= WPDA_List_Table::column_media_attachment( $url, $title, $mime_type );
							}
						}
					}

					$row[ $column_index ] = $media_links;
					break;
				case 'Audio':
					$audio_ids = explode( ',', $row[ $column_index ] );
					$audio_src = '';

					foreach ( $audio_ids as $audio_id ) {
						if ( 'audio' === substr( get_post_mime_type( $audio_id ), 0, 5 ) ) {
							$url = wp_get_attachment_url( esc_attr( $audio_id ) );
							if ( false !== $url ) {
								$title = get_the_title( esc_attr( $audio_id ) );
								if ( false !== $url ) {
									$audio_src .=
										'<div title="' . $title . '" class="wpda_tooltip">' .
										do_shortcode( '[audio src="' . $url . '"]' ) .
										'</div>';
								}
							}
						}
					}

					$row[ $column_index ] = $audio_src;
					break;
				case 'Image':
					$image_ids = explode( ',', $row[ $column_index ] );
					$image_src = '';

					foreach ( $image_ids as $image_id ) {
						$url = wp_get_attachment_url( esc_attr( $image_id ) );
						if ( false !== $url ) {
							$title     = get_the_title( esc_attr( $image_id ) );
							$image_src .= '' !== $image_src ? '<br/>' : '';
							$image_src .= sprintf( '<img src="%s" class="wpda_tooltip" title="%s" width="100%%">', $url, $title );
						}
					}

					$row[ $column_index ] = $image_src;
					break;
				case 'ImageURL':
					$row[ $column_index ] = sprintf( '<img src="%s" class="wpda_tooltip" width="100%%">', $row[ $column_index ] );
					break;
				case 'Hyperlink':
					if ( null !== $row[ $column_index ] && '' !== $row[ $column_index ] ) {
						$hyperlink = json_decode( $row[ $column_index ], true );
						if ( $hyperlink !== null ) {
							// Get hyperlink from JSON
							if ( is_array( $hyperlink ) &&
								isset( $hyperlink['label'] ) &&
								isset( $hyperlink['url'] ) &&
								isset( $hyperlink['target'] )
							) {
								if ( '' === $hyperlink['url'] || null === $hyperlink['url'] ) {
									$row[ $column_index ] = $hyperlink['label'];
								} else {
									$row[ $column_index ] = "<a href='{$hyperlink['url']}' target='{$hyperlink['target']}' onclick='event.stopPropagation()'>{$hyperlink['label']}</a>";
								}
							}
						} else {
							// Get hyperlink from plain text
							$hyperlink_label      = $this->wpda_list_columns->get_column_label( $this->column_names[ $this->get_column_position_from_row( $row, $column_index ) ] );
							$row[ $column_index ] = "<a href='{$row[ $column_index ]}' target='_blank' onclick='event.stopPropagation()'>{$hyperlink_label}</a>";
						}
					}
					break;
				case 'Video':
					$video_ids = explode( ',', $row[ $column_index ] );
					$video_src = '';

					foreach ( $video_ids as $video_id ) {
						if ( 'video' === substr( get_post_mime_type( $video_id ), 0, 5 ) ) {
							$url = wp_get_attachment_url( esc_attr( $video_id ) );
							if ( false !== $url ) {
								if ( false !== $url ) {
									$video_src .=
										do_shortcode( '[video src="' . $url . '"]' );
								}
							}
						}
					}

					$row[ $column_index ] = $video_src;
					break;
			}
		}

		protected function get_relationships( $table_name, $set_name, $schema_name ) {
			$relationships = WPDP_Project_Design_Table_Model::get_column_options( $table_name, 'relationships', $set_name, $schema_name );
			$lookups       = [];
			$autocompletes = [];

			if ( isset( $relationships['relationships'] ) ) {
				foreach ( $relationships['relationships'] as $relationship ) {
					if ( 'lookup' === $relationship->relation_type ) {
						$lookups[] = $relationship;
					} elseif ( 'autocomplete' === $relationship->relation_type ) {
						// TODO Remove listbox and add auto complete
						$lookups[]       = $relationship;
						$autocompletes[] = $relationship;
					}
				}
			}

			return $lookups;
		}

		public function get_form_data_anonymous() {
			if ( $this->allow_anonymous_access() ) {
				$this->get_form_data();
			}
		}

		public function get_form_data() {
			$form_data   = json_decode( file_get_contents( "php://input" ) );
			$status      = 'ok';
			$message     = '';
			$rows        = [];
			$is_embedded = false;

			if (
				! isset( $form_data->wpdadataforms_wp_nonce ) ||
				! isset( $form_data->wpdadataforms_page_id ) ||
				! isset( $form_data->wpdadataforms_schema_name ) ||
				! isset( $form_data->wpdadataforms_table_name ) ||
				! isset( $form_data->wpdadataforms_pk )
			) {
				$status  = 'error';
				$message = __( 'Wrong arguments', 'wp-data-access' );
			} else {
				// Process arguments
				$wpnonce				= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_wp_nonce ) ); // input var okay.
				$page_id				= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_page_id ) ); // input var okay.
				$schema_name			= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_schema_name ) ); // input var okay.
				$table_name				= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_table_name ) ); // input var okay.
				$wpdadataforms_pk		= $form_data->wpdadataforms_pk; // input var okay.
				$wpdadataforms_embedded	= isset( $form_data->wpdadataforms_embedded ) && true === $form_data->wpdadataforms_embedded;

				$this->get_user_info();
				if ( $wpdadataforms_embedded ) {
					// Force user ID = 0 for all embedded actions
					$this->user_id = '0';
				}
				$wpnonce_action = "wpdadataforms-get-form-data-{$this->user_id}-{$page_id}-{$table_name}";
				$is_embedded    = WPDA::wpda_verify_sonce( $wpnonce, $wpnonce_action );

				// Security check
				if (
					! wp_verify_nonce( $wpnonce, $wpnonce_action ) &&
					! $is_embedded
				) {
					$status  = 'error';
					$message = __( 'Token expired or not authorized', 'wp-data-access' );
				} else {
					$wpdadb = WPDADB::get_db_connection( $schema_name );
					if ( null === $wpdadb ) {
						$status  = 'error';
						$message = sprintf( __( 'Remote database %s not available', 'wp-data-access' ), $schema_name );
					} else {
						$where = '';
						foreach ( $wpdadataforms_pk as $key => $val ) {
							if ( '' === $where ) {
								$where .= ' where ';
							} else {
								$where .= ' and ';
							}
							$where .= $wpdadb->prepare(
								'`' . sanitize_text_field( wp_unslash( $key ) ) . "` = %s",
								sanitize_text_field( wp_unslash( $val ) )
							); // input vars ok.
						}
						$query = "select * from `{$table_name}` {$where}";
						$rows  = $wpdadb->get_results( $query, 'ARRAY_A' );
					}
				}
			}

			$response = [
				'status'  => $status,
				'message' => $message,
				'rows'    => $rows,
			];

			if ( ! $is_embedded ) {
				WPDA::sent_header( 'application/json' );
			} else {
				WPDA::sent_header( 'application/json', '*' );
			}

			echo json_encode( $response, JSON_NUMERIC_CHECK );
			wp_die();
		}

		public function update_form_data_anonymous() {
			if ( $this->allow_anonymous_access() ) {
				$this->update_form_data();
			}
		}

		public function update_form_data() {
			$form_data   = json_decode( file_get_contents( "php://input" ) );
			$status      = 'ok';
			$message	 = '';
			$is_embedded = false;

			if (
				! isset( $form_data->wpdadataforms_wp_nonce ) ||
				! isset( $form_data->wpdadataforms_page_id ) ||
				! isset( $form_data->wpdadataforms_schema_name ) ||
				! isset( $form_data->wpdadataforms_table_name ) ||
				! isset( $form_data->wpdadataforms_pk ) ||
				! isset( $form_data->wpdadataforms_values )
			) {
				$status  = 'error';
				$message = __( 'Wrong arguments', 'wp-data-access' );
			} else {
				// Process arguments
				$wpnonce            	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_wp_nonce ) ); // input var okay.
				$page_id            	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_page_id ) ); // input var okay.
				$schema_name        	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_schema_name ) ); // input var okay.
				$table_name         	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_table_name ) ); // input var okay.
				$wpdadataforms_pk   	= $form_data->wpdadataforms_pk;
				$wpdadataforms_vals 	= $form_data->wpdadataforms_values;
				$wpdadataforms_embedded	= isset( $form_data->wpdadataforms_embedded ) && true === $form_data->wpdadataforms_embedded;

				$this->get_user_info();
				if ( $wpdadataforms_embedded ) {
					// Force user ID = 0 for all embedded actions
					$this->user_id = '0';
				}
				$wpnonce_action = "wpdadataforms-update-form-data-{$this->user_id}-{$page_id}-{$table_name}";
				$is_embedded    = WPDA::wpda_verify_sonce( $wpnonce, $wpnonce_action );

				// Security check
				if (
					! wp_verify_nonce( $wpnonce, $wpnonce_action ) &&
					! $is_embedded
				) {
					$status  = 'error';
					$message = __( 'Token expired or not authorized', 'wp-data-access' );
				} else {
					foreach ( $wpdadataforms_pk as $key => $value ) {
						if ( null === $value ) {
							$pk[ sanitize_text_field( wp_unslash( $key ) ) ] = null;
						} else {
							$pk[ sanitize_text_field( wp_unslash( $key ) ) ] = sanitize_text_field( wp_unslash( $value ) ); // input var okay.
						}
					}

					foreach ( $wpdadataforms_vals as $key => $value ) {
						if ( null === $value ) {
							$vals[ sanitize_text_field( wp_unslash( $key ) ) ] = null;
						} else {
							$vals[ sanitize_text_field( wp_unslash( $key ) ) ] = sanitize_text_field( wp_unslash( $value ) ); // input var okay.
						}
					}

					$wpdadb = WPDADB::get_db_connection( $schema_name );
					if ( null === $wpdadb ) {
						$status  = 'error';
						$message = sprintf( __( 'Remote database %s not available', 'wp-data-access' ), $schema_name );
					} else {
						$wpdadb->suppress_errors( true );
						$upd = $wpdadb->update( $table_name, $vals, $pk );
						switch ( $upd ) {
							case 0:
								if ( '' !== $wpdadb->last_error ) {
									// Show error
									$status  = 'error';
									$message = $wpdadb->last_error;
								} else {
									// Nothing to save
									$message = __( 'Nothing to save', 'wp-data-access' );
								}
								break;
							case 1:
								// Success
								$message = __( 'Row succesfully updated', 'wp-data-access' );
								break;
							default:
								// Error
								$status  = 'error';
								$message = $wpdadb->last_error;
						}
					}
				}
			}

			$response = [
				'status'  => $status,
				'message' => $message
			];

			if ( ! $is_embedded ) {
				WPDA::sent_header( 'application/json' );
			} else {
				WPDA::sent_header( 'application/json', '*' );
			}

			echo json_encode( $response, JSON_NUMERIC_CHECK );
			wp_die();
		}

		public function insert_form_data_nm_anonymous() {
			if ( $this->allow_anonymous_access() ) {
				$this->insert_form_data_nm();
			}
		}

		public function insert_form_data_nm() {
			$form_data 	 = json_decode( file_get_contents( "php://input" ) );
			$status    	 = 'ok';
			$message   	 = '';
			$is_embedded = false;

			if (
			! isset(
				$form_data->wpdadataforms_wp_nonce,
				$form_data->wpdadataforms_page_id,
				$form_data->wpdadataforms_schema_name,
				$form_data->wpdadataforms_parent_table_name,
				$form_data->wpdadataforms_child_table_name,
				$form_data->wpdadataforms_values
			)
			) {
				$status  = 'error';
				$message = __( 'Wrong arguments', 'wp-data-access' );
			}

			if ( 'ok' === $status ) {
				// Process arguments
				$wpnonce            	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_wp_nonce ) ); // input var okay.
				$page_id            	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_page_id ) ); // input var okay.
				$schema_name        	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_schema_name ) ); // input var okay.
				$parent_table_name  	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_parent_table_name ) ); // input var okay.
				$child_table_name   	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_child_table_name ) ); // input var okay.
				$wpdadataforms_vals 	= $form_data->wpdadataforms_values;
				$wpdadataforms_embedded	= isset( $form_data->wpdadataforms_embedded ) && true === $form_data->wpdadataforms_embedded;

				$this->get_user_info();
				$this->get_user_info();
				if ( $wpdadataforms_embedded ) {
					// Force user ID = 0 for all embedded actions
					$this->user_id = '0';
				}
				$wpnonce_action = "wpdadataforms-insert-form-data-{$this->user_id}-{$page_id}-{$parent_table_name}";
				$is_embedded    = WPDA::wpda_verify_sonce( $wpnonce, $wpnonce_action );

				// Security check
				if (
					! wp_verify_nonce( $wpnonce, $wpnonce_action ) &&
					! $is_embedded
				) {
					$status  = 'error';
					$message = __( 'Token expired or not authorized', 'wp-data-access' );
				} else {
					$wpdadb = WPDADB::get_db_connection( $schema_name );
					if ( null === $wpdadb ) {
						$status  = 'error';
						$message = sprintf( __( 'Remote database %s not available', 'wp-data-access' ), $schema_name );
					} else {
						$wpdadb->suppress_errors( true );
						$rows_inserted = 0;
						for ( $i = 0; $i < sizeof( $wpdadataforms_vals ); $i++ ) {
							if (
								1 === $wpdadb->insert(
									$child_table_name,
									json_decode( sanitize_text_field( json_encode( $wpdadataforms_vals[ $i ] ) ), true )
								)
							) {
								$rows_inserted++;
							}
						}

						if ( $rows_inserted === sizeof( $wpdadataforms_vals ) ) {
							// All rows successfully inserted
							if ( 1 === $rows_inserted ) {
								$message = __( 'Row successfully inserted', 'wp-data-access' );
							} else {
								$message = sprintf( __( '%d rows successfully inserted', 'wp-data-access' ), $rows_inserted );
							}
						} else {
							// Not all rows inserted
							$status  = 'error';
							$message = __( 'Not all rows inserted', 'wp-data-access' );
						}
					}
				}
			}

			$response = [
				'status'  => $status,
				'message' => $message,
			];

			if ( ! $is_embedded ) {
				WPDA::sent_header( 'application/json' );
			} else {
				WPDA::sent_header( 'application/json', '*' );
			}

			echo json_encode( $response, JSON_NUMERIC_CHECK );
			wp_die();
		}

		public function insert_form_data_anonymous() {
			if ( $this->allow_anonymous_access() ) {
				$this->insert_form_data();
			}
		}

		public function insert_form_data() {
			$form_data           = json_decode( file_get_contents( "php://input" ) );
			$status              = 'ok';
			$message             = '';
			$insert_id           = 0;
			$wpdadataforms_relnm = null;
			$is_embedded 		 = false;

			if (
				! isset( $form_data->wpdadataforms_wp_nonce ) ||
				! isset( $form_data->wpdadataforms_page_id ) ||
				! isset( $form_data->wpdadataforms_schema_name ) ||
				! isset( $form_data->wpdadataforms_table_name ) ||
				! isset( $form_data->wpdadataforms_values )
			) {
				$status  = 'error';
				$message = __( 'Wrong arguments', 'wp-data-access' );
			}

			if ( 'ok' === $status ) {
				if ( isset( $form_data->wpdadataforms_relnm ) && null !== $form_data->wpdadataforms_relnm ) {
					// Request contains a many to many relationship, check argument
					$wpdadataforms_relnm = $form_data->wpdadataforms_relnm;

					if (
						! isset( $wpdadataforms_relnm->relationship_table ) ||
						! isset( $wpdadataforms_relnm->relationship_column ) ||
						! isset( $wpdadataforms_relnm->relationship_value ) ||
						! isset( $wpdadataforms_relnm->relationship_base_column ) ||
						! isset( $wpdadataforms_relnm->relationship_base_value )
					) {
						$status  = 'error';
						$message = __( 'Wrong arguments', 'wp-data-access' );
					}
				}
			}

			if ( 'ok' === $status ) {
				// Process arguments
				$wpnonce            	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_wp_nonce ) ); // input var okay.
				$page_id            	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_page_id ) ); // input var okay.
				$schema_name        	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_schema_name ) ); // input var okay.
				$table_name         	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_table_name ) ); // input var okay.
				$wpdadataforms_vals 	= $form_data->wpdadataforms_values;
				$wpdadataforms_embedded	= isset( $form_data->wpdadataforms_embedded ) && true === $form_data->wpdadataforms_embedded;

				$this->get_user_info();
				if ( $wpdadataforms_embedded ) {
					// Force user ID = 0 for all embedded actions
					$this->user_id = '0';
				}
				$wpnonce_action = "wpdadataforms-insert-form-data-{$this->user_id}-{$page_id}-{$table_name}";
				$is_embedded    = WPDA::wpda_verify_sonce( $wpnonce, $wpnonce_action );

				// Security check
				if (
					! wp_verify_nonce( $wpnonce, $wpnonce_action ) &&
					! $is_embedded
				) {
					$status  = 'error';
					$message = __( 'Token expired or not authorized', 'wp-data-access' );
				} else {
					$vals = [];
					foreach ( $wpdadataforms_vals as $key => $value ) {
						if ( null !== $value ) {
							$vals[ sanitize_text_field( wp_unslash( $key ) ) ] = sanitize_text_field( wp_unslash( $value ) ); // input var okay.
						}
					}

					$wpdadb = WPDADB::get_db_connection( $schema_name );
					if ( null === $wpdadb ) {
						$status  = 'error';
						$message = sprintf( __( 'Remote database %s not available', 'wp-data-access' ), $schema_name );
					} else {
						$wpdadb->suppress_errors( true );
						$upd       = $wpdadb->insert( $table_name, $vals );
						$insert_id = $wpdadb->insert_id; // Return auto increment value if available

						switch ( $upd ) {
							case 1:
								// Success
								if ( null !== $wpdadataforms_relnm ) {
									// Add relationship
									if ( 1 === $this->add_relationship( $wpdadataforms_relnm, $vals, $insert_id ) ) {
										$message = __( 'Row successfully inserted', 'wp-data-access' );
									} else {
										$status  = 'error';
										$message = $wpdadb->last_error;
									}
								} else {
									$message = __( 'Row successfully inserted', 'wp-data-access' );
								}
								break;
							default:
								// Error
								$status  = 'error';
								$message = $wpdadb->last_error;
						}
					}
				}
			}

			$response = [
				'status'    => $status,
				'message'   => $message,
				'insert_id' => $insert_id
			];

			if ( ! $is_embedded ) {
				WPDA::sent_header( 'application/json' );
			} else {
				WPDA::sent_header( 'application/json', '*' );
			}

			echo json_encode( $response, JSON_NUMERIC_CHECK );
			wp_die();
		}

		protected function add_relationship( $relationship, $vals, $insert_id ) {
			global $wpdb;

			$columns = [];
			for ( $i = 0; $i < sizeof( $relationship->relationship_column ); $i++ ) {
				$columns[ $relationship->relationship_column[ $i ] ] =
					$relationship->relationship_value[ $i ];
			}
			for ( $i = 0; $i < sizeof( $relationship->relationship_base_column ); $i++ ) {
				if ( 'auto_increment' === $relationship->relationship_base_value[ $i ] ) {
					$columns[ $relationship->relationship_base_column[ $i ] ] =
						$insert_id;
				} else {
					$columns[ $relationship->relationship_base_column[ $i ] ] =
						$vals[ $relationship->relationship_base_value[ $i ] ];
				}
			}

			return $wpdb->insert( $relationship->relationship_table, $columns );
		}

		public function delete_form_data_anonymous() {
			if ( $this->allow_anonymous_access() ) {
				$this->delete_form_data();
			}
		}

		public function delete_form_data() {
			$form_data   = json_decode( file_get_contents( "php://input" ) );
			$status      = 'ok';
			$message     = '';
			$is_embedded = false;

			if (
				! isset( $form_data->wpdadataforms_wp_nonce ) ||
				! isset( $form_data->wpdadataforms_page_id ) ||
				! isset( $form_data->wpdadataforms_schema_name ) ||
				! isset( $form_data->wpdadataforms_table_name ) ||
				! isset( $form_data->wpdadataforms_pk ) ||
				! isset( $form_data->wpdadataforms_is_child ) ||
				! isset( $form_data->wpdadataforms_set_name )
			) {
				$status  = 'error';
				$message = __( 'Wrong arguments', 'wp-data-access' );
			} else {
				// Process arguments
				$wpnonce          		= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_wp_nonce ) ); // input var okay.
				$page_id          		= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_page_id ) ); // input var okay.
				$schema_name      		= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_schema_name ) ); // input var okay.
				$table_name       		= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_table_name ) ); // input var okay.
				$wpdadataforms_pk 		= $form_data->wpdadataforms_pk;
				$is_child         		= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_is_child ) ); // input var okay.
				$set_name         		= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_set_name ) ); // input var okay.
				$wpdadataforms_embedded	= isset( $form_data->wpdadataforms_embedded ) && true === $form_data->wpdadataforms_embedded;

				$this->get_user_info();
				if ( $wpdadataforms_embedded ) {
					// Force user ID = 0 for all embedded actions
					$this->user_id = '0';
				}
				$wpnonce_action = "wpdadataforms-delete-form-data-{$this->user_id}-{$page_id}-{$table_name}";
				$is_embedded    = WPDA::wpda_verify_sonce( $wpnonce, $wpnonce_action );

				// Security check
				if (
					! wp_verify_nonce( $wpnonce, $wpnonce_action ) &&
					! $is_embedded
				) {
					$status  = 'error';
					$message = __( 'Token expired or not authorized', 'wp-data-access' );
				} else {
					$delete_response = $this->delete( $schema_name, $table_name, $wpdadataforms_pk );
					$status          = $delete_response['status'];
					$message         = $delete_response['message'];

					if ( ! $is_child ) {
						// Delete child rows
						if ( $this->delete_child_rows( $wpdadataforms_pk, $table_name, $set_name, $schema_name ) ) {
							$status  = 'error';
							$message = 'Row deleted! Not all child rows deleted.';
						}
					}
				}
			}

			$response = [
				'status'  => $status,
				'message' => $message
			];

			if ( ! $is_embedded ) {
				WPDA::sent_header( 'application/json' );
			} else {
				WPDA::sent_header( 'application/json', '*' );
			}

			echo json_encode( $response, JSON_NUMERIC_CHECK );
			wp_die();
		}

		protected function delete_child_rows( $parent_key, $table_name, $set_name, $schema_name ) {
			$relationships = WPDP_Project_Design_Table_Model::get_column_options( $table_name, 'relationships', $set_name, $schema_name );
			$has_errors    = false;
			if ( isset( $relationships['relationships'] ) ) {
				foreach ( $relationships['relationships'] as $relationship ) {
					if ( '1n' === $relationship->relation_type || 'nm' === $relationship->relation_type ) {
						$row_to_be_deleted = [];
						$i                 = 0;
						$all_columns_match = true;
						foreach ( $parent_key as $col => $val ) {
							if ( $relationship->source_column_name[ $i ] === $col ) {
								$row_to_be_deleted[ $relationship->target_column_name[ $i ] ] = $val;
								$i++;
							} else {
								$all_columns_match = false;
								$has_errors        = true;
							}
						}
						if ( $all_columns_match ) {
							$this->delete_row_relationship( $schema_name, $relationship->target_table_name, $row_to_be_deleted );
						}
					}
				}
			}
			return $has_errors;
		}

		protected function delete_row_relationship( $schema_name, $table_name, $row_to_be_deleted ) {
			$this->delete( $schema_name, $table_name, $row_to_be_deleted );
		}

		public function delrel_form_data_anonymous() {
			if ( $this->allow_anonymous_access() ) {
				$this->delrel_form_data();
			}
		}

		public function delrel_form_data() {
			$form_data   = json_decode( file_get_contents( "php://input" ) );
			$status      = 'ok';
			$message     = '';
			$is_embedded = false;

			if (
				! isset( $form_data->wpdadataforms_wp_nonce ) ||
				! isset( $form_data->wpdadataforms_page_id ) ||
				! isset( $form_data->wpdadataforms_schema_name ) ||
				! isset( $form_data->wpdadataforms_table_name ) ||
				! isset( $form_data->wpdadataforms_relationship )
			) {
				$status  = 'error';
				$message = __( 'Wrong arguments', 'wp-data-access' );
			} else {
				// Process arguments
				$wpnonce      			= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_wp_nonce ) ); // input var okay.
				$page_id      			= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_page_id ) ); // input var okay.
				$schema_name  			= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_schema_name ) ); // input var okay.
				$table_name   			= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_table_name ) ); // input var okay.
				$relationship 			= $form_data->wpdadataforms_relationship;
				$wpdadataforms_embedded	= isset( $form_data->wpdadataforms_embedded ) && true === $form_data->wpdadataforms_embedded;

				$this->get_user_info();
				if ( $wpdadataforms_embedded ) {
					// Force user ID = 0 for all embedded actions
					$this->user_id = '0';
				}
				$wpnonce_action = "wpdadataforms-delete-form-data-{$this->user_id}-{$page_id}-{$table_name}";
				$is_embedded    = WPDA::wpda_verify_sonce( $wpnonce, $wpnonce_action );

				// Security check
				if (
					! wp_verify_nonce( $wpnonce, $wpnonce_action ) &&
					! $is_embedded
				) {
					$status  = 'error';
					$message = __( 'Token expired or not authorized', 'wp-data-access' );
				} else {
					$column_values = [];

					for ( $i = 0; $i < sizeof( $relationship->relationship_column ); $i++ ) {
						$column_values[ $relationship->relationship_column[ $i ] ] = $relationship->relationship_value[ $i ];
					}

					for ( $i = 0; $i < sizeof( $relationship->relationship_base_column ); $i++ ) {
						$column_values[ $relationship->relationship_base_column[ $i ] ] = $relationship->relationship_base_value[ $i ];
					}

					$delete_response = $this->delete( $schema_name, $relationship->relationship_table, $column_values );

					$status  = $delete_response['status'];
					$message = $delete_response['message'];
				}
			}

			$response = [
				'status'  => $status,
				'message' => $message
			];

			if ( ! $is_embedded ) {
				WPDA::sent_header( 'application/json' );
			} else {
				WPDA::sent_header( 'application/json', '*' );
			}

			echo json_encode( $response, JSON_NUMERIC_CHECK );
			wp_die();
		}

		protected function delete( $schema_name, $table_name, $column_values ) {
			$values = [];

			foreach ( $column_values as $key => $value ) {
				$values[ sanitize_text_field( wp_unslash( $key ) ) ] = sanitize_text_field( wp_unslash( $value ) ); // input var okay.
			}

			$wpdadb = WPDADB::get_db_connection( $schema_name );
			if ( null === $wpdadb ) {
				$status  = 'error';
				$message = sprintf( __( 'Remote database %s not available', 'wp-data-access' ), $schema_name );
			} else {
				$wpdadb->suppress_errors( true );
				$upd = $wpdadb->delete( $table_name, $values );
				switch ( $upd ) {
					case 1:
						// Success
						$status  = 'ok';
						$message = __( 'Row succesfully deleted', 'wp-data-access' );
						break;
					default:
						// Error
						$status  = 'error';
						$message = $wpdadb->last_error;
				}
			}

			return [
				'status'  => $status,
				'message' => $message
			];
		}

		public function lookup_anonymous() {
			if ( $this->allow_anonymous_access() ) {
				$this->lookup();
			}
		}

		public function lookup() {
			$form_data   = json_decode( file_get_contents( "php://input" ) );
			$status      = 'ok';
			$message     = '';
			$rows        = [];
			$is_embedded = false;

			if (
				! isset( $form_data->wpdadataforms_wp_nonce ) ||
				! isset( $form_data->wpdadataforms_page_id ) ||
				! isset( $form_data->wpdadataforms_source_schema_name ) ||
				! isset( $form_data->wpdadataforms_source_table_name ) ||
				! isset( $form_data->wpdadataforms_target_schema_name ) ||
				! isset( $form_data->wpdadataforms_target_table_name ) ||
				! isset( $form_data->wpdadataforms_target_column_name ) ||
				! isset( $form_data->wpdadataforms_target_text_column )
			) {
				$status  = 'error';
				$message = __( 'Wrong arguments', 'wp-data-access' );
			} else {
				// Process arguments
				$wpnonce            	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_wp_nonce ) ); // input var okay.
				$page_id            	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_page_id ) ); // input var okay.
				$source_schema_name 	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_source_schema_name ) ); // input var okay.
				$source_table_name  	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_source_table_name ) ); // input var okay.
				$target_schema_name 	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_target_schema_name ) ); // input var okay.
				$target_table_name  	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_target_table_name ) ); // input var okay.
				$target_column_name 	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_target_column_name ) ); // input var okay.
				$target_text_column 	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_target_text_column ) ); // input var okay.
				$wpdadataforms_embedded	= isset( $form_data->wpdadataforms_embedded ) && true === $form_data->wpdadataforms_embedded;

				$this->get_user_info();
				if ( $wpdadataforms_embedded ) {
					// Force user ID = 0 for all embedded actions
					$this->user_id = '0';
				}
				$wpnonce_action = "wpdadataforms-get-form-data-{$this->user_id}-{$page_id}-{$source_table_name}";
				$is_embedded    = WPDA::wpda_verify_sonce( $wpnonce, $wpnonce_action );

				// Security check
				if (
					! wp_verify_nonce( $wpnonce, $wpnonce_action ) &&
					! $is_embedded
				) {
					$status  = 'error';
					$message = __( 'Token expired or not authorized', 'wp-data-access' );
				} else {
					$wpdadb = WPDADB::get_db_connection( $target_schema_name );
					if ( null === $wpdadb ) {
						$status  = 'error';
						$message = sprintf( __( 'Remote database %s not available', 'wp-data-access' ), $target_schema_name );
					} else {
						$query = "select `{$target_column_name}` as lookup_value, `{$target_text_column}` as lookup_label from `{$target_table_name}` order by 2";
						$rows  = $wpdadb->get_results( $query, 'ARRAY_A' );
					}
				}
			}

			$response = [
				'status'  => $status,
				'message' => $message,
				'rows'    => $rows,
			];

			if ( ! $is_embedded ) {
				WPDA::sent_header( 'application/json' );
			} else {
				WPDA::sent_header( 'application/json', '*' );
			}

			echo json_encode( $response, JSON_NUMERIC_CHECK );
			wp_die();
		}

		public function conditional_lookup_anonymous() {
			if ( $this->allow_anonymous_access() ) {
				$this->conditional_lookup();
			}
		}

		public function conditional_lookup() {
			$form_data   = json_decode( file_get_contents( "php://input" ) );
			$status      = 'ok';
			$message     = '';
			$rows        = [];
			$is_embedded = false;

			if (
			! isset(
				$form_data->wpdadataforms_wp_nonce,
				$form_data->wpdadataforms_page_id,
				$form_data->wpdadataforms_source_schema_name,
				$form_data->wpdadataforms_source_table_name,
				$form_data->wpdadataforms_target_schema_name,
				$form_data->wpdadataforms_target_table_name,
				$form_data->wpdadataforms_target_column_name,
				$form_data->wpdadataforms_target_text_column,
				$form_data->wpdadataforms_filter
			)
			) {
				$status  = 'error';
				$message = __( 'Wrong arguments', 'wp-data-access' );
			} else {
				// Process arguments
				$wpnonce              	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_wp_nonce ) ); // input var okay.
				$page_id              	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_page_id ) ); // input var okay.
				$source_schema_name   	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_source_schema_name ) ); // input var okay.
				$source_table_name    	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_source_table_name ) ); // input var okay.
				$target_schema_name   	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_target_schema_name ) ); // input var okay.
				$target_table_name    	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_target_table_name ) ); // input var okay.
				$target_column_name   	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_target_column_name ) ); // input var okay.
				$target_text_column   	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_target_text_column ) ); // input var okay.
				$wpdadataforms_filter 	= $form_data->wpdadataforms_filter;
				$wpdadataforms_embedded	= isset( $form_data->wpdadataforms_embedded ) && true === $form_data->wpdadataforms_embedded;

				$this->get_user_info();
				if ( $wpdadataforms_embedded ) {
					// Force user ID = 0 for all embedded actions
					$this->user_id = '0';
				}
				$wpnonce_action = "wpdadataforms-get-form-data-{$this->user_id}-{$page_id}-{$source_table_name}";
				$is_embedded    = WPDA::wpda_verify_sonce( $wpnonce, $wpnonce_action );

				// Security check
				if (
					! wp_verify_nonce( $wpnonce, $wpnonce_action ) &&
					! $is_embedded
				) {
					$status  = 'error';
					$message = __( 'Token expired or not authorized', 'wp-data-access' );
				} else {
					// Add condition
					$wpdadb = WPDADB::get_db_connection( $target_schema_name );
					if ( null === $wpdadb ) {
						$status  = 'error';
						$message = sprintf( __( 'Remote database %s not available', 'wp-data-access' ), $target_schema_name );
					} else {
						$where = 'where ';
						foreach ( $wpdadataforms_filter as $key => $val ) {
							$where .=
								$wpdadb->prepare(
									sanitize_text_field( wp_unslash( $key ) ) . ' = %s',
									sanitize_text_field( wp_unslash( $val ) ) // input var okay.
								);
						}

						$query = "select `{$target_column_name}` as lookup_value, `{$target_text_column}` as lookup_label from `{$target_table_name}` {$where}";
						$rows  = $wpdadb->get_results( $query, 'ARRAY_A' );
					}
				}
			}

			$response = [
				'status'  => $status,
				'message' => $message,
				'rows'    => $rows,
			];

			if ( ! $is_embedded ) {
				WPDA::sent_header( 'application/json' );
			} else {
				WPDA::sent_header( 'application/json', '*' );
			}

			echo json_encode( $response, JSON_NUMERIC_CHECK );
			wp_die();
		}

		public function conditional_lookup_get_anonymous() {
			if ( $this->allow_anonymous_access() ) {
				$this->conditional_lookup_get();
			}
		}

		public function conditional_lookup_get() {
			$status  	 = 'ok';
			$message 	 = '';
			$rows    	 = [];
			$is_embedded = false;

			if (
			! isset(
				$_POST['wpdadataforms_wp_nonce'],
				$_POST['wpdadataforms_page_id'],
				$_POST['wpdadataforms_source_schema_name'],
				$_POST['wpdadataforms_source_table_name'],
				$_POST['wpdadataforms_target_schema_name'],
				$_POST['wpdadataforms_target_table_name'],
				$_POST['wpdadataforms_target_column_name'],
				$_POST['wpdadataforms_target_text_column'],
				$_POST['wpdadataforms_filter_column_name'],
				$_POST['wpdadataforms_filter_column_value']
			)
			) {
				$status  = 'error';
				$message = __( 'Wrong arguments', 'wp-data-access' );
			} else {
				// Process arguments
				$wpnonce             	= sanitize_text_field( wp_unslash( $_POST['wpdadataforms_wp_nonce'] ) ); // input var okay.
				$page_id             	= sanitize_text_field( wp_unslash( $_POST['wpdadataforms_page_id'] ) ); // input var okay.
				$source_schema_name  	= sanitize_text_field( wp_unslash( $_POST['wpdadataforms_source_schema_name'] ) ); // input var okay.
				$source_table_name   	= sanitize_text_field( wp_unslash( $_POST['wpdadataforms_source_table_name'] ) ); // input var okay.
				$target_schema_name  	= sanitize_text_field( wp_unslash( $_POST['wpdadataforms_target_schema_name'] ) ); // input var okay.
				$target_table_name   	= sanitize_text_field( wp_unslash( $_POST['wpdadataforms_target_table_name'] ) ); // input var okay.
				$target_column_name  	= sanitize_text_field( wp_unslash( $_POST['wpdadataforms_target_column_name'] ) ); // input var okay.
				$target_text_column  	= sanitize_text_field( wp_unslash( $_POST['wpdadataforms_target_text_column'] ) ); // input var okay.
				$filter_column_name  	= sanitize_text_field( wp_unslash( $_POST['wpdadataforms_filter_column_name'] ) ); // input var okay.
				$filter_column_value 	= sanitize_text_field( wp_unslash( $_POST['wpdadataforms_filter_column_value'] ) ); // input var okay.
				$wpdadataforms_embedded	= isset( $form_data->wpdadataforms_embedded ) && true === $form_data->wpdadataforms_embedded;

				$this->get_user_info();
				if ( $wpdadataforms_embedded ) {
					// Force user ID = 0 for all embedded actions
					$this->user_id = '0';
				}
				$wpnonce_action = "wpdadataforms-get-form-data-{$this->user_id}-{$page_id}-{$source_table_name}";
				$is_embedded    = WPDA::wpda_verify_sonce( $wpnonce, $wpnonce_action );

				// Security check
				if (
					! wp_verify_nonce( $wpnonce, $wpnonce_action ) &&
					! $is_embedded
				) {
					$status  = 'error';
					$message = __( 'Token expired or not authorized', 'wp-data-access' );
				} else {
					// Add condition
					$wpdadb = WPDADB::get_db_connection( $target_schema_name );
					if ( null === $wpdadb ) {
						$status  = 'error';
						$message = sprintf( __( 'Remote database %s not available', 'wp-data-access' ), $target_schema_name );
					} else {
						$query = "
							select distinct `{$target_column_name}` as lookup_value, 
											`{$target_text_column}` as lookup_label
							from `{$target_table_name}`
							where `{$filter_column_name}` = %s
						";
						$rows  = $wpdadb->get_results(
							$wpdadb->prepare(
								$query,
								$filter_column_value
							)
							, 'ARRAY_A'
						);
					}
				}
			}

			$response = [
				'status'  => $status,
				'message' => $message,
				'rows'    => $rows,
			];

			if ( ! $is_embedded ) {
				WPDA::sent_header( 'application/json' );
			} else {
				WPDA::sent_header( 'application/json', '*' );
			}

			echo json_encode( $response, JSON_NUMERIC_CHECK );
			wp_die();
		}

		public function autocomplete_anonymous() {
			if ( $this->allow_anonymous_access() ) {
				$this->autocomplete();
			}
		}

		public function autocomplete() {
			$form_data   = json_decode( file_get_contents( "php://input" ) );
			$status      = 'ok';
			$message     = '';
			$rows        = [];
			$is_embedded = false;

			if (
			! isset(
				$form_data->wpdadataforms_wp_nonce,
				$form_data->wpdadataforms_page_id,
				$form_data->wpdadataforms_source_schema_name,
				$form_data->wpdadataforms_source_table_name,
				$form_data->wpdadataforms_target_schema_name,
				$form_data->wpdadataforms_target_table_name,
				$form_data->wpdadataforms_target_column_name,
				$form_data->wpdadataforms_lookup_column_name,
				$form_data->wpdadataforms_lookup_column_value
			)
			) {
				$status  = 'error';
				$message = __( 'Wrong arguments', 'wp-data-access' );
			} else {
				// Process arguments
				$wpnonce             	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_wp_nonce ) ); // input var okay.
				$page_id             	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_page_id ) ); // input var okay.
				$source_schema_name  	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_source_schema_name ) ); // input var okay.
				$source_table_name   	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_source_table_name ) ); // input var okay.
				$target_schema_name  	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_target_schema_name ) ); // input var okay.
				$target_table_name   	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_target_table_name ) ); // input var okay.
				$target_column_name  	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_target_column_name ) ); // input var okay.
				$lookup_column_name  	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_lookup_column_name ) ); // input var okay.
				$lookup_column_value 	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_lookup_column_value ) ); // input var okay.
				$wpdadataforms_embedded	= isset( $form_data->wpdadataforms_embedded ) && true === $form_data->wpdadataforms_embedded;

				$this->get_user_info();
				if ( $wpdadataforms_embedded ) {
					// Force user ID = 0 for all embedded actions
					$this->user_id = '0';
				}
				$wpnonce_action = "wpdadataforms-get-form-data-{$this->user_id}-{$page_id}-{$source_table_name}";
				$is_embedded    = WPDA::wpda_verify_sonce( $wpnonce, $wpnonce_action );

				if (
					! wp_verify_nonce( $wpnonce, $wpnonce_action ) &&
					! $is_embedded
				) {
					$status  = 'error';
					$message = __( 'Token expired or not authorized', 'wp-data-access' );
				} else {
					$autocomplete = new WPDA_Autocomplete();
					$rows         = $autocomplete->autocomplete_query(
						$target_schema_name,
						$target_table_name,
						$target_column_name,
						$lookup_column_name,
						$lookup_column_value
					);
				}
			}

			$response = [
				'status'  => $status,
				'message' => $message,
				'rows'    => $rows,
			];

			if ( ! $is_embedded ) {
				WPDA::sent_header( 'application/json' );
			} else {
				WPDA::sent_header( 'application/json', '*' );
			}

			echo json_encode( $response, JSON_NUMERIC_CHECK );
			wp_die();
		}

		public function autocomplete_get_anonymous() {
			if ( $this->allow_anonymous_access() ) {
				$this->autocomplete_get();
			}
		}

		public function autocomplete_get() {
			$form_data = json_decode( file_get_contents( "php://input" ) );
			if ( null === $form_data ) {
				// Service is called from rendered: use $_POST
				$form_data = (object) $_POST;
			}

			$status  	 = 'ok';
			$message 	 = '';
			$lookup  	 = false;
			$is_embedded = false;

			if (
			! isset(
				$form_data->wpdadataforms_wp_nonce,
				$form_data->wpdadataforms_page_id,
				$form_data->wpdadataforms_source_schema_name,
				$form_data->wpdadataforms_source_table_name,
				$form_data->wpdadataforms_target_schema_name,
				$form_data->wpdadataforms_target_table_name,
				$form_data->wpdadataforms_target_column_name,
				$form_data->wpdadataforms_lookup_column_name,
				$form_data->wpdadataforms_lookup_column_value
			)
			) {
				$status  = 'error';
				$message = __( 'Wrong arguments', 'wp-data-access' );
			} else {
				// Process arguments
				$wpnonce             	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_wp_nonce ) ); // input var okay.
				$page_id             	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_page_id ) ); // input var okay.
				$source_schema_name  	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_source_schema_name ) ); // input var okay.
				$source_table_name   	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_source_table_name ) ); // input var okay.
				$target_schema_name  	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_target_schema_name ) ); // input var okay.
				$target_table_name   	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_target_table_name ) ); // input var okay.
				$target_column_name  	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_target_column_name ) ); // input var okay.
				$lookup_column_name  	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_lookup_column_name ) ); // input var okay.
				$lookup_column_value 	= sanitize_text_field( wp_unslash( $form_data->wpdadataforms_lookup_column_value ) ); // input var okay.
				$wpdadataforms_embedded	= isset( $form_data->wpdadataforms_embedded ) && true === $form_data->wpdadataforms_embedded;

				$this->get_user_info();
				if ( $wpdadataforms_embedded ) {
					// Force user ID = 0 for all embedded actions
					$this->user_id = '0';
				}
				$wpnonce_action = "wpdadataforms-get-form-data-{$this->user_id}-{$page_id}-{$source_table_name}";
				$is_embedded    = WPDA::wpda_verify_sonce( $wpnonce, $wpnonce_action );

				if (
					! wp_verify_nonce( $wpnonce, $wpnonce_action ) &&
					! $is_embedded
				) {
					$status  = 'error';
					$message = __( 'Token expired or not authorized', 'wp-data-access' );
				} else {
					$autocomplete = new WPDA_Autocomplete();
					$lookup       = $autocomplete->autocomplete_lookup(
						$target_schema_name,
						$target_table_name,
						$target_column_name,
						$lookup_column_name,
						$lookup_column_value
					);
				}
			}

			$response = [
				'status'  => $status,
				'message' => $message,
				'lookup'  => $lookup,
			];

			if ( ! $is_embedded ) {
				WPDA::sent_header( 'application/json' );
			} else {
				WPDA::sent_header( 'application/json', '*' );
			}

			echo json_encode( $response, JSON_NUMERIC_CHECK );
			wp_die();
		}

		protected function allow_anonymous_access() {
			if ( 'on' === WPDA::get_option( WPDA::OPTION_PLUGIN_WPDADATAFORMS_ALLOW_ANONYMOUS_ACCESS ) ) {
				// Anonymous access granted
				return true;
			} else {
				// No anonymous access allowed
				WPDA::sent_header('application/json', '*');
				echo 'Anonymous access disabled';
				return false;
			}
		}

		private function create_empty_response( $error = '' ) {
			$obj                  = (object) null;
			$obj->draw            = 0;
			$obj->recordsTotal    = 0;
			$obj->recordsFiltered = 0;
			$obj->data            = [];
			$obj->error           = $error;

			echo json_encode( $obj );
		}

	}

}