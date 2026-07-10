=== WPBuoy Video Sync ===
Contributors: martincipriano
Tags: youtube, video, sync, playlist, import
Requires at least: 6.0
Tested up to: 7.0
Stable tag: 2.7.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sync new videos, playlists, and channel data from a YouTube channel into WordPress posts, with thumbnails and metadata.

== Description ==

WPBuoy Video Sync imports content from a YouTube channel into WordPress. Point it at a channel, choose what to sync and which post type to save it as, and WPBuoy Video Sync creates posts complete with titles, descriptions, thumbnails, view counts, and other YouTube metadata.

Every sync runs once, immediately after you save the rule — so you stay in control of when content is imported.

**Features**

* Sync from a YouTube channel
* Three sync actions: import **new videos**, import **new playlists**, or import the **channel** itself
* Save synced items as **any post type** you choose (Posts, or a custom post type)
* **On-demand imports** — each rule runs immediately when you save it, so you decide exactly when content is imported
* **Quota estimation** — see how many YouTube Data API units a sync will use before you run it
* **Thumbnails** shown as the featured image when none is set; channel profile pictures and banners are downloaded into your media library
* **Sync history** with the last-synced time and a per-rule error log
* Stores all YouTube data in standard post meta (`_wpbyvs_*`) for easy use in your theme or queries
* **`[video-sync]` shortcode** and **three Gutenberg blocks** (Field, Image, Embed) to display synced titles, descriptions, stats, thumbnails, and the YouTube player anywhere on your site — no code required
* Optional "remove all data on uninstall" setting — your content stays unless you ask for it to be removed

**Requires a Google API key** with the YouTube Data API v3 enabled. Requests are made directly from your server to Google using your own key; no data passes through any third-party service.

== Installation ==

1. Upload the `wby-video-sync` folder to the `/wp-content/plugins/` directory, or install it from **Plugins → Add New**.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **WPBuoy Video Sync → Settings** and enter your Google API key.
4. Go to **WPBuoy Video Sync → Channels** and add a YouTube channel.
5. Add a sync rule to the channel — pick an action and a destination post type — then save to import.

**Getting a Google API key**

1. Visit [Google Cloud Console](https://console.cloud.google.com/).
2. Create a project and enable the **YouTube Data API v3**.
3. Generate an API key under **Credentials**.
4. Paste the key into **WPBuoy Video Sync → Settings**.

== Frequently Asked Questions ==

= Does WPBuoy Video Sync upload video files to my site? =

No. WPBuoy Video Sync imports metadata (title, description, thumbnail URL, view counts, and so on) and saves it in WordPress. The video files stay on YouTube. Channel profile pictures and banners are downloaded into your media library; video and playlist thumbnails are displayed from the URLs YouTube serves them at.

= Where do synced items go? =

You choose. Each sync rule has a destination post type, so videos, playlists, and channels are saved as the post type you select — Posts or any public custom post type.

= How often does it sync? =

Every rule runs once, immediately after you save it. To import again later — for example after publishing new videos — re-enable the rule and save, and it picks up anything new.

= Why do I need a Google API key? =

YouTube data is fetched from the official YouTube Data API v3, which requires a key. The key is yours and stays on your server; WPBuoy Video Sync never sends your data to a third party.

= How much API quota does a sync use? =

Each API call costs 1 quota unit. Importing from a channel needs at least 2 calls (channel data plus playlist items), and roughly 1 more call per 50 videos for metadata. Google provides 10,000 free units per day. WPBuoy Video Sync shows an estimate before you run a sync.

= What happens to my data if I uninstall WPBuoy Video Sync? =

By default your synced posts and settings are kept. If you want everything removed, enable **Remove all WPBuoy Video Sync data when the plugin is deleted** in **WPBuoy Video Sync → Settings** before deleting the plugin.

== External services ==

WPBuoy Video Sync connects to the **YouTube Data API v3**, a service provided by Google, to fetch the public metadata it imports into WordPress.

**What it is used for:** retrieving public information about the YouTube channels, playlists, and videos you choose to sync — such as titles, descriptions, thumbnail URLs, view counts, publish dates, and channel/playlist/video IDs.

**What data is sent and when:** requests are made only when you save a channel, run a sync, or use the pre-sync quota estimate. Each request is sent directly from your own server to Google's API endpoint (`https://www.googleapis.com/youtube/v3/`) and includes the Google API key you provide and the channel, playlist, or video identifier being synced. No personal data about your site's visitors or users is sent, and no data passes through any service operated by the plugin author or a third party.

**Images:** channel profile pictures and banners are downloaded from the URLs the YouTube Data API returns and stored locally in your media library. Video and playlist thumbnail URLs returned by the API point to YouTube's own image host (`i.ytimg.com`) and are stored and referenced as-is, in the same way YouTube serves them — the plugin does not host, offload, or proxy any images from its author's servers.

**Video player:** the optional Embed block and the `[video-sync type="embed"]` shortcode display YouTube's own embedded player in an iframe pointed at `https://www.youtube.com/embed/`, the same way pasting a YouTube link into the block editor does via WordPress's built-in oEmbed support. No plugin code or data is involved in loading the player — the visitor's browser requests it directly from YouTube.

This service is provided by Google. By using it you agree to Google's terms and privacy policy:

* YouTube API Services Terms of Service: https://developers.google.com/youtube/terms/api-services-terms-of-service
* Google Privacy Policy: https://policies.google.com/privacy

== Using synced data on your site ==

Every synced video, playlist, and channel is a regular WordPress post, so you can already query and template it however you like. For everyone else, the `[video-sync]` shortcode and three Gutenberg blocks put the same data on the page without writing any code.

**Shortcode**

`[video-sync id="123" field="title"]`
`[video-sync id="123" field="view_count"]`
`[video-sync id="123" field="thumbnail" size="maxres"]`
`[video-sync id="123" type="embed"]`

`id` is the post ID of the synced video, playlist, or channel post. Available `field` values depend on the post: `title`, `description`, and, for videos, `video_url`, `published_date`, `duration`, `view_count`, `like_count`, `comment_count`; for playlists, `playlist_video_count`; for channels, `subscriber_count`, `video_count`. Image fields are `thumbnail` (video), `playlist_thumbnail`, `profile_photo`, and `banner_image` (channel).

**Gutenberg blocks**

Add a **Video Sync Field**, **Video Sync Image**, or **Video Sync Embed** block from the block inserter (search "Video Sync"). Each block reads from the post it's placed on by default — drop an Embed block into a synced video post's content and it plays that video — or pick a different post from the block's settings panel.

== For Developers ==

WPBuoy Video Sync fires action hooks after each item's metadata is saved, so you can run your own code whenever a video, playlist, or channel is synced. Each hook fires on both the initial import and every re-sync, after all metadata has been written. The synced data is passed to the hook, so you can identify or filter items without making another API call.

= wpbyvs_video_synced =

Fires after a video's metadata is saved.

`do_action( 'wpbyvs_video_synced', int $post_id, array $video_data, string $source_type, int $source_id );`

* `$post_id` — the video post ID.
* `$video_data` — video data from the YouTube Data API (title, description, view_count, like_count, comment_count, video_id, and more).
* `$source_type` — how it was synced: `channel`, `playlist`, or `video`.
* `$source_id` — the source term ID the video was synced from.

= wpbyvs_playlist_synced =

Fires after a playlist's metadata is saved.

`do_action( 'wpbyvs_playlist_synced', int $post_id, array $playlist_data, string $channel_id );`

* `$post_id` — the playlist post ID.
* `$playlist_data` — playlist data from the YouTube Data API.
* `$channel_id` — the source channel ID the playlist belongs to.

= wpbyvs_channel_synced =

Fires after a channel's metadata is saved.

`do_action( 'wpbyvs_channel_synced', int $post_id, array $channel_data, string $channel_id );`

* `$post_id` — the channel post ID.
* `$channel_data` — channel data from the YouTube Data API.
* `$channel_id` — the YouTube channel ID.

= Example =

`add_action( 'wpbyvs_video_synced', function ( $post_id, $video_data ) {
    // Runs each time a video is synced.
    error_log( sprintf( 'Synced video %d (%s)', $post_id, $video_data['video_id'] ?? '' ) );
}, 10, 2 );`

= wpbyvs_metabox_tabs =

Add your own tab to the video, playlist, or channel metabox on the post edit screen. Return an array of tabs, each with a `slug`, a `label`, and a `render` callback that echoes the panel's content.

`apply_filters( 'wpbyvs_metabox_tabs', array $tabs, string $type, int $post_id );`

* `$tabs` — list of tabs to add. Each: `[ 'slug' => string, 'label' => string, 'render' => callable( int $post_id ) ]`.
* `$type` — which metabox is rendering: `video`, `playlist`, or `channel`.
* `$post_id` — the current post ID.

Example — add a tab only to the video metabox:

`add_filter( 'wpbyvs_metabox_tabs', function ( $tabs, $type, $post_id ) {
    if ( 'video' !== $type ) {
        return $tabs;
    }
    $tabs[] = array(
        'slug'   => 'my_tab',
        'label'  => 'My Tab',
        'render' => function ( $post_id ) {
            echo '<p>Custom content for post ' . (int) $post_id . '</p>';
        },
    );
    return $tabs;
}, 10, 3 );`

== Screenshots ==

1. Channels page — add a YouTube channel and configure its sync rules.
2. Sync rule — choose an action, set an items-per-run limit, and pick a destination post type.
3. Sync history — a per-channel log of what was synced and any errors.
4. Synced post — a metabox showing the imported YouTube metadata and thumbnails.

== Changelog ==

= 2.7.0 =
* New: `[video-sync]` shortcode and three Gutenberg blocks (Field, Image, Embed) to display synced titles, descriptions, stats, thumbnails, and the YouTube player anywhere on your site.
* Fixed: Uninstalling with "remove all data" enabled now also removes synced playlist posts (previously only videos and channels were detected).

= 2.6.0 =
* New: Channel profile pictures and banners are now downloaded into the media library and attached to the channel post, instead of being referenced from YouTube's CDN.
* Improved: Uninstall now also removes the channel configuration, per-channel sync history, plugin transients, and — when the "remove all data" setting is enabled — all synced posts and their attachments.
* Improved: Internal code and documentation cleanup.

= 2.5.1 =
* Standardized internal code naming for consistency across WPBuoy plugins.
* Simplified the single-channel architecture and removed unused legacy code. No changes to features or settings.

= 2.4.0 =
* New: `wpbyvs_metabox_tabs` filter for adding custom tabs to the video, playlist, and channel metaboxes.

= 2.3.0 =
* New: Action hooks fire after each video, playlist, and channel is synced — `wpbyvs_video_synced`, `wpbyvs_playlist_synced`, `wpbyvs_channel_synced`.

= 2.2.5 =
* Maintenance release.

= 2.2.4 =
* Fixed: The channel History badge now counts only unread sync errors and clears when you open the History tab.

= 2.2.3 =
* New: Option to use the YouTube thumbnail as the post featured image (enabled by default) — disable it to keep your own featured images.
* Fixed: Channel posts now reliably display their profile image (removed image sideloading that failed on extension-less YouTube CDN URLs).
* Improved: Documented the YouTube image CDN in the readme's External services section, per WordPress.org review feedback.

= 2.2.2 =
* Improved: Added an "External services" section to the readme documenting the YouTube Data API v3 usage, in line with WordPress.org plugin guidelines.

= 2.2.1 =
* Fixed: Sync history status icons now render as inline SVG and no longer load an external font, removing the Google Fonts (Material Icons) dependency for full WordPress.org compliance.

= 2.2.0 =
* New: Paste a channel URL or @handle in the Channel field — WPBuoy Video Sync resolves it to the channel ID automatically (supports channel URLs, @handles, /user/ and /c/ URLs, and video links).
* Improved: The Settings page shows a generic "Settings saved." notice and no longer re-validates the API key when it hasn't changed.

= 2.1.0 =
* New: In-plugin Help tabs linking to the WPBuoy Video Sync knowledge base on every admin screen.
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
