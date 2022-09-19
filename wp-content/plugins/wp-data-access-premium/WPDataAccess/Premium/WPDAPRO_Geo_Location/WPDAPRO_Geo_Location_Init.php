<?php

namespace WPDataAccess\Premium\WPDAPRO_Geo_Location {

	use WPDataAccess\Data_Dictionary\WPDA_Dictionary_Exist;
	use WPDataAccess\Data_Dictionary\WPDA_List_Columns;
	use WPDataAccess\Plugin_Table_Models\WPDA_Table_Settings_Model;
	use WPDataAccess\WPDA;

	class WPDAPRO_Geo_Location_Init {

		public function shortcode_wpdageomap( $atts ) {
			$editing = WPDA::is_editing_post();
			if ( false !== $editing ) {
				// Prevent errors when user is editing a post
				return $editing;
			}

			$atts    = array_change_key_case( (array) $atts, CASE_LOWER );
			$wp_atts = shortcode_atts(
				[
					'schema_name'  => '',
					'table_name'   => '',
					'map_center'   => 'plugin',
					'map_zoom'     => 'plugin',
					'map_width'    => 'plugin',
					'map_height'   => 'plugin',
					'map_select'   => 'plugin',
					'map_init'     => 'plugin',
					'map_label'    => 'plugin',
					'map_location' => 'plugin',
					'map_search'   => null,
					'map_mode'	   => null,
				], $atts
			);

			$schema_name  = $wp_atts['schema_name'];
			$table_name   = $wp_atts['table_name'];
			$map_center   = $wp_atts['map_center'];
			$map_zoom     = $wp_atts['map_zoom'];
			$map_width    = $wp_atts['map_width'];
			$map_height   = $wp_atts['map_height'];
			$map_select   = $wp_atts['map_select'];
			$map_init     = $wp_atts['map_init'];
			$map_label    = $wp_atts['map_label'];
			$map_location = $wp_atts['map_location'];
			$map_search   = $wp_atts['map_search'];
			$map_mode     = $wp_atts['map_mode'];

			if ( '' === $schema_name || '' === $table_name ) {
				return '<p>' . __( 'ERROR - Invalid arguments', 'wp-data-access' ) . '</p>';
			}

			if ( ! WPDA::schema_exists( $schema_name ) ) {
				return '<p>' . __( 'ERROR - Schema name not found', 'wp-data-access' ) . '</p>';
			}

			$dictionary = new WPDA_Dictionary_Exist( $schema_name, $table_name );
			if ( ! $dictionary->table_exists() ) {
				return '<p>' . __( 'ERROR - Table not found or not authorized', 'wp-data-access' ) . '</p>';
			}

			$listcols     = new WPDA_List_Columns( $schema_name, $table_name );
			$columns      = $listcols->get_table_columns();
			$column_names = [];
			for ( $i = 0; $i < sizeof( $columns ); $i++ ) {
				$column_names[ $columns[ $i ]['column_name'] ] = true;
			}

			$settings_db       = WPDA_Table_Settings_Model::query( $table_name, $schema_name );
			$settings_db_valid = false;
			$marker_info       = '';
			if ( isset( $settings_db[0]['wpda_table_settings'] ) ) {
				$settings_object   = json_decode( $settings_db[0]['wpda_table_settings'] );
				$settings_db_valid =
					isset( $settings_object->geolocation_settings->status ) &&
					'enabled' === $settings_object->geolocation_settings->status &&
					isset(
						$settings_object->geolocation_settings->google_maps_api_key,
						$settings_object->geolocation_settings->latitude,
						$settings_object->geolocation_settings->longitude,
						$settings_object->geolocation_settings->type,
						$settings_object->geolocation_settings->viewport,
						$settings_object->geolocation_settings->initial_location,
						$settings_object->geolocation_settings->initial_lat,
						$settings_object->geolocation_settings->initial_lng,
						$settings_object->geolocation_settings->marker,
						$settings_object->geolocation_settings->marker_title,
						$settings_object->geolocation_settings->marker_info,
						$settings_object->geolocation_settings->unit,
						$settings_object->geolocation_settings->unit_select,
						$settings_object->geolocation_settings->radius,
						$settings_object->geolocation_settings->radius_csv,
						$settings_object->geolocation_settings->radius_type,
						$settings_object->geolocation_settings->width,
						$settings_object->geolocation_settings->width_type,
						$settings_object->geolocation_settings->height,
						$settings_object->geolocation_settings->height_type,
						$settings_object->geolocation_settings->zoom,
						$settings_object->geolocation_settings->auto_zoom,
						$settings_object->geolocation_settings->location_marker_title,
						$settings_object->geolocation_settings->location_marker_info,
						$settings_object->geolocation_settings->location_marker_max
					);
				if ( $settings_db_valid ) {
					$marker_info = str_replace( "\n", '', $settings_object->geolocation_settings->marker_info );
				}
			}

			if ( ! $settings_db_valid ) {
				return 'ERROR - Invalid geolocation configuration';
			}

			$wp_nonce_action_batch = "wpdapro-geo-get-data-{$table_name}";
			$wp_nonce_batch        = wp_create_nonce( $wp_nonce_action_batch );

			$current_zoom = $settings_object->geolocation_settings->zoom;
			$fixed_zoom   = null;
			if ( isset( $_REQUEST['map_zoom'] ) && is_numeric( $_REQUEST['map_zoom'] ) ) {
				$current_zoom = sanitize_text_field( wp_unslash( $_REQUEST['map_zoom'] ) );
				$fixed_zoom   = $current_zoom;
			} else {
				if ( is_numeric( $map_zoom ) ) {
					$current_zoom = $map_zoom;
					$fixed_zoom   = $current_zoom;
				}
			}

			if ( isset( $_REQUEST['map_center'] ) && 'first' === $_REQUEST['map_center'] ) {
				$map_center = 'first';
			}

			if ( isset( $_REQUEST['map_select'] ) ) {
				$map_select = sanitize_text_field( wp_unslash( $_REQUEST['map_select'] ) );
			}

			if ( $map_width === 'plugin' ) {
				$map_width = $settings_object->geolocation_settings->width . $settings_object->geolocation_settings->width_type;
			}

			if ( $map_height === 'plugin' ) {
				$map_height = $settings_object->geolocation_settings->height . $settings_object->geolocation_settings->height_type;
			}

			if ( $map_location === 'user' ) {
				$user_location = true;
			} elseif ( $map_location === 'fixed' ) {
				$user_location = false;
			} else {
				$user_location = $settings_object->geolocation_settings->initial_location === 'user';
			}

			ob_start();
 			?>
			<style type="text/css">
                .wpdapro_geolocation_container {
                    height: <?php echo esc_attr( $map_height ); ?>;
                    width: <?php echo esc_attr( $map_width ); ?>;
                }
                .wpdapro_geolocation_selection {
                    float: right;
                }
				.wpdapro_geolocation_selection_search,
                .wpdapro_geolocation_range_selection {
					vertical-align: bottom;
				}
                .wpdapro_geolocation_selection_search *,
                .wpdapro_geolocation_range_selection * {
                    vertical-align: middle;
                }
                #wpdapro_geolocation_home {
                    width: 100%;
                    height: 100%;
                    border: 1px solid lightgrey;
                }
			</style>
			<script type="text/javascript">
				var columns = <?php echo json_encode( $column_names ); ?>;
				var wpAjaxUrl = "<?php echo admin_url('admin-ajax.php'); ?>";
				var map = null;
				var markers = [];
				var markerPopup = null;
				var coords = null;
				var labelIndex = 0;
				var home = {
					lat: <?php echo $settings_object->geolocation_settings->initial_lat; ?>,
					lng: <?php echo $settings_object->geolocation_settings->initial_lng; ?>
				};
				var autoZoom = <?php echo $settings_object->geolocation_settings->auto_zoom; ?>;
				var autocomplete;
				var autocompletePlace = null;
				var startMode = "<?php echo isset( $settings_object->geolocation_settings->start_mode ) ? $settings_object->geolocation_settings->start_mode : 'map'; ?>";
				<?php
				if ( null !== $fixed_zoom ) {
					echo 'autoZoom = false;';
				}
				if (
					'map' === $map_mode ||
					'satellite' === $map_mode ||
					'hybrid' === $map_mode ||
					'terrain' === $map_mode
				) {
					echo 'startMode = ' . esc_attr( $map_mode ) . ';';
				}
				?>
				var bounds = null;

				function wpdaproGetUserLocation(callback = null) {
					if (!navigator.geolocation) {
						jQuery("#wpdapro_geolocation_home").text("Geolocation is not supported by your browser");
					} else {
						navigator.geolocation.getCurrentPosition(
							function(pos) {
								if (pos.coords===undefined) {
									coords = null;
									jQuery("#wpdapro_geolocation_home").text("ERROR - Could not determine user location");
								} else {
									coords = pos.coords;
									if (callback!==null) {
										callback();
									}
								}
							},
							function(err) {
								coords = null;
								jQuery("#wpdapro_geolocation_home").text("ERROR - " + err);
							},
							{
								enableHighAccuracy: true,
								timeout: 5000,
								maximumAge: 0
							}
						);
					}
				}

				function setUserPosition() {
					if (coords!==null) {
						home = {
							lat: coords.latitude,
							lng: coords.longitude
						};

						wpdaproGeolocationCreate();

						if (map!==null) {
							map.setCenter(home);
						}
					}
				}

				function addMarker(position, title, info, addLabel = true) {
					var markerArgs = {
						map: map,
						position: position,
						info: info
					};

					if (addLabel && "<?php echo esc_attr( $map_label ); ?>"==="true") {
						markerArgs.label = String.fromCharCode(65+labelIndex);
						labelIndex++;
					}

					if (title!=="") {
						markerArgs.title = title;
					}

					var marker = new google.maps.Marker(markerArgs);
					markers.push(marker);

					if (info!=="") {
						marker.addListener("click", () => {
							markerPopup.setPosition(position);
							markerPopup.setContent(info);
							markerPopup.open(map);
						});
					}
				}

				function popupClose() {
					markerPopup.close();
				}

				function popupMarker(label) {
					for (var i=0; i<markers.length; i++) {
						if (markers[i].getLabel()===label) {
							map.panTo(markers[i].getPosition());
							markerPopup.setContent(markers[i].info);
							markerPopup.open(map);
						}
					}
				}

				function deleteAllMarkers() {
					for (var i=0; i<markers.length; i++) {
						markers[i].setMap(null);
					}

					markers = [];
				}

				function renderMarkerText( markerText, markerObject ) {
					for ( var columnName in columns ) {
						markerText = markerText.replaceAll( "$$" + columnName + "$$", markerObject[columnName] );
					}

					return markerText;
				}

				function wpdaproGetGeoData(args = null) {
					// console.log(args);
					labelIndex = 0;

					// Prepare request
					if (args!==null) {
						msg = args.msg ? args.msg : null;
					} else {
						msg = null;
					}

					var data = {
						wpda_wpnonce: "<?php echo $wp_nonce_batch;?>",
						wpda_schema_name: "<?php echo esc_attr( $schema_name ); ?>",
						wpda_table_name: "<?php echo esc_attr( $table_name ); ?>",
						wpda_latitude: "<?php echo esc_attr( $settings_object->geolocation_settings->latitude ); ?>",
						wpda_longitude: "<?php echo esc_attr( $settings_object->geolocation_settings->longitude ); ?>",
						wpda_selection: "geo",
						wpda_start: 0,
						wpda_step: "<?php echo $settings_object->geolocation_settings->location_marker_max; ?>",
						wpda_user_latitude: home.lat,
						wpda_user_longitude: home.lng,
						wpda_user_radius:  jQuery("#wpdapro_geolocation_selection_range").val(),
						wpda_user_radius_unit: jQuery("#wpdapro_geolocation_selection_range_unit").val(),
						wpda_msg: msg
					};

					// Add user specific arguments
					var wpda_url_params = {};
					jQuery.each(window.location.search.replace('?','').split('&'), function(index, val) {
						var urlparam = val.split('=');
						if (urlparam.length===2) {
							if (urlparam[0].substring(0, 19) === 'wpda_search_column_') {
								wpda_url_params[urlparam[0]] = urlparam[1];
							}
						}
					});
					for (var arg in wpda_url_params) {
						data[arg] = wpda_url_params[arg];
						data.wpda_user_radius = 99999;
					}

					// Add function arguments
					if (args!==null) {
						if (args.start!==undefined) {
							data.wpda_start = args.start;
						}
						if (args.step!==undefined) {
							data.wpda_step = args.step;
						}
						if (args.radius!==undefined) {
							data.wpda_user_radius = args.radius;
						}
						if (args.unit) {
							data.wpda_user_radius_unit = args.unit;
						}
						if (args.user_latitude && args.user_longitude) {
							data.wpda_user_latitude = args.user_latitude;
							data.wpda_user_longitude = args.user_longitude;
						}
						data.wpda_msg = {};
						if (args.msg && typeof args.msg === 'object' && args.msg.filter_args) {
							data.wpda_msg.filter_args = {};
							for (var prop in args.msg.filter_args) {
								data.wpda_msg.filter_args[prop] = args.msg.filter_args[prop];
							}
						}
						if (args.search) {
							data.wpda_msg.filter_dyn = args.search;
						}
						if (args.msg && typeof args.msg === 'object' && args.msg.searchPanes) {
							data.wpda_msg.searchPanes = args.msg.searchPanes;
						}
						if (args.msg && typeof args.msg === 'object' && args.msg.searchBuilder) {
							data.wpda_msg.searchBuilder = args.msg.searchBuilder;
						}
					}

					if (autocompletePlace!==null && autocompletePlace.geometry) {
						// Default location overwritten by user
						data.wpda_user_latitude = autocompletePlace.geometry.location.lat();
						data.wpda_user_longitude = autocompletePlace.geometry.location.lng();
					}
					// console.log(data);

					// Execute query
					jQuery.ajax({
						type: "POST",
						url: wpAjaxUrl + "?action=wpdapro_geolocation_get_data",
						data: data
					}).done(
						function(data) {
							// console.log(data);
							bounds = new google.maps.LatLngBounds();
							if ("<?php echo $settings_object->geolocation_settings->marker; ?>"=="1") {
								let userLatitude = home.lat;
								let userLongitude = home.lng;

								if (autocompletePlace!==null && autocompletePlace.geometry) {
									// Default location overwritten by user
									userLatitude = autocompletePlace.geometry.location.lat();
									userLongitude = autocompletePlace.geometry.location.lng();

									markerHtml = `
										<div>
											<h6>${autocompletePlace.name}</h6>
											<p>${autocompletePlace.formatted_address}</p>
										</div>
									`;
									addMarker(autocompletePlace.geometry.location, autocompletePlace.name, markerHtml, false);
								} else {
									addMarker(
										home,
										"<?php echo $user_location ? "Your location" : $settings_object->geolocation_settings->marker_title; ?>",
										"<?php echo $user_location ? "Your location" : $marker_info; ?>",
										false
									);
								}

								var addPlace = new google.maps.LatLng(
									userLatitude,
									userLongitude
								);
								bounds.extend(addPlace);
							}
							// Add markers
							for (var i=0; i<data.data.length; i++) {
								if (i===0 && "<?php echo esc_attr( $map_center ); ?>"==="first") {
									map.setCenter({
										lat: data.data[i]["<?php echo esc_attr( $settings_object->geolocation_settings->latitude ); ?>"],
										lng: data.data[i]["<?php echo esc_attr( $settings_object->geolocation_settings->longitude ); ?>"]
									});
								}
								markerPosition = {
									lat: data.data[i]["<?php echo esc_attr( $settings_object->geolocation_settings->latitude ); ?>"],
									lng: data.data[i]["<?php echo esc_attr( $settings_object->geolocation_settings->longitude ); ?>"]
								};
								markerTitle = renderMarkerText(
									"<?php echo $settings_object->geolocation_settings->location_marker_title; ?>",
									data.data[i]
								);
								markerInfo = renderMarkerText(
									"<?php echo str_replace( "\n", '', $settings_object->geolocation_settings->location_marker_info ); ?>",
									data.data[i]
								);
								addMarker(markerPosition, markerTitle, markerInfo);

								if (autoZoom) {
									var addPlace = new google.maps.LatLng(
										data.data[i]["<?php echo esc_attr( $settings_object->geolocation_settings->latitude ); ?>"],
										data.data[i]["<?php echo esc_attr( $settings_object->geolocation_settings->longitude ); ?>"]
									);
									bounds.extend(addPlace);
								}
							}
							wpdaproGeolocationRefreshMap();
						}
					);
				}

				function wpdaproGeolocationRefreshMap() {
					if (autoZoom) {
						map.fitBounds(bounds);
						map.panToBounds(bounds);

						if (markers.length<2) {
							map.setZoom(<?php echo esc_attr( $settings_object->geolocation_settings->zoom ); ?>);
						}
					}
				}

				function wpdaproGeolocationInit() {
					markerPopup = new google.maps.InfoWindow();
					if ("<?php echo esc_attr( $user_location ); ?>") {
						wpdaproGetUserLocation(setUserPosition);
					} else {
						wpdaproGeolocationCreate();
					}

					if (jQuery("#map_search").length>0) {
						// Add autocomplete
						wpdaproGeoSearchAutocomplete();
					}
				}

				function wpdaproGeoSearchAutocomplete() {
					const options = {
						fields: ["formatted_address", "geometry", "name"],
						strictBounds: false,
						types: ["establishment"],
					};
					const input = document.getElementById("map_search");

					autocomplete = new google.maps.places.Autocomplete(input, options);
					autocomplete.addListener("place_changed", function() {
						autocompletePlace = autocomplete.getPlace();
						deleteAllMarkers();
						wpdaproGetGeoData();
					});
				}

				function wpdaproGeolocationCreate() {
					let mapTypeId = google.maps.MapTypeId.ROADMAP;
					if (startMode==="satellite") {
						mapTypeId = google.maps.MapTypeId.SATELLITE;
					} else if (startMode==="hybrid") {
						mapTypeId = google.maps.MapTypeId.HYBRID;
					} else if (startMode==="terrain") {
						mapTypeId = google.maps.MapTypeId.TERRAIN;
					}

					map = new google.maps.Map(
						document.getElementById("wpdapro_geolocation_home"),
						{
							zoom: <?php echo esc_attr( $current_zoom ); ?>,
							center: home,
							mapTypeId: mapTypeId
						}
					);

					if ("false"!=="<?php echo esc_attr( $map_init ); ?>") {
						wpdaproGetGeoData();
					} else {
						args = {};
						args.radius = 0;
						wpdaproGetGeoData(args);
					}

					recalculateMapHeight();
				}

				function recalculateMapHeight() {
					// Recalculate map height
					jQuery("#wpdapro_geolocation_home").css("height", "calc(100% - "+jQuery(".wpdapro_geolocation_selection").height()+"px)");
				}

				jQuery(function() {
					jQuery("#wpdapro_geolocation_selection_range").on("change", function() {
						deleteAllMarkers();
						wpdaproGetGeoData();
					});

					jQuery("#wpdapro_geolocation_selection_range_unit").on("change", function() {
						deleteAllMarkers();
						wpdaproGetGeoData();
					});
				});
			</script>
			<div class="wpdapro_geolocation_container">
				<?php
				if ( 'hide' == $map_select ) {
					$allow_selection = false;
				} elseif ( 'show' == $map_select ) {
					$allow_selection = true;
				} else {
					$allow_selection = 'show' === $settings_object->geolocation_settings->unit_select;
				}
				if ( $allow_selection ) {
					if ( 'user' === $settings_object->geolocation_settings->radius_type ) {
						// Allow user to change search radius
						?>
						<div class="wpdapro_geolocation_selection">
							<?php
							$this->add_location_search_box( $map_search );
							?>
							<span class="wpdapro_geolocation_range_selection">
								<label><?php echo __( 'Range', 'wp-data-access' ); ?></label>
								<?php
								if ( 'user' === $settings_object->geolocation_settings->radius_type ) {
									$radius = explode( ',', $settings_object->geolocation_settings->radius_csv );
									echo '<select id="wpdapro_geolocation_selection_range">';
									for ($i=0; $i<sizeof($radius); $i++) {
										$radius_value = esc_attr( $radius[$i] );
										$selected     = $settings_object->geolocation_settings->radius === $radius_value ? ' selected' : '';
										echo "<option value='{$radius_value}'{$selected}>{$radius_value}</option>";
									}
									echo '</select>';
								}
								?>
								<select id="wpdapro_geolocation_selection_range_unit">
									<option value="km" <?php echo 'mile' !== $settings_object->geolocation_settings->unit ? 'selected' : ''; ?> >Kilometer</option>
									<option value="mile" <?php echo 'mile' === $settings_object->geolocation_settings->unit ? 'selected' : ''; ?> >Mile</option>
								</select>
							</span>
						</div>
						<?php
					} else {
						$this->add_hidden_radius_fields( $settings_object );
						?>
						<div class="wpdapro_geolocation_selection">
							<?php
							$this->add_location_search_box( $map_search );
							?>
							<span>
								<?php echo __( 'Range', 'wp-data-access' ); ?>
								<?php echo esc_attr( $settings_object->geolocation_settings->radius ); ?>
								<?php echo esc_attr( $settings_object->geolocation_settings->unit ); ?>
							</span>
						</div>
						<?php
					}
				} else {
					$this->add_hidden_radius_fields( $settings_object );
					if ( 'true' === $map_search ) {
						?>
							<div class="wpdapro_geolocation_selection">
								<?php
								$this->add_location_search_box( $map_search );
								?>
							</div>
							<?php
					}
				}
				?>
				<div id="wpdapro_geolocation_home"></div>
			</div>
			<script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo $settings_object->geolocation_settings->google_maps_api_key; ?>&libraries=places&callback=wpdaproGeolocationInit"></script>
			<?php

			return ob_get_clean();
		}

		private function add_location_search_box( $map_search ) {
			if ( 'true' === $map_search ) {
				$map_search_label = __( 'Your location', 'wp-data-access' );
				echo "
									<span class='wpdapro_geolocation_selection_search'>
										<label for='map_search'>{$map_search_label}</label>
										<input type='text' id='map_search' />
									</span>
								";
			}
		}

		private function add_hidden_radius_fields( $settings_object ) {
			?>
			<div style="display: none">
				<input type="text"
					   id="wpdapro_geolocation_selection_range"
					   value="<?php echo esc_attr( $settings_object->geolocation_settings->radius ); ?>"
				/>
				<input type="text"
					   id="wpdapro_geolocation_selection_range_unit"
					   value="<?php echo esc_attr( $settings_object->geolocation_settings->unit ); ?>"
				/>
			</div>
			<?php
		}

	}

}