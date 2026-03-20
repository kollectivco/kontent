<?php
/**
 * Upload, parsing, matching, scoring, generation, and publishing pipeline.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AMC_Ingestion {
	/**
	 * Ensure default scoring rules exist.
	 *
	 * @return void
	 */
	public static function ensure_defaults() {
		if ( AMC_DB::count_rows( 'scoring_rules' ) > 0 ) {
			return;
		}

		$defaults = array(
			array( 'rule_group' => 'weight', 'rule_key' => 'spotify_weight', 'rule_value' => '30', 'rule_type' => 'number', 'sort_order' => 1 ),
			array( 'rule_group' => 'weight', 'rule_key' => 'youtube_music_weight', 'rule_value' => '22', 'rule_type' => 'number', 'sort_order' => 2 ),
			array( 'rule_group' => 'weight', 'rule_key' => 'tiktok_weight', 'rule_value' => '18', 'rule_type' => 'number', 'sort_order' => 3 ),
			array( 'rule_group' => 'weight', 'rule_key' => 'shazam_weight', 'rule_value' => '12', 'rule_type' => 'number', 'sort_order' => 4 ),
			array( 'rule_group' => 'weight', 'rule_key' => 'apple_music_weight', 'rule_value' => '10', 'rule_type' => 'number', 'sort_order' => 5 ),
			array( 'rule_group' => 'weight', 'rule_key' => 'anghami_weight', 'rule_value' => '8', 'rule_type' => 'number', 'sort_order' => 6 ),
			array( 'rule_group' => 'methodology', 'rule_key' => 'minimum_release_age', 'rule_value' => '3 days before chart cut-off', 'rule_type' => 'text', 'sort_order' => 10 ),
			array( 'rule_group' => 'methodology', 'rule_key' => 'minimum_source_coverage', 'rule_value' => 'At least 2 eligible sources', 'rule_type' => 'text', 'sort_order' => 11 ),
			array( 'rule_group' => 'methodology', 'rule_key' => 'manual_editorial_override', 'rule_value' => 'Allowed with approval note', 'rule_type' => 'text', 'sort_order' => 12 ),
			array( 'rule_group' => 'methodology', 'rule_key' => 'catalog_reentry_threshold', 'rule_value' => '85 methodology score', 'rule_type' => 'text', 'sort_order' => 13 ),
		);

		foreach ( $defaults as $rule ) {
			AMC_DB::save_row( 'scoring_rules', $rule );
		}
	}

	/**
	 * Create upload and parse it.
	 *
	 * @param array $file File array.
	 * @param array $data Form data.
	 * @return int
	 */
	public static function create_upload( $file, $data ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$platform   = self::normalize_platform( isset( $data['source_platform'] ) ? $data['source_platform'] : '' );
		$chart_type = self::normalize_chart_type( isset( $data['chart_type'] ) ? $data['chart_type'] : '' );
		$country    = self::normalize_country( isset( $data['country'] ) ? $data['country'] : '' );
		$chart_date = ! empty( $data['chart_date'] ) ? sanitize_text_field( $data['chart_date'] ) : current_time( 'Y-m-d' );
		$chart_week = ! empty( $data['chart_week'] ) ? sanitize_text_field( $data['chart_week'] ) : $chart_date;
		$chart_id   = ! empty( $data['target_chart_id'] ) ? absint( $data['target_chart_id'] ) : 0;
		$chart      = $chart_id ? AMC_DB::get_row( 'charts', $chart_id ) : null;

		if ( ! $platform || ! $chart_type || ! $chart_id || ! $chart ) {
			return 0;
		}

		if ( ! empty( $chart['type'] ) && $chart['type'] !== $chart_type ) {
			return 0;
		}

		$upload = wp_handle_upload( $file, array( 'test_form' => false ) );

		if ( ! empty( $upload['error'] ) ) {
			return 0;
		}

		$upload_id = AMC_DB::save_row(
			'source_uploads',
			array(
				'source_name'       => self::platform_label( $platform ),
				'source_platform'   => $platform,
				'country'           => $country,
				'chart_week'        => $chart_week,
				'chart_date'        => $chart_date,
				'target_chart_id'   => $chart_id,
				'chart_type'        => $chart_type,
				'file_name'         => basename( $upload['file'] ),
				'file_path'         => $upload['file'],
				'file_url'          => $upload['url'],
				'mime_type'         => ! empty( $upload['type'] ) ? $upload['type'] : '',
				'file_size'         => file_exists( $upload['file'] ) ? filesize( $upload['file'] ) : 0,
				'file_status'       => 'uploaded',
				'generated_week_id' => 0,
				'row_count'         => 0,
				'preview_text'      => '',
				'uploader_id'       => get_current_user_id(),
				'parser_name'       => '',
				'error_message'     => '',
			)
		);

		if ( ! $upload_id ) {
			return 0;
		}

		self::log(
			$upload_id,
			0,
			'upload',
			'info',
			'Source file uploaded successfully.',
			array(
				'source_platform' => $platform,
				'country'         => $country,
				'chart_date'      => $chart_date,
				'target_chart_id' => $chart_id,
				'chart_type'      => $chart_type,
			)
		);

		if ( self::parse_upload( $upload_id ) ) {
			self::run_matching( $upload_id );
		}

		return $upload_id;
	}

	/**
	 * Parse an uploaded file.
	 *
	 * @param int $upload_id Upload id.
	 * @return bool
	 */
	public static function parse_upload( $upload_id ) {
		$upload = AMC_DB::get_row( 'source_uploads', $upload_id );

		if ( ! $upload ) {
			return false;
		}

		self::clear_upload_rows( $upload_id );

		$ext = strtolower( pathinfo( $upload['file_name'], PATHINFO_EXTENSION ) );

		if ( in_array( $ext, array( 'xlsx', 'xls' ), true ) ) {
			self::fail_upload( $upload_id, 'Spreadsheet parsing is not supported yet. Please upload CSV, TSV, or TXT exports only.', 'unsupported-spreadsheet' );
			return false;
		}

		if ( ! in_array( $ext, array( 'csv', 'txt', 'tsv' ), true ) ) {
			self::fail_upload( $upload_id, 'This file type is unsupported. Use CSV, TSV, or TXT exports.', 'unsupported-file' );
			return false;
		}

		$delimiter = 'tsv' === $ext ? "\t" : ',';
		$rows      = self::read_delimited_rows( $upload['file_path'], $delimiter );

		if ( empty( $rows ) ) {
			self::fail_upload( $upload_id, 'No parsable rows were found in the uploaded file.', 'empty-file' );
			return false;
		}

		$headers       = self::extract_headers( array_shift( $rows ), $upload['source_platform'] );
		$parser_result = self::parse_rows_for_upload( $upload, $headers, $rows );

		if ( empty( $parser_result['success'] ) ) {
			self::fail_upload( $upload_id, $parser_result['message'], $parser_result['parser_name'] );
			return false;
		}

		$count   = 0;
		$preview = array();

		foreach ( $parser_result['rows'] as $index => $mapped ) {
			$row_id = AMC_DB::save_row(
				'source_rows',
				array(
					'upload_id'            => $upload_id,
					'row_index'            => $index + 1,
					'raw_data'             => wp_json_encode( $mapped['raw'] ),
					'raw_row_json'         => wp_json_encode( $mapped['raw'] ),
					'chart_date'           => $mapped['chart_date'],
					'source_platform'      => $mapped['source_platform'],
					'country'              => $mapped['country'],
					'target_chart_id'      => $mapped['target_chart_id'],
					'chart_type'           => $mapped['chart_type'],
					'rank'                 => $mapped['rank'],
					'previous_rank'        => $mapped['previous_rank'],
					'peak_rank'            => $mapped['peak_rank'],
					'weeks_on_chart'       => $mapped['weeks_on_chart'],
					'track_title'          => $mapped['track_title'],
					'artist_name'          => $mapped['artist_name'],
					'artist_names'         => $mapped['artist_names'],
					'album_name'           => $mapped['album_name'],
					'source_metric_value'  => $mapped['source_metric_value'],
					'growth'               => $mapped['growth'],
					'source_url'           => $mapped['source_url'],
					'source_uri'           => $mapped['source_uri'],
					'normalized_title'     => $mapped['normalized_title'],
					'normalized_artist'    => $mapped['normalized_artist'],
					'normalized_album'     => $mapped['normalized_album'],
					'normalized_isrc'      => $mapped['normalized_isrc'],
					'normalized_rank'      => $mapped['rank'],
					'normalized_score'     => $mapped['source_metric_value'],
					'matched_entity_type'  => '',
					'matched_entity_id'    => 0,
					'matching_status'      => 'pending',
				)
			);

			if ( $row_id && count( $preview ) < 5 ) {
				$preview[] = self::preview_label_for_row( $mapped );
			}

			++$count;
		}

		AMC_DB::save_row(
			'source_uploads',
			array(
				'file_status'   => 'parsed',
				'row_count'     => $count,
				'preview_text'  => implode( ' | ', array_filter( $preview ) ),
				'parser_name'   => $parser_result['parser_name'],
				'error_message' => '',
			),
			$upload_id
		);

		self::log( $upload_id, 0, 'parse', 'info', 'Upload parsed into normalized source rows.', array( 'row_count' => $count, 'parser' => $parser_result['parser_name'] ) );

		return true;
	}

	/**
	 * Run matching for an upload.
	 *
	 * @param int $upload_id Upload id.
	 * @return void
	 */
	public static function run_matching( $upload_id ) {
		global $wpdb;

		$rows = AMC_DB::get_rows(
			'source_rows',
			array(
				'where'    => array( 'upload_id' => $upload_id ),
				'order_by' => 'row_index ASC',
			)
		);

		$wpdb->delete( AMC_DB::table( 'matching_queue' ), array( 'upload_id' => absint( $upload_id ) ) );

		foreach ( $rows as $row ) {
			$candidate = 'artist' === $row['chart_type'] ? self::find_artist_candidate( $row ) : self::find_track_candidate( $row );
			$type      = 'artist' === $row['chart_type'] ? 'artist' : 'track';
			$basis     = 'No exact candidate';
			$entity_id = 0;
			$label     = '';
			$confidence = 0;
			$status    = 'pending';

			if ( $candidate ) {
				$entity_id  = (int) $candidate['id'];
				$label      = 'artist' === $type ? $candidate['name'] : $candidate['title'];
				$basis      = 'Exact normalized metadata match';
				$confidence = 'artist' === $type ? 93 : 97;
				$status     = 'approved';

				AMC_DB::save_row(
					'source_rows',
					array(
						'matched_entity_type' => $type,
						'matched_entity_id'   => $entity_id,
						'matching_status'     => 'matched',
					),
					(int) $row['id']
				);
			}

			AMC_DB::save_row(
				'matching_queue',
				array(
					'upload_id'            => $upload_id,
					'source_row_id'        => (int) $row['id'],
					'entity_type'          => $type,
					'candidate_entity_id'  => $entity_id,
					'candidate_label'      => $label,
					'confidence'           => $confidence,
					'match_basis'          => $basis,
					'status'               => $status,
					'override_entity_type' => '',
					'override_entity_id'   => 0,
					'notes'                => '',
				)
			);
		}

		self::sync_upload_status_from_rows( $upload_id );
		self::log( $upload_id, 0, 'match', 'info', 'Matching queue generated for upload.', array( 'rows' => count( $rows ) ) );
	}

	/**
	 * Apply matching decision.
	 *
	 * @param int    $queue_id Queue id.
	 * @param string $decision Decision.
	 * @param array  $override Optional override payload.
	 * @return bool
	 */
	public static function apply_matching_decision( $queue_id, $decision, $override = array() ) {
		$queue = AMC_DB::get_row( 'matching_queue', $queue_id );

		if ( ! $queue ) {
			return false;
		}

		$source_row_id = (int) $queue['source_row_id'];
		$source_row    = AMC_DB::get_row( 'source_rows', $source_row_id );

		if ( ! $source_row ) {
			return false;
		}

		$status_map  = array(
			'approve'  => 'approved',
			'reject'   => 'rejected',
			'override' => 'override',
		);
		$new_status  = isset( $status_map[ $decision ] ) ? $status_map[ $decision ] : 'pending';
		$entity_type = ! empty( $override['entity_type'] ) ? sanitize_key( $override['entity_type'] ) : $queue['entity_type'];
		$entity_id   = ! empty( $override['entity_id'] ) ? absint( $override['entity_id'] ) : (int) $queue['candidate_entity_id'];

		AMC_DB::save_row(
			'matching_queue',
			array(
				'status'               => $new_status,
				'override_entity_type' => 'override' === $decision ? $entity_type : $queue['override_entity_type'],
				'override_entity_id'   => 'override' === $decision ? $entity_id : (int) $queue['override_entity_id'],
				'notes'                => ! empty( $override['notes'] ) ? sanitize_text_field( $override['notes'] ) : $queue['notes'],
			),
			$queue_id
		);

		if ( in_array( $decision, array( 'approve', 'override' ), true ) ) {
			AMC_DB::save_row(
				'source_rows',
				array(
					'matched_entity_type' => $entity_type,
					'matched_entity_id'   => $entity_id,
					'matching_status'     => 'matched',
				),
				$source_row_id
			);
		} elseif ( 'reject' === $decision ) {
			AMC_DB::save_row(
				'source_rows',
				array(
					'matched_entity_type' => '',
					'matched_entity_id'   => 0,
					'matching_status'     => 'rejected',
				),
				$source_row_id
			);
		}

		self::sync_upload_status_from_rows( (int) $queue['upload_id'] );
		self::log( (int) $queue['upload_id'], $source_row_id, 'matching_decision', 'info', 'Matching queue decision stored.', array( 'decision' => $decision ) );
		return true;
	}

	/**
	 * Generate chart week from a specific upload's metadata group.
	 *
	 * @param int $upload_id Upload id.
	 * @return array
	 */
	public static function generate_chart_for_upload( $upload_id ) {
		$upload = AMC_DB::get_row( 'source_uploads', $upload_id );

		if ( ! $upload ) {
			return array( 'success' => false, 'message' => 'Upload could not be found.' );
		}

		return self::generate_chart_week(
			(int) $upload['target_chart_id'],
			$upload['country'],
			$upload['chart_date'],
			$upload['chart_type']
		);
	}

	/**
	 * Generate or regenerate a draft chart week.
	 *
	 * @param int    $chart_id Chart id.
	 * @param string $country Country.
	 * @param string $chart_date Chart date.
	 * @param string $chart_type Chart type.
	 * @return array
	 */
	public static function generate_chart_week( $chart_id, $country, $chart_date, $chart_type ) {
		global $wpdb;

		$chart_id   = absint( $chart_id );
		$country    = self::normalize_country( $country );
		$chart_date = sanitize_text_field( $chart_date );
		$chart_type = self::normalize_chart_type( $chart_type );
		$chart      = AMC_DB::get_row( 'charts', $chart_id );

		if ( ! $chart || ! $chart_type ) {
			return array( 'success' => false, 'message' => 'Chart metadata is incomplete for generation.' );
		}

		$uploads_table = AMC_DB::table( 'source_uploads' );
		$rows_table    = AMC_DB::table( 'source_rows' );
		$sql           = $wpdb->prepare(
			"SELECT r.*, u.id AS upload_id_ref, u.source_platform, u.country, u.chart_date, u.target_chart_id, u.chart_type
			FROM {$rows_table} r
			INNER JOIN {$uploads_table} u ON u.id = r.upload_id
			WHERE u.target_chart_id = %d
				AND u.country = %s
				AND u.chart_date = %s
				AND u.chart_type = %s
				AND r.matching_status = %s
			ORDER BY r.rank ASC, r.id ASC",
			$chart_id,
			$country,
			$chart_date,
			$chart_type,
			'matched'
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows          = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( empty( $rows ) ) {
			self::log( 0, 0, 'generation', 'warning', 'No approved rows were available for generation.', array( 'chart_id' => $chart_id, 'country' => $country, 'chart_date' => $chart_date ) );
			return array( 'success' => false, 'message' => 'No approved matched rows were found for the selected chart, country, and week.' );
		}

		$aggregate           = self::aggregate_generation_rows( $rows );
		$ranked              = self::rank_aggregate_rows( $aggregate );
		$previous_week       = self::get_previous_chart_week( $chart_id, $country, $chart_date );
		$previous_entries    = $previous_week ? AMC_DB::get_chart_entries( (int) $previous_week['id'] ) : array();
		$comparison          = self::build_trend_payload( $ranked, $previous_entries, $chart_id, $country );
		$current_week        = self::find_or_create_chart_week( $chart_id, $country, $chart_date );
		$current_week_id     = (int) $current_week['id'];
		$generated_from_rows = count( $rows );

		AMC_DB::delete_chart_week_entries( $current_week_id );

		foreach ( $comparison['entries'] as $entry ) {
			AMC_DB::save_row(
				'chart_entries',
				array(
					'chart_week_id'  => $current_week_id,
					'entity_type'    => $chart_type,
					'entity_id'      => $entry['entity_id'],
					'current_rank'   => $entry['current_rank'],
					'previous_rank'  => $entry['previous_rank'],
					'peak_rank'      => $entry['peak_rank'],
					'weeks_on_chart' => $entry['weeks_on_chart'],
					'movement'       => $entry['movement'],
					'score'          => $entry['score'],
					'score_change'   => $entry['score_change'],
					'source_count'   => $entry['source_count'],
					'artwork'        => self::resolve_entry_artwork( $chart_type, $entry['entity_id'] ),
				)
			);
		}

		AMC_DB::save_row(
			'chart_weeks',
			array(
				'status'       => 'draft',
				'published_at' => null,
				'archived_at'  => null,
				'notes'        => sprintf( 'Generated from %1$d approved source rows across %2$d uploads.', $generated_from_rows, count( $comparison['upload_ids'] ) ),
			),
			$current_week_id
		);

		foreach ( $comparison['upload_ids'] as $upload_id ) {
			AMC_DB::save_row(
				'source_uploads',
				array(
					'file_status'       => 'generated',
					'generated_week_id' => $current_week_id,
					'error_message'     => '',
				),
				(int) $upload_id
			);

			self::log( (int) $upload_id, 0, 'generation', 'info', 'Upload rows were used to generate a draft chart week.', array( 'chart_week_id' => $current_week_id ) );
		}

		self::log(
			0,
			0,
			'generation',
			'info',
			'Chart week generated successfully.',
			array(
				'chart_week_id'  => $current_week_id,
				'chart_id'       => $chart_id,
				'country'        => $country,
				'chart_date'     => $chart_date,
				'entries'        => count( $comparison['entries'] ),
				'dropped_out'    => count( $comparison['dropped_out'] ),
				'previous_week'  => $previous_week ? (int) $previous_week['id'] : 0,
			)
		);

		return array(
			'success'      => true,
			'week_id'      => $current_week_id,
			'entries'      => $comparison['entries'],
			'dropped_out'  => $comparison['dropped_out'],
			'chart_date'   => $chart_date,
			'country'      => $country,
			'chart_id'     => $chart_id,
			'chart_type'   => $chart_type,
			'message'      => 'Draft chart week generated successfully.',
		);
	}

	/**
	 * Publish a chart week and mark its source uploads live.
	 *
	 * @param int $week_id Week id.
	 * @return bool
	 */
	public static function publish_chart_week( $week_id ) {
		$week = AMC_DB::get_row( 'chart_weeks', $week_id );

		if ( ! $week ) {
			return false;
		}

		AMC_DB::save_row(
			'chart_weeks',
			array(
				'status'       => 'published',
				'published_at' => current_time( 'mysql' ),
				'archived_at'  => null,
			),
			$week_id
		);

		self::set_uploads_status_for_week( $week_id, 'published' );
		self::log( 0, 0, 'publishing', 'info', 'Chart week published.', array( 'chart_week_id' => (int) $week_id ) );
		return true;
	}

	/**
	 * Unpublish a chart week.
	 *
	 * @param int $week_id Week id.
	 * @return bool
	 */
	public static function unpublish_chart_week( $week_id ) {
		$week = AMC_DB::get_row( 'chart_weeks', $week_id );

		if ( ! $week ) {
			return false;
		}

		AMC_DB::save_row(
			'chart_weeks',
			array(
				'status'       => 'draft',
				'published_at' => null,
			),
			$week_id
		);

		self::set_uploads_status_for_week( $week_id, 'generated' );
		self::log( 0, 0, 'publishing', 'info', 'Chart week unpublished back to draft.', array( 'chart_week_id' => (int) $week_id ) );
		return true;
	}

	/**
	 * Archive a chart week.
	 *
	 * @param int $week_id Week id.
	 * @return bool
	 */
	public static function archive_chart_week( $week_id ) {
		$week = AMC_DB::get_row( 'chart_weeks', $week_id );

		if ( ! $week ) {
			return false;
		}

		AMC_DB::save_row(
			'chart_weeks',
			array(
				'status'      => 'archived',
				'archived_at' => current_time( 'mysql' ),
			),
			$week_id
		);

		self::set_uploads_status_for_week( $week_id, 'generated' );
		self::log( 0, 0, 'publishing', 'info', 'Chart week archived.', array( 'chart_week_id' => (int) $week_id ) );
		return true;
	}

	/**
	 * Restore an archived chart week to draft.
	 *
	 * @param int $week_id Week id.
	 * @return bool
	 */
	public static function restore_chart_week( $week_id ) {
		$week = AMC_DB::get_row( 'chart_weeks', $week_id );

		if ( ! $week ) {
			return false;
		}

		AMC_DB::save_row(
			'chart_weeks',
			array(
				'status'      => 'draft',
				'archived_at' => null,
			),
			$week_id
		);

		self::set_uploads_status_for_week( $week_id, 'generated' );
		self::log( 0, 0, 'publishing', 'info', 'Archived chart week restored to draft.', array( 'chart_week_id' => (int) $week_id ) );
		return true;
	}

	/**
	 * Get preview rows for an upload.
	 *
	 * @param int $upload_id Upload id.
	 * @param int $limit Limit.
	 * @return array
	 */
	public static function preview_rows( $upload_id, $limit = 8 ) {
		return AMC_DB::get_rows(
			'source_rows',
			array(
				'where'    => array( 'upload_id' => absint( $upload_id ) ),
				'order_by' => 'row_index ASC',
				'limit'    => $limit,
			)
		);
	}

	/**
	 * Get scoring rules grouped for the admin UI.
	 *
	 * @return array
	 */
	public static function get_scoring_rules() {
		$rules = AMC_DB::get_rows( 'scoring_rules', array( 'order_by' => 'sort_order ASC, id ASC' ) );
		$out   = array(
			'weights'     => array(),
			'methodology' => array(),
		);

		foreach ( $rules as $rule ) {
			if ( 'weight' === $rule['rule_group'] ) {
				$out['weights'][] = array(
					'id'     => (int) $rule['id'],
					'source' => self::humanize_rule_key( $rule['rule_key'] ),
					'key'    => $rule['rule_key'],
					'weight' => $rule['rule_value'] . '%',
					'value'  => $rule['rule_value'],
				);
			} elseif ( 'methodology' === $rule['rule_group'] ) {
				$out['methodology'][ self::humanize_rule_key( $rule['rule_key'] ) ] = $rule['rule_value'];
			}
		}

		return $out;
	}

	/**
	 * Save scoring rules.
	 *
	 * @param array $payload Form payload.
	 * @return void
	 */
	public static function save_scoring_rules( $payload ) {
		$current         = self::get_scoring_rules();
		$current_weights = array();

		foreach ( $current['weights'] as $weight ) {
			$current_weights[ $weight['key'] ] = $weight['value'];
		}

		$weights = array(
			'spotify_weight',
			'youtube_music_weight',
			'tiktok_weight',
			'shazam_weight',
			'apple_music_weight',
			'anghami_weight',
		);

		foreach ( $weights as $index => $key ) {
			$value = isset( $payload[ $key ] ) ? sanitize_text_field( wp_unslash( $payload[ $key ] ) ) : ( isset( $current_weights[ $key ] ) ? $current_weights[ $key ] : '0' );
			self::upsert_rule( 'weight', $key, $value, 'number', $index + 1 );
		}

		$methods = array(
			'minimum_release_age',
			'minimum_source_coverage',
			'manual_editorial_override',
			'catalog_reentry_threshold',
		);

		foreach ( $methods as $index => $key ) {
			$fallback = isset( $current['methodology'][ self::humanize_rule_key( $key ) ] ) ? $current['methodology'][ self::humanize_rule_key( $key ) ] : '';
			$value    = isset( $payload[ $key ] ) ? sanitize_textarea_field( wp_unslash( $payload[ $key ] ) ) : $fallback;
			self::upsert_rule( 'methodology', $key, $value, 'text', 10 + $index );
		}
	}

	/**
	 * Log ingestion event.
	 *
	 * @param int    $upload_id Upload id.
	 * @param int    $source_row_id Row id.
	 * @param string $action Action.
	 * @param string $level Level.
	 * @param string $message Message.
	 * @param array  $context Context.
	 * @return void
	 */
	public static function log( $upload_id, $source_row_id, $action, $level, $message, $context = array() ) {
		AMC_DB::save_row(
			'ingestion_logs',
			array(
				'upload_id'     => absint( $upload_id ),
				'source_row_id' => absint( $source_row_id ),
				'action'        => sanitize_key( $action ),
				'level'         => sanitize_key( $level ),
				'message'       => $message,
				'context'       => wp_json_encode( $context ),
			)
		);
	}

	/**
	 * Parse rows for a specific upload source.
	 *
	 * @param array $upload Upload record.
	 * @param array $headers Headers.
	 * @param array $rows Raw rows.
	 * @return array
	 */
	private static function parse_rows_for_upload( $upload, $headers, $rows ) {
		$platform   = self::normalize_platform( $upload['source_platform'] );
		$chart_type = self::normalize_chart_type( $upload['chart_type'] );
		$context    = array(
			'chart_date'      => $upload['chart_date'],
			'source_platform' => $platform,
			'country'         => $upload['country'],
			'target_chart_id' => (int) $upload['target_chart_id'],
			'chart_type'      => $chart_type,
		);

		if ( 'youtube' === $platform && 'artist' === $chart_type ) {
			return array(
				'success'     => true,
				'parser_name' => 'youtube-top-artists-csv',
				'rows'        => self::parse_youtube_top_artists_rows( $headers, $rows, $context ),
			);
		}

		if ( 'youtube' === $platform && 'track' === $chart_type ) {
			return array(
				'success'     => true,
				'parser_name' => 'youtube-top-songs-csv',
				'rows'        => self::parse_youtube_top_song_rows( $headers, $rows, $context ),
			);
		}

		if ( 'spotify' === $platform && 'track' === $chart_type ) {
			return array(
				'success'     => true,
				'parser_name' => 'spotify-weekly-csv',
				'rows'        => self::parse_spotify_weekly_rows( $headers, $rows, $context ),
			);
		}

		if ( 'shazam' === $platform && 'track' === $chart_type ) {
			return array(
				'success'     => true,
				'parser_name' => 'shazam-chart-csv',
				'rows'        => self::parse_shazam_chart_rows( $headers, $rows, $context ),
			);
		}

		return array(
			'success'     => false,
			'parser_name' => 'unsupported-source-parser',
			'message'     => 'This source + chart type combination is not supported yet. Supported parsers currently cover YouTube Top Artists CSV, YouTube Top Songs CSV, Spotify weekly CSV, and Shazam chart CSV.',
		);
	}

	/**
	 * Parse YouTube top artists rows.
	 *
	 * @param array $headers Headers.
	 * @param array $rows Rows.
	 * @param array $context Context.
	 * @return array
	 */
	private static function parse_youtube_top_artists_rows( $headers, $rows, $context ) {
		$out = array();

		foreach ( $rows as $values ) {
			$raw         = self::build_raw_row( $headers, $values );
			$artist_name = self::first_value( $raw, array( 'artist', 'artist_name', 'name', 'channel', 'channel_name' ) );
			$rank        = self::integer_value( self::first_value( $raw, array( 'rank', 'position', 'current_rank' ) ) );

			if ( ! $artist_name || ! $rank ) {
				continue;
			}

			$out[] = self::build_normalized_row(
				$context,
				array(
					'raw'                 => $raw,
					'rank'                => $rank,
					'previous_rank'       => self::integer_value( self::first_value( $raw, array( 'previous_rank', 'last_rank', 'prior_rank' ) ) ),
					'peak_rank'           => self::integer_value( self::first_value( $raw, array( 'peak_rank', 'best_rank' ) ) ),
					'weeks_on_chart'      => self::integer_value( self::first_value( $raw, array( 'weeks_on_chart', 'weeks', 'woc' ) ) ),
					'artist_name'         => $artist_name,
					'source_metric_value' => self::numeric_value( self::first_value( $raw, array( 'views', 'weekly_views', 'video_views', 'source_metric_value' ) ) ),
					'growth'              => self::first_value( $raw, array( 'growth', 'growth_rate', 'change' ) ),
					'source_url'          => self::first_value( $raw, array( 'url', 'channel_url', 'source_url' ) ),
					'source_uri'          => self::first_value( $raw, array( 'uri', 'channel_id', 'source_uri' ) ),
				)
			);
		}

		return $out;
	}

	/**
	 * Parse YouTube top songs rows.
	 *
	 * @param array $headers Headers.
	 * @param array $rows Rows.
	 * @param array $context Context.
	 * @return array
	 */
	private static function parse_youtube_top_song_rows( $headers, $rows, $context ) {
		$out = array();

		foreach ( $rows as $values ) {
			$raw        = self::build_raw_row( $headers, $values );
			$track      = self::first_value( $raw, array( 'title', 'track_title', 'song', 'song_title', 'name' ) );
			$artist     = self::first_value( $raw, array( 'artist', 'artist_name', 'artist_names', 'performer' ) );
			$rank       = self::integer_value( self::first_value( $raw, array( 'rank', 'position', 'current_rank' ) ) );

			if ( ! $track || ! $artist || ! $rank ) {
				continue;
			}

			$out[] = self::build_normalized_row(
				$context,
				array(
					'raw'                 => $raw,
					'rank'                => $rank,
					'previous_rank'       => self::integer_value( self::first_value( $raw, array( 'previous_rank', 'last_rank', 'prior_rank' ) ) ),
					'peak_rank'           => self::integer_value( self::first_value( $raw, array( 'peak_rank', 'best_rank' ) ) ),
					'weeks_on_chart'      => self::integer_value( self::first_value( $raw, array( 'weeks_on_chart', 'weeks', 'woc' ) ) ),
					'track_title'         => $track,
					'artist_names'        => $artist,
					'album_name'          => self::first_value( $raw, array( 'album', 'album_name', 'release' ) ),
					'source_metric_value' => self::numeric_value( self::first_value( $raw, array( 'views', 'weekly_views', 'plays', 'streams', 'source_metric_value' ) ) ),
					'growth'              => self::first_value( $raw, array( 'growth', 'growth_rate', 'change' ) ),
					'source_url'          => self::first_value( $raw, array( 'url', 'video_url', 'source_url' ) ),
					'source_uri'          => self::first_value( $raw, array( 'uri', 'video_id', 'source_uri' ) ),
				)
			);
		}

		return $out;
	}

	/**
	 * Parse Spotify weekly rows.
	 *
	 * @param array $headers Headers.
	 * @param array $rows Rows.
	 * @param array $context Context.
	 * @return array
	 */
	private static function parse_spotify_weekly_rows( $headers, $rows, $context ) {
		$out = array();

		foreach ( $rows as $values ) {
			$raw    = self::build_raw_row( $headers, $values, array( 'source' ) );
			$track  = self::first_value( $raw, array( 'title', 'track_title', 'track_name', 'song', 'song_title', 'name' ) );
			$artist = self::first_value( $raw, array( 'artist', 'artist_name', 'artist_names' ) );
			$rank   = self::integer_value( self::first_value( $raw, array( 'rank', 'position', 'current_rank' ) ) );

			if ( ! $track || ! $artist ) {
				continue;
			}

			if ( ! $rank ) {
				$rank = count( $out ) + 1;
			}

			$out[] = self::build_normalized_row(
				$context,
				array(
					'raw'                 => $raw,
					'rank'                => $rank,
					'previous_rank'       => self::integer_value( self::first_value( $raw, array( 'previous_rank', 'last_rank', 'prior_rank' ) ) ),
					'peak_rank'           => self::integer_value( self::first_value( $raw, array( 'peak_rank', 'best_rank' ) ) ),
					'weeks_on_chart'      => self::integer_value( self::first_value( $raw, array( 'weeks_on_chart', 'weeks', 'woc' ) ) ),
					'track_title'         => $track,
					'artist_names'        => $artist,
					'album_name'          => self::first_value( $raw, array( 'album', 'album_name' ) ),
					'source_metric_value' => self::numeric_value( self::first_value( $raw, array( 'streams', 'streams_total', 'plays', 'listeners' ) ) ),
					'growth'              => self::first_value( $raw, array( 'growth', 'growth_rate', 'change' ) ),
					'source_url'          => self::first_value( $raw, array( 'url', 'track_url', 'spotify_url' ) ),
					'source_uri'          => self::first_value( $raw, array( 'uri', 'spotify_uri' ) ),
					'normalized_isrc'     => strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', self::first_value( $raw, array( 'isrc', 'track_isrc' ) ) ) ),
				)
			);
		}

		return $out;
	}

	/**
	 * Parse Shazam chart rows.
	 *
	 * @param array $headers Headers.
	 * @param array $rows Rows.
	 * @param array $context Context.
	 * @return array
	 */
	private static function parse_shazam_chart_rows( $headers, $rows, $context ) {
		$out = array();

		foreach ( $rows as $values ) {
			$raw    = self::build_raw_row( $headers, $values );
			$track  = self::first_value( $raw, array( 'title', 'track_title', 'song', 'song_title', 'name' ) );
			$artist = self::first_value( $raw, array( 'artist', 'artist_name', 'subtitle', 'artist_names' ) );
			$rank   = self::integer_value( self::first_value( $raw, array( 'rank', 'position', 'current_rank' ) ) );

			if ( ! $track || ! $artist || ! $rank ) {
				continue;
			}

			$out[] = self::build_normalized_row(
				$context,
				array(
					'raw'                 => $raw,
					'rank'                => $rank,
					'previous_rank'       => self::integer_value( self::first_value( $raw, array( 'previous_rank', 'last_rank', 'prior_rank' ) ) ),
					'peak_rank'           => self::integer_value( self::first_value( $raw, array( 'peak_rank', 'best_rank' ) ) ),
					'weeks_on_chart'      => self::integer_value( self::first_value( $raw, array( 'weeks_on_chart', 'weeks', 'woc' ) ) ),
					'track_title'         => $track,
					'artist_names'        => $artist,
					'album_name'          => self::first_value( $raw, array( 'album', 'album_name' ) ),
					'source_metric_value' => self::numeric_value( self::first_value( $raw, array( 'shazams', 'count', 'source_metric_value', 'plays' ) ) ),
					'growth'              => self::first_value( $raw, array( 'growth', 'growth_rate', 'change' ) ),
					'source_url'          => self::first_value( $raw, array( 'url', 'track_url', 'source_url' ) ),
					'source_uri'          => self::first_value( $raw, array( 'uri', 'apple_music_uri', 'source_uri' ) ),
				)
			);
		}

		return $out;
	}

	/**
	 * Build a normalized row payload.
	 *
	 * @param array $context Upload context.
	 * @param array $payload Parsed fields.
	 * @return array
	 */
	private static function build_normalized_row( $context, $payload ) {
		$track_title      = ! empty( $payload['track_title'] ) ? trim( $payload['track_title'] ) : '';
		$artist_name      = ! empty( $payload['artist_name'] ) ? trim( $payload['artist_name'] ) : '';
		$artist_names     = ! empty( $payload['artist_names'] ) ? trim( $payload['artist_names'] ) : $artist_name;
		$album_name       = ! empty( $payload['album_name'] ) ? trim( $payload['album_name'] ) : '';
		$normalized_title = ! empty( $payload['normalized_title'] ) ? $payload['normalized_title'] : self::normalize_text( $track_title );
		$normalized_artist= ! empty( $payload['normalized_artist'] ) ? $payload['normalized_artist'] : self::normalize_text( $artist_name ? $artist_name : $artist_names );
		$normalized_album = ! empty( $payload['normalized_album'] ) ? $payload['normalized_album'] : self::normalize_text( $album_name );
		$chart_type       = $context['chart_type'];

		if ( 'artist' === $chart_type ) {
			$track_title = '';
			$artist_name = ! empty( $payload['artist_name'] ) ? trim( $payload['artist_name'] ) : trim( $artist_names );
			$artist_names = $artist_name;
			$album_name   = '';
			$normalized_title = '';
		}

		return array(
			'raw'                 => ! empty( $payload['raw'] ) ? $payload['raw'] : array(),
			'chart_date'          => $context['chart_date'],
			'source_platform'     => $context['source_platform'],
			'country'             => $context['country'],
			'target_chart_id'     => (int) $context['target_chart_id'],
			'chart_type'          => $chart_type,
			'rank'                => ! empty( $payload['rank'] ) ? absint( $payload['rank'] ) : 0,
			'previous_rank'       => ! empty( $payload['previous_rank'] ) ? absint( $payload['previous_rank'] ) : 0,
			'peak_rank'           => ! empty( $payload['peak_rank'] ) ? absint( $payload['peak_rank'] ) : 0,
			'weeks_on_chart'      => ! empty( $payload['weeks_on_chart'] ) ? absint( $payload['weeks_on_chart'] ) : 0,
			'track_title'         => $track_title,
			'artist_name'         => $artist_name,
			'artist_names'        => $artist_names,
			'album_name'          => $album_name,
			'source_metric_value' => ! empty( $payload['source_metric_value'] ) ? (float) $payload['source_metric_value'] : 0,
			'growth'              => ! empty( $payload['growth'] ) ? (string) $payload['growth'] : '',
			'source_url'          => ! empty( $payload['source_url'] ) ? esc_url_raw( $payload['source_url'] ) : '',
			'source_uri'          => ! empty( $payload['source_uri'] ) ? sanitize_text_field( $payload['source_uri'] ) : '',
			'normalized_title'    => $normalized_title,
			'normalized_artist'   => $normalized_artist,
			'normalized_album'    => $normalized_album,
			'normalized_isrc'     => ! empty( $payload['normalized_isrc'] ) ? $payload['normalized_isrc'] : '',
		);
	}

	/**
	 * Aggregate matched rows into entity-level scoring buckets.
	 *
	 * @param array $rows Matched rows.
	 * @return array
	 */
	private static function aggregate_generation_rows( $rows ) {
		$weights      = self::weight_lookup();
		$source_maxes = self::source_maxes( $rows );
		$aggregate    = array();

		foreach ( $rows as $row ) {
			$entity_id   = (int) $row['matched_entity_id'];
			$entity_type = $row['matched_entity_type'];

			if ( ! $entity_id || ! $entity_type ) {
				continue;
			}

			$key                = $entity_type . ':' . $entity_id;
			$platform           = self::normalize_platform( $row['source_platform'] );
			$weight             = isset( $weights[ $platform ] ) ? $weights[ $platform ] : 0;
			$platform_max       = isset( $source_maxes[ $platform ] ) ? $source_maxes[ $platform ] : 0;
			$normalized_metric  = self::normalize_metric( $row, $platform_max );
			$growth_bonus       = self::growth_bonus( $row['growth'] );
			$final_contribution = round( ( $normalized_metric + $growth_bonus ) * $weight, 4 );

			if ( empty( $aggregate[ $key ] ) ) {
				$aggregate[ $key ] = array(
					'entity_id'      => $entity_id,
					'entity_type'    => $entity_type,
					'score'          => 0,
					'raw_metric'     => 0,
					'source_count'   => 0,
					'best_rank'      => 9999,
					'upload_ids'     => array(),
					'platforms'      => array(),
				);
			}

			$aggregate[ $key ]['score']        += $final_contribution;
			$aggregate[ $key ]['raw_metric']   += (float) $row['source_metric_value'];
			$aggregate[ $key ]['source_count'] += 1;
			$aggregate[ $key ]['best_rank']     = min( $aggregate[ $key ]['best_rank'], absint( $row['rank'] ) );
			$aggregate[ $key ]['upload_ids'][]  = (int) $row['upload_id'];
			$aggregate[ $key ]['platforms'][]   = $platform;
		}

		return $aggregate;
	}

	/**
	 * Rank aggregate rows.
	 *
	 * @param array $aggregate Aggregate rows.
	 * @return array
	 */
	private static function rank_aggregate_rows( $aggregate ) {
		$items = array_values( $aggregate );

		usort(
			$items,
			function ( $a, $b ) {
				if ( (float) $a['score'] === (float) $b['score'] ) {
					if ( (int) $a['best_rank'] === (int) $b['best_rank'] ) {
						return (int) $a['entity_id'] <=> (int) $b['entity_id'];
					}
					return (int) $a['best_rank'] <=> (int) $b['best_rank'];
				}

				return (float) $b['score'] <=> (float) $a['score'];
			}
		);

		foreach ( $items as $index => $item ) {
			$items[ $index ]['current_rank'] = $index + 1;
			$items[ $index ]['score']        = round( (float) $item['score'], 2 );
			$items[ $index ]['upload_ids']   = array_values( array_unique( array_filter( array_map( 'absint', $item['upload_ids'] ) ) ) );
			$items[ $index ]['platforms']    = array_values( array_unique( array_filter( $item['platforms'] ) ) );
		}

		return $items;
	}

	/**
	 * Build trend comparison against the previous saved week.
	 *
	 * @param array  $ranked Ranked rows.
	 * @param array  $previous_entries Previous entries.
	 * @param int    $chart_id Chart id.
	 * @param string $country Country.
	 * @return array
	 */
	private static function build_trend_payload( $ranked, $previous_entries, $chart_id, $country ) {
		$previous_map = array();
		$seen_keys    = array();
		$dropped_out  = array();
		$entries      = array();

		foreach ( $previous_entries as $entry ) {
			$previous_map[ $entry['entity_type'] . ':' . $entry['entity_id'] ] = $entry;
		}

		foreach ( $ranked as $item ) {
			$key           = $item['entity_type'] . ':' . $item['entity_id'];
			$previous      = isset( $previous_map[ $key ] ) ? $previous_map[ $key ] : null;
			$history       = self::get_entity_history( $chart_id, $country, $item['entity_type'], $item['entity_id'] );
			$seen_keys[]   = $key;
			$previous_rank = $previous ? (int) $previous['current_rank'] : 0;
			$movement      = 'same';

			if ( $previous ) {
				if ( $previous_rank > (int) $item['current_rank'] ) {
					$movement = 'up';
				} elseif ( $previous_rank < (int) $item['current_rank'] ) {
					$movement = 'down';
				}
			} elseif ( ! empty( $history['has_history'] ) ) {
				$movement = 're-entry';
			} else {
				$movement = 'new';
			}

			$peak_rank      = ! empty( $history['best_peak_rank'] ) ? min( (int) $item['current_rank'], (int) $history['best_peak_rank'] ) : (int) $item['current_rank'];
			$weeks_on_chart = $previous ? ( (int) $previous['weeks_on_chart'] + 1 ) : ( ! empty( $history['max_weeks_on_chart'] ) ? ( (int) $history['max_weeks_on_chart'] + 1 ) : 1 );
			$score_change   = $previous ? round( (float) $item['score'] - (float) $previous['score'], 2 ) : round( (float) $item['score'], 2 );

			$entries[] = array(
				'entity_id'      => (int) $item['entity_id'],
				'entity_type'    => $item['entity_type'],
				'current_rank'   => (int) $item['current_rank'],
				'previous_rank'  => $previous_rank,
				'peak_rank'      => $peak_rank,
				'weeks_on_chart' => $weeks_on_chart,
				'movement'       => $movement,
				'score'          => round( (float) $item['score'], 2 ),
				'score_change'   => $score_change,
				'source_count'   => (int) $item['source_count'],
			);
		}

		foreach ( $previous_entries as $entry ) {
			$key = $entry['entity_type'] . ':' . $entry['entity_id'];
			if ( ! in_array( $key, $seen_keys, true ) ) {
				$dropped_out[] = $key;
			}
		}

		return array(
			'entries'    => $entries,
			'dropped_out'=> $dropped_out,
			'upload_ids' => self::collect_upload_ids( $ranked ),
		);
	}

	/**
	 * Get previous saved chart week by chart/country/date.
	 *
	 * @param int    $chart_id Chart id.
	 * @param string $country Country.
	 * @param string $chart_date Date.
	 * @return array|null
	 */
	private static function get_previous_chart_week( $chart_id, $country, $chart_date ) {
		global $wpdb;

		$table = AMC_DB::table( 'chart_weeks' );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE chart_id = %d AND country = %s AND week_date < %s ORDER BY week_date DESC, id DESC LIMIT 1",
				absint( $chart_id ),
				$country,
				$chart_date
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $row ? $row : null;
	}

	/**
	 * Find or create a chart week row.
	 *
	 * @param int    $chart_id Chart id.
	 * @param string $country Country.
	 * @param string $chart_date Date.
	 * @return array
	 */
	private static function find_or_create_chart_week( $chart_id, $country, $chart_date ) {
		global $wpdb;

		$table = AMC_DB::table( 'chart_weeks' );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE chart_id = %d AND country = %s AND week_date = %s LIMIT 1",
				absint( $chart_id ),
				$country,
				$chart_date
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $row ) {
			return $row;
		}

		$id = AMC_DB::save_row(
			'chart_weeks',
			array(
				'chart_id'      => absint( $chart_id ),
				'country'       => $country,
				'week_date'     => $chart_date,
				'status'        => 'draft',
				'is_featured'   => 0,
				'notes'         => 'Generated draft week',
				'published_at'  => null,
				'archived_at'   => null,
			)
		);

		return AMC_DB::get_row( 'chart_weeks', $id );
	}

	/**
	 * Set upload statuses for a week.
	 *
	 * @param int    $week_id Week id.
	 * @param string $status Status.
	 * @return void
	 */
	private static function set_uploads_status_for_week( $week_id, $status ) {
		foreach ( AMC_DB::get_rows( 'source_uploads', array( 'where' => array( 'generated_week_id' => absint( $week_id ) ) ) ) as $upload ) {
			AMC_DB::save_row( 'source_uploads', array( 'file_status' => sanitize_key( $status ) ), (int) $upload['id'] );
		}
	}

	/**
	 * Sync upload status from row matching state.
	 *
	 * @param int $upload_id Upload id.
	 * @return void
	 */
	private static function sync_upload_status_from_rows( $upload_id ) {
		$rows         = AMC_DB::get_rows( 'source_rows', array( 'where' => array( 'upload_id' => absint( $upload_id ) ) ) );
		$matched      = 0;
		$pending      = 0;
		$rejected     = 0;

		foreach ( $rows as $row ) {
			if ( 'matched' === $row['matching_status'] ) {
				++$matched;
			} elseif ( 'rejected' === $row['matching_status'] ) {
				++$rejected;
			} else {
				++$pending;
			}
		}

		$status = 'parsed';

		if ( $matched > 0 && 0 === $pending ) {
			$status = 'matched';
		} elseif ( $matched > 0 ) {
			$status = 'matched';
		} elseif ( $rejected > 0 && 0 === $pending ) {
			$status = 'parsed';
		}

		AMC_DB::save_row( 'source_uploads', array( 'file_status' => $status ), $upload_id );
	}

	/**
	 * Read delimited rows.
	 *
	 * @param string $path File path.
	 * @param string $delimiter Delimiter.
	 * @return array
	 */
	private static function read_delimited_rows( $path, $delimiter ) {
		$rows = array();
		$fp   = fopen( $path, 'r' );

		if ( ! $fp ) {
			return $rows;
		}

		while ( false !== ( $data = fgetcsv( $fp, 0, $delimiter ) ) ) {
			if ( ! empty( $data[0] ) ) {
				$data[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $data[0] );
			}

			if ( array_filter( $data, 'strlen' ) ) {
				$rows[] = $data;
			}
		}

		fclose( $fp );
		return $rows;
	}

	/**
	 * Extract normalized headers.
	 *
	 * @param array  $header_row Header row.
	 * @param string $platform Platform.
	 * @return array
	 */
	private static function extract_headers( $header_row, $platform = '' ) {
		$out = array();

		foreach ( $header_row as $header ) {
			$key = sanitize_key( strtolower( str_replace( array( ' ', '-', '/' ), '_', trim( $header ) ) ) );

			if ( 'spotify' === self::normalize_platform( $platform ) && 'source' === $key ) {
				$out[] = '';
				continue;
			}

			$out[] = $key;
		}

		return $out;
	}

	/**
	 * Build raw row keyed by normalized headers.
	 *
	 * @param array $headers Headers.
	 * @param array $values Values.
	 * @param array $skip_keys Keys to skip.
	 * @return array
	 */
	private static function build_raw_row( $headers, $values, $skip_keys = array() ) {
		$raw = array();

		foreach ( $headers as $index => $header ) {
			if ( ! $header || in_array( $header, $skip_keys, true ) ) {
				continue;
			}

			$raw[ $header ] = isset( $values[ $index ] ) ? trim( (string) $values[ $index ] ) : '';
		}

		return $raw;
	}

	/**
	 * Clear upload-derived rows.
	 *
	 * @param int $upload_id Upload id.
	 * @return void
	 */
	private static function clear_upload_rows( $upload_id ) {
		global $wpdb;

		$rows = AMC_DB::get_rows( 'source_rows', array( 'where' => array( 'upload_id' => absint( $upload_id ) ) ) );

		foreach ( $rows as $row ) {
			$wpdb->delete( AMC_DB::table( 'matching_queue' ), array( 'source_row_id' => absint( $row['id'] ) ) );
		}

		$wpdb->delete( AMC_DB::table( 'source_rows' ), array( 'upload_id' => absint( $upload_id ) ) );
	}

	/**
	 * Find track candidate.
	 *
	 * @param array $row Source row.
	 * @return array|null
	 */
	private static function find_track_candidate( $row ) {
		$tracks = AMC_DB::get_rows( 'tracks', array( 'order_by' => 'id ASC' ) );

		foreach ( $tracks as $track ) {
			$title_match = self::normalize_text( $track['title'] ) === $row['normalized_title'];
			$artist      = ! empty( $track['artist_id'] ) ? AMC_DB::get_row( 'artists', (int) $track['artist_id'] ) : null;
			$artist_name = $artist ? self::normalize_text( $artist['name'] ) : '';
			$artist_match = empty( $row['normalized_artist'] ) || $artist_name === $row['normalized_artist'];

			if ( ! $title_match ) {
				continue;
			}

			if ( ! empty( $row['normalized_isrc'] ) && ! empty( $track['isrc'] ) && strtoupper( $track['isrc'] ) === strtoupper( $row['normalized_isrc'] ) ) {
				return $track;
			}

			if ( $artist_match ) {
				return $track;
			}
		}

		return null;
	}

	/**
	 * Find artist candidate.
	 *
	 * @param array $row Source row.
	 * @return array|null
	 */
	private static function find_artist_candidate( $row ) {
		$artists = AMC_DB::get_rows( 'artists', array( 'order_by' => 'id ASC' ) );

		foreach ( $artists as $artist ) {
			if ( self::normalize_text( $artist['name'] ) === $row['normalized_artist'] ) {
				return $artist;
			}
		}

		return null;
	}

	/**
	 * Normalize metric value.
	 *
	 * @param array $row Source row.
	 * @param float $platform_max Platform max metric.
	 * @return float
	 */
	private static function normalize_metric( $row, $platform_max ) {
		$metric = (float) $row['source_metric_value'];

		if ( $metric > 0 && $platform_max > 0 ) {
			return min( 1, $metric / $platform_max );
		}

		$rank = absint( $row['rank'] );

		if ( $rank > 0 ) {
			return max( 0, 1 - ( ( $rank - 1 ) / 100 ) );
		}

		return 0;
	}

	/**
	 * Build per-source maximum metrics.
	 *
	 * @param array $rows Source rows.
	 * @return array
	 */
	private static function source_maxes( $rows ) {
		$out = array();

		foreach ( $rows as $row ) {
			$platform = self::normalize_platform( $row['source_platform'] );
			$metric   = (float) $row['source_metric_value'];

			if ( empty( $out[ $platform ] ) || $metric > $out[ $platform ] ) {
				$out[ $platform ] = $metric;
			}
		}

		return $out;
	}

	/**
	 * Convert stored weights into platform lookup.
	 *
	 * @return array
	 */
	private static function weight_lookup() {
		$rules = self::get_scoring_rules();
		$map   = array(
			'spotify'     => 'spotify_weight',
			'youtube'     => 'youtube_music_weight',
			'tiktok'      => 'tiktok_weight',
			'shazam'      => 'shazam_weight',
			'apple-music' => 'apple_music_weight',
			'anghami'     => 'anghami_weight',
		);
		$out   = array();

		foreach ( $map as $platform => $key ) {
			$out[ $platform ] = 0;

			foreach ( $rules['weights'] as $row ) {
				if ( $row['key'] === $key ) {
					$out[ $platform ] = max( 0, (float) $row['value'] ) / 100;
					break;
				}
			}
		}

		return $out;
	}

	/**
	 * Calculate growth bonus.
	 *
	 * @param string $growth Growth value.
	 * @return float
	 */
	private static function growth_bonus( $growth ) {
		$clean = preg_replace( '/[^0-9\.\-]/', '', (string) $growth );

		if ( '' === $clean || ! is_numeric( $clean ) ) {
			return 0;
		}

		return max( -0.05, min( 0.05, (float) $clean / 1000 ) );
	}

	/**
	 * Get entity history summary.
	 *
	 * @param int    $chart_id Chart id.
	 * @param string $country Country.
	 * @param string $entity_type Entity type.
	 * @param int    $entity_id Entity id.
	 * @return array
	 */
	private static function get_entity_history( $chart_id, $country, $entity_type, $entity_id ) {
		global $wpdb;

		$weeks_table   = AMC_DB::table( 'chart_weeks' );
		$entries_table = AMC_DB::table( 'chart_entries' );
		$history       = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT e.peak_rank, e.weeks_on_chart
				FROM {$entries_table} e
				INNER JOIN {$weeks_table} w ON w.id = e.chart_week_id
				WHERE w.chart_id = %d AND w.country = %s AND e.entity_type = %s AND e.entity_id = %d
				ORDER BY w.week_date DESC, e.id DESC",
				absint( $chart_id ),
				$country,
				$entity_type,
				absint( $entity_id )
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $history ) ) {
			return array(
				'has_history'        => false,
				'best_peak_rank'     => 0,
				'max_weeks_on_chart' => 0,
			);
		}

		$best_peak = 9999;
		$max_weeks = 0;

		foreach ( $history as $item ) {
			$best_peak = min( $best_peak, absint( $item['peak_rank'] ) );
			$max_weeks = max( $max_weeks, absint( $item['weeks_on_chart'] ) );
		}

		return array(
			'has_history'        => true,
			'best_peak_rank'     => 9999 === $best_peak ? 0 : $best_peak,
			'max_weeks_on_chart' => $max_weeks,
		);
	}

	/**
	 * Collect unique upload ids from ranked items.
	 *
	 * @param array $ranked Ranked items.
	 * @return array
	 */
	private static function collect_upload_ids( $ranked ) {
		$upload_ids = array();

		foreach ( $ranked as $item ) {
			if ( ! empty( $item['upload_ids'] ) ) {
				$upload_ids = array_merge( $upload_ids, $item['upload_ids'] );
			}
		}

		return array_values( array_unique( array_map( 'absint', $upload_ids ) ) );
	}

	/**
	 * Resolve artwork for an entry.
	 *
	 * @param string $entity_type Entity type.
	 * @param int    $entity_id Entity id.
	 * @return string
	 */
	private static function resolve_entry_artwork( $entity_type, $entity_id ) {
		if ( 'artist' === $entity_type ) {
			$artist = AMC_DB::get_row( 'artists', $entity_id );
			return ! empty( $artist['image'] ) ? $artist['image'] : '';
		}

		if ( 'album' === $entity_type ) {
			$album = AMC_DB::get_row( 'albums', $entity_id );
			return ! empty( $album['cover_image'] ) ? $album['cover_image'] : '';
		}

		$track = AMC_DB::get_row( 'tracks', $entity_id );
		return ! empty( $track['cover_image'] ) ? $track['cover_image'] : '';
	}

	/**
	 * Fail an upload with explicit messaging.
	 *
	 * @param int    $upload_id Upload id.
	 * @param string $message Message.
	 * @param string $parser_name Parser name.
	 * @return void
	 */
	private static function fail_upload( $upload_id, $message, $parser_name ) {
		AMC_DB::save_row(
			'source_uploads',
			array(
				'file_status'   => 'failed',
				'error_message' => $message,
				'parser_name'   => $parser_name,
			),
			$upload_id
		);

		self::log( $upload_id, 0, 'parse', 'error', $message, array( 'parser' => $parser_name ) );
	}

	/**
	 * Build preview label.
	 *
	 * @param array $mapped Parsed row.
	 * @return string
	 */
	private static function preview_label_for_row( $mapped ) {
		if ( 'artist' === $mapped['chart_type'] ) {
			return trim( $mapped['artist_name'] . ' / rank ' . $mapped['rank'] );
		}

		return trim( $mapped['track_title'] . ' / ' . $mapped['artist_names'], ' /' );
	}

	/**
	 * Normalize text for matching.
	 *
	 * @param string $value Input.
	 * @return string
	 */
	private static function normalize_text( $value ) {
		$value = strtolower( trim( (string) $value ) );
		$value = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $value );
		$value = preg_replace( '/\s+/', ' ', $value );
		return trim( $value );
	}

	/**
	 * Get first available value from raw row.
	 *
	 * @param array $raw Raw row.
	 * @param array $keys Keys.
	 * @return string
	 */
	private static function first_value( $raw, $keys ) {
		foreach ( $keys as $key ) {
			if ( ! empty( $raw[ $key ] ) ) {
				return (string) $raw[ $key ];
			}
		}

		return '';
	}

	/**
	 * Integer helper.
	 *
	 * @param string $value Value.
	 * @return int
	 */
	private static function integer_value( $value ) {
		$clean = preg_replace( '/[^0-9\-]/', '', (string) $value );
		return '' === $clean ? 0 : absint( $clean );
	}

	/**
	 * Numeric helper.
	 *
	 * @param string $value Value.
	 * @return float
	 */
	private static function numeric_value( $value ) {
		$clean = preg_replace( '/[^0-9\.\-]/', '', (string) $value );
		return '' === $clean || ! is_numeric( $clean ) ? 0 : (float) $clean;
	}

	/**
	 * Humanize stored rule key.
	 *
	 * @param string $key Rule key.
	 * @return string
	 */
	private static function humanize_rule_key( $key ) {
		return ucwords( str_replace( '_', ' ', preg_replace( '/_weight$/', '', $key ) ) );
	}

	/**
	 * Upsert a scoring rule by key.
	 *
	 * @param string $group Group.
	 * @param string $key Key.
	 * @param string $value Value.
	 * @param string $type Type.
	 * @param int    $order Order.
	 * @return void
	 */
	private static function upsert_rule( $group, $key, $value, $type, $order ) {
		global $wpdb;

		$table = AMC_DB::table( 'scoring_rules' );
		$id    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE rule_key = %s", $key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		AMC_DB::save_row(
			'scoring_rules',
			array(
				'rule_group' => $group,
				'rule_key'   => $key,
				'rule_value' => $value,
				'rule_type'  => $type,
				'sort_order' => $order,
				'is_active'  => 1,
			),
			$id
		);
	}

	/**
	 * Normalize platform slug.
	 *
	 * @param string $platform Platform value.
	 * @return string
	 */
	private static function normalize_platform( $platform ) {
		$platform = strtolower( trim( (string) $platform ) );
		$platform = str_replace( array( ' ', '_' ), '-', $platform );

		$map = array(
			'youtube-music' => 'youtube',
			'youtube'       => 'youtube',
			'spotify'       => 'spotify',
			'shazam'        => 'shazam',
			'apple-music'   => 'apple-music',
			'anghami'       => 'anghami',
			'tiktok'        => 'tiktok',
		);

		return isset( $map[ $platform ] ) ? $map[ $platform ] : sanitize_key( $platform );
	}

	/**
	 * Platform label.
	 *
	 * @param string $platform Platform.
	 * @return string
	 */
	public static function platform_label( $platform ) {
		$labels = array(
			'youtube'     => 'YouTube',
			'spotify'     => 'Spotify',
			'shazam'      => 'Shazam',
			'apple-music' => 'Apple Music',
			'anghami'     => 'Anghami',
			'tiktok'      => 'TikTok',
		);

		$platform = self::normalize_platform( $platform );
		return isset( $labels[ $platform ] ) ? $labels[ $platform ] : ucwords( str_replace( '-', ' ', $platform ) );
	}

	/**
	 * Normalize chart type.
	 *
	 * @param string $type Type value.
	 * @return string
	 */
	private static function normalize_chart_type( $type ) {
		$type = sanitize_key( $type );
		return in_array( $type, array( 'track', 'artist', 'album' ), true ) ? $type : '';
	}

	/**
	 * Normalize country.
	 *
	 * @param string $country Country.
	 * @return string
	 */
	private static function normalize_country( $country ) {
		$country = trim( sanitize_text_field( $country ) );
		return $country ? $country : 'Global';
	}
}
