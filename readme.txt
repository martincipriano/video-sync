=== YouSync ===
Contributors: martincipriano
Tags: youtube, video, sync, channel, playlist
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sync YouTube channels and playlists to WordPress. Import videos as a custom post type with metadata, thumbnails, and flexible sync rules.

== Description ==

YouSync connects your WordPress site to YouTube. Add a channel or playlist, define sync rules, and WordPress automatically imports videos as posts — complete with titles, descriptions, thumbnails, view counts, tags, and categories.

**Key features:**

* Import videos from YouTube channels or playlists as a custom post type
* Flexible sync rules with schedule options (hourly, daily, weekly, monthly, custom)
* Filter what gets imported using conditions (title, description, tags, duration, view count, published date, and more)
* Automatic thumbnail import and featured image assignment
* Per-rule sync history with last-synced time and error log
* Channels and playlists managed through a familiar taxonomy-style admin interface
* Optional archive pages for videos, channels, and playlists
* "Delete all data on uninstall" option — your data stays unless you ask for it to be removed

**Requires a Google API key** with the YouTube Data API v3 enabled. Calls are made directly from your server to Google's API using your key.

== Installation ==

1. Upload the `yousync` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **Videos → Settings** and enter your Google API key.
4. Go to **Videos → Channels** and add your first YouTube channel.
5. Set up a sync rule on the channel to start importing videos.

**Getting a Google API key:**

1. Visit [Google Cloud Console](https://console.cloud.google.com/).
2. Create a project and enable the **YouTube Data API v3**.
3. Generate an API key under **Credentials**.
4. Copy the key into YouSync → Settings → Google API Key.

== Frequently Asked Questions ==

= Where do the synced videos go? =

Videos are saved as the `yousync_videos` custom post type. You can find them under **Videos** in the WordPress admin.

= Does YouSync upload videos to my site? =

No. YouSync imports metadata (title, description, thumbnail URL, view counts, etc.) and stores it in WordPress. Video files stay on YouTube. Thumbnails are optionally downloaded as WordPress attachments.

= Will synced videos be updated automatically? =

Yes, if you configure a sync rule with a recurring schedule (daily, weekly, etc.). YouSync uses WP Cron to run scheduled syncs.

= Is WP Cron reliable? =

WP Cron runs when your site receives a visitor. On low-traffic sites, scheduled syncs may be delayed. For more reliable scheduling, consider using a real cron job to trigger `wp-cron.php` on your server.

= What happens to my data if I uninstall YouSync? =

By default, your videos, channels, playlists, and settings are kept even after uninstalling. If you want everything removed, enable **Remove all YouSync data when the plugin is deleted** under Videos → Settings → Advanced before deleting the plugin.

= How many API quota units does YouSync use? =

Each sync uses 1 quota unit per API call. Importing videos from a channel requires 2 calls minimum (1 for channel data, 1+ for playlist items), plus 1 call per 50 videos for metadata. Google provides 10,000 units per day for free.

== Screenshots ==

1. Channel management — add and configure YouTube channels with sync rules.
2. Playlist management — sync individual playlists independently.
3. Sync rule editor — set schedules, conditions, and actions per rule.
4. Synced video list — all imported videos with status and metadata.
5. Video detail — metabox showing YouTube metadata alongside the post editor.

== Changelog ==

= 1.0.0 =
* Initial release.
* Import videos from YouTube channels and playlists.
* Flexible sync rules with schedule, conditions, and action options.
* Per-rule sync history (last synced, next run, error log).
* Thumbnail download and featured image assignment.
* Archive page support for videos, channels, and playlists.
* Delete-on-uninstall option.
