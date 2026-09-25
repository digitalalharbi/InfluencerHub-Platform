<?php

/** Invoices list — English. Status from shared statuses; the rest of the surface is translated here. */
return [
    'title' => 'Invoices',
    'eyebrow' => 'Finance',
    'sub' => 'Client claims and their collection — the counterpart to creator payouts',
    'new_invoice' => 'New invoice',

    // KPIs
    'kpi_outstanding' => 'Outstanding',
    'kpi_outstanding_sub' => ':n open invoice(s)',
    'kpi_collected' => 'Collected',
    'kpi_collected_sub' => ':n paid',
    'kpi_draft' => 'Drafts',
    'kpi_draft_sub' => 'Not issued yet',
    'kpi_total' => 'Total',
    'kpi_total_sub' => 'All invoices',

    // Collection overview (donut)
    'd_collected' => 'Collected',
    'd_outstanding' => 'Outstanding',
    'd_center' => 'collected',
    'd_headline' => 'Collection — :pct% of :total SAR total',

    // Segments
    'seg_all' => 'All',
    'seg_draft' => 'Draft',
    'seg_open' => 'Outstanding',
    'seg_paid' => 'Paid',
    'seg_cancelled' => 'Cancelled',

    // Empty state
    'empty_title' => 'No invoices yet',
    'empty_text' => 'Create an invoice from a campaign to have its line items suggested from recorded deliverables, or start with a blank invoice.',

    // Table
    'th_number' => 'Number',
    'th_client' => 'Client',
    'th_campaign' => 'Campaign',
    'th_total' => 'Total',
    'th_balance' => 'Balance',
    'th_due' => 'Due',
    'th_status' => 'Status',
    'open' => 'Open',

    // Create modal
    'm_title' => 'New invoice',
    'f_client' => 'Client',
    'choose_client' => 'Choose a client…',
    'f_campaign' => 'Campaign',
    'no_campaign' => 'No campaign',
    'loading_items' => 'Fetching campaign deliverables…',
    'items_hint' => 'Line items suggested from the campaign deliverables — edit them if invoicing partially.',
    'items' => 'Line items',
    'item_desc' => 'Description',
    'del_item' => 'Delete item',
    'add_item' => '+ Item',
    'discount' => 'Discount (SAR)',
    'due_date' => 'Due date',
    'subtotal' => 'Subtotal',
    'discount_row' => 'Discount',
    'vat' => 'VAT :rate%',
    'total' => 'Total',
    'ready_hint' => 'Choose a client and add a priced item',
    'cancel' => 'Cancel',
    'saving' => 'Saving…',
    'save_draft' => 'Save as draft',
];
