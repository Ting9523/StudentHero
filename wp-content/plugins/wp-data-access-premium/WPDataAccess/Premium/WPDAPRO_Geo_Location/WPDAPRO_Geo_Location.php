<?php

namespace WPDataAccess\Premium\WPDAPRO_Geo_Location {

	use WPDataAccess\Connection\WPDADB;
	use WPDataAccess\Data_Dictionary\WPDA_Dictionary_Lists;
	use WPDataAccess\Plugin_Table_Models\WPDA_Table_Settings_Model;
	use WPDataAccess\Data_Dictionary\WPDA_List_Columns;

	class WPDAPRO_Geo_Location {

		public static function add_geolocation_settings( $schema_name, $table_name, $table_structure ) {
			// Get table columns to fill listboxes
			$columns = WPDA_Dictionary_Lists::get_table_columns( $table_name, $schema_name );

			// Values used to create forms
			$e_page        = esc_attr( \WP_Data_Access_Admin::PAGE_MAIN );
			$e_schema_name = esc_attr( $schema_name );
			$e_table_name  = esc_attr( $table_name );

			// Add submit form
			$wp_nonce_action_settings     = "wpda-settings-{$e_table_name}";
			$wp_nonce_settings            = wp_create_nonce( $wp_nonce_action_settings );
			$settings_table_form_id       = 'gelocation_settings_table_form_' . $e_table_name;
			$settings_table_form_settings = 'gelocation_settings_table_form_settings_' . $e_table_name;
			$settings_table_form          =
				"<form id='{$settings_table_form_id}' action='?page={$e_page}' method='post'>" .
				"<input type='hidden' name='action' value='settings-table' />" .
				"<input type='hidden' name='settings_table_name' value='{$e_table_name}' />" .
				"<input type='hidden' name='settings' id='{$settings_table_form_settings}' value='' />" .
				"<input type='hidden' name='_wpnonce' value='{$wp_nonce_settings}' />" .
			    "</form>";

			// Add batch update form
			$wp_nonce_action_batch   = "wpdapro-geo-get-data-{$e_table_name}";
			$wp_nonce_batch          = wp_create_nonce( $wp_nonce_action_batch );
			$batch_table_form_id     = 'gelocation_batch_table_form_' . $e_table_name;
			$batch_update_table_form =
				"<form id='{$batch_table_form_id}' action='?page={$e_page}' method='post' target='_blank'>" .
			    "<input type='hidden' name='page_action' value='geolocation_batch_update' />" .
			    "<input type='hidden' name='wpdaschema_name' value='{$e_schema_name}' />" .
			    "<input type='hidden' name='table_name' value='{$e_table_name}' />" .
				"<input type='hidden' name='_wpnonce' value='{$wp_nonce_batch}' />" .
			    "</form>";

			// Geolocation defaults
			$google_maps_api_key = '';
			$address_target = [];
			$latitude = '';
			$longitude = '';
			$type = '';
			$viewport = '';
			$initial_location = 'fixed';
			$initial_lat = '';
			$initial_lng = '';
			$marker = false;
			$marker_title = '';
			$marker_info = '';
			$unit = 'km';
			$unit_select = 'hide';
			$radius = 10;
			$radius_csv = '1,3,5,10,15,25,50,75,100';
			$radius_type = 'fixed';
			$start_mode = 'map';
			$width = 400;
			$width_type = "px";
			$height = 490;
			$height_type = "px";
			$zoom = 14;
			$auto_zoom = true;
			$location_marker_title = '';
			$location_marker_info = '';
			$location_marker_max = 50;
			$status = '';

			// Get geolocation settings
			$settings_db = WPDA_Table_Settings_Model::query( $table_name, $schema_name );
			$geolocation = null;
			if ( isset( $settings_db[0]['wpda_table_settings'] ) ) {
				$sql_dml = 'UPDATE';
				$settings_object = json_decode( $settings_db[0]['wpda_table_settings'] );
				if ( isset( $settings_object->geolocation_settings->google_maps_api_key ) ) {
					$google_maps_api_key = $settings_object->geolocation_settings->google_maps_api_key;
				}
				if ( isset( $settings_object->geolocation_settings->address_target ) ) {
					$address_target = $settings_object->geolocation_settings->address_target;
				}
				if ( isset( $settings_object->geolocation_settings->latitude ) ) {
					$latitude = $settings_object->geolocation_settings->latitude;
				}
				if ( isset( $settings_object->geolocation_settings->longitude ) ) {
					$longitude = $settings_object->geolocation_settings->longitude;
				}
				if ( isset( $settings_object->geolocation_settings->type ) ) {
					$type = $settings_object->geolocation_settings->type;
				}
				if ( isset( $settings_object->geolocation_settings->viewport ) ) {
					$viewport = $settings_object->geolocation_settings->viewport;
				}
				if ( isset( $settings_object->geolocation_settings->initial_location ) ) {
					$initial_location = $settings_object->geolocation_settings->initial_location;
				}
				if ( isset( $settings_object->geolocation_settings->initial_lat ) ) {
					$initial_lat = $settings_object->geolocation_settings->initial_lat;
				}
				if ( isset( $settings_object->geolocation_settings->initial_lng ) ) {
					$initial_lng = $settings_object->geolocation_settings->initial_lng;
				}
				if ( isset( $settings_object->geolocation_settings->marker ) ) {
					$marker = $settings_object->geolocation_settings->marker;
				}
				if ( isset( $settings_object->geolocation_settings->marker_title ) ) {
					$marker_title = $settings_object->geolocation_settings->marker_title;
				}
				if ( isset( $settings_object->geolocation_settings->marker_info ) ) {
					$marker_info = $settings_object->geolocation_settings->marker_info;
				}
				if ( isset( $settings_object->geolocation_settings->unit ) ) {
					$unit = $settings_object->geolocation_settings->unit;
				}
				if ( isset( $settings_object->geolocation_settings->unit_select ) ) {
					$unit_select = $settings_object->geolocation_settings->unit_select;
				}
				if ( isset( $settings_object->geolocation_settings->radius ) ) {
					$radius = $settings_object->geolocation_settings->radius;
				}
				if ( isset( $settings_object->geolocation_settings->radius_csv ) ) {
					$radius_csv = $settings_object->geolocation_settings->radius_csv;
				}
				if ( isset( $settings_object->geolocation_settings->radius_type ) ) {
					$radius_type = $settings_object->geolocation_settings->radius_type;
				}
				if ( isset( $settings_object->geolocation_settings->start_mode ) ) {
					$start_mode = $settings_object->geolocation_settings->start_mode;
				}
				if ( isset( $settings_object->geolocation_settings->width ) ) {
					$width = $settings_object->geolocation_settings->width;
				}
				if ( isset( $settings_object->geolocation_settings->width_type ) ) {
					$width_type = $settings_object->geolocation_settings->width_type;
				}
				if ( isset( $settings_object->geolocation_settings->height ) ) {
					$height = $settings_object->geolocation_settings->height;
				}
				if ( isset( $settings_object->geolocation_settings->height_type ) ) {
					$height_type = $settings_object->geolocation_settings->height_type;
				}
				if ( isset( $settings_object->geolocation_settings->zoom ) ) {
					$zoom = $settings_object->geolocation_settings->zoom;
				}
				if ( isset( $settings_object->geolocation_settings->auto_zoom ) ) {
					$auto_zoom = $settings_object->geolocation_settings->auto_zoom;
				}
				if ( isset( $settings_object->geolocation_settings->location_marker_title ) ) {
					$location_marker_title = $settings_object->geolocation_settings->location_marker_title;
				}
				if ( isset( $settings_object->geolocation_settings->location_marker_info ) ) {
					$location_marker_info = $settings_object->geolocation_settings->location_marker_info;
				}
				if ( isset( $settings_object->geolocation_settings->location_marker_max ) ) {
					$location_marker_max = $settings_object->geolocation_settings->location_marker_max;
				}
				if ( isset( $settings_object->geolocation_settings->status ) ) {
					$status = $settings_object->geolocation_settings->status;
				}
			} else {
				$sql_dml = 'INSERT';
			}
			?>
			<li>
				<span class="wpda_table_settings_caret"><?php echo __( 'Geolocation Settings', 'wp-data-access' ); ?></span>
				<a href="https://wpdataaccess.com/docs/documentation/data-explorer/geolocation-settings/" target="_blank">
						<span class="dashicons dashicons-editor-help wpda_tooltip"
							  title="<?php echo __( 'Define geolocation columns [help opens in a new tab or window]', 'wp-data-access' ); ?>"
							  style="cursor:pointer;"></span>
				</a>
				<ul class="wpda_table_settings_nested wpda_action_font wpda_geolocation_settings">
					<style type="text/css">
                        .<?php echo esc_attr( $table_name ); ?>_wpda_geolocation_container {
                            height: <?php echo esc_attr( $height . $height_type ); ?>;
							width: <?php echo esc_attr( $width . $width_type ); ?>;
                        }
                        #<?php echo esc_attr( $table_name ); ?>_wpda_geolocation_home {
                            width: 100%;
                            height: 100%;
                            border: 1px solid lightgrey;
                        }
						.wpda_geolocation_settings label {
							display: inline-block;
							padding-top: 6px;
							width: 125px;
						}
						.wpda_geolocation_settings input,
						.wpda_geolocation_settings select,
                        .wpda_geolocation_settings textarea {
							font-size: 90% !important;
						}
						.wpda_geolocation_settings select {
							padding-top: 4px;
						}
						fieldset.wpdp_fieldset legend {
							margin: 0;
						}
						.wpda_geolocation_settings_line {
							display: flex;
						}
						.wpda_geolocation_settings_icon {
							cursor: pointer;
							vertical-align: top;
							padding-top: 6px;
						}
						select[multiple].wpdapro_goelocation_address_source,
						select[multiple].wpdapro_goelocation_address_target {
							height: 155px;
							min-width: 200px;
						}
						#<?php echo esc_attr( $table_name ); ?>_wpdapro_geolocation_preview {
							float: right;
							padding-left: 10px;
							display: none;
						}
						.wpda_geolocation_settings_half {
							display: inline-block;
							width: 330px;
                            float: left
                        }
						.wpda_geolocation_settings_radio {
							display: inline-block;
							padding-left: 20px;
						}
						.wpda_div_nowrap {
							white-space: nowrap;
						}
						.wpda_radio_horizontal {
							width: auto !important;
                            padding: 15px 10px 10px 0 !important;

						}
					</style>
					<script type="text/javascript">
						var <?php echo esc_attr( $table_name ); ?>_map = null;
						var <?php echo esc_attr( $table_name ); ?>_home = null;
						<?php if ( '' !== $initial_lat && '' !== $initial_lng ) { ?>
						<?php echo esc_attr( $table_name ); ?>_home = {
							lat: <?php echo esc_attr( $initial_lat ); ?>,
							lng: <?php echo esc_attr( $initial_lng ); ?>
						};
						<?php } ?>
						var isLoaded = false;
						var coords = null;

						function wpdaproInitGoogleMaps() {
							isLoaded = true;
						}

						function wpdaproGetUserLocation(callback = null) {
							if (!navigator.geolocation) {
								console.log("Geo location is not supported by your browser");
							} else {
								navigator.geolocation.getCurrentPosition(
									function(pos) {
										coords = pos.coords;
										if (callback!==null) {
											callback();
										}
									},
									function(err) {
										coords = null;
										console.log("ERROR - " + err);
									},
									{
										enableHighAccuracy: true,
										timeout: 5000,
										maximumAge: 0
									}
								);
							}
						}

						if ("<?php echo esc_attr( $initial_location ); ?>"==="user") {
							jQuery(function () {
								wpdaproGetUserLocation(<?php echo esc_attr( $table_name ); ?>_googleMapsSetCenter);
							});
						}

						function <?php echo esc_attr( $table_name ); ?>_googleMapsSetCenter() {
							if ("<?php echo esc_attr( $initial_location ); ?>"=="user") {
								if (coords!==undefined && coords!==null) {
									<?php echo esc_attr( $table_name ); ?>_home = {
										lat: coords.latitude,
										lng: coords.longitude
									};
								}
							}
						}

						function <?php echo esc_attr( $table_name ); ?>_googleMapsTest(listener) {
							if (!isLoaded) {
								alert("INFO - Google Maps not yet loaded! Please try again in a few seconds. Check the console for errors if the problem remains.");
								return;
							}

							if (<?php echo esc_attr( $table_name ); ?>_home==null) {
								alert("ERROR - No location available!");
								return;
							}

							<?php echo esc_attr( $table_name ); ?>_map = new google.maps.Map(
								document.getElementById("<?php echo esc_attr( $table_name ); ?>_wpda_geolocation_home"),
								{
									zoom: <?php echo esc_attr( $zoom ); ?>,
									center: <?php echo esc_attr( $table_name ); ?>_home
								}
							);

							function addMarker(position, title, info) {
								var markerArgs = {
									map: <?php echo esc_attr( $table_name ); ?>_map,
									position: position
								};

								if (title!=="") {
									markerArgs.title = title;
								}

								var marker = new google.maps.Marker(markerArgs);

								if (info!=="") {
									var infowindow = new google.maps.InfoWindow({
										content: info,
									});

									marker.addListener("click", () => {
										infowindow.open(<?php echo esc_attr( $table_name ); ?>_map, marker);
									});
								}
							}

							if ("<?php echo esc_attr( $marker ); ?>"=="1") {
								// Add marker for initial location
								addMarker(<?php echo esc_attr( $table_name ); ?>_home, "<?php echo esc_attr( $marker_title ); ?>", "<?php echo str_replace( "\n", '', $marker_info ); ?>");
							}
						}

						function <?php echo esc_attr( $table_name ); ?>_resetMap() {
							// Reset zoom
							zoom = parseInt(jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_zoom").val());
							<?php echo esc_attr( $table_name ); ?>_map.setZoom(zoom);
							jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_zoom_label").html("Initial zoom (" + zoom + ")");

							// Reset width
							jQuery(".<?php echo esc_attr( $table_name ); ?>_wpda_geolocation_container").css(
								"width",
								jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_width").val() +
								jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_width_type").val()
							);

							// Reset height
							jQuery(".<?php echo esc_attr( $table_name ); ?>_wpda_geolocation_container").css(
								"height",
								jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_height").val() +
								jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_height_type").val()
							);
						}

						jQuery(
							function() {
								// Add submit form to page
								jQuery("#wpda_invisible_container").append("<?php echo $settings_table_form; ?>");
								jQuery("#wpda_invisible_container").append("<?php echo $batch_update_table_form; ?>");
								// Source listbox
								jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_address_source").on("click", function() {
									value = jQuery(this).val();
									if (value.length>0) {
										text = jQuery(this).find(":selected").text();
										jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_address_target").append(new Option(text, value));
										jQuery(this).find(":selected").remove();
										jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_address_target option[value='*']").remove();
									}
								});

								// Target listbox
								jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_address_target").on("click", function() {
									value = jQuery(this).val();
									if (value.length>0 && value[0]!=="*") {
										text = jQuery(this).find(":selected").text();
										jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_address_source").append(new Option(text, value));
										jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_address_source").animate(
											{scrollTop: jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_address_source")[0].scrollHeight},
											100
										);
										jQuery(this).find(":selected").remove();
										if (jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_address_target option").length===0) {
											jQuery(this).append(new Option("Select columns from list", "*"));
										}
									}
								});

								// Submit form
								jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_save_geolocation_settings").on("click", function() {
									// Validate form data
									if (jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_status").val()==='enabled') {
										if (jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_key").val()==='') {
											alert("Error: Missing Google Maps API key\n\nAction: Add Google Maps API key or change status to disabled");
											return false;
										}
										address_target_options = jQuery.map(jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_address_target option") ,function(option) {
											return option.value;
										});
										if (address_target_options.length===1 && address_target_options[0]==="*") {
											address_target_options = [];
										}
										if (address_target_options.length===0) {
											alert("Error: No address lookup defined\n\nAction: Add address lookup columns or change status to disabled");
											return false;
										}
										if (jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_lat").val()==='') {
											alert("Error: Latitude column must be entered\n\nAction: Add latitude or change status to disabled");
											return false;
										}
										if (jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_lng").val()==='') {
											alert("Error: Longitude column must be entered\n\nAction: Add longitude or change status to disabled");
											return false;
										}

										if (jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_initial_location_lat").val()==='') {
											alert("Error: Initial location latitude must be entered\n\nAction: Add initial location latitude or change status to disabled");
											return false;
										}
										if (jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_initial_location_lng").val()==='') {
											alert("Error: Initial location longitude column must be entered\n\nAction: Add initial location longitude or change status to disabled");
											return false;
										}
									}

									// Prepare geolocation data
									geolocation_settings = {};
									geolocation_settings["google_maps_api_key"] = jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_key").val();
									geolocation_settings["address_target"] =
										jQuery.map(jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_address_target option") ,function(option) {
											return option.value;
										});
									geolocation_settings["latitude"] = jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_lat").val();
									geolocation_settings["longitude"] = jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_lng").val();
									geolocation_settings["type"] = jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_type").val();
									geolocation_settings["viewport"] = jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_viewport").val();
									geolocation_settings["initial_lat"] = jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_initial_location_lat").val();
									geolocation_settings["initial_lng"] = jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_initial_location_lng").val();
									if (jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_initial_location_user").is(":checked")) {
										geolocation_settings["initial_location"] = "user";
									} else {
										geolocation_settings["initial_location"] = "fixed";
									}
									geolocation_settings["marker"] = jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_marker").is(":checked");
									geolocation_settings["marker_title"] = jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_marker_title").val();
									geolocation_settings["marker_info"] = jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_marker_info").val();
									geolocation_settings["unit"] = jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_unit").val();
									if (jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_unit_select_show").is(":checked")) {
										geolocation_settings["unit_select"] = "show";
									} else {
										geolocation_settings["unit_select"] = "hide";
									}
									if (''===jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_radius").val().trim()) {
										alert("Search radius must be entered");
										return;
									}
									geolocation_settings["radius"] = jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_radius").val();
									// Check if radius_csv is a csv of integers
									radius_csv = jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_radius_csv").val();
									radius_csv_arr = radius_csv.split(",");
									for (var i=0; i<radius_csv_arr.length; i++) {
										if (!Number.isInteger(+radius_csv_arr[i])) {
											alert("Column search radius must be a comma separated list of integers");
											return;
										}
									}
									geolocation_settings["radius_csv"] = jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_radius_csv").val();
									if (jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_radius_type_fixed").is(":checked")) {
										geolocation_settings["radius_type"] = "fixed";
									} else {
										geolocation_settings["radius_type"] = "user";
									}
									geolocation_settings["start_mode"] = jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_start_mode").val();
									geolocation_settings["width"] = jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_width").val();
									geolocation_settings["width_type"] = jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_width_type").val();
									geolocation_settings["height"] = jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_height").val();
									geolocation_settings["height_type"] = jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_height_type").val();
									geolocation_settings["zoom"] = jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_zoom").val();
									geolocation_settings["auto_zoom"] = jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_auto_zoom").is(":checked");
									geolocation_settings["location_marker_title"] = jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_location_marker_title").val();
									geolocation_settings["location_marker_info"] = jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_location_marker_info").val();
									geolocation_settings["location_marker_max"] = jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_location_marker_max").val();
									geolocation_settings["status"] = jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_status").val();

									// Prepare meta data
									unused = {};
									unused["sql_dml"] = "<?php echo esc_attr( $sql_dml ); ?>";

									// Prepare JSON string
									jsonData = {};
									jsonData["request_type"] = "column_settings";
									jsonData["geolocation_settings"] = geolocation_settings;
									jsonData["unused"] = unused;

									// Submit form
									// console.log(jsonData);
									jQuery("#<?php echo esc_attr( $settings_table_form_settings ); ?>").val(JSON.stringify(jsonData));
									jQuery("#<?php echo esc_attr( $settings_table_form_id ); ?>").submit();

									return false;
								});

								// Cancel form
								jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_cancel_geolocation_settings").on("click", function() {
									jQuery("#wpda_admin_menu_actions_<?php echo esc_attr( $_REQUEST["rownum"] ); ?>").toggle();
									wpda_toggle_row_actions('<?php echo esc_attr( $_REQUEST["rownum"] ); ?>');
								});

								// Start batch update
								jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_save_geolocation_batch_update").on("click", function() {
									if (confirm('Start batch update? [open in a new tab or window]')) {
										jQuery("#<?php echo esc_attr( $batch_table_form_id ); ?>").submit();
									}
								});

								// Toggle preview
								if ("<?php echo esc_attr( $status ); ?>"==="enabled") {
									jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_geolocation_settings_preview").on("click", function() {
										<?php echo esc_attr( $table_name ); ?>_googleMapsTest();
										jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_geolocation_preview").toggle();
										if (jQuery("#<?php echo esc_attr( $table_name ); ?>_wpdapro_geolocation_preview").is(":visible")) {
											jQuery(this).find("span").html("visibility_off");
										} else {
											jQuery(this).find("span").html("visibility");
										}
									});
								}
							}
						);
					</script>
					<div id="<?php echo esc_attr( $table_name ); ?>_wpdapro_geolocation_preview">
						<fieldset class="wpda_fieldset wpdp_fieldset">
							<legend>
								Google Maps
							</legend>
							<?php
							if ( $google_maps_api_key !== '' ) {
								?>
								<div class="<?php echo esc_attr( $table_name ); ?>_wpda_geolocation_container">
									<div id="<?php echo esc_attr( $table_name ); ?>_wpda_geolocation_home"></div>
								</div>
								<?php
							}
							?>
						</fieldset>
					</div>
					<fieldset class="wpda_fieldset wpdp_fieldset">
						<legend>
							Google Maps
						</legend>
						<div class="wpda_geolocation_settings_line">
							<label for="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_key">
								API key
							</label>
							<input type="text"
								   id="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_key"
								   value="<?php echo $google_maps_api_key; ?>"/>
							<span class="dashicons dashicons-editor-help wpda_tooltip wpda_geolocation_settings_icon"
								  title="Enter a valid Google Maps API key"></span>
						</div>
					</fieldset>
					<fieldset class="wpda_fieldset wpdp_fieldset">
						<legend>
							Address lookup
						</legend>
						<div class="wpda_geolocation_settings_line">
							<label for="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_address_source">
								Select columns
							</label>
							<span>
								<select id="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_address_source"
										class="wpdapro_goelocation_address_source"
										multiple="multiple">
									<?php
									foreach ( $columns as $column ) {
										if ( ! in_array( $column['column_name'], $address_target ) ) {
											echo "<option value='{$column['column_name']}'>{$column['column_name']}</option>";
										}
									}
									?>
								</select>
								<select id="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_address_target"
										class="wpdapro_goelocation_address_target"
										multiple="multiple">
									<?php
									if ( sizeof( $address_target ) === 0 ) {
										echo '<option value="*">Select columns from list</option>';
									}
									foreach ( $address_target as $address ) {
										echo "<option value='{$address}'>{$address}</option>";
									}
									?>
								</select>
							</span>
							<span class="dashicons dashicons-editor-help wpda_tooltip wpda_geolocation_settings_icon"
								  title="Add columns from list to be used in address lookup (for example: country + state + city + zipcode + address)"></span>
						</div>
					</fieldset>
					<fieldset class="wpda_fieldset wpdp_fieldset">
						<legend>
							Column Mapping
						</legend>
						<span class="wpda_geolocation_settings_half">
							<div class="wpda_div_nowrap">
								<label for="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_lat">
									Latitude column
								</label>
								<select id="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_lat">
									<option value="">** mandatory **</option>
									<?php
									foreach ( $columns as $column ) {
										$selected = $column['column_name'] === $latitude ? 'selected' : '';
										echo "<option value='{$column['column_name']}' {$selected}>{$column['column_name']}</option>";
									}
									?>
								</select>
								<span class="dashicons dashicons-editor-help wpda_tooltip wpda_geolocation_settings_icon"
									  title="The latitude will be stored in this column (must be entered)"></span>
							</div>
							<div class="wpda_div_nowrap">
								<label for="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_lng">
									Longitude column
								</label>
								<select id="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_lng">
									<option value="">** mandatory **</option>
									<?php
									foreach ( $columns as $column ) {
										$selected = $column['column_name'] === $longitude ? 'selected' : '';
										echo "<option value='{$column['column_name']}' {$selected}>{$column['column_name']}</option>";
									}
									?>
								</select>
								<span class="dashicons dashicons-editor-help wpda_tooltip wpda_geolocation_settings_icon"
									  title="The longitude will be stored in this column (must be entered)"></span>
							</div>
						</span>
						<span class="wpda_geolocation_settings_half">
							<div class="wpda_div_nowrap">
								<label for="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_type">
									Location type
								</label>
								<select id="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_type">
									<option value="">-- not saved --</option>
									<?php
									foreach ( $columns as $column ) {
										$selected = $column['column_name'] === $type ? 'selected' : '';
										echo "<option value='{$column['column_name']}' $selected>{$column['column_name']}</option>";
									}
									?>
								</select>
								<span class="dashicons dashicons-editor-help wpda_tooltip wpda_geolocation_settings_icon"
									  title="The location type will be stored in this column (select -- not saved -- to skip)"></span>
							</div>
							<div class="wpda_div_nowrap">
								<label for="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_viewport">
									Viewport
								</label>
								<select id="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_viewport">
									<option value="">-- not saved --</option>
									<?php
									foreach ( $columns as $column ) {
										$selected = $column['column_name'] === $viewport ? 'selected' : '';
										echo "<option value='{$column['column_name']}' $selected>{$column['column_name']}</option>";
									}
									?>
								</select>
								<span class="dashicons dashicons-editor-help wpda_tooltip wpda_geolocation_settings_icon"
									  title="The viewport will be stored in this column (select -- not saved -- to skip)"></span>
							</div>
						</span>
					</fieldset>
					<fieldset class="wpda_fieldset wpdp_fieldset">
						<legend>
							Initial location
						</legend>
						<div class="wpda_geolocation_settings_half">
							<label>
								Latitude
							</label>
							<input type="number"
								   id="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_initial_location_lat"
								   value="<?php echo esc_attr( $initial_lat ); ?>"
								   placeholder="latitude"
								   step="0.0000001"
							/>
							<br/>
							<label>
								Longitude
							</label>
							<input type="number"
								   id="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_initial_location_lng"
								   value="<?php echo esc_attr( $initial_lng ); ?>"
								   placeholder="longitude"
								   step="0.0000001"
							/>
							<br/>
							<label>
								Use
							</label>
							<label>
								<input type="radio"
									   id="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_initial_location_user"
									   name="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_initial_location"
									   value="user"
									   <?php echo 'user' === $initial_location ? 'checked' : ''; ?>
								/>
								User location
							</label>
							<br/>
							<label></label>
							<label>
								<input type="radio"
									   id="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_initial_location_fixed"
									   name="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_initial_location"
									   value="fixed"
									   <?php echo 'fixed' === $initial_location ? 'checked' : ''; ?>
								/>
								Static location
							</label>
						</div>
						<div class="wpda_geolocation_settings_half">
							<div class="wpda_div_nowrap">
								<label>
									Home marker
								</label>
								<label>
									<input type="checkbox"
										   id="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_marker"
										   <?php echo $marker ? 'checked' : ''; ?>
									/>
									Add marker
								</label>
							</div>
							<div class="wpda_div_nowrap">
								<label>
									Marker title
								</label>
								<input type="text"
									   id="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_marker_title"
									   value="<?php echo esc_attr( $marker_title ); ?>"
								/>
							</div>
							<div class="wpda_div_nowrap">
								<label>
									Marker popup info
								</label>
								<textarea id="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_marker_info"
										  style="vertical-align: top; padding: 0.8em; width: 172px; resize: both"
								><?php echo esc_attr( $marker_info ); ?></textarea>
							</div>
						</div>
					</fieldset>
					<fieldset class="wpda_fieldset wpdp_fieldset">
						<legend>
							User Interaction
						</legend>
						<div class="wpda_geolocation_settings_half">
							<div class="wpda_div_nowrap">
								<label>
									Distance in
								</label>
								<select id="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_unit">
									<option value="km" <?php echo 'mile' !== $unit ? 'selected' : ''; ?> >Kilometer</option>
									<option value="mile" <?php echo 'mile' === $unit ? 'selected' : ''; ?> >Mile</option>
								</select>
								<br/>
								<label></label>
								<label class="wpda_radio_horizontal">
									<input type="radio"
										   id="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_unit_select_show"
										   name="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_unit_select"
										   value="show"
										   <?php echo 'show' === $unit_select ? 'checked' : '' ?>
									> Show
								</label>
								<label class="wpda_radio_horizontal">
									<input type="radio"
										   id="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_unit_select_hide"
										   name="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_unit_select"
										   value="hide"
										   <?php echo 'hide' === $unit_select ? 'checked' : '' ?>
									> Hide
								</label>
							</div>
							<div class="wpda_div_nowrap">
								<label>
									Search radius
								</label>
								<input type="radio"
									   id="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_radius_type_fixed"
									   name="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_radius_type"
									   value="fixed"
									   <?php echo 'fixed' === $radius_type ? 'checked' : ''; ?>
								>
								<input type="number"
									   id="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_radius"
									   value="<?php echo esc_attr( $radius ); ?>"
								>
								<span>
									Fixed radius if selected or default if list is selected
								</span>
							</div>
							<div class="wpda_div_nowrap">
								<label></label>
								<input type="radio"
									   id="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_radius_type_user"
									   name="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_radius_type"
									   value="user"
									   <?php echo 'user' === $radius_type ? 'checked' : ''; ?>
								>
								<input type="text"
									   id="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_radius_csv"
									   value="<?php echo esc_attr( $radius_csv ); ?>"
								>
								<span>
									Allow user to select a radius (add comma separated list)
								</span>
							</div>
							<div class="wpda_div_nowrap" style="margin-top: 10px">
								<label>
									Start mode
								</label>
								<select id="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_start_mode">
									<option value="map" <?php echo 'map' === $start_mode ? 'selected' : ''; ?>>Map</option>
									<option value="satellite" <?php echo 'satellite' === $start_mode ? 'selected' : ''; ?>>Satellite</option>
									<option value="hybrid" <?php echo 'hybrid' === $start_mode ? 'selected' : ''; ?>>Hybrid</option>
									<option value="terrain" <?php echo 'terrain' === $start_mode ? 'selected' : ''; ?>>Terrain</option>
								</select>
							</div>
						</div>
					</fieldset>
					<fieldset class="wpda_fieldset wpdp_fieldset">
						<legend>
							Dimensions
						</legend>
						<div class="wpda_geolocation_settings_half">
							<div class="wpda_div_nowrap">
								<label for="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_width">
									Width
								</label>
								<input type="number"
									   id="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_width"
									   value="<?php echo esc_attr( $width ); ?>"
									   onchange="<?php echo esc_attr( $table_name ); ?>_resetMap()"
								/>
								<select id="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_width_type"
										onchange="<?php echo esc_attr( $table_name ); ?>_resetMap()"
								>
									<option <?php echo 'px' === $width_type ? 'selected' : ''; ?> value="px">px</option>
									<option <?php echo 'em' === $width_type ? 'selected' : ''; ?> value="em">em</option>
									<option <?php echo 'pt' === $width_type ? 'selected' : ''; ?> value="pt">pt</option>
								</select>
							</div>
							<div class="wpda_div_nowrap">
								<label for="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_height">
									Height
								</label>
								<input type="number"
									   id="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_height"
									   value="<?php echo esc_attr( $height ); ?>"
									   onchange="<?php echo esc_attr( $table_name ); ?>_resetMap()"
								/>
								<select id="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_height_type"
										onchange="<?php echo esc_attr( $table_name ); ?>_resetMap()"
								>
									<option <?php echo 'px' === $height_type ? 'selected' : ''; ?> value="px">px</option>
									<option <?php echo 'em' === $height_type ? 'selected' : ''; ?> value="em">em</option>
									<option <?php echo 'pt' === $height_type ? 'selected' : ''; ?> value="pt">pt</option>
								</select>
							</div>
							<div class="wpda_div_nowrap">
								<label for="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_zoom"
									   id="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_zoom_label"
									   style="vertical-align: text-bottom">
									Initial zoom (<?php echo esc_attr( $zoom ); ?>)
								</label>
								<input type="range"
									   min="0"
									   max="30"
									   step="1"
									   value="<?php echo esc_attr( $zoom ); ?>"
									   id="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_zoom"
									   style="outline: none; margin-top: 10px"
									   onchange="<?php echo esc_attr( $table_name ); ?>_resetMap()"
								/>
							</div>
							<div class="wpda_div_nowrap">
								<label></label>
								<label>
									<input type="checkbox"
										   id="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_auto_zoom"
										   <?php echo $auto_zoom ? 'checked' : ''; ?>
									/>
									Automatically adjust zoom
								</label>
							</div>
						</div>
					</fieldset>
					<fieldset class="wpda_fieldset wpdp_fieldset">
						<legend>
							Markers
						</legend>
						<div class="wpda_div_nowrap">
							<label>
								Marker title
							</label>
							<input type="text"
								   id="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_location_marker_title"
								   value="<?php echo esc_attr( $location_marker_title ); ?>"
							/>
							<span class="dashicons dashicons-editor-help wpda_tooltip wpda_geolocation_settings_icon"
								  title="To use column values add $$ around the column name. For example:

$$name$$"></span>
						</div>
						<div class="wpda_div_nowrap">
							<label>
								Marker popup info
							</label>
							<textarea id="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_location_marker_info"
									  style="vertical-align: top; padding: 0.8em; width: 172px; resize: both"
							><?php echo esc_attr( $location_marker_info ); ?></textarea>
							<span class="dashicons dashicons-editor-help wpda_tooltip wpda_geolocation_settings_icon"
								  title="To use column values add $$ around the column name. For example:

<h6>$$name$$</h3>
<div>$$address$$</div>"></span>
						</div>
						<div class="wpda_div_nowrap">
							<label>
								Max markers
							</label>
							<input type="number"
								   id="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_location_marker_max"
								   value="<?php echo esc_attr( $location_marker_max ); ?>"
							/>
						</div>
					</fieldset>
					<fieldset class="wpda_fieldset wpdp_fieldset">
						<legend>
							Status
						</legend>
						<label for="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_status">
							Status
						</label>
						<select id="<?php echo esc_attr( $table_name ); ?>_wpdapro_goelocation_status">
							<option value="enabled" <?php echo 'enabled' === $status ? 'selected' : ''; ?>>Enabled</option>
							<option value="disabled" <?php echo 'disabled' === $status ? 'selected' : ''; ?>>Disabled</option>
						</select>
						<span class="dashicons dashicons-editor-help wpda_tooltip wpda_geolocation_settings_icon"
							  title="Enable or disable geolocation for this table"></span>
					</fieldset>
					<div class="wpda-spacer"></div>
					<div>
						<span style="float: right">
							<button type="button"
									id="<?php echo esc_attr( $table_name ); ?>_wpdapro_geolocation_settings_preview"
									class="button button-primary"
									style="font-size: 100%"
									<?php echo 'enabled'!==$status ? 'disabled' : ''; ?>
							>
							<i class="fas fa-eye wpda_icon_on_button"></i>
							<?php echo __( 'Preview', 'wp-data-access' ); ?>
						</button>
						</span>
						<button type="button"
								id="<?php echo esc_attr( $table_name ); ?>_wpdapro_save_geolocation_settings"
								style="font-size: 100%"
								class="button button-primary">
							<i class="fas fa-check wpda_icon_on_button"></i>
							<?php echo __( 'Save Geolocation Settings', 'wp-data-access' ); ?>
						</button>
						<button type="button"
								id="<?php echo esc_attr( $table_name ); ?>_wpdapro_cancel_geolocation_settings"
								style="font-size: 100%"
								class="button button-secondary">
							<i class="fas fa-times-circle wpda_icon_on_button"></i>
							<?php echo __( 'Cancel', 'wp-data-access' ); ?>
						</button>
						<button type="button"
								id="<?php echo esc_attr( $table_name ); ?>_wpdapro_save_geolocation_batch_update"
								style="font-size: 100%"
								class="button button-primary"
								<?php echo 'enabled'!==$status ? 'disabled' : ''; ?>
						>
							<i class="fas fa-cog wpda_icon_on_button"></i>
							<?php echo __( 'Batch update', 'wp-data-access' ); ?>
						</button>
					</div>
				</ul>
			</li>
			<?php
			if ( '' !== $google_maps_api_key ) {
				?>
				<script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo $google_maps_api_key; ?>&callback=wpdaproInitGoogleMaps"></script>
				<?php
			}
		}

		public static function start_geolocation_batch_update() {
			if ( ! isset( $_REQUEST['wpdaschema_name'], $_REQUEST['table_name'] ) ) {
				wp_die( 'ERROR - Invalid arguments' );
			}

			$e_schema_name = sanitize_text_field( wp_unslash( $_REQUEST['wpdaschema_name'] ) ); // input var okay; sanitization okay.
			$e_table_name  = sanitize_text_field( wp_unslash( $_REQUEST['table_name'] ) ); // input var okay; sanitization okay.


			$list_columns = new WPDA_List_Columns( $e_schema_name, $e_table_name );
			$pk_columns   = $list_columns->get_table_primary_key();
			if ( sizeof( $pk_columns ) === 0 ) {
				wp_die( 'ERROR - Base table has no primary key' );
			}

			$settings_db = WPDA_Table_Settings_Model::query( $e_table_name, $e_schema_name );
			$geolocation = null;
			if ( isset( $settings_db[0]['wpda_table_settings'] ) ) {
				$settings_object = json_decode( $settings_db[0]['wpda_table_settings'] );
				if ( isset( $settings_object->geolocation_settings ) ) {
					$geolocation = $settings_object->geolocation_settings;
				}
			}

			if ( empty( $geolocation->google_maps_api_key ) ) {
				wp_die( 'ERROR - No Google Maps API key defined' );
			}

			if ( empty( $geolocation->latitude ) || empty( $geolocation->longitude ) ) {
				wp_die( 'ERROR - Invalid column mapping [missing latitude|longitude]' );
			}

			if ( empty( $geolocation->address_target ) ) {
				wp_die( 'ERROR - Missing address lookup' );
			}

			$wpdadb      = WPDADB::get_db_connection( $e_schema_name );
			$query       = 'select count(*) as norows from `' . str_replace( '`', '', $e_table_name ) . '`';
			$where       = ' where `' . $geolocation->latitude . '` is null or `' . $geolocation->longitude . '` is null';
			$rows_total  = $wpdadb->get_results( $query, 'ARRAY_A' );
			$rows_nomaps = $wpdadb->get_results( "{$query} {$where}", 'ARRAY_A' );

			$no_rows_total  = $rows_total[0]['norows'];
			$no_rows_nomaps = $rows_nomaps[0]['norows'];

			$address_target = implode( ', ', $geolocation->address_target );

			$wp_nonce_action_batch = "wpdapro-geo-get-data-{$e_table_name}";
			$wp_nonce_batch        = wp_create_nonce( $wp_nonce_action_batch );

			$wp_nonce_action_update = "wpdapro-geo-update-{$e_table_name}";
			$wp_nonce_update         = wp_create_nonce( $wp_nonce_action_update );
			?>
			<style type="text/css">
                fieldset.wpdp_fieldset {
					margin-right: 20px;
				}
                fieldset.wpdp_fieldset legend {
                    margin: 0;
                }
                label.wpda_geolocation_batch_label {
					width: 120px;
					display: inline-block;
				}
				#batch_monitor {
					display: none;
				}
			</style>
			<script type="text/javascript">
				var wpAjaxUrl = "<?php echo admin_url('admin-ajax.php'); ?>";

				var primary_key = <?php echo json_encode( $pk_columns ); ?>;
				var geolocation_settings = <?php echo json_encode( $geolocation ); ?>;

				var geolocation_batch_index = 0;
				var geolocation_batch_end = 0;
				var geolocation_batch_step = 2;
				var geolocation_batch_run = true;
				var geolocation_batch_lookup = <?php echo json_encode( $geolocation->address_target ); ?>;
				var geolocation_batch_updated = 0;
				var geolocation_batch_failed = 0;

				function getData() {
					var selection = jQuery("#wpda_geolocation_batch_selection").val();
					if (selection==undefined) {
						selection = "all";
					}
					if (geolocation_batch_run && geolocation_batch_index < geolocation_batch_end) {
						jQuery.ajax({
							type: "POST",
							url: wpAjaxUrl + "?action=wpdapro_geolocation_get_data",
							data: {
								wpda_wpnonce: "<?php echo $wp_nonce_batch;?>",
								wpda_schema_name: "<?php echo $e_schema_name; ?>",
								wpda_table_name: "<?php echo $e_table_name ?>",
								wpda_latitude: "<?php echo esc_attr( $geolocation->latitude ); ?>",
								wpda_longitude: "<?php echo esc_attr( $geolocation->longitude ); ?>",
								wpda_selection: selection,
								wpda_start: geolocation_batch_index,
								wpda_step: geolocation_batch_index + geolocation_batch_step > geolocation_batch_end ? geolocation_batch_end - geolocation_batch_index : geolocation_batch_step
							}
						}).done(
							function(data) {
								for (var i=0; i<data.data.length; i++) {
									if (geolocation_batch_run) {
										pkey = {};
										lookup = "";
										for (var j=0; j<geolocation_batch_lookup.length; j++) {
											for (var k=0; k<primary_key.length; k++) {
												pkey[primary_key[k]] = data.data[i][primary_key[k]];
											}
											lookup += data.data[i][geolocation_batch_lookup[j]];
											if (j<geolocation_batch_lookup.length-1) {
												lookup += ",";
											}
										}
										jQuery("#batch_monitor_row").html(geolocation_batch_index + i + 1);
										jQuery("#batch_monitor_lookup").html(lookup);
										getGeolocation(lookup, pkey);
									}
								}

								geolocation_batch_index += geolocation_batch_step;
								getData();
							}
						);
					}
				}

				function getGeolocation(lookup, pkey) {
					jQuery.ajax({
						type: "POST",
						url: wpAjaxUrl + "?action=wpdapro_update_geolocation",
						data: {
							wpda_wpnonce: "<?php echo $wp_nonce_update; ?>",
							wpda_schema_name: "<?php echo $e_schema_name; ?>",
							wpda_table_name: "<?php echo $e_table_name; ?>",
							wpda_primary_key: pkey,
							wpda_lookup: lookup,
							wpda_geolocation_settings: geolocation_settings
						}
					}).done(
						function(data) {
							if (data.status=="ok") {
								geolocation_batch_updated++;
								jQuery("#batch_monitor_updated").html(geolocation_batch_updated);
							} else {
								geolocation_batch_failed++;
								jQuery("#batch_monitor_failed").html(geolocation_batch_failed);
							}
						}
					).fail(
						function(data) {
							geolocation_batch_failed++;
							jQuery("#batch_monitor_failed").html(geolocation_batch_failed);
						}
					);
				}

				function formSubmit() {
					geolocation_batch_index = 0;
					geolocation_batch_run = true;

					geolocation_batch_end = jQuery("#wpda_geolocation_batch_max_rows").val();

					jQuery("#batch_monitor").show();

					jQuery("#batch_monitor_updated").html(geolocation_batch_updated);
					jQuery("#batch_monitor_failed").html(geolocation_batch_failed);

					getData();
				}

				function formCancel() {
					geolocation_batch_run = false;
				}

				function wpdaproGoogleMapsBatchUpdateInit() {
					jQuery("#button_cancel").prop("disabled", false);
					jQuery("#button_cancel").on("click", formCancel);

					jQuery("#button_start").prop("disabled", false);
					jQuery("#button_start").on("click", formSubmit);

					jQuery("#reset_counters").on("click",
						function() {
							geolocation_batch_updated = 0;
							geolocation_batch_failed = 0;

							jQuery("#batch_monitor_updated").html(geolocation_batch_updated);
							jQuery("#batch_monitor_failed").html(geolocation_batch_failed);
						}
					);
				}
			</script>
			<h1 class="wp-heading-inline">
				<span style="vertical-align:top;">Geolocation Batch Update</span>
			</h1>
			<p>
				This batch procedures uses your Google Maps key to update the latitude and longitude for
				table <strong>`<?php echo $e_table_name; ?>`</strong>.
			</p>
			<p>
				Click <strong>START</strong> to begin. Click <strong>CANCEL</strong> (anytime) to stop the batch update.
			</p>
			<form>
				<fieldset class="wpda_fieldset wpdp_fieldset">
					<legend>
						Geolcation Settings
					</legend>
					<label class="wpda_geolocation_batch_label">
						Schema name
					</label>
					<strong>
						<?php echo esc_attr( $e_schema_name ); ?>
					</strong>
					<div class="wpda-spacer"></div>
					<label class="wpda_geolocation_batch_label">
						Table name
					</label>
					<strong>
						<?php echo esc_attr( $e_table_name ); ?>
					</strong>
					<div class="wpda-spacer"></div>
					<label class="wpda_geolocation_batch_label">
						Lookup columns
					</label>
					<strong>
						<?php echo esc_attr( $address_target ); ?>
					</strong>
				</fieldset>
					<fieldset class="wpda_fieldset wpdp_fieldset">
						<legend>
							Selection
						</legend>
						<label class="wpda_geolocation_batch_label">
							Update
						</label>
						<select id="wpda_geolocation_batch_selection">
							<option value="all">All rows (<?php echo $no_rows_total; ?>)</option>
							<option value="new">Only rows without a latitude and longitude (<?php echo $no_rows_nomaps; ?>)</option>
						</select>
						<br/>
						<label class="wpda_geolocation_batch_label">
							Max rows
						</label>
						<input type="number" id="wpda_geolocation_batch_max_rows" value="100">
						<span>(max rows processed in one batch)</span>
					</fieldset>
			</form>
			<div id="batch_monitor">
				<fieldset class="wpda_fieldset wpdp_fieldset">
					<legend>
						Progress
					</legend>
					<label class="wpda_geolocation_batch_label">
						Row
					</label>
					<strong>
						<span id="batch_monitor_row"></span>
					</strong>
					<br/>
					<label class="wpda_geolocation_batch_label">
						Lookup
					</label>
					<strong>
						<span id="batch_monitor_lookup"></span>
					</strong>
					<br/><br/>
					<label class="wpda_geolocation_batch_label">
						#Rows updated
					</label>
					<strong>
						<span id="batch_monitor_updated"></span>
					</strong>
					<br/>
					<label class="wpda_geolocation_batch_label">
						#Rows failed
					</label>
					<strong>
						<span id="batch_monitor_failed"></span>
					</strong>
					<br/>
					<div class="wpda-half-spacer"></div>
					<label class="wpda_geolocation_batch_label"></label>
					<input type="button" value="Reset counters" id="reset_counters" class="button" />
				</fieldset>
			</div>
			<div class="wpda-spacer"></div>
			<label class="wpda_geolocation_batch_label"></label>
			<span style="padding-left: 10px">
				<input type="button" value="START" id="button_start" class="button button-primary" disabled />
				<input type="button" value="CANCEL" id="button_cancel" class="button" disabled />
			</span>
			<script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo $geolocation->google_maps_api_key; ?>&callback=wpdaproGoogleMapsBatchUpdateInit"></script>
			<?php
		}

	}

}