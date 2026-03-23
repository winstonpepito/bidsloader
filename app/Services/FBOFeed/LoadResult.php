<?php

namespace App\Services\FBOFeed;

class LoadResult
{
    public function __construct(
        public readonly string $date,
        public readonly string $filename,
        public readonly bool $success,
        public readonly int $entriesLoaded,
        public readonly int $errorsCount,
        public readonly string $message = '',
    ) {
    }

    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'filename' => $this->filename,
            'success' => $this->success,
            'entries_loaded' => $this->entriesLoaded,
            'errors_count' => $this->errorsCount,
            'message' => $this->message,
        ];
    }
}
