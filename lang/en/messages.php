<?php

return [
    'banner_title' => 'Cookies on this website',
    'banner_description' => 'Some cookies are needed for the site to work. Others are only set if you allow them.',
    'accept_all_label' => 'Accept all',
    'only_necessary_label' => 'Essential only',
    'settings_label' => 'Settings',
    'privacy_policy_label' => 'Privacy policy',
    'imprint_label' => 'Imprint',
    'modal_title' => 'Privacy settings',
    'modal_description' => 'Decide which services may be loaded. You can change this at any time.',
    'save_label' => 'Save selection',
    'reject_all_label' => 'Reject all',
    'close_label' => 'Close',
    'always_active_label' => 'Always active',
    'service_policy_label' => 'Privacy policy',
    'blocked_title' => 'Content blocked',
    'blocked_message' => 'This content is provided by a third party. Allow the service to see it.',
    'blocked_button_label' => 'Load content',
    'unknown_service' => 'This embed refers to the service ":handle", which is not configured. It stays blocked.',

    // Defaults for the categories and services the addon ships. A site that
    // renames one in the control panel wins; these only fill the blanks.
    'categories' => [
        'essential' => [
            'name' => 'Essential',
            'description' => 'Required for the site to work. Cannot be switched off.',
        ],
        'analytics' => [
            'name' => 'Analytics',
            'description' => 'Anonymous usage statistics that help improve the site.',
        ],
        'external_media' => [
            'name' => 'External media',
            'description' => 'Content loaded from external platforms.',
        ],
        'marketing' => [
            'name' => 'Marketing',
            'description' => 'Used to tailor offers to you.',
        ],
    ],

    'services' => [
        'youtube' => ['description' => 'Embedded videos from YouTube.'],
        'vimeo' => ['description' => 'Embedded videos from Vimeo.'],
        'google_maps' => ['description' => 'Embedded maps from Google Maps.'],
    ],

    // Metrics for the Insights dashboard. They count decisions, never people:
    // `consent_id` is a cookie token, not a human being.
    'metric_group' => 'Consent',
    'metric_decisions' => 'Decisions',
    'metric_decisions_description' => 'How many cookie decisions were made in the period. The decision is counted, not the person.',
    'metric_breakdown_version' => 'Version',
    'metric_breakdown_how' => 'Kind of decision',
    'metric_breakdown_site' => 'Site',
    'metric_no_version' => 'No version',
    'metric_no_how' => 'Not stated',
    'metric_no_site' => 'No site',
    'metric_version_label' => 'Version :version',
    'metric_how' => [
        'accept_all' => 'Accepted everything',
        'necessary_only' => 'Essential only',
        'reject_all' => 'Rejected everything',
        'custom' => 'Own selection',
        'gate' => 'Individual content allowed',
        'gpc' => 'Global Privacy Control',
        'unknown' => 'Unknown',
    ],
];
