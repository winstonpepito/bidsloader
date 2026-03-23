<?php

namespace App\Services\FBOFeed;

use Carbon\Carbon;

class FBOFeedEntry
{
    public const UID_SEPARATOR = '~';

    private FBOEntryType $entryType;
    private array $attributes = [];
    private ?Carbon $fboFileDate = null;
    private ?string $thirdPartyIdentifier = null;
    private ?string $cachedDescription = null;

    public function __construct(FBOEntryType $entryType)
    {
        $this->entryType = $entryType;
    }

    public function getEntryType(): FBOEntryType
    {
        return $this->entryType;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function setFboFileDate(?Carbon $date): void
    {
        $this->fboFileDate = $date;
    }

    public function fboFileDate(): ?Carbon
    {
        return $this->fboFileDate;
    }

    public function addAttribute(FBOAttributeType $type, mixed $value): void
    {
        if ($type === FBOAttributeType::DESC) {
            $newDesc = is_array($value) ? ($value[0] ?? null) : $value;
            if ($newDesc !== null && strtolower(trim($newDesc)) === 'link to document') {
                return;
            }
            $currentDesc = $this->getStringAttribute(FBOAttributeType::DESC);
            if ($currentDesc !== null) {
                if ($newDesc === null || mb_strlen($newDesc) < mb_strlen($currentDesc)) {
                    return;
                }
            }
        }
        $this->attributes[$type->value] = $value;
    }

    public function getAttribute(FBOAttributeType $type): mixed
    {
        return $this->attributes[$type->value] ?? null;
    }

    public function getStringAttribute(FBOAttributeType $type): ?string
    {
        $value = $this->getAttribute($type);
        if (is_array($value)) {
            return $value[0] ?? null;
        }
        return $value !== null ? trim((string) $value) : null;
    }

    public function getDateAttribute(FBOAttributeType $type): ?Carbon
    {
        $value = $this->getAttribute($type);
        if ($value instanceof Carbon) {
            return $value;
        }
        if (is_array($value) && isset($value[0]) && $value[0] instanceof Carbon) {
            return $value[0];
        }
        return null;
    }

    public static function prepareEntityName(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        $stop = strlen($value);
        for ($i = 0; $i < $stop; $i++) {
            $c = $value[$i];
            if ($c === "\n" || $c === "\r" || $c === ',') {
                return substr($value, 0, $i);
            }
        }
        return $value;
    }

    public function agency(): ?string
    {
        $agency = $this->getStringAttribute(FBOAttributeType::AGENCY);
        return $agency !== null ? self::prepareEntityName($agency) : null;
    }

    public function category(): ?string
    {
        return $this->getStringAttribute(FBOAttributeType::CLASSCOD);
    }

    public function subject(): string
    {
        $subject = $this->getStringAttribute(FBOAttributeType::SUBJECT);
        if ($subject === null) {
            return 'N/A';
        }
        $idx = strpos($subject, '--');
        if ($idx !== false) {
            $subject = trim(substr($subject, $idx + 2));
        }
        return $subject ?: 'N/A';
    }

    public function contact(): ?string
    {
        return $this->getStringAttribute(FBOAttributeType::CONTACT);
    }

    public function description(): string
    {
        if ($this->cachedDescription !== null) {
            return $this->cachedDescription;
        }

        $desc = $this->getStringAttribute(FBOAttributeType::DESC);
        $subject = $this->subject();

        if ($desc === null || mb_strlen($desc) < mb_strlen($subject)) {
            $desc = $subject;
        }

        $isHtml = strip_tags($desc) !== $desc;
        $nl = $isHtml ? '<br>' : "\n";

        $buffer = $desc . $nl . $nl . '<b>Contact Information</b>' . $nl;

        $additional = $this->contact();
        if ($additional) {
            $buffer .= $nl . $additional;
        }

        $additional = $this->officeAddress();
        if ($additional) {
            $buffer .= $nl . 'Office Address: ' . $additional;
        }

        $additional = $this->zip();
        if ($additional) {
            $buffer .= $nl . 'ZIP code: ' . $additional;
        }

        $additional = $this->popAddress();
        if ($additional) {
            $buffer .= $nl . 'Place of performance address: ' . $additional;
        }

        $additional = $this->popZip();
        if ($additional) {
            $buffer .= $nl . 'Place of performance ZIP code: ' . $additional;
        }

        $additional = $this->popCountry();
        if ($additional) {
            $buffer .= $nl . 'Place of performance country: ' . $additional;
        }

        $additional = $this->naicsCode();
        if ($additional) {
            $buffer .= $nl . 'NAICS: ' . $additional;
        }

        $additional = $this->location();
        if ($additional) {
            $buffer .= $nl . 'Location: ' . $additional;
        }

        $this->cachedDescription = $buffer;
        return $this->cachedDescription;
    }

    public function title(): ?string
    {
        $subject = $this->subject();
        return $subject !== null ? mb_substr(trim($subject), 0, 255) : null;
    }

    public function fedDate(): Carbon
    {
        $date = $this->getDateAttribute(FBOAttributeType::DATE);
        if ($date === null || $date->isFuture()) {
            return Carbon::now();
        }
        return $date;
    }

    public function endDate(): Carbon
    {
        $date = $this->getDateAttribute(FBOAttributeType::RESPDATE);
        if ($date === null) {
            $date = $this->getDateAttribute(FBOAttributeType::ARCHDATE);
        }
        if ($date === null) {
            $date = Carbon::now()->addDays(14);
        }
        return $date;
    }

    public function email(): ?string
    {
        return $this->getStringAttribute(FBOAttributeType::EMAIL);
    }

    public function url(): ?string
    {
        return $this->getStringAttribute(FBOAttributeType::URL);
    }

    public function thirdPartyIdentifier(): string
    {
        if ($this->thirdPartyIdentifier !== null) {
            return $this->thirdPartyIdentifier;
        }

        $solNumber = $this->solicitationNumber() ?? '';
        $crc = crc32($this->toStringEntry());

        $this->thirdPartyIdentifier = implode(self::UID_SEPARATOR, [
            $this->entryType->value,
            $solNumber,
            $this->endDate()->getTimestampMs(),
            $crc,
        ]);

        return $this->thirdPartyIdentifier;
    }

    public function naicsCode(): ?string
    {
        return $this->getStringAttribute(FBOAttributeType::NAICS);
    }

    public function solicitationNumber(): ?string
    {
        return $this->getStringAttribute(FBOAttributeType::SOLNBR);
    }

    public function setAsideCode(): ?string
    {
        return $this->getStringAttribute(FBOAttributeType::SETASIDE);
    }

    public function popZip(): ?string
    {
        return $this->getStringAttribute(FBOAttributeType::POPZIP);
    }

    public function popCountry(): ?string
    {
        return $this->getStringAttribute(FBOAttributeType::POPCOUNTRY);
    }

    public function location(): ?string
    {
        return $this->getStringAttribute(FBOAttributeType::LOCATION);
    }

    public function zip(): ?string
    {
        return $this->getStringAttribute(FBOAttributeType::ZIP);
    }

    public function popAddress(): ?string
    {
        return $this->getStringAttribute(FBOAttributeType::POPADDRESS);
    }

    public function officeAddress(): ?string
    {
        return $this->getStringAttribute(FBOAttributeType::OFFADD);
    }

    public function office(): ?string
    {
        return $this->getStringAttribute(FBOAttributeType::OFFICE);
    }

    public function toStringEntry(): string
    {
        $buffer = $this->entryType->openTag() . "\n";
        foreach ($this->attributes as $key => $value) {
            $attrType = FBOAttributeType::from($key);
            $strValue = is_array($value) ? implode("\n", $value) : (string) $value;
            $buffer .= $attrType->tag() . $strValue . "\n";
        }
        $buffer .= $this->entryType->closeTag();
        return $buffer;
    }
}
