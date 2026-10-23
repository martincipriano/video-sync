<?php
declare(strict_types=1);
/**
 * Template part for displaying the main settings page.
 *
 * Two tabs: General (every registered settings section) and Export/Import. The tabs are links (?tab=…), so they work without
 * JavaScript; settings.js switches them in place.
 *
 * @package Buoy_Video_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$buoyvs_page = 'buoyvs_settings';
$buoyvs_tabs = array(
	'general' => __( 'General', 'buoy-video-sync' ),
	\Buoy_Video_Sync\Import_Export::TAB_SLUG => __( 'Export/Import', 'buoy-video-sync' ),
);

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab selection.
$buoyvs_active = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
if ( ! isset( $buoyvs_tabs[ $buoyvs_active ] ) ) {
	$buoyvs_active = (string) array_key_first( $buoyvs_tabs );
}
$buoyvs_is_ei = \Buoy_Video_Sync\Import_Export::TAB_SLUG === $buoyvs_active;
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Buoy Video Sync Settings', 'buoy-video-sync' ); ?></h1>

	<?php settings_errors( 'buoyvs_api_key' ); ?>
	<?php \Buoy_Video_Sync\Import_Export::render_notices(); ?>

	<nav class="buoyvs-settings-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Settings sections', 'buoy-video-sync' ); ?>">
		<?php foreach ( $buoyvs_tabs as $buoyvs_slug => $buoyvs_label ) : ?>
			<?php $buoyvs_is_active = $buoyvs_slug === $buoyvs_active; ?>
			<a
				href="<?php echo esc_url( add_query_arg( array( 'page' => $buoyvs_page, 'tab' => $buoyvs_slug ), admin_url( 'admin.php' ) ) ); ?>"
				id="buoyvs-tab-<?php echo esc_attr( $buoyvs_slug ); ?>"
				class="buoyvs-tab-button<?php echo $buoyvs_is_active ? ' active' : ''; ?>"
				role="tab"
				aria-selected="<?php echo $buoyvs_is_active ? 'true' : 'false'; ?>"
				aria-controls="buoyvs-panel-<?php echo esc_attr( $buoyvs_slug ); ?>"
				tabindex="<?php echo $buoyvs_is_active ? '0' : '-1'; ?>"
				data-tab="<?php echo esc_attr( $buoyvs_slug ); ?>"
			><?php echo esc_html( $buoyvs_label ); ?></a>
		<?php endforeach; ?>
	</nav>

	<form method="post" action="options.php">
		<?php settings_fields( 'buoyvs_settings_group' ); ?>

		<div
			id="buoyvs-panel-general"
			class="buoyvs-tab-panel buoyvs-tab-panel--general"
			role="tabpanel"
			aria-labelledby="buoyvs-tab-general"
			<?php echo 'general' === $buoyvs_active ? '' : 'hidden'; ?>
		>
			<?php
			// Section headings only when there is more than one section — the
			// tab label already names a single section.
			global $wp_settings_sections;
			$buoyvs_sections = $wp_settings_sections[ $buoyvs_page ] ?? array();
			foreach ( $buoyvs_sections as $buoyvs_section ) :
				if ( count( $buoyvs_sections ) > 1 && $buoyvs_section['title'] ) :
					?>
					<h2><?php echo esc_html( $buoyvs_section['title'] ); ?></h2>
					<?php
				endif;
				?>
				<table class="form-table" role="presentation">
					<?php do_settings_fields( $buoyvs_page, $buoyvs_section['id'] ); ?>
				</table>
			<?php endforeach; ?>
		</div>

		<div class="buoyvs-settings-footer" <?php echo $buoyvs_is_ei ? 'hidden' : ''; ?>>
			<?php submit_button(); ?>
		</div>
	</form>

	<div
		id="buoyvs-panel-<?php echo esc_attr( \Buoy_Video_Sync\Import_Export::TAB_SLUG ); ?>"
		class="buoyvs-tab-panel"
		role="tabpanel"
		aria-labelledby="buoyvs-tab-<?php echo esc_attr( \Buoy_Video_Sync\Import_Export::TAB_SLUG ); ?>"
		<?php echo $buoyvs_is_ei ? '' : 'hidden'; ?>
	>
		<?php \Buoy_Video_Sync\Import_Export::render_panel(); ?>
	</div>

	<?php
	buoyvs_get_template_part( 'upgrade-banner', null, array(
		'heading'     => __( 'Fine-tune how your syncs run', 'buoy-video-sync' ),
		'description' => __( 'Map custom fields, filter which items sync, and protect manually edited posts from being overwritten.', 'buoy-video-sync' ),
	) );
	?>
</div>
