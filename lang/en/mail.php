<?php

// Email copy — clear, simple, professional. Localized to the recipient. Business names are never translated.
return [
    'subject_suffix' => ' — :brand',
    'automated_notice' => 'This is an automated message from :brand. You can ignore it if you did not request it.',
    'greeting' => 'Hello :name,',
    'greeting_generic' => 'Hello,',
    'cta_open' => 'View details',
    'fallback_hint' => 'If the button does not work, copy this link into your browser:',
    'secondary_link' => 'Related link',
    'footer' => [
        'privacy' => 'Privacy',
        'terms' => 'Terms',
        'help' => 'Help',
    ],
    // Info card — real business labels (no "context" / no technical terms). Only present fields render.
    'meta' => [
        'status' => 'Status',
        'requester' => 'Requested by',
        'due' => 'Due',
    ],
    // Human label for the business object shown (its real name appears beside it).
    'object' => [
        'campaign' => 'Campaign',
        'client' => 'Client',
        'brand' => 'Brand',
        'creator' => 'Creator',
        'nomination' => 'Nomination',
        'content' => 'Content',
        'contract' => 'Contract',
        'invoice' => 'Invoice',
        'collection' => 'Collection',
        'payout' => 'Payout',
        'task' => 'Task',
        'publication' => 'Publication',
        'service_request' => 'Request',
    ],
    'priority' => [
        'urgent' => 'Urgent',
        'high' => 'High',
        'normal' => 'Normal',
        'low' => 'Low',
    ],
];
