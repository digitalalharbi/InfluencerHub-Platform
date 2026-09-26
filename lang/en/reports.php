<?php

/** Reports page — English mirror of ar/reports.php. */
return [
    'title' => 'Reports',
    'eyebrow' => 'Data & reports',
    'sub' => 'An aggregate view of financial and operational performance — derived from real PostgreSQL data',
    'pdf_preview' => 'Preview PDF',
    'pdf_preview_title' => 'Preview PDF report',

    // Financial KPIs
    'fin_revenue' => 'Net revenue',
    'fin_revenue_sub' => 'Ex. tax :tax · billed :billed',
    'fin_cost' => 'Creator cost',
    'fin_cost_sub' => ':paid disbursed',
    'fin_profit' => 'Profit',
    'fin_margin' => 'Margin',
    'fin_collected' => 'Collected',
    'fin_collected_sub' => ':out outstanding',

    // Timeline
    'sec_paid_timeline' => 'Paid to creators — last 6 months',
    'sec_campaigns_content' => 'Campaigns & published content',
    'tl_campaigns_published' => ':c campaign(s) · :p published',

    // Distribution & top clients
    'sec_campaign_dist' => 'Campaign distribution',
    'center_campaign' => 'campaign(s)',
    'sec_top_clients' => 'Top clients by revenue',
    'no_revenue' => 'No revenue recorded yet.',
    'tc_active' => ':n active',

    // Operational KPIs
    'op_clients' => 'Clients',
    'active_n' => ':n active',
    'op_creators' => 'Creators',
    'op_campaigns' => 'Campaigns',
    'op_campaigns_sub' => ':n active · budget :budget',
    'op_requests' => 'Open requests',
    'op_requests_sub' => ':n overdue',
    'op_content' => 'Published content',
    'op_content_sub' => ':n awaiting review',
    'op_collabs' => 'Collaborations',
    'op_collabs_sub' => 'Total',

    // By-status breakdowns
    'bd_campaigns' => 'Campaigns by status',
    'bd_requests' => 'Requests by status',
    'bd_content' => 'Content by status',
    'bd_collabs' => 'Collaborations by status',
    'sec_creators_type' => 'Creators by type',
    'no_data' => 'No data.',

    // Month names (timeline) — resolved in the controller
    'mon_01' => 'January',
    'mon_02' => 'February',
    'mon_03' => 'March',
    'mon_04' => 'April',
    'mon_05' => 'May',
    'mon_06' => 'June',
    'mon_07' => 'July',
    'mon_08' => 'August',
    'mon_09' => 'September',
    'mon_10' => 'October',
    'mon_11' => 'November',
    'mon_12' => 'December',
];
