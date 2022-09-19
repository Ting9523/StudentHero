<?php

/**
 * Suppress "error - 0 - No summary was found for this file" on phpdoc generation
 *
 * @package WPDataAccess\Plugin_Table_Models
 */

namespace WPDataAccess\Plugin_Table_Models {

	use WPDataAccess\WPDA;
	use WPDataAccess\Connection\WPDADB;

	/**
	 * Class WPDA_Publisher_Model
	 *
	 * Model for plugin table 'publisher'
	 *
	 * @author  Peter Schulz
	 * @since   2.6.0
	 */
	class WPDA_Publisher_Model extends WPDA_Plugin_Table_Base_Model {

		const BASE_TABLE_NAME = 'wpda_publisher';

		/**
		 * Return the publication for a specific publication id
		 *
		 * @param int $pub_id Publication id
		 *
		 * @return bool|array
		 */
		public static function get_publication( $pub_id ) {
			global $wpdb;
			$dataset = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM `%1s` WHERE pub_id = %d', // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
					array(
						WPDA::remove_backticks( self::get_base_table_name() ),
						$pub_id,
					)
				), // db call ok; no-cache ok.
				'ARRAY_A'
			); // phpcs:ignore Standard.Category.SniffName.ErrorCode

			return 1 === $wpdb->num_rows ? $dataset : false;
		}

		/**
		 * Return the publication for a specific publication name
		 *
		 * @param int $pub_name Publication name
		 *
		 * @return bool|array
		 */
		public static function get_publication_by_name( $pub_name ) {
			global $wpdb;
			$dataset = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM `%1s` WHERE pub_name = %s', // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
					array(
						WPDA::remove_backticks( self::get_base_table_name() ),
						$pub_name,
					)
				), // db call ok; no-cache ok.
				'ARRAY_A'
			); // phpcs:ignore Standard.Category.SniffName.ErrorCode

			return 1 === $wpdb->num_rows ? $dataset : false;
		}

		public static function get_publication_list() {
			global $wpdb;
			return $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM `%1s` ORDER BY pub_name', // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
					array(
						WPDA::remove_backticks( self::get_base_table_name() ),
					)
				), // db call ok; no-cache ok.
				'ARRAY_A'
			); // phpcs:ignore Standard.Category.SniffName.ErrorCode
		}

		public static function get_temporary_table_from_custom_query( $database, $query ) {
			$response = [
				'data' => [],
				'msg'  => '',
			];
			if ( wpda_freemius()->can_use_premium_code__premium_only() ) {
				$wpdadb = WPDADB::get_db_connection( $database );
				if ( null === $wpdadb ) {
					$response['msg'] = __( 'ERROR: Unable to connect to database ', 'wp-data-access' ) . $database;
					return $response;
				}

				$wpdadb->suppress_errors( true );
				$table_name = 'custom' . mt_rand(0, time());

				// Create temporary table.
				$wpdadb->query(
					"CREATE TEMPORARY TABLE `{$table_name}` AS (select * from (" . $query . ') as t limit 0)'
				);

				if ( '' !== $wpdadb->last_error ) {
					$response['msg'] = __( 'ERROR: Invalid query', 'wp-data-access' );
					return $response;
				}

				// Get query structure.
				$rows = $wpdadb->get_results( "desc `{$table_name}`", 'ARRAY_A' );

				if ( '' !== $wpdadb->last_error ) {
					$response['msg'] = __( 'ERROR: Invalid query', 'wp-data-access' );
					return $response;
				}

				$response['data'] = $rows;
				return $response;
			}
		}

	}

}
