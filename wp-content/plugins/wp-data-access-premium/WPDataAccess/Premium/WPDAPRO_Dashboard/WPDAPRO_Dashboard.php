<?php

namespace WPDataAccess\Premium\WPDAPRO_Dashboard {

    use WPDataAccess\Dashboard\WPDA_Dashboard;
	use WPDataAccess\Dashboard\WPDA_Widget_Code;
	use WPDataAccess\Dashboard\WPDA_Widget_Dbms;
	use WPDataAccess\Dashboard\WPDA_Widget_Google_Chart;
	use WPDataAccess\Dashboard\WPDA_Widget_Publication;
	use WPDataAccess\Premium\WPDAPRO_Data_Forms\WPDAPRO_Data_Forms_Init;
	use WPDataAccess\Settings\WPDA_Settings_Dashboard;
	use WPDataAccess\Settings\WPDA_Settings_FrontEnd;
	use WPDataAccess\WPDA;
	use WPDataAccess\Premium\WPDAPRO_Data_Publisher\WPDAPRO_Data_Publisher_Init;

	class WPDAPRO_Dashboard {

		const OPTION_DASHBOARDS		   = 'wpda-dashboards';
        const OPTION_DASHBOARD_WIDGETS = 'wpda-dashboard-widgets';
		const OPTION_SHARED_DASHBOARDS = 'wpda-shared-dashboards';

        protected $wpda_dashboard = null;

        public function __construct() {
            $this->load_dashboard();
        }

        protected function load_dashboard() {
            $this->wpda_dashboard = get_option( self::OPTION_DASHBOARD_WIDGETS );

            if ( false === $this->wpda_dashboard ) {
                $this->wpda_dashboard = [];
            }
        }

		public function save_dashboard() {
            update_option( self::OPTION_DASHBOARD_WIDGETS, $this->wpda_dashboard );
        }

        public function add_widget( $widget ) {
            if ( isset( $widget['widgetName'] ) ) {
                $this->wpda_dashboard[ $widget['widgetName'] ] = $widget;
            }
        }

		public function del_widget( $widget_name ) {
            unset( $this->wpda_dashboard[ $widget_name ] );
        }

        public function get_widget_list( $filter = true ) {
        	ksort($this->wpda_dashboard);

			if ( current_user_can( 'manage_options' ) || ! $filter ) {
				return $this->wpda_dashboard;
			} else {
				$wpda_dashboard = [];
				$user_login     = WPDA::get_current_user_login();
				$user_roles     = WPDA::get_current_user_roles();
				foreach ( $this->wpda_dashboard as $key => $widget ) {
					if (
						isset( $widget['widgetShare']['users'] ) &&
						$widget['widgetShare']['users'] === $user_login
					) {
						$wpda_dashboard[ $key ] = $widget;
					} else {
						$has_access = false;
						foreach ( $user_roles as $user_role ) {
							if (
								isset( $widget['widgetShare']['roles'] ) &&
								false !== stripos( $widget['widgetShare']['roles'], $user_role )
							) {
								$has_access = true;
							}
						}
						if ( $has_access ) {
							$wpda_dashboard[ $key ] = $widget;
						}
					}
				}

				return $wpda_dashboard;
			}
        }

		public function get_widget( $widget_name ) {
        	return isset( $this->wpda_dashboard[ $widget_name ] ) ? $this->wpda_dashboard[ $widget_name ] : null;
		}

		public function get_dashboards() {
			$dashboards = get_user_meta(
				WPDA::get_current_user_id(),
				self::OPTION_DASHBOARDS,
				true
			);
			return ''===$dashboards ? [] : $dashboards;
		}

		protected function save_dashboards( $dashboards ) {
			update_user_meta(
				WPDA::get_current_user_id(),
				self::OPTION_DASHBOARDS,
				$dashboards
			);
		}

		public function add_dashboard( $new_dashboard ) {
			$dashboards = $this->get_dashboards();
			$dashboards[] = $new_dashboard;
			$this->save_dashboards( $dashboards );
		}

		public function delete_dashboard( $delete_dashboard, $tab_name ) {
			$dashboards = $this->get_dashboards();
			if ( ( $key = array_search( $delete_dashboard, $dashboards ) ) !== false ) {
				unset( $dashboards[ $key ] );
			}

			// Remove dashboard widgets
			$this->delete_widget_positions( $tab_name );

			$this->save_dashboards( $dashboards );
		}

		protected function rename_dashboard( $old_name, $new_name ) {
			$dashboards = $this->get_dashboards();

			if ( ( $key = array_search( $old_name, $dashboards ) ) !== false ) {
				unset( $dashboards[ $key ] );
				$dashboards[] = $new_name;
				$this->save_dashboards( $dashboards );

				// Rename dashboard widgets
				global $wpdb;
				$wpdb->update(
					"{$wpdb->prefix}usermeta",
					[
						'meta_key' => self::OPTION_DASHBOARD_WIDGETS . WPDA_Dashboard::get_tab_name( $new_name )
					],
					[
						'meta_key' => self::OPTION_DASHBOARD_WIDGETS . WPDA_Dashboard::get_tab_name( $old_name )
					]
				);

				// Rename shares
				$shared_dashboards = get_option( WPDAPRO_Dashboard::OPTION_SHARED_DASHBOARDS );
				foreach ( $shared_dashboards as $key => $shared_dashboard ) {
					if ( $shared_dashboard['dashboardName'] === $old_name ) {
						$shared_dashboards[$key]['dashboardName'] = $new_name;
					}
				}
				update_option( self::OPTION_SHARED_DASHBOARDS, $shared_dashboards );
			}
		}

		protected function load_widget_positions( $tab_name, $user_id = null ) {
            $widgets = get_user_meta(
				( null === $user_id ? WPDA::get_current_user_id() : $user_id ),
				self::OPTION_DASHBOARD_WIDGETS . $tab_name
			);

			return false === $widgets ? [] : $widgets;
        }

        protected function save_widget_positions( $tab_name, $widgets ) {
			update_user_meta(
				WPDA::get_current_user_id(),
				self::OPTION_DASHBOARD_WIDGETS . $tab_name,
				$widgets
			);
        }

        public function get_widget_positions( $tab_name, $user_id = null ) {
            return $this->load_widget_positions( $tab_name, $user_id );
        }

		protected function delete_widget_positions( $tab_name ) {
			delete_user_meta(
				WPDA::get_current_user_id(),
				self::OPTION_DASHBOARD_WIDGETS . $tab_name
			);
		}

		public function hide_default_tab() {
			$user_roles    = implode( ',',WPDA::get_current_user_roles() );
			$granted_roles = get_option( WPDA_Settings_Dashboard::DASHBOARD_ROLES_HIDE_DEFAULT );
			if ( strpos( ",{$granted_roles},", ",{$user_roles}," ) !== false ) {
				return true;
			}

			$user_login	   = WPDA::get_current_user_login();
			$granted_users = get_option( WPDA_Settings_Dashboard::DASHBOARD_USERS_HIDE_DEFAULT );
			if ( strpos( ",{$granted_users},", ",{$user_login}," ) !== false ) {
				return true;
			}

			return false;
		}

		public function cannot_create_dashboard() {
			$user_roles   = implode( ',',WPDA::get_current_user_roles() );
			$create_roles = get_option( WPDA_Settings_Dashboard::DASHBOARD_ROLES_CREATE );
			if ( strpos( ",{$create_roles},", ",{$user_roles}," ) !== false ) {
				return true;
			}

			$user_login	  = WPDA::get_current_user_login();
			$create_users = get_option( WPDA_Settings_Dashboard::DASHBOARD_USERS_CREATE );
			if ( strpos( ",{$create_users},", ",{$user_login}," ) !== false ) {
				return true;
			}

			return false;
		}

		public function save() {
            $updated_dashboard = false;

            if ( isset( $_POST['wpda_deleted'] ) && is_array( $_POST['wpda_deleted'] ) ) {
                foreach( $_POST['wpda_deleted'] as $wpda_deleted ) {
                    $widget_name = sanitize_text_field( $wpda_deleted );
                    $this->del_widget( $widget_name );
                    $updated_dashboard = true;
                }
            }

            if ( isset( $_POST['wpda_widgets'] ) && is_array( $_POST['wpda_widgets'] ) ) {
                $widgets = [];
                // Sanitize widget array
                foreach ( $_POST['wpda_widgets'] as $widget ) {
                    $widget_sanitized = [];
                    foreach ( $widget as $key => $val ) {
						if (
							is_array( $val ) &&
							( 'chartType' === $key || 'userChartTypeList' === $key )
						) {
							$sanitized_array = [];
							foreach ( $val as $value ) {
								$sanitized_array[] = sanitize_text_field( $value );
							}
							$widget_sanitized[ sanitize_text_field( $key ) ] = $sanitized_array;
						} elseif ( is_array( $val ) && ( 'chartOptions' === $key || 'widgetShare' === $key ) ) {
							$widget_sanitized[ sanitize_text_field( $key ) ] = $val; // JSON
						} elseif ( 'chartSql' === $key ) {
							$widget_sanitized[ sanitize_text_field( $key ) ] = sanitize_textarea_field( $val );
						} else {
                            $widget_sanitized[ sanitize_text_field( $key ) ] = sanitize_text_field( $val );
                        }
                    }
                    $widgets[] = $widget_sanitized;
                }
                // Save widgets
                foreach ( $widgets as $widget ) {
                    $this->add_widget( $widget );
                    $updated_dashboard = true;
                }
            }

			if ( isset( $_POST['wpda_old_name'] ) && isset( $_POST['wpda_new_name'] ) ) {
				$wpda_old_name = sanitize_text_field( $_POST['wpda_old_name'] );
				$wpda_new_name = sanitize_text_field( $_POST['wpda_new_name'] );

				$dashboards = $this->get_dashboards();
				if ( ( array_search( $wpda_new_name, $dashboards ) ) !== false ) {
					echo WPDA_Dashboard::msg( 'ERROR', 'Dashboard name is already is use' );
					return;
				}

				if ( ( array_search( $wpda_old_name, $dashboards ) ) !== false ) {
					$this->rename_dashboard( $wpda_old_name, $wpda_new_name );
					$updated_dashboard = true;
				} else {
					echo WPDA_Dashboard::msg( 'ERROR', 'Dashboard not found' );
					return;
				}
			}

            if ( $updated_dashboard ) {
                $this->save_dashboard();
            }

            if ( isset( $_POST['wpda_positions'] ) && is_array( $_POST['wpda_positions'] ) ) {
                $widgets = [];
                // Sanitize positions array
                foreach ( $_POST['wpda_positions'] as $key => $wpda_position ) {
                    $column_widgets = [];
                    foreach ( $wpda_position as $column_position ) {
                        $column_widgets[] = sanitize_text_field( $column_position );
                    }
                    $widgets[ sanitize_text_field( $key ) ] = $column_widgets;
                }
                $this->save_widget_positions(
					isset( $_REQUEST['wpda_tabname'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpda_tabname'] ) ) : '',
					$widgets
				);
            }

			if ( isset( $_POST['wpda_shared_dashboards'] ) ) {
				// Share dashboard
				$dashboard 		   = wp_unslash( $_POST['wpda_shared_dashboards'] );
				$shared_dashboards = get_option( self::OPTION_SHARED_DASHBOARDS );

				$found = false;
				if ( false == $shared_dashboards ) {
					$shared_dashboards = [];
				} else {
					$user_id = WPDA::get_current_user_id();
					foreach ( $shared_dashboards as $key => $shared_dashboard ) {
						if ( $shared_dashboard['dashboardName'] === $dashboard['dashboardName'] ) {
							if ( $shared_dashboard['dashboardOwner'] == $user_id ) {
								unset( $shared_dashboards[ $key ] );
							} else {
								$found = true;
							}
						}
					}
				}

				if ( ! $found ) {
					array_push( $shared_dashboards, $dashboard );
					update_option( self::OPTION_SHARED_DASHBOARDS, $shared_dashboards );
				} else {
					echo WPDA_Dashboard::msg( 'ERROR', 'Another shared dashboard with this name is already in use, please rename your dashboard' );
					return;
				}
			}

            echo WPDA_Dashboard::msg( 'SUCCESS', 'Dashboard successfully saved' );
        }

		public static function get_user_shared_dashboards() {
			return self::get_shared_dashboards('false');
		}

		public static function get_locked_shared_dashboards() {
			return self::get_shared_dashboards('true');
		}

		public static function get_shared_dashboards( $dashboardLocked ) {
			$shared_dashboards 		= get_option( self::OPTION_SHARED_DASHBOARDS );
			$user_login        		= WPDA::get_current_user_login();
			$user_shared_dashboards = [];

			if ( is_array( $shared_dashboards ) ) {
				foreach ( $shared_dashboards as $key => $shared_dashboard ) {
					if ( $shared_dashboard['dashboardLocked'] === $dashboardLocked ) {
						if ( strpos( $shared_dashboard['dashboardUsers'], $user_login ) !== false ) {
							array_push( $user_shared_dashboards, $shared_dashboard );
						} else {
							$user_roles = implode( ',',WPDA::get_current_user_roles() );
							if ( strpos( ",{$shared_dashboard['dashboardRoles']},", ",{$user_roles}," ) !== false ) {
								array_push( $user_shared_dashboards, $shared_dashboard );
							}
						}
					}
				}
			}

			return $user_shared_dashboards;
		}

		public function shortcode_wpdawidget( $atts ) {
			$editing = WPDA::is_editing_post();
			if ( false !== $editing ) {
				// Prevent errors when user is editing a post
				return $editing;
			}

			$atts    = array_change_key_case( (array) $atts, CASE_LOWER );
			$wp_atts = shortcode_atts(
				[
					'widget_name' => '',
				], $atts
			);

			if ( '' === $wp_atts['widget_name'] ) {
				return __( 'ERROR: Missing argument(s) [need a valid widget_name]', 'wp-data-access' );
			}

			ob_start();

			$widget = $this->get_widget( $wp_atts['widget_name'] );
			if ( null === $widget ) {
				echo __( 'ERROR: Widget not found', 'wp-data-access' );
			} else {
				if ( isset ( $widget['widgetType'], $widget['widgetName'], $widget['widgetShare'] ) ) {
					if ( 'pub' === $widget['widgetType'] ) {
						echo __( 'ERROR: Please use shortcode wpdataaccess for publications', 'wp-data-access' );
					} elseif ( 'project' === $widget['widgetType'] ) {
						echo __( 'ERROR: Please use shortcode wpdadataproject or wpdadataforms for projects', 'wp-data-access' );
					} else {
						if ( isset( $widget['widgetShare']['post'], $widget['widgetShare']['page'] ) ) {
							if ( 'true' !== $widget['widgetShare']['post'] && WPDA::is_post() ) {
								echo sprintf( __( 'ERROR: Sorry, you cannot use widget "%s" in a post', 'wp-data-access' ), $widget['widgetName'] );
							} elseif ( 'true' !== $widget['widgetShare']['page'] && WPDA::is_page() ) {
								echo sprintf( __( 'ERROR: Sorry, you cannot use widget "%s" in a page', 'wp-data-access' ), $widget['widgetName'] );
							} else {
								switch ( $widget['widgetType'] ) {
									case 'dbs':
										$args = $this->get_dbms_args( $widget );
										if ( false !== $args ) {
											$dbms = new WPDA_Widget_Dbms( $args );
											$dbms->do_shortcode( $widget );
										} else {
											echo __( 'ERROR: Widget data corrupt', 'wp-data-access' );
										}
										break;
									case 'chart':
										$args = $this->get_chart_args( $widget );
										if ( false !== $args ) {
											wp_enqueue_style( 'wpda_panels' );
											wp_enqueue_script( 'wpda_google_charts' );
											wp_enqueue_script( 'wpda_panels' );

											$chart = new WPDA_Widget_Google_Chart( $args );
											$chart->do_shortcode( $widget );
										} else {
											echo __( 'ERROR: Widget data corrupt', 'wp-data-access' );
										}
										break;
								}
							}
						} else {
							echo sprintf( __( 'ERROR: Sorry, you cannot use widget "%s" in a post or page', 'wp-data-access' ), $widget['widgetName'] );
						}
					}
				} else {
					return __( 'ERROR: Widget data corrupt', 'wp-data-access' );
				}
			}

			return ob_get_clean();
		}

		protected function get_code_args( $widget ) {
			if ( isset( $widget['codeId'] ) ) {
				return [
					'code_id' => $widget['codeId'],
				];
			} else {
				return false;
			}
		}

		protected function get_chart_args( $widget ) {
			if ( isset(
				$widget['chartType'],
				$widget['userChartTypeList'],
				$widget['chartDbs'],
				$widget['chartSql']
			)
			) {
				return [
					'outputType'        => $widget['chartType'],
					'userChartTypeList' => $widget['userChartTypeList'],
					'dbs'               => $widget['chartDbs'],
					'query'             => $widget['chartSql'],
				];
			} else {
				return false;
			}
		}

		protected function get_dbms_args( $widget ) {
			if ( isset( $widget['dbsDbms'] ) ) {
				return [
					'schema_name' => $widget['dbsDbms'],
				];
			} else {
				return false;
			}
		}

		protected function get_project_args( $widget ) {
			if ( isset( $widget['projectId'] ) ) {
				return [
					'project_id' => $widget['projectId'],
				];
			} else {
				return false;
			}
		}

		protected function get_pub_args( $widget ) {
			if ( isset( $widget['pubId'] ) ) {
				return [
					'pub_id' => $widget['pubId'],
				];
			} else {
				return false;
			}
		}

		public function embed_wpdapanel() {
        	WPDA::sent_header('text/javascript');

			$panel_name     = isset( $_REQUEST['widget_name'] ) ? sanitize_text_field( $_REQUEST['widget_name'] ) : null; // input var okay.
			$target_element = isset( $_REQUEST['target_element'] ) ? sanitize_text_field( $_REQUEST['target_element'] ) : null; // input var okay.
			if ( null === $panel_name || null === $target_element ) {
				echo 'console.log("' . __( 'WP Data Access ERROR: Invalid arguments', 'wp-data-access' ) . '");';
				wp_die();
			}
			$jquery_ui_theme = isset( $_REQUEST['jquery_ui_theme'] ) ? sanitize_text_field( $_REQUEST['jquery_ui_theme'] ) : 'smoothness'; // input var okay.
			if ( ! in_array( $jquery_ui_theme, WPDA_Settings_FrontEnd::UI_THEMES ) ) {
				$jquery_ui_theme = 'smoothness';
			}

			$widget = $this->get_widget( $panel_name );
			if ( null === $widget ) {
				$panel_not_found = sprintf( __( 'WP Data Access ERROR: Panel \"%s\" not found', 'wp-data-access' ), $panel_name );
				echo 'document.getElementById("' . $target_element . '").innerHTML = "' . $panel_not_found . '";';
				echo 'console.log("' . $panel_not_found . '");';
			} else {
				$widget_type           = isset ( $widget['widgetType'] ) ? $widget['widgetType'] : '';
				$share                 = isset( $widget['widgetShare']['embed'] ) ? $widget['widgetShare']['embed'] : 'block';
				$embedding_not_allowed = sprintf( __( 'WP Data Access ERROR: Sorry, embedding panel \"%s\" is not allowed', 'wp-data-access' ), $panel_name );
				if ( 'block' === $share ) {
					echo 'document.getElementById("' . $target_element . '").innerHTML = "' . $embedding_not_allowed . '";';
					return;
				} elseif ( 'allow' === $share ) {
					$allowed = isset( $widget['widgetShare']['allow'] ) ? $widget['widgetShare']['allow'] : [];
					if ( is_array( $allowed ) ) {
						$is_allowed = false;
						foreach ( $allowed as $allow ) {
							if ( isset( $_SERVER['HTTP_REFERER'] ) && $allow === $_SERVER['HTTP_REFERER'] ) {
								$is_allowed = true;
								break;
							}
						}
						if ( ! $is_allowed ) {
							echo 'document.getElementById("' . $target_element . '").innerHTML = "' . $embedding_not_allowed . '";';
							echo 'console.log("' . $embedding_not_allowed . '");';
							return;
						}
					} else {
						echo 'document.getElementById("' . $target_element . '").innerHTML = "' . $embedding_not_allowed . '";';
						echo 'console.log("' . $embedding_not_allowed . '");';
						return;
					}
				}

				$scripts = [];
				$styles = [
					'wpda_fontawesome_icons-css'		=> WPDA::CDN_FONTAWESOME . 'fontawesome.min.css',
					'wpda_fontawesome_icons_solid-css'	=> WPDA::CDN_FONTAWESOME . 'solid.min.css',
					'wpda_panels-css'					=> plugins_url( '../../../assets/premium/css/wpda_panels.css', __FILE__ ),
					'wpda_embedded-css'					=> plugins_url( '../../../assets/premium/css/wpda_embedded.css', __FILE__ ),
					'wpdapro_dashicons'					=> home_url() . '/wp-includes/css/dashicons.min.css',
				];

				$scripts_chart = [
					'wpda_gcharts-js'	=> WPDA::GOOGLE_CHARTS,
					'wpda_panels-js'	=> plugins_url( '../../../assets/premium/js/wpda_panels.js', __FILE__ ),
				];
				$styles_chart = [];

				$scripts_dbs = [
					'wpda_dbms-js'	=> plugins_url( '../../../assets/js/wpda_dbms.js', __FILE__ ),
				];
				$styles_dbs = [];

				$scripts_jdt = [
					'wpda_jquery_datatables-js'	            	=> WPDAPRO_Data_Publisher_Init::JDT_CORE . 'js/jquery.dataTables.min.js',
					'wpda_jquery_datatables_responsive-js'	 	=> WPDAPRO_Data_Publisher_Init::JDT_RESPONSIVE . 'js/dataTables.responsive.min.js',
					'wpda_jquery_ui_datatables-js'				=> WPDAPRO_Data_Publisher_Init::JDT_JQUERYUI . 'js/dataTables.jqueryui.min.js',
				];
				$styles_jdt = [
					'wpda_jquery_datatables-css'				=> WPDAPRO_Data_Publisher_Init::JDT_CORE . 'css/jquery.dataTables.min.css',
					'wpda_jquery_datatables_responsive-css'		=> WPDAPRO_Data_Publisher_Init::JDT_RESPONSIVE . 'css/responsive.dataTables.min.css',
				];

				$scripts_jdt_ext = [
					'wpda_jquery_datatables_buttons-js'			=> WPDAPRO_Data_Publisher_Init::JDT_BUTTONS . 'js/dataTables.buttons.min.js',
					'wpda_jquery_datatables_flash-js'			=> WPDAPRO_Data_Publisher_Init::JDT_BUTTONS . 'js/buttons.flash.min.js',
					'wpda_jquery_datatables_jszip-js'			=> WPDAPRO_Data_Publisher_Init::JDT_JSZIP . 'jszip.min.js',
					'wpda_jquery_datatables_pdfmake-js'			=> WPDAPRO_Data_Publisher_Init::JDT_PDFMAKE . 'pdfmake.min.js',
					'wpda_jquery_datatables_vfs_fonts-js'		=> WPDAPRO_Data_Publisher_Init::JDT_PDFMAKE . 'vfs_fonts.js',
					'wpda_jquery_datatables_html5-js'			=> WPDAPRO_Data_Publisher_Init::JDT_BUTTONS . 'js/buttons.html5.min.js',
					'wpda_jquery_datatables_print-js'			=> WPDAPRO_Data_Publisher_Init::JDT_BUTTONS . 'js/buttons.print.min.js',
					'wpda_jquery_datatables_select-js'			=> WPDAPRO_Data_Publisher_Init::JDT_SELECT . 'js/dataTables.select.min.js',
					'wpda_jquery_datatables_colvis-js'			=> WPDAPRO_Data_Publisher_Init::JDT_BUTTONS . 'js/buttons.colVis.min.js',
					'wpda_jquery_datatables_rowgroup-js'		=> WPDAPRO_Data_Publisher_Init::JDT_ROWGROUP . 'js/dataTables.rowGroup.min.js',
					'wpda_jquery_datatables_searchbuilder-js'	=> WPDAPRO_Data_Publisher_Init::JDT_SB . 'js/dataTables.searchBuilder.min.js',
					'wpda_jquery_datatables_searchpanes-js'		=> WPDAPRO_Data_Publisher_Init::JDT_SP . 'js/dataTables.searchPanes.min.js',
					'wpda_jquery_datatables_datetime-js'		=> WPDAPRO_Data_Publisher_Init::JDT_DATETIME . 'js/dataTables.dateTime.min.js',
				];
				$styles_jdt_ext = [
					'wpda_jquery_datatables_buttons-css' 		=> WPDAPRO_Data_Publisher_Init::JDT_BUTTONS . 'css/buttons.dataTables.min.css',
					'wpda_jquery_datatables_select-css' 		=> WPDAPRO_Data_Publisher_Init::JDT_SELECT . 'css/select.dataTables.min.css',
					'wpda_jquery_datatables_searchbuilder-css' 	=> WPDAPRO_Data_Publisher_Init::JDT_SB . 'css/searchBuilder.dataTables.min.css',
					'wpda_jquery_datatables_searchpanes-css' 	=> WPDAPRO_Data_Publisher_Init::JDT_SP . 'css/searchPanes.dataTables.min.css',
					'wpda_jquery_datatables_datetime-css' 		=> WPDAPRO_Data_Publisher_Init::JDT_DATETIME . 'css/dataTables.dateTime.min.css',
				];

				$scripts_pubs = [
					'wpda_datatables-js'		    => plugins_url( '../../../assets/js/wpda_datatables.js', __FILE__ ),
					'wpda_datatables_premium-js'	=> plugins_url( '../../../assets/premium/js/wpda_datatables.js', __FILE__ ),
				];
				$styles_pubs = [
					'wpda_datatables_default'	=> plugins_url( '../../../assets/css/wpda_datatables_default.css', __FILE__ ),
				];

				$scripts_forms = [
					'wpda_datatables_premium-js'	=> plugins_url( '../../../assets/premium/js/wpda_datatables.js', __FILE__ ),
					'wpdapro_angularjs' 			=> WPDAPRO_Data_Forms_Init::DF_ANGULAR,
					'wpdapro_forms'					=> plugins_url( '../../../assets/premium/js/wpda_data_forms.js', __FILE__ ),
					'wpdapro_forms_datatable'		=> plugins_url( '../../../assets/premium/js/wpda_data_forms_datatable.js', __FILE__ ),
					'wpdapro_forms_dataentry'		=> plugins_url( '../../../assets/premium/js/wpda_data_forms_dataentry.js', __FILE__ ),
				];
				$styles_forms = [
					'wpdapro_forms'		=> plugins_url( '../../../assets/premium/css/wpda_data_forms.css', __FILE__ ),
				];
				?>
				function isAlreadyLoaded(resourceType, id) {
					var loadedResources = document.getElementsByTagName(resourceType);
					var isLoaded = false;
					for (var i=0; i<loadedResources.length; i++) {
						if (loadedResources[i].id==id) {
							isLoaded = true;
							break;
						}
					}
					return isLoaded;
				}
				function loadStyle(id, source) {
					if (!isAlreadyLoaded("link", id)) {
						var style = document.createElement("link");
						style.id = id;
						style.rel = "stylesheet";
						style.href = source;
						style.type = "text/css";
						document.getElementsByTagName("head")[0].appendChild(style);
					}
				}
				function loadScript(id, source, appendToBody = false) {
					if (!isAlreadyLoaded("script", id)) {
						var script = document.createElement("script");
						script.id = id;
						script.src = source;
						script.type = "text/javascript";
						script.async = false;
						if (appendToBody) {
							document.body.appendChild(script);
						} else {
							document.getElementsByTagName("head")[0].appendChild(script);
						}
					}
				}

				var wpda_panel_vars = {};
				wpda_panel_vars.wpda_ajaxurl = "<?php echo admin_url( 'admin-ajax.php' ); ?>";

				<?php
				if ( 'code' !== $widget_type ) {
					?>
					var wpdaSonce = "<?php echo WPDA::wpda_create_sonce(); ?>";
					<?php
				}

				switch( $widget_type ) {
					case 'chart':
						?>
						var googleChartsLoaded = false;
						<?php
						break;
					case 'dbs':
						?>
						var wpda_dbms_vars = {};
						wpda_dbms_vars.wpda_ajaxurl = "<?php echo admin_url( 'admin-ajax.php' ); ?>";
						<?php
						break;
					case 'pub':
						?>
						var wpda_publication_vars =	{};
						wpda_publication_vars.wpda_ajaxurl = "<?php echo admin_url( 'admin-ajax.php' ); ?>";
						<?php
						break;
				}
				?>

				if (!window.jQuery) {
					// jQuery not available: load it
					// console.log("Loading jQuery...");
					loadScript("wpda_resource_jquery-js", "https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js");
					setTimeout(waitUntiljQueryIsLoaded, 1000);
					function waitUntiljQueryIsLoaded() {
						if (!window.jQuery) {
							setTimeout(waitUntiljQueryIsLoaded, 1000);
						} else {
							jQueryLoaded<?php echo esc_attr( $target_element ); ?>();
						}
					}
				} else {
					jQueryLoaded<?php echo esc_attr( $target_element ); ?>();
				}

				
				function jQueryLoaded<?php echo esc_attr( $target_element ); ?>() {
					if (typeof jQuery.ui==='undefined') {
						// jQuery UI not available: load it
						// console.log("Loading jQuery UI...");
						loadScript("wpda_resource_jqueryui-js", "<?php echo WPDAPRO_Data_Forms_Init::DF_JQUERYUI; ?>jquery-ui.min.js");
						loadStyle("wpda_resource_jqueryui-css", "<?php echo WPDAPRO_Data_Forms_Init::DF_JQUERYUI; ?>themes/<?php echo $jquery_ui_theme; ?>/jquery-ui.min.css");
					}

					<?php
					switch( $widget_type ) {
						case 'chart':
							$this->load_scripts_and_styles( $scripts_chart, $styles_chart );
							break;
						case 'dbs':
							$this->load_scripts_and_styles( $scripts_dbs, $styles_dbs );
							break;
						case 'project':
							$this->load_scripts_and_styles( $scripts_jdt, $styles_jdt );
							$this->load_scripts_and_styles( $scripts_jdt_ext, $styles_jdt_ext );
							$this->load_scripts_and_styles( $scripts_forms, $styles_forms );
							break;
						case 'pub':
							$this->load_scripts_and_styles( $scripts_jdt, $styles_jdt );
							$this->load_scripts_and_styles( $scripts_jdt_ext, $styles_jdt_ext );
							$this->load_scripts_and_styles( $scripts_pubs, $styles_pubs );
							break;
					}
					?>
				}
				
				<?php foreach ( $scripts as $key => $script ) { ?> loadScript("<?php echo $key; ?>", "<?php echo $script; ?>"); <?php } ?>
				<?php foreach ( $styles as $key => $style ) { ?> loadStyle("<?php echo $key; ?>", "<?php echo $style; ?>"); <?php } ?>
				<?php
				if ( isset ( $widget['widgetName'] ) ) {
					switch( $widget_type ) {
						case 'code':
							$args = $this->get_code_args( $widget );
							if ( false !== $args ) {
								$chart = new WPDA_Widget_Code( $args );
								$chart->do_embed( $widget, $target_element );
							} else {
								echo 'console.log("' . __( 'WP Data Access: Error loading widget data', 'wp-data-access' ) . '");';
							}
							break;
						case 'chart':
							$args = $this->get_chart_args( $widget );
							if ( false !== $args ) {
								$chart = new WPDA_Widget_Google_Chart( $args );
								$chart->do_embed( $widget, $target_element );
							} else {
								echo 'console.log("' . __( 'WP Data Access: Error loading widget data', 'wp-data-access' ) . '");';
							}
							break;
						case 'dbs':
							$args = $this->get_dbms_args( $widget );
							if ( false !== $args ) {
								$dbms = new WPDA_Widget_Dbms( $args );
								$dbms->do_embed( $widget, $target_element );
							} else {
								echo 'console.log("' . __( 'WP Data Access: Error loading widget data', 'wp-data-access' ) . '");';
							}
							break;
						case 'project':
							$args = $this->get_project_args( $widget );
							if ( false !== $args ) {
								$args['embedding'] = true;
								$pub               = new WPDAPRO_Widget_Project( $args );
								$pub->do_embed( $widget, $target_element );
							} else {
								echo 'console.log("' . __( 'WP Data Access: Error loading widget data', 'wp-data-access' ) . '");';
							}
							break;
						case 'pub':
							$args = $this->get_pub_args( $widget );
							if ( false !== $args ) {
								$args['embedding'] = true;
								$pub               = new WPDA_Widget_Publication( $args );
								$pub->do_embed( $widget, $target_element );
							} else {
								echo 'console.log("' . __( 'WP Data Access: Error loading widget data', 'wp-data-access' ) . '");';
							}
							break;
					}
				};
			}

			wp_die();
		}

		protected function load_scripts_and_styles( $scripts, $styles ) {
			foreach ( $scripts as $key => $script ) {
				echo "loadScript('{$key}', '{$script}');";
			}

			foreach ( $styles as $key => $style ) {
				echo "loadStyle('{$key}', '{$style}');";
			}
		}

	}

}