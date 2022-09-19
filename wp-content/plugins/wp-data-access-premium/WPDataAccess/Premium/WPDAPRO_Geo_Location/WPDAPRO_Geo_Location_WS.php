<?php

namespace WPDataAccess\Premium\WPDAPRO_Geo_Location {

	use GuzzleHttp\Client;
	use GuzzleHttp\Exception\GuzzleException;
	use WPDataAccess\Connection\WPDADB;
	use WPDataAccess\Data_Dictionary\WPDA_List_Columns_Cache;
	use WPDataAccess\Data_Tables\WPDA_Data_Tables;
	use WPDataAccess\WPDA;

	class WPDAPRO_Geo_Location_WS {

		const RADIUS_UNIT_KM   = 6371;
		const RADIUS_UNIT_MILE = 3959;

		private function get_geoquery(
			$table_name,
			$selection,
			$latitude,
			$longitude,
			$step,
			$start,
			$where_arg,
			$user_latitude,
			$user_longitude,
			$user_radius,
			$user_radius_unit
		) {
			$radius      = is_numeric( $user_radius ) ? $user_radius : -1; // Show no results on invalid input
			$radius_unit = $user_radius_unit === 'mile' ? self::RADIUS_UNIT_MILE : self::RADIUS_UNIT_KM;

			$query = 'select * ';
			if ( 'geo' === $selection ) {
				$query .= ", ({$radius_unit} * acos (
								  cos ( radians({$user_latitude}) )
								  * cos( radians( {$latitude} ) )
								  * cos( radians( {$longitude} ) - radians({$user_longitude}) )
								  + sin ( radians({$user_latitude}) )
								  * sin( radians( {$latitude} ) )
								)
							) AS distance";
			}
			$query .= ' from `' . str_replace( '`', '', $table_name ) . '` ';

			if ( '' !== $where_arg  ) {
				if ( 'where' === substr( strtolower( ltrim( $where_arg, ' ' ) ), 0, 5 ) ) {
					$query .= $where_arg;
				} else {
					$query .= " where {$where_arg} ";
				}
			}
			if ( 'all' === $selection ) {
				// All rows
			} else if ( 'new' === $selection ) {
				// All rows without a geolocation
				$and    = '' === $where_arg ? ' where ' : ' and ';
				$query .=
					" {$and} ( `" . str_replace( '`', '', $latitude ) . '` is null ' .
					' or `' . str_replace( '`', '', $longitude ) . '` is null ) ';
			} else if ( 'geo' === $selection ) {
				// All rows with a geolocation
				$and    = '' === $where_arg ? ' where ' : ' and ';
				$query .=
					" {$and} ( `" . str_replace( '`', '', $latitude ) . '` is not null ' .
					' or `' . str_replace( '`', '', $longitude ) . '` is not null ) ';
			}

			if ( 'geo' === $selection ) {
				$query .= " having distance < {$radius} ";
				$query .= " order by distance ";
			}

			$query .= " limit {$step} offset {$start} ";

			return $query;
		}

		public function get_data_ajax_anonymous() {
			$this->get_data_ajax();
		}

		public function get_data_ajax() {
			$status    = 'ok';
			$message   = '';
			$data      = [];

			if (
				isset(
					$_POST['wpda_wpnonce'],
					$_POST['wpda_schema_name'],
					$_POST['wpda_table_name'],
					$_POST['wpda_latitude'],
					$_POST['wpda_longitude'],
					$_POST['wpda_selection'],
					$_POST['wpda_start'],
					$_POST['wpda_step']
				)
			) {
				$wpnonce         = sanitize_text_field( wp_unslash( $_POST['wpda_wpnonce'] ) ); // input var okay.
				$schema_name     = sanitize_text_field( wp_unslash( $_POST['wpda_schema_name'] ) ); // input var okay; sanitization okay.
				$table_name      = sanitize_text_field( wp_unslash( $_POST['wpda_table_name'] ) ); // input var okay; sanitization okay.
				$latitude        = sanitize_text_field( wp_unslash( $_POST['wpda_latitude'] ) ); // input var okay; sanitization okay.
				$longitude       = sanitize_text_field( wp_unslash( $_POST['wpda_longitude'] ) ); // input var okay; sanitization okay.
				$selection       = sanitize_text_field( wp_unslash( $_POST['wpda_selection'] ) ); // input var okay; sanitization okay.
				$start           = sanitize_text_field( wp_unslash( $_POST['wpda_start'] ) ); // input var okay; sanitization okay.
				$step            = sanitize_text_field( wp_unslash( $_POST['wpda_step'] ) ); // input var okay; sanitization okay.

				if ( 'geo' === $selection &&
					! isset(
						$_REQUEST['wpda_user_latitude'],
						$_REQUEST['wpda_user_longitude'],
						$_REQUEST['wpda_user_radius'],
						$_REQUEST['wpda_user_radius_unit']
					)
				) {
					$status  = 'error';
					$message = __( 'Invalid arguments', 'wp-data-access' );
				} else {
					$user_latitude    = sanitize_text_field( wp_unslash( $_POST['wpda_user_latitude'] ) ); // input var okay; sanitization okay.
					$user_longitude   = sanitize_text_field( wp_unslash( $_POST['wpda_user_longitude'] ) ); // input var okay; sanitization okay.
					$user_radius      = sanitize_text_field( wp_unslash( $_POST['wpda_user_radius'] ) ); // input var okay; sanitization okay.
					$user_radius_unit = sanitize_text_field( wp_unslash( $_POST['wpda_user_radius_unit'] ) ); // input var okay; sanitization okay.
					if ( isset( $_POST['wpda_msg'] ) ) {
						$user_msg = $_POST['wpda_msg'];
					} else {
						$user_msg = '';
					}

					if ( ! wp_verify_nonce( $wpnonce, "wpdapro-geo-get-data-{$table_name}" ) ) {
						$status  = 'error';
						$message = __( 'Token expired or not authorized', 'wp-data-access' );
					} else {
						$wpdadb = WPDADB::get_db_connection( $schema_name );
						if ( null !== $wpdadb ) {
							// Check for URL arguments
							$wpda_list_columns = WPDA_List_Columns_Cache::get_list_columns( $schema_name, $table_name );
							$table_columns     = $wpda_list_columns->get_table_columns();
							$where             = '';
							if ( '' !== $user_msg ) {
								$args_handled = false;
								if ( isset( $user_msg['filter_dyn'] ) ) {
									$filter_dyn   = isset( $user_msg['filter_dyn'] ) ? sanitize_text_field( wp_unslash( $user_msg['filter_dyn'] ) ) : ''; // input var okay; sanitization okay.
									$where        = WPDA::construct_where_clause( $schema_name, $table_name, $table_columns, $filter_dyn );
									$args_handled = true;
								}
								if ( isset( $user_msg['filter_args'] ) ) {
									foreach ( $user_msg['filter_args'] as $key => $val ) {
										if ( '' === $where ) {
											$where =
												$wpdadb->prepare(
													" where `{$key}` like %s ", [ "%$val%" ]
												);
										} else {
											$where .=
												$wpdadb->prepare(
													" and `{$key}` like %s ", [ "%$val%" ]
												);
										}

									}
									$args_handled = true;
								}
								if ( isset( $user_msg['filter_default'] ) ) {
									$filter = sanitize_text_field( wp_unslash( $user_msg['filter_default'] ) ); // input var okay; sanitization okay.
									if ( '' !== $filter ) {
										if ( '' !== $where ) {
											$where = $filter;
										} else {
											$where = " and ( $filter ) ";
										}
									}
								}
								if ( isset( $user_msg['filter_field_name'] ) && isset( $user_msg['filter_field_value'] ) ) {
									$filter_field_name  = str_replace( '`', '', sanitize_text_field( wp_unslash( $_REQUEST['filter_field_name'] ) ) ); // input var okay.
									$filter_field_value = sanitize_text_field( wp_unslash( $_REQUEST['filter_field_value'] ) ); // input var okay.
									if ( '' !== $filter_field_name && '' !== $filter_field_value ) {
										$filter_field_name_array = array_map('trim', explode( ',', $filter_field_name ) );
										$filter_field_value_array = array_map('trim', explode( ',', $filter_field_value ) );
										if ( sizeof( $filter_field_name_array ) === sizeof( $filter_field_value_array ) ) {
											// Add filter to where clause
											for ( $i = 0; $i < sizeof( $filter_field_name_array ); $i++ ) {
												if ( '' === $where ) {
													$where =
														$wpdadb->prepare(
															" where `{$filter_field_name_array[ $i ]}` like %s ", [ $filter_field_value_array[ $i ] ]
														);
												} else {
													$where .=
														$wpdadb->prepare(
															" and `{$filter_field_name_array[ $i ]}` like %s ", [ $filter_field_value_array[ $i ] ]
														);
												}
											}
										}
									}
								}
								// Handle Search Panes
								if ( isset( $user_msg['searchPanes'] ) && is_array( $user_msg['searchPanes'] ) ) {
									foreach ( $user_msg['searchPanes'] AS $pane ) {
										if ( null !== $pane ) {
											$key = isset( $pane['key'] ) ? sanitize_text_field( wp_unslash( $pane['key'] ) ) : null;
											$val = isset( $pane['val'] ) ? sanitize_text_field( wp_unslash( $pane['val'] ) ) : null;
											if ( null !== $key && null !== $val ) {
												if ( '' === $where ) {
													$where =
														$wpdadb->prepare(
															" where `{$key}` = %s ", [ $val ]
														);
												} else {
													$where .=
														$wpdadb->prepare(
															" and `{$key}` = %s ", [ $val ]
														);
												}
											}
										}
									}
								}
								// Handle Search Builder
								if ( isset( $user_msg['searchBuilder'] ) ) {
									$wpda_data_tables = new WPDA_Data_Tables();
									$searchbuilder    = WPDA::sanitize_text_field_array( $user_msg['searchBuilder'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
									$sb_where = $wpda_data_tables->qb_group( $searchbuilder );
									if ( '' !== $sb_where ) {
										if ( strpos( $where, '1=3' ) !== false ) {
											$where = '';
										}
										$where .= ( '' === $where ? ' WHERE ' : ' AND ' ) . $sb_where;
									}

								}
								if ( ! $args_handled ) {
									$where_args = WPDA::add_wpda_search_args( $table_columns );
									if ( '' !== $where_args ) {
										if ( '' === $where ) {
											$where = $where_args;
										} else {
											$where .= " and ( $where_args ) ";
										}
									}
								}
							} else {
								$where = WPDA::add_wpda_search_args( $table_columns );
							}
							$where = WPDA::substitute_environment_vars( $where );
							// Execute query
							$query = $this->get_geoquery(
								$table_name,
								$selection,
								$latitude,
								$longitude,
								$step,
								$start,
								$where,
								$user_latitude,
								$user_longitude,
								$user_radius,
								$user_radius_unit
							);
							$data  = $wpdadb->get_results( $query, 'ARRAY_A' );
						} else {
							$status  = 'error';
							$message = __( 'Database connection failed', 'wp-data-access' );
						}
					}
				}
			} else {
				$status  = 'error';
				$message = __( 'Invalid arguments', 'wp-data-access' );
			}

			$response = [
				'status'  => $status,
				'message' => $message,
				'data'    => $data,
			];

			WPDA::sent_header('application/json');

			echo json_encode( $response, JSON_NUMERIC_CHECK );
			wp_die();
		}

		public function get_geolocation( $address ) {
			try {
				$client  = new Client();
				$request = $client->request(
					'GET',
					'https://maps.google.com/maps/api/geocode/json',
					[
						'query' => [
							'address' => $address,
							'key'     => 'AIzaSyDfeEN-tX-ENxWVf542AMzKzzqBpAR5iV8'
						]
					]
				);
				return json_decode( $request->getBody()->getContents(), true );
			} catch ( GuzzleException $e ) {
				return [
					'status' => 'error',
					'error' => [
						'nr'  => $e->getCode(),
						'msg' => $e->getMessage()
					]
				];
			}
		}

		public function update_geolocation_ajax() {
			$status    = 'ok';
			$message   = '';

			if (
				isset(
					$_POST['wpda_wpnonce'],
					$_POST['wpda_schema_name'],
					$_POST['wpda_table_name'],
					$_POST['wpda_primary_key'],
					$_POST['wpda_lookup'],
					$_POST['wpda_geolocation_settings']
				)
			) {
				$wpnonce     = sanitize_text_field( wp_unslash( $_POST['wpda_wpnonce'] ) ); // input var okay.
				$schema_name = sanitize_text_field( wp_unslash( $_POST['wpda_schema_name'] ) ); // input var okay; sanitization okay.
				$table_name  = sanitize_text_field( wp_unslash( $_POST['wpda_table_name'] ) ); // input var okay; sanitization okay.
				$pkey        = wp_unslash( $_POST['wpda_primary_key'] ); // input var okay; sanitization okay.
				$lookup      = sanitize_text_field( wp_unslash( $_POST['wpda_lookup'] ) ); // input var okay; sanitization okay.
				$settings    = wp_unslash( $_POST['wpda_geolocation_settings'] ); // input var okay; sanitization okay.

				if ( ! wp_verify_nonce( $wpnonce, "wpdapro-geo-update-{$table_name}" ) ) {
					$status  = 'error';
					$message = __( 'Token expired or not authorized', 'wp-data-access' );
				} else {
					// Get geolocation
					$geolocation = $this->get_geolocation( $lookup );
					if ( 'OK' === $geolocation['status'] ) {
						// Process geolocation data
						$geometry = $geolocation['results'][0]['geometry'];

						// Prepare update statement
						$column_location_lat      = sanitize_text_field( $settings['latitude'] );
						$column_location_lng      = sanitize_text_field( $settings['longitude'] );
						$column_location_type     = sanitize_text_field( $settings['type'] );
						$column_location_viewport = sanitize_text_field( $settings['viewport'] );

						$update_columns = [
							$column_location_lat => $geometry['location']['lat'],
							$column_location_lng => $geometry['location']['lng'],
						];
						if ( null !== $column_location_type && '' !== $column_location_type ) {
							$update_columns[$column_location_type] = $geometry['location_type'];
						}
						if ( null !== $column_location_viewport && '' !== $column_location_viewport ) {
							$update_columns[$column_location_viewport] = $geometry['viewport'];
						}

						$update_keys = [];
						foreach ( $pkey as $key => $val ) {
							$update_keys[ $key ] = $val;
						}

						// Update geolocation columns
						$wpdadb = WPDADB::get_db_connection( $schema_name );
						$norows = $wpdadb->update(
							$table_name,
							$update_columns,
							$update_keys
						);

						if ( $norows < 1 ) {
							$status  = 'ok';
							$message = 'Nothing updated';
						}
					} else {
						$status    = 'error';
						$message   = __( 'Invalid response', 'wp-data-access' );
					}
				}
			} else {
				$status  = 'error';
				$message = __( 'Invalid arguments', 'wp-data-access' );
			}

			WPDA::sent_header('application/json');

			$response = [
				'status'  => $status,
				'message' => $message
			];

			WPDA::sent_header('application/json');

			echo json_encode( $response, JSON_NUMERIC_CHECK );
			wp_die();
		}

	}

}