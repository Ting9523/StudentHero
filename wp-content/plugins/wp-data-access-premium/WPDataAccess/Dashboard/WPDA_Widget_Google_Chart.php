<?php // phpcs:ignore Standard.Category.SniffName.ErrorCode

namespace WPDataAccess\Dashboard {

	use WPDataAccess\Connection\WPDADB;
	use WPDataAccess\WPDA;

	/**
	 * Chart widget
	 */
	class WPDA_Widget_Google_Chart extends WPDA_Widget {

		const OPTION_CHART_CACHE = 'wpda-chart-cache';

		/**
		 * Output type
		 *
		 * @var mixed|string[]
		 */
		protected $outputType = array( 'Table' ); // phpcs:ignore
		/**
		 * Selected chart types
		 *
		 * @var array|mixed
		 */
		protected $userChartTypeList = array(); // phpcs:ignore
		/**
		 * Database name
		 *
		 * @var mixed|null
		 */
		protected $dbs = null;
		/**
		 * SQL query
		 *
		 * @var mixed|null
		 */
		protected $query = null;
		/**
		 * Refresh indicator
		 *
		 * @var mixed|null
		 */
		protected $refresh = null;
		/**
		 * Cache indicator
		 *
		 * @var mixed|null
		 */
		protected $cache = null;
		/**
		 * Unit of measurement (for cache)
		 *
		 * @var mixed|null
		 */
		protected $unit = null;
		/**
		 * Queried columns
		 *
		 * @var array
		 */
		protected $columns = array();
		/**
		 * Selected rows
		 *
		 * @var array
		 */
		protected $rows = array();
		/**
		 * Chart options
		 *
		 * @var mixed|null
		 */
		protected $options = null;

		/**
		 * Constructor
		 *
		 * @param array $args Constructor arguments.
		 */
		public function __construct( $args = array() ) {
			parent::__construct( $args );

			$this->can_share   = true;
			$this->has_layout  = true;
			$this->has_setting = true;
			$this->can_refresh = true;

			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				$this->outputType        = isset( $args['outputType'] ) ? $args['outputType'] : array( 'Table' ); // phpcs:ignore
				$this->userChartTypeList = isset( $args['userChartTypeList'] ) ? $args['userChartTypeList'] : array(); // phpcs:ignore
				$this->dbs               = isset( $args['dbs'] ) ? $args['dbs'] : null;
				$this->query             = isset( $args['query'] ) ? $args['query'] : null;
				$this->refresh           = isset( $args['refresh'] ) ? $args['refresh'] : null;
				$this->cache             = isset( $args['cache'] ) ? $args['cache'] : null;
				$this->unit              = isset( $args['unit'] ) ? $args['unit'] : null;
				$this->options           = isset( $args['options'] ) ? $args['options'] : null;
			}

			// Create container.
			$this->content = "
				<div class='wpda-chart-container'>
					<div class='wpda_widget_chart_selection'>
						<button href='javascript:void(0)' class='dt-button wpda-chart-button-export'>
							<i class='fas fa-cloud-download wpda_icon_on_button'></i>
							Export data
						</button>
						<a href='' target='_blank' style='display:none' class='wpda-chart-button-export-link'>Export data hyperlink</a>
						<button href='javascript:void(0)' class='dt-button wpda-chart-button-print' style='display:none'>
							<i class='fas fa-print wpda_icon_on_button'></i>
							Printable version
						</button>
						<select id='wpda_widget_chart_selection_{$this->widget_id}' style='display:none'></select>
					</div>
					<div class='wpda_widget_chart_container' id='wpda_widget_container_{$this->widget_id}'></div>
				</div>
			";
		}

		/**
		 * Chart shortcode implementation
		 *
		 * @param WPDA_Widget_Google_Chart $widget Chart widget.
		 * @return void
		 */
		public function do_shortcode( $widget ) {
			// This method is implemented in the premium version.
			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				$obj                    = new \stdClass();
				$obj->chartType         = $widget['chartType']; // phpcs:ignore
				$obj->userChartTypeList = $this->userChartTypeList; // phpcs:ignore
				$obj->chartOptions      = isset( $widget['chartOptions'] ) ? $widget['chartOptions'] : null; // phpcs:ignore

				$esc_attr = 'esc_attr'; // Used to escape values before writing to output.
				$html     = "
					<div id='wpda_panel_{$esc_attr( $this->widget_id )}' class='wpda-panel'>
						<div id='wpda_panel_selection_container_{$esc_attr( $this->widget_id )}' class='wpda-panel-selection' style='display:none'>
							<button href='javascript:void(0)' class='dt-button wpda-chart-button-export'>Export data</button>
							<a href='' target='_blank' style='display:none' class='wpda-chart-button-export-link'>Export data hyperlink</a>
							<button href='javascript:void(0)' class='dt-button wpda-chart-button-print' style='display:none'>Printable version</button>
							<select id='wpda_panel_selection_{$this->widget_id}'></select>
						</div>
						<div id='wpda_panel_container_{$esc_attr( $this->widget_id )}' class='wpda-panel-container'>
						</div>
					</div>
					<script type='application/javascript'>
						jQuery(function() {
							setTimeout(waitForGoogleCharts_{$esc_attr( $this->widget_id )}, 1000);
							function waitForGoogleCharts_{$esc_attr( $this->widget_id )}() {
								if (googleChartsLoaded) {
									wpdaSonce = '" . esc_attr( WPDA::wpda_create_sonce() ) . "';
									var obj = " . wp_json_encode( $obj ) . ";
									addPanel(
										{$esc_attr( $this->widget_id )}, 
										'{$esc_attr( $widget['widgetName'] )}', 
										'{$esc_attr( $widget['widgetType'] )}', 
										obj
									);
									getChartData({$esc_attr( $this->widget_id )});
									// console.log('WP Data Access chart libraries loaded...');
								} else {
									setTimeout(waitForGoogleCharts_{$esc_attr( $this->widget_id )}, 1000);
									console.log('Waiting for WP Data Access chart libraries to be loaded...');
								}
							}
						});
					</script>
				";
				echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
			}
		}

		/**
		 * Embed chart widget on external website
		 *
		 * @param WPDA_Widget_Google_Chart $widget Chart widget.
		 * @param string                   $target_element HTML element id.
		 * @return void
		 */
		public function do_embed( $widget, $target_element ) {
			// This method is implemented in the premium version.
			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				$obj                    = new \stdClass();
				$obj->chartType         = $widget['chartType']; // phpcs:ignore
				$obj->userChartTypeList = $this->userChartTypeList; // phpcs:ignore
				$obj->chartOptions      = isset( $widget['chartOptions'] ) ? $widget['chartOptions'] : null; // phpcs:ignore
				?>
					if (typeof window.wpdaPanelSequenceNr === 'undefined') {
						window.wpdaPanelSequenceNr = (function() {
							var wpdaPanelSequenceNr = 0;
							return function() {
								wpdaPanelSequenceNr++;
								return wpdaPanelSequenceNr;
							}
						})();
					}

					setTimeout(waitForGoogleCharts<?php echo esc_attr( $target_element ); ?>, 1000);
					function waitForGoogleCharts<?php echo esc_attr( $target_element ); ?>() {
						if (googleChartsLoaded) {
							wpdaSonce = '<?php echo esc_attr( WPDA::wpda_create_sonce() ); ?>';
							wpdaCaller = 'embedded';
							var wpdaPanelSequenceNr = window.wpdaPanelSequenceNr();
							var html = `
								<div id='wpda_panel_${wpdaPanelSequenceNr}' class='wpda-panel'>
									<div id='wpda_panel_selection_container_${wpdaPanelSequenceNr}' class='wpda-panel-selection' style='display:none'>
										<button href='javascript:void(0)' class='dt-button wpda-chart-button-export'>Export data</button>
										<a href='' target='_blank' style='display:none' class='wpda-chart-button-export-link'>Export data hyperlink</a>
										<button href='javascript:void(0)' class='dt-button wpda-chart-button-print' style='display:none'>Printable version</button>
										<select id='wpda_panel_selection_${wpdaPanelSequenceNr}'></select>
									</div>
									<div id='wpda_panel_container_${wpdaPanelSequenceNr}' class='wpda-panel-container'>
									</div>
								</div>
							`;
							jQuery('#<?php echo esc_attr( $target_element ); ?>').append(jQuery(html));
							addPanel(
								wpdaPanelSequenceNr, 
								'<?php echo esc_attr( $widget['widgetName'] ); ?>',
								'<?php echo esc_attr( $widget['widgetType'] ); ?>',
								<?php echo wp_json_encode( $obj ); ?>
							);
							getChartData(wpdaPanelSequenceNr);
							// console.log("WP Data Access chart libraries loaded...");
						} else {
							setTimeout(waitForGoogleCharts<?php echo esc_attr( $target_element ); ?>, 1000);
							console.log("Waiting for WP Data Access chart libraries to be loaded...");
						}
					}
				<?php
			}
		}

		/**
		 * Edit chart form
		 *
		 * @return void
		 */
		public function edit_chart() {
			echo $this->html(); // phpcs:ignore WordPress.Security.EscapeOutput
			echo $this->js( 300 ); // phpcs:ignore WordPress.Security.EscapeOutput
		}

		/**
		 * Chart javascript code
		 *
		 * @param integer $interval Interval.
		 * @return void
		 */
		protected function js( $interval = 1000 ) {
			?>
			<script type='application/javascript' class="wpda-widget-<?php echo esc_attr( $this->widget_id ); ?>">
				jQuery(function() {
					var widget = jQuery("#wpda-widget-<?php echo esc_attr( $this->widget_id ); ?>");

					<?php
					if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
						?>
						widget.find(".wpda-widget-share").on("click", function(e) {
							shareWidget("<?php echo esc_attr( $this->widget_id ); ?>");
							e.stopPropagation();
						});
						<?php
					}
					?>

					widget.find(".wpda-widget-layout").on("click", function() {
						chartLayout("<?php echo esc_attr( $this->widget_id ); ?>");
					});

					widget.find(".wpda-widget-setting").on("click", function() {
						chartSettings("<?php echo esc_attr( $this->widget_id ); ?>");
					});

					widget.find(".wpda-widget-refresh").on("click", function(e, action = null) {
						if (action==="refresh") {
							refreshChart("<?php echo esc_attr( $this->widget_id ); ?>");
						} else {
							getChartData("<?php echo esc_attr( $this->widget_id ); ?>");
						}
					});

					jQuery("#wpda_widget_chart_selection_<?php echo esc_attr( $this->widget_id ); ?>").on("change", function() {
						refreshChart("<?php echo esc_attr( $this->widget_id ); ?>");
					});

					<?php
					if ( 'new' === $this->state ) {
						?>
						chartSettings("<?php echo esc_attr( $this->widget_id ); ?>");
						<?php
					}
					if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
						if ( 'new' !== $this->state ) {
							$obj = new \stdClass();
							// phpcs:disable
							// Snake type for javascript conversion
							$obj->chartType         = $this->outputType;
							$obj->userChartTypeList = $this->userChartTypeList;
							$obj->chartDbs          = esc_attr( $this->dbs );
							$obj->chartSql          = wp_unslash( $this->query );
							$obj->chartOptions      = wp_unslash( $this->options );
							$obj->chartRefresh      = esc_attr( $this->refresh );
							$obj->chartCache        = esc_attr( $this->cache );
							$obj->chartUnit         = esc_attr( $this->unit );
							// phpcs:enable
							?>
							setTimeout(waitForGoogleCharts_<?php echo esc_attr( $this->widget_id ); ?>, <?php echo esc_attr( $interval ); ?>);
							function waitForGoogleCharts_<?php echo esc_attr( $this->widget_id ); ?>() {
								if (googleChartsLoaded) {
									addDashboardWidget(
										<?php echo esc_attr( $this->widget_id ); ?>,
										"<?php echo esc_attr( $this->name ); ?>",
										"chart",
										<?php echo wp_json_encode( $this->share ); ?>,
										<?php echo wp_json_encode( $obj ); ?>,
										false
									);
									getChartData(<?php echo esc_attr( $this->widget_id ); ?>);
									// console.log("WP Data Access chart libraries loaded...");
								} else {
									setTimeout(waitForGoogleCharts_<?php echo esc_attr( $this->widget_id ); ?>, 1000);
									console.log("Waiting for WP Data Access chart libraries to be loaded...");
								}
							}
							<?php
						}
					}
					?>
				});
			</script>
			<?php
		}

		/**
		 * Get chart data
		 *
		 * @param string $dbs Database name.
		 * @param string $query SQL query.
		 * @return array
		 */
		protected static function get_data( $dbs, $query ) {
			$return_value = array(
				'cols'  => array(),
				'rows'  => array(),
				'error' => '',
			);

			$wpdadb = WPDADB::get_db_connection( $dbs );
			if ( null === $wpdadb ) {
				$return_value['error'] = 'Database connection failed';
				return $return_value;
			}

			$suppress = $wpdadb->suppress_errors( true );

			// Get column info.
			$wpdadb->query(
				"
				create temporary table widget as select * from (
					{$query}
				) resultset limit 0
			"
			);
			if ( '' !== $wpdadb->last_error ) {
				$return_value['error'] = $wpdadb->last_error;
				return $return_value;
			}

			$cols        = $wpdadb->get_results( 'desc widget', 'ARRAY_A' );
			$cols_return = array();
			foreach ( $cols as $col ) {
				$cols_return[] = array(
					'id'    => $col['Field'],
					'label' => $col['Field'],
					'type'  => self::google_charts_type( $col['Type'] ),
				);
			}

			// Perform query.
			$rows        = $wpdadb->get_results( $query, 'ARRAY_A' );
			$rows_return = array();
			foreach ( $rows as $row ) {
				$val   = array();
				$index = 0;
				foreach ( $row as $col ) {
					if ( 'number' === $cols_return[ $index ]['type'] ) {
						if ( is_int( $col ) ) {
							$col = intval( $col );
						} else {
							$col = floatval( $col );
						}
					} elseif (
						'date' === $cols_return[ $index ]['type'] ||
						'datetime' === $cols_return[ $index ]['type']
					) {
						$year  = substr( $col, 0, 4 );
						$month = substr( $col, 5, 2 );
						$day   = substr( $col, 8, 2 );
						if ( 'datetime' === $cols_return[ $index ]['type'] ) {
							$hrs = substr( $col, 11, 2 );
							$min = substr( $col, 14, 2 );
							$sec = substr( $col, 17, 2 );
							$col = "Date($year,$month,$day,$hrs,$min,$sec)";
						} else {
							$col = "Date($year,$month,$day)";
						}
					} elseif (
						'timeofday' === $cols_return[ $index ]['type']
					) {
						$hrs = substr( $col, 0, 2 );
						$min = substr( $col, 3, 2 );
						$sec = substr( $col, 6, 2 );
						$col = "[$hrs,$min,$sec,0]";
					}
					$val[] = array(
						'v' => $col,
					);
					$index++;
				}
				$rows_return[] = array(
					'c' => $val,
				);
			}

			$wpdadb->suppress_errors( $suppress );

			$return_value['cols'] = $cols_return;
			$return_value['rows'] = $rows_return;

			return $return_value;
		}

		/**
		 * Cache query result
		 *
		 * @param array  $cached_data Cached meta data.
		 * @param string $widget_name Widget name.
		 * @param array  $data Cached data.
		 * @return void
		 */
		protected static function write_cache( $cached_data, $widget_name, $data ) {
			// This method is implemented in the premium version.
			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				if (
					isset(
						$cached_data[ $widget_name ],
						$cached_data[ $widget_name ]['filename']
					)
				) {
					unlink( WPDA::get_plugin_upload_dir() . $cached_data[ $widget_name ]['filename'] );
				}

				$filename = bin2hex( openssl_random_pseudo_bytes( mt_rand( 30, 30 ) ) ) . '.cache'; // phpcs:ignore

				$fw = fopen( WPDA::get_plugin_upload_dir() . "{$filename}", 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
				if ( false !== $fw ) {
					fwrite( $fw, $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fwrite
				}
				fclose( $fw ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose

				$cached_data[ $widget_name ] = array(
					'filename'   => $filename,
					'lastupdate' => time(),
					'status'     => 'ready',
				);
				update_option( self::OPTION_CHART_CACHE, $cached_data );
			}
		}

		/**
		 * Read cached data
		 *
		 * @param string $filename File to read data from.
		 * @return false|string|void|null
		 */
		protected static function read_cache( $filename ) {
			// This method is implemented in the premium version.
			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				$fr = fopen( WPDA::get_plugin_upload_dir() . "{$filename}", 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
				if ( false !== $fr ) {
					$file_content = '';

					while ( ! feof( $fr ) ) {
						$file_content = fread( $fr, 1024 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fread
					}
					fclose( $fr ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose

					return $file_content;
				}

				return null;
			}
		}

		/**
		 * Remove cached data for a specific chart widget
		 *
		 * @param array  $cached_data Cached meta data.
		 * @param string $widget_name Widget name.
		 * @return void
		 */
		protected static function remove_cache( $cached_data, $widget_name ) {
			// This method is implemented in the premium version.
			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				if ( false !== $cached_data && isset( $cached_data[ $widget_name ] ) ) {
					if ( isset( $cached_data[ $widget_name ]['filename'] ) ) {
						unlink( WPDA::get_plugin_upload_dir() . $cached_data[ $widget_name ]['filename'] );
					}
					unset( $cached_data[ $widget_name ] );
					update_option( self::OPTION_CHART_CACHE, $cached_data );
				}
			}
		}

		/**
		 * Chart widget implementation
		 *
		 * @return void
		 */
		public static function widget() {
			$panel_name         = isset( $_REQUEST['wpda_panel_name'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpda_panel_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			$panel_dbs          = isset( $_REQUEST['wpda_panel_dbs'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpda_panel_dbs'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			$panel_query        = isset( $_REQUEST['wpda_panel_query'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpda_panel_query'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			$panel_column       = isset( $_REQUEST['wpda_panel_column'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpda_panel_column'] ) ) : '1'; // phpcs:ignore WordPress.Security.NonceVerification
			$column_position    = isset( $_REQUEST['wpda_column_position'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpda_column_position'] ) ) : 'prepend'; // phpcs:ignore WordPress.Security.NonceVerification
			$widget_sequence_nr = isset( $_REQUEST['wpda_widget_sequence_nr'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpda_widget_sequence_nr'] ) ) : '1'; // phpcs:ignore WordPress.Security.NonceVerification

			$wdg = new WPDA_Widget_Google_Chart(
				array(
					'outputtype' => array( 'Table' ),
					'name'       => $panel_name,
					'dbs'        => $panel_dbs,
					'query'      => $panel_query,
					'column'     => $panel_column,
					'position'   => $column_position,
					'widget_id'  => $widget_sequence_nr,
				)
			);

			WPDA::sent_header( 'text/html; charset=UTF-8' );
			echo $wdg->container(); // phpcs:ignore WordPress.Security.EscapeOutput
			wp_die();
		}

		/**
		 * Widget refresh implementation
		 *
		 * @return void
		 */
		public static function refresh() {
			$is_header_send = false;
			$dbs            = null;
			$query          = null;
			$widget_name    = isset( $_POST['wpda_name'] ) ? sanitize_text_field( wp_unslash( $_POST['wpda_name'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification

			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				$dashboard = new \WPDataAccess\Premium\WPDAPRO_Dashboard\WPDAPRO_Dashboard();
				$widget    = $dashboard->get_widget( $widget_name );
				$dbs       = $widget['chartDbs'];
				$query     = wp_unslash( $widget['chartSql'] );

				if ( null === $widget ) {
					WPDA::sent_header( 'application/json', '*' );
					echo static::msg( 'ERROR', 'Invalid arguments' ); // phpcs:ignore WordPress.Security.EscapeOutput
					wp_die();
				} else {
					$is_header_send = self::check_cors( $widget );
				}
			}

			if ( ! $is_header_send ) {
				WPDA::sent_header( 'application/json' );
			}

			if ( ! isset( $_REQUEST['wpda_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				echo static::msg( 'ERROR', 'Invalid arguments' ); // phpcs:ignore WordPress.Security.EscapeOutput
				wp_die();
			}

			if ( null === $dbs || null === $query ) {
				$widgets      = get_user_meta( WPDA::get_current_user_id(), WPDA_Dashboard::USER_DASHBOARD );
				$widget_found = false;
				foreach ( $widgets as $widget ) {
					if ( isset( $widget[ $widget_name ] ) ) {
						$dbs          = $widget[ $widget_name ]['chartDbs'];
						$query        = wp_unslash( $widget[ $widget_name ]['chartSql'] );
						$widget_found = true;
					}
				}
				if ( ! $widget_found ) {
					echo static::msg( 'ERROR', 'Invalid arguments' ); // phpcs:ignore WordPress.Security.EscapeOutput
					wp_die();
				}
			}

			switch ( $_REQUEST['wpda_action'] ) { // phpcs:ignore WordPress.Security.NonceVerification
				case 'get_data':
					if ( ! wpda_freemius()->can_use_premium_code__premium_only() ) {
						echo wp_json_encode( static::get_data( $dbs, $query ) );
					}
					if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
						$cached_data = get_option( self::OPTION_CHART_CACHE );
						if ( 'cache' !== $widget['chartRefresh'] ) {
							echo wp_json_encode( static::get_data( $dbs, $query ) ); // phpcs:ignore WordPress.Security.EscapeOutput
							static::remove_cache( $cached_data, $widget_name );
						} else {
							if ( false === $cached_data ) {
								$cached_data = array();
							}

							$must_update = true;
							if ( isset( $cached_data[ $widget_name ] ) ) {
								if (
									isset(
										$cached_data[ $widget_name ]['filename'],
										$cached_data[ $widget_name ]['lastupdate']
									)
								) {
									$cache = $widget['chartCache'];
									$unit  = $widget['chartUnit'];

									$lastupdate = $cached_data[ $widget_name ]['lastupdate'];
									$delta      = time() - $lastupdate;

									$passed = $cache * 60; // minutes.
									if ( 'hours' === $unit ) {
										$passed *= 60;
									}
									if ( 'days' === $unit ) {
										$passed *= 60 * 24;
									}

									$must_update = $delta > $passed;
								}
							}

							if ( $must_update || isset( $_POST['wpda_force_update'] ) && 'true' === $_POST['wpda_force_update'] ) { // phpcs:ignore WordPress.Security.NonceVerification
								if (
									isset(
										$cached_data[ $widget_name ],
										$cached_data[ $widget_name ]['status']
									)
								) {
									if ( 'updating' === $cached_data[ $widget_name ]['status'] ) {
										$data = static::read_cache( $cached_data[ $widget_name ]['filename'] );
										if ( null === $data ) {
											echo static::msg( 'ERROR', 'Data cache corrupt' ); // phpcs:ignore WordPress.Security.EscapeOutput
										} else {
											echo $data; // phpcs:ignore WordPress.Security.EscapeOutput
										}
										wp_die();
									} else {
										// Prevent multiple simultaneous updates.
										$cached_data[ $widget_name ]['status'] = 'updating';
										update_option( self::OPTION_CHART_CACHE, $cached_data );
									}
								}
								$data = wp_json_encode( static::get_data( $dbs, $query ) );
								static::write_cache( $cached_data, $widget_name, $data );
								echo $data; // phpcs:ignore WordPress.Security.EscapeOutput
							} else {
								$data = static::read_cache( $cached_data[ $widget_name ]['filename'] );
								if ( null === $data ) {
									echo static::msg( 'ERROR', 'Data cache corrupt' ); // phpcs:ignore WordPress.Security.EscapeOutput
								} else {
									echo $data; // phpcs:ignore WordPress.Security.EscapeOutput
								}
							}
						}
					}
					wp_die();
					break;
				case 'refresh':
					// TODO ???
					wp_die();
					break;
				default:
					echo static::msg( 'ERROR', 'Invalid arguments' ); // phpcs:ignore WordPress.Security.EscapeOutput
					wp_die();
			}

		}

		/**
		 * Supported data types for charts
		 *
		 * @param string $data_type Original data type.
		 * @return string
		 */
		public static function google_charts_type( $data_type ) {
			$type = explode( '(', $data_type );
			switch ( $type[0] ) {
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

				case 'date':
					return 'date';

				case 'datetime':
				case 'timestamp':
					return 'datetime';

				case 'time':
					// TODO Timeofday returns an error in Google Charts
					// Workaround = return time as string
					// return 'timeofday';
					// .

				default:
					return 'string';
			}
		}

	}

}
