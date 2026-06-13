=== YouSync ===
Contributors: martincipriano
Tags: youtube, video, sync, playlist, import
Requires at least: 6.0
Tested up to: 7.0
Stable tag: 2.2.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sync new videos, playlists, and channel data from a YouTube channel into WordPress posts, with thumbnails and metadata.

== Description ==

YouSync imports content from a YouTube channel into WordPress. Point it at a channel, choose what to sync and which post type to save it as, and YouSync creates posts complete with titles, descriptions, thumbnails, view counts, and other YouTube metadata.

Every sync runs once, immediately after you save the rule — so you stay in control of when content is imported.

**Free features**

* Sync from a single YouTube channel
* Three sync actions: import **new videos**, import **new playlists**, or import the **channel** itself
* Save synced items as **any post type** you choose (Posts, or a custom post type)
* **Run once** — each rule imports immediately when you save it
* **Quota estimation** — see how many YouTube Data API units a sync will use before you run it
* **Thumbnails** imported and used as the featured image when no image is set
* **Sync history** with the last-synced time and a per-rule error log
* Stores all YouTube data in standard post meta (`_yousync_*`) for easy use in your theme or queries
* Optional "remove all data on uninstall" setting — your content stays unless you ask for it to be removed

**Requires a Google API key** with the YouTube Data API v3 enabled. Requests are made directly from your server to Google using your own key; no data passes through any third-party service.

**Need more?**

[YouSync Pro](https://wpbuoy.com/product/yousync/) adds power-user automation:

* Unlimited channels
* Recurring schedules (hourly, daily, weekly, monthly, or a custom interval)
* Update metadata on existing posts, and update only the fields you choose
* Assign synced items to categories, tags, and custom taxonomy terms
* Map YouTube fields to custom meta keys
* Filter conditions to sync only items that match your rules
* Video protection to stop syncs from overwriting your manual edits
* Gutenberg blocks and shortcodes for video metadata

== Installation ==

1. Upload the `yousync` folder to the `/wp-content/plugins/` directory, or install it from **Plugins → Add New**.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **YouSync → Settings** and enter your Google API key.
4. Go to **YouSync → Channels** and add a YouTube channel.
5. Add a sync rule to the channel — pick an action and a destination post type — then save to import.

**Getting a Google API key**

1. Visit [Google Cloud Console](https://console.cloud.google.com/).
2. Create a project and enable the **YouTube Data API v3**.
3. Generate an API key under **Credentials**.
4. Paste the key into **YouSync → Settings**.

== Frequently Asked Questions ==

= Does YouSync upload video files to my site? =

No. YouSync imports metadata (title, description, thumbnail URL, view counts, and so on) and saves it in WordPress. The video files stay on YouTube. Thumbnails are optionally downloaded as WordPress attachments.

= Where do synced items go? =

You choose. Each sync rule has a destination post type, so videos, playlists, and channels are saved as the post type you select — Posts or any public custom post type.

= How often does it sync? =

In the free version every rule runs once, immediately after you save it. Recurring schedules (hourly, daily, weekly, monthly, or custom) are available in [YouSync Pro](https://wpbuoy.com/product/yousync/).

= Why do I need a Google API key? =

YouTube data is fetched from the official YouTube Data API v3, which requires a key. The key is yours and stays on your server; YouSync never sends your data to a third party.

= How much API quota does a sync use? =

Each API call costs 1 quota unit. Importing from a channel needs at least 2 calls (channel data plus playlist items), and roughly 1 more call per 50 videos for metadata. Google provides 10,000 free units per day. YouSync shows an estimate before you run a sync.

= What happens to my data if I uninstall YouSync? =

By default your synced posts and settings are kept. If you want everything removed, enable **Remove all YouSync data when the plugin is deleted** in **YouSync → Settings** before deleting the plugin.

== Screenshots ==

1. Channels page — add a YouTube channel and configure its sync rules.
2. Sync rule — choose an action, set an items-per-run limit, and pick a destination post type.
3. Sync history — a per-channel log of what was synced and any errors.
4. Synced post — a metabox showing the imported YouTube metadata and thumbnails.

== Changelog ==

= 2.2.0 =
* New: Paste a channel URL or @handle in the Channel field — YouSync resolves it to the channel ID automatically (supports channel URLs, @handles, /user/ and /c/ URLs, and video links).
* Improved: The Settings page shows a generic "Settings saved." notice and no longer re-validates the API key when it hasn't changed.

= 2.1.0 =
* New: In-plugin Help tabs linking to the YouSync knowledge base on every admin screen.
* New: Pro-only sync rules are preserved (not deleted) when the plugin runs as the free version, so re-activating Pro restores them.
* Improved: WordPress.org coding-standards compliance — prepared SQL statements, output escaping, and translator comments.
* Improved: Tested up to WordPress 7.0.

= 2.0.0 =
* Sync new videos, new playlists, or channel data from a YouTube channel.
* Choose any post type as the destination for synced items.
* Run-once sync with a pre-sync API quota estimate.
* Thumbnail import with featured-image fallback.
* Per-rule sync history and error logging.

== Upgrade Notice ==

= 2.0.0 =
Imports synced items as standard WordPress posts of your chosen post type, with run-once syncing and quota estimates.
