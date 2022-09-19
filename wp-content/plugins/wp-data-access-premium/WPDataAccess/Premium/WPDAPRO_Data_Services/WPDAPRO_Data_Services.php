<?php

namespace WPDataAccess\Premium\WPDAPRO_Data_Services {

	use WPDataAccess\Data_Dictionary\WPDA_List_Columns_Cache;
	use WPDataAccess\Plugin_Table_Models\WPDA_Table_Settings_Model;
	use WPDataAccess\WPDA;
	use WPDataAccess\Connection\WPDADB;
	use WPDataAccess\Utilities\WPDA_Message_Box;
	use WPDataAccess\Utilities\WPDA_Remote_Call;

	class WPDAPRO_Data_Services {

		const PREMIUM_DATA_SERVICES_WPNONCE = 'wp-data-access-premium-data-services';
		const PREMIUM_DATA_SERVICES_FREE    = 'wpdafree.youniquedata.com';

		private static $premium_data_services = [
			self::PREMIUM_DATA_SERVICES_FREE => [
				'protocol' => 'https',
				'port'     => 3306
			]
		];

		private $freemius_account = null;
		private $wpda_account     = null;
		private $wpnonce          = null;

		private $pds_server   = null;
		private $pds_database = null;
		private $pds_port     = null;
		private $pds_url      = null;

		public function __construct() {
			if ( current_user_can( 'manage_options' ) ) {
				$this->freemius_account = wpda_freemius()->get_user();
				$this->wpnonce          = wp_create_nonce(
					self::PREMIUM_DATA_SERVICES_WPNONCE .
					$this->freemius_account->id .
					$this->freemius_account->public_key .
					$this->freemius_account->secret_key
				);
			}
		}

		public static function get_pds_servers() {
			// 4DEVELOPMENT
			// ------------
			// update_option(
			// 	'wpda_dev_premium_data_services',
			// 	[
			// 		'wpdadev.youniquedata.com' => [
			// 			'protocol' => 'http',
			// 			'port'     => 3306
			// 		]
			// 	]
			// );

			if ( false !== ( $dev_server = get_option( 'wpda_dev_premium_data_services' ) ) ) {
				self::$premium_data_services = array_merge( self::$premium_data_services, $dev_server );
			}

			return self::$premium_data_services;
		}

		public static function is_pds_server( $server ) {
			$pds_servers = self::get_pds_servers();

			return isset( $pds_servers[ $server ] );
		}

		public static function is_pds_database( $database ) {
			if ( 'rdb:' !== substr( $database, 0, 4 ) ) {
				return false;
			} else {
				$pds_servers = self::get_pds_servers();

				return isset( $pds_servers[ substr( $database, 4 ) ] );
			}
		}

		public function show() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( __( 'ERROR: Not authorized', 'wp-data-access' ) );
			}

			if ( ! wpda_freemius()->is_paying() ) {
				?>
				<h3>
					Premium Data Services not available for this license
				</h3>
				<p>
					Premium Data Services is available for users with an active premium license only.
					This feature does not work with a trial license.
				</p>
				<p>
					<a href="https://wpdataaccess.com/pricing/" class="button button-primary">ORDER PREMIUM LICENSE ONLINE</a>
					<a href="<?php echo admin_url('admin.php'); ?>?page=wpda-account" class="button button-secondary">ORDER PREMIUM LICENSE FROM YOUR WordPress DASHBOARD</a>
				</p>
				<?php
				return;
			}

			if ( isset( $_POST['pds_server'] ) ) {
				$pds_server = sanitize_text_field( wp_unslash( $_POST['pds_server'] ) ); // input var okay.
				if ( self::is_pds_server( $pds_server ) ) {
					$this->pds_server   = $pds_server;
					$this->pds_database = "rdb:{$pds_server}";
					$this->pds_port     = self::$premium_data_services[ $pds_server ]['port'];
					$this->pds_url      = self::$premium_data_services[ $pds_server ]['protocol'] . '://' . $pds_server . '/';

					$this->show_page();
				} else {
					$this->pds_server   = null;
					$this->pds_database = null;
					$this->pds_port     = null;
					$this->pds_url      = null;

					$this->select_server();
				}
			} else {
				$this->pds_server   = null;
				$this->pds_database = null;
				$this->pds_port     = null;
				$this->pds_url      = null;

				$this->select_server();
			}
		}

		private function select_server() {
			?>
				<form method="post">
					<fieldset class="wpda_fieldset" style="margin-top:10px">
						<legend>Premium data services</legend>
						<label>Select server</label>
						<select name="pds_server">
							<?php
							$pds_servers = self::get_pds_servers();
							foreach ( $pds_servers as $pds_server => $pds_server_port ) {
								echo '<option value="' . esc_attr( $pds_server ) . '">' . esc_attr( $pds_server ) . '</option>';
							}
							?>
						</select>
						<input type="submit" class="button button-primary">
					</fieldset>
				</form>
			<?php
		}

		private function show_page() {
			$this->css();
			?>
			<div class="wrap">
				<p id="wpda_loading">
					<strong>Processing remote data...</strong>
				</p>
				<?php
				// Setup account
				$this->setup_account();
				?>
			</div>
			<?php
			$this->js();
		}

		private function js() {
			?>
			<script>
				fieldLabels = {
					'pds_ip': 'IP Address',
					'pds_port': 'Post',
					'pds_service_name': 'Service name',
					'pds_dbs': 'Database',
					'pds_usr': 'Username',
					'pds_source_table_name': 'Source table name',
					'pds_path': 'File path',
					'pds_interval': 'Update interval',
					'pds_interval_unit': 'Update interval',
					'pds_csv_header': 'CSV header',
					'pds_csv_delimiter': 'CSV delimiter',
					'pds_json_object': 'Table object',
					'pds_table_name': 'Table name'
				}

				function submitCreateTable() {
					let pdsType = jQuery("#pds_type").val();
					let mandatory = [];

					switch(pdsType) {
						case "ORACLE":
						case "MYSQL":
						case "PSQL":
						case "MSSQL":
							mandatory.push('pds_ip');
							mandatory.push('pds_port');
							if (pdsType==="ORACLE") {
								mandatory.push('pds_service_name');
							}
							mandatory.push('pds_dbs');
							mandatory.push('pds_usr');
							mandatory.push('pds_source_table_name');
							break;
						case "CSV":
						case "JSON":
						case "ACCESS":
						case "ACCESSDB":
						case "XML":
							if (pdsType==="ACCESS" || pdsType==="ACCESSDB") {
								mandatory.push('pds_source_table_name');
							}
							mandatory.push('pds_path');
							if (pdsType==="CSV") {
								mandatory.push('pds_csv_header');
								mandatory.push('pds_csv_delimiter');
							} else if (pdsType==="JSON") {
								mandatory.push('pds_json_object');
							}
					}
					mandatory.push('pds_table_name');

					for (let i=0; i<mandatory.length; i++) {
						let field = jQuery("input[name=" + mandatory[i] + "]");
						if (field!==undefined) {
							if (field.val() === "") {
								field.focus();
								if (fieldLabels[mandatory[i]] !== undefined) {
									alert(fieldLabels[mandatory[i]] + " must be entered");
								} else {
									alert("Field must be entered");
								}
								return false;
							}
						}
					}

					return true;
				}

				function accept_disclaimer() {
					if ( ! jQuery("#disclaimer_accepted").is(":checked") ) {
						alert("You must accept the terms and conditions to activate your premium data service account!");
						return false;
					}
					return true;
				}

				function disableMyAcount() {
					if (confirm("Disable premium data services account?\n\n- Your account will be disabled\n- Your premium remote database will be deleted\n- Your premium tables and views will be dropped\n- This action cannot be undone\n\nAre you sure you want to disable your account?")) {
						jQuery("#wpda_pds_deactivate").submit();
					}

					return false;
				}

				function updateConnectForm() {
					jQuery(".connect_item").hide();

					let pdsType = jQuery("#pds_type option:selected").val();
					if (pdsType!=="") {
						let pdst = pdsType.toLowerCase();
						jQuery(".connect, ." + pdst).show();

						if (pdst==="accessdb" || pdst==="access") {
							jQuery(".dbs").hide();
						} else {
							jQuery(".dbs").show();
						}
					}
				}

				jQuery("#wpda_loading").hide();

				jQuery(function() {
					jQuery('#pds_table_name').off('keyup paste');
					jQuery('#pds_table_name').on('keyup paste', function () {
						this.value = this.value.replace(/[^\w\_]/g, '');
					});
				});
			</script>
			<?php
		}

		private function css() {
			?>
			<style>
                #wpda_pds_canvas>div {
					margin: 10px 0;
				}
                div.container_pds {
                    background: #fff;
                    border-top: 1px solid #ccc;
                    border-bottom: 1px solid #ccc;
                    padding: 0 10px;
                    margin-right: 0;
                }
                div.container_pds label {
                    width: 140px;
                    display: inline-block;
                    font-weight: bold;
                }
                div.container_pds div {
                    margin-bottom: 4px;
                }
				div.container_pds fieldset {
					margin-top: 10px;
				}
                div.container_pds fieldset:first-child {
                    margin-top: 0;
                }
                h3.wpda_configuration {
                    padding-left: 20px;
                }
				ul.wpda_configuration {
                    list-style: disclosure-closed;
                    margin-left: 40px;
                }
				p.wpda_configuration {
					padding-left: 20px;
				}
                div.wpda_configuration {
                    background-color: white;
                    margin: 20px -20px 20px 0;
                    padding: 20px;
                    border-top: 1px solid #c3c4c7;
                    border-bottom: 1px solid #c3c4c7;
                }
                table.wpda_premium-data-services {
					width: calc(100% - 20px);
					margin: 0 20px;
					table-layout: fixed;
                    border-collapse: collapse;
				}
                table.wpda_premium-data-services tr {
                    height: 40px;
                }
                table.wpda_premium-data-services tr.yes {
                    font-weight: 500;
                }
                table.wpda_premium-data-services th {
                    text-align: left;
                }
                table.wpda_premium-data-services th,
                table.wpda_premium-data-services td {
                    padding: 14px;
                }
                table.wpda_premium-data-services th:first-child,
                table.wpda_premium-data-services td:first-child {
                    padding-right: 0;
                }
				p.wpda_comment {
					padding: 10px 0;
					font-weight: 500;
				}
                #wpda_loading {
                    animation: blinkingText 1s infinite;
                }
                @keyframes blinkingText {
                    50% {
                        color: #fff;
                    }
                    100% {
                        color: #000;
                    }
                }
			</style>
			<?php
		}

		private function pds( $page, $args = [] ) {
			$forms_args = array(
				'u' => $this->freemius_account->id,
				'p' => password_hash(
					$this->freemius_account->id . $this->freemius_account->public_key . $this->freemius_account->secret_key,
					PASSWORD_DEFAULT
				)
			);

			$response = WPDA_Remote_Call::post(
				$this->pds_url . $page,
				array_merge( $forms_args, $args )
			);

			if ( false === $response ) {
				$this->hide_loading_info();
				wp_die( __( 'ERROR: Remote call failed', 'wp-data-access' ) );
			}

			if ( isset( $response['response']['code'] ) && 200 !== $response['response']['code'] ) {
				$this->hide_loading_info();
				if ( isset( $response['response']['message'] ) ) {
					wp_die( __( 'ERROR:' . ' ' . esc_attr( $response['response']['message'] ) . ' (' . esc_attr( $response['response']['code'] ) . ')', 'wp-data-access' ) );
				} else {
					wp_die( __( 'ERROR: Remote call failed', 'wp-data-access' ) );
				}
			}

			$msg = json_decode( $response['body'], true );
			if ( null === $msg ) {
				$this->hide_loading_info();
				wp_die( __( 'ERROR: Remote call failed', 'wp-data-access' ) );
			}

			return $msg;
		}

		private function hide_loading_info() {
			?>
			<script>
				jQuery("#wpda_loading").hide();
			</script>
			<?php
		}

		private function get_account_info() {
			// Get account info.
			$this->wpda_account = $this->pds('index.php');
		}

		private function activate_account() {
			$args['install']    = $_POST['install']; // Already checked
			$args['user_email'] = isset( $_POST['user_email'] ) ?
				sanitize_text_field( wp_unslash( $_POST['user_email'] ) ) : null; // input var okay.

			// Activate account.
			$this->wpda_account = $this->pds( 'activate.php', $args );
			if ( isset( $this->wpda_account['status'] ) ) {
				if ( 'OK' === $this->wpda_account['status'] ) {
					$this->manage_account();
				} else {
					if ( 'ERR' === $this->wpda_account['status'] ) {
						if ( isset( $this->wpda_account['msg'] ) ) {
							$this->show_error( $this->wpda_account['msg'] );
						} else {
							$this->show_error( 'Internal server error' );
						}
					} else {
						$this->show_error( 'Internal server error' );
					}
				}
			} else {
				$this->show_error( 'Internal server error' );
			}
		}

		private function disable_account( $feedback = true ) {
			if ( ! $feedback ) {
				WPDADB::del_remote_database( $this->pds_database );
			} else {
				if ( false === WPDADB::del_remote_database( $this->pds_database ) ) {
					$msg = new WPDA_Message_Box(
						array(
							'message_text' => sprintf(
								__( 'Cannot delete remote database connection `%s`', 'wp-data-access' ),
								$this->pds_database
							),
							'message_type' => 'error',
							'message_is_dismissible' => false,
						)
					);
					$msg->box();
				} else {
					$msg = new WPDA_Message_Box(
						array(
							'message_text' => sprintf(
								__( 'Remote database `%s` deleted', 'wp-data-access' ),
								$this->pds_database
							),
						)
					);
					$msg->box();
				}

				$this->manage_account();
			}
		}

		private function enable_account() {
			// Add remote database connection to enable account on this server
			if ( ! WPDADB::add_remote_database(
				'rdb:' . $this->pds_server,
				$this->pds_server,
				$this->freemius_account->id,
				base64_encode(
					openssl_encrypt(
						$this->freemius_account->id,
						'AES-256-CBC',
						hash( 'sha256', $this->freemius_account->secret_key ),
						0,
						substr( hash( 'sha256', $this->freemius_account->public_key ), 0, 16 )
					)
				),
				$this->pds_port,
				"pds{$this->freemius_account->id}",
				'on',
				'',
				'',
				'',
				'',
				''
			) ) {
				$msg = new WPDA_Message_Box(
					array(
						'message_text'           => sprintf(
							__( 'Cannot add remote database connection', 'wp-data-access' )
						),
						'message_type'           => 'error',
						'message_is_dismissible' => false,
					)
				);
				$msg->box();
			} else {
				$msg = new WPDA_Message_Box(
					array(
						'message_text' => sprintf(
							__( 'Remote database connection `%s` added', 'wp-data-access' ),
							$this->pds_database
						),
					)
				);
				$msg->box();
			}

			$this->manage_account();
		}

		private function show_error( $error ) {
			?>
			<div>
				<?php echo esc_html( $error ); ?>
			</div>
			<?php
		}

		private function setup_account() {
			// Flush output to show loading message.
			ob_flush();
			flush();

			if ( isset( $_POST['pds_action'] ) ) {
				$wpnonce = isset( $_POST['wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wpnonce'] ) ) : ''; // input var okay.
				if (
					! wp_verify_nonce(
						$wpnonce,
						self::PREMIUM_DATA_SERVICES_WPNONCE .
						$this->freemius_account->id .
						$this->freemius_account->public_key .
						$this->freemius_account->secret_key
					)
				) {
					wp_die( __( 'ERROR: Not authorized', 'wp-data-access' ) );
				}
			}

			if (
				isset( $_POST['install'], $_POST['pds_action'] ) &&
				'activate' === $_POST['pds_action']
			) {
				// Activate account
				$this->activate_account();
			} elseif (
				isset( $_POST['pds_action'] ) &&
				'deactivate' === $_POST['pds_action']
			) {
				// Deactivate account
				$this->deactivate_account();
			} elseif (
				isset( $_POST['pds_action'] ) &&
				'enable' === $_POST['pds_action']
			) {
				// Get account info
				$this->get_account_info();

				// Enable account on this server
				$this->enable_account();
			} elseif (
				isset( $_POST['pds_action'] ) &&
				'disable' === $_POST['pds_action']
			) {
				// Get account info
				$this->get_account_info();

				// Disable account on this server
				$this->disable_account();
			} else {
				// Get account info
				$this->get_account_info();

				// Check account status
				if (
					isset( $this->wpda_account['status'] ) &&
					'FOUND' === $this->wpda_account['status'] &&
					isset( $this->wpda_account['msg']['freemius']['admin'] ) &&
					$this->wpda_account['msg']['freemius']['admin']
				) {
					// Manage existing account.
					$this->manage_account();
				} else {
					// Create new account.
					$this->create_account();
				}
			}
		}

		private function create_account() {
			?>
			<div class="wpda_configuration">
				<p>
					<strong><?php echo esc_attr( self::PREMIUM_DATA_SERVICES_FREE ); ?></strong> is a free for premium users.
					Do <strong>NOT</strong> use this service for critical information provisioning.
					There are <strong>NO</strong> uptime or resource <strong>GUARANTEES</strong>.
					<strong>You are NOT paying for this service.</strong>
				</p>
			</div>
			<p class="wpda_configuration">
				Premium data services is a plugin feature which allows premium users to make external data sources available to their WordPress back-end and front-end:
			</p>
			<ul class="wpda_configuration">
				<li>
					Remote database connections through ODBC (SQL Server, Oracle, PostgreSQL, MS Access, MariaDB | MySQL and more potential for later...)
					<span class="dashicons dashicons-editor-help wpda_tooltip"
						  title="Data is not cached or replicated. Our server acts as a proxy to your DBMS.

Only Access files are cached."
						  style="cursor:pointer"
					></span>
				</li>
				<li>
					Remote data files (CSV, JSON, XML and more potential for later...)
					<span class="dashicons dashicons-editor-help wpda_tooltip"
						  title="Upload your files from public links. Data is cached on our server and refreshed at configurable intervals."
						  style="cursor:pointer"
					></span>
				</li>
			</ul>
			<div class="wpda_configuration">
				<p>
					No premium data service account found for user id:
					<strong>
						<?php echo esc_attr( $this->freemius_account->id ); ?>
					</strong>
				</p>
				<p>
					Premium data services for this user id are currently available for the following hosts:
				</p>
				<form method="post" onsubmit="return accept_disclaimer()">
					<p>
						<?php
						$this->wpda_account_status();
						?>
					</p>
					<?php
					if (
						isset( $this->wpda_account['msg']['freemius']['admin'] ) &&
						$this->wpda_account['msg']['freemius']['admin']
					) {
						// Allow user to activate wpda account
						?>
						<p>
							<label>
								<input type="checkbox" id="disclaimer_accepted" />
								<strong>
									I have read the disclaimer and agree to the terms and conditions
								</strong>
							</label>
						</p>
						<p>
							<button class="button button-primary">
								Activate my premium data services account for all selected hosts
							</button>
							<input type="hidden" name="pds_action" value="activate" />
							<input type="hidden" name="pds_server" value="<?php echo esc_attr( $this->pds_server ); ?>" />
							<input type="hidden" name="wpnonce" value="<?php echo esc_attr( $this->wpnonce ); ?>" />
						</p>
						<?php
					} else {
						// User is now allowed to activate wpda account
						?>
						<p>
							<i class="fas fa-exclamation-triangle"></i>
							Sorry, you cannot activate a premium data services account from this host.
							Your host must be on a registered public IP address.
							<br/>
							<i class="fas fa-server"></i>
							You current IP:
							<strong>
							<?php echo esc_attr( WPDA::get_server_address() ); ?>
							</strong>
							(is a
							<?php
							echo filter_var(
									WPDA::get_server_address(),
									FILTER_VALIDATE_IP,
									FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
								) === WPDA::get_server_address() ?
								'public' : 'local';
							?>
							address)
						</p>
						<?php
					}
					?>
				</form>
			</div>
			<h3 class="wpda_configuration">
				Disclaimer
			</h3>
			<ul class="wpda_configuration">
				<li>
					Your data sources are only accessible from installs with a public IP address.
					Local IP addresses are NOT supported.
				</li>
				<li>
					Data encryption between your WordPress server and our premium data service is enabled by default.
					This can be disabled in <strong><a href="<?php echo admin_url( 'options-general.php' ) . '?page=wpdataaccess'; ?>">Plugin settings > Remote database connections</a></strong>.
				</li>
				<li>
					Data encryption between our premium data service and your data source is your own responsibility.
				</li>
				<li>
					Use this service at your own risk. We do not accept any responsibility or liability for any loss of
					business or profits nor any direct, indirect or consequential loss or damage resulting from any such
					irregularity, inaccuracy or use of our premium data services.
				</li>
				<li>
					Any privacy violation or violation of any law will result in closing and blocking your account permanently.
				</li>
			</ul>
			<?php
		}

		private function deactivate_account() {
			// Disable account on this server
			$this->disable_account( false );

			// Deactivate account.
			$json = $this->pds('deactivate.php');
			if ( isset( $json['status'] ) && 'OK' === $json['status'] ) {
				?>
				<p>
					Your premium data service account was successfully disabled!
				</p>
				<h3>
					NOTE
				</h3>
				<p>
					Your premium remote database, including its tables and views. is no longer available.
					<br/>
					If other servers are still using this account, you need to disable it for each server.
				</p>
				<?php
			} else {
				?>
				<p>
					An error occured while disabling your premium data services. Please try again.
				</p>
				<p>
					Please contact support if the problem persists.
				</p>
				<?php
			}
		}

		private function manage_account() {
			?>
			<p class="wpda_comment">
				<strong>Premium data services configuration</strong>
			</p>
			<form method="post">
				<p>
					<?php
					$this->wpda_account_status();
					?>
				</p>
				<p>
					<input type="hidden" name="pds_action" value="activate" />
					<input type="hidden" name="pds_server" value="<?php echo esc_attr( $this->pds_server ); ?>" />
					<input type="hidden" name="wpnonce" value="<?php echo esc_attr( $this->wpnonce ); ?>" />
					<button class="button button-primary">
						<i class="fas fa-cog"></i>&nbsp;
						Update my premium data services account&nbsp;
					</button>

					<button class="button button-primary" onclick="return disableMyAcount()">
						<i class="fas fa-trash"></i>&nbsp;
						Disable my premium data services account&nbsp;
					</button>
				</p>
			</form>
			<form id="wpda_pds_deactivate" method="post">
				<input type="hidden" name="pds_action" value="deactivate" />
				<input type="hidden" name="pds_server" value="<?php echo esc_attr( $this->pds_server ); ?>" />
				<input type="hidden" name="wpnonce" value="<?php echo esc_attr( $this->wpnonce ); ?>" />
			</form>
			<form id="wpda_pds_enable" method="post">
				<input type="hidden" name="pds_action" value="enable" />
				<input type="hidden" name="pds_server" value="<?php echo esc_attr( $this->pds_server ); ?>" />
				<input type="hidden" name="wpnonce" value="<?php echo esc_attr( $this->wpnonce ); ?>" />
			</form>
			<form id="wpda_pds_disable" method="post">
				<input type="hidden" name="pds_action" value="disable" />
				<input type="hidden" name="pds_server" value="<?php echo esc_attr( $this->pds_server ); ?>" />
				<input type="hidden" name="wpnonce" value="<?php echo esc_attr( $this->wpnonce ); ?>" />
			</form>
			<?php
		}

		private function wpda_account_status() {
			if ( null === $this->wpda_account ) {
				return;
			}

			if (
				isset( $this->wpda_account['msg']['freemius']['domains'] ) &&
				is_array( $this->wpda_account['msg']['freemius']['domains'] )
			) {
				$admin = isset( $this->wpda_account['msg']['freemius']['admin'] ) ?
					$this->wpda_account['msg']['freemius']['admin'] : false;
				?>

				<table class="wpda_premium-data-services striped">
					<tr>
						<th>Host</th>
						<th>IP</th>
						<th>Network</th>
						<th>
							Available
							<span class="dashicons dashicons-editor-help wpda_tooltip" title="Premium data services available for this host? Services are only be manageable on the current host." style="cursor:pointer"></span>
						</th>
						<th></th>
					</tr>

				<?php
				$user_found = isset( $this->wpda_account['msg']['wpda']['user'] ) &&
					$this->wpda_account['msg']['wpda']['user'];

				// Get premium data services instance on this server
				$pds = WPDADB::get_remote_database( $this->pds_database );

				if ( isset( $this->wpda_account['msg']['freemius']['domains'] ) ) {
					foreach ( $this->wpda_account['msg']['freemius']['domains'] as $domain ) {
						if ( $user_found ) {
							// Use activated installs
							$checked = isset( $this->wpda_account['msg']['wpda']['installs'][ $domain['ip'] ] ) ?
								'checked' : '';
						} else {
							// Use available installs
							$checked = $domain['public'] ? 'checked' : '';
						}

						$disabled  = $admin && $domain['admin'] ? '' : 'disabled';
						$public    = $domain['public'] ? 'public' : 'local';
						$available = $domain['admin'] ? 'yes' : 'no';
						$activate  = '';

						if (
							isset(
								$this->wpda_account['status'],
								$this->wpda_account['msg']['wpda']['installs']
							) &&
							(
								'FOUND' === $this->wpda_account['status'] ||
								'OK' === $this->wpda_account['status']
							)
						) {
							if ( false === $pds ) {
								if (
									WPDA::get_server_address() === $domain['ip'] &&
									isset( $this->wpda_account['msg']['wpda']['installs'][ $domain['ip'] ] )
								) {
									$activate = '
										<input type="hidden" name="pds_action" value="enable" />
										<button class="button button-primary" onclick="jQuery(\'#wpda_pds_enable\').submit(); return false;" style="font-weight:normal">
											<i class="fas fa-cog"></i>&nbsp;
											Enable on this server&nbsp;
										</button>';
								}
							} else {
								if (
									WPDA::get_server_address() === $domain['ip'] &&
									isset( $this->wpda_account['msg']['wpda']['installs'][ $domain['ip'] ] )
								) {
									$activate = '
										<input type="hidden" name="pds_action" value="enable" />
										<button class="button button-primary" onclick="jQuery(\'#wpda_pds_disable\').submit(); return false;" style="font-weight:normal">
											<i class="fas fa-cog"></i>&nbsp;
											Disable on this server&nbsp;
										</button>';
								}
							}
						}

						echo "
							<tr class='{$available}'>
								<td>
									<input type='checkbox' name='install[]' {$checked} {$disabled} value='{$domain['ip']}' />
									{$domain['url']}
								</td>
								<td>{$domain['ip']}</td>
								<td>{$public}</td>
								<td>{$available}</td>
								<td>{$activate}</td>
							</tr>
						";
					}
				}
				?>

				</table>

				<?php
				if ( isset( $this->wpda_account['msg']['wpda']['user'][0]['email'] ) ) {
					$user_email = $this->wpda_account['msg']['wpda']['user'][0]['email'];
				} else {
					$user_email = '';
				}
				?>

				<p>
					<label>Mail my info and error messages to:</label>
					<input type="text" name="user_email" value="<?php echo esc_attr( $user_email );?>" />
					<span class="dashicons dashicons-editor-help wpda_tooltip" title="Error and info messages are available from your Data Explorer. Please submit your email address if you prefer to read your messages by email as well.

Leave field empty to disable mailing." style="cursor:pointer;font-size:20px;vertical-align:middle"></span>
				</p>

				<p class="wpda_comment">
					<i class="fas fa-exclamation-triangle"></i>
					Activation is IP based
					<span class="dashicons dashicons-editor-help wpda_tooltip" title="If you host multiple domains on the same IP address, all hosts on that IP will have access to your premium data services after activation." style="cursor:pointer"></span>
				</p>

				<?php
			}
		}

		public static function stored_locally( $table_name, $schema_name ) {
			$tables = self::sync( $schema_name );

			return isset( $tables[ $table_name ] ) ? $tables[ $table_name ] : false;
		}

		public static function sync( $schema_name ) {
			$wpdadb = WPDADB::get_db_connection( $schema_name );
			if ( $wpdadb === null ) {
				return array();
			}

			$suppress = $wpdadb->suppress_errors( true );
			$queue = $wpdadb->get_results( 'call snc()', 'ARRAY_A' );
			$wpdadb->suppress_errors( $suppress );

			$tables = [];
			foreach ( $queue as $que ) {
				$tables[ $que['table_name'] ] = $que;
			}

			return $tables;
		}

		public static function drop_table( $wpdadb, $table_name, $schema_name ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( __( 'ERROR: Not authorized', 'wp-data-access' ) );
			}

			// Prepare arguments.
			$checksum = md5( mt_rand() );
			$args     = [
				'pds_schema_name' => $wpdadb->dbname,
				'pds_table_name'  => $table_name,
			];

			// Save drop table command.
			$suppress = $wpdadb->suppress_errors( true );
			$wpdadb->query(
				$wpdadb->prepare(
					"call pds(%s, %s, @id)",
					$checksum,
					json_encode( $args, true )
				)
			);
			$wpdadb->suppress_errors( $suppress );

			// Get command id.
			$id = $wpdadb->get_results( 'select @id', 'ARRAY_N' );
			if ( '' === $wpdadb->last_error || isset( $id[0][0] ) ) {
				// PDS request successful, send request to execute command on pds server.
				$pds_url = self::get_url_from_schema_name( $schema_name );
				if ( false !== $pds_url ) {
					WPDA_Remote_Call::post(
						$pds_url . 'drop.php',
						array(
							'i' => $id[0][0],
							'c' => $checksum,
						),
						true
					);
					// No error handling. Worst case we have a dead file.
				}
			}
		}

		private static function get_url_from_schema_name( $schema_name ) {
			return self::is_pds_database( $schema_name ) ?
				self::$premium_data_services[ substr( $schema_name, 4 ) ]['protocol'] . '://' . substr( $schema_name, 4 ) . '/' :
				false;
		}

		public function message_box( $pds_schema_name ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( __( 'ERROR: Not authorized', 'wp-data-access' ) );
			}

			$msg   = \WPDataAccess\Premium\WPDAPRO_Data_Services\WPDAPRO_Data_Services::get_messages( $pds_schema_name );
			$color = '';
			if ( is_array( $msg ) && count( $msg ) > 0 ) {
				$has_info  = false;
				$has_error = false;

				foreach ( $msg as $m ) {
					if ( 'No' === $m['read'] ) {
						$has_info  = $has_info || 'INFO' === $m['msg_type'];
						$has_error = $has_error || 'ERROR' === $m['msg_type'];
						if ( $has_error ) {
							break;
						}
					}
				}

				if ( $has_error ) {
					$color = 'red';
				} elseif ( $has_info) {
					$color = 'green';
				}
			}
			?>
			<div id="wpda_pds_message_box" style="display:none">
				<?php
				if ( is_array( $msg ) && count( $msg ) > 0 ) {
					?>
					<div>
						<strong>Message box</strong>
						<span style="float:right">
							<a href="javascript:void(0)" onclick="deleteMessage('ALL', null)" class="button button-secondary" style="margin-bottom:10px">
								<i class="fas fa-trash wpda_icon_on_button"></i> Delete all
							</a>
							<a href="javascript:void(0)" onclick="markAllMessagesAsRead()" class="button button-secondary" style="margin-bottom:10px">
								<i class="fas fa-flag wpda_icon_on_button"></i> Mark all as read
							</a>
							<a href="javascript:void(0)" onclick="jQuery('#wpda_pds_message_box').hide()" class="button button-secondary" style="margin-bottom:10px">
								<i class="fas fa-times-circle wpda_icon_on_button"></i> Close
							</a>
						</span>
					</div>
					<table>
						<thead>
							<tr>
								<th></th>
								<th>Date</th>
								<th>Message</th>
								<th>Type</th>
							</tr>
						</thead>
						<tbody>
						<?php
						foreach ( $msg as $m ) {
							?>
							<tr>
								<td>
									<a href="javascript:void(0)" onclick="deleteMessage('<?php echo esc_attr( $m['msg_date'] ); ?>', jQuery(this))" class="wpda_tooltip" title="Delete message">
										<i class="fas fa-trash"></i>
									</a>
								</td>
								<td><?php echo esc_attr( $m['msg_date'] ); ?></td>
								<td><?php echo esc_attr( $m['message'] ); ?></td>
								<td><?php echo esc_attr( $m['msg_type'] ); ?></td>
							</tr>
							<?php
						}
						?>
						</tbody>
					</table>
					<?php
				} else {
					echo 'No messages';
				}
				?>
			</div>
			<style>
				#wpda_pds_message_box {
                    background: #fff;
                    border-top: 1px solid #ccc;
                    border-bottom: 1px solid #ccc;
					padding: 10px;
                    margin: 10px 0;
				}
				#wpda_pds_message_box table {
                    border-collapse: collapse;
                    border: 1px solid #ccc;
                    width: 100%;
				}
                #wpda_pds_message_box table thead {
                    display: block;
                    overflow-y: scroll;
                    overflow-x: hidden;
                }
                #wpda_pds_message_box table tbody {
                    display: block;
                    overflow-y: scroll;
					overflow-x: hidden;
                    height: 150px;
                    border-top: 1px solid #ccc;
                    background-color: #fff;
                }
                #wpda_pds_message_box table tr {
                    width: 100%;
                    display: table;
                    table-layout: fixed;
                }
                #wpda_pds_message_box table th,
                #wpda_pds_message_box table td {
                    overflow-wrap: anywhere;
                    text-align: left;
                    padding: 4px;
                }
                #wpda_pds_message_box table th {
                    border-right: 1px solid #ccc;
                }
                #wpda_pds_message_box table td {
                    border-left: 1px dotted #ccc;
                    border-right: 1px dotted #ccc;
                    border-bottom: 1px dotted #ccc;
                    border-left: 0;
				}
                #wpda_pds_message_box table thead th:first-child,
                #wpda_pds_message_box table tbody td:first-child {
					width: 30px;
					text-align: center;
				}
			</style>
			<script>
				function markAllMessagesAsRead() {
					jQuery.ajax({
						method: 'POST',
						url: "<?php echo admin_url( 'admin-ajax.php' ); ?>?action=wpda_pds_mark_all_messages_as_read",
						data: {
							wpnonce:         '<?php echo wp_create_nonce( self::PREMIUM_DATA_SERVICES_WPNONCE . $this->freemius_account->id ); ?>',
							user_id:         '<?php echo esc_attr( $this->freemius_account->id ); ?>',
							pds_schema_name: '<?php echo esc_attr( $pds_schema_name ); ?>'
						}
					}).done(
						function(msg) {
							jQuery("#pds_msg_icon").css("color", "");
						}
					);
				}

				function deleteMessage(msgDate, elem) {
					jQuery.ajax({
						method: 'POST',
						url: "<?php echo admin_url( 'admin-ajax.php' ); ?>?action=wpda_pds_delete_messages",
						data: {
							wpnonce:         '<?php echo wp_create_nonce( self::PREMIUM_DATA_SERVICES_WPNONCE . $this->freemius_account->id ); ?>',
							user_id:         '<?php echo esc_attr( $this->freemius_account->id ); ?>',
							pds_schema_name: '<?php echo esc_attr( $pds_schema_name ); ?>',
							msg_date:         msgDate
						}
					}).done(
						function(msg) {
							if (msg==="OK") {
								if (elem===null) {
									jQuery("#wpda_pds_message_box tbody tr").remove();
									jQuery("#pds_msg_icon").css("color", "");
								} else {
									elem.closest("tr").remove();
								}
							}
						}
					);
				}

				jQuery(function() {
					jQuery("#pds_msg_icon").css('color', '<?php echo esc_attr( $color ) ?>');
				});
			</script>
			<?php
		}

		public function add_ajax() {
			// jQuery(this).closest('.wpda_pds_schedule_edit').find('.pds_interval').val(), jQuery(this).closest('.wpda_pds_schedule_edit').find('.pds_interval_unit').val()
			?>
			<script>
				function updatePdsInterval(elem, schemaName, tableName) {
					jQuery.ajax({
						method: 'POST',
						url: "<?php echo admin_url( 'admin-ajax.php' ); ?>?action=wpda_pds_update_interval",
						data: {
							wpnonce    : '<?php echo wp_create_nonce( \WPDataAccess\Premium\WPDAPRO_Data_Services\WPDAPRO_Data_Services::PREMIUM_DATA_SERVICES_WPNONCE ); ?>',
							schema_name: schemaName,
							table_name : tableName,
							interval   : elem.closest('.wpda_pds_schedule_edit').find('.pds_interval').val(),
							unit       : elem.closest('.wpda_pds_schedule_edit').find('.pds_interval_unit').val()
						}
					}).done(
						function(msg) {
							if (msg==="OK") {
								alert("Update request send to server");
							}
						}
					);
				}
			</script>
			<?php
		}

		public static function update_interval() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( __( 'ERROR: Not authorized', 'wp-data-access' ) );
			}

			if ( ! isset( $_POST['wpnonce'], $_POST['schema_name'], $_POST['table_name'], $_POST[ 'interval' ], $_POST[ 'unit' ] ) ) {
				wp_die( __( 'ERROR: Invalid request', 'wp-data-access' ) );
			}

			$wpnonce     = isset( $_POST['wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wpnonce'] ) ) : ''; // input var okay.
			$schema_name = isset( $_POST['schema_name'] ) ? sanitize_text_field( wp_unslash( $_POST['schema_name'] ) ) : ''; // input var okay.
			$table_name  = isset( $_POST['table_name'] ) ? sanitize_text_field( wp_unslash( $_POST['table_name'] ) ) : ''; // input var okay.
			$interval    = isset( $_POST['interval'] ) ? sanitize_text_field( wp_unslash( $_POST['interval'] ) ) : ''; // input var okay.
			$unit        = isset( $_POST['unit'] ) ? sanitize_text_field( wp_unslash( $_POST['unit'] ) ) : ''; // input var okay.

			if (
				! wp_verify_nonce(
					$wpnonce,
					self::PREMIUM_DATA_SERVICES_WPNONCE
				)
			) {
				wp_die( __( 'ERROR: Not authorized', 'wp-data-access' ) );
			}

			$wpdadb = WPDADB::get_db_connection( $schema_name );
			if ( $wpdadb === null ) {
				wp_die("Cannot connect to database connection");
			}

			$suppress = $wpdadb->suppress_errors( true );
			$wpdadb->query(
				$wpdadb->prepare(
					"call usnc(%s, %s, %s)",
					$table_name,
					$interval,
					$unit
				)
			);
			$wpdadb->suppress_errors( $suppress );

			wp_die( 'OK' );
		}

		public static function mark_all_messages_as_read() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( __( 'ERROR: Not authorized', 'wp-data-access' ) );
			}

			if ( ! isset( $_POST['wpnonce'], $_POST['user_id'], $_POST[ 'pds_schema_name' ] ) ) {
				wp_die( __( 'ERROR: Invalid request', 'wp-data-access' ) );
			}

			$wpnonce         = isset( $_POST['wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wpnonce'] ) ) : ''; // input var okay.
			$user_id         = isset( $_POST['user_id'] ) ? sanitize_text_field( wp_unslash( $_POST['user_id'] ) ) : ''; // input var okay.
			$pds_schema_name = isset( $_POST['pds_schema_name'] ) ? sanitize_text_field( wp_unslash( $_POST['pds_schema_name'] ) ) : ''; // input var okay.

			if (
				! wp_verify_nonce(
					$wpnonce,
					self::PREMIUM_DATA_SERVICES_WPNONCE .
					$user_id
				)
			) {
				wp_die( __( 'ERROR: Not authorized', 'wp-data-access' ) );
			}

			$wpdadb = WPDADB::get_db_connection( $pds_schema_name );
			if ( $wpdadb === null ) {
				wp_die("Cannot connect to database connection");
			}

			$suppress = $wpdadb->suppress_errors( true );
			$wpdadb->query( 'call umsg()' );
			$wpdadb->suppress_errors( $suppress );

			wp_die( 'OK' );
		}

		public static function delete_messages() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( __( 'ERROR: Not authorized', 'wp-data-access' ) );
			}

			if ( ! isset( $_POST['wpnonce'], $_POST['user_id'], $_POST[ 'pds_schema_name' ], $_POST[ 'msg_date' ] ) ) {
				wp_die( __( 'ERROR: Invalid request', 'wp-data-access' ) );
			}

			$wpnonce         = isset( $_POST['wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wpnonce'] ) ) : ''; // input var okay.
			$user_id         = isset( $_POST['user_id'] ) ? sanitize_text_field( wp_unslash( $_POST['user_id'] ) ) : ''; // input var okay.
			$pds_schema_name = isset( $_POST['pds_schema_name'] ) ? sanitize_text_field( wp_unslash( $_POST['pds_schema_name'] ) ) : ''; // input var okay.
			$msg_date        = isset( $_POST['msg_date'] ) ? sanitize_text_field( wp_unslash( $_POST['msg_date'] ) ) : ''; // input var okay.

			if (
				! wp_verify_nonce(
					$wpnonce,
					self::PREMIUM_DATA_SERVICES_WPNONCE .
					$user_id
				)
			) {
				wp_die( __( 'ERROR: Not authorized', 'wp-data-access' ) );
			}

			$wpdadb = WPDADB::get_db_connection( $pds_schema_name );
			if ( $wpdadb === null ) {
				wp_die("Cannot connect to database connection");
			}

			$suppress = $wpdadb->suppress_errors( true );
			$wpdadb->query(
				$wpdadb->prepare(
					"call dmsg(%s)",
					$msg_date
				)
			);
			$wpdadb->suppress_errors( $suppress );

			wp_die( 'OK' );
		}

		public function upload_form() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( __( 'ERROR: Not authorized', 'wp-data-access' ) );
			}
			?>
			<div id="wpda_pds_canvas" style="display:none">
				<div>
					<div class="container_pds">
						<form id="form_pds" method="post" onsubmit="return submitCreateTable()">
							<p>
								<fieldset class="wpda_fieldset">
									<legend>
										Select
									</legend>

									<label>Connection type</label>
									<select id="pds_type" name="pds_type" onchange="updateConnectForm()">
										<option value=""></option>
										<optgroup label="Database">
											<option value="MSSQL">Microsoft SQL Server</option>
											<option value="PSQL">PostgreSQL</option>
											<option value="ORACLE">Oracle</option>
											<option value="MYSQL">MariaDB/MySQL</option>
										</optgroup>
										<optgroup label="Data files">
											<option value="ACCESS">Microsoft Access MDB file</option>
											<option value="ACCESSDB">Microsoft Access ACCDB file</option>
											<option value="CSV">CSV file</option>
											<option value="JSON">JSON file</option>
											<option value="XML">XML file</option>
										</optgroup>
									</select>
									<label>
										<span class="dashicons dashicons-editor-help wpda_tooltip" title="Databases and data files need to be accessible to our premium server.

Data is not cached or replicated for MariaDB, MySQL, PostgreSQL, SQL Server and Oracle connections. Our server acts as a proxy to your DBMS.

Data files are cached on our server and refreshed at configurable intervals." style="cursor:pointer"></span>
									</label>
								</fieldset>

								<fieldset class="wpda_fieldset connect_item mssql accessdb access psql mysql oracle" style="display:none">
									<legend>
										Remote database
									</legend>

									<div class="dbs">
										<label>IP Address</label>
										<input type="text" name="pds_ip">
										<label>
											<span class="dashicons dashicons-editor-help wpda_tooltip" title="The IP address of your database server. Your WordPress server must be able to access this IP address." style="cursor:pointer"></span>
										</label>
									</div>
									<div class="dbs">
										<label>Port</label>
										<input type="text" name="pds_port">
										<label>
											<span class="dashicons dashicons-editor-help wpda_tooltip" title="Port your database server is listening on." style="cursor:pointer"></span>
										</label>
									</div>
									<div class="connect_item oracle">
										<label>Service name</label>
										<input type="text" name="pds_service_name">
										<label>
											<span class="dashicons dashicons-editor-help wpda_tooltip" title="Oracle service name." style="cursor:pointer"></span>
										</label>
									</div>
									<div class="dbs">
										<label>Database</label>
										<input type="text" name="pds_dbs">
										<label>
											<span class="dashicons dashicons-editor-help wpda_tooltip" title="Database name server." style="cursor:pointer"></span>
										</label>
									</div>
									<div>
										<label>Username</label>
										<input type="text" name="pds_usr">
										<label>
											<span class="dashicons dashicons-editor-help wpda_tooltip" title="Database username." style="cursor:pointer"></span>
										</label>
									</div>
									<div>
										<label>Password</label>
										<input type="password" name="pds_pwd" autocomplete="new-password">
										<label>
											<span class="dashicons dashicons-editor-help wpda_tooltip" title="Database password." style="cursor:pointer"></span>
										</label>
									</div>
									<div>
										<label>Source table name</label>
										<input type="text" name="pds_source_table_name">
										<label>
											<span class="dashicons dashicons-editor-help wpda_tooltip" title="Table or view name on your database server." style="cursor:pointer"></span>
										</label>
									</div>
								</fieldset>

								<fieldset class="wpda_fieldset connect_item csv json xml accessdb access" style="display:none">
									<legend>
										Remote file
									</legend>

									<div>
										<label>File path</label>
										<input type="text" name="pds_path">
										<label>
											<span class="dashicons dashicons-editor-help wpda_tooltip" title="Public URL to your file. This URL must return the content of your file. Redirection are not supported. Please test your URL before you submit!" style="cursor:pointer"></span>
										</label>
									</div>
									<div>
										<label>Update interval</label>
										<input type="number" name="pds_interval">
										<select name="pds_interval_unit"  style="vertical-align:baseline">
											<option value=""></option>
											<option value="hour">hours</option>
											<option value="day">days</option>
										</select>
										<label>
											<span class="dashicons dashicons-editor-help wpda_tooltip" title="Frequency used to update. Make sure your file remains available. Updates result in errors when the file is not available or corrupt.

	Leave empty if updates are not required." style="cursor:pointer"></span>
										</label>
									</div>
								</fieldset>

							<fieldset class="wpda_fieldset connect_item csv" style="display:none">
								<legend>
									CSV file
								</legend>

								<div>
									<label>CSV header</label>
									<select name="pds_csv_header">
										<option value="1">CSV contains header row</option>
										<option value="0">CSV contains no header row</option>
									</select>
									<label>
										<span class="dashicons dashicons-editor-help wpda_tooltip" title="The header row is used to define the column names. If a header row is not present, column names are auto generated." style="cursor:pointer"></span>
									</label>
								</div>
								<div>
									<label>CSV delimiter</label>
									<input type="text" name="pds_csv_delimiter" value=",">
									<label>
										<span class="dashicons dashicons-editor-help wpda_tooltip" title="Character that separates field values. Usually a comma (,)." style="cursor:pointer"></span>
									</label>
								</div>
							</fieldset>

							<fieldset class="wpda_fieldset connect_item json" style="display:none">
								<legend>
									JSON file
								</legend>

								<div>
									<label>Table object</label>
									<input type="text" name="pds_json_object" value="">
									<label>
										<span class="dashicons dashicons-editor-help wpda_tooltip" title="Object which contains the table rows." style="cursor:pointer"></span>
									</label>
								</div>
							</fieldset>

							<fieldset class="wpda_fieldset connect_item connect" style="display:none">
									<legend>
										Local database
									</legend>

									<div>
										<label>Table name</label>
										<input type="text" name="pds_table_name" id="pds_table_name" maxlength="64">
										<label>
											<span class="dashicons dashicons-editor-help wpda_tooltip" title="You will be able to access your table in WordPress with this name." style="cursor:pointer"></span>
										</label>
									</div>
								</fieldset>
							</p>

							<p style="display:none" class="connect_item connect">
								<label></label>
								<button type="submit" class="button button-primary">
									<i class="fas fa-check wpda_icon_on_button"></i>
									Create
								</button>
								<button type="button" class="button button-secondary" onclick="jQuery('#wpda_pds_canvas').toggle()">
									<i class="fas fa-times-circle wpda_icon_on_button"></i>
									Cancel
								</button>
							</p>
							<input type="hidden" name="action" value="pds">
							<input type="hidden" name="pds_server" value="<?php echo esc_attr( $this->pds_server ); ?>" />
							<input type="hidden" name="wpnonce" value="<?php echo esc_attr( self::PREMIUM_DATA_SERVICES_WPNONCE ); ?>" />
						</form>
					</div>
				</div>
			</div>
			<?php
			$this->js();
			$this->css();
		}

		public function upload_post( $wpdadb, $schema_name ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( __( 'ERROR: Not authorized', 'wp-data-access' ) );
			}

			// Prepare arguments
			$checksum = md5( mt_rand() );
			$args     = [
				'pds_schema_name' => $wpdadb->dbname,
			];

			foreach ( $_POST as $arg => $value ) {
				if ( 'pds_' === substr( $arg, 0, 4 ) ) {
					$args[ $arg ] = sanitize_text_field( wp_unslash( $value ) );
				}
			}

			$arg = json_encode( $args, true );

			// Save create table command
			$suppress = $wpdadb->suppress_errors( true );
			$wpdadb->query(
				$wpdadb->prepare(
					"call pds(%s, %s, @id)",
					$checksum,
					$arg
				)
			);
			$wpdadb->suppress_errors( $suppress );

			// Get command id
			$id = $wpdadb->get_results( 'select @id', 'ARRAY_N' );
			if ( '' !== $wpdadb->last_error || ! isset( $id[0][0] ) ) {
				// PDS error
				$msg = new WPDA_Message_Box(
					array(
						'message_text'           => sprintf( __( 'Database error: %s', 'wp-data-access' ), $wpdadb->last_error ),
						'message_type'           => 'error',
						'message_is_dismissible' => false,
					)
				);
				$msg->box();
			} else {
				$pds_url = self::get_url_from_schema_name( $schema_name );
				if ( false === $pds_url ) {
					$msg = new WPDA_Message_Box(
						array(
							'message_text'           => __( 'Remote call failed [invalid url]', 'wp-data-access' ),
							'message_type'           => 'error',
							'message_is_dismissible' => false,
						)
					);
					$msg->box();

					return;
				}

				// PDS request successful, send request to execute command on pds server
				$json = WPDA_Remote_Call::post(
					$pds_url . 'create.php',
					array(
						'i' => $id[0][0],
						'c' => $checksum,
					)
				);

				if ( false === $json ) {
					$msg = new WPDA_Message_Box(
						array(
							'message_text'           => __( 'Remote call failed', 'wp-data-access' ),
							'message_type'           => 'error',
							'message_is_dismissible' => false,
						)
					);
					$msg->box();

					return;
				}

				if ( isset( $json['response']['code'] ) && 200 !== $json['response']['code'] ) {
					if ( isset( $json['response']['message'] ) ) {
						$msg = new WPDA_Message_Box(
							array(
								'message_text'           => __( esc_attr( $json['response']['message'] ) . ' (' . esc_attr( $json['response']['code'] ) . ')', 'wp-data-access' ),
								'message_type'           => 'error',
								'message_is_dismissible' => false,
							)
						);
						$msg->box();

						return;
					} else {
						$msg = new WPDA_Message_Box(
							array(
								'message_text'           => __( 'Remote call failed [invalid response]', 'wp-data-access' ),
								'message_type'           => 'error',
								'message_is_dismissible' => false,
							)
						);
						$msg->box();

						return;
					}
				}

				$body = json_decode( $json['body'], true );
				if ( null === $body ) {
					$msg = new WPDA_Message_Box(
						array(
							'message_text'           => __( 'Remote call failed [response malformed]', 'wp-data-access' ),
							'message_type'           => 'error',
							'message_is_dismissible' => false,
						)
					);
					$msg->box();

					return;
				}

				if ( isset( $body['status'] ) && 'ERR' === $body['status'] ) {
					$msg = new WPDA_Message_Box(
						array(
							'message_text'           => isset( $body['msg'] ) ? esc_attr( $body['msg'] ) : __( 'Internal server error', 'wp-data-access' ),
							'message_type'           => 'error',
							'message_is_dismissible' => false,
						)
					);
					$msg->box();
				} elseif ( isset( $body['status'] ) && 'OK' === $body['status'] ) {
					// OK
					$table_name = isset( $args[ 'pds_table_name' ] ) ? $args[ 'pds_table_name' ] : '';
					$msg = new WPDA_Message_Box(
						array(
							'message_text' => sprintf( __( 'Table `%s` created', 'wp-data-access' ), $table_name )
						)
					);
					$msg->box();
				} else {
					// Error
					$msg = new WPDA_Message_Box(
						array(
							'message_text'           => __( 'Internal server error', 'wp-data-access' ),
							'message_type'           => 'error',
							'message_is_dismissible' => false,
						)
					);
					$msg->box();
				}
			}
		}

		public static function get_messages( $schema_name ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( __( 'ERROR: Not authorized', 'wp-data-access' ) );
			}

			// Connect to premium data service
			$wpdadb = WPDADB::get_db_connection( $schema_name );
			if ( $wpdadb === null ) {
				return false;
			}

			$suppress = $wpdadb->suppress_errors( true );
			$msg = $wpdadb->get_results(
				"call msg()",
				'ARRAY_A'
			);
			$wpdadb->suppress_errors( $suppress );

			return $msg;
		}

		public static function optimize_table( $wpdadb, $table_name ) {
			// Optimize table.
			$suppress = $wpdadb->suppress_errors( true );
			$wpdadb->query( "optimize table `$table_name`" ); // db call ok; no-cache ok.
			$wpdadb->suppress_errors( $suppress );
		}

		public static function refresh_table( $table_name, $schema_name ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( __( 'ERROR: Not authorized', 'wp-data-access' ) );
			}

			// Connect to premium data service
			$wpdadb = WPDADB::get_db_connection( $schema_name );
			if ( $wpdadb === null ) {
				return false;
			}

			// Prepare arguments
			$checksum = md5( mt_rand() );
			$args     = [
				'pds_schema_name' => $wpdadb->dbname,
				'pds_table_name'  => $table_name,
			];

			// Save create table command
			$suppress = $wpdadb->suppress_errors( true );
			$wpdadb->query(
				$wpdadb->prepare(
					"call pds(%s, %s, @id)",
					$checksum,
					json_encode( $args, true )
				)
			);
			$wpdadb->suppress_errors( $suppress );

			// Get command id
			$id = $wpdadb->get_results( 'select @id', 'ARRAY_N' );
			if ( '' !== $wpdadb->last_error || ! isset( $id[0][0] ) ) {
				// PDS error
				return false;
			} else {
				$pds_url = self::get_url_from_schema_name( $schema_name );
				if ( false === $pds_url ) {
					return false;
				}

				// PDS request successful, send request to execute command on pds server
				$json = WPDA_Remote_Call::post(
					$pds_url . 'refresh.php',
					array(
						'i' => $id[0][0],
						'c' => $checksum,
					)
				);

				if ( false === $json ) {
					return false;
				}

				if ( isset( $json['response']['code'] ) && 200 !== $json['response']['code'] ) {
					return false;
				}

				$msg = json_decode( $json['body'], true );
				if ( null === $msg ) {
					return false;
				}

				if ( isset( $msg['status'] ) && 'ERR' === $msg['status'] ) {
					// Error
					return false;
				} elseif ( isset( $msg['status'] ) && 'OK' === $msg['status'] ) {
					// OK
					return true;
				} else {
					// Error
					return false;
				}
			}
		}

	}

}