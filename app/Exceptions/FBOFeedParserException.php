<?php

namespace App\Exceptions;

use Exception;

class FBOFeedParserException extends Exception
{
    private ?string $entryType = null;
    private array $entryLines = [];
    private ?string $sourceLine = null;

    public function __construct(string $message, ?string $sourceLine = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->sourceLine = $sourceLine;
    }

    public function getEntryType(): ?string
    {
        return $this->entryType;
    }

    public function setEntryType(?string $type): void
    {
        $this->entryType = $type;
    }

    public function getEntryLines(): array
    {
        return $this->entryLines;
    }

    public function setEntryLines(array $lines): void
    {
        $this->entryLines = $lines;
    }

    public function getSourceLine(): ?string
    {
        return $this->sourceLine;
    }
}
