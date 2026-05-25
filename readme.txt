=== Pulse Press ===
Contributors: karmatosed
Tags: slack, github, ai, drafts, digest
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn Slack, GitHub, and pasted content into formatted WordPress draft posts using WordPress AI.

== Description ==

Pulse Press creates draft posts from:

* **Slack** — User OAuth, channel history, threads, and rich formatting for team and meeting digests
* **GitHub** — OAuth and release notes from configured repositories
* **Paste** — Summarize channel backscroll or notes when API access is unavailable

**Content types:** team updates, meeting updates, release announcements, What's new in…, and agendas (dev chat, release party, and more).

Requires WordPress 6.9+ with WordPress AI configured under Settings → AI (7.0+ recommended).

== Installation ==

1. Install the plugin to `wp-content/plugins/pulse-press`
2. Run `npm install && npm run build` in the plugin directory if admin assets are missing
3. Activate through the Plugins screen
4. Open **Pulse Press** in the admin menu
5. Configure Slack (and optional GitHub) on the Connection tab
6. Configure WordPress AI under Settings → AI

== Frequently Asked Questions ==

= Does this copy Slack AI Recaps? =

No. Slack does not expose native Recap text via API. Pulse Press fetches channel history (or accepts paste) and summarizes via WordPress AI.

= What Slack scopes are needed? =

channels:read, groups:read, channels:history, groups:history, users:read, and files:read for image attachments.

== Changelog ==

= 1.1.0 =
* GitHub OAuth and release flows
* Additional content types (release, what's new in, agendas)
* Rich Slack message formatting and template system
* React admin app

= 1.0.0 =
* Initial release: Slack connection, team/meeting digests, paste, cron
