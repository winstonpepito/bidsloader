<?php

namespace App\Services\FBOFeed;

use App\Exceptions\FBOFeedLoaderException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SamApiClient
{
    private string $baseUrl;
    private string $apiKey;
    private int $pageSize;
    private int $retryCount;
    private int $retryDelay;

    public function __construct()
    {
        $this->baseUrl = config('fbo.sam_api_url', 'https://api.sam.gov/opportunities/v2/search');
        $this->apiKey = config('fbo.sam_api_key', '');
        $this->pageSize = (int) config('fbo.sam_api_page_size', 1000);
        $this->retryCount = (int) config('fbo.retry_count', 3);
        $this->retryDelay = (int) config('fbo.retry_timeout', 5);

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
        $allOpportunities = [];
        $offset = 0;

        do {
            $response = $this->fetchPage($params, $offset);
            $opportunities = $response['opportunitiesData'] ?? [];
            $totalRecords = $response['totalRecords'] ?? 0;

            $allOpportunities = array_merge($allOpportunities, $opportunities);
            $offset += $this->pageSize;
        } while ($offset < $totalRecords);

        Log::info("SAM API returned {$totalRecords} total opportunities", $params);

        return $allOpportunities;
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
                $response = Http::timeout(30)->get($this->baseUrl, $queryParams);

                if ($response->status() === 429) {
                    Log::warning('SAM API rate limit hit, waiting before retry...');
                    sleep($this->retryDelay * 2);
                    continue;
                }

                if ($response->failed()) {
                    throw new FBOFeedLoaderException(
                        "SAM.gov API returned HTTP {$response->status()}: " . mb_substr($response->body(), 0, 500)
                    );
                }

                return $response->json() ?? [];
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

        throw new FBOFeedLoaderException(
            "SAM.gov API request failed after {$this->retryCount} attempts: " . $lastException?->getMessage()
        );
    }

    /**
     * Fetch the full description for an opportunity via its description URL.
     * The SAM.gov search endpoint returns a URL rather than inline description text.
     */
    public function fetchDescription(string $descriptionUrl): ?string
    {
        if (empty($descriptionUrl) || !filter_var($descriptionUrl, FILTER_VALIDATE_URL)) {
            return null;
        }

        try {
            $separator = str_contains($descriptionUrl, '?') ? '&' : '?';
            $url = $descriptionUrl . $separator . 'api_key=' . $this->apiKey;

            $response = Http::timeout(15)->get($url);

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
