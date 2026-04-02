<?php
/**
 * Default template for the root playlist archive page.
 *
 * Override by creating taxonomy-yousync_playlist.php in your theme.
 *
 * @package YouSync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$playlists = get_terms( array(
	'taxonomy'   => 'yousync_playlist',
	'hide_empty' => false,
	'orderby'    => 'name',
	'order'      => 'ASC',
) );
?>
<div class="yousync-playlist-archive">
	<h1><?php esc_html_e( 'Playlists', 'yousync' ); ?></h1>

	<?php if ( is_wp_error( $playlists ) || empty( $playlists ) ) : ?>
		<p><?php esc_html_e( 'No playlists found.', 'yousync' ); ?></p>
	<?php else : ?>
		<ul class="yousync-archive-list">
			<?php foreach ( $playlists as $playlist ) : ?>
				<li>
					<a href="<?php echo esc_url( get_term_link( $playlist ) ); ?>">
						<?php echo esc_html( $playlist->name ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
<?php
get_footer();
