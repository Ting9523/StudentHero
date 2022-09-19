<?php

namespace WPDataAccess\Utilities {

	class WPDA_Remote_Call {

		public static function post( $url, $body, $die = false, $headers = array() ) {
			$response = wp_remote_post(
				$url,
				array(
					'headers' => $headers,
					'body'    => $body,
					'timeout' => 600,
				)
			);
			// var_dump($response);

			if ( is_wp_error( $response ) ) {
				if ( $die ) {
					self::die();
				}

				return false;
			}

			if ( ! isset( $response['response'], $response['body'] ) ) {
				if ( $die ) {
					self::die();
				}

				return false;
			}

			return $response;
		}

		public static function die() {
			wp_die( __( 'ERROR: Remote call failed', 'wp-data-access' ) );
		}

		public static function max_size() {
			$max  = ini_get('post_max_size');
			$unit = $max[ strlen( $max ) - 1 ];
			$max  = substr( $max, 0, strlen( $max ) - 1 );

			switch($unit) {
				case 'G':
					$max *= 1024;
				case 'M':
					$max *= 1024;
				case 'K':
					$max *= 1024;
			}

			return $max;
		}

	}

}