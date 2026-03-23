<?php

namespace App\Console\Commands;

use App\Jobs\LoadFboFeedJob;
use Illuminate\Console\Command;

class LoadFboFeed extends Command
{
    protected $signature = 'fbo:load
        {--file= : Load from a local file (legacy FBO text format)}
        {--date= : Load a specific date (YYYYMMDD)}
        {--date-range= : Load a date range (YYYYMMDD-YYYYMMDD)}
        {--sync : Run synchronously instead of dispatching to queue}';

    protected $description = 'Load federal contract opportunities from the SAM.gov API';

    public function handle(): int
    {
        $file = $this->option('file');
        $date = $this->option('date');
        $dateRange = $this->option('date-range');
        $sync = $this->option('sync');

        $job = null;

        if ($file) {
            $this->info("Loading from file: {$file}");
            $job = new LoadFboFeedJob(filePath: $file);
        } elseif ($date) {
            $this->info("Loading from SAM.gov API for date: {$date}");
            $job = new LoadFboFeedJob(singleDate: $date);
        } elseif ($dateRange) {
            $parts = explode('-', $dateRange, 2);
            if (count($parts) !== 2) {
                $this->error('Invalid date range format. Use YYYYMMDD-YYYYMMDD');
                return self::FAILURE;
            }
            $this->info("Loading from SAM.gov API: {$parts[0]} to {$parts[1]}");
            $job = new LoadFboFeedJob(startDate: $parts[0], endDate: $parts[1]);
        } else {
            $lookBack = config('fbo.look_back_days', 60);
            $this->info("Loading unloaded feeds from SAM.gov API (lookback: {$lookBack} days)");
            $job = new LoadFboFeedJob();
        }

        if ($sync) {
            $this->info('Running synchronously...');
            $job->handle();
        } else {
            dispatch($job);
            $this->info('Job dispatched to queue.');
        }

        $this->info('Done.');
        return self::SUCCESS;
    }
}
