<?php
/**
 * Admin Sidebar
 *
 * Injects the upgrade/support sidebar into the WordPress admin footer
 * on configured screens via a body class + fixed CSS positioning.
 *
 * @package YouSync
 */

namespace YouSync;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin_Sidebar Class
 *
 * To add the sidebar to additional screens, pass their screen IDs
 * to the constructor below.
 */
class Admin_Sidebar {

	/**
	 * Screen IDs on which the sidebar should appear.
	 *
	 * @var string[]
	 */
	private array $screen_ids;

	/**
	 * Constructor.
	 *
	 * @param string[] $screen_ids WP screen IDs on which to show the sidebar.
	 */
	public function __construct( array $screen_ids ) {
		$this->screen_ids = $screen_ids;
		add_filter( 'admin_body_class', array( $this, 'add_body_class' ) );
		add_action( 'admin_footer', array( $this, 'render' ) );
	}

	/**
	 * Whether the current screen should show the sidebar.
	 *
	 * @return bool
	 */
	private function is_sidebar_screen(): bool {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}
		// On taxonomy list pages never show the sidebar — only on term edit pages.
		if ( 'edit-tags' === $screen->base ) {
			return false;
		}
		return in_array( $screen->id, $this->screen_ids, true );
	}

	/**
	 * Add body class when sidebar is active.
	 *
	 * @param string $classes Space-separated body classes.
	 * @return string
	 */
	public function add_body_class( string $classes ): string {
		if ( $this->is_sidebar_screen() ) {
			$classes .= ' yousync-has-sidebar';
		}
		return $classes;
	}

	/**
	 * Render the sidebar in the admin footer.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! $this->is_sidebar_screen() ) {
			return;
		}
		echo '<div class="ys-admin-sidebar">';
		yousync_get_template_part( 'sidebar' );
		echo '</div>';
	}
}

new Admin_Sidebar(
	array(
		'yousync_videos_page_yousync_settings',
		'edit-yousync_channel',
		'edit-yousync_playlist',
	)
);
