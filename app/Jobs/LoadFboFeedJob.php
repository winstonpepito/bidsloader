<?php

namespace App\Jobs;

use App\Mail\FeedLoadNotification;
use App\Services\FBOFeed\FBOFeedLoader;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class LoadFboFeedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;

    public function __construct(
        private readonly ?string $filePath = null,
        private readonly ?string $startDate = null,
        private readonly ?string $endDate = null,
        private readonly ?string $singleDate = null,
    ) {
    }

    public function handle(): void
    {
        $loader = new FBOFeedLoader();
        $results = [];

        try {
            if ($this->filePath) {
                Log::info("Loading feed from file: {$this->filePath}");
                $result = $loader->loadFromFile($this->filePath);
                $results[$result->date] = $result;
            } elseif ($this->singleDate) {
                Log::info("Loading feed from SAM.gov API for date: {$this->singleDate}");
                $date = Carbon::parse($this->singleDate);
                $result = $loader->loadFromApi($date);
                $results[$result->date] = $result;
            } elseif ($this->startDate && $this->endDate) {
                Log::info("Loading feeds from SAM.gov API: {$this->startDate} to {$this->endDate}");
                $results = $loader->loadDateRange(
                    Carbon::parse($this->startDate),
                    Carbon::parse($this->endDate)
                );
            } else {
                Log::info('Loading unloaded feeds from SAM.gov API (lookback mode)');
                $lookBack = (int) config('fbo.look_back_days', 60);
                $results = $loader->loadUnloadedDates($lookBack);
            }

            $this->sendNotification($results);
        } catch (\Exception $e) {
            Log::error("SAM.gov feed load failed: {$e->getMessage()}", ['exception' => $e]);
            throw $e;
        }
    }

    private function sendNotification(array $results): void
    {
        $emailTo = config('fbo.email_to');
        if (empty($emailTo)) {
            return;
        }

        try {
            Mail::to($emailTo)->send(new FeedLoadNotification($results));
        } catch (\Exception $e) {
            Log::error("Failed to send notification: {$e->getMessage()}");
        }
    }
}
