# EchoPress operator notes

- The live production site remains untouched under `/srv/internum/www/aboyandhiscomputer.music`. Do **not** modify it. All EchoPress work happens inside `/srv/internum/echopress`.
- Point your web server at `web/`. All environment-specific data now lives outside the document root (`config/`, `.env`, `storage/`).
- To deploy a fresh instance:
  1. Copy this repo.
  2. Visit `/install/` in a browser (or run `php installer/install.php`) and answer the prompts (site name, URL, contact email, database + mail driver).
  3. Configure Nginx or Apache using the sample in `web/nginx.conf.sample`.
- Database access flows through `bootstrap/database.php`. Switch drivers by editing `.env`/`config/app.php` and re-running `php installer/migrate.php --driver=<sqlite|mysql>`.
- Newsletter, contact, and blog automations all call the shared mailer in `web/includes/mailer.php`. Set SMTP credentals under `newsletter.mailer` in `config/app.php`.
- Analytics scripts are user-supplied. Point operators to Admin ➜ Analytics Embed so they can paste the snippet issued by Google Analytics, Matomo, Plausible, etc.
- Newsletter delivery defaults to the built-in SMTP/`mail()` flow, but you can switch `newsletter.mailer.driver` to `webhook` to hand off sending to an external provider. The webhook receives JSON with the full message payload.
- The installer attempts to download FFmpeg automatically. If outbound HTTP is blocked, run `php tools/install_tools.php ffmpeg` from SSH or upload a static FFmpeg binary into `storage/tools/bin/` so the admin audio exports continue to work.
- Release packages should be staged in `updates/packages/`. The `EchoPressUpdater` stub in `bootstrap/updater.php` already inventories that folder so we can wire a one-click updater later without touching production.
- Treat `storage/` as writable scratch space. It contains the default SQLite database, newsletter throttle tables, and the installer lock file. Don’t commit its contents to version control.
