<?php

/** Brands list — English. Fully translated surface: client + server. */
return [
    'title' => 'Brands',
    'eyebrow' => 'Relationship management',
    'sub' => 'Client brand profiles and their approval/review queue',

    // KPIs
    'kpi_total' => 'Total brands',
    'kpi_total_sub' => ':approved approved',
    'kpi_needs_review' => 'Awaiting your review',
    'kpi_needs_review_sub' => ':submitted submitted · :under_review under review',
    'kpi_changes' => 'Changes requested',
    'kpi_changes_sub' => 'Awaiting client revision',
    'kpi_suspended' => 'Suspended',
    'kpi_suspended_sub' => 'Suspended brands',

    // Segments
    'seg_all' => 'All',
    'seg_needs_review' => 'Awaiting review',
    'seg_submitted' => 'Submitted',
    'seg_under_review' => 'Under review',
    'seg_changes_requested' => 'Changes requested',
    'seg_approved' => 'Approved',
    'seg_suspended' => 'Suspended',
    'seg_draft' => 'Draft',

    'search_placeholder' => 'Search by brand or client name…',

    // Empty states
    'empty_filtered_title' => 'No matching brands',
    'empty_filtered_text' => 'No results for the current search or segment.',
    'clear_filters' => 'Clear filters',
    'empty_title' => 'No brands yet',
    'empty_text_no_clients' => 'A brand belongs to a client, and you have no clients yet. Start by adding a client, then add their brands.',
    'empty_text_has_clients' => 'A brand is created from its client page, and appears here after it is submitted for approval.',
    'add_client' => 'Add client',

    // Table/cards
    'th_brand' => 'Brand',
    'th_client' => 'Client',
    'th_sector' => 'Sector',
    'th_version' => 'Version',
    'th_submitted' => 'Submitted',
    'th_status' => 'Status',
    'needs_review' => 'Needs review',
    'needs_your_review' => '● Needs your review',
    'open' => 'Open',
    'count_item' => ':n brand(s)',
    'filtered_suffix' => ' · filtered',

    // Brand creator (server — createHint)
    'add_brand_for' => 'Add a brand for :client',
    'choose_client' => 'Choose a client',

    // Detail page (Show)
    'show_heading' => 'Brand',
    'show_eyebrow' => 'Brand · :client',
    'back_all' => 'All brands',
    'm_sector' => 'Sector',
    'm_version' => 'Version',
    'm_submitted' => 'Submitted',
    'm_reviewed' => 'Reviewed',
    'changes_label' => 'Changes requested:',
    'ss_campaigns' => 'Campaigns',
    'ss_budget' => 'Budget',
    'ss_content' => 'Content',
    'ss_awaiting' => 'Awaiting review',
    'currency_sar' => 'SAR',

    // Tabs
    'tab_overview' => 'Overview',
    'tab_campaigns' => 'Campaigns',
    'tab_content' => 'Content',
    'tab_accounts' => 'Accounts',
    'tab_review' => 'Review',

    // Campaigns tab
    'no_campaigns' => 'No campaigns for this brand',
    'open_nomination_title' => 'Open this campaign’s nomination',
    'nomination_prefix' => 'Nomination:',
    'published_content' => 'Published content',
    'deliverables_count' => ':n deliverable(s)',

    // Content tab
    'no_content' => 'No linked content',
    'needs_action' => 'Needs action',

    // Overview — brand profile
    'sec_profile' => 'Brand profile',
    'f_website' => 'Website',
    'f_language' => 'Preferred language',
    'f_tone' => 'Tone of voice',
    'f_audience' => 'Target audience',
    'prohibited_topics' => 'Prohibited topics',
    'required_messages' => 'Required messages',
    'visual_guidelines' => 'Visual guidelines',

    // Approval readiness
    'sec_readiness' => 'Approval readiness',
    'critical_complete' => 'Critical items complete',
    'critical_missing' => ':n critical item(s) missing',
    'critical_tag' => 'Critical',
    'sec_last_decision' => 'Last decision',
    'all_decisions' => 'All decisions',
    'no_decisions' => 'No decisions yet.',

    // Accounts tab
    'no_accounts' => 'No accounts recorded',
    'no_accounts_hint' => 'Add the brand’s accounts to track its activity.',
    'open_account' => 'Open account',

    // Review tab
    'sec_review_decisions' => 'Review decisions',
    'sec_history' => 'Status log',
    'no_history' => 'No log yet.',

    // Decision modal
    'approve_warning' => 'Note: :n critical item(s) are missing — review approval readiness before proceeding.',
    'reason_required_ph' => 'Reason (shown to the client) — required',
    'note_optional_ph' => 'Approval note (optional)',
    'confirm' => 'Confirm',
    'cancel' => 'Cancel',

    // Approval action labels
    'act_start_review' => 'Start review',
    'act_approve' => 'Approve brand',
    'act_request_changes' => 'Request changes',
    'act_suspend' => 'Suspend brand',
    'act_reapprove' => 'Re-approve',
    'act_submit' => 'Submit for approval',
    'act_resubmit' => 'Resubmit for approval',

    // Approval-readiness checklist items
    'ck_name' => 'Brand name',
    'ck_client' => 'Client ownership',
    'ck_sector' => 'Sector',
    'ck_description' => 'Brand description',
    'ck_logo' => 'Logo',
    'ck_website' => 'Website/domain',
    'ck_cr' => 'Commercial registration',
    'ck_contact' => 'Contact information',
    'ck_guidelines' => 'Brand guidelines',
    'ck_voice' => 'Tone of voice & audience',
    'ck_accounts' => 'At least one social account',

    // Review decisions
    'dec_approved' => 'Approved',
    'dec_changes_requested' => 'Changes requested',
    'dec_rejected' => 'Rejected',

    // Nomination status (shown on the campaign card)
    'nom_status_draft' => 'Draft',
    'nom_status_submitted' => 'Awaiting client',
    'nom_status_approved' => 'Approved',
    'nom_status_partially_approved' => 'Partially approved',
    'nom_status_changes_requested' => 'Alternative requested',
    'nom_status_rejected' => 'Rejected',
];
