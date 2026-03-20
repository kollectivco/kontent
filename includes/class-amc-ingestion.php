<?php
/**
 * Upload, parsing, matching, and scoring foundations.
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

		$upload = wp_handle_upload( $file, array( 'test_form' => false ) );

		if ( ! empty( $upload['error'] ) ) {
			return 0;
		}

		$upload_id = AMC_DB::save_row(
			'source_uploads',
			array(
				'source_name'   => sanitize_text_field( $data['source_name'] ),
				'chart_week'    => sanitize_text_field( $data['chart_week'] ),
				'file_name'     => basename( $upload['file'] ),
				'file_path'     => $upload['file'],
				'file_url'      => $upload['url'],
				'mime_type'     => ! empty( $upload['type'] ) ? $upload['type'] : '',
				'file_size'     => file_exists( $upload['file'] ) ? filesize( $upload['file'] ) : 0,
				'file_status'   => 'uploaded',
				'row_count'     => 0,
				'preview_text'  => '',
				'uploader_id'   => get_current_user_id(),
				'parser_name'   => '',
				'error_message' => '',
			)
		);

		if ( ! $upload_id ) {
			return 0;
		}

		self::log( $upload_id, 0, 'upload', 'info', 'Source file uploaded successfully.', array( 'source' => $data['source_name'] ) );
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

		if ( ! in_array( $ext, array( 'csv', 'txt', 'tsv' ), true ) ) {
			AMC_DB::save_row(
				'source_uploads',
				array(
					'file_status'   => 'failed',
					'error_message' => 'Only CSV/TSV parsing is active in this phase.',
					'parser_name'   => 'phase-3-1-foundation',
				),
				$upload_id
			);
			self::log( $upload_id, 0, 'parse', 'warning', 'File stored but parser is not available for this extension yet.', array( 'extension' => $ext ) );
			return false;
		}

		$delimiter = 'tsv' === $ext ? "\t" : ',';
		$rows      = self::read_delimited_rows( $upload['file_path'], $delimiter );

		if ( empty( $rows ) ) {
			AMC_DB::save_row(
				'source_uploads',
				array(
					'file_status'   => 'failed',
					'error_message' => 'No parsable rows were found in the uploaded file.',
					'parser_name'   => 'phase-3-1-foundation',
				),
				$upload_id
			);
			self::log( $upload_id, 0, 'parse', 'error', 'No parsable rows found.', array() );
			return false;
		}

		$headers = self::extract_headers( array_shift( $rows ) );
		$count   = 0;
		$preview = array();

		foreach ( $rows as $index => $values ) {
			$mapped = self::map_source_row( $headers, $values );

			$row_id = AMC_DB::save_row(
				'source_rows',
				array(
					'upload_id'            => $upload_id,
					'row_index'            => $index + 1,
					'raw_data'             => wp_json_encode( $mapped['raw'] ),
					'normalized_title'     => $mapped['normalized_title'],
					'normalized_artist'    => $mapped['normalized_artist'],
					'normalized_album'     => $mapped['normalized_album'],
					'normalized_isrc'      => $mapped['normalized_isrc'],
					'normalized_rank'      => $mapped['normalized_rank'],
					'normalized_score'     => $mapped['normalized_score'],
					'matched_entity_type'  => '',
					'matched_entity_id'    => 0,
					'matching_status'      => 'pending',
				)
			);

			if ( $row_id && count( $preview ) < 5 ) {
				$preview[] = trim( $mapped['normalized_title'] . ' / ' . $mapped['normalized_artist'], ' /' );
			}

			++$count;
		}

		AMC_DB::save_row(
			'source_uploads',
			array(
				'file_status'   => 'parsed',
				'row_count'     => $count,
				'preview_text'  => implode( ' | ', array_filter( $preview ) ),
				'parser_name'   => 'phase-3-1-foundation',
				'error_message' => '',
			),
			$upload_id
		);
		self::log( $upload_id, 0, 'parse', 'info', 'Upload parsed into normalized source rows.', array( 'row_count' => $count ) );

		return true;
	}

	/**
	 * Run basic matching for an upload.
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
			$candidate = self::find_track_candidate( $row );
			$status    = 'pending';
			$label     = '';
			$type      = 'track';
			$entity_id = 0;
			$basis     = 'No exact candidate';
			$confidence= 0;

			if ( $candidate ) {
				$label      = $candidate['title'];
				$entity_id  = (int) $candidate['id'];
				$basis      = 'Exact normalized title + artist';
				$confidence = 96;
			} else {
				$artist_candidate = self::find_artist_candidate( $row );

				if ( $artist_candidate ) {
					$type       = 'artist';
					$label      = $artist_candidate['name'];
					$entity_id  = (int) $artist_candidate['id'];
					$basis      = 'Exact normalized artist';
					$confidence = 88;
				}
			}

			AMC_DB::save_row(
				'matching_queue',
				array(
					'upload_id'             => $upload_id,
					'source_row_id'         => (int) $row['id'],
					'entity_type'           => $type,
					'candidate_entity_id'   => $entity_id,
					'candidate_label'       => $label,
					'confidence'            => $confidence,
					'match_basis'           => $basis,
					'status'                => $status,
					'override_entity_type'  => '',
					'override_entity_id'    => 0,
					'notes'                 => '',
				)
			);
		}

		AMC_DB::save_row( 'source_uploads', array( 'file_status' => 'matched' ), $upload_id );
		self::log( $upload_id, 0, 'match', 'info', 'Basic matching queue generated.', array( 'rows' => count( $rows ) ) );
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
		$status_map    = array(
			'approve'  => 'approved',
			'reject'   => 'rejected',
			'override' => 'override',
		);
		$new_status    = isset( $status_map[ $decision ] ) ? $status_map[ $decision ] : 'pending';
		$entity_type   = ! empty( $override['entity_type'] ) ? $override['entity_type'] : $queue['entity_type'];
		$entity_id     = ! empty( $override['entity_id'] ) ? absint( $override['entity_id'] ) : (int) $queue['candidate_entity_id'];

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

		if ( 'approve' === $decision || 'override' === $decision ) {
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
					'matching_status' => 'rejected',
				),
				$source_row_id
			);
		}

		self::log( (int) $queue['upload_id'], $source_row_id, 'matching_decision', 'info', 'Matching queue decision stored.', array( 'decision' => $decision ) );
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
		$current = self::get_scoring_rules();
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
	 * @param array $header_row Header row.
	 * @return array
	 */
	private static function extract_headers( $header_row ) {
		return array_map(
			function ( $header ) {
				return sanitize_key( strtolower( str_replace( array( ' ', '-', '/' ), '_', trim( $header ) ) ) );
			},
			$header_row
		);
	}

	/**
	 * Map a source row into normalized storage.
	 *
	 * @param array $headers Headers.
	 * @param array $values Values.
	 * @return array
	 */
	private static function map_source_row( $headers, $values ) {
		$raw = array();

		foreach ( $headers as $index => $header ) {
			$raw[ $header ] = isset( $values[ $index ] ) ? trim( (string) $values[ $index ] ) : '';
		}

		$title  = self::first_value( $raw, array( 'title', 'track', 'track_title', 'song', 'song_title', 'name' ) );
		$artist = self::first_value( $raw, array( 'artist', 'artist_name', 'performer' ) );
		$album  = self::first_value( $raw, array( 'album', 'album_title', 'release' ) );
		$isrc   = self::first_value( $raw, array( 'isrc', 'track_isrc' ) );
		$rank   = self::first_value( $raw, array( 'rank', 'position' ) );
		$score  = self::first_value( $raw, array( 'score', 'streams', 'plays' ) );

		return array(
			'raw'               => $raw,
			'normalized_title'  => self::normalize_text( $title ),
			'normalized_artist' => self::normalize_text( $artist ),
			'normalized_album'  => self::normalize_text( $album ),
			'normalized_isrc'   => strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $isrc ) ),
			'normalized_rank'   => absint( $rank ),
			'normalized_score'  => is_numeric( $score ) ? (float) $score : 0,
		);
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
			$artist_match= empty( $row['normalized_artist'] ) || $artist_name === $row['normalized_artist'];

			if ( $title_match && $artist_match ) {
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
	 * Get first available value from mapped raw row.
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
}
