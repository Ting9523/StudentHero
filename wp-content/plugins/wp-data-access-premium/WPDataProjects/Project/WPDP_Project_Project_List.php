<?php

/**
 * Suppress "error - 0 - No summary was found for this file" on phpdoc generation
 *
 * @package WPDataProjects\Project
 */

namespace WPDataProjects\Project {

	use WPDataAccess\WPDA;
	use \WPDataProjects\Parent_Child\WPDP_Parent_List_Table;
	use WPDataAccess\Utilities\WPDA_Import_Multi;

	/**
	 * Class WPDP_Project_Project_List extends WPDP_Parent_List_Table
	 *
	 * @see WPDP_Parent_List_Table
	 *
	 * @author  Peter Schulz
	 * @since   2.0.0
	 */
	class WPDP_Project_Project_List extends WPDP_Parent_List_Table {

		/**
		 * WPDP_Project_Project_List constructor
		 *
		 * @param array $args
		 */
		public function __construct( array $args = array() ) {
			$args['column_headers'] = self::column_headers_labels();
			$args['allow_import']   = 'off';
			$args['allow_insert']   = 'on';

			parent::__construct( $args );

			try {
				// Instantiate WPDA_Import.
				$this->wpda_import = new WPDA_Import_Multi(
					"?page={$this->page}",
					$this->schema_name,
					array(
						__( 'IMPORT DATA PROJECTS', 'wp-data-access' ),
						'',
					)
				);
			} catch ( \Exception $e ) {
				// If import is turned off instantition will fail. Handle is set to null (check in future calls).
				$this->wpda_import = null;
			}
		}

		/**
		 * Overwrites method add_header_button to add arguments to insert button
		 */
		protected function add_header_button() {
			?>
			<form
					method="post"
					action="?page=<?php echo esc_attr( $this->page ); ?>"
					style="display: inline-block; vertical-align: unset;"
			>
				<div>
					<input type="hidden" name="action" value="new">
					<input type="hidden" name="mode" value="edit">
					<input type="hidden" name="table_name" value="<?php echo esc_attr( $this->table_name ); ?>">
					<button type="submit" class="page-title-action wpda_tooltip"
							title="Add new project to repository"
					>
						<i class="fas fa-plus-circle wpda_icon_on_button"></i>
						<?php echo __( 'Add New', 'wp-data-access' ); ?>
					</button>
				</div>
			</form>
			<?php
			if ( null !== $this->wpda_import ) {
				$this->wpda_import->add_button( __( 'Import', 'wp-data-access' ) );
			}
		}

		/**
		 * Overwrites method column_default_add_action to rewrite export action
		 *
		 * Default export supports single table export only. Project export support exporting child rows as well.
		 *
		 * @param array  $item
		 * @param string $column_name
		 * @param array  $actions
		 */
		protected function column_default_add_action( $item, $column_name, &$actions ) {
			parent::column_default_add_action( $item, $column_name, $actions );
			$wp_nonce_action   = 'wpdp-export-project-' . $item['project_id'];
			$wp_nonce          = wp_create_nonce( $wp_nonce_action );
			$src               = '?action=wpda_export_project&project_id=' . $item['project_id'] . '&wpnonce=' . $wp_nonce;
			$actions['export'] = sprintf(
				'
					<a href="%s" target="_blank" title="Export project" class="wpda_tooltip">
						<span style="white-space:nowrap">
							<i class="fas fa-cloud-download wpda_icon_on_button"></i>
							%s
						</span>
					</a>
				',
				$src,
				__( 'Export', 'wp-data-access' )
			);

			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				$shortcode_enabled =
					'on' === WPDA::get_option( WPDA::OPTION_PLUGIN_WPDADATAFORMS_POST ) &&
					'on' === WPDA::get_option( WPDA::OPTION_PLUGIN_WPDADATAFORMS_PAGE );

				?>
				<div id="wpdapro_dataforms_<?php echo esc_attr( $item['project_id'] ); ?>"
					 title="<?php echo __( 'Data Forms shortcode', 'wp-data-access' ); ?>"
					 style="display:none"
				>
					<p class="wpda_shortcode_content">
						Copy the shortcode below into your post or page to make this Data Project available on your website.
					</p>
					<p class="wpda_shortcode_text">
						<strong>
							[wpdadataproject project_id="<?php echo esc_attr( $item['project_id'] ); ?>"]
						</strong>
					</p>
					<p class="wpda_shortcode_buttons">
						<button class="button wpda_shortcode_clipboard wpda_shortcode_button"
								type="button"
								data-clipboard-text='[wpdadataproject project_id="<?php echo esc_attr( $item['project_id'] ); ?>"]'
								onclick="jQuery.notify('<?php echo __( 'Shortcode successfully copied to clipboard!' ); ?>','info')"
						>
							<?php echo __( 'Copy', 'wp-data-access' ); ?>
						</button>
						<button class="button button-primary wpda_shortcode_button"
								type="button"
								onclick="jQuery('.ui-dialog-content').dialog('close')"
						>
							<?php echo __( 'Close', 'wp-data-access' ); ?>
						</button>
					</p>
					<?php
					if ( ! $shortcode_enabled ) {
						?>
						<p>
							Shortcode wpdadataproject is not enabled for all output types.
							<a href="<?php echo admin_url( 'options-general.php' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>?page=wpdataaccess" class="wpda_shortcode_link">&raquo; Manage settings</a>
						</p>
						<?php
					}
					?>
				</div>
				<?php

				$actions['shortcode'] = sprintf(
					'<a href="javascript:void(0)" 
						class="view wpda_tooltip"  
						title="%s"
						onclick="jQuery(\'#wpdapro_dataforms_%s\').dialog()"
						<span style="white-space:nowrap">
							<i class="fas fa-code wpda_icon_on_button"></i>
							%s
						</span>
					</a>
					',
					__( 'Get Data Forms shortcode', 'wp-data-access' ),
					esc_attr( $item['project_id'] ),
					__( 'Shortcode', 'wp-data-access' )
				);
			}
		}

		public static function column_headers_labels() {
			return array(
				'project_id'          => __( 'Project ID', 'wp-data-access' ),
				'project_name'        => __( 'Project Name', 'wp-data-access' ),
				'project_description' => __( 'Project Description', 'wp-data-access' ),
				'add_to_menu'         => __( 'Add To Menu', 'wp-data-access' ),
				'menu_name'           => __( 'Menu Name', 'wp-data-access' ),
				'project_sequence'    => __( 'Seq#', 'wp-data-access' ),
			);
		}

		// Overwrite method
		public function show() {
			parent::show();

			WPDA::shortcode_popup();
		}

		public function get_bulk_actions() {
			$actions = parent::get_bulk_actions();

			unset(
				$actions['bulk-export'],
				$actions['bulk-export-xml'],
				$actions['bulk-export-json'],
				$actions['bulk-export-excel'],
				$actions['bulk-export-csv']
			);

			return $actions;
		}

	}

}
