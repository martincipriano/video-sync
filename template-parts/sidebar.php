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

<div class="ys-sidebar-widget ys-faq-widget">
	<h3><?php esc_html_e( 'FAQ', 'yousync' ); ?></h3>
	<details>
		<summary><?php esc_html_e( "Why hasn't my sync run?", 'yousync' ); ?></summary>
		<p><?php esc_html_e( 'YouSync schedules run via WP-Cron, which requires site traffic to trigger. If your site is low-traffic or you need reliable timing, ask your host to configure a real server-side cron job.', 'yousync' ); ?></p>
	</details>
	<details>
		<summary><?php esc_html_e( 'What is YouTube API quota?', 'yousync' ); ?></summary>
		<p><?php esc_html_e( 'Google gives each project 10,000 API units per day. YouSync shows an estimate next to each rule. If you hit the limit, syncs resume the next day. You can request a higher quota in the Google Cloud Console.', 'yousync' ); ?></p>
	</details>
	<details>
		<summary><?php esc_html_e( 'Why did my sync fail?', 'yousync' ); ?></summary>
		<p><?php esc_html_e( 'Check the Logs page for details. Common causes: an expired or missing YouTube API key, or daily quota exhausted. Fix the cause and the next scheduled run will retry automatically.', 'yousync' ); ?></p>
	</details>
	<a href="<?php echo esc_url( 'https://wpbuoy.com/product/yousync/#faqs' ); ?>" target="_blank" class="button button-secondary" style="text-align: center;"><?php esc_html_e( 'View all FAQs', 'yousync' ); ?></a>
</div>
