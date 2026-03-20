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
	 * Overview counts.
	 *
	 * @return array
	 */
	public static function overview_cards() {
		return array(
			array( 'label' => 'Total Charts', 'value' => '12', 'delta' => '+2 prepared', 'tone' => 'gold' ),
			array( 'label' => 'Tracks In Library', 'value' => '4,286', 'delta' => '318 pending review', 'tone' => 'violet' ),
			array( 'label' => 'Artists In Library', 'value' => '1,042', 'delta' => '41 unmatched', 'tone' => 'blue' ),
			array( 'label' => 'Albums In Library', 'value' => '612', 'delta' => '27 hidden', 'tone' => 'emerald' ),
		);
	}

	/**
	 * Recent uploads.
	 *
	 * @return array
	 */
	public static function recent_uploads() {
		return array(
			array( 'source' => 'Spotify', 'chart_week' => '2026-03-20', 'status' => 'Processed', 'rows' => 1220, 'uploader' => 'Mina Farid' ),
			array( 'source' => 'YouTube Music', 'chart_week' => '2026-03-20', 'status' => 'Pending Match Review', 'rows' => 1188, 'uploader' => 'Dalia Hassan' ),
			array( 'source' => 'TikTok', 'chart_week' => '2026-03-20', 'status' => 'Preview Ready', 'rows' => 680, 'uploader' => 'Ramy Adel' ),
			array( 'source' => 'Shazam', 'chart_week' => '2026-03-20', 'status' => 'Duplicate Alerts', 'rows' => 510, 'uploader' => 'Nada Samir' ),
		);
	}

	/**
	 * Alerts.
	 *
	 * @return array
	 */
	public static function alerts() {
		return array(
			array( 'title' => 'Duplicate track candidates', 'body' => '18 likely duplicates need manual review before the next publishing cycle.', 'tone' => 'warning' ),
			array( 'title' => 'Missing artwork', 'body' => '9 entries in the current Hot 100 preview are still using fallback covers.', 'tone' => 'danger' ),
			array( 'title' => 'Unmatched upload rows', 'body' => '42 rows from YouTube Music and Shazam are waiting for artist or track resolution.', 'tone' => 'info' ),
		);
	}

	/**
	 * Week status.
	 *
	 * @return array
	 */
	public static function chart_week_status() {
		return array(
			array( 'label' => 'Published Weeks', 'value' => 24 ),
			array( 'label' => 'Draft Weeks', 'value' => 3 ),
			array( 'label' => 'Archived Weeks', 'value' => 68 ),
		);
	}

	/**
	 * Chart categories.
	 *
	 * @return array
	 */
	public static function chart_categories() {
		return array(
			array( 'name' => 'Top Artists', 'slug' => 'top-artists', 'type' => 'Artists', 'display_order' => 1, 'active' => 'Active', 'featured' => 'Yes', 'archive' => 'Enabled' ),
			array( 'name' => 'Top Tracks', 'slug' => 'top-tracks', 'type' => 'Tracks', 'display_order' => 2, 'active' => 'Active', 'featured' => 'Yes', 'archive' => 'Enabled' ),
			array( 'name' => 'Top Albums', 'slug' => 'top-albums', 'type' => 'Albums', 'display_order' => 3, 'active' => 'Active', 'featured' => 'No', 'archive' => 'Enabled' ),
			array( 'name' => 'Hot 100 Tracks', 'slug' => 'hot-100-tracks', 'type' => 'Tracks', 'display_order' => 4, 'active' => 'Active', 'featured' => 'Yes', 'archive' => 'Enabled' ),
			array( 'name' => 'Hot 100 Artists', 'slug' => 'hot-100-artists', 'type' => 'Artists', 'display_order' => 5, 'active' => 'Active', 'featured' => 'No', 'archive' => 'Enabled' ),
			array( 'name' => 'Top New Voices', 'slug' => 'top-new-voices', 'type' => 'Artists', 'display_order' => 6, 'active' => 'Hidden', 'featured' => 'No', 'archive' => 'Disabled' ),
		);
	}

	/**
	 * Weekly entries sample.
	 *
	 * @return array
	 */
	public static function weekly_entries() {
		return array(
			array( 'rank' => 1, 'item' => 'Shabab El Layl', 'linked' => 'Nancy Ajram', 'previous' => 2, 'peak' => 1, 'weeks' => 15, 'movement' => 'Up', 'score' => '97.4', 'status' => 'Preview Ready' ),
			array( 'rank' => 2, 'item' => 'Dorak Gai', 'linked' => 'Wegz', 'previous' => 1, 'peak' => 1, 'weeks' => 16, 'movement' => 'Down', 'score' => '96.2', 'status' => 'Preview Ready' ),
			array( 'rank' => 3, 'item' => 'Baheb El Bahr', 'linked' => 'Amr Diab', 'previous' => 5, 'peak' => 3, 'weeks' => 10, 'movement' => 'Up', 'score' => '93.8', 'status' => 'Draft' ),
			array( 'rank' => 4, 'item' => 'Maa Elsowar', 'linked' => 'Balqees', 'previous' => 8, 'peak' => 4, 'weeks' => 5, 'movement' => 'Up', 'score' => '91.0', 'status' => 'Draft' ),
			array( 'rank' => 5, 'item' => 'Ghorba', 'linked' => 'Marwan Pablo', 'previous' => 4, 'peak' => 4, 'weeks' => 9, 'movement' => 'Down', 'score' => '89.7', 'status' => 'Preview Ready' ),
		);
	}

	/**
	 * Tracks sample.
	 *
	 * @return array
	 */
	public static function tracks() {
		return array(
			array( 'title' => 'Shabab El Layl', 'artist' => 'Nancy Ajram', 'album' => 'Noor Nights', 'isrc' => 'EG-KTN-26-00001', 'aliases' => 'Shabab El Leil', 'release_date' => '2026-02-14', 'genre' => 'Regional Pop', 'status' => 'Active' ),
			array( 'title' => 'Dorak Gai', 'artist' => 'Wegz', 'album' => 'Standalone', 'isrc' => 'EG-KTN-26-00012', 'aliases' => 'Dork Gai', 'release_date' => '2026-01-28', 'genre' => 'Trap', 'status' => 'Active' ),
			array( 'title' => 'Akhbarak Eh', 'artist' => 'Elissa', 'album' => 'Letters In Neon', 'isrc' => 'LB-KTN-26-00005', 'aliases' => 'Akhbarak Eih', 'release_date' => '2026-02-05', 'genre' => 'Ballad', 'status' => 'Hidden' ),
		);
	}

	/**
	 * Artists sample.
	 *
	 * @return array
	 */
	public static function artists() {
		return array(
			array( 'name' => 'Nancy Ajram', 'country' => 'Lebanon', 'genre' => 'Regional Pop, Dance Pop', 'socials' => 'IG, YouTube, TikTok', 'related_tracks' => 6, 'related_albums' => 2, 'status' => 'Active' ),
			array( 'name' => 'Amr Diab', 'country' => 'Egypt', 'genre' => 'Mediterranean Pop', 'socials' => 'IG, YouTube', 'related_tracks' => 12, 'related_albums' => 5, 'status' => 'Active' ),
			array( 'name' => 'Marwan Pablo', 'country' => 'Egypt', 'genre' => 'Trap, Alternative Rap', 'socials' => 'IG, Spotify', 'related_tracks' => 8, 'related_albums' => 1, 'status' => 'Hidden' ),
		);
	}

	/**
	 * Albums sample.
	 *
	 * @return array
	 */
	public static function albums() {
		return array(
			array( 'title' => 'Noor Nights', 'artist' => 'Nancy Ajram', 'release_date' => '2026-02-01', 'tracks' => 13, 'genre' => 'Pop', 'label' => 'Kontentainment Music', 'status' => 'Active' ),
			array( 'title' => 'Parallel Lines', 'artist' => 'Marwan Pablo', 'release_date' => '2026-01-15', 'tracks' => 10, 'genre' => 'Rap', 'label' => 'Northwave', 'status' => 'Active' ),
			array( 'title' => 'Golden Room', 'artist' => 'Assala', 'release_date' => '2025-11-07', 'tracks' => 11, 'genre' => 'Tarab Pop', 'label' => 'Sada', 'status' => 'Hidden' ),
		);
	}

	/**
	 * Upload sources.
	 *
	 * @return array
	 */
	public static function uploads() {
		return array(
			array( 'source' => 'Spotify', 'upload_date' => '2026-03-19 09:14', 'week' => '2026-03-20', 'status' => 'Processed', 'row_count' => 1220, 'preview' => 'Top rows mapped', 'uploader' => 'Mina Farid' ),
			array( 'source' => 'Apple Music', 'upload_date' => '2026-03-19 09:26', 'week' => '2026-03-20', 'status' => 'Awaiting Validation', 'row_count' => 1140, 'preview' => '3 unmatched albums', 'uploader' => 'Mina Farid' ),
			array( 'source' => 'Anghami', 'upload_date' => '2026-03-19 10:01', 'week' => '2026-03-20', 'status' => 'Preview Ready', 'row_count' => 930, 'preview' => '12 duplicate candidates', 'uploader' => 'Dalia Hassan' ),
			array( 'source' => 'TikTok', 'upload_date' => '2026-03-19 10:45', 'week' => '2026-03-20', 'status' => 'Draft Preview', 'row_count' => 680, 'preview' => 'Trend velocity imported', 'uploader' => 'Nada Samir' ),
			array( 'source' => 'Shazam', 'upload_date' => '2026-03-19 11:10', 'week' => '2026-03-20', 'status' => 'Needs Match Review', 'row_count' => 510, 'preview' => '42 rows unresolved', 'uploader' => 'Ramy Adel' ),
			array( 'source' => 'YouTube Music', 'upload_date' => '2026-03-19 11:32', 'week' => '2026-03-20', 'status' => 'Processed', 'row_count' => 1188, 'preview' => 'All tracks ingested', 'uploader' => 'Dalia Hassan' ),
		);
	}

	/**
	 * Matching data.
	 *
	 * @return array
	 */
	public static function matching_candidates() {
		return array(
			array( 'candidate' => 'Shabab El Layl / Shabab El Leil', 'type' => 'Track', 'confidence' => '96%', 'sources' => 'Spotify, TikTok', 'status' => 'Needs Approval' ),
			array( 'candidate' => 'Wegz / Wegz.', 'type' => 'Artist', 'confidence' => '93%', 'sources' => 'Shazam, YouTube Music', 'status' => 'Needs Approval' ),
			array( 'candidate' => 'Nancy Ajram feat. Guest / Nancy Ajram', 'type' => 'Artist', 'confidence' => '81%', 'sources' => 'Apple Music', 'status' => 'Override Suggested' ),
		);
	}

	/**
	 * Scoring data.
	 *
	 * @return array
	 */
	public static function scoring() {
		return array(
			'weights' => array(
				array( 'source' => 'Spotify', 'weight' => '30%' ),
				array( 'source' => 'YouTube Music', 'weight' => '22%' ),
				array( 'source' => 'TikTok', 'weight' => '18%' ),
				array( 'source' => 'Shazam', 'weight' => '12%' ),
				array( 'source' => 'Apple Music', 'weight' => '10%' ),
				array( 'source' => 'Anghami', 'weight' => '8%' ),
			),
			'methodology' => array(
				'Minimum release age' => '3 days before chart cut-off',
				'Minimum source coverage' => 'At least 2 eligible sources',
				'Manual editorial override' => 'Allowed with approval note',
				'Catalog re-entry threshold' => '85 methodology score',
			),
		);
	}

	/**
	 * Publishing preview data.
	 *
	 * @return array
	 */
	public static function publishing_preview() {
		return array(
			'current_week'   => 'Week of March 20, 2026',
			'comparison'     => array(
				array( 'metric' => 'New entries', 'value' => '6' ),
				array( 'metric' => 'Biggest jump', 'value' => '+4 positions' ),
				array( 'metric' => 'Hidden rows', 'value' => '3' ),
				array( 'metric' => 'Manual overrides', 'value' => '2' ),
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
		return array(
			array( 'week' => '2026-03-13', 'charts' => 5, 'status' => 'Archived', 'notes' => 'Featured homepage week', 'actions' => 'Reopen / Restore' ),
			array( 'week' => '2026-03-06', 'charts' => 5, 'status' => 'Archived', 'notes' => '2 manual merges logged', 'actions' => 'Restore' ),
			array( 'week' => '2026-02-27', 'charts' => 5, 'status' => 'Published', 'notes' => 'Frozen snapshot', 'actions' => 'Archive / Reopen' ),
		);
	}

	/**
	 * User roles.
	 *
	 * @return array
	 */
	public static function users() {
		return array(
			array( 'role' => 'Admin', 'permissions' => 'Full control', 'members' => 2 ),
			array( 'role' => 'Editor', 'permissions' => 'Edit charts, publish weeks', 'members' => 3 ),
			array( 'role' => 'Data Manager', 'permissions' => 'Uploads, matching, scoring review', 'members' => 4 ),
			array( 'role' => 'Viewer', 'permissions' => 'Read-only visibility', 'members' => 5 ),
		);
	}

	/**
	 * Settings sample values.
	 *
	 * @return array
	 */
	public static function settings() {
		return array(
			'Platform name' => 'Kontentainment Charts',
			'Logo' => 'kontentainment-charts-mark.svg',
			'SEO defaults' => 'Enable chart-specific metadata',
			'Social image' => 'weekly-share-default.jpg',
			'Homepage chart' => 'Hot 100 Tracks',
			'Methodology text' => 'Custom weighted methodology summary',
			'Language' => 'English',
			'Date format' => 'F j, Y',
		);
	}
}
