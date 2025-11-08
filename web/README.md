# EchoPress Platform

EchoPress turns the original aboyandhiscomputer.music codebase into a configurable artist portal. Everything under `web/` is still the public document root, but the repository now layers an installer, migration tooling, and configuration helpers so you can deploy the site for any artist without touching the live production copy.

## Layout

```
web/                # public document root to point your web server at
config/app.php      # platform configuration (copied from app.example.php)
installer/          # CLI installer + SQL migrations
storage/            # writable runtime storage (SQLite database, installer lock)
updates/            # drop release packages here for future one-click updates
bootstrap/          # shared helpers (config, database, updater)
```

## Quick start

1. Copy this repository to your target host (or clone it locally) so that the document root maps to `web/`.
2. Visit `https://your-domain/install/` in a browser. The web installer checks requirements, collects site/database/mail settings, writes `.env`, runs migrations, and locks itself.
3. (Optional) You can still run the CLI installer (`php installer/install.php`) if you prefer shell access.
4. Configure your web server to serve the `web/` directory. The included `web/nginx.conf.sample` is generic—replace `example.com` with your own domain.
5. Log into `/admin/` using your existing credentials or migrate users from the source site. Content editors can start rebuilding albums, playlists, and blog posts directly from the admin area once the database is seeded.

## Configuration

`config/app.php` centralises everything that used to be hard-coded:

- `site.*` — name, tagline, base URL, timezone, and theme colours.
- `artist.*` — the primary artist name, default bio placeholder, and optional social link list rendered in the header.
- `contact.*` — destination email addresses and reCAPTCHA keys for forms.
- `newsletter.*` — default From/Reply-To values plus the mail transport. SMTP credentials live under `newsletter.mailer.smtp.*`; set `newsletter.mailer.driver` to `smtp` when you want PHPMailer to send through your provider, otherwise the platform falls back to PHP’s `mail()`.
- `analytics.embed_code` — optional analytics snippet (Google, Matomo, Plausible, etc.). Drop the same embed in `config/app.php` or use the Admin ➜ Analytics Embed page to manage it.
- `database.*` — default connection and credentials for both SQLite and MySQL. The admin dashboard, the homepage, and the new CLI installer all read from here.
- `updates.*` — release channel and the local packages directory used by the updater stub in `bootstrap/updater.php`.

Copy `config/app.php` to `config/app.example.php` whenever you want to reset; the installer will never overwrite your customised configuration.

## Database & migrations

Two migration sets now exist:

- `installer/migrations/mysql/001_create_core_tables.sql` mirrors the original MariaDB schema (with artist-neutral defaults).
- `installer/migrations/sqlite/001_create_core_tables.sql` reproduces the same tables with SQLite-friendly types.

Run the migrations at any time with `php installer/migrate.php --driver=mysql` (or omit the flag to use the configured default). The script feeds each SQL file into the PDO connection returned by `echopress_database()`.

### SQLite by default

Out of the box, EchoPress stores everything in `storage/database/echopress.sqlite` so the platform feels portable—no manual database provisioning is required for local demos. The admin panel and public pages all run through the PDO abstraction in `bootstrap/database.php`, so switching to MySQL is just a matter of updating `.env`/`config/app.php` and re-running the migrations.

## Newsletter delivery

The newsletter signup (`/newsletter/`) and automation scripts now use the shared mailer in `web/includes/mailer.php`. The driver is selected via `newsletter.mailer.driver`:

- `mail` – PHP’s built-in `mail()` (works on shared hosts but lacks TLS/auth).
- `smtp` – configure host, port, username, password, and encryption; EchoPress sends via PHPMailer.
- `webhook` – post each newsletter payload (subject, HTML/text bodies, recipients, etc.) to a third-party endpoint such as SendGrid, Mailchimp, or a custom serverless function. Set `newsletter.mailer.webhook.url` plus optional `secret`, `headers`, and `method` and handle the JSON payload on your side.

The welcome email and the `send_blog_newsletters.php` cron script reuse whichever transport you choose, automatically attaching List-Unsubscribe headers and the From/Reply-To values defined in `config/app.php`.

## Web installer vs CLI installer

- **Web installer**: Navigate to `/install/` after uploading the files. It guides non-technical users through prerequisite checks, site settings, database selection (SQLite or MySQL), and newsletter delivery (mail/SMTP/webhook). When it finishes, it writes `.env`, runs migrations, and locks itself.
- **CLI installer**: `php installer/install.php` performs the same steps via prompts. Use this when you have SSH access or want to script deployments.
- Both installers drop a lock file at `storage/installer.lock`. Delete it if you ever need to rerun the setup wizard.

## Audio toolchain (FFmpeg)

EchoPress relies on FFmpeg for generating MP3/FLAC derivatives and tagging downloads. The installer automatically downloads a static FFmpeg build into `storage/tools/bin`. If your host blocks outbound downloads, upload your own `ffmpeg` binary into that directory or run `php tools/install_tools.php` once network access is available. `echopress_tool_path('ffmpeg')` ensures all admin scripts use the bundled binary first, so shared hosting users don’t need shell access once the tool is present.

The spam guard in `includes/newsletter_guard.php` pulls reCAPTCHA keys from config, so you no longer have to define `RECAPTCHA_*` constants manually.

## Analytics

Analytics snippets are injected as-is—paste the script tags from your provider (Google, Matomo, Plausible, etc.) into Admin ➜ Analytics Embed or set `analytics.embed_code` in `config/app.php`. Advanced users can continue to manage reporting from their analytics provider’s own dashboard.

## Updates & release packages

`bootstrap/updater.php` introduces the `EchoPressUpdater` helper. It doesn’t yet download packages, but it centralises release metadata (`updates.*` in config) and enumerates anything dropped into `updates/packages`. Future work can hook admin UI buttons to the `EchoPressUpdater` stub to deliver one-click update flows without touching the live site.

## Notes for agents & operators

- `web/` is safe to deploy anywhere; all environment-specific details now live in `.env`/`config/app.php`.
- Keep `storage/` writable for SQLite databases, the analytics embed snippet, and the installer lock file.
- Never edit files directly under `updates/packages`. Treat that directory as a staging area for signed release zips.
- If you need to reset the installer, delete `storage/installer.lock` and re-run `php installer/install.php`.

For day-to-day administration, the existing PHP admin panel continues to manage albums, playlists, and blog posts—only now it draws its defaults from the EchoPress configuration instead of the original artist’s branding.
