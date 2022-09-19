<?php

namespace WPDataAccess\Premium\WPDAPRO_CPT {

	use WPDataAccess\WPDA;

	class WPDAPRO_CPT_Services {

		const CPTS_NON_SELECTABLE = 'WPDA_CPTS_NON_SELECTABLE';

		const CPTS_CUSTOM_FIELDS_SELECTABLE = [
				'post.post_title',
				'post.post_content',
			];

		const CPTS_CUSTOM_FIELDS_HIDDEN = [
			'post.ID',
			'post.post_author',
			'post.post_date',
			'post.post_date_gmt',
			'post.post_excerpt',
			'post.post_status',
			'post.comment_status',
			'post.ping_status',
			'post.post_password',
			'post.post_name',
			'post.to_ping',
			'post.pinged',
			'post.post_modified',
			'post.post_modified_gmt',
			'post.post_content_filtered',
			'post.post_parent',
			'post.guid',
			'post.menu_order',
			'post.post_type',
			'post.post_mime_type',
			'post.comment_count',
		];

		/**
		 * Get custom fields for a given post type
		 *
		 * @return array|false
		 */
		public static function get_custom_fields( $post_type ) {
			global $wpdb;

			// Get latest post.
			$posts = $wpdb->get_results(
				$wpdb->prepare("
						select `ID`
						from `{$wpdb->posts}`
						where `post_type` = %s
						order by `ID` desc
						limit 1
					",
					$post_type
				), 'ARRAY_A'
			);

			if ( 1 !== count( $posts ) ) {
				return false;
			}

			$post_id = $posts[0]['ID'];
			$cfld    = [];
			$rows    = $wpdb->get_results(
				$wpdb->prepare("
						select `{$wpdb->posts}`.`post_title`, `{$wpdb->postmeta}`.`meta_key`, `{$wpdb->postmeta}`.`meta_value`
						from `{$wpdb->postmeta}` 
							inner join `{$wpdb->posts}` on `{$wpdb->posts}`.`ID` = `{$wpdb->postmeta}`.`post_id` 
						where `{$wpdb->posts}`.`ID` = %d
					",
					$post_id
				), 'ARRAY_A'
			);

			$custom_field_keys = get_post_custom_keys( $post_id );
			$protected_cfld = [];
			if ( is_array( $custom_field_keys ) ) {
				foreach ( $custom_field_keys as $key => $value ) {
					if ( is_protected_meta( $value, 'post' ) ) {
						$protected_cfld[ $value ] = true;
					}
				}
			}

			foreach ( $rows as $row ) {
				if ( ! isset( $protected_cfld[ $row['meta_key'] ] ) ) {
					array_push( $cfld, $row['meta_key'] );
				}
			}

			return $cfld;
		}

		/**
		 * Get custom fields for a given post type (called via ajax)
		 */
		public static function get_custom_fields_ajax() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die();
			}

			$pub_id   = isset( $_REQUEST['pub_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['pub_id'] ) ) : null;
			$wp_nonce = isset( $_REQUEST['wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpnonce'] ) ) : ''; // input var okay.

			if ( ! wp_verify_nonce( $wp_nonce, "wpda-publication-{$pub_id}" ) ) {
				WPDA::sent_header( 'application/json' );
				WPDA::sent_msg( 'ERROR', 'Not authorized' );
				wp_die();
			}

			$post_type = isset( $_REQUEST['post_type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['post_type'] ) ) : null;
			if ( null === $post_type ) {
				WPDA::sent_header( 'application/json' );
				WPDA::sent_msg( 'ERROR', 'Wrong arguments' );
				wp_die();
			}

			$cfld = self::get_custom_fields( $post_type );

			if ( false === $cfld ) {
				WPDA::sent_header( 'application/json' );
				WPDA::sent_msg( 'ERROR', 'No posts found for this post type' );
				wp_die();
			}

			WPDA::sent_header( 'application/json' );
			WPDA::sent_msg( 'SUCCESS', $cfld );
			wp_die();
		}

		/**
		 * Get all non selectable custom post types
		 *
		 * @return array
		 */
		public static function get_non_selectable_cpts() {
			$cpts_non_selectable = get_option( self::CPTS_NON_SELECTABLE );
			if ( false === $cpts_non_selectable ) {
				$cpts_non_selectable = [];
			}
			return $cpts_non_selectable;
		}

		/**
		 * Update non selectable custom post types
		 *
		 * @return void
		 */
		public static function set_non_selectable_cpts() {
			if ( current_user_can( 'manage_options' ) ) {
				$pub_id   = isset( $_REQUEST['pub_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['pub_id'] ) ) : null;
				$wp_nonce = isset( $_REQUEST['wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpnonce'] ) ) : ''; // input var okay.

				if ( wp_verify_nonce( $wp_nonce, "wpda-publication-{$pub_id}" ) ) {
					$cpts = isset( $_REQUEST['cpts'] ) ? WPDA::sanitize_text_field_array( wp_unslash( $_REQUEST['cpts'] ) ) : [];
					update_option( self::CPTS_NON_SELECTABLE, $cpts );
				}
			}

			wp_die();
		}

	}

}