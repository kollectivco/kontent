<?php
/**
 * Hybrid admin UI layer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AMC_Admin {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function boot() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );

		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		}
	}

	/**
	 * Register wp-admin menu pages.
	 *
	 * @return void
	 */
	public static function register_menu() {
		$admin_pages = AMC_Admin_Data::wp_admin_pages();
		$legacy      = AMC_Admin_Data::pages();

		add_menu_page(
			'Kontentainment Charts',
			'Kontentainment Charts',
			'manage_options',
			$admin_pages['overview']['menu_slug'],
			array( __CLASS__, 'render_page' ),
			'dashicons-chart-area',
			58
		);

		foreach ( $admin_pages as $key => $page ) {
			if ( 'overview' === $key ) {
				continue;
			}

			add_submenu_page(
				$admin_pages['overview']['menu_slug'],
				'Kontentainment Charts - ' . $page['title'],
				$page['title'],
				'manage_options',
				$page['menu_slug'],
				array( __CLASS__, 'render_page' )
			);
		}

		foreach ( $legacy as $key => $page ) {
			add_submenu_page(
				null,
				'Kontentainment Charts - ' . $page['title'],
				$page['title'],
				'manage_options',
				$page['menu_slug'],
				array( __CLASS__, 'render_page' )
			);
		}
	}

	/**
	 * Enqueue wp-admin assets.
	 *
	 * @param string $hook_suffix Hook suffix.
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
	 * Render a wp-admin page.
	 *
	 * @return void
	 */
	public static function render_page() {
		$slug       = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'kontentainment-charts';
		$admin_page = self::find_admin_page_by_slug( $slug );
		$legacy     = self::find_legacy_page_by_slug( $slug );

		echo '<div class="wrap amc-admin-wrap"><div class="amc-admin-shell">';

		if ( $admin_page ) {
			self::render_wp_admin_shell( $admin_page['key'], $admin_page['page']['title'] );
		} elseif ( $legacy ) {
			self::render_legacy_shell( $legacy['key'], $legacy['page']['title'] );
		} else {
			self::render_wp_admin_shell( 'overview', 'Overview' );
		}

		echo '</div></div>';
	}

	/**
	 * Render custom dashboard shell for the frontend route.
	 *
	 * @return void
	 */
	public static function render_custom_dashboard() {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access Kontentainment Charts dashboard.', 'arabic-music-charts' ) );
		}

		$key      = self::get_dashboard_section_key();
		$sections = AMC_Admin_Data::dashboard_sections();
		$title    = $sections[ $key ]['title'];
		?>
		<div class="amc-custom-dashboard">
			<div class="amc-custom-dashboard__sidebar">
				<div class="amc-custom-dashboard__brand">
					<span>KC</span>
					<div>
						<strong>Kontentainment Charts</strong>
						<small>Main workspace</small>
					</div>
				</div>
				<nav class="amc-custom-dashboard__nav" aria-label="Kontentainment Charts Dashboard Navigation">
					<?php foreach ( $sections as $section_key => $section ) : ?>
						<a class="<?php echo $section_key === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url( AMC_Admin_Data::custom_dashboard_url( $section_key ) ); ?>">
							<?php echo esc_html( $section['title'] ); ?>
						</a>
					<?php endforeach; ?>
				</nav>
				<div class="amc-custom-dashboard__sidebar-footer">
					<a class="amc-custom-dashboard__utility" href="<?php echo esc_url( admin_url( 'admin.php?page=kontentainment-charts' ) ); ?>">Open wp-admin control layer</a>
				</div>
			</div>
			<div class="amc-custom-dashboard__main">
				<header class="amc-admin-topbar amc-admin-topbar--dashboard">
					<div>
						<p class="amc-admin-kicker">Kontentainment Charts</p>
						<h1><?php echo esc_html( $title ); ?></h1>
						<p class="amc-admin-subcopy">This is the main working experience for managing charts, weekly entries, tracks, artists, albums, uploads, methodology, publishing, archives, users, and settings.</p>
					</div>
					<div class="amc-admin-topbar__actions">
						<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=kontentainment-charts-settings' ) ); ?>">wp-admin Settings</a>
						<button type="button" class="button button-primary">New Working Draft</button>
					</div>
				</header>

				<div class="amc-admin-content">
					<?php self::render_workspace_view( $key ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Resolve dashboard section key.
	 *
	 * @return string
	 */
	public static function get_dashboard_section_key() {
		$key      = get_query_var( 'amc_dashboard' ) ? sanitize_key( get_query_var( 'amc_dashboard' ) ) : 'dashboard';
		$sections = AMC_Admin_Data::dashboard_sections();

		return isset( $sections[ $key ] ) ? $key : 'dashboard';
	}

	/**
	 * Render lightweight wp-admin shell.
	 *
	 * @param string $key Page key.
	 * @param string $title Page title.
	 * @return void
	 */
	private static function render_wp_admin_shell( $key, $title ) {
		$pages = AMC_Admin_Data::wp_admin_pages();
		?>
		<header class="amc-admin-topbar">
			<div>
				<p class="amc-admin-kicker">Kontentainment Charts</p>
				<h1><?php echo esc_html( $title ); ?></h1>
				<p class="amc-admin-subcopy">wp-admin remains the lightweight plugin control layer for overview, settings, logs, tools, permissions, and fast access into the full custom dashboard.</p>
			</div>
			<div class="amc-admin-topbar__actions">
				<a class="button button-primary" href="<?php echo esc_url( AMC_Admin_Data::custom_dashboard_url() ); ?>">Open Dashboard</a>
			</div>
		</header>

		<nav class="amc-admin-tabs" aria-label="Kontentainment Charts wp-admin Control Pages">
			<?php foreach ( $pages as $page_key => $page ) : ?>
				<a class="<?php echo $page_key === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $page['menu_slug'] ) ); ?>">
					<?php echo esc_html( $page['title'] ); ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<div class="amc-admin-content">
			<?php
			switch ( $key ) {
				case 'settings':
					self::render_wp_admin_settings();
					break;
				case 'tools':
					self::render_wp_admin_tools();
					break;
				case 'logs':
					self::render_wp_admin_logs();
					break;
				case 'permissions':
					self::render_wp_admin_permissions();
					break;
				case 'open-dashboard':
					self::render_wp_admin_open_dashboard();
					break;
				case 'overview':
				default:
					self::render_wp_admin_overview();
					break;
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render legacy page shell.
	 *
	 * @param string $key Legacy key.
	 * @param string $title Title.
	 * @return void
	 */
	private static function render_legacy_shell( $key, $title ) {
		$sections = AMC_Admin_Data::dashboard_sections();
		$target   = isset( $sections[ $key ] ) ? AMC_Admin_Data::custom_dashboard_url( $key ) : AMC_Admin_Data::custom_dashboard_url();
		?>
		<header class="amc-admin-topbar">
			<div>
				<p class="amc-admin-kicker">Kontentainment Charts</p>
				<h1><?php echo esc_html( $title ); ?></h1>
				<p class="amc-admin-subcopy">This legacy wp-admin page remains available for compatibility, but the full working experience now lives inside the custom dashboard.</p>
			</div>
			<div class="amc-admin-topbar__actions">
				<a class="button button-primary" href="<?php echo esc_url( $target ); ?>">Open In /charts-dashboard</a>
			</div>
		</header>
		<div class="amc-admin-content">
			<section class="amc-admin-panel">
				<header>
					<h2>Section moved to the main dashboard</h2>
					<p>Use the custom dashboard for the full experience. This lightweight legacy page intentionally preserves the old URL without keeping wp-admin as the main workspace.</p>
				</header>
				<div class="amc-admin-button-row">
					<a class="button button-primary" href="<?php echo esc_url( $target ); ?>">Open Dashboard Section</a>
					<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=kontentainment-charts' ) ); ?>">Back to Overview</a>
				</div>
			</section>
		</div>
		<?php
	}

	/**
	 * Overview page.
	 *
	 * @return void
	 */
	private static function render_wp_admin_overview() {
		$overview = AMC_Admin_Data::overview_cards();
		$alerts   = AMC_Admin_Data::alerts();

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
		self::render_panel_start( 'Control layer shortcuts', 'Jump directly to the main dashboard workspace for operational sections.' );
		echo '<div class="amc-admin-button-row">';
		foreach ( AMC_Admin_Data::dashboard_sections() as $key => $section ) {
			printf(
				'<a class="button %1$s" href="%2$s">%3$s</a>',
				'dashboard' === $key ? 'button-primary' : 'button-secondary',
				esc_url( AMC_Admin_Data::custom_dashboard_url( $key ) ),
				esc_html( $section['title'] )
			);
		}
		echo '</div>';
		self::render_panel_end();

		self::render_panel_start( 'Current alerts', 'A quick operational glance before moving into the main workspace.' );
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
		echo '</section>';
	}

	/**
	 * Settings page.
	 *
	 * @return void
	 */
	private static function render_wp_admin_settings() {
		self::render_panel_start( 'Plugin settings', 'Brand, homepage chart defaults, methodology text, SEO defaults, language, and date format remain available in wp-admin.' );
		self::render_form( AMC_Admin_Data::settings() );
		self::render_button_row( array( 'Save settings', 'Open full settings dashboard' ) );
		self::render_panel_end();
	}

	/**
	 * Tools page.
	 *
	 * @return void
	 */
	private static function render_wp_admin_tools() {
		$tools = AMC_Admin_Data::tools();

		self::render_panel_start( 'Plugin tools', 'Maintenance and helper tools stay in wp-admin while operational work moves into /charts-dashboard.' );
		self::render_table(
			array( 'Tool', 'Description', 'Action' ),
			array_map(
				function ( $row ) {
					return array( $row['tool'], $row['description'], $row['action'] );
				},
				$tools
			)
		);
		self::render_panel_end();
	}

	/**
	 * Logs page.
	 *
	 * @return void
	 */
	private static function render_wp_admin_logs() {
		$logs = AMC_Admin_Data::logs();

		self::render_panel_start( 'System logs', 'Recent seeded events and operational notes for the plugin control layer.' );
		self::render_table(
			array( 'Time', 'Event', 'Actor', 'Status' ),
			array_map(
				function ( $row ) {
					return array( $row['time'], $row['event'], $row['actor'], $row['status'] );
				},
				$logs
			)
		);
		self::render_panel_end();
	}

	/**
	 * Permissions page.
	 *
	 * @return void
	 */
	private static function render_wp_admin_permissions() {
		$rows = AMC_Admin_Data::permissions();

		self::render_panel_start( 'Permissions', 'Role and access snapshots remain in wp-admin for plugin governance.' );
		self::render_table(
			array( 'Role', 'Dashboard Access', 'Publishing', 'Settings' ),
			array_map(
				function ( $row ) {
					return array( $row['role'], $row['dashboard_access'], $row['publishing'], $row['settings'] );
				},
				$rows
			)
		);
		self::render_panel_end();
	}

	/**
	 * Open dashboard page.
	 *
	 * @return void
	 */
	private static function render_wp_admin_open_dashboard() {
		self::render_panel_start( 'Open Dashboard', 'The custom dashboard is now the main management surface for charts operations.' );
		echo '<div class="amc-admin-button-row">';
		echo '<a class="button button-primary" href="' . esc_url( AMC_Admin_Data::custom_dashboard_url() ) . '">Open Main Dashboard</a>';
		echo '<a class="button button-secondary" href="' . esc_url( AMC_Admin_Data::custom_dashboard_url( 'publishing' ) ) . '">Open Publishing</a>';
		echo '<a class="button button-secondary" href="' . esc_url( AMC_Admin_Data::custom_dashboard_url( 'uploads' ) ) . '">Open Source Uploads</a>';
		echo '</div>';
		self::render_panel_end();
	}

	/**
	 * Render full workspace section.
	 *
	 * @param string $view View key.
	 * @return void
	 */
	private static function render_workspace_view( $view ) {
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

	private static function find_admin_page_by_slug( $slug ) {
		foreach ( AMC_Admin_Data::wp_admin_pages() as $key => $page ) {
			if ( $page['menu_slug'] === $slug ) {
				return array(
					'key'  => $key,
					'page' => $page,
				);
			}
		}

		return null;
	}

	private static function find_legacy_page_by_slug( $slug ) {
		foreach ( AMC_Admin_Data::pages() as $key => $page ) {
			if ( $page['menu_slug'] === $slug ) {
				return array(
					'key'  => $key,
					'page' => $page,
				);
			}
		}

		return null;
	}

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

		self::render_panel_start( 'Published vs draft chart weeks', 'A seeded weekly publishing overview for the main dashboard UI.' );
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

	private static function render_panel_start( $title, $copy ) {
		printf(
			'<section class="amc-admin-panel"><header><h2>%1$s</h2><p>%2$s</p></header>',
			esc_html( $title ),
			esc_html( $copy )
		);
	}

	private static function render_panel_end() {
		echo '</section>';
	}

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

	private static function render_button_row( $buttons ) {
		echo '<div class="amc-admin-button-row">';
		foreach ( $buttons as $index => $label ) {
			$class = 0 === $index ? 'button-primary' : 'button-secondary';
			printf( '<button type="button" class="button %1$s">%2$s</button>', esc_attr( $class ), esc_html( $label ) );
		}
		echo '</div>';
	}
}
