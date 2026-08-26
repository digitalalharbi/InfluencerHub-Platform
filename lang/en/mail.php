<?php

// Email copy — localized to the recipient's preferredLocale. Business/entity names are never translated.
return [
    'subject_suffix' => ' — :brand',
    'automated_notice' => 'This is an automated message from :brand. You can ignore it if you did not request it.',
    'cta_open' => 'Open in :brand',
    'fallback_hint' => 'If the button does not work, copy this link into your browser:',
    'secondary_link' => 'Related link',
    'footer' => [
        'privacy' => 'Privacy',
        'terms' => 'Terms',
        'help' => 'Help',
    ],
    // Context card (only present fields render — nothing is invented)
    'meta' => [
        'context' => 'Context',
        'status' => 'Status',
        'requester' => 'Requested by',
        'due' => 'Due',
        'priority' => 'Priority',
    ],
    'priority' => [
        'urgent' => 'Urgent',
        'high' => 'High',
        'normal' => 'Normal',
        'low' => 'Low',
    ],
];
