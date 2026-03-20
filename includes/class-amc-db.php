<?php
/**
 * Database layer for Kontentainment Charts.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AMC_DB {
	/**
	 * Database version.
	 */
	const VERSION = '1.2.0';

	/**
	 * Table suffixes.
	 *
	 * @return array
	 */
	public static function tables() {
		return array(
			'charts'            => 'amc_charts',
			'chart_weeks'       => 'amc_chart_weeks',
			'chart_entries'     => 'amc_chart_entries',
			'artists'           => 'amc_artists',
			'tracks'            => 'amc_tracks',
			'albums'            => 'amc_albums',
			'platform_settings' => 'amc_platform_settings',
			'source_uploads'    => 'amc_source_uploads',
			'source_rows'       => 'amc_source_rows',
			'matching_queue'    => 'amc_matching_queue',
			'scoring_rules'     => 'amc_scoring_rules',
			'ingestion_logs'    => 'amc_ingestion_logs',
		);
	}

	/**
	 * Get full table name.
	 *
	 * @param string $key Table key.
	 * @return string
	 */
	public static function table( $key ) {
		global $wpdb;

		$tables = self::tables();

		return $wpdb->prefix . $tables[ $key ];
	}

	/**
	 * Install or update database tables.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$charts          = self::table( 'charts' );
		$chart_weeks     = self::table( 'chart_weeks' );
		$chart_entries   = self::table( 'chart_entries' );
		$artists         = self::table( 'artists' );
		$tracks          = self::table( 'tracks' );
		$albums          = self::table( 'albums' );
		$settings        = self::table( 'platform_settings' );
		$source_uploads  = self::table( 'source_uploads' );
		$source_rows     = self::table( 'source_rows' );
		$matching_queue  = self::table( 'matching_queue' );
		$scoring_rules   = self::table( 'scoring_rules' );
		$ingestion_logs  = self::table( 'ingestion_logs' );

		dbDelta(
			"CREATE TABLE {$charts} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(191) NOT NULL,
				slug varchar(191) NOT NULL,
				description text NULL,
				type varchar(20) NOT NULL DEFAULT 'track',
				cover_image varchar(255) NULL,
				display_order int(11) NOT NULL DEFAULT 0,
				status varchar(20) NOT NULL DEFAULT 'active',
				is_featured_home tinyint(1) NOT NULL DEFAULT 0,
				archive_enabled tinyint(1) NOT NULL DEFAULT 1,
				accent varchar(40) NOT NULL DEFAULT 'amber',
				kicker varchar(191) NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY slug (slug),
				KEY type (type),
				KEY status (status),
				KEY display_order (display_order)
			) {$charset_collate};"
		);

		dbDelta(
			"CREATE TABLE {$artists} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(191) NOT NULL,
				slug varchar(191) NOT NULL,
				image varchar(255) NULL,
				bio longtext NULL,
				blurb text NULL,
				country varchar(191) NULL,
				genre varchar(191) NULL,
				social_links text NULL,
				monthly_listeners varchar(40) NULL,
				chart_streak varchar(191) NULL,
				gradient varchar(40) NOT NULL DEFAULT 'ocean',
				status varchar(20) NOT NULL DEFAULT 'active',
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY slug (slug),
				KEY status (status)
			) {$charset_collate};"
		);

		dbDelta(
			"CREATE TABLE {$albums} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				artist_id bigint(20) unsigned NOT NULL DEFAULT 0,
				title varchar(191) NOT NULL,
				slug varchar(191) NOT NULL,
				cover_image varchar(255) NULL,
				description longtext NULL,
				release_date date NULL,
				release_year varchar(4) NULL,
				track_list longtext NULL,
				genre varchar(191) NULL,
				label varchar(191) NULL,
				gradient varchar(40) NOT NULL DEFAULT 'ocean',
				status varchar(20) NOT NULL DEFAULT 'active',
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY slug (slug),
				KEY artist_id (artist_id),
				KEY status (status)
			) {$charset_collate};"
		);

		dbDelta(
			"CREATE TABLE {$tracks} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				artist_id bigint(20) unsigned NOT NULL DEFAULT 0,
				album_id bigint(20) unsigned NOT NULL DEFAULT 0,
				title varchar(191) NOT NULL,
				slug varchar(191) NOT NULL,
				cover_image varchar(255) NULL,
				description longtext NULL,
				isrc varchar(64) NULL,
				aliases text NULL,
				release_date date NULL,
				genre varchar(191) NULL,
				duration varchar(20) NULL,
				gradient varchar(40) NOT NULL DEFAULT 'ocean',
				status varchar(20) NOT NULL DEFAULT 'active',
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY slug (slug),
				KEY artist_id (artist_id),
				KEY album_id (album_id),
				KEY status (status)
			) {$charset_collate};"
		);

		dbDelta(
			"CREATE TABLE {$chart_weeks} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				chart_id bigint(20) unsigned NOT NULL,
				country varchar(64) NOT NULL DEFAULT 'Global',
				week_date date NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'draft',
				is_featured tinyint(1) NOT NULL DEFAULT 0,
				notes text NULL,
				published_at datetime NULL,
				archived_at datetime NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY chart_week (chart_id, country, week_date),
				KEY chart_id (chart_id),
				KEY country (country),
				KEY status (status),
				KEY week_date (week_date)
			) {$charset_collate};"
		);

		dbDelta(
			"CREATE TABLE {$chart_entries} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				chart_week_id bigint(20) unsigned NOT NULL,
				entity_type varchar(20) NOT NULL,
				entity_id bigint(20) unsigned NOT NULL DEFAULT 0,
				current_rank int(11) NOT NULL DEFAULT 0,
				previous_rank int(11) NOT NULL DEFAULT 0,
				peak_rank int(11) NOT NULL DEFAULT 0,
				weeks_on_chart int(11) NOT NULL DEFAULT 0,
				movement varchar(20) NOT NULL DEFAULT 'same',
				score decimal(10,2) NOT NULL DEFAULT 0.00,
				score_change decimal(10,2) NOT NULL DEFAULT 0.00,
				source_count int(11) NOT NULL DEFAULT 0,
				artwork varchar(255) NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY chart_week_id (chart_week_id),
				KEY entity_lookup (entity_type, entity_id),
				KEY current_rank (current_rank)
			) {$charset_collate};"
		);

		dbDelta(
			"CREATE TABLE {$settings} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				setting_key varchar(191) NOT NULL,
				setting_value longtext NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY setting_key (setting_key)
			) {$charset_collate};"
		);

		dbDelta(
			"CREATE TABLE {$source_uploads} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				source_name varchar(191) NOT NULL,
				source_platform varchar(64) NOT NULL DEFAULT '',
				country varchar(64) NOT NULL DEFAULT 'Global',
				chart_week varchar(32) NOT NULL,
				chart_date date NULL,
				target_chart_id bigint(20) unsigned NOT NULL DEFAULT 0,
				chart_type varchar(20) NOT NULL DEFAULT 'track',
				file_name varchar(255) NOT NULL,
				file_path text NOT NULL,
				file_url text NOT NULL,
				mime_type varchar(191) NULL,
				file_size bigint(20) unsigned NOT NULL DEFAULT 0,
				file_status varchar(20) NOT NULL DEFAULT 'uploaded',
				generated_week_id bigint(20) unsigned NOT NULL DEFAULT 0,
				row_count int(11) NOT NULL DEFAULT 0,
				preview_text text NULL,
				uploader_id bigint(20) unsigned NOT NULL DEFAULT 0,
				parser_name varchar(191) NULL,
				error_message text NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY file_status (file_status),
				KEY chart_week (chart_week),
				KEY chart_date (chart_date),
				KEY source_platform (source_platform),
				KEY target_chart_id (target_chart_id),
				KEY chart_type (chart_type),
				KEY country (country)
			) {$charset_collate};"
		);

		dbDelta(
			"CREATE TABLE {$source_rows} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				upload_id bigint(20) unsigned NOT NULL,
				row_index int(11) NOT NULL DEFAULT 0,
				raw_data longtext NULL,
				raw_row_json longtext NULL,
				chart_date date NULL,
				source_platform varchar(64) NOT NULL DEFAULT '',
				country varchar(64) NOT NULL DEFAULT 'Global',
				target_chart_id bigint(20) unsigned NOT NULL DEFAULT 0,
				chart_type varchar(20) NOT NULL DEFAULT 'track',
				rank int(11) NOT NULL DEFAULT 0,
				previous_rank int(11) NOT NULL DEFAULT 0,
				peak_rank int(11) NOT NULL DEFAULT 0,
				weeks_on_chart int(11) NOT NULL DEFAULT 0,
				track_title varchar(191) NULL,
				artist_name varchar(191) NULL,
				artist_names text NULL,
				album_name varchar(191) NULL,
				source_metric_value decimal(18,4) NOT NULL DEFAULT 0.0000,
				growth varchar(64) NULL,
				source_url text NULL,
				source_uri text NULL,
				normalized_title varchar(191) NULL,
				normalized_artist varchar(191) NULL,
				normalized_album varchar(191) NULL,
				normalized_isrc varchar(64) NULL,
				normalized_rank int(11) NOT NULL DEFAULT 0,
				normalized_score decimal(10,2) NOT NULL DEFAULT 0.00,
				matched_entity_type varchar(20) NULL,
				matched_entity_id bigint(20) unsigned NOT NULL DEFAULT 0,
				matching_status varchar(20) NOT NULL DEFAULT 'pending',
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY upload_id (upload_id),
				KEY target_chart_id (target_chart_id),
				KEY chart_date (chart_date),
				KEY source_platform (source_platform),
				KEY chart_type (chart_type),
				KEY normalized_title (normalized_title),
				KEY normalized_artist (normalized_artist),
				KEY matching_status (matching_status)
			) {$charset_collate};"
		);

		dbDelta(
			"CREATE TABLE {$matching_queue} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				upload_id bigint(20) unsigned NOT NULL,
				source_row_id bigint(20) unsigned NOT NULL,
				entity_type varchar(20) NOT NULL,
				candidate_entity_id bigint(20) unsigned NOT NULL DEFAULT 0,
				candidate_label varchar(191) NULL,
				confidence decimal(5,2) NOT NULL DEFAULT 0.00,
				match_basis varchar(191) NULL,
				status varchar(20) NOT NULL DEFAULT 'pending',
				override_entity_type varchar(20) NULL,
				override_entity_id bigint(20) unsigned NOT NULL DEFAULT 0,
				notes text NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY upload_id (upload_id),
				KEY source_row_id (source_row_id),
				KEY status (status)
			) {$charset_collate};"
		);

		dbDelta(
			"CREATE TABLE {$scoring_rules} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				rule_group varchar(64) NOT NULL,
				rule_key varchar(191) NOT NULL,
				rule_value longtext NULL,
				rule_type varchar(32) NOT NULL DEFAULT 'text',
				sort_order int(11) NOT NULL DEFAULT 0,
				is_active tinyint(1) NOT NULL DEFAULT 1,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY rule_key (rule_key),
				KEY rule_group (rule_group)
			) {$charset_collate};"
		);

		dbDelta(
			"CREATE TABLE {$ingestion_logs} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				upload_id bigint(20) unsigned NOT NULL DEFAULT 0,
				source_row_id bigint(20) unsigned NOT NULL DEFAULT 0,
				action varchar(64) NOT NULL,
				level varchar(20) NOT NULL DEFAULT 'info',
				message text NOT NULL,
				context longtext NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY upload_id (upload_id),
				KEY source_row_id (source_row_id),
				KEY level (level)
			) {$charset_collate};"
		);

		update_option( 'amc_db_version', self::VERSION, false );
	}

	/**
	 * Maybe update schema.
	 *
	 * @return void
	 */
	public static function maybe_install() {
		$current = (string) get_option( 'amc_db_version', '' );

		if ( self::VERSION !== $current ) {
			self::install();
		}
	}

	/**
	 * Fetch a collection of rows.
	 *
	 * @param string $table_key Table key.
	 * @param array  $args Query args.
	 * @return array
	 */
	public static function get_rows( $table_key, $args = array() ) {
		global $wpdb;

		$defaults = array(
			'where'   => array(),
			'order_by'=> 'id DESC',
			'limit'   => 0,
		);
		$args     = wp_parse_args( $args, $defaults );
		$table    = self::table( $table_key );
		$where    = array();
		$values   = array();

		foreach ( $args['where'] as $column => $value ) {
			if ( is_null( $value ) ) {
				continue;
			}

			$where[]  = "{$column} = %s";
			$values[] = (string) $value;
		}

		$sql = "SELECT * FROM {$table}";

		if ( ! empty( $where ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}

		$sql .= ' ORDER BY ' . $args['order_by'];

		if ( ! empty( $args['limit'] ) ) {
			$sql .= ' LIMIT ' . absint( $args['limit'] );
		}

		if ( ! empty( $values ) ) {
			$sql = $wpdb->prepare( $sql, $values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Fetch a single row by id.
	 *
	 * @param string $table_key Table key.
	 * @param int    $id Record id.
	 * @return array|null
	 */
	public static function get_row( $table_key, $id ) {
		global $wpdb;

		$table = self::table( $table_key );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $row ? $row : null;
	}

	/**
	 * Fetch a single row by slug.
	 *
	 * @param string $table_key Table key.
	 * @param string $slug Slug.
	 * @return array|null
	 */
	public static function get_row_by_slug( $table_key, $slug ) {
		global $wpdb;

		$table = self::table( $table_key );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s LIMIT 1", sanitize_title( $slug ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $row ? $row : null;
	}

	/**
	 * Upsert a row by id.
	 *
	 * @param string $table_key Table key.
	 * @param array  $data Row data.
	 * @param int    $id Optional id.
	 * @return int
	 */
	public static function save_row( $table_key, $data, $id = 0 ) {
		global $wpdb;

		$table = self::table( $table_key );
		$now   = current_time( 'mysql' );

		if ( empty( $id ) ) {
			$data['created_at'] = $now;
		}

		$data['updated_at'] = $now;

		if ( ! empty( $id ) ) {
			$wpdb->update( $table, $data, array( 'id' => absint( $id ) ) );
			return absint( $id );
		}

		$wpdb->insert( $table, $data );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete a row.
	 *
	 * @param string $table_key Table key.
	 * @param int    $id Record id.
	 * @return void
	 */
	public static function delete_row( $table_key, $id ) {
		global $wpdb;

		$wpdb->delete( self::table( $table_key ), array( 'id' => absint( $id ) ) );
	}

	/**
	 * Get dashboard counts.
	 *
	 * @return array
	 */
	public static function dashboard_counts() {
		return array(
			'charts'  => self::count_rows( 'charts' ),
			'tracks'  => self::count_rows( 'tracks' ),
			'artists' => self::count_rows( 'artists' ),
			'albums'  => self::count_rows( 'albums' ),
		);
	}

	/**
	 * Count rows in a table.
	 *
	 * @param string $table_key Table key.
	 * @param array  $where Optional where.
	 * @return int
	 */
	public static function count_rows( $table_key, $where = array() ) {
		global $wpdb;

		$table  = self::table( $table_key );
		$sql    = "SELECT COUNT(*) FROM {$table}";
		$parts  = array();
		$values = array();

		foreach ( $where as $column => $value ) {
			$parts[]  = "{$column} = %s";
			$values[] = (string) $value;
		}

		if ( ! empty( $parts ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $parts );
			$sql  = $wpdb->prepare( $sql, $values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get current published chart week.
	 *
	 * @param int $chart_id Chart id.
	 * @return array|null
	 */
	public static function get_current_published_week( $chart_id ) {
		global $wpdb;

		$table = self::table( 'chart_weeks' );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE chart_id = %d AND status = %s ORDER BY is_featured DESC, week_date DESC, id DESC LIMIT 1",
				absint( $chart_id ),
				'published'
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $row ? $row : null;
	}

	/**
	 * Get chart weeks.
	 *
	 * @param array $args Filters.
	 * @return array
	 */
	public static function get_chart_weeks( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'chart_id' => 0,
			'country'  => '',
			'status'   => '',
			'order_by' => 'week_date DESC, id DESC',
		);
		$args     = wp_parse_args( $args, $defaults );
		$table    = self::table( 'chart_weeks' );
		$where    = array();
		$values   = array();

		if ( ! empty( $args['chart_id'] ) ) {
			$where[]  = 'chart_id = %d';
			$values[] = absint( $args['chart_id'] );
		}

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		if ( ! empty( $args['country'] ) ) {
			$where[]  = 'country = %s';
			$values[] = $args['country'];
		}

		$sql = "SELECT * FROM {$table}";

		if ( ! empty( $where ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}

		$sql .= ' ORDER BY ' . $args['order_by'];

		if ( ! empty( $values ) ) {
			$sql = $wpdb->prepare( $sql, $values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get chart entries.
	 *
	 * @param int $chart_week_id Chart week id.
	 * @return array
	 */
	public static function get_chart_entries( $chart_week_id ) {
		global $wpdb;

		$table = self::table( 'chart_entries' );
		$sql   = $wpdb->prepare( "SELECT * FROM {$table} WHERE chart_week_id = %d ORDER BY current_rank ASC, id ASC", absint( $chart_week_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Remove all entries for a chart week.
	 *
	 * @param int $chart_week_id Week id.
	 * @return void
	 */
	public static function delete_chart_week_entries( $chart_week_id ) {
		global $wpdb;

		$wpdb->delete( self::table( 'chart_entries' ), array( 'chart_week_id' => absint( $chart_week_id ) ) );
	}

	/**
	 * Get settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$defaults = array(
			'platform_name'    => 'Kontentainment Charts',
			'logo'             => 'kontentainment-charts-mark.svg',
			'seo_defaults'     => 'Enable chart-specific metadata',
			'social_image'     => 'weekly-share-default.jpg',
			'homepage_chart'   => 'hot-100-tracks',
			'methodology_text' => 'Custom weighted methodology summary',
			'language'         => 'English',
			'date_format'      => 'F j, Y',
		);

		global $wpdb;
		$table = self::table( 'platform_settings' );
		$rows  = $wpdb->get_results( "SELECT setting_key, setting_value FROM {$table}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		foreach ( $rows as $row ) {
			$defaults[ $row['setting_key'] ] = maybe_unserialize( $row['setting_value'] );
		}

		return $defaults;
	}

	/**
	 * Save settings.
	 *
	 * @param array $settings Settings values.
	 * @return void
	 */
	public static function save_settings( $settings ) {
		global $wpdb;

		$table = self::table( 'platform_settings' );
		$now   = current_time( 'mysql' );

		foreach ( $settings as $key => $value ) {
			$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE setting_key = %s", $key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			if ( $existing ) {
				$wpdb->update(
					$table,
					array(
						'setting_value' => maybe_serialize( $value ),
						'updated_at'    => $now,
					),
					array( 'id' => absint( $existing ) )
				);
			} else {
				$wpdb->insert(
					$table,
					array(
						'setting_key'   => $key,
						'setting_value' => maybe_serialize( $value ),
						'updated_at'    => $now,
					)
				);
			}
		}
	}
}
