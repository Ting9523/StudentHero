<?php

/**
 * Suppress "error - 0 - No summary was found for this file" on phpdoc generation
 *
 * @package WPDataAccess\Data_Publisher
 */

namespace WPDataAccess\Data_Publisher {

	use WPDataAccess\Data_Dictionary\WPDA_Dictionary_Lists;
	use WPDataAccess\Data_Dictionary\WPDA_List_Columns_Cache;
	use WPDataAccess\Data_Tables\WPDA_Data_Tables;
	use WPDataAccess\Plugin_Table_Models\WPDA_Table_Settings_Model;
	use WPDataAccess\Plugin_Table_Models\WPDA_Publisher_Model;
	use WPDataAccess\Premium\WPDAPRO_CPT\WPDAPRO_CPT_Services;
	use WPDataAccess\Simple_Form\WPDA_Simple_Form;
	use WPDataAccess\Simple_Form\WPDA_Simple_Form_Item_Boolean;
	use WPDataAccess\Simple_Form\WPDA_Simple_Form_Item_Enum;
	use WPDataAccess\Simple_Form\WPDA_Simple_Form_Item_Enum_Radio;
	use WPDataAccess\WPDA;
	use function GuzzleHttp\Psr7\_caseless_remove;

	/**
	 * Class WPDA_Publisher_Form extends WPDA_Simple_Form
	 *
	 * Data entry form which allows users to create, update and test publications. A publication consists of a database
	 * table, a number of columns and some options. A shortcode can be generated for a publication. The shortcode can
	 * be copied to the clipboard and from there pasted in a WordPress post or page. The shortcode is used to add a
	 * dynamic HTML table to a post or page that supports searching, pagination and sorting. Tables are created with
	 * jQuery DataTables.
	 *
	 * @author  Peter Schulz
	 * @since   2.0.15
	 */
	class WPDA_Publisher_Form extends WPDA_Simple_Form {

		protected $hyperlinks          = array();
		protected $color               = array();
		protected $databases           = array();
		protected $cpts                = [];
		protected $cpts_non_selectable = [];
		protected $cpts_selected       = [];
		protected $cfds                = [];
		protected $cfds_selectable     = [];
		protected $cfds_hidden         = [];
		protected $pub_data_source     = 'Table';

		/**
		 * WPDA_Publisher_Form constructor.
		 *
		 * @param string $schema_name Database schema name
		 * @param string $table_name Database table name
		 * @param object $wpda_list_columns Handle to instance of WPDA_List_Columns
		 * @param array  $args
		 */
		public function __construct( $schema_name, $table_name, &$wpda_list_columns, $args = array() ) {
			// Add column labels.
			$args['column_headers'] = array(
				'pub_id'                          => __( 'Publication ID', 'wp-data-accesss' ),
				'pub_name'                        => __( 'Publication Name', 'wp-data-accesss' ),
				'pub_schema_name'                 => __( 'Database', 'wp-data-access' ),
				'pub_data_source'                 => __( '', 'wp-data-access' ),
				'pub_table_name'                  => __( 'Table/View Name', 'wp-data-accesss' ),
				'pub_column_names'                => __( 'Column Names (* = all)', 'wp-data-accesss' ),
				'pub_format'                      => __( 'Column Labels', 'wp-data-accesss' ),
				'pub_query'                       => __( '* Query', 'wp-data-access' ),
				'pub_sort_icons'                  => __( 'Sort Icons', 'wp-data-access' ),
				'pub_styles'                      => __( 'Styling', 'wp-data-access' ),
				'pub_style_premium'               => __( 'Enable Premium Styling', 'wp-data-access' ),
				'pub_style_color'                 => __( 'Color', 'wp-data-access' ),
				'pub_style_space'                 => __( 'Spacing', 'wp-data-access' ),
				'pub_style_corner'                => __( 'Corner Radius', 'wp-data-access' ),
				'pub_style_modal_width'           => __( 'Modal Width', 'wp-data-access' ),
				'pub_responsive'                  => __( 'Output', 'wp-data-accesss' ),
				'pub_responsive_popup_title'      => __( 'Popup Title', 'wp-data-accesss' ),
				'pub_responsive_cols'             => __( 'Number Of Columns', 'wp-data-accesss' ),
				'pub_responsive_type'             => __( 'Type', 'wp-data-accesss' ),
				'pub_responsive_modal_hyperlinks' => __( 'Hyperlinks On Modal', 'wp-data-access' ),
				'pub_responsive_icon'             => __( 'Show Icon', 'wp-data-accesss' ),
				'pub_default_where'               => __( 'WHERE Clause', 'wp-data-access' ),
				'pub_default_orderby'             => __( 'Default Order By', 'wp-data-access' ),
				'pub_table_options_searching'     => __( 'Allow Searching?', 'wp-data-access' ),
				'pub_table_options_ordering'      => __( 'Allow Ordering?', 'wp-data-access' ),
				'pub_table_options_paging'        => __( 'Allow Paging?', 'wp-data-access' ),
				'pub_table_options_serverside'    => __( 'Server Side Processing?', 'wp-data-access' ),
				'pub_table_options_nl2br'         => __( 'NL > BR', 'wp-data-access' ),
				'pub_table_options_advanced'      => __( 'Advanced Options', 'wp-data-access' ),
				'pub_extentions'                  => __( 'Publication Extentions', 'wp-data-access' ),
				'pub_cpt'                         => __( 'Post type', 'wp-data-access' ),
				'pub_cpt_fields'				  => __( 'Custom fields', 'wp-data-access' ),
				'pub_cpt_query'					  => __( 'CPT query', 'wp-data-access' ),
				'pub_cpt_format'				  => __( 'Field labels', 'wp-data-access' ),
			);

			$this->check_table_type = false;
			$this->title            = 'Data Publisher';
			$args['help_url']       = 'https://wpdataaccess.com/docs/documentation/data-publisher/data-publisher-getting-started/';
			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				$this->title = 'Premium ' . $this->title;
				$this->color = \WPDataAccess\Premium\WPDAPRO_Templates\WPDAPRO_Template_Data_Publisher_Color::COLORS;
			}

			parent::__construct( $schema_name, $table_name, $wpda_list_columns, $args );

			// Get available databases.
			$schema_names = WPDA_Dictionary_Lists::get_db_schemas();
			foreach ( $schema_names as $schema_name ) {
				array_push( $this->databases, $schema_name['schema_name'] );
			}

			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				// Get post types.
				$this->cpts                = WPDA::get_post_types();
				$this->cpts_non_selectable = WPDAPRO_CPT_Services::get_non_selectable_cpts();
				$this->cpts_selected       = array_values( array_diff( $this->cpts, $this->cpts_non_selectable ) );
			}

			// Add scripts and styles.
			WPDA_Data_Tables::enqueue_styles_and_script();
		}

		/**
		 * Overwrites method add_buttons
		 */
		public function add_buttons() {
			$index       = $this->get_item_index( 'pub_id' );
			$pub_id_item = $this->form_items[ $index ];
			$pub_id      = $pub_id_item->get_item_value();
			$disabled    = 'new' === $this->action ? 'disabled' : '';
			?>
			<a href="javascript:void(0)"
			   onclick="test_publication()"
			   class="button wpda_tooltip <?php echo esc_attr( $disabled ); ?>"
			   title="Test publication"
			>
				<i class="fas fa-bug wpda_icon_on_button"></i>
				<?php echo __( 'Test', 'wp-data-access' ); ?>
			</a>
			<?php
			$this->show_shortcode( $pub_id );
		}

		/**
		 * Overwrites method prepare_items
		 *
		 * @param bool $set_back_form_values
		 */
		public function prepare_items( $set_back_form_values = false ) {
			parent::prepare_items( $set_back_form_values );

			$i = 0;
			foreach ( $this->form_items as $form_item ) {
				// Prepare listbox for column pub_schema_name
				if ( $form_item->get_item_name() === 'pub_schema_name' ) {
					if ( '' === $form_item->get_item_value() || null === $form_item->get_item_value() ) {
						$form_item->set_item_value( WPDA::get_user_default_scheme() );
					}
					$form_item->set_enum( $this->databases );
					$this->form_items[ $i ] = new WPDA_Simple_Form_Item_Enum( $form_item );
				}

				if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
					// Prepare radio group for column pub_data_source
					if ( $form_item->get_item_name() === 'pub_data_source' ) {
						$this->form_items[ $i ]->set_enum_text( array( 'Table or view', 'Custom query', 'Custom post type' ) );
						$this->form_items[ $i ]->set_item_default_value( 'Table' );
						$this->form_items[ $i ] = new WPDA_Simple_Form_Item_Enum_Radio( $form_item );
					}
				}

				if ( $form_item->get_item_name() === 'pub_sort_icons' ) {
					$form_item->set_enum( array( 'Show', 'Hide' ) );
					$form_item->set_enum_options( array( 'default', 'none' ) );
				}

				if ( $form_item->get_item_name() === 'pub_data_source' ) {
					if ( null !== $form_item->get_item_value() ) {
						$this->pub_data_source = $form_item->get_item_value();
					}
				}

				// Prepare listbox for column pub_table_name
				if ( $form_item->get_item_name() === 'pub_table_name' ) {
					if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
						$form_item->set_hide_item_init( true );
					}
					$this->form_items[ $i ] = new WPDA_Simple_Form_Item_Enum( $form_item );
				}

				// Set default value for popup title.
				if ( $form_item->get_item_name() === 'pub_responsive_popup_title' ) {
					$form_item->set_item_default_value( __( 'Row details', 'wp-data-access' ) );
				}

				// Prepare listbox for column pub_responsive.
				if ( $form_item->get_item_name() === 'pub_responsive' ) {
					$form_item->set_enum( array( 'Responsive', 'Flat' ) );
					$form_item->set_enum_options( array( 'Yes', 'No' ) );
				}

				// Prepare selection for column pub_column_names.
				if ( $form_item->get_item_name() === 'pub_column_names' ) {
					$title = __( 'Select columns shown in publication', 'wp-data-access' );
					$form_item->set_item_hide_icon( true );
					if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
						$form_item->set_hide_item_init( true );
					}
					$form_item->set_item_js(
						'jQuery("#pub_column_names").parent().parent().find("td.icon").append("<a id=\'select_columns\' class=\'button wpda_tooltip\' href=\'javascript:void(0)\' title=\'' . $title . '\' onclick=\'select_columns()\'>' .
						'<i class=\'fas fa-list wpda_icon_on_button\'></i> ' . __( 'Select', 'wp-data-access' ) .
						'</a>");'
					);
				}

				// Prepare column label settings.
				if ( $form_item->get_item_name() === 'pub_format' ) {
					$title = __( 'Define columns for publication (not necessary if already defined in Data Explorer table settings)', 'wp-data-access' );
					$form_item->set_item_hide_icon( true );
					if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
						$form_item->set_hide_item_init( true );
					}
					$form_item->set_item_class( 'hide_item' );
					$form_item->set_item_js(
						'jQuery("#pub_format").parent().parent().find("td.data").append("<a id=\'format_columns\' class=\'button wpda_tooltip\' href=\'javascript:void(0)\' title=\'' . $title . '\' onclick=\'format_columns()\'>' .
						'<i class=\'fas fa-tag wpda_icon_on_button\'></i> ' . __( 'Click to define column labels', 'wp-data-access' ) .
						'</a>");'
					);
				}

				if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
					// Prepare SQL query field.
					if ( $form_item->get_item_name() === 'pub_query' ) {
						$title = __( 'Get SQL from Query Builder', 'wp-data-access' );
						$form_item->set_item_hide_icon( true );
						$form_item->set_hide_item_init( true );
						$form_item->set_item_js(
							'jQuery("#pub_query").parent().parent().find("td.icon").append("<a class=\'button wpda_tooltip\' href=\'javascript:void(0)\' title=\'' . $title . '\' onclick=\'getQuery()\'>' .
							'<i class=\'fas fa-tools wpda_icon_on_button\'></i> Query Builder' .
							'</a>");'
						);
					}
				}

				if (
					'pub_responsive_popup_title' === $form_item->get_item_name() ||
					'pub_responsive_cols' === $form_item->get_item_name() ||
					'pub_responsive_type' === $form_item->get_item_name() ||
					'pub_responsive_modal_hyperlinks' === $form_item->get_item_name() ||
					'pub_responsive_icon' === $form_item->get_item_name()
				) {
					$form_item->set_hide_item_init( true );
				}

				if ( 'pub_table_options_advanced' === $form_item->get_item_name() ) {
					if ( '' === $form_item->get_item_value() || null === $form_item->get_item_value() ) {
						$form_item->set_item_value( '{}' );
					}
				}

				if (
					'pub_table_options_searching' === $form_item->get_item_name() ||
					'pub_table_options_ordering' === $form_item->get_item_name() ||
					'pub_table_options_paging' === $form_item->get_item_name() ||
					'pub_table_options_serverside' === $form_item->get_item_name()
				) {
					if ( 'pub_table_options_searching' !== $form_item->get_item_name() ) {
						$form_item->set_hide_item_init( true );
					}
					$form_item->checkbox_value_on = 'on';
					if ( 'new' === $this->action ) {
						$form_item->set_item_value( 'on' );
					}
					$this->form_items[ $i ] = new WPDA_Simple_Form_Item_Boolean( $form_item );
				}

				if ( 'pub_table_options_nl2br' === $form_item->get_item_name() ) {
					$form_item->set_hide_item_init( true );
					$form_item->checkbox_value_on = 'on';
					$this->form_items[ $i ]       = new WPDA_Simple_Form_Item_Boolean( $form_item );
				}

				if ( $form_item->get_item_name() === 'pub_styles' ) {
					$options          = $form_item->get_item_enum();
					$option_values    = $options;
					$option_values[0] = 'default = stripe + hover + order-column + row-border';
					$form_item->set_enum( $option_values );
					$form_item->set_enum_options( $options );
				}

				if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
					if ( $form_item->get_item_name() === 'pub_style_color' ) {
						$form_item->set_item_hide_icon( true );
						if ( null === $form_item->get_item_value() || '' === $form_item->get_item_value() ) {
							$form_item->set_item_value( 'default' );
						}
					}
					if ( $form_item->get_item_name() === 'pub_style_space' ) {
						$form_item->set_item_hide_icon( true );
						if ( null === $form_item->get_item_value() || '' === $form_item->get_item_value() ) {
							$form_item->set_item_value( 0 );
						}
					}
					if ( $form_item->get_item_name() === 'pub_style_corner' ) {
						$form_item->set_item_hide_icon( true );
						if ( null === $form_item->get_item_value() || '' === $form_item->get_item_value() ) {
							$form_item->set_item_value( 0 );
						}
					}
					if ( $form_item->get_item_name() === 'pub_style_modal_width' ) {
						$form_item->set_item_hide_icon( true );
						if ( null === $form_item->get_item_value() || '' === $form_item->get_item_value() ) {
							$form_item->set_item_value( 80 );
						}
					}
					if (
						'pub_default_where' === $form_item->get_item_name() ||
						'pub_default_orderby' === $form_item->get_item_name() ||
						'pub_extentions' === $form_item->get_item_name() ||
						'pub_cpt_query' === $form_item->get_item_name()
					) {
						$form_item->set_hide_item_init( true );
					}

					if ( 'pub_cpt' === $form_item->get_item_name() ) {
						$form_item->set_hide_item_init( true );
						$form_item->set_enum( $this->cpts_selected );
						$this->form_items[ $i ] = new WPDA_Simple_Form_Item_Enum( $form_item );
					}

					if ( 'pub_cpt_fields' === $form_item->get_item_name() ) {
						$form_item->set_hide_item_init( true );
						$form_item->set_item_hide_icon( true );

						if ( null === $form_item->get_item_value() || '' === $form_item->get_item_value() ) {
							$this->init_custom_fields();
						} else {
							try {
								$pub_cpt_fields = json_decode( $form_item->get_item_value(), true );
								if ( ! isset(
									$pub_cpt_fields['selected'],
									$pub_cpt_fields['selectable'],
									$pub_cpt_fields['hidden']
								) ) {
									throw new \Exception('Invalid JSON');
								}

								$this->cfds            = $pub_cpt_fields['selected'];
								$this->cfds_selectable = $pub_cpt_fields['selectable'];
								$this->cfds_hidden     = $pub_cpt_fields['hidden'];
							} catch ( \Exception $e ) {
								$this->init_custom_fields();
							}
						}
					}

					if ( 'pub_cpt_format' === $form_item->get_item_name() ) {
						$title = __( 'Define field labels for selected custom fields', 'wp-data-access' );
						$form_item->set_hide_item_init( true );
						$form_item->set_item_hide_icon( true );
						$form_item->set_item_js(
							'jQuery("#pub_cpt_format").closest("tr").find("td.data").append("<a class=\'button wpda_tooltip\' href=\'javascript:void(0)\' title=\'' . $title . '\' onclick=\'format_cpt_columns()\'>' .
							'<i class=\'fas fa-tag wpda_icon_on_button\'></i> ' . __( 'Click to define field labels', 'wp-data-access' ) .
							'</a>");'
						);
					}
				}

				if ( 'pub_default_orderby' === $form_item->get_item_name() ) {
					$form_item->set_item_hide_icon( true );
				}

				$i ++;
			}
		}

		protected function init_custom_fields() {
			// This method is implemented in the premium version.
			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				$pub_cpt = $this->form_items[ $this->get_item_index( 'pub_cpt' ) ]->get_item_value();
				if ( null === $pub_cpt || '' === $pub_cpt ) {
					if ( is_array( $this->cpts_selected ) && count( $this->cpts_selected ) > 0 ) {
						$pub_cpt = $this->cpts_selected[0];
					}
				}

				$this->cfds            = [];
				$this->cfds_selectable = array_merge(
					WPDAPRO_CPT_Services::CPTS_CUSTOM_FIELDS_SELECTABLE,
					WPDAPRO_CPT_Services::get_custom_fields( $pub_cpt )
				);
				$this->cfds_hidden     = WPDAPRO_CPT_Services::CPTS_CUSTOM_FIELDS_HIDDEN;

				// Write JSON to custom field column for javascript processing.
				$this->form_items[ $this->get_item_index( 'pub_cpt_fields' ) ]->set_item_value(
					json_encode( [
						'selected'   => $this->cfds,
						'selectable' => $this->cfds_selectable,
						'hidden'     => $this->cfds_hidden,
					] )
				);
			}
		}

		protected function add_fieldsets() {
			$fields = array();
			foreach ( $this->form_items as $item ) {
				$fields[ $item->get_item_name() ] = true;
			}
			$data = array(
				'pub_id',
				'pub_name',
				'pub_schema_name',
				'pub_table_name',
				'pub_column_names',
				'pub_format',
			);
			$styling = array(
				'pub_sort_icons',
				'pub_styles',
			);
			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				$data = array_merge(
					array_slice( $data, 0, 2 ),
					array( 'pub_data_source' ),
					array_slice( $data, 2, 4 ),
					array( 'pub_query', 'pub_cpt', 'pub_cpt_fields', 'pub_cpt_query', 'pub_cpt_format' )
				);
				$styling = array_merge(
					$styling,
					array(
						'pub_style_premium',
						'pub_style_color',
						'pub_style_space',
						'pub_style_corner',
						'pub_style_modal_width',
					)
				);
			}
			$this->fieldsets = array(
				'Publication Data'    => array(
					'id'     => 'pub_main',
					'fields' => $data,
				),
				'Publication Type'    => array(
					'id'         => 'pub_type',
					'fields'     => array(
						'pub_responsive',
						'pub_responsive_popup_title',
						'pub_responsive_cols',
						'pub_responsive_type',
						'pub_responsive_modal_hyperlinks',
						'pub_responsive_icon',
					),
					'expandable' => true,
				),
				'Publication Styling' => array(
					'id'         => 'pub_styling',
					'fields'     => $styling,
					'expandable' => true,
				),
			);
			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				$this->fieldsets['Premium Extentions'] = array(
					'id'         => 'pub_extent',
					'fields'     => array(
						'pub_extentions',
					),
					'expandable' => true,
				);
			}
			$this->fieldsets['Advanced Settings'] = array(
				'id'         => 'pub_advanced',
				'fields'     => array(
					'pub_default_where',
					'pub_default_orderby',
					'pub_table_options_searching',
					'pub_table_options_ordering',
					'pub_table_options_paging',
					'pub_table_options_serverside',
					'pub_table_options_nl2br',
					'pub_table_options_advanced',
				),
				'expandable' => true,
			);
		}

		/**
		 * Overwrites method show
		 *
		 * @param bool   $allow_save
		 * @param string $add_param
		 */
		public function show( $allow_save = true, $add_param = '' ) {
			parent::show( $allow_save, $add_param );

			$index       = $this->get_item_index( 'pub_id' );
			$pub_id_item = $this->form_items[ $index ];
			$pub_id      = $pub_id_item->get_item_value();

			$index            = $this->get_item_index( 'pub_schema_name' );
			$schema_name_item = $this->form_items[ $index ];
			$schema_name      = $schema_name_item->get_item_value();

			global $wpdb;
			$wpdb_name = $wpdb->dbname;

			$index           = $this->get_item_index( 'pub_table_name' );
			$table_name_item = $this->form_items[ $index ];
			$table_name      = $table_name_item->get_item_value();

			$table_columns = WPDA_List_Columns_Cache::get_list_columns( $schema_name, $table_name );
			$columns       = array();
			foreach ( $table_columns->get_table_columns() as $table_column ) {
				array_push( $columns, $table_column['column_name'] );
			}

			$column_labels = $table_columns->get_table_column_headers();

			$json_editing = WPDA::get_option( WPDA::OPTION_DP_JSON_EDITING );

			$wpda_table_settings_db = WPDA_Table_Settings_Model::query( $table_name, $schema_name );
			if ( isset( $wpda_table_settings_db[0]['wpda_table_settings'] ) ) {
				$wpda_table_settings = json_decode( $wpda_table_settings_db[0]['wpda_table_settings'] );
				if ( isset( $wpda_table_settings->hyperlinks ) ) {
					foreach ( $wpda_table_settings->hyperlinks as $hyperlink ) {
						$hyperlink_label = isset( $hyperlink->hyperlink_label ) ? $hyperlink->hyperlink_label : '';
						$hyperlink_html  = isset( $hyperlink->hyperlink_html ) ? $hyperlink->hyperlink_html : '';
						if ( $hyperlink_label !== '' && $hyperlink_html !== '' ) {
							array_push( $this->hyperlinks, $hyperlink_label );
						}
					}
				}
			}

			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				$index              = $this->get_item_index( 'pub_cpt_query' );
				$pub_cpt_query_item = $this->form_items[ $index ];
				$pub_cpt_query      = $pub_cpt_query_item->get_item_value();
				$cpt_columns        = WPDA_Publisher_Model::get_temporary_table_from_custom_query( $wpdb_name, $pub_cpt_query );
				$cpt_fields         = [];
				if ( isset( $cpt_columns['data'] ) && is_array( $cpt_columns['data'] ) ) {
					foreach ( $cpt_columns['data'] as $cpt_column ) {
						if ( isset( $cpt_column['Field'] ) ) {
							array_push( $cpt_fields, $cpt_column['Field'] );
						}
					}
				}
			}
			?>
			<style>
                #pub_cpt_fields,
				#pub_cpt_format,
				#pub_default_orderby {
                    display: none;
                }
                table.wpda_simple_table td.icon a.button.wpda_tooltip {
                    width: 120px;
					text-align: center;
                }
                span.pub_premium {
                    line-height: 40px;
                }
                span.pub_buttons {
                    float: right;
                    margin-top: 5px;
                    margin-bottom: 5px;
                }
                div.multiselect_sortable_content {
                    margin: 0 0 0 6px;
                }
                div.selection div.selection_title,
                div.selectable div.selectable_title {
                    font-weight: bold;
                }
                div.selection ul.selection_content,
                div.selectable ul.selectable_content {
                    background-color: white;
                    border: 1px solid #8c8f94;
                    border-radius: 4px;
                    height: 120px;
                }
                div.selection ul.selection_content li,
                div.selectable ul.selectable_content li {
                    cursor: pointer;
                    padding: 0 10px;
                    margin: 0;
                    border: 0;
                    user-select: none;
                }
                div.selection ul.selection_content li:hover,
                div.selectable ul.selectable_content li:hover {
                    font-weight: bold;
                    background-color: lightgrey;
                }
                select.multiselect_sortable_hide {
                    display: none;
                }
				.pub-post-types {
                    padding-top: 20px !important;
				}
			</style>
			<script type='text/javascript'>
				let wpda_qb_columns = [];
				let wpda_sp_columns = [];

				let cpts_all = <?php echo wp_json_encode( $this->cpts ); ?>;
				let cpts_non_selectable = <?php echo wp_json_encode( $this->cpts_non_selectable ); ?>;

				let cfds_selected = <?php echo wp_json_encode( $this->cfds ); ?>;
				let cfds_selection = <?php echo wp_json_encode( $this->cfds_selectable ); ?>;
				let cfds_hidden = <?php echo wp_json_encode( $this->cfds_hidden ); ?>;
				let cfds_default_selectable = [];
				let cfds_default_hidden = [];

				function set_responsive_columns() {
					if (jQuery('#pub_responsive').val() == 'Yes') {
						// Show responsive settings
						jQuery('#pub_responsive_popup_title').parent().parent().show();
						jQuery('#pub_responsive_cols').parent().parent().show();
						jQuery('#pub_responsive_type').parent().parent().show();
						jQuery('#pub_responsive_modal_hyperlinks').parent().parent().show();
						jQuery('#pub_responsive_icon').parent().parent().show();
					} else {
						// Hide responsive settings
						jQuery('#pub_responsive_popup_title').parent().parent().hide();
						jQuery('#pub_responsive_cols').parent().parent().hide();
						jQuery('#pub_responsive_type').parent().parent().hide();
						jQuery('#pub_responsive_modal_hyperlinks').parent().parent().hide();
						jQuery('#pub_responsive_icon').parent().parent().hide();
					}
				}

				function get_selected_columns() {
					let selectedColumns = table_columns;
					let dataSource = "<?php echo esc_attr( $this->pub_data_source ); ?>";
					if (dataSource==="CPT") {
						selectedColumns = cpt_fields;
					} else if (dataSource==="Table") {
						if (jQuery("#pub_column_names").val() !== "*") {
							selectedColumns = jQuery("#pub_column_names").val().split(",");
						}
					}
					return selectedColumns;
				}

				<?php
				if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				?>
				cfds_default_selectable = <?php echo wp_json_encode( WPDAPRO_CPT_Services::CPTS_CUSTOM_FIELDS_SELECTABLE ); ?>;
				cfds_default_hidden = <?php echo wp_json_encode( WPDAPRO_CPT_Services::CPTS_CUSTOM_FIELDS_HIDDEN ); ?>;

				function set_premium_styling() {
					// Add premium styling
					if (jQuery("#pub_style_premium").val() == 'Yes') {
						jQuery("#pub_style_color").prop("readonly", false).prop("disabled", false).removeClass("disabled");
						jQuery("#pub_style_space").prop("readonly", false).prop("disabled", false).removeClass("disabled");
						jQuery("#pub_style_corner").prop("readonly", false).prop("disabled", false).removeClass("disabled");
						jQuery("#pub_style_modal_width").prop("readonly", false).prop("disabled", false).removeClass("disabled");
					} else {
						jQuery("#pub_style_color").prop("readonly", true).prop("disabled", true).addClass("disabled");
						jQuery("#pub_style_space").prop("readonly", true).prop("disabled", true).addClass("disabled");
						jQuery("#pub_style_corner").prop("readonly", true).prop("disabled", true).addClass("disabled");
						jQuery("#pub_style_modal_width").prop("readonly", true).prop("disabled", true).addClass("disabled");
					}
				}

				function get_selected_columns_hint(columns) {
					selectedColumns = get_selected_columns();
					selectedColumnsHint = '';
					for (var i=0; i<columns.length; i++) {
						if (i>0) {
							selectedColumnsHint += ", ";
						}
						selectedColumnsHint += selectedColumns[columns[i]];
					}
					if (selectedColumnsHint!=="") {
						selectedColumnsHint = "(" + selectedColumnsHint + ")";
					}
					return selectedColumnsHint;
				}

				function config_search(extention) {
					if (jQuery("#pub_data_source").val()==="CPT") {
						if (!(Array.isArray(cpt_fields) && cpt_fields.length)) {
							alert("<?php echo __( 'To select columns you need to save your publication first', 'wp-data-access' ); ?>");
							return;
						}
					} else {
						if (!(Array.isArray(table_columns) && table_columns.length)) {
							alert("<?php echo __( 'To select columns you need to save your publication first', 'wp-data-access' ); ?>");
							return;
						}
					}

					let dialog = jQuery(`
						<div class="wpda_sb">
							<label for="sb_columns">Searchable columns</label>
						</div>
					`);

					let sb_columns = jQuery(
						'<select id="sb_columns" multiple="true" size="8">' +
						'</select>'
					);
					let checkArray = get_selected_columns();
					jQuery.each(checkArray, function (i, val) {
						let option = jQuery('<option></option>').attr('value', val).text(val);
						if (extention==="P") {
							// Search Panes
							if (wpda_sp_columns.indexOf(i) > -1) {
								option.attr("selected", "selected");
							}
						} else {
							// Search Builder
							if (wpda_qb_columns.indexOf(i) > -1) {
								option.attr("selected", "selected");
							}
						}
						sb_columns.append(option);
					});
					dialog.append(sb_columns);
					dialog.append(`
						<div class="wpda_sb">
							Hold CRTL to select multiple or deselect all</label>
						</div>
					`);

					jQuery(dialog).dialog(
						{
							dialogClass: 'wp-dialog no-close',
							title: 'Configure Search Builder',
							modal: true,
							autoOpen: true,
							closeOnEscape: false,
							resizable: false,
							width: 400,
							buttons: {
								"Select": function () {
									stringColumns = jQuery("#sb_columns :selected").map(function(index, elem) {
										return jQuery(elem).val();
									}).get().join();
									arrayColumns = stringColumns.split(",");
									var temp_columns = [];
									let checkArray = get_selected_columns();;
									for (let i=0; i<arrayColumns.length; i++) {
										let index = checkArray.indexOf(arrayColumns[i]);
										if (index > -1) {
											temp_columns.push(index);
										}
									}

									if (extention==="P") {
										// Search Panes
										wpda_sp_columns = temp_columns;
									} else {
										// Search Builder
										wpda_qb_columns = temp_columns;
									}

									var show_columns = "";
									for (var i=0; i<arrayColumns.length; i++) {
										if (i>0) {
											show_columns += ", ";
										}
										show_columns += arrayColumns[i];
									}
									if (show_columns!=="") {
										show_columns = "(" + show_columns + ")";
									}

									if (extention==="P") {
										// Search Panes
										jQuery("#pub_extention_searchpanes_columns").text(show_columns);
									} else {
										// Search Builder
										jQuery("#pub_extention_searchbuilder_columns").text(show_columns);
									}

									jQuery(this).dialog('destroy').remove();
								},
								"Cancel": function () {
									jQuery(this).dialog('destroy').remove();
								}
							}
						}
					);

					wpda_add_icons_to_dialog_buttons();
				}

				function set_premium_extentions() {
					// Add premium extentions
					let jsonString = jQuery("#pub_extentions").val();
					let json = {};
					try {
						json = JSON.parse(jsonString);
					} catch (e) {}

					// Set default
					let show_searchbuilder = false;
					let show_searchpanes = false;
					let show_button = false;
					let show_button_text_default = 'label';
					let show_arrange = false;

					let show_button_text_default_label = 'checked';
					let show_button_text_default_icon = '';
					let show_button_text_default_both = '';

					// Process json
					if (json.dom) {
						show_searchbuilder = json.dom.includes("Q");
						show_searchpanes = json.dom.includes("P");
						show_button = json.dom.includes("B");
					}
					if (json.arrange) {
						show_arrange = json.arrange;
					}
					if (json.button_caption) {
						show_button_text_default = json.button_caption;
					}

					let ext_button = show_button ? 'checked' : '';
					let ext_button_text = show_button ? '' : 'style="display:none"';

					switch (show_button_text_default) {
						case 'both':
							show_button_text_default_label = '';
							show_button_text_default_icon = '';
							show_button_text_default_both = 'checked';
							break;
						case 'icon':
							show_button_text_default_label = '';
							show_button_text_default_icon = 'checked';
							show_button_text_default_both = '';
							break;
						default:
							show_button_text_default_label = 'checked';
							show_button_text_default_icon = '';
							show_button_text_default_both = '';
					}
					ext_searchbuilder = show_searchbuilder ? 'checked' : '';
					ext_searchbuilder_columns = '';
					if (show_searchbuilder && json.wpda_qb_columns) {
						ext_searchbuilder_columns = get_selected_columns_hint(json.wpda_qb_columns);
					}
					ext_searchbuilder_text = show_searchbuilder ? '' : 'style="display:none"';

					ext_searchpanes = show_searchpanes ? 'checked' : '';
					ext_searchpanes_columns = "";
					if (show_searchpanes && json.wpda_sp_columns) {
						ext_searchpanes_columns = get_selected_columns_hint(json.wpda_sp_columns);
					}
					ext_searchpanes_text = show_searchpanes ? '' : 'style="display:none"';

					ext_arrange = show_arrange ? 'checked' : '';
					ext_arrange_text = show_arrange ? '' : 'style="display:none"';

					jQuery("#wpda_table_expand_pub_extent tbody").prepend(`
						<tr>
							<td class="label">
								<label>Search Builder</label>
							</td>
							<td class="data">
								<label>
									<input type="checkbox" id="pub_extention_searchbuilder" ${ext_searchbuilder} onchange="jQuery('.jdtDomQB').toggle()" />
									Add search builder
									<span id="pub_extention_searchbuilder_columns">${ext_searchbuilder_columns}</span>
								</label>
							</td>
							<td class="icon">
								<a id="config_search_builder" class="button wpda_tooltip" href="javascript:void(0)" title="Configure Search Builder" onclick="config_search('Q')">
									<i class="fas fa-cog wpda_icon_on_button"></i> Config
								</a>
							</td>
						</tr>
						<tr>
							<td class="label">
								<label>Search Panes</label>
							</td>
							<td class="data">
								<label>
									<input type="checkbox" id="pub_extention_searchpanes" ${ext_searchpanes} onchange="jQuery('.jdtDomSP').toggle()" />
									Add search panes
									<span id="pub_extention_searchpanes_columns">${ext_searchpanes_columns}</span>
								</label>
							</td>
							<td class="icon">
								<a id="config_search_panes" class="button wpda_tooltip" href="javascript:void(0)" title="Configure Search Panes" onclick="config_search('P')">
									<i class="fas fa-cog wpda_icon_on_button"></i> Config
								</a>
							</td>
						</tr>
						<tr>
							<td class="label">
								<label>Buttons</label>
							</td>
							<td class="data">
								<label>
									<input type="checkbox" id="pub_extention_buttons" ${ext_button} onchange="jQuery('.wpda-button-extention, .jdtDomB').toggle(); jdtDomButtonExpand(jQuery('#jdtDom .wpda-jdtdom-expand').parent(), true)" />
									Add buttons
								</label>
							</td>
							<td class="icon"></td>
						</tr>
						<tr ${ext_button_text} class="wpda-button-extention">
							<td class="label">
								<label>Add Buttons</label>
							</td>
							<td class="data">
								<div id="wpda_buttons">
									<label id="wpda_buttons_C" title="Add export to CSV button" class="wpda_tooltip">
										<input type="checkbox" id="pub_extention_buttons_csv" data-dom="C" />
										CSV
									</label>
									<label id="wpda_buttons_E" title="Add export to Excel button" class="wpda_tooltip">
										<input type="checkbox" id="pub_extention_buttons_excel" data-dom="E" />
										Excel
									</label>
									<label id="wpda_buttons_F" title="Add export to PDF button" class="wpda_tooltip">
										<input type="checkbox" id="pub_extention_buttons_pdf" data-dom="F" />
										PDF
									</label>
									<label id="wpda_buttons_P" title="Add Print button" class="wpda_tooltip">
										<input type="checkbox" id="pub_extention_buttons_print" data-dom="P" />
										Print
									</label>
									<label id="wpda_buttons_Y" title="Add Copy button" class="wpda_tooltip">
										<input type="checkbox" id="pub_extention_buttons_copy" data-dom="Y" />
										Copy
									</label>
									<label id="wpda_buttons_S" title="Add export to SQL button" class="wpda_tooltip">
										<input type="checkbox" id="pub_extention_buttons_sql" data-dom="S" />
										SQL
									</label>
									<label id="wpda_buttons_V" title="Toggle column visibility from a list" class="wpda_tooltip">
										<input type="checkbox" id="pub_extention_buttons_columnlist" data-dom="V" />
										Column list
									</label>
									<label id="wpda_buttons_T" title="Toggle column visibility from a button" class="wpda_tooltip">
										<input type="checkbox" id="pub_extention_buttons_columnbutton" data-dom="T" />
										Column button
									</label>
								</div>
							</td>
							<td class="icon">
								<i title="Drag and drop to change button order
Column button does not support icons" class="fas fa-circle-question pointer wpda_tooltip"></i>
							</td>
						</tr>
						<tr ${ext_button_text} class="wpda-button-extention">
							<td class="label">
								<label>Button Caption</label>
							</td>
							<td class="data">
								<div>
									<label>
										<input type="radio" name="pub_extention_buttons_text" value="label" style="width:unset" ${show_button_text_default_label} />
										Labels only
									</label>
									<label class="wpda-left-indent">
										<input type="radio" name="pub_extention_buttons_text" value="icon" style="width:unset" ${show_button_text_default_icon} />
										Icons only
									</label>
									<label class="wpda-left-indent">
										<input type="radio" name="pub_extention_buttons_text" value="both" style="width:unset" ${show_button_text_default_both} />
										Labels and icons
									</label>
								</div>
							</td>
							<td class="icon"></td>
						</tr>
						<tr>
							<td class="label" style="vertical-align:top;padding-top:7px;height:28px;">
								<label>
									<input type="checkbox" id="pub_extention_arrange" onchange="jQuery('#jdtDom, #jdtDomDefault').toggle()" ${ext_arrange} style="vertical-align:sub;" />
									Arrange
								</label>
							</td>
							<td class="data">
								<div id="jdtDom" ${ext_arrange_text}>
									<div class="jdtDom3 jdtDomQB" ${ext_searchbuilder_text} data-dom="Q">search builder</div>
									<div class="jdtDom3 jdtDomSP" ${ext_searchpanes_text} data-dom="P">search panes</div>
									<div class="jdtDom3 jdtDomB" ${ext_button_text} data-dom="B">export buttons<i class="fas fa-expand wpda-jdtdom-expand wpda_tooltip" title="Click to switch  position"></i></div>
									<div class="jdtDomPL" data-dom="l">page length</div>
									<div class="jdtDomHeader" data-dom="hdr"></div>
									<div class="jdtDomSB" data-dom="f">search box</div>
									<div class="jdtDom3 jdtDomP" data-dom="r">processing...</div>
									<div class="jdtDom3 jdtDomT" data-dom="t">table</div>
									<div class="jdtDomS" data-dom="i">summary</div>
									<div class="jdtDomFooter" data-dom="ftr"></div>
									<div class="jdtDomPA" data-dom="p">pagination</div>
								</div>
							</td>
							<td class="icon" style="vertical-align:top;padding-top:7px;">
								<i title="(1) Select elements to be displayed
(2) Drag and drop to arrange elements" class="fas fa-circle-question pointer wpda_tooltip"></i>
								<div id="jdtDomDefault" ${ext_arrange_text}>
									<label><input type="checkbox" checked onchange="toggleDomElement(jQuery(this))" data-dom="l" data-dom-elem="jdtDomPL" />page length</label><br/>
									<label><input type="checkbox" checked onchange="toggleDomElement(jQuery(this))" data-dom="f" data-dom-elem="jdtDomSB" />search box</label><br/>
									<label><input type="checkbox" checked onchange="toggleDomElement(jQuery(this))" data-dom="r" data-dom-elem="jdtDomP"  />processing...</label><br/>
									<label><input type="checkbox" checked onchange="toggleDomElement(jQuery(this))" data-dom="t" data-dom-elem="jdtDomT"  />table</label><br/>
									<label><input type="checkbox" checked onchange="toggleDomElement(jQuery(this))" data-dom="i" data-dom-elem="jdtDomS"  />summary</label><br/>
									<label><input type="checkbox" checked onchange="toggleDomElement(jQuery(this))" data-dom="p" data-dom-elem="jdtDomPA"  />pagination</label>
								</div>
							</td>
						</tr>
					`);

					if (json.dom) {
						// Reset selected dom elements
						jQuery("#jdtDomDefault input[type='checkbox']").each(function() {
							if (!json.dom.includes(jQuery(this).data("dom"))) {
								jQuery(this).prop("checked", false);
								jQuery(this).change();
							}
						});

						// Add panes if not yet available in array
						var indexSB = json.button_grid.indexOf("Q");
						if (indexSB!==-1) {
							json.button_grid.splice(indexSB+1, 0, "P");
						}

						// Reposition elements
						if (json.button_grid) {
							for (let i=json.button_grid.length; i>=0; i--) {
								jQuery("#jdtDom").prepend(jQuery("#jdtDom>div[data-dom=" + json.button_grid[i] + "]"));
							}
						}
					}

					if (json.selected_buttons) {
						// Rearrange selected buttons
						for (let i=json.selected_buttons.length+1; i>=0; i--) {
							jQuery("#wpda_buttons_" + json.selected_buttons[i] + ">input[type='checkbox']").prop("checked", true);
							jQuery("#wpda_buttons").prepend(jQuery("#wpda_buttons_" + json.selected_buttons[i]));
						}
					}

					// Correction
					if (json.button_grid && json.button_grid.length===0) {
						json.button_width = 3;
					}

					if (json.button_width && json.button_width==1) {
						jQuery("#jdtDom .jdtDomB").removeClass("jdtDom3");
						jQuery("#jdtDom .jdtDomHeader").hide();
					}

					if (json.wpda_qb_columns) {
						wpda_qb_columns = json.wpda_qb_columns;
					}

					if (json.wpda_sp_columns) {
						wpda_sp_columns = json.wpda_sp_columns;
					}

					jQuery("#wpda_buttons").sortable();
					jQuery("#jdtDom").sortable();
					jQuery(".wpda-jdtdom-expand").on("click", function() {
						jdtDomButtonExpand(jQuery(this).parent());
					});
				}

				function toggleDomElement(elem, reset=false) {
					const label = elem.parent().text();
					const className = elem.data("domElem")
					if (jQuery("." + className).text()===label) {
						jQuery("." + className).text("");
					} else {
						jQuery("." + className).text(label);
					}
				}

				function jdtDomButtonExpand(elem, reset=false) {
					if (!elem.hasClass("jdtDom3") || reset) {
						elem.addClass("jdtDom3");
						elem.insertAfter("#jdtDom .jdtDomSP");
						jQuery("#jdtDom .jdtDomHeader").show();
					} else {
						elem.removeClass("jdtDom3");
						elem.insertAfter("#jdtDom .jdtDomHeader");
						jQuery("#jdtDom .jdtDomHeader").hide();
					}
				}

				function getSelectButtons() {
					let selected_buttons = "";

					jQuery("#wpda_buttons input[type='checkbox']").each(function() {
						if (jQuery(this).is(":checked")) {
							selected_buttons += jQuery(this).data("dom");
						}
					});

					return selected_buttons;
				}

				function updateDataSource() {
					if (jQuery("input[name='pub_data_source']:checked").val()==="Table") {
						jQuery("#pub_schema_name").closest("tr").show();
						jQuery("#pub_table_name").closest("tr").show();
						jQuery("#pub_column_names").closest("tr").show();
						jQuery("#pub_cpt").closest("tr").hide();
						jQuery("#pub_cpt_fields").closest("tr").hide();
						jQuery("#pub_cpt_format").closest("tr").hide();
						jQuery("#pub_format").closest("tr").show();
						jQuery("#pub_default_where").closest("tr").show();
						jQuery("#pub_default_orderby").closest("tr").show();
						jQuery("#pub_query").closest("tr").hide();
					} else if (jQuery("input[name='pub_data_source']:checked").val()==="Query") {
						jQuery("#pub_schema_name").closest("tr").show();
						jQuery("#pub_table_name").closest("tr").hide();
						jQuery("#pub_column_names").closest("tr").hide();
						jQuery("#pub_cpt").closest("tr").hide();
						jQuery("#pub_cpt_fields").closest("tr").hide();
						jQuery("#pub_cpt_format").closest("tr").hide();
						jQuery("#pub_format").closest("tr").hide();
						jQuery("#pub_default_where").closest("tr").hide();
						jQuery("#pub_default_orderby").closest("tr").hide();
						jQuery("#pub_query").closest("tr").show();
					} else {
						jQuery("#pub_schema_name").closest("tr").hide();
						jQuery("#pub_table_name").closest("tr").hide();
						jQuery("#pub_column_names").closest("tr").hide();
						jQuery("#pub_cpt").closest("tr").show();
						jQuery("#pub_cpt_fields").closest("tr").show();
						jQuery("#pub_cpt_format").closest("tr").show();
						jQuery("#pub_format").closest("tr").hide();
						jQuery("#pub_default_where").closest("tr").show();
						jQuery("#pub_default_orderby").closest("tr").show();
						jQuery("#pub_query").closest("tr").hide();
					}
				}

				function cptDefaultOptions(customFields) {
					let options = "";
					options += `
						${customFields}
					`

					jQuery("#pub_cpt_fields_selected").remove();
					jQuery("#pub_cpt_fields").closest("td").find("div.multiselect_sortable_content").remove();

					// Remove delegate action to prevent double entries.
					jQuery(document).off('click', '.selectable_content .select_li');
					jQuery(document).off('click', '.selection_content .select_li');

					buildCustomFields(options);
				}

				function cptSaved(cptOptions) {
					let options = "";

					for (let i=0; i<cptOptions.selected.length; i++) {
						options += '<option value="' + cptOptions.selected[i] + '" selected>' + cptOptions.selected[i] + '</option>';
					}

					for (let i=0; i<cptOptions.selectable.length; i++) {
						options += '<option value="' + cptOptions.selectable[i] + '">' + cptOptions.selectable[i] + '</option>';
					}

					return options;
				}

				function getCustomFields() {
					url = "<?php echo admin_url( 'admin-ajax.php?action=wpda_get_custom_fields' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>";
					jQuery.ajax({
						type: "POST",
						url: url,
						data: {
							wpnonce: "<?php echo esc_attr( wp_create_nonce( "wpda-publication-{$pub_id}" ) ); ?>",
							pub_id: "<?php echo esc_attr( $pub_id ); ?>",
							post_type: jQuery("#pub_cpt").val()
						}
					}).done(
						function(json) {
							let customFields = "";

							for (let i=0; i<cfds_default_selectable.length; i++) {
								customFields += '<option value="' + cfds_default_selectable[i] + '">' + cfds_default_selectable[i] + '</option>';
							}
							for (let i=0; i<json.msg.length; i++) {
								customFields += '<option value="' + json.msg[i] + '">' + json.msg[i] + '</option>';
							}

							cfds_hidden = cfds_default_hidden;
							cptDefaultOptions(customFields);
						}
					);
				}

				function buildCustomFields(cptFields) {
					jQuery("#pub_cpt_fields").closest("td").prepend(`
						<select id="pub_cpt_fields_selected" multiple="true">
							${cptFields}
						</select>
					`);

					jQuery("#pub_cpt_fields_selected").multiselect_sortable({
						selectable: {
							title: "Selectable fields"
						},
						selection : {
							title: "Selected fields"
						}
					});
				}

				function buildCPT() {
					let cptOptions = null;

					if (jQuery("#pub_cpt_fields").val()!=="") {
						try {
							cptOptions = JSON.parse(jQuery("#pub_cpt_fields").val());
							if (!cptOptions.selected || !cptOptions.selectable) {
								cptOptions = null; // Invalid JSON.
							}
						} catch(e) {
							cptOptions = null;
						}
					}

					if (cptOptions===null) {
						// CPT not yet initialized.
						getCustomFields();
					} else {
						// Use saved CPT.
						buildCustomFields(cptSaved(cptOptions));
					}
				}

				function cptReload() {
					if (confirm("Reload custom fields for selected post type?\n\nThis will overwrite your current selection!")) {
						getCustomFields();
					}
				}

				function selectablePostTypes() {
					let html = "";
					for (let i=0; i<cpts_all.length; i++) {
						let checked = cpts_non_selectable.includes(cpts_all[i]) ? '' : 'checked';
						html += `
							<div>
								<label>
									<input type="checkbox" ${checked} data-pt="${cpts_all[i]}" /> ${cpts_all[i]}
								</label>
							</div>
						`;
					}
					jQuery(`
						<div id="pub_cpts_selectable" style="padding:10px">
							${html}
							<div style="padding:10px 0 0 0">
								Disable post types to hide
							</div>
						</div>
					`).dialog({
						_dialogClass: 'wp-dialog no-close',
						classes: {
							"ui-dialog": "wp-dialog no-close",
							"ui-dialog-content": "pub-post-types"
						},
						title: 'Selectable post types',
						modal: true,
						autoOpen: true,
						closeOnEscape: false,
						resizable: false,
						width: 400,
						buttons: {
							"OK": function () {
								cpts_non_selectable = [];

								jQuery("#pub_cpts_selectable input").each(function() {
									let postType = jQuery(this).data("pt");
									if (!jQuery(this).is(":checked")) {
										cpts_non_selectable.push(postType);
										jQuery("#pub_cpt option[value='" + postType + "']").remove();
									} else {
										if (jQuery("#pub_cpt option[value='" + postType + "']").length===0) {
											jQuery("#pub_cpt").append(
												jQuery("<option/>").val(postType).text(postType)
											);
										}
									}
								});

								url = "<?php echo admin_url( 'admin-ajax.php?action=wpda_non_selectable_cpts' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>";
								jQuery.ajax({
									type: "POST",
									url: url,
									data: {
										wpnonce: "<?php echo esc_attr( wp_create_nonce( "wpda-publication-{$pub_id}" ) ); ?>",
										pub_id: "<?php echo esc_attr( $pub_id ); ?>",
										cpts: cpts_non_selectable
									}
								});

								jQuery(this).dialog('destroy').remove();
							},
							"Cancel": function () {
								jQuery(this).dialog('destroy').remove();
							}
						}
					});

					wpda_add_icons_to_dialog_buttons();
				}

				function selectableFields() {
					let html = "";

					for (let i=0; i<cfds_selected.length; i++) {
						html += `
							<div>
								<label>
									<input type="checkbox" class="wpda_cfds_selected" disabled checked data-pt="${cfds_selected[i]}" /> ${cfds_selected[i]}
								</label>
							</div>
						`;
					}

					for (let i=0; i<cfds_selection.length; i++) {
						html += `
							<div>
								<label>
									<input type="checkbox" class="wpda_cfds_selection" checked data-pt="${cfds_selection[i]}" /> ${cfds_selection[i]}
								</label>
							</div>
						`;
					}

					for (let i=0; i<cfds_hidden.length; i++) {
						html += `
							<div>
								<label>
									<input type="checkbox" class="wpda_cfds_hidden" data-pt="${cfds_hidden[i]}" /> ${cfds_hidden[i]}
								</label>
							</div>
						`;
					}

					jQuery(`
						<div id="pub_cfds_selectable" style="padding:10px">
							${html}
							<div style="padding:10px 0 0 0">
								Disable fields to hide
							</div>
						</div>
					`).dialog({
						_dialogClass: 'wp-dialog no-close',
						classes: {
							"ui-dialog": "wp-dialog no-close",
							"ui-dialog-content": "pub-post-types"
						},
						title: 'Selectable fields',
						modal: true,
						autoOpen: true,
						closeOnEscape: false,
						resizable: false,
						width: 400,
						buttons: {
							"OK": function () {
								let customFields = "";
								let selection = []
								let hidden = [];

								jQuery("#pub_cfds_selectable input.wpda_cfds_selected").each(function() {
									let postType = jQuery(this).data("pt");
									customFields += '<option value="' + postType + '" selected>' + postType + '</option>';
								});

								jQuery("#pub_cfds_selectable input.wpda_cfds_selection").each(function() {
									if (jQuery(this).is(":checked")) {
										let postType = jQuery(this).data("pt");
										customFields += '<option value="' + postType + '">' + postType + '</option>';
										selection.push(postType);
									}
								});

								jQuery("#pub_cfds_selectable input.wpda_cfds_hidden").each(function() {
									if (jQuery(this).is(":checked")) {
										let postType = jQuery(this).data("pt");
										customFields += '<option value="' + postType + '">' + postType + '</option>';
										selection.push(postType);
									} else {
										hidden.push(jQuery(this).data("pt"));
									}
								});

								cfds_selection = selection;
								cfds_hidden = hidden;
								cptDefaultOptions(customFields);

								jQuery(this).dialog('destroy').remove();
							},
							"Cancel": function () {
								jQuery(this).dialog('destroy').remove();
							}
						}
					});

					wpda_add_icons_to_dialog_buttons();
				}
				<?php
				}
				?>

				function pre_submit_form() {
					// Simple form will automatically find and execute this function before the submit_form().
					// Process order by.
					let defaultOrderBy = "";
					jQuery(".wpda_dp_orderby").each(function() {
						let index = jQuery(this).data("index");
						let column = jQuery("#orderby" + index).val();
						let order = jQuery("#order" + index).val();
						if (column!=="") {
							if (defaultOrderBy!=="") {
								defaultOrderBy += "|";
							}
							defaultOrderBy += column;
							if (order!=="") {
								defaultOrderBy += "," + order;
							}
						}
					});
					jQuery("#pub_default_orderby").val(defaultOrderBy);

					<?php
					if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
					?>
					// Process extensions.
					let json = {};
					if (jQuery("#pub_extention_arrange").is(":checked")) {
						let dom = "";
						jQuery("#jdtDom div").each(function() {
							if (
								jQuery(this).css("display")!=="none" &&
								jQuery(this).text()!=="" &&
								jQuery(this).data("dom")
							) {
								if (jQuery(this).data("dom")==="B" && jQuery(this).hasClass("jdtDom3")) {
									dom += "<'wpda-buttons'" + jQuery(this).data("dom") + ">";
								} else {
									dom += jQuery(this).data("dom");
								}
							}
						});
						json.dom = dom;
						json.arrange = true;
						json.button_caption = jQuery("#wpda_table_expand_pub_extent input[name='pub_extention_buttons_text']:checked").val();
						json.selected_buttons = getSelectButtons();
						json.button_width = jQuery("#jdtDom .jdtDomB").hasClass("jdtDom3") ? 3 : 1;
						json.button_grid = [];
						jQuery("#jdtDom>div").each(function() {
							json.button_grid.push(jQuery(this).data("dom"));
						});
					} else {
						let dom = "";
						if (jQuery("#pub_extention_searchbuilder").is(":checked")) {
							dom += "Q";
						}
						if (jQuery("#pub_extention_searchpanes").is(":checked")) {
							dom += "P";
						}
						if (jQuery("#pub_extention_buttons").is(":checked")) {
							dom += "B";
						}
						dom += "lfrtip";
						json.dom = dom;
						json.arrange = false;
						json.button_caption = jQuery("#wpda_table_expand_pub_extent input[name='pub_extention_buttons_text']:checked").val();
						json.selected_buttons = getSelectButtons();
						json.button_width = 3;
						json.button_grid = [];
					}
					json.wpda_qb_columns = wpda_qb_columns; // select columns search builder
					json.wpda_sp_columns = wpda_sp_columns; // select columns search panes
					jQuery("#pub_extentions").val(JSON.stringify(json));

					// Process CPT.
					const cpts_selected = getListValues(jQuery("#pub_cpt_fields_selected").closest("td").find(".selection_content li"));
					const cpts_selectable = getListValues(jQuery("#pub_cpt_fields_selected").closest("td").find(".selectable_content li"));
					const cpts = {
						selected: cpts_selected,
						selectable: cpts_selectable,
						hidden: cfds_hidden
					};
					jQuery("#pub_cpt_fields").val(JSON.stringify(cpts));

					let ctp_query = "select ";
					for (let i=0; i<cpts_selected.length; i++) {
						if (cpts_selected[i].substring(0,5)==="post.") {
							ctp_query += cpts_selected[i];
						} else {
							ctp_query += "cpt" + i + ".meta_value as " + cpts_selected[i];
						}
						if (i<cpts_selected.length-1) {
							ctp_query += ",";
						}
					}
					ctp_query += " from <?php global $wpdb; echo esc_attr( $wpdb->posts ); ?> post ";
					ctp_where = "";
					for (let i=0; i<cpts_selected.length; i++) {
						if (cpts_selected[i].substring(0,5)!=="post.") {
							ctp_query += " left join <?php global $wpdb; echo esc_attr( $wpdb->postmeta ); ?> as cpt" + i +
								" on ( post.ID = cpt" + i + ".post_id ) ";
							ctp_where += " and cpt" + i + ".meta_key = '" + cpts_selected[i] + "' ";
						}
					}
					ctp_query += " where post.post_type = '" + jQuery("#pub_cpt").val() + "' ";
					ctp_query += " and post.post_status = 'publish' ";
					ctp_query += ctp_where;

					jQuery("#pub_cpt_query").val(ctp_query);
					<?php
					}
					?>

					return true;
				}

				function getListValues(list) {
					let values = [];
					list.each(
						function() {
							values.push(jQuery(this).text());
						}
					);
					return values;
				}

				function update_table_list(table_name = '') {
					var url = location.pathname + '?action=wpda_get_tables';
					var data = {
						wpdaschema_name: jQuery("[name='pub_schema_name']").val(),
						wpda_wpnonce: '<?php echo esc_attr( wp_create_nonce( 'wpda-getdata-access-' . WPDA::get_current_user_login() ) ); ?>'
					};
					jQuery.post(
						url,
						data,
						function (data) {
							jQuery('[name="pub_table_name"]').empty();
							var tables = JSON.parse(data);
							for (var i = 0; i < tables.length; i++) {
								jQuery('<option/>', {
									value: tables[i].table_name,
									html: tables[i].table_name
								}).appendTo("[name='pub_table_name']");
							}
							if (table_name!=='') {
								jQuery("[name='pub_table_name']").val(table_name);
							} else {
								jQuery('#pub_column_names').val('*');
								jQuery('#pub_format').val('');
								table_columns = [];
							}
						}
					);
				}

				function updateWordPressDatabaseName() {
					jQuery("#pub_schema_name option[value='<?php echo esc_attr( $wpdb_name ); ?>']").text("WordPress database (<?php echo esc_attr( $wpdb_name ); ?>)");
				}

				jQuery(function () {
					updateWordPressDatabaseName();

					pub_table_options_searching = jQuery('#pub_table_options_searching').parent().parent();
					pub_table_options_ordering = jQuery('#pub_table_options_ordering').parent().parent().children();
					pub_table_options_ordering_tr = jQuery(pub_table_options_ordering).parent().parent();
					pub_table_options_paging = jQuery('#pub_table_options_paging').parent().parent().children();
					pub_table_options_paging_tr = jQuery(pub_table_options_paging).parent().parent();
					pub_table_options_serverside = jQuery('#pub_table_options_serverside').parent().parent().children();
					pub_table_options_serverside_tr = jQuery(pub_table_options_serverside).parent().parent();
					pub_table_options_nl2br = jQuery('#pub_table_options_nl2br').parent().parent().children();
					pub_table_options_nl2br_tr = jQuery(pub_table_options_nl2br).parent().parent();

					jQuery('<span style="width:10px;display:inline-block;"></span>').appendTo(pub_table_options_searching);
					pub_table_options_ordering.appendTo(pub_table_options_searching);
					jQuery('<span style="width:10px;display:inline-block;"></span>').appendTo(pub_table_options_searching);
					pub_table_options_paging.appendTo(pub_table_options_searching);
					jQuery('<span style="width:10px;display:inline-block;"></span>').appendTo(pub_table_options_searching);
					pub_table_options_serverside.appendTo(pub_table_options_searching);
					jQuery('<span style="width:10px;display:inline-block;"></span>').appendTo(pub_table_options_searching);
					pub_table_options_nl2br.appendTo(pub_table_options_searching);

					pub_table_options_ordering_tr.remove();
					pub_table_options_paging_tr.remove();
					pub_table_options_serverside_tr.remove();
					pub_table_options_nl2br_tr.remove();

					set_responsive_columns();

					<?php if ( WPDA::OPTION_DP_JSON_EDITING[1] === $json_editing ) { ?>
					var cm = wp.codeEditor.initialize(jQuery('#pub_table_options_advanced'), cm_settings);
					<?php } ?>

					jQuery("[name='pub_schema_name']").on('change', function () {
						update_table_list();
					});
					update_table_list('<?php echo esc_attr( $table_name ); ?>');

					jQuery("[name='pub_table_name']").on('change', function () {
						jQuery('#pub_column_names').val('*');
						jQuery('#pub_format').val('');
						table_columns = [];
					});

					<?php
					if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
						?>
						jQuery("#pub_cpt").closest("tr").find('td.icon').empty().append(
							'<a class="button wpda_tooltip" href="javascript:void(0)" title="Configure selectable post types" onclick="selectablePostTypes()">' +
							'<i class="fas fa-cog wpda_icon_on_button"></i> Config' +
							'</a>'
						);
						jQuery("#pub_cpt_fields").closest("tr").find('td.icon').empty().append(
							'<a class="button wpda_tooltip" href="javascript:void(0)" title="Configure selectable fields" onclick="selectableFields()">' +
								'<i class="fas fa-cog wpda_icon_on_button"></i> Config' +
							'</a>' +
							'<br/>' +
							'<a class="button wpda_tooltip" href="javascript:void(0)" title="Reload selectable fields" onclick="cptReload()">' +
								'<i class="fas fa-undo wpda_icon_on_button"></i> Reload' +
							'</a>'
						);
						jQuery('#pub_style_premium').closest("tr").find('td.icon').empty().append('<a href="<?php echo admin_url( 'options-general.php' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>?page=wpdataaccess&tab=datapublisher" target="_blank"><i title="Yes: use premium styling settings below\nNo: use default plugin styling\n\nNOTE\nSet global publication style to Default to activate premium style (click to check)" class="fas fa-circle-question pointer wpda_tooltip"></i></a>');
						<?php
					}
					?>

					jQuery('#pub_default_where').closest("tr").find('td.icon').empty().append('<i title="Enter a valid sql where clause, for example:\nfirst_name like \'Peter%\'" class="fas fa-circle-question pointer wpda_tooltip"></i>');
					jQuery('#pub_table_options_searching').closest("tr").find('td.icon').empty().append('<i title="When paging is disabled, all rows are fetch on page load (this implicitly disables server side processing)\n\nEnable NL > BR to automatically convert New Lines to <BR> tags" class="fas fa-circle-question pointer wpda_tooltip"></i>');
					jQuery('#pub_table_options_advanced').closest("tr").find('td.icon').empty().append('<i title=\'Must be valid JSON:\n{"option":"value","option2","value2"}\' class="fas fa-circle-question pointer wpda_tooltip"></i>');
					jQuery('#pub_table_options_advanced').closest("tr").find('td.icon').append('<br/><a href="https://datatables.net/reference/option/" target="_blank" title="Click to check jQuery DataTables website for available\noptions (opens in a new tab or window)" class="dashicons dashicons-external wpda_tooltip" style="margin-top:5px;"></a>');
					jQuery('#pub_styles').closest("tr").find('td.icon').empty().append('<i title="Hold control key to selected multiple" class="fas fa-circle-question pointer wpda_tooltip"></i>');

					jQuery( '.wpda_tooltip' ).tooltip();
					jQuery( '.wpda_tooltip_ic' ).tooltip({
						tooltipClass: "wpda_tooltip_css_ic",
					});

					<?php if ( 'view' === $this->action ) { ?>
					jQuery('#format_columns').prop("readonly", true).prop("disabled", true).addClass("disabled");
					jQuery('#select_columns').prop("readonly", true).prop("disabled", true).addClass("disabled");
					<?php } ?>

					jQuery('#pub_responsive').on('change', function () {
						set_responsive_columns();
					});

					if (jQuery("#pub_style_color").length) {
						currentValue = jQuery("#pub_style_color").val();
						jQuery("#pub_style_color").replaceWith("<select id='pub_style_color' name='pub_style_color'></select>")
						<?php
						foreach ( $this->color as $color ) {
							echo 'jQuery("#pub_style_color").append(new Option("' . esc_attr( $color ) . '", "' . esc_attr( $color ) . '"));';
						}
						?>
						jQuery("#pub_style_color").val(currentValue);
					}

					if (jQuery("#pub_style_space").length) {
						jQuery("#pub_style_space").attr("type", "range").attr("min", 0).attr("max", 50);
						jQuery("#pub_style_space").closest("tr").find("td.icon").append("<span id ='pub_style_space_val' class='wpda-range'>");
						jQuery("#pub_style_space").on("change", function() { jQuery("#pub_style_space_val").html(this.value + "px"); });
						jQuery("#pub_style_space_val").html(jQuery("#pub_style_space").val() + "px");
					}

					if (jQuery("#pub_style_corner").length) {
						jQuery("#pub_style_corner").attr("type", "range").attr("min", 0).attr("max", 50);
						jQuery("#pub_style_corner").closest("tr").find("td.icon").append("<span id ='pub_style_corner_val' class='wpda-range'>");
						jQuery("#pub_style_corner").on("change", function() { jQuery("#pub_style_corner_val").html(this.value + "px"); });
						jQuery("#pub_style_corner_val").html(jQuery("#pub_style_corner").val() + "px");
					}

					if (jQuery("#pub_style_modal_width").length) {
						jQuery("#pub_style_modal_width").attr("type", "range").attr("min", 50).attr("max", 100);
						jQuery("#pub_style_modal_width").closest("tr").find("td.icon").append("<span id ='pub_style_modal_width_val' class='wpda-range'>");
						jQuery("#pub_style_modal_width").on("change", function() { jQuery("#pub_style_modal_width_val").html(this.value + "px"); });
						jQuery("#pub_style_modal_width_val").html(jQuery("#pub_style_modal_width").val() + "%");
					}

					// Add default order by UI.
					jQuery("#pub_default_orderby").before(createOrderByLine(0));
					// Restore default sorting.
					let defaultSorting = jQuery("#pub_default_orderby").val();
					let arraySorting = defaultSorting.split("|");
					for (let i=0; i<arraySorting.length; i++) {
						if (arraySorting[i]!=="") {
							if (jQuery("#orderby" + i).length===0) {
								// Add order by line.
								jQuery("#pub_default_orderby").before(createOrderByLine(i));
							}
							let orderBy = arraySorting[i].split(",");
							if (orderBy[0]!=="") {
								jQuery("#orderby" + i).val(orderBy[0]);
								jQuery("#order" + i).val(orderBy[1]);
							}
						}
					}

					<?php
					if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
						?>
						jQuery("#pub_style_premium").on('change', function () {
							set_premium_styling();
						});
						set_premium_styling();
						set_premium_extentions();

						jQuery("input[name='pub_data_source']").on("click", function() {
							updateDataSource();
						});
						updateDataSource();

						jQuery("#pub_cpt").on("change", function() {
							getCustomFields();
						});
						buildCPT();
						<?php
					}
					?>
				});

				let no_cols_selected = '* (= show all columns)';

				let table_columns = [];
				let cpt_fields = [];
				<?php
				foreach ( $columns as $column ) {
					?>
					table_columns.push('<?php echo esc_attr( $column ); ?>');
					<?php
				}
				if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
					if ( 'Query' === $this->pub_data_source ) {
						?>
						table_columns = [];
						<?php
						$index          = $this->get_item_index( 'pub_query' );
						$pub_query_item = $this->form_items[ $index ];
						$pub_query      = $pub_query_item->get_item_value();
	
						$response = WPDA_Publisher_Model::get_temporary_table_from_custom_query( $schema_name, $pub_query );
						if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
							$rows = $response['data'];
							foreach ( $rows as $column ) {
								?>
								table_columns.push('<?php echo esc_attr( $column['Field'] ); ?>');
								<?php
							}
						}
					}
					if ( isset( $cpt_fields ) && is_array( $cpt_fields ) ) {
						for ( $i = 0; $i < count( $cpt_fields ); $i++ ) {
							?>
							cpt_fields.push("<?php echo esc_attr( $cpt_fields[ $i ] ); ?>");
							<?php
						}
					}
				}
				?>

				var hyperlinks = [];
				<?php
				if ( null !== $this->hyperlinks && is_array( $this->hyperlinks ) ) {
					foreach ( $this->hyperlinks as $hyperlink ) {
						echo "hyperlinks.push('{$hyperlink}');"; // phpcs:ignore WordPress.Security.EscapeOutput
					}
				}
				?>

				function select_available(e) {
					var option = jQuery("#columns_available option:selected");
					var add_to = jQuery("#columns_selected");

					option.remove();
					new_option = add_to.append(option);

					if (jQuery("#columns_selected option[value='*']").length > 0) {
						// Remove ALL from selected list.
						jQuery("#columns_selected option[value='*']").remove();
					}

					jQuery('select#columns_selected option').prop("selected", false);
				}

				function select_selected() {
					var option = jQuery("#columns_selected option:selected");
					if (option[0].value === '*') {
						// Cannot remove ALL.
						return;
					}

					var add_to = jQuery("#columns_available");

					option.remove();
					add_to.append(option);

					if (jQuery('select#columns_selected option').length === 0) {
						jQuery("#columns_selected").append(jQuery('<option></option>').attr('value', '*').text(no_cols_selected));
					}

					jQuery('select#columns_available option').prop("selected", false);
				}

				function select_columns() {
					if (!(Array.isArray(table_columns) && table_columns.length)) {
						alert("<?php echo __( 'To select columns you need to save your publication first', 'wp-data-access' ); ?>");
						return;
					}

					var columns_available = jQuery(
						'<select id="columns_available" name="columns_available[]" multiple size="8" style="width:200px" onchange="select_available()">' +
						'</select>'
					);
					jQuery.each(table_columns, function (i, val) {
						columns_available.append(jQuery('<option></option>').attr('value', val).text(val));
					});
					for (let i=0; i<hyperlinks.length;i++) {
						columns_available.append(jQuery('<option></option>').attr('value', 'wpda_hyperlink_' + i).text('Hyperlink: ' + hyperlinks[i]));
					}

					var currently_select_option = '';
					var currently_select_values = jQuery('#pub_column_names').val();
					if (currently_select_values == '*') {
						currently_select_values = [];
					} else {
						currently_select_values = currently_select_values.split(',');
					}
					if (currently_select_values.length === 0) {
						currently_select_option = '<option value="*">' + no_cols_selected + '</option>';
					} else {
						for (let i=0; i < currently_select_values.length; i++) {
							if (currently_select_values[i].substr(0,15)==='wpda_hyperlink_') {
								hyperlink_no = currently_select_values[i].substr(15);
								if (hyperlink_no<hyperlinks.length) {
									option_text = 'Hyperlink: ' + hyperlinks[hyperlink_no];
									currently_select_option += '<option value="' + currently_select_values[i] + '">' + option_text + '</option>';
								}
							} else {
								option_text = currently_select_values[i];
								currently_select_option += '<option value="' + currently_select_values[i] + '">' + option_text + '</option>';
							}
						}
					}

					var columns_selected = jQuery(
						'<select id="columns_selected" name="columns_selected[]" multiple size="8" style="width:200px" onchange="select_selected()">' +
						currently_select_option +
						'</select>'
					);

					var dialog_table = jQuery('<table style="width:410px"></table>');
					var dialog_table_row = dialog_table.append(jQuery('<tr></tr>'));
					dialog_table_row.append(jQuery('<td width="50%"></td>').append(columns_available));
					dialog_table_row.append(jQuery('<td width="50%"></td>').append(columns_selected));

					var dialog_text = jQuery('<div style="width:410px"></div>');
					var dialog = jQuery('<div></div>');

					dialog.append(dialog_text);
					dialog.append(dialog_table);

					jQuery(dialog).dialog(
						{
							dialogClass: 'wp-dialog no-close',
							title: 'Add column(s) to publication',
							modal: true,
							autoOpen: true,
							closeOnEscape: false,
							resizable: false,
							width: 'auto',
							buttons: {
								"OK": function () {
									var selected_columns = '';
									jQuery("#columns_selected option").each(
										function () {
											selected_columns += jQuery(this).val() + ',';
										}
									);
									if (selected_columns !== '') {
										selected_columns = selected_columns.slice(0, -1);
									}
									jQuery('#pub_column_names').val(selected_columns);
									jQuery(this).dialog('destroy').remove();
								},
								"Cancel": function () {
									jQuery(this).dialog('destroy').remove();
								}
							}
						}
					);

					wpda_add_icons_to_dialog_buttons();

					// Remove selected columns from available columns
					for (let i = 0; i < currently_select_values.length; i++) {
						jQuery("#columns_available option[value='" + currently_select_values[i] + "']").remove();
					}
				}

				function format_cpt_columns() {
					if (!(Array.isArray(cpt_fields) && cpt_fields.length)) {
						alert("<?php echo __( 'To format columns you need to save your publication first', 'wp-data-access' ); ?>");
						return;
					}

					let pub_cpt_format = null;
					try {
						pub_cpt_format = JSON.parse(jQuery('#pub_cpt_format').val());
					} catch (e) {
						pub_cpt_format = null;
					}

					let dialog_table = jQuery('<table></table>');
					dialog_table.append(
						jQuery('<tr></tr>').append(
							jQuery('<th style="text-align:left;"><?php echo __( 'Field Name', 'wp-data-access' ); ?></th>'),
							jQuery('<th style="text-align:left;"><?php echo __( 'Field Label', 'wp-data-access' ); ?></th>'),
						)
					);

					for (let i=0; i<cpt_fields.length; i++) {
						let label = cpt_fields[i];
						try {
							label = pub_cpt_format.cpt_format.cpt_labels[cpt_fields[i]];
						} catch (e) {
							label = cpt_fields[i];
						}
						dialog_table.append(
							jQuery('<tr></tr>').append(
								jQuery('<td style="text-align:left;">' + cpt_fields[i] + '</td>'),
								jQuery('<td style="text-align:left;"><input type="text" class="cpt_label" name="' + cpt_fields[i] + '" value="' + label + '"></td>'),
							)
						);
					}

					let dialog_text = jQuery('<div></div>');
					let dialog = jQuery('<div id="define_cpt_labels"></div>');

					dialog.append(dialog_text);
					dialog.append(dialog_table);

					jQuery(dialog).dialog({
						dialogClass: 'wp-dialog no-close',
						title: 'Define field labels',
						modal: true,
						autoOpen: true,
						closeOnEscape: false,
						resizable: false,
						width: 'auto',
						buttons: {
							"OK": function () {
								// Create JSON from defined field labels
								var cpt_labels = {};
								jQuery("#define_cpt_labels input.cpt_label").each(
									function () {
										cpt_labels[jQuery(this).attr('name')] = jQuery(this).val();
									}
								);

								// Write JSON to column pub_format
								cpt_format = {
									"cpt_format": {
										"cpt_labels": cpt_labels
									}
								};
								jQuery('#pub_cpt_format').val(JSON.stringify(cpt_format));
								jQuery(this).dialog('destroy').remove();
							},
							"Cancel": function () {
								jQuery(this).dialog('destroy').remove();
							}
						}
					});

					wpda_add_icons_to_dialog_buttons();
				}

				function format_columns() {
					if (!(Array.isArray(table_columns) && table_columns.length)) {
						alert("<?php echo __( 'To format columns you need to save your publication first', 'wp-data-access' ); ?>");
						return;
					}

					var pub_format_json_string = jQuery('#pub_format').val();

					var columns_labels = [];

					if (pub_format_json_string !== '') {
						// Use previously defined formatting
						var pub_format = JSON.parse(pub_format_json_string);
						if (typeof pub_format['pub_format']['column_labels'] !== 'undefined') {
							columns_labels = pub_format['pub_format']['column_labels'];
						}
					} else {
						// Get column labels from table settings
						columns_labels = <?php echo json_encode( $column_labels ); ?>;
					}

					var dialog_table = jQuery('<table></table>');
					dialog_table.append(
						jQuery('<tr></tr>').append(
							jQuery('<th style="text-align:left;"><?php echo __( 'Column Name', 'wp-data-access' ); ?></th>'),
							jQuery('<th style="text-align:left;"><?php echo __( 'Column Label', 'wp-data-access' ); ?></th>'),
						)
					);

					<?php
					foreach ( $table_columns->get_table_columns() as $table_column ) {
						?>
						columns_label = '<?php echo esc_attr( $table_column['column_name'] ); ?>';
						if (typeof columns_labels !== 'undefined') {
							if (columns_label in columns_labels) {
								columns_label = columns_labels[columns_label];
							}
						}
						dialog_table.append(
							jQuery('<tr></tr>').append(
								jQuery('<td style="text-align:left;"><?php echo esc_attr( $table_column['column_name'] ); ?></td>'),
								jQuery('<td style="text-align:left;"><input type="text" class="column_label" name="<?php echo esc_attr( $table_column['column_name'] ); ?>" value="' + columns_label + '"></td>'),
							)
						);
						<?php
					}
					?>

					var dialog_text = jQuery('<div></div>');
					var dialog = jQuery('<div id="define_column_labels"></div>');

					dialog.append(dialog_text);
					dialog.append(dialog_table);

					jQuery(dialog).dialog({
						dialogClass: 'wp-dialog no-close',
						title: 'Define column labels',
						modal: true,
						autoOpen: true,
						closeOnEscape: false,
						resizable: false,
						width: 'auto',
						buttons: {
							"OK": function () {
								// Create JSON from defined column labels
								var column_labels = {};
								jQuery('.column_label').each(
									function () {
										column_labels[jQuery(this).attr('name')] = jQuery(this).val();
									}
								);

								// Write JSON to column pub_format
								pub_format = {
									"pub_format": {
										"column_labels": column_labels
									}
								};
								jQuery('#pub_format').val(JSON.stringify(pub_format));
								jQuery(this).dialog('destroy').remove();
							},
							"Cancel": function () {
								jQuery(this).dialog('destroy').remove();
							}
						}
					});

					wpda_add_icons_to_dialog_buttons();
				}

				function getQuery() {
					url = location.pathname + '?action=wpda_query_builder_open_sql';
					jQuery.ajax({
						method: 'POST',
						url: url,
						data: {
							wpda_wpnonce: "<?php echo esc_attr( wp_create_nonce( 'wpda-query-builder-' . WPDA::get_current_user_id() ) ); ?>",
							wpda_exclude: ""
						}
					}).done(
						function (msg) {
							if (!Array.isArray(msg.data)) {
								// Show queries
								list = jQuery("<ul/>");
								for (var queryName in msg.data) {
									dbs = msg.data[queryName].schema_name;
									qry = msg.data[queryName].query;

									query = jQuery(`
										<div class="wpda-query-select">
											<div class="wpda-query-select-title ui-widget-header">
												${queryName}
												<span class="fas fa-copy wpda-query-select-title-copy wpda_tooltip_left" title="Copy SQL"></span>
											</div>
											<div class="wpda-query-select-content">
												<textarea>${qry}</textarea>
											</div>
										</div>
									`);
									listitem = jQuery("<li/>").attr("data-dbs", dbs);
									listitem.append(query);

									list.append(listitem);
								}
								dialog = jQuery("<div class='wpda-query'/>").attr("title", "Select from Query Builder");
								dialog.append(list);
								dialog.dialog({
									modal: true,
									resizable: false,
									width: "700px"
								});
								jQuery(".wpda_tooltip_left").tooltip({
									tooltipClass: "wpda_tooltip_dashboard",
									position: { my: "right top", at: "right bottom" }
								});

								jQuery(".wpda-query-select-title-copy").on("click", function() {
									selectedDbs = jQuery(this).closest("li").data("dbs");
									selectedQuery = jQuery(this).closest("li").find("textarea").val();

									jQuery("#pub_schema_name").val(selectedDbs);
									jQuery("#pub_query").val(selectedQuery);

									jQuery(this).closest('.ui-dialog-content').dialog('close');
								});
							} else {
								// No queries found
							}
						}
					).fail(
						function (msg) {
							console.log("WP Data Access error (getSQLFromQueryBuilder):", msg);
						}
					);
				}

				function test_publication() {
					jQuery.ajax({
						type: "POST",
						url: "<?php echo admin_url( 'admin-ajax.php?action=wpda_test_publication' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>",
						data: {
							wpnonce: "<?php echo esc_attr( wp_create_nonce( "wpda-publication-{$pub_id}" ) ); ?>",
							pub_id: "<?php echo esc_attr( $pub_id ); ?>"
						}
					}).done(
						function(html) {
							jQuery("body").append(html);
							jQuery('#data_publisher_test_container_<?php echo esc_html( $pub_id ); ?>').show();
							//publication_loaded = true;
						}
					);
				}

				function addOrderByColumn() {
					let index = 0;
					jQuery(".wpda_dp_orderby").each(function() {
						if (parseInt(jQuery(this).data("index"))>index) {
							index = parseInt(jQuery(this).data("index"));
						}
					});
					index++;
					jQuery("#pub_default_orderby").before(createOrderByLine(index));
				}

				function removeOrderByColumn(elem) {
					elem.parent().remove();
				}

				function createOrderByLine(index) {
					let selectedColumns = get_selected_columns();

					let options = '<option value=""></option>';
					for (let i=0; i<selectedColumns.length; i++) {
						options += `<option value="${i}">${selectedColumns[i]}</option>`;
					}
					let columns = `<select id="orderby${index}">${options}</select>`;

					let order = `<select id="order${index}"><option></option><option value="asc">Ascending</option><option value="desc">Descending</option></select>`;

					let icon = "";
					if (index===0) {
						icon = `<i class="fas fa-plus-circle wpda_tooltip" title="Add column" onclick="addOrderByColumn()"></i>`;
					} else {
						icon = `<i class="fas fa-minus-circle wpda_tooltip" title="Remove column" onclick="removeOrderByColumn(jQuery(this))"></i>`;
					}

					return `<div data-index="${index}" class="wpda_dp_orderby">${columns}${order}${icon}</div>`;
				}
			</script>
			<?php
		}

		protected function show_shortcode( $pub_id ) {
			// Show publication shortcode directly from Data Publisher main page
			$shortcode_enabled =
				'on' === WPDA::get_option( WPDA::OPTION_PLUGIN_WPDATAACCESS_POST ) &&
				'on' === WPDA::get_option( WPDA::OPTION_PLUGIN_WPDATAACCESS_PAGE );

			?>
			<div id="wpda_publication_<?php echo esc_attr( $pub_id ); ?>"
				 title="<?php echo __( 'Publication shortcode', 'wp-data-access' ); ?>"
				 style="display:none"
			>
				<p class="wpda_shortcode_content">
					Copy the shortcode below into your post or page to make this publications available on your website.
				</p>
				<p class="wpda_shortcode_text">
					<strong>
						[wpdataaccess pub_id="<?php echo esc_attr( $pub_id ); ?>"]
					</strong>
				</p>
				<p class="wpda_shortcode_buttons">
					<button class="button wpda_shortcode_clipboard wpda_shortcode_button"
							type="button"
							data-clipboard-text='[wpdataaccess pub_id="<?php echo esc_attr( $pub_id ); ?>"]'
							onclick="jQuery.notify('<?php echo __( 'Shortcode successfully copied to clipboard!' ); ?>','info')"
					>
						<?php echo __( 'Copy', 'wp-data-access' ); ?>
					</button>
					<button class="button button-primary wpda_shortcode_button"
							type="button"
							onclick="jQuery('.ui-dialog-content').dialog('close')"
					>
						<?php echo __( 'Close', 'wp-data-access' ); ?>
					</button>
				</p>
				<?php
				if ( ! $shortcode_enabled ) {
					?>
					<p>
						Shortcode wpdataaccess is not enabled for all output types.
						<a href="<?php echo admin_url( 'options-general.php' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>?page=wpdataaccess" class="wpda_shortcode_link">&raquo; Manage settings</a>
					</p>
					<?php
				}
				?>
			</div>
			<a href="javascript:void(0)"
			   class="button view wpda_tooltip"
			   title="<?php echo __( 'Get publication shortcode', 'wp-data-access' ); ?>"
			   onclick="jQuery('#wpda_publication_<?php echo esc_attr( $pub_id ); ?>').dialog()"
			>
				<span style="white-space:nowrap">
					<i class="fas fa-code wpda_icon_on_button"></i>
					<?php echo __( 'Shortcode', 'wp-data-access' ); ?>
				</span>
			</a>
			<?php
			WPDA::shortcode_popup();
		}

		public static function test_publication() {
			$pub_id   = isset( $_REQUEST['pub_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['pub_id'] ) ) : null;
			$wp_nonce = isset( $_REQUEST['wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpnonce'] ) ) : ''; // input var okay.

			$datatables_enabled            = WPDA::get_option( WPDA::OPTION_BE_LOAD_DATATABLES ) === 'on';
			$datatables_responsive_enabled = WPDA::get_option( WPDA::OPTION_BE_LOAD_DATATABLES_RESPONSE ) === 'on';

			if ( ! wp_verify_nonce( $wp_nonce, "wpda-publication-{$pub_id}" ) ) {
				$publication =
					'<strong>' . __( 'ERROR: Not authorized', 'wp-data-access' ) . '</strong>';
			} elseif ( null === $pub_id ) {
				$publication =
					'<strong>' . __( 'ERROR: Cannot test publication [wrong arguments]', 'wp-data-access' ) . '</strong>';
			} elseif ( ! $datatables_enabled || ! $datatables_responsive_enabled ) {
				$publication =
					'<strong>' . __( 'ERROR: Cannot test publication', 'wp-data-access' ) . '</strong><br/><br/>' .
					__( 'SOLUTION: Load jQuery DataTables: WP Data Access > Manage Plugin > Back-End Settings', 'wp-data-access' );
			} else {
				$wpda_data_tables = new WPDA_Data_Tables();
				$publication      = $wpda_data_tables->show( $pub_id, '', '', '', '', '', '', '', '', '' );// , '', '', '', true );
			}

			ob_start();
			?>
			<div id="data_publisher_test_container_<?php echo esc_html( $pub_id ); ?>">
				<style>
					#data_publisher_test_header_<?php echo esc_html( $pub_id ); ?> {
						height: 30px;
						background-color: #ccc;
						padding: 10px;
						margin-bottom: 10px;
					}

					#data_publisher_test_header_<?php echo esc_html( $pub_id ); ?> span strong {
						padding-top: 10px;
						font-size: 14px;
						vertical-align: middle;
					}

					#data_publisher_test_container_<?php echo esc_html( $pub_id ); ?> {
						display: none;
						padding: 10px;
						position: absolute;
						top: 30px;
						left: 10px;
						color: black;
						overflow-y: auto;
						background-color: white;
						border: 1px solid #ccc;
						width: calc(100% - 100px);
						z-index: 9999;
					}

                    #data_publisher_test_container_<?php echo esc_html( $pub_id ); ?> thead input[type='search'],
                    #data_publisher_test_container_<?php echo esc_html( $pub_id ); ?> thead select,
                    #data_publisher_test_container_<?php echo esc_html( $pub_id ); ?> tfoot input[type='search'],
                    #data_publisher_test_container_<?php echo esc_html( $pub_id ); ?> tfoot select {
						padding-top: 5px;
                        padding-bottom: 5px;
                    }

                    #data_publisher_test_container_<?php echo esc_html( $pub_id ); ?> .dataTables_filter {
						height: 42px;
					}

					.dataTables_wrapper .dataTables_filter {
						height: 35px;
					}
				</style>
				<div id="data_publisher_test_header_<?php echo esc_attr( $pub_id ); ?>">
					<span><strong><?php echo __( 'Test Publication', 'wp-data-access' ); ?> (pub_id=<?php echo esc_attr( $pub_id ); ?>
							- <?php echo __( 'publication looks different on your website', 'wp-data-access' ); ?>)
						</strong></span>
					<span class="button" style="float:right;"
						  onclick="jQuery('#data_publisher_test_container_<?php echo esc_attr( $pub_id ); ?>').hide(); jQuery('#data_publisher_test_container_<?php echo esc_attr( $pub_id ); ?>').remove();">x</span><br/>
				</div>
				<?php echo $publication; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</div>
			<script type='text/javascript'>
				jQuery("#data_publisher_test_container_<?php echo esc_attr( $pub_id ); ?>").appendTo("#wpbody-content");
			</script>
			<?php

			echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput
			wp_die();
		}

	}

}
