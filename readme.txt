=== Pulse Press ===
Contributors: karmatosed
Tags: slack, github, ai, drafts, digest
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn Slack, GitHub, and pasted content into formatted WordPress draft posts using WordPress AI.

== Description ==

Pulse Press creates draft posts from:

* **Slack** — User OAuth, channel history, threads, and rich formatting for team and meeting digests (direct messages are not supported)
* **GitHub** — OAuth and release notes from configured repositories
* **Paste** — Summarize channel backscroll or notes when API access is unavailable

**Content types:** team updates, meeting updates, release announcements, What's new in…, and agendas (dev chat, release party, and more).

Requires WordPress 7.0+ with WordPress AI configured under Settings → AI.

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

channels:read, groups:read, channels:history, groups:history, users:read, and files:read for image attachments. Direct message scopes are not used.

= Does uninstall remove everything? =

Uninstall removes plugin settings, scheduled cron events, Pulse taxonomy terms, and `_pulse_press_*` post meta. Existing draft posts created by the plugin are not deleted.

== External services ==

This plugin connects to third-party services only when configured by a site administrator:

= Slack =

Used to OAuth-connect a workspace and fetch public and private channel history, threads, and file metadata for digest drafts.

Data sent: OAuth authorization codes and tokens, channel IDs, message content, user IDs from messages, and file metadata as needed for formatting.

Service provider: Slack Technologies, LLC.

Terms of service: https://slack.com/terms-of-service

Privacy policy: https://slack.com/privacy-policy

= GitHub =

Used to OAuth-connect an account and fetch release notes from repositories you configure.

Data sent: OAuth authorization codes and tokens, repository names, and release metadata and bodies.

Service provider: GitHub, Inc.

Terms of service: https://docs.github.com/en/site-policy/github-terms/github-terms-of-service

Privacy policy: https://docs.github.com/en/site-policy/privacy-policies/github-privacy-statement

= WordPress AI =

Used to summarize fetched or pasted content into structured draft posts.

Data sent: Slack message text, GitHub release text, pasted content, and editorial prompts to the AI provider configured under Settings → AI on your site. The destination provider depends on your WordPress AI configuration.

See WordPress AI documentation and your chosen provider's terms for details.

== Development ==

Admin JavaScript and CSS are built with `@wordpress/scripts`. Source files live in `src/admin/`; committed build artifacts are in `build/`.

Source repository: https://github.com/karmatosed/pulsepress

== Changelog ==

= 1.1.1 =
* Block Slack direct message access; channel-only digests
* Plugin Directory review compliance (external services readme, i18n, uninstall cleanup, license file)

= 1.1.0 =
* GitHub OAuth and release flows
* Additional content types (release, what's new in, agendas)
* Rich Slack message formatting and template system
* React admin app

= 1.0.0 =
* Initial release: Slack connection, team/meeting digests, paste, cron
