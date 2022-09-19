<?php

namespace WPDataAccess\Premium\WPDAPRO_Data_Forms {

	use WPDataAccess\Premium\WPDAPRO_Data_Publisher\WPDAPRO_Data_Publisher_Init;
	use WPDataAccess\WPDA;

	class WPDAPRO_Data_Forms_Init {

		const DF_JQUERYUI = 'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/';
		const DF_ANGULAR  = 'https://cdnjs.cloudflare.com/ajax/libs/angular.js/1.8.2/angular.min.js';

		public static function enqueue_styles() {
			$jquery_ui_styles = WPDA::get_option( WPDA::WPDA_DT_UI_THEME_DEFAULT );
			wp_register_style(
				'wpdapro_forms_jquery-ui-theme_default',
				self::DF_JQUERYUI . "themes/{$jquery_ui_styles}/jquery-ui.min.css",
				array(),
				null,
				false
			);

			wp_register_style(
				'wpdapro_forms',
				plugins_url( '../../../assets/premium/css/wpda_data_forms.css', __FILE__ ),
				[],
				WPDA::get_option( WPDA::OPTION_WPDA_VERSION )
			);
		}

		public static function enqueue_scripts() {
			wp_register_script(
				'wpdapro_angularjs',
				self::DF_ANGULAR,
				array(),
				null,
				false
			);

			wp_register_script(
				'wpdapro_forms_jquery_ui_datatables',
				WPDAPRO_Data_Publisher_Init::JDT_JQUERYUI . 'js/dataTables.jqueryui.min.js',
				array(),
				null,
				false
			);

			wp_register_script(
				'wpdapro_forms',
				plugins_url( '../../../assets/premium/js/wpda_data_forms.js', __FILE__ ),
				[],
				WPDA::get_option( WPDA::OPTION_WPDA_VERSION )
			);

			wp_register_script(
				'wpdapro_forms_datatable',
				plugins_url( '../../../assets/premium/js/wpda_data_forms_datatable.js', __FILE__ ),
				[],
				WPDA::get_option( WPDA::OPTION_WPDA_VERSION )
			);

			wp_register_script(
				'wpdapro_forms_dataentry',
				plugins_url( '../../../assets/premium/js/wpda_data_forms_dataentry.js', __FILE__ ),
				[],
				WPDA::get_option( WPDA::OPTION_WPDA_VERSION )
			);
		}

		public static function activate_styles() {
			// Activate styles
			wp_enqueue_style( 'dashicons' );
			if ( WPDA::get_option( WPDA::OPTION_FE_LOAD_DATATABLES_RESPONSE ) === 'on' ) {
				wp_enqueue_style( 'jquery_datatables_responsive' );
			}
			wp_enqueue_style( 'wpda_jqueryui_theme_structure' );
			if ( ! is_admin() ) {
				wp_enqueue_style( 'wpdapro_forms_jquery-ui-theme_default' );
			}

			// Register Data Forms style
			wp_enqueue_style( 'wpdapro_forms' );
		}

		public static function activate_scripts() {
			// Activate scripts
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'jquery-ui-core' );
			wp_enqueue_script( 'jquery-ui-dialog' );
			wp_enqueue_script( 'jquery-ui-tabs' );
			wp_enqueue_script( 'jquery-ui-sortable' );
			wp_enqueue_script( 'jquery-ui-tooltip' );
			wp_enqueue_script( 'jquery-ui-droppable' );
			wp_enqueue_script( 'jquery-ui-draggable' );
			wp_enqueue_script( 'jquery-ui-menu' );
			if ( WPDA::get_option( WPDA::OPTION_FE_LOAD_DATATABLES ) === 'on' ) {
				wp_enqueue_script( 'jquery_datatables' );
			}
			if ( WPDA::get_option( WPDA::OPTION_FE_LOAD_DATATABLES_RESPONSE ) === 'on' ) {
				wp_enqueue_script( 'jquery_datatables_responsive' );
			}
			wp_enqueue_script( 'wpdapro_forms_jquery_ui_datatables' );
			wp_enqueue_script( 'wpdapro_angularjs' );

			// Register javascript Data Forms libraries
			wp_enqueue_script( 'wpdapro_forms' );
			wp_enqueue_script( 'wpdapro_forms_datatable' );
			wp_enqueue_script( 'wpdapro_forms_dataentry' );

			// Add button extensions
			wp_enqueue_style( 'wpdapro_jquery_datatables_select' );
			wp_enqueue_script( 'wpdapro_jquery_datatables_buttons' );
			wp_enqueue_script( 'wpdapro_jquery_datatables_flash' );
			wp_enqueue_script( 'wpdapro_jquery_datatables_jszip' );
			wp_enqueue_script( 'wpdapro_jquery_datatables_pdfmake' );
			wp_enqueue_script( 'wpdapro_jquery_datatables_vfs_fonts' );
			wp_enqueue_script( 'wpdapro_jquery_datatables_html5' );
			wp_enqueue_script( 'wpdapro_jquery_datatables_print' );
			wp_enqueue_script( 'wpdapro_jquery_datatables_select' );
			wp_enqueue_script( 'wpdapro_jquery_datatables_colvis' );
			wp_enqueue_script( 'wpdapro_jquery_datatables_premium' );
		}

		public function shortcode_wpdadataproject( $atts ) {
			$editing = WPDA::is_editing_post();
			if ( false !== $editing ) {
				// Prevent errors when user is editing a post
				return $editing;
			}

			if ( 'on' !== WPDA::get_option( WPDA::OPTION_PLUGIN_WPDADATAFORMS_POST ) ) {
				if ( $this->is_post() ) {
					return '<p>' . __( 'Sorry, you cannot use shortcode wpdadataproject in a post!', 'wp-data-access' ) . '</p>';
				}
			}

			if ( 'on' !== WPDA::get_option( WPDA::OPTION_PLUGIN_WPDADATAFORMS_PAGE ) ) {
				if ( $this->is_page() ) {
					return '<p>' . __( 'Sorry, you cannot use shortcode wpdadataproject in a page!', 'wp-data-access' ) . '</p>';
				}
			}

			if (
				'on' !== WPDA::get_option( WPDA::OPTION_PLUGIN_WPDADATAFORMS_ALLOW_ANONYMOUS_ACCESS ) &&
				0 === get_current_user_id()
			) {
				return '<p>' . __( 'Anonymous access disabled!', 'wp-data-access' ) . '</p>';
			}

			$atts    = array_change_key_case( (array) $atts, CASE_LOWER );
			$wp_atts = shortcode_atts(
				[
					'project_id'           => '',
				], $atts
			);

			if ( '' === $wp_atts['project_id'] ) {
				return __( 'ERROR: Missing argument(s) [need a valid project_id]', 'wp-data-access' );
			}

			ob_start();

			$project = new WPDAPRO_Data_Project( $wp_atts['project_id'] );
			$project->show();

			return ob_get_clean();
		}

		public function shortcode_wpdadataforms( $atts ) {
			$editing = WPDA::is_editing_post();
			if ( false !== $editing ) {
				// Prevent errors when user is editing a post
				return $editing;
			}

			if ( 'on' !== WPDA::get_option( WPDA::OPTION_PLUGIN_WPDADATAFORMS_POST ) ) {
				if ( $this->is_post() ) {
					return '<p>' . __( 'Sorry, you cannot use shortcode wpdadataforms in a post!', 'wp-data-access' ) . '</p>';
				}
			}

			if ( 'on' !== WPDA::get_option( WPDA::OPTION_PLUGIN_WPDADATAFORMS_PAGE ) ) {
				if ( $this->is_page() ) {
					return '<p>' . __( 'Sorry, you cannot use shortcode wpdadataforms in a page!', 'wp-data-access' ) . '</p>';
				}
			}

			if (
				'on' !== WPDA::get_option( WPDA::OPTION_PLUGIN_WPDADATAFORMS_ALLOW_ANONYMOUS_ACCESS ) &&
				0 === get_current_user_id()
			) {
				return '<p>' . __( 'Anonymous access disabled!', 'wp-data-access' ) . '</p>';
			}

			$atts    = array_change_key_case( (array) $atts, CASE_LOWER );
			$wp_atts = shortcode_atts(
				[
					'project_id'           => '',
					'page_id'              => '',
				], $atts
			);

			if ( '' === $wp_atts['project_id'] && '' === $wp_atts['page_id'] ) {
				return __( 'ERROR: Missing argument(s) [need a valid project_id and page_id]', 'wp-data-access' );
			}

			ob_start();

			$form = new WPDAPRO_Data_Forms( $wp_atts['project_id'], $wp_atts['page_id'] );
			$form->show();

			return ob_get_clean();
		}

		protected function is_post() {
			global $post;
			$posttype = get_post_type( $post );

			return $posttype == 'post' ? true : false;
		}

		protected function is_page() {
			global $post;
			$posttype = get_post_type( $post );

			return $posttype == 'page' ? true : false;
		}

	}

}