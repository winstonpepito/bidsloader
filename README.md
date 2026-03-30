# FBO Feed Loader

A React + Laravel web application that ingests federal contract opportunities from the SAM.gov Opportunities API and loads them into a database. Rebuilt from the original Java/WebObjects `ODSWOFBOFeed` command-line application into a modern web interface.

## Features

- **SAM.gov API Integration**: Fetches opportunities via the SAM.gov REST API (`/opportunities/v2/search`) with pagination, retry logic, and automatic field mapping
- **Legacy Feed Parsing**: Retains support for loading local FBO-format text files for backward compatibility
- **Entry Processing**: Creates and updates Bid records with proper categorization, entity resolution, and amendment handling
- **Web Dashboard**: React/Inertia.js dashboard showing feed status, bid counts, and recent activity
- **Feed Management**: Trigger feed loads from the UI (lookback, specific date, date range, or local file)
- **Error Tracking**: View and manage parsing/loading errors with decompressed entry content and stack traces
- **Bid Browser**: Search, filter, and view federal bid opportunities
- **Queue Support**: Background processing via Laravel Queues
- **Email Notifications**: Send load reports via Amazon SES (or any Laravel mail driver)
- **Artisan CLI**: `php artisan fbo:load` command for cron/scheduled execution

## Requirements

- PHP 8.2+
- Node.js 18+
- Composer
- SQLite (default) or MySQL/PostgreSQL

## Installation

```bash
# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Copy environment file (already done if cloned)
cp .env.example .env

# Generate application key
php artisan key:generate

# Create SQLite database
touch database/database.sqlite

# Run migrations
php artisan migrate

# Seed initial data (admin user, states, categories, subscription types)
php artisan db:seed
# Seeded login: admin@example.com / password (change this in production)

# Build frontend assets
npm run build
```

## Configuration

Edit `.env` to configure:

```env
# SAM.gov API Configuration
SAM_API_URL=https://api.sam.gov/opportunities/v2/search
SAM_API_KEY=your-api-key-here
SAM_API_PAGE_SIZE=1000
SAM_API_TIMEOUT=120
SAM_API_CONNECT_TIMEOUT=20
SAM_FETCH_DESCRIPTIONS=false
SAM_INTER_PAGE_DELAY_MS=0
SAM_BROWSER_INTER_PAGE_DELAY_MS=400
SAM_BROWSER_SERVER_DELAY_MS=350

# Feed Loader Settings
FBO_LOOK_BACK_DAYS=60
FBO_RETRY_COUNT=3
FBO_RETRY_TIMEOUT=5

# Notification Email
FBO_EMAIL_TO=admin@example.com
FBO_EMAIL_FROM=noreply@example.com

# Database (switch to MySQL/PostgreSQL for production)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=fbo_feed
DB_USERNAME=root
DB_PASSWORD=
```

## Usage

### Web Interface

```bash
# Start the development server
php artisan serve

# In a separate terminal, start Vite dev server
npm run dev
```

Visit `http://localhost:8000` and log in with **`admin@example.com`** / **`password`** (Laravel `UserFactory` default). Change the password after first login in production.

### Command Line

```bash
# Load unloaded feeds (lookback mode)
php artisan fbo:load --sync

# Load a specific date
php artisan fbo:load --date=20240115 --sync

# Load a date range
php artisan fbo:load --date-range=20240101-20240131 --sync

# Load from a local file
php artisan fbo:load --file=/path/to/FBOFeed20240115 --sync

# Dispatch to queue (remove --sync)
php artisan fbo:load
```

### Scheduled Execution

The scheduler runs **`php artisan fbo:load --yesterday`** every day at **2:00 AM** in **`APP_TIMEZONE`**. That loads SAM.gov for the **previous calendar day** (so a 2:00 AM run on Tuesday loads Monday’s postings).

Add this to the server crontab (use your real app path; on EC2 often `/var/www/bidsloader`):

```
* * * * * cd /var/www/bidsloader && php artisan schedule:run >> /dev/null 2>&1
```

The scheduled task **dispatches to the queue** (not `--sync`), so a **queue worker** must be running or the job will sit in `jobs` until a worker starts.

Manual one-off for “yesterday” in app timezone:

```bash
php artisan fbo:load --yesterday
```

### Queue Worker

For background job processing (required for the nightly schedule above):

```bash
php artisan queue:work database --sleep=3 --tries=3
```

On production, run this under **systemd** or **Supervisor** (see deployment notes).

### Production: Apache / EC2 — storage permissions (fixes HTTP 500 / `tempnam()`)

If the site returns **500** with `ErrorException` in `Illuminate\Filesystem\Filesystem.php` around **`tempnam()`** (often when compiling Blade views), the web server user usually **cannot write** under `storage/` or `bootstrap/cache/`.

On the server, from the app root (e.g. `/var/www/bidsloader`):

```bash
cd /var/www/bidsloader

# Ensure framework directories exist (git may not ship compiled views)
sudo mkdir -p storage/framework/{views,cache,sessions}
sudo mkdir -p storage/logs
sudo mkdir -p bootstrap/cache

# Web server user: Amazon Linux httpd is typically 'apache'
# (If you use PHP-FPM, match the pool user, e.g. grep ^User /etc/httpd/conf/httpd.conf or the FPM pool.)
sudo chown -R apache:apache storage bootstrap/cache
sudo chmod -R ug+rwx storage bootstrap/cache

# SQLite: the DB file and its directory must be writable (sessions, cache, jobs)
sudo touch database/database.sqlite 2>/dev/null || true
sudo chown apache:apache database/database.sqlite
sudo chmod 664 database/database.sqlite
sudo chown apache:apache database
sudo chmod 775 database

# If SELinux is enforcing (getenforce → Enforcing), allow httpd to write:
# sudo chcon -R -t httpd_sys_rw_content_t storage bootstrap/cache database

sudo systemctl reload httpd
```

Then clear cached config/views if you had partial failures:

```bash
php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan cache:clear
```

Set `APP_URL=https://your-loader-hostname` in `.env` and run `php artisan config:cache` when finished.

### Production: home page still shows the Laravel “Welcome” page

The app is configured so **`/` shows the login form** for guests (see `routes/web.php`). If you still see the generic Welcome page after `git pull`:

1. **Confirm the server has current code** — the route change is in recent commits; run `git pull` on the server.
2. **Clear route cache** — a cached route file will keep the *old* `/` → `Welcome` definition until you run:
   ```bash
   cd /var/www/bidsloader
   php artisan route:clear
   ```
   Or clear everything: `php artisan optimize:clear`
3. **Rebuild frontend assets** if you deploy with `npm run build` on the server (so `public/build` matches the app).
4. **Reload PHP / Apache** so OPcache picks up changed PHP files: `sudo systemctl reload httpd` (and `php-fpm` if used).

Check what Laravel thinks `/` is:

```bash
php artisan route:list --path=/
```

You should see a closure/`/` route serving the app (not an old `Welcome` Inertia page from cached routes).

### Production: admin login (`admin@example.com`)

If login fails on the server, the admin user usually **was never created** (only `migrate` was run, not `db:seed`), or the password is not what you expect.

1. **Create / refresh seeded data** (from app root). The web user must be able to write `storage/logs` (or run Artisan as that user):

   ```bash
   cd /var/www/bidsloader
   sudo -u apache php artisan migrate --force
   sudo -u apache php artisan db:seed --force
   ```

   If you run `php artisan` as `ec2-user` instead, ensure logs are writable (e.g. `sudo chown -R apache:apache storage bootstrap/cache` after deploy, or add group write on `storage/logs`).

2. **Default seeded credentials** (`DatabaseSeeder`; password matches `UserFactory` for local dev):

   - **Email:** `admin@example.com`
   - **Password:** `password` (all lowercase)

3. **Verify the user exists** (SQLite example):

   ```bash
   sqlite3 database/database.sqlite "SELECT id, email, email_verified_at FROM users;"
   ```

   `email_verified_at` should be non-null (dashboard routes use the `verified` middleware).

4. **If the user exists but the password was changed**, reset it with Tinker:

   ```bash
   php artisan tinker
   >>> \App\Models\User::where('email', 'admin@example.com')->update(['password' => bcrypt('your-new-password')]);
   ```

## Architecture

### Backend (Laravel)

```
app/
├── Console/Commands/LoadFboFeed.php    # CLI command
├── Exceptions/
│   ├── FBOFeedLoaderException.php
│   └── FBOFeedParserException.php
├── Http/Controllers/
│   ├── BidController.php               # Bid listing & detail
│   ├── DashboardController.php         # Dashboard stats
│   ├── FeedController.php              # Feed management & trigger
│   └── FeedErrorController.php         # Error viewing
├── Jobs/LoadFboFeedJob.php             # Queued feed loading
├── Mail/FeedLoadNotification.php       # Email notification
├── Models/
│   ├── Bid.php                         # Federal bid record
│   ├── Category.php                    # Bid classification
│   ├── Entity.php                      # Agency/organization
│   ├── FboFeedError.php                # Parse/load errors
│   ├── FeedLoadLog.php                 # Load operation log
│   ├── LoadedFboFeed.php               # Loaded feed tracker
│   ├── PurchasingAgent.php             # Contact info
│   ├── Source.php                      # Data source (FBO)
│   ├── State.php                       # US state
│   └── SubscriptionType.php           # Prebid/Bid Federal
└── Services/FBOFeed/
    ├── FBOAttributeClass.php           # Attribute type enum
    ├── FBOAttributeType.php            # Feed attribute enum
    ├── FBOEntryType.php                # Entry type enum
    ├── FBOFeedEntry.php                # Parsed entry DTO
    ├── FBOFeedLoader.php               # Main loader service
    ├── FBOFeedParser.php               # Legacy feed text parser
    ├── FBOFeedParseResult.php          # Parse result container
    ├── LoadResult.php                  # Load result DTO
    ├── ProcessorDispatcher.php         # Routes entries to processors
    ├── SamApiClient.php                # SAM.gov REST API HTTP client
    ├── SamApiMapper.php                # Maps API JSON to FBOFeedEntry
    └── Processors/
        ├── AmdcssProcessor.php         # Amendment processor
        ├── BaseProcessor.php           # Shared processing logic
        ├── CombineProcessor.php        # Combined solicitation
        ├── ModProcessor.php            # Modification processor
        ├── PresolProcessor.php         # Pre-solicitation
        ├── SnoteProcessor.php          # Special notice
        └── SrcsgtProcessor.php         # Sources sought
```

### Frontend (React + Inertia.js)

```
resources/js/Pages/
├── Dashboard.jsx           # Stats overview with cards and recent activity
├── Bids/
│   ├── Index.jsx           # Searchable/filterable bid listing
│   └── Show.jsx            # Bid detail view
├── Feeds/
│   ├── Index.jsx           # Feed load history with trigger UI
│   └── Show.jsx            # Feed load detail with logs
└── Errors/
    ├── Index.jsx           # Error listing with filters
    └── Show.jsx            # Error detail with entry/stack content
```

## Mapping from Original Java Application

| Java Class | PHP Equivalent |
|---|---|
| `Main.java` | `LoadFboFeed` command + `LoadFboFeedJob` |
| `FBOFeedParser` | `App\Services\FBOFeed\FBOFeedParser` |
| `FBOEntryParserBase` | Integrated into `FBOFeedParser` |
| `FBOFeedEntry` | `App\Services\FBOFeed\FBOFeedEntry` |
| `FBOFeedFtpLoader` | `App\Services\FBOFeed\FBOFeedLoader` + `SamApiClient` |
| `FBOFeedPRESOLProcessor` | `App\Services\FBOFeed\Processors\PresolProcessor` |
| `FBOFeedCOMBINEProcessor` | `App\Services\FBOFeed\Processors\CombineProcessor` |
| `FBOFeedAMDCSSProcessor` | `App\Services\FBOFeed\Processors\AmdcssProcessor` |
| `FBOFeedMODProcessor` | `App\Services\FBOFeed\Processors\ModProcessor` |
| `FBOFeedSRCSGTProcessor` | `App\Services\FBOFeed\Processors\SrcsgtProcessor` |
| `FBOFeedSNOTEProcessor` | `App\Services\FBOFeed\Processors\SnoteProcessor` |
| `FBOFeedLoaderResultReader` | `App\Services\FBOFeed\ProcessorDispatcher` |
| `LoadedFBOFeed` (EO) | `App\Models\LoadedFboFeed` |
| `FBOFeedError` (EO) | `App\Models\FboFeedError` |
| `Bid` (EO) | `App\Models\Bid` |
| `BidsFeedLoaderNotificationEmail` | `App\Mail\FeedLoadNotification` |

## Notes

- FedBizOpps (ftp.fbo.gov) was retired when it migrated to SAM.gov in 2019. This app now uses the SAM.gov Opportunities REST API. The legacy FBO text parser is retained for loading from local files.
- The application uses SQLite by default for easy local development. Switch to MySQL or PostgreSQL for production.
