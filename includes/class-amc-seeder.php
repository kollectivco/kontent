<?php
/**
 * Demo data seeding for plugin database tables.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AMC_Seeder {
	/**
	 * Seed plugin data if missing.
	 *
	 * @return void
	 */
	public static function seed() {
		if ( AMC_DB::count_rows( 'charts' ) > 0 ) {
			update_option( 'amc_demo_seeded', 1, false );
			return;
		}

		$payload      = AMC_Data::demo_payload();
		$definitions  = AMC_Data::chart_definitions();
		$artist_ids   = array();
		$album_ids    = array();
		$track_ids    = array();
		$chart_ids    = array();
		$today        = '2026-03-20';
		$last_week    = '2026-03-13';

		foreach ( $payload['artists'] as $slug => $artist ) {
			$artist_ids[ $slug ] = AMC_DB::save_row(
				'artists',
				array(
					'name'              => $artist['name'],
					'slug'              => $slug,
					'image'             => '',
					'aliases'           => '',
					'bio'               => $artist['description'],
					'blurb'             => $artist['blurb'],
					'country'           => $artist['country'],
					'genre'             => $artist['genres'],
					'social_links'      => '',
					'monthly_listeners' => $artist['monthly'],
					'chart_streak'      => $artist['streak'],
					'gradient'          => $artist['gradient'],
					'status'            => 'active',
				)
			);
		}

		foreach ( $payload['albums'] as $slug => $album ) {
			$album_ids[ $slug ] = AMC_DB::save_row(
				'albums',
				array(
					'artist_id'     => ! empty( $artist_ids[ $album['artist_slug'] ] ) ? $artist_ids[ $album['artist_slug'] ] : 0,
					'title'         => $album['title'],
					'slug'          => $slug,
					'cover_image'   => '',
					'description'   => $album['description'],
					'release_date'  => $album['year'] . '-01-01',
					'release_year'  => $album['year'],
					'track_list'    => '',
					'genre'         => '',
					'label'         => '',
					'gradient'      => $album['gradient'],
					'status'        => 'active',
				)
			);
		}

		foreach ( $payload['tracks'] as $slug => $track ) {
			$track_ids[ $slug ] = AMC_DB::save_row(
				'tracks',
				array(
					'artist_id'    => ! empty( $artist_ids[ $track['artist_slug'] ] ) ? $artist_ids[ $track['artist_slug'] ] : 0,
					'album_id'     => ! empty( $track['album_slug'] ) && ! empty( $album_ids[ $track['album_slug'] ] ) ? $album_ids[ $track['album_slug'] ] : 0,
					'title'        => $track['title'],
					'slug'         => $slug,
					'cover_image'  => '',
					'description'  => $track['description'],
					'isrc'         => '',
					'aliases'      => '',
					'release_date' => '2026-01-01',
					'genre'        => '',
					'duration'     => $track['duration'],
					'gradient'     => $track['gradient'],
					'status'       => 'active',
				)
			);
		}

		foreach ( $definitions as $slug => $definition ) {
			$chart_ids[ $slug ] = AMC_DB::save_row(
				'charts',
				array(
					'name'             => $definition['title'],
					'slug'             => $slug,
					'description'      => $definition['description'],
					'type'             => $definition['type'],
					'cover_image'      => '',
					'display_order'    => count( $chart_ids ) + 1,
					'status'           => 'active',
					'is_featured_home' => in_array( $slug, array( 'top-artists', 'top-tracks', 'hot-100-tracks' ), true ) ? 1 : 0,
					'archive_enabled'  => 1,
					'accent'           => $definition['accent'],
					'kicker'           => $definition['kicker'],
				)
			);

			$current_week_id = AMC_DB::save_row(
				'chart_weeks',
				array(
					'chart_id'      => $chart_ids[ $slug ],
					'country'       => 'Global',
					'week_date'     => $today,
					'status'        => 'published',
					'is_featured'   => 'hot-100-tracks' === $slug ? 1 : 0,
					'notes'         => 'Auto-seeded published week',
					'published_at'  => current_time( 'mysql' ),
					'archived_at'   => null,
				)
			);

			$archived_week_id = AMC_DB::save_row(
				'chart_weeks',
				array(
					'chart_id'      => $chart_ids[ $slug ],
					'country'       => 'Global',
					'week_date'     => $last_week,
					'status'        => 'archived',
					'is_featured'   => 0,
					'notes'         => 'Auto-seeded archive week',
					'published_at'  => current_time( 'mysql' ),
					'archived_at'   => current_time( 'mysql' ),
				)
			);

			if ( empty( $payload['charts'][ $slug ]['entries'] ) ) {
				continue;
			}

			foreach ( $payload['charts'][ $slug ]['entries'] as $index => $entry ) {
				$entity_id = 0;

				if ( 'artist' === $entry['entity_type'] && ! empty( $artist_ids[ $entry['slug'] ] ) ) {
					$entity_id = $artist_ids[ $entry['slug'] ];
				} elseif ( 'track' === $entry['entity_type'] && ! empty( $track_ids[ $entry['slug'] ] ) ) {
					$entity_id = $track_ids[ $entry['slug'] ];
				} elseif ( 'album' === $entry['entity_type'] && ! empty( $album_ids[ $entry['slug'] ] ) ) {
					$entity_id = $album_ids[ $entry['slug'] ];
				}

				$entry_data = array(
					'entity_type'    => $entry['entity_type'],
					'entity_id'      => $entity_id,
					'current_rank'   => $entry['current_rank'],
					'previous_rank'  => $entry['last_rank'],
					'peak_rank'      => $entry['peak_rank'],
					'weeks_on_chart' => $entry['weeks_on_chart'],
					'movement'       => $entry['movement'],
					'score'          => 100 - $index,
					'score_change'   => 0,
					'source_count'   => 1,
					'artwork'        => '',
				);

				AMC_DB::save_row( 'chart_entries', array_merge( $entry_data, array( 'chart_week_id' => $current_week_id ) ) );

				$archived_entry = $entry_data;
				$archived_entry['chart_week_id']   = $archived_week_id;
				$archived_entry['current_rank']    = max( 1, $entry['current_rank'] + ( 'up' === $entry['movement'] ? 1 : -1 ) );
				$archived_entry['previous_rank']   = $entry['current_rank'];
				$archived_entry['movement']        = 'same';
				$archived_entry['score']           = 95 - $index;
				$archived_entry['score_change']    = 0;
				$archived_entry['source_count']    = 1;
				AMC_DB::save_row( 'chart_entries', $archived_entry );
			}
		}

		AMC_DB::save_settings(
			array(
				'platform_name'    => 'Kontentainment Charts',
				'logo'             => 'kontentainment-charts-mark.svg',
				'seo_defaults'     => 'Enable chart-specific metadata',
				'social_image'     => 'weekly-share-default.jpg',
				'homepage_chart'   => 'hot-100-tracks',
				'methodology_text' => 'Custom weighted methodology summary',
				'language'         => 'English',
				'date_format'      => 'F j, Y',
			)
		);

		update_option( 'amc_demo_seeded', 1, false );
	}
}
