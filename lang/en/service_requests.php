<?php

/** Service request detail (Show) + priority/type labels — English mirror of ar/service_requests.php. */
return [
    // Detail page (Show)
    'show_heading' => 'Request',
    'show_eyebrow' => 'Request · :num',
    'back_all' => 'All requests',
    'm_client' => 'Client',
    'm_type' => 'Type',
    'm_assignee' => 'Assigned to',
    'unassigned' => 'Unassigned',
    'm_due' => 'Due',
    'open_campaign' => 'Open campaign :num',
    'convert_to_campaign' => 'Convert to campaign',

    // Campaign brief
    'brief_title' => 'Campaign brief — carries over to the campaign on conversion',
    'brief_brand' => 'Brand',
    'brief_budget' => 'Budget',
    'brief_start' => 'Preferred start',
    'brief_end' => 'Preferred end',
    'brief_platforms' => 'Requested platforms',
    'brief_scope' => 'Scope of work',
    'brief_moved' => 'This brief has already been moved to the campaign.',
    'brief_will_move' => 'All of this carries over to the campaign automatically — no re-entry.',

    // Summary strip
    'ss_priority' => 'Priority',
    'ss_created' => 'Created',
    'ss_resolved' => 'Resolved',

    // SLA (:n hours)
    'sla_overdue' => 'Overdue :nh',
    'sla_soon' => 'Within :nh',
    'sla_ok' => ':nh left',

    // Sections
    'sec_details' => 'Request details',
    'no_description' => 'No description.',
    'sec_comments' => 'Comments',
    'sec_comments_n' => 'Comments (:n)',
    'comment_placeholder' => 'Add an internal comment…',
    'add' => 'Add',
    'no_comments' => 'No comments yet.',
    'internal_tag' => 'internal',
    'sec_assign' => 'Assignment',
    'choose_member' => '— Choose a member —',
    'assign_btn' => 'Assign',
    'sec_history' => 'Status log',
    'no_history' => 'No log yet.',

    // Reason modal
    'reason_placeholder' => 'Reason / note',
    'confirm' => 'Confirm',
    'cancel' => 'Cancel',

    // Workflow action labels
    'act_triage' => 'Start triage',
    'act_cancel' => 'Cancel',
    'act_start' => 'Start work',
    'act_request_info' => 'Request info',
    'act_resolve' => 'Resolve request',
    'act_resume' => 'Resume work',
    'act_close' => 'Close request',
    'act_reopen' => 'Reopen',

    // Priority (ServiceRequestPriority)
    'prio_low' => 'Low',
    'prio_normal' => 'Normal',
    'prio_high' => 'High',
    'prio_urgent' => 'Urgent',

    // Type (ServiceRequestType)
    'type_campaign' => 'Campaign',
    'type_content' => 'Content',
    'type_report' => 'Report',
    'type_consultation' => 'Consultation',
    'type_other' => 'Other',

    // ===== List page (Index) =====
    'idx_title' => 'Requests',
    'idx_eyebrow' => 'Operations',
    'idx_sub' => 'The inbound request queue: triage, assign, and track response times (SLA)',
    'idx_new' => 'New request',

    // KPIs
    'kpi_open' => 'Open',
    'kpi_open_sub' => ':triage in triage · :prog in progress',
    'kpi_breached' => 'SLA breached',
    'kpi_breached_sub' => 'Need urgent attention',
    'kpi_unassigned' => 'Unassigned',
    'kpi_unassigned_sub' => 'Awaiting assignment',
    'kpi_mine' => 'Assigned to me',
    'kpi_mine_sub' => ':n due today',

    // Segments
    'seg_all' => 'All',
    'seg_mine' => 'Assigned to me',
    'seg_unassigned' => 'Unassigned',
    'seg_breached' => 'SLA breached',
    'seg_triage' => 'In triage',
    'seg_in_progress' => 'In progress',
    'seg_needs_info' => 'Awaiting info',
    'seg_resolved' => 'Resolved',

    'search_placeholder' => 'Search by title, number, or client…',
    'all_priorities' => 'All priorities',

    // Empty states
    'empty_filtered_title' => 'No matching requests',
    'empty_filtered_text' => 'No results for the current search or segment.',
    'clear_filters' => 'Clear filters',
    'empty_title' => 'No requests yet',
    'empty_text' => 'Requests arrive from the client portal, and you can log one on their behalf if it comes by phone or email.',
    'empty_action' => 'Log a request',

    // Triage queue (buckets)
    'bk_overdue' => 'Overdue',
    'bk_new' => 'New',
    'bk_open' => 'In progress',
    'bk_done' => 'Closed',
    'unassigned_dash' => 'Unassigned',
    'idx_sla_hours' => ':nh',
    'filtered_suffix' => ' · filtered',
    'count_item' => ':n request(s)',

    // New-request modal
    'f_client' => 'Client',
    'choose_client' => 'Choose a client…',
    'f_brand' => 'Brand',
    'no_brand' => 'No specific brand',
    'f_type' => 'Request type',
    'f_priority' => 'Priority',
    'f_title' => 'Request title',
    'f_desc' => 'Request description',
    'brief_hint' => 'Campaign brief — carries over to the campaign automatically on conversion',
    'f_budget' => 'Budget (SAR)',
    'f_start' => 'Start',
    'f_end' => 'End',
    'f_platforms' => 'Platforms',
    'f_scope' => 'Scope notes',
    'saving' => 'Saving…',
    'submit' => 'Log the request',
    'save_hint' => 'Choose a client and enter a title to enable saving',
];
