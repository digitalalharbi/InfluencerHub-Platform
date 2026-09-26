<?php

/** Campaigns list — English. Status from shared statuses; the rest of the surface is translated here. */
return [
    'title' => 'Campaigns',
    'eyebrow' => 'Operations',
    'sub' => 'Influencer campaigns, their deliverables, budgets, progress, and risks on one board',
    'new_campaign' => 'New campaign',

    // KPIs
    'kpi_total' => 'Total campaigns',
    'kpi_total_sub' => ':planning shortlisting · :draft draft',
    'kpi_active' => 'Active now',
    'kpi_active_sub' => ':n completed',
    'kpi_awaiting' => 'Awaiting client',
    'kpi_awaiting_sub' => 'Pending approval or review',
    'kpi_late' => 'Late',
    'kpi_late_sub' => 'Past the end date',

    // Segments
    'seg_all' => 'All',
    'seg_active' => 'Active',
    'seg_planning' => 'Shortlisting',
    'seg_awaiting_client' => 'Awaiting client',
    'seg_late' => 'Late',
    'seg_completed' => 'Completed',
    'seg_paused' => 'Paused',
    'seg_draft' => 'Draft',

    'search_placeholder' => 'Search by name, number, or client…',
    'count_item' => ':n campaign(s)',

    // Empty states
    'empty_filtered_title' => 'No matching campaigns',
    'empty_filtered_text' => 'No results for the current search or segment.',
    'clear_filters' => 'Clear filters',
    'empty_title' => 'Launch your first campaign',
    'empty_text' => 'Create a campaign to track its deliverables, shortlists, content, and budget from a single command center.',

    // Stage groups
    'st_running' => 'In progress',
    'st_planning' => 'Planning',
    'st_closed' => 'Closed',

    // Cards
    'progress_deliverables' => 'Progress · :n deliverable(s)',
    'm_budget' => 'Budget',
    'm_creators' => 'Creators',
    'm_end' => 'End',
    'late_badge' => '● Late',
    'awaiting_badge' => ':n awaiting client',

    // Create modal
    'f_client' => 'Client',
    'choose_client' => 'Choose a client…',
    'f_brand' => 'Brand (optional)',
    'no_brand' => '— None —',
    'f_name' => 'Campaign name',
    'f_objective' => 'Objective (optional)',
    'f_budget' => 'Budget (SAR)',
    'f_start' => 'Start',
    'f_end' => 'End',
    'create' => 'Create campaign',
    'cancel' => 'Cancel',

    // ─────────────────────────────────────────────────────────────
    // Detail page (Show) — fully translated single-campaign surface
    // ─────────────────────────────────────────────────────────────
    'show_heading' => 'Campaign',
    'more' => 'More',
    'show_eyebrow' => 'Campaign · :num',
    'back_all' => 'All campaigns',

    // Meta strip
    'm_client' => 'Client',
    'm_brand' => 'Brand',
    'm_start' => 'Start',
    'm_finish' => 'End',

    // Currency
    'currency_sar' => 'SAR',

    // Connection row (source chain)
    'conn_to' => 'Connected to',
    'conn_request' => 'Request :num',
    'conn_direct' => '· Created directly without a prior request',

    // Next step
    'next_eyebrow' => 'Next step · Stage: :stage',
    'do_now' => 'Do it now',

    // Campaign command center (cycle)
    'cc_cycle' => 'Campaign cycle',
    'cc_title' => 'Campaign command center — :n stages',
    'cc_stage' => ':done/:total · Stage: :stage',
    'cc_operational' => 'Operationally: :label',
    'cc_financial' => 'Financially: :label',

    // Summary strip
    'ss_deliverables' => 'Deliverables',
    'ss_creators' => 'Creators',
    'ss_committed' => 'Committed amount',

    // Financial panel
    'fin_status' => 'Financial status',
    'fin_over_badge' => 'Commitments exceed budget',
    'fin_approved_budget' => 'Approved budget',
    'fin_total_commitments' => 'Total commitments',
    'fin_over' => 'Over budget',
    'fin_remaining' => 'Remaining budget',
    'fin_variance' => 'Variance',
    'fin_review_costs' => 'Review costs',
    'fin_open_influencers' => 'Open influencers',

    // Influencers panel
    'inf_title' => 'Influencers',
    'inf_review_list' => 'Review list',
    'inf_start' => 'Start nomination',
    'inf_primary' => 'Primary',
    'inf_backup' => 'Backup',
    'inf_approved' => 'Approved',
    'inf_pending' => 'Awaiting client',
    'inf_empty' => 'Nominations for this campaign have not started yet — begin by selecting suitable influencers.',

    // Execution readiness (client)
    'rdy_title' => 'Execution readiness',
    'rdy_state_ready' => 'Ready',
    'rdy_state_attention' => 'Needs attention',
    'rdy_state_blocked' => 'Blocked',
    'rdy_state_na' => 'N/A',
    'rdy_percent' => ':n% ready to execute',

    // Tabs
    'tab_deliverables' => 'Deliverables',
    'tab_collaborations' => 'Collaborations',
    'tab_content' => 'Content',
    'tab_contracts' => 'Contracts',
    'tab_finance' => 'Collection',
    'tab_payouts' => 'Payouts',

    // Collection tab (invoices)
    'fin_invoices_title' => 'Campaign invoices',
    'fin_no_invoices' => 'No invoices for this campaign yet. On creation, line items are suggested from its recorded deliverables.',
    'fin_create_invoice' => 'Create invoice',
    'fin_balance' => 'Balance :amount',

    // Table headers
    'th_type' => 'Type',
    'th_platform' => 'Platform',
    'th_quantity' => 'Quantity',
    'th_creator' => 'Creator',
    'th_status' => 'Status',
    'th_collab' => 'Collaboration',
    'th_fee' => 'Fee',
    'th_content' => 'Content',
    'th_contract' => 'Contract',
    'th_party' => 'Party',
    'th_value' => 'Value',
    'th_payout' => 'Payout',
    'th_due' => 'Due',

    // Deliverables tab
    'deliv_add' => 'Add deliverable',
    'deliv_empty' => 'No deliverables yet.',
    'deliv_suggest' => 'Suggest creators',

    // Tab empty states / notes
    'collab_empty' => 'No collaborations yet.',
    'content_empty' => 'No content yet.',
    'contract_empty' => 'No contracts for this campaign yet.',
    'payout_note' => "This campaign's creator payouts — separate from client collection.",
    'payout_empty' => 'No payouts for this campaign yet.',

    // Timeline (client)
    'tl_title' => 'Timeline',
    'tl_empty' => 'No events yet.',

    // Generic actions
    'action_delete' => 'Delete',
    'action_save' => 'Save',
    'action_add' => 'Add',
    'confirm' => 'Confirm',

    // Edit modal
    'edit_title' => 'Edit campaign',
    'edit_objective' => 'Objective',

    // Add-deliverable modal
    'd_platform_opt' => 'Platform (optional)',
    'd_unit_fee' => 'Unit fee (SAR)',
    'd_due_date' => 'Due date',
    'd_total_prefix' => 'This deliverable total',
    'd_total_suffix' => '— it carries to the invoice line and to the creator payout, so it is not entered twice.',
    'd_fee_note' => 'The unit fee carries to the invoice line and the creator payout — so it is not entered twice.',

    // Action modal
    'action_note_ph' => 'Note (optional)',

    // Overflow menu
    'nominations' => 'Nominations',
    'client_brief' => 'Client brief',
    'client_brief_stale' => 'Client brief (needs update)',

    // Deliverable types (DeliverableType)
    'dtype_post' => 'Post',
    'dtype_story' => 'Story',
    'dtype_reel' => 'Reel',
    'dtype_video' => 'Video',
    'dtype_ugc' => 'UGC',

    // Campaign state actions (ACTIONS)
    'act_plan' => 'Move to planning',
    'act_cancel' => 'Cancel campaign',
    'act_activate' => 'Activate',
    'act_complete' => 'Complete campaign',
    'act_pause' => 'Pause',
    'act_resume' => 'Resume',

    // Contract parties
    'party_creator' => 'Creator',
    'party_client' => 'Client',

    // Client brief title (server)
    'brief_title' => 'Campaign brief (client-safe)',

    // Nomination status (nomStatusLabel)
    'nom_status_draft' => 'Draft',
    'nom_status_submitted' => 'Awaiting client',
    'nom_status_approved' => 'Approved',
    'nom_status_partially_approved' => 'Partially approved',
    'nom_status_changes_requested' => 'Alternative requested',
    'nom_status_rejected' => 'Rejected',

    // Command center (CampaignAnalytics::commandCenter) — stage labels
    'cmd_stage_setup' => 'Setup',
    'cmd_stage_planning' => 'Planning',
    'cmd_stage_sourcing' => 'Sourcing',
    'cmd_stage_production' => 'Production',
    'cmd_stage_review' => 'Review',
    'cmd_stage_publishing' => 'Publishing',
    'cmd_stage_closure' => 'Closure',
    // Next step per stage
    'cmd_next_setup_title' => 'Move the campaign to planning',
    'cmd_next_setup_hint' => 'Define the scope and budget, then move it to planning.',
    'cmd_next_planning_title' => 'Start nomination',
    'cmd_next_planning_hint' => 'Nominate suitable influencers for the campaign.',
    'cmd_next_sourcing_title' => 'Send nominations to the client',
    'cmd_next_sourcing_hint' => 'Send the influencer list for client approval.',
    'cmd_next_production_title' => 'Follow up on content production',
    'cmd_next_production_hint' => 'Track deliverables and update their statuses.',
    'cmd_next_review_title' => 'Approve pending content',
    'cmd_next_review_hint' => ':n item(s) awaiting client approval.',
    'cmd_next_publishing_title' => 'Verify publishing and approve payouts',
    'cmd_next_publishing_hint' => 'Verify publish links and approve creator payouts.',
    'cmd_next_closure_title' => 'Close the campaign',
    'cmd_next_closure_hint' => 'Obligations are complete — close the campaign and issue the report.',
    'cmd_next_default_title' => 'Follow up on the campaign',

    // Timeline (CampaignAnalytics::timeline)
    'tl_campaign' => 'Campaign → :status',
    'tl_collab' => 'Collaboration :status',
    'tl_content' => 'Content :status',

    // Execution readiness (CampaignAnalytics::readiness)
    'rdy_client_label' => 'Client active',
    'rdy_client_ok' => 'The client is eligible to contract.',
    'rdy_client_blocked' => 'The client status is not active/qualified — execution cannot proceed.',
    'rdy_client_ev' => 'Current status: :status',
    'rdy_client_none' => 'No linked client',
    'rdy_client_action' => 'Open client profile',
    'rdy_brand_label' => 'Brand approved',
    'rdy_brand_na' => 'No brand linked to this campaign.',
    'rdy_brand_ok' => 'The brand is approved.',
    'rdy_brand_blocked' => 'The brand is awaiting review approval.',
    'rdy_brand_ev' => 'Brand: :name',
    'rdy_brand_action' => 'Review brands',
    'rdy_budget_label' => 'Budget set',
    'rdy_budget_ok' => 'The campaign budget is set.',
    'rdy_budget_attention' => 'No budget set yet — commitments cannot be controlled.',
    'rdy_budget_ev' => 'Budget: :amount',
    'rdy_budget_action' => 'Set budget',
    'rdy_deliv_label' => 'Deliverables added',
    'rdy_deliv_ok' => 'Deliverables are added.',
    'rdy_deliv_attention' => 'Add at least one deliverable to start execution.',
    'rdy_deliv_ev' => ':n deliverable(s)',
    'rdy_deliv_action' => 'Add deliverable',
    'rdy_assign_label' => 'Every deliverable assigned to a creator',
    'rdy_assign_none' => 'No deliverables to assign yet.',
    'rdy_assign_ok' => 'All deliverables are assigned.',
    'rdy_assign_attention' => ':n deliverable(s) without an assigned creator.',
    'rdy_assign_ev' => ':assigned/:total assigned',
    'rdy_assign_action' => 'Assign creators',
    'rdy_within_label' => 'Within budget',
    'rdy_within_na' => 'Budget not set yet.',
    'rdy_within_blocked' => 'Commitments exceed the approved budget.',
    'rdy_within_ok' => 'Commitments are within budget.',
    'rdy_within_ev' => 'Budget :budget · Commitments :committed',
    'rdy_within_action' => 'Review costs',
    'rdy_content_label' => 'Content approved',
    'rdy_content_na' => 'No content submitted yet.',
    'rdy_content_ok' => 'All content is approved.',
    'rdy_content_attention' => ':n item(s) awaiting approval.',
    'rdy_content_ev' => ':approved/:total approved',
    'rdy_content_action' => 'Review content',

    // Campaign lifecycle engine (CampaignLifecycleService) — stage labels
    'lc_stage_creation' => 'Campaign Creation',
    'lc_stage_nomination' => 'Creator Nomination',
    'lc_stage_internal_approval' => 'Internal Approval',
    'lc_stage_send_to_client' => 'Send to Client',
    'lc_stage_client_decision' => 'Client Decision',
    'lc_stage_quotation_contract' => 'Quotation & Contract',
    'lc_stage_client_collection' => 'Client Collection',
    'lc_stage_creator_booking' => 'Creator Booking',
    'lc_stage_scheduling' => 'Scheduling',
    'lc_stage_creator_finance' => 'Creator Finance',
    'lc_stage_publishing' => 'Publishing & Proof',
    'lc_stage_archive_performance' => 'Archive & Performance',
    'lc_stage_closure' => 'Campaign Closure',
    // Stage owners
    'lc_owner_manager' => 'Campaign manager',
    'lc_owner_ops' => 'Operations',
    'lc_owner_client' => 'Client',
    'lc_owner_finance' => 'Finance',
    'lc_owner_creator' => 'Creator',
    // Operational state
    'lc_op_closed' => 'Operationally closed',
    'lc_op_ready' => 'Ready to close',
    'lc_op_in_progress' => 'In progress',
    // Financial state
    'lc_fin_settled' => 'Financially settled',
    'lc_fin_collection_pending' => 'Client collection pending',
    'lc_fin_payout_pending' => 'Creator payout pending',
    'lc_fin_none' => 'No financial obligation yet',
    // Stage evidence/blockers/missing/actions
    'lc_missing_budget' => 'Set the budget',
    'lc_missing_deliv' => 'Add at least one deliverable',
    'lc_creation_ev' => 'Campaign #:num created (budget set, :n deliverable(s))',
    'lc_creation_ip' => 'The campaign is being set up',
    'lc_creation_next' => 'Complete campaign details',
    'lc_nomination_ev' => ':n influencer(s) nominated',
    'lc_nomination_missing' => 'Nominate influencers for the campaign',
    'lc_nomination_next' => 'Start nomination',
    'lc_internal_ev' => 'The team approved the nomination version internally and locked it',
    'lc_internal_ip' => 'The nomination is a draft — awaiting team approval',
    'lc_internal_missing' => 'Approve the version internally',
    'lc_internal_next' => 'Approve the nomination internally',
    'lc_send_ev' => 'The nomination version was sent to the client',
    'lc_send_ip' => 'Approved internally — not sent yet',
    'lc_send_missing' => 'Send the version to the client',
    'lc_send_next' => 'Send the nomination to the client',
    'lc_decision_blocker' => 'The client rejected the nominees — nominate alternatives',
    'lc_decision_next_alt' => 'Nominate alternatives',
    'lc_decision_ev' => 'Client decided (:n approved)',
    'lc_decision_ip' => 'Awaiting client decision',
    'lc_decision_missing' => 'Awaiting the client decision on the nominees',
    'lc_quote_ev' => 'The contract is signed/active',
    'lc_quote_ip' => 'The contract is sent — awaiting signature',
    'lc_quote_missing_sign' => 'Sign the contract',
    'lc_quote_next_follow' => 'Follow up on contract signing',
    'lc_quote_missing_issue' => 'Issue the quotation/contract',
    'lc_quote_next_issue' => 'Issue the contract',
    'lc_collection_ev' => 'All invoices collected',
    'lc_collection_ip' => ':n invoice(s) not collected',
    'lc_collection_missing' => 'Collect the open invoices',
    'lc_collection_next_follow' => 'Follow up on collection',
    'lc_collection_missing_issue' => 'Issue the client invoice',
    'lc_collection_next_issue' => 'Issue an invoice',
    'lc_booking_blocker' => 'The creator declined — book an alternative',
    'lc_booking_next_alt' => 'Book an alternative',
    'lc_booking_ev' => ':n creator(s) booked (accepted)',
    'lc_booking_ip' => ':n offer(s) awaiting creator acceptance',
    'lc_booking_missing' => 'Awaiting creator acceptance',
    'lc_booking_next_follow' => 'Follow up on booking',
    'lc_sched_ev' => 'All deliverables scheduled with dates',
    'lc_sched_ip' => ':done/:total deliverable(s) scheduled',
    'lc_sched_missing_rest' => 'Set publish dates for the remaining deliverables',
    'lc_sched_next' => 'Schedule deliverables',
    'lc_sched_missing_all' => 'Set publish dates for the deliverables',
    'lc_finance_ev' => 'All payouts disbursed',
    'lc_finance_ip' => ':n payout(s) not disbursed',
    'lc_finance_missing' => 'Approve/disburse payouts',
    'lc_finance_next' => 'Follow up on payouts',
    'lc_finance_missing_create' => 'Create creator payouts',
    'lc_publish_ev' => ':n content published and proven',
    'lc_publish_ip' => ':done/:total published with proof',
    'lc_publish_missing_rest' => 'Provide publish proof for the remaining content',
    'lc_publish_next' => 'Verify publishing',
    'lc_publish_missing_all' => 'Publish content and attach the proof link',
    'lc_archive_ev' => 'Performance archived and recorded for :n content',
    'lc_archive_ip' => 'Performance :done/:total recorded',
    'lc_archive_missing' => 'Record performance metrics (manually or via integration)',
    'lc_archive_next' => 'Record performance',
    'lc_obl_collabs' => 'Collaborations not closed',
    'lc_obl_content' => 'Content in review',
    'lc_closure_ev' => 'The campaign was closed',
    'lc_closure_missing_blocked' => 'Close the open obligations before closing',
    'lc_closure_ip' => 'Obligations complete — ready to close',
    'lc_closure_missing' => 'Close the campaign and issue the report',
    'lc_closure_next' => 'Close the campaign',
];
