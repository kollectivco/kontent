<?php
/**
 * Data access helpers and seeded defaults.
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
	 * Initial dataset for seeding.
	 *
	 * @return array
	 */
	public static function demo_payload() {
		$artists = array(
			'nancy-ajram' => array(
				'name'        => 'Nancy Ajram',
				'country'     => 'Lebanon',
				'genres'      => 'Regional Pop, Dance Pop',
				'description' => 'A chart-dominating pop icon with polished hooks, cross-generational reach, and a sharp visual identity.',
				'blurb'       => 'Nancy owns this week with a polished, high-gloss return powered by massive replay value.',
				'monthly'     => '38.4M',
				'streak'      => '14 weeks inside top 10',
				'gradient'    => 'sunset',
			),
			'amr-diab' => array(
				'name'        => 'Amr Diab',
				'country'     => 'Egypt',
				'genres'      => 'Mediterranean Pop, Regional Pop',
				'description' => 'Still setting the bar for mainstream longevity, Amr balances effortless cool with precision songwriting.',
				'blurb'       => 'A legacy giant with renewed heat thanks to playlist strength and live demand.',
				'monthly'     => '31.2M',
				'streak'      => '9 weeks inside top 10',
				'gradient'    => 'ocean',
			),
			'elissa' => array(
				'name'        => 'Elissa',
				'country'     => 'Lebanon',
				'genres'      => 'Regional Pop, Ballad',
				'description' => 'A commanding vocalist whose catalog keeps generating new traction around every emotional release.',
				'blurb'       => 'Big emotional songwriting and loyal fan momentum keep Elissa climbing.',
				'monthly'     => '24.8M',
				'streak'      => '7 weeks inside top 10',
				'gradient'    => 'ruby',
			),
			'marwan-pablo' => array(
				'name'        => 'Marwan Pablo',
				'country'     => 'Egypt',
				'genres'      => 'Trap, Alternative Rap',
				'description' => 'A defining voice in regional rap with a dense, high-style sound and instant cultural gravity.',
				'blurb'       => 'Pablo’s grip on the rap conversation remains intense, especially among younger listeners.',
				'monthly'     => '18.1M',
				'streak'      => '6 weeks inside top 10',
				'gradient'    => 'plum',
			),
			'assala' => array(
				'name'        => 'Assala',
				'country'     => 'Syria',
				'genres'      => 'Regional Pop, Tarab',
				'description' => 'A powerhouse performer whose catalog thrives on big vocals and emotionally direct arrangements.',
				'blurb'       => 'A vocal institution with broad family-audience appeal and a durable catalog.',
				'monthly'     => '19.6M',
				'streak'      => '5 weeks inside top 10',
				'gradient'    => 'gold',
			),
			'wegz' => array(
				'name'        => 'Wegz',
				'country'     => 'Egypt',
				'genres'      => 'Trap, Shaabi Fusion',
				'description' => 'A scene-shifting artist known for urgent flows, cinematic hooks, and festival-scale energy.',
				'blurb'       => 'Still one of the fastest sparks in the region whenever a new release lands.',
				'monthly'     => '22.4M',
				'streak'      => '10 weeks inside top 20',
				'gradient'    => 'emerald',
			),
			'tul8te' => array(
				'name'        => 'Tul8te',
				'country'     => 'Egypt',
				'genres'      => 'Alternative Pop, Indie',
				'description' => 'A moody, modern crossover act with standout hooks and a devoted digital-native following.',
				'blurb'       => 'A streaming-native breakout continuing to turn curiosity into repeat listening.',
				'monthly'     => '12.9M',
				'streak'      => '4 weeks inside top 20',
				'gradient'    => 'ice',
			),
			'balqees' => array(
				'name'        => 'Balqees',
				'country'     => 'Yemen / UAE',
				'genres'      => 'Khaleeji Pop, Regional Pop',
				'description' => 'Elegant pop craft, strong live presence, and a catalog built for glossy chart moments.',
				'blurb'       => 'Balqees blends premium visuals with strong radio-friendly execution.',
				'monthly'     => '11.6M',
				'streak'      => '3 weeks inside top 20',
				'gradient'    => 'rose',
			),
		);

		$albums = array(
			'noor-nights' => array(
				'title'       => 'Noor Nights',
				'artist_slug' => 'nancy-ajram',
				'year'        => '2026',
				'description' => 'A luminous pop release built around midnight dance grooves and high-drama choruses.',
				'gradient'    => 'sunset',
			),
			'seaside-radio' => array(
				'title'       => 'Seaside Radio',
				'artist_slug' => 'amr-diab',
				'year'        => '2025',
				'description' => 'Mediterranean textures, clean melodies, and a warm summer pulse.',
				'gradient'    => 'ocean',
			),
			'letters-in-neon' => array(
				'title'       => 'Letters In Neon',
				'artist_slug' => 'elissa',
				'year'        => '2026',
				'description' => 'A big-hearted pop set balancing club polish with intimate ballads.',
				'gradient'    => 'ruby',
			),
			'parallel-lines' => array(
				'title'       => 'Parallel Lines',
				'artist_slug' => 'marwan-pablo',
				'year'        => '2026',
				'description' => 'A sharp, moody rap project stitched together with industrial textures and memorable refrains.',
				'gradient'    => 'plum',
			),
			'golden-room' => array(
				'title'       => 'Golden Room',
				'artist_slug' => 'assala',
				'year'        => '2025',
				'description' => 'Rich orchestration and commanding vocals in a classic-meets-modern package.',
				'gradient'    => 'gold',
			),
			'northern-lights' => array(
				'title'       => 'Northern Lights',
				'artist_slug' => 'tul8te',
				'year'        => '2026',
				'description' => 'A hazy, melodic release with intimate writing and atmospheric production.',
				'gradient'    => 'ice',
			),
		);

		$tracks = array(
			'shabab-el-layl' => array(
				'title'       => 'Shabab El Layl',
				'artist_slug' => 'nancy-ajram',
				'album_slug'  => 'noor-nights',
				'description' => 'A glossy after-hours anthem powered by tight percussion, sweeping synths, and a huge singalong chorus.',
				'duration'    => '3:24',
				'gradient'    => 'sunset',
			),
			'baheb-el-bahr' => array(
				'title'       => 'Baheb El Bahr',
				'artist_slug' => 'amr-diab',
				'album_slug'  => 'seaside-radio',
				'description' => 'Warm guitar lines and easy confidence make this one of the week’s most replayed songs.',
				'duration'    => '3:11',
				'gradient'    => 'ocean',
			),
			'akhbarak-eh' => array(
				'title'       => 'Akhbarak Eh',
				'artist_slug' => 'elissa',
				'album_slug'  => 'letters-in-neon',
				'description' => 'A polished pop ballad built on emotional clarity and a wide-open chorus.',
				'duration'    => '3:48',
				'gradient'    => 'ruby',
			),
			'ghorba' => array(
				'title'       => 'Ghorba',
				'artist_slug' => 'marwan-pablo',
				'album_slug'  => 'parallel-lines',
				'description' => 'Brooding production and clipped flows give this record a deep midnight pull.',
				'duration'    => '2:58',
				'gradient'    => 'plum',
			),
			'fouq-el-sama' => array(
				'title'       => 'Fouq El Sama',
				'artist_slug' => 'assala',
				'album_slug'  => 'golden-room',
				'description' => 'A dramatic vocal showcase with sweeping strings and stadium-sized emotion.',
				'duration'    => '4:02',
				'gradient'    => 'gold',
			),
			'dorak-gai' => array(
				'title'       => 'Dorak Gai',
				'artist_slug' => 'wegz',
				'album_slug'  => '',
				'description' => 'Restless energy, clipped drums, and instantly quotable hooks keep this one surging.',
				'duration'    => '2:49',
				'gradient'    => 'emerald',
			),
			'kol-youm' => array(
				'title'       => 'Kol Youm',
				'artist_slug' => 'tul8te',
				'album_slug'  => 'northern-lights',
				'description' => 'Soft-focus synths and confessional writing make this an intimate streaming favorite.',
				'duration'    => '3:27',
				'gradient'    => 'ice',
			),
			'maa-elsowar' => array(
				'title'       => 'Maa Elsowar',
				'artist_slug' => 'balqees',
				'album_slug'  => '',
				'description' => 'A bright Khaleeji-pop cut with glossy percussion and an instant hook.',
				'duration'    => '3:16',
				'gradient'    => 'rose',
			),
		);

		$charts = array(
			'top-artists' => array(
				'entries' => array(
					array( 'entity_type' => 'artist', 'slug' => 'nancy-ajram', 'current_rank' => 1, 'last_rank' => 2, 'peak_rank' => 1, 'weeks_on_chart' => 16, 'movement' => 'up' ),
					array( 'entity_type' => 'artist', 'slug' => 'amr-diab', 'current_rank' => 2, 'last_rank' => 1, 'peak_rank' => 1, 'weeks_on_chart' => 18, 'movement' => 'down' ),
					array( 'entity_type' => 'artist', 'slug' => 'elissa', 'current_rank' => 3, 'last_rank' => 4, 'peak_rank' => 2, 'weeks_on_chart' => 12, 'movement' => 'up' ),
					array( 'entity_type' => 'artist', 'slug' => 'marwan-pablo', 'current_rank' => 4, 'last_rank' => 3, 'peak_rank' => 3, 'weeks_on_chart' => 10, 'movement' => 'down' ),
					array( 'entity_type' => 'artist', 'slug' => 'wegz', 'current_rank' => 5, 'last_rank' => 5, 'peak_rank' => 2, 'weeks_on_chart' => 14, 'movement' => 'same' ),
					array( 'entity_type' => 'artist', 'slug' => 'assala', 'current_rank' => 6, 'last_rank' => 8, 'peak_rank' => 4, 'weeks_on_chart' => 11, 'movement' => 'up' ),
				),
			),
			'top-tracks' => array(
				'entries' => array(
					array( 'entity_type' => 'track', 'slug' => 'shabab-el-layl', 'current_rank' => 1, 'last_rank' => 1, 'peak_rank' => 1, 'weeks_on_chart' => 12, 'movement' => 'same' ),
					array( 'entity_type' => 'track', 'slug' => 'baheb-el-bahr', 'current_rank' => 2, 'last_rank' => 3, 'peak_rank' => 2, 'weeks_on_chart' => 9, 'movement' => 'up' ),
					array( 'entity_type' => 'track', 'slug' => 'ghorba', 'current_rank' => 3, 'last_rank' => 6, 'peak_rank' => 3, 'weeks_on_chart' => 7, 'movement' => 'up' ),
					array( 'entity_type' => 'track', 'slug' => 'akhbarak-eh', 'current_rank' => 4, 'last_rank' => 2, 'peak_rank' => 2, 'weeks_on_chart' => 10, 'movement' => 'down' ),
					array( 'entity_type' => 'track', 'slug' => 'dorak-gai', 'current_rank' => 5, 'last_rank' => 4, 'peak_rank' => 4, 'weeks_on_chart' => 8, 'movement' => 'down' ),
					array( 'entity_type' => 'track', 'slug' => 'kol-youm', 'current_rank' => 6, 'last_rank' => 10, 'peak_rank' => 6, 'weeks_on_chart' => 4, 'movement' => 'up' ),
				),
			),
			'top-albums' => array(
				'entries' => array(
					array( 'entity_type' => 'album', 'slug' => 'noor-nights', 'current_rank' => 1, 'last_rank' => 2, 'peak_rank' => 1, 'weeks_on_chart' => 11, 'movement' => 'up' ),
					array( 'entity_type' => 'album', 'slug' => 'parallel-lines', 'current_rank' => 2, 'last_rank' => 1, 'peak_rank' => 1, 'weeks_on_chart' => 8, 'movement' => 'down' ),
					array( 'entity_type' => 'album', 'slug' => 'letters-in-neon', 'current_rank' => 3, 'last_rank' => 4, 'peak_rank' => 3, 'weeks_on_chart' => 9, 'movement' => 'up' ),
					array( 'entity_type' => 'album', 'slug' => 'seaside-radio', 'current_rank' => 4, 'last_rank' => 3, 'peak_rank' => 2, 'weeks_on_chart' => 13, 'movement' => 'down' ),
					array( 'entity_type' => 'album', 'slug' => 'golden-room', 'current_rank' => 5, 'last_rank' => 5, 'peak_rank' => 4, 'weeks_on_chart' => 6, 'movement' => 'same' ),
					array( 'entity_type' => 'album', 'slug' => 'northern-lights', 'current_rank' => 6, 'last_rank' => 9, 'peak_rank' => 6, 'weeks_on_chart' => 3, 'movement' => 'up' ),
				),
			),
			'hot-100-tracks' => array(
				'entries' => array(
					array( 'entity_type' => 'track', 'slug' => 'shabab-el-layl', 'current_rank' => 1, 'last_rank' => 2, 'peak_rank' => 1, 'weeks_on_chart' => 15, 'movement' => 'up' ),
					array( 'entity_type' => 'track', 'slug' => 'dorak-gai', 'current_rank' => 2, 'last_rank' => 1, 'peak_rank' => 1, 'weeks_on_chart' => 16, 'movement' => 'down' ),
					array( 'entity_type' => 'track', 'slug' => 'baheb-el-bahr', 'current_rank' => 3, 'last_rank' => 5, 'peak_rank' => 3, 'weeks_on_chart' => 10, 'movement' => 'up' ),
					array( 'entity_type' => 'track', 'slug' => 'maa-elsowar', 'current_rank' => 4, 'last_rank' => 8, 'peak_rank' => 4, 'weeks_on_chart' => 5, 'movement' => 'up' ),
					array( 'entity_type' => 'track', 'slug' => 'ghorba', 'current_rank' => 5, 'last_rank' => 4, 'peak_rank' => 4, 'weeks_on_chart' => 9, 'movement' => 'down' ),
					array( 'entity_type' => 'track', 'slug' => 'akhbarak-eh', 'current_rank' => 6, 'last_rank' => 3, 'peak_rank' => 2, 'weeks_on_chart' => 13, 'movement' => 'down' ),
				),
			),
			'hot-100-artists' => array(
				'entries' => array(
					array( 'entity_type' => 'artist', 'slug' => 'nancy-ajram', 'current_rank' => 1, 'last_rank' => 1, 'peak_rank' => 1, 'weeks_on_chart' => 18, 'movement' => 'same' ),
					array( 'entity_type' => 'artist', 'slug' => 'wegz', 'current_rank' => 2, 'last_rank' => 4, 'peak_rank' => 2, 'weeks_on_chart' => 13, 'movement' => 'up' ),
					array( 'entity_type' => 'artist', 'slug' => 'amr-diab', 'current_rank' => 3, 'last_rank' => 2, 'peak_rank' => 1, 'weeks_on_chart' => 17, 'movement' => 'down' ),
					array( 'entity_type' => 'artist', 'slug' => 'marwan-pablo', 'current_rank' => 4, 'last_rank' => 3, 'peak_rank' => 3, 'weeks_on_chart' => 12, 'movement' => 'down' ),
					array( 'entity_type' => 'artist', 'slug' => 'assala', 'current_rank' => 5, 'last_rank' => 7, 'peak_rank' => 5, 'weeks_on_chart' => 8, 'movement' => 'up' ),
					array( 'entity_type' => 'artist', 'slug' => 'balqees', 'current_rank' => 6, 'last_rank' => 9, 'peak_rank' => 6, 'weeks_on_chart' => 4, 'movement' => 'up' ),
				),
			),
		);

		return compact( 'artists', 'albums', 'tracks', 'charts' );
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
	 * Home collections.
	 *
	 * @return array
	 */
	public static function get_home_context() {
		$charts = self::get_all_charts();

		return array(
			'hero_chart'      => isset( $charts['hot-100-tracks'] ) ? $charts['hot-100-tracks'] : reset( $charts ),
			'featured_charts' => array_slice( $charts, 0, 3 ),
			'more_charts'     => array_slice( $charts, 3, null, true ),
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
		$definitions = self::chart_definitions();
		$charts      = array();

		foreach ( $definitions as $slug => $definition ) {
			$charts[ $slug ] = self::get_chart( $slug );
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
		$definitions = self::chart_definitions();

		if ( empty( $definitions[ $slug ] ) ) {
			return null;
		}

		$definition = $definitions[ $slug ];
		$entries    = self::get_chart_entries( $slug );
		$featured   = ! empty( $entries ) ? $entries[0] : null;

		return array(
			'slug'        => $slug,
			'title'       => $definition['title'],
			'kicker'      => $definition['kicker'],
			'description' => $definition['description'],
			'type'        => $definition['type'],
			'accent'      => $definition['accent'],
			'url'         => self::route_url( 'charts/' . $slug ),
			'entries'     => $entries,
			'featured'    => $featured,
			'summary'     => self::get_chart_summary( $entries ),
		);
	}

	/**
	 * Fetch chart entries from database.
	 *
	 * @param string $slug Chart slug.
	 * @return array
	 */
	public static function get_chart_entries( $slug ) {
		$chart_post = get_page_by_path( $slug, OBJECT, 'amc_chart' );

		if ( ! $chart_post ) {
			return array();
		}

		$entries = get_post_meta( $chart_post->ID, '_amc_chart_entries', true );

		if ( ! is_array( $entries ) ) {
			return array();
		}

		$view_models = array();

		foreach ( $entries as $entry ) {
			$entity = self::get_entity( $entry['entity_type'], $entry['entity_id'] );

			if ( ! $entity ) {
				continue;
			}

			$view_models[] = array_merge(
				$entry,
				array(
					'entity'        => $entity,
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
		$post = get_post( $id );

		if ( ! $post ) {
			return null;
		}

		$meta = get_post_meta( $id );

		if ( 'artist' === $type ) {
			return array(
				'id'          => $id,
				'type'        => 'artist',
				'name'        => $post->post_title,
				'slug'        => $post->post_name,
				'description' => $post->post_content,
				'excerpt'     => $post->post_excerpt,
				'country'     => self::meta_value( $meta, '_amc_country' ),
				'genres'      => self::meta_value( $meta, '_amc_genres' ),
				'monthly'     => self::meta_value( $meta, '_amc_monthly_listeners' ),
				'streak'      => self::meta_value( $meta, '_amc_chart_streak' ),
				'gradient'    => self::meta_value( $meta, '_amc_gradient' ),
				'url'         => self::route_url( 'artist/' . $post->post_name ),
			);
		}

		if ( 'track' === $type ) {
			$artist_id = (int) self::meta_value( $meta, '_amc_artist_id' );
			$album_id  = (int) self::meta_value( $meta, '_amc_album_id' );

			return array(
				'id'          => $id,
				'type'        => 'track',
				'name'        => $post->post_title,
				'slug'        => $post->post_name,
				'description' => $post->post_content,
				'excerpt'     => $post->post_excerpt,
				'duration'    => self::meta_value( $meta, '_amc_duration' ),
				'gradient'    => self::meta_value( $meta, '_amc_gradient' ),
				'artist'      => $artist_id ? self::get_entity( 'artist', $artist_id ) : null,
				'album'       => $album_id ? self::get_entity( 'album', $album_id ) : null,
				'url'         => self::route_url( 'track/' . $post->post_name ),
			);
		}

		if ( 'album' === $type ) {
			$artist_id = (int) self::meta_value( $meta, '_amc_artist_id' );

			return array(
				'id'          => $id,
				'type'        => 'album',
				'name'        => $post->post_title,
				'slug'        => $post->post_name,
				'description' => $post->post_content,
				'excerpt'     => $post->post_excerpt,
				'year'        => self::meta_value( $meta, '_amc_release_year' ),
				'gradient'    => self::meta_value( $meta, '_amc_gradient' ),
				'artist'      => $artist_id ? self::get_entity( 'artist', $artist_id ) : null,
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
		$post = get_page_by_path( $slug, OBJECT, 'amc_track' );

		return $post ? self::get_entity( 'track', $post->ID ) : null;
	}

	/**
	 * Fetch artist by slug.
	 *
	 * @param string $slug Artist slug.
	 * @return array|null
	 */
	public static function get_artist_by_slug( $slug ) {
		$post = get_page_by_path( $slug, OBJECT, 'amc_artist' );

		return $post ? self::get_entity( 'artist', $post->ID ) : null;
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
		$posts = get_posts(
			array(
				'post_type'      => 'amc_track',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
				'meta_query'     => array(
					array(
						'key'   => '_amc_artist_id',
						'value' => $artist_id,
					),
				),
			)
		);

		return array_map(
			function ( $post ) {
				return self::get_entity( 'track', $post->ID );
			},
			$posts
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
		$posts = get_posts(
			array(
				'post_type'      => 'amc_track',
				'post_status'    => 'publish',
				'posts_per_page' => $limit + 2,
				'post__not_in'   => array( $track_id ),
				'meta_query'     => $artist_id ? array(
					array(
						'key'   => '_amc_artist_id',
						'value' => $artist_id,
					),
				) : array(),
			)
		);

		if ( empty( $posts ) ) {
			$posts = get_posts(
				array(
					'post_type'      => 'amc_track',
					'post_status'    => 'publish',
					'posts_per_page' => $limit,
					'post__not_in'   => array( $track_id ),
				)
			);
		}

		return array_slice(
			array_values(
				array_filter(
					array_map(
						function ( $post ) {
							return self::get_entity( 'track', $post->ID );
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
	 * Safe meta value.
	 *
	 * @param array  $meta Meta array.
	 * @param string $key Meta key.
	 * @return string
	 */
	private static function meta_value( $meta, $key ) {
		return isset( $meta[ $key ][0] ) ? (string) $meta[ $key ][0] : '';
	}
}
