<?php
/**
 * Data access helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AMC_Data {
	/**
	 * Chart configuration.
	 *
	 * @return array
	 */
	public static function chart_definitions() {
		$charts = array(
			'top-artists'     => array(
				'slug'        => 'top-artists',
				'title'       => 'Top Artists',
				'type'        => 'artist',
				'kicker'      => 'Weekly Artist Power Index',
				'description' => 'The region’s most influential artists this week, ranked by momentum, reach, and cultural impact.',
				'accent'      => 'amber',
			),
			'top-tracks'      => array(
				'slug'        => 'top-tracks',
				'title'       => 'Top Tracks',
				'type'        => 'track',
				'kicker'      => 'Weekly Song Leaders',
				'description' => 'The biggest records in rotation right now, blending streaming heat with editorial energy.',
				'accent'      => 'crimson',
			),
			'top-albums'      => array(
				'slug'        => 'top-albums',
				'title'       => 'Top Albums',
				'type'        => 'album',
				'kicker'      => 'Essential Full-Length Releases',
				'description' => 'The albums shaping the week across pop, rap, indie, and crossover sounds.',
				'accent'      => 'teal',
			),
			'hot-100-tracks'  => array(
				'slug'        => 'hot-100-tracks',
				'title'       => 'Hot 100 Tracks',
				'type'        => 'track',
				'kicker'      => 'Mainstream Heat',
				'description' => 'The definitive song chart with the sharpest movers, strongest hooks, and longest-running hits.',
				'accent'      => 'violet',
			),
			'hot-100-artists' => array(
				'slug'        => 'hot-100-artists',
				'title'       => 'Hot 100 Artists',
				'type'        => 'artist',
				'kicker'      => 'Culture Movers',
				'description' => 'A weekly snapshot of the artists commanding the loudest conversation across the scene.',
				'accent'      => 'blue',
			),
		);

		return apply_filters( 'amc_chart_definitions', $charts );
	}

	/**
	 * Build page url.
	 *
	 * @param string $path Route path after base.
	 * @return string
	 */
	public static function route_url( $path = '' ) {
		$path = trim( $path, '/' );
		$base = home_url( trailingslashit( AMC_ROUTE_BASE ) );

		return $path ? trailingslashit( $base . $path ) : $base;
	}

	/**
	 * Public live-data state.
	 *
	 * @return array
	 */
	public static function public_state() {
		$active_charts   = AMC_DB::count_rows( 'charts', array( 'status' => 'active' ) );
		$published_weeks = AMC_DB::count_rows( 'chart_weeks', array( 'status' => 'published' ) );

		return array(
			'has_charts'         => $active_charts > 0,
			'has_published_data' => $published_weeks > 0,
			'active_chart_count' => $active_charts,
			'live_week_count'    => $published_weeks,
		);
	}

	/**
	 * Chart-specific live state.
	 *
	 * @param string $chart_slug Chart slug.
	 * @return array
	 */
	public static function chart_public_state( $chart_slug ) {
		$chart = AMC_DB::get_row_by_slug( 'charts', $chart_slug );

		if ( ! $chart ) {
			return array(
				'exists'       => false,
				'has_live_week'=> false,
			);
		}

		return array(
			'exists'        => true,
			'has_live_week' => (bool) AMC_DB::get_current_published_week( (int) $chart['id'] ),
		);
	}

	/**
	 * Home collections.
	 *
	 * @return array
	 */
	public static function get_home_context() {
		$charts   = self::get_all_charts();
		$settings = AMC_DB::get_settings();
		$hero_key = ! empty( $settings['homepage_chart'] ) ? $settings['homepage_chart'] : 'hot-100-tracks';
		$hero     = isset( $charts[ $hero_key ] ) ? $charts[ $hero_key ] : ( ! empty( $charts ) ? reset( $charts ) : null );

		return array(
			'hero_chart'      => $hero,
			'featured_charts' => array_values( array_slice( $charts, 0, 3 ) ),
			'more_charts'     => array_values( array_slice( $charts, 3, null, true ) ),
			'trending_tracks' => array_slice( self::get_chart_entries( 'top-tracks' ), 0, 4 ),
			'trending_artists'=> array_slice( self::get_chart_entries( 'top-artists' ), 0, 4 ),
		);
	}

	/**
	 * Fetch all chart view models.
	 *
	 * @return array
	 */
	public static function get_all_charts() {
		$rows   = AMC_DB::get_rows(
			'charts',
			array(
				'where'    => array( 'status' => 'active' ),
				'order_by' => 'display_order ASC, id ASC',
			)
		);
		$charts = array();

		foreach ( $rows as $row ) {
			$chart = self::hydrate_chart( $row );

			if ( $chart ) {
				$charts[ $chart['slug'] ] = $chart;
			}
		}

		return $charts;
	}

	/**
	 * Fetch single chart.
	 *
	 * @param string $slug Chart slug.
	 * @return array|null
	 */
	public static function get_chart( $slug ) {
		$row = AMC_DB::get_row_by_slug( 'charts', $slug );

		if ( ! $row || 'hidden' === $row['status'] ) {
			return null;
		}

		return self::hydrate_chart( $row );
	}

	/**
	 * Fetch chart entries from database.
	 *
	 * @param string $slug Chart slug.
	 * @return array
	 */
	public static function get_chart_entries( $slug ) {
		$chart = AMC_DB::get_row_by_slug( 'charts', $slug );

		if ( ! $chart ) {
			return array();
		}

		$week = AMC_DB::get_current_published_week( (int) $chart['id'] );

		if ( ! $week ) {
			return array();
		}

		$entries      = AMC_DB::get_chart_entries( (int) $week['id'] );
		$view_models  = array();

		foreach ( $entries as $entry ) {
			$entity = self::get_entity( $entry['entity_type'], (int) $entry['entity_id'] );

			if ( ! $entity ) {
				continue;
			}

			$view_models[] = array_merge(
				$entry,
				array(
					'entity'        => $entity,
					'entity_id'     => (int) $entry['entity_id'],
					'movement_icon' => self::movement_icon( $entry['movement'] ),
					'movement_label'=> ucfirst( $entry['movement'] ),
					'movement_delta'=> self::movement_delta( $entry ),
				)
			);
		}

		return $view_models;
	}

	/**
	 * Resolve entity by type and id.
	 *
	 * @param string $type Entity type.
	 * @param int    $id Post ID.
	 * @return array|null
	 */
	public static function get_entity( $type, $id ) {
		if ( 'artist' === $type ) {
			$row = AMC_DB::get_row( 'artists', $id );

			if ( ! $row || 'archived' === $row['status'] ) {
				return null;
			}

			return array(
				'id'          => (int) $row['id'],
				'type'        => 'artist',
				'name'        => $row['name'],
				'slug'        => $row['slug'],
				'description' => $row['bio'],
				'excerpt'     => $row['blurb'],
				'country'     => $row['country'],
				'genres'      => $row['genre'],
				'monthly'     => $row['monthly_listeners'],
				'streak'      => $row['chart_streak'],
				'gradient'    => $row['gradient'],
				'url'         => self::route_url( 'artist/' . $row['slug'] ),
			);
		}

		if ( 'track' === $type ) {
			$row = AMC_DB::get_row( 'tracks', $id );

			if ( ! $row || 'archived' === $row['status'] ) {
				return null;
			}

			return array(
				'id'          => (int) $row['id'],
				'type'        => 'track',
				'name'        => $row['title'],
				'slug'        => $row['slug'],
				'description' => $row['description'],
				'excerpt'     => $row['aliases'],
				'duration'    => $row['duration'],
				'gradient'    => $row['gradient'],
				'artist'      => ! empty( $row['artist_id'] ) ? self::get_entity( 'artist', (int) $row['artist_id'] ) : null,
				'album'       => ! empty( $row['album_id'] ) ? self::get_entity( 'album', (int) $row['album_id'] ) : null,
				'url'         => self::route_url( 'track/' . $row['slug'] ),
			);
		}

		if ( 'album' === $type ) {
			$row = AMC_DB::get_row( 'albums', $id );

			if ( ! $row || 'archived' === $row['status'] ) {
				return null;
			}

			return array(
				'id'          => (int) $row['id'],
				'type'        => 'album',
				'name'        => $row['title'],
				'slug'        => $row['slug'],
				'description' => $row['description'],
				'excerpt'     => $row['genre'],
				'year'        => $row['release_year'],
				'gradient'    => $row['gradient'],
				'artist'      => ! empty( $row['artist_id'] ) ? self::get_entity( 'artist', (int) $row['artist_id'] ) : null,
				'url'         => self::route_url( 'charts/top-albums/' ),
			);
		}

		return null;
	}

	/**
	 * Fetch track by slug.
	 *
	 * @param string $slug Track slug.
	 * @return array|null
	 */
	public static function get_track_by_slug( $slug ) {
		$row = AMC_DB::get_row_by_slug( 'tracks', $slug );

		return $row ? self::get_entity( 'track', (int) $row['id'] ) : null;
	}

	/**
	 * Fetch artist by slug.
	 *
	 * @param string $slug Artist slug.
	 * @return array|null
	 */
	public static function get_artist_by_slug( $slug ) {
		$row = AMC_DB::get_row_by_slug( 'artists', $slug );

		return $row ? self::get_entity( 'artist', (int) $row['id'] ) : null;
	}

	/**
	 * Related chart pages.
	 *
	 * @param string $exclude_slug Excluded chart slug.
	 * @return array
	 */
	public static function get_more_charts( $exclude_slug = '' ) {
		$charts = self::get_all_charts();

		if ( $exclude_slug && isset( $charts[ $exclude_slug ] ) ) {
			unset( $charts[ $exclude_slug ] );
		}

		return array_values( $charts );
	}

	/**
	 * Featured tracks for artist page.
	 *
	 * @param int $artist_id Artist ID.
	 * @return array
	 */
	public static function get_tracks_for_artist( $artist_id ) {
		$rows = AMC_DB::get_rows(
			'tracks',
			array(
				'where'    => array(
					'artist_id' => absint( $artist_id ),
					'status'    => 'active',
				),
				'order_by' => 'title ASC',
			)
		);

		return array_map(
			function ( $row ) {
				return self::get_entity( 'track', (int) $row['id'] );
			},
			$rows
		);
	}

	/**
	 * Get tracks related to a track.
	 *
	 * @param int $track_id Track ID.
	 * @param int $artist_id Artist ID.
	 * @param int $limit Number of items.
	 * @return array
	 */
	public static function get_related_tracks( $track_id, $artist_id = 0, $limit = 4 ) {
		$rows   = AMC_DB::get_rows(
			'tracks',
			array(
				'where'    => array( 'status' => 'active' ),
				'order_by' => 'title ASC',
			)
		);
		$posts  = array();

		foreach ( $rows as $row ) {
			if ( (int) $row['id'] === (int) $track_id ) {
				continue;
			}

			if ( $artist_id && (int) $row['artist_id'] !== (int) $artist_id ) {
				continue;
			}

			$posts[] = $row;
		}

		if ( empty( $posts ) ) {
			foreach ( $rows as $row ) {
				if ( (int) $row['id'] !== (int) $track_id ) {
					$posts[] = $row;
				}
			}
		}

		return array_slice(
			array_values(
				array_filter(
					array_map(
						function ( $post ) {
							return self::get_entity( 'track', (int) $post['id'] );
						},
						$posts
					)
				)
			),
			0,
			$limit
		);
	}

	/**
	 * Get artist peers from chart lists.
	 *
	 * @param int $artist_id Artist ID.
	 * @param int $limit Number of items.
	 * @return array
	 */
	public static function get_related_artists( $artist_id, $limit = 4 ) {
		$slugs   = array( 'top-artists', 'hot-100-artists' );
		$results = array();

		foreach ( $slugs as $slug ) {
			foreach ( self::get_chart_entries( $slug ) as $entry ) {
				if ( empty( $entry['entity']['id'] ) || (int) $entry['entity']['id'] === (int) $artist_id ) {
					continue;
				}

				$results[ $entry['entity']['id'] ] = $entry['entity'];

				if ( count( $results ) >= $limit ) {
					break 2;
				}
			}
		}

		return array_values( $results );
	}

	/**
	 * Get chart appearances for an artist.
	 *
	 * @param int $artist_id Artist ID.
	 * @return array
	 */
	public static function get_artist_chart_positions( $artist_id ) {
		return self::get_entity_chart_positions( 'artist', $artist_id );
	}

	/**
	 * Get chart appearances for a track.
	 *
	 * @param int $track_id Track ID.
	 * @return array
	 */
	public static function get_track_chart_positions( $track_id ) {
		return self::get_entity_chart_positions( 'track', $track_id );
	}

	/**
	 * Get related charts for an entity.
	 *
	 * @param string $entity_type Entity type.
	 * @param int    $entity_id Entity ID.
	 * @return array
	 */
	public static function get_entity_chart_positions( $entity_type, $entity_id ) {
		$positions = array();

		foreach ( self::get_all_charts() as $chart ) {
			foreach ( $chart['entries'] as $entry ) {
				if ( $entry['entity_type'] === $entity_type && (int) $entry['entity_id'] === (int) $entity_id ) {
					$positions[] = array(
						'chart' => $chart,
						'entry' => $entry,
					);
					break;
				}
			}
		}

		return $positions;
	}

	/**
	 * Get chart summary metrics.
	 *
	 * @param array $entries Chart entries.
	 * @return array
	 */
	public static function get_chart_summary( $entries ) {
		if ( empty( $entries ) ) {
			return array();
		}

		$total_weeks  = 0;
		$highest_jump = null;
		$steady_count = 0;

		foreach ( $entries as $entry ) {
			$total_weeks += (int) $entry['weeks_on_chart'];

			if ( 'same' === $entry['movement'] ) {
				++$steady_count;
			}

			if ( 'up' === $entry['movement'] ) {
				if ( null === $highest_jump || (int) $entry['movement_delta'] > (int) $highest_jump['movement_delta'] ) {
					$highest_jump = $entry;
				}
			}
		}

		return array(
			'entries'       => count( $entries ),
			'average_weeks' => count( $entries ) ? round( $total_weeks / count( $entries ) ) : 0,
			'steady_count'  => $steady_count,
			'top_mover'     => $highest_jump,
		);
	}

	/**
	 * Determine movement icon.
	 *
	 * @param string $movement Movement type.
	 * @return string
	 */
	public static function movement_icon( $movement ) {
		switch ( $movement ) {
			case 'up':
				return '↑';
			case 'down':
				return '↓';
			case 'new':
				return '●';
			default:
				return '→';
		}
	}

	/**
	 * Determine movement delta.
	 *
	 * @param array $entry Entry data.
	 * @return int
	 */
	public static function movement_delta( $entry ) {
		$current = isset( $entry['current_rank'] ) ? (int) $entry['current_rank'] : 0;
		$last    = isset( $entry['last_rank'] ) ? (int) $entry['last_rank'] : 0;

		if ( $current <= 0 || $last <= 0 ) {
			return 0;
		}

		return abs( $last - $current );
	}

	/**
	 * Hydrate chart row into frontend model.
	 *
	 * @param array $row Chart row.
	 * @return array
	 */
	private static function hydrate_chart( $row ) {
		$entries  = self::get_chart_entries( $row['slug'] );
		$featured = ! empty( $entries ) ? $entries[0] : null;

		return array(
			'id'          => (int) $row['id'],
			'slug'        => $row['slug'],
			'title'       => $row['name'],
			'kicker'      => $row['kicker'],
			'description' => $row['description'],
			'type'        => $row['type'],
			'accent'      => $row['accent'],
			'url'         => self::route_url( 'charts/' . $row['slug'] ),
			'entries'     => $entries,
			'featured'    => $featured,
			'summary'     => self::get_chart_summary( $entries ),
		);
	}
}
