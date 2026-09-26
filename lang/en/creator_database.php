<?php

/**
 * Creator database (creator discovery) — English mirror of ar/creator_database.php.
 * Server labels (type/notes) + Index/Show UI strings. Keys are stable; platform
 * names come from PlatformRegistry. ر.س currency and "UGC" stay as-is.
 */
return [
    // Server-built labels (also used as type-filter options)
    'type_celebrity' => 'Influencer',
    'type_ugc' => 'UGC Creator',
    'reference_rate_note' => 'Recorded reference rate — not guaranteed; negotiated with the creator',
    'data_freshness' => 'Recorded data',

    // Header
    'title' => 'Creator Database',
    'eyebrow' => 'Creator discovery · Premium',
    'sub' => 'A broad in-platform creator database — search, reach out, keep your relationship and nominate straight to your campaign.',
    'count_item' => ':n creators',

    // Campaign context bar
    'ctx_aria' => 'Campaign nomination context',
    'ctx_label' => 'Nominating for',
    'ctx_primary' => 'Primary :n',
    'ctx_backup' => 'Backup :n',
    'ctx_primary_title' => 'Primary candidates',
    'ctx_backup_title' => 'Backup candidates',
    'ctx_review' => 'Review shortlist',
    'ctx_locked' => 'This shortlist was sent to the client — to add candidates, create a new version from the shortlist workspace.',

    // Search & filters
    'search_placeholder' => 'Search by name, city or account…',
    'f_platform' => 'Platform',
    'all_platforms' => 'All platforms',
    'f_region' => 'Location',
    'all_regions' => 'All regions',
    'f_sort' => 'Sort results',
    'sort_followers' => 'Most followed',
    'sort_price' => 'Highest priced',
    'sort_recent' => 'Freshest data',
    'more_filters' => 'More filters',
    'clear_filters' => 'Clear filters',
    'clear_filters_title' => 'Clear all filters',
    'f_type' => 'Creator type',
    'all_types' => 'All types',
    'f_tier' => 'Tier',
    'all_tiers' => 'All tiers',
    'tier_label' => 'Tier :t',
    'f_gender' => 'Gender',
    'gender_female' => 'Female',
    'gender_male' => 'Male',
    'f_price' => 'Price',
    'f_price_aria' => 'Price availability',
    'price_available' => 'Price available',

    // Categories
    'cat_all' => 'All',
    'cat_less' => 'Show less',
    'cat_all_count' => 'Show all categories (:n)',
    'filter_by' => 'Filter: :cat',

    // Phase G filters
    'f_category' => 'Category',
    'all_categories' => 'All categories',
    'clear_all' => 'Clear all',
    'active_filters' => 'Active filters',

    // Quick-preview drawer
    'preview_aria' => 'Creator preview',
    'view_profile' => 'Full profile',
    'add_to_campaign' => 'Add to campaign',
    'sec_about' => 'About',
    'sec_reach' => 'Reach',
    'sec_pricing' => 'Pricing',
    'sec_notes' => 'Your private notes',
    'notes_none' => 'No notes yet.',
    'loading' => 'Loading…',

    // Empty state
    'empty_title' => 'No matching creators',
    'empty_text' => 'No results for the current search or filters.',
    'empty_hint' => 'Try removing some filters or widening your search.',

    // Creator card
    'm_followers' => 'Followers',
    'm_likes' => 'Likes',
    'm_shows_face' => 'Shows face',
    'yes' => 'Yes',
    'no' => 'No',
    'reference_rate' => 'Reference rate',
    'rate_missing' => 'Rate not added',
    'profile' => 'Profile',
    'compare' => 'Compare',
    'in_compare' => '✓ In comparison',
    'compare_add' => 'Add to comparison',
    'compare_max' => 'Maximum 4 for comparison',
    'account' => 'Account',
    'copy_phone' => 'Copy phone',
    'whatsapp' => 'WhatsApp',
    'role_primary' => 'primary',
    'role_backup' => 'backup',
    'in_shortlist_toggle' => '✓ :role — tap to switch',
    'role_toggle_title' => 'Switch between primary/backup',
    'add_primary' => '+ Primary',
    'add_backup' => '+ Backup',
    'nominate_to_campaign' => 'Nominate to campaign',

    // Compare bar
    'comparebar_aria' => 'Comparison bar',
    'selected_one' => '1 creator selected',
    'selected_many' => ':n creators selected',
    'remove_x' => 'Remove :name',
    'compare_btn' => 'Compare (:n)',
    'compare_min' => 'Select at least two creators',
    'clear_selection' => 'Clear selection',

    // Compare panel
    'compare_modal_aria' => 'Compare creators',
    'compare_title' => 'Comparing :n creators',
    'close' => 'Close',
    'dim' => 'Dimension',
    'dim_platform' => 'Platform',
    'dim_type' => 'Type',
    'dim_followers' => 'Followers',
    'dim_likes' => 'Likes',
    'dim_tier' => 'Tier',
    'dim_location' => 'Location',
    'dim_shows_face' => 'Shows face',
    'dim_rating' => 'Rating',
    'dim_categories' => 'Categories',
    'dim_rate' => 'Reference rate',
    'rate_not_added' => 'Not added',
    'compare_note' => 'Dimensions from real database data only. Missing values show «—» and are not estimated.',

    // Detail page (Show)
    'back_all' => '← All creators',
    'fav_on' => '★ Favorite',
    'fav_off' => '☆ Add to favorites',
    'info' => 'Info',
    'info_region' => 'Region',
    'info_city' => 'City',
    'reference_rate_short' => 'Reference rate',
    'not_guaranteed' => 'not guaranteed',
    'last_updated' => 'Last updated: :date',
    'contact' => 'Contact',
    'copy' => 'Copy',
    'call' => 'Call',
    'social_account' => 'Social account',
    'your_notes' => 'Your private notes',
    'notes_placeholder' => 'Private notes for your org about this creator…',
    'save_notes' => 'Save notes',
    'nominate_hint' => 'The creator is added to your relationships and nominated directly — advancing the campaign to its second stage.',
    'no_campaigns' => 'No campaigns available for nomination.',
    'add_and_nominate' => 'Add and nominate',
];
