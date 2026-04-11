<?php
/**
 * Admin sidebar template.
 *
 * @package YouSync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ys-sidebar-widget ys-upgrade-widget">
	<h3><?php esc_html_e( 'Upgrade to Pro', 'yousync' ); ?></h3>
	<p><?php esc_html_e( 'Upgrade to YouSync Pro for advanced features:', 'yousync' ); ?></p>
	<ul class="ys-pro-features">
		<li><?php esc_html_e( 'Recurring Schedules', 'yousync' ); ?></li>
		<li><?php esc_html_e( 'More Sync Actions', 'yousync' ); ?></li>
		<li><?php esc_html_e( 'Sync Conditions & Filters', 'yousync' ); ?></li>
		<li><?php esc_html_e( 'Video Protection', 'yousync' ); ?></li>
	</ul>
	<a href="<?php echo esc_url( 'https://wpbuoy.com/plugins/yousync/' ); ?>" target="_blank" class="button button-primary"><?php esc_html_e( 'Upgrade to Pro', 'yousync' ); ?></a>
	<a href="<?php echo esc_url( 'https://wpbuoy.com/plugins/yousync/' ); ?>" target="_blank" class="button button-secondary"><?php esc_html_e( 'View All Features', 'yousync' ); ?></a>
</div>

<div class="ys-sidebar-widget ys-support-widget">
	<h3><?php esc_html_e( 'Need Help?', 'yousync' ); ?></h3>
	<p><?php esc_html_e( 'Get support and documentation for YouSync.', 'yousync' ); ?></p>
	<ul>
		<li><a href="<?php echo esc_url( 'https://wpbuoy.com/product/yousync/#faqs' ); ?>" target="_blank"><?php esc_html_e( 'FAQ', 'yousync' ); ?></a></li>
		<li><a href="<?php echo esc_url( 'https://wpbuoy.com/plugins/yousync/' ); ?>" target="_blank"><?php esc_html_e( 'Documentation', 'yousync' ); ?></a></li>
		<li><a href="<?php echo esc_url( 'https://wpbuoy.com/support/' ); ?>" target="_blank"><?php esc_html_e( 'Helpdesk', 'yousync' ); ?></a></li>
	</ul>
</div>
