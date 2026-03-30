<?php

namespace App\Services\FBOFeed;

use App\Exceptions\FBOFeedLoaderException;
use App\Exceptions\FBOFeedParserException;
use App\Models\FboFeedError;
use App\Models\FeedLoadLog;
use App\Models\LoadedFboFeed;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FBOFeedLoader
{
    private FBOFeedParser $parser;

    private ProcessorDispatcher $dispatcher;

    private int $errorCount = 0;

    private int $loadedCount = 0;

    public function __construct()
    {
        $this->parser = new FBOFeedParser;
        $this->dispatcher = new ProcessorDispatcher;
    }

    /**
     * Load from a local file path (legacy FBO text format).
     */
    public function loadFromFile(string $filePath): LoadResult
    {
        $fboDate = FBOFeedParser::getFboDateFromFilename($filePath);
        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new FBOFeedLoaderException("Unable to read file: {$filePath}");
        }

        return $this->processContent($content, $fboDate, basename($filePath));
    }

    /**
     * Load opportunities from the SAM.gov REST API for a specific date.
     * API-sourced data goes to the staged_bids table for review before going live.
     */
    public function loadFromApi(Carbon $date): LoadResult
    {
        $client = new SamApiClient;
        $fetchDescriptions = (bool) config('fbo.sam_fetch_descriptions', false);
        $mapper = new SamApiMapper($client, $fetchDescriptions);

        Log::info("Fetching opportunities from SAM.gov API for {$date->format('Y-m-d')}");

        $opportunities = $client->searchByDate($date);
        $entries = $mapper->mapOpportunities($opportunities, $date);

        Log::info('Mapped '.count($entries).' entries from '.count($opportunities).' API results');

        $this->dispatcher->setStaging(true);

        try {
            return $this->processEntries($entries, $date, 'SAM API '.$date->format('Y-m-d'));
        } finally {
            $this->dispatcher->setStaging(false);
        }
    }

    /**
     * Load from pre-fetched SAM.gov opportunity objects (e.g. browser-orchestrated paged fetch).
     *
     * @param  array<int, array<string, mixed>>  $opportunities
     */
    public function loadFromApiWithOpportunities(Carbon $date, array $opportunities): LoadResult
    {
        $client = new SamApiClient;
        $fetchDescriptions = (bool) config('fbo.sam_fetch_descriptions', false);
        $mapper = new SamApiMapper($client, $fetchDescriptions);

        Log::info('Processing '.count($opportunities).' pre-fetched SAM.gov opportunities for '.$date->format('Y-m-d'));

        $entries = $mapper->mapOpportunities($opportunities, $date);

        Log::info('Mapped '.count($entries).' entries from '.count($opportunities).' API results');

        $this->dispatcher->setStaging(true);

        try {
            return $this->processEntries($entries, $date, 'SAM API '.$date->format('Y-m-d').' (browser session)');
        } finally {
            $this->dispatcher->setStaging(false);
        }
    }

    /**
     * Load from a completed browser-orchestrated session file (stored under storage/app/sam-browser/).
     */
    public function loadFromSamBrowserSession(string $sessionId, Carbon $date): LoadResult
    {
        $relative = 'sam-browser/'.$sessionId.'.json';

        if (! Storage::exists($relative)) {
            throw new FBOFeedLoaderException('Browser session data not found or already processed.');
        }

        $raw = json_decode(Storage::get($relative), true) ?? [];
        $opportunities = $raw['opportunities'] ?? [];

        Storage::delete($relative);

        return $this->loadFromApiWithOpportunities($date, $opportunities);
    }

    /**
     * Load all unloaded dates within the lookback window.
     */
    public function loadUnloadedDates(int $lookBackDays = 60): array
    {
        $startDate = Carbon::yesterday()->subDays($lookBackDays);
        $datesToLoad = LoadedFboFeed::getDatesNotLoaded($startDate, $lookBackDays);

        $results = [];
        foreach ($datesToLoad as $dateStr) {
            $date = Carbon::parse($dateStr);
            try {
                $results[$dateStr] = $this->loadFromApi($date);
            } catch (\Exception $e) {
                Log::error("Failed to load SAM feed for {$dateStr}: {$e->getMessage()}");
                $results[$dateStr] = new LoadResult(
                    date: $dateStr,
                    filename: 'SAM API '.$dateStr,
                    success: false,
                    entriesLoaded: 0,
                    errorsCount: 1,
                    message: $e->getMessage()
                );
            }
        }

        return $results;
    }

    /**
     * Load from a date range.
     */
    public function loadDateRange(Carbon $startDate, Carbon $endDate): array
    {
        $results = [];
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $dateStr = $current->format('Y-m-d');
            if (! LoadedFboFeed::isFboDateLoaded($dateStr)) {
                try {
                    $results[$dateStr] = $this->loadFromApi($current->copy());
                } catch (\Exception $e) {
                    Log::error("Failed to load SAM feed for {$dateStr}: {$e->getMessage()}");
                    $results[$dateStr] = new LoadResult(
                        date: $dateStr,
                        filename: '',
                        success: false,
                        entriesLoaded: 0,
                        errorsCount: 1,
                        message: $e->getMessage()
                    );
                }
            }
            $current->addDay();
        }

        return $results;
    }

    /**
     * Process raw feed content (legacy FBO text format) and persist entries.
     */
    public function processContent(string $content, ?Carbon $fboDate, string $filename = ''): LoadResult
    {
        $this->parser->setFboFileDate($fboDate);
        $parseResult = $this->parser->parseFeed($content);

        return $this->processEntries(
            entries: $parseResult->getEntries(),
            fboDate: $fboDate,
            source: $filename,
            parseErrors: $parseResult->getErrors()
        );
    }

    /**
     * Process an array of FBOFeedEntry objects and persist them via the processor pipeline.
     *
     * @param  FBOFeedEntry[]  $entries
     * @param  FBOFeedParserException[]  $parseErrors
     */
    public function processEntries(
        array $entries,
        ?Carbon $fboDate,
        string $source = '',
        array $parseErrors = []
    ): LoadResult {
        $this->errorCount = 0;
        $this->loadedCount = 0;
        $dateStr = $fboDate?->format('Y-m-d') ?? now()->format('Y-m-d');

        if ($fboDate && LoadedFboFeed::isFboDateLoaded($dateStr)) {
            return new LoadResult(
                date: $dateStr,
                filename: $source,
                success: true,
                entriesLoaded: 0,
                errorsCount: 0,
                message: 'Already loaded'
            );
        }

        $feedRecord = LoadedFboFeed::create([
            'fbo_date' => $dateStr,
            'status' => 'processing',
        ]);

        foreach ($parseErrors as $error) {
            $this->recordError($error, $fboDate);
            $this->errorCount++;
        }

        foreach ($entries as $entry) {
            $processor = $this->dispatcher->getProcessor($entry->getEntryType());
            if ($processor === null) {
                continue;
            }

            try {
                $processor->loadEntry($entry);
                $this->loadedCount++;
            } catch (FBOFeedLoaderException $e) {
                Log::debug("Skipped entry ({$entry->getEntryType()->value}): {$e->getMessage()}");
                $this->recordEntryError($entry, $e, $fboDate);
                $this->errorCount++;
            } catch (\Exception $e) {
                Log::error("Error processing entry ({$entry->getEntryType()->value}): {$e->getMessage()}");
                $this->recordEntryError($entry, $e, $fboDate);
                $this->errorCount++;
            }
        }

        $feedRecord->update([
            'status' => 'completed',
            'entries_loaded' => $this->loadedCount,
            'errors_count' => $this->errorCount,
        ]);

        FeedLoadLog::create([
            'loaded_fbo_feed_id' => $feedRecord->id,
            'level' => $this->errorCount > 0 ? 'warning' : 'info',
            'message' => "Loaded {$this->loadedCount} entries with {$this->errorCount} errors from {$source}",
        ]);

        return new LoadResult(
            date: $dateStr,
            filename: $source,
            success: true,
            entriesLoaded: $this->loadedCount,
            errorsCount: $this->errorCount,
            message: "Loaded {$this->loadedCount} entries"
        );
    }

    private function recordError(FBOFeedParserException $error, ?Carbon $fboDate): void
    {
        FboFeedError::create([
            'entry_type' => $error->getEntryType(),
            'error_message' => mb_substr($error->getMessage(), 0, 1000),
            'fbo_file_date' => $fboDate,
            'compressed_entry' => ! empty($error->getEntryLines()) ? implode("\n", $error->getEntryLines()) : null,
            'compressed_stack' => $error->getTraceAsString(),
        ]);
    }

    private function recordEntryError(FBOFeedEntry $entry, \Throwable $error, ?Carbon $fboDate): void
    {
        FboFeedError::create([
            'entry_type' => $entry->getEntryType()->value,
            'error_message' => mb_substr($error->getMessage(), 0, 1000),
            'fbo_file_date' => $fboDate,
            'compressed_entry' => $entry->toStringEntry(),
            'compressed_stack' => $error->getTraceAsString(),
        ]);
    }
}
