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
		add_action( 'admin_post_amc_save_entity', array( __CLASS__, 'handle_save_entity' ) );
		add_action( 'admin_post_amc_row_action', array( __CLASS__, 'handle_row_action' ) );
		add_action( 'admin_post_amc_bulk_action', array( __CLASS__, 'handle_bulk_action' ) );
		add_action( 'admin_post_amc_save_settings', array( __CLASS__, 'handle_save_settings' ) );
		add_action( 'admin_post_amc_upload_source', array( __CLASS__, 'handle_upload_source' ) );
		add_action( 'admin_post_amc_save_scoring', array( __CLASS__, 'handle_save_scoring' ) );

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
			'amc_view_dashboard',
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
				self::page_capability( $key, true ),
				$page['menu_slug'],
				array( __CLASS__, 'render_page' )
			);
		}

		foreach ( $legacy as $key => $page ) {
			add_submenu_page(
				null,
				'Kontentainment Charts - ' . $page['title'],
				$page['title'],
				self::page_capability( $key, false ),
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

		echo '<div class="wrap amc-admin-wrap"><div class="amc-admin-shell" data-amc-theme="dark">';

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

		if ( ! current_user_can( 'amc_view_dashboard' ) ) {
			wp_die( esc_html__( 'You do not have permission to access Kontentainment Charts dashboard.', 'kontentainment-charts' ) );
		}

		$key      = self::get_dashboard_section_key();
		$sections = AMC_Admin_Data::dashboard_sections();
		$title    = $sections[ $key ]['title'];
		?>
		<div class="amc-custom-dashboard" data-amc-theme="dark">
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
						<?php self::render_theme_toggle(); ?>
						<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=kontentainment-charts-settings' ) ); ?>">wp-admin Settings</a>
						<button type="button" class="button button-primary">New Working Draft</button>
					</div>
				</header>

				<div class="amc-admin-content">
					<?php self::render_notices(); ?>
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
				<?php self::render_theme_toggle(); ?>
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
			<?php self::render_notices(); ?>
			<?php
			switch ( $key ) {
				case 'settings':
					self::render_wp_admin_settings();
					break;
				case 'notifications':
					self::render_wp_admin_notifications();
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
				<?php self::render_theme_toggle(); ?>
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
		echo '<section class="amc-admin-grid amc-admin-grid--cards amc-admin-grid--bento">';
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

		self::render_setup_panel(
			true,
			'Production setup checklist',
			'This lightweight overview tracks first-run readiness while the full operational workflow continues inside /charts-dashboard.'
		);

		echo '<section class="amc-admin-grid amc-admin-grid--split amc-admin-grid--bento">';
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
		$settings = AMC_DB::get_settings();
		self::open_form( 'save_settings' );
		echo '<div class="amc-admin-form">';
		self::field_input( 'Platform name', 'platform_name', $settings['platform_name'] );
		self::field_input( 'Logo', 'logo', $settings['logo'] );
		self::field_input( 'SEO defaults', 'seo_defaults', $settings['seo_defaults'] );
		self::field_input( 'Social image', 'social_image', $settings['social_image'] );
		self::field_select( 'Homepage chart', 'homepage_chart', $settings['homepage_chart'], self::chart_slug_options() );
		self::field_textarea( 'Methodology text', 'methodology_text', $settings['methodology_text'] );
		self::field_input( 'Language', 'language', $settings['language'] );
		self::field_input( 'Date format', 'date_format', $settings['date_format'] );
		self::field_input( 'Alert email', 'alert_email', ! empty( $settings['alert_email'] ) ? $settings['alert_email'] : '', 'email' );
		self::field_input( 'Alert webhook URL', 'alert_webhook_url', ! empty( $settings['alert_webhook_url'] ) ? $settings['alert_webhook_url'] : '' );
		self::field_input( 'Enabled alert types (comma-separated)', 'alert_types_enabled', ! empty( $settings['alert_types_enabled'] ) ? $settings['alert_types_enabled'] : '' );
		echo '</div>';
		echo '<div class="amc-admin-button-row"><button type="submit" class="button button-primary">Save settings</button><a class="button button-secondary" href="' . esc_url( AMC_Admin_Data::custom_dashboard_url( 'settings' ) ) . '">Open full settings dashboard</a></div>';
		self::close_form();
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
		echo '<div class="amc-admin-button-row">';
		echo '<a class="button button-primary" href="' . esc_url( AMC_Updater::check_updates_url() ) . '">Check for updates</a>';
		echo '<a class="button button-secondary" href="' . esc_url( admin_url( 'update-core.php?force-check=1' ) ) . '">Open WordPress Updates</a>';
		echo '</div>';
		self::render_table(
			array( 'Tool', 'Description', 'Action' ),
			array_map(
				function ( $row ) {
					return array( $row['tool'], $row['description'], $row['action'] );
				},
				$tools
			)
		);
		echo '<div class="amc-admin-definition-list">';
		printf( '<div><strong>Cron-ready service methods</strong><span>Reprocessing, regeneration, publication checks, republishing, and rollback now exist as reusable backend service methods for future automation layers.</span></div>' );
		printf( '<div><strong>Manual-first workflow</strong><span>The admin-triggered flow remains the primary path in this phase, with automation support intentionally kept foundational.</span></div>' );
		echo '</div>';
		self::render_panel_end();
	}

	/**
	 * Logs page.
	 *
	 * @return void
	 */
	private static function render_wp_admin_logs() {
		$filters = AMC_Admin_Data::logs_filters_from_request();
		$logs    = AMC_Admin_Data::logs( $filters );

		self::render_panel_start( 'System logs', 'Operational diagnostics with filters for upload, source, country, chart, status, and date range.' );
		echo '<form method="get" class="amc-admin-form">';
		echo '<input type="hidden" name="page" value="' . esc_attr( isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'kontentainment-charts-logs' ) . '">';
		self::field_input( 'Upload ID', 'log_upload_id', ! empty( $filters['upload_id'] ) ? (string) $filters['upload_id'] : '', 'number' );
		self::field_select( 'Source', 'log_source_platform', $filters['source_platform'], array( '' => 'All sources' ) + self::source_platform_options() );
		self::field_input( 'Country', 'log_country', $filters['country'] );
		self::field_select( 'Chart', 'log_target_chart_id', $filters['target_chart_id'], array( 0 => 'All charts' ) + self::chart_options() );
		self::field_select( 'Upload status', 'log_upload_status', $filters['upload_status'], array( '' => 'All statuses', 'uploaded' => 'Uploaded', 'parsed' => 'Parsed', 'matched' => 'Matched', 'generated' => 'Generated', 'published' => 'Published', 'failed' => 'Failed' ) );
		self::field_select( 'Level', 'log_level', $filters['level'], array( '' => 'All levels', 'error' => 'Error', 'warning' => 'Warning', 'info' => 'Info' ) );
		self::field_input( 'From date', 'log_date_from', $filters['date_from'], 'date' );
		self::field_input( 'To date', 'log_date_to', $filters['date_to'], 'date' );
		self::submit_row( array( array( 'label' => 'Filter logs', 'class' => 'button-primary' ) ) );
		echo '</form>';
		echo '<div class="amc-admin-button-row"><a class="button button-secondary" href="' . esc_url( self::action_url( 'export', 0, 'logs' ) ) . '">Export logs CSV</a></div>';
		self::render_table(
			array( 'Time', 'Event', 'Source', 'Country', 'Chart', 'Upload', 'Upload status', 'Status' ),
			array_map(
				function ( $row ) {
					$upload_link = $row['upload_id'] ? '<a href="' . esc_url( add_query_arg( 'upload_id', $row['upload_id'], AMC_Admin_Data::custom_dashboard_url( 'uploads' ) ) ) . '">#' . (int) $row['upload_id'] . '</a>' : 'N/A';
					return array( $row['time'], $row['event'], $row['source'], $row['country'], $row['chart'], array( 'value' => $upload_link, 'html' => true ), $row['upload_status'], $row['status'] );
				},
				$logs
			)
		);
		$priority_logs = array_filter(
			$logs,
			function ( $row ) {
				return in_array( $row['status'], array( 'Error', 'Warning' ), true );
			}
		);
		if ( ! empty( $priority_logs ) ) {
			echo '<div class="amc-admin-definition-list">';
			foreach ( array_slice( $priority_logs, 0, 8 ) as $row ) {
				printf( '<div><strong>%1$s</strong><span>%2$s</span></div>', esc_html( strtoupper( $row['status'] ) . ' / ' . $row['time'] ), esc_html( $row['event'] ) );
			}
			echo '</div>';
		}
		self::render_panel_end();
	}

	/**
	 * Notification center page.
	 *
	 * @return void
	 */
	private static function render_wp_admin_notifications() {
		self::render_notification_center_panel( true );
	}

	/**
	 * Shared notification center panel.
	 *
	 * @param bool $wp_admin Whether wp-admin shell is active.
	 * @return void
	 */
	private static function render_notification_center_panel( $wp_admin = false ) {
		$filters       = AMC_Admin_Data::notification_filters_from_request();
		$notifications = AMC_Admin_Data::notification_center( $filters );
		$severity_map  = array( 'Info' => 0, 'Success' => 0, 'Warning' => 0, 'Error' => 0 );

		foreach ( $notifications as $notification ) {
			if ( isset( $severity_map[ $notification['severity'] ] ) ) {
				++$severity_map[ $notification['severity'] ];
			}
		}

		self::render_panel_start( 'Notification center', 'Persistent operational notifications with unread/read state, dismissal, and severity filters.' );
		echo '<div class="amc-admin-stat-stack">';
		foreach ( $severity_map as $label => $count ) {
			printf( '<div><strong>%1$s</strong><span>%2$s</span></div>', esc_html( (string) $count ), esc_html( $label ) );
		}
		echo '</div>';
		echo '<form method="get" class="amc-admin-form">';
		if ( $wp_admin ) {
			echo '<input type="hidden" name="page" value="' . esc_attr( isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'kontentainment-charts-notifications' ) . '">';
		}
		self::field_select( 'Severity', 'notice_severity', $filters['severity'], array( '' => 'All severities', 'info' => 'Info', 'success' => 'Success', 'warning' => 'Warning', 'error' => 'Error' ) );
		self::field_select( 'Status', 'notice_status', $filters['status'], array( '' => 'All statuses', 'unread' => 'Unread', 'read' => 'Read', 'dismissed' => 'Dismissed' ) );
		self::field_input( 'Date', 'notice_date', $filters['date'], 'date' );
		self::submit_row( array( array( 'label' => 'Filter notifications', 'class' => 'button-primary' ) ) );
		echo '</form>';
		$this_url = $wp_admin ? admin_url( 'admin.php?page=kontentainment-charts-notifications' ) : AMC_Admin_Data::custom_dashboard_url( 'notifications' );
		echo '<div class="amc-admin-button-row">';
		echo '<a class="button button-secondary" href="' . esc_url( $this_url ) . '">Reset filters</a>';
		if ( $wp_admin ) {
			echo '<a class="button button-secondary" href="' . esc_url( AMC_Admin_Data::custom_dashboard_url( 'notifications' ) ) . '">Open full notification center</a>';
		}
		echo '<a class="button button-secondary" href="' . esc_url( self::bulk_action_url( 'notification', 'read_filtered' ) ) . '">Mark all filtered read</a>';
		echo '<a class="button button-secondary" href="' . esc_url( self::bulk_action_url( 'notification', 'dismiss_filtered' ) ) . '">Dismiss all filtered</a>';
		echo '</div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'amc_bulk_action' );
		echo '<input type="hidden" name="action" value="amc_bulk_action">';
		echo '<input type="hidden" name="entity" value="notification">';
		echo '<input type="hidden" name="redirect_to" value="' . esc_attr( self::current_url() ) . '">';
		self::render_table(
			array( '', 'Severity', 'Message', 'Status', 'Time', 'Actions' ),
			array_map(
				function ( $row ) {
					$actions = array();
					if ( 'Unread' === $row['status'] ) {
						$actions[] = '<a href="' . esc_url( self::action_url( 'notification', $row['id'], 'read' ) ) . '">Mark read</a>';
					}
					if ( 'Dismissed' !== $row['status'] ) {
						$actions[] = '<a href="' . esc_url( self::action_url( 'notification', $row['id'], 'dismiss' ) ) . '">Dismiss</a>';
					}
					$link_bits = array();
					if ( ! empty( $row['context']['upload_id'] ) ) {
						$link_bits[] = '<a href="' . esc_url( add_query_arg( 'upload_id', (int) $row['context']['upload_id'], AMC_Admin_Data::custom_dashboard_url( 'uploads' ) ) ) . '">Upload #' . (int) $row['context']['upload_id'] . '</a>';
					}
					if ( ! empty( $row['context']['chart_id'] ) ) {
						$link_bits[] = '<a href="' . esc_url( AMC_Admin_Data::custom_dashboard_url( 'charts' ) ) . '">Chart #' . (int) $row['context']['chart_id'] . '</a>';
					}
					if ( ! empty( $row['context']['week_id'] ) ) {
						$link_bits[] = '<a href="' . esc_url( add_query_arg( 'week_id', (int) $row['context']['week_id'], AMC_Admin_Data::custom_dashboard_url( 'weekly-entries' ) ) ) . '">Week #' . (int) $row['context']['week_id'] . '</a>';
					}
					if ( ! empty( $row['context']['job_id'] ) ) {
						$link_bits[] = '<a href="' . esc_url( AMC_Admin_Data::custom_dashboard_url( 'jobs' ) ) . '">Job #' . (int) $row['context']['job_id'] . '</a>';
					}
					$message = esc_html( $row['message'] );
					if ( ! empty( $link_bits ) ) {
						$message .= '<div class="amc-admin-inline-links">' . implode( ' / ', $link_bits ) . '</div>';
					}
					return array( array( 'value' => '<input type="checkbox" name="selected_ids[]" value="' . esc_attr( $row['id'] ) . '">', 'html' => true ), $row['severity'], array( 'value' => $message, 'html' => true ), $row['status'], $row['created_at'], array( 'value' => implode( ' / ', $actions ), 'html' => true ) );
				},
				$notifications
			)
		);
		echo '<div class="amc-admin-button-row">';
		echo '<button type="submit" class="button button-secondary" name="task" value="read_selected">Mark selected read</button>';
		echo '<button type="submit" class="button button-secondary" name="task" value="dismiss_selected">Dismiss selected</button>';
		echo '</div>';
		echo '</form>';
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
			case 'jobs':
				self::render_jobs();
				break;
			case 'archives':
				self::render_archives();
				break;
			case 'notifications':
				self::render_notification_center();
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
	 * Get capability for a page key.
	 *
	 * @param string $key Page key.
	 * @param bool   $wp_admin Whether this is a wp-admin page.
	 * @return string
	 */
	private static function page_capability( $key, $wp_admin = false ) {
		$map = array(
			'overview'       => 'amc_view_dashboard',
			'notifications'  => 'amc_view_dashboard',
			'tools'          => 'amc_view_dashboard',
			'logs'           => 'amc_view_dashboard',
			'permissions'    => 'amc_manage_settings',
			'open-dashboard' => 'amc_view_dashboard',
			'settings'       => 'amc_manage_settings',
			'dashboard'      => 'amc_view_dashboard',
			'charts'         => 'amc_manage_charts',
			'weekly-entries' => 'amc_manage_weeks',
			'tracks'         => 'amc_manage_library',
			'artists'        => 'amc_manage_library',
			'albums'         => 'amc_manage_library',
			'uploads'        => 'amc_manage_weeks',
			'cleaning'       => 'amc_manage_weeks',
			'scoring'        => 'amc_manage_weeks',
			'publishing'     => 'amc_publish_charts',
			'jobs'           => 'amc_view_dashboard',
			'archives'       => 'amc_publish_charts',
			'users'          => 'amc_manage_settings',
		);

		if ( ! empty( $map[ $key ] ) ) {
			return $map[ $key ];
		}

		return $wp_admin ? 'amc_view_dashboard' : 'amc_manage_settings';
	}

	/**
	 * Render notices from redirects.
	 *
	 * @return void
	 */
	private static function render_notices() {
		$type    = isset( $_GET['amc_notice_type'] ) ? sanitize_key( wp_unslash( $_GET['amc_notice_type'] ) ) : '';
		$message = isset( $_GET['amc_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['amc_notice'] ) ) : '';
		$notifications = AMC_Admin_Data::notifications();

		if ( ! $type || ! $message ) {
			if ( empty( $notifications ) ) {
				return;
			}
		}

		if ( $type && $message ) {
			$tone = 'info';

			if ( 'success' === $type ) {
				$tone = 'info';
			} elseif ( 'error' === $type ) {
				$tone = 'danger';
			} elseif ( 'warning' === $type ) {
				$tone = 'warning';
			}

			echo '<section class="amc-admin-alert amc-admin-alert--' . esc_attr( $tone ) . '"><strong>' . esc_html( ucfirst( $type ) ) . '</strong><p>' . esc_html( $message ) . '</p></section>';
		}

		if ( ! empty( $notifications ) ) {
			echo '<div class="amc-admin-alerts">';
			foreach ( $notifications as $notification ) {
				$tone = 'info';
				if ( 'warning' === $notification['type'] ) {
					$tone = 'warning';
				} elseif ( 'error' === $notification['type'] ) {
					$tone = 'danger';
				}
				echo '<section class="amc-admin-alert amc-admin-alert--' . esc_attr( $tone ) . '"><strong>' . esc_html( ucfirst( $notification['type'] ) ) . '</strong><p>' . esc_html( $notification['message'] ) . '</p></section>';
			}
			echo '</div>';
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
		$ops      = AMC_Admin_Data::operational_summary();
		$jobs     = AMC_Admin_Data::job_observability();

		self::render_workflow_strip();
		echo '<section class="amc-admin-grid amc-admin-grid--cards amc-admin-grid--bento">';
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

		self::render_setup_panel(
			false,
			'First live data workflow',
			'Use this checklist to move from a clean production install to the first published chart week without demo content or hidden assumptions.'
		);

		echo '<section class="amc-admin-grid amc-admin-grid--split amc-admin-grid--bento">';
		self::render_panel_start( 'Recent uploads', 'Latest source sheets moving through the chart pipeline.' );
		if ( empty( $uploads ) ) {
			echo '<p>No uploads are in motion yet. Start from Source Uploads, then return here to monitor parse, match, generate, and publish progress.</p>';
		}
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

		self::render_panel_start( 'Published vs draft chart weeks', 'A live weekly publishing overview for the main dashboard UI.' );
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

		self::render_panel_start( 'Next actions', 'Direct entry points into the operational flow.' );
		echo '<div class="amc-admin-button-row">';
		echo '<a class="button button-primary" href="' . esc_url( AMC_Admin_Data::custom_dashboard_url( 'uploads' ) ) . '">Upload source file</a>';
		echo '<a class="button button-secondary" href="' . esc_url( AMC_Admin_Data::custom_dashboard_url( 'cleaning' ) ) . '">Review matching queue</a>';
		echo '<a class="button button-secondary" href="' . esc_url( AMC_Admin_Data::custom_dashboard_url( 'weekly-entries' ) ) . '">Review generated weeks</a>';
		echo '<a class="button button-secondary" href="' . esc_url( AMC_Admin_Data::custom_dashboard_url( 'publishing' ) ) . '">Open publishing</a>';
		echo '<a class="button button-secondary" href="' . esc_url( AMC_Admin_Data::custom_dashboard_url( 'jobs' ) ) . '">Open jobs queue</a>';
		echo '<a class="button button-secondary" href="' . esc_url( AMC_Admin_Data::custom_dashboard_url( 'notifications' ) ) . '">Open notification center</a>';
		echo '</div>';
		self::render_panel_end();

		echo '<section class="amc-admin-grid amc-admin-grid--split amc-admin-grid--bento">';
		self::render_panel_start( 'Queue observability', 'Track queued, running, and failed work without leaving the main dashboard.' );
		echo '<div class="amc-admin-stat-stack">';
		printf( '<div><strong>%1$s</strong><span>Queued jobs</span></div>', esc_html( (string) $jobs['summary']['queued'] ) );
		printf( '<div><strong>%1$s</strong><span>Running jobs</span></div>', esc_html( (string) $jobs['summary']['running'] ) );
		printf( '<div><strong>%1$s</strong><span>Failed jobs</span></div>', esc_html( (string) $jobs['summary']['failed'] ) );
		printf( '<div><strong>%1$s</strong><span>Avg. processing duration</span></div>', esc_html( $jobs['average_duration'] ? (string) $jobs['average_duration'] . 's' : 'N/A' ) );
		echo '</div>';
		echo '<div class="amc-admin-button-row"><a class="button button-secondary" href="' . esc_url( self::action_url( 'job', 0, 'run-queued' ) ) . '">Run queued jobs now</a><a class="button button-secondary" href="' . esc_url( AMC_Admin_Data::custom_dashboard_url( 'jobs' ) ) . '">Open jobs queue</a></div>';
		self::render_panel_end();

		self::render_panel_start( 'Recent failures', 'Quick drill-down into recent failed jobs and blocked operations.' );
		if ( empty( $jobs['recent_failures'] ) ) {
			echo '<p>No failed jobs are waiting for attention right now.</p>';
		} else {
			echo '<div class="amc-admin-definition-list">';
			foreach ( $jobs['recent_failures'] as $job ) {
				printf( '<div><strong>%1$s</strong><span>%2$s</span></div>', esc_html( $job['job_type'] . ' / ' . $job['related'] ), esc_html( $job['failure_reason'] ? $job['failure_reason'] : 'Failed without a stored reason.' ) );
			}
			echo '</div>';
		}
		self::render_panel_end();
		echo '</section>';
	}

	/**
	 * Jobs queue view.
	 *
	 * @return void
	 */
	private static function render_jobs() {
		$filters = AMC_Admin_Data::job_filters_from_request();
		$jobs    = AMC_Admin_Data::jobs( $filters );

		self::render_panel_start( 'Jobs and queue', 'Queue controls for retrying failures, cancelling queued tasks, rerunning safe completed tasks, and manually advancing queued work.' );
		echo '<form method="get" class="amc-admin-form">';
		if ( is_admin() ) {
			echo '<input type="hidden" name="page" value="' . esc_attr( isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'kontentainment-charts' ) . '">';
		}
		self::field_select( 'State', 'job_status', $filters['status'], array( '' => 'All states', 'queued' => 'Queued', 'running' => 'Running', 'completed' => 'Completed', 'failed' => 'Failed', 'cancelled' => 'Cancelled' ) );
		self::field_select( 'Job type', 'job_type', $filters['job_type'], array( '' => 'All types', 'parse_upload' => 'Parse Upload', 'rerun_matching' => 'Rerun Matching', 'auto_create_processing' => 'Auto-create Processing', 'generate_chart' => 'Generate Chart', 'publish_checks' => 'Publish Checks', 'cleanup_diagnostics' => 'Cleanup Diagnostics' ) );
		self::field_select( 'Chart', 'job_chart_id', $filters['chart_id'], array( 0 => 'All charts' ) + self::chart_options() );
		self::field_input( 'Country', 'job_country', $filters['country'] );
		self::field_input( 'Week / date', 'job_week_date', $filters['week_date'], 'date' );
		self::submit_row( array( array( 'label' => 'Filter jobs', 'class' => 'button-primary' ) ) );
		echo '</form>';
		echo '<div class="amc-admin-button-row">';
		echo '<a class="button button-primary" href="' . esc_url( self::action_url( 'job', 0, 'run-queued' ) ) . '">Run queued jobs now</a>';
		echo '<a class="button button-secondary" href="' . esc_url( AMC_Admin_Data::custom_dashboard_url( 'notifications' ) ) . '">Open notifications</a>';
		echo '</div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'amc_bulk_action' );
		echo '<input type="hidden" name="action" value="amc_bulk_action">';
		echo '<input type="hidden" name="entity" value="job">';
		echo '<input type="hidden" name="redirect_to" value="' . esc_attr( self::current_url() ) . '">';
		self::render_table(
			array( '', 'Job type', 'Related', 'State', 'Trigger', 'Created', 'Started', 'Finished', 'Duration', 'Attempts', 'Retry', 'Failure', 'Actions' ),
			array_map(
				function ( $row ) {
					$actions = array();
					if ( 'failed' === $row['raw_state'] ) {
						$actions[] = '<a href="' . esc_url( self::action_url( 'job', $row['id'], 'retry' ) ) . '">Retry</a>';
					}
					if ( 'queued' === $row['raw_state'] ) {
						$actions[] = '<a href="' . esc_url( self::action_url( 'job', $row['id'], 'run' ) ) . '">Run now</a>';
						$actions[] = '<a href="' . esc_url( self::action_url( 'job', $row['id'], 'cancel' ) ) . '">Cancel</a>';
					}
					if ( 'completed' === $row['raw_state'] ) {
						$actions[] = '<a href="' . esc_url( self::action_url( 'job', $row['id'], 'rerun' ) ) . '">Rerun safely</a>';
					}
					$retry_meta = $row['max_attempts'] ? $row['attempts'] . '/' . $row['max_attempts'] : (string) $row['attempts'];
					if ( ! empty( $row['next_retry_at'] ) ) {
						$retry_meta .= ' / next ' . $row['next_retry_at'];
					}
					if ( ! empty( $row['last_error_step'] ) ) {
						$retry_meta .= ' / ' . $row['last_error_step'];
					}
					return array( array( 'value' => '<input type="checkbox" name="selected_ids[]" value="' . esc_attr( $row['id'] ) . '">', 'html' => true ), $row['job_type'], $row['related'], $row['state'], $row['trigger_mode'], $row['created_time'], $row['started_time'], $row['finished_time'], $row['duration'], (string) $row['attempts'], $retry_meta, $row['failure_reason'] ? $row['failure_reason'] : 'None', array( 'value' => implode( ' / ', $actions ), 'html' => true ) );
				},
				$jobs
			)
		);
		echo '<div class="amc-admin-button-row">';
		echo '<button type="submit" class="button button-secondary" name="task" value="retry">Retry selected failed jobs</button>';
		echo '<button type="submit" class="button button-secondary" name="task" value="cancel">Cancel selected queued jobs</button>';
		echo '</div>';
		echo '</form>';
		self::render_panel_end();
		$analytics = AMC_Admin_Data::job_observability();
		if ( ! empty( $analytics['by_type'] ) || ! empty( $analytics['backlog'] ) ) {
			echo '<div class="amc-admin-grid amc-admin-grid--split amc-admin-grid--bento">';
			self::render_panel_start( 'Job history by type', 'Track throughput, failure rates, average duration, and retry frequency by job type.' );
			echo '<div class="amc-admin-definition-list">';
			foreach ( $analytics['by_type'] as $row ) {
				$failure_rate = ! empty( $row['total_jobs'] ) ? round( ( (int) $row['failed_jobs'] / (int) $row['total_jobs'] ) * 100, 1 ) : 0;
				printf( '<div><strong>%1$s</strong><span>%2$s jobs / %3$s%% failure / avg %4$ss / retries %5$s</span></div>', esc_html( ucwords( str_replace( '_', ' ', $row['job_type'] ) ) ), esc_html( (string) $row['total_jobs'] ), esc_html( (string) $failure_rate ), esc_html( (string) round( (float) $row['avg_duration'], 1 ) ), esc_html( (string) $row['retried_jobs'] ) );
			}
			echo '</div>';
			self::render_panel_end();
			self::render_panel_start( 'Backlog over time', 'A recent operational trend view of total jobs versus queued/running backlog by day.' );
			echo '<div class="amc-admin-definition-list">';
			foreach ( $analytics['backlog'] as $row ) {
				printf( '<div><strong>%1$s</strong><span>%2$s total jobs / %3$s backlog</span></div>', esc_html( $row['bucket_date'] ), esc_html( (string) $row['total_jobs'] ), esc_html( (string) $row['backlog_jobs'] ) );
			}
			echo '</div>';
			self::render_panel_end();
			echo '</div>';
		}
	}

	/**
	 * Full notification center inside the custom dashboard.
	 *
	 * @return void
	 */
	private static function render_notification_center() {
		self::render_notification_center_panel( false );
	}

	private static function render_charts() {
		$charts = AMC_Admin_Data::chart_categories();
		$id     = isset( $_GET['chart_id'] ) ? absint( wp_unslash( $_GET['chart_id'] ) ) : 0;
		$chart  = $id ? AMC_DB::get_row( 'charts', $id ) : null;
		$chart  = $chart ? $chart : array(
			'id'               => 0,
			'name'             => '',
			'slug'             => '',
			'description'      => '',
			'type'             => 'track',
			'cover_image'      => '',
			'display_order'    => count( $charts ) + 1,
			'status'           => 'active',
			'is_featured_home' => 0,
			'archive_enabled'  => 1,
			'accent'           => 'amber',
			'kicker'           => '',
		);
		echo '<section class="amc-admin-grid amc-admin-grid--split">';
		self::render_panel_start( $id ? 'Edit chart category' : 'Create chart category', 'Dynamic chart creation now writes to the plugin database and is no longer hardcoded.' );
		self::open_form(
			'save_entity',
			array(
				'entity' => 'chart',
				'id'     => $chart['id'],
			)
		);
		echo '<div class="amc-admin-form">';
		self::field_input( 'Chart name', 'name', $chart['name'] );
		self::field_input( 'Slug', 'slug', $chart['slug'] );
		self::field_textarea( 'Description', 'description', $chart['description'] );
		self::field_select( 'Type', 'type', $chart['type'], array( 'track' => 'Tracks', 'artist' => 'Artists', 'album' => 'Albums' ) );
		self::field_input( 'Cover image', 'cover_image', $chart['cover_image'] );
		self::field_input( 'Display order', 'display_order', $chart['display_order'], 'number' );
		self::field_select( 'Active / Hidden', 'status', $chart['status'], array( 'active' => 'Active', 'hidden' => 'Hidden' ) );
		self::field_checkbox( 'Featured on homepage', 'is_featured_home', ! empty( $chart['is_featured_home'] ) );
		self::field_checkbox( 'Archive enabled', 'archive_enabled', ! empty( $chart['archive_enabled'] ) );
		self::field_select( 'Accent', 'accent', $chart['accent'], array( 'amber' => 'Amber', 'crimson' => 'Crimson', 'teal' => 'Teal', 'violet' => 'Violet', 'blue' => 'Blue' ) );
		self::field_input( 'Kicker', 'kicker', $chart['kicker'] );
		echo '</div>';
		self::submit_row( array( array( 'label' => $id ? 'Update chart' : 'Create chart', 'class' => 'button-primary' ) ) );
		self::close_form();
		self::render_panel_end();
		self::render_panel_start( 'Existing chart categories', 'Categories remain dynamic and are not hardcoded to a fixed set.' );
		self::render_table(
			array( 'Chart name', 'Slug', 'Type', 'Order', 'Status', 'Featured', 'Archive', 'Actions' ),
			array_map(
				function ( $row ) {
					$actions = array(
						'<a href="' . esc_url( add_query_arg( 'chart_id', $row['id'], self::current_url() ) ) . '">Edit</a>',
						'<a href="' . esc_url( self::action_url( 'chart', $row['id'], 'Active' === $row['active'] ? 'hide' : 'activate' ) ) . '">' . esc_html( 'Active' === $row['active'] ? 'Hide' : 'Activate' ) . '</a>',
						'<a href="' . esc_url( self::action_url( 'chart', $row['id'], 'Yes' === $row['featured'] ? 'unfeature' : 'feature' ) ) . '">' . esc_html( 'Yes' === $row['featured'] ? 'Unfeature' : 'Feature' ) . '</a>',
						'<a href="' . esc_url( self::action_url( 'chart', $row['id'], 'delete' ) ) . '">Delete</a>',
					);

					return array( $row['name'], $row['slug'], $row['type'], (string) $row['display_order'], $row['active'], $row['featured'], $row['archive'], array( 'value' => implode( ' / ', $actions ), 'html' => true ) );
				},
				$charts
			)
		);
		self::render_panel_end();
		echo '</section>';

		self::render_panel_start( 'Live operations snapshot', 'Real-time operational summary across uploads, review queues, generation readiness, and currently live chart weeks.' );
		echo '<div class="amc-admin-stat-stack">';
		printf( '<div><strong>%1$s</strong><span>Pending review items</span></div>', esc_html( (string) $ops['pending_review'] ) );
		printf( '<div><strong>%1$s</strong><span>Ready-to-create entities</span></div>', esc_html( (string) $ops['ready_to_create'] ) );
		printf( '<div><strong>%1$s</strong><span>Uploads ready to generate</span></div>', esc_html( (string) $ops['charts_ready'] ) );
		printf( '<div><strong>%1$s</strong><span>Draft weeks ready to publish</span></div>', esc_html( (string) $ops['draft_ready'] ) );
		printf( '<div><strong>%1$s</strong><span>Currently live weeks</span></div>', esc_html( (string) count( $ops['live_weeks'] ) ) );
		printf( '<div><strong>%1$s</strong><span>Queued jobs</span></div>', esc_html( (string) $ops['jobs']['queued'] ) );
		printf( '<div><strong>%1$s</strong><span>Running jobs</span></div>', esc_html( (string) $ops['jobs']['running'] ) );
		printf( '<div><strong>%1$s</strong><span>Failed jobs</span></div>', esc_html( (string) $ops['jobs']['failed'] ) );
		echo '</div>';
		if ( ! empty( $ops['live_weeks'] ) ) {
			echo '<div class="amc-admin-definition-list">';
			foreach ( $ops['live_weeks'] as $item ) {
				printf( '<div><strong>%1$s</strong><span>%2$s / Week of %3$s</span></div>', esc_html( $item['chart'] ), esc_html( $item['country'] ), esc_html( $item['week'] ) );
			}
			echo '</div>';
		} else {
			echo '<p>No chart week is live yet. Complete the first-run workflow to publish the first public chart.</p>';
		}
		self::render_panel_end();
	}

	private static function render_weekly_entries() {
		$weeks   = AMC_DB::get_chart_weeks();
		$week_id = isset( $_GET['week_id'] ) ? absint( wp_unslash( $_GET['week_id'] ) ) : 0;
		$entry_id= isset( $_GET['entry_id'] ) ? absint( wp_unslash( $_GET['entry_id'] ) ) : 0;
		$week    = $week_id ? AMC_DB::get_row( 'chart_weeks', $week_id ) : null;

		if ( ! $week && ! empty( $weeks[0] ) ) {
			$week = $weeks[0];
		}

		$entries = $week ? AMC_Admin_Data::entries_for_week( (int) $week['id'] ) : array();
		$entry   = $entry_id ? AMC_DB::get_row( 'chart_entries', $entry_id ) : null;

		if ( ! $week ) {
			$week = array(
				'id'          => 0,
				'chart_id'    => 0,
				'country'     => 'Global',
				'week_date'   => current_time( 'Y-m-d' ),
				'status'      => 'draft',
				'is_featured' => 0,
				'notes'       => '',
			);
		}

		if ( ! $entry ) {
			$entry = array(
				'id'             => 0,
				'chart_week_id'  => $week['id'],
				'entity_type'    => 'track',
				'entity_id'      => 0,
				'current_rank'   => 1,
				'previous_rank'  => 0,
				'peak_rank'      => 1,
				'weeks_on_chart' => 1,
				'movement'       => 'new',
				'score'          => '0.00',
				'artwork'        => '',
			);
		}

		$selected_chart = ! empty( $week['chart_id'] ) ? AMC_DB::get_row( 'charts', (int) $week['chart_id'] ) : null;
		$entity_options = self::entity_options_for_type( ! empty( $selected_chart['type'] ) ? $selected_chart['type'] : $entry['entity_type'] );

		self::render_workflow_strip( 'weekly-entries' );
		self::render_panel_start( 'Workflow handoff', 'Use Weekly Entries after uploads and matching are clean: review generated draft rows here, then move to Publishing for final live review and publish actions.' );
		echo '<div class="amc-admin-button-row">';
		echo '<a class="button button-secondary" href="' . esc_url( AMC_Admin_Data::custom_dashboard_url( 'uploads' ) ) . '">Back to uploads</a>';
		echo '<a class="button button-secondary" href="' . esc_url( AMC_Admin_Data::custom_dashboard_url( 'cleaning' ) ) . '">Open matching review</a>';
		echo '<a class="button button-primary" href="' . esc_url( AMC_Admin_Data::custom_dashboard_url( 'publishing' ) ) . '">Continue to publishing</a>';
		echo '</div>';
		self::render_panel_end();

		echo '<section class="amc-admin-grid amc-admin-grid--split">';
		self::render_panel_start( 'Chart week controls', 'Create new chart weeks, switch status, and archive previous snapshots.' );
		self::open_form(
			'save_entity',
			array(
				'entity' => 'week',
				'id'     => $week['id'],
			)
		);
		echo '<div class="amc-admin-form">';
		self::field_select( 'Chart category', 'chart_id', $week['chart_id'], self::chart_options() );
		self::field_input( 'Country', 'country', ! empty( $week['country'] ) ? $week['country'] : 'Global' );
		self::field_input( 'Week / date', 'week_date', $week['week_date'], 'date' );
		self::field_select( 'Status', 'status', $week['status'], array( 'draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived' ) );
		self::field_checkbox( 'Feature on homepage', 'is_featured', ! empty( $week['is_featured'] ) );
		self::field_textarea( 'Notes', 'notes', $week['notes'] );
		echo '</div>';
		self::submit_row( array( array( 'label' => $week['id'] ? 'Update week' : 'Create week', 'class' => 'button-primary' ) ) );
		self::close_form();

		if ( ! empty( $week['id'] ) ) {
			echo '<div class="amc-admin-button-row">';
			echo '<a class="button button-secondary" href="' . esc_url( self::action_url( 'week', (int) $week['id'], 'generate' ) ) . '">Generate draft from approved uploads</a>';
			echo '<a class="button button-secondary" href="' . esc_url( self::confirm_action_url( 'week', (int) $week['id'], 'republish', 'Republish this chart week?' ) ) . '">Republish week</a>';
			echo '<a class="button button-secondary" href="' . esc_url( self::confirm_action_url( 'week', (int) $week['id'], 'rollback', 'Rollback to the previous published week?' ) ) . '">Rollback to previous live week</a>';
			echo '</div>';
		}

		if ( ! empty( $weeks ) ) {
			self::render_table(
				array( 'Week', 'Country', 'Chart', 'Status', 'Featured', 'Actions' ),
				array_map(
					function ( $row ) {
						$chart = AMC_DB::get_row( 'charts', (int) $row['chart_id'] );
						$actions = array(
							'<a href="' . esc_url( add_query_arg( 'week_id', $row['id'], self::current_url() ) ) . '">Edit</a>',
							'<a href="' . esc_url( self::action_url( 'week', $row['id'], 'generate' ) ) . '">Generate</a>',
							'<a href="' . esc_url( self::confirm_action_url( 'week', $row['id'], 'published' === $row['status'] ? 'unpublish' : 'publish', 'Are you sure you want to change the live state of this chart week?' ) ) . '">' . esc_html( 'published' === $row['status'] ? 'Unpublish' : 'Publish' ) . '</a>',
							'<a href="' . esc_url( self::confirm_action_url( 'week', $row['id'], 'republish', 'Republish this chart week?' ) ) . '">Republish</a>',
							'<a href="' . esc_url( self::action_url( 'week', $row['id'], 'archived' === $row['status'] ? 'restore' : 'archive' ) ) . '">' . esc_html( 'archived' === $row['status'] ? 'Restore' : 'Archive' ) . '</a>',
							'<a href="' . esc_url( self::confirm_action_url( 'week', $row['id'], 'rollback', 'Rollback to the previous published week?' ) ) . '">Rollback</a>',
							'<a href="' . esc_url( self::action_url( 'week', $row['id'], ! empty( $row['is_featured'] ) ? 'unfeature' : 'feature' ) ) . '">' . esc_html( ! empty( $row['is_featured'] ) ? 'Unfeature' : 'Feature' ) . '</a>',
						);
						return array(
							$row['week_date'],
							! empty( $row['country'] ) ? $row['country'] : 'Global',
							$chart ? $chart['name'] : 'Unknown',
							ucfirst( $row['status'] ),
							! empty( $row['is_featured'] ) ? 'Yes' : 'No',
							array( 'value' => implode( ' / ', $actions ), 'html' => true ),
						);
					},
					$weeks
				)
			);
		}
		self::render_panel_end();
		self::render_panel_start( 'Weekly ranking entries', 'Manual editing UI for current rank, previous rank, peak rank, movement, score, and linked artwork.' );
		if ( ! empty( $week['id'] ) ) {
			self::open_form(
				'save_entity',
				array(
					'entity'        => 'entry',
					'id'            => $entry['id'],
					'chart_week_id' => $week['id'],
				)
			);
			echo '<div class="amc-admin-form">';
			self::field_select( 'Entity type', 'entity_type', $entry['entity_type'], array( 'track' => 'Track', 'artist' => 'Artist', 'album' => 'Album' ) );
			self::field_select( 'Linked item', 'entity_id', $entry['entity_id'], $entity_options );
			self::field_input( 'Current rank', 'current_rank', $entry['current_rank'], 'number' );
			self::field_input( 'Previous rank', 'previous_rank', $entry['previous_rank'], 'number' );
			self::field_input( 'Peak rank', 'peak_rank', $entry['peak_rank'], 'number' );
			self::field_input( 'Weeks on chart', 'weeks_on_chart', $entry['weeks_on_chart'], 'number' );
			self::field_select( 'Movement', 'movement', $entry['movement'], array( 'up' => 'Up', 'down' => 'Down', 'same' => 'Same', 'new' => 'New', 're-entry' => 'Re-entry' ) );
			self::field_input( 'Score', 'score', $entry['score'], 'number' );
			self::field_input( 'Artwork', 'artwork', $entry['artwork'] );
			echo '</div>';
			self::submit_row( array( array( 'label' => $entry['id'] ? 'Update entry' : 'Add entry', 'class' => 'button-primary' ) ) );
			self::close_form();
		} else {
			echo '<p>Create or select a chart week first to manage entries.</p>';
		}
		self::render_table(
			array( 'Rank', 'Linked item', 'Artist', 'Previous', 'Peak', 'Weeks', 'Move', 'Score', 'Change', 'Sources', 'Status', 'Actions' ),
			array_map(
				function ( $row ) use ( $week ) {
					$actions = array(
						'<a href="' . esc_url( add_query_arg( array( 'week_id' => (int) $week['id'], 'entry_id' => $row['id'] ), self::current_url() ) ) . '">Edit</a>',
						'<a href="' . esc_url( self::action_url( 'entry', $row['id'], 'delete' ) ) . '">Delete</a>',
					);
					return array( (string) $row['rank'], $row['item'], $row['linked'], (string) $row['previous'], (string) $row['peak'], (string) $row['weeks'], $row['movement'], $row['score'], $row['score_change'], (string) $row['source_count'], $row['status'], array( 'value' => implode( ' / ', $actions ), 'html' => true ) );
				},
				$entries
			)
		);
		if ( ! empty( $week['dropped_out_json'] ) ) {
			$dropped_out = json_decode( $week['dropped_out_json'], true );
			if ( is_array( $dropped_out ) && ! empty( $dropped_out ) ) {
				echo '<div class="amc-admin-definition-list">';
				foreach ( $dropped_out as $item ) {
					printf(
						'<div><strong>%1$s</strong><span>Dropped out from previous week at rank %2$s</span></div>',
						esc_html( ! empty( $item['name'] ) ? $item['name'] : 'Unknown item' ),
						esc_html( (string) ( ! empty( $item['previous_rank'] ) ? $item['previous_rank'] : '-' ) )
					);
				}
				echo '</div>';
				echo '<div class="amc-admin-button-row"><a class="button button-secondary" href="' . esc_url( self::action_url( 'export', (int) $week['id'], 'dropped_out' ) ) . '">Export dropped-out summary</a></div>';
			}
		}
		self::render_panel_end();
		echo '</section>';
	}

	private static function render_tracks() {
		$tracks = AMC_Admin_Data::tracks();
		$id     = isset( $_GET['track_id'] ) ? absint( wp_unslash( $_GET['track_id'] ) ) : 0;
		$track  = $id ? AMC_DB::get_row( 'tracks', $id ) : null;
		$track  = $track ? $track : array(
			'id'           => 0,
			'title'        => '',
			'slug'         => '',
			'cover_image'  => '',
			'artist_id'    => 0,
			'album_id'     => 0,
			'isrc'         => '',
			'aliases'      => '',
			'release_date' => '',
			'genre'        => '',
			'duration'     => '',
			'description'  => '',
			'gradient'     => 'ocean',
			'status'       => 'active',
		);
		echo '<section class="amc-admin-grid amc-admin-grid--split">';
		self::render_panel_start( 'Track editor', 'Manage title, slug, artist, album, ISRC, aliases, release date, genre, and visibility state.' );
		self::open_form(
			'save_entity',
			array(
				'entity' => 'track',
				'id'     => $track['id'],
			)
		);
		echo '<div class="amc-admin-form">';
		self::field_input( 'Track title', 'title', $track['title'] );
		self::field_input( 'Slug', 'slug', $track['slug'] );
		self::field_input( 'Cover art', 'cover_image', $track['cover_image'] );
		self::field_select( 'Artist', 'artist_id', $track['artist_id'], self::artist_options() );
		self::field_select( 'Album', 'album_id', $track['album_id'], self::album_options( true ) );
		self::field_input( 'ISRC', 'isrc', $track['isrc'] );
		self::field_input( 'Aliases', 'aliases', $track['aliases'] );
		self::field_input( 'Release date', 'release_date', $track['release_date'], 'date' );
		self::field_input( 'Genre', 'genre', $track['genre'] );
		self::field_input( 'Duration', 'duration', $track['duration'] );
		self::field_textarea( 'Description', 'description', $track['description'] );
		self::field_select( 'Gradient', 'gradient', $track['gradient'], self::gradient_options() );
		self::field_select( 'Active / Hidden', 'status', $track['status'], array( 'active' => 'Active', 'hidden' => 'Hidden', 'archived' => 'Archived' ) );
		echo '</div>';
		self::submit_row( array( array( 'label' => $id ? 'Update track' : 'Create track', 'class' => 'button-primary' ) ) );
		self::close_form();
		self::render_panel_end();
		self::render_panel_start( 'Track library', 'Current editable track records.' );
		self::render_table(
			array( 'Title', 'Artist', 'Album', 'ISRC', 'Aliases', 'Release date', 'Genre', 'Status', 'Actions' ),
			array_map(
				function ( $row ) {
					$actions = array(
						'<a href="' . esc_url( add_query_arg( 'track_id', $row['id'], self::current_url() ) ) . '">Edit</a>',
						'<a href="' . esc_url( self::action_url( 'track', $row['id'], 'archived' === $row['raw_status'] ? 'restore' : 'archive' ) ) . '">' . esc_html( 'archived' === $row['raw_status'] ? 'Restore' : 'Archive' ) . '</a>',
						'<a href="' . esc_url( self::action_url( 'track', $row['id'], 'delete' ) ) . '">Delete</a>',
					);
					return array( $row['title'], $row['artist'], $row['album'], $row['isrc'], $row['aliases'], $row['release_date'], $row['genre'], $row['status'], array( 'value' => implode( ' / ', $actions ), 'html' => true ) );
				},
				$tracks
			)
		);
		self::render_panel_end();
		echo '</section>';
	}

	private static function render_artists() {
		$artists = AMC_Admin_Data::artists();
		$id      = isset( $_GET['artist_id'] ) ? absint( wp_unslash( $_GET['artist_id'] ) ) : 0;
		$artist  = $id ? AMC_DB::get_row( 'artists', $id ) : null;
		$artist  = $artist ? $artist : array(
			'id'                => 0,
			'name'              => '',
			'slug'              => '',
			'image'             => '',
			'aliases'           => '',
			'bio'               => '',
			'country'           => '',
			'genre'             => '',
			'social_links'      => '',
			'monthly_listeners' => '',
			'chart_streak'      => '',
			'gradient'          => 'ocean',
			'status'            => 'active',
			'blurb'             => '',
		);
		echo '<section class="amc-admin-grid amc-admin-grid--split">';
		self::render_panel_start( 'Artist editor', 'Profile fields for bio, socials, country, genre, related tracks, albums, and visibility.' );
		self::open_form(
			'save_entity',
			array(
				'entity' => 'artist',
				'id'     => $artist['id'],
			)
		);
		echo '<div class="amc-admin-form">';
		self::field_input( 'Artist name', 'name', $artist['name'] );
		self::field_input( 'Slug', 'slug', $artist['slug'] );
		self::field_input( 'Image', 'image', $artist['image'] );
		self::field_input( 'Aliases', 'aliases', ! empty( $artist['aliases'] ) ? $artist['aliases'] : '' );
		self::field_textarea( 'Bio', 'bio', $artist['bio'] );
		self::field_input( 'Country', 'country', $artist['country'] );
		self::field_input( 'Genre', 'genre', $artist['genre'] );
		self::field_input( 'Social links', 'social_links', $artist['social_links'] );
		self::field_input( 'Monthly listeners', 'monthly_listeners', $artist['monthly_listeners'] );
		self::field_input( 'Chart streak', 'chart_streak', $artist['chart_streak'] );
		self::field_input( 'Short blurb', 'blurb', $artist['blurb'] );
		self::field_select( 'Gradient', 'gradient', $artist['gradient'], self::gradient_options() );
		self::field_select( 'Active / Hidden', 'status', $artist['status'], array( 'active' => 'Active', 'hidden' => 'Hidden', 'archived' => 'Archived' ) );
		echo '</div>';
		self::submit_row( array( array( 'label' => $id ? 'Update artist' : 'Create artist', 'class' => 'button-primary' ) ) );
		self::close_form();
		self::render_panel_end();
		self::render_panel_start( 'Artist library', 'Current artist records and metadata overview.' );
		self::render_table(
			array( 'Name', 'Country', 'Genre', 'Socials', 'Tracks', 'Albums', 'Status', 'Actions' ),
			array_map(
				function ( $row ) {
					$actions = array(
						'<a href="' . esc_url( add_query_arg( 'artist_id', $row['id'], self::current_url() ) ) . '">Edit</a>',
						'<a href="' . esc_url( self::action_url( 'artist', $row['id'], 'archived' === $row['raw_status'] ? 'restore' : 'archive' ) ) . '">' . esc_html( 'archived' === $row['raw_status'] ? 'Restore' : 'Archive' ) . '</a>',
						'<a href="' . esc_url( self::action_url( 'artist', $row['id'], 'delete' ) ) . '">Delete</a>',
					);
					return array( $row['name'], $row['country'], $row['genre'], $row['socials'], (string) $row['related_tracks'], (string) $row['related_albums'], $row['status'], array( 'value' => implode( ' / ', $actions ), 'html' => true ) );
				},
				$artists
			)
		);
		self::render_panel_end();
		echo '</section>';
	}

	private static function render_albums() {
		$albums = AMC_Admin_Data::albums();
		$id     = isset( $_GET['album_id'] ) ? absint( wp_unslash( $_GET['album_id'] ) ) : 0;
		$album  = $id ? AMC_DB::get_row( 'albums', $id ) : null;
		$album  = $album ? $album : array(
			'id'           => 0,
			'title'        => '',
			'slug'         => '',
			'artist_id'    => 0,
			'cover_image'  => '',
			'release_date' => '',
			'release_year' => '',
			'track_list'   => '',
			'genre'        => '',
			'label'        => '',
			'description'  => '',
			'gradient'     => 'ocean',
			'status'       => 'active',
		);
		echo '<section class="amc-admin-grid amc-admin-grid--split">';
		self::render_panel_start( 'Album editor', 'Manage album title, artist, track list, genre, label, cover art, and release state.' );
		self::open_form(
			'save_entity',
			array(
				'entity' => 'album',
				'id'     => $album['id'],
			)
		);
		echo '<div class="amc-admin-form">';
		self::field_input( 'Album title', 'title', $album['title'] );
		self::field_input( 'Slug', 'slug', $album['slug'] );
		self::field_select( 'Artist', 'artist_id', $album['artist_id'], self::artist_options() );
		self::field_input( 'Cover art', 'cover_image', $album['cover_image'] );
		self::field_input( 'Release date', 'release_date', $album['release_date'], 'date' );
		self::field_input( 'Release year', 'release_year', $album['release_year'] );
		self::field_textarea( 'Track list', 'track_list', $album['track_list'] );
		self::field_input( 'Genre', 'genre', $album['genre'] );
		self::field_input( 'Label', 'label', $album['label'] );
		self::field_textarea( 'Description', 'description', $album['description'] );
		self::field_select( 'Gradient', 'gradient', $album['gradient'], self::gradient_options() );
		self::field_select( 'Active / Hidden', 'status', $album['status'], array( 'active' => 'Active', 'hidden' => 'Hidden', 'archived' => 'Archived' ) );
		echo '</div>';
		self::submit_row( array( array( 'label' => $id ? 'Update album' : 'Create album', 'class' => 'button-primary' ) ) );
		self::close_form();
		self::render_panel_end();
		self::render_panel_start( 'Album library', 'Current album records with release context and visibility.' );
		self::render_table(
			array( 'Title', 'Artist', 'Release date', 'Tracks', 'Genre', 'Label', 'Status', 'Actions' ),
			array_map(
				function ( $row ) {
					$actions = array(
						'<a href="' . esc_url( add_query_arg( 'album_id', $row['id'], self::current_url() ) ) . '">Edit</a>',
						'<a href="' . esc_url( self::action_url( 'album', $row['id'], 'archived' === $row['raw_status'] ? 'restore' : 'archive' ) ) . '">' . esc_html( 'archived' === $row['raw_status'] ? 'Restore' : 'Archive' ) . '</a>',
						'<a href="' . esc_url( self::action_url( 'album', $row['id'], 'delete' ) ) . '">Delete</a>',
					);
					return array( $row['title'], $row['artist'], $row['release_date'], (string) $row['tracks'], $row['genre'], $row['label'], $row['status'], array( 'value' => implode( ' / ', $actions ), 'html' => true ) );
				},
				$albums
			)
		);
		self::render_panel_end();
		echo '</section>';
	}

	private static function render_uploads() {
		$uploads = AMC_Admin_Data::uploads();
		$guidance = AMC_Admin_Data::upload_guidance();
		$onboarding = AMC_Admin_Data::onboarding_status();
		$preview_upload_id = isset( $_GET['upload_id'] ) ? absint( wp_unslash( $_GET['upload_id'] ) ) : 0;
		$preview_rows      = $preview_upload_id ? AMC_Ingestion::preview_rows( $preview_upload_id, 8 ) : array();
		$invalid_groups    = $preview_upload_id ? AMC_Admin_Data::invalid_row_groups( $preview_upload_id ) : array();
		$current_upload    = $preview_upload_id ? AMC_DB::get_row( 'source_uploads', $preview_upload_id ) : null;
		self::render_workflow_strip( 'uploads' );
		echo '<section class="amc-admin-grid amc-admin-grid--split amc-admin-grid--bento">';
		self::render_panel_start( 'Source upload intake', 'Each upload now stores source platform, country, chart week, target chart, chart type, parser state, duplicate checks, dry-run metadata, and file diagnostics.' );
		self::open_form( 'upload_source', array(), array( 'enctype' => 'multipart/form-data' ) );
		echo '<div class="amc-admin-form">';
		self::field_select( 'Source platform', 'source_platform', 'spotify', self::source_platform_options() );
		self::field_input( 'Country', 'country', 'Global' );
		self::field_input( 'Chart week', 'chart_week', current_time( 'Y-m-d' ), 'date' );
		self::field_input( 'Chart date', 'chart_date', current_time( 'Y-m-d' ), 'date' );
		self::field_select( 'Target chart', 'target_chart_id', 0, self::chart_options() );
		self::field_select( 'Chart type', 'chart_type', 'track', array( 'track' => 'Track', 'artist' => 'Artist' ) );
		echo '<label><span>Upload file</span><input type="file" name="source_file" accept=".csv,.tsv,.txt,.xlsx,.xls"></label>';
		self::field_checkbox( 'Validation-only dry run', 'dry_run', false );
		self::field_checkbox( 'Allow duplicate override', 'allow_duplicate', false );
		echo '<label><span>Uploader</span><input type="text" value="' . esc_attr( wp_get_current_user()->display_name ? wp_get_current_user()->display_name : 'Current user' ) . '" readonly></label>';
		echo '</div>';
		self::submit_row( array( array( 'label' => 'Upload source sheet', 'class' => 'button-primary' ) ) );
		self::close_form();
		echo '<div class="amc-admin-definition-list">';
		printf( '<div><strong>First-run selection tips</strong><span>Always choose source platform, country, target chart, chart type, and week/date before upload. The plugin does not auto-detect chart ownership.</span></div>' );
		foreach ( $guidance as $item ) {
			printf( '<div><strong>%1$s</strong><span>%2$s</span></div>', esc_html( $item['title'] ), esc_html( $item['copy'] ) );
		}
		printf( '<div><strong>Unsupported or corrupted files</strong><span>Unsupported formats and broken spreadsheets fail clearly with parser diagnostics. Use CSV, TSV, TXT, XLSX, or XLS exports only.</span></div>' );
		echo '</div>';
		if ( ! $onboarding['first_upload'] ) {
			echo '<div class="amc-admin-button-row">';
			echo '<a class="button button-secondary" href="' . esc_url( AMC_Admin_Data::custom_dashboard_url( 'charts' ) ) . '">Create chart first</a>';
			echo '<a class="button button-secondary" href="' . esc_url( AMC_Admin_Data::custom_dashboard_url( 'scoring' ) ) . '">Configure scoring rules</a>';
			echo '</div>';
		}
		self::render_panel_end();
		self::render_panel_start( 'Recent source uploads', 'Operational view of incoming chart source sheets.' );
		self::render_table(
			array( 'Source', 'Country', 'Chart', 'Type', 'Upload date', 'Chart week', 'Status', 'Rows', 'Preview', 'Uploader', 'Actions' ),
			array_map(
				function ( $row ) {
					$badges = array();
					if ( ! empty( $row['is_dry_run'] ) ) {
						$badges[] = 'Dry run';
					}
					if ( ! empty( $row['is_duplicate'] ) ) {
						$badges[] = 'Duplicate';
					}
					$actions = array(
						'<a href="' . esc_url( add_query_arg( 'upload_id', $row['id'], self::current_url() ) ) . '">Preview</a>',
						'<a href="' . esc_url( self::action_url( 'upload', $row['id'], 'parse' ) ) . '">Reparse</a>',
						'<a href="' . esc_url( self::action_url( 'upload', $row['id'], 'match' ) ) . '">Run matching</a>',
						'<a href="' . esc_url( self::action_url( 'upload', $row['id'], 'generate' ) ) . '">Generate draft</a>',
						'<a href="' . esc_url( self::action_url( 'upload', $row['id'], 'commit' ) ) . '">Commit dry run</a>',
						'<a href="' . esc_url( self::action_url( 'export', $row['id'], 'invalid_rows' ) ) . '">Export invalid rows</a>',
						'<a href="' . esc_url( self::action_url( 'upload', $row['id'], 'delete' ) ) . '">Delete</a>',
					);
					if ( ! empty( $row['file_url'] ) ) {
						$actions[] = '<a href="' . esc_url( $row['file_url'] ) . '" target="_blank" rel="noreferrer">File</a>';
					}
					return array( $row['source'], $row['country'], $row['chart'], $row['chart_type'], $row['upload_date'], $row['week'], $row['status'] . ( ! empty( $badges ) ? ' / ' . implode( ' / ', $badges ) : '' ), (string) $row['row_count'], $row['preview'], $row['uploader'], array( 'value' => implode( ' / ', $actions ), 'html' => true ) );
				},
				$uploads
			)
		);
		if ( $preview_upload_id ) {
			if ( $current_upload && ! empty( $current_upload['diagnostic_summary'] ) ) {
				$summary = json_decode( $current_upload['diagnostic_summary'], true );
				if ( is_array( $summary ) ) {
					echo '<div class="amc-admin-stat-stack">';
					foreach ( $summary as $key => $value ) {
						printf( '<div><strong>%1$s</strong><span>%2$s</span></div>', esc_html( (string) $value ), esc_html( ucwords( str_replace( '_', ' ', $key ) ) ) );
					}
					echo '</div>';
				}
			}
			if ( $preview_rows ) {
				echo '<div class="amc-admin-definition-list">';
				foreach ( $preview_rows as $preview_row ) {
					printf(
						'<div><strong>%1$s</strong><span>%2$s | %3$s | rank %4$s | %5$s%6$s</span></div>',
						esc_html( ! empty( $preview_row['track_title'] ) ? $preview_row['track_title'] : ( ! empty( $preview_row['artist_name'] ) ? $preview_row['artist_name'] : 'Untitled row' ) ),
						esc_html( ! empty( $preview_row['artist_names'] ) ? $preview_row['artist_names'] : $preview_row['artist_name'] ),
						esc_html( $preview_row['album_name'] ),
						esc_html( (string) $preview_row['rank'] ),
						esc_html( ucwords( str_replace( '_', ' ', $preview_row['matching_status'] ) ) ),
						! empty( $preview_row['validation_message'] ) ? ' | ' . esc_html( $preview_row['validation_message'] ) : ''
					);
				}
				echo '</div>';
				if ( ! empty( $invalid_groups ) ) {
					echo '<div class="amc-admin-definition-list">';
					foreach ( $invalid_groups as $reason => $count ) {
						printf( '<div><strong>%1$s</strong><span>%2$s rejected rows</span></div>', esc_html( $reason ), esc_html( (string) $count ) );
					}
					echo '</div>';
				}
				echo '<div class="amc-admin-button-row">';
				echo '<a class="button button-secondary" href="' . esc_url( self::action_url( 'export', $preview_upload_id, 'invalid_rows' ) ) . '">Download invalid-row debug CSV</a>';
				echo '<a class="button button-secondary" href="' . esc_url( self::action_url( 'export', $preview_upload_id, 'logs' ) ) . '">Export this upload logs</a>';
				echo '</div>';
			} else {
				echo '<p>No parsed preview rows are available for this upload yet.</p>';
			}
		} elseif ( empty( $uploads ) ) {
			echo '<p>No source uploads have been processed yet. Start with a real CSV, TSV, TXT, XLSX, or XLS file and confirm the target chart metadata before uploading.</p>';
		}
		self::render_panel_end();
		echo '</section>';
	}

	private static function render_cleaning() {
		$candidates = AMC_Admin_Data::matching_candidates();
		$override_id = isset( $_GET['queue_id'] ) ? absint( wp_unslash( $_GET['queue_id'] ) ) : 0;
		$override    = $override_id ? AMC_DB::get_row( 'matching_queue', $override_id ) : null;
		self::render_workflow_strip( 'cleaning' );
		echo '<section class="amc-admin-grid amc-admin-grid--split amc-admin-grid--bento">';
		self::render_panel_start( 'Matching and Cleaning queue', 'Each row below is a parsed source row with a real pipeline state: matched automatically, ready to create, possible duplicate, or unmatched review.' );
		if ( empty( $candidates ) ) {
			echo '<p>No review-needed matches are waiting right now. When ambiguous rows arrive from uploads, they will appear here for manual review before generation.</p>';
		}
		self::render_table(
			array( 'Candidate', 'Row type', 'Type', 'Confidence', 'Why it is here', 'Creation behavior', 'Next step', 'Actions' ),
			array_map(
				function ( $row ) {
					$actions = array(
						'<a href="' . esc_url( self::action_url( 'matching', $row['id'], 'approve' ) ) . '">Approve match</a>',
						'<a href="' . esc_url( self::action_url( 'matching', $row['id'], 'reject' ) ) . '">Reject row</a>',
						'<a href="' . esc_url( add_query_arg( 'queue_id', $row['id'], self::current_url() ) ) . '">Override target</a>',
					);
					return array(
						$row['candidate'],
						array( 'value' => self::badge_html( $row['row_type'] ), 'html' => true ),
						$row['type'],
						$row['confidence'],
						$row['basis'],
						$row['create_mode'],
						$row['action_hint'],
						array( 'value' => implode( ' / ', $actions ), 'html' => true ),
					);
				},
				$candidates
			)
		);
		echo '<div class="amc-admin-button-row"><a class="button button-secondary" href="' . esc_url( self::action_url( 'export', 0, 'matching_queue' ) ) . '">Export matching review queue</a></div>';
		self::render_panel_end();
		self::render_panel_start( 'Decision guide', 'Approve confirms the suggested match, Reject removes the row from generation, and Override manually links the row to a specific Track, Artist, or Album.' );
		echo '<div class="amc-admin-definition-list">';
		echo '<div><strong>Matched automatically</strong><span>The system linked the row to an existing entity with exact or high-confidence evidence.</span></div>';
		echo '<div><strong>Ready to create</strong><span>The row was valid, unmatched, and safe, so the system created a new entity automatically.</span></div>';
		echo '<div><strong>Possible duplicate</strong><span>A likely entity exists, but the confidence basis was not strong enough for an automatic decision.</span></div>';
		echo '<div><strong>Unmatched</strong><span>No safe match or auto-create path was available, so the operator must review or override.</span></div>';
		echo '</div>';
		self::render_panel_end();
		self::render_panel_start( 'Manual override tools', 'Manual override decisions now persist to the matching queue and source row records, and they are recorded as manual overrides in the pipeline state.' );
		self::open_form(
			'row_action',
			array(
				'entity' => 'matching',
				'id'     => $override ? (int) $override['id'] : 0,
				'task'   => 'override',
			)
		);
		echo '<div class="amc-admin-form">';
		self::field_input( 'Queue item ID', 'id_display', $override ? (string) $override['id'] : '', 'text' );
		self::field_select( 'Entity type', 'override_entity_type', $override ? $override['entity_type'] : 'track', array( 'track' => 'Track', 'artist' => 'Artist', 'album' => 'Album' ) );
		self::field_select( 'Target record', 'override_entity_id', $override ? (int) $override['candidate_entity_id'] : 0, self::merged_entity_options() );
		self::field_textarea( 'Override reason', 'notes', $override ? $override['notes'] : 'Regional title variant / metadata mismatch' );
		echo '</div>';
		self::submit_row( array( array( 'label' => 'Save override', 'class' => 'button-primary' ) ) );
		self::close_form();
		self::render_panel_end();
		echo '</section>';
	}

	private static function render_scoring() {
		$data = AMC_Admin_Data::scoring();
		$methodology_keys = array(
			'Minimum Release Age'       => 'minimum_release_age',
			'Minimum Source Coverage'   => 'minimum_source_coverage',
			'Manual Editorial Override' => 'manual_editorial_override',
			'Catalog Reentry Threshold' => 'catalog_reentry_threshold',
			'Growth Bonus Multiplier'   => 'growth_bonus_multiplier',
			'Fallback Metric Behavior'  => 'fallback_metric_behavior',
			'Minimum Source Count'      => 'minimum_source_count',
			'Eligibility Mode'          => 'eligibility_mode',
		);
		echo '<section class="amc-admin-grid amc-admin-grid--split">';
		self::render_panel_start( 'Source weights', 'Scoring weights now persist to the scoring rules table and prepare the next generation layer.' );
		self::open_form( 'save_scoring' );
		echo '<div class="amc-admin-form">';
		foreach ( $data['weights'] as $row ) {
			self::field_input( $row['source'], $row['key'], $row['value'], 'number' );
		}
		echo '</div>';
		self::submit_row( array( array( 'label' => 'Save methodology draft', 'class' => 'button-primary' ) ) );
		self::close_form();
		self::render_table(
			array( 'Source', 'Weight' ),
			array_map(
				function ( $row ) {
					return array( $row['source'], $row['weight'] );
				},
				$data['weights']
			)
		);
		self::render_panel_end();
		self::render_panel_start( 'Methodology rules', 'Methodology values are now real persisted records ready for chart generation in the next phase.' );
		self::open_form( 'save_scoring' );
		echo '<div class="amc-admin-form">';
		foreach ( $methodology_keys as $label => $key ) {
			$value = isset( $data['methodology'][ $label ] ) ? $data['methodology'][ $label ] : '';
			self::field_textarea( $label, $key, $value );
		}
		echo '</div>';
		self::submit_row( array( array( 'label' => 'Save methodology rules', 'class' => 'button-primary' ) ) );
		self::close_form();
		echo '<div class="amc-admin-definition-list">';
		if ( $data['methodology'] ) {
			foreach ( $data['methodology'] as $label => $value ) {
				printf( '<div><strong>%1$s</strong><span>%2$s</span></div>', esc_html( $label ), esc_html( $value ) );
			}
		} else {
			echo '<div><strong>No scoring rules saved yet</strong><span>Enter real source weights and methodology rules to begin using the scoring layer.</span></div>';
		}
		echo '</div>';
		self::render_panel_end();
		echo '</section>';
	}

	private static function render_publishing() {
		$data = AMC_Admin_Data::publishing_preview();
		$weeks = AMC_DB::get_chart_weeks();
		$week  = ! empty( $weeks[0] ) ? $weeks[0] : null;
		$review = AMC_Admin_Data::first_publish_summary( $week ? (int) $week['id'] : 0 );
		self::render_workflow_strip( 'publishing' );
		echo '<section class="amc-admin-grid amc-admin-grid--split amc-admin-grid--bento">';
		self::render_panel_start( 'Publishing preview', 'Preview generated chart weeks, compare against previous periods, and control publication state.' );
		printf( '<div class="amc-admin-publish-week"><strong>%s</strong><span>Pipeline-backed preview</span></div>', esc_html( $data['current_week'] ) );
		echo '<div class="amc-admin-stat-stack">';
		foreach ( $data['comparison'] as $item ) {
			printf( '<div><strong>%1$s</strong><span>%2$s</span></div>', esc_html( $item['value'] ), esc_html( $item['metric'] ) );
		}
		echo '</div>';
		self::render_panel_end();
		self::render_panel_start( 'Publishing actions', 'Publishing, unpublishing, and featuring chart weeks now update the real chart_weeks table.' );
		if ( $week ) {
			echo '<div class="amc-admin-button-row">';
			echo '<a class="button button-secondary" href="' . esc_url( self::action_url( 'week', (int) $week['id'], 'generate' ) ) . '">Generate draft</a>';
			echo '<a class="button button-primary" href="' . esc_url( self::confirm_action_url( 'week', (int) $week['id'], 'published' === $week['status'] ? 'unpublish' : 'publish', 'Are you sure you want to change the live state of this chart week?' ) ) . '">' . esc_html( 'published' === $week['status'] ? 'Unpublish week' : 'Publish week' ) . '</a>';
			echo '<a class="button button-secondary" href="' . esc_url( self::confirm_action_url( 'week', (int) $week['id'], 'republish', 'Republish this chart week and refresh the live version?' ) ) . '">Republish week</a>';
			echo '<a class="button button-secondary" href="' . esc_url( self::action_url( 'week', (int) $week['id'], 'archive' ) ) . '">Archive week</a>';
			echo '<a class="button button-secondary" href="' . esc_url( self::confirm_action_url( 'week', (int) $week['id'], 'rollback', 'Rollback to the previous published week for this chart and country?' ) ) . '">Rollback to previous live week</a>';
			echo '<a class="button button-secondary" href="' . esc_url( self::action_url( 'week', (int) $week['id'], ! empty( $week['is_featured'] ) ? 'unfeature' : 'feature' ) ) . '">' . esc_html( ! empty( $week['is_featured'] ) ? 'Unfeature week' : 'Feature on homepage' ) . '</a>';
			echo '</div>';
		} else {
			echo '<p>No chart week is available for publishing yet.</p>';
		}

		if ( ! empty( $data['dropped_out'] ) ) {
			echo '<div class="amc-admin-definition-list">';
			foreach ( $data['dropped_out'] as $item ) {
				printf(
					'<div><strong>%1$s</strong><span>Dropped out from previous week at rank %2$s</span></div>',
					esc_html( ! empty( $item['name'] ) ? $item['name'] : 'Unknown item' ),
					esc_html( (string) ( ! empty( $item['previous_rank'] ) ? $item['previous_rank'] : '-' ) )
				);
			}
			echo '</div>';
			echo '<div class="amc-admin-button-row"><a class="button button-secondary" href="' . esc_url( self::action_url( 'export', (int) $week['id'], 'dropped_out' ) ) . '">Export dropped-out summary</a></div>';
		}
		self::render_panel_end();
		self::render_panel_start( 'First publish review', 'Use this final preflight summary before taking a chart week live for the first time.' );
		if ( ! empty( $review['available'] ) ) {
			echo '<div class="amc-admin-definition-list">';
			foreach ( $review['summary'] as $label => $value ) {
				printf( '<div><strong>%1$s</strong><span>%2$s</span></div>', esc_html( $label ), esc_html( (string) $value ) );
			}
			echo '</div>';
			echo '<div class="amc-admin-definition-list">';
			foreach ( $review['safety'] as $check ) {
				printf(
					'<div><strong>%1$s</strong><span>%2$s %3$s</span></div>',
					esc_html( $check['label'] ),
					esc_html( ! empty( $check['state'] ) ? 'Ready:' : 'Attention:' ),
					esc_html( $check['copy'] )
				);
			}
			echo '</div>';
		} else {
			echo '<p>No generated chart week is ready for final publish review yet. Complete uploads, matching, and generation first.</p>';
		}
		self::render_panel_end();
		echo '</section>';
	}

	private static function render_archives() {
		$archives = AMC_Admin_Data::archives();
		self::render_panel_start( 'Archive management', 'Past chart weeks can be reopened, restored, or re-frozen from this control panel UI.' );
		self::render_table(
			array( 'Week', 'Chart', 'Status', 'Notes', 'Actions' ),
			array_map(
				function ( $row ) {
					$actions = array(
						'<a href="' . esc_url( add_query_arg( 'week_id', $row['id'], AMC_Admin_Data::custom_dashboard_url( 'weekly-entries' ) ) ) . '">Open week</a>',
						'<a href="' . esc_url( self::action_url( 'week', $row['id'], 'Archived' === $row['status'] ? 'restore' : 'archive' ) ) . '">' . esc_html( 'Archived' === $row['status'] ? 'Restore' : 'Archive' ) . '</a>',
					);
					return array( $row['week'], $row['chart'], $row['status'], $row['notes'], array( 'value' => implode( ' / ', $actions ), 'html' => true ) );
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
		self::render_panel_start( 'Permission profile', 'Current operational permission model for the main chart workflow.' );
		echo '<div class="amc-admin-definition-list">';
		echo '<div><strong>Upload and cleaning access</strong><span>Controlled by the `amc_manage_weeks` capability.</span></div>';
		echo '<div><strong>Chart and library editing</strong><span>Controlled by `amc_manage_charts` and `amc_manage_library`.</span></div>';
		echo '<div><strong>Publishing and archives</strong><span>Controlled by `amc_publish_charts`.</span></div>';
		echo '<div><strong>Settings and governance</strong><span>Controlled by `amc_manage_settings`.</span></div>';
		echo '</div>';
		self::render_panel_end();
		echo '</section>';
	}

	private static function render_settings() {
		$settings = AMC_DB::get_settings();
		echo '<section class="amc-admin-grid amc-admin-grid--split">';
		self::render_panel_start( 'Platform settings', 'Brand, SEO, homepage, methodology, and localization controls now persist to the plugin settings table.' );
		self::open_form( 'save_settings' );
		echo '<div class="amc-admin-form">';
		self::field_input( 'Platform name', 'platform_name', $settings['platform_name'] );
		self::field_input( 'Logo', 'logo', $settings['logo'] );
		self::field_input( 'SEO defaults', 'seo_defaults', $settings['seo_defaults'] );
		self::field_input( 'Social image', 'social_image', $settings['social_image'] );
		self::field_select( 'Homepage chart', 'homepage_chart', $settings['homepage_chart'], self::chart_slug_options() );
		self::field_textarea( 'Methodology text', 'methodology_text', $settings['methodology_text'] );
		self::field_input( 'Language', 'language', $settings['language'] );
		self::field_input( 'Date format', 'date_format', $settings['date_format'] );
		echo '</div>';
		self::submit_row( array( array( 'label' => 'Save settings', 'class' => 'button-primary' ) ) );
		self::close_form();
		self::render_panel_end();
		self::render_panel_start( 'Settings summary', 'Current saved settings displayed for the production configuration.' );
		echo '<div class="amc-admin-definition-list">';
		printf( '<div><strong>Platform name</strong><span>%s</span></div>', esc_html( $settings['platform_name'] ) );
		printf( '<div><strong>Homepage chart</strong><span>%s</span></div>', esc_html( $settings['homepage_chart'] ? $settings['homepage_chart'] : 'Not set' ) );
		printf( '<div><strong>Language</strong><span>%s</span></div>', esc_html( $settings['language'] ? $settings['language'] : 'Not set' ) );
		printf( '<div><strong>Date format</strong><span>%s</span></div>', esc_html( $settings['date_format'] ? $settings['date_format'] : 'Not set' ) );
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

	/**
	 * Shared workflow strip.
	 *
	 * @param string $current Current step.
	 * @return void
	 */
	private static function render_workflow_strip( $current = '' ) {
		$steps = array(
			'uploads'        => 'Upload file',
			'parse'          => 'Parse',
			'validate'       => 'Validate',
			'cleaning'       => 'Match or auto-create',
			'weekly-entries' => 'Generate chart',
			'review'         => 'Review',
			'publishing'     => 'Publish',
		);

		echo '<section class="amc-admin-workflow"><div class="amc-admin-workflow__grid">';
		foreach ( $steps as $key => $label ) {
			$class = $current === $key ? ' is-active' : '';
			printf( '<div class="amc-admin-workflow__step%1$s"><strong>%2$s</strong><span>%3$s</span></div>', esc_attr( $class ), esc_html( $label ), esc_html( strtoupper( str_replace( '-', ' ', $key ) ) ) );
		}
		echo '</div></section>';
	}

	/**
	 * Render state badge.
	 *
	 * @param string $label Label.
	 * @return string
	 */
	private static function badge_html( $label ) {
		$slug = sanitize_title( $label );
		return '<span class="amc-admin-badge amc-admin-badge--' . esc_attr( $slug ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Shared onboarding/setup panel.
	 *
	 * @param bool   $wp_admin Whether rendering in wp-admin.
	 * @param string $title Section title.
	 * @param string $copy Section copy.
	 * @return void
	 */
	private static function render_setup_panel( $wp_admin, $title, $copy ) {
		$status = AMC_Admin_Data::onboarding_status();

		self::render_panel_start( $title, $copy );
		echo '<div class="amc-admin-stat-stack">';
		printf( '<div><strong>%1$s / %2$s</strong><span>Readiness steps complete</span></div>', esc_html( (string) $status['completed_steps'] ), esc_html( (string) $status['total_steps'] ) );
		printf( '<div><strong>%1$s</strong><span>Active charts created</span></div>', esc_html( (string) $status['charts_count'] ) );
		printf( '<div><strong>%1$s</strong><span>Generated weeks</span></div>', esc_html( (string) $status['generated_weeks'] ) );
		printf( '<div><strong>%1$s</strong><span>Published weeks</span></div>', esc_html( (string) $status['published_weeks'] ) );
		echo '</div>';
		echo '<div class="amc-admin-definition-list">';
		foreach ( $status['steps'] as $step ) {
			printf(
				'<div><strong>%1$s</strong><span>%2$s %3$s <a href="%4$s">%5$s</a></span></div>',
				esc_html( $step['label'] ),
				esc_html( ! empty( $step['complete'] ) ? 'Done.' : 'Next.' ),
				esc_html( $step['description'] ),
				esc_url( $step['url'] ),
				esc_html( $step['action'] )
			);
		}
		echo '</div>';
		if ( $status['is_ready'] ) {
			echo '<p>Kontentainment Charts is operationally ready: charts exist, scoring has been configured, uploads have been processed, and at least one live week is already published.</p>';
		} elseif ( $wp_admin ) {
			echo '<div class="amc-admin-button-row"><a class="button button-primary" href="' . esc_url( AMC_Admin_Data::custom_dashboard_url() ) . '">Continue setup in /charts-dashboard</a></div>';
		} else {
			echo '<div class="amc-admin-button-row">';
			echo '<a class="button button-primary" href="' . esc_url( AMC_Admin_Data::custom_dashboard_url( 'charts' ) ) . '">Start with charts</a>';
			echo '<a class="button button-secondary" href="' . esc_url( AMC_Admin_Data::custom_dashboard_url( 'uploads' ) ) . '">Go to uploads</a>';
			echo '<a class="button button-secondary" href="' . esc_url( AMC_Admin_Data::custom_dashboard_url( 'publishing' ) ) . '">Open publishing</a>';
			echo '</div>';
		}
		self::render_panel_end();
	}

	private static function render_table( $headers, $rows ) {
		echo '<div class="amc-admin-table-wrap"><table class="widefat striped amc-admin-table"><thead><tr>';
		foreach ( $headers as $header ) {
			printf( '<th>%s</th>', esc_html( $header ) );
		}
		echo '</tr></thead><tbody>';
		if ( empty( $rows ) ) {
			echo '<tr><td colspan="' . esc_attr( count( $headers ) ) . '">No records are available yet.</td></tr>';
		}
		foreach ( $rows as $row ) {
			echo '<tr>';
			foreach ( $row as $cell ) {
				if ( is_array( $cell ) && ! empty( $cell['html'] ) ) {
					printf( '<td>%s</td>', $cell['value'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				} else {
					printf( '<td>%s</td>', esc_html( is_array( $cell ) && isset( $cell['value'] ) ? $cell['value'] : $cell ) );
				}
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

	/**
	 * Open a real admin-post form.
	 *
	 * @param string $action Action.
	 * @param array  $hidden Hidden values.
	 * @return void
	 */
	private static function open_form( $action, $hidden = array(), $args = array() ) {
		$enctype = ! empty( $args['enctype'] ) ? ' enctype="' . esc_attr( $args['enctype'] ) . '"' : '';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . $enctype . '>';
		wp_nonce_field( 'amc_' . $action );
		echo '<input type="hidden" name="action" value="' . esc_attr( 'amc_' . $action ) . '">';
		echo '<input type="hidden" name="redirect_to" value="' . esc_attr( self::current_url() ) . '">';

		foreach ( $hidden as $name => $value ) {
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
		}
	}

	/**
	 * Close form.
	 *
	 * @return void
	 */
	private static function close_form() {
		echo '</form>';
	}

	/**
	 * Current request url.
	 *
	 * @return string
	 */
	private static function current_url() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		return home_url( $request_uri );
	}

	/**
	 * Render text-like field.
	 *
	 * @param string $label Label.
	 * @param string $name Name.
	 * @param string $value Value.
	 * @param string $type Input type.
	 * @return void
	 */
	private static function field_input( $label, $name, $value = '', $type = 'text' ) {
		echo '<label><span>' . esc_html( $label ) . '</span><input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"></label>';
	}

	/**
	 * Render textarea field.
	 *
	 * @param string $label Label.
	 * @param string $name Name.
	 * @param string $value Value.
	 * @return void
	 */
	private static function field_textarea( $label, $name, $value = '' ) {
		echo '<label><span>' . esc_html( $label ) . '</span><textarea name="' . esc_attr( $name ) . '" rows="4">' . esc_textarea( $value ) . '</textarea></label>';
	}

	/**
	 * Render select field.
	 *
	 * @param string $label Label.
	 * @param string $name Name.
	 * @param string $value Current value.
	 * @param array  $options Options.
	 * @return void
	 */
	private static function field_select( $label, $name, $value, $options ) {
		echo '<label><span>' . esc_html( $label ) . '</span><select name="' . esc_attr( $name ) . '">';
		foreach ( $options as $option_value => $option_label ) {
			echo '<option value="' . esc_attr( $option_value ) . '"' . selected( (string) $value, (string) $option_value, false ) . '>' . esc_html( $option_label ) . '</option>';
		}
		echo '</select></label>';
	}

	/**
	 * Render checkbox field.
	 *
	 * @param string $label Label.
	 * @param string $name Name.
	 * @param bool   $checked Checked state.
	 * @return void
	 */
	private static function field_checkbox( $label, $name, $checked ) {
		echo '<label><span>' . esc_html( $label ) . '</span><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . checked( $checked, true, false ) . '></label>';
	}

	/**
	 * Render submit row.
	 *
	 * @param array $buttons Button definitions.
	 * @return void
	 */
	private static function submit_row( $buttons ) {
		echo '<div class="amc-admin-button-row">';
		foreach ( $buttons as $button ) {
			$type  = ! empty( $button['type'] ) ? $button['type'] : 'submit';
			$class = ! empty( $button['class'] ) ? $button['class'] : 'button-secondary';
			$name  = ! empty( $button['name'] ) ? $button['name'] : '';
			$value = ! empty( $button['value'] ) ? $button['value'] : '';
			echo '<button type="' . esc_attr( $type ) . '" class="button ' . esc_attr( $class ) . '"' . ( $name ? ' name="' . esc_attr( $name ) . '"' : '' ) . ( $value ? ' value="' . esc_attr( $value ) . '"' : '' ) . '>' . esc_html( $button['label'] ) . '</button>';
		}
		echo '</div>';
	}

	/**
	 * Build action url.
	 *
	 * @param string $entity Entity.
	 * @param int    $id Record id.
	 * @param string $task Task.
	 * @return string
	 */
	private static function action_url( $entity, $id, $task ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'      => 'amc_row_action',
					'entity'      => $entity,
					'id'          => is_numeric( $id ) ? absint( $id ) : sanitize_text_field( (string) $id ),
					'task'        => $task,
					'redirect_to' => self::current_url(),
				),
				admin_url( 'admin-post.php' )
			),
			'amc_row_action'
		);
	}

	/**
	 * Build a confirm-style action url.
	 *
	 * @param string $entity Entity.
	 * @param int    $id Id.
	 * @param string $task Task.
	 * @param string $message Message.
	 * @return string
	 */
	private static function confirm_action_url( $entity, $id, $task, $message ) {
		return add_query_arg( 'amc_confirm', rawurlencode( $message ), self::action_url( $entity, $id, $task ) );
	}

	/**
	 * Build bulk action URL.
	 *
	 * @param string $entity Entity.
	 * @param string $task Task.
	 * @return string
	 */
	private static function bulk_action_url( $entity, $task ) {
		$query = array(
			'action'      => 'amc_bulk_action',
			'entity'      => $entity,
			'task'        => $task,
			'redirect_to' => self::current_url(),
		);

		foreach ( array( 'notice_severity', 'notice_status', 'notice_date' ) as $key ) {
			if ( isset( $_GET[ $key ] ) ) {
				$query[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
			}
		}

		return wp_nonce_url( add_query_arg( $query, admin_url( 'admin-post.php' ) ), 'amc_bulk_action' );
	}

	/**
	 * Chart options.
	 *
	 * @return array
	 */
	private static function chart_options() {
		$options = array( 0 => 'Select chart' );

		foreach ( AMC_DB::get_rows( 'charts', array( 'order_by' => 'display_order ASC, id ASC' ) ) as $chart ) {
			$options[ $chart['id'] ] = $chart['name'];
		}

		return $options;
	}

	/**
	 * Chart slug options.
	 *
	 * @return array
	 */
	private static function chart_slug_options() {
		$options = array();

		foreach ( AMC_DB::get_rows( 'charts', array( 'order_by' => 'display_order ASC, id ASC' ) ) as $chart ) {
			$options[ $chart['slug'] ] = $chart['name'];
		}

		return $options;
	}

	/**
	 * Artist options.
	 *
	 * @return array
	 */
	private static function artist_options() {
		$options = array( 0 => 'Select artist' );

		foreach ( AMC_DB::get_rows( 'artists', array( 'order_by' => 'name ASC' ) ) as $artist ) {
			$options[ $artist['id'] ] = $artist['name'];
		}

		return $options;
	}

	/**
	 * Album options.
	 *
	 * @param bool $include_empty Include empty option.
	 * @return array
	 */
	private static function album_options( $include_empty = false ) {
		$options = $include_empty ? array( 0 => 'Standalone / None' ) : array();

		foreach ( AMC_DB::get_rows( 'albums', array( 'order_by' => 'title ASC' ) ) as $album ) {
			$options[ $album['id'] ] = $album['title'];
		}

		return $options;
	}

	/**
	 * Entity options by type.
	 *
	 * @param string $type Entity type.
	 * @return array
	 */
	private static function entity_options_for_type( $type ) {
		$options = array( 0 => 'Select item' );
		$table   = 'tracks';
		$field   = 'title';

		if ( 'artist' === $type ) {
			$table = 'artists';
			$field = 'name';
		} elseif ( 'album' === $type ) {
			$table = 'albums';
			$field = 'title';
		}

		foreach ( AMC_DB::get_rows( $table, array( 'order_by' => $field . ' ASC' ) ) as $row ) {
			$options[ $row['id'] ] = $row[ $field ];
		}

		return $options;
	}

	/**
	 * Gradient options.
	 *
	 * @return array
	 */
	private static function gradient_options() {
		return array(
			'sunset'  => 'Sunset',
			'ocean'   => 'Ocean',
			'ruby'    => 'Ruby',
			'plum'    => 'Plum',
			'gold'    => 'Gold',
			'emerald' => 'Emerald',
			'ice'     => 'Ice',
			'rose'    => 'Rose',
		);
	}

	/**
	 * Source platform options.
	 *
	 * @return array
	 */
	private static function source_platform_options() {
		return array(
			'spotify'     => 'Spotify',
			'youtube'     => 'YouTube',
			'shazam'      => 'Shazam',
			'apple-music' => 'Apple Music',
			'anghami'     => 'Anghami',
			'tiktok'      => 'TikTok',
		);
	}

	/**
	 * Combined entity options for override UI.
	 *
	 * @return array
	 */
	private static function merged_entity_options() {
		$options = array( 0 => 'Select target record' );

		foreach ( AMC_DB::get_rows( 'tracks', array( 'order_by' => 'title ASC' ) ) as $row ) {
			$options[ $row['id'] ] = '[Track] ' . $row['title'];
		}

		foreach ( AMC_DB::get_rows( 'artists', array( 'order_by' => 'name ASC' ) ) as $row ) {
			$options[ $row['id'] ] = '[Artist] ' . $row['name'];
		}

		foreach ( AMC_DB::get_rows( 'albums', array( 'order_by' => 'title ASC' ) ) as $row ) {
			$options[ $row['id'] ] = '[Album] ' . $row['title'];
		}

		return $options;
	}

	/**
	 * Handle entity save requests.
	 *
	 * @return void
	 */
	public static function handle_save_entity() {
		if ( ! current_user_can( 'amc_view_dashboard' ) ) {
			wp_die( esc_html__( 'You do not have permission to save Kontentainment Charts data.', 'kontentainment-charts' ) );
		}

		check_admin_referer( 'amc_save_entity' );

		$entity   = isset( $_POST['entity'] ) ? sanitize_key( wp_unslash( $_POST['entity'] ) ) : '';
		$redirect = self::posted_redirect();
		$id       = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;

		switch ( $entity ) {
			case 'chart':
				self::assert_cap( 'amc_manage_charts' );
				$id = AMC_DB::save_row(
					'charts',
					array(
						'name'             => sanitize_text_field( wp_unslash( $_POST['name'] ) ),
						'slug'             => sanitize_title( wp_unslash( $_POST['slug'] ) ),
						'description'      => sanitize_textarea_field( wp_unslash( $_POST['description'] ) ),
						'type'             => sanitize_key( wp_unslash( $_POST['type'] ) ),
						'cover_image'      => esc_url_raw( wp_unslash( $_POST['cover_image'] ) ),
						'display_order'    => absint( wp_unslash( $_POST['display_order'] ) ),
						'status'           => ! empty( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'hidden',
						'is_featured_home' => empty( $_POST['is_featured_home'] ) ? 0 : 1,
						'archive_enabled'  => empty( $_POST['archive_enabled'] ) ? 0 : 1,
						'accent'           => sanitize_key( wp_unslash( $_POST['accent'] ) ),
						'kicker'           => sanitize_text_field( wp_unslash( $_POST['kicker'] ) ),
					),
					$id
				);
				if ( ! $id ) {
					self::redirect_notice( $redirect, 'error', 'Chart could not be saved. Check slug uniqueness.' );
				}
				self::redirect_notice( add_query_arg( 'chart_id', $id, $redirect ), 'success', 'Chart saved successfully.' );
				break;

			case 'track':
				self::assert_cap( 'amc_manage_library' );
				$id = AMC_DB::save_row(
					'tracks',
					array(
						'title'        => sanitize_text_field( wp_unslash( $_POST['title'] ) ),
						'slug'         => sanitize_title( wp_unslash( $_POST['slug'] ) ),
						'cover_image'  => esc_url_raw( wp_unslash( $_POST['cover_image'] ) ),
						'artist_id'    => absint( wp_unslash( $_POST['artist_id'] ) ),
						'album_id'     => absint( wp_unslash( $_POST['album_id'] ) ),
						'isrc'         => sanitize_text_field( wp_unslash( $_POST['isrc'] ) ),
						'aliases'      => sanitize_text_field( wp_unslash( $_POST['aliases'] ) ),
						'release_date' => sanitize_text_field( wp_unslash( $_POST['release_date'] ) ),
						'genre'        => sanitize_text_field( wp_unslash( $_POST['genre'] ) ),
						'duration'     => sanitize_text_field( wp_unslash( $_POST['duration'] ) ),
						'description'  => sanitize_textarea_field( wp_unslash( $_POST['description'] ) ),
						'gradient'     => sanitize_key( wp_unslash( $_POST['gradient'] ) ),
						'status'       => sanitize_key( wp_unslash( $_POST['status'] ) ),
					),
					$id
				);
				if ( ! $id ) {
					self::redirect_notice( $redirect, 'error', 'Track could not be saved.' );
				}
				self::redirect_notice( add_query_arg( 'track_id', $id, $redirect ), 'success', 'Track saved successfully.' );
				break;

			case 'artist':
				self::assert_cap( 'amc_manage_library' );
				$id = AMC_DB::save_row(
					'artists',
					array(
						'name'              => sanitize_text_field( wp_unslash( $_POST['name'] ) ),
						'slug'              => sanitize_title( wp_unslash( $_POST['slug'] ) ),
						'image'             => esc_url_raw( wp_unslash( $_POST['image'] ) ),
						'aliases'           => sanitize_text_field( wp_unslash( $_POST['aliases'] ) ),
						'bio'               => sanitize_textarea_field( wp_unslash( $_POST['bio'] ) ),
						'country'           => sanitize_text_field( wp_unslash( $_POST['country'] ) ),
						'genre'             => sanitize_text_field( wp_unslash( $_POST['genre'] ) ),
						'social_links'      => sanitize_text_field( wp_unslash( $_POST['social_links'] ) ),
						'monthly_listeners' => sanitize_text_field( wp_unslash( $_POST['monthly_listeners'] ) ),
						'chart_streak'      => sanitize_text_field( wp_unslash( $_POST['chart_streak'] ) ),
						'gradient'          => sanitize_key( wp_unslash( $_POST['gradient'] ) ),
						'status'            => sanitize_key( wp_unslash( $_POST['status'] ) ),
						'blurb'             => sanitize_text_field( wp_unslash( $_POST['blurb'] ) ),
					),
					$id
				);
				if ( ! $id ) {
					self::redirect_notice( $redirect, 'error', 'Artist could not be saved.' );
				}
				self::redirect_notice( add_query_arg( 'artist_id', $id, $redirect ), 'success', 'Artist saved successfully.' );
				break;

			case 'album':
				self::assert_cap( 'amc_manage_library' );
				$id = AMC_DB::save_row(
					'albums',
					array(
						'title'        => sanitize_text_field( wp_unslash( $_POST['title'] ) ),
						'slug'         => sanitize_title( wp_unslash( $_POST['slug'] ) ),
						'artist_id'    => absint( wp_unslash( $_POST['artist_id'] ) ),
						'cover_image'  => esc_url_raw( wp_unslash( $_POST['cover_image'] ) ),
						'release_date' => sanitize_text_field( wp_unslash( $_POST['release_date'] ) ),
						'release_year' => sanitize_text_field( wp_unslash( $_POST['release_year'] ) ),
						'track_list'   => sanitize_textarea_field( wp_unslash( $_POST['track_list'] ) ),
						'genre'        => sanitize_text_field( wp_unslash( $_POST['genre'] ) ),
						'label'        => sanitize_text_field( wp_unslash( $_POST['label'] ) ),
						'description'  => sanitize_textarea_field( wp_unslash( $_POST['description'] ) ),
						'gradient'     => sanitize_key( wp_unslash( $_POST['gradient'] ) ),
						'status'       => sanitize_key( wp_unslash( $_POST['status'] ) ),
					),
					$id
				);
				if ( ! $id ) {
					self::redirect_notice( $redirect, 'error', 'Album could not be saved.' );
				}
				self::redirect_notice( add_query_arg( 'album_id', $id, $redirect ), 'success', 'Album saved successfully.' );
				break;

			case 'week':
				self::assert_cap( 'amc_manage_weeks' );
				$week_status = sanitize_key( wp_unslash( $_POST['status'] ) );
				if ( in_array( $week_status, array( 'published', 'archived' ), true ) ) {
					self::assert_cap( 'amc_publish_charts' );
				}
				$id = AMC_DB::save_row(
					'chart_weeks',
					array(
						'chart_id'     => absint( wp_unslash( $_POST['chart_id'] ) ),
						'country'      => isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : 'Global',
						'week_date'    => sanitize_text_field( wp_unslash( $_POST['week_date'] ) ),
						'status'       => $week_status,
						'is_featured'  => empty( $_POST['is_featured'] ) ? 0 : 1,
						'notes'        => sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ),
						'published_at' => 'published' === $week_status ? current_time( 'mysql' ) : null,
						'archived_at'  => 'archived' === $week_status ? current_time( 'mysql' ) : null,
					),
					$id
				);
				if ( ! $id ) {
					self::redirect_notice( $redirect, 'error', 'Chart week could not be saved. Check for duplicate chart/date combinations.' );
				}
				self::redirect_notice( add_query_arg( 'week_id', $id, $redirect ), 'success', 'Chart week saved successfully.' );
				break;

			case 'entry':
				self::assert_cap( 'amc_manage_weeks' );
				$id = AMC_DB::save_row(
					'chart_entries',
					array(
						'chart_week_id'  => absint( wp_unslash( $_POST['chart_week_id'] ) ),
						'entity_type'    => sanitize_key( wp_unslash( $_POST['entity_type'] ) ),
						'entity_id'      => absint( wp_unslash( $_POST['entity_id'] ) ),
						'current_rank'   => absint( wp_unslash( $_POST['current_rank'] ) ),
						'previous_rank'  => absint( wp_unslash( $_POST['previous_rank'] ) ),
						'peak_rank'      => absint( wp_unslash( $_POST['peak_rank'] ) ),
						'weeks_on_chart' => absint( wp_unslash( $_POST['weeks_on_chart'] ) ),
						'movement'       => sanitize_key( wp_unslash( $_POST['movement'] ) ),
						'score'          => (float) wp_unslash( $_POST['score'] ),
						'artwork'        => esc_url_raw( wp_unslash( $_POST['artwork'] ) ),
					),
					$id
				);
				if ( ! $id ) {
					self::redirect_notice( $redirect, 'error', 'Chart entry could not be saved.' );
				}
				self::redirect_notice( add_query_arg( array( 'week_id' => absint( wp_unslash( $_POST['chart_week_id'] ) ), 'entry_id' => $id ), $redirect ), 'success', 'Chart entry saved successfully.' );
				break;
		}

		self::redirect_notice( $redirect, 'error', 'Unsupported save request.' );
	}

	/**
	 * Handle row actions.
	 *
	 * @return void
	 */
	public static function handle_row_action() {
		if ( ! current_user_can( 'amc_view_dashboard' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Kontentainment Charts records.', 'kontentainment-charts' ) );
		}

		check_admin_referer( 'amc_row_action' );

		$entity   = isset( $_REQUEST['entity'] ) ? sanitize_key( wp_unslash( $_REQUEST['entity'] ) ) : '';
		$task     = isset( $_REQUEST['task'] ) ? sanitize_key( wp_unslash( $_REQUEST['task'] ) ) : '';
		$raw_id   = isset( $_REQUEST['id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['id'] ) ) : '';
		$id       = is_numeric( $raw_id ) ? absint( $raw_id ) : 0;
		$redirect = self::posted_redirect();

		if ( ! $entity || ! $task || ( ! $id && ! in_array( $entity, array( 'export', 'job', 'notification' ), true ) ) ) {
			self::redirect_notice( $redirect, 'error', 'Missing action payload.' );
		}

		if ( 'export' === $entity ) {
			self::assert_cap( 'amc_view_dashboard' );
			self::export_debug_csv( $task, $id );
		}

		if ( in_array( $entity, array( 'track', 'artist', 'album' ), true ) ) {
			self::assert_cap( 'amc_manage_library' );
			$table = $entity . 's';

			if ( 'delete' === $task ) {
				global $wpdb;
				$wpdb->delete(
					AMC_DB::table( 'chart_entries' ),
					array(
						'entity_type' => $entity,
						'entity_id'   => $id,
					)
				);
				AMC_DB::delete_row( $table, $id );
			} elseif ( 'archive' === $task ) {
				AMC_DB::save_row( $table, array( 'status' => 'archived' ), $id );
			} elseif ( 'restore' === $task ) {
				AMC_DB::save_row( $table, array( 'status' => 'active' ), $id );
			}

			self::redirect_notice( $redirect, 'success', ucfirst( $entity ) . ' updated.' );
		}

		if ( 'chart' === $entity ) {
			self::assert_cap( 'amc_manage_charts' );

			if ( 'delete' === $task ) {
				foreach ( AMC_DB::get_chart_weeks( array( 'chart_id' => $id ) ) as $week ) {
					AMC_DB::delete_chart_week_entries( (int) $week['id'] );
					AMC_DB::delete_row( 'chart_weeks', (int) $week['id'] );
				}
				AMC_DB::delete_row( 'charts', $id );
			} elseif ( 'hide' === $task ) {
				AMC_DB::save_row( 'charts', array( 'status' => 'hidden' ), $id );
			} elseif ( 'activate' === $task ) {
				AMC_DB::save_row( 'charts', array( 'status' => 'active' ), $id );
			} elseif ( 'feature' === $task ) {
				AMC_DB::save_row( 'charts', array( 'is_featured_home' => 1 ), $id );
			} elseif ( 'unfeature' === $task ) {
				AMC_DB::save_row( 'charts', array( 'is_featured_home' => 0 ), $id );
			}

			self::redirect_notice( $redirect, 'success', 'Chart updated.' );
		}

		if ( 'week' === $entity ) {
			self::assert_cap( 'amc_manage_weeks' );

			if ( 'delete' === $task ) {
				self::assert_cap( 'amc_publish_charts' );
				AMC_DB::delete_chart_week_entries( $id );
				AMC_DB::delete_row( 'chart_weeks', $id );
			} elseif ( 'generate' === $task ) {
				$week  = AMC_DB::get_row( 'chart_weeks', $id );
				$chart = $week ? AMC_DB::get_row( 'charts', (int) $week['chart_id'] ) : null;

				if ( ! $week || ! $chart ) {
					self::redirect_notice( $redirect, 'error', 'Chart week could not be found for generation.' );
				}

				$result = AMC_Ingestion::generate_chart_week( (int) $week['chart_id'], $week['country'], $week['week_date'], $chart['type'] );

				if ( empty( $result['success'] ) ) {
					self::redirect_notice( $redirect, 'error', $result['message'] );
				}

				self::redirect_notice( add_query_arg( 'week_id', $result['week_id'], $redirect ), 'success', $result['message'] );
			} elseif ( 'publish' === $task ) {
				self::assert_cap( 'amc_publish_charts' );
				$result = AMC_Ingestion::publish_chart_week( $id );
				self::redirect_notice( $redirect, empty( $result['success'] ) ? 'warning' : 'success', $result['message'] );
			} elseif ( 'unpublish' === $task ) {
				self::assert_cap( 'amc_publish_charts' );
				AMC_Ingestion::unpublish_chart_week( $id );
			} elseif ( 'republish' === $task ) {
				self::assert_cap( 'amc_publish_charts' );
				$result = AMC_Ingestion::republish_chart_week( $id );
				self::redirect_notice( $redirect, empty( $result['success'] ) ? 'warning' : 'success', $result['message'] );
			} elseif ( 'archive' === $task ) {
				self::assert_cap( 'amc_publish_charts' );
				AMC_Ingestion::archive_chart_week( $id );
			} elseif ( 'restore' === $task ) {
				self::assert_cap( 'amc_publish_charts' );
				AMC_Ingestion::restore_chart_week( $id );
			} elseif ( 'rollback' === $task ) {
				self::assert_cap( 'amc_publish_charts' );
				$result = AMC_Ingestion::rollback_chart_week( $id );
				self::redirect_notice( $redirect, empty( $result['success'] ) ? 'warning' : 'success', $result['message'] );
			} elseif ( 'feature' === $task ) {
				self::assert_cap( 'amc_publish_charts' );
				$week = AMC_DB::get_row( 'chart_weeks', $id );

				if ( $week ) {
					foreach ( AMC_DB::get_chart_weeks( array( 'chart_id' => (int) $week['chart_id'], 'country' => $week['country'] ) ) as $sibling ) {
						AMC_DB::save_row( 'chart_weeks', array( 'is_featured' => (int) $sibling['id'] === $id ? 1 : 0 ), (int) $sibling['id'] );
					}
				}
			} elseif ( 'unfeature' === $task ) {
				self::assert_cap( 'amc_publish_charts' );
				AMC_DB::save_row( 'chart_weeks', array( 'is_featured' => 0 ), $id );
			}

			self::redirect_notice( $redirect, 'success', 'Chart week updated.' );
		}

		if ( 'entry' === $entity ) {
			self::assert_cap( 'amc_manage_weeks' );

			if ( 'delete' === $task ) {
				AMC_DB::delete_row( 'chart_entries', $id );
			}

			self::redirect_notice( $redirect, 'success', 'Chart entry updated.' );
		}

		if ( 'upload' === $entity ) {
			self::assert_cap( 'amc_manage_weeks' );

			if ( 'parse' === $task ) {
				$result = AMC_Ingestion::reprocess_upload( $id, array( 'parse' => true, 'match' => false ) );
				self::redirect_notice( add_query_arg( 'upload_id', $id, $redirect ), empty( $result['success'] ) ? 'error' : 'success', $result['message'] );
			} elseif ( 'match' === $task ) {
				$result = AMC_Ingestion::reprocess_upload( $id, array( 'parse' => false, 'match' => true ) );
				self::redirect_notice( add_query_arg( 'upload_id', $id, $redirect ), empty( $result['success'] ) ? 'error' : 'success', $result['message'] );
			} elseif ( 'generate' === $task ) {
				$result = AMC_Ingestion::generate_chart_for_upload( $id );
				if ( empty( $result['success'] ) ) {
					self::redirect_notice( add_query_arg( 'upload_id', $id, $redirect ), 'error', $result['message'] );
				}
				self::redirect_notice( add_query_arg( array( 'upload_id' => $id, 'week_id' => $result['week_id'] ), $redirect ), 'success', $result['message'] );
			} elseif ( 'commit' === $task ) {
				$result = AMC_Ingestion::commit_dry_run_upload( $id );
				self::redirect_notice( add_query_arg( 'upload_id', $id, $redirect ), empty( $result['success'] ) ? 'warning' : 'success', $result['message'] );
			} elseif ( 'delete' === $task ) {
				global $wpdb;
				$rows = AMC_DB::get_rows( 'source_rows', array( 'where' => array( 'upload_id' => $id ) ) );
				foreach ( $rows as $row ) {
					$wpdb->delete( AMC_DB::table( 'matching_queue' ), array( 'source_row_id' => (int) $row['id'] ) );
				}
				$wpdb->delete( AMC_DB::table( 'source_rows' ), array( 'upload_id' => $id ) );
				$wpdb->delete( AMC_DB::table( 'ingestion_logs' ), array( 'upload_id' => $id ) );
				AMC_DB::delete_row( 'source_uploads', $id );
				self::redirect_notice( $redirect, 'success', 'Upload deleted.' );
			}
		}

		if ( 'matching' === $entity ) {
			self::assert_cap( 'amc_manage_weeks' );
			$notes = isset( $_REQUEST['notes'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['notes'] ) ) : '';
			AMC_Ingestion::apply_matching_decision(
				$id,
				$task,
				array(
					'entity_type' => isset( $_REQUEST['override_entity_type'] ) ? sanitize_key( wp_unslash( $_REQUEST['override_entity_type'] ) ) : '',
					'entity_id'   => isset( $_REQUEST['override_entity_id'] ) ? absint( wp_unslash( $_REQUEST['override_entity_id'] ) ) : 0,
					'notes'       => $notes,
				)
			);
			self::redirect_notice( $redirect, 'success', 'Matching decision saved.' );
		}

		if ( 'job' === $entity ) {
			self::assert_cap( 'amc_view_dashboard' );
			$result = array( 'success' => false, 'message' => 'Unsupported job action.' );

			if ( 'run-queued' === $task ) {
				$result = AMC_Ingestion::run_queued_jobs_now();
			} elseif ( 'run' === $task ) {
				$result = AMC_Ingestion::run_job( $id );
			} elseif ( 'retry' === $task ) {
				$result = AMC_Ingestion::retry_job( $id );
			} elseif ( 'cancel' === $task ) {
				$result = AMC_Ingestion::cancel_job( $id );
			} elseif ( 'rerun' === $task ) {
				$result = AMC_Ingestion::rerun_job( $id );
			}

			self::redirect_notice( $redirect, ! empty( $result['success'] ) ? 'success' : 'warning', ! empty( $result['message'] ) ? $result['message'] : 'Job action completed.' );
		}

		if ( 'notification' === $entity ) {
			self::assert_cap( 'amc_view_dashboard' );
			$result = array( 'success' => false, 'message' => 'Unsupported notification action.' );

			if ( 'read' === $task ) {
				$result = AMC_Ingestion::mark_notification_read( $raw_id );
			} elseif ( 'dismiss' === $task ) {
				$result = AMC_Ingestion::dismiss_notification( $raw_id );
			}

			self::redirect_notice( $redirect, ! empty( $result['success'] ) ? 'success' : 'warning', ! empty( $result['message'] ) ? $result['message'] : 'Notification updated.' );
		}

		self::redirect_notice( $redirect, 'error', 'Unsupported action request.' );
	}

	/**
	 * Handle bulk actions.
	 *
	 * @return void
	 */
	public static function handle_bulk_action() {
		self::assert_cap( 'amc_view_dashboard' );
		check_admin_referer( 'amc_bulk_action' );

		$entity   = isset( $_POST['entity'] ) ? sanitize_key( wp_unslash( $_POST['entity'] ) ) : '';
		$task     = isset( $_POST['task'] ) ? sanitize_key( wp_unslash( $_POST['task'] ) ) : '';
		$selected = isset( $_POST['selected_ids'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['selected_ids'] ) ) : array();
		$redirect = self::posted_redirect();

		if ( 'job' === $entity ) {
			$result = AMC_Ingestion::bulk_job_action( $task, $selected );
			self::redirect_notice( $redirect, ! empty( $result['success'] ) ? 'success' : 'warning', $result['message'] );
		}

		if ( 'notification' === $entity ) {
			if ( 'read_selected' === $task ) {
				$result = AMC_Ingestion::mark_notifications_read( array( 'ids' => $selected ) );
			} elseif ( 'dismiss_selected' === $task ) {
				$result = AMC_Ingestion::dismiss_notifications( array( 'ids' => $selected ) );
			} elseif ( 'read_filtered' === $task ) {
				$result = AMC_Ingestion::mark_notifications_read( AMC_Admin_Data::notification_filters_from_request() );
			} elseif ( 'dismiss_filtered' === $task ) {
				$result = AMC_Ingestion::dismiss_notifications( AMC_Admin_Data::notification_filters_from_request() );
			} else {
				$result = array( 'success' => false, 'message' => 'Unsupported notification bulk action.' );
			}

			self::redirect_notice( $redirect, ! empty( $result['success'] ) ? 'success' : 'warning', $result['message'] );
		}

		self::redirect_notice( $redirect, 'warning', 'Unsupported bulk action.' );
	}

	/**
	 * Handle source upload submission.
	 *
	 * @return void
	 */
	public static function handle_upload_source() {
		self::assert_cap( 'amc_manage_weeks' );
		check_admin_referer( 'amc_upload_source' );

		if ( empty( $_FILES['source_file']['name'] ) ) {
			self::redirect_notice( self::posted_redirect(), 'error', 'Please choose a source file to upload.' );
		}

		$result = AMC_Ingestion::create_upload(
			$_FILES['source_file'],
			array(
				'source_platform' => isset( $_POST['source_platform'] ) ? sanitize_text_field( wp_unslash( $_POST['source_platform'] ) ) : '',
				'country'         => isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : 'Global',
				'chart_week'      => isset( $_POST['chart_week'] ) ? sanitize_text_field( wp_unslash( $_POST['chart_week'] ) ) : current_time( 'Y-m-d' ),
				'chart_date'      => isset( $_POST['chart_date'] ) ? sanitize_text_field( wp_unslash( $_POST['chart_date'] ) ) : current_time( 'Y-m-d' ),
				'target_chart_id' => isset( $_POST['target_chart_id'] ) ? absint( wp_unslash( $_POST['target_chart_id'] ) ) : 0,
				'chart_type'      => isset( $_POST['chart_type'] ) ? sanitize_text_field( wp_unslash( $_POST['chart_type'] ) ) : 'track',
				'dry_run'         => ! empty( $_POST['dry_run'] ) ? 1 : 0,
				'allow_duplicate' => ! empty( $_POST['allow_duplicate'] ) ? 1 : 0,
			)
		);

		if ( empty( $result['upload_id'] ) ) {
			self::redirect_notice( self::posted_redirect(), ! empty( $result['type'] ) ? $result['type'] : 'error', ! empty( $result['message'] ) ? $result['message'] : 'Upload failed. Check file permissions or file type.' );
		}

		self::redirect_notice( add_query_arg( 'upload_id', $result['upload_id'], self::posted_redirect() ), ! empty( $result['type'] ) ? $result['type'] : 'success', ! empty( $result['message'] ) ? $result['message'] : 'Source upload saved, parsed, and queued for matching.' );
	}

	/**
	 * Export debug data as CSV.
	 *
	 * @param string $task Export task.
	 * @param int    $id Context id.
	 * @return void
	 */
	private static function export_debug_csv( $task, $id ) {
		$rows     = array();
		$filename = 'kontentainment-charts-export.csv';

		if ( 'logs' === $task ) {
			$filters = array();
			if ( $id > 0 ) {
				$filters['upload_id'] = $id;
			}
			$rows     = AMC_Admin_Data::logs( $filters );
			$filename = $id > 0 ? 'kontentainment-charts-upload-' . $id . '-logs.csv' : 'kontentainment-charts-logs.csv';
		} elseif ( 'invalid_rows' === $task ) {
			$rows     = AMC_Ingestion::invalid_rows( $id, 500 );
			$filename = 'kontentainment-charts-upload-' . $id . '-invalid-rows.csv';
		} elseif ( 'dropped_out' === $task ) {
			$week     = AMC_DB::get_row( 'chart_weeks', $id );
			$rows     = $week && ! empty( $week['dropped_out_json'] ) ? json_decode( $week['dropped_out_json'], true ) : array();
			$filename = 'kontentainment-charts-week-' . $id . '-dropped-out.csv';
		} elseif ( 'matching_queue' === $task ) {
			$rows     = AMC_Admin_Data::matching_candidates();
			$filename = 'kontentainment-charts-matching-queue.csv';
		}

		if ( empty( $rows ) ) {
			self::redirect_notice( self::posted_redirect(), 'warning', 'No exportable records were found for that request.' );
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array_keys( (array) reset( $rows ) ) );

		foreach ( $rows as $row ) {
			$flat = array();
			foreach ( (array) $row as $value ) {
				$flat[] = is_array( $value ) ? wp_json_encode( $value ) : $value;
			}
			fputcsv( $output, $flat );
		}

		fclose( $output );
		exit;
	}

	/**
	 * Handle scoring save requests.
	 *
	 * @return void
	 */
	public static function handle_save_scoring() {
		self::assert_cap( 'amc_manage_weeks' );
		check_admin_referer( 'amc_save_scoring' );
		$result = AMC_Ingestion::save_scoring_rules( $_POST );
		if ( ! empty( $result['warnings'] ) ) {
			self::redirect_notice( self::posted_redirect(), 'warning', 'Some scoring values were invalid and were kept at their previous values.' );
		}
		self::redirect_notice( self::posted_redirect(), 'success', 'Scoring rules saved successfully.' );
	}

	/**
	 * Handle settings save requests.
	 *
	 * @return void
	 */
	public static function handle_save_settings() {
		self::assert_cap( 'amc_manage_settings' );
		check_admin_referer( 'amc_save_settings' );

		AMC_DB::save_settings(
			array(
				'platform_name'    => sanitize_text_field( wp_unslash( $_POST['platform_name'] ) ),
				'logo'             => sanitize_text_field( wp_unslash( $_POST['logo'] ) ),
				'seo_defaults'     => sanitize_text_field( wp_unslash( $_POST['seo_defaults'] ) ),
				'social_image'     => sanitize_text_field( wp_unslash( $_POST['social_image'] ) ),
				'homepage_chart'   => sanitize_text_field( wp_unslash( $_POST['homepage_chart'] ) ),
				'methodology_text' => sanitize_textarea_field( wp_unslash( $_POST['methodology_text'] ) ),
				'language'         => sanitize_text_field( wp_unslash( $_POST['language'] ) ),
				'date_format'      => sanitize_text_field( wp_unslash( $_POST['date_format'] ) ),
				'alert_email'      => sanitize_email( wp_unslash( $_POST['alert_email'] ) ),
				'alert_webhook_url'=> esc_url_raw( wp_unslash( $_POST['alert_webhook_url'] ) ),
				'alert_types_enabled' => sanitize_text_field( wp_unslash( $_POST['alert_types_enabled'] ) ),
			)
		);

		self::redirect_notice( self::posted_redirect(), 'success', 'Settings saved successfully.' );
	}

	/**
	 * Ensure current user has a capability.
	 *
	 * @param string $cap Capability.
	 * @return void
	 */
	private static function assert_cap( $cap ) {
		if ( ! current_user_can( $cap ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'kontentainment-charts' ) );
		}
	}

	/**
	 * Get redirect target from request.
	 *
	 * @return string
	 */
	private static function posted_redirect() {
		$redirect = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : wp_get_referer();

		return $redirect ? $redirect : admin_url( 'admin.php?page=kontentainment-charts' );
	}

	/**
	 * Redirect with a notice.
	 *
	 * @param string $url Target url.
	 * @param string $type Notice type.
	 * @param string $message Message.
	 * @return void
	 */
	private static function redirect_notice( $url, $type, $message ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'amc_notice_type' => $type,
					'amc_notice'      => $message,
				),
				$url
			)
		);
		exit;
	}

	/**
	 * Render theme toggle button.
	 *
	 * @return void
	 */
	private static function render_theme_toggle() {
		echo '<button type="button" class="button button-secondary amc-theme-toggle" data-amc-theme-toggle data-amc-theme-label-dark="Light Mode" data-amc-theme-label-light="Dark Mode" aria-pressed="false">Light Mode</button>';
	}
}
