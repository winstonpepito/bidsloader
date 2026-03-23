<?php

namespace App\Services\FBOFeed;

use App\Services\FBOFeed\Processors\AmdcssProcessor;
use App\Services\FBOFeed\Processors\BaseProcessor;
use App\Services\FBOFeed\Processors\CombineProcessor;
use App\Services\FBOFeed\Processors\ModProcessor;
use App\Services\FBOFeed\Processors\PresolProcessor;
use App\Services\FBOFeed\Processors\SnoteProcessor;
use App\Services\FBOFeed\Processors\SrcsgtProcessor;

class ProcessorDispatcher
{
    private array $processors = [];

    public function __construct()
    {
        $this->processors = [
            FBOEntryType::PRESOL->value => new PresolProcessor(),
            FBOEntryType::COMBINE->value => new CombineProcessor(),
            FBOEntryType::SRCSGT->value => new SrcsgtProcessor(),
            FBOEntryType::SNOTE->value => new SnoteProcessor(),
            FBOEntryType::AMDCSS->value => new AmdcssProcessor(),
            FBOEntryType::MOD->value => new ModProcessor(),
            FBOEntryType::ITB->value => new PresolProcessor(),
        ];
    }

    public function setStaging(bool $staging): void
    {
        foreach ($this->processors as $processor) {
            $processor->setStaging($staging);
        }
    }

    public function getProcessor(FBOEntryType $type): ?BaseProcessor
    {
        return $this->processors[$type->value] ?? null;
    }

    public function hasProcessor(FBOEntryType $type): bool
    {
        return isset($this->processors[$type->value]);
    }

    /** @return string[] */
    public function supportedTypes(): array
    {
        return array_keys($this->processors);
    }
}
