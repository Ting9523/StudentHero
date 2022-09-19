<?php

namespace WPDataAccess\Premium\WPDAPRO_Inline_Editing {

	use WPDataAccess\Plugin_Table_Models\WPDA_Table_Settings_Model;
	use WPDataAccess\Plugin_Table_Models\WPDP_Project_Design_Table_Model;

	class WPDAPRO_Data_Projects {

		public static function add_table_option( $schema_name, $table_name ) {
			$project_table_settings = new WPDP_Project_Design_Table_Model();
			$project_table_settings->query();

			$table_settings = $project_table_settings->get_table_design();
			if ( isset( $table_settings->tableinfo->custom_table_settings->inline_editing ) ) {
				$custom_table_settings = $table_settings->tableinfo->custom_table_settings->inline_editing;
			}
			$editing_enabled_columns = self::get_inline_editing_enabled_columns( $schema_name, $table_name );

			$html       = '';
			$html_child = '';

			foreach ( $editing_enabled_columns as $column_name => $column_value ) {
				if ( $column_value ) {
					if ( isset( $custom_table_settings->parent->$column_name ) ) {
						$checked = $custom_table_settings->parent->$column_name ? 'checked' : '';
					} else {
						$checked = 'checked';
					}

					$html .=
						"<label style='padding-right: 10px;'>
							<input type='checkbox' name='{$column_name}_inline_editing' style='width: 16px; height: 16px;' {$checked}/>
							{$column_name}
						</label>";

					if ( isset( $custom_table_settings->child->$column_name ) ) {
						$checked_child = $custom_table_settings->child->$column_name ? 'checked' : '';
					} else {
						$checked_child = 'checked';
					}

					$html_child .=
						"<label style='padding-right: 10px;'>
							<input type='checkbox' name='{$column_name}_inline_editing_child' style='width: 16px; height: 16px;' {$checked_child}/>
							{$column_name}
						</label>";
				}
			}

			if ( '' === $html ) {
				$html = '--';
			}

			if ( '' === $html_child ) {
				$html_child = '--';
			}
			?>
			<div class="wpdp-spacer"></div>
			<div class="wpdp-spacer"></div>

			<fieldset class="wpda_fieldset wpdp_fieldset">
				<legend>
					<label style="font-weight: normal;">
						<?php echo __( 'Inline editing (premium feature) - uncheck to disable', 'wp-data-access' ); ?>
						<span class="dashicons dashicons-editor-help wpda_tooltip" title="Only available for items for which inline editing is enabled."></span>
					</label>
				</legend>
				<table class="wpda-table-structure wpdp-table-structure">
					<tr>
						<td style="text-align: right; padding-right: 5px; padding-top: 10px;" class="wpdp-label-colomn">
							<label>
								<?php echo __( 'Inline editing parent', 'wp-data-access' ) ?>
							</label>
						</td>
						<td style="padding-top: 10px;">
							<?php echo $html; ?>
						</td>
					</tr>
					<tr>
						<td style="text-align: right; padding-right: 5px;" class="wpdp-label-colomn">
							<label>
								<?php echo __( 'Inline editing child', 'wp-data-access' ) ?>
							</label>
						</td>
						<td>
							<?php echo $html_child; ?>
						</td>
					</tr>
				</table>
			</fieldset>
			<?php
		}

		public static function save_table_option( $custom_table_settings, $schema_name, $table_name ) {
			$editing_enabled_columns        = self::get_inline_editing_enabled_columns( $schema_name, $table_name );
			$column_editing_settings_parent = [];
			$column_editing_settings_child  = [];

			foreach ( $editing_enabled_columns as $column_name => $column_value ) {
				$column_editing_settings_parent[ $column_name ] = isset( $_REQUEST[ "{$column_name}_inline_editing" ] );
				$column_editing_settings_child[ $column_name ]  = isset( $_REQUEST[ "{$column_name}_inline_editing_child" ] );
			}

			$column_editing_settings['inline_editing']['parent'] = $column_editing_settings_parent;
			$column_editing_settings['inline_editing']['child']  = $column_editing_settings_child;

			return $column_editing_settings;
		}

		public static function get_inline_editing_enabled_columns( $schema_name, $table_name ) {
			$editing_enabled_columns = [];
			$table_settings          = WPDA_Table_Settings_Model::query( $table_name, $schema_name );

			if ( 0 < sizeof( $table_settings ) && isset( $table_settings[0]['wpda_table_settings'] ) ) {
				$settings = json_decode( $table_settings[0]['wpda_table_settings'] );
				if ( isset( $settings->custom_settings ) ) {
					$custom_settings = $settings->custom_settings;
					$prefix          = WPDAPRO_Inline_Editing::INLINE_EDITING_ITEM_NAME_PREFIX;
					foreach ( $custom_settings as $key => $value ) {
						if ( substr( $key, 0, strlen( $prefix ) ) === $prefix ) {
							$editing_enabled_columns[ substr( $key, strlen( $prefix ) ) ] = $value;
						}
					}
				}
			}

			return $editing_enabled_columns;
		}

	}

}