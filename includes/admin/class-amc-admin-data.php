<?php
/**
 * Seeded admin-only data for Phase 2 interface.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AMC_Admin_Data {
	/**
	 * Menu/page registry.
	 *
	 * @return array
	 */
	public static function pages() {
		return array(
			'dashboard'      => array( 'menu_slug' => 'kontentainment-charts', 'title' => 'Dashboard' ),
			'charts'         => array( 'menu_slug' => 'kontentainment-charts-charts', 'title' => 'Charts Management' ),
			'weekly-entries' => array( 'menu_slug' => 'kontentainment-charts-weeks', 'title' => 'Weekly Chart Entries' ),
			'tracks'         => array( 'menu_slug' => 'kontentainment-charts-tracks', 'title' => 'Tracks Management' ),
			'artists'        => array( 'menu_slug' => 'kontentainment-charts-artists', 'title' => 'Artists Management' ),
			'albums'         => array( 'menu_slug' => 'kontentainment-charts-albums', 'title' => 'Albums Management' ),
			'uploads'        => array( 'menu_slug' => 'kontentainment-charts-uploads', 'title' => 'Source Uploads' ),
			'cleaning'       => array( 'menu_slug' => 'kontentainment-charts-cleaning', 'title' => 'Matching and Cleaning' ),
			'scoring'        => array( 'menu_slug' => 'kontentainment-charts-scoring', 'title' => 'Scoring Rules' ),
			'publishing'     => array( 'menu_slug' => 'kontentainment-charts-publishing', 'title' => 'Publishing' ),
			'archives'       => array( 'menu_slug' => 'kontentainment-charts-archives', 'title' => 'Archive Management' ),
			'users'          => array( 'menu_slug' => 'kontentainment-charts-users', 'title' => 'Users and Roles' ),
			'settings'       => array( 'menu_slug' => 'kontentainment-charts-settings', 'title' => 'Settings' ),
		);
	}

	/**
	 * Lightweight wp-admin pages.
	 *
	 * @return array
	 */
	public static function wp_admin_pages() {
		return array(
			'overview'       => array( 'menu_slug' => 'kontentainment-charts', 'title' => 'Overview' ),
			'settings'       => array( 'menu_slug' => 'kontentainment-charts-settings', 'title' => 'Settings' ),
			'tools'          => array( 'menu_slug' => 'kontentainment-charts-tools', 'title' => 'Tools' ),
			'logs'           => array( 'menu_slug' => 'kontentainment-charts-logs', 'title' => 'Logs' ),
			'permissions'    => array( 'menu_slug' => 'kontentainment-charts-permissions', 'title' => 'Permissions' ),
			'open-dashboard' => array( 'menu_slug' => 'kontentainment-charts-open-dashboard', 'title' => 'Open Dashboard' ),
		);
	}

	/**
	 * Full dashboard sections.
	 *
	 * @return array
	 */
	public static function dashboard_sections() {
		return array(
			'dashboard'      => array( 'title' => 'Dashboard', 'path' => '' ),
			'charts'         => array( 'title' => 'Charts', 'path' => 'charts' ),
			'weekly-entries' => array( 'title' => 'Weekly Entries', 'path' => 'weekly-entries' ),
			'tracks'         => array( 'title' => 'Tracks', 'path' => 'tracks' ),
			'artists'        => array( 'title' => 'Artists', 'path' => 'artists' ),
			'albums'         => array( 'title' => 'Albums', 'path' => 'albums' ),
			'uploads'        => array( 'title' => 'Source Uploads', 'path' => 'uploads' ),
			'cleaning'       => array( 'title' => 'Matching and Cleaning', 'path' => 'cleaning' ),
			'scoring'        => array( 'title' => 'Scoring Rules', 'path' => 'scoring' ),
			'publishing'     => array( 'title' => 'Publishing', 'path' => 'publishing' ),
			'archives'       => array( 'title' => 'Archive Management', 'path' => 'archives' ),
			'users'          => array( 'title' => 'Users and Roles', 'path' => 'users' ),
			'settings'       => array( 'title' => 'Settings', 'path' => 'settings' ),
		);
	}

	/**
	 * Resolve custom dashboard url.
	 *
	 * @param string $section Section key.
	 * @return string
	 */
	public static function custom_dashboard_url( $section = 'dashboard' ) {
		$sections = self::dashboard_sections();

		if ( empty( $sections[ $section ] ) || empty( $sections[ $section ]['path'] ) ) {
			return home_url( '/charts-dashboard/' );
		}

		return home_url( '/charts-dashboard/' . $sections[ $section ]['path'] . '/' );
	}

	/**
	 * Overview counts.
	 *
	 * @return array
	 */
	public static function overview_cards() {
		$counts = AMC_DB::dashboard_counts();

		return array(
			array( 'label' => 'Total Charts', 'value' => (string) $counts['charts'], 'delta' => self::count_label( 'chart_weeks', 'draft', 'draft weeks' ), 'tone' => 'gold' ),
			array( 'label' => 'Tracks In Library', 'value' => (string) $counts['tracks'], 'delta' => self::count_label( 'tracks', 'archived', 'archived' ), 'tone' => 'violet' ),
			array( 'label' => 'Artists In Library', 'value' => (string) $counts['artists'], 'delta' => self::count_label( 'artists', 'archived', 'archived' ), 'tone' => 'blue' ),
			array( 'label' => 'Albums In Library', 'value' => (string) $counts['albums'], 'delta' => self::count_label( 'albums', 'archived', 'archived' ), 'tone' => 'emerald' ),
		);
	}

	/**
	 * Recent uploads.
	 *
	 * @return array
	 */
	public static function recent_uploads() {
		$uploads = AMC_DB::get_rows( 'source_uploads', array( 'order_by' => 'id DESC', 'limit' => 4 ) );

		return array_map(
			function ( $row ) {
				$user = ! empty( $row['uploader_id'] ) ? get_user_by( 'id', (int) $row['uploader_id'] ) : null;

				return array(
					'source'     => $row['source_name'],
					'chart_week' => $row['chart_week'],
					'status'     => ucfirst( $row['file_status'] ),
					'rows'       => (int) $row['row_count'],
					'uploader'   => $user ? $user->display_name : 'System',
				);
			},
			$uploads
		);
	}

	/**
	 * Alerts.
	 *
	 * @return array
	 */
	public static function alerts() {
		global $wpdb;

		$tracks_table  = AMC_DB::table( 'tracks' );
		$artists_table = AMC_DB::table( 'artists' );
		$albums_table  = AMC_DB::table( 'albums' );
		$dupes         = (int) $wpdb->get_var( "SELECT COUNT(*) FROM (SELECT slug FROM {$tracks_table} GROUP BY slug HAVING COUNT(*) > 1) duplicate_tracks" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$missing       = AMC_DB::count_rows( 'tracks', array( 'cover_image' => '' ) ) + AMC_DB::count_rows( 'artists', array( 'image' => '' ) ) + AMC_DB::count_rows( 'albums', array( 'cover_image' => '' ) );
		$draft_weeks   = AMC_DB::count_rows( 'chart_weeks', array( 'status' => 'draft' ) );

		return array(
			array( 'title' => 'Duplicate track candidates', 'body' => $dupes ? $dupes . ' duplicate slugs need manual review.' : 'No duplicate track slugs are currently detected.', 'tone' => 'warning' ),
			array( 'title' => 'Missing artwork', 'body' => $missing ? $missing . ' artist, album, or track records still use fallback artwork styling.' : 'All current records have artwork placeholders or assigned imagery.', 'tone' => 'danger' ),
			array( 'title' => 'Draft chart weeks', 'body' => $draft_weeks ? $draft_weeks . ' chart weeks are still waiting for publication flow.' : 'No pending draft weeks right now.', 'tone' => 'info' ),
		);
	}

	/**
	 * Week status.
	 *
	 * @return array
	 */
	public static function chart_week_status() {
		return array(
			array( 'label' => 'Published Weeks', 'value' => AMC_DB::count_rows( 'chart_weeks', array( 'status' => 'published' ) ) ),
			array( 'label' => 'Draft Weeks', 'value' => AMC_DB::count_rows( 'chart_weeks', array( 'status' => 'draft' ) ) ),
			array( 'label' => 'Archived Weeks', 'value' => AMC_DB::count_rows( 'chart_weeks', array( 'status' => 'archived' ) ) ),
		);
	}

	/**
	 * Chart categories.
	 *
	 * @return array
	 */
	public static function chart_categories() {
		$rows = AMC_DB::get_rows( 'charts', array( 'order_by' => 'display_order ASC, id ASC' ) );

		return array_map(
			function ( $row ) {
				return array(
					'id'            => (int) $row['id'],
					'name'          => $row['name'],
					'slug'          => $row['slug'],
					'type'          => ucfirst( $row['type'] ) . 's',
					'display_order' => (int) $row['display_order'],
					'active'        => 'active' === $row['status'] ? 'Active' : 'Hidden',
					'featured'      => ! empty( $row['is_featured_home'] ) ? 'Yes' : 'No',
					'archive'       => ! empty( $row['archive_enabled'] ) ? 'Enabled' : 'Disabled',
					'accent'        => $row['accent'],
					'description'   => $row['description'],
					'kicker'        => $row['kicker'],
					'status'        => $row['status'],
				);
			},
			$rows
		);
	}

	/**
	 * Weekly entries sample.
	 *
	 * @return array
	 */
	public static function weekly_entries() {
		$weeks = AMC_DB::get_chart_weeks();
		$week  = ! empty( $weeks[0] ) ? $weeks[0] : null;

		if ( ! $week ) {
			return array();
		}

		return self::entries_for_week( (int) $week['id'] );
	}

	/**
	 * Tracks sample.
	 *
	 * @return array
	 */
	public static function tracks() {
		$rows = AMC_DB::get_rows( 'tracks', array( 'order_by' => 'title ASC' ) );

		return array_map(
			function ( $row ) {
				$artist = ! empty( $row['artist_id'] ) ? AMC_DB::get_row( 'artists', (int) $row['artist_id'] ) : null;
				$album  = ! empty( $row['album_id'] ) ? AMC_DB::get_row( 'albums', (int) $row['album_id'] ) : null;

				return array(
					'id'           => (int) $row['id'],
					'title'        => $row['title'],
					'slug'         => $row['slug'],
					'artist'       => $artist ? $artist['name'] : 'Unknown',
					'album'        => $album ? $album['title'] : 'Standalone',
					'isrc'         => $row['isrc'],
					'aliases'      => $row['aliases'],
					'release_date' => $row['release_date'],
					'genre'        => $row['genre'],
					'status'       => ucfirst( $row['status'] ),
					'raw_status'   => $row['status'],
					'artist_id'    => (int) $row['artist_id'],
					'album_id'     => (int) $row['album_id'],
					'duration'     => $row['duration'],
					'description'  => $row['description'],
					'gradient'     => $row['gradient'],
				);
			},
			$rows
		);
	}

	/**
	 * Artists sample.
	 *
	 * @return array
	 */
	public static function artists() {
		$rows = AMC_DB::get_rows( 'artists', array( 'order_by' => 'name ASC' ) );

		return array_map(
			function ( $row ) {
				return array(
					'id'             => (int) $row['id'],
					'name'           => $row['name'],
					'slug'           => $row['slug'],
					'country'        => $row['country'],
					'genre'          => $row['genre'],
					'socials'        => $row['social_links'],
					'related_tracks' => AMC_DB::count_rows( 'tracks', array( 'artist_id' => (int) $row['id'] ) ),
					'related_albums' => AMC_DB::count_rows( 'albums', array( 'artist_id' => (int) $row['id'] ) ),
					'status'         => ucfirst( $row['status'] ),
					'raw_status'     => $row['status'],
					'bio'            => $row['bio'],
					'monthly'        => $row['monthly_listeners'],
					'streak'         => $row['chart_streak'],
					'gradient'       => $row['gradient'],
				);
			},
			$rows
		);
	}

	/**
	 * Albums sample.
	 *
	 * @return array
	 */
	public static function albums() {
		$rows = AMC_DB::get_rows( 'albums', array( 'order_by' => 'title ASC' ) );

		return array_map(
			function ( $row ) {
				$artist = ! empty( $row['artist_id'] ) ? AMC_DB::get_row( 'artists', (int) $row['artist_id'] ) : null;

				return array(
					'id'           => (int) $row['id'],
					'title'        => $row['title'],
					'slug'         => $row['slug'],
					'artist'       => $artist ? $artist['name'] : 'Unknown',
					'release_date' => $row['release_date'],
					'tracks'       => AMC_DB::count_rows( 'tracks', array( 'album_id' => (int) $row['id'] ) ),
					'genre'        => $row['genre'],
					'label'        => $row['label'],
					'status'       => ucfirst( $row['status'] ),
					'raw_status'   => $row['status'],
					'artist_id'    => (int) $row['artist_id'],
					'description'  => $row['description'],
					'gradient'     => $row['gradient'],
				);
			},
			$rows
		);
	}

	/**
	 * Upload sources.
	 *
	 * @return array
	 */
	public static function uploads() {
		$rows = AMC_DB::get_rows( 'source_uploads', array( 'order_by' => 'id DESC' ) );

		return array_map(
			function ( $row ) {
				$user = ! empty( $row['uploader_id'] ) ? get_user_by( 'id', (int) $row['uploader_id'] ) : null;

				return array(
					'id'          => (int) $row['id'],
					'source'      => $row['source_name'],
					'upload_date' => $row['created_at'],
					'week'        => $row['chart_week'],
					'status'      => ucfirst( $row['file_status'] ),
					'raw_status'  => $row['file_status'],
					'row_count'   => (int) $row['row_count'],
					'preview'     => $row['error_message'] ? $row['error_message'] : $row['preview_text'],
					'uploader'    => $user ? $user->display_name : 'System',
					'file_name'   => $row['file_name'],
					'file_url'    => $row['file_url'],
					'error'       => $row['error_message'],
				);
			},
			$rows
		);
	}

	/**
	 * Matching data.
	 *
	 * @return array
	 */
	public static function matching_candidates() {
		$rows = AMC_DB::get_rows( 'matching_queue', array( 'order_by' => 'id DESC' ) );

		return array_map(
			function ( $row ) {
				$source_row = AMC_DB::get_row( 'source_rows', (int) $row['source_row_id'] );
				$candidate  = trim( ( ! empty( $source_row['normalized_title'] ) ? $source_row['normalized_title'] : 'Unknown title' ) . ' / ' . ( ! empty( $source_row['normalized_artist'] ) ? $source_row['normalized_artist'] : 'Unknown artist' ), ' /' );
				$upload     = AMC_DB::get_row( 'source_uploads', (int) $row['upload_id'] );

				return array(
					'id'         => (int) $row['id'],
					'candidate'  => $candidate,
					'type'       => ucfirst( $row['entity_type'] ),
					'confidence' => $row['confidence'] . '%',
					'sources'    => $upload ? $upload['source_name'] : 'Unknown source',
					'status'     => ucwords( str_replace( '_', ' ', $row['status'] ) ),
					'raw_status' => $row['status'],
					'queue'      => $row,
				);
			},
			$rows
		);
	}

	/**
	 * Scoring data.
	 *
	 * @return array
	 */
	public static function scoring() {
		return AMC_Ingestion::get_scoring_rules();
	}

	/**
	 * Publishing preview data.
	 *
	 * @return array
	 */
	public static function publishing_preview() {
		$weeks = AMC_DB::get_chart_weeks();
		$week  = ! empty( $weeks[0] ) ? $weeks[0] : null;

		if ( ! $week ) {
			return array(
				'current_week' => 'No chart week available',
				'comparison'   => array(),
				'actions'      => array( 'Create Draft Week' ),
			);
		}

		$entries = AMC_DB::get_chart_entries( (int) $week['id'] );
		$new     = 0;
		$hidden  = 0;
		$jump    = 0;

		foreach ( $entries as $entry ) {
			if ( 'new' === $entry['movement'] ) {
				++$new;
			}

			if ( absint( $entry['previous_rank'] ) > 0 ) {
				$jump = max( $jump, absint( $entry['previous_rank'] ) - absint( $entry['current_rank'] ) );
			}

			if ( empty( $entry['artwork'] ) ) {
				++$hidden;
			}
		}

		return array(
			'current_week'   => 'Week of ' . wp_date( 'F j, Y', strtotime( $week['week_date'] ) ),
			'comparison'     => array(
				array( 'metric' => 'New entries', 'value' => (string) $new ),
				array( 'metric' => 'Biggest jump', 'value' => $jump ? '+' . $jump . ' positions' : 'No upward movement' ),
				array( 'metric' => 'Fallback artwork', 'value' => (string) $hidden ),
				array( 'metric' => 'Status', 'value' => ucfirst( $week['status'] ) ),
			),
			'actions'        => array( 'Preview Draft', 'Compare With Previous Week', 'Publish Week', 'Unpublish Week', 'Feature On Homepage' ),
		);
	}

	/**
	 * Archive sample.
	 *
	 * @return array
	 */
	public static function archives() {
		$weeks  = AMC_DB::get_chart_weeks();
		$charts = self::chart_lookup();

		return array_map(
			function ( $row ) use ( $charts ) {
				return array(
					'id'      => (int) $row['id'],
					'week'    => $row['week_date'],
					'chart'   => ! empty( $charts[ $row['chart_id'] ] ) ? $charts[ $row['chart_id'] ]['name'] : 'Unknown chart',
					'charts'  => 1,
					'status'  => ucfirst( $row['status'] ),
					'notes'   => $row['notes'],
					'actions' => 'archived' === $row['status'] ? 'Restore' : 'Archive',
				);
			},
			$weeks
		);
	}

	/**
	 * User roles.
	 *
	 * @return array
	 */
	public static function users() {
		global $wp_roles;

		$map = array(
			'administrator'   => 'Full control',
			'editor'          => 'Edit charts, library, weeks, publish',
			'amc_data_manager'=> 'Charts, library, weeks',
			'amc_viewer'      => 'Read-only dashboard visibility',
		);

		$rows = array();

		foreach ( $map as $role_key => $label ) {
			$role = isset( $wp_roles->roles[ $role_key ] ) ? $wp_roles->roles[ $role_key ] : null;

			if ( ! $role ) {
				continue;
			}

			$rows[] = array(
				'role'        => $role['name'],
				'permissions' => $label,
				'members'     => count( get_users( array( 'role' => $role_key ) ) ),
			);
		}

		return $rows;
	}

	/**
	 * Settings sample values.
	 *
	 * @return array
	 */
	public static function settings() {
		$settings = AMC_DB::get_settings();

		return array(
			'Platform name'   => $settings['platform_name'],
			'Logo'            => $settings['logo'],
			'SEO defaults'    => $settings['seo_defaults'],
			'Social image'    => $settings['social_image'],
			'Homepage chart'  => $settings['homepage_chart'],
			'Methodology text'=> $settings['methodology_text'],
			'Language'        => $settings['language'],
			'Date format'     => $settings['date_format'],
		);
	}

	/**
	 * Logs data.
	 *
	 * @return array
	 */
	public static function logs() {
		$rows = AMC_DB::get_rows( 'ingestion_logs', array( 'order_by' => 'id DESC', 'limit' => 20 ) );

		return array_map(
			function ( $row ) {
				return array(
					'time'   => $row['created_at'],
					'event'  => $row['action'] . ': ' . $row['message'],
					'actor'  => 'System',
					'status' => ucfirst( $row['level'] ),
				);
			},
			$rows
		);
	}

	/**
	 * Tools data.
	 *
	 * @return array
	 */
	public static function tools() {
		return array(
			array( 'tool' => 'Rebuild seeded admin previews', 'description' => 'Refresh UI demo datasets for dashboard screens.', 'action' => 'Run preview rebuild' ),
			array( 'tool' => 'Flush dashboard routes', 'description' => 'Re-register public and dashboard routes after structural changes.', 'action' => 'Flush routes' ),
			array( 'tool' => 'Export UI snapshot', 'description' => 'Generate a management-state export for review.', 'action' => 'Export snapshot' ),
		);
	}

	/**
	 * Permission presets.
	 *
	 * @return array
	 */
	public static function permissions() {
		return array(
			array( 'role' => 'Administrator', 'dashboard_access' => 'Full', 'publishing' => 'Allowed', 'settings' => 'Allowed' ),
			array( 'role' => 'Editor', 'dashboard_access' => 'Operational dashboard', 'publishing' => 'Allowed', 'settings' => 'Restricted' ),
			array( 'role' => 'Data Manager', 'dashboard_access' => 'Charts, library, weeks', 'publishing' => 'Restricted', 'settings' => 'Restricted' ),
			array( 'role' => 'Viewer', 'dashboard_access' => 'Read only', 'publishing' => 'Blocked', 'settings' => 'Blocked' ),
		);
	}

	/**
	 * Entry rows for a given chart week.
	 *
	 * @param int $week_id Week id.
	 * @return array
	 */
	public static function entries_for_week( $week_id ) {
		$entries = AMC_DB::get_chart_entries( $week_id );

		return array_map(
			function ( $row ) {
				$entity = AMC_Data::get_entity( $row['entity_type'], (int) $row['entity_id'] );

				return array(
					'id'       => (int) $row['id'],
					'rank'     => (int) $row['current_rank'],
					'item'     => $entity ? $entity['name'] : 'Missing item',
					'linked'   => ! empty( $entity['artist']['name'] ) ? $entity['artist']['name'] : ( ! empty( $entity['country'] ) ? $entity['country'] : ucfirst( $row['entity_type'] ) ),
					'previous' => (int) $row['previous_rank'],
					'peak'     => (int) $row['peak_rank'],
					'weeks'    => (int) $row['weeks_on_chart'],
					'movement' => ucfirst( $row['movement'] ),
					'score'    => (string) $row['score'],
					'status'   => $entity ? 'Connected' : 'Missing',
					'entity_type' => $row['entity_type'],
					'entity_id'   => (int) $row['entity_id'],
					'artwork'     => $row['artwork'],
				);
			},
			$entries
		);
	}

	/**
	 * Simple chart id lookup.
	 *
	 * @return array
	 */
	private static function chart_lookup() {
		$rows = AMC_DB::get_rows( 'charts', array( 'order_by' => 'display_order ASC, id ASC' ) );
		$out  = array();

		foreach ( $rows as $row ) {
			$out[ $row['id'] ] = $row;
		}

		return $out;
	}

	/**
	 * Count label helper.
	 *
	 * @param string $table Table key.
	 * @param string $status Status.
	 * @param string $label Label.
	 * @return string
	 */
	private static function count_label( $table, $status, $label ) {
		return AMC_DB::count_rows( $table, array( 'status' => $status ) ) . ' ' . $label;
	}
}
