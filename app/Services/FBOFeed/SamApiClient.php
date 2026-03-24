<?php

namespace App\Services\FBOFeed;

use App\Exceptions\FBOFeedLoaderException;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SamApiClient
{
    private string $baseUrl;

    private string $apiKey;

    private int $pageSize;

    private int $retryCount;

    private int $retryDelay;

    private int $requestTimeout;

    private int $connectTimeout;

    public function __construct()
    {
        $this->baseUrl = config('fbo.sam_api_url', 'https://api.sam.gov/opportunities/v2/search');
        $this->apiKey = config('fbo.sam_api_key', '');
        $this->pageSize = (int) config('fbo.sam_api_page_size', 1000);
        $this->retryCount = (int) config('fbo.retry_count', 3);
        $this->retryDelay = (int) config('fbo.retry_timeout', 5);
        $this->requestTimeout = (int) config('fbo.sam_api_timeout', 120);
        $this->connectTimeout = (int) config('fbo.sam_api_connect_timeout', 20);

        if (empty($this->apiKey)) {
            throw new FBOFeedLoaderException('SAM.gov API key is not configured. Set SAM_API_KEY in your .env file.');
        }
    }

    /**
     * Search opportunities posted on a specific date.
     */
    public function searchByDate(Carbon $date): array
    {
        $dateStr = $date->format('m/d/Y');

        return $this->searchAll([
            'postedFrom' => $dateStr,
            'postedTo' => $dateStr,
        ]);
    }

    /**
     * Search opportunities posted within a date range.
     */
    public function searchByDateRange(Carbon $startDate, Carbon $endDate): array
    {
        return $this->searchAll([
            'postedFrom' => $startDate->format('m/d/Y'),
            'postedTo' => $endDate->format('m/d/Y'),
        ]);
    }

    /**
     * Fetch all pages of results for given search parameters.
     */
    private function searchAll(array $params): array
    {
        $pages = $this->collectPages($params);
        $allOpportunities = [];
        $totalRecords = 0;

        foreach ($pages as $response) {
            $allOpportunities = array_merge($allOpportunities, $response['opportunitiesData'] ?? []);
            $totalRecords = max($totalRecords, (int) ($response['totalRecords'] ?? 0));
        }

        Log::info("SAM API returned {$totalRecords} total opportunities", $params);

        return $allOpportunities;
    }

    /**
     * Raw JSON body of each SAM.gov search page (no DB / mapping). For inspection or export.
     *
     * @return array{export_meta: array, pages: array<int, array>}
     */
    public function fetchRawExportForDate(Carbon $date): array
    {
        $dateStr = $date->format('m/d/Y');
        $params = [
            'postedFrom' => $dateStr,
            'postedTo' => $dateStr,
        ];

        return [
            'export_meta' => [
                'posted_from' => $dateStr,
                'posted_to' => $dateStr,
                'calendar_date' => $date->toDateString(),
                'sam_api_url' => $this->baseUrl,
                'generated_at' => now()->toIso8601String(),
                'page_size' => $this->pageSize,
            ],
            'pages' => $this->collectPages($params),
        ];
    }

    /**
     * @return list<array> Decoded JSON per HTTP response (one per page).
     */
    private function collectPages(array $params): array
    {
        $pages = [];
        $offset = 0;

        do {
            $response = $this->fetchPage($params, $offset);
            $pages[] = $response;
            $totalRecords = (int) ($response['totalRecords'] ?? 0);
            $offset += $this->pageSize;
        } while ($offset < $totalRecords);

        return $pages;
    }

    /**
     * Fetch a single page of search results.
     */
    private function fetchPage(array $params, int $offset): array
    {
        $queryParams = array_merge($params, [
            'api_key' => $this->apiKey,
            'limit' => $this->pageSize,
            'offset' => $offset,
        ]);

        $lastException = null;

        for ($attempt = 1; $attempt <= $this->retryCount; $attempt++) {
            try {
                $response = Http::timeout($this->requestTimeout)
                    ->connectTimeout($this->connectTimeout)
                    ->withHeaders([
                        'Accept' => 'application/json',
                        'User-Agent' => config('app.name', 'Laravel').'/1.0 (SAM.gov client)',
                    ])
                    ->get($this->baseUrl, $queryParams);

                if ($response->status() === 429) {
                    $lastException = new FBOFeedLoaderException(
                        'SAM.gov API rate limited (HTTP 429). Wait before retrying or reduce how often you call the API.'
                    );
                    $wait = $this->secondsToWaitAfter429($response);
                    Log::warning("SAM API rate limit hit, waiting {$wait}s before retry (attempt {$attempt}/{$this->retryCount})...");

                    sleep($wait);

                    continue;
                }

                if ($response->failed()) {
                    throw new FBOFeedLoaderException(
                        "SAM.gov API returned HTTP {$response->status()}: ".mb_substr($response->body(), 0, 500)
                    );
                }

                $body = $response->body();
                $data = $response->json();

                if ($data === null && $body !== '') {
                    throw new FBOFeedLoaderException(
                        'SAM.gov API returned a non-JSON body (HTTP '.$response->status().'): '.mb_substr($body, 0, 400)
                    );
                }

                $data = $data ?? [];

                if ($apiError = self::extractSamApiErrorMessage($data)) {
                    throw new FBOFeedLoaderException('SAM.gov API: '.$apiError);
                }

                return $data;
            } catch (FBOFeedLoaderException $e) {
                $lastException = $e;
                Log::warning("SAM API attempt {$attempt}/{$this->retryCount} failed: {$e->getMessage()}");

                if ($attempt < $this->retryCount) {
                    sleep($this->retryDelay);
                }
            } catch (\Exception $e) {
                $lastException = $e;
                Log::warning("SAM API attempt {$attempt}/{$this->retryCount} failed: {$e->getMessage()}");

                if ($attempt < $this->retryCount) {
                    sleep($this->retryDelay);
                }
            }
        }

        $detail = $lastException instanceof \Throwable ? $lastException->getMessage() : 'Unknown error (no exception captured).';

        Log::error('SAM.gov API request failed after all attempts', [
            'message' => $detail,
            'url' => $this->baseUrl,
            'offset' => $offset,
        ]);

        throw new FBOFeedLoaderException(
            "SAM.gov API request failed after {$this->retryCount} attempts. {$detail}"
        );
    }

    /**
     * Honor Retry-After when SAM.gov sends it; otherwise use a default backoff (capped).
     */
    private function secondsToWaitAfter429(Response $response): int
    {
        $default = max(1, $this->retryDelay * 2);
        $max = 120;
        $header = $response->header('Retry-After');

        if ($header === null || $header === '') {
            return min($default, $max);
        }

        if (is_numeric($header)) {
            return min(max(1, (int) $header), $max);
        }

        $ts = strtotime($header);
        if ($ts !== false) {
            return min(max(1, $ts - time()), $max);
        }

        return min($default, $max);
    }

    /**
     * SAM.gov often returns error text inside a 200 JSON body.
     */
    private static function extractSamApiErrorMessage(array $data): ?string
    {
        if (! empty($data['errorMessage'])) {
            $msg = $data['errorMessage'];

            return is_string($msg) ? $msg : json_encode($msg);
        }

        if (! empty($data['error']) && is_string($data['error'])) {
            return $data['error'];
        }

        if (! empty($data['message']) && is_string($data['message'])) {
            return $data['message'];
        }

        return null;
    }

    /**
     * Fetch the full description for an opportunity via its description URL.
     * The SAM.gov search endpoint returns a URL rather than inline description text.
     */
    public function fetchDescription(string $descriptionUrl): ?string
    {
        if (empty($descriptionUrl) || ! filter_var($descriptionUrl, FILTER_VALIDATE_URL)) {
            return null;
        }

        try {
            $separator = str_contains($descriptionUrl, '?') ? '&' : '?';
            $url = $descriptionUrl.$separator.'api_key='.$this->apiKey;

            $response = Http::timeout(min(30, $this->requestTimeout))
                ->connectTimeout($this->connectTimeout)
                ->get($url);

            if ($response->successful()) {
                $body = $response->body();
                $json = json_decode($body, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    return $json['description'] ?? $json['content'] ?? $body;
                }

                return $body;
            }
        } catch (\Exception $e) {
            Log::debug("Failed to fetch description from {$descriptionUrl}: {$e->getMessage()}");
        }

        return null;
    }
}
