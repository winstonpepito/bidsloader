<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateOracleIndexes extends Command
{
    protected $signature = 'oracle:create-indexes';

    protected $description = 'Create performance indexes on the live Oracle BID table';

    public function handle(): int
    {
        $oracle = DB::connection('oracle');

        $indexes = [
            'IDX_BID_FEDDATE' => 'CREATE INDEX IDX_BID_FEDDATE ON BID (FEDDATE)',
            'IDX_BID_SOLNUM'  => 'CREATE INDEX IDX_BID_SOLNUM ON BID (SOLICITATIONNUMBER)',
        ];

        foreach ($indexes as $name => $sql) {
            $this->info("Creating index {$name}...");

            try {
                $exists = $oracle->selectOne(
                    "SELECT COUNT(*) AS CNT FROM USER_INDEXES WHERE INDEX_NAME = ?",
                    [$name]
                );

                if (($exists->CNT ?? $exists->cnt ?? 0) > 0) {
                    $this->warn("  Index {$name} already exists — skipped.");
                    continue;
                }

                $oracle->statement($sql);
                $this->info("  Created {$name} successfully.");
            } catch (\Exception $e) {
                $this->error("  Failed: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info('Done. Queries on FEDDATE and SOLICITATIONNUMBER should now be fast.');

        return self::SUCCESS;
    }
}
