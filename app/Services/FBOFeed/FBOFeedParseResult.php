<?php

namespace App\Services\FBOFeed;

use App\Exceptions\FBOFeedParserException;

class FBOFeedParseResult
{
    /** @var FBOFeedEntry[] */
    private array $entries = [];

    /** @var FBOFeedParserException[] */
    private array $errors = [];

    public function addEntry(FBOFeedEntry $entry): void
    {
        $this->entries[] = $entry;
    }

    public function addError(FBOFeedParserException $error): void
    {
        $this->errors[] = $error;
    }

    /** @return FBOFeedEntry[] */
    public function getEntries(): array
    {
        return $this->entries;
    }

    /** @return FBOFeedParserException[] */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function entryCount(): int
    {
        return count($this->entries);
    }

    public function errorCount(): int
    {
        return count($this->errors);
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }
}
