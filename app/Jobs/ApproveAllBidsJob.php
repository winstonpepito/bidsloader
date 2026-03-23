<?php

namespace App\Jobs;

use App\Models\StagedBid;
use App\Services\LiveBidWriter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ApproveAllBidsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries = 1;

    public function __construct(
        public readonly ?string $entryType = null,
        public readonly ?array $ids = null,
    ) {}

    public function handle(LiveBidWriter $writer): void
    {
        $query = StagedBid::pending();

        if ($this->ids) {
            $query->whereIn('id', $this->ids);
        }

        if ($this->entryType) {
            $query->where('entry_type', $this->entryType);
        }

        $total = $query->count();
        $processed = 0;
        $approved = 0;
        $skipped = 0;
        $errors = 0;

        $this->updateProgress($total, $processed, $approved, $skipped, $errors, 'running');

        $query->with([
            'source', 'subscriptionType', 'category', 'entity',
            'state', 'setasideCode', 'categoryAlias', 'entityAlias',
            'purchasingAgent',
        ])->chunk(50, function ($bids) use ($writer, $total, &$processed, &$approved, &$skipped, &$errors) {
            foreach ($bids as $stagedBid) {
                try {
                    if ($writer->isDuplicateInOracle($stagedBid)) {
                        $skipped++;
                        $processed++;
                        continue;
                    }

                    $writer->writeBid($stagedBid);
                    $stagedBid->update(['review_status' => 'approved']);
                    $approved++;
                } catch (\Exception $e) {
                    Log::error('ApproveAllBidsJob: failed to write bid', [
                        'staged_bid_id' => $stagedBid->id,
                        'error' => $e->getMessage(),
                    ]);
                    $errors++;
                }

                $processed++;
                $this->updateProgress($total, $processed, $approved, $skipped, $errors, 'running');
            }

            $writer->clearCache();
        });

        $this->updateProgress($total, $processed, $approved, $skipped, $errors, 'completed');

        Log::info('ApproveAllBidsJob finished', compact('total', 'approved', 'skipped', 'errors'));
    }

    private function updateProgress(int $total, int $processed, int $approved, int $skipped, int $errors, string $status): void
    {
        Cache::put('approve_all_progress', [
            'status' => $status,
            'total' => $total,
            'processed' => $processed,
            'approved' => $approved,
            'skipped' => $skipped,
            'errors' => $errors,
            'updated_at' => now()->toIso8601String(),
        ], now()->addHour());
    }
}
