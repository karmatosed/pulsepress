# AI instructions — Pulse Press

WordPress plugin: Slack, GitHub, and paste → AI → Gutenberg draft posts.

Repository: https://github.com/karmatosed/pulsepress  
Install path: `wp-content/plugins/pulse-press` (symlink or copy)

## Stack

- **PHP 7.4+** — `declare(strict_types=1);`, namespace `Pulse_Press\`, WordPress coding standards
- **Admin UI** — React in `src/admin/`, built with `@wordpress/scripts` → `build/`
- **AI** — WordPress AI (`Pulse_Press\AI\AI_Gateway`), structured JSON via `Prompt_Factory`
- **No separate database** — `pulse_press_settings` option, post meta, taxonomy `pulse_press_type`

## Before changing admin JS

```bash
npm install && npm run build
```

If `build/admin.asset.php` is missing, the admin page shows an error notice.

## Key flows

1. **Slack digest** — `Slack\API_Client` + `Message_Formatter` → `Pipeline::run( $text, 'slack', $args )`
2. **Paste** — `admin-post` `paste_*` actions → same pipeline
3. **GitHub** — `GitHub\Release_Repository` → pipeline with type `release-update` etc.
4. **Cron** — `Jobs\Digest_Cron` for scheduled team updates

## Conventions

- Hooks over core edits; capability checks on admin/REST (`manage_options` / `edit_posts` as appropriate)
- Sanitize with WordPress APIs; escape on output
- OAuth secrets stored encrypted (`Crypto`)
- Templates: `templates/*.html` with `{{placeholders}}` and `{{#loops}}`; renderer in `Templates\Template_Renderer`
- Extend types via filter `pulse_press_post_types`

## Do not

- Commit `node_modules/`
- Break the `pulse-press` plugin directory name (WordPress expects it)
- Use bare `wp` on WordPress Studio sites — use `studio wp` in that environment
- Duplicate Pulse Release–only features here unless explicitly requested (separate plugin)

## Verify

```bash
php -l pulse-press.php
npm run build
# In WP: activate plugin, open Pulse Press admin, test connection + paste draft
```
