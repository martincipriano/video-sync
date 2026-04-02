<?php
/**
 * Default template for the root channel archive page.
 *
 * Override by creating taxonomy-yousync_channel.php in your theme.
 *
 * @package YouSync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$channels = get_terms( array(
	'taxonomy'   => 'yousync_channel',
	'hide_empty' => false,
	'orderby'    => 'name',
	'order'      => 'ASC',
) );
?>
<div class="yousync-channel-archive">
	<h1><?php esc_html_e( 'Channels', 'yousync' ); ?></h1>

	<?php if ( is_wp_error( $channels ) || empty( $channels ) ) : ?>
		<p><?php esc_html_e( 'No channels found.', 'yousync' ); ?></p>
	<?php else : ?>
		<ul class="yousync-archive-list">
			<?php foreach ( $channels as $channel ) : ?>
				<li>
					<a href="<?php echo esc_url( get_term_link( $channel ) ); ?>">
						<?php echo esc_html( $channel->name ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
<?php
get_footer();
