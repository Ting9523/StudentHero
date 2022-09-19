<?php

namespace WPDataAccess\Premium\WPDAPRO_Data_Forms {

	use WPDataAccess\Connection\WPDADB;
	use WPDataAccess\Plugin_Table_Models\WPDA_Table_Settings_Model;
	use WPDataAccess\Plugin_Table_Models\WPDP_Page_Model;
	use WPDataAccess\Plugin_Table_Models\WPDP_Project_Design_Table_Model;
	use WPDataAccess\WPDA;
	use WPDataProjects\Data_Dictionary\WPDP_List_Columns_Cache;
	use WPDataProjects\Project\WPDP_Project;
	use WPDataAccess\Plugin_Table_Models\WPDA_Media_Model;

	class WPDAPRO_Data_Forms {

		protected $project_id  = null;
		protected $page_id     = null;
		protected $form_id     = 0;

		protected $project      = null;
		protected $project_page = null;

		protected $page_properties    = [];
		protected $table_properties   = [];
		protected $child_tables       = [];
		protected $table_lookups      = [];

		protected $table_settings = [];
		protected $tableinfo      = [];
		protected $table_media    = [];

		protected $embedding = false;

		public function __construct( $project_id, $page_id, $args = [] ) {
			$this->project_id = $project_id;
			$this->page_id    = $page_id;

			WPDAPRO_Data_Forms_Init::activate_styles();
			WPDAPRO_Data_Forms_Init::activate_scripts();

			if ( isset( $args['embedding'] ) && true === $args['embedding'] ) {
				$this->embedding = true;
			}
		}

		protected function get_wp_nonce( $wpnonce_action ) {
			return ! $this->embedding ? wp_create_nonce( $wpnonce_action ) : WPDA::wpda_create_sonce( $wpnonce_action );
		}

		protected function add_table_properties( $schema_name, $table_name, $setname, $is_child = false ) {
			$this->page_properties = [
				"page_type"    => $this->project_page['page_type'],
				"page_setname" => $this->project_page['page_setname'],
				"title"        => $this->project_page['page_title'],
				"mode"         => $this->project_page['page_mode'],
				"allow_insert" => $this->project_page['page_allow_insert'],
				"allow_delete" => $this->project_page['page_allow_delete'],
				"allow_import" => $this->project_page['page_allow_import'],
				"allow_bulk"   => $this->project_page['page_allow_bulk'],
				"page_orderby" => $this->project_page['page_orderby'],
			];

			// Force user ID = 0 for all embedded actions
			$user_id = ! $this->embedding ? get_current_user_id() : 0;

			$this->table_properties[ $schema_name ][ $table_name ][ '_get_list' ] =
				$this->get_wp_nonce( "wpdadataforms-get-table-{$user_id}-{$this->page_id}-{$table_name}" );

			$this->table_properties[ $schema_name ][ $table_name ]['_get_form_data'] =
				$this->get_wp_nonce( "wpdadataforms-get-form-data-{$user_id}-{$this->page_id}-{$table_name}" );

			if ( "edit" === $this->project_page['page_mode'] ) {
				// Add DDL nonces only when user has access
				$this->table_properties[ $schema_name ][ $table_name ]['_update_form_data'] =
					$this->get_wp_nonce( "wpdadataforms-update-form-data-{$user_id}-{$this->page_id}-{$table_name}" );

				if ( "yes" === $this->project_page['page_allow_insert'] ) {
					$this->table_properties[ $schema_name ][ $table_name ]['_insert_form_data'] =
						$this->get_wp_nonce( "wpdadataforms-insert-form-data-{$user_id}-{$this->page_id}-{$table_name}" );
				}

				if ( "yes" === $this->project_page['page_allow_delete'] ) {
					$this->table_properties[ $schema_name ][ $table_name ]['_delete_form_data'] =
						$this->get_wp_nonce( "wpdadataforms-delete-form-data-{$user_id}-{$this->page_id}-{$table_name}" );
				}
			}

			$listtable =
				WPDP_List_Columns_Cache::get_list_columns(
					$schema_name,
					$table_name,
					'listtable',
					$setname
				);

			$tableform =
				WPDP_List_Columns_Cache::get_list_columns(
					$schema_name,
					$table_name,
					'tableform',
					$setname
				);

			// Get table settings
			$table_settings_db = WPDA_Table_Settings_Model::query( $table_name, $schema_name );
			if ( isset( $table_settings_db[0]['wpda_table_settings'] ) ) {
				$this->table_settings[ $table_name ][ $is_child ] = json_decode( $table_settings_db[0]['wpda_table_settings'] );
			}

			$this->table_properties[ $schema_name ][ $table_name ][ 'primary_key' ]         = array_flip( $listtable->get_table_primary_key() );
			$this->table_properties[ $schema_name ][ $table_name ][ 'columns' ]             = $listtable->get_all_columns();
			$this->table_properties[ $schema_name ][ $table_name ][ 'date_columns' ]        = [];
			$this->table_properties[ $schema_name ][ $table_name ][ 'table' ]               = $listtable->get_table_columns();
			$this->table_properties[ $schema_name ][ $table_name ][ 'table_hidden' ]        = [];
			$this->table_properties[ $schema_name ][ $table_name ][ 'form' ]                = [];
			$this->table_properties[ $schema_name ][ $table_name ][ 'form_less' ]           = [];
			$this->table_properties[ $schema_name ][ $table_name ][ 'form_hidden' ]         = [];
			$this->table_properties[ $schema_name ][ $table_name ][ 'lookups' ]             = [];
			$this->table_properties[ $schema_name ][ $table_name ][ 'conditional_lookups' ] = [];
			$this->table_properties[ $schema_name ][ $table_name ][ 'table_settings' ]      = [];

			if ( isset( $this->table_settings[ $table_name ][ $is_child ]->table_settings ) ) {
				$this->table_properties[ $schema_name ][ $table_name ][ 'table_settings' ] =
					$this->table_settings[ $table_name ][ $is_child ]->table_settings;
			}

			$cols = $this->table_properties[ $schema_name ][ $table_name ][ 'columns' ];

			// Prepare column section for table
			// (1) Set all primary key columns to false
			$pks = $this->table_properties[ $schema_name ][ $table_name ][ 'primary_key' ];
			foreach ( $pks as $pk => $val ) {
				$pks[ $pk ] = false;
			}
			// Loop through all column shown in table
			// (2) Set column label
			// (3) Check if primary key column
			for ( $i = 0; $i < sizeof( $this->table_properties[ $schema_name ][ $table_name ][ 'table' ] ); $i++ ) {
				$column_name = $this->table_properties[ $schema_name ][ $table_name ][ 'table' ][ $i ][ 'column_name' ];
				$this->table_properties[ $schema_name ][ $table_name ][ 'table' ][ $i ][ 'label' ] =
					$listtable->get_column_label( $column_name );

				foreach ( $pks as $pk => $val ) {
					if ( $pk === $column_name ) {
						// Primary key column is visible, so there is no need to show it as a hidden column
						$pks[ $pk ] = true;
						if ( isset( $this->table_properties[ $schema_name ][ $table_name ][ 'primary_key' ][ $pk ] ) ) {
							// Remember the position of the column in the table
							$this->table_properties[ $schema_name ][ $table_name ][ 'primary_key' ][ $pk ] = $i;
						}
					}
				}
			}
			// Loop through primary key columns
			// (4) Primary key columns not shown in the table, are added as hidden fields
			foreach ( $pks as $pk => $val ) {
				if ( ! $pks[ $pk ] ) {
					// Add column to selection
					foreach ( $cols as $col ) {
						if ( $pk === $col['column_name'] ) {
							$col['label'] = $listtable->get_column_label( $col['column_name'] );
							$this->table_properties[ $schema_name ][ $table_name ][ 'table_hidden' ][] =
								sizeof( $this->table_properties[ $schema_name ][ $table_name ][ 'table' ] );
							if ( isset( $this->table_properties[ $schema_name ][ $table_name ][ 'primary_key' ][ $pk ] ) ) {
								$this->table_properties[ $schema_name ][ $table_name ]['primary_key'][ $pk ] =
									sizeof( $this->table_properties[ $schema_name ][ $table_name ]['table'] );
							}
							$this->table_properties[ $schema_name ][ $table_name ][ 'table' ][] = $col;
						}
					}
				}
			}

			// Prepare column section for data entry form
			// (1) Add all visible column to form
			// (2) Add all column defined as less to form_less (used for quick parent view)
			foreach ( $tableform->get_table_columns() as $table_column ) {
				if ( isset( $table_column['show'] ) && false === $table_column['show'] ) {
					// Hide column
				} else {
					// Show column
					$add_to_view = false;
					if ( isset( $table_column['less'] ) && false === $table_column['less'] ) {
						// Hide column in view
					} else {
						// Add column to view
						$add_to_view = true;
					}
					$table_column['label'] = $tableform->get_column_label(  $table_column['column_name'] );
					$table_column['ng_data_type'] = $this->get_ng_data_type( $table_column['data_type'] );
					if ( 'tinyint(1)' === $table_column['column_type'] ) {
						$table_column['ng_data_type'] = 'checkbox';
					}

					unset( $table_column['show'] );
					unset( $table_column['less'] );

					$this->table_properties[ $schema_name ][ $table_name ][ 'form' ][] = $table_column;
					if ( $add_to_view ) {
						$this->table_properties[ $schema_name ][ $table_name ]['form_less'][] = $table_column;
					}
				}

				if (
					'date' === $table_column['data_type'] ||
					'datetime' === $table_column['data_type'] ||
					'time' === $table_column['data_type'] ||
					'timestamp' === $table_column['data_type']
				) {
					$this->table_properties[ $schema_name ][ $table_name ][ 'date_columns' ][ $table_column['column_name'] ] = $table_column['data_type'];
				}

				// ???
				// Store media columns
				$media_type = WPDA_Media_Model::get_column_media( $table_name, $table_column['column_name'], $schema_name );
				if (
					'Hyperlink' === $media_type &&
					isset( $this->table_properties[ $schema_name ][ $table_name ][ 'table_settings' ]->hyperlink_definition ) &&
					'text' === $this->table_properties[ $schema_name ][ $table_name ][ 'table_settings' ]->hyperlink_definition
				) {
					$media_type = 'text';
				}
				if ( false !== $media_type ) {
					$this->table_media[ $schema_name ][ $table_name ][ $table_column['column_name'] ] = $media_type;
				}
			}

			// (3) Show all visible items if form_less is empty
			if ( 0 === sizeof( $this->table_properties[ $schema_name ][ $table_name ][ 'form_less' ] ) ) {
				$this->table_properties[ $schema_name ][ $table_name ][ 'form_less' ] = $this->table_properties[ $schema_name ][ $table_name ][ 'form' ];
			}

			// (4) Maintain key list > keys not displayed need to be added as hidden fields to support SQL DML
			foreach ( $this->table_properties[ $schema_name ][ $table_name ][ 'primary_key' ] as $key => $val ) {
				$key_visible = false;
				for ( $i = 0; $i < sizeof( $this->table_properties[ $schema_name ][ $table_name ][ 'form' ] ); $i++ ) {
					$column_name = $this->table_properties[ $schema_name ][ $table_name ][ 'form' ][ $i ][ 'column_name' ];
					if ( $column_name === $key ) {
						$key_visible = true;
					}
				}
				if ( ! $key_visible ) {
					// Add primary key as hidden field
					$this->table_properties[ $schema_name ][ $table_name ]['form_hidden'][] = $key;
				}
			}

			// (5) Hide child key columns (getting their values from the parent table)
			if ( $is_child && isset( $this->child_tables[$table_name]['relation_1n']['child_key'] ) ) {
				$child_key_columns = $this->child_tables[$table_name]['relation_1n']['child_key'];
				foreach ( $child_key_columns as $child_index => $child_column ) {
					foreach ( $this->table_properties[ $schema_name ][ $table_name ][ 'form' ] as $form_index => $form_column ) {
						if ( $child_column === $form_column['column_name'] ) {
							$this->table_properties[ $schema_name ][ $table_name ][ 'form_hidden' ][] = $this->table_properties[ $schema_name ][ $table_name ][ 'form' ][ $form_index ][ 'column_name' ];
						}
					}
				}
			}

			// Save lookups
			$relationships = WPDP_Project_Design_Table_Model::get_column_options( $table_name, 'relationships', $setname, $schema_name );
			if ( isset( $relationships['relationships'] ) ) {
				foreach ( $relationships['relationships'] as $relationship ) {
					if ( 'lookup' === $relationship->relation_type || 'autocomplete' === $relationship->relation_type ) {
						$this->table_lookups[ $table_name ][ $relationship->source_column_name[0] ] = $relationship;
					}
				}
			}

			// Get lookup settings
			// Table lookups are performed before the listtable is displayed
			$listtable_lookups = WPDP_Project_Design_Table_Model::get_column_options( $table_name, 'listtable', $setname, $schema_name );
			if ( null !== $listtable_lookups ) {
				foreach ( $listtable_lookups as $lookup ) {
					if ( isset( $lookup->lookup ) && false !== $lookup->lookup ) {
						for ( $i = 0; $i < sizeof( $this->table_properties[ $schema_name ][ $table_name ]['table'] ); $i++ ) {
							if ( $lookup->column_name === $this->table_properties[ $schema_name ][ $table_name ]['table'][ $i ]['column_name'] ) {
								if ( 1 === sizeof( $this->table_lookups[$table_name][$lookup->column_name]->target_column_name ) ) {
									if ( 'lookup' === $this->table_lookups[$table_name][$lookup->column_name]->relation_type ) {
										// Store lookup values in array
										// Listboxes can only ne cached for single column lookups
										// Conditional lookups can only be processed in real time
										$target_column_name = str_replace( '`', '', $this->table_lookups[ $table_name ][ $lookup->column_name ]->target_column_name[0] );
										$target_text_column = str_replace( '`', '', $lookup->lookup );
										$wpdadb             = WPDADB::get_db_connection( $this->table_lookups[ $table_name ][ $lookup->column_name ]->target_schema_name );
										if ( null !== $wpdadb ) {
											$query = "select `{$target_column_name}` as lookup_value, `{$target_text_column}` as lookup_label from `" . str_replace( '`', '', $this->table_lookups[$table_name][$lookup->column_name]->target_table_name ) . "` order by 2";
											$rows  = $wpdadb->get_results( $query, 'ARRAY_A' );
											$list  = [];

											foreach ( $rows as $row ) {
												$list[ $row['lookup_value'] ] = $row['lookup_label'];
											}
											$this->table_properties[ $schema_name ][ $table_name ]['table_lookups'][ $i ] = $list;
											$this->table_properties[ $schema_name ][ $table_name ]['table_lookups_sorted'][ $i ] = $rows;
										}
									} elseif ( 'autocomplete' === $this->table_lookups[$table_name][$lookup->column_name]->relation_type ) {
										// ???
										$this->table_properties[ $schema_name ][ $table_name ]['table_autocomplete'][ $i ] = [
											'wpdadataforms_wp_nonce'            => $this->table_properties[ $schema_name ][ $table_name ][ '_get_form_data' ],
											'wpdadataforms_page_id'             => $this->page_id,
											'wpdadataforms_source_schema_name'  => $schema_name,
											'wpdadataforms_source_table_name'   => $table_name,
											'wpdadataforms_target_schema_name'  => $this->table_lookups[$table_name][$lookup->column_name]->target_schema_name,
											'wpdadataforms_target_table_name'   => $this->table_lookups[$table_name][$lookup->column_name]->target_table_name,
											'wpdadataforms_target_column_name'  => $this->table_lookups[$table_name][$lookup->column_name]->target_column_name[0],
											'wpdadataforms_lookup_column_name'  => $lookup->lookup,
											'wpdadataforms_lookup_column_value' => null
										];
									}
								} else {
									// Add conditional lookup info
									$wpdadataforms_filter_column_name                                                                         =
										$this->table_lookups[$table_name][$lookup->column_name]->source_column_name[ 0 ];
									$wpdadataforms_filter_column_value                                                                        = null;
									$this->table_properties[ $schema_name ][ $table_name ]['conditional_lookups'][ $i ]                       = [
										'wpdadataforms_page_id'             => $this->page_id,
										'wpdadataforms_source_schema_name'  => $schema_name,
										'wpdadataforms_source_table_name'   => $table_name,
										'wpdadataforms_target_schema_name'  => $this->table_lookups[$table_name][$lookup->column_name]->target_schema_name,
										'wpdadataforms_target_table_name'   => $this->table_lookups[$table_name][$lookup->column_name]->target_table_name,
										'wpdadataforms_target_column_name'  => $this->table_lookups[$table_name][$lookup->column_name]->target_column_name[0],
										'wpdadataforms_target_text_column'  => $lookup->lookup,
										'wpdadataforms_wp_nonce'            => $this->table_properties[ $schema_name ][ $table_name ][ '_get_form_data' ],
										'wpdadataforms_filter_column_name'  => $wpdadataforms_filter_column_name,
										'wpdadataforms_filter_column_value' => $wpdadataforms_filter_column_value,
									];
								}
							}
						}
					}
				}
			}

			// Form lookups are perform when needed
			$tableform_lookups = WPDP_Project_Design_Table_Model::get_column_options( $table_name, 'tableform', $setname, $schema_name );
			if ( null !== $tableform_lookups ) {
				foreach ( $tableform_lookups as $lookup ) {
					if ( isset( $lookup->lookup ) && false !== $lookup->lookup ) {
						for ( $i = 0; $i < sizeof( $this->table_properties[ $schema_name ][ $table_name ]['form'] ); $i++ ) {
							if (
									isset( $this->table_properties[ $schema_name ][ $table_name ]['form'][ $i ]['column_name'] ) &&
									$lookup->column_name === $this->table_properties[ $schema_name ][ $table_name ]['form'][ $i ]['column_name']
							) {
								$this->table_properties[ $schema_name ][ $table_name ]['lookups'][ $lookup->column_name ]['lookup']          = $lookup->lookup;
								$this->table_properties[ $schema_name ][ $table_name ]['lookups'][ $lookup->column_name ]['hide_lookup_key'] = isset( $lookup->hide_lookup_key ) ? $lookup->hide_lookup_key : 'off';
							}
						}
					}
				}
			}

			// Add hyperlinks
			$this->tableinfo[ $table_name ] = WPDP_Project_Design_Table_Model::get_column_options( $table_name, 'tableinfo', $setname, $schema_name );
			if ( isset( $this->table_settings[ $table_name ][ $is_child ]->hyperlinks ) ) {
				foreach ( $this->table_settings[ $table_name ][ $is_child ]->hyperlinks as $hyperlink ) {
					if ( self::is_hyperlink_enabled( $hyperlink, $this->tableinfo[ $table_name ], $is_child ) ) {
						$this->table_properties[ $schema_name ][ $table_name ][ 'table' ][] = null;
					}
				}
			}
		}

		public static function is_hyperlink_enabled( $hyperlink, $tableinfo, $is_child ) {
			if ( isset( $hyperlink->hyperlink_list ) && $hyperlink->hyperlink_list ) {
				if ( $is_child ) {
					$add_hyperlink =
						isset( $tableinfo->hyperlinks_child ) ?
							$tableinfo->hyperlinks_child : null;
				} else {
					$add_hyperlink =
						isset( $tableinfo->hyperlinks_parent ) ?
							$tableinfo->hyperlinks_parent : null;
				}

				if (
					null !== $add_hyperlink &&
					property_exists( $add_hyperlink, $hyperlink->hyperlink_label )
				) {
					return $add_hyperlink->{$hyperlink->hyperlink_label};
				}
			}

			return false;
		}

		public function show() {
			// Get relevant project data
			$project_page = WPDP_Page_Model::get_page( $this->project_id, $this->page_id );
			if ( ! is_array( $project_page ) || 1 !== sizeof( $project_page ) ) {
				echo __( 'ERROR: Data Project page not found [need a valid project_id and page_id]', 'wp-data-access' );
				return;
			}

			// Check access
			if ( 'off' !== WPDA::get_option( WPDA::OPTION_WPDA_USE_ROLES_IN_SHORTCODE ) ) {
				if ( '' !== $project_page[0]['page_role'] && null !== $project_page[0]['page_role'] ) {
					$user_roles = WPDA::get_current_user_roles();
					if ( false === $user_roles ) {
						$user_roles = [];
					}

					$user_has_role = false;
					if ( sizeof( $user_roles ) > 0 ) {
						foreach ( $user_roles as $user_role ) {
							if ( stripos( strval( $project_page[0]['page_role'] ), strval( $user_role ) ) !== false ) {
								$user_has_role = true;
							}
						}
					}

					if ( ! $user_has_role ) {
						echo __( 'INFO: You have no access to this page [check page roles]', 'wp-data-access' );;
						return;
					}
				}
			}

			$this->project_page = $project_page[0];
			$this->add_table_properties(
				$this->project_page['page_schema_name'],
				$this->project_page['page_table_name'],
				$this->project_page['page_setname']
			);

			$relationships = WPDP_Project_Design_Table_Model::get_column_options(
				$this->project_page['page_table_name'],
				'relationships',
				$this->project_page['page_setname'],
				$this->project_page['page_schema_name']
			);

			$this->project = new WPDP_Project( $this->project_id, $this->page_id );
			foreach ( $this->project->get_children() as $child ) {
				if ( isset( $child['relation_1n'] ) || isset( $child['relation_nm'] ) ) {
					$this->child_tables[ $child['table_name'] ] = $child;

					if ( isset( $child['relation_nm'] ) ) {
						if ( isset( $relationships['relationships'] ) ) {
							foreach ( $relationships['relationships'] as $relationship) {
								if ( 'nm' === $relationship->relation_type ) {
									$this->child_tables[ $child['table_name'] ][ 'child_table' ] = $relationship;
								}
							}
						}
					}

					$this->add_table_properties(
						$this->project_page['page_schema_name'],
						$child['table_name'],
						$this->project_page['page_setname'],
						true
					);
				}
			}

			$debug_mode    = WPDA::get_option( WPDA::OPTION_PLUGIN_DEBUG );
			$table_options = '{ "dom": "lfrtip" }'; // TODO Add table options to Data Projects
			$table_options = str_replace( ["\r\n", "\r", "\n", "\t"], '', $table_options );
			?>
			<div id="wpdadataforms_page_<?php echo esc_attr( $this->page_id ); ?>"
				 ng-app="wpdadataforms_page_<?php echo esc_attr( $this->page_id ); ?>"
				 class="wpdadataforms_page"
			>
				<?php
				$this->table( $this->project_page['page_schema_name'], $this->project_page['page_table_name'] );
				?>
			</div>
			<script type="text/javascript">
				jQuery(function() {
					// Make ajax URL globally available (needed to built DataTables)
					wpdaDataFormsAjaxUrl = "<?php echo admin_url('admin-ajax.php'); ?>";
					wpdaDataFormsLanguage = "<?php echo WPDA::get_option( WPDA::OPTION_DP_LANGUAGE ); ?>";
					wpdaDataFormsIsEmbedded = <?php echo $this->embedding ? 'true' : 'false'; ?>;

					// Make table properties and child tables globally available to DataTables and Angular
					wpdaDataFormsProjectInfo['<?php echo esc_attr( $this->page_id ); ?>'] = <?php echo json_encode( $this->page_properties ); ?>;
					wpdaDataFormsProjectPages['<?php echo esc_attr( $this->page_id ); ?>'] = <?php echo json_encode( $this->table_properties ); ?>;
					wpdaDataFormsProjectChildTables['<?php echo esc_attr( $this->page_id ); ?>'] = <?php echo json_encode( $this->child_tables ); ?>;
					wpdaDataFormsProjectMedia['<?php echo esc_attr( $this->page_id ); ?>'] = <?php echo json_encode( $this->table_media ); ?>;
					wpdaDataFormsProjectLookupTables['<?php echo esc_attr( $this->page_id ); ?>'] = <?php echo json_encode( $this->table_lookups ); ?>;
					wpdaDataFormsTableOptions['<?php echo esc_attr( $this->page_id ); ?>'] = "<?php echo esc_attr( $table_options ); ?>";

					<?php
					if ( is_admin() ) {
						echo "wpdaDataFormsIsFrontEnd = false;";
					}
					?>

					// Add parent table
					wpdadataforms_table(
						'<?php echo esc_attr( $this->page_id ); ?>',
						'<?php echo esc_attr( $this->project_page['page_schema_name'] ); ?>',
						'<?php echo esc_attr( $this->project_page['page_table_name'] ); ?>'
					);

					// Add parent data entry form (including child tabs)
					var wpdadataforms_app = angular.module('wpdadataforms_page_<?php echo esc_attr( $this->page_id ); ?>', []);

					wpdadataformsFeatures(wpdadataforms_app);

					wpdadataforms_app.config(function($logProvider){
						$logProvider.debugEnabled(<?php echo $debug_mode === 'on' ? 'true' : 'false'; ?>);
					});

					// Add form controller for base table
					wpdadataforms_add_controller(
						wpdadataforms_app,
						'<?php echo esc_attr( $this->page_id ); ?>',
						'<?php echo esc_attr( $this->project_page['page_schema_name'] ); ?>',
						'<?php echo esc_attr( $this->project_page['page_table_name'] ); ?>',
						'_form'
					);

					// Add view controller for base table
					wpdadataforms_add_controller(
						wpdadataforms_app,
						'<?php echo esc_attr( $this->page_id ); ?>',
						'<?php echo esc_attr( $this->project_page['page_schema_name'] ); ?>',
						'<?php echo esc_attr( $this->project_page['page_table_name'] ); ?>',
						'_view'
					);
					<?php

					// Add child data entry forms (child tables are automatically added to parent data entry form)
					foreach ( $this->child_tables as $child_table ) {
						if ( isset( $child_table['relation_1n'] ) || isset( $child_table['relation_nm'] ) ) {
							?>
							wpdadataforms_add_controller(
								wpdadataforms_app,
								'<?php echo esc_attr( $this->page_id ); ?>',
								'<?php echo esc_attr( $this->project_page['page_schema_name'] ); ?>',
								'<?php echo esc_attr( $child_table['table_name'] ); ?>',
								'_form'
							);
							<?php
						}
					}
					?>

					// Add tabs
					jQuery(".wpdadataforms-children").tabs({
						activate: function(event ,ui){
							// Handle tab refresh
							table = jQuery("#" + jQuery(event.currentTarget).data("tableId")).DataTable();
							table.responsive.recalc();
						}
					});

					// Style buttons
					jQuery(".wpdadataforms-button").not("ui-button").button();

					if ( ! wpdaDataFormsIsEmbedded ) {
						// Angular supports only 1 ng-app directive per document
						// We need to manually bootstrap additional ng-apps
						angular.element(document).ready(function() {
							if (wpdaDataFormsAngularBootstrapped === null) {
								wpdaDataFormsAngularBootstrapped = '<?php echo esc_attr( $this->page_id ); ?>';
							} else {
								angular.bootstrap(
									document.getElementById('wpdadataforms_page_<?php echo esc_attr( $this->page_id ); ?>'),
									['wpdadataforms_page_<?php echo esc_attr( $this->page_id ); ?>']
								);
							}
							jQuery(".wpdaforms-tooltip").tooltip();
						});
					} else {
						// Every embedded form needs to be bootstrapped
						angular.element(document).ready(function() {
							angular.bootstrap(
								document.getElementById('wpdadataforms_page_<?php echo esc_attr( $this->page_id ); ?>'),
								['wpdadataforms_page_<?php echo esc_attr( $this->page_id ); ?>']
							);
						});
					}

					// Debug page info
					// console.log(wpdaDataFormsAjaxUrl);
					// console.log(wpdaDataFormsLanguage);
					// console.log(wpdaDataFormsIsEmbedded);
					// console.log(wpdaDataFormsProjectInfo);
					// console.log(wpdaDataFormsProjectPages);
					// console.log(wpdaDataFormsProjectChildTables);
					// console.log(wpdaDataFormsProjectMedia);
					// console.log(wpdaDataFormsProjectLookupTables);
					// console.log(wpdaDataFormsTableOptions);
					// console.log(wpdadataforms_app);
				});
			</script>
			<?php
		}

		protected function table( $schema_name, $table_name, $is_child = false, $is_lov = false ) {
			$schema_name_rdb     = $this->convert_remote_database( $schema_name );
			$wpdadataforms_table = "{$this->page_id}_{$schema_name_rdb}_{$table_name}";
			if ( $is_lov ) {
				$wpdadataforms_table .= '_lov';
			}
			?>
				<div class="wpdadataforms_table ui-widget">
					<table id="wpdadataforms_table_<?php echo esc_attr( $wpdadataforms_table ); ?>" class="ui-widget-content wpda-datatable dataTable display">
						<thead>
							<tr>
								<?php
								$this->table_header( $schema_name, $table_name, $is_child, $is_lov );
								?>
							</tr>
						</thead>
						<tfoot>
							<tr>
								<?php
								$this->table_header( $schema_name, $table_name, $is_child, $is_lov );
								?>
							</tr>
						</tfoot>
					</table>
				</div>
			<?php
			if ( ! $is_lov ) {
				$this->widget( $schema_name, $table_name, $is_child );
			}
		}

		protected function table_header( $schema_name, $table_name, $is_child, $is_lov = false ) {
			$schema_name_rdb    = $this->convert_remote_database( $schema_name );
			$wpdadataforms_name = "{$this->page_id}_{$schema_name_rdb}_{$table_name}";
			$allow_insert       =
				isset( $this->page_properties[ 'mode' ] ) &&
				'edit' === $this->page_properties[ 'mode' ] &&
				isset( $this->page_properties[ 'allow_insert' ] ) &&
				'yes' === $this->page_properties[ 'allow_insert' ];

			foreach ( $this->table_properties[ $schema_name ][ $table_name ][ 'table' ] as $column ) {
				if ( isset( $column['label'] ) ) {
					echo "<th class='{$column['column_name']}'>{$column['label']}</th>";
				}
			}

			// Add hyperlinks
			if ( isset( $this->table_settings[ $table_name ][ $is_child ]->hyperlinks ) ) {
				foreach ( $this->table_settings[ $table_name ][ $is_child ]->hyperlinks as $hyperlink ) {
					if ( self::is_hyperlink_enabled( $hyperlink, $this->tableinfo[ $table_name ], $is_child ) ) {
						$hyperlink_label = isset( $hyperlink->hyperlink_label ) ? $hyperlink->hyperlink_label : '';
						echo "<th>{$hyperlink_label}</th>";
					}
				}
			}

			if ( sizeof( $this->table_properties[ $schema_name ][ $table_name ]['primary_key'] ) > 0 ) {
				echo '<th class="all wpdaforms-action-icons sorting">'; // sorting class removes background for some themes
				if ( ! $is_lov ) {
					if ( $allow_insert ) {
					?>
						<button type="button"
								onclick="wpdaDataFormsAngular['wpdadataforms_controller_<?php echo esc_attr( $wpdadataforms_name ); ?>_form'].addData(<?php echo esc_attr( $is_child ); ?>)"
								class="wpdadataforms-icon dashicons dashicons-database-add wpdaforms-tooltip"
								title="Insert"
						>
						</button>
					<?php
					}
				} else {
					echo '<input type="checkbox" class="wpdaDataFormsLovCheck" onclick="wpdaDataFormsLovSelect(this)" />';
				}
				echo '</th>';
			}
		}

		protected function widget( $schema_name, $table_name, $is_child ) {
			$schema_name_rdb    = $this->convert_remote_database( $schema_name );
			$wpdadataforms_name = "{$this->page_id}_{$schema_name_rdb}_{$table_name}";
			if ( 'table' === $this->project_page['page_type'] || $is_child ) {
				$wpdadataforms_name .= '_form';
			}
			?>
			<div id="wpdadataforms_modal_<?php echo esc_attr( $wpdadataforms_name ); ?>"
				 class="wpdadataforms_modal"
				 style="display: none"
			>
				<?php
				if ( 'parent/child' === $this->project_page['page_type'] && ! $is_child ) {
					$this->view( $schema_name, $table_name );
					$this->children();
					$this->widget( $schema_name, $table_name, true );
				} else {
					$this->form( $schema_name, $table_name );
				}
				?>
			</div>
			<?php
		}

		protected function view( $schema_name, $table_name ) {
			$schema_name_rdb    = $this->convert_remote_database( $schema_name );
			$wpdadataforms_name = "{$this->page_id}_{$schema_name_rdb}_{$table_name}";
			$viewform_class     = $this->get_no_columns_shown( sizeOf( $this->table_properties[ $schema_name ][ $table_name ][ 'form' ] ) );
			$button_label       = isset( $this->page_properties[ 'mode' ] ) &&
				'edit' === $this->page_properties[ 'mode' ] ? 'Edit' : 'View';
			?>
				<div id="wpdadataforms_controller_<?php echo esc_attr( $wpdadataforms_name ); ?>_view"
					 ng-controller="wpdadataforms_controller_<?php echo esc_attr( $wpdadataforms_name ); ?>_view"
					 ng-init="init('<?php echo esc_attr( $this->project_page['page_table_name'] ); ?>')"
				>
					<form>
						<div class="wpdadataforms-view ui-widget-content ui-corner-all wpdadataforms-<?php echo esc_attr( $viewform_class ); ?>-columns">
							<div class="wpdadataforms-group" ng-repeat="(key, val) in dataProjectPageTable.form_less">
								<label>{{val['label']}}</label>
								<ng-container ng-switch on="hasLookup(val['column_name'])">
									<ng-container ng-switch-when="false">
										<input type="{{val['ng_data_type']}}"
											   name="{{key}}"
											   ng-model="selectedRow[val['column_name']]"
											   class="wpdadataforms-control"
											   readonly
										/>
									</ng-container>
									<ng-container ng-switch-default>
										<input ng-value="getLookupValue(key, val['column_name'])"
											   type="text"
											   class="wpdadataforms-control"
											   readonly
										/>
									</ng-container>
								</ng-container>
							</div>
						</div>
						<div class="wpdadataforms-modal-footer">
							<button type="button" class="wpdadataforms-button" onclick="wpdaDataFormsAngular['wpdadataforms_controller_<?php echo esc_attr( $wpdadataforms_name ); ?>_form'].editDataFromView(wpdaDataFormsAngular['wpdadataforms_controller_<?php echo esc_attr( $wpdadataforms_name ); ?>_view'])"><?php echo esc_attr( $button_label ); ?></button>
							<button type="button" class="wpdadataforms-button" ng-click="closeModal()">Close</button>
						</div>
					</form>
				</div>
			<?php
		}

		protected function form( $schema_name, $table_name ) {
			$schema_name_rdb    = $this->convert_remote_database( $schema_name );
			$wpdadataforms_base = "{$this->page_id}_{$schema_name_rdb}_{$table_name}";
			$wpdadataforms_name = "{$wpdadataforms_base}_form";
			$wpdadataforms_id   = "wpdadataforms_controller_{$wpdadataforms_name}";
			$wpdadataforms_lov  = "wpdadataforms_table_{$this->page_id}_{$schema_name}_{$table_name}_lov";
			$is_relation_nm     = isset( $this->child_tables[ $table_name ]['relation_nm'] );
			?>
			<div id="<?php echo esc_attr( $wpdadataforms_id ); ?>"
				 ng-controller="<?php echo esc_attr( $wpdadataforms_id ); ?>"
				 ng-init="init('<?php echo esc_attr( $this->project_page['page_table_name'] ); ?>')"
			>
				<?php if ( $is_relation_nm ) { ?>
					<div id="wpdadataforms-<?php echo esc_attr( $wpdadataforms_base ); ?>-tab" class="wpdadataforms-children wpdadataforms-lov ui-state-active" ng-show="action==='insert'">
						<ul ng-show="action==='insert'">
							<li>
								<a href="#wpdadataforms-<?php echo esc_attr( $wpdadataforms_base ); ?>-tab-0">
									<?php echo __( 'Add New', 'wp-data-access' ); ?>
								</a>
							</li>
							<li>
								<a href="#wpdadataforms-<?php echo esc_attr( $wpdadataforms_base ); ?>-tab-1" ng-click="addExistingRows('<?php echo esc_attr( $wpdadataforms_lov ); ?>')">
									<?php echo __( 'Add Existing', 'wp-data-access' ); ?>
								</a>
							</li>
						</ul>
						<div id="wpdadataforms-<?php echo esc_attr( $wpdadataforms_base ); ?>-tab-0">
							<?php
							$this->htmlform( $schema_name, $table_name, true );
							?>
						</div>
						<div id="wpdadataforms-<?php echo esc_attr( $wpdadataforms_base ); ?>-tab-1" style="display: none">
							<?php
							$this->table( $schema_name, $table_name, true, true );
							?>
							<span class="wpdadataforms-lov-footer">
								<button type="button" class="wpdadataforms-button" ng-click="addSelected()">Add Selected</button>
								<button type="button" class="wpdadataforms-button" ng-click="closeModal()">Close</button>
							</span>
						</div>
					</div>
					<div ng-show="action!=='insert'">
						<?php
						$this->htmlform( $schema_name, $table_name );
						?>
					</div>
				<?php } else {
					$this->htmlform( $schema_name, $table_name );
				}
				?>
			</div>
			<?php
		}

		protected function htmlform( $schema_name, $table_name, $in_tabs = false ) {
			$div_class      = $in_tabs ? '' : 'wpdadataforms-modal-footer';
			$span_class     = $in_tabs ? 'wpdadataforms-lov-footer' : 'right';
			$form_id        = "wpdaforms_{$this->page_id}_{$this->form_id}";
			$editform_class = $this->get_no_columns_shown( sizeOf( $this->table_properties[ $schema_name ][ $table_name ][ 'form' ] ) );
			$this->form_id++;
			?>
			<form method="post" ng-submit="submitForm()" id="<?php echo esc_attr( $form_id ); ?>" name="<?php echo esc_attr( $form_id ); ?>">
				<div class="wpdadataforms-error" ng-show="error.show">
					<a href="#" ng-click="error.show=false" class="wpdadataforms-close">&times;</a>
					{{error.message}}
				</div>
				<div class="wpdadataforms-success" ng-show="success.show" >
					<a href="#" ng-click="success.show=false" class="wpdadataforms-close">&times;</a>
					{{success.message}}
				</div>
				<div class="wpdadataforms-column-selection">
					<span class="wpdadataforms-column-selection-label">Columns per row</span>
					<button type="button"
							class="wpdadataforms-button wpdadataforms-button-icon <?php echo $editform_class=='1' ? 'ui-state-active' : ''; ?>"
							onclick="wpdadataformsNumberOfColumns('<?php echo esc_attr( $form_id ); ?>', '1', jQuery(this))"
					>1</button>
					<button type="button"
							class="wpdadataforms-button wpdadataforms-button-icon <?php echo $editform_class=='2' ? 'ui-state-active' : ''; ?>"
							onclick="wpdadataformsNumberOfColumns('<?php echo esc_attr( $form_id ); ?>', '2', jQuery(this))"
					>2</button>
					<span class="wpdadataforms-column-selection-smart">
						<button type="button"
								class="wpdadataforms-button wpdadataforms-button-icon <?php echo $editform_class=='3' ? 'ui-state-active' : ''; ?>"
								onclick="wpdadataformsNumberOfColumns('<?php echo esc_attr( $form_id ); ?>', '3', jQuery(this))"
						>3</button>
						<button type="button"
								class="wpdadataforms-button wpdadataforms-button-icon <?php echo $editform_class=='4' ? 'ui-state-active' : ''; ?>"
								onclick="wpdadataformsNumberOfColumns('<?php echo esc_attr( $form_id ); ?>', '4', jQuery(this))"
						>4</button>
					</span>
				</div>
				<div class="wpdadataforms-edit ui-widget-content ui-corner-all wpdadataforms-<?php echo esc_attr( $editform_class ); ?>-columns">
					<div class="wpdadataforms-group" ng-repeat="(key, val) in formItems()">
						<ng-container ng-switch on="val['column_type']">
							<ng-container ng-switch-when="tinyint(1)">
								<label>
									&nbsp;
								</label>
								<br/>
							</ng-container>
							<ng-container ng-switch-default>
								<label>
									{{val['label']}}
								</label>
							</ng-container>
						</ng-container>
						<ng-container ng-if="columnType(val)==='media'">
							<span class="dashicons dashicons-warning wpdadataforms-icon-alert wpdaforms-tooltip"
								  title="Editing media items is only allowed in the back-end"
							></span>
						</ng-container>
						<ng-container ng-switch on="val['data_type']">
							<ng-container ng-switch-when="enum">
								<!-- Use enum to generate listbox -->
								<select ng-model="selectedRow[val['column_name']]"
										ng-options="item for item in getEnumValues(val['column_type'])"
										ng-disabled="isReadOnly(val['column_name'], action, val['extra'])"
										class="wpdadataforms-control"
								></select>
							</ng-container>
							<ng-container ng-switch-when="set">
								<!-- Use set to generate multiple selection listbox -->
								<select multiple
										ng-model="selectedRow[val['column_name']]"
										ng-disabled="isReadOnly(val['column_name'], action, val['extra'])"
										class="wpdadataforms-control"
								>
									<option ng-selected="item"
											ng-repeat="item in getSetValues(val['column_type'])"
											value="{{item}}"
									>
										{{item}}
									</option>
								</select>
							</ng-container>
							<ng-container ng-switch-default>
								<ng-container ng-switch on="hasLookup(val['column_name'])">
									<ng-container ng-switch-when="lookup">
										<!-- Use lookup to generate listbox -->
										<select ng-model="selectedRow[val['column_name']]"
												ng-options="item.lookup_value as item.lookup_label for item in lookupData[val['column_name']]"
												ng-disabled="isReadOnly(val['column_name'], action, val['extra'])"
												class="wpdadataforms-control"
										></select>
									</ng-container>
									<ng-container ng-switch-when="autocomplete">
										<!-- Use lookup to generate autocomplete field -->
										<input ng-keyup="autocompleteUpdate($event, val['column_name'])"
											   ng-enter="autocompleteEnter(val['column_name'])"
											   ng-model="autocomplete[val['column_name']]"
											   type="text"
											   placeholder="Start typing"
											   class="wpdadataforms-control"
										/>
										<ul ng-hide="autocompleteHide[val['column_name']]"
											id="{{val['column_name']}}_list"
											class="wpdadataforms-list-group  wpdadataforms-control"
										>
											<li ng-repeat="item in autocompleteData[val['column_name']]"
												ng-click="autocompleteSelect(val['column_name'], item)"
												ng-mouseover="autocompleteMouseOver($event)"
												class="wpdadataforms-list-group-item"
											>{{item.value}}</li>
										</ul>
										<input ng-model="selectedRow[val['column_name']]"
											   ng-required="{{val['is_nullable']==='NO'}}"
											   ng-hide="true"
											   type="{{val['ng_data_type']}}"
											   maxlength="{{columnMaxLength(val['column_name'])}}"
										/>
									</ng-container>
									<ng-container ng-switch-when="conditional">
										<!-- Use conditional lookup to generate listbox -->
										<select ng-model="selectedRow[val['column_name']]"
												ng-options="item.lookup_value as item.lookup_label for item in conditionalLookupData[val['column_name']]"
												ng-disabled="isReadOnly(val['column_name'], action, val['extra'])"
												class="wpdadataforms-control"
										></select>
									</ng-container>
									<ng-container ng-switch-default>
										<ng-container ng-switch on="columnType(val)">
											<ng-container ng-switch-when="checkbox">
												<!-- Add checkbox -->
												<label class="wpda_checkbox_label">
													<input ng-model="selectedRow[val['column_name']]"
														   ng-required="{{val['is_nullable']==='NO'}}"
														   ng-readonly="isReadOnly(val['column_name'], action, val['extra'])"
														   type="checkbox"
														   ng-true-value="1"
														   ng-false-value="0"
														   class="wpdadataforms-control"
													/>
													{{val['label']}}
												</label>
											</ng-container>
											<ng-container ng-switch-when="media">
												<!-- Media columns are not editable -->
												<input ng-model="selectedRow[val['column_name']]"
													   ng-required="{{val['is_nullable']==='NO'}}"
													   ng-readonly="true"
													   type="{{val['ng_data_type']}}"
													   maxlength="{{columnMaxLength(val['column_name'])}}"
													   class="wpdadataforms-control wpdadataforms-media-item"
												/>
											</ng-container>
											<ng-container ng-switch-when="hyperlink">
												<!-- Edit hyperlink -->
												<table class="wpdadataforms-hyperlink">
													<tr>
														<td>
															<input type="text"
																   readonly="readonly"
																   class="wpdadataforms-control wpdadataforms-hyperlink-text"
																   id="{{getHyperlinkId(val['column_name'])}}_display"
																   value="{{getHyperlinkLabel(val['column_name'])}}"
																   ng-click="openHyperlinkPopup(val['column_name'])"
															/>
															<input ng-model="selectedRow[val['column_name']]"
																   id="{{getHyperlinkId(val['column_name'])}}"
																   type="hidden"
															/>
														</td>
														<td>
															<span class="dashicons dashicons-admin-links wpdaforms-tooltip wpdadataforms-hyperlink-link"
																  title="Edit hyperlink"
																  ng-click="openHyperlinkPopup(val['column_name'])"
															></span>
														</td>
													</tr>
												</table>
												<div id="{{getHyperlinkId(val['column_name'])}}_popup"
													 style="display:none"
													 class="wpdadataforms-hyperlink-form"
												>
													<div class="wpdadataforms-hyperlink-div ui-widget-content ui-corner-all">
														<table class="wpdadataforms-hyperlink-table">
															<tr>
																<td>
																	<label for="{{getHyperlinkId(val['column_name'])}}_label">Link Text&nbsp;</label>
																</td>
																<td>
																	<input type="text"
																		   id="{{getHyperlinkId(val['column_name'])}}_label"
																		   class="wpdadataforms-control"
																	/>
																</td>
															</tr>
															<tr>
																<td>
																	<label for="{{getHyperlinkId(val['column_name'])}}_url">URL</label>
																</td>
																<td>
																	<input type="text"
																		   id="{{getHyperlinkId(val['column_name'])}}_url"
																		   class="wpdadataforms-control"
																	/>
																</td>
															</tr>
															<tr>
																<td></td>
																<td>
																	<label>
																		<input type="checkbox"
																			   id="{{getHyperlinkId(val['column_name'])}}_target"
																		/>
																		Open link in a new tab
																	</label>
																</td>
															</tr>
														</table>
													</div>
													<div class="wpdadataforms-hyperlink-buttons">
														<button type="button"
																class="wpdadataforms-button"
																ng-click="saveEditHyperlink($event)"
																data-hyperlink-id="{{getHyperlinkId(val['column_name'])}}"
																data-column-name="{{val['column_name']}}"
														>
															OK
														</button>
														<button type="button"
																class="wpdadataforms-button"
																ng-click="closeEditHyperlink($event)"
														>
															Cancel
														</button>
													</div>
												</div>
											</ng-container>
											<ng-container ng-switch-when="textarea">
												<!-- Textarea -->
												<textarea ng-model="selectedRow[val['column_name']]"
														  ng-required="{{val['is_nullable']==='NO'}}"
														  ng-readonly="isReadOnly(val['column_name'], action, val['extra'])"
														  type="{{val['ng_data_type']}}"
														  maxlength="{{columnMaxLength(val['column_name'])}}"
														  class="wpdadataforms-control"
												></textarea>
											</ng-container>
											<ng-container ng-switch-default>
												<ng-container ng-switch on="val['ng_data_type']">
													<ng-container ng-switch-when="number">
														<!-- Number input box -->
														<input ng-model="selectedRow[val['column_name']]"
															   ng-required="{{val['is_nullable']==='NO'}}"
															   ng-readonly="isReadOnly(val['column_name'], action, val['extra'])"
															   type="{{val['ng_data_type']}}"
															   min="{{columnMinValue(val['column_name'])}}"
															   max="{{columnMaxValue(val['column_name'])}}"
															   step="{{getStep(val['column_name'])}}"
															   class="wpdadataforms-control"
														/>
													</ng-container>
													<ng-container ng-switch-default>
														<!-- Standard text input box -->
														<input ng-model="selectedRow[val['column_name']]"
															   ng-required="{{val['is_nullable']==='NO'}}"
															   ng-readonly="isReadOnly(val['column_name'], action, val['extra'])"
															   type="{{val['ng_data_type']}}"
															   maxlength="{{columnMaxLength(val['column_name'])}}"
															   class="wpdadataforms-control"
														/>
													</ng-container>
												</ng-container>
											</ng-container>
										</ng-container>
									</ng-container>
								</ng-container>
							</ng-container>
						</ng-container>
					</div>
				</div>
				<div class="<?php echo $div_class; ?>">
					<span ng-repeat="hiddenField in dataProjectPageTable.form_hidden">
						<input type="hidden" name="{{hiddenField}}" value="{{selectedRow[hiddenField]}}" />
					</span>
					<span class="left" ng-show="action==='update'">
						<button type="button" class="wpdadataforms-button" ng-click="prevRow()">Previous</button>
						<button type="button" class="wpdadataforms-button" ng-click="nextRow()">Next</button>
					</span>
					<span class="<?php echo $span_class; ?>">
						<?php if ( isset( $this->page_properties[ 'mode' ] ) && 'edit' === $this->page_properties[ 'mode' ] ) { ?>
							<button type="submit" class="wpdadataforms-button">{{submit_button}}</button>
						<?php } ?>
						<button type="button" class="wpdadataforms-button" ng-click="closeModal()">Close</button>
					</span>
				</div>
			</form>
			<?php
		}

		protected function children() {
			// Add tabs
			$schema_name_rdb     = $this->convert_remote_database( $this->project_page['page_schema_name'] );
			$tab                 = 1;
			?>
			<div class="wpdadataforms-children ui-state-active">
					<ul>
					<?php
					foreach ( $this->child_tables as $child_table ) {
						if ( isset( $child_table['relation_1n'] ) || isset( $child_table['relation_nm'] ) ) {
							$wpdadataforms_table = "{$this->page_id}_{$schema_name_rdb}_{$child_table['table_name']}";
							?>
								<li>
									<a href="#wpdadataforms-children-<?php echo $child_table['table_name']; ?>-tab-<?php echo $tab; ?>" data-table-id="wpdadataforms_table_<?php echo esc_attr( $wpdadataforms_table ); ?>">
										<?php echo $child_table['tab_label']; ?>
									</a>
								</li>
							<?php
							$tab++;
						}
					}
					?>
					</ul>
			<?php

			// Add tab content
			$tab = 1;
			foreach ( $this->child_tables as $child_table ) {
				if ( isset( $child_table['relation_1n'] ) || isset( $child_table['relation_nm'] ) ) {
					?>
					<div id="wpdadataforms-children-<?php echo $child_table['table_name']; ?>-tab-<?php echo $tab; ?>">
						<?php
						$this->table( $this->project_page['page_schema_name'], $child_table['table_name'], true );
						?>
					</div>
					<?php
					$tab++;
				}
			}
			?>
			</div>
			<?php
		}

		protected function get_ng_data_type( $arg ) {
			switch ( $arg ) {
				case 'tinyint':
				case 'smallint':
				case 'mediumint':
				case 'int':
				case 'bigint':
				case 'float':
				case 'double':
				case 'decimal':
				case 'year':
					return 'number';
				case 'datetime':
				case 'timestamp':
					return 'datetime-local';
				case 'date':
					return 'date';
				case 'time':
					return 'time';
				case 'enum':
				case 'set':
				default:
					return 'text';
			}
		}

		protected function get_no_columns_shown( $noColumns ) {
			if ( $noColumns > 23 ) {
				return '4';
			} elseif ( $noColumns > 8 ) {
				return '3';
			} elseif ( $noColumns < 5 ) {
				return '1';
			} else {
				return '2';
			}
		}

		protected function convert_remote_database( $schema_name ) {
			if ( 'rdb:' === substr( $schema_name, 0, 4 ) ) {
				return substr( $schema_name, 4 );
			} else {
				return $schema_name;
			}
		}

	}

}
