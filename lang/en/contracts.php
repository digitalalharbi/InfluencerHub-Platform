<?php

/** Contracts list — English. Fully translated surface: client + server. */
return [
    'title' => 'Contracts',
    'eyebrow' => 'Operations',
    'sub' => 'Client and creator contracts: issue, send, activate, and track value and duration',
    'new_contract' => 'New contract',

    // KPIs
    'kpi_active' => 'Active contracts',
    'kpi_active_sub' => ':n signed',
    'kpi_awaiting' => 'Awaiting signature',
    'kpi_awaiting_sub' => 'Sent to the counterparty',
    'kpi_active_value' => 'Active contract value',
    'kpi_active_value_sub' => 'Signed/active',
    'kpi_completed' => 'Completed',
    'kpi_completed_sub' => ':n draft(s)',

    // Segments
    'seg_all' => 'All',
    'seg_draft' => 'Draft',
    'seg_sent' => 'Sent',
    'seg_signed' => 'Signed',
    'seg_active' => 'Active',
    'seg_completed' => 'Completed',
    'seg_terminated' => 'Terminated',
    'seg_cancelled' => 'Cancelled',

    'search_placeholder' => 'Search by contract title or number…',

    // Empty states
    'empty_filtered_title' => 'No matching contracts',
    'empty_filtered_text' => 'No results for the current search or segment.',
    'clear_filters' => 'Clear filters',
    'empty_title' => 'No contracts yet',
    'empty_text' => 'Contracts issued to clients and creators appear here.',

    // Contract workspace buckets
    'b_awaiting' => 'Awaiting signature',
    'b_active' => 'Active',
    'b_draft' => 'Drafts',
    'b_closed' => 'Closed',

    // Cards
    'sent_prefix' => 'Sent',
    'signed_prefix' => 'Signed',
    'ends' => 'Ends',
    'expiring_soon' => 'Expires within 30 days',
    'expired' => 'Expired',
    'count_item' => ':n contract(s)',
    'm_value' => 'Value',

    // Contract parties (server partyType + modal)
    'pt_creator' => 'Creator',
    'pt_client' => 'Client',

    // Create modal
    'f_party' => 'Party',
    'f_creator' => 'Creator',
    'f_client' => 'Client',
    'f_title' => 'Contract title',
    'f_value' => 'Value (SAR)',
    'f_start' => 'Start',
    'f_end' => 'End',
    'f_terms' => 'Terms',
    'choose' => '— Choose —',
    'create_draft' => 'Create draft',
    'cancel' => 'Cancel',

    // Detail page (Show)
    'show_heading' => 'Contract',
    'show_eyebrow' => 'Contract · :num',
    'back_all' => 'All contracts',
    'm_signed_by' => 'Accepted by',
    'preview_pdf' => 'Preview PDF',
    'preview_pdf_title' => 'Preview contract (PDF)',
    'accepted_at' => 'Accepted on :date',
    'accepted_in_platform' => 'In-platform acceptance (recorded consent, not an external legal signature).',
    'sec_terms' => 'Contract terms',
    'sec_history' => 'Status log',
    'edit_draft' => 'Edit draft',
    'save' => 'Save',
    'no_terms' => 'No terms recorded.',
    'no_history' => 'No log yet.',
    'reason_placeholder' => 'Reason',
    'confirm' => 'Confirm',

    // Workflow action labels
    'act_send' => 'Send to party',
    'act_cancel' => 'Cancel',
    'act_activate' => 'Activate contract',
    'act_complete' => 'Complete',
    'act_terminate' => 'Terminate',
];
