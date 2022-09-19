<?php

namespace WPDataAccess\Dashboard {

	use WPDataAccess\Data_Tables\WPDA_Data_Tables;
	use WPDataAccess\Plugin_Table_Models\WPDA_Publisher_Model;
	use WPDataAccess\WPDA;

	class WPDA_Widget_Publication extends WPDA_Widget {

		protected $pub_id;
		protected $pub_content;
		protected $table_name;
		protected $search_header;

		public function __construct( $args = array() ) {
			parent::__construct( $args );

			$this->can_share   = true;
			$this->can_refresh = true;

			if ( isset( $args['pub_id'] ) ) {
				$this->pub_id = $args['pub_id'];
				$embedding    = isset( $args['embedding'] ) && true === $args['embedding'];

				$wpda_data_tables  = new WPDA_Data_Tables();
				$this->pub_content = $wpda_data_tables->show( $this->pub_id, '', '', '', '', '', '', '', '', '', '', '', '', true, $embedding );

				$wpda_publication = WPDA_Publisher_Model::get_publication( $this->pub_id );
				$this->table_name = $wpda_publication['0']['pub_table_name'];

				try {
					$json = json_decode( $wpda_publication['0']['pub_table_options_advanced'] );
					if (
						isset( $json->wpda_searchbox ) &&
						( 'header' === $json->wpda_searchbox || 'both' === $json->wpda_searchbox )
					) {
						$this->search_header = true;
					}
				} catch ( \Exception $e ) {
					$this->search_header = false;
				}
			}
		}

		public function do_shortcode( $widget ) {
			// Not implemented (use Data Publisher short code)
		}

		public function do_embed( $widget, $target_element ) {
			// This method is implemented in the premium version
			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				if ( isset( $widget['pubId'] ) ) {
					?>
					setTimeout(waitForPubLibs<?php echo esc_attr( $target_element ); ?>, 1000);
					function waitForPubLibs<?php echo esc_attr( $target_element ); ?>() {
						if (window.jQuery && typeof jQuery.ui!=="undefined" && typeof wpda_datatables_ajax_call==="function") {
							jQuery('#<?php echo esc_attr( $target_element ); ?>').append(jQuery(`<?php echo wp_slash( $this->pub_content ); // phpcs:ignore WordPress.Security.EscapeOutput ?>`));
							// console.log("WP Data Access publication libraries loaded...");
						} else {
							setTimeout(waitForPubLibs<?php echo esc_attr( $target_element ); ?>, 1000);
							console.log("Waiting for WP Data Access publication libraries to be loaded...");
						}
					}
					<?php
				} else {
					?>
					console.log("WP Data Access ERROR: Invalid widget data");
					<?php
				}
			}
		}

		protected function js( $is_backend = true ) {
			?>
			<script type="application/javascript">
				jQuery(function() {
					jQuery("#wpda-widget-<?php echo esc_attr( $this->widget_id ); ?>").find(".wpda-widget-refresh").on("click", function(e, action = null) {
						if (action==="refresh") {
							jQuery("#<?php echo esc_attr( $this->table_name ) . esc_attr( $this->pub_id ); ?>").DataTable().ajax.json();
						} else {
							jQuery("#<?php echo esc_attr( $this->table_name ) . esc_attr( $this->pub_id ); ?>").DataTable().draw("page");
						}
						jQuery("#<?php echo esc_attr( $this->table_name ) . esc_attr( $this->pub_id ); ?>").DataTable().responsive.recalc();
						<?php
						if ( $this->search_header && $is_backend ) {
							?>
							post_publication_widget("<?php echo esc_attr( $this->table_name ); ?>", "<?php echo esc_attr( $this->pub_id ); ?>");
							<?php
						}
						?>
					});
				});
			</script>
			<?php
			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				if ( $is_backend ) {
					$obj        = new \stdClass();
					$obj->pubId = esc_attr( $this->pub_id );
					?>
					<script type='application/javascript' class="wpda-widget-<?php echo esc_attr( $this->widget_id ); ?>">
						jQuery(function() {
							setTimeout(function() {
								addDashboardWidget(
									<?php echo esc_attr( $this->widget_id ); ?>,
									"<?php echo esc_attr( $this->name ); ?>",
									'pub',
									<?php echo json_encode( $this->share ); ?>,
									<?php echo json_encode( $obj ); ?>,
									false
								);

								jQuery("#wpda-widget-<?php echo esc_attr( $this->widget_id ); ?>").find(".wpda-widget-share").on("click", function(e) {
									shareWidget(<?php echo esc_attr( $this->widget_id ); ?>);
									e.stopPropagation();
								});
							}, 500);
						});
					</script>
					<?php
				}
			}
		}

		protected function container() {
			ob_start();

			echo parent::container(); // phpcs:ignore WordPress.Security.EscapeOutput
			$post_publication = $this->search_header ? "post_publication_widget('{$this->table_name}', '{$this->pub_id}');" : '';
			$post_content     = "
				<div id='wpda-panel-pub-id-{$this->pub_id}' style='display: block'>
					{$this->pub_content}
				</div>
				<script type='application/javascript'>
				jQuery(function() {
					setTimeout(waitUntilWidgetIsLoaded_{$this->pub_id}, 1000);
					function waitUntilWidgetIsLoaded_{$this->pub_id}() {
						if (jQuery('#wpda-widget-{$this->widget_id} div.ui-widget-content').length>0) {
							jQuery('#wpda-widget-{$this->widget_id} div.ui-widget-content').html('');
							jQuery('#wpda-panel-pub-id-{$this->pub_id}').appendTo(jQuery('#wpda-widget-{$this->widget_id} div.ui-widget-content'));
							jQuery('#{$this->table_name}{$this->pub_id}').DataTable().responsive.recalc();
							{$post_publication}
							// console.log('WP Data Access publication libraries loaded...');
						} else {
							setTimeout(waitUntilWidgetIsLoaded_{$this->pub_id}, 1000);
							console.log('Waiting for WP Data Access publication libraries to be loaded...');
						}
					}
				});
				</script>
			";
			echo $post_content; // phpcs:ignore WordPress.Security.EscapeOutput

			return ob_get_clean();
		}

		public static function widget() {
			$panel_name         = isset( $_REQUEST['wpda_panel_name'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpda_panel_name'] ) ) : ''; // input var okay.;
			$panel_pub_id       = isset( $_REQUEST['wpda_panel_pub_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpda_panel_pub_id'] ) ) : ''; // input var okay.;
			$panel_column       = isset( $_REQUEST['wpda_panel_column'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpda_panel_column'] ) ) : '1'; // input var okay.;
			$column_position    = isset( $_REQUEST['wpda_column_position'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpda_column_position'] ) ) : 'prepend'; // input var okay.;
			$widget_sequence_nr = isset( $_REQUEST['wpda_widget_sequence_nr'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpda_widget_sequence_nr'] ) ) : '1'; // input var okay.;

			$wdg = new WPDA_Widget_Publication(
				array(
					'name'      => $panel_name,
					'pub_id'    => $panel_pub_id,
					'column'    => $panel_column,
					'position'  => $column_position,
					'widget_id' => $widget_sequence_nr,
				)
			);

			WPDA::sent_header( 'text/html; charset=UTF-8' );
			echo $wdg->container(); // phpcs:ignore WordPress.Security.EscapeOutput
			wp_die();
		}

		public static function refresh() {
			echo static::msg( 'ERROR', 'Method not available for this panel type' ); // phpcs:ignore WordPress.Security.EscapeOutput
			wp_die();
		}

	}

}
