<?php
/**
 * Legacy demo-data cleanup utilities.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AMC_Seeder {
	/**
	 * Remove legacy seeded/demo content once.
	 *
	 * @return void
	 */
	public static function cleanup_legacy_demo_content() {
		$already_cleaned = (int) get_option( 'amc_legacy_demo_cleaned', 0 );

		if ( $already_cleaned ) {
			return;
		}

		global $wpdb;

		$legacy_chart_slugs = array_keys( AMC_Data::chart_definitions() );
		$legacy_artist_slugs = array( 'nancy-ajram', 'amr-diab', 'elissa', 'marwan-pablo', 'assala', 'wegz', 'tul8te', 'balqees' );
		$legacy_track_slugs  = array( 'shabab-el-layl', 'baheb-el-bahr', 'akhbarak-eh', 'ghorba', 'fouq-el-sama', 'dorak-gai', 'kol-youm', 'maa-elsowar' );
		$legacy_album_slugs  = array( 'noor-nights', 'seaside-radio', 'letters-in-neon', 'parallel-lines', 'golden-room', 'northern-lights' );

		$legacy_markers = array(
			'amc_demo_seeded' => (int) get_option( 'amc_demo_seeded', 0 ),
			'seeded_weeks'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . AMC_DB::table( 'chart_weeks' ) . " WHERE notes IN ('Auto-seeded published week', 'Auto-seeded archive week')" ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			'legacy_charts'   => self::count_slug_matches( 'charts', $legacy_chart_slugs ),
			'legacy_artists'  => self::count_slug_matches( 'artists', $legacy_artist_slugs ),
			'legacy_tracks'   => self::count_slug_matches( 'tracks', $legacy_track_slugs ),
			'legacy_albums'   => self::count_slug_matches( 'albums', $legacy_album_slugs ),
		);

		if ( ! array_filter( $legacy_markers ) ) {
			update_option( 'amc_legacy_demo_cleaned', 1, false );
			return;
		}

		$delete_by_slug = function ( $table_key, $slugs ) use ( $wpdb ) {
			if ( empty( $slugs ) ) {
				return;
			}

			$placeholders = implode( ',', array_fill( 0, count( $slugs ), '%s' ) );
			$table        = AMC_DB::table( $table_key );
			$sql          = $wpdb->prepare( "DELETE FROM {$table} WHERE slug IN ({$placeholders})", $slugs ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		};

		$week_ids = $wpdb->get_col( "SELECT id FROM " . AMC_DB::table( 'chart_weeks' ) . " WHERE notes IN ('Auto-seeded published week', 'Auto-seeded archive week')" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! empty( $week_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $week_ids ), '%d' ) );
			$entries_sql  = $wpdb->prepare( "DELETE FROM " . AMC_DB::table( 'chart_entries' ) . " WHERE chart_week_id IN ({$placeholders})", $week_ids ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$weeks_sql    = $wpdb->prepare( "DELETE FROM " . AMC_DB::table( 'chart_weeks' ) . " WHERE id IN ({$placeholders})", $week_ids ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( $entries_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( $weeks_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$delete_by_slug( 'tracks', $legacy_track_slugs );
		$delete_by_slug( 'albums', $legacy_album_slugs );
		$delete_by_slug( 'artists', $legacy_artist_slugs );
		$delete_by_slug( 'charts', $legacy_chart_slugs );

		$wpdb->query( "DELETE FROM " . AMC_DB::table( 'source_uploads' ) . " WHERE preview_text LIKE '%seeded%' OR error_message LIKE '%seeded%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "DELETE FROM " . AMC_DB::table( 'ingestion_logs' ) . " WHERE message LIKE '%seeded%' OR message LIKE '%demo%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "DELETE FROM " . AMC_DB::table( 'scoring_rules' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "DELETE FROM " . AMC_DB::table( 'platform_settings' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		delete_option( 'amc_demo_seeded' );
		update_option( 'amc_legacy_demo_cleaned', 1, false );
	}

	/**
	 * Count rows matching legacy slugs.
	 *
	 * @param string $table_key Table key.
	 * @param array  $slugs Slugs.
	 * @return int
	 */
	private static function count_slug_matches( $table_key, $slugs ) {
		global $wpdb;

		if ( empty( $slugs ) ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $slugs ), '%s' ) );
		$table        = AMC_DB::table( $table_key );
		$sql          = $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE slug IN ({$placeholders})", $slugs ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
