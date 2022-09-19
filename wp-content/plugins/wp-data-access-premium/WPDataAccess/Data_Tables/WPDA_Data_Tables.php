<?php

/**
 * Suppress "error - 0 - No summary was found for this file" on phpdoc generation
 *
 * @package WPDataAccess\Data_Tables
 */

namespace WPDataAccess\Data_Tables {

	use stdClass;
	use WPDataAccess\Connection\WPDADB;
	use WPDataAccess\Data_Dictionary\WPDA_Dictionary_Exist;
	use WPDataAccess\Data_Dictionary\WPDA_List_Columns_Cache;
	use WPDataAccess\Macro\WPDA_Macro;
	use WPDataAccess\Plugin_Table_Models\WPDA_Publisher_Model;
	use WPDataAccess\Plugin_Table_Models\WPDA_Media_Model;
	use WPDataAccess\Plugin_Table_Models\WPDA_Table_Settings_Model;
	use WPDataAccess\List_Table\WPDA_List_Table;
	use WPDataAccess\Premium\WPDAPRO_Geo_Location\WPDAPRO_Geo_Location_WS;
	use WPDataAccess\Templates\WPDAPRO_Template_Data_Publisher_Color;
	use WPDataAccess\Templates\WPDAPRO_Template_Data_Publisher_Space;
	use WPDataAccess\Templates\WPDA_Template_Data_Publisher_Corner;
	use WPDataAccess\WPDA;

	/**
	 * Class WPDA_Data_Tables
	 *
	 * @author  Peter Schulz
	 * @since   1.0.0
	 */
	class WPDA_Data_Tables {

		protected $wpda_list_columns      = null;
		protected $wpda_dictionary_checks = null;
		protected $json                   = null;
		protected $table_settings         = null;
		protected $hyperlink_positions    = array();
		protected $columns                = array();
		protected $column_labels          = null;
		protected $primary_index_sorted   = array();
		protected $buttons                = '[]';
		protected $geomap                 = '';
		protected $geo_search             = '';
		protected $geo_search_type        = null;
		protected $read_more_html         = '';

		public static function enqueue_styles_and_script( $styling = 'default' ) {
			wp_enqueue_script( 'jquery-ui-draggable' );
			wp_enqueue_script( 'jquery-ui-resizable' );

			// Plugin css
			wp_enqueue_style( 'wpda_datatables_default' );
			wp_enqueue_style( 'dashicons' ); // Needed to display icons for media attachments

			// Plugin js
			wp_enqueue_script( 'purl' );
			wp_enqueue_script( 'wpda_datatables' );

			// Add jQuery DataTables library scripts
			if (
					(
						is_admin() &&
						WPDA::get_option( WPDA::OPTION_BE_LOAD_DATATABLES ) === 'on'
					) ||
					(
						! is_admin() &&
						WPDA::get_option( WPDA::OPTION_FE_LOAD_DATATABLES ) === 'on'
					)
			) {
				wp_enqueue_script( 'jquery_datatables' );
			}
			if (
					(
						is_admin() &&
						WPDA::get_option( WPDA::OPTION_BE_LOAD_DATATABLES_RESPONSE ) === 'on'
					) ||
					(
						! is_admin() &&
						WPDA::get_option( WPDA::OPTION_FE_LOAD_DATATABLES_RESPONSE ) === 'on'
					)
			) {
				wp_enqueue_script( 'jquery_datatables_responsive' );
			}

			$style_added = false;
			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				switch ( $styling ) {
					case 'jqueryui':
						wp_enqueue_style( 'wpda_jqueryui_theme_structure' );
						wp_enqueue_style( 'wpda_jqueryui_theme' );
						wp_enqueue_style( 'jquery_datatables_jqueryui' );
						wp_enqueue_style( 'jquery_datatables_responsive_jqueryui' );
						wp_enqueue_script( 'jquery_datatables_jqueryui' );
						// wp_enqueue_script( 'jquery_datatables_responsive_jqueryui' );

						$style_added = true;
						break;
					case 'semantic':
						wp_enqueue_style( 'jquery_datatables_semanticui' );
						wp_enqueue_style( 'jquery_datatables_responsive_semanticui' );
						wp_enqueue_script( 'jquery_datatables_semanticui' );
						// wp_enqueue_script( 'jquery_datatables_responsive_semanticui' );

						$style_added = true;
						break;
					case 'foundation':
						wp_enqueue_style( 'jquery_datatables_foundation' );
						wp_enqueue_style( 'jquery_datatables_responsive_foundation' );
						wp_enqueue_script( 'jquery_datatables_foundation' );
						// wp_enqueue_script( 'jquery_datatables_responsive_foundation' );

						$style_added = true;
						break;
					case 'bootstrap':
						wp_enqueue_style( 'jquery_datatables_bootstrap3' );
						wp_enqueue_style( 'jquery_datatables_responsive_bootstrap3' );
						wp_enqueue_script( 'jquery_datatables_bootstrap3' );
						// wp_enqueue_script( 'jquery_datatables_responsive_bootstrap3' );

						$style_added = true;
						break;
					case 'bootstrap4':
						wp_enqueue_style( 'jquery_datatables_bootstrap4' );
						wp_enqueue_style( 'jquery_datatables_responsive_bootstrap4' );
						wp_enqueue_script( 'jquery_datatables_bootstrap4' );
						// wp_enqueue_script( 'jquery_datatables_responsive_bootstrap4' );

						$style_added = true;
						break;
					case 'bootstrap5':
						wp_enqueue_style( 'jquery_datatables_bootstrap5' );
						wp_enqueue_style( 'jquery_datatables_responsive_bootstrap5' );
						wp_enqueue_script( 'jquery_datatables_bootstrap5' );
						// wp_enqueue_script( 'jquery_datatables_responsive_bootstrap4' );

						$style_added = true;
						break;
					case 'bulma':
						wp_enqueue_style( 'jquery_datatables_bulma' );
						wp_enqueue_style( 'jquery_datatables_responsive_bulma' );
						wp_enqueue_script( 'jquery_datatables_bulma' );
						// wp_enqueue_script( 'jquery_datatables_responsive_bootstrap4' );

						$style_added = true;
						break;
				}
			}

			if ( ! $style_added || 'default' === $styling ) {
				// Add jQuery DataTables library styles
				if (
					(
						is_admin() &&
						WPDA::get_option( WPDA::OPTION_BE_LOAD_DATATABLES ) === 'on'
					) ||
					(
						! is_admin() &&
						WPDA::get_option( WPDA::OPTION_FE_LOAD_DATATABLES ) === 'on'
					)
				) {
					wp_enqueue_style( 'jquery_datatables' );
				}
				if (
					(
						is_admin() &&
						WPDA::get_option( WPDA::OPTION_BE_LOAD_DATATABLES_RESPONSE ) === 'on'
					) ||
					(
						! is_admin() &&
						WPDA::get_option( WPDA::OPTION_FE_LOAD_DATATABLES_RESPONSE ) === 'on'
					)
				) {
					wp_enqueue_style( 'jquery_datatables_responsive' );
				}
			}

			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				// Add premium library styles
				wp_enqueue_style( 'wpdapro_datatables_select' );
				wp_enqueue_style( 'wpdapro_jquery_datatables_searchbuilder' );
				wp_enqueue_style( 'wpdapro_jquery_datatables_searchpanes' );
				wp_enqueue_style( 'wpdapro_jquery_datatables_datetime' );
				wp_enqueue_style( 'wpdapro_jquery_datatables_buttons' );
				wp_enqueue_style( 'wpdapro_jquery_datatables_select' );

				// Add premium library scripts
				wp_enqueue_script( 'wpdapro_jquery_datatables_buttons' );
				wp_enqueue_script( 'wpdapro_jquery_datatables_flash' );
				wp_enqueue_script( 'wpdapro_jquery_datatables_jszip' );
				wp_enqueue_script( 'wpdapro_jquery_datatables_pdfmake' );
				wp_enqueue_script( 'wpdapro_jquery_datatables_vfs_fonts' );
				wp_enqueue_script( 'wpdapro_jquery_datatables_html5' );
				wp_enqueue_script( 'wpdapro_jquery_datatables_print' );
				wp_enqueue_script( 'wpdapro_jquery_datatables_colvis' );
				wp_enqueue_script( 'wpdapro_jquery_datatables_select' );
				wp_enqueue_script( 'wpdapro_jquery_datatables_rowgroup' );
				wp_enqueue_script( 'wpdapro_jquery_datatables_searchbuilder' );
				wp_enqueue_script( 'wpdapro_jquery_datatables_searchpanes' );
				wp_enqueue_script( 'wpdapro_jquery_datatables_datetime' );
				wp_enqueue_script( 'wpdapro_jquery_datatables_premium' );
			}
		}

		/**
		 * Generate jQuery DataTable code
		 *
		 * Table and column names provided are checked for existency and access to prevent hacking the DataTable code
		 * and SQL injection.
		 *
		 * @param int    $pub_id Publication ID.
		 * @param string $pub_name Publication name.
		 * @param string $database Database name.
		 * @param string $table_name Database table name.
		 * @param string $column_names Comma seperated list of column names.
		 * @param string $responsive Yes = responsive mode, No = No responsive mode.
		 * @param int    $responsive_cols Number of columns to be displayd in responsive mode.
		 * @param string $responsive_type Modal, Collaped or Expanded (only if $responsive = Yes).
		 * @param string $responsive_icon Yes = show icon, No = do not show icon (only if $responsive = Yes).
		 * @param string $sql_orderby SQL default order by
		 * @param string $filter_field_name Filter field name (CSV)
		 * @param string $filter_field_value Filter field value (CSV)
		 * @param string $nl2br Convert New Line characters to BR tags
		 *
		 * @return string response wpda_datatables_ajax_call
		 *
		 * @since   1.0.0
		 */
		public function show(
			$pub_id, $pub_name, $database, $table_name, $column_names, $responsive, $responsive_cols,
			$responsive_type, $responsive_icon, $sql_orderby, $filter_field_name = '', $filter_field_value = '',
			$nl2br = '', $dashboard_styling = false, $is_embedded = false
		) {
			if ( '' === $pub_id && '' === $pub_name && '' === $table_name ) {
				return '<p>' . __( 'ERROR: Missing argument [need at least pub_id, pub_name or table argument]', 'wp-data-access' ) . '</p>';
			}

			if ( '' !== $pub_id || '' !== $pub_name ) {
				// Get publication information
				if ( '' !== $pub_id ) {
					$publication = WPDA_Publisher_Model::get_publication( $pub_id );
				} else {
					$publication = WPDA_Publisher_Model::get_publication_by_name( $pub_name );
				}
				if ( false === $publication ) {
					// Querying tables in other schema's is not allowed!
					return '<p>' . __( 'ERROR: Publication not found', 'wp-data-access' ) . '</p>';
				}
				$pub_id                          = $publication[0]['pub_id'];
				$database                        = $publication[0]['pub_schema_name'];
				$data_source                     = $publication[0]['pub_data_source'];
				$table_name                      = $publication[0]['pub_table_name'];
				$column_names                    = $publication[0]['pub_column_names'];
				$pub_query                       = $publication[0]['pub_query'];
				$pub_cpt_query                   = $publication[0]['pub_cpt_query'];
				$pub_cpt_format                  = $publication[0]['pub_cpt_format'];
				$responsive                      = strtolower( $publication[0]['pub_responsive'] );
				$responsive_popup_title          = $publication[0]['pub_responsive_popup_title'];
				$responsive_cols                 = $publication[0]['pub_responsive_cols'];
				$responsive_type                 = strtolower( $publication[0]['pub_responsive_type'] );
				$responsive_icon                 = strtolower( $publication[0]['pub_responsive_icon'] );
				$pub_format                      = $publication[0]['pub_format'];
				$sql_orderby                     = $publication[0]['pub_default_orderby'];
				if ( null === $sql_orderby || 'null' === $sql_orderby ) {
					$sql_orderby = '';
				}
				$pub_table_options_searching     = $publication[0]['pub_table_options_searching'];
				$pub_table_options_ordering      = $publication[0]['pub_table_options_ordering'];
				$pub_table_options_paging        = $publication[0]['pub_table_options_paging'];
				$pub_table_options_serverside    = $publication[0]['pub_table_options_serverside'];
				$pub_table_options_advanced      = $publication[0]['pub_table_options_advanced'];
				$pub_table_options_advanced      = str_replace( array( "\r\n", "\r", "\n", "\t" ), '', $pub_table_options_advanced );
				$pub_responsive_modal_hyperlinks = $publication[0]['pub_responsive_modal_hyperlinks'];
				$pub_sort_icons                  = $publication[0]['pub_sort_icons'];
				$pub_styles                      = $publication[0]['pub_styles'];
				$pub_extentions                  = $publication[0]['pub_extentions'];
			} else {
				$pub_id                          = '0';
				$data_source                     = 'Table';
				$pub_query                       = null;
				$pub_cpt_query                   = null;
				$pub_cpt_format                  = '';
				$responsive_popup_title          = '';
				$pub_format                      = '';
				$pub_table_options_searching     = 'on';
				$pub_table_options_ordering      = 'on';
				$pub_table_options_paging        = 'on';
				$pub_table_options_serverside    = 'on';
				$pub_table_options_advanced      = '';
				$pub_responsive_modal_hyperlinks = '';
				$pub_sort_icons                  = 'default';
				$pub_styles                      = 'default';
				$pub_extentions                  = '';
			}

			// Activate scripts and styles
			$styling = $this->set_style( $dashboard_styling, ( isset( $publication ) ? $publication : null ) );
			self::enqueue_styles_and_script( $styling );

			// Create JSON object from advanced settings
			try {
				$this->json = json_decode( $pub_table_options_advanced );
				if ( null === $this->json ) {
					$this->json = new stdClass();
				}
			} catch ( \Exception $e ) {
				$this->json = new stdClass();
			}

			// Add extension support
			$this->extension_wizard( $pub_extentions );

			// Check for extra header column
			$header2 = $this->add_extra_header();

			// Check button usage
			$use_buttons_extension = $this->use_buttons_extension();

			// Determine sort icon style
			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				if ( 'jqueryui' === $styling ) {
					$pub_sort_icons = 'none';
				}
			}
			switch ( $pub_sort_icons ) {
				case 'none':
					// Hide sort icons
					wp_enqueue_style( 'wpda_datatables_hide_sort_icons' );
					break;
				default:
					// Show sort icons
			}

			if ( 'on' !== $pub_table_options_searching || null === $pub_table_options_searching ) {
				$pub_table_options_searching = 'false';
			} else {
				$pub_table_options_searching = 'true';
			}

			if ( 'on' !== $pub_table_options_ordering || null === $pub_table_options_ordering ) {
				$pub_table_options_ordering = 'false';
			} else {
				$pub_table_options_ordering = 'true';
			}

			if ( 'on' !== $pub_table_options_paging || null === $pub_table_options_paging ) {
				$pub_table_options_paging = 'false';
			} else {
				$pub_table_options_paging = 'true';
			}

			if ( ! isset( $this->json->serverSide ) ) {
				if ( 'on' !== $pub_table_options_serverside || null === $pub_table_options_serverside ) {
					$this->json->serverSide = false;
				}
			}

			if (
				'' === $responsive_popup_title ||
				null === $responsive_popup_title ||
				'Row details' === $responsive_popup_title // Translate Data Publisher default
			) {
				$responsive_popup_title = __( 'Row details', 'wp-data-access' ); // Set title of modal window here to support i18n.
			}

			// WordPress database is default
			if ( '' === $database ) {
				global $wpdb;
				$database = $wpdb->dbname;
			}

			if ( 'Query' === $data_source || 'CPT' === $data_source ) {
				// Custom query: implemented in the premium version only.
				if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
					$query = 'Query' === $data_source ? $pub_query : $pub_cpt_query;
					$query = WPDA::substitute_environment_vars( $query ); // Allow environment vars in custom query.

					$response = WPDA_Publisher_Model::get_temporary_table_from_custom_query( $database, $query );
					if ( isset( $response['msg'] ) && '' !== $response['msg'] ) {
						return '<p>' . esc_html( $response['msg'] ) . '</p>';
					}

					if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
						$rows = $response['data'];
					} else {
						return '<p>' . __( 'ERROR: Invalid query?', 'wp-data-access' ) . '</p>';
					}

					// Prepare all necessary variables.
					$this->columns       = [];
					$this->column_labels = [];
					$publication_columns = [];
					$index               = 0;
					$pub_cpt_labels      = [];
					if ( 'CPT' === $data_source ) {
						try {
							$pub_cpt_format_json = json_decode( $pub_cpt_format, true );
							if ( isset( $pub_cpt_format_json['cpt_format']['cpt_labels'] ) ) {
								$pub_cpt_labels = $pub_cpt_format_json['cpt_format']['cpt_labels'];
							}
						}catch ( \Exception $e ) {
							$pub_cpt_labels = [];
						}
					}

					foreach ( $rows as $col ) {
						$column_name = "pub{$pub_id}_col{$index}";
						$data_type   = WPDA::get_type( $col['Type'] );

						$publication_columns[]                = [
							'className'         => "{$column_name} wpda_format_{$data_type}",
							'name'              => $column_name,
							'targets'           => $index,
							'label'             => $col['Field'],
							'searchBuilderType' => WPDA::get_sb_type( $col['Type'] ),
						];
						$this->columns[]                     = $col['Field'];
						$this->column_labels[ $column_name ] = $col['Field'];
						if ( isset( $pub_cpt_labels[ $col['Field'] ] ) ) {
							$this->column_labels[ $col['Field'] ] = $pub_cpt_labels[ $col['Field'] ];
						}

						$index++;
					}

					// Define additional variables.
					$hyperlinks                       = array(); // Not used for custom queries.
					$geolocation                      = null;  // Not used for custom queries.
					if ( count( $publication_columns ) > 0 ) {
						$wpda_database_columns = substr( substr( json_encode( $publication_columns ), 1 ), 0, -1 ); // Remove square brackets.
					} else {
						$wpda_database_columns = '';
					}
					$calc_estimate = false; // Not used for custom queries.
				}
			} else {
				// Check if table exists to prevent SQL injection
				$this->wpda_dictionary_checks = new WPDA_Dictionary_Exist( $database, $table_name );
				if ( ! $this->wpda_dictionary_checks->table_exists( '0' === $pub_id, false ) ) {
					// Table not found.
					return '<p>' . __( 'ERROR: Invalid table name or not authorized', 'wp-data-access' ) . '</p>';
				}

				// Load table settings
				$table_settings_db = WPDA_Table_Settings_Model::query( $table_name, $database );
				if ( isset( $table_settings_db[0]['wpda_table_settings'] ) ) {
					$this->table_settings = json_decode( $table_settings_db[0]['wpda_table_settings'] );
				}

				// Get table settings > hyperlinks
				$hyperlinks = array();
				if ( isset( $this->table_settings->hyperlinks ) ) {
					foreach ( $this->table_settings->hyperlinks as $hyperlink ) {
						$hyperlink_label = isset( $hyperlink->hyperlink_label ) ? $hyperlink->hyperlink_label : '';
						$hyperlink_html  = isset( $hyperlink->hyperlink_html ) ? $hyperlink->hyperlink_html : '';
						if ( $hyperlink_label !== '' && $hyperlink_html !== '' ) {
							array_push( $hyperlinks, $hyperlink_label );
						}
					}
				}

				// Check for geolocation support
				$geolocation = $this->get_geolocation_settings();

				$row_count_estimate = WPDA::get_row_count_estimate( $database, $table_name, $this->table_settings );
				$calc_estimate      = $row_count_estimate['is_estimate'];

				// Get table columns
				$this->wpda_list_columns = WPDA_List_Columns_Cache::get_list_columns( $database, $table_name );

				// Set columns to be queried
				$this->columns = $this->get_columns( $column_names );

				// Get column labels
				$this->column_labels = $this->get_labels( $pub_format );

				// Define publication columns
				$wpda_database_columns = $this->define_columns( $use_buttons_extension, $hyperlinks, $geolocation );
			}

			// Run filters to allow plugin users to add custom features
			if ( has_filter( 'wpda_wpdataaccess_prepare' ) ) {
				$wpda_wpdataaccess_prepare_filter = apply_filters(
					'wpda_wpdataaccess_prepare',
					'',
					$database,
					$table_name,
					$pub_id,
					$this->columns,
					$this->table_settings
				);
			} else {
				$wpda_wpdataaccess_prepare_filter = '';
			}

			// Get jQuery DataTables language
			$language = $this->get_language();

			// Create dynamic columns variable name (must be unique per publication to support multiple publication on one page)
			$columnsvar = 'wpdaDbColumns' . preg_replace( '/[^a-zA-Z0-9]/', '', $table_name ) . $pub_id;

			// Add button extension
			$this->add_buttons( $use_buttons_extension, $pub_id, $table_name );

			// Add geolocation support
			$this->add_geolocation( $geolocation, $pub_id, $table_name, $database );

			// Add read more button
			$read_more = $this->add_read_more( $pub_id, $table_name, $pub_table_options_paging );

			// Update extra header of necessary
			$header2 = $this->update_extra_header( $header2, $pub_table_options_searching );

			// Apply global styling
			$dataTablesClass = $this->add_global_style( $styling, $pub_styles );

			// Themes like DIVI use IDs to overwrite all defaults of others. This style reassures correct positioning
			// of the responsive icon. This cannot be added to the plugin CSS files. It needs an ID to overwrite other
			// ID styling.
			$styling_default = "
				<style>
					#" . esc_attr( $table_name ) . "{$pub_id}.dataTable.wpda-datatable.dtr-inline.collapsed>tbody>tr>td.dtr-control,
					#" . esc_attr( $table_name ) . "{$pub_id}.dataTable.wpda-datatable.dtr-inline.collapsed>tbody>tr>th.dtr-control {
    					padding-left: 30px;
					}
				</style>";
			// Add premium styling
			$styling_template = $this->add_styling_template( $pub_id, $table_name, $dashboard_styling, ( isset( $publication ) ? $publication : null ) );

			// Prepare values needed for ajax request
			$database_value     = $database;
			$column_names_value = $column_names;
			if ( '0' != $pub_id ) {
				$database_value     = '';
				$column_names_value = '';
			}

			// Convert JSON to string
			$json_value = $this->prepare_json();

			// Generate nonce
			$wpnonce = $this->generate_nonce( $table_name, $column_names_value, $is_embedded );
			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				if ( 'Query' === $data_source || 'CPT' === $data_source ) {
					$wpnonce = $this->generate_nonce( "custom{$pub_id}", $column_names_value, $is_embedded );
				}
			}

			return $wpda_wpdataaccess_prepare_filter .
				$styling_default .
				$styling_template .
				"<div class='wpda_publication_container'><table id=\"" . esc_attr( $table_name ) . "$pub_id\" class=\"{$dataTablesClass}\" cellspacing=\"0\">" .
				'<thead>' . $this->show_header( $responsive, $responsive_cols, $hyperlinks, $header2, $geolocation ) . '</thead>' .
				'<tfoot>' . $this->show_header( $responsive, $responsive_cols, $hyperlinks, '', $geolocation ) . '</tfoot>' .
				'</table></div>' .
				$this->read_more_html .
				"<script type='text/javascript'>" .
				"var {$columnsvar}_advanced_options = " . $json_value . '; ' .
				"var $columnsvar = [" . $wpda_database_columns . '];' .
				"var {$columnsvar}_geosearch_options = " . json_encode( $this->geo_search_type ) . '; ' .
				'jQuery(function () {' .
				'	wpda_datatables_ajax_call(' .
				"		{$columnsvar}," .
				'		"' . esc_attr( $database_value ) . '",' .
				'		"' . esc_attr( $table_name ) . '",' .
				'		"' . esc_attr( $column_names_value ) . '",' .
				'		"' . esc_attr( $responsive ) . '",' .
				'		"' . esc_attr( $responsive_popup_title ) . '",' .
				'		"' . esc_attr( $responsive_type ) . '",' .
				'		"' . esc_attr( $responsive_icon ) . '",' .
				'		"' . esc_attr( $language ) . '",' .
				'		"' . htmlentities( $sql_orderby ) . '",' .
				"		{$pub_table_options_searching}," .
				"	    {$pub_table_options_ordering}," .
				"		{$pub_table_options_paging}," .
				"		{$columnsvar}_advanced_options," .
				"		{$pub_id}," .
				'		"' . esc_attr( $pub_responsive_modal_hyperlinks ) . '",' .
				'		[' . implode( ',', $this->hyperlink_positions ) . '],' .
				'		"' . esc_attr( $filter_field_name ) . '",' .
				'		"' . esc_attr( $filter_field_value ) . '",' .
				'		"' . esc_attr( $nl2br ) . '",' .
				"		{$this->buttons}," .
				"		\"$read_more\"," .
				'		"' . ( $calc_estimate ? 'true' : 'false' ) . '",' .
				'		"' . trim( preg_replace( '/\s+/', ' ', $this->geo_search ) ) . '",' .
				"		{$columnsvar}_geosearch_options," .
				'		"' . $wpnonce . '"' .
				'	);' .
				'});' .
				'</script>' .
				$this->geomap;
		}

		protected function set_style( $dashboard_styling, $publication ) {
			$styling = 'default';

			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				if ( ! $dashboard_styling ) {
					if (
						! (
							isset( $publication[0]['pub_style_premium'] ) &&
							'Yes' === $publication[0]['pub_style_premium']
						)
					) {
						$styling = WPDA::get_option( WPDA::OPTION_DP_STYLE );
					}
				}
			}

			return $styling;
		}

		protected function extension_wizard( $pub_extentions ) {
			// This feature is implemented in the premium version
			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				if ( ! isset( $this->json->dom ) ) {
					if (
						null !== $pub_extentions &&
						'' !== $pub_extentions &&
						'{}' !== $pub_extentions
					) {
						try {
							$extensions = json_decode( $pub_extentions );
							if ( isset( $extensions->dom ) ) {
								$this->json->dom    = $extensions->dom;
								$this->json->select = array(
									'selector' => 'td:not(.dtr-control)',
									'style'    => 'multi',
								);
								if ( isset( $extensions->selected_buttons ) ) {
									$this->json->wpda_buttons = $extensions->selected_buttons;
								}
								$this->json->wpda_button_caption = isset( $extensions->button_caption ) ? $extensions->button_caption : 'label';
								if ( 'icon' === $this->get_button_caption() || 'both' === $this->get_button_caption() ) {
									// Load font awesome icons
									wp_enqueue_style( 'wpda_fontawesome_icons' );
									wp_enqueue_style( 'wpda_fontawesome_icons_solid' );
								}
							}
							if (
								isset( $extensions->wpda_qb_columns ) &&
								(
									! isset( $this->json->searchBuilder ) ||
									! isset( $this->json->searchBuilder->columns )
								)
							) {
								$this->json->searchBuilder = array(
									'columns'    => $extensions->wpda_qb_columns,
									'depthLimit' => 2,
								);
							}
							if ( isset( $extensions->wpda_sp_columns ) ) {
								// Allow user to define search panes as advance option
								if ( ! isset( $this->json->searchPanes->columns ) ) {
									$this->json->searchPanes['columns'] = $extensions->wpda_sp_columns;
								}
							}
						} catch ( \Exception $e ) {
						}
					}
				}
			}
		}

		protected function add_extra_header() {
			$header2 = '';

			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				if ( isset( $this->json->wpda_searchbox ) ) {
					$header2 = $this->json->wpda_searchbox;
				}
			}

			return $header2;
		}

		protected function use_buttons_extension() {
			$use_buttons_extension = false;

			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				// Check for button extension
				if ( isset( $this->json->dom ) && strpos( $this->json->dom, 'B' ) !== false ) {
					$use_buttons_extension = true;

					// Load ui theme and tooltip library
					wp_enqueue_style( 'wpda_jqueryui_theme_structure' );
					wp_enqueue_style( 'wpda_jqueryui_theme' );
					wp_enqueue_script( 'jquery-ui-tooltip' );
				}
			}

			return $use_buttons_extension;
		}

		protected function get_geolocation_settings() {
			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				// Check for geolocation support
				if (
					isset( $this->table_settings->geolocation_settings->status ) &&
					'enabled' === $this->table_settings->geolocation_settings->status
				) {
					return $this->table_settings->geolocation_settings;
				}
			}
		}

		protected function get_columns( $column_names ) {
			if ( '*' === $column_names ) {
				// Get all column names from table.
				$columns = array();
				foreach ( $this->wpda_list_columns->get_table_columns() as $column ) {
					$columns[] = $column['column_name'];
				}
				return $columns;
			} else {
				$columns = explode( ',', $column_names ); // Create column ARRAY
				// Check if columns exist to prevent sql injection
				$i = 0;
				foreach ( $columns as $column ) {
					if ( 'wpda_hyperlink_' !== substr( $column, 0, 15 ) ) {
						if ( ! $this->wpda_dictionary_checks->column_exists( $column ) ) {
							// Column not found
							return __( 'ERROR: Column', 'wp-data-access' ) . ' ' . esc_attr( $column ) . ' ' . __( 'not found', 'wp-data-access' );
						}
					} else {
						$this->hyperlink_positions[] = $i;
					}
					$i++;
				}
				return $columns;
			}
		}

		protected function get_labels( $pub_format ) {
			try {
				$pub_format_json = json_decode( $pub_format, true );
				if ( isset( $pub_format_json['pub_format']['column_labels'] ) ) {
					return array_merge(
						$this->wpda_list_columns->get_table_column_headers(),
						$pub_format_json['pub_format']['column_labels']
					);
				} else {
					return $this->wpda_list_columns->get_table_column_headers();
				}
			} catch ( \Exception $e ) {
				return $this->wpda_list_columns->get_table_column_headers();
			}
		}

		protected function define_columns( $use_buttons_extension, $hyperlinks, $geolocation ) {
			$wpda_database_columns = '';

			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				if ( $use_buttons_extension ) {
					// Add row selection support
					$primary_keys        = $this->wpda_list_columns->get_table_primary_key();
					$primary_keys_sorted = array();
					foreach ( $primary_keys as $pk ) {
						$primary_keys_sorted[ $pk ] = true;
					}
					$this->primary_index_sorted = array();
				}
			}

			for ( $i = 0; $i < count( $this->columns ); $i ++ ) {
				if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
					if ( $use_buttons_extension ) {
						if ( isset( $primary_keys_sorted[ $this->columns[ $i ] ] ) ) {
							$this->primary_index_sorted[ $i ] = $this->columns[ $i ];
						}
					}
				}

				if ( 'wpda_hyperlink_' !== substr( $this->columns[ $i ], 0, 15 ) ) {
					$column_label = isset( $this->column_labels[ $this->columns[ $i ] ] ) ? $this->column_labels[ $this->columns[ $i ] ] : $this->columns[ $i ];
				} else {
					$column_label = $hyperlinks[ substr( $this->columns[ $i ], strrpos( $this->columns[ $i ], '_' ) + 1 ) ];
				}

				$data_type       = WPDA::get_type( $this->wpda_list_columns->get_column_data_type( $this->columns[ $i ] ) );
				$data_type_class = "wpda_format_{$data_type}";

				$wpda_database_columns_obj                    = (object) null;
				$wpda_database_columns_obj->className         = "{$this->columns[ $i ]} {$data_type_class}";
				$wpda_database_columns_obj->name              = $this->columns[ $i ];
				$wpda_database_columns_obj->targets           = $i;
				$wpda_database_columns_obj->label             = $column_label;
				$wpda_database_columns_obj->searchBuilderType = WPDA::get_sb_type( $this->wpda_list_columns->get_column_data_type( $this->columns[ $i ] ) );

				if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
					// Check for geolocation support
					if ( isset( $this->json->wpda_geo ) && null !== $geolocation ) {
						if (
							isset( $this->json->wpda_geo->geo_marker_column ) &&
							is_numeric( $this->json->wpda_geo->geo_marker_column ) &&
							$i === $this->json->wpda_geo->geo_marker_column
						) {
							if (
								isset( $this->json->wpda_geo->geo_marker_position ) &&
								'before' === $this->json->wpda_geo->geo_marker_position
							) {
								$wpda_database_columns_obj->className .= ' wpda_geo_map_marker_before';
							} else {
								$wpda_database_columns_obj->className .= ' wpda_geo_map_marker';
							}
						}
					}
				}

				$wpda_database_columns .= json_encode( $wpda_database_columns_obj );
				if ( $i < count( $this->columns ) - 1 ) {
					$wpda_database_columns .= ',';
				}
			}

			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				// Add distance column to table
				if ( isset( $this->json->wpda_geo ) && null !== $geolocation ) {
					$wpda_database_columns_obj            = (object) null;
					$wpda_database_columns_obj->className = 'wpda_geo_distance distance always';
					$wpda_database_columns_obj->name      = 'distance';
					$wpda_database_columns_obj->targets   = count( $this->columns );
					$wpda_database_columns_obj->label     = __( 'Distance', 'wp-data-access' );
					$wpda_database_columns               .= ',' . json_encode( $wpda_database_columns_obj );
				}
			}

			return $wpda_database_columns;
		}

		protected function get_language() {
			// Get jQuery DataTables language
			return WPDA::get_option( WPDA::OPTION_DP_LANGUAGE );
		}

		protected function add_buttons( $use_buttons_extension, $pub_id, $table_name ) {
			// This feature is implemented in the premium version
			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				if ( $use_buttons_extension ) {
					// Add jQuery DataTables buttons
					$this->buttons = '[';

					// Add custom buttons
					$has_custom_buttons = false;
					if ( isset( $this->json->wpda_buttons_custom ) && is_array( $this->json->wpda_buttons_custom ) ) {
						foreach ( $this->json->wpda_buttons_custom as $wpda_button_custom ) {
							if ( $has_custom_buttons ) {
								$this->buttons .= ',';
							}
							$button       = '{';
							$button_start = true;
							foreach ( $wpda_button_custom as $key => $value ) {
								if ( ! $button_start ) {
									$button .= ',';
								}
								if ( 'action' === $key ) {
									$button .= "\"{$key}\":{$value}";
								} else {
									if ( 'object' === gettype( $value ) ) {
										$button .= "\"{$key}\":" . json_encode( $value );
									} else {
										$button .= "\"{$key}\":\"{$value}\"";
									}
								}
								$button_start = false;
							}
							$button            .= '}';
							$this->buttons     .= $button;
							$has_custom_buttons = true;
						}
					}

					// Add standard buttons, values allowed:
					// C = export to csv
					// E = export to excel
					// F = export to pdf
					// S = export to sql
					// Y = copy to cliboard
					// P = print
					// V = toggle column visibility - list
					// T = toggle column visibility - buttons
					$wpda_buttons = '';
					if ( isset( $this->json->wpda_buttons ) ) {
						if ( '' !== $this->json->wpda_buttons ) {
							$wpda_buttons = strtoupper( $this->json->wpda_buttons );
						}
					}
					if ( '' === $wpda_buttons && ! $has_custom_buttons ) {
						$wpda_buttons = 'CEFPYSVT';
					}

					$first_character = true;
					foreach ( str_split( $wpda_buttons ) as $wpda_button ) {
						if ( ! $first_character || $has_custom_buttons ) {
							$this->buttons .= ',';
						}
						$first_character = false;
						switch ( $wpda_button ) {
							case 'Y':
								$this->buttons .= $this->add_export_button( 'copy', 'fa-copy', 'Copy table to clipboard' );
								break;
							case 'C':
								$this->buttons .= $this->add_export_button( 'csv', 'fa-file-csv', 'Export to CSV' );
								break;
							case 'E':
								$this->buttons .= $this->add_export_button( 'excel', 'fa-file-excel', 'Export to Excel' );
								break;
							case 'F':
								$this->buttons .= $this->add_export_button( 'pdf', 'fa-file-pdf', 'Export to PDF' );
								break;
							case 'P':
								$this->buttons .= $this->add_export_button( 'print', 'fa-print', 'Print table' );
								break;
							case 'T':
								$this->buttons .= $this->add_export_button( 'columnsToggle', 'fa-check-circle', 'Select columns' );
								break;
							case 'V':
								$this->buttons .= $this->add_export_button( 'colvis', 'fa-check-circle', 'Select columns' );
								break;
							case 'S':
								$wpnonce  = wp_create_nonce( 'wpda-export-' . json_encode( $table_name ) );
								$function =
									'function ( e, dt, node, config ) { export_publication_selection_to_sql(' .
									'"' . esc_attr( $table_name ) . '"' .
									',' . esc_attr( $pub_id ) .
									', "' . esc_attr( $wpnonce ) . '"' .
									',' . json_encode( $this->primary_index_sorted ) .
									');';
								switch ( $this->get_button_caption() ) {
									case 'icon':
										$button_text = '<i class=\\"fas fa-database\\"></i>';
										break;
									case 'both':
										$button_text = '<i class=\\"fas fa-database wpda-space\\"></i>SQL';
										break;
									default:
										$button_text = 'SQL';
								}
								$this->buttons .= '{"className":"wpda_tooltip","attr":{"title":"Export to SQL"},"text":"' . $button_text . '","action":' . $function . '}}';
								break;
						}
					}
					$this->buttons .= ']';
				}
			}
		}

		private function get_button_caption() {
			return isset( $this->json->wpda_button_caption ) && null !== $this->json->wpda_button_caption ?
					$this->json->wpda_button_caption : 'label';

		}

		protected function add_export_button( $button_type, $icon, $hint ) {
			// This feature is implemented in the premium version
			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				$button_label = 'colvis' === $button_type ? 'Columns' : $button_type;
				switch ( $this->get_button_caption() ) {
					case 'icon':
						$button_text = '<i class=\\"fas ' . $icon . '\\"></i>';
						break;
					case 'both':
						$button_text = '<i class=\\"fas ' . $icon . ' wpda-space\\"></i>' . $button_label;
						break;
					default:
						$button_text = $button_label;
				}
				return '{"extend":"' . $button_type . '","className":"wpda_tooltip","attr":{"title":"' . $hint . '"},"text":"' . $button_text . '"}';

			}
		}

		protected function add_geolocation( $geolocation, $pub_id, $table_name, $database ) {
			$this->geo_search_type = (object) null;

			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				if (
					isset( $this->json->wpda_geo ) &&
					null !== $geolocation &&
					isset(
						$geolocation->latitude,
						$geolocation->longitude,
						$geolocation->radius_csv,
						$geolocation->radius
					)
				) {
					// Add geolocation support
					$this->json->ordering = false;

					$range_label   = __( 'Geo range search', 'wp-data-access' );
					$selected_km   = '';
					$selected_mile = '';

					if (
						isset( $this->table_settings->geolocation_settings->unit ) &&
						'mile' === $this->table_settings->geolocation_settings->unit
					) {
						$selected_mile = 'selected';
					} else {
						$selected_km = 'selected';
					}

					$radius_options = '';
					$radius         = explode( ',', $geolocation->radius_csv );
					for ( $i = 0; $i < sizeof( $radius ); $i++ ) {
						$radius_value    = esc_attr( trim( $radius[ $i ] ) );
						$selected        = $geolocation->radius === $radius_value ? ' selected' : '';
						$radius_options .= "<option value='{$radius_value}'{$selected}>{$radius_value}</option>";
					}

					$showmap = ! (
						isset( $this->json->wpda_geo->show_map ) &&
						(
							false === $this->json->wpda_geo->show_map ||
							'false' === $this->json->wpda_geo->show_map
						)
					);

					$showfilter = ! (
						isset( $this->json->wpda_geo->show_filter ) &&
						(
							false === $this->json->wpda_geo->show_filter ||
							'false' === $this->json->wpda_geo->show_filter
						)
					);

					if ( isset( $this->json->wpda_geo->map_location ) ) {
						$maplocation = $this->json->wpda_geo->map_location;
					} else {
						$maplocation = 'plugin';
						if ( isset( $this->table_settings->geolocation_settings->radius_type ) ) {
							$this->json->wpda_geo->map_location = $this->table_settings->geolocation_settings->radius_type;
						}
					}

					$id              = esc_attr( $table_name ) . esc_attr( $pub_id );
					$geo_search_link = '';
					if ( $showmap ) {
						// Use shortcode wpdageomap to add map (add id for interaction)
						$this->geomap =
							'<div id="' . esc_attr( $table_name ) . esc_attr( $pub_id ) . '_geocontainer" class="wpda_geo_map">' .
							do_shortcode( "[wpdageomap schema_name='" . esc_attr( $database ) . "' table_name='" . esc_attr( $table_name ) . "' map_location='{$maplocation}' map_select='hide' map_init='false' map_label='true']" ) .
							'</div>';

						$geo_search_link = "
							<a href='javascript:void(0)' 
								id='{$id}_geobutton' 
								title='Show map' 
								class='wpda_tooltip wpda_geo_globe'
							>
								&#127760;
							</a>
						";
					}

					if ( $showfilter ) {
						$this->geo_search = "
							<div class='wpda_range_filter'>
								<label>
									{$range_label}:
								</label>
								<select id='{$id}_georange'>
									{$radius_options}
								</select>
								<select id='{$id}_geounits'>
									<option value='km' {$selected_km}>Kilometer</option>
									<option value='mile' {$selected_mile}>Mile</option>
								</select>
								{$geo_search_link}
							</div>
						";
					} else {
						$this->geo_search = "
							<div class='wpda_range_filter_globe'>
								{$geo_search_link}
							</div>
						";

					}

					$this->geo_search_type->initial_lat = isset( $this->table_settings->geolocation_settings->initial_lat ) ? $this->table_settings->geolocation_settings->initial_lat : null;
					$this->geo_search_type->initial_lng = isset( $this->table_settings->geolocation_settings->initial_lng ) ? $this->table_settings->geolocation_settings->initial_lng : null;
					$this->geo_search_type->radius      = isset( $this->table_settings->geolocation_settings->radius ) ? $this->table_settings->geolocation_settings->radius : null;
					$this->geo_search_type->unit        = isset( $this->table_settings->geolocation_settings->unit ) ? $this->table_settings->geolocation_settings->unit : null;
				}
			}
		}

		protected function add_read_more( $pub_id, $table_name, $pub_table_options_paging ) {
			if (
				'false' === $pub_table_options_paging &&
				isset( $this->json->serverSide ) &&
				( 'true' === $this->json->serverSide || true === $this->json->serverSide )
			) {
				$this->read_more_html =
					'<div id="' . esc_attr( $table_name ) . "{$pub_id}_more_container\" class='wpda_more_container' >" .
					'<button id="' . esc_attr( $table_name ) . "{$pub_id}_more_button\" type='button' class='wpda_more_button dt-button'>SHOW MORE</button>" .
					'</div>';
			}

			return '' === $this->read_more_html ? 'false' : 'true';
		}

		protected function update_extra_header( $header2, $pub_table_options_searching ) {
			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				if (
					isset( $this->json->serverSide ) &&
					( 'false' === $this->json->serverSide || false === $this->json->serverSide )
				) {
					return '';
				}
			}

			return $header2;
		}

		protected function add_global_style( $styling, $pub_styles ) {
			$dataTablesClass = str_replace( array( ',', 'default' ), array( ' ', 'display' ), $pub_styles );

			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				// Apply global styling
				if ( 'jqueryui' === $styling ) {
					$dataTablesClass .= 'ui-widget-content';
				}
				switch ( $styling ) {
					case 'jqueryui':
						if ( isset( $this->json->dom ) ) {
							$table = strpos( $this->json->dom, 't' );
							if ( false !== $table ) {
								$before = substr( $this->json->dom, 0, $table );
								$after  = substr( $this->json->dom, $table + 1 );

								$this->json->dom =
									'<"fg-toolbar ui-toolbar ui-widget-header ui-helper-clearfix ui-corner-tl ui-corner-tr"' . $before . '>' .
									't' .
									'<"fg-toolbar ui-toolbar ui-widget-header ui-helper-clearfix ui-corner-bl ui-corner-br"' . $after . '>';
							}
						}
						break;
					case 'semantic':
						if ( isset( $this->json->dom ) ) {
							$tablePos   = strpos( $this->json->dom, 't' );
							$processPos = strpos( $this->json->dom, 'r' );
							if ( false !== $tablePos ) {
								$before = str_replace( 'r', '', substr( $this->json->dom, 0, $tablePos ) );
								$table  = 't';
								if ( false !== $processPos ) {
									$table .= 'r';
								}
								$after = str_replace( 'r', '', substr( $this->json->dom, $tablePos + 1 ) );

								$semantic = function ( $tags ) {
									$cols = '';
									if ( strlen( $tags ) % 2 !== 0 ) {
										$cols = "<'sixteen wide column'{$tags[0]}>";
										$tags = substr( $tags, 1 );
									}
									for ( $i = 0; $i < strlen( $tags ); $i++ ) {
										$align = $i % 2 !== 0 ? 'right aligned' : '';
										$cols .= "<'{$align} eight wide column'{$tags[$i]}>";
									}
									return "<'row'{$cols}>";
								};

								$dom_before = $semantic( $before );
								$dom_after  = $semantic( $after );

								$this->json->dom =
									"<'ui stackable grid'
								{$dom_before}
								<'row dt-table'
								<'sixteen wide column'{$table}>
								>
								{$dom_after}
								>";
							}
						}
						break;
					case 'foundation':
						// Use jQuery DataTables default
						break;
					case 'bootstrap':
						if ( isset( $this->json->dom ) ) {
							$tablePos   = strpos( $this->json->dom, 't' );
							$processPos = strpos( $this->json->dom, 'r' );
							if ( false !== $tablePos ) {
								$before = str_replace( 'r', '', substr( $this->json->dom, 0, $tablePos ) );
								$table  = 't';
								if ( false !== $processPos ) {
									$table .= 'r';
								}
								$after = str_replace( 'r', '', substr( $this->json->dom, $tablePos + 1 ) );

								$bootstrap3 = function ( $tags ) {
									$cols = '';
									if ( strlen( $tags ) % 2 !== 0 ) {
										$cols = "<'col-sm-12'{$tags[0]}>";
										$tags = substr( $tags, 1 );
									}
									for ( $i = 0; $i < strlen( $tags ); $i++ ) {
										$cols .= "<'col-sm-6'{$tags[$i]}>";
									}
									return "<'row'{$cols}>";
								};

								$dom_before = $bootstrap3( $before );
								$dom_after  = $bootstrap3( $after );

								$this->json->dom =
									$dom_before .
									"<'row'<'col-sm-12'tr>>" .
									$dom_after;
							}
						}
						break;
					case 'bootstrap4':
						if ( isset( $this->json->dom ) ) {
							$tablePos   = strpos( $this->json->dom, 't' );
							$processPos = strpos( $this->json->dom, 'r' );
							if ( false !== $tablePos ) {
								$before = str_replace( 'r', '', substr( $this->json->dom, 0, $tablePos ) );
								$table  = 't';
								if ( false !== $processPos ) {
									$table .= 'r';
								}
								$after = str_replace( 'r', '', substr( $this->json->dom, $tablePos + 1 ) );

								$bootstrap4 = function ( $tags ) {
									$cols = '';
									if ( strlen( $tags ) % 2 !== 0 ) {
										$cols = "<'col-sm-12'{$tags[0]}>";
										$tags = substr( $tags, 1 );
									}
									for ( $i = 0; $i < strlen( $tags ); $i++ ) {
										$cols .= "<'col-sm-12 col-sm-6'{$tags[$i]}>";
									}
									return "<'row'{$cols}>";
								};

								$dom_before = $bootstrap4( $before );
								$dom_after  = $bootstrap4( $after );

								$this->json->dom =
									$dom_before .
									"<'row'<'col-sm-12'tr>>" .
									$dom_after;
							}
						}
						break;
					default:
						// Use default or provided custom dom
				}
			}

			return $dataTablesClass;
		}

		protected function add_styling_template( $pub_id, $table_name, $dashboard_styling, $publication ) {
			$styling_template = '';
			$add_modal_head   = false;

			// Needed for jdt
			$this->json->wpda_styling = $styling_template;

			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				if (
					! $dashboard_styling &&
					isset( $publication[0]['pub_style_premium'] ) &&
					'Yes' === $publication[0]['pub_style_premium']
				) {
					// Add color
					if (
						! isset( $publication[0]['pub_style_color'] ) ||
						null === $publication[0]['pub_style_color'] ||
						'' === $publication[0]['pub_style_color']
					) {
						$color = 'default';
					} else {
						$color = $publication[0]['pub_style_color'];
					}
					$template         = new \WPDataAccess\Premium\WPDAPRO_Templates\WPDAPRO_Template_Data_Publisher_Color();
					$styling_template = $template->get_template(
						array(
							'table_name' => $table_name,
							'pub_id'     => $pub_id,
							'color'      => $color,
						)
					);
					$css              = "wpda-color-{$pub_id}";
					$add_modal_head   = true;

					// Add spacing
					if (
						! isset( $publication[0]['pub_style_space'] ) ||
						null === $publication[0]['pub_style_space'] ||
						'' === $publication[0]['pub_style_space']
					) {
						$space = 10;
					} else {
						$space = $publication[0]['pub_style_space'];
					}
					$template          = new \WPDataAccess\Premium\WPDAPRO_Templates\WPDAPRO_Template_Data_Publisher_Space();
					$styling_template .= $template->get_template(
						array(
							'table_name' => $table_name,
							'pub_id'     => $pub_id,
							'space'      => $space,
						)
					);
					$css              .= " wpda-space-{$pub_id}";

					// Define corner settings
					if (
						! isset( $publication[0]['pub_style_corner'] ) ||
						null === $publication[0]['pub_style_corner'] ||
						'' === $publication[0]['pub_style_corner']
					) {
						$corner = 0;
					} else {
						$corner = $publication[0]['pub_style_corner'];
					}
					$template          = new \WPDataAccess\Premium\WPDAPRO_Templates\WPDAPRO_Template_Data_Publisher_Corner();
					$styling_template .= $template->get_template(
						array(
							'table_name' => $table_name,
							'pub_id'     => $pub_id,
							'corner'     => $corner,
						)
					);
					$css              .= " wpda-corner-{$pub_id}";

					// Define modal width
					$pub_style_modal_width =
						isset( $publication[0]['pub_style_modal_width'] ) ?
							esc_attr( $publication[0]['pub_style_modal_width'] ) : 80;
					$template              = new \WPDataAccess\Premium\WPDAPRO_Templates\WPDAPRO_Template_Data_Publisher_Modal();
					$styling_template     .= $template->get_template(
						array(
							'table_name' => $table_name,
							'pub_id'     => $pub_id,
							'modal'      => $pub_style_modal_width,
						)
					);
					$css                  .= " wpda-modal-{$pub_id}";

					$this->json->primary_css_classes      = $css;
					$this->json->primary_add_modal_header = $add_modal_head;
				}
			}

			return $styling_template;
		}

		protected function prepare_json() {
			if ( ! isset( $this->json->dom ) ) {
				$this->json->dom = 'lfrtip';
			}

			// Convert JSON to string
			return json_encode( $this->json );
		}

		protected function generate_nonce( $table_name, $column_names_value, $is_embedded ) {
			// Generate nonce
			$nonce_seed = 'wpda-publication-' . $table_name . '-' . $column_names_value;
			if ( ! $is_embedded ) {
				// Normal WordPress nonce
				return wp_create_nonce( $nonce_seed );
			} else {
				// Plugin string based nonce to secure embedding
				return WPDA::wpda_create_sonce( $nonce_seed );
			}
		}

		/**
		 * Show table header (footer as well)
		 *
		 * @param string $responsive Yes = responsive mode, No = No responsive mode.
		 * @param int    $responsive_cols Number of columns to be displayd in responsive mode.
		 * @param array  $hyperlinks Hyperlinks defined in column settings.
		 * @param string $header2 Adds an extra header row if TRUE.
		 * @param mixed  $geolocation
		 *
		 * @return HTML output
		 */
		protected function show_header( $responsive, $responsive_cols, $hyperlinks, $header2, $geolocation ) {
			$count       = 0;
			$html_output = '';
			$html_search = '';

			foreach ( $this->columns as $column ) {
				$class = '';
				if ( 'yes' === $responsive ) {
					if ( is_numeric( $responsive_cols ) ) {
						if ( (int) $responsive_cols > 0 ) {
							if ( $count >= 0 && $count < $responsive_cols ) {
								$class = 'all';
							} else {
								$class = 'none';
							}
						}
					}
				}

				if ( 'wpda_hyperlink_' !== substr( $column, 0, 15 ) ) {
					$column_label = isset( $this->column_labels[ $column ] ) ? $this->column_labels[ $column ] : $column;
				} else {
					$column_label = $hyperlinks[ substr( $column, strrpos( $column, '_' ) + 1 ) ];
				}

				if ( 'header' === $header2 || 'both' === $header2 ) {
					$html_search .= "<td class=\"{$class}\" data-column_name_search=\"{$column}\" data-column_name_label=\"{$column_label}\"></td>";
					$html_output .= "<th class=\"{$class}\" data-column_name=\"{$column}\">{$column_label}</th>";
				} else {
					$html_output .= "<th class=\"{$class}\" data-column_name_search=\"{$column}\">{$column_label}</th>";
				}
				$count++;
			}

			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				// Add distance column to table
				if ( null !== $this->json ) {
					if ( isset( $this->json->wpda_geo ) && null !== $geolocation ) {
						if ( 'header' === $header2 || 'both' === $header2 ) {
							$html_search = '<th></th>';
						}
						$html_output .=
							'<th class="wpda_geo_distance distance always" data-column_name_search="distance">' .
							__( 'Distance', 'wp-data-access' ) .
							'<span class="wpda_geo_unit"></span>' .
							'</th>';
					}
				}
			}

			if ( '' !== $html_search ) {
				$html_search = "<tr>{$html_search}</tr>";
			}

			return "{$html_search}<tr>{$html_output}</tr>";
		}

		/**
		 * Performs jQuery DataTable query
		 *
		 * Once a jQuery DataTable is build using {@see WPDA_Data_Tables::show()}, the DataTable is filled according
		 * to the search criteria and pagination settings on the Datable. The query is performed through this function.
		 * The query result is returned (echo) in JSON format. Table and column names are checked for existence and
		 * access to prevent hacking the DataTable code and SQL injection.
		 *
		 * @since   1.0.0
		 *
		 * @see WPDA_Data_Tables::show()
		 */
		public function get_data() {
			$where      = '';
			$_filter    = array();
			$has_sp     = false;
			$sp_columns = [];

			$pub_id     = isset( $_REQUEST['pubid'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['pubid'] ) ) : ''; // input var okay.
			$database   = isset( $_REQUEST['wpdasrc'] ) ? str_replace( '`', '', sanitize_text_field( wp_unslash( $_REQUEST['wpdasrc'] ) ) ) : ''; // input var okay.
			$table_name = isset( $_REQUEST['wpdatabs'] ) ? str_replace( '`', '', sanitize_text_field( wp_unslash( $_REQUEST['wpdatabs'] ) ) ) : ''; // input var okay.
			$columns    = isset( $_REQUEST['wpdacols'] ) ? str_replace( '`', '', sanitize_text_field( wp_unslash( $_REQUEST['wpdacols'] ) ) ) : '*'; // input var okay.
			$wpnonce    = isset( $_REQUEST['wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpnonce'] ) ) : ''; // input var okay.

			if ( '' === $pub_id && '' === $table_name ) { // input var okay.
				// Database and table name must be set!
				$this->create_empty_response( 'Missing arguments' );
				wp_die();
			}

			$this->serverSide = true;

			// Set pagination values.
			$offset = 0;
			if ( isset( $_REQUEST['start'] ) ) {
				$offset = sanitize_text_field( wp_unslash( $_REQUEST['start'] ) ); // input var okay.
			}
			$limit = -1; // jQuery DataTables default.
			if ( isset( $_REQUEST['length'] ) ) {
				$limit = sanitize_text_field( wp_unslash( $_REQUEST['length'] ) ); // input var okay.
			}

			$publication_mode = 'normal';
			if (
				-1 == $limit &&
				isset( $_REQUEST['more_start'] ) &&
				isset( $_REQUEST['more_limit'] )
			) {
				$publication_mode = 'more';
				$offset           = sanitize_text_field( wp_unslash( $_REQUEST['more_start'] ) ); // input var okay.
				$limit            = sanitize_text_field( wp_unslash( $_REQUEST['more_limit'] ) ); // input var okay.
			}

			if ( '' !== $pub_id && '0' != $pub_id ) {
				// Get publication data
				$publication = WPDA_Publisher_Model::get_publication( $pub_id );
				if ( false === $publication ) {
					// Publication not found
					$this->create_empty_response( 'Invalid arguments' );
					wp_die();
				}

				$kill_token                 = false;
				$pub_table_options_advanced = $publication[0]['pub_table_options_advanced'];
				$pub_table_options_advanced = str_replace( array( "\r\n", "\r", "\n", "\t" ), '', $pub_table_options_advanced );
				try {
					$json       = json_decode( $pub_table_options_advanced );
					$kill_token = isset( $json->killToken ) && ( true === $json->killToken || 'true' === $json->killToken );
					if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
						// Check for search panes.
						if ( null === $json || ! isset( $json->serverSide ) || true === $json->serverSide ) {
							if ( isset( $json->dom ) && strpos( $json->dom, 'P' ) !== false ) {
								$has_sp = true;
								if ( isset( $json->searchPanes, $json->searchPanes->columns ) && is_array( $json->searchPanes->columns ) ) {
									$sp_columns = $json->searchPanes->columns;
								}
							} else {
								if ( null !== $publication[0]['pub_extentions'] && '' !== $publication[0]['pub_extentions'] ) {
									// Check extension manager for search panes.
									try {
										$pub_extentions = json_decode( $publication[0]['pub_extentions'] );
										if ( isset( $pub_extentions->dom ) && strpos( $pub_extentions->dom, 'P' ) !== false ) {
											$has_sp = true;
										}
										if ( isset( $pub_extentions->wpda_sp_columns ) && is_array( $pub_extentions->wpda_sp_columns ) ) {
											$sp_columns = $pub_extentions->wpda_sp_columns;
										}
									} catch ( \Exception $ee ) {
										$pub_extentions = null;
									}
								}
							}
						}
					}
				} catch ( \Exception $e ) {
					$json = null;
				}

				$database   = $publication[0]['pub_schema_name'];
				$table_name = $publication[0]['pub_table_name'];
				$columns    = $publication[0]['pub_column_names'];

				// Check token
				$table_name_verify = 'Query' === $publication[0]['pub_data_source'] ? "custom{$pub_id}" : $table_name;
				if (
					! $kill_token &&
					! wp_verify_nonce( $wpnonce, 'wpda-publication-' . $table_name_verify . '-' ) &&
					! WPDA::wpda_verify_sonce( $wpnonce, 'wpda-publication-' . $table_name_verify . '-' )
				) {
					$this->create_empty_response( 'Token expired, please refresh page' );
					wp_die();
				}

				if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
					$sql_query = null;
					$is_cpt    = false;
					if ( 'Query' === $publication[0]['pub_data_source'] ) {
						$sql_query = $publication[0]['pub_query'];
					} elseif ( 'CPT' === $publication[0]['pub_data_source'] ) {
						$sql_query = $publication[0]['pub_cpt_query'];
						$is_cpt    = true;
					}

					if ( null !== $sql_query ) {
						// Handle custom query.
						return $this->get_data_custom_query(
							$publication,
							$json,
							$has_sp,
							$sp_columns,
							$sql_query,
							$is_cpt,
							$offset,
							$limit
						);
					}
				}

				// Get default where
				if ( isset( $publication[0]['pub_default_where'] ) ) {
					if ( null !== $publication[0]['pub_default_where'] && '' !== trim( $publication[0]['pub_default_where'] ) ) {
						$where = $publication[0]['pub_default_where'];
					}
				}

				// Get server side options.
				if ( isset( $json->serverSide ) ) {
					$this->serverSide = true === $json->serverSide || 'true' === $json->serverSide;
				} else {
					if ( 'on' !== $publication[0]['pub_table_options_serverside'] || null === $publication[0]['pub_table_options_serverside'] ) {
						$this->serverSide = false;
					}
				}
			} else {
				// Check token = old shortcode usage
				if (
					! wp_verify_nonce( $wpnonce, 'wpda-publication-' . $table_name . '-' . $columns ) &&
					! WPDA::wpda_verify_sonce( $wpnonce, 'wpda-publication-' . $table_name . '-' . $columns )
				) {
					$this->create_empty_response( 'Token expired, please refresh page' );
					wp_die();
				}

				// Do not allow to access other schemas
				if ( strpos( $table_name, '.' ) ) {
					$this->create_empty_response( 'Wrong argument' );
					wp_die();
				}

				// Check access
				$wpda_dictionary_checks = new WPDA_Dictionary_Exist( $database, $table_name );
				if ( ! $wpda_dictionary_checks->table_exists( true, false ) ) {
					$this->create_empty_response( 'Not authorized' );
					wp_die();
				}
			}

			if (
				'' !== $where &&
				'where' !== strtolower( trim( substr( $where, 0, 5 ) ) )
			) {
				$where = "where $where";
			}
			if ( '' !== $where ) {
				$_filter = array(
					'filter_default' => $where,
				);
			}

			$wpdadb = WPDADB::get_db_connection( $database );
			if ( null === $wpdadb ) {
				$this->create_empty_response( 'Invalid connection' );
				wp_die(); // Remote database not available
			}

			// Add field filters from shortcode
			$filter_field_name  = str_replace( '`', '', sanitize_text_field( wp_unslash( $_REQUEST['filter_field_name'] ) ) ); // input var okay.
			$filter_field_value = sanitize_text_field( wp_unslash( $_REQUEST['filter_field_value'] ) ); // input var okay.
			if ( '' !== $filter_field_name && '' !== $filter_field_value ) {
				$filter_field_name_array  = array_map( 'trim', explode( ',', $filter_field_name ) );
				$filter_field_value_array = array_map( 'trim', explode( ',', $filter_field_value ) );
				if ( sizeof( $filter_field_name_array ) === sizeof( $filter_field_value_array ) ) {
					// Add filter to where clause
					for ( $i = 0; $i < sizeof( $filter_field_name_array ); $i++ ) {
						if ( '' === $where ) {
							$where =
								$wpdadb->prepare(
									" where `{$filter_field_name_array[ $i ]}` like %s ",
									array( $filter_field_value_array[ $i ] )
								);
						} else {
							$where .=
								$wpdadb->prepare(
									" and `{$filter_field_name_array[ $i ]}` like %s ",
									array( $filter_field_value_array[ $i ] )
								);
						}

						$_filter['filter_field_name']  = $filter_field_name;
						$_filter['filter_field_value'] = $filter_field_value;
					}
				}
			}

			// Get all column names from table (must be comma seperated string)
			$this->wpda_list_columns = WPDA_List_Columns_Cache::get_list_columns( $database, $table_name );
			$table_columns           = $this->wpda_list_columns->get_table_columns();

			// Save column:data_type pairs for fast access
			$column_array_ordered = array();
			foreach ( $table_columns as $column ) {
				$column_array_ordered[ $column['column_name'] ] = $column['data_type'];
			}

			// Load table settings
			$table_settings_db = WPDA_Table_Settings_Model::query( $table_name, $database );
			if ( isset( $table_settings_db[0]['wpda_table_settings'] ) ) {
				$table_settings = json_decode( $table_settings_db[0]['wpda_table_settings'] );
			}

			if ( '*' === $columns ) {
				// Get all column names from table (must be comma seperated string).
				$column_array = array();
				foreach ( $table_columns as $column ) {
					$column_array[] = $column['column_name'];
				}
				$columns = implode( ',', $column_array );
			} else {
				// Check if columns exist (prevent sql injection).
				$wpda_dictionary_checks = new WPDA_Dictionary_Exist( $database, $table_name );
				$column_array           = explode( ',', $columns );
				$has_dynamic_hyperlinks = false;
				foreach ( $column_array as $column ) {
					if ( 'wpda_hyperlink_' !== substr( $column, 0, 15 ) ) {
						if ( ! $wpda_dictionary_checks->column_exists( $column ) ) {
							// Column not found.
							$this->create_empty_response( 'Invalid column name' );
							wp_die();
						}
					} else {
						$has_dynamic_hyperlinks = true;
					}
				}
				if ( $has_dynamic_hyperlinks ) {
					// Check for columns needed for substitution and missing in the query
					$hyperlink_substitution_columns = array();
					if ( isset( $table_settings->hyperlinks ) ) {
						foreach ( $table_settings->hyperlinks as $hyperlink ) {
							if ( isset( $hyperlink->hyperlink_html ) ) {
								foreach ( $table_columns as $column ) {
									if ( stripos( $hyperlink->hyperlink_html, "\$\${$column['column_name']}\$\$" ) !== false ) {
										$hyperlink_substitution_columns[ $column['column_name'] ] = true;
									}
								}
							}
						}
					}
					if ( sizeof( $hyperlink_substitution_columns ) > 0 ) {
						foreach ( $hyperlink_substitution_columns as $hyperlink_substitution_column => $val ) {
							if ( ! in_array( $hyperlink_substitution_column, $column_array ) ) {
								$columns .= ",{$hyperlink_substitution_column}";
							}
						}
					}
				}
			}

			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				// Add search pane values.
				if ( $has_sp ) {
					if ( isset( $_REQUEST['searchPanes'] ) && is_array( $_REQUEST['searchPanes'] ) ) {
						// Add search panes.
						foreach ( $_REQUEST['searchPanes'] as $key => $search_pane ) {
							if ( is_array( $search_pane ) ) {
								// Add search pane.
								$where .= '' === $where ? ' where ( ' : ' and ( ';
								foreach ( $search_pane as $index => $value ) {
									// Add search pane value.
									$where .=
										$wpdadb->prepare(
											( $index > 0 ? 'or ' : '' ) . " `{$column_array[ $key ]}` = %s ",
											sanitize_text_field( wp_unslash( $search_pane[ $index ] ) )
										);
								}
								$where .= ')';
							}
						}
					}
				}
			}

			// Save column name without backticks for later use
			$column_array_clean = $column_array;

			// Set order by.
			$orderby = '';
			if ( isset( $_REQUEST['order'] ) && is_array( $_REQUEST['order'] ) ) { // input var okay.
				$orderby_columns = array();
				$orderby_args    = array();
				// Sanitize argument array and write result to temporary sanitizes array for processing:
				foreach ( $_REQUEST['order'] as $order_column ) {  // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
					$orderby_args[] = array(
						'column' => sanitize_sql_orderby( wp_unslash( $order_column['column'] ) ),
						'dir'    => sanitize_text_field( wp_unslash( $order_column['dir'] ) ),
					);
				}
				foreach ( $orderby_args as $order_column ) { // input var okay.
					$column_index      = $order_column['column'];
					$column_name       = str_replace( '`', '', $column_array[ $column_index ] );
					$column_dir        = $order_column['dir'];
					$orderby_columns[] = "`$column_name` $column_dir";
				}
				$orderby = implode( ',', $orderby_columns );
			}

			// Add search criteria.
			if ( isset( $_REQUEST['search']['value'] ) ) {
				$search_value = sanitize_text_field( wp_unslash( $_REQUEST['search']['value'] ) ); // input var okay.
			} else {
				$search_value = '';
			}

			$where_columns = WPDA::construct_where_clause(
				$database,
				$table_name,
				$this->wpda_list_columns->get_table_columns(),
				$search_value
			);

			if ( '' !== $where_columns ) {
				if ( '' === $where ) {
					$where = " where $where_columns ";
				} else {
					$where .= " and $where_columns ";
				}
			}

			if ( '' !== $where ) {
				$where = WPDA::substitute_environment_vars( $where );
			}

			if ( '' !== $search_value ) {
				$_filter['filter_dyn'] = $search_value;
			}
			foreach ( $_REQUEST as $key => $value ) {
				if ( 'wpda_search_' === substr( $key, 0, 12 ) ) {
					$_filter['filter_args'][ $key ] = $value;
				}
			}

			$geo_radius_col = '';
			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				if ( isset( $_REQUEST['geosearch'] ) ) {
					if ( isset(
						$_REQUEST['geosearch']['initial_lat'],
						$_REQUEST['geosearch']['initial_lng'],
						$_REQUEST['geosearch']['radius'],
						$_REQUEST['geosearch']['unit']
					) &&
						isset(
							$table_settings->geolocation_settings,
							$table_settings->geolocation_settings->latitude,
							$table_settings->geolocation_settings->longitude
						)
					) {
						// Add geo range search
						$initial_lat = sanitize_text_field( wp_unslash( $_REQUEST['geosearch']['initial_lat'] ) ); // input var okay.
						$initial_lng = sanitize_text_field( wp_unslash( $_REQUEST['geosearch']['initial_lng'] ) ); // input var okay.
						$radius      = sanitize_text_field( wp_unslash( $_REQUEST['geosearch']['radius'] ) ); // input var okay.
						if ( 'mile' === $_REQUEST['geosearch']['unit'] ) {
							$geo_unit = WPDAPRO_Geo_Location_WS::RADIUS_UNIT_MILE;
						} else {
							$geo_unit = WPDAPRO_Geo_Location_WS::RADIUS_UNIT_KM;
						}

						$latitude_column_name  = str_replace( '`', '', $table_settings->geolocation_settings->latitude );
						$longitude_column_name = str_replace( '`', '', $table_settings->geolocation_settings->longitude );
						$geo_radius_calc       = "
						(
							{$geo_unit} * acos (
								cos ( radians( {$initial_lat} ) )
								* cos( radians( `{$latitude_column_name}` ) )
								* cos( radians( `{$longitude_column_name}` ) - radians( {$initial_lng} ) )
								+ sin ( radians( {$initial_lat} ) )
								* sin( radians( `{$latitude_column_name}` ) )
							)
						)
					";
						$where_geo             = "
						(
								$geo_radius_calc < {$radius}
							and `{$latitude_column_name}` is not null
							and `{$longitude_column_name}` is not null
						)
					";

						if ( '' === $where ) {
							$where .= ' where ' . trim( preg_replace( '/\s+/', ' ', $where_geo ) ) . ' ';
						} else {
							$where .= ' and ' . trim( preg_replace( '/\s+/', ' ', $where_geo ) ) . ' ';
						}

						$orderby = $geo_radius_calc . ' ' . $orderby;

						$geo_radius_col = ", format( {$geo_radius_calc}, 2 ) as distance ";
					} else {
						if (
							isset( $_REQUEST['geosearch']['map_location'] ) &&
							'user' === $_REQUEST['geosearch']['map_location']
						) {
							if ( '' === $where ) {
								$where .= ' where 1=2 ';
							} else {
								$where .= ' and 1=2 ';
							}
						}
					}
				}
			}

			// Execute query.
			$column_array         = explode( ',', $columns );
			$column_array_orig    = $column_array;
			$images_array         = array();
			$imagesurl_array      = array();
			$attachments_array    = array();
			$hyperlinks_array     = array();
			$hyperlinks_array_col = array();
			$audio_array          = array();
			$video_array          = array();
			if (
				isset( $publication[0]['pub_format'] ) &&
				'' !== $publication[0]['pub_format'] &&
				null !== $publication[0]['pub_format']
			) {
				try {
					$pub_format = json_decode( $publication[0]['pub_format'], true );
				} catch ( \Exception $e ) {
					$pub_format = null;
				}

				$column_images      = array();
				$column_attachments = array();
				if ( isset( $pub_format['pub_format']['column_images'] ) ) {
					$column_images = $pub_format['pub_format']['column_images'];
				}
				if ( isset( $pub_format['pub_format']['column_attachments'] ) ) {
					$column_attachments = $pub_format['pub_format']['column_attachments'];
				}
				$i = 0;
				foreach ( $column_array as $col ) {
					if ( isset( $column_images[ $col ] ) ) {
						array_push( $images_array, $i );
					}
					$i ++;
				}
				$i = 0;
				foreach ( $column_array as $col ) {
					if ( isset( $column_attachments[ $col ] ) ) {
						array_push( $attachments_array, $i );
					}
					$i ++;
				}
			} else {
				$pub_format = null;
			}

			// Check media columns defined on plugin level and add to arrays
			$i = 0;
			foreach ( $column_array as $col ) {
				if ( 'Image' === WPDA_Media_Model::get_column_media( $table_name, $col, $database ) ) {
					if ( ! isset( $images_array[ $i ] ) ) {
						array_push( $images_array, $i );
					}
				} elseif ( 'ImageURL' === WPDA_Media_Model::get_column_media( $table_name, $col, $database ) ) {
					array_push( $imagesurl_array, $i );
				} elseif ( 'Attachment' === WPDA_Media_Model::get_column_media( $table_name, $col, $database ) ) {
					if ( ! isset( $attachments_array[ $i ] ) ) {
						array_push( $attachments_array, $i );
					}
				} elseif ( 'Hyperlink' === WPDA_Media_Model::get_column_media( $table_name, $col, $database ) ) {
					if ( ! isset( $hyperlinks_array[ $i ] ) ) {
						array_push( $hyperlinks_array, $i );
						array_push( $hyperlinks_array_col, $col );
					}
				} elseif ( 'Audio' === WPDA_Media_Model::get_column_media( $table_name, $col, $database ) ) {
					array_push( $audio_array, $i );
				} elseif ( 'Video' === WPDA_Media_Model::get_column_media( $table_name, $col, $database ) ) {
					array_push( $video_array, $i );
				}
				$i ++;
			}

			// Change dynamic hyperlinks
			$update                  = array();
			$i                       = 0;
			$hyperlinks_column_index = array();
			foreach ( $column_array as $col ) {
				if ( 'wpda_hyperlink_' === substr( $col, 0, 15 ) ) {
					$update[ $col ]                = "'x' as {$col}";
					$hyperlinks_column_index[ $i ] = substr( $col, 15 );
				} else {
					$update[ $col ] = '`' . str_replace( '`', '', $col ) . '`';
				}
				$i ++;
			}
			$column_array = $update;

			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				// Add Query Builder support.
				$qb_where = $this->qb( array_flip( $this->get_labels( json_encode( $pub_format ) ) ) );
				if ( '' !== $qb_where ) {
					if ( strpos( $where, '1=3' ) !== false ) {
						$where = '';
					}
					$where .= ( '' === $where ? ' WHERE ' : ' AND ' ) . $qb_where;
				}
			}

			$columns_backticks = implode( ',', $column_array ) . $geo_radius_col;
			$query             = "select $columns_backticks from `{$wpdadb->dbname}`.`$table_name` $where";
			if ( '' !== $orderby ) {
				$query .= " order by {$orderby} ";
			}
			if ( -1 != $limit ) {
				$query .= " limit $limit offset $offset";
			}

			$hyperlinks = array();
			if ( sizeof( $hyperlinks_column_index ) ) {
				if ( isset( $table_settings->hyperlinks ) ) {
					foreach ( $table_settings->hyperlinks as $hyperlink ) {
						$hyperlink_label  = isset( $hyperlink->hyperlink_label ) ? $hyperlink->hyperlink_label : '';
						$hyperlink_target = isset( $hyperlink->hyperlink_target ) ? $hyperlink->hyperlink_target : false;
						$hyperlink_html   = isset( $hyperlink->hyperlink_html ) ? $hyperlink->hyperlink_html : '';
						if ( $hyperlink_label !== '' && $hyperlink_html !== '' ) {
							array_push(
								$hyperlinks,
								array(
									'hyperlink_label'  => $hyperlink_label,
									'hyperlink_target' => $hyperlink_target,
									'hyperlink_html'   => $hyperlink_html,
								)
							);
						}
					}
				}
			}

			$nl2br = isset( $_REQUEST['nl2br'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nl2br'] ) ) : ''; // input var okay.
			if ( 'on' === $nl2br || 'yes' === $nl2br || 'true' === $nl2br ) {
				$nl2br = 'on';
			} else {
				if ( '' !== $pub_id ) {
					if ( isset( $publication[0]['pub_table_options_nl2br'] ) ) {
						$nl2br = $publication[0]['pub_table_options_nl2br'];
					}
				}
			}

			$wpdadb->suppress_errors( true );
			$rows = $wpdadb->get_results( $query, 'ARRAY_N' ); // phpcs:ignore Standard.Category.SniffName.ErrorCode
			if ( '' !== $wpdadb->last_error ) {
				$this->create_empty_response( $wpdadb->last_error, $query );
				wp_die();
			}
			$rows_final = array();
			foreach ( $rows as $row ) {
				$row_orig = $row;

				if ( 'on' === $nl2br && null !== $nl2br ) {
					// Replace NL with BR tags
					for ( $nl = 0; $nl < sizeof( $row ); $nl++ ) {
						$row[ $nl ] = nl2br( $row[ $nl ] );
					}
				}

				foreach ( $hyperlinks_column_index as $key => $value ) {
					if ( isset( $hyperlinks[ $value ] ) ) {
						$hyperlink_html = isset( $hyperlinks[ $value ]['hyperlink_html'] ) ? $hyperlinks[ $value ]['hyperlink_html'] : '';

						if ( '' !== $hyperlink_html ) {
							$i = 0;
							foreach ( $column_array as $column ) {
								$column_name    = str_replace( '`', '', $column );
								$hyperlink_html = str_replace( "\$\${$column_name}\$\$", $row[ $i ], $hyperlink_html );
								$i ++;
							}
						}

						$macro          = new WPDA_Macro( $hyperlink_html );
						$hyperlink_html = $macro->exe_macro();

						if ( '' !== $hyperlink_html ) {
							if ( false !== strpos( ltrim( $hyperlink_html ), '&lt;' ) ) {
								$row[ $key ] = html_entity_decode( $hyperlink_html );
							} else {
								$hyperlink_label  = isset( $hyperlinks[ $value ]['hyperlink_label'] ) ? $hyperlinks[ $value ]['hyperlink_label'] : '';
								$hyperlink_target = isset( $hyperlinks[ $value ]['hyperlink_target'] ) ? $hyperlinks[ $value ]['hyperlink_target'] : false;

								$target = true === $hyperlink_target ? "target='_blank'" : '';

								$row[ $key ] = "<a href='" . str_replace( ' ', '+', $hyperlink_html ) . "' {$target}>{$hyperlink_label}</a>";
							}
						} else {
							$row[ $key ] = '';
						}
					} else {
						$row[ $key ] = 'ERROR';
					}
				}

				for ( $i = 0; $i < sizeof( $imagesurl_array ); $i ++ ) {
					$row[ $imagesurl_array[ $i ] ] = '<img src="' . $row[ $imagesurl_array[ $i ] ] . '" width="100%">';
				}

				for ( $i = 0; $i < sizeof( $images_array ); $i ++ ) {
					$image_ids = explode( ',', $row[ $images_array[ $i ] ] );
					$image_src = '';
					foreach ( $image_ids as $image_id ) {
						$url = wp_get_attachment_url( esc_attr( $image_id ) );
						if ( false !== $url ) {
							$image_src .= '' !== $image_src ? '<br/>' : '';
							$image_src .= '<img src="' . $url . '" width="100%">';
						}
					}
					$row[ $images_array[ $i ] ] = $image_src;
				}

				for ( $i = 0; $i < sizeof( $attachments_array ); $i ++ ) {
					$media_ids   = explode( ',', $row[ $attachments_array[ $i ] ] );
					$media_links = '';
					foreach ( $media_ids as $media_id ) {
						$url = wp_get_attachment_url( esc_attr( $media_id ) );
						if ( false !== $url ) {
							$mime_type = get_post_mime_type( $media_id );
							if ( false !== $mime_type ) {
								$title        = get_the_title( esc_attr( $media_id ) );
								$media_links .= WPDA_List_Table::column_media_attachment( $url, $title, $mime_type );
							}
						}
					}
					$row[ $attachments_array[ $i ] ] = $media_links;
				}

				if ( isset( $hyperlinks_array ) ) {
					$hyperlink_definition =
						isset( $table_settings->table_settings->hyperlink_definition ) &&
						'text' === $table_settings->table_settings->hyperlink_definition ? 'text' : 'json';

					for ( $i = 0; $i < sizeof( $hyperlinks_array ); $i ++ ) {
						if ( 'json' === $hyperlink_definition ) {
							$hyperlink = json_decode( $row[ $hyperlinks_array[ $i ] ], true );
							if ( is_array( $hyperlink ) &&
								 isset( $hyperlink['label'] ) &&
								 isset( $hyperlink['url'] ) &&
								 isset( $hyperlink['target'] )
							) {
								if ( '' === $hyperlink['url'] ) {
									$row[ $hyperlinks_array[ $i ] ] = $hyperlink['label'];
								} else {
									$row[ $hyperlinks_array[ $i ] ] = "<a href='{$hyperlink['url']}' target='{$hyperlink['target']}'>{$hyperlink['label']}</a>";
								}
							} else {
								$row[ $hyperlinks_array[ $i ] ] = '';
							}
						} else {
							if ( null !== $row[ $hyperlinks_array[ $i ] ] && '' !== $row[ $hyperlinks_array[ $i ] ] ) {
								$hyperlink_label                = $this->wpda_list_columns->get_column_label( $hyperlinks_array_col[ $i ] );
								$row[ $hyperlinks_array[ $i ] ] = "<a href='{$row[ $hyperlinks_array[ $i ] ]}' target='_blank'>{$hyperlink_label}</a>";
							} else {
								$row[ $hyperlinks_array[ $i ] ] = '';
							}
						}
					}
				}

				for ( $i = 0; $i < sizeof( $audio_array ); $i ++ ) {
					$media_ids   = explode( ',', $row[ $audio_array[ $i ] ] );
					$media_links = '';
					foreach ( $media_ids as $media_id ) {
						if ( 'audio' === substr( get_post_mime_type( $media_id ), 0, 5 ) ) {
							$url = wp_get_attachment_url( esc_attr( $media_id ) );
							if ( false !== $url ) {
								$title = get_the_title( esc_attr( $media_id ) );
								if ( false !== $url ) {
									$media_links .=
										'<div class="wpda_tooltip" title="' . $title . '">' .
										do_shortcode( '[audio src="' . $url . '"]' ) .
										'</div>';
								}
							}
						}
					}
					$row[ $audio_array[ $i ] ] = $media_links;
				}

				for ( $i = 0; $i < sizeof( $video_array ); $i ++ ) {
					$media_ids   = explode( ',', $row[ $video_array[ $i ] ] );
					$media_links = '';
					foreach ( $media_ids as $media_id ) {
						if ( 'video' === substr( get_post_mime_type( $media_id ), 0, 5 ) ) {
							$url = wp_get_attachment_url( esc_attr( $media_id ) );
							if ( false !== $url ) {
								if ( false !== $url ) {
									$media_links .=
										do_shortcode( '[video src="' . $url . '"]' );
								}
							}
						}
					}
					$row[ $video_array[ $i ] ] = $media_links;
				}

				// Format date and time columns
				for ( $i = 0; $i < sizeof( $row ); $i++ ) {
					if ( '' !== $row[ $i ] && null !== $row[ $i ] ) {
						if ( isset( $column_array_clean[ $i ] ) ) {
							if ( isset( $column_array_ordered[ $column_array_clean[ $i ] ] ) ) {
								switch ( $column_array_ordered[ $column_array_clean[ $i ] ] ) {
									case 'date':
										$row[ $i ] = date_i18n( get_option( 'date_format' ), strtotime( $row[ $i ] ) );
										break;
									case 'time':
										$row[ $i ] = date_i18n( get_option( 'time_format' ), strtotime( $row[ $i ] ) );
										break;
									case 'datetime':
									case 'timestamp':
										$row[ $i ] = date_i18n(
											get_option( 'date_format' ) . ' ' .
											get_option( 'time_format' ),
											strtotime( $row[ $i ] )
										);
								}
							}
						}
					}
				}

				// Remove script tags if available
				for ( $i = 0; $i < sizeof( $row ); $i++ ) {
					$row[ $i ] = str_replace(
						array( '<script>', '</script>' ),
						array( '&lt;script&gt;', '&lt;/script&gt;' ),
						$row[ $i ]
					);
				}

				array_push( $rows_final, $row );
			}

			if ( $this->serverSide ) {
				$row_count_estimate = WPDA::get_row_count_estimate( $database, $table_name, $table_settings );
				$rows_estimate      = $row_count_estimate['row_count'];
				$do_real_count      = $row_count_estimate['do_real_count'];
			} else {
				$rows_estimate = count( $rows_final );
				$do_real_count = false;
			}

			if ( 'more' === $publication_mode ) {
				// Use estimate row count
				$count_table          = $rows_estimate;
				$count_table_filtered = $rows_estimate;
			} else {
				if ( ! $do_real_count ) {
					// Use estimate row count
					$count_table = $rows_estimate;
				} else {
					// Count rows in table = real row count
					$query2      = "select count(*) from `{$wpdadb->dbname}`.`$table_name`";
					$count_rows  = $wpdadb->get_results( $query2, 'ARRAY_N' ); // phpcs:ignore Standard.Category.SniffName.ErrorCode
					$count_table = $count_rows[0][0]; // Number of rows in table.
				}

				if ( isset( $_REQUEST['wpda_use_estimates_only'] ) && 'true' === $_REQUEST['wpda_use_estimates_only'] ) {
					// Prevent row count, only estimates required
					$count_table_filtered = $count_table;
				} else {
					if ( '' !== $where ) {
						// Count rows in selection (only necessary if a search criteria was entered).
						$query3               = "select count(*) from `{$wpdadb->dbname}`.`$table_name` $where";
						$count_rows_filtered  = $wpdadb->get_results( $query3, 'ARRAY_N' ); // phpcs:ignore Standard.Category.SniffName.ErrorCode
						$count_table_filtered = $count_rows_filtered[0][0]; // Number of rows in table.
					} else {
						// No search criteria entered: # filtered rows = # table rows.
						$count_table_filtered = $count_table;
					}
				}
			}

			// Convert query result to jQuery DataTables object.
			$obj                  = (object) null;
			$obj->draw            = isset( $_REQUEST['draw'] ) ? intval( $_REQUEST['draw'] ) : 0;
			$obj->recordsTotal    = intval( $count_table );
			$obj->recordsFiltered = intval( $count_table_filtered );
			$obj->data            = $rows_final;
			$obj->error           = $wpdadb->last_error;
			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				if ( isset( $_REQUEST['draw'] ) &&  1 === intval( $_REQUEST['draw'] ) ) {
					// Add search pane content to first draw only.
					if (
						$has_sp &&
						(
							null === $json ||
							! isset( $json->serverSide ) ||
							true === $json->serverSide
						)
					) {
						// Add search panes.
						$obj->searchPanes = $this->sp( $wpdadb, $table_name, $column_array_clean, $sp_columns, $where );
					}
				}
			}

			if ( 'on' === WPDA::get_option( WPDA::OPTION_PLUGIN_DEBUG ) ) {
				$obj->debug = array(
					'columns'           => $columns,
					'columns_backticks' => $columns_backticks,
					'query'             => $query,
					'where'             => $where,
					'orderby'           => $orderby,
					'filter'            => $_filter,
					'advanced_settings' => $json,
					'column_labels'     => $this->wpda_list_columns->get_table_column_headers(),
					'labels'            => array_flip( $this->get_labels( json_encode( $pub_format ) ) ),
					'serverSide'		=> $this->serverSide,
				);
			}

			// Send header
			if ( ! WPDA::wpda_verify_sonce( $wpnonce, 'wpda-publication-' . $table_name . '-' ) ) {
				WPDA::sent_header( 'application/json' );
			} else {
				// Enable CORS for embedded publications
				WPDA::sent_header( 'application/json', '*' );
			}

			// Convert object to json. jQuery DataTables needs json format.
			echo json_encode( $obj );
			wp_die();
		}

		private function get_data_custom_query(
			$publication,
			$json,
			$has_sp,
			$sp_columns,
			$sql_query,
			$is_cpt,
			$offset,
			$limit
		) {
			// Execute custom SQL query.
			// This feature is implemented in the premium version.
			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				if ($is_cpt) {
					global $wpdb;
					$database = $wpdb->dbname;
				} else {
					$database = $publication[0]['pub_schema_name'];
				}
				$sql_query = WPDA::substitute_environment_vars( $sql_query ); // Allow environment vars in custom query.
				$query     = 'select `wpda_derived_table`.* from (' . $sql_query . ') as `wpda_derived_table`';

				$response            = WPDA_Publisher_Model::get_temporary_table_from_custom_query( $database, $query );
				$publication_columns = [];
				$columns_labels      = [];
				$column_array_clean  = [];
				$pub_cpt_labels      = [];
				if ( $is_cpt ) {
					try {
						$pub_cpt_format_json = json_decode( $publication[0]['pub_cpt_format'], true );
						if ( isset( $pub_cpt_format_json['cpt_format']['cpt_labels'] ) ) {
							$pub_cpt_labels = $pub_cpt_format_json['cpt_format']['cpt_labels'];
						}
					}catch ( \Exception $e ) {
						$pub_cpt_labels = [];
					}
				}

				if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
					foreach ( $response['data'] as $col ) {
						$publication_columns[] = [
							'column_name' => $col['Field'],
							'data_type'   => WPDA::get_type( $col['Type'] ),
						];
						$columns_labels[$col['Field']] = $col['Field'];
						$column_array_clean[] = $col['Field'];

						if ($is_cpt) {
							// Change labels for CPTs.
							if ( isset( $pub_cpt_labels[ $col['Field'] ] ) ) {
								$columns_labels[ $pub_cpt_labels[ $col['Field'] ] ] = $col['Field'];
							}
						}
					}
				}

				$wpdadb   = WPDADB::get_db_connection( $database );
				if ( null === $wpdadb ) {
					$this->create_empty_response( 'Invalid connection' );
					wp_die(); // Remote database not available.
				}

				$wpdadb->suppress_errors( true );

				$where = ''; // Init where clause.
				if ( '' !== isset( $_REQUEST['search']['value'] ) ) {
					$search_value  = sanitize_text_field( wp_unslash( $_REQUEST['search']['value'] ) ); // input var okay.
					$where_columns = WPDA::construct_where_clause(
						$database,
						'wpda_derived_table',
						$publication_columns,
						$search_value
					);

					if ( '' !== $where_columns ) {
						$where = " where $where_columns ";
					}
				}

				// Add Query Builder support.
				$sb_where = $this->qb( $columns_labels );
				if ( '' !== $sb_where ) {
					if ( strpos( $where, '1=3' ) !== false ) {
						$where = '';
					}
					$where .= ( '' === $where ? ' WHERE ' : ' AND ' ) . $sb_where;
				}

				$orderby = $this->get_orderby_from_request(); // Get order by.

				// Add search pane values.
				if ( $has_sp ) {
					if ( isset( $_REQUEST['searchPanes'] ) && is_array( $_REQUEST['searchPanes'] ) ) {
						// Add search panes.
						foreach ( $_REQUEST['searchPanes'] as $key => $search_pane ) {
							if ( is_array( $search_pane ) ) {
								// Add search pane.
								$where .= '' === $where ? ' where ( ' : ' and ( ';
								foreach ( $search_pane as $index => $value ) {
									// Add search pane value.
									$where .=
										$wpdadb->prepare(
											( $index > 0 ? 'or ' : '' ) . " `{$column_array_clean[ $key ]}` = %s ",
											sanitize_text_field( wp_unslash( $search_pane[ $index ] ) )
										);
								}
								$where .= ')';
							}
						}
					}
				}

				$query_original = 'select `wpda_derived_table`.* from (' . $sql_query . ') as `wpda_derived_table` ';
				$query          = $query_original . $where . $orderby;
				if ( -1 != $limit ) {
					$query .= " limit $limit offset $offset";
				}

				$rows = $wpdadb->get_results( $query, 'ARRAY_N' ); // phpcs:ignore Standard.Category.SniffName.ErrorCode
				if ( '' !== $wpdadb->last_error ) {
					$this->create_empty_response(
						$wpdadb->last_error,
						array(
							'query'   => $query,
							'columns' => $publication_columns,
							'labels'  => $columns_labels,
						)
					);
					wp_die();
				}

				if ( ! $this->serverSide ) {
					$records_filtered = $wpdadb->num_rows;
				} else {
					$count_rows       = $wpdadb->get_results( 'select 1 from (' . $sql_query . ') as `wpda_derived_table` ' . $where );
					$records_filtered = count( $count_rows );
				}

				$obj                  = (object) null;
				$obj->draw            = isset( $_REQUEST['draw'] ) ? intval( $_REQUEST['draw'] ) : 0;
				$obj->recordsTotal    = intval( $wpdadb->num_rows );
				$obj->recordsFiltered = $records_filtered;
				$obj->data            = $rows;
				$obj->error           = $wpdadb->last_error;
				if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
					if ( isset( $_REQUEST['draw'] ) &&  1 === intval( $_REQUEST['draw'] ) ) {
						// Add search pane content to first draw only.
						if (
							$has_sp &&
							(
								null === $json ||
								! isset( $json->serverSide ) ||
								true === $json->serverSide
							)
						) {
							// Add search panes.
							$obj->searchPanes = $this->sp( $wpdadb, " ($query_original) as `t` ", $column_array_clean, $sp_columns, $where, '' );
						}
					}
				}

				if ( 'on' === WPDA::get_option( WPDA::OPTION_PLUGIN_DEBUG ) ) {
					$obj->debug = array(
						'query'   		=> $query,
						'where'   		=> $where_columns,
						'orderby' 		=> $orderby,
						'columns' 		=> $publication_columns,
						'labels'  		=> $columns_labels,
						'serverSide'	=> $this->serverSide,
					);
				}

				echo wp_json_encode( $obj );
				wp_die();
			}
		}

		private function get_orderby_from_request() {
			$orderby = ''; // Init order by.
			if ( isset( $_REQUEST['order'] ) && is_array( $_REQUEST['order'] ) ) { // input var okay.
				foreach ( $_REQUEST['order'] as $order_column ) {  // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
					if (
						isset( $order_column['column'], $order_column['dir'] ) &&
						is_numeric( $order_column['column'] ) &&
						(
							'asc'=== $order_column['dir'] ||
							'desc'=== $order_column['dir']
						)
					) {
						$preprend = '' === $orderby ? ' order by ' : ',';
						$orderby  .= $preprend . ( intval( $order_column['column'] ) + 1 ) . ' ' . $order_column['dir'];
					}
				}
			}
			return $orderby;
		}

		private function sp( $wpdadb, $table_name, $columns, $panes, $where, $bt = '`' ) {
			$sp = [];

			// Add Search Panes support.
			// This feature is implemented in the premium version.
			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				if ( is_array( $panes ) ) {
					foreach ( $panes as $column_id ) {
						if ( is_numeric( $column_id ) ) {
							// Add pane.
							$sp['options'][ $column_id ] = [];
							$column_name                 = $columns[ $column_id ];
							$column_values               = $wpdadb->get_results(
								"select `{$column_name}`, count(*) from {$bt}{$table_name}{$bt} {$where} group by 1",
								'ARRAY_N'
							);
							if ( '' === $wpdadb->last_error ) {
								// Add pane values.
								foreach ( $column_values as $value ) {
									$sp['options'][ $column_id ][] = [
										'label' => $value[0],
										'total' => $value[1],
										'value' => $value[0],
										'count' => $value[1],
									];
								}
							}
						}
					}
				}

				if ( 0 === count( $sp ) ) {
					// No data: force remove all panes.
					$sp['options'][ -1 ] = [];
				}
			}

			return $sp;
		}

		public function qb_group( $data ) {
			// Add Query Builder support.
			// This feature is implemented in the premium version.
			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				$is_first = true;
				$sql      = '';
				foreach ( $data['criteria'] as $crit ) {
					if ( isset( $crit['criteria'] ) ) {
						// Process group
						$sql_part = $this->qb_group( $crit );
						$sql     .= '' === $sql_part ? '' : ( ( $is_first || '' === $sql ) ? '' : $data['logic'] ) . " ({$sql_part}) ";
					} else {
						// Process criteria
						if (
							isset( $crit['condition'] ) &&
							(
								isset( $crit['value'] ) ||
								$crit['condition'] === 'null' ||
								$crit['condition'] === '!null'
							)
						) {
							$sql_part = $this->qb_criteria( $crit );
							$sql     .= '' === $sql_part ? '' : ( ( $is_first || '' === $sql ) ? '' : $data['logic'] ) . " ({$sql_part}) ";
						}
					}
					$is_first = false;
				}
				return '' === $sql ? '' : "($sql)";
			}
		}

		public function qb_criteria( $crit ) {
			// Add Query Builder support.
			// This feature is implemented in the premium version.
			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				global $wpdb;

				$val1        = isset( $crit['value'][0] ) ? $crit['value'][0] : '';
				$val2        = isset( $crit['value'][1] ) ? $crit['value'][1] : '';
				$condition   = $crit['condition'];
				if ( isset( $crit['origData'] ) ) {
					$column_name = $crit['origData'];
				} else {
					$column_name = $this->column_labels[ $crit['data'] ];
				}

				if ( strlen( $val1 ) === 0 && $condition !== 'null' && $condition !== '!null' ) {
					return '';
				}
				if ( strlen( $val2 ) === 0 && ( $condition === 'between' || $condition === '!between' ) ) {
					return '';
				}

				switch ( $condition ) {
					case '=':
					case '!=':
					case '<':
					case '<=':
					case '>=':
					case '>':
						if ( 'num' === $crit['type'] ) {
							return $wpdb->prepare(
								'`%1s` %1s %d', // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
								array(
									WPDA::remove_backticks( $column_name ),
									$condition,
									$val1,
								)
							);
						} else {
							return $wpdb->prepare(
								'`%1s` %1s %s', // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
								array(
									WPDA::remove_backticks( $column_name ),
									$condition,
									$val1,
								)
							);
						}
					case 'starts':
					case 'contains':
					case 'ends':
						return $wpdb->prepare(
							'`%1s` LIKE %s ', // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
							array(
								WPDA::remove_backticks( $column_name ),
								( 'starts' === $condition ? '' : '%' ) .
								$val1 .
								( 'ends' === $condition ? '' : '%' ),
							)
						);
					case 'between':
					case '!between':
						if ( 'num' === $crit['type'] ) {
							return $wpdb->prepare(
								'`%1s` %1s %d AND %d', // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
								array(
									WPDA::remove_backticks( $column_name ),
									$condition,
									$val1,
									$val2,
								)
							);
						} else {
							return $wpdb->prepare(
								'`%1s` %1s %s AND %s', // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
								array(
									WPDA::remove_backticks( $column_name ),
									$condition,
									$val1,
									$val2,
								)
							);
						}
					case 'null':
						return "`{$column_name}` is null";
					case '!null':
						return "`{$column_name}` is not null";
				}
			}
		}

		private function qb( $labels ) {
			// Add Query Builder support.
			// This feature is implemented in the premium version.
			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				// Taken from jQuery DataTables function _constructSearchBuilderConditions($query, $data).
				if ( ! isset( $_POST['searchBuilder'] ) ) {
					return '';
				}

				// Set column labels.
				$this->column_labels = $labels;

				// Sanitize and process searchbuilder array.
				$searchbuilder = WPDA::sanitize_text_field_array( $_POST['searchBuilder'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				return $this->qb_group( $searchbuilder );
			}
		}

		private function create_empty_response( $error = '', $debug = '' ) {
			$obj                  = (object) null;
			$obj->draw            = 0;
			$obj->recordsTotal    = 0;
			$obj->recordsFiltered = 0;
			$obj->data            = array();
			$obj->error           = $error;
			$obj->debug           = $debug;

			echo json_encode( $obj );
		}

	}

}
