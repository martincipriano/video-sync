<?php
declare(strict_types=1);
/**
 * Template part for the Export/Import tab on the Settings screen.
 *
 * @package Buoy_Video_Sync
 *
 * Variables available in this template:
 * @var array $channel    The current channel configuration.
 * @var bool  $has_config Whether a channel is configured.
 * @var int   $rule_count Number of sync rules on the channel.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<h2><?php esc_html_e( 'Export Sync Rules', 'buoy-video-sync' ); ?></h2>
<p><?php esc_html_e( 'Download your channel and its sync rules as a JSON file — use it as a backup, or to copy your setup to another site. Your YouTube API key and plugin settings are not included.', 'buoy-video-sync' ); ?></p>

<?php if ( $has_config ) : ?>
	<p class="buoyvs-eim-summary">
		<?php esc_html_e( 'Current setup:', 'buoy-video-sync' ); ?>
		<strong><?php echo esc_html( '' !== ( $channel['channel_title'] ?? '' ) ? $channel['channel_title'] : $channel['youtube_id'] ); ?></strong>
		&middot;
		<?php
		echo esc_html( sprintf(
			/* translators: %s: number of sync rules */
			_n( '%s sync rule', '%s sync rules', $rule_count, 'buoy-video-sync' ),
			number_format_i18n( $rule_count )
		) );
		?>
	</p>
	<p class="buoyvs-eim-submit">
		<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=buoyvs_export_channels' ), 'buoyvs_export_channels' ) ); ?>">
			<?php esc_html_e( 'Export Sync Rules (JSON)', 'buoy-video-sync' ); ?>
		</a>
	</p>
<?php else : ?>
	<p class="buoyvs-eim-summary"><?php esc_html_e( 'No channel is set up yet, so there is nothing to export.', 'buoy-video-sync' ); ?></p>
	<p class="buoyvs-eim-submit">
		<button type="button" class="button button-primary" disabled><?php esc_html_e( 'Export Sync Rules (JSON)', 'buoy-video-sync' ); ?></button>
	</p>
<?php endif; ?>

<hr>

<h2><?php esc_html_e( 'Import Sync Rules', 'buoy-video-sync' ); ?></h2>
<p><?php esc_html_e( 'Upload a previously exported JSON file. This replaces your current channel and all of its sync rules with the contents of the file. Posts that were already synced are not changed.', 'buoy-video-sync' ); ?></p>

<form
	class="buoyvs-import-form"
	method="post"
	enctype="multipart/form-data"
	action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
	data-confirm="<?php echo esc_attr( __( 'Importing will replace your current channel and all of its sync rules. Continue?', 'buoy-video-sync' ) ); ?>"
>
	<?php wp_nonce_field( 'buoyvs_import_channels', 'buoyvs_import_nonce' ); ?>
	<input type="hidden" name="action" value="buoyvs_import_channels">
	<p class="buoyvs-import-file-wrap">
		<label for="buoyvs-import-file"><?php esc_html_e( 'Export file (.json, max 1 MB)', 'buoy-video-sync' ); ?></label><br>
		<input type="file" id="buoyvs-import-file" name="buoyvs_import_file" accept=".json,application/json" required>
	</p>
	<p class="buoyvs-eim-submit">
		<button type="submit" class="button button-primary buoyvs-import-trigger"><?php esc_html_e( 'Import Sync Rules', 'buoy-video-sync' ); ?></button>
	</p>
</form>
