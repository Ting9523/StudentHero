<?php

namespace WPDataAccess\Premium\WPDAPRO_Inline_Editing {

	use WPDataAccess\WPDA;

	class WPDAPRO_Inline_Editing_Init {

		public static function enqueue_styles( $page = '' ) {
			if ( ! is_admin() || WPDA::is_plugin_page( $page ) ) {
				wp_enqueue_style(
					'wpdapro_inline_editing',
					plugins_url( '../../../assets/premium/css/wpdapro_inline_editing.css', __FILE__ ),
					[],
					WPDA::get_option( WPDA::OPTION_WPDA_VERSION )
				);
			}
		}

		public static function enqueue_scripts( $page = '' ) {
			if ( ! is_admin() || WPDA::is_plugin_page( $page ) ) {
				wp_enqueue_script(
					'wpdapro_inline_editor',
					plugins_url( '../../../assets/premium/js/wpdapro_inline_editor.js', __FILE__ ),
					[],
					WPDA::get_option( WPDA::OPTION_WPDA_VERSION )
				);
			}
		}

	}

}