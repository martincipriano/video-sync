<?php
declare(strict_types=1);
/**
 * Template part for displaying the main settings page.
 *
 * @package WPBuoyVideoSync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'WPBuoy Video Sync Settings', 'wpbuoy-video-sync' ); ?></h1>

	<?php settings_errors( 'wpbuoy_video_sync_api_key' ); ?>

	<form method="post" action="options.php">
		<?php
		settings_fields( 'wpbuoy_video_sync_settings_group' );
		do_settings_sections( 'wpbuoy_video_sync_settings' );
		submit_button();
		?>
	</form>
</div>
