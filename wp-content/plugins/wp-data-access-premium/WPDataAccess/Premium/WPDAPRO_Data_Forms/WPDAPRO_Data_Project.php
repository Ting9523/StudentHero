<?php

namespace WPDataAccess\Premium\WPDAPRO_Data_Forms {

	use WPDataAccess\Plugin_Table_Models\WPDP_Page_Model;
	use WPDataAccess\Plugin_Table_Models\WPDP_Project_Model;
	use WPDataAccess\WPDA;

	class WPDAPRO_Data_Project {

		protected $project_id  = null;
		protected $page_tables = [];
		protected $use_roles   = false;
		protected $user_roles  = [];

		protected $embedding = false;

		public function __construct( $project_id, $args = [] ) {
			$this->project_id = $project_id;
			$this->use_roles  = 'off' !== WPDA::get_option( WPDA::OPTION_WPDA_USE_ROLES_IN_SHORTCODE );

			if ( $this->use_roles ) {
				$this->user_roles = WPDA::get_current_user_roles();
				if ( false === $this->user_roles ) {
					$this->user_roles = [];
				}
			}

			if ( isset( $args['embedding'] ) && true === $args['embedding'] ) {
				$this->embedding = true;
			}
		}

		public function show() {
			global $wpdb;

			$query_pages = $wpdb->prepare(
				" select * from " . WPDP_Page_Model::get_base_table_name() .
				" where project_id = %d " .
				" and add_to_menu = 'Yes' " .
				" order by page_sequence",
				[
					$this->project_id,
				]
			);
			$all_pages = $wpdb->get_results( $query_pages, 'ARRAY_A' ); // phpcs:ignore Standard.Category.SniffName.ErrorCode

			if ( 0 === sizeof( $all_pages ) ) {
				echo __( 'INFO: No pages found for this project [set add to menu to Yes]', 'wp-data-access' );;
				return;
			}

			$pages = [];
			foreach ( $all_pages as $page ) {
				if ( $this->user_has_access( $page ) ) {
					$pages[] = $page;
				}
			}

			if ( 0 === sizeof( $pages ) ) {
				echo __( 'INFO: You have no access to this project [check page roles]', 'wp-data-access' );;
				return;
			}

			if ( sizeof( $pages ) > 0 ) {
				// Add navigation
				echo '<div class="wpdadataproject_menu">';
				echo '<ul id="wpdadataproject_menu_' . esc_attr( $this->project_id ) . '" class="wpdadataproject_menu_items">';
				$index = 0;
				foreach ( $pages as $page ) {
					if ( $index > 0 ) {
						$add_menu_class = 'wpdadataproject_sub_menu_item';
					} else {
						if ( sizeOf( $pages ) > 0 ) {
							$title          = $this->get_project_title();
							$add_menu_icon  = '<span class="wpdadataproject_main_menu_icon dashicons dashicons-menu-alt3"></span>';
							echo "<li class='wpdadataproject_main_menu_item wpdadataproject_menu_titel_item wpdadataproject_menu_item'><div>{$title}{$add_menu_icon}</div></li>";
							$add_menu_class = 'wpdadataproject_sub_menu_item';
						} else {
							$add_menu_class = '';
						}
					}

					if ( null === $page['page_title'] || '' === $page['page_title'] ) {
						$title = 'No Title';
					} else {
						$title = $page['page_title'];
					}

					echo "<li id='wpdadataproject_menu_item_" . esc_attr( $this->project_id ) ."_{$index}' class='wpdadataproject_menu_item {$add_menu_class}'><div>{$title}</div></li>";

					$this->page_tables[ $index ] = [
						'pageId'     => $page['page_id'],
						'schemaName' => $page['page_schema_name'],
						'tableName'  => $page['page_table_name'],
					];
					$index++;
				}
				echo '</ul>';
				echo '</div>';

				// Add data forms
				$index = 0;
				echo '<div class="wpdadataproject_menu_pages">';
				foreach ( $pages as $page ) {
					if ( $index > 0 ) {
						$style = 'visibility:hidden;position:absolute;left:-999em;';
					} else {
						$style = '';
					}

					echo "<div id='wpdadataproject_menu_page_" . esc_attr( $this->project_id ) . "_{$index}' class='wpdadataproject_menu_page_" . esc_attr( $this->project_id ) . "' style='{$style}'>";

					$args = [];
					if ( $this->embedding ) {
						$args['embedding'] = true;
					}

					$form = new WPDAPRO_Data_Forms( $this->project_id, $page['page_id'], $args );
					$form->show();

					echo "</div>";

					$index++;
				}
				echo '</div>';
			}
			?>
			<script type="text/javascript">
				jQuery(function() {
					wpdaDataFormsProjectTables["<?php echo esc_attr( $page['project_id'] ); ?>"] = <?php echo json_encode( $this->page_tables ) ?>;

					jQuery("#wpdadataproject_menu_<?php echo esc_attr( $this->project_id ); ?>").menu();
					jQuery(".wpdadataproject_menu_item").on("click", function() {
						if (jQuery(this).attr('id')!==undefined) {
							// Sub menu item clicked
							var id = jQuery(this).attr('id').split('_');
							pageTables = wpdaDataFormsProjectTables[id[3]][id[4]];
							if (jQuery("#wpdadataproject_menu_page_" + id[3] + "_" + id[4]).length===1) {
								jQuery(".wpdadataproject_menu_page_" + id[3]).css("visibility", "hidden").css("position", "absolute").css("left", "-999em");
								jQuery("#wpdadataproject_menu_page_" + id[3] + "_" + id[4]).css("visibility", "visible").css("position", "unset").css("left", "unset");
								schemaName = pageTables.schemaName.substr(0, 4)==="rdb:" ? pageTables.schemaName.substr(4) : pageTables.schemaName;
								datatableSelector = "#wpdadataforms_table_" + pageTables.pageId + "_" + schemaName + "_" + pageTables.tableName;
								datatable = jQuery(datatableSelector).DataTable();
								datatable.responsive.recalc(); // Recalculate responsive columns without reloading data
								jQuery("#wpdadataproject_menu_<?php echo esc_attr( $this->project_id ); ?> .wpdadataproject_main_menu_item").click();
							}
						} else {
							// Main menu item clicked
							wpdadataformsToggleMenu("<?php echo esc_attr( $this->project_id ); ?>");
						}
					});
					jQuery(".wpdadataproject_menu_item").on("mouseover", function() {

					});
				});
			</script>
			<?php
		}

		protected function user_has_access( $page ) {
			if ( $this->use_roles ) {
				if ( '' === $page['page_role'] || null === $page['page_role'] ) {
					return true;
				}

				if ( sizeof( $this->user_roles ) > 0 ) {
					foreach ( $this->user_roles as $user_role ) {
						if ( stripos( strval( $page['page_role'] ), strval( $user_role ) ) !== false ) {
							return true;
						}
					}
				}

				return false;
			} else {
				return true;
			}
		}

		protected function get_project_title() {
			global $wpdb;

			$query_project = $wpdb->prepare(
				" select * from " . WPDP_Project_Model::get_base_table_name() .
				" where project_id = %d ",
				[
					$this->project_id,
				]
			);

			$project = $wpdb->get_results( $query_project, 'ARRAY_A' ); // phpcs:ignore Standard.Category.SniffName.ErrorCode
			if ( sizeOf( $project) === 1 ) {
				return $project[0]['menu_name'] !== null && $project[0]['menu_name'] !== '' ?
					$project[0]['menu_name'] : $project[0]['project_name'];
			} else {
				return 'No Title';
			}
		}

	}

}