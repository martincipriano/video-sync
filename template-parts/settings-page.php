<?php
declare(strict_types=1);
/**
 * Template part for displaying the main settings page.
 *
 * @package WPBuoy_Video_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'WPBuoy Video Sync Settings', 'wby-video-sync' ); ?></h1>

	<?php settings_errors( 'wpbyvs_api_key' ); ?>

	<form method="post" action="options.php">
		<?php
		settings_fields( 'wpbyvs_settings_group' );
		do_settings_sections( 'wpbyvs_settings' );
		submit_button();
		?>
	</form>
</div>
