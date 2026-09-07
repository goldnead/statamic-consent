<?php

/*
 * Labels for the settings screen.
 *
 * Field keys are the config path with the dots flattened (`cookie.days` →
 * `cookie_days`): a dot inside a lang key is a path separator to the translator.
 */

return [

    'permission_group' => 'Consent',
    'permission_manage' => 'Manage consent settings',

    'groups' => [

        'decision' => [
            'title' => 'Decision',
            'description' => 'How long a visitor\'s decision holds and when it is asked for again. The cookie name and its SameSite value stay in config/statamic-consent.php: a different name makes every stored decision unfindable without it looking like a discard.',
        ],

        'assets' => [
            'title' => 'Shipped assets',
            'description' => 'Whether {{ consent:head }} prints the addon\'s stylesheet and script. Sites shipping their own CSS against the documented class names turn the stylesheet off here.',
        ],

        'record' => [
            'title' => 'Proof of consent',
            'description' => 'Whether and how long a server-side record is kept. The record itself is still switched on in config/statamic-consent.php, because switching it on needs a migration (php artisan migrate); so is the endpoint\'s rate limit, which is read while the route is registered.',
        ],

        'google' => [
            'title' => 'Google Consent Mode v2',
            'description' => 'Only for sites that load gtag. Which Google signal is bound to which service stays in config/statamic-consent.php under google_consent_mode.signals: it maps four signals onto lists and fits no field on this screen.',
        ],

    ],

    'fields' => [

        'version' => [
            'label' => 'Consent version',
            'description' => 'Raising it invalidates every stored decision and shows the banner to every visitor again. Raise it as soon as a non-essential service is added: the old decision never covered it.',
        ],

        'cookie_days' => [
            'label' => 'Validity in days',
            'description' => 'After this many days the cookie expires and the visitor is asked again. Applies to decisions made from now on; cookies already set keep their expiry.',
        ],

        'respect_gpc' => [
            'label' => 'Honour Global Privacy Control',
            'description' => 'When a browser sends the GPC signal, non-essential services count as rejected and the banner stops asking. Off means the signal is ignored.',
        ],

        'assets_styles' => [
            'label' => 'Print the stylesheet',
            'description' => 'Off means {{ consent:head }} prints no stylesheet and the banner is unstyled until your own CSS loads against the documented class names.',
        ],

        'assets_scripts' => [
            'label' => 'Print the script',
            'description' => 'Off means {{ consent:head }} no longer prints the addon\'s script. Without a script of your own no banner appears and no decision is stored.',
        ],

        'record_keep_days' => [
            'label' => 'Keep records for (days)',
            'description' => 'Records older than this are deleted on the next run of php please consent:prune. Empty means keep everything, which is the opposite of data minimisation.',
        ],

        'google_consent_mode_enabled' => [
            'label' => 'Consent Mode active',
            'description' => 'On means the page creates a gtag object and reports Google\'s four signals. On a site without a Google tag that creates an object which does nothing.',
        ],

        'google_consent_mode_wait_for_update' => [
            'label' => 'Wait for update (milliseconds)',
            'description' => 'How long Google waits for the update that follows the visitor\'s decision before it carries on without it. 0 means do not wait.',
        ],

    ],

];
