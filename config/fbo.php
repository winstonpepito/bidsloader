<?php

return [
    'sam_api_url' => env('SAM_API_URL', 'https://api.sam.gov/opportunities/v2/search'),
    'sam_api_key' => env('SAM_API_KEY', ''),
    'sam_api_page_size' => (int) env('SAM_API_PAGE_SIZE', 1000),
    'sam_fetch_descriptions' => (bool) env('SAM_FETCH_DESCRIPTIONS', false),

    'look_back_days' => (int) env('FBO_LOOK_BACK_DAYS', 60),
    'retry_count' => (int) env('FBO_RETRY_COUNT', 3),
    'retry_timeout' => (int) env('FBO_RETRY_TIMEOUT', 5),

    'invalid_solicitation_numbers' => env('FBO_INVALID_SOL_NUMBERS', 'n/a,not applicable,none,nosolicitation'),

    'email_to' => env('FBO_EMAIL_TO', ''),
    'email_from' => env('FBO_EMAIL_FROM', ''),
    'email_subject' => env('FBO_EMAIL_SUBJECT', 'FBO FeedLoader Notification'),
];
