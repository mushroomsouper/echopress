# EchoPress installer

EchoPress ships with both a web-based wizard (`/install/`) and a CLI installer. Use whichever fits your hosting environment.

```
php installer/install.php
```

What the script does:

1. Prompts for high-level settings (site name, canonical URL, contact email, preferred database driver, and newsletter mail driver).
2. Writes a `.env` file with those answers so `config/app.php` can read them.
3. Ensures the SQLite database directory exists (or records the MySQL credentials).
4. Runs the SQL migrations in `installer/migrations/<driver>` via `installer/migrate.php`.
5. Drops `storage/installer.lock` to prevent accidental re-installs.

You can re-run the installer at any time by deleting `storage/installer.lock`.

### Running migrations manually

```
php installer/migrate.php --driver=sqlite
php installer/migrate.php --driver=mysql
```

Migrations are plain SQL files sorted alphabetically per driver. Add future migrations to the relevant folder (e.g., `installer/migrations/mysql/002_add_new_table.sql`) and rerun the migrate script.

### Customising defaults

- Edit `config/app.php` (or override values via `.env`) to change artist metadata, SMTP credentials, or paste a default analytics snippet.
- Place release zips inside `updates/packages/` to make them visible to the `EchoPressUpdater` helper.
- Keep `storage/` writable—the installer, SQLite, and future updater all depend on it.
