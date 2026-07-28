<?php
declare(strict_types=1);
/**
 * Template part for displaying the main settings page.
 *
 * @package Buoy_Video_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Buoy Video Sync Settings', 'buoy-video-sync' ); ?></h1>

	<?php settings_errors( 'buoyvs_api_key' ); ?>

	<form method="post" action="options.php">
		<?php
		settings_fields( 'buoyvs_settings_group' );
		do_settings_sections( 'buoyvs_settings' );
		submit_button();
		?>
	</form>

	<?php
	buoyvs_get_template_part( 'upgrade-banner', null, array(
		'heading'     => __( 'Fine-tune how your syncs run', 'buoy-video-sync' ),
		'description' => __( 'Map custom fields, filter which items sync, and protect manually edited posts from being overwritten.', 'buoy-video-sync' ),
	) );
	?>
</div>
