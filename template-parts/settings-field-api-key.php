<?php
declare(strict_types=1);
/**
 * Template part for displaying API key settings field
 *
 * @package Buoy_Video_Sync
 * @var string $value The current API key value
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<input
	aria-describedby="buoyvs_api_key_description"
	class="regular-text"
	name="buoyvs_api_key"
	type="text"
	value="<?php echo esc_attr( $value ); ?>"
>
<p class="description" id="buoyvs_api_key_description">
	<?php
	printf(
		/* translators: %s: Link to YouTube Data API documentation */
		esc_html__( 'This key allows Buoy Video Sync to access public data from any YouTube channel. You can create an API key in the Google Cloud Console by following the guide in the %s.', 'buoy-video-sync'),
		'<a href="https://developers.google.com/youtube/v3/getting-started" target="_blank" rel="noopener noreferrer">' . esc_html__( 'YouTube Data API Overview', 'buoy-video-sync') . '</a>'
	);
	?>
</p>
