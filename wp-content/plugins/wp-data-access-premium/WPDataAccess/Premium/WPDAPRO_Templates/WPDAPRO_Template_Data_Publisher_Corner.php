<?php

namespace WPDataAccess\Premium\WPDAPRO_Templates {

	class WPDAPRO_Template_Data_Publisher_Corner extends WPDAPRO_Template {

		const TEMPLATE = '
			<style>
				/* DataTable */
				
				#$table_name$pub_id {
					border-radius: $css_cornerpx;
				}
				
				#$table_name$pub_id.dataTable.wpda-datatable>thead>tr>th,
				#$table_name$pub_id.dataTable.wpda-datatable>thead>tr>td,
				#$table_name$pub_id.dataTable.wpda-datatable>tfoot>tr>th,
				#$table_name$pub_id.dataTable.wpda-datatable>tfoot>tr>td {
					border: none;
				}
				
				#$table_name$pub_id_wrapper select,
				#$table_name$pub_id_wrapper input,
				#$table_name$pub_id_wrapper button,
				#$table_name$pub_id_wrapper .dataTables_paginate .paginate_button,
				button.dt-button {
					border-radius: $css_cornerpx;
				}
				
				#$table_name$pub_id_more_button {
					border-radius: $css_cornerpx;
				}
				
				/* Popup */
				
				div.dtr-modal-content table.wpda-child-table.$class {
					border-radius: $css_cornerpx;
				}
				
				div.dtr-modal-content table.wpda-child-modal.$class input.dtr-modal-close {
					border-radius: $css_cornerpx;
				}

				div.dtr-modal-content table.wpda-child-modal tbody {
					max-height: 50vh;
				}
			</style>
		';

		public function get_template( $args ) {
			if ( isset( $args['table_name'], $args['pub_id'], $args['corner'] ) ) {
				$table_name = esc_attr( $args['table_name'] );
				$pub_id     = esc_attr( $args['pub_id'] );
				$css_corner = esc_attr( $args['corner'] );

				$class = "wpda-corner-{$pub_id}";

				return str_replace(
					[
						'$table_name', '$pub_id', '$class', '$css_corner'
					],
					[
						$table_name, $pub_id, $class, $css_corner
					],
					self::TEMPLATE
				);
			} else {
				return '';
			}
		}

	}

}