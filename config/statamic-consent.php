<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cookie
    |--------------------------------------------------------------------------
    |
    | The visitor's decision is stored in a first-party cookie so the server can
    | read it too, plus a localStorage mirror for pages served from a cache that
    | strips cookies. Raising "version" invalidates every stored decision and
    | shows the banner again — do that when you add a service that is not
    | essential, because the old decision never covered it.
    |
    */

    'cookie' => [
        'name' => 'statamic_consent',
        'days' => 182,
        'same_site' => 'Lax',
    ],

    'version' => 1,

    /*
    |--------------------------------------------------------------------------
    | Behaviour
    |--------------------------------------------------------------------------
    |
    | "reject_on_dismiss" decides what a closed banner means. Keep it true: under
    | the GDPR, no decision is a rejection, and a banner that can only be
    | accepted is not a choice.
    |
    | "respect_gpc" honours the Global Privacy Control browser signal, which
    | German courts have read as a valid objection.
    |
    */

    'reject_on_dismiss' => true,
    'respect_gpc' => true,

    /*
    |--------------------------------------------------------------------------
    | Assets
    |--------------------------------------------------------------------------
    |
    | The {{ consent:head }} tag prints the stylesheet and script tags. Turn
    | "styles" off to ship your own CSS against the documented class names.
    |
    */

    'assets' => [
        'styles' => true,
        'scripts' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Consent Mode v2
    |--------------------------------------------------------------------------
    |
    | Off by default, and deliberately so: an addon that creates a Google object
    | on a site that does not use Google is the opposite of what it is for.
    |
    | Switch it on only where the site actually loads gtag (Google Analytics,
    | Google Ads, Tag Manager). Then map each of Google's four signals to the
    | service handles that must be granted for it. An empty list means the signal
    | stays denied — which is the right default for anything you have not thought
    | about yet.
    |
    | "wait_for_update" is how long, in milliseconds, Google waits for the update
    | that follows a visitor's decision before it gives up on the page.
    |
    */

    'google_consent_mode' => [
        'enabled' => false,

        'signals' => [
            'analytics_storage' => [],
            'ad_storage' => [],
            'ad_user_data' => [],
            'ad_personalization' => [],
        ],

        'wait_for_update' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Categories
    |--------------------------------------------------------------------------
    |
    | The grouping the visitor sees in the settings dialog. "essential" is
    | always present and can never be switched off. Name and description are
    | left out on purpose: the shipped four are translated, so a site that adds
    | nothing gets a banner in the visitor's language. Set them here, or in the
    | control panel, to override.
    |
    */

    'categories' => [
        ['handle' => 'essential'],
        ['handle' => 'analytics'],
        ['handle' => 'external_media'],
        ['handle' => 'marketing'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Services
    |--------------------------------------------------------------------------
    |
    | One entry per third party that sets a cookie or receives data. The handle
    | is what {{ consent:gate }} and {{ consent:granted }} refer to; it is part
    | of your templates, so do not rename it after launch.
    |
    | "block_content" is the two-click gate: an embed wrapped in a gate is not in
    | the document at all until the visitor allows the service.
    |
    */

    'services' => [
        // Empty on purpose. A service listed here appears in the banner, and a
        // banner that offers YouTube on a site with no YouTube describes data
        // processing that does not happen. Add what this site actually loads —
        // here, or in the control panel under Globals → Consent.
        //
        // The shape, for the three most common:
        //
        //   ['handle' => 'youtube',     'name' => 'YouTube',     'category' => 'external_media',
        //    'policy_url' => 'https://policies.google.com/privacy', 'block_content' => true],
        //   ['handle' => 'vimeo',       'name' => 'Vimeo',       'category' => 'external_media',
        //    'policy_url' => 'https://vimeo.com/privacy',           'block_content' => true],
        //   ['handle' => 'google_maps', 'name' => 'Google Maps', 'category' => 'external_media',
        //    'policy_url' => 'https://policies.google.com/privacy', 'block_content' => true],
        //
        // With nothing here the addon renders nothing at all: no banner, no
        // stylesheet, no script. The site is untouched until you add one.
    ],
];
