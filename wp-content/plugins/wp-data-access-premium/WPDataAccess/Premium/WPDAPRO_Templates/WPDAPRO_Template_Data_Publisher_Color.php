<?php

namespace WPDataAccess\Premium\WPDAPRO_Templates {

	class WPDAPRO_Template_Data_Publisher_Color extends WPDAPRO_Template {

		const COLORS = [
			'default', 'black', 'blue', 'blue/yellow', 'green', 'pink', 'red', 'yellow'
		];

		const TEMPLATE = '
			<style>
				/* DataTable */
				
				#$table_name$pub_id_wrapper * {
					color: $tab_color;
				}
				
				#$table_name$pub_id_wrapper tr.odd:not(.selected) {
					background-color: $tab_bg_color;
				}
							
				#$table_name$pub_id_wrapper select,
				#$table_name$pub_id_wrapper select option,
				#$table_name$pub_id_wrapper input,
				#$table_name$pub_id_wrapper button,
				#$table_name$pub_id_wrapper .dataTables_paginate .paginate_button {
					color: $txt_color !important;
					background-color: $tab_bg_color;
					border: 1px solid $tab_border;
					font-weight: bold;
				}
				
				#$table_name$pub_id_wrapper div.dt-datetime div.dt-datetime-title button {
					background-color: transparent;
					border: none;
				}
				
				#$table_name$pub_id_wrapper select,
				#$table_name$pub_id_wrapper input {
					background: $tab_bgl_color;
				}
				#$table_name$pub_id_wrapper input::placeholder {
					color: $hvr_color;
					font-weight: normal;
				}
				
				#$table_name$pub_id_wrapper button:hover,
				#$table_name$pub_id_wrapper .dataTables_paginate .paginate_button:hover {
					color: $tab_color;
					background-color: $hvr_color;
					border: 1px solid $tab_border;
				}
				
				#$table_name$pub_id_wrapper .dataTables_paginate .paginate_button.current,
				#$table_name$pub_id_wrapper .dataTables_paginate .paginate_button.disabled {
					color: $tab_color;
					background: transparent;
					border: none;
				}
				
				#$table_name$pub_id_wrapper .dataTables_paginate .paginate_button.current:hover,
				#$table_name$pub_id_wrapper .dataTables_paginate .paginate_button.disabled:hover {
					color: $tab_color;
					background: transparent;
					border: none;
				}
				
				#$table_name$pub_id {
					background: $tbl_color;
					border: 1px solid $tab_border;
				}
				
				#$table_name$pub_id thead th,
				#$table_name$pub_id thead td,
				#$table_name$pub_id tfoot th,
				#$table_name$pub_id tfoot td {
					background-color: transparent;
					color: $hdr_color;
					border: none;
					vertical-align: middle;
				}

				#$table_name$pub_id tbody tr th,
				#$table_name$pub_id tbody tr td {
					color: $txt_color;
					border: none;
					border-bottom: 1px solid $tab_border;
				}
				
				#$table_name$pub_id.dataTable.dtr-inline.collapsed>tbody>tr>td.dtr-control:before, 
				#$table_name$pub_id.dataTable.dtr-inline.collapsed>tbody>tr>th.dtr-control:before {
					background-color: $tab_color;
				}
				
				#$table_name$pub_id_wrapper tr.odd:hover:not(.selected),
				#$table_name$pub_id tbody tr:hover:not(.selected) {
					background-color: $hvr_color;
				}
				
				#$table_name$pub_id td table.wpda-child-expanded td,
				#$table_name$pub_id td table.wpda-child-table {
					border: none;
				}
				#$table_name$pub_id table.wpda-child-table td {
					border: none;
				}				
				#$table_name$pub_id table.wpda-child-table tbody tr td:first-child {
					border-left: 1.3em solid $tab_color;
					padding-left: 1em;
				}
				
				#$table_name$pub_id_more_button {
					background: none;
					background-color: $tab_color !important;
					color: $hdr_color;
				}

				#$table_name$pub_id_wrapper div.dtsp-searchPanes table,
				#$table_name$pub_id_wrapper div.dtsp-searchPanes table tr td,
				#$table_name$pub_id_wrapper div.dtsp-panesContainer div.dtsp-searchPanes div.dtsp-searchPane div.dataTables_wrapper {
					border: none;
				}
				#$table_name$pub_id_wrapper div.dtsp-searchPanes table {
					border: 1px solid $tab_border;
					border-bottom: none;
				}
				#$table_name$pub_id_wrapper div.dtsp-searchPanes table tr td {
					border-bottom: 1px solid $tab_border;
				}
								
				/* Popup */
				
				div.dtr-modal div.dtr-modal-close {
					line-height: 20px;
				}
				
				div.dtr-modal-content h2 span.$class {
					color: $pup_color;
				}

				div.dtr-modal-content table.wpda-child-table.$class {
					color: $pup_color;
					background: $pup_border;
					border-bottom: none;
				}
				
				div.dtr-modal-content table.wpda-child-table.$class tbody {
					border: 1px solid $pup_border;
				}
				
				div.dtr-modal-content table.wpda-child-table.$class tbody tr:nth-child(odd) {
					background-color: $tab_bg_color;
				}
				
				div.dtr-modal-content table.wpda-child-table.$class tbody tr td {
					border: none;
					border-bottom: 1px solid $pup_border;
				}
				
				div.dtr-modal-content table.wpda-child-table.$class tbody tr:hover {
					background-color: $hvr_color;
				}				
			
				div.dtr-modal-content table.wpda-child-modal.$class input.dtr-modal-close {
					color: $pup_color;
					background: $pup_bg_color;
					border: 1px solid $pup_border;
				}
				
				div.dtr-modal-content table.wpda-child-modal.$class input.dtr-modal-close:hover {
					color: $pup_color;
					background: $hvr_color;
				}

				div.dtr-modal-content table.wpda-child-table.$class thead,
				div.dtr-modal-content table.wpda-child-table.$class tfoot {
					padding: 0;
					border: none;				
				}
				
				div.dtr-modal-content table.wpda-child-table.$class thead td {
					border: none;
				}
				
				div.dtr-modal-content table.wpda-child-table.$class tfoot td {
					border: none;
				}
			</style>
		';

		public function get_template( $args ) {
			if ( isset( $args['table_name'], $args['pub_id'], $args['color'] ) ) {
				$table_name  = esc_attr( $args['table_name'] );
				$pub_id      = esc_attr( $args['pub_id'] );
				$css_color   = esc_attr( $args['color'] );

				/**
				 * DataTable color settings
				 * ------------------------
				 * $txt_color     = table td color
				 * $tab_color     = table color
				 * $hdr_color     = table header color
				 * $tab_bg_color  = table background color
				 * $tab_bgl_color = table background color light
				 * $tbl_color     = table color light
				 * $bdy_color     = table body color
				 * $tab_border    = table border color
				 *
				 * Popup color settings
				 * --------------------
				 * $pup_color     = popup color
				 * $pup_bg_color  = popup background color
				 * $pup_bgl_color = popup background color light
				 * $pup_border    = popup border color
				 */

				switch ( $css_color ) {
					case 'black':
						$txt_color       = '#000000';
						$tab_color       = '#23282d';
						$hdr_color       = '#ffffff';
						$tab_bg_color    = '#e5e5e5';
						$tab_bgl_color   = '#ffffff';
						$tbl_color       = '#23282d';
						$bdy_color       = '#ffffff';
						$tab_border      = '#23282d';
						$hvr_color       = '#ababab';
						$pup_color       = $tab_color;
						$pup_bg_color    = $tab_bg_color;
						$pup_bgl_color   = $tab_bgl_color;
						$pup_border      = $tab_border;
						break;
					case 'blue':
						$txt_color       = '#096484';
						$tab_color       = '#096484';
						$hdr_color       = '#ffffff';
						$tab_bg_color    = '#f0f8ff';
						$tab_bgl_color   = '#ffffff';
						$tbl_color       = '#096484';
						$bdy_color       = '#ffffff';
						$tab_border      = '#096484';
						$hvr_color       = '#74b6ce';
						$pup_color       = $tab_color;
						$pup_bg_color    = $tab_bg_color;
						$pup_bgl_color   = $tab_bgl_color;
						$pup_border      = $tab_border;
						break;
					case 'blue/yellow':
						$txt_color       = '#096484';
						$tab_color       = '#096484';
						$hdr_color       = '#ffffff';
						$tab_bg_color    = '#ffffdd';
						$tab_bgl_color   = '#ffffff';
						$tbl_color       = '#096484';
						$bdy_color       = '#ffffff';
						$tab_border      = '#096484';
						$hvr_color       = '#ffd300';
						$pup_color       = $tab_color;
						$pup_bg_color    = $tab_bg_color;
						$pup_bgl_color   = $tab_bgl_color;
						$pup_border      = $tab_border;
						break;
					case 'green':
						$txt_color       = '#2e6409';
						$tab_color       = '#2e6409';
						$hdr_color       = '#ffffff';
						$tab_bg_color    = '#e0eee0';
						$tab_bgl_color   = '#ffffff';
						$tbl_color       = '#2e6409';
						$bdy_color       = '#ffffff';
						$tab_border      = '#2e6409';
						$hvr_color       = '#abcdab';
						$pup_color       = $tab_color;
						$pup_bg_color    = $tab_bg_color;
						$pup_bgl_color   = $tab_bgl_color;
						$pup_border      = $tab_border;
						break;
					case 'pink':
						$txt_color       = '#ff1493';
						$tab_color       = '#ff1493';
						$hdr_color       = '#ffffff';
						$tab_bg_color    = '#fff0f5';
						$tab_bgl_color   = '#ffffff';
						$tbl_color       = '#ff1493';
						$bdy_color       = '#ffffff';
						$tab_border      = '#ff1493 ';
						$hvr_color       = '#ffb9d5';
						$pup_color       = $tab_color;
						$pup_bg_color    = $tab_bg_color;
						$pup_bgl_color   = $tab_bgl_color;
						$pup_border      = $tab_border;
						break;
					case 'red':
						$txt_color       = '#d1675a';
						$tab_color       = '#d1675a';
						$hdr_color       = '#ffffff';
						$tab_bg_color    = '#ffeeee';
						$tab_bgl_color   = '#ffffff';
						$tbl_color       = '#d1675a';
						$bdy_color       = '#ffffff';
						$tab_border      = '#d1675a';
						$hvr_color       = '#ffbbbb';
						$pup_color       = $tab_color;
						$pup_bg_color    = $tab_bg_color;
						$pup_bgl_color   = $tab_bgl_color;
						$pup_border      = $tab_border;
						break;
					case 'yellow';
						$txt_color       = '#ffd300';
						$tab_color       = '#ffd300';
						$hdr_color       = '#ffffff';
						$tab_bg_color    = '#ffffdd';
						$tab_bgl_color   = '#ffffff';
						$tbl_color       = '#ffd300';
						$bdy_color       = '#ffffff';
						$tab_border      = '#ffd300';
						$hvr_color       = '#fff8e6';
						$pup_color       = $tab_color;
						$pup_bg_color    = $tab_bg_color;
						$pup_bgl_color   = $tab_bgl_color;
						$pup_border      = $tab_border;
						break;
					default:
						$txt_color       = '#444444';
						$tab_color       = '#444444';
						$hdr_color       = '#444444';
						$tab_bg_color    = '#f9f9f9';
						$tab_bgl_color   = '#ffffff';
						$tbl_color       = '#b0b0b0';
						$bdy_color       = '#ffffff';
						$tab_border      = '#b0b0b0';
						$hvr_color       = '#ededed';
						$pup_color       = $tab_color;
						$pup_bg_color    = $tab_bg_color;
						$pup_bgl_color   = $tab_bgl_color;
						$pup_border      = $tab_border;
				}

				$class = "wpda-color-{$pub_id}";

				return str_replace(
						[
							'$table_name', '$pub_id', '$class',
							'$txt_color', '$tab_color', '$hdr_color', '$tab_bg_color', '$tab_bgl_color', '$tbl_color', '$bdy_color', '$tab_border', '$hvr_color',
							'$pup_color', '$pup_bg_color', '$pup_bgl_color', '$pup_border'
						],
						[
							$table_name, $pub_id, $class,
							$txt_color, $tab_color, $hdr_color, $tab_bg_color, $tab_bgl_color, $tbl_color, $bdy_color, $tab_border, $hvr_color,
							$pup_color, $pup_bg_color, $pup_bgl_color, $pup_border
						],
						self::TEMPLATE
					);

			} else {
				return '';
			}
		}

	}

}