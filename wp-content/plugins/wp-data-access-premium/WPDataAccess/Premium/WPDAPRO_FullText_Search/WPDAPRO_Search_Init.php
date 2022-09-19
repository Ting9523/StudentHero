<?php

namespace WPDataAccess\Premium\WPDAPRO_FullText_Search {

	use WPDataAccess\WPDA;

	class WPDAPRO_Search_Init {

		public static function enqueue_scripts( $page ) {
			if ( WPDA::is_plugin_page( $page ) ) {
				// Enqueue javascript fulltext search support
				wp_enqueue_script(
					'wpdapro_search',
					plugins_url( '../../../assets/premium/js/wpdapro_search.js', __FILE__ ),
					[],
					WPDA::get_option( WPDA::OPTION_WPDA_VERSION )
				);
			}
		}

	}

}