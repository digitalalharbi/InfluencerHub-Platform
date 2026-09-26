<?php

/** Content queue (list) — English. Fully translated surface: client + server. */
return [
    'title' => 'Content',
    'eyebrow' => 'Operations',
    'sub' => 'Content review and approval queue before publishing — from agency to client',

    // KPIs
    'kpi_agency_review' => 'Awaiting my review',
    'kpi_agency_review_sub' => 'Content submitted to the agency',
    'kpi_client_review' => 'Awaiting client',
    'kpi_client_review_sub' => 'Sent for client approval',
    'kpi_changes' => 'Changes requested',
    'kpi_changes_sub' => 'Awaiting creator revision',
    'kpi_published' => 'Published',
    'kpi_published_sub' => ':scheduled scheduled · :approved approved',

    // Segments
    'seg_all' => 'All',
    'seg_agency_review' => 'Awaiting my review',
    'seg_client_review' => 'Awaiting client',
    'seg_changes_requested' => 'Changes requested',
    'seg_approved' => 'Approved',
    'seg_scheduled' => 'Scheduled',
    'seg_published' => 'Published',
    'seg_draft' => 'Draft',
    'seg_rejected' => 'Rejected',

    // Search/filter
    'search_placeholder' => 'Search by title, number, or creator…',
    'all_types' => 'All types',

    // Empty states
    'empty_filtered_title' => 'No matching content',
    'empty_filtered_text' => 'No results for the current search or segment.',
    'clear_filters' => 'Clear filters',
    'empty_title' => 'No content in the queue',
    'empty_text' => 'Content submitted by creators for review and approval appears here.',

    // Cards
    'review' => 'Review',
    'needs_action' => 'Needs action',
    'count_item' => ':n item(s)',
    'filtered_suffix' => ' · filtered',

    // Content types (TYPE_LABEL)
    't_post' => 'Post',
    't_story' => 'Story',
    't_reel' => 'Reel',
    't_video' => 'Video',
    't_ugc' => 'UGC',

    // Detail page (Show) — review
    'show_heading' => 'Content review',
    'show_eyebrow' => 'Content review · :num · v:ver',
    'back_all' => 'All content',
    'm_creator' => 'Creator',
    'm_client' => 'Client',
    'm_type' => 'Type',
    'm_platform' => 'Platform',
    'm_campaign' => 'Campaign',
    'ss_version' => 'Version',
    'ss_review_decisions' => 'Review decisions',
    'ss_scheduled' => 'Scheduled',
    'ss_published' => 'Published',
    'sec_content' => 'Content',
    'media_url' => 'Content link',
    'caption' => 'Caption',
    'no_media' => 'No link or caption yet.',
    'sec_context' => 'Context & links',
    'ctx_campaign_hint' => 'The 13 campaign stages',
    'live_post' => 'Live post',
    'sec_proof' => 'Publish proof & results',
    'live_post_url' => 'Live post link',
    'proved_at' => 'Proved :date',
    'proof_missing_hint' => 'The content was published but the post link is not recorded yet. Proof is what the invoice and report are built on.',
    'record_proof' => 'Record publish proof',
    'r_reach' => 'Reach',
    'r_impressions' => 'Impressions',
    'r_engagements' => 'Engagement',
    'r_clicks' => 'Clicks',
    'record_results' => 'Record results',
    'src_platform' => 'From platform',
    'src_manual' => 'Manual entry',
    'sec_timeline' => 'Review & status log',
    'sec_timeline_n' => 'Review & status log (:n)',
    'no_timeline' => 'No log yet.',
    'proof_modal_title' => 'Publish proof',
    'proof_modal_hint' => 'The live post link on the platform — not the creative file link.',
    'f_url' => 'Link',
    'f_note_optional' => 'Note (optional)',
    'save_proof' => 'Save proof',
    'results_modal_title' => 'Post results',
    'results_modal_hint' => 'Entered manually and tagged as such — no platform provider connected. Leave anything you did not measure blank.',
    'save_results' => 'Save results',
    'reason_placeholder' => 'Reason (shown to the creator)',
    'confirm' => 'Confirm',
    'cancel' => 'Cancel',

    // Workflow action labels
    'act_start_review' => 'Start review',
    'act_send_client' => 'Send to client',
    'act_request_changes' => 'Request changes',
    'act_reject' => 'Reject',
    'act_publish' => 'Publish',
    'act_schedule' => 'Schedule publish',
    'act_publish_now' => 'Publish now',
    'act_reschedule' => 'Reschedule',

    // Review log
    'tl_reschedule' => 'Rescheduled',
    'dec_approved' => 'Approved',
    'dec_changes_requested' => 'Changes requested',
    'dec_rejected' => 'Rejected',
    'actor_agency' => 'Agency',
    'actor_client' => 'Client',
    'actor_creator' => 'Creator',
];
