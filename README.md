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

# Seed initial data (states, categories, subscription types)
php artisan db:seed

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
SAM_FETCH_DESCRIPTIONS=false

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

Visit `http://localhost:8000` and log in with the seeded admin account (`admin@example.com`).

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

The feed loader runs daily at 2:00 AM via Laravel's scheduler. Add this to your crontab:

```
* * * * * cd /path/to/FBOFeedApp && php artisan schedule:run >> /dev/null 2>&1
```

### Queue Worker

For background job processing:

```bash
php artisan queue:work --queue=default --tries=3
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
