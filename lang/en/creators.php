<?php

/**
 * Agency creators list (English mirror of ar/creators.php).
 * Keys are stable; only presentation is translated. ر.س currency suffix and
 * the "UGC" label stay as-is, matching the rest of the app.
 */
return [
    'title' => 'Creators',
    'title_with' => 'Creators · :cap',
    'cap_influencers' => 'Influencers',
    'eyebrow' => 'Creator network',
    'sub' => 'Influencer and creator database with automatic tiering, verification, engagement and rates',
    'new_creator' => 'New creator',

    // KPIs
    'kpi_total' => 'Total creators',
    'kpi_total_sub' => ':a tier A · :active active',
    'kpi_verified' => 'Verified',
    'kpi_verified_sub' => ':n unverified',
    'kpi_active_collab' => 'With active collab',
    'kpi_active_collab_sub' => 'Taking part in running campaigns',
    'kpi_needs_review' => 'Need review',
    'kpi_needs_review_sub' => ':n incomplete profiles',

    // Type switcher
    'type_all' => 'All',

    // Segments
    'seg_all' => 'All',
    'seg_tier_a' => 'Tier A',
    'seg_tier_b' => 'Tier B',
    'seg_tier_c' => 'Tier C',
    'seg_verified' => 'Verified',
    'seg_unverified' => 'Unverified',
    'seg_active' => 'Active',
    'seg_incomplete' => 'Incomplete',
    'seg_needs_review' => 'Needs review',
    'seg_active_collab' => 'Has active collab',

    // Search & filters
    'search_placeholder' => 'Search by name, handle or city…',
    'all_statuses' => 'All statuses',
    'all_platforms' => 'All platforms',
    'all_cities' => 'All cities',
    's_prospect' => 'Prospect',
    's_active' => 'Active',
    's_paused' => 'Paused',
    's_blocked' => 'Blocked',

    // Table
    'th_creator' => 'Creator',
    'th_tier' => 'Tier',
    'th_platform' => 'Platform',
    'th_followers' => 'Followers',
    'th_engagement' => 'Engagement',
    'th_rate' => 'Rate/post',
    'th_status' => 'Status',
    'incomplete_badge' => 'Incomplete',
    'collab_count' => ':n collab',
    'open' => 'Open',
    'count_item' => ':n creators',
    'filtered_suffix' => ' · filtered',

    // Mobile cards
    'm_followers' => 'Followers',
    'm_engagement' => 'Engagement',
    'm_rate' => 'Rate',
    'active_collab_note' => ':n active collaborations',

    // Create modal
    'f_name' => 'Name',
    'f_capabilities' => 'Capabilities',
    'f_status' => 'Status',
    'f_handle' => 'Handle',
    'f_platform' => 'Primary platform',
    'f_followers' => 'Followers count',
    'f_city' => 'City',
    'save' => 'Save creator',
    'cancel' => 'Cancel',

    // Empty states
    'empty_filtered_title' => 'No matching creators',
    'empty_filtered_text' => 'No results for the current search or filters. Try changing the platform or tier.',
    'clear_filters' => 'Clear filters',
    'empty_title' => 'Start building your creator network',
    'empty_text' => 'Add influencers and creators and the system will tier them automatically by size, engagement and trust.',

    // ===== Detail page (Show) =====
    'show_heading' => 'Creator profile',
    'no_capabilities' => 'No capabilities',
    'back_all' => 'All creators',
    'm_platform' => 'Platform',
    'm_city' => 'City',
    'm_trust' => 'Verification',
    'verified' => 'Verified',
    'unverified' => 'Unverified',
    'm_categories' => 'Categories',
    'currency_sar' => 'SAR',

    // Status switcher (select options)
    'status_label' => 'Status',
    'opt_prospect' => 'Prospect',
    'opt_active' => 'Active',
    'opt_paused' => 'Paused',
    'opt_blocked' => 'Blocked',

    // Score and sub-scores
    'score_label' => 'Creator score',
    'tier_prefix' => 'Tier',
    'subscores_title' => 'Sub-scores (computed automatically from real data)',
    'top_factors' => 'Top factors:',
    'risk_label' => 'Risk: :n overdue',

    // Summary strip
    'ss_followers' => 'Followers',
    'ss_engagement' => 'Engagement (est.)',
    'ss_campaigns' => 'Campaigns',
    'ss_active_collabs' => 'Active collaborations',
    'ss_content_published' => 'Published content',
    'ss_paid' => 'Paid',
    'ss_commitment' => 'Commitment',

    // Tabs
    'tab_overview' => 'Overview',
    'tab_platforms' => 'Platforms',
    'tab_collaborations' => 'Campaigns',
    'tab_content' => 'Content',
    'tab_contracts' => 'Contracts',
    'tab_payouts' => 'Payouts',

    // Overview
    'sec_bio' => 'Bio & categories',
    'no_bio' => 'No bio yet.',
    'sec_contact' => 'Contact & pricing',
    'c_email' => 'Email',
    'c_phone' => 'Phone',
    'c_rate' => 'Price per post',
    'c_accept_rate' => 'Accept rate',

    // Platforms
    'no_platforms' => 'No platforms recorded',
    'platforms_compare' => 'Reach comparison across platforms',
    'followers' => 'Followers',

    // Tab tables
    'th_collab' => 'Collaboration',
    'th_campaign' => 'Campaign',
    'th_fee' => 'Fee',
    'th_status' => 'Status',
    'no_collaborations' => 'No collaborations yet.',
    'no_content' => 'No content yet',
    'needs_action' => 'Needs action',
    'th_contract' => 'Contract',
    'th_number' => 'Number',
    'th_value' => 'Value',
    'no_contracts' => 'No contracts yet.',
    'th_payout' => 'Payout',
    'th_amount' => 'Amount',
    'no_payouts' => 'No payouts yet.',

    // Creator portal (access panel)
    'acc_title' => 'Creator portal',
    'acc_link_once' => 'Copy the link now — it is shown only once.',
    'acc_sent_to' => 'Sent to:',
    'acc_verified' => 'verified',
    'acc_phone' => 'Mobile:',
    'acc_expires' => 'Expires:',
    'acc_last_sent' => 'Last sent:',
    'acc_f_email' => 'Email',
    'acc_f_phone' => 'Mobile (optional)',
    'acc_send' => 'Send invitation',
    'acc_resend' => 'Resend',
    'acc_revoke' => 'Revoke invitation',

    // Access states (derived server-side)
    'acc_state_active' => 'Portal active',
    'acc_reason_linked' => 'The account is already linked — no invitation needed.',
    'acc_reason_missing_email' => 'Add the creator’s email first — the invitation is sent to it.',
    'acc_reason_no_perm' => 'You don’t have permission to invite a creator.',
    'acc_state_unlinked' => 'Not linked',
    'acc_state_revoked' => 'Invitation revoked',
    'acc_state_expired' => 'Invitation expired',
    'acc_state_phone_verified' => 'Mobile verified — awaiting password',
    'acc_state_email_verified' => 'Email verified',
    'acc_state_pending' => 'Invitation pending',

    // Creator status (CREATOR_STATUS — badge label)
    'st_prospect' => 'Prospect',
    'st_active' => 'Active',
    'st_paused' => 'Paused',
    'st_blocked' => 'Blocked',

    // Sub-scores (CreatorAnalytics)
    'sub_audience' => 'Audience size',
    'sub_engagement' => 'Engagement',
    'sub_reliability' => 'Reliability',
    'sub_content_quality' => 'Content quality',
    'sub_commercial' => 'Commercial performance',
    'sub_profile' => 'Profile completeness',
    'sub_trust' => 'Trust',
    'tier_under_review' => 'Under review',
    'reason_overdue' => 'Overdue deliveries',
];
