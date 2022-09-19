<?php

namespace WPDataAccess\Premium\WPDAPRO_Templates {

	class WPDAPRO_Template_Data_Publisher_Modal extends WPDAPRO_Template {

		const TEMPLATE = '
			<script type="application/javascript">
				function $fnc() {
					tableWidth = jQuery("#$table_name$pub_id").width();
					if (!isNaN(tableWidth) && tableWidth>0) {
						jQuery("html > head").append(
							"<style> \
							 div.dtr-modal-content table.wpda-child-table.$class { \\
							 	 width: calc(" + tableWidth + "px * $css_modal /100 - 5em); \\
							 } \
							 </style>"
						);
					}
				}
			</script>
		';

		public function get_template( $args ) {
			if ( isset( $args['table_name'], $args['pub_id'], $args['modal'] ) ) {
				$table_name = esc_attr( $args['table_name'] );
				$pub_id     = esc_attr( $args['pub_id'] );
				$css_modal  = esc_attr( $args['modal'] );

				$class = "wpda-modal-{$pub_id}";

				return str_replace(
					[
						'$table_name', '$pub_id', '$class', '$css_modal', '$fnc'
					],
					[
						$table_name, $pub_id, $class, $css_modal, "{$table_name}{$pub_id}SetModalWidth"
					],
					self::TEMPLATE
				);
			} else {
				return '';
			}
		}

	}

}