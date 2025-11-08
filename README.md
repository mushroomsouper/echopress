# EchoPress

EchoPress is a portable, installer-driven platform for independent musicians. Drop the code onto any standard host, run the web wizard, and launch a full site—albums, playlists, blog, contact, newsletter, analytics embed—without touching code or the command line.

## Features

- **Zero-code installer** – Upload the repo, visit `/install/`, and a guided wizard checks requirements, writes `.env`, runs migrations, and downloads a bundled FFmpeg build so audio tools work even on locked-down shared hosts. (CLI installer available via `php installer/install.php`.)
- **Content publishing** – Manage albums, playlists, videos, blog posts, bios, appearances, and downloadable bundles directly from the PHP admin panel.
- **Newsletter engine** – Built-in subscriber database with throttling, preference management, and delivery through PHP `mail()`, SMTP, or a custom webhook (e.g., SendGrid/Mailchimp API).
- **Analytics embed** – Paste any tracking snippet (Google Analytics, Matomo, Plausible, etc.) in Admin ➜ Analytics Embed and EchoPress injects it across the public site.
- **Portable database support** – Ships with SQLite by default and can switch to MySQL/MariaDB via config/installer prompts.
- **Bundled FFmpeg pipeline** – Audio conversion, streaming previews, and download packages work out-of-the-box thanks to the auto-downloaded FFmpeg binary stored under `storage/tools/bin`.
- **Installer-safe structure** – The public docroot is the `web/` directory, keeping configuration (`config/`), storage, and installer logic outside the exposed path.

## Installation

### Option A – Web installer (recommended)
1. Upload or clone the repository so that the domain’s document root points to the `web/` directory.
2. Visit `https://your-domain/install/` in a browser. Provide your site name, URL, contact email, timezone, database choice (SQLite or MySQL credentials), newsletter delivery (mail/SMTP/webhook), and let the wizard download FFmpeg.
3. Click “Install EchoPress.” The wizard writes `.env`, runs migrations, prepares the tools directory, and drops `storage/installer.lock`. You’ll be redirected to `/admin/login.php` to start creating content.

### Option B – CLI installer
1. SSH into the server, `cd` into the project, and run `php installer/install.php`.
2. Answer the same prompts as the web wizard. The script writes `.env`, runs migrations, downloads FFmpeg, and creates `storage/installer.lock`.
3. Point the web server at the `web/` directory and log into `/admin/`.

If you ever need to reinstall, delete `storage/installer.lock` and rerun either installer. Detailed steps live in `INSTALL.txt` and the full platform documentation lives in `web/README.md`.
