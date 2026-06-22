<?php
declare(strict_types=1);
/**
 * Template part for displaying API key settings field
 *
 * @package WPBuoy_Video_Sync
 * @var string $value The current API key value
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<input
	aria-describedby="wpbyvs_api_key_description"
	class="regular-text"
	name="wpbyvs_api_key"
	type="text"
	value="<?php echo esc_attr( $value ); ?>"
>
<p class="description" id="wpbyvs_api_key_description">
	<?php
	printf(
		/* translators: %s: Link to YouTube Data API documentation */
		esc_html__( 'This key allows WPBuoy Video Sync to access public data from any YouTube channel. You can create an API key in the Google Cloud Console by following the guide in the %s.', 'wpbuoy-video-sync'),
		'<a href="https://developers.google.com/youtube/v3/getting-started" target="_blank" rel="noopener noreferrer">' . esc_html__( 'YouTube Data API Overview', 'wpbuoy-video-sync') . '</a>'
	);
	?>
</p>
