<?php
/**
 * Suppress "error - 0 - No summary was found for this file" on phpdoc generation
 *
 * @package plugin\includes
 */

use WPDataAccess\Connection\WPDADB;
use WPDataAccess\Cookies\WPDA_Cookies;
use WPDataAccess\Data_Dictionary\WPDA_Dictionary_Lists;
use WPDataAccess\Data_Tables\WPDA_Data_Tables;
use WPDataAccess\Utilities\WPDA_Table_Actions;
use WPDataAccess\Utilities\WPDA_Export;
use WPDataAccess\Utilities\WPDA_Favourites;
use WPDataAccess\WPDA;
use WPDataProjects\Utilities\WPDP_Export_Project;
use WPDataAccess\Backup\WPDA_Data_Export;
use WPDataAccess\Plugin_Table_Models\WPDA_CSV_Uploads_Model;
use WPDataRoles\WPDA_Roles;
use WPDataAccess\Utilities\WPDA_Autocomplete;
use WPDataAccess\Premium\WPDAPRO_Geo_Location\WPDAPRO_Geo_Location_WS;
use WPDataAccess\Premium\WPDAPRO_Geo_Location\WPDAPRO_Geo_Location;
use WPDataAccess\Query_Builder\WPDA_Query_Builder;
use WPDataAccess\Dashboard\WPDA_Dashboard;
use WPDataAccess\Dashboard\WPDA_Widget_Code;
use WPDataAccess\Dashboard\WPDA_Widget_Publication;
use WPDataAccess\Dashboard\WPDA_Widget_Dbms;
use WPDataAccess\Dashboard\WPDA_Widget_Google_Chart;
use WPDataAccess\Premium\WPDAPRO_Dashboard\WPDAPRO_Widget_Project;

/**
 * Class WP_Data_Access
 *
 * Core plugin class used to define:
 * + admin specific functionality {@see WP_Data_Access_Admin}
 * + public specific functionality {@see WP_Data_Access_Public}
 * + internationalization {@see WP_Data_Access_I18n}
 * + plugin activation and deactivation {@see WP_Data_Access_Loader}
 *
 * @author  Peter Schulz
 * @since   1.0.0
 *
 * @see WP_Data_Access_Admin
 * @see WP_Data_Access_Public
 * @see WP_Data_Access_I18n
 * @see WP_Data_Access_Loader
 */
class WP_Data_Access {

	/**
	 * Reference to plugin loader
	 *
	 * @var WP_Data_Access_Loader
	 *
	 * @since   1.0.0
	 *
	 * @see WP_Data_Access_Loader
	 */
	protected $loader;

	/**
	 * Menu slug or null
	 *
	 * @var null
	 */
	protected $page = null;

	/**
	 * WP_Data_Access constructor
	 *
	 * Calls method the following methods to setup plugin:
	 * + {@see WP_Data_Access::load_dependencies()}
	 * + {@see WP_Data_Access::set_locale()}
	 * + {@see WP_Data_Access::define_admin_hooks()}
	 * + {@see WP_Data_Access::define_public_hooks()}
	 *
	 * @since   1.0.0
	 *
	 * @see WP_Data_Access::load_dependencies()
	 * @see WP_Data_Access::set_locale()
	 * @see WP_Data_Access::define_admin_hooks()
	 * @see WP_Data_Access::define_public_hooks()
	 */
	public function __construct() {
		if ( isset( $_REQUEST['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$this->page = sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

		// WP Data Access REST API.
		$this->api();
	}

	/**
	 * Add WP Data Access JSON REST API
	 *
	 * @return void
	 */
	private function api() {
		$api = new \WPDataAccess\API\WPDA_API();
		$this->loader->add_action( 'rest_api_init', $api, 'init' );
	}

	/**
	 * Load required dependencies
	 *
	 * Loads required plugin files and initiates the plugin loader.
	 *
	 * @since   1.0.0
	 *
	 * @see WP_Data_Access_Loader
	 */
	private function load_dependencies() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-data-access-loader.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-data-access-i18n.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-wp-data-access-admin.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-wp-data-access-public.php';

		$this->loader = new WP_Data_Access_Loader();
	}

	/**
	 * Set locale for internationalization
	 *
	 * @since   1.0.0
	 *
	 * @see WP_Data_Access_I18n
	 */
	private function set_locale() {
		$wpda_i18n = new WP_Data_Access_I18n();
		$this->loader->add_action( 'init', $wpda_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Add admin hooks
	 *
	 * Initiates {@see WP_Data_Access_Admin} (admin functionality) and {@see WPDA_Export} (export functionality).
	 * Adds the appropriate actions to the loader.
	 *
	 * @since   1.0.0
	 *
	 * @see WP_Data_Access_Admin
	 * @see WPDA_Export
	 */
	private function define_admin_hooks() {
		$plugin_admin = new WP_Data_Access_Admin();

		if ( WPDA::is_plugin_page( $this->page ) ) {
			// Handle plugin cookies.
			$wpda_cookies = new WPDA_Cookies();
			$this->loader->add_action( 'admin_init', $wpda_cookies, 'handle_plugin_cookies' );
		}

		// Admin menu.
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_menu_items' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_menu_my_tables', 11 );
		$this->loader->add_filter( 'submenu_file', $plugin_admin, 'wpda_submenu_filter' );

		// Admin scripts.
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'in_admin_header', $plugin_admin, 'user_admin_notices' );
		$this->loader->add_action( 'admin_head', $plugin_admin, 'remove_icons' );

		// Add settings page.
		$this->loader->add_action( 'admin_menu', $this, 'wpdataaccess_register_settings_page' );

		// Query Builder.
		$query_builder = new WPDA_Query_Builder();
		$this->loader->add_action( 'admin_action_wpda_query_builder_execute_sql', $query_builder, 'execute' );
		$this->loader->add_action( 'admin_action_wpda_query_builder_save_sql', $query_builder, 'save' );
		$this->loader->add_action( 'admin_action_wpda_query_builder_open_sql', $query_builder, 'open' );
		$this->loader->add_action( 'admin_action_wpda_query_builder_delete_sql', $query_builder, 'delete' );
		$this->loader->add_action( 'admin_action_wpda_query_builder_get_db_hints', $query_builder, 'get_db_hints' );
		$this->loader->add_action( 'admin_action_wpda_query_builder_set_db_hints', $query_builder, 'set_db_hints' );
		$this->loader->add_action( 'admin_action_wpda_query_builder_get_vqb', $query_builder, 'get_visual_query' );

		// Export action.
		$this->loader->add_action( 'admin_action_wpda_export', WPDA_Export::class, 'export' );

		// Dashboard and widgets.
		$this->loader->add_action( 'wp_ajax_wpda_save_dashboard', WPDA_Dashboard::class, 'save' );
		$this->loader->add_action( 'wp_ajax_wpda_dashboard_list', WPDA_Dashboard::class, 'get_list' );
		$this->loader->add_action( 'wp_ajax_wpda_widget_load_panel', WPDA_Dashboard::class, 'load_widget' );
		$this->loader->add_action( 'wp_ajax_wpda_widget_delete', WPDA_Dashboard::class, 'delete_widget' );
		$this->loader->add_action( 'wp_ajax_wpda_widget_code_add', WPDA_Widget_Code::class, 'ajax_widget' );
		$this->loader->add_action( 'wp_ajax_wpda_widget_dbms_add', WPDA_Widget_Dbms::class, 'ajax_widget' );
		$this->loader->add_action( 'wp_ajax_wpda_widget_dbms_refresh', WPDA_Widget_Dbms::class, 'ajax_refresh' );
		$this->loader->add_action( 'wp_ajax_wpda_remove_new_dashboard_message', WPDA_Dashboard::class, 'remove_new_dashboard_message' );
		if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
			$this->loader->add_action( 'wp_ajax_wpda_edit_chart', WPDA_Dashboard::class, 'edit_chart' );
			$this->loader->add_action( 'wp_ajax_wpda_widget_project_add', WPDAPRO_Widget_Project::class, 'ajax_widget' );
		}
		$this->loader->add_action( 'wp_ajax_wpda_widget_pub_add', WPDA_Widget_Publication::class, 'ajax_widget' );
		$this->loader->add_action( 'wp_ajax_wpda_widget_chart_add', WPDA_Widget_Google_Chart::class, 'ajax_widget' );
		$this->loader->add_action( 'wp_ajax_wpda_widget_chart_refresh', WPDA_Widget_Google_Chart::class, 'ajax_refresh' );

		// Add/remove favourites.
		$plugin_favourites = new WPDA_Favourites();
		$this->loader->add_action( 'admin_action_wpda_add_favourite', $plugin_favourites, 'add' );
		$this->loader->add_action( 'admin_action_wpda_rem_favourite', $plugin_favourites, 'rem' );

		// Show tables actions.
		$plugin_table_actions = new WPDA_Table_Actions();
		$this->loader->add_action( 'admin_action_wpda_show_table_actions', $plugin_table_actions, 'show' );

		$plugin_dictionary_list = new WPDA_Dictionary_Lists();
		// Get tables for a specific database.
		$this->loader->add_action( 'admin_action_wpda_get_tables', $plugin_dictionary_list, 'get_tables_ajax' );
		// Get columns for a specific table.
		$this->loader->add_action( 'admin_action_wpda_get_columns', $plugin_dictionary_list, 'get_columns' );
		// Get row count for a specific table.
		$this->loader->add_action( 'admin_action_wpda_get_table_row_count', $plugin_dictionary_list, 'get_table_row_count_ajax' );
		// Get table widget info.
		$this->loader->add_action( 'admin_action_wpda_get_table_widget_info', $plugin_dictionary_list, 'get_table_widget_info' );

		// Export project.
		$plugin_export_project = new WPDP_Export_Project();
		$this->loader->add_action( 'admin_action_wpda_export_project', $plugin_export_project, 'export' );

		// Data backup.
		$wpda_data_backup = new WPDA_Data_Export();
		$this->loader->add_action( 'wpda_data_backup', $wpda_data_backup, 'wpda_data_backup' );

		// Allow to add multiple user roles.
		$wpda_roles = new WPDA_Roles();
		$this->loader->add_action( 'user_new_form', $wpda_roles, 'multiple_roles_selection' );
		$this->loader->add_action( 'edit_user_profile', $wpda_roles, 'multiple_roles_selection' );
		$this->loader->add_action( 'profile_update', $wpda_roles, 'multiple_roles_update' );
		$this->loader->add_filter( 'manage_users_columns', $wpda_roles, 'multiple_roles_label' );

		// Check if a remote db connection can be established via ajax.
		$wpdadb = new WPDADB();
		$this->loader->add_action( 'admin_action_wpda_check_remote_database_connection', $wpdadb, 'check_remote_database_connection' );

		// Make WordPress user ID available to database connections.
		add_action(
			'wpda_dbinit',
			function( $wpdadb ) {
				if ( null !== $wpdadb ) {
					$suppress_errors = $wpdadb->suppress_errors( true );
					$wpdadb->query( 'set @wpda_wp_user_id = ' . get_current_user_id() );
					$wpdadb->suppress_errors( $suppress_errors );
				}
			},
			10,
			1
		);
		add_action(
			'wp_ajax_wpda_dbinit_admin',
			function() {
				if ( ! isset( $_POST['wpnonce'], $_POST['wpdaschema_name'] ) ) {
					WPDA::sent_header( 'application/json' );
					echo WPDA::sent_msg( 'ERROR', 'Invalid arguments' ); // phpcs:ignore WordPress.Security.EscapeOutput
					wp_die();
				}

				$wpnonce = sanitize_text_field( wp_unslash( $_REQUEST['wpnonce'] ) ); // input var okay.
				if (
					! current_user_can( 'manage_options' ) ||
					! wp_verify_nonce( $wpnonce, 'wpda_dbinit_admin_' . WPDA::get_current_user_login() )
				) {
					WPDA::sent_header( 'application/json' );
					echo WPDA::sent_msg( 'ERROR', 'Not authorized' ); // phpcs:ignore WordPress.Security.EscapeOutput
					wp_die();
				}

				$wpdaschema_name = sanitize_text_field( wp_unslash( $_REQUEST['wpdaschema_name'] ) ); // input var okay.

				$wpdadb = WPDADB::get_db_connection( $wpdaschema_name );
				if ( null === $wpdadb ) {
					WPDA::sent_header( 'application/json' );
					echo WPDA::sent_msg( 'ERROR', 'Cannot connect to database' ); // phpcs:ignore WordPress.Security.EscapeOutput
					wp_die();
				}

				$suppress_errors = $wpdadb->suppress_errors( true );
				$wpdadb->query( 'create function wpda_get_wp_user_id() returns integer deterministic no sql return @wpda_wp_user_id' );
				$error = $wpdadb->last_error;
				$fnc   = $wpdadb->dbh->query( "show function status like 'wpda_get_wp_user_id'" );
				$wpdadb->suppress_errors( $suppress_errors );

				if ( 1 === count( $fnc ) ) {
					echo WPDA::sent_msg( 'OK', '' ); // phpcs:ignore WordPress.Security.EscapeOutput
				} else {
					echo WPDA::sent_msg( 'ERROR', "Function not created [{$error}]" ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
				wp_die();
			},
			10,
			1
		);

		// Add id to wpda_datatables.js (for IE).
		$this->loader->add_filter( 'script_loader_tag', $this, 'add_id_to_script', 10, 3 );

		// Add CSV mapping calls.
		$wpda_csv_uploads_model = new WPDA_CSV_Uploads_Model();
		$this->loader->add_action( 'admin_action_wpda_save_csv_mapping', $wpda_csv_uploads_model, 'save_mapping' );
		$this->loader->add_action( 'admin_action_wpda_csv_preview_mapping', $wpda_csv_uploads_model, 'preview_mapping' );

		// Show what's new page and update option.
		add_action(
			'admin_action_wpda_show_whats_new',
			function () {
				if ( isset( $_REQUEST['whats_new'] ) && 'off' === $_REQUEST['whats_new'] ) { // phpcs:ignore WordPress.Security.NonceVerification
					WPDA::set_option( WPDA::OPTION_WPDA_SHOW_WHATS_NEW, 'off' );
				}
				header( 'Location: https://wpdataaccess.com/docs/documentation/updates/whats-new/' );
			},
			10,
			1
		);

		// Data Publisher services.
		$this->loader->add_action( 'wp_ajax_wpda_test_publication', \WPDataAccess\Data_Publisher\WPDA_Publisher_Form::class, 'test_publication' );

		if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
			// Premium data services
			$this->loader->add_action( 'wp_ajax_wpda_pds_mark_all_messages_as_read', \WPDataAccess\Premium\WPDAPRO_Data_Services\WPDAPRO_Data_Services::class, 'mark_all_messages_as_read' );
			$this->loader->add_action( 'wp_ajax_wpda_pds_delete_messages', \WPDataAccess\Premium\WPDAPRO_Data_Services\WPDAPRO_Data_Services::class, 'delete_messages' );
			$this->loader->add_action( 'wp_ajax_wpda_pds_update_interval', \WPDataAccess\Premium\WPDAPRO_Data_Services\WPDAPRO_Data_Services::class, 'update_interval' );

			// Data Publisher services.
			$this->loader->add_action( 'wp_ajax_wpda_non_selectable_cpts', \WPDataAccess\Premium\WPDAPRO_CPT\WPDAPRO_CPT_Services::class, 'set_non_selectable_cpts' );
			$this->loader->add_action( 'wp_ajax_wpda_get_custom_fields', \WPDataAccess\Premium\WPDAPRO_CPT\WPDAPRO_CPT_Services::class, 'get_custom_fields_ajax' );

			// ADD FULL TEXT SEARCH.

			// Add full-text search support.
			// Add index actions to Data Explorer main page > table settings.
			$wpdapro_search = new WPDataAccess\Premium\WPDAPRO_FullText_Search\WPDAPRO_Search();
			$this->loader->add_action( 'admin_action_wpdapro_create_fulltext_index', $wpdapro_search, 'create_fulltext_index' );
			$this->loader->add_action( 'admin_action_wpdapro_check_fulltext_index', $wpdapro_search, 'check_fulltext_index' );
			$this->loader->add_action( 'admin_action_wpdapro_cancel_fulltext_index', $wpdapro_search, 'cancel_fulltext_index' );
			$this->loader->add_action( 'admin_action_wpdapro_no_listbox_items', $wpdapro_search, 'no_listbox_items' );
			// Add settings to Data Explorer main page > table settings.
			$this->loader->add_action( 'wpda_prepend_table_settings', $wpdapro_search, 'add_search_settings', 10, 3 );
			// Add search actions to Data Explorer table page search box.
			$this->loader->add_action( 'wpda_add_search_actions', $wpdapro_search, 'add_search_actions', 10, 4 );
			// Add search html to Data Explorer table page search box.
			$this->loader->add_action( 'wpda_add_search_filter', $wpdapro_search, 'add_search_filter', 10, 4 );
			// Add individual column search to publication (creates jQuery DataTable search columns).
			$this->loader->add_filter( 'wpda_wpdataaccess_prepare', $wpdapro_search, 'add_column_search_to_publication', 10, 6 );
			// Add pro search to list tables.
			$this->loader->add_filter( 'wpda_construct_where_clause', $wpdapro_search, 'construct_where_clause', 10, 5 );

			// Add geolocation settings to Data Explorer main page > geolocation settings.
			$geolocation_settings = new WPDAPRO_Geo_Location();
			$this->loader->add_action( 'wpda_prepend_table_settings', $geolocation_settings, 'add_geolocation_settings', 11, 3 );

			// ADD INLINE EDITING.

			// Admin menu.
			add_action(
				'admin_enqueue_scripts',
				function () {
					if ( ! current_user_can( 'manage_options' ) ) {
						// Remove freemius support menu for non admin users.
						remove_submenu_page( 'wpda', 'wpda-wp-support-forum' );
					}
				}
			);

			// Add line editing support.
			// Add inline editing to list tables.
			$inline_editing = new WPDataAccess\Premium\WPDAPRO_Inline_Editing\WPDAPRO_Inline_Editing();
			$this->loader->add_filter( 'wpda_column_default', $inline_editing, 'add_inline_editing_to_column', 10, 8 );
			// Add ajax call to inline editing.
			$this->loader->add_action( 'admin_action_wpdapro_inline_editor', $inline_editing, 'wpdapro_inline_editor', 10, 7 );
			// Add inline editing column selection to Data Explorer settings.
			add_filter(
				'wpda_add_column_settings',
				function( $column_settings ) {
					$add_column = array(
						array(
							'label'       => 'Edit',
							'hint'        => 'Allow inline editing?',
							'name_prefix' => 'inline_editing_',
							'type'        => 'checkbox',
							'default'     => '',
							'disable'     => 'keys',
						),
					);

					if ( is_array( $column_settings ) ) {
						array_push( $column_settings, $add_column[0] );
						return $column_settings;
					} else {
						return $add_column;
					}
				},
				10,
				2
			);
			// Add inline editing to shortcode wpdadiehard.
			add_action(
				'wpda_wpdadiehard_prepare',
				function() {
					wp_enqueue_style( 'wpdapro_inline_editing' );
					wp_enqueue_script( 'wpdapro_inline_editor' );
				},
				10,
				1
			);
			// Add inline editing settings to Data Projects.
			$inline_editing_projects = new WPDataAccess\Premium\WPDAPRO_Inline_Editing\WPDAPRO_Data_Projects();
			$this->loader->add_action(
				'wpda_data_projects_add_table_option',
				$inline_editing_projects,
				'add_table_option',
				10,
				2
			);
			// Save Data Projects inline editing settings.
			$this->loader->add_filter(
				'wpda_data_projects_save_table_option',
				$inline_editing_projects,
				'save_table_option',
				10,
				3
			);
		}

		// Add custom CSS to freemius pages.
		add_action(
			'admin_footer',
			function() {
				if (
				'wpda-account' === $this->page ||
				'wpda-pricing' === $this->page
				) {
					?>
				<script type="application/javascript">
					jQuery(function() {
						jQuery.each(document.styleSheets, function (index, cssFile) {
							if (cssFile.href!==null && cssFile.href.toString().includes("load-styles.php")) {
								var classes = cssFile.rules || cssFile.cssRules;
								for (var x=0; x<classes.length; x++) {
									if (
										classes[x].selectorText!==undefined &&
										classes[x].selectorText!==null &&
										classes[x].selectorText.includes("#adminmenu li.current a.menu-top")
									) {
										jQuery("#adminmenu #toplevel_page_wpda a.menu-top").attr("style", classes[x].style.cssText);
									}
								}
							}
						});
					});
				</script>
					<?php
				}
			},
			10,
			1
		);
	}

	/**
	 * Needed for JDT to support IE
	 *
	 * @param string $tag Tag.
	 * @param string $handle Handle.
	 * @param string $src Source.
	 * @return mixed|string
	 */
	public function add_id_to_script( $tag, $handle, $src ) {
		if ( 'wpda_datatables' === $handle ) {
			$tag = '<script id="wpda_datatables" src="' . $src . '"></script>'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
		}
		return $tag;
	}

	/**
	 * Add public hooks
	 *
	 * Initiates {@see WP_Data_Access_Public} (public functionality), {@see WPDA_Data_Tables} (ajax call to support
	 * server side jQuery DataTables functionality). Adds the appropriate actions to
	 * the loader.
	 *
	 * @since   1.0.0
	 *
	 * @see WP_Data_Access_Public
	 * @see WPDA_Data_Tables
	 * @see WPDA_Dictionary_Lists
	 */
	private function define_public_hooks() {
		$plugin_public = new WP_Data_Access_Public();

		// Shortcodes.
		$this->loader->add_action( 'init', $plugin_public, 'register_shortcodes' );

		// Public scripts.
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_bar_menu', $plugin_public, 'add_data_projects_to_admin_toolbar', 9999 );

		// Ajax calls.
		$plugin_datatables = new WPDA_Data_Tables();
		$this->loader->add_action( 'wp_ajax_wpda_datatables', $plugin_datatables, 'get_data' );
		$this->loader->add_action( 'wp_ajax_nopriv_wpda_datatables', $plugin_datatables, 'get_data' );

		// Export action.
		$this->loader->add_action( 'wp_ajax_wpda_export', WPDA_Export::class, 'export_ajax' );
		$this->loader->add_action( 'wp_ajax_nopriv_wpda_export', WPDA_Export::class, 'export_ajax' );

		// Add id to wpda_datatables.js (for IE).
		$this->loader->add_filter( 'script_loader_tag', $this, 'add_id_to_script', 10, 3 );

		// Autocomplete.
		$autocomplete_service = new WPDA_Autocomplete();
		$this->loader->add_action( 'wp_ajax_wpda_autocomplete', $autocomplete_service, 'autocomplete' );
		$this->loader->add_action( 'wp_ajax_nopriv_wpda_autocomplete', $autocomplete_service, 'autocomplete_anonymous' );

		if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
			// ADD GEOLOCATION SUPPORT.
			$geo = new WPDAPRO_Geo_Location_WS();
			$this->loader->add_action( 'wp_ajax_wpdapro_update_geolocation', $geo, 'update_geolocation_ajax' );
			$this->loader->add_action( 'wp_ajax_wpdapro_geolocation_get_data', $geo, 'get_data_ajax' );
			$this->loader->add_action( 'wp_ajax_nopriv_wpdapro_geolocation_get_data', $geo, 'get_data_ajax_anonymous' );

			// ADD INLINE EDITING.
			$inline_editing = new WPDataAccess\Premium\WPDAPRO_Inline_Editing\WPDAPRO_Inline_Editing();
			$this->loader->add_action( 'wp_ajax_wpdapro_inline_editor', $inline_editing, 'wpdapro_inline_editor', 10, 7 );
			$this->loader->add_action( 'wp_ajax_nopriv_wpdapro_inline_editor', $inline_editing, 'wpdapro_inline_editor', 10, 7 );

			// Add responsive forms.
			$wpdadataforms = new WPDataAccess\Premium\WPDAPRO_Data_Forms\WPDAPRO_Data_Forms_WS();
			// Get list to built table (returns jQuery DataTables format).
			$this->loader->add_action( 'wp_ajax_wpdadataforms_get_list', $wpdadataforms, 'get_list' );
			$this->loader->add_action( 'wp_ajax_nopriv_wpdadataforms_get_list', $wpdadataforms, 'get_list_anonymous' );
			// Get single row for data entry form (returns AngularJS format).
			$this->loader->add_action( 'wp_ajax_wpdadataforms_get_form_data', $wpdadataforms, 'get_form_data' );
			$this->loader->add_action( 'wp_ajax_nopriv_wpdadataforms_get_form_data', $wpdadataforms, 'get_form_data_anonymous' );
			// Update row.
			$this->loader->add_action( 'wp_ajax_wpdadataforms_update_form_data', $wpdadataforms, 'update_form_data' );
			$this->loader->add_action( 'wp_ajax_nopriv_wpdadataforms_update_form_data', $wpdadataforms, 'update_form_data_anonymous' );
			// Insert row.
			$this->loader->add_action( 'wp_ajax_wpdadataforms_insert_form_data', $wpdadataforms, 'insert_form_data' );
			$this->loader->add_action( 'wp_ajax_nopriv_wpdadataforms_insert_form_data', $wpdadataforms, 'insert_form_data_anonymous' );
			// Insert child row n:m relationship.
			$this->loader->add_action( 'wp_ajax_wpdadataforms_insert_form_data_nm', $wpdadataforms, 'insert_form_data_nm' );
			$this->loader->add_action( 'wp_ajax_nopriv_wpdadataforms_insert_form_data_nm', $wpdadataforms, 'insert_form_data_nm_anonymous' );
			// Delete relationship.
			$this->loader->add_action( 'wp_ajax_wpdadataforms_delrel_form_data', $wpdadataforms, 'delrel_form_data' );
			$this->loader->add_action( 'wp_ajax_nopriv_wpdadataforms_delrel_form_data', $wpdadataforms, 'delrel_form_data_anonymous' );
			// Delete row.
			$this->loader->add_action( 'wp_ajax_wpdadataforms_delete_form_data', $wpdadataforms, 'delete_form_data' );
			$this->loader->add_action( 'wp_ajax_nopriv_wpdadataforms_delete_form_data', $wpdadataforms, 'delete_form_data_anonymous' );
			// Lookups.
			$this->loader->add_action( 'wp_ajax_wpdadataforms_lookup', $wpdadataforms, 'lookup' );
			$this->loader->add_action( 'wp_ajax_nopriv_wpdadataforms_lookup', $wpdadataforms, 'lookup_anonymous' );
			$this->loader->add_action( 'wp_ajax_wpdadataforms_conditional_lookup', $wpdadataforms, 'conditional_lookup' );
			$this->loader->add_action( 'wp_ajax_nopriv_wpdadataforms_conditional_lookup', $wpdadataforms, 'conditional_lookup_anonymous' );
			$this->loader->add_action( 'wp_ajax_wpdadataforms_conditional_lookup_get', $wpdadataforms, 'conditional_lookup_get' );
			$this->loader->add_action( 'wp_ajax_nopriv_wpdadataforms_conditional_lookup_get', $wpdadataforms, 'conditional_lookup_get_anonymous' );
			// Autocomplete.
			$this->loader->add_action( 'wp_ajax_wpdadataforms_autocomplete', $wpdadataforms, 'autocomplete' );
			$this->loader->add_action( 'wp_ajax_nopriv_wpdadataforms_autocomplete', $wpdadataforms, 'autocomplete_anonymous' );
			$this->loader->add_action( 'wp_ajax_wpdadataforms_autocomplete_get', $wpdadataforms, 'autocomplete_get' );
			$this->loader->add_action( 'wp_ajax_nopriv_wpdadataforms_autocomplete_get', $wpdadataforms, 'autocomplete_get_anonymous' );

			// Embedding panels.
			$dashboard = new \WPDataAccess\Premium\WPDAPRO_Dashboard\WPDAPRO_Dashboard();
			$this->loader->add_action( 'wp_ajax_wpda_embed_widget', $dashboard, 'embed_wpdapanel' );
			$this->loader->add_action( 'wp_ajax_nopriv_wpda_embed_widget', $dashboard, 'embed_wpdapanel' );
			$this->loader->add_action( 'wp_ajax_wpda_widget_dbms_refresh', WPDA_Widget_Dbms::class, 'ajax_refresh' );
			$this->loader->add_action( 'wp_ajax_nopriv_wpda_widget_dbms_refresh', WPDA_Widget_Dbms::class, 'ajax_refresh' );
			$this->loader->add_action( 'wp_ajax_wpda_widget_chart_refresh', WPDA_Widget_Google_Chart::class, 'ajax_refresh' );
			$this->loader->add_action( 'wp_ajax_nopriv_wpda_widget_chart_refresh', WPDA_Widget_Google_Chart::class, 'ajax_refresh' );
		}
	}

	/**
	 * Start plugin loader
	 *
	 * @since   1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * Add plugin settings page
	 */
	public function wpdataaccess_register_settings_page() {
		add_options_page(
			'WP Data Access',
			'WP Data Access',
			'manage_options',
			WP_Data_Access_Admin::PAGE_SETTINGS,
			array(
				$this,
				'wpdataaccess_settings_page',
			)
		);
	}

	/**
	 * Show settings page
	 */
	public function wpdataaccess_settings_page() {
		WPDA_Dashboard::add_dashboard();

		$current_tab = isset( $_REQUEST['tab'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['tab'] ) ) : 'plugin'; // phpcs:ignore WordPress.Security.NonceVerification
		switch ( $current_tab ) {
			case 'backend':
				$wpda_settings_class_name = 'WPDA_Settings_BackEnd';
				$help_url                 = 'https://wpdataaccess.com/docs/documentation/plugin-settings/back-end/';
				break;
			case 'frontend':
				$wpda_settings_class_name = 'WPDA_Settings_FrontEnd';
				$help_url                 = 'https://wpdataaccess.com/docs/documentation/plugin-settings/front-end/';
				break;
			case 'pds':
				$wpda_settings_class_name = 'WPDA_Settings_PDS';
				$help_url                 = 'https://wpdataaccess.com/docs/documentation/plugin-settings/premium-data-services/';
				break;
			case 'dashboard':
				$wpda_settings_class_name = 'WPDA_Settings_Dashboard';
				$help_url                 = 'https://wpdataaccess.com/docs/documentation/plugin-settings/dashboard/';
				break;
			case 'datatables':
				$wpda_settings_class_name = 'WPDA_Settings_DataTables';
				$help_url                 = 'https://wpdataaccess.com/docs/documentation/plugin-settings/datatables/';
				break;
			case 'datapublisher':
				$wpda_settings_class_name = 'WPDA_Settings_DataPublisher';
				$help_url                 = 'https://wpdataaccess.com/docs/documentation/plugin-settings/data-publisher/';
				break;
			case 'dataforms':
				$wpda_settings_class_name = 'WPDA_Settings_DataForms';
				$help_url                 = 'https://wpdataaccess.com/docs/documentation/plugin-settings/data-forms/';
				break;
			case 'databackup':
				$wpda_settings_class_name = 'WPDA_Settings_DataBackup';
				$help_url                 = 'https://wpdataaccess.com/docs/documentation/plugin-settings/data-backup/';
				break;
			case 'uninstall':
				$wpda_settings_class_name = 'WPDA_Settings_Uninstall';
				$help_url                 = 'https://wpdataaccess.com/docs/documentation/plugin-settings/uninstall/';
				break;
			case 'repository':
				$wpda_settings_class_name = 'WPDA_Settings_ManageRepository';
				$help_url                 = 'https://wpdataaccess.com/docs/documentation/plugin-settings/manage-repository/';
				break;
			case 'roles':
				$wpda_settings_class_name = 'WPDA_Settings_ManageRoles';
				$help_url                 = 'https://wpdataaccess.com/docs/documentation/plugin-settings/manage-roles/';
				break;
			case 'system':
				$wpda_settings_class_name = 'WPDA_Settings_SystemInfo';
				$help_url                 = 'https://wpdataaccess.com/docs/documentation/plugin-settings/system-info/';
				break;
			default:
				$wpda_settings_class_name = 'WPDA_Settings_Plugin';
				$help_url                 = 'https://wpdataaccess.com/docs/documentation/plugin-settings/plugin/';
		}
		$wpda_settings_class_name = '\\WPDataAccess\\Settings\\' . $wpda_settings_class_name;
		$wpda_settings            = new $wpda_settings_class_name( $current_tab, $help_url );
		$wpda_settings->show();
	}

}
