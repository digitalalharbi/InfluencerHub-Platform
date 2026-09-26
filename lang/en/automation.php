<?php

/** Automation (index page + trigger/action/reminder labels) — English mirror of ar/automation.php. */
return [
    // Trigger labels (TRIGGER_LABEL) — dots in the key replaced with underscores
    'trig_service_request_created' => 'Service request created',
    'trig_service_request_assigned' => 'Request assigned',
    'trig_content_approved' => 'Content approved',
    'trig_content_submitted' => 'Content submitted',
    'trig_content_revision_requested' => 'Content revision requested',
    'trig_creator_declined' => 'Creator declined',

    // Action labels (ACTION_LABEL)
    'act_notify' => 'Notify',
    'act_create_task' => 'Create task',
    'act_escalate' => 'Escalate',

    // Trigger descriptions (TRIGGER_DESC) — the "when"
    'trigdesc_service_request_created' => 'When a new service request is created',
    'trigdesc_service_request_assigned' => 'When a request is assigned to a member',
    'trigdesc_content_approved' => 'When content is approved',
    'trigdesc_content_submitted' => 'When content is submitted for review',
    'trigdesc_content_revision_requested' => 'When a revision is requested on content',
    'trigdesc_creator_declined' => 'When a creator declines a collaboration',

    // Action descriptions (ACTION_DESC) — the "what"
    'actdesc_notify' => 'A notification is sent to the relevant person',
    'actdesc_create_task' => 'A follow-up task is created',
    'actdesc_escalate' => 'The matter is escalated to the manager',

    // Reminder schedule (SCHEDULED_REMINDERS.schedule)
    'sched_daily' => 'Daily',
    'sched_hourly' => 'Hourly',

    // Scheduled reminder descriptions (SCHEDULED_REMINDERS.desc) — keyed by reminder key
    'rmdesc_invoice_overdue' => 'If an issued invoice passes its due date ← remind admins to follow up on collection',
    'rmdesc_content_publishing' => 'If a scheduled content publish time nears or passes without publishing ← remind the creator and campaign owner',
    'rmdesc_creator_response' => 'If the influencer does not respond to a collaboration offer within 48 hours ← remind them and the offer owner',
    'rmdesc_client_decision' => 'If the client does not decide on the shortlist within 72 hours ← remind the client and the agency',
    'rmdesc_contract_signature' => 'If a party does not sign the sent contract within 72 hours ← remind the party and the contract owner',
    'rmdesc_sla' => 'If a service request passes its due date ← flag the breach and notify admins',

    // System rule names (AutomationRule.name) — keyed by rule key (dots → underscores)
    'rule_sys_request_created_confirm' => 'Request receipt confirmation',
    'rule_sys_content_approved_notify_owner' => 'Notify the campaign owner of content approval',
    'rule_sys_creator_declined_alert' => 'Creator-declined alert',

    // ===== Page UI (Index) =====
    'heading' => 'Automation',
    'eyebrow' => 'Smart automation',
    'sub' => 'Rules that run automatically on workflow events — notifications, tasks, and escalation.',

    // Run status labels (RUN_LABEL + donut segments)
    'run_executed' => 'Executed',
    'run_skipped' => 'Skipped',
    'run_failed' => 'Failed',

    // Automation health center
    'last_runs' => 'Recent runs',
    'rules_enabled' => ':enabled of :total rules enabled',
    'failed_runs_review' => ':n failed run(s) need review',
    'no_failures_recent' => 'No failures in recent runs',
    'no_runs_health' => 'No runs yet — automation health will appear here once the first event occurs.',

    // Rules section
    'rules' => 'Rules',
    'system' => 'System',
    'enabled_badge' => 'Enabled',
    'disabled_badge' => 'Disabled',
    'ran_prefix' => 'Ran',
    'times_suffix' => 'time(s)',
    'last_run_label' => 'Last run:',
    'failures_n' => ':n failure(s)',
    'no_failures' => 'No failures',
    'disable' => 'Disable',
    'enable' => 'Enable',

    // Scheduled reminders section
    'scheduled_reminders' => 'Scheduled reminders',
    'fired_prefix' => 'Fired',
    'last_fired_label' => 'Last fired:',

    // Run log
    'run_log' => 'Run log',
    'no_runs' => 'No runs yet.',
    'th_event' => 'Event',
    'th_status' => 'Status',
    'th_actions' => 'Actions',
    'th_time' => 'Time',
    'th_error' => 'Error',

    // Separator for donut segment accessibility (aria) text
    'aria_sep' => ', ',
];
