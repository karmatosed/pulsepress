=== Pulse Press ===
Contributors: pulsepress
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn Slack, GitHub, and pasted content into formatted WordPress draft posts using WordPress AI.

== Description ==

Pulse Press creates draft posts from:

* **Connected Slack** — user OAuth, fixed channel list, daily or weekly team digests
* **Paste** — summarize and format Slack recaps or meeting backscroll when API access is unavailable

Content types: **Team updates** and **Meeting updates**.

Requires WordPress 7.0+ with an AI provider configured under Settings → AI.

== Installation ==

1. Upload the plugin to `wp-content/plugins/pulse-press`
2. Activate through the Plugins screen
3. Go to Settings → Pulse Press
4. Configure Slack app credentials and connect
5. Configure WordPress AI under Settings → AI

== Frequently Asked Questions ==

= Does this copy Slack AI Recaps? =

No. Slack does not expose native Recap text via API. Pulse Press fetches channel history (or accepts paste) and summarizes via WordPress AI.

== Changelog ==

= 1.0.0 =
* Initial release.
