<?php

/** Payouts list — English. Fully translated surface (status from shared statuses). */
return [
    'title' => 'Payouts',
    'eyebrow' => 'Finance',
    'sub' => 'Creator payouts: approve, schedule, and record disbursement — the system runs no transfers (manual recording)',
    'new_payout' => 'New payout',

    // KPIs
    'kpi_open' => 'Open payable',
    'kpi_open_sub' => ':n payment(s)',
    'kpi_ready' => 'Ready to pay',
    'kpi_ready_sub' => ':n approved/scheduled',
    'kpi_paid' => 'Paid',
    'kpi_paid_sub' => ':n payment(s)',
    'kpi_waiting' => 'Awaiting provider',
    'kpi_waiting_sub' => ':n failed',

    // Disbursement overview (stage donut)
    'stage_in_process' => 'In process',
    'stage_ready' => 'Ready to pay',
    'stage_paid' => 'Paid',
    'donut_center' => 'Total SAR',
    'donut_title' => 'Payouts by stage',

    // Segments
    'seg_all' => 'All',
    'seg_open' => 'Open',
    'seg_ready' => 'Ready to pay',
    'seg_pending' => 'Pending approval',
    'seg_waiting' => 'Awaiting provider',
    'seg_paid' => 'Paid',
    'seg_failed' => 'Failed',

    'search_placeholder' => 'Search by payout number or creator…',

    // Empty states
    'empty_filtered_title' => 'No matching payouts',
    'empty_filtered_text' => 'No results for the current search or segment.',
    'clear_filters' => 'Clear filters',
    'empty_title' => 'No payouts yet',
    'empty_text' => 'Creator payouts appear here once created.',

    // Disbursement buckets
    'b_ready' => 'Ready to pay',
    'b_pending' => 'Awaiting approval',
    'b_paid' => 'Paid',
    'b_closed' => 'Closed',
    'overdue_prefix' => 'Overdue',
    'count_item' => ':n payout(s)',

    // Create modal
    'modal_note' => 'A payout is recorded for tracking and approval only — the system runs no financial transfer.',
    'f_creator' => 'Creator',
    'choose' => '— Choose —',
    'f_amount' => 'Amount (SAR)',
    'f_due' => 'Due date',
    'f_description' => 'Description',
    'desc_placeholder' => 'Campaign collaboration fee…',
    'create' => 'Create payout',
    'cancel' => 'Cancel',
];
