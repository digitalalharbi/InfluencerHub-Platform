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
];
