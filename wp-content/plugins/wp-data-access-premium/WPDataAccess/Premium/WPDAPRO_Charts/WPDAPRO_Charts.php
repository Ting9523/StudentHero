<?php

namespace WPDataAccess\Premium\WPDAPRO_Charts {

	use WPDataAccess\Dashboard\WPDA_Dashboard;
	use WPDataAccess\Dashboard\WPDA_Widget;
	use WPDataAccess\Utilities\WPDA_Message_Box;
	use WPDataAccess\Wordpress_Original\WP_List_Table;
	use WPDataAccess\Premium\WPDAPRO_Dashboard\WPDAPRO_Dashboard;
	use WPDataAccess\WPDA;

	class WPDAPRO_Charts extends WP_List_Table {

		static protected $list_number = 0;

		protected $charts             = [];
		protected $charts_export_list = [];

		public function __construct( $args = [] ) {
			parent::__construct([
				'singular' => 'Chart',
				'plural'   => 'Charts',
				'ajax'     => false,
			]);

			if ( isset( $_POST['wpnonce'], $_POST['action'] ) ) {
				$wpnonce = isset( $_POST['wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wpnonce'] ) ) : '';
				if ( 'delete' === $_POST['action'] && isset( $_POST['chart_name'] ) ) {
					// Check permission.
					if ( ! wp_verify_nonce( $wpnonce, WPDA_Dashboard::DASHBOARD_SAVE . WPDA::get_current_user_login() ) ) {
						wp_die( __( 'ERROR: Not authorized', 'wp-data-access' ) );
					}

					// Delete chart.
					$chart_name = sanitize_text_field( $_POST['chart_name'] ); // No sanitization needed: just check value
					$dashboard  = new WPDAPRO_Dashboard();
					$dashboard->del_widget( $chart_name );
					$dashboard->save_dashboard();

					$msg = new WPDA_Message_Box(
						array(
							'message_text' => 'Chart deleted'
						)
					);
					$msg->box();
				} elseif ( 'import' === $_POST['action'] ) {
					// Check permission.
					if ( ! wp_verify_nonce( $wpnonce, 'wpda-import-charts-' . WPDA::get_current_user_login() ) ) {
						wp_die( __( 'ERROR: Not authorized', 'wp-data-access' ) );
					}

					// Import charts.
					if ( isset( $_FILES['filename'] ) ) {
						// phpcs:disable
						$temp_file_name = sanitize_text_field( $_FILES['filename']['tmp_name'] ); // For Windows: do NOT unslash!
						// phpcs:enable
						if ( UPLOAD_ERR_OK === $_FILES['filename']['error']
							&& is_uploaded_file( $temp_file_name )
						) {
							// Get file content.
							$file    = fopen( $temp_file_name, "r" );
							$content = fread( $file, filesize( $temp_file_name ) );
							fclose( $file );

							try {
								$json = json_decode( $content, true );
							} catch ( \Exception $e ) {
								$json = [];
							}

							$dashboard  = new WPDAPRO_Dashboard();
							$no_charts  = count( $json );
							$imported   = 0;
							foreach ( $json as $chart_name => $chart ) {
								if ( isset( $_POST['overwrite_charts'] ) && 'on' === $_POST['overwrite_charts'] ) {
									$dashboard->add_widget( $chart );
									$imported++;
								} else {
									if ( null === $dashboard->get_widget( $chart_name ) ) {
										$dashboard->add_widget( $chart );
										$imported++;
									}
								}
							}
							$dashboard->save_dashboard();

							$msg = new WPDA_Message_Box(
								array(
									'message_text' => "{$imported} from {$no_charts} charts imported"
								)
							);
							$msg->box();
						}
					} else {
						// File upload failed: inform user.
						$msg = new WPDA_Message_Box(
							array(
								'message_text'           => __( 'File upload failed', 'wp-data-access' ),
								'message_type'           => 'error',
								'message_is_dismissible' => false,
							)
						);
						$msg->box();
					}
				}
			}

			$search		= isset( $_REQUEST['s'] ) && '' !== $_REQUEST['s'] ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : null;
			$dashboards = new WPDAPRO_Dashboard();
			foreach ( $dashboards->get_widget_list() as $widget_name => $widget ) {
				if (
					'chart' === $widget['widgetType'] &&
					( null === $search || false !== stripos( $widget_name, $search ) )
				) {
					$this->charts[ $widget_name ] = $widget;
				}
			}
		}

		public function prepare_items() {
			$columns				= $this->get_columns();
			$hidden					= [];
			$sortable 				= $this->get_sortable_columns();
			$this->_column_headers	= array($columns, $hidden, $sortable);
			$data					= [];

			foreach ( $this->charts as $chart_name => $chart ) {
				$shares = 'Dashboard roles: ';
				if ( isset( $chart['widgetShare']['roles'] ) && '' !== $chart['widgetShare']['roles'] ) {
					$shares .= $chart['widgetShare']['roles'];
				} else {
					$shares .= 'none';
				}
				$shares .= "\n";

				$shares .= 'Dashboard users: ';
				if ( isset( $chart['widgetShare']['users'] ) && '' !== $chart['widgetShare']['users'] ) {
					$shares .= $chart['widgetShare']['users'];
				} else {
					$shares .= 'none';
				}
				$shares .= "\n";

				$shares .= 'Shortcode: ';
				if ( isset( $chart['widgetShare']['page'] ) && 'true' === $chart['widgetShare']['page'] ) {
					$shares   .= 'page';
					$has_page = true;
				} else {
					$has_page = false;
				}
				if ( isset( $chart['widgetShare']['post'] ) && 'true' === $chart['widgetShare']['post'] ) {
					if ( $has_page ) {
						$shares .= ', post';
					} else {
						$shares .= 'post';
					}
				}
				$shares .= "\n";

				$shares .= 'Longcode: ';
				if ( isset( $chart['widgetShare']['embed'] ) ) {
					if ( '*' === $chart['widgetShare']['embed'] ) {
						$shares .= 'allow';
					} else {
						$shares .= $chart['widgetShare']['embed'];
					}
				}

				$data[] = [
					'chart'		=> $chart_name,
					'shares'	=> $shares,
					'type'		=> implode( ', ', $chart['chartType'] ),
					'cached'	=> 'cache' === $chart['chartRefresh'] ?
									$chart['chartCache'] . ' ' . $chart['chartUnit'] :
									$chart['chartRefresh'] . ' (not cached)'
				];
			}

			$per_page     	= 10;
			$current_page 	= $this->get_pagenum();
			$total_items	= count( $data );
			$data			= array_slice( $data, ( ( $current_page - 1 ) * $per_page ) , $per_page );
			$this->items	= $data;

			$this->set_pagination_args([
				'total_items'	=> $total_items,
				'per_page'	    => $per_page,
				'total_pages'	=> ceil( $total_items / $per_page )
			]);
		}

		public function show() {
			$this->prepare_items();
			$this->prepare_export();
			?>
			<div class="wrap">
				<h1 class="wp-heading-inline">
					Premium Charts
				</h1>
				<div id="wpda_new_chart" style="display:none" class="wpda_new_chart_container">
					<div id="wpda_new_chart_container"></div>
				</div>
				<?php
				$this->add_container();
				?>
				<div>
					<form method="post">
						<input type="hidden" name="page" value="wpda_charts" />
						<?php
							$this->search_box( __( 'search', 'wp-data-access' ), 'search_id' );
							$this->display();
						?>
					</form>
				</div>
			</div>
			<form id="wpda_delete_chart" method="post" style="display:none">
				<input type="hidden" name="wpnonce" value="<?php echo esc_attr( wp_create_nonce( WPDA_Dashboard::DASHBOARD_SAVE . WPDA::get_current_user_login() ) ); ?>"/>
				<input type="hidden" name="action" value="delete"/>
				<input type="hidden" name="chart_name" id="delete_form_chart_name" value=""/>
			</form>
			<?php
			(new WPDA_Dashboard())->dashboard_js();
			?>
			<script>
				let charts = <?php echo json_encode( $this->charts ); ?>;
				let chartsExportList = <?php echo json_encode( $this->charts_export_list ); ?>;

				function createNewChart(chartName) {
					jQuery("#wpda_new_chart_container").empty().append("<div/>").attr("id", "wpda-dashboard-column-new");
					jQuery("#wpda_add_chart").fadeOut(400);
					jQuery("#wpda_new_chart").fadeIn(800);

					jQuery.ajax({
						type: "POST",
						url: wpda_dashboard_vars.wpda_ajaxurl + "?action=wpda_widget_chart_add",
						data: {
							wp_nonce: "<?php echo esc_attr( wp_create_nonce( WPDA_Widget::WIDGET_ADD . WPDA::get_current_user_login() ) ); ?>",
							wpda_panel_name: chartName,
							wpda_panel_dbs: null,
							wpda_panel_query: null,
							wpda_panel_column: "new",
							wpda_column_position: "append",
							wpda_widget_sequence_nr: 9999
						}
					}).done(
						function(data) {
							jQuery("#wpbody-content").append(data);
							setTimeout(function() {
								jQuery("#wpda-widget-9999 .wpda-widget-close").off().on("click", function() {
									// Redefine close icon
									window.location.href = window.location.href;
								})
							}, 500);
						}
					);
				}

				function loadChart(chartName, rowNumber) {
					jQuery.ajax({
						type: "POST",
						url: wpda_dashboard_vars.wpda_ajaxurl + "?action=wpda_edit_chart",
						data: {
							wpda_wpnonce: wpda_wpnonce_refresh,
							wpda_chart_name: chartName,
							wpda_chart_id: rowNumber,
						}
					}).done(
						function(response) {
							if (typeof response === 'string') {
								jQuery("#wpda_edit_chart_container_" + rowNumber).append(response);
								jQuery("#wpda-widget-" + rowNumber).data("name", chartName);
								jQuery("#wpda-widget-" + rowNumber + " .wpda-widget-close").off().on("click", function() {
									// Redefine close icon
									jQuery("#rownum_" + rowNumber + "_2").hide();
								});
							} else {
								if (response.status==="ERROR" && response.msg!==undefined) {
									alert(response.msg);
								}
							}
						}
					).fail(
						function (response) {
							console.log("WP Data Access error (loadChart):", response);
						}
					);
				}

				function editChart(chartName, rowNumber) {
					if (jQuery("#rownum_" + rowNumber + "_2").is(":visible")) {
						jQuery("#rownum_" + rowNumber + "_2").hide();
					} else {
						jQuery("#wpda_edit_chart_container_" + rowNumber).empty();

						if (charts[chartName]) {
							loadChart(chartName, rowNumber);
						} else {
							alert("ERROR: Chart not found!");
						}

						jQuery("#rownum_" + rowNumber + "_2").show();
					}
				}

				function deleteChartAction(chartName) {
					if (confirm("Are you sure you want to deletre this chart?\n\nThis action cannot be undone!")) {
						jQuery("#delete_form_chart_name").val(chartName);
						jQuery("#wpda_delete_chart").submit();
					}
				}

				jQuery(function() {
					jQuery("#search_id-search-input").attr("placeholder", "Chart name only");
					if (Object.keys(chartsExportList).length>0) {
						// Export selected charts
						let json = JSON.stringify(chartsExportList);
						let blob = new Blob([json]);
						let link = document.createElement('a');
						link.href = window.URL.createObjectURL(blob);
						link.download = "wpda_chart_export.json";
						link.click();
					}
				});
			</script>
			<?php
		}

		public function single_row( $item ) {
			?>
			<tr id="rownum_<?php echo self::$list_number; ?>">
			<?php
				$this->single_row_columns( $item );
			?>
			</tr>

			<tr style="display:none">
				<td colspan="5"></td>
			</tr>

			<tr id="rownum_<?php echo self::$list_number; ?>_2" style="display:none">
				<td colspan="5" id="wpda_edit_chart_container_<?php echo self::$list_number; ?>" style="overflow:auto"></td>
			</tr>

			<?php
			self::$list_number++;
		}

		public function column_default( $item, $column_name ) {
			switch( $column_name ) {
				case 'chart':
					$actions['edit'] = sprintf(
						'<a href="javascript:void(0)" 
                                    class="edit wpda_tooltip"
                                    title="Edit chart"
                                    onclick="editChart(\'%s\', %d)">
                                    <span style="white-space: nowrap">
										<i class="fas fa-pen wpda_icon_on_button"></i>
										%s
                                    </span>
                                </a>
                                ',
						esc_attr( $item['chart'] ),
						$this::$list_number,
						__( 'Edit', 'wp-data-access' )
					);
					$actions['delete'] = sprintf(
						'<a href="javascript:void(0)" 
                                    class="delete wpda_tooltip"
                                    title="Delete chart"
                                    onclick="deleteChartAction(\'%s\')">
                                    <span style="white-space: nowrap">
										<i class="fas fa-trash wpda_icon_on_button"></i>
										%s
                                    </span>
                                </a>
                                ',
						$item['chart'],
						__( 'Delete', 'wp-data-access' )
					);

					return sprintf( '%1$s %2$s', $item[ $column_name ], $this->row_actions( $actions ) );
				case 'shares':
				case 'type':
				case 'cached':
					return str_replace( "\n", '<br/>', esc_html( $item[ $column_name ] ) );
			}
		}

		public function get_columns() {
			return [
				'cb'        => '<input type="checkbox" />',
				'chart'		=> 'Chart Name',
				'shares'	=> 'Shares',
				'type'		=> 'Chart Types',
				'cached'	=> 'Cached'
			];
		}

		public function column_cb( $item ) {
			return sprintf(
				'<input type="checkbox" name="chart[]" value="%s" />', $item['chart']
			);
		}

		public function get_bulk_actions() {
			$actions['bulk-export'] = __( 'Export', 'wp-data-access' );
			return $actions;
		}

		private function prepare_export() {
			if (
				isset( $_POST['_wpnonce'], $_POST['action'], $_POST['chart'] ) &&
				'bulk-export' === $_POST['action']
			) {
				if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'bulk-' . $this->_args['plural'] ) ) {
					wp_die( __( 'ERROR: Not authorized', 'wp-data-access' ) );
				} else {
					$charts_to_be_exported = WPDA::sanitize_text_field_array( $_POST['chart'] );
					$charts_export_list    = [];
					foreach ( $this->charts as $chart_name => $chart ) {
						if ( in_array( $chart_name, $charts_to_be_exported ) ) {
							$charts_export_list[ $chart_name ] = $chart;
						}
					}
					$this->charts_export_list = $charts_export_list;
				}
			}
		}

		private function add_container() {
			$file_uploads_enabled = @ini_get( 'file_uploads' );
			?>
			<script type='text/javascript'>
				function before_submit_upload() {
					if (jQuery('#filename').val() == '') {
						alert('<?php echo __( 'No file to import!', 'wp-data-access' ); ?>');
						return false;
					}
					if (!(jQuery('#filename')[0].files[0].size < <?php echo esc_attr( WPDA::convert_memory_to_decimal( @ini_get( 'upload_max_filesize' ) ) ); ?>)) {
						alert("<?php echo __( 'File exceeds maximum size of', 'wp-data-access' ); ?> <?php echo esc_attr( @ini_get( 'upload_max_filesize' ) ); ?>!");
						return false;
					}
				}
			</script>
			<div id="upload_file_container" style="display: none">
				<div>&nbsp;</div>
				<div>
					<?php if ( $file_uploads_enabled ) { ?>
						<form id="form_import_table" method="post" enctype="multipart/form-data">
							<fieldset class="wpda_fieldset" style="position:relative;padding:20px;padding-top:10px;padding-bottom:10px">
								<legend>
								<span>
									<?php echo 'SUPPORTS ONLY CHART IMPORTS'; ?>
								</span>
								</legend>
								<p>
									<?php
									echo __( 'Supports only file type', 'wp-data-access' ) . ' <strong>json</strong>. ' . __( 'Maximum supported file size is', 'wp-data-access' ) . ' <strong>' . esc_attr( @ini_get( 'upload_max_filesize' ) ) . '</strong>.';
									?>
								</p>
								<input type="file" name="filename" id="filename" class="wpda_tooltip" accept=".json">
								<label style="vertical-align:baseline;">
									<input type="checkbox" name="overwrite_charts" style="vertical-align:sub;" checked>
									Overwrite existing charts?
								</label>
								<p>
									<button type="submit"
											class="button button-primary"
											onclick="return before_submit_upload()">
										<i class="fas fa-code wpda_icon_on_button"></i>
										<?php echo __( 'Import file', 'wp-data-access' ); ?>
									</button>
									<button type="button"
											onclick="jQuery('#upload_file_container').hide()"
											class="button button-secondary">
										<i class="fas fa-times-circle wpda_icon_on_button"></i>
										<?php echo __( 'Cancel', 'wp-data-access' ); ?>
									</button>
								</p>
								<input type="hidden" name="action" value="import">
								<?php wp_nonce_field( 'wpda-import-charts-' . WPDA::get_current_user_login(), 'wpnonce', false ); ?>
							</fieldset>
						</form>
					<?php } else { ?>
						<p>
							<strong><?php echo __( 'ERROR', 'wp-data-access' ); ?></strong>
						</p>
						<p class="wpda_list_indent">
							<?php
							echo __( 'Your configuration does not allow file uploads!', 'wp-data-access' );
							echo ' ';
							echo __( 'Set', 'wp-data-access' );
							echo ' <strong>';
							echo __( 'file_uploads', 'wp-data-access' );
							echo '</strong> ';
							echo __( 'to', 'wp-data-access' );
							echo ' <strong>';
							echo __( 'On', 'wp-data-access' );
							echo '</strong> (<a href="https://wpdataaccess.com/docs/documentation/getting-started/known-limitations/">';
							echo __( 'see documentation', 'wp-data-access' );
							echo '</a>).';
							?>
						</p>
					<?php } ?>
				</div>
				<div>&nbsp;</div>
			</div>
			<?php
		}

	}

}