# XAMPP and Hostinger (MariaDB)

WealthDesk is Laravel 12 / PHP 8.2+ / MariaDB. Blade, ordinary CSS and browser JavaScript need no npm build. PHP needs PDO MySQL, mbstring, OpenSSL, fileinfo, XML, ctype, curl, DOM, session and tokenizer. SQLite extensions are needed only for the legacy import and default automated tests.

## Folder layout

Like the existing Fibro application, the project contains app/, bootstrap/, config/, database/, public/, resources/, routes/, storage/, vendor/, artisan, index.php and .htaccess. Root index.php delegates to public/index.php. Root .htaccess exposes only the application and named CSS/JS/favicon/robots assets, blocks private directories, dotfiles, archives and database dumps, and requires Apache mod_rewrite. Do not remove it. Laravel's standard public/ document root also remains supported and is preferable when the host allows it.

## XAMPP

1. Start Apache and MySQL in XAMPP Control Panel (the bundled server is MariaDB).
2. Place the app in D:\Xampp\htdocs\wealthdesk. Do not overwrite Fibro. The tested installation uses a directory junction to the working source at D:\Kuldeep\wealthdesk-laravel, so edits are shared.
3. For a fresh installation only, copy .env.example to .env. Do not replace an existing .env. Set APP_URL=http://localhost/wealthdesk and DB_CONNECTION=mariadb, DB_HOST=127.0.0.1, DB_PORT=3306, DB_DATABASE=wealthdesk, DB_USERNAME=root, DB_PASSWORD= (XAMPP's local default).
4. Create the empty database wealthdesk with utf8mb4 / utf8mb4_unicode_ci in http://localhost/phpmyadmin.
5. From the project folder run:

```powershell
D:\Xampp\php\php.exe artisan key:generate
D:\Xampp\php\php.exe artisan migrate
D:\Xampp\php\php.exe artisan wealthdesk:install
```

Generate a key only for a NEW workspace. Existing workspaces must retain APP_KEY. The installer prompts for a new administrator; it refuses to replace an existing one. Do not install demo data over imported records.

Open **http://localhost/wealthdesk/login**. Both roles use this login. Apache serves the app without php artisan serve. Local MariaDB database, administrator credentials and private files are shared with the original working folder when using the junction.

## Move existing SQLite data without resetting users

Back up .env, database/database.sqlite and storage/app/private privately before starting. Keep APP_KEY: it decrypts client password copies. Never put backups in public_html. Apply all source migrations first. Configure MariaDB credentials without switching the live DB_CONNECTION until verification completes. The target database must be new and empty; existing data is never overwritten.

```powershell
php artisan down
php artisan migrate --database=mariadb --force
php artisan wealthdesk:import-sqlite D:\Kuldeep\wealthdesk-laravel\database\database.sqlite
```

The importer requires maintenance mode, reads a consistent SQLite transaction, and inserts raw records in foreign-key order inside a MariaDB transaction. It verifies every imported field and row count before commit and refuses populated destination tables. It preserves user IDs, hashes, encrypted passwords, balances, trades, deposits, withdrawals, file references, settings, archived/deleted records and audits. Sessions, caches, jobs and reset tokens are intentionally excluded; users sign in again. The source SQLite file remains intact. Keep the original private uploads with the application.

After success, set DB_CONNECTION=mariadb and the correct database credentials in .env, then:

```powershell
php artisan config:clear
php artisan migrate:status
php artisan up
```

Do not switch back to the old SQLite file after new MariaDB writes: it becomes an outdated snapshot.

## Hostinger upload

Hostinger documents Laravel support on Web and Cloud hosting: https://www.hostinger.com/support/which-programming-languages-and-frameworks-are-supported-at-hostinger/

1. Use a PHP hosting website, select PHP 8.2 or newer and enable the required extensions. In hPanel create a database and database user. Use the exact host, name, username and password hPanel supplies (often names have an account prefix). Choose DB_CONNECTION=mariadb when the server is MariaDB; mysql is also supported if your plan provides MySQL instead.
2. Extract **wealthdesk-hostinger.zip** into the domain's public_html directory, including hidden .htaccess and .user.ini. The archive's contents belong directly inside public_html, without an extra wealthdesk-laravel folder. Production vendor/ is bundled; no npm or Composer install is needed for this archive. Avoid mixing it with an existing website.
3. Copy .env.hostinger.example to .env and enter your HTTPS APP_URL and real database credentials. Keep APP_ENV=production, APP_DEBUG=false and SESSION_SECURE_COOKIE=true. Never upload the local .env or local SQLite database. The template is not a working set of credentials.
4. Open SSH/terminal at public_html and run for a NEW empty workspace:

```sh
php artisan key:generate --force
php artisan migrate --force
php artisan wealthdesk:install
php artisan optimize
```

5. Ensure storage/ and bootstrap/cache/ are writable by the PHP user (typically directories 775 and files 664; do not use 777). Set PHP upload_max_filesize at least 6M and post_max_size at least 20M. Confirm the domain redirects HTTP to HTTPS before logging in.
6. Open https://your-domain.com/login. Check both roles, a protected document download and a password reveal. Requests for /.env, /database/, /storage/, /vendor/, /composer.json and /public/ must be denied.

For an EXISTING workspace move, restore a private MariaDB SQL backup into the empty hosting database, transfer storage/app/private separately, and retain the original APP_KEY. Do not run key:generate or wealthdesk:install. Run migrate --force and optimize after configuring the destination .env. The public upload ZIP deliberately contains no existing records, credentials or uploads. Use a private database export/import for those. Never leave SQL backups or ZIPs inside public_html after extracting.

If terminal access is unavailable, prepare migrations and the first administrator in a separate local production-preparation database, then import that database through phpMyAdmin and retain its APP_KEY. Do not add a public browser installer or artisan runner.

For later releases preserve .env, storage/app/private and database records. Upload source changes, then run php artisan optimize:clear, php artisan migrate --force and php artisan optimize. Do not clear records or regenerate keys.

## Database verification

The current release passed 24 tests / 238 assertions against an isolated MariaDB database, including raw SQLite import verification, refusal to overwrite records, rollback after a late import failure, and settings singleton persistence after an auto-increment sequence advances. The normal SQLite suite passes 22 tests / 225 assertions and skips the two MariaDB import integration tests.

The upload ZIP was extracted into a clean directory and successfully ran migrations and optimize using XAMPP PHP 8.2 and bundled production dependencies. The supplied SQL schema was imported into another empty MariaDB database: all five migrations were recognized and there were zero users. Browser checks exercised both roles and password reveal at localhost/wealthdesk on mobile. Apache checks confirmed CSS/JS assets load and private files, source folders and explicit /public/ requests return 403.

To repeat the MariaDB suite, create a separate empty database named wealthdesk_testing. Never point tests at wealthdesk or your production database: RefreshDatabase removes testing tables. In a temporary PowerShell session:

```powershell
$env:DB_CONNECTION='mariadb'
$env:DB_DATABASE='wealthdesk_testing'
$env:DB_HOST='127.0.0.1'
$env:DB_USERNAME='root'
$env:DB_PASSWORD=''
php vendor/phpunit/phpunit/phpunit
```

Close that temporary shell afterward, so the test connection variables do not affect subsequent app commands. PHPUnit sets APP_URL=http://localhost to keep tests independent of XAMPP's /wealthdesk subdirectory.

## Rebuild archives

Source only:

```powershell
php -d extension=zip scripts/package.php
```

Build production dependencies in an isolated staging folder, with copies of composer.json and composer.lock (never remove development dependencies from the working project just to package):

```powershell
composer install --working-dir=dist/hosting-deps --no-dev --prefer-dist --optimize-autoloader --no-scripts --no-interaction
php -d extension=zip scripts/package-hosting.php dist/hosting-deps/vendor
```

Archives exclude .env, local data, uploads, caches, review artifacts and source-control directories. A clean SQL schema for phpMyAdmin is provided at database/exports/mariadb-schema.sql; it contains tables and migration history only, no accounts or business records. On hosts with terminal access, prefer artisan migrate.
