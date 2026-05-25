# Pulse Press

WordPress plugin that turns **Slack**, **GitHub**, and **pasted notes** into formatted **draft posts** using [WordPress AI](https://wordpress.org/documentation/article/wordpress-ai/).

Repository: [github.com/karmatosed/pulsepress](https://github.com/karmatosed/pulsepress)

## Features

- **Slack** — User OAuth, channel selection, rich message fetch (attachments, blocks, threads)
- **GitHub** — OAuth and release notes from configured `owner/repo` lists
- **Paste** — Summarize backscroll when API access is not available
- **Scheduled digests** — Cron for team updates (daily or weekly)
- **Block templates** — Mustache-style placeholders rendered to Gutenberg block markup
- **Content types** — Team updates, meeting updates, release announcements, What’s new in…, agendas (with subtypes)

Drafts are created as standard WordPress posts, tagged with a `pulse_press_type` taxonomy, and optional categories per type.

## Requirements

- WordPress **6.9+** (AI features: **7.0+** recommended)
- PHP **7.4+**
- WordPress AI configured under **Settings → AI**
- For Slack: a Slack app with user token scopes (see below)

## Installation

### From a release / clone

1. Copy or symlink this repo into `wp-content/plugins/pulse-press` (folder name must be `pulse-press` for WordPress).
2. Activate **Pulse Press** on the Plugins screen.
3. Open **Pulse Press** in the admin menu.

### Build admin UI (required for the settings app)

```bash
cd wp-content/plugins/pulse-press   # or your clone path
npm install
npm run build
```

Committed `build/` assets are included so production installs work without Node; rebuild after changing `src/admin/`.

## Configuration

1. **Settings → AI** — Connect an AI provider.
2. **Pulse Press → Connection** — Slack app Client ID/Secret, OAuth redirect URL (copy into Slack app), connect Slack, pick team channels.
3. Optional: GitHub OAuth app credentials and repositories (`owner/repo`, one per line).
4. **Posts** — Draft author, categories per content type.
5. **Templates** — Edit block markup templates and preview with sample data.
6. **Run** — Manual team/meeting digests, GitHub/release flows, paste tab for one-off drafts.

### Slack app (summary)

- Create a Slack app at [api.slack.com](https://api.slack.com/apps)
- **OAuth & Permissions → Redirect URLs**: use the exact URL shown on the Connection tab
- **User token scopes**: `channels:read`, `groups:read`, `channels:history`, `groups:history`, `users:read`, `files:read` (for screenshots in digests)

## Local development (WordPress Studio)

This plugin is often developed via symlink from a [WordPress Studio](https://developer.wordpress.com/studio/) site:

```bash
ln -sf ~/repos/pulsepress /path/to/site/wp-content/plugins/pulse-press
```

Work in `~/repos/pulsepress`; the Studio site loads the plugin through the symlink.

```bash
npm run start   # watch admin JS/CSS
npm run build   # production assets
```

Use `studio wp` (not bare `wp`) when the site runs in Studio — see your site’s `STUDIO.md`.

## Project layout

```
pulse-press.php          # Bootstrap
includes/
  class-plugin.php       # Hooks
  class-settings.php     # Options (pulse_press_settings)
  admin/                 # Menu, OAuth, admin-post actions
  ai/                    # Prompts + AI gateway
  github/                # GitHub OAuth + API
  slack/                 # Slack OAuth, API, message formatter
  posts/                 # Pipeline, drafts, post types
  templates/             # Template registry + renderer
  rest/                  # REST config + templates API
  jobs/                  # Digest cron
src/admin/               # React admin (wp-scripts)
templates/               # Default block markup (*.html)
build/                   # Compiled admin assets
```

PHP namespace: `Pulse_Press\` (autoloaded from `includes/`).

## REST API

- `GET/POST /wp-json/pulse-press/v1/config` — Admin config (capabilities required)
- `GET/POST /wp-json/pulse-press/v1/templates` — Template load/save/preview

Most actions use `admin-post.php` with nonces (`pulse_press_action_*`).

## Related projects

**Pulse Release** is a separate plugin (release-only fork). This repo is **Pulse Press** only.

## License

GPL-2.0-or-later. See [readme.txt](readme.txt) for WordPress.org-style metadata.
