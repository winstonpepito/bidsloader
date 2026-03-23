<?php

namespace App\Services\FBOFeed;

enum FBOEntryType: string
{
    case PRESOL = 'PRESOL';
    case COMBINE = 'COMBINE';
    case AMDCSS = 'AMDCSS';
    case MOD = 'MOD';
    case AWARD = 'AWARD';
    case SRCSGT = 'SRCSGT';
    case FSTD = 'FSTD';
    case SNOTE = 'SNOTE';
    case SSALE = 'SSALE';
    case EPSUPLOAD = 'EPSUPLOAD';
    case DELETE = 'DELETE';
    case ARCHIVE = 'ARCHIVE';
    case UNARCHIVE = 'UNARCHIVE';
    case JA = 'JA';
    case FAIROPP = 'FAIROPP';
    case ITB = 'ITB';

    public function openTag(): string
    {
        return '<' . $this->value . '>';
    }

    public function closeTag(): string
    {
        return '</' . $this->value . '>';
    }
}
