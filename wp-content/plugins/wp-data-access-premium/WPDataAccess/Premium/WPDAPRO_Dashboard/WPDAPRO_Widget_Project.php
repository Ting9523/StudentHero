<?php

namespace WPDataAccess\Premium\WPDAPRO_Dashboard {

	use WPDataAccess\Dashboard\WPDA_Widget;
	use WPDataAccess\Premium\WPDAPRO_Data_Forms\WPDAPRO_Data_Forms;
	use WPDataAccess\Premium\WPDAPRO_Data_Forms\WPDAPRO_Data_Project;
	use WPDataAccess\WPDA;

	class WPDAPRO_Widget_Project extends WPDA_Widget {

		protected $project_id;
		protected $page_id;
		protected $project_content;

		public function __construct( $args = [] ) {
			parent::__construct( $args );

			$this->can_share   = true;
			$this->can_refresh = true;

			$this->project_id = isset( $args['project_id'] ) ? $args['project_id'] : null;
			$this->page_id    = isset( $args['page_id'] ) ? $args['page_id'] : null;

			$project_args = [];
			if ( isset( $args['embedding'] ) && true === $args['embedding'] ) {
				$project_args['embedding'] = true;
			}

			if ( null !== $this->project_id || null !== $this->page_id ) {
				ob_start();

				if ( null === $this->page_id ) {
					// Add data project
					$project = new WPDAPRO_Data_Project( $this->project_id, $project_args );
					$project->show();
				} else {
					// Add data project page
					$form = new WPDAPRO_Data_Forms( $this->project_id, $this->page_id, $project_args );
					$form->show();
				}

				$this->project_content = ob_get_clean();
			}
		}

		public function do_shortcode( $widget ) {
			// Not implemented (use Data Publisher short code)
		}

		public function do_embed( $widget, $target_element ) {
			// This method is implemented in the premium version
			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				if ( isset( $widget['projectId'] ) ) {
					?>
					setTimeout(waitForFormsLibs<?php echo esc_attr( $target_element ); ?>, 1000);
					function waitForFormsLibs<?php echo esc_attr( $target_element ); ?>() {
						if (window.jQuery && typeof jQuery.ui!=="undefined" && typeof angular!=='undefined' && typeof wpdadataforms_table==="function") {
							jQuery('#<?php echo $target_element; ?>').append(jQuery(`<?php echo wp_slash( $this->project_content ); ?>`));
							console.log("WP Data Access Data Forms libraries loaded...");
						} else {
							setTimeout(waitForFormsLibs<?php echo esc_attr( $target_element ); ?>, 1000);
							console.log("Waiting for WP Data Access Data Forms libraries to be loaded...");
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
			if ( $is_backend ) {
				$obj = new \stdClass();
				$obj->projectId = esc_attr( $this->project_id );
				?>
				<script type='application/javascript' class="wpda-widget-<?php echo $this->widget_id; ?>">
					jQuery(function() {
						setTimeout(function() {
							addDashboardWidget(
								<?php echo esc_attr( $this->widget_id ); ?>,
								"<?php echo esc_attr( $this->name ); ?>",
								'project',
								<?php echo json_encode( $this->share ); ?>,
								<?php echo json_encode( $obj ); ?>,
								false
							);

							jQuery("#wpda-widget-<?php echo esc_attr( $this->widget_id ); ?>").find(".wpda-widget-share").on("click", function() {
								shareWidget(<?php echo esc_attr( $this->widget_id ); ?>);
							});
						}, 500);
					});
				</script>
				<?php
			}
		}

		protected function container() {
			ob_start();

			echo parent::container();
			echo "
				<div id='wpda-panel-project-id-{$this->project_id}' style='display: block'>
					{$this->project_content}
				</div>
				<script type='application/javascript'>
					setTimeout(waitUntilWidgetIsLoaded_{$this->widget_id}, 1000);
					function waitUntilWidgetIsLoaded_{$this->widget_id}() {
						if (jQuery('#wpda-widget-{$this->widget_id} div.ui-widget-content').length>0) {
							jQuery('#wpda-widget-{$this->widget_id} div.ui-widget-content').html('');
							jQuery('#wpda-panel-project-id-{$this->project_id}').appendTo(jQuery('#wpda-widget-{$this->widget_id} div.ui-widget-content'));
							// console.log('WP Data Access project libraries loaded...');
						} else {
							setTimeout(waitUntilWidgetIsLoaded_{$this->widget_id}, 1000);
							console.log('Waiting for WP Data Access project libraries to be loaded...');
						}
					}
				</script>
			";

			return ob_get_clean();
		}

		public static function widget() {
			$panel_name         = isset( $_REQUEST['wpda_panel_name'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpda_panel_name'] ) ) : ''; // input var okay.;
			$panel_project_id   = isset( $_REQUEST['wpda_panel_project_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpda_panel_project_id'] ) ) : null; // input var okay.;
			$panel_page_id      = isset( $_REQUEST['wpda_panel_page_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpda_panel_page_id'] ) ) : null; // input var okay.;
			$panel_column       = isset( $_REQUEST['wpda_panel_column'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpda_panel_column'] ) ) : '1'; // input var okay.;
			$column_position    = isset( $_REQUEST['wpda_column_position'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpda_column_position'] ) ) : 'prepend'; // input var okay.;
			$widget_sequence_nr = isset( $_REQUEST['wpda_widget_sequence_nr'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpda_widget_sequence_nr'] ) ) : '1'; // input var okay.;

			$wdg = new WPDAPRO_Widget_Project([
				'name'		  => $panel_name,
				'project_id'  => $panel_project_id,
				'page_id'     => $panel_page_id,
				'column'	  => $panel_column,
				'position'	  => $column_position,
				'widget_id'	  => $widget_sequence_nr,
			]);

			WPDA::sent_header('text/html; charset=UTF-8');
			echo $wdg->container();
			wp_die();
		}

		public static function refresh() {
			echo static::msg('ERROR', 'Method not available for this panel type');
			wp_die();
		}
	}

}