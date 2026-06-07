=== YouSync Pro ===
Contributors: martincipriano
Tags: youtube, video, sync, channel, playlist
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

YouSync Pro — advanced YouTube sync for WordPress. Recurring schedules, metadata updates, conditional filters, and video protection.

== Description ==

YouSync Pro extends the free YouSync plugin with advanced sync controls for power users and agencies.

**Everything in the free version, plus:**

* **Recurring Schedules** — sync automatically on hourly, daily, weekly, monthly, or custom intervals via WP-Cron
* **More Sync Actions** — update channel metadata, playlist metadata, and video metadata (all or non-modified only)
* **Sync Conditions & Filters** — filter what gets imported or updated using conditions on title, description, tags, duration, view count, published date, and more
* **Video Protection** — mark individual videos to prevent YouSync from overwriting your manual edits during future syncs

**All free features included:**

* Import videos from YouTube channels or playlists as a custom post type
* Automatic thumbnail import and featured image assignment
* Per-rule sync history with last-synced time and error log
* Channels and playlists managed through a familiar taxonomy-style admin interface
* Optional archive pages for videos, channels, and playlists
* "Delete all data on uninstall" option — your data stays unless you ask for it to be removed

**Requires a Google API key** with the YouTube Data API v3 enabled. Calls are made directly from your server to Google's API using your key.

**Requires an active YouSync Pro license.** Purchase at [wpbuoy.com](https://wpbuoy.com/plugins/yousync/). Pro features are disabled if the license is inactive — the plugin continues to work as the free version.

== Installation ==

1. Upload the `yousync-pro` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **Videos → License** and enter your YouSync Pro license key.
4. Go to **Videos → Settings** and enter your Google API key.
5. Go to **Videos → Channels** and add your first YouTube channel.
6. Set up a sync rule on the channel to start importing videos.

**Getting a Google API key:**

1. Visit [Google Cloud Console](https://console.cloud.google.com/).
2. Create a project and enable the **YouTube Data API v3**.
3. Generate an API key under **Credentials**.
4. Copy the key into YouSync → Settings → Google API Key.

== Frequently Asked Questions ==

= Do I need the free YouSync plugin too? =

No. YouSync Pro is a standalone plugin — it includes everything from the free version. Do not run both plugins at the same time.

= What happens if my license expires? =

Pro features (recurring schedules, metadata update actions, conditions, and video protection) are automatically paused. The plugin continues to work as the free version — syncing new videos with the "once" schedule and basic actions. Renew your license to restore pro features.

= Where do the synced videos go? =

Videos are saved as the `yousync_videos` custom post type. You can find them under **Videos** in the WordPress admin.

= Does YouSync Pro upload videos to my site? =

No. YouSync Pro imports metadata (title, description, thumbnail URL, view counts, etc.) and stores it in WordPress. Video files stay on YouTube. Thumbnails are optionally downloaded as WordPress attachments.

= Is WP Cron reliable? =

WP Cron runs when your site receives a visitor. On low-traffic sites, scheduled syncs may be delayed. For more reliable scheduling, consider using a real cron job to trigger `wp-cron.php` on your server.

= What happens to my data if I uninstall YouSync Pro? =

By default, your videos, channels, playlists, and settings are kept even after uninstalling. If you want everything removed, enable **Remove all YouSync data when the plugin is deleted** under Videos → Settings → Advanced before deleting the plugin.

= How many API quota units does YouSync Pro use? =

Each sync uses 1 quota unit per API call. Importing videos from a channel requires 2 calls minimum (1 for channel data, 1+ for playlist items), plus 1 call per 50 videos for metadata. Google provides 10,000 units per day for free.

== Screenshots ==

1. Channel management — add and configure YouTube channels with sync rules.
2. Playlist management — sync individual playlists independently.
3. Sync rule editor — set recurring schedules, conditions, and actions per rule.
4. Synced video list — all imported videos with status and metadata.
5. Video detail — metabox showing YouTube metadata and video protection toggle.

== Changelog ==

= 1.0.0 =
* Initial release.
* Import videos from YouTube channels and playlists.
* Recurring sync schedules (hourly, daily, weekly, monthly, custom).
* Full metadata update actions for channels, playlists, and videos.
* Sync conditions and filters on title, description, tags, duration, view count, and more.
* Video protection — prevent YouSync from overwriting manual edits.
* Per-rule sync history (last synced, next run, error log).
* Thumbnail download and featured image assignment.
* Archive page support for videos, channels, and playlists.
* Delete-on-uninstall option.
