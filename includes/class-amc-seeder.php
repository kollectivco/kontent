<?php
/**
 * Demo data seeding for Phase 1.
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
		$payload = AMC_Data::demo_payload();
		$map     = array(
			'artists' => array(),
			'albums'  => array(),
			'tracks'  => array(),
			'charts'  => array(),
		);

		foreach ( $payload['artists'] as $slug => $artist ) {
			$map['artists'][ $slug ] = self::upsert_post(
				'amc_artist',
				$slug,
				$artist['name'],
				$artist['description'],
				$artist['blurb'],
				array(
					'_amc_country'           => $artist['country'],
					'_amc_genres'            => $artist['genres'],
					'_amc_monthly_listeners' => $artist['monthly'],
					'_amc_chart_streak'      => $artist['streak'],
					'_amc_gradient'          => $artist['gradient'],
				)
			);
		}

		foreach ( $payload['albums'] as $slug => $album ) {
			$map['albums'][ $slug ] = self::upsert_post(
				'amc_album',
				$slug,
				$album['title'],
				$album['description'],
				$album['description'],
				array(
					'_amc_artist_id'    => $map['artists'][ $album['artist_slug'] ],
					'_amc_release_year' => $album['year'],
					'_amc_gradient'     => $album['gradient'],
				)
			);
		}

		foreach ( $payload['tracks'] as $slug => $track ) {
			$map['tracks'][ $slug ] = self::upsert_post(
				'amc_track',
				$slug,
				$track['title'],
				$track['description'],
				$track['description'],
				array(
					'_amc_artist_id' => $map['artists'][ $track['artist_slug'] ],
					'_amc_album_id'  => ! empty( $track['album_slug'] ) && ! empty( $map['albums'][ $track['album_slug'] ] ) ? $map['albums'][ $track['album_slug'] ] : '',
					'_amc_duration'  => $track['duration'],
					'_amc_gradient'  => $track['gradient'],
				)
			);
		}

		foreach ( AMC_Data::chart_definitions() as $slug => $chart ) {
			$entries            = array();
			$payload_entries    = isset( $payload['charts'][ $slug ]['entries'] ) ? $payload['charts'][ $slug ]['entries'] : array();
			$map_key_lookup     = array(
				'artist' => 'artists',
				'track'  => 'tracks',
				'album'  => 'albums',
			);

			foreach ( $payload_entries as $entry ) {
				$bucket = $map_key_lookup[ $entry['entity_type'] ];

				$entries[] = array(
					'entity_type'    => $entry['entity_type'],
					'entity_id'      => $map[ $bucket ][ $entry['slug'] ],
					'current_rank'   => $entry['current_rank'],
					'last_rank'      => $entry['last_rank'],
					'peak_rank'      => $entry['peak_rank'],
					'weeks_on_chart' => $entry['weeks_on_chart'],
					'movement'       => $entry['movement'],
				);
			}

			$chart_id = self::upsert_post(
				'amc_chart',
				$slug,
				$chart['title'],
				$chart['description'],
				$chart['kicker'],
				array(
					'_amc_chart_type'    => $chart['type'],
					'_amc_chart_accent'  => $chart['accent'],
					'_amc_chart_entries' => $entries,
				)
			);

			wp_set_object_terms( $chart_id, array( sanitize_title( $chart['type'] ) ), 'amc_chart_group', false );
			$map['charts'][ $slug ] = $chart_id;
		}

		update_option( 'amc_demo_seeded', 1, false );
	}

	/**
	 * Insert or update post with meta.
	 *
	 * @param string $post_type Post type.
	 * @param string $slug Post slug.
	 * @param string $title Post title.
	 * @param string $content Post content.
	 * @param string $excerpt Post excerpt.
	 * @param array  $meta Meta values.
	 * @return int
	 */
	private static function upsert_post( $post_type, $slug, $title, $content, $excerpt, $meta ) {
		$existing = get_page_by_path( $slug, OBJECT, $post_type );

		$postarr = array(
			'post_type'    => $post_type,
			'post_name'    => $slug,
			'post_title'   => $title,
			'post_content' => $content,
			'post_excerpt' => $excerpt,
			'post_status'  => 'publish',
		);

		if ( $existing ) {
			$postarr['ID'] = $existing->ID;
			$post_id       = wp_update_post( $postarr );
		} else {
			$post_id = wp_insert_post( $postarr );
		}

		if ( is_wp_error( $post_id ) ) {
			return 0;
		}

		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		return (int) $post_id;
	}
}
