<?php

namespace WPDataAccess\Premium\WPDAPRO_Data_Publisher {

	use WPDataAccess\WPDA;

	class WPDAPRO_Data_Publisher_Init {

		const JDT_CORE       = 'https://cdn.datatables.net/1.11.5/';
		const JDT_JQUERYUI   = 'https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/';
		const JDT_RESPONSIVE = 'https://cdn.datatables.net/responsive/2.2.9/';
		const JDT_BUTTONS 	 = 'https://cdn.datatables.net/buttons/2.2.1/';
		const JDT_SELECT 	 = 'https://cdn.datatables.net/select/1.3.4/';
		const JDT_ROWGROUP	 = 'https://cdn.datatables.net/rowgroup/1.1.4/';
		const JDT_SB		 = 'https://cdn.datatables.net/searchbuilder/1.3.2/';
		const JDT_SP		 = 'https://cdn.datatables.net/searchpanes/2.0.0/';
		const JDT_DATETIME 	 = 'https://cdn.datatables.net/datetime/1.1.2/';
		const JDT_PDFMAKE 	 = 'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/';
		const JDT_JSZIP		 = 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/';

		public static function enqueue_styles() {
			// Register jQuery DataTables premium features
			wp_register_style( 'wpdapro_jquery_datatables_buttons', self::JDT_BUTTONS . 'css/buttons.dataTables.min.css', array(), null, false );
			wp_register_style( 'wpdapro_jquery_datatables_select', self::JDT_SELECT . 'css/select.dataTables.min.css', array(), null, false );
			wp_register_style( 'wpdapro_jquery_datatables_datetime', self::JDT_DATETIME . 'css/dataTables.dateTime.min.css', array(), null, false );
			wp_register_style( 'wpdapro_jquery_datatables_searchbuilder', self::JDT_SB . 'css/searchBuilder.dataTables.min.css', array(), null, false );
			wp_register_style( 'wpdapro_jquery_datatables_searchpanes', self::JDT_SP . 'css/searchPanes.dataTables.min.css', array(), null, false );
			wp_register_style(
				'wpdapro_datatables_select',
				plugins_url( '../../../assets/premium/css/wpda_datatables_select.css', __FILE__ ),
				[],
				WPDA::get_option( WPDA::OPTION_WPDA_VERSION )
			);
		}

		public static function enqueue_scripts() {
			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				// Register jQuery DataTables premium features
				wp_register_script( 'wpdapro_jquery_datatables_buttons', self::JDT_BUTTONS . 'js/dataTables.buttons.min.js', array(), null, false );
				wp_register_script( 'wpdapro_jquery_datatables_flash', self::JDT_BUTTONS . 'js/buttons.flash.min.js', array(), null, false );
				wp_register_script( 'wpdapro_jquery_datatables_html5', self::JDT_BUTTONS . 'js/buttons.html5.min.js', array(), null, false );
				wp_register_script( 'wpdapro_jquery_datatables_print', self::JDT_BUTTONS . 'js/buttons.print.min.js', array(), null, false );
				wp_register_script( 'wpdapro_jquery_datatables_colvis', self::JDT_BUTTONS . 'js/buttons.colVis.min.js', array(), null, false );
				wp_register_script( 'wpdapro_jquery_datatables_select', self::JDT_SELECT . 'js/dataTables.select.min.js', array(), null, false );
				wp_register_script( 'wpdapro_jquery_datatables_datetime', self::JDT_DATETIME . 'js/dataTables.dateTime.min.js', array(), null, false );
				wp_register_script( 'wpdapro_jquery_datatables_pdfmake', self::JDT_PDFMAKE . 'pdfmake.min.js', array(), null, false );
				wp_register_script( 'wpdapro_jquery_datatables_vfs_fonts', self::JDT_PDFMAKE . 'vfs_fonts.js', array(), null, false );
				wp_register_script( 'wpdapro_jquery_datatables_jszip', self::JDT_JSZIP . 'jszip.min.js', array(), null, false );
				wp_register_script( 'wpdapro_jquery_datatables_rowgroup', self::JDT_ROWGROUP . 'js/dataTables.rowGroup.min.js', array(), null, false );
				wp_register_script( 'wpdapro_jquery_datatables_searchbuilder', self::JDT_SB . 'js/dataTables.searchBuilder.min.js', array(), null, false );
				wp_register_script( 'wpdapro_jquery_datatables_searchpanes', self::JDT_SP . 'js/dataTables.searchPanes.min.js', array(), null, false );
				wp_register_script(
					'wpdapro_jquery_datatables_premium',
					plugins_url( '../../../assets/premium/js/wpda_datatables.js', __FILE__ ),
					[],
					WPDA::get_option( WPDA::OPTION_WPDA_VERSION )
				);
			}
		}

	}

}