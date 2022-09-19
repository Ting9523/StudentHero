<?php

namespace WPDataAccess\Premium\WPDAPRO_Templates {

	class WPDAPRO_Template_Data_Publisher_Space extends WPDAPRO_Template {

		const TEMPLATE = '
			<style>
				/* DataTable */
				
				#$table_name$pub_id.dataTable.wpda-datatable>thead>tr>th,
				#$table_name$pub_id.dataTable.wpda-datatable>thead>tr>td,
				#$table_name$pub_id.dataTable.wpda-datatable>tfoot>tr>th,
				#$table_name$pub_id.dataTable.wpda-datatable>tfoot>tr>td,
				#$table_name$pub_id>tbody>tr>th,
				#$table_name$pub_id>tbody>tr>td {
					padding: calc($css_spacepx * 0.8) $css_spacepx;
					padding-right: 20px;
				}
				
				#$table_name$pub_id.dataTable.wpda-datatable.dtr-inline.collapsed>tbody>tr>td.dtr-control,
				#$table_name$pub_id.dataTable.wpda-datatable.dtr-inline.collapsed>tbody>tr>th.dtr-control {
					padding-left: calc($css_spacepx * 0.7 + 30px);
				}
				
				#$table_name$pub_id.dataTable.wpda-datatable.dtr-inline.collapsed>tbody>tr>td.dtr-control:before,
				#$table_name$pub_id.dataTable.wpda-datatable.dtr-inline.collapsed>tbody>tr>th.dtr-control:before {
					left: calc($css_spacepx * 0.7);
					top: calc($css_spacepx - 1px);
					margin-top: unset;
				}
				
				#$table_name$pub_id tbody td.child,
				#$table_name$pub_id table.wpda-child-expanded td {
					padding: 0;
				}
				
				#$table_name$pub_id td table.wpda-child-table td {
					padding: $css_spacepx;
					box-sizing: border-box;
					width: max-content;
				}
				#$table_name$pub_id td table.wpda-child-table td:first-child {
					min-width: 15%;
					max-width: 30%;
				}

				#$table_name$pub_id_more_button {
					padding: calc($css_spacepx * 1.5);
					line-height: 0;
				}
				
				#$table_name$pub_id_wrapper .dtsp-panesContainer button {
					padding: 5px;
				}
				#$table_name$pub_id_wrapper .dtsp-panesContainer button:first-child {
					margin-left: 7px;
				}
				
				#$table_name$pub_id_wrapper div.dtsp-panesContainer div.dtsp-searchPanes div.dtsp-searchPane div.dtsp-topRow div.dtsp-searchCont input.dtsp-search {
    			    padding-left: 10px;
				}
				
				/* Popup */
				
				div.dtr-modal-content table.wpda-child-table.$class th,
				div.dtr-modal-content table.wpda-child-table.$class td {
					padding: $css_spacepx;
				}
			</style>
		';

		public function get_template( $args ) {
			if ( isset( $args['table_name'], $args['pub_id'], $args['space'] ) ) {
				$table_name  = esc_attr( $args['table_name'] );
				$pub_id      = esc_attr( $args['pub_id'] );
				$css_space   = esc_attr( $args['space'] );

				$class = "wpda-space-{$pub_id}";

				return str_replace(
					[
						'$table_name', '$pub_id', '$class', '$css_space'
					],
					[
						$table_name, $pub_id, $class, $css_space
					],
					self::TEMPLATE
				);
			} else {
				return '';
			}
		}

	}

}