<?php
/**
 * Upload, parsing, matching, scoring, generation, and publishing pipeline.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AMC_Ingestion {
	const CRON_HOOK = 'amc_process_jobs';

	/**
	 * Ensure default scoring rules exist.
	 *
	 * @return void
	 */
	public static function ensure_defaults() {
		return;
	}

	/**
	 * Create upload and parse it.
	 *
	 * @param array $file File array.
	 * @param array $data Form data.
	 * @return array
	 */
	public static function create_upload( $file, $data ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$platform   = self::normalize_platform( isset( $data['source_platform'] ) ? $data['source_platform'] : '' );
		$chart_type = self::normalize_chart_type( isset( $data['chart_type'] ) ? $data['chart_type'] : '' );
		$country    = self::normalize_country( isset( $data['country'] ) ? $data['country'] : '' );
		$chart_date = ! empty( $data['chart_date'] ) ? sanitize_text_field( $data['chart_date'] ) : current_time( 'Y-m-d' );
		$chart_week = ! empty( $data['chart_week'] ) ? sanitize_text_field( $data['chart_week'] ) : $chart_date;
		$chart_id   = ! empty( $data['target_chart_id'] ) ? absint( $data['target_chart_id'] ) : 0;
		$dry_run    = ! empty( $data['dry_run'] ) ? 1 : 0;
		$override_duplicate = ! empty( $data['allow_duplicate'] );
		$chart      = $chart_id ? AMC_DB::get_row( 'charts', $chart_id ) : null;

		if ( ! $platform || ! $chart_type || ! $chart_id || ! $chart ) {
			return array(
				'success'  => false,
				'type'     => 'error',
				'upload_id'=> 0,
				'message'  => 'Upload metadata is incomplete. Choose source platform, chart, country, chart date, and chart type.',
			);
		}

		if ( ! empty( $chart['type'] ) && $chart['type'] !== $chart_type ) {
			return array(
				'success'  => false,
				'type'     => 'error',
				'upload_id'=> 0,
				'message'  => 'The selected chart type does not match the target chart.',
			);
		}

		$upload = wp_handle_upload( $file, array( 'test_form' => false ) );

		if ( ! empty( $upload['error'] ) ) {
			return array(
				'success'  => false,
				'type'     => 'error',
				'upload_id'=> 0,
				'message'  => $upload['error'],
			);
		}

		$file_hash = file_exists( $upload['file'] ) ? md5_file( $upload['file'] ) : '';

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
				'file_hash'         => $file_hash,
				'is_duplicate'      => 0,
				'duplicate_of_upload_id' => 0,
				'is_dry_run'        => $dry_run,
				'file_status'       => 'uploaded',
				'generated_week_id' => 0,
				'row_count'         => 0,
				'preview_text'      => '',
				'diagnostic_summary'=> '',
				'uploader_id'       => get_current_user_id(),
				'parser_name'       => '',
				'error_message'     => '',
			)
		);

		if ( ! $upload_id ) {
			return array(
				'success'  => false,
				'type'     => 'error',
				'upload_id'=> 0,
				'message'  => 'The upload record could not be created.',
			);
		}

		$duplicate = $file_hash ? AMC_DB::find_duplicate_upload( $file_hash, $platform, $chart_id, $country, $chart_date, $upload_id ) : null;

		if ( $duplicate ) {
			AMC_DB::save_row(
				'source_uploads',
				array(
					'is_duplicate'           => 1,
					'duplicate_of_upload_id' => (int) $duplicate['id'],
				),
				$upload_id
			);

			self::log(
				$upload_id,
				0,
				'duplicate',
				'warning',
				'Likely duplicate upload detected.',
				array(
					'duplicate_of_upload_id' => (int) $duplicate['id'],
					'file_hash'              => $file_hash,
				)
			);

			if ( ! $override_duplicate ) {
				$message = sprintf( 'Likely duplicate upload detected against upload #%d for the same source, chart, country, and week. Re-upload with duplicate override if you want to process it anyway.', (int) $duplicate['id'] );
				self::fail_upload( $upload_id, $message, 'duplicate-detection' );
				self::notify( 'warning', $message, array( 'upload_id' => $upload_id ) );
				return array(
					'success'  => false,
					'type'     => 'warning',
					'upload_id'=> $upload_id,
					'message'  => $message,
				);
			}

			self::log( $upload_id, 0, 'duplicate_override', 'warning', 'Duplicate override approved by admin. Upload processing continued.', array( 'duplicate_of_upload_id' => (int) $duplicate['id'] ) );
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

		return array(
			'success'  => true,
			'type'     => $dry_run ? 'warning' : 'success',
			'upload_id'=> $upload_id,
			'message'  => $dry_run ? 'Dry-run upload saved, validated, and queued for review without entering live generation pools.' : 'Source upload saved, parsed, and queued for matching.',
		);
	}

	/**
	 * Register cron hooks.
	 *
	 * @return void
	 */
	public static function boot() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'process_queued_jobs' ) );
	}

	/**
	 * Queue a background-safe job.
	 *
	 * @param string $job_type Job type.
	 * @param array  $args Payload.
	 * @return int
	 */
	public static function enqueue_job( $job_type, $args = array() ) {
		$lock_key = self::build_job_lock_key( $job_type, $args );
		$existing = AMC_DB::find_active_job_by_lock_key( $lock_key );
		$policy   = self::retry_policy_for_job_type( $job_type );

		if ( $existing ) {
			self::notify(
				'warning',
				'An equivalent job is already queued or running, so a duplicate job was not added.',
				array(
					'job_id'   => (int) $existing['id'],
					'job_type' => $job_type,
					'lock_key' => $lock_key,
				)
			);
			return (int) $existing['id'];
		}

		$job_id = AMC_DB::save_row(
			'jobs',
			array(
				'job_type'       => sanitize_key( $job_type ),
				'job_group'      => ! empty( $args['job_group'] ) ? sanitize_key( $args['job_group'] ) : 'operations',
				'status'         => 'queued',
				'lock_key'       => $lock_key,
				'trigger_mode'   => ! empty( $args['trigger_mode'] ) ? sanitize_key( $args['trigger_mode'] ) : 'queued',
				'initiated_by'   => get_current_user_id(),
				'reference_type' => ! empty( $args['reference_type'] ) ? sanitize_key( $args['reference_type'] ) : '',
				'reference_id'   => ! empty( $args['reference_id'] ) ? absint( $args['reference_id'] ) : 0,
				'chart_id'       => ! empty( $args['chart_id'] ) ? absint( $args['chart_id'] ) : 0,
				'country'        => ! empty( $args['country'] ) ? self::normalize_country( $args['country'] ) : '',
				'week_date'      => ! empty( $args['week_date'] ) ? sanitize_text_field( $args['week_date'] ) : null,
				'attempts'       => 0,
				'max_attempts'   => ! empty( $args['max_attempts'] ) ? absint( $args['max_attempts'] ) : $policy['max_attempts'],
				'retry_delay_seconds' => ! empty( $args['retry_delay_seconds'] ) ? absint( $args['retry_delay_seconds'] ) : $policy['retry_delay_seconds'],
				'next_retry_at'  => null,
				'last_error_step'=> '',
				'payload'        => wp_json_encode( $args ),
				'result_data'    => '',
				'error_message'  => '',
			)
		);

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 60, self::CRON_HOOK );
		}

		return $job_id;
	}

	/**
	 * Process queued jobs.
	 *
	 * @return void
	 */
	public static function process_queued_jobs() {
		$jobs = array_filter(
			AMC_DB::get_jobs(
				array(
					'status' => 'queued',
					'limit'  => 10,
				)
			),
			function ( $job ) {
				return empty( $job['next_retry_at'] ) || strtotime( $job['next_retry_at'] ) <= current_time( 'timestamp' );
			}
		);

		foreach ( $jobs as $job ) {
			self::run_job( (int) $job['id'], 'cron' );
		}
	}

	/**
	 * Execute one queued job.
	 *
	 * @param int $job_id Job id.
	 * @return array
	 */
	public static function run_job( $job_id, $execution_mode = 'manual' ) {
		$job = AMC_DB::get_row( 'jobs', $job_id );

		if ( ! $job || ! in_array( $job['status'], array( 'queued', 'failed' ), true ) ) {
			return array( 'success' => false, 'message' => 'Job is not available to run.' );
		}

		if ( ! empty( $job['lock_key'] ) ) {
			$active = AMC_DB::find_active_job_by_lock_key( $job['lock_key'], $job_id );
			if ( $active ) {
				return array( 'success' => false, 'message' => 'Another equivalent job is already queued or running.' );
			}
		}

		$lock_acquired = self::acquire_lock( 'job:' . $job_id, 10 * MINUTE_IN_SECONDS );

		if ( ! $lock_acquired ) {
			return array( 'success' => false, 'message' => 'This job is already being processed.' );
		}

		$payload = ! empty( $job['payload'] ) ? json_decode( $job['payload'], true ) : array();
		$payload = is_array( $payload ) ? $payload : array();

		AMC_DB::save_row(
			'jobs',
			array(
				'status'     => 'running',
				'started_at' => current_time( 'mysql' ),
				'attempts'   => (int) $job['attempts'] + 1,
				'trigger_mode' => sanitize_key( $execution_mode ),
				'next_retry_at' => null,
				'last_error_step' => '',
				'error_message' => '',
			),
			$job_id
		);

		$result = self::dispatch_job( $job['job_type'], $payload );

		$job_update = array(
			'status'          => ! empty( $result['success'] ) ? 'completed' : 'failed',
			'result_data'     => wp_json_encode( $result ),
			'error_message'   => ! empty( $result['success'] ) ? '' : ( ! empty( $result['message'] ) ? $result['message'] : 'Job failed.' ),
			'completed_at'    => current_time( 'mysql' ),
			'last_error_step' => ! empty( $result['step'] ) ? sanitize_key( $result['step'] ) : '',
		);

		if ( empty( $result['success'] ) ) {
			$attempts      = (int) $job['attempts'] + 1;
			$max_attempts  = ! empty( $job['max_attempts'] ) ? (int) $job['max_attempts'] : 3;
			$retry_delay   = ! empty( $job['retry_delay_seconds'] ) ? (int) $job['retry_delay_seconds'] : 300;
			$should_retry  = $attempts < $max_attempts && self::job_type_retryable( $job['job_type'] );
			$backoff_delay = $retry_delay * max( 1, $attempts );

			if ( $should_retry ) {
				$job_update['status']        = 'queued';
				$job_update['next_retry_at'] = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp', true ) + $backoff_delay );
				self::notify( 'warning', 'A failed job was queued for automatic retry with backoff.', array( 'job_id' => $job_id, 'job_type' => $job['job_type'], 'next_retry_at' => $job_update['next_retry_at'] ) );
			} else {
				self::maybe_send_external_alert( 'repeated_failures', 'Repeated job failures reached the retry limit.', array( 'job_id' => $job_id, 'job_type' => $job['job_type'], 'attempts' => $attempts ) );
			}
		}

		AMC_DB::save_row( 'jobs', $job_update, $job_id );

		self::release_lock( 'job:' . $job_id );

		self::log(
			! empty( $payload['upload_id'] ) ? (int) $payload['upload_id'] : 0,
			0,
			'job_' . $job['job_type'],
			! empty( $result['success'] ) ? 'info' : 'error',
			! empty( $result['success'] ) ? 'Background job completed.' : 'Background job failed.',
			array(
				'job_id'   => $job_id,
				'job_type' => $job['job_type'],
				'payload'  => $payload,
				'result'   => $result,
			)
		);

		return $result;
	}

	/**
	 * Cancel a queued job.
	 *
	 * @param int $job_id Job id.
	 * @return array
	 */
	public static function cancel_job( $job_id ) {
		$job = AMC_DB::get_row( 'jobs', $job_id );

		if ( ! $job || 'queued' !== $job['status'] ) {
			return array( 'success' => false, 'message' => 'Only queued jobs can be cancelled.' );
		}

		AMC_DB::save_row(
			'jobs',
			array(
				'status'        => 'cancelled',
				'error_message' => 'Cancelled by operator.',
				'completed_at'  => current_time( 'mysql' ),
			),
			$job_id
		);

		self::notify( 'info', 'Queued job cancelled by operator.', array( 'job_id' => $job_id, 'job_type' => $job['job_type'] ) );
		return array( 'success' => true, 'message' => 'Queued job cancelled.' );
	}

	/**
	 * Retry a failed job.
	 *
	 * @param int $job_id Job id.
	 * @return array
	 */
	public static function retry_job( $job_id ) {
		$job = AMC_DB::get_row( 'jobs', $job_id );

		if ( ! $job || 'failed' !== $job['status'] ) {
			return array( 'success' => false, 'message' => 'Only failed jobs can be retried.' );
		}

		$max_attempts = ! empty( $job['max_attempts'] ) ? (int) $job['max_attempts'] : 3;
		if ( (int) $job['attempts'] >= $max_attempts ) {
			return array( 'success' => false, 'message' => 'This job has reached its retry limit.' );
		}

		if ( ! empty( $job['lock_key'] ) && AMC_DB::find_active_job_by_lock_key( $job['lock_key'], $job_id ) ) {
			return array( 'success' => false, 'message' => 'A matching queued or running job already exists.' );
		}

		AMC_DB::save_row(
			'jobs',
			array(
				'status'        => 'queued',
				'started_at'    => null,
				'completed_at'  => null,
				'next_retry_at' => null,
				'error_message' => '',
				'result_data'   => '',
			),
			$job_id
		);

		self::notify( 'info', 'Failed job was queued for retry.', array( 'job_id' => $job_id, 'job_type' => $job['job_type'] ) );
		return array( 'success' => true, 'message' => 'Failed job queued for retry.' );
	}

	/**
	 * Rerun a completed job where safe.
	 *
	 * @param int $job_id Job id.
	 * @return array
	 */
	public static function rerun_job( $job_id ) {
		$job = AMC_DB::get_row( 'jobs', $job_id );

		if ( ! $job || 'completed' !== $job['status'] ) {
			return array( 'success' => false, 'message' => 'Only completed jobs can be rerun.' );
		}

		if ( ! self::job_type_rerunnable( $job['job_type'] ) ) {
			return array( 'success' => false, 'message' => 'This completed job type is not safe to rerun.' );
		}

		$new_job_id = self::enqueue_job( $job['job_type'], self::job_payload_for_rerun( $job ) );
		$result     = self::run_job( $new_job_id );

		return array(
			'success' => ! empty( $result['success'] ),
			'message' => ! empty( $result['message'] ) ? $result['message'] : 'Completed job rerun finished.',
			'result'  => $result,
		);
	}

	/**
	 * Run queued jobs immediately.
	 *
	 * @return array
	 */
	public static function run_queued_jobs_now() {
		$jobs = AMC_DB::get_rows(
			'jobs',
			array(
				'where'    => array( 'status' => 'queued' ),
				'order_by' => 'id ASC',
				'limit'    => 10,
			)
		);

		foreach ( $jobs as $job ) {
			self::run_job( (int) $job['id'], 'operator' );
		}
		return array( 'success' => true, 'message' => 'Queued jobs runner finished.' );
	}

	/**
	 * Dispatch background-safe task.
	 *
	 * @param string $job_type Job type.
	 * @param array  $payload Payload.
	 * @return array
	 */
	private static function dispatch_job( $job_type, $payload ) {
		switch ( $job_type ) {
			case 'parse_upload':
				return ! empty( $payload['upload_id'] ) ? self::parse_upload_service( (int) $payload['upload_id'] ) : array(
					'success' => false,
					'message' => 'Missing upload id for parse job.',
				);
			case 'rerun_matching':
				if ( empty( $payload['upload_id'] ) ) {
					return array( 'success' => false, 'message' => 'Missing upload id for matching job.' );
				}
				return self::rerun_matching_service( (int) $payload['upload_id'] );
			case 'auto_create_processing':
				if ( empty( $payload['upload_id'] ) ) {
					return array( 'success' => false, 'message' => 'Missing upload id for auto-create processing job.' );
				}
				return self::auto_create_processing_service( (int) $payload['upload_id'] );
			case 'generate_chart':
				return self::generate_chart_week(
					! empty( $payload['chart_id'] ) ? (int) $payload['chart_id'] : 0,
					! empty( $payload['country'] ) ? $payload['country'] : '',
					! empty( $payload['chart_date'] ) ? $payload['chart_date'] : '',
					! empty( $payload['chart_type'] ) ? $payload['chart_type'] : ''
				);
			case 'publish_checks':
				if ( empty( $payload['week_id'] ) ) {
					return array( 'success' => false, 'message' => 'Missing chart week id for publish checks.' );
				}
				return self::publication_safety_check( (int) $payload['week_id'] );
			case 'cleanup_diagnostics':
				return self::run_cleanup_diagnostics();
			default:
				return array( 'success' => false, 'message' => 'Unknown job type.' );
		}
	}

	/**
	 * Parse an uploaded file.
	 *
	 * @param int $upload_id Upload id.
	 * @return bool
	 */
	public static function parse_upload( $upload_id ) {
		if ( ! self::acquire_operation_lock( 'upload-parse-' . absint( $upload_id ), 'Parsing is already running for this upload.' ) ) {
			return false;
		}

		$upload = AMC_DB::get_row( 'source_uploads', $upload_id );

		if ( ! $upload ) {
			self::release_operation_lock( 'upload-parse-' . absint( $upload_id ) );
			return false;
		}

		self::clear_upload_rows( $upload_id );

		$ext = strtolower( pathinfo( $upload['file_name'], PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, array( 'csv', 'txt', 'tsv', 'xlsx', 'xls' ), true ) ) {
			self::fail_upload( $upload_id, 'This file type is unsupported. Use CSV, TSV, TXT, XLSX, or XLS exports.', 'unsupported-file' );
			self::release_operation_lock( 'upload-parse-' . absint( $upload_id ) );
			return false;
		}

		$rows = self::read_tabular_rows( $upload['file_path'], $ext );

		if ( empty( $rows['success'] ) ) {
			self::fail_upload( $upload_id, $rows['message'], ! empty( $rows['parser_name'] ) ? $rows['parser_name'] : 'read-failure' );
			self::maybe_send_external_alert( 'parsing_failed', 'Upload parsing failed.', array( 'upload_id' => $upload_id, 'message' => $rows['message'] ) );
			self::release_operation_lock( 'upload-parse-' . absint( $upload_id ) );
			return false;
		}

		if ( empty( $rows['rows'] ) ) {
			self::fail_upload( $upload_id, 'No parsable rows were found in the uploaded file.', 'empty-file' );
			self::release_operation_lock( 'upload-parse-' . absint( $upload_id ) );
			return false;
		}

		$headers       = self::extract_headers( array_shift( $rows['rows'] ), $upload['source_platform'] );
		$parser_result = self::parse_rows_for_upload( $upload, $headers, $rows['rows'] );

		if ( empty( $parser_result['success'] ) ) {
			self::fail_upload( $upload_id, $parser_result['message'], $parser_result['parser_name'] );
			self::release_operation_lock( 'upload-parse-' . absint( $upload_id ) );
			return false;
		}

		$count   = 0;
		$preview = array();
		$invalid = 0;

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
					'validation_status'    => ! empty( $mapped['validation_status'] ) ? $mapped['validation_status'] : 'valid',
					'validation_message'   => ! empty( $mapped['validation_message'] ) ? $mapped['validation_message'] : '',
					'normalized_title'     => $mapped['normalized_title'],
					'normalized_artist'    => $mapped['normalized_artist'],
					'normalized_album'     => $mapped['normalized_album'],
					'normalized_isrc'      => $mapped['normalized_isrc'],
					'normalized_rank'      => $mapped['rank'],
					'normalized_score'     => $mapped['source_metric_value'],
					'matched_entity_type'  => '',
					'matched_entity_id'    => 0,
					'matching_status'      => 'pending',
					'match_confidence'     => 0,
					'match_confidence_label' => '',
					'match_resolution'     => '',
					'auto_created_entity_type' => '',
					'auto_created_entity_id'   => 0,
				)
			);

			if ( $row_id && count( $preview ) < 5 ) {
				$preview[] = self::preview_label_for_row( $mapped );
			}

			if ( ! empty( $mapped['validation_status'] ) && 'valid' !== $mapped['validation_status'] ) {
				++$invalid;
				self::log( $upload_id, (int) $row_id, 'validation', 'warning', $mapped['validation_message'], array( 'row_index' => $index + 1 ) );
			}

			++$count;
		}

		AMC_DB::save_row(
			'source_uploads',
			array(
				'file_status'   => 'parsed',
				'row_count'     => $count,
				'preview_text'  => implode( ' | ', array_filter( $preview ) ),
				'diagnostic_summary' => wp_json_encode(
					array(
						'row_count'     => $count,
						'invalid_rows'  => $invalid,
						'valid_rows'    => max( 0, $count - $invalid ),
						'parser'        => $parser_result['parser_name'],
					)
				),
				'parser_name'   => $parser_result['parser_name'],
				'error_message' => '',
			),
			$upload_id
		);

		self::log( $upload_id, 0, 'parse', 'info', 'Upload parsed into normalized source rows.', array( 'row_count' => $count, 'invalid_rows' => $invalid, 'parser' => $parser_result['parser_name'] ) );
		if ( $invalid > 0 ) {
			self::notify( 'warning', 'Upload parsing completed with invalid or rejected rows.', array( 'upload_id' => $upload_id, 'invalid_rows' => $invalid ) );
		}
		self::maybe_send_threshold_alerts_for_upload( $upload_id );

		self::release_operation_lock( 'upload-parse-' . absint( $upload_id ) );
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

		if ( ! self::acquire_operation_lock( 'upload-match-' . absint( $upload_id ), 'Matching is already running for this upload.' ) ) {
			return;
		}

		$rows = AMC_DB::get_rows(
			'source_rows',
			array(
				'where'    => array( 'upload_id' => $upload_id ),
				'order_by' => 'row_index ASC',
			)
		);

		$wpdb->delete( AMC_DB::table( 'matching_queue' ), array( 'upload_id' => absint( $upload_id ) ) );

		foreach ( $rows as $row ) {
			$type       = 'artist' === $row['chart_type'] ? 'artist' : 'track';
			$basis      = 'No match candidate';
			$entity_id  = 0;
			$label      = '';
			$confidence = 0;
			$status     = 'pending';
			$level      = '';

			if ( ! empty( $row['validation_status'] ) && 'valid' !== $row['validation_status'] ) {
				AMC_DB::save_row(
					'source_rows',
					array(
						'matching_status'       => 'invalid',
						'match_confidence'      => 0,
						'match_confidence_label'=> 'invalid',
						'match_resolution'      => 'invalid',
					),
					(int) $row['id']
				);
				continue;
			}

			$candidate_result = 'artist' === $type ? self::find_artist_candidate( $row ) : self::find_track_candidate( $row );

			if ( ! empty( $candidate_result['candidate'] ) ) {
				$candidate  = $candidate_result['candidate'];
				$entity_id  = (int) $candidate['id'];
				$label      = 'artist' === $type ? $candidate['name'] : $candidate['title'];
				$basis      = $candidate_result['basis'];
				$confidence = (float) $candidate_result['confidence'];
				$level      = $candidate_result['level'];

				if ( in_array( $level, array( 'exact', 'high confidence' ), true ) ) {
					$status = 'approved';
					AMC_DB::save_row(
						'source_rows',
						array(
							'matched_entity_type'    => $type,
							'matched_entity_id'      => $entity_id,
							'matching_status'        => 'matched',
							'match_confidence'       => $confidence,
							'match_confidence_label' => $level,
							'match_resolution'       => 'matched_existing',
							'auto_created_entity_type' => '',
							'auto_created_entity_id'   => 0,
						),
						(int) $row['id']
					);
				} else {
					$status = 'review_needed';
					AMC_DB::save_row(
						'source_rows',
						array(
							'matched_entity_type'    => '',
							'matched_entity_id'      => 0,
							'matching_status'        => 'review_needed',
							'match_confidence'       => $confidence,
							'match_confidence_label' => $level,
							'match_resolution'       => 'review_needed',
						),
						(int) $row['id']
					);
					self::log( $upload_id, (int) $row['id'], 'matching_review', 'warning', 'Ambiguous match sent to review queue.', array( 'confidence' => $confidence, 'label' => $label ) );
				}
			} else {
				$creation_result = self::auto_create_entity_from_row( $row, $type );

				if ( ! empty( $creation_result['success'] ) ) {
					$entity_id  = (int) $creation_result['entity_id'];
					$entity_type = $creation_result['entity_type'];
					$label      = $creation_result['label'];
					$basis      = $creation_result['basis'];
					$level      = 'ready to create';
					$confidence = 100;
					$status     = 'auto_created';

					AMC_DB::save_row(
						'source_rows',
						array(
							'matched_entity_type'      => $entity_type,
							'matched_entity_id'        => $entity_id,
							'matching_status'          => 'matched',
							'match_confidence'         => $confidence,
							'match_confidence_label'   => $level,
							'match_resolution'         => 'auto_created',
							'auto_created_entity_type' => $entity_type,
							'auto_created_entity_id'   => $entity_id,
						),
						(int) $row['id']
					);

					self::log( $upload_id, (int) $row['id'], 'auto_create', 'info', 'Missing entity was created automatically from a valid unambiguous row.', $creation_result );
					self::notify( 'info', 'Auto-create created a new entity from an upload row.', array( 'upload_id' => $upload_id, 'entity_type' => $entity_type, 'entity_id' => $entity_id ) );
				} else {
					AMC_DB::save_row(
						'source_rows',
						array(
							'matching_status'        => 'review_needed',
							'match_confidence'       => 0,
							'match_confidence_label' => 'unmatched',
							'match_resolution'       => 'review_needed',
						),
						(int) $row['id']
					);

					$status = 'review_needed';
					$basis  = ! empty( $creation_result['reason'] ) ? $creation_result['reason'] : 'No safe match or auto-create path was available.';
					$level  = 'unmatched';
					self::log( $upload_id, (int) $row['id'], 'matching_review', 'warning', 'Row requires review before matching or creating an entity.', array( 'reason' => $basis ) );
				}
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
					'confidence_label'     => $level,
					'match_basis'          => $basis,
					'row_type'             => self::queue_row_type( $status, $entity_id, $label, $basis ),
					'action_hint'          => self::queue_action_hint( $status, $entity_id ),
					'status'               => $status,
					'override_entity_type' => '',
					'override_entity_id'   => 0,
					'notes'                => $level ? 'Confidence: ' . $level : '',
				)
			);
		}

		self::sync_upload_status_from_rows( $upload_id );
		self::refresh_upload_diagnostics( $upload_id );
		self::log( $upload_id, 0, 'match', 'info', 'Matching queue generated for upload.', array( 'rows' => count( $rows ) ) );
		$upload_state = AMC_DB::get_row( 'source_uploads', $upload_id );
		if ( $upload_state && ! empty( $upload_state['diagnostic_summary'] ) ) {
			$diag = json_decode( $upload_state['diagnostic_summary'], true );
			if ( is_array( $diag ) && ! empty( $diag['review_needed'] ) ) {
				self::notify( 'warning', 'Matching review queue has items waiting for review.', array( 'upload_id' => $upload_id, 'review_needed' => (int) $diag['review_needed'] ) );
			}
		}
		self::maybe_send_threshold_alerts_for_upload( $upload_id );
		self::release_operation_lock( 'upload-match-' . absint( $upload_id ) );
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
					'row_type'             => 'override' === $decision ? 'review needed' : $queue['row_type'],
					'action_hint'          => 'override' === $decision ? 'Operator selected a manual target record.' : $queue['action_hint'],
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
					'match_resolution'    => 'override' === $decision ? 'manual_override' : 'matched_existing',
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
					'match_resolution'    => 'rejected',
				),
				$source_row_id
			);
		}

		self::sync_upload_status_from_rows( (int) $queue['upload_id'] );
		self::refresh_upload_diagnostics( (int) $queue['upload_id'] );
		self::log( (int) $queue['upload_id'], $source_row_id, 'matching_decision', 'info', 'Matching queue decision stored.', array( 'decision' => $decision ) );
		return true;
	}

	/**
	 * Reprocess an upload through parse and/or matching.
	 *
	 * @param int   $upload_id Upload id.
	 * @param array $steps Steps.
	 * @return array
	 */
	public static function reprocess_upload( $upload_id, $steps = array() ) {
		$lock_name = 'upload-reprocess-' . absint( $upload_id );
		if ( ! self::acquire_operation_lock( $lock_name, 'Reprocessing is already running for this upload.' ) ) {
			return array(
				'success' => false,
				'message' => 'Reprocessing is already running for this upload.',
			);
		}

		$defaults = array(
			'parse' => true,
			'match' => true,
		);
		$steps    = wp_parse_args( $steps, $defaults );

		if ( ! empty( $steps['parse'] ) ) {
			$parsed = self::parse_upload( $upload_id );
			if ( ! $parsed ) {
				self::release_operation_lock( $lock_name );
				return array(
					'success' => false,
					'message' => 'Upload reparse failed. Check parser diagnostics and invalid-row output.',
				);
			}
		}

		if ( ! empty( $steps['match'] ) ) {
			self::run_matching( $upload_id );
		}

		self::log( $upload_id, 0, 'reprocess', 'info', 'Upload reprocessing completed.', $steps );
		self::release_operation_lock( $lock_name );

		return array(
			'success' => true,
			'message' => 'Upload reprocessing completed successfully.',
		);
	}

	/**
	 * Cron-safe parse wrapper.
	 *
	 * @param int $upload_id Upload id.
	 * @return array
	 */
	public static function parse_upload_service( $upload_id ) {
		$success = self::parse_upload( $upload_id );
		return array(
			'success' => $success,
			'step'    => 'parser',
			'message' => $success ? 'Parse service completed.' : 'Parse service failed.',
		);
	}

	/**
	 * Cron-safe match wrapper.
	 *
	 * @param int $upload_id Upload id.
	 * @return array
	 */
	public static function rerun_matching_service( $upload_id ) {
		self::run_matching( $upload_id );
		return array(
			'success' => true,
			'step'    => 'matching',
			'message' => 'Matching service completed.',
		);
	}

	/**
	 * Cron-safe auto-create processing wrapper.
	 *
	 * @param int $upload_id Upload id.
	 * @return array
	 */
	public static function auto_create_processing_service( $upload_id ) {
		self::run_matching( $upload_id );
		return array(
			'success' => true,
			'step'    => 'auto_create',
			'message' => 'Auto-create processing service completed.',
		);
	}

	/**
	 * Cron-safe generation wrapper.
	 *
	 * @param int    $chart_id Chart id.
	 * @param string $country Country.
	 * @param string $chart_date Chart date.
	 * @param string $chart_type Chart type.
	 * @return array
	 */
	public static function generate_chart_service( $chart_id, $country, $chart_date, $chart_type ) {
		$result = self::generate_chart_week( $chart_id, $country, $chart_date, $chart_type );
		$result['step'] = 'generation';
		return $result;
	}

	/**
	 * Cron-safe publish-check wrapper.
	 *
	 * @param int $week_id Week id.
	 * @return array
	 */
	public static function publish_checks_service( $week_id ) {
		$result = self::publication_safety_check( $week_id );

		if ( empty( $result['success'] ) ) {
			$result['message'] = ! empty( $result['issues'] ) ? implode( ' ', $result['issues'] ) : 'Publish checks failed.';
		} else {
			$result['message'] = 'Publish checks passed.';
		}
		$result['step'] = 'publishing';

		return $result;
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
		$lock_name  = 'week-generate-' . $chart_id . '-' . md5( $country . '|' . $chart_date . '|' . $chart_type );

		if ( ! self::acquire_operation_lock( $lock_name, 'Generation is already running for this chart, country, and week.' ) ) {
			return array( 'success' => false, 'step' => 'generation', 'message' => 'Generation is already running for this chart, country, and week.' );
		}

		if ( ! $chart || ! $chart_type ) {
			self::release_operation_lock( $lock_name );
			return array( 'success' => false, 'step' => 'generation', 'message' => 'Chart metadata is incomplete for generation.' );
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
				AND u.is_dry_run = %d
				AND r.matching_status = %s
			ORDER BY r.rank ASC, r.id ASC",
			$chart_id,
			$country,
			$chart_date,
			$chart_type,
			0,
			'matched'
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows          = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( empty( $rows ) ) {
			self::log( 0, 0, 'generation', 'warning', 'No approved rows were available for generation.', array( 'chart_id' => $chart_id, 'country' => $country, 'chart_date' => $chart_date ) );
			self::release_operation_lock( $lock_name );
			return array( 'success' => false, 'step' => 'generation', 'message' => 'No approved matched rows were found for the selected chart, country, and week.' );
		}

		$aggregate           = self::aggregate_generation_rows( $rows );
		$ranked              = self::rank_aggregate_rows( $aggregate );

		if ( empty( $ranked ) ) {
			self::log( 0, 0, 'generation', 'warning', 'Generation produced no eligible entries after methodology filters.', array( 'chart_id' => $chart_id, 'country' => $country, 'chart_date' => $chart_date ) );
			self::release_operation_lock( $lock_name );
			return array( 'success' => false, 'step' => 'generation', 'message' => 'No eligible entries remained after applying scoring and eligibility rules.' );
		}

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
				'comparison_summary' => wp_json_encode(
					array(
						'new_entries'  => count(
							array_filter(
								$comparison['entries'],
								function ( $entry ) {
									return 'new' === $entry['movement'];
								}
							)
						),
						're_entries'   => count(
							array_filter(
								$comparison['entries'],
								function ( $entry ) {
									return 're-entry' === $entry['movement'];
								}
							)
						),
						'dropped_out'  => count( $comparison['dropped_out'] ),
					)
				),
				'dropped_out_json' => wp_json_encode( $comparison['dropped_out'] ),
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
		self::notify( 'info', 'Draft chart week generated and ready for review.', array( 'week_id' => $current_week_id, 'chart_id' => $chart_id, 'country' => $country, 'chart_date' => $chart_date ) );
		self::maybe_send_external_alert( 'chart_ready_to_generate', 'A draft chart week is ready for operator review.', array( 'week_id' => $current_week_id, 'chart_id' => $chart_id, 'country' => $country, 'chart_date' => $chart_date ) );
		self::release_operation_lock( $lock_name );

		return array(
			'success'      => true,
			'step'         => 'generation',
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
	 * Regenerate a chart week by id.
	 *
	 * @param int $week_id Week id.
	 * @return array
	 */
	public static function regenerate_chart_week( $week_id ) {
		$week = AMC_DB::get_row( 'chart_weeks', $week_id );
		$chart = $week ? AMC_DB::get_row( 'charts', (int) $week['chart_id'] ) : null;

		if ( ! $week || ! $chart ) {
			return array(
				'success' => false,
				'message' => 'Chart week could not be found for regeneration.',
			);
		}

		return self::generate_chart_week( (int) $week['chart_id'], $week['country'], $week['week_date'], $chart['type'] );
	}

	/**
	 * Publish a chart week and mark its source uploads live.
	 *
	 * @param int $week_id Week id.
	 * @param bool $force Whether to republish already-published weeks.
	 * @return array
	 */
	public static function publish_chart_week( $week_id, $force = false ) {
		$week = AMC_DB::get_row( 'chart_weeks', $week_id );

		if ( ! $week ) {
			return array(
				'success' => false,
				'step'    => 'publishing',
				'message' => 'Chart week could not be found.',
			);
		}

		$lock_name = 'week-publish-' . absint( $week_id );
		if ( ! self::acquire_operation_lock( $lock_name, 'Publish flow is already running for this chart week.' ) ) {
			return array(
				'success' => false,
				'step'    => 'publishing',
				'message' => 'Publish flow is already running for this chart week.',
			);
		}

		if ( 'published' === $week['status'] && ! $force ) {
			self::release_operation_lock( $lock_name );
			return array(
				'success' => false,
				'step'    => 'publishing',
				'message' => 'This chart week is already live. Use republish only when you intentionally want to refresh the live timestamp.',
			);
		}

		$safety = self::publication_safety_check( $week_id );

		if ( empty( $safety['success'] ) ) {
			self::log( 0, 0, 'publishing_check', 'warning', 'Publishing safety check blocked publication.', array( 'chart_week_id' => (int) $week_id, 'issues' => $safety['issues'] ) );
			self::notify( 'warning', 'Publishing was blocked by safety checks.', array( 'week_id' => $week_id, 'issues' => $safety['issues'] ) );
			self::maybe_send_external_alert( 'publish_blocked', 'Publishing was blocked by safety checks.', array( 'week_id' => $week_id, 'issues' => $safety['issues'] ) );
			self::release_operation_lock( $lock_name );
			return array(
				'success' => false,
				'step'    => 'publishing',
				'message' => implode( ' ', $safety['issues'] ),
			);
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
		self::log( 0, 0, $force ? 'republishing' : 'publishing', 'info', $force ? 'Chart week republished.' : 'Chart week published.', array( 'chart_week_id' => (int) $week_id ) );
		self::notify( 'success', $force ? 'Chart week republished successfully.' : 'Chart week published successfully.', array( 'week_id' => $week_id ) );
		self::maybe_send_external_alert( 'publish_completed', $force ? 'Chart week republished successfully.' : 'Chart week published successfully.', array( 'week_id' => $week_id, 'forced' => (bool) $force ) );
		self::release_operation_lock( $lock_name );
		return array(
			'success' => true,
			'step'    => 'publishing',
			'message' => $force ? 'Chart week republished successfully.' : 'Chart week published successfully.',
		);
	}

	/**
	 * Republish a chart week.
	 *
	 * @param int $week_id Week id.
	 * @return array
	 */
	public static function republish_chart_week( $week_id ) {
		return self::publish_chart_week( $week_id, true );
	}

	/**
	 * Run publication safety checks for a week.
	 *
	 * @param int $week_id Week id.
	 * @return array
	 */
	public static function publication_safety_check( $week_id ) {
		$week   = AMC_DB::get_row( 'chart_weeks', $week_id );
		$issues = array();

		if ( ! $week ) {
			return array(
				'success' => false,
				'issues'  => array( 'Chart week could not be found.' ),
			);
		}

		$entries = AMC_DB::get_chart_entries( $week_id );
		$chart   = AMC_DB::get_row( 'charts', (int) $week['chart_id'] );

		if ( empty( $entries ) ) {
			$issues[] = 'Chart week has no generated entries.';
		}

		if ( 'archived' === $week['status'] ) {
			$issues[] = 'Archived weeks should be restored to draft before publishing.';
		}

		if ( ! $chart || empty( $chart['type'] ) || empty( $chart['name'] ) ) {
			$issues[] = 'Chart metadata is incomplete.';
		}

		$rules = self::get_scoring_rules();
		$has_scoring = false;
		if ( ! empty( $rules['weights'] ) ) {
			foreach ( $rules['weights'] as $row ) {
				if ( '' !== trim( (string) $row['value'] ) && (float) $row['value'] > 0 ) {
					$has_scoring = true;
					break;
				}
			}
		}
		if ( ! $has_scoring ) {
			$issues[] = 'Scoring rules are missing or empty.';
		}

		$uploads = AMC_DB::get_rows(
			'source_uploads',
			array(
				'where' => array(
					'generated_week_id' => absint( $week_id ),
				),
			)
		);
		$rejected = 0;
		$review   = 0;
		foreach ( $uploads as $upload ) {
			$diag = ! empty( $upload['diagnostic_summary'] ) ? json_decode( $upload['diagnostic_summary'], true ) : array();
			if ( is_array( $diag ) ) {
				$rejected += ! empty( $diag['rejected_rows'] ) ? (int) $diag['rejected_rows'] : 0;
				$review   += ! empty( $diag['review_needed'] ) ? (int) $diag['review_needed'] : 0;
			}
		}

		if ( $review > 10 ) {
			$issues[] = 'Too many review-needed rows remain for this week.';
		}

		if ( $rejected > 25 ) {
			$issues[] = 'Too many rejected rows were produced for this week.';
		}

		$live_conflict = AMC_DB::get_rows(
			'chart_weeks',
			array(
				'where' => array(
					'chart_id' => (int) $week['chart_id'],
					'country'  => $week['country'],
					'status'   => 'published',
				),
			)
		);

		foreach ( $live_conflict as $live_week ) {
			if ( (int) $live_week['id'] !== (int) $week_id ) {
				$issues[] = 'Another live week already exists for this chart and country.';
				break;
			}
		}

		return array(
			'success' => empty( $issues ),
			'issues'  => $issues,
		);
	}

	/**
	 * Run cleanup/diagnostics helper task.
	 *
	 * @return array
	 */
	public static function run_cleanup_diagnostics() {
		$summary = array(
			'failed_jobs'     => AMC_DB::count_rows( 'jobs', array( 'status' => 'failed' ) ),
			'queued_jobs'     => AMC_DB::count_rows( 'jobs', array( 'status' => 'queued' ) ),
			'review_queue'    => AMC_DB::count_rows( 'matching_queue', array( 'status' => 'review_needed' ) ),
			'draft_weeks'     => AMC_DB::count_rows( 'chart_weeks', array( 'status' => 'draft' ) ),
		);

		self::log( 0, 0, 'diagnostics', 'info', 'Diagnostics snapshot completed.', $summary );

		return array(
			'success' => true,
			'step'    => 'diagnostics',
			'message' => 'Diagnostics snapshot completed.',
			'summary' => $summary,
		);
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
		self::notify( 'info', 'Chart week moved back to draft.', array( 'week_id' => $week_id ) );
		return true;
	}

	/**
	 * Roll back a week to the previous published week for the same chart + country.
	 *
	 * @param int $week_id Week id.
	 * @return array
	 */
	public static function rollback_chart_week( $week_id ) {
		$week = AMC_DB::get_row( 'chart_weeks', $week_id );

		if ( ! $week ) {
			return array(
				'success' => false,
				'message' => 'Chart week could not be found for rollback.',
			);
		}

		$previous = self::get_previous_published_week( (int) $week['chart_id'], $week['country'], $week['week_date'] );

		if ( ! $previous ) {
			return array(
				'success' => false,
				'message' => 'No previous published week was found for rollback.',
			);
		}

		if ( 'published' === $week['status'] ) {
			self::unpublish_chart_week( $week_id );
		}

		$result = self::publish_chart_week( (int) $previous['id'], true );
		self::log( 0, 0, 'rollback', 'warning', 'Rollback to previous published week executed.', array( 'from_week_id' => (int) $week_id, 'to_week_id' => (int) $previous['id'] ) );
		if ( ! empty( $result['success'] ) ) {
			self::notify( 'warning', 'Rollback completed successfully.', array( 'from_week_id' => $week_id, 'to_week_id' => (int) $previous['id'] ) );
		}

		return array(
			'success' => ! empty( $result['success'] ),
			'message' => ! empty( $result['success'] ) ? sprintf( 'Rollback completed. Week #%d is live again.', (int) $previous['id'] ) : $result['message'],
		);
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
	 * Get invalid rows for an upload.
	 *
	 * @param int $upload_id Upload id.
	 * @param int $limit Limit.
	 * @return array
	 */
	public static function invalid_rows( $upload_id, $limit = 100 ) {
		return AMC_DB::get_rows(
			'source_rows',
			array(
				'where'    => array(
					'upload_id' => absint( $upload_id ),
					'validation_status' => 'invalid',
				),
				'order_by' => 'row_index ASC',
				'limit'    => $limit,
			)
		);
	}

	/**
	 * Commit a dry-run upload into the live generation pool.
	 *
	 * @param int $upload_id Upload id.
	 * @return array
	 */
	public static function commit_dry_run_upload( $upload_id ) {
		$upload = AMC_DB::get_row( 'source_uploads', $upload_id );

		if ( ! $upload ) {
			return array(
				'success' => false,
				'message' => 'Dry-run upload could not be found.',
			);
		}

		if ( empty( $upload['is_dry_run'] ) ) {
			return array(
				'success' => false,
				'message' => 'This upload is already part of the active generation pool.',
			);
		}

		AMC_DB::save_row(
			'source_uploads',
			array(
				'is_dry_run' => 0,
			),
			$upload_id
		);

		self::log( $upload_id, 0, 'dry_run_commit', 'info', 'Dry-run upload committed into the active generation pool.', array() );

		return array(
			'success' => true,
			'message' => 'Dry-run upload committed successfully.',
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

		$expected_weights = array(
			'spotify_weight'       => 'Spotify',
			'youtube_music_weight' => 'YouTube Music',
			'tiktok_weight'        => 'TikTok',
			'shazam_weight'        => 'Shazam',
			'apple_music_weight'   => 'Apple Music',
			'anghami_weight'       => 'Anghami',
		);

		foreach ( $expected_weights as $key => $label ) {
			$exists = false;
			foreach ( $out['weights'] as $row ) {
				if ( $row['key'] === $key ) {
					$exists = true;
					break;
				}
			}
			if ( ! $exists ) {
				$out['weights'][] = array(
					'id'     => 0,
					'source' => $label,
					'key'    => $key,
					'weight' => '',
					'value'  => '',
				);
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
		$warnings        = array();

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
			'growth_bonus_multiplier',
			'fallback_metric_behavior',
			'minimum_source_count',
			'eligibility_mode',
		);

		foreach ( $methods as $index => $key ) {
			$fallback = isset( $current['methodology'][ self::humanize_rule_key( $key ) ] ) ? $current['methodology'][ self::humanize_rule_key( $key ) ] : '';
			$value    = isset( $payload[ $key ] ) ? sanitize_textarea_field( wp_unslash( $payload[ $key ] ) ) : $fallback;
			$type     = in_array( $key, array( 'growth_bonus_multiplier', 'minimum_source_count' ), true ) ? 'number' : 'text';

			if ( 'number' === $type && '' !== trim( (string) $value ) && ! is_numeric( $value ) ) {
				self::log( 0, 0, 'scoring', 'warning', 'Invalid scoring rule value ignored.', array( 'rule_key' => $key, 'value' => $value ) );
				$warnings[] = $key;
				$value = $fallback;
			}

			self::upsert_rule( 'methodology', $key, $value, $type, 10 + $index );
		}

		return array(
			'warnings' => $warnings,
		);
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
	 * Store an operator notification.
	 *
	 * @param string $type Type.
	 * @param string $message Message.
	 * @param array  $context Context.
	 * @return void
	 */
	private static function notify( $type, $message, $context = array() ) {
		$notifications = get_option( 'amc_operator_notifications', array() );
		$notifications[] = array(
			'id'         => uniqid( 'amc_notice_', true ),
			'type'       => sanitize_key( $type ),
			'message'    => sanitize_text_field( $message ),
			'context'    => $context,
			'status'     => 'unread',
			'is_read'    => 0,
			'is_dismissed' => 0,
			'created_at' => current_time( 'mysql' ),
			'read_at'    => '',
			'dismissed_at' => '',
		);

		if ( count( $notifications ) > 30 ) {
			$notifications = array_slice( $notifications, -30 );
		}

		update_option( 'amc_operator_notifications', $notifications, false );
	}

	/**
	 * Return stored operator notifications.
	 *
	 * @return array
	 */
	public static function notifications() {
		$notifications = get_option( 'amc_operator_notifications', array() );
		return is_array( $notifications ) ? $notifications : array();
	}

	/**
	 * Mark a notification as read.
	 *
	 * @param string $notification_id Notification id.
	 * @return array
	 */
	public static function mark_notification_read( $notification_id ) {
		return self::update_notification_state( $notification_id, 'read' );
	}

	/**
	 * Dismiss a notification.
	 *
	 * @param string $notification_id Notification id.
	 * @return array
	 */
	public static function dismiss_notification( $notification_id ) {
		return self::update_notification_state( $notification_id, 'dismissed' );
	}

	/**
	 * Mark matching notifications read in bulk.
	 *
	 * @param array $filters Filters.
	 * @return array
	 */
	public static function mark_notifications_read( $filters = array() ) {
		return self::bulk_update_notifications( $filters, 'read' );
	}

	/**
	 * Dismiss matching notifications in bulk.
	 *
	 * @param array $filters Filters.
	 * @return array
	 */
	public static function dismiss_notifications( $filters = array() ) {
		return self::bulk_update_notifications( $filters, 'dismissed' );
	}

	/**
	 * Update notification state.
	 *
	 * @param string $notification_id Notification id.
	 * @param string $state Target state.
	 * @return array
	 */
	private static function update_notification_state( $notification_id, $state ) {
		$notifications = self::notifications();
		$updated       = false;

		foreach ( $notifications as &$notification ) {
			if ( empty( $notification['id'] ) || $notification['id'] !== $notification_id ) {
				continue;
			}

			if ( 'read' === $state ) {
				$notification['status']  = 'read';
				$notification['is_read'] = 1;
				$notification['read_at'] = current_time( 'mysql' );
			}

			if ( 'dismissed' === $state ) {
				$notification['status']        = 'dismissed';
				$notification['is_dismissed']  = 1;
				$notification['dismissed_at']  = current_time( 'mysql' );
			}

			$updated = true;
		}
		unset( $notification );

		if ( $updated ) {
			update_option( 'amc_operator_notifications', $notifications, false );
			return array( 'success' => true, 'message' => 'Notification updated.' );
		}

		return array( 'success' => false, 'message' => 'Notification could not be found.' );
	}

	/**
	 * Bulk update notifications by filters.
	 *
	 * @param array  $filters Filters.
	 * @param string $state Target state.
	 * @return array
	 */
	private static function bulk_update_notifications( $filters, $state ) {
		$notifications = self::notifications();
		$count         = 0;

		foreach ( $notifications as &$notification ) {
			if ( ! self::notification_matches_filters( $notification, $filters ) ) {
				continue;
			}

			if ( 'read' === $state && empty( $notification['is_read'] ) ) {
				$notification['status']  = 'read';
				$notification['is_read'] = 1;
				$notification['read_at'] = current_time( 'mysql' );
				++$count;
			}

			if ( 'dismissed' === $state && empty( $notification['is_dismissed'] ) ) {
				$notification['status']       = 'dismissed';
				$notification['is_dismissed'] = 1;
				$notification['dismissed_at'] = current_time( 'mysql' );
				++$count;
			}
		}
		unset( $notification );

		update_option( 'amc_operator_notifications', $notifications, false );

		return array(
			'success' => true,
			'message' => $count ? sprintf( '%d notifications updated.', $count ) : 'No notifications matched the selected filters.',
		);
	}

	/**
	 * Check whether a notification matches filter values.
	 *
	 * @param array $notification Notification.
	 * @param array $filters Filters.
	 * @return bool
	 */
	private static function notification_matches_filters( $notification, $filters ) {
		if ( ! empty( $filters['ids'] ) ) {
			return in_array( $notification['id'], $filters['ids'], true );
		}

		if ( ! empty( $filters['severity'] ) && ( empty( $notification['type'] ) || $notification['type'] !== $filters['severity'] ) ) {
			return false;
		}

		if ( ! empty( $filters['status'] ) ) {
			if ( 'unread' === $filters['status'] && ! empty( $notification['is_read'] ) ) {
				return false;
			}

			if ( 'read' === $filters['status'] && empty( $notification['is_read'] ) ) {
				return false;
			}

			if ( 'dismissed' === $filters['status'] && empty( $notification['is_dismissed'] ) ) {
				return false;
			}
		}

		if ( ! empty( $filters['date'] ) && ( empty( $notification['created_at'] ) || 0 !== strpos( $notification['created_at'], $filters['date'] ) ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Build a lock key for a queued job.
	 *
	 * @param string $job_type Job type.
	 * @param array  $args Job payload.
	 * @return string
	 */
	private static function build_job_lock_key( $job_type, $args ) {
		$parts = array(
			sanitize_key( $job_type ),
			! empty( $args['reference_type'] ) ? sanitize_key( $args['reference_type'] ) : '',
			! empty( $args['reference_id'] ) ? absint( $args['reference_id'] ) : 0,
			! empty( $args['chart_id'] ) ? absint( $args['chart_id'] ) : 0,
			! empty( $args['country'] ) ? self::normalize_country( $args['country'] ) : '',
			! empty( $args['week_date'] ) ? sanitize_text_field( $args['week_date'] ) : ( ! empty( $args['chart_date'] ) ? sanitize_text_field( $args['chart_date'] ) : '' ),
		);

		return implode( ':', $parts );
	}

	/**
	 * Whether a job type can be rerun safely from the UI.
	 *
	 * @param string $job_type Job type.
	 * @return bool
	 */
	private static function job_type_rerunnable( $job_type ) {
		return in_array( $job_type, array( 'parse_upload', 'rerun_matching', 'auto_create_processing', 'generate_chart', 'publish_checks', 'cleanup_diagnostics' ), true );
	}

	/**
	 * Whether a job type can be retried automatically.
	 *
	 * @param string $job_type Job type.
	 * @return bool
	 */
	private static function job_type_retryable( $job_type ) {
		return in_array( $job_type, array( 'parse_upload', 'rerun_matching', 'auto_create_processing', 'generate_chart', 'publish_checks' ), true );
	}

	/**
	 * Retry policy per job type.
	 *
	 * @param string $job_type Job type.
	 * @return array
	 */
	private static function retry_policy_for_job_type( $job_type ) {
		$default = array(
			'max_attempts'       => 3,
			'retry_delay_seconds'=> 300,
		);

		$map = array(
			'parse_upload'           => array( 'max_attempts' => 2, 'retry_delay_seconds' => 180 ),
			'rerun_matching'         => array( 'max_attempts' => 2, 'retry_delay_seconds' => 180 ),
			'auto_create_processing' => array( 'max_attempts' => 2, 'retry_delay_seconds' => 240 ),
			'generate_chart'         => array( 'max_attempts' => 3, 'retry_delay_seconds' => 300 ),
			'publish_checks'         => array( 'max_attempts' => 2, 'retry_delay_seconds' => 300 ),
			'cleanup_diagnostics'    => array( 'max_attempts' => 1, 'retry_delay_seconds' => 600 ),
		);

		return ! empty( $map[ $job_type ] ) ? $map[ $job_type ] : $default;
	}

	/**
	 * Build payload for rerunning a completed job.
	 *
	 * @param array $job Job row.
	 * @return array
	 */
	private static function job_payload_for_rerun( $job ) {
		$payload = ! empty( $job['payload'] ) ? json_decode( $job['payload'], true ) : array();
		$payload = is_array( $payload ) ? $payload : array();
		$payload['trigger_mode'] = 'manual';
		return $payload;
	}

	/**
	 * Acquire a generic transient lock.
	 *
	 * @param string $name Lock name.
	 * @param int    $ttl TTL in seconds.
	 * @return bool
	 */
	private static function acquire_lock( $name, $ttl ) {
		$key = 'amc_lock_' . md5( $name );

		if ( get_transient( $key ) ) {
			return false;
		}

		return set_transient( $key, 1, $ttl );
	}

	/**
	 * Release a generic transient lock.
	 *
	 * @param string $name Lock name.
	 * @return void
	 */
	private static function release_lock( $name ) {
		delete_transient( 'amc_lock_' . md5( $name ) );
	}

	/**
	 * Acquire an operation lock with operator notification.
	 *
	 * @param string $name Lock name.
	 * @param string $message Busy message.
	 * @return bool
	 */
	private static function acquire_operation_lock( $name, $message ) {
		$acquired = self::acquire_lock( $name, 10 * MINUTE_IN_SECONDS );

		if ( ! $acquired ) {
			self::notify( 'warning', $message, array( 'lock' => $name ) );
		}

		return $acquired;
	}

	/**
	 * Release an operation lock.
	 *
	 * @param string $name Lock name.
	 * @return void
	 */
	private static function release_operation_lock( $name ) {
		self::release_lock( $name );
	}

	/**
	 * Send external alert notifications when configured.
	 *
	 * @param string $alert_type Alert type.
	 * @param string $message Message.
	 * @param array  $context Context.
	 * @return void
	 */
	private static function maybe_send_external_alert( $alert_type, $message, $context = array() ) {
		$settings     = AMC_DB::get_settings();
		$enabled_raw  = ! empty( $settings['alert_types_enabled'] ) ? $settings['alert_types_enabled'] : '';
		$enabled      = array_filter( array_map( 'trim', explode( ',', (string) $enabled_raw ) ) );

		if ( ! empty( $enabled ) && ! in_array( $alert_type, $enabled, true ) ) {
			return;
		}

		if ( ! empty( $settings['alert_email'] ) && is_email( $settings['alert_email'] ) ) {
			wp_mail(
				$settings['alert_email'],
				'Kontentainment Charts alert: ' . $alert_type,
				$message . "\n\n" . wp_json_encode( $context )
			);
		}

		if ( ! empty( $settings['alert_webhook_url'] ) ) {
			wp_remote_post(
				esc_url_raw( $settings['alert_webhook_url'] ),
				array(
					'timeout' => 10,
					'headers' => array( 'Content-Type' => 'application/json' ),
					'body'    => wp_json_encode(
						array(
							'brand'      => 'Kontentainment Charts',
							'alert_type' => $alert_type,
							'message'    => $message,
							'context'    => $context,
							'timestamp'  => current_time( 'mysql' ),
						)
					),
				)
			);
		}
	}

	/**
	 * Send threshold-driven alerts for an upload.
	 *
	 * @param int $upload_id Upload id.
	 * @return void
	 */
	private static function maybe_send_threshold_alerts_for_upload( $upload_id ) {
		$upload = AMC_DB::get_row( 'source_uploads', $upload_id );

		if ( ! $upload || empty( $upload['diagnostic_summary'] ) ) {
			return;
		}

		$diag     = json_decode( $upload['diagnostic_summary'], true );
		$review   = is_array( $diag ) && ! empty( $diag['review_needed'] ) ? (int) $diag['review_needed'] : 0;
		$rejected = is_array( $diag ) && ! empty( $diag['rejected_rows'] ) ? (int) $diag['rejected_rows'] : 0;

		if ( $review >= 10 ) {
			self::maybe_send_external_alert( 'too_many_review_needed_rows', 'An upload generated too many review-needed rows.', array( 'upload_id' => $upload_id, 'review_needed' => $review ) );
		}

		if ( $rejected >= 15 ) {
			self::maybe_send_external_alert( 'too_many_rejected_rows', 'An upload generated too many rejected rows.', array( 'upload_id' => $upload_id, 'rejected_rows' => $rejected ) );
		}

		$backlog = AMC_DB::jobs_summary();
		if ( ! empty( $backlog['queued'] ) && (int) $backlog['queued'] >= 10 ) {
			self::maybe_send_external_alert( 'queue_backlog_warning', 'Queued job backlog exceeded the warning threshold.', array( 'queued_jobs' => (int) $backlog['queued'] ) );
		}
	}

	/**
	 * Bulk job action handler.
	 *
	 * @param string $task Task.
	 * @param array  $job_ids Job ids.
	 * @return array
	 */
	public static function bulk_job_action( $task, $job_ids ) {
		$job_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $job_ids ) ) ) );
		$count   = 0;
		$errors  = array();

		foreach ( $job_ids as $job_id ) {
			if ( 'retry' === $task ) {
				$result = self::retry_job( $job_id );
			} elseif ( 'cancel' === $task ) {
				$result = self::cancel_job( $job_id );
			} else {
				$result = array( 'success' => false, 'message' => 'Unsupported bulk job action.' );
			}

			if ( ! empty( $result['success'] ) ) {
				++$count;
			} else {
				$errors[] = $result['message'];
			}
		}

		return array(
			'success' => $count > 0,
			'message' => $count ? sprintf( '%d jobs updated.', $count ) : ( ! empty( $errors ) ? implode( ' ', array_slice( $errors, 0, 3 ) ) : 'No jobs were updated.' ),
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
			$validation = self::validate_required_columns( $headers, array( array( 'artist', 'artist_name', 'name', 'channel', 'channel_name' ), array( 'rank', 'position', 'current_rank' ) ), 'YouTube Top Artists' );
			if ( ! empty( $validation ) ) {
				return $validation;
			}
			return array(
				'success'     => true,
				'parser_name' => 'youtube-top-artists-csv',
				'rows'        => self::parse_youtube_top_artists_rows( $headers, $rows, $context ),
			);
		}

		if ( 'youtube' === $platform && 'track' === $chart_type ) {
			$validation = self::validate_required_columns( $headers, array( array( 'title', 'track_title', 'song', 'song_title', 'name' ), array( 'artist', 'artist_name', 'artist_names', 'performer' ), array( 'rank', 'position', 'current_rank' ) ), 'YouTube Top Songs' );
			if ( ! empty( $validation ) ) {
				return $validation;
			}
			return array(
				'success'     => true,
				'parser_name' => 'youtube-top-songs-csv',
				'rows'        => self::parse_youtube_top_song_rows( $headers, $rows, $context ),
			);
		}

		if ( 'spotify' === $platform && 'track' === $chart_type ) {
			$validation = self::validate_required_columns( $headers, array( array( 'title', 'track_title', 'track_name', 'song', 'song_title', 'name' ), array( 'artist', 'artist_name', 'artist_names' ) ), 'Spotify Weekly' );
			if ( ! empty( $validation ) ) {
				return $validation;
			}
			return array(
				'success'     => true,
				'parser_name' => 'spotify-weekly-csv',
				'rows'        => self::parse_spotify_weekly_rows( $headers, $rows, $context ),
			);
		}

		if ( 'shazam' === $platform && 'track' === $chart_type ) {
			$validation = self::validate_required_columns( $headers, array( array( 'title', 'track_title', 'song', 'song_title', 'name' ), array( 'artist', 'artist_name', 'subtitle', 'artist_names' ), array( 'rank', 'position', 'current_rank' ) ), 'Shazam Chart' );
			if ( ! empty( $validation ) ) {
				return $validation;
			}
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

		$validation = self::validate_normalized_payload(
			array(
				'chart_type'      => $chart_type,
				'rank'            => ! empty( $payload['rank'] ) ? absint( $payload['rank'] ) : 0,
				'track_title'     => $track_title,
				'artist_name'     => $artist_name,
				'artist_names'    => $artist_names,
				'normalized_title'=> $normalized_title,
				'normalized_artist' => $normalized_artist,
			)
		);

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
			'validation_status'   => $validation['status'],
			'validation_message'  => $validation['message'],
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
		$minimum_source_count = max( 1, (int) self::methodology_value( 'minimum_source_count', 1 ) );
		$items = array_values(
			array_filter(
				$aggregate,
				function ( $item ) use ( $minimum_source_count ) {
					return (int) $item['source_count'] >= $minimum_source_count;
				}
			)
		);

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
				$entity = AMC_Data::get_entity( $entry['entity_type'], (int) $entry['entity_id'] );
				$dropped_out[] = array(
					'entity_type'   => $entry['entity_type'],
					'entity_id'     => (int) $entry['entity_id'],
					'name'          => $entity ? $entity['name'] : $key,
					'previous_rank' => (int) $entry['current_rank'],
				);
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
	 * Get previous published chart week by chart/country/date.
	 *
	 * @param int    $chart_id Chart id.
	 * @param string $country Country.
	 * @param string $chart_date Date.
	 * @return array|null
	 */
	private static function get_previous_published_week( $chart_id, $country, $chart_date ) {
		global $wpdb;

		$table = AMC_DB::table( 'chart_weeks' );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE chart_id = %d AND country = %s AND status = %s AND week_date < %s ORDER BY week_date DESC, id DESC LIMIT 1",
				absint( $chart_id ),
				$country,
				'published',
				$chart_date
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $row ? $row : null;
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
		$review       = 0;
		$invalid      = 0;

		foreach ( $rows as $row ) {
			if ( 'matched' === $row['matching_status'] ) {
				++$matched;
			} elseif ( 'review_needed' === $row['matching_status'] ) {
				++$review;
			} elseif ( 'invalid' === $row['matching_status'] ) {
				++$invalid;
			} elseif ( 'rejected' === $row['matching_status'] ) {
				++$rejected;
			} else {
				++$pending;
			}
		}

		$status = 'parsed';

		if ( $matched > 0 && 0 === $pending && 0 === $review ) {
			$status = 'matched';
		} elseif ( $matched > 0 || $review > 0 ) {
			$status = 'matched';
		} elseif ( $rejected > 0 || $invalid > 0 ) {
			$status = 'parsed';
		}

		AMC_DB::save_row( 'source_uploads', array( 'file_status' => $status ), $upload_id );
	}

	/**
	 * Refresh upload diagnostics summary.
	 *
	 * @param int $upload_id Upload id.
	 * @return void
	 */
	private static function refresh_upload_diagnostics( $upload_id ) {
		$rows         = AMC_DB::get_rows( 'source_rows', array( 'where' => array( 'upload_id' => absint( $upload_id ) ) ) );
		$summary      = array(
			'row_count'         => count( $rows ),
			'valid_rows'        => 0,
			'invalid_rows'      => 0,
			'matched_rows'      => 0,
			'review_needed'     => 0,
			'auto_created_rows' => 0,
			'rejected_rows'     => 0,
			'pending_rows'      => 0,
		);

		foreach ( $rows as $row ) {
			if ( 'invalid' === $row['validation_status'] ) {
				++$summary['invalid_rows'];
			} else {
				++$summary['valid_rows'];
			}

			if ( 'matched' === $row['matching_status'] ) {
				++$summary['matched_rows'];
				if ( 'auto_created' === $row['match_resolution'] ) {
					++$summary['auto_created_rows'];
				}
			} elseif ( 'review_needed' === $row['matching_status'] ) {
				++$summary['review_needed'];
			} elseif ( 'rejected' === $row['matching_status'] || 'invalid' === $row['matching_status'] ) {
				++$summary['rejected_rows'];
			} else {
				++$summary['pending_rows'];
			}
		}

		AMC_DB::save_row(
			'source_uploads',
			array(
				'diagnostic_summary' => wp_json_encode( $summary ),
			),
			$upload_id
		);
	}

	/**
	 * Read delimited rows.
	 *
	 * @param string $path File path.
	 * @param string $delimiter Delimiter.
	 * @return array
	 */
	private static function read_tabular_rows( $path, $extension ) {
		if ( in_array( $extension, array( 'csv', 'txt', 'tsv' ), true ) ) {
			$delimiter = 'tsv' === $extension ? "\t" : ',';
			return array(
				'success'     => true,
				'parser_name' => 'delimited-' . $extension,
				'rows'        => self::read_delimited_rows( $path, $delimiter ),
			);
		}

		if ( 'xlsx' === $extension ) {
			$xlsx = \Shuchkin\SimpleXLSX::parse( $path );

			if ( ! $xlsx ) {
				return array(
					'success'     => false,
					'parser_name' => 'xlsx-parser',
					'message'     => 'XLSX parsing failed: ' . \Shuchkin\SimpleXLSX::parseError(),
				);
			}

			return array(
				'success'     => true,
				'parser_name' => 'xlsx-parser',
				'rows'        => $xlsx->rows(),
			);
		}

		if ( 'xls' === $extension ) {
			$xls = \Shuchkin\SimpleXLS::parse( $path );

			if ( ! $xls ) {
				return array(
					'success'     => false,
					'parser_name' => 'xls-parser',
					'message'     => 'XLS parsing failed: ' . \Shuchkin\SimpleXLS::parseError(),
				);
			}

			return array(
				'success'     => true,
				'parser_name' => 'xls-parser',
				'rows'        => $xls->rows(),
			);
		}

		return array(
			'success'     => false,
			'parser_name' => 'unsupported-file',
			'message'     => 'Unsupported tabular format.',
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
		$tracks      = AMC_DB::get_rows( 'tracks', array( 'order_by' => 'id ASC' ) );
		$best        = null;
		$best_score  = 0;
		$best_basis  = 'No exact candidate';
		$best_level  = 'review needed';

		foreach ( $tracks as $track ) {
			$artist          = ! empty( $track['artist_id'] ) ? AMC_DB::get_row( 'artists', (int) $track['artist_id'] ) : null;
			$track_aliases   = self::track_aliases( $track );
			$artist_aliases  = $artist ? self::artist_aliases( $artist ) : array();
			$title_result    = self::best_alias_match_score( $row['normalized_title'], $track_aliases );
			$artist_result   = self::best_alias_match_score( $row['normalized_artist'], $artist_aliases );
			$combined_score  = ( $title_result['score'] * 0.7 ) + ( $artist_result['score'] * 0.3 );
			$basis           = 'Alias similarity review';

			if ( ! empty( $row['normalized_isrc'] ) && ! empty( $track['isrc'] ) && strtoupper( $track['isrc'] ) === strtoupper( $row['normalized_isrc'] ) ) {
				return array(
					'candidate'  => $track,
					'confidence' => 100,
					'level'      => 'exact',
					'basis'      => 'Exact ISRC match',
				);
			}

			if ( 100 === $title_result['score'] && ( empty( $row['normalized_artist'] ) || 100 === $artist_result['score'] ) ) {
				return array(
					'candidate'  => $track,
					'confidence' => 99,
					'level'      => 'exact',
					'basis'      => 'Exact normalized title + artist match',
				);
			}

			if ( $title_result['alias'] && $artist_result['score'] >= 90 ) {
				$basis = 'Alias-based title match with strong artist confirmation';
			} elseif ( $title_result['score'] >= 92 && $artist_result['score'] >= 84 ) {
				$basis = 'High-confidence normalized similarity';
			}

			if ( $combined_score > $best_score ) {
				$best_score = $combined_score;
				$best       = $track;
				$best_basis = $basis;
				$best_level = $combined_score >= 92 ? 'high confidence' : ( $combined_score >= 76 ? 'review needed' : 'no match' );
			}
		}

		if ( $best && $best_score >= 76 ) {
			return array(
				'candidate'  => $best,
				'confidence' => round( $best_score, 2 ),
				'level'      => $best_level,
				'basis'      => $best_basis,
			);
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
		$artists     = AMC_DB::get_rows( 'artists', array( 'order_by' => 'id ASC' ) );
		$best        = null;
		$best_score  = 0;
		$best_basis  = 'No exact candidate';
		$best_level  = 'review needed';

		foreach ( $artists as $artist ) {
			$aliases = self::artist_aliases( $artist );
			$result  = self::best_alias_match_score( $row['normalized_artist'], $aliases );

			if ( 100 === $result['score'] ) {
				return array(
					'candidate'  => $artist,
					'confidence' => 99,
					'level'      => 'exact',
					'basis'      => $result['alias'] ? 'Artist alias exact match' : 'Artist exact normalized match',
				);
			}

			if ( $result['score'] > $best_score ) {
				$best_score = $result['score'];
				$best       = $artist;
				$best_basis = $result['alias'] ? 'Artist alias similarity' : 'Artist normalized similarity';
				$best_level = $best_score >= 92 ? 'high confidence' : ( $best_score >= 78 ? 'review needed' : 'no match' );
			}
		}

		if ( $best && $best_score >= 78 ) {
			return array(
				'candidate'  => $best,
				'confidence' => round( $best_score, 2 ),
				'level'      => $best_level,
				'basis'      => $best_basis,
			);
		}

		return null;
	}

	/**
	 * Find album candidate.
	 *
	 * @param array $row Source row.
	 * @param int   $artist_id Artist id.
	 * @return array|null
	 */
	private static function find_album_candidate( $row, $artist_id = 0 ) {
		if ( empty( $row['normalized_album'] ) ) {
			return null;
		}

		$albums      = AMC_DB::get_rows( 'albums', array( 'order_by' => 'id ASC' ) );
		$best        = null;
		$best_score  = 0;
		$best_basis  = 'No album candidate';
		$best_level  = 'review needed';

		foreach ( $albums as $album ) {
			if ( $artist_id > 0 && ! empty( $album['artist_id'] ) && (int) $album['artist_id'] !== (int) $artist_id ) {
				continue;
			}

			$result = self::best_alias_match_score( $row['normalized_album'], array( self::normalize_text( $album['title'] ) ) );

			if ( 100 === $result['score'] ) {
				return array(
					'candidate'  => $album,
					'confidence' => 99,
					'level'      => 'exact',
					'basis'      => 'Exact normalized album match',
				);
			}

			if ( $result['score'] > $best_score ) {
				$best_score = $result['score'];
				$best       = $album;
				$best_basis = 'Album normalized similarity';
				$best_level = $best_score >= 92 ? 'high confidence' : ( $best_score >= 78 ? 'review needed' : 'no match' );
			}
		}

		if ( $best && $best_score >= 78 ) {
			return array(
				'candidate'  => $best,
				'confidence' => round( $best_score, 2 ),
				'level'      => $best_level,
				'basis'      => $best_basis,
			);
		}

		return null;
	}

	/**
	 * Auto-create a safe entity from a normalized row.
	 *
	 * @param array  $row Source row.
	 * @param string $type Target entity type.
	 * @return array
	 */
	private static function auto_create_entity_from_row( $row, $type ) {
		if ( 'artist' === $type ) {
			if ( empty( $row['normalized_artist'] ) ) {
				return array( 'success' => false, 'reason' => 'Artist name is empty after normalization.' );
			}

			$artist_id = self::create_artist_from_row( $row );

			return $artist_id ? array(
				'success'     => true,
				'entity_id'   => $artist_id,
				'entity_type' => 'artist',
				'label'       => $row['artist_name'],
				'basis'       => 'Created a new artist because the row was valid, unmatched, and unambiguous.',
			) : array(
				'success' => false,
				'reason'  => 'Artist could not be auto-created safely.',
			);
		}

		if ( empty( $row['normalized_title'] ) || empty( $row['normalized_artist'] ) ) {
			return array( 'success' => false, 'reason' => 'Track title or artist metadata is missing after normalization.' );
		}

		$artist_review = self::find_artist_candidate(
			array(
				'normalized_artist' => $row['normalized_artist'],
			)
		);

		if ( $artist_review && 'review needed' === $artist_review['level'] ) {
			return array( 'success' => false, 'reason' => 'Artist candidate is ambiguous and needs manual review.' );
		}

		$artist_id = $artist_review && ! empty( $artist_review['candidate']['id'] ) ? (int) $artist_review['candidate']['id'] : self::create_artist_from_row( $row );

		if ( ! $artist_id ) {
			return array( 'success' => false, 'reason' => 'Artist record could not be resolved or created safely.' );
		}

		$album_id = 0;
		if ( ! empty( $row['normalized_album'] ) ) {
			$album_review = self::find_album_candidate( $row, $artist_id );

			if ( $album_review && 'review needed' === $album_review['level'] ) {
				return array( 'success' => false, 'reason' => 'Album metadata is ambiguous and needs review before creating the track.' );
			}

			$album_id = $album_review && ! empty( $album_review['candidate']['id'] ) ? (int) $album_review['candidate']['id'] : self::create_album_from_row( $row, $artist_id );
		}

		$track_id = self::create_track_from_row( $row, $artist_id, $album_id );

		return $track_id ? array(
			'success'     => true,
			'entity_id'   => $track_id,
			'entity_type' => 'track',
			'label'       => $row['track_title'],
			'basis'       => 'Created a new track because the row was valid, unmatched, and unambiguous.',
			'artist_id'   => $artist_id,
			'album_id'    => $album_id,
		) : array(
			'success' => false,
			'reason'  => 'Track record could not be auto-created safely.',
		);
	}

	/**
	 * Create artist from row.
	 *
	 * @param array $row Source row.
	 * @return int
	 */
	private static function create_artist_from_row( $row ) {
		$name = ! empty( $row['artist_name'] ) ? $row['artist_name'] : $row['artist_names'];

		if ( ! $name ) {
			return 0;
		}

		$slug = self::unique_slug( 'artists', sanitize_title( $name ) );

		return AMC_DB::save_row(
			'artists',
			array(
				'name'              => $name,
				'slug'              => $slug,
				'image'             => '',
				'aliases'           => '',
				'bio'               => '',
				'blurb'             => '',
				'country'           => ! empty( $row['country'] ) ? $row['country'] : '',
				'genre'             => '',
				'social_links'      => '',
				'monthly_listeners' => '',
				'chart_streak'      => '',
				'gradient'          => 'ocean',
				'status'            => 'active',
			)
		);
	}

	/**
	 * Create album from row.
	 *
	 * @param array $row Source row.
	 * @param int   $artist_id Artist id.
	 * @return int
	 */
	private static function create_album_from_row( $row, $artist_id ) {
		if ( empty( $row['album_name'] ) ) {
			return 0;
		}

		$slug = self::unique_slug( 'albums', sanitize_title( $row['album_name'] ) );

		return AMC_DB::save_row(
			'albums',
			array(
				'artist_id'    => absint( $artist_id ),
				'title'        => $row['album_name'],
				'slug'         => $slug,
				'cover_image'  => '',
				'description'  => '',
				'release_date' => null,
				'release_year' => '',
				'track_list'   => '',
				'genre'        => '',
				'label'        => '',
				'gradient'     => 'ocean',
				'status'       => 'active',
			)
		);
	}

	/**
	 * Create track from row.
	 *
	 * @param array $row Source row.
	 * @param int   $artist_id Artist id.
	 * @param int   $album_id Album id.
	 * @return int
	 */
	private static function create_track_from_row( $row, $artist_id, $album_id ) {
		if ( empty( $row['track_title'] ) ) {
			return 0;
		}

		$slug = self::unique_slug( 'tracks', sanitize_title( $row['track_title'] ) );

		return AMC_DB::save_row(
			'tracks',
			array(
				'artist_id'    => absint( $artist_id ),
				'album_id'     => absint( $album_id ),
				'title'        => $row['track_title'],
				'slug'         => $slug,
				'cover_image'  => '',
				'description'  => '',
				'isrc'         => ! empty( $row['normalized_isrc'] ) ? $row['normalized_isrc'] : '',
				'aliases'      => '',
				'release_date' => null,
				'genre'        => '',
				'duration'     => '',
				'gradient'     => 'ocean',
				'status'       => 'active',
			)
		);
	}

	/**
	 * Build unique slug for entity table.
	 *
	 * @param string $table_key Table key.
	 * @param string $base_slug Base slug.
	 * @return string
	 */
	private static function unique_slug( $table_key, $base_slug ) {
		$base_slug = $base_slug ? $base_slug : 'item';
		$slug      = $base_slug;
		$index     = 2;

		while ( AMC_DB::get_row_by_slug( $table_key, $slug ) ) {
			$slug = $base_slug . '-' . $index;
			++$index;
		}

		return $slug;
	}

	/**
	 * Resolve queue row type.
	 *
	 * @param string $status Queue status.
	 * @param int    $entity_id Entity id.
	 * @param string $label Candidate label.
	 * @param string $basis Basis.
	 * @return string
	 */
	private static function queue_row_type( $status, $entity_id, $label, $basis ) {
		if ( 'auto_created' === $status ) {
			return 'ready to create';
		}

		if ( 'approved' === $status ) {
			return 'matched automatically';
		}

		if ( 'review_needed' === $status && $entity_id > 0 ) {
			return 'possible duplicate';
		}

		if ( 'review_needed' === $status && 0 === $entity_id ) {
			return 'unmatched';
		}

		return 'review needed';
	}

	/**
	 * Resolve queue action hint.
	 *
	 * @param string $status Queue status.
	 * @param int    $entity_id Entity id.
	 * @return string
	 */
	private static function queue_action_hint( $status, $entity_id ) {
		if ( 'auto_created' === $status ) {
			return 'System created a new entity automatically because the row was valid and unambiguous.';
		}

		if ( 'approved' === $status ) {
			return 'System matched this row automatically to an existing entity.';
		}

		if ( 'review_needed' === $status && $entity_id > 0 ) {
			return 'Review before confirming this possible duplicate or near-match.';
		}

		return 'Review or override before generation. No safe automatic target was available.';
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

		$fallback_behavior = strtolower( (string) self::methodology_value( 'fallback_metric_behavior', 'Use rank fallback when source metric is missing' ) );

		if ( false === strpos( $fallback_behavior, 'rank fallback' ) ) {
			return 0;
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
	 * Read a single methodology value.
	 *
	 * @param string $key Rule key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	private static function methodology_value( $key, $default = '' ) {
		global $wpdb;

		$table = AMC_DB::table( 'scoring_rules' );
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT rule_value FROM {$table} WHERE rule_key = %s LIMIT 1", $key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return null !== $value ? $value : $default;
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

		$multiplier = (float) self::methodology_value( 'growth_bonus_multiplier', 1 );
		return max( -0.05, min( 0.05, ( (float) $clean / 1000 ) * $multiplier ) );
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
	 * Validate required columns for a parser.
	 *
	 * @param array  $headers Headers.
	 * @param array  $groups Required groups.
	 * @param string $label Parser label.
	 * @return array
	 */
	private static function validate_required_columns( $headers, $groups, $label ) {
		$available = array_filter( $headers );
		$missing   = array();

		foreach ( $groups as $group ) {
			$found = false;
			foreach ( $group as $candidate ) {
				if ( in_array( $candidate, $available, true ) ) {
					$found = true;
					break;
				}
			}
			if ( ! $found ) {
				$missing[] = implode( ' / ', $group );
			}
		}

		if ( empty( $missing ) ) {
			return array();
		}

		return array(
			'success'     => false,
			'parser_name' => 'missing-required-columns',
			'message'     => sprintf( '%1$s file is missing required columns: %2$s.', $label, implode( ', ', $missing ) ),
		);
	}

	/**
	 * Validate a normalized payload.
	 *
	 * @param array $payload Payload.
	 * @return array
	 */
	private static function validate_normalized_payload( $payload ) {
		if ( empty( $payload['rank'] ) ) {
			return array(
				'status'  => 'invalid',
				'message' => 'Row rejected because rank is missing or invalid.',
			);
		}

		if ( 'artist' === $payload['chart_type'] ) {
			if ( empty( $payload['artist_name'] ) || empty( $payload['normalized_artist'] ) ) {
				return array(
					'status'  => 'invalid',
					'message' => 'Row rejected because artist name is empty after normalization.',
				);
			}
		} else {
			if ( empty( $payload['track_title'] ) || empty( $payload['normalized_title'] ) ) {
				return array(
					'status'  => 'invalid',
					'message' => 'Row rejected because track title is empty after normalization.',
				);
			}

			if ( empty( $payload['artist_names'] ) || empty( $payload['normalized_artist'] ) ) {
				return array(
					'status'  => 'invalid',
					'message' => 'Row rejected because artist metadata is empty after normalization.',
				);
			}
		}

		return array(
			'status'  => 'valid',
			'message' => '',
		);
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
		$value = trim( (string) $value );

		if ( function_exists( 'mb_strtolower' ) ) {
			$value = mb_strtolower( $value, 'UTF-8' );
		} else {
			$value = strtolower( $value );
		}

		$value = str_replace( array( 'ـ', '“', '”', '’', '‘', '`', '´', '–', '—', '_', '|', '•' ), ' ', $value );
		$value = preg_replace( '/[\x{064B}-\x{065F}\x{0670}]/u', '', $value );
		$value = str_replace( array( 'أ', 'إ', 'آ', 'ٱ' ), 'ا', $value );
		$value = str_replace( array( 'ى', 'ئ' ), 'ي', $value );
		$value = str_replace( 'ة', 'ه', $value );
		$value = str_replace( 'ؤ', 'و', $value );
		$value = self::strip_noise_suffixes( $value );
		$value = preg_replace( '/\b(feat|ft|featuring)\b\.?/u', ' ', $value );
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
	 * Remove common non-title suffixes.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function strip_noise_suffixes( $value ) {
		$patterns = array(
			'/\b(official audio|official video|audio|video|lyrics?|lyric video|visualizer|live|performance|clip officiel)\b/u',
			'/\(([^)]*(official audio|official video|lyrics?|visualizer|live)[^)]*)\)/u',
			'/\[([^\]]*(official audio|official video|lyrics?|visualizer|live)[^\]]*)\]/u',
		);

		return trim( preg_replace( $patterns, ' ', $value ) );
	}

	/**
	 * Build artist aliases.
	 *
	 * @param array $artist Artist row.
	 * @return array
	 */
	private static function artist_aliases( $artist ) {
		$aliases = array( self::normalize_text( $artist['name'] ) );
		if ( ! empty( $artist['aliases'] ) ) {
			$aliases = array_merge( $aliases, preg_split( '/[,|\n]+/', (string) $artist['aliases'] ) );
		}

		return self::normalize_alias_list( $aliases );
	}

	/**
	 * Build track aliases.
	 *
	 * @param array $track Track row.
	 * @return array
	 */
	private static function track_aliases( $track ) {
		$aliases = array( self::normalize_text( $track['title'] ) );
		if ( ! empty( $track['aliases'] ) ) {
			$aliases = array_merge( $aliases, preg_split( '/[,|\n]+/', (string) $track['aliases'] ) );
		}

		return self::normalize_alias_list( $aliases );
	}

	/**
	 * Normalize alias list.
	 *
	 * @param array $aliases Aliases.
	 * @return array
	 */
	private static function normalize_alias_list( $aliases ) {
		$out = array();
		foreach ( $aliases as $alias ) {
			$normalized = self::normalize_text( $alias );
			if ( $normalized ) {
				$out[] = $normalized;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Find best match score against aliases.
	 *
	 * @param string $needle Normalized input.
	 * @param array  $aliases Aliases.
	 * @return array
	 */
	private static function best_alias_match_score( $needle, $aliases ) {
		$needle = self::normalize_text( $needle );
		$best   = array(
			'score' => 0,
			'alias' => false,
		);

		if ( ! $needle ) {
			return $best;
		}

		foreach ( $aliases as $index => $alias ) {
			if ( $needle === $alias ) {
				return array(
					'score' => 100,
					'alias' => $index > 0,
				);
			}

			similar_text( $needle, $alias, $percent );
			if ( $percent > $best['score'] ) {
				$best = array(
					'score' => (float) $percent,
					'alias' => $index > 0,
				);
			}
		}

		return $best;
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
