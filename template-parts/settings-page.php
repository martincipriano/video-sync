<?php
/**
 * Template part for displaying the main settings page
 *
 * @package YouSync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'YouSync Settings', 'yousync' ); ?></h1>

	<?php settings_errors( 'yousync_api_key' ); ?>

	<div class="ys-page-container">
		<div class="ys-page-content">
			<form method="post" action="options.php">
				<?php
				settings_fields( 'yousync_settings_group' );
				do_settings_sections( 'yousync_settings' );
				submit_button();
				?>
			</form>
		</div>

		<div class="ys-page-sidebar">
			<?php yousync_get_template_part( 'sidebar' ); ?>
		</div>
	</div>
</div>
