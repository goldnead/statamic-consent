<?php

return [
    'banner_title' => 'Cookies auf dieser Website',
    'banner_description' => 'Manche Cookies braucht die Seite, um zu funktionieren. Andere werden nur gesetzt, wenn du sie erlaubst.',
    'accept_all_label' => 'Alle akzeptieren',
    'only_necessary_label' => 'Nur essenzielle',
    'settings_label' => 'Einstellungen',
    'privacy_policy_label' => 'Datenschutzerklärung',
    'imprint_label' => 'Impressum',
    'modal_title' => 'Datenschutz-Einstellungen',
    'modal_description' => 'Entscheide, welche Dienste geladen werden dürfen. Du kannst das jederzeit ändern.',
    'save_label' => 'Auswahl speichern',
    'reject_all_label' => 'Alle ablehnen',
    'close_label' => 'Schließen',
    'always_active_label' => 'Immer aktiv',
    'service_policy_label' => 'Datenschutzerklärung',
    'blocked_title' => 'Inhalt blockiert',
    'blocked_message' => 'Dieser Inhalt wird von einem Drittanbieter bereitgestellt. Erlaube den Dienst, um ihn zu sehen.',
    'blocked_button_label' => 'Inhalt laden',
    'unknown_service' => 'Diese Einbindung verweist auf den Dienst „:handle", der nicht konfiguriert ist. Sie bleibt blockiert.',

    // Voreinstellungen für die mitgelieferten Kategorien und Dienste. Was im
    // Control Panel umbenannt wird, gewinnt; das hier füllt nur die Lücken.
    'categories' => [
        'essential' => [
            'name' => 'Essenziell',
            'description' => 'Erforderlich für den Betrieb der Website. Kann nicht deaktiviert werden.',
        ],
        'analytics' => [
            'name' => 'Analysen',
            'description' => 'Anonyme Nutzungsstatistik, die hilft, die Website zu verbessern.',
        ],
        'external_media' => [
            'name' => 'Externe Medien',
            'description' => 'Inhalte, die von externen Plattformen geladen werden.',
        ],
        'marketing' => [
            'name' => 'Marketing',
            'description' => 'Dient dazu, Angebote auf dich zuzuschneiden.',
        ],
    ],

    'services' => [
        'youtube' => ['description' => 'Eingebettete Videos von YouTube.'],
        'vimeo' => ['description' => 'Eingebettete Videos von Vimeo.'],
        'google_maps' => ['description' => 'Eingebettete Karten von Google Maps.'],
    ],
];
