# WealthDesk — Laravel

The complete investment admin and client application rebuilt in Laravel 12, as requested. It uses Blade templates, Laravel session authentication, Eloquent, SQL migrations, private file storage, ordinary CSS, and a small amount of browser JavaScript. There is no Node.js/npm build, React, Vite, Cloudflare runtime, or ChatGPT login dependency.

## Open the application

Local URL: **http://127.0.0.1:8000/login**

The local PHP launcher uses Laravel?s own router and enables uploads up to the application?s 5 MB limit. You can also use php artisan serve if your PHP upload limits are configured.

The local development database contains fictional sample data and the following development accounts:

| Role | Email / login | Password |
| --- | --- | --- |
| Administrator | admin@wealthdesk.test | Admin@12345! |
| Client — Aarav Sharma | client@wealthdesk.test or DEMO1A | Client@12345! |

Other fictional sample clients use the password Client@12345!. Find their email and Client ID in Manage Clients. These accounts are created only by an explicitly requested local demo installation. Production installation creates an administrator with credentials you choose.

After signing in, the role determines which panel opens. The administrator manages clients, positions, deposits, withdrawal decisions, and payment settings. Clients see only their own records and can request withdrawals, update their photo, and change their password.

## Start locally

Requires PHP 8.2+ with PDO SQLite (or PDO MySQL), mbstring, OpenSSL, fileinfo, and standard Laravel extensions. PHP ZIP or an unzip utility is useful for Composer installation. Composer is the only dependency manager required.

From this directory, for the already installed workspace:

```powershell
php scripts/serve.php
```

For a fresh checkout:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
New-Item -ItemType File -Path database/database.sqlite
php artisan migrate
php artisan wealthdesk:install --demo
php scripts/serve.php
```

Run the environment-copy and database-file commands only when those files do not already exist. To create an empty real workspace instead of sample data, omit --demo from wealthdesk:install and enter your chosen admin name, email, and password. The installer does not overwrite an existing administrator. Never rerun key:generate against an existing production installation.

On this Windows installation, PHP's ZIP extension is available but disabled by default. If Composer reports a missing ZIP/unzip dependency, enable extension=zip in the PHP configuration, or invoke Composer's PHAR with php -d extension=zip for that command. Do not change the application to work around a missing PHP extension.

## Included requirements

- Separate admin/client access; sign in with email or generated Client ID and password. No public registration.
- Client personal, bank, nominee, PAN/Aadhaar, investment and risk-profile details, with a unique six-character alphanumeric ID.
- Client create/edit, archival/restoration, protected profile photo/signature/Aadhaar uploads, and admin preview of a client panel.
- Equity, Futures & Options, Commodity, and Forex & Currency. Current positions, last trades, orders, and total-value sections where specified; edit, remove, hold, and resume actions.
- Deposits with date/time, amount, processor, unique reference, Completed/Pending status, edit, and removal.
- Client withdrawal requests, Pending/Approved/Declined states, administrator decisions, and reserved funds.
- Client profile, own records, password changes with admin-visible timestamps, and photo updates.
- Admin QR upload, payee/UPI/instructions, and client Add Funds screen.
- Dashboard charts from stored records, search/filters, paginated clients, CSV exports, and audit history.
- Responsive desktop/mobile layouts, form validation, CSRF protection, login rate limiting, password hashing, and private file authorization.
- Coming Soon pages for the future sections named in the PDF.

## Balance rules

Amounts are INR and stored as integer paise.

Balance = opening investment + completed deposits + closed-trade P&L - approved withdrawals.

Available = balance - pending withdrawals.

Pending deposits do not add funds. Creating a withdrawal reserves its amount inside a database transaction after obtaining a client row/write lock. Decline releases the reserve; approval records a completed payout. Arrange the actual bank transfer before approving.

Only Last trades realize P&L. Current positions display manually recorded unrealized P&L; orders and total-value entries do not change balance. Hold pauses a position without closing it. A calculation helper provides before-fees P&L; the administrator enters the final result. Edits/removals that would overdraw available funds are rolled back.

Client deletion is implemented as archival to preserve financial history. Pending requests must be resolved before archival. Document downloads require an authenticated, authorized user; they are not placed in public storage. Password changes invalidate other sessions and are recorded without storing plaintext passwords.

All displayed dates and date/time entry use India Standard Time; stored timestamps use UTC. Prices, trades, bank transfers, and deposit verification are manually managed, as in the PDF. No broker or payment gateway integration was specified.

## Verify

```powershell
php artisan test
php artisan view:cache
composer validate --no-check-publish
```

Feature tests use an isolated in-memory SQLite database. They cover login, roles, client isolation, view rendering, client editing, money precision, deposits, trade P&L, reservations/overdrafts, immutable withdrawal decisions, archives, protected uploads, QR details, password-change notifications, and CSV formula escaping.

A separate local browser review exercised actual admin/client logins, client creation, position and deposit forms, password changes, withdrawal submission/approval, and 1440px/390px layouts. It reported zero JavaScript exceptions, zero HTTP failures, and no horizontal overflow on the checked mobile pages. Review screenshots are local-only under storage/app/review.

## Deploy to PHP hosting

The Laravel version needs a PHP server (Apache/Nginx with PHP-FPM, Laravel Forge, a suitable VPS, or compatible shared hosting). The earlier ChatGPT Sites host cannot execute PHP. The renamed online prototype at https://wealthdesk.racing-eagle-2816.chatgpt.site is not this Laravel application.

1. Upload the application source and point the web document root to this project's public directory.
2. Run composer install --no-dev --optimize-autoloader on the server.
3. Create a server-specific .env from .env.example. Set APP_ENV=production, APP_DEBUG=false, APP_URL to the HTTPS domain, and SESSION_SECURE_COOKIE=true. Configure MySQL credentials or a writable SQLite path.
4. Generate APP_KEY once for a new installation. Keep that key for subsequent deployments.
5. Run php artisan migrate --force and php artisan wealthdesk:install to create the first administrator interactively with your chosen credentials. Do not seed demo accounts on production.
6. Make storage and bootstrap/cache writable, then run php artisan optimize. Configure PHP upload_max_filesize to at least 6M and post_max_size to at least 20M. public/.user.ini supplies those values on hosts that support per-directory PHP settings.

No npm installation, frontend build, public storage link, external authentication, or queue worker is needed for the implemented workflows. Back up both the database and storage/app/private, which contains uploaded documents. Do not upload the local .env, local database, test uploads, browser profile, or review screenshots.

The deployable source archive excludes local credentials, databases, uploads, caches, browser artifacts, and vendor dependencies; install Composer dependencies on the target server.

## Readability and mobile design

The refreshed interface uses larger system fonts, dark blue text, clear emerald accents, stronger status colors, 44-pixel touch targets, and 16-pixel form inputs. On phones, client/position/transaction tables become labeled record cards. The navigation drawer supports a close button, Escape, focus trapping, and background scroll locking. Layouts were checked across 38 admin/client screens at 1440, 1024, 768, 390, and 320 pixels with no horizontal page overflow, JavaScript exceptions, or HTTP failures. CSS and JavaScript URLs include file timestamps so updates appear after a normal page refresh.

## Admin client-password visibility

In Manage Clients, use the key icon, or open a client profile and select View password. Confirm your own administrator password for each reveal. The current client password is shown for up to 30 seconds and cleared immediately when the dialog closes or the page loses visibility. Password changes and administrator resets automatically update the viewable value. Passwords are stored as login hashes plus a separate encrypted client-only copy protected by APP_KEY; administrator passwords have no recoverable copy. Do not rotate APP_KEY without a supported data migration.

Reveals require an authenticated administrator, password confirmation, CSRF protection, and rate limiting. Each successful reveal is audited without the password. The reveal response disables caching. Passwords are excluded from normal HTML pages, user serialization, and CSV exports. Clients are informed of the administrator visibility in the password-change dialog.

Existing hash-only passwords cannot be recovered. They become viewable after the client next signs in successfully or after the administrator sets a new password. The migration does not reset or guess existing passwords. Apply new migrations with php artisan migrate --force when deploying this feature.
