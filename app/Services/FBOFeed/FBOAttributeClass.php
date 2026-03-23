<?php

namespace App\Services\FBOFeed;

enum FBOAttributeClass: string
{
    case MONTH_DAY = 'month_day';
    case YEAR = 'year';
    case FULL_DATE = 'full_date';
    case SHORT_DATE = 'short_date';
    case STRING = 'string';
    case LONG_STRING = 'long_string';
    case ZIP = 'zip';
    case EMAIL = 'email';
    case URL = 'url';
    case ADDRESS = 'address';
    case CODE_TITLE = 'code_title';
    case CONTACT_INFO = 'contact_info';
    case MONEY = 'money';
    case NTYPE = 'ntype';
}
