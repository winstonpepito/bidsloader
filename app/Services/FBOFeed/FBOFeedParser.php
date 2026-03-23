<?php

namespace App\Services\FBOFeed;

use Carbon\Carbon;
use App\Exceptions\FBOFeedParserException;

class FBOFeedParser
{
    public const FILE_NAME_PREFIX = 'FBOFeed';
    public const FILE_DATE_FORMAT = 'Ymd';

    private static array $htmlTags = [
        'p', 'html', 'meta', 'body', 'table', 'tr', 'td', 'th', 'tbody', 'head',
        'style', 'div', 'span', 'font', 'b', 'br', 'strong', 'ol', 'li', 'ul', 'thead', 'u',
    ];

    private ?Carbon $fboFileDate = null;

    public static function getFboDateFromFilename(string $filename): ?Carbon
    {
        $basename = basename($filename);
        $dateStr = substr($basename, strlen(self::FILE_NAME_PREFIX));
        try {
            return Carbon::createFromFormat(self::FILE_DATE_FORMAT, $dateStr)->startOfDay();
        } catch (\Exception $e) {
            return null;
        }
    }

    public function setFboFileDate(?Carbon $date): void
    {
        $this->fboFileDate = $date;
    }

    /**
     * Parse a feed from raw content and return structured entries and errors.
     */
    public function parseFeed(string $content): FBOFeedParseResult
    {
        $result = new FBOFeedParseResult();
        $lines = explode("\n", str_replace("\r\n", "\n", $content));

        $entryType = null;
        $entryLines = [];

        foreach ($lines as $line) {
            $closeType = $this->isCloseEntry($line);
            if ($closeType !== null) {
                if ($entryType === null || $closeType !== $entryType) {
                    $result->addError(new FBOFeedParserException(
                        'Close tag mismatch. Expected: ' . ($entryType?->closeTag() ?? 'none') . ' Got: ' . $closeType->closeTag(),
                        $line
                    ));
                    $entryType = null;
                    $entryLines = [];
                    continue;
                }

                try {
                    $entry = $this->parseEntry($entryType, $entryLines);
                    $result->addEntry($entry);
                } catch (FBOFeedParserException $e) {
                    $e->setEntryType($entryType->value);
                    $e->setEntryLines($entryLines);
                    $result->addError($e);
                }

                $entryType = null;
                $entryLines = [];
                continue;
            }

            $newType = $this->isNewEntry($line);
            if ($newType !== null) {
                if ($entryType !== null) {
                    $result->addError(new FBOFeedParserException(
                        'Unclosed tag: ' . $entryType->openTag() . ' before new: ' . $newType->openTag(),
                        $line
                    ));
                }
                $entryType = $newType;
                $entryLines = [];
                continue;
            }

            if ($entryType !== null) {
                $entryLines[] = $line;
            }
        }

        if ($entryType !== null) {
            $result->addError(new FBOFeedParserException(
                'Unclosed tag at end of feed: ' . $entryType->openTag()
            ));
        }

        return $result;
    }

    private function isNewEntry(string $line): ?FBOEntryType
    {
        $trimmed = trim($line);
        if ($trimmed === '' || $trimmed[0] !== '<') {
            return null;
        }

        foreach (FBOEntryType::cases() as $type) {
            if ($trimmed === $type->openTag()) {
                return $type;
            }
        }

        return null;
    }

    private function isCloseEntry(string $line): ?FBOEntryType
    {
        $trimmed = trim($line);
        if (strlen($trimmed) < 3 || $trimmed[0] !== '<' || $trimmed[1] !== '/') {
            return null;
        }

        foreach (FBOEntryType::cases() as $type) {
            if ($trimmed === $type->closeTag()) {
                return $type;
            }
        }

        return null;
    }

    private function parseEntry(FBOEntryType $type, array $lines): FBOFeedEntry
    {
        $entry = new FBOFeedEntry($type);
        $entry->setFboFileDate($this->fboFileDate);

        $attributeType = null;
        $attributeLines = [];

        foreach ($lines as $line) {
            $newAttr = $this->isNewAttribute($line);
            if ($newAttr !== null) {
                if ($attributeType !== null) {
                    $this->parseAttribute($entry, $attributeType, $attributeLines);
                }
                $attributeType = $newAttr;
                $attributeLines = [$line];
                continue;
            }
            $attributeLines[] = $line;
        }

        if ($attributeType !== null) {
            $this->parseAttribute($entry, $attributeType, $attributeLines);
        }

        return $entry;
    }

    private function isNewAttribute(string $line): ?FBOAttributeType
    {
        if ($line === '' || $line[0] !== '<') {
            return null;
        }

        foreach (FBOAttributeType::cases() as $type) {
            if (str_starts_with($line, $type->tag())) {
                return $type;
            }
        }

        if (preg_match('/^<([A-Z]+)>/', $line, $m)) {
            $tag = strtolower($m[1]);
            if (!in_array($tag, self::$htmlTags)) {
                // Unknown non-HTML tag; skip silently
            }
        }

        return null;
    }

    private function parseAttribute(FBOFeedEntry $entry, FBOAttributeType $type, array $lines): void
    {
        $cleanLines = [];
        foreach ($lines as $i => $line) {
            if ($i === 0) {
                $afterTag = substr($line, strlen($type->tag()));
                if (trim($afterTag) === '' && trim($line) === $type->tag()) {
                    continue;
                }
                $cleanLines[] = $afterTag;
            } else {
                $cleanLines[] = $line;
            }
        }

        $attrClass = $type->attributeClass();
        $value = match ($attrClass) {
            FBOAttributeClass::MONTH_DAY => $this->parseMonthDay($cleanLines),
            FBOAttributeClass::YEAR => $this->parseYear($cleanLines),
            FBOAttributeClass::SHORT_DATE => $this->parseShortDate($cleanLines),
            FBOAttributeClass::FULL_DATE => $this->parseFullDate($cleanLines),
            FBOAttributeClass::EMAIL => $this->parseEmail($cleanLines),
            FBOAttributeClass::URL => $this->parseUrl($cleanLines),
            FBOAttributeClass::CODE_TITLE => $this->parseCodeTitle($cleanLines),
            default => $this->parseString($cleanLines),
        };

        if ($value !== null) {
            $entry->addAttribute($type, $value);
        }
    }

    private function parseMonthDay(array $lines): ?Carbon
    {
        $text = trim(implode('', $lines));
        if ($text === '') {
            return null;
        }
        try {
            $date = Carbon::createFromFormat('md', substr($text, 0, 4));
            if ($this->fboFileDate) {
                $date->year($this->fboFileDate->year);
            }
            return $date->startOfDay();
        } catch (\Exception $e) {
            return null;
        }
    }

    private function parseYear(array $lines): ?Carbon
    {
        $text = trim(implode('', $lines));
        if ($text === '') {
            return null;
        }
        try {
            return Carbon::createFromFormat('y', substr($text, 0, 2))->startOfYear();
        } catch (\Exception $e) {
            return null;
        }
    }

    private function parseShortDate(array $lines): ?Carbon
    {
        $text = trim(implode('', $lines));
        if ($text === '') {
            return null;
        }
        try {
            return Carbon::createFromFormat('mdy', $text)->startOfDay();
        } catch (\Exception $e) {
            try {
                return Carbon::parse($text)->startOfDay();
            } catch (\Exception $e2) {
                return null;
            }
        }
    }

    private function parseFullDate(array $lines): ?Carbon
    {
        $text = trim(implode('', $lines));
        if ($text === '') {
            return null;
        }
        try {
            return Carbon::createFromFormat('mdY', $text)->startOfDay();
        } catch (\Exception $e) {
            try {
                return Carbon::parse($text)->startOfDay();
            } catch (\Exception $e2) {
                return null;
            }
        }
    }

    private function parseString(array $lines): ?string
    {
        $result = implode("\n", $lines);
        return trim($result) !== '' ? $result : null;
    }

    private function parseEmail(array $lines): ?string
    {
        $emails = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            foreach (explode(';', $line) as $part) {
                $email = trim($part);
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $emails[] = $email;
                }
            }
        }
        return $emails ? $emails[0] : null;
    }

    private function parseUrl(array $lines): ?string
    {
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (filter_var($line, FILTER_VALIDATE_URL)) {
                return $line;
            }
        }
        return null;
    }

    private function parseCodeTitle(array $lines): ?string
    {
        return !empty($lines) ? trim($lines[0]) : null;
    }
}
