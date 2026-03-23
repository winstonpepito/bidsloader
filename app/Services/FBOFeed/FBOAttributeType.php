<?php

namespace App\Services\FBOFeed;

enum FBOAttributeType: string
{
    case DATE = 'DATE';
    case YEAR = 'YEAR';
    case CBAC = 'CBAC';
    case PASSWORD = 'PASSWORD';
    case ZIP = 'ZIP';
    case CLASSCOD = 'CLASSCOD';
    case NAICS = 'NAICS';
    case OFFADD = 'OFFADD';
    case SUBJECT = 'SUBJECT';
    case SOLNBR = 'SOLNBR';
    case RESPDATE = 'RESPDATE';
    case ARCHDATE = 'ARCHDATE';
    case CONTACT = 'CONTACT';
    case DESC = 'DESC';
    case LINK = 'LINK';
    case URL = 'URL';
    case EMAIL = 'EMAIL';
    case ADDRESS = 'ADDRESS';
    case SETASIDE = 'SETASIDE';
    case POPADDRESS = 'POPADDRESS';
    case POPZIP = 'POPZIP';
    case POPCOUNTRY = 'POPCOUNTRY';
    case AWDNBR = 'AWDNBR';
    case AWDAMT = 'AWDAMT';
    case LINENBR = 'LINENBR';
    case AWDDATE = 'AWDDATE';
    case AWARDEE = 'AWARDEE';
    case AGENCY = 'AGENCY';
    case LOCATION = 'LOCATION';
    case OFFICE = 'OFFICE';
    case STAUTH = 'STAUTH';
    case MODNBR = 'MODNBR';
    case MIMETYPE = 'MIMETYPE';
    case NTYPE = 'NTYPE';

    public function tag(): string
    {
        return '<' . $this->value . '>';
    }

    public function attributeClass(): FBOAttributeClass
    {
        return match ($this) {
            self::DATE => FBOAttributeClass::MONTH_DAY,
            self::YEAR => FBOAttributeClass::YEAR,
            self::RESPDATE, self::AWDDATE => FBOAttributeClass::SHORT_DATE,
            self::ARCHDATE => FBOAttributeClass::FULL_DATE,
            self::DESC => FBOAttributeClass::LONG_STRING,
            self::ZIP, self::POPZIP => FBOAttributeClass::STRING,
            self::EMAIL => FBOAttributeClass::EMAIL,
            self::URL => FBOAttributeClass::URL,
            self::OFFADD, self::ADDRESS, self::POPADDRESS => FBOAttributeClass::ADDRESS,
            self::SUBJECT => FBOAttributeClass::CODE_TITLE,
            self::CONTACT, self::AWARDEE => FBOAttributeClass::CONTACT_INFO,
            self::AWDAMT => FBOAttributeClass::MONEY,
            default => FBOAttributeClass::STRING,
        };
    }
}
