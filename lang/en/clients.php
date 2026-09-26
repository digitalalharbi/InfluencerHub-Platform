<?php

/** Clients list — English. Fully translated surface: client + server (excluding the separate export). */
return [
    'title' => 'Clients',
    'eyebrow' => 'Relationship management',
    'sub' => 'Client accounts, profiles, campaigns, and financial follow-up in one operating environment',
    'new_client' => 'New client',

    // KPIs
    'kpi_revenue' => 'Total revenue',
    'kpi_revenue_sub' => ':vip VIP client(s)',
    'kpi_active_campaigns' => 'Running campaigns',
    'kpi_active_campaigns_sub' => ':n client(s) with active campaigns',
    'kpi_pending_payouts' => 'Pending payouts',
    'kpi_pending_payouts_sub' => 'Awaiting approval or payment',
    'kpi_completion' => 'Profile completeness',

    // Segments
    'seg_all' => 'All',
    'seg_active' => 'Active',
    'seg_inactive' => 'Inactive',
    'seg_complete' => 'Complete profile',
    'seg_incomplete' => 'Incomplete',
    'seg_vip' => 'VIP',
    'seg_needs_action' => 'Needs action',
    'seg_with_active_campaigns' => 'Has active campaigns',

    // Search/filter
    'search_placeholder' => 'Search by name or registration…',
    'all_statuses' => 'All statuses',
    'all_sectors' => 'All sectors',
    'all_managers' => 'All managers',

    // Empty states
    'empty_filtered_title' => 'No matching clients',
    'empty_filtered_text' => 'No results for the current search or filters.',
    'clear_filters' => 'Clear filters',
    'empty_title' => 'Start by adding your first client',
    'empty_text' => 'Create a client profile to track its brands, campaigns, and payouts in one place.',

    // Table/cards
    'th_client' => 'Client',
    'th_sector' => 'Sector',
    'th_brands' => 'Brands',
    'th_manager' => 'Account manager',
    'th_campaigns' => 'Campaigns',
    'th_completion' => 'Profile completeness',
    'th_status' => 'Status',
    'th_revenue' => 'Revenue',
    'active_count' => ':n active',
    'open' => 'Open',
    'count_item' => ':n client(s)',
    'filtered_suffix' => ' · filtered',
    'm_active_campaigns' => 'Active campaigns',
    'm_revenue' => 'Revenue',
    'm_completion' => 'Completion',
    'needs_action_note' => ':n item(s) need action',
    'needs_action_tooltip' => 'Items needing action',

    // Create modal
    'f_name' => 'Client name',
    'f_type' => 'Type',
    'f_status' => 'Status',
    'f_sector' => 'Sector',
    'f_email' => 'Email',
    'create' => 'Create client',
    'cancel' => 'Cancel',

    // Client types
    'type_company' => 'Company',
    'type_brand_owner' => 'Brand owner',
    'type_government' => 'Government entity',
    'type_nonprofit' => 'Nonprofit',
    'type_agency' => 'Agency',
    'type_individual' => 'Individual',
    'type_other' => 'Other',

    // Client statuses (server c.statusLabel + filter/modal)
    's_lead' => 'Lead',
    's_qualified' => 'Qualified',
    's_active' => 'Active',
    's_inactive' => 'Inactive',
    's_suspended' => 'Suspended',
    's_archived' => 'Archived',

    // ─────────────────────────────────────────────────────────────
    // Detail page (Show) — fully translated single-client surface
    // ─────────────────────────────────────────────────────────────

    // Currency
    'currency_sar' => 'SAR',

    // Common
    'save' => 'Save',

    // Page header
    'show_heading' => 'Client profile',
    'show_eyebrow' => 'Client · :num',
    'back_all' => 'All clients',
    'm_sector' => 'Sector',
    'm_manager' => 'Account manager',
    'm_city' => 'City',
    'm_classification' => 'Classification',
    'm_vip' => 'VIP',
    'm_regular' => 'Regular',
    'status' => 'Status',
    'archive' => 'Archive',
    'archive_confirm' => 'Archive client ":name"? You can restore it later by changing its status.',

    // Summary strip
    'ss_revenue' => 'Revenue',
    'ss_cost' => 'Cost',
    'ss_profit' => 'Profit',
    'ss_margin' => 'Margin',
    'ss_campaigns' => 'Campaigns',
    'ss_creators' => 'Creators',
    'ss_receivable' => 'Receivable',
    'ss_completion' => 'Completion',

    // Tabs
    'tab_overview' => 'Overview',
    'tab_campaigns' => 'Campaigns',
    'tab_creators' => 'Creators',
    'tab_content' => 'Content',
    'tab_requests' => 'Requests',
    'tab_docs' => 'Contracts & documents',
    'tab_finance' => 'Finance',
    'tab_brands' => 'Brands',
    'tab_contacts' => 'Contacts',
    'tab_team' => 'Team',
    'tab_custom' => 'Custom fields',

    // Overview — next step
    'next_step' => 'Next step',
    'process' => 'Handle',
    'all_clear' => 'Nothing needs attention right now.',

    // Overview — sections
    'sec_risks' => 'Risks',
    'risk_handle' => 'Handle ←',
    'sec_active_campaigns' => 'Active campaigns',
    'link_all_campaigns' => 'All campaigns',
    'no_campaigns_yet' => 'No campaigns yet.',
    'ov_deliverables' => ':n deliverable(s)',
    'sec_activity' => 'Recent activity',
    'no_activity' => 'No recorded activity.',
    'sec_completion' => 'Profile completeness',
    'profile_complete' => 'Profile is complete.',
    'profile_incomplete' => 'Complete the legal and financial details.',
    'sec_contact_info' => 'Contact details',
    'ci_email' => 'Email',
    'ci_phone' => 'Phone',
    'ci_website' => 'Website',
    'ci_city' => 'City',
    'ci_cr' => 'Commercial registration',
    'ci_tax' => 'Tax number',
    'link_all' => 'All',
    'contact_message' => 'Message',

    // Campaigns tab (pipeline)
    'empty_campaigns_title' => 'No campaigns yet',
    'empty_campaigns_hint' => 'This client\'s campaigns will appear here as soon as they are created.',
    'stage_planning' => 'Planning',
    'stage_running' => 'Execution',
    'stage_closed' => 'Closed',
    'no_campaigns_in_stage' => 'No campaigns in this stage.',
    'wcard_published' => 'Published content',
    'wcard_budget' => 'Budget',
    'awaiting_review_n' => ':n awaiting review',

    // Brands tab
    'brand_add' => 'Add brand',
    'brand_name' => 'Brand name',
    'empty_brands_title' => 'No brands yet',
    'empty_brands_hint' => 'This client\'s brands appear here with their activity.',
    'brand_stat_campaigns' => 'Campaigns',
    'brand_stat_active' => 'Active',
    'brand_stat_budget' => 'Budget',

    // Content tab
    'empty_content_title' => 'No content yet',
    'empty_content_hint' => 'Content from this client\'s campaigns appears here for review and approval.',
    'needs_action' => 'Needs action',

    // Contracts & documents tab
    'n_contract_awaiting_sign' => ':n contract(s) awaiting signature',
    'n_contract_expiring' => ':n contract(s) expiring within 30 days',
    'n_doc_expired' => ':n expired document(s)',
    'n_doc_pending' => ':n document(s) awaiting review',
    'sec_contracts' => 'Contracts',
    'no_contracts' => 'No contracts yet.',
    'sign_sent' => 'Sent',
    'sign_signed' => 'Signed',
    'sign_active' => 'Active',
    'expires_on' => 'Expires :date',
    'awaiting_signature' => 'Awaiting signature',
    'expiring_30' => 'Expiring within 30 days',
    'expired' => 'Expired',
    'sec_documents' => 'Documents',
    'doc_upload' => 'Upload document',
    'doc_title' => 'Title',
    'doc_category' => 'Category',
    'doc_cat_contract' => 'Contract',
    'doc_cat_cr' => 'Commercial registration',
    'doc_cat_tax' => 'Tax certificate',
    'doc_cat_other' => 'Other',
    'doc_file' => 'File (up to 20 MB)',
    'no_documents' => 'No documents.',
    'expiring_soon' => 'Expiring soon',

    // Finance tab
    'fin_revenue_sub' => 'From campaigns',
    'fin_cost_sub' => 'Creator fees',
    'fin_margin_sub' => 'Margin :n%',
    'fin_overdue_payout' => 'Overdue payouts',
    'fin_overdue_needs' => 'Needs handling',
    'fin_overdue_none' => 'No overdue',
    'sec_by_campaign' => 'Breakdown by campaign',
    'no_finance_data' => 'No financial data yet.',
    'fin_bars_note' => 'Top bar: budget · bottom: actual cost',
    'sec_receivables_status' => 'Payouts status',
    'fin_paid' => 'Paid',
    'fin_pending' => 'Pending payout',
    'fin_overdue' => 'Overdue',
    'sec_recent_payments' => 'Recent payments',
    'no_payments' => 'No payments made.',
    'sec_payouts' => 'Payouts',
    'no_payouts' => 'No payouts yet.',
    'th_payout' => 'Payout',
    'th_creator' => 'Creator',
    'th_campaign' => 'Campaign',
    'th_due' => 'Due',
    'th_amount' => 'Amount',
    'th_status' => 'Status',

    // Creators tab
    'empty_creators_title' => 'No linked creators',
    'empty_creators_hint' => 'Creators who collaborated on this client\'s campaigns appear here.',
    'rel_active' => 'Collaborating now',
    'rel_recent' => 'Recently collaborated',
    'rel_dormant' => 'Dormant',
    'cr_stat_collabs' => 'Collaborations',
    'cr_stat_published' => 'Published',
    'cr_stat_value' => 'Value',
    'cr_quality' => 'Collaboration quality',
    'cr_last_collab' => 'Last collaboration :date',
    'cr_no_collab' => 'No prior collaboration',
    'cr_new_collab' => 'New collaboration',

    // Requests tab
    'empty_requests_title' => 'No requests',
    'empty_requests_hint' => 'This client\'s requests appear here ordered by urgency.',
    'bucket_overdue' => 'Overdue',
    'bucket_new' => 'New',
    'bucket_open' => 'In progress',
    'bucket_done' => 'Done',
    'unassigned' => 'Unassigned',
    'due_at' => 'Due :date',

    // Contacts tab
    'contact_add' => 'Add contact',
    'contact_name' => 'Name',
    'contact_title' => 'Job title',
    'empty_contacts_title' => 'No contacts',
    'empty_contacts_hint' => 'Add the client\'s contacts for direct communication.',
    'contact_primary' => 'Primary',
    'contact_preferred_channel' => 'Preferred channel',
    'contact_email_btn' => 'Email',
    'contact_call_btn' => 'Call',
    'contact_whatsapp_btn' => 'WhatsApp',
    'contact_has_portal' => 'Has portal access',

    // Team tab
    'invite_member' => 'Invite portal member',
    'role_label' => 'Role',
    'role_client_admin_opt' => 'Client account admin',
    'role_member_opt' => 'Member',
    'invite_token_title' => 'Invite code — shown once',
    'invite_token_hint' => 'Copy it now and hand it to the member; it cannot be retrieved after leaving the page.',
    'th_member' => 'Member',
    'no_members' => 'No members.',

    // Custom fields tab
    'field_define' => 'Define field',
    'field_key' => 'Key',
    'field_label' => 'Label',
    'field_type' => 'Type',
    'ftype_text' => 'Text',
    'ftype_textarea' => 'Long text',
    'ftype_number' => 'Number',
    'ftype_date' => 'Date',
    'ftype_boolean' => 'Yes/No',
    'ftype_url' => 'URL',
    'ftype_email' => 'Email',
    'ftype_phone' => 'Phone',
    'field_set_values' => 'Set values',
    'empty_custom_title' => 'No custom fields',
    'empty_custom_hint' => 'Fields are defined in settings and appear here for each client.',
    'field_required' => 'Required',
    'field_empty' => '— Not set',

    // Server — team roles (CLIENT_ROLE)
    'role_client_admin' => 'Admin',
    'role_client_finance' => 'Finance',
    'role_client_campaign_manager' => 'Campaign manager',
    'role_client_content_reviewer' => 'Content reviewer',
    'role_client_viewer' => 'Viewer',

    // Server — risks / next step
    'risk_sla' => ':n request(s) breaching SLA',
    'risk_awaiting_client' => ':n content item(s) awaiting client',
    'risk_ready_payout' => ':n payout(s) ready to disburse',

    // Server — recent activity
    'act_campaign' => 'Campaign: :name',
    'act_content' => 'Content: :title · :status',
    'act_contract' => 'Contract: :title · :status',
    'act_request' => 'Request: :title · :status',

    // Server — campaign card risk (pipeline)
    'camp_risk_late' => 'Past its due date',
    'camp_risk_over_budget' => 'Over budget',
    'camp_risk_awaiting' => ':n content item(s) awaiting review',

    // Server — content stages (contentStages)
    'cs_draft' => 'Draft',
    'cs_agency_review' => 'Agency review',
    'cs_client_review' => 'Client review',
    'cs_changes_requested' => 'Changes requested',
    'cs_approved' => 'Approved',
    'cs_scheduled' => 'Scheduled',
    'cs_published' => 'Published',

    // Server — request priority (priorityLabel)
    'prio_low' => 'Low',
    'prio_normal' => 'Normal',
    'prio_high' => 'High',
    'prio_urgent' => 'Urgent',

    // Server — request blocked reason (blocked)
    'blk_sla' => 'SLA breached',
    'blk_needs_info' => 'Awaiting info',
];
