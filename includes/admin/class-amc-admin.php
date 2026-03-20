<?php
/**
 * Phase 2 admin UI.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AMC_Admin {
	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public static function boot() {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Register admin menu pages.
	 *
	 * @return void
	 */
	public static function register_menu() {
		$pages = AMC_Admin_Data::pages();

		add_menu_page(
			'Kontentainment Charts',
			'Kontentainment Charts',
			'manage_options',
			$pages['dashboard']['menu_slug'],
			array( __CLASS__, 'render_page' ),
			'dashicons-chart-area',
			58
		);

		foreach ( $pages as $key => $page ) {
			add_submenu_page(
				$pages['dashboard']['menu_slug'],
				'Kontentainment Charts - ' . $page['title'],
				$page['title'],
				'manage_options',
				$page['menu_slug'],
				array( __CLASS__, 'render_page' )
			);
		}
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook_suffix Screen hook.
	 * @return void
	 */
	public static function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, 'kontentainment-charts' ) ) {
			return;
		}

		wp_enqueue_style(
			'amc-admin',
			AMC_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			AMC_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'amc-admin',
			AMC_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			AMC_PLUGIN_VERSION,
			true
		);
	}

	/**
	 * Render admin page.
	 *
	 * @return void
	 */
	public static function render_page() {
		$pages       = AMC_Admin_Data::pages();
		$current_key = self::get_current_page_key();
		$current     = $pages[ $current_key ];
		?>
		<div class="wrap amc-admin-wrap">
			<div class="amc-admin-shell">
				<header class="amc-admin-topbar">
					<div>
						<p class="amc-admin-kicker">Kontentainment Charts</p>
						<h1><?php echo esc_html( $current['title'] ); ?></h1>
						<p class="amc-admin-subcopy">Phase 2 introduces a plugin-owned control center UI for chart operations, publishing workflow, methodology planning, and archive visibility. This pass is UI-only by design.</p>
					</div>
					<div class="amc-admin-topbar__actions">
						<button type="button" class="button button-secondary">Preview Current Week</button>
						<button type="button" class="button button-primary">New Chart Week</button>
					</div>
				</header>

				<nav class="amc-admin-tabs" aria-label="Kontentainment Charts Sections">
					<?php foreach ( $pages as $key => $page ) : ?>
						<a class="<?php echo $key === $current_key ? 'is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $page['menu_slug'] ) ); ?>">
							<?php echo esc_html( $page['title'] ); ?>
						</a>
					<?php endforeach; ?>
				</nav>

				<div class="amc-admin-content">
					<?php self::render_view( $current_key ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Current page key.
	 *
	 * @return string
	 */
	private static function get_current_page_key() {
		$page  = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'kontentainment-charts';
		$pages = AMC_Admin_Data::pages();

		foreach ( $pages as $key => $config ) {
			if ( $config['menu_slug'] === $page ) {
				return $key;
			}
		}

		return 'dashboard';
	}

	/**
	 * Render section-specific view.
	 *
	 * @param string $view View key.
	 * @return void
	 */
	private static function render_view( $view ) {
		switch ( $view ) {
			case 'dashboard':
				self::render_dashboard();
				break;
			case 'charts':
				self::render_charts();
				break;
			case 'weekly-entries':
				self::render_weekly_entries();
				break;
			case 'tracks':
				self::render_tracks();
				break;
			case 'artists':
				self::render_artists();
				break;
			case 'albums':
				self::render_albums();
				break;
			case 'uploads':
				self::render_uploads();
				break;
			case 'cleaning':
				self::render_cleaning();
				break;
			case 'scoring':
				self::render_scoring();
				break;
			case 'publishing':
				self::render_publishing();
				break;
			case 'archives':
				self::render_archives();
				break;
			case 'users':
				self::render_users();
				break;
			case 'settings':
				self::render_settings();
				break;
		}
	}

	/**
	 * Dashboard view.
	 *
	 * @return void
	 */
	private static function render_dashboard() {
		$overview = AMC_Admin_Data::overview_cards();
		$uploads  = AMC_Admin_Data::recent_uploads();
		$alerts   = AMC_Admin_Data::alerts();
		$weeks    = AMC_Admin_Data::chart_week_status();

		echo '<section class="amc-admin-grid amc-admin-grid--cards">';
		foreach ( $overview as $card ) {
			printf(
				'<article class="amc-admin-card amc-admin-card--%1$s"><span>%2$s</span><strong>%3$s</strong><p>%4$s</p></article>',
				esc_attr( $card['tone'] ),
				esc_html( $card['label'] ),
				esc_html( $card['value'] ),
				esc_html( $card['delta'] )
			);
		}
		echo '</section>';

		echo '<section class="amc-admin-grid amc-admin-grid--split">';
		self::render_panel_start( 'Recent uploads', 'Latest source sheets moving through the chart pipeline.' );
		self::render_table(
			array( 'Source', 'Chart week', 'Status', 'Rows', 'Uploader' ),
			array_map(
				function ( $row ) {
					return array( $row['source'], $row['chart_week'], $row['status'], (string) $row['rows'], $row['uploader'] );
				},
				$uploads
			)
		);
		self::render_panel_end();

		self::render_panel_start( 'Published vs draft chart weeks', 'A seeded weekly publishing overview for the admin dashboard UI.' );
		echo '<div class="amc-admin-stat-stack">';
		foreach ( $weeks as $week ) {
			printf( '<div><strong>%1$s</strong><span>%2$s</span></div>', esc_html( (string) $week['value'] ), esc_html( $week['label'] ) );
		}
		echo '</div>';
		self::render_panel_end();
		echo '</section>';

		self::render_panel_start( 'Alerts', 'Duplicates, missing artwork, and unresolved data issues highlighted for the next chart cycle.' );
		echo '<div class="amc-admin-alerts">';
		foreach ( $alerts as $alert ) {
			printf(
				'<article class="amc-admin-alert amc-admin-alert--%1$s"><strong>%2$s</strong><p>%3$s</p></article>',
				esc_attr( $alert['tone'] ),
				esc_html( $alert['title'] ),
				esc_html( $alert['body'] )
			);
		}
		echo '</div>';
		self::render_panel_end();
	}

	/**
	 * Charts management view.
	 *
	 * @return void
	 */
	private static function render_charts() {
		$charts = AMC_Admin_Data::chart_categories();

		echo '<section class="amc-admin-grid amc-admin-grid--split">';
		self::render_panel_start( 'Create chart category', 'UI-only controls for dynamic chart creation and future expansion.' );
		self::render_form(
			array(
				'Chart name' => 'Top New Voices',
				'Slug' => 'top-new-voices',
				'Description' => 'Emerging artist momentum tracker.',
				'Type' => 'Artists',
				'Cover image' => 'Upload field placeholder',
				'Display order' => '6',
				'Active / Hidden' => 'Hidden',
				'Featured on homepage' => 'No',
				'Archive enabled' => 'No',
			)
		);
		self::render_button_row( array( 'Save draft category', 'Create chart', 'Reset form' ) );
		self::render_panel_end();

		self::render_panel_start( 'Existing chart categories', 'Categories remain dynamic and are not hardcoded to a fixed set.' );
		self::render_table(
			array( 'Chart name', 'Slug', 'Type', 'Order', 'Status', 'Featured', 'Archive' ),
			array_map(
				function ( $row ) {
					return array( $row['name'], $row['slug'], $row['type'], (string) $row['display_order'], $row['active'], $row['featured'], $row['archive'] );
				},
				$charts
			)
		);
		self::render_panel_end();
		echo '</section>';
	}

	/**
	 * Weekly entries view.
	 *
	 * @return void
	 */
	private static function render_weekly_entries() {
		$entries = AMC_Admin_Data::weekly_entries();

		echo '<section class="amc-admin-grid amc-admin-grid--split">';
		self::render_panel_start( 'Chart week controls', 'Create new chart weeks, switch status, and archive previous snapshots.' );
		self::render_form(
			array(
				'Chart category' => 'Hot 100 Tracks',
				'Week / date' => '2026-03-20',
				'Status' => 'Draft',
				'Feature on homepage' => 'Yes',
				'Archive previous week' => 'After publish',
			)
		);
		self::render_button_row( array( 'Save as draft', 'Publish week', 'Archive older week' ) );
		self::render_panel_end();

		self::render_panel_start( 'Weekly ranking entries', 'Manual editing UI for current rank, previous rank, peak rank, movement, score, and linked artwork.' );
		self::render_table(
			array( 'Rank', 'Linked item', 'Artist', 'Previous', 'Peak', 'Weeks', 'Move', 'Score', 'Status' ),
			array_map(
				function ( $row ) {
					return array( (string) $row['rank'], $row['item'], $row['linked'], (string) $row['previous'], (string) $row['peak'], (string) $row['weeks'], $row['movement'], $row['score'], $row['status'] );
				},
				$entries
			)
		);
		self::render_panel_end();
		echo '</section>';
	}

	/**
	 * Tracks view.
	 *
	 * @return void
	 */
	private static function render_tracks() {
		$tracks = AMC_Admin_Data::tracks();

		echo '<section class="amc-admin-grid amc-admin-grid--split">';
		self::render_panel_start( 'Track editor', 'Manage title, slug, artist, album, ISRC, aliases, release date, genre, and visibility state.' );
		self::render_form(
			array(
				'Track title' => 'Shabab El Layl',
				'Slug' => 'shabab-el-layl',
				'Cover art' => 'Artwork picker placeholder',
				'Artist' => 'Nancy Ajram',
				'Album' => 'Noor Nights',
				'ISRC' => 'EG-KTN-26-00001',
				'Aliases' => 'Shabab El Leil',
				'Release date' => '2026-02-14',
				'Genre' => 'Regional Pop',
				'Active / Hidden' => 'Active',
			)
		);
		self::render_panel_end();

		self::render_panel_start( 'Track library', 'Current seeded view of editable track records.' );
		self::render_table(
			array( 'Title', 'Artist', 'Album', 'ISRC', 'Aliases', 'Release date', 'Genre', 'Status' ),
			array_map(
				function ( $row ) {
					return array( $row['title'], $row['artist'], $row['album'], $row['isrc'], $row['aliases'], $row['release_date'], $row['genre'], $row['status'] );
				},
				$tracks
			)
		);
		self::render_panel_end();
		echo '</section>';
	}

	/**
	 * Artists view.
	 *
	 * @return void
	 */
	private static function render_artists() {
		$artists = AMC_Admin_Data::artists();

		echo '<section class="amc-admin-grid amc-admin-grid--split">';
		self::render_panel_start( 'Artist editor', 'Profile fields for bio, socials, country, genre, related tracks, albums, and visibility.' );
		self::render_form(
			array(
				'Artist name' => 'Nancy Ajram',
				'Slug' => 'nancy-ajram',
				'Image' => 'Profile image placeholder',
				'Bio' => 'Chart-dominating pop icon with polished hooks.',
				'Country' => 'Lebanon',
				'Genre' => 'Regional Pop, Dance Pop',
				'Social links' => 'Instagram, YouTube, TikTok',
				'Related tracks' => '6 linked',
				'Related albums' => '2 linked',
				'Active / Hidden' => 'Active',
			)
		);
		self::render_panel_end();

		self::render_panel_start( 'Artist library', 'Seeded artist records and metadata overview.' );
		self::render_table(
			array( 'Name', 'Country', 'Genre', 'Socials', 'Tracks', 'Albums', 'Status' ),
			array_map(
				function ( $row ) {
					return array( $row['name'], $row['country'], $row['genre'], $row['socials'], (string) $row['related_tracks'], (string) $row['related_albums'], $row['status'] );
				},
				$artists
			)
		);
		self::render_panel_end();
		echo '</section>';
	}

	/**
	 * Albums view.
	 *
	 * @return void
	 */
	private static function render_albums() {
		$albums = AMC_Admin_Data::albums();

		echo '<section class="amc-admin-grid amc-admin-grid--split">';
		self::render_panel_start( 'Album editor', 'Manage album title, artist, track list, genre, label, cover art, and release state.' );
		self::render_form(
			array(
				'Album title' => 'Noor Nights',
				'Slug' => 'noor-nights',
				'Artist' => 'Nancy Ajram',
				'Cover art' => 'Artwork picker placeholder',
				'Release date' => '2026-02-01',
				'Track list' => '13 tracks seeded',
				'Genre' => 'Pop',
				'Label' => 'Kontentainment Music',
				'Active / Hidden' => 'Active',
			)
		);
		self::render_panel_end();

		self::render_panel_start( 'Album library', 'Seeded album records with release context and visibility.' );
		self::render_table(
			array( 'Title', 'Artist', 'Release date', 'Tracks', 'Genre', 'Label', 'Status' ),
			array_map(
				function ( $row ) {
					return array( $row['title'], $row['artist'], $row['release_date'], (string) $row['tracks'], $row['genre'], $row['label'], $row['status'] );
				},
				$albums
			)
		);
		self::render_panel_end();
		echo '</section>';
	}

	/**
	 * Uploads view.
	 *
	 * @return void
	 */
	private static function render_uploads() {
		$uploads = AMC_Admin_Data::uploads();

		echo '<section class="amc-admin-grid amc-admin-grid--split">';
		self::render_panel_start( 'Source upload intake', 'UI-only placeholder for file ingestion across Spotify, YouTube Music, Apple Music, Anghami, TikTok, and Shazam.' );
		self::render_form(
			array(
				'Source name' => 'Spotify',
				'Upload file' => 'CSV / XLSX field placeholder',
				'Chart week' => '2026-03-20',
				'File status' => 'Pending',
				'Row count' => 'Auto-detect later',
				'Preview' => 'Generated after parse',
				'Uploader' => wp_get_current_user()->display_name ? wp_get_current_user()->display_name : 'Current user',
			)
		);
		self::render_button_row( array( 'Upload source sheet', 'Generate preview', 'Discard batch' ) );
		self::render_panel_end();

		self::render_panel_start( 'Recent source uploads', 'Operational view of incoming chart source sheets.' );
		self::render_table(
			array( 'Source', 'Upload date', 'Chart week', 'Status', 'Rows', 'Preview', 'Uploader' ),
			array_map(
				function ( $row ) {
					return array( $row['source'], $row['upload_date'], $row['week'], $row['status'], (string) $row['row_count'], $row['preview'], $row['uploader'] );
				},
				$uploads
			)
		);
		self::render_panel_end();
		echo '</section>';
	}

	/**
	 * Matching and cleaning view.
	 *
	 * @return void
	 */
	private static function render_cleaning() {
		$candidates = AMC_Admin_Data::matching_candidates();

		echo '<section class="amc-admin-grid amc-admin-grid--split">';
		self::render_panel_start( 'Duplicate and similarity queue', 'Approve or reject matches, then apply manual merges and overrides in later phases.' );
		self::render_table(
			array( 'Candidate', 'Type', 'Confidence', 'Sources', 'Status' ),
			array_map(
				function ( $row ) {
					return array( $row['candidate'], $row['type'], $row['confidence'], $row['sources'], $row['status'] );
				},
				$candidates
			)
		);
		self::render_button_row( array( 'Approve selected', 'Reject selected', 'Open manual merge tool' ) );
		self::render_panel_end();

		self::render_panel_start( 'Manual override tools', 'Placeholder controls for merge, split, and source-priority overrides.' );
		self::render_form(
			array(
				'Primary record' => 'Select track or artist',
				'Secondary record' => 'Select duplicate candidate',
				'Override reason' => 'Regional title variant / metadata mismatch',
				'Resulting slug' => 'auto-generated later',
			)
		);
		self::render_panel_end();
		echo '</section>';
	}

	/**
	 * Scoring view.
	 *
	 * @return void
	 */
	private static function render_scoring() {
		$data = AMC_Admin_Data::scoring();

		echo '<section class="amc-admin-grid amc-admin-grid--split">';
		self::render_panel_start( 'Source weights', 'Configure chart source weighting before future scoring automation is wired in.' );
		self::render_table(
			array( 'Source', 'Weight' ),
			array_map(
				function ( $row ) {
					return array( $row['source'], $row['weight'] );
				},
				$data['weights']
			)
		);
		self::render_button_row( array( 'Save methodology draft', 'Reset weights', 'Duplicate ruleset' ) );
		self::render_panel_end();

		self::render_panel_start( 'Methodology rules', 'Eligibility conditions and override policy placeholders.' );
		echo '<div class="amc-admin-definition-list">';
		foreach ( $data['methodology'] as $label => $value ) {
			printf( '<div><strong>%1$s</strong><span>%2$s</span></div>', esc_html( $label ), esc_html( $value ) );
		}
		echo '</div>';
		self::render_panel_end();
		echo '</section>';
	}

	/**
	 * Publishing view.
	 *
	 * @return void
	 */
	private static function render_publishing() {
		$data = AMC_Admin_Data::publishing_preview();

		echo '<section class="amc-admin-grid amc-admin-grid--split">';
		self::render_panel_start( 'Publishing preview', 'Preview generated chart weeks, compare against previous periods, and control publication state.' );
		printf( '<div class="amc-admin-publish-week"><strong>%s</strong><span>Preview state only</span></div>', esc_html( $data['current_week'] ) );
		echo '<div class="amc-admin-stat-stack">';
		foreach ( $data['comparison'] as $item ) {
			printf( '<div><strong>%1$s</strong><span>%2$s</span></div>', esc_html( $item['value'] ), esc_html( $item['metric'] ) );
		}
		echo '</div>';
		self::render_panel_end();

		self::render_panel_start( 'Publishing actions', 'UI placeholders for publish, unpublish, compare, and feature states.' );
		self::render_button_row( $data['actions'] );
		self::render_panel_end();
		echo '</section>';
	}

	/**
	 * Archive management view.
	 *
	 * @return void
	 */
	private static function render_archives() {
		$archives = AMC_Admin_Data::archives();

		self::render_panel_start( 'Archive management', 'Past chart weeks can be reopened, restored, or re-frozen from this control panel UI.' );
		self::render_table(
			array( 'Week', 'Charts', 'Status', 'Notes', 'Actions' ),
			array_map(
				function ( $row ) {
					return array( $row['week'], (string) $row['charts'], $row['status'], $row['notes'], $row['actions'] );
				},
				$archives
			)
		);
		self::render_panel_end();
	}

	/**
	 * Users and roles view.
	 *
	 * @return void
	 */
	private static function render_users() {
		$roles = AMC_Admin_Data::users();

		echo '<section class="amc-admin-grid amc-admin-grid--split">';
		self::render_panel_start( 'Roles overview', 'Role-based permissions prepared for Admin, Editor, Data Manager, and Viewer.' );
		self::render_table(
			array( 'Role', 'Permissions', 'Members' ),
			array_map(
				function ( $row ) {
					return array( $row['role'], $row['permissions'], (string) $row['members'] );
				},
				$roles
			)
		);
		self::render_panel_end();

		self::render_panel_start( 'Permission profile', 'UI-only placeholder for granular capability mapping and user assignment.' );
		self::render_form(
			array(
				'Role' => 'Data Manager',
				'Can upload sources' => 'Yes',
				'Can approve matches' => 'Yes',
				'Can publish chart weeks' => 'No',
				'Can edit settings' => 'No',
			)
		);
		self::render_panel_end();
		echo '</section>';
	}

	/**
	 * Settings view.
	 *
	 * @return void
	 */
	private static function render_settings() {
		$settings = AMC_Admin_Data::settings();

		echo '<section class="amc-admin-grid amc-admin-grid--split">';
		self::render_panel_start( 'Platform settings', 'Brand, SEO, homepage, methodology, and localization controls reserved for future persistence.' );
		self::render_form( $settings );
		self::render_button_row( array( 'Save settings', 'Reset defaults', 'Generate preview' ) );
		self::render_panel_end();

		self::render_panel_start( 'Settings summary', 'Current seeded defaults displayed for interface planning.' );
		echo '<div class="amc-admin-definition-list">';
		foreach ( $settings as $label => $value ) {
			printf( '<div><strong>%1$s</strong><span>%2$s</span></div>', esc_html( $label ), esc_html( $value ) );
		}
		echo '</div>';
		self::render_panel_end();
		echo '</section>';
	}

	/**
	 * Panel open.
	 *
	 * @param string $title Panel title.
	 * @param string $copy Panel copy.
	 * @return void
	 */
	private static function render_panel_start( $title, $copy ) {
		printf(
			'<section class="amc-admin-panel"><header><h2>%1$s</h2><p>%2$s</p></header>',
			esc_html( $title ),
			esc_html( $copy )
		);
	}

	/**
	 * Panel close.
	 *
	 * @return void
	 */
	private static function render_panel_end() {
		echo '</section>';
	}

	/**
	 * Render generic table.
	 *
	 * @param array $headers Table headers.
	 * @param array $rows Table rows.
	 * @return void
	 */
	private static function render_table( $headers, $rows ) {
		echo '<div class="amc-admin-table-wrap"><table class="widefat striped amc-admin-table"><thead><tr>';
		foreach ( $headers as $header ) {
			printf( '<th>%s</th>', esc_html( $header ) );
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr>';
			foreach ( $row as $cell ) {
				printf( '<td>%s</td>', esc_html( $cell ) );
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Render key/value form shell.
	 *
	 * @param array $fields Fields.
	 * @return void
	 */
	private static function render_form( $fields ) {
		echo '<div class="amc-admin-form">';
		foreach ( $fields as $label => $value ) {
			printf(
				'<label><span>%1$s</span><input type="text" value="%2$s" readonly></label>',
				esc_html( $label ),
				esc_attr( $value )
			);
		}
		echo '</div>';
	}

	/**
	 * Render action buttons.
	 *
	 * @param array $buttons Buttons.
	 * @return void
	 */
	private static function render_button_row( $buttons ) {
		echo '<div class="amc-admin-button-row">';
		foreach ( $buttons as $index => $label ) {
			printf(
				'<button type="button" class="button %1$s">%2$s</button>',
				0 === $index ? 'button-primary' : 'button-secondary',
				esc_html( $label )
			);
		}
		echo '</div>';
	}
}
