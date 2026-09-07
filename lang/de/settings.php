<?php

/*
 * Die Beschriftungen der Einstellungsseite.
 *
 * Feldschlüssel sind der Config-Pfad mit flachgelegten Punkten
 * (`cookie.days` → `cookie_days`): ein Punkt im Sprachschlüssel ist für den
 * Übersetzer ein Pfadtrenner.
 */

return [

    'permission_group' => 'Consent',
    'permission_manage' => 'Consent-Einstellungen verwalten',

    'groups' => [

        'decision' => [
            'title' => 'Entscheidung',
            'description' => 'Wie lange die Entscheidung eines Besuchers gilt und wann sie neu eingeholt wird. Cookie-Name und SameSite-Wert stehen weiterhin in der Datei config/statamic-consent.php: ein anderer Name macht jede gespeicherte Entscheidung unauffindbar, ohne dass es wie eine Verwerfung aussieht.',
        ],

        'assets' => [
            'title' => 'Mitgelieferte Dateien',
            'description' => 'Ob der Tag {{ consent:head }} Stylesheet und Skript des Addons ausgibt. Wer eigenes CSS gegen die dokumentierten Klassennamen schreibt, schaltet den Stil hier ab.',
        ],

        'record' => [
            'title' => 'Einwilligungsnachweis',
            'description' => 'Ob und wie lange ein serverseitiger Nachweis aufbewahrt wird. Der Nachweis selbst wird weiterhin in config/statamic-consent.php eingeschaltet, weil das Einschalten eine Migration braucht (php artisan migrate); ebenso die Anfragebremse des Endpunkts, die schon beim Registrieren der Route gelesen wird.',
        ],

        'google' => [
            'title' => 'Google Consent Mode v2',
            'description' => 'Nur für Seiten, die gtag laden. Welches Google-Signal an welchen Dienst gebunden ist, steht weiterhin in config/statamic-consent.php unter google_consent_mode.signals: das ist eine Abbildung von vier Signalen auf Listen und passt in kein Feld dieser Seite.',
        ],

    ],

    'fields' => [

        'version' => [
            'label' => 'Version der Einwilligung',
            'description' => 'Erhöhen macht jede bereits gespeicherte Entscheidung ungültig und zeigt jedem Besucher das Banner erneut. Zu erhöhen, sobald ein nicht-essenzieller Dienst dazukommt: die alte Entscheidung hat ihn nie abgedeckt.',
        ],

        'cookie_days' => [
            'label' => 'Gültigkeit in Tagen',
            'description' => 'Nach so vielen Tagen läuft das Cookie ab und der Besucher wird erneut gefragt. Gilt für Entscheidungen, die ab jetzt getroffen werden; bereits gesetzte Cookies behalten ihre Frist.',
        ],

        'respect_gpc' => [
            'label' => 'Global Privacy Control beachten',
            'description' => 'Sendet ein Browser das GPC-Signal, gelten nicht-essenzielle Dienste als abgelehnt und das Banner fragt nicht mehr. Abschalten heißt, das Signal zu ignorieren.',
        ],

        'assets_styles' => [
            'label' => 'Stylesheet ausgeben',
            'description' => 'Aus heißt: {{ consent:head }} gibt kein Stylesheet mehr aus und das Banner ist ungestaltet, bis eigenes CSS gegen die dokumentierten Klassennamen geladen wird.',
        ],

        'assets_scripts' => [
            'label' => 'Skript ausgeben',
            'description' => 'Aus heißt: {{ consent:head }} gibt das Skript des Addons nicht mehr aus. Ohne eigenes Skript wird dann kein Banner angezeigt und keine Entscheidung gespeichert.',
        ],

        'record_keep_days' => [
            'label' => 'Nachweis aufbewahren (Tage)',
            'description' => 'Nachweise, die älter sind, löscht php please consent:prune beim nächsten Lauf. Leer heißt: alles behalten, was das Gegenteil von Datenminimierung ist.',
        ],

        'google_consent_mode_enabled' => [
            'label' => 'Consent Mode aktiv',
            'description' => 'An heißt: die Seite legt ein gtag-Objekt an und meldet Googles vier Signale. Auf einer Seite ohne Google-Tag erzeugt das ein Objekt, das nichts tut.',
        ],

        'google_consent_mode_wait_for_update' => [
            'label' => 'Wartezeit in Millisekunden',
            'description' => 'So lange wartet Google auf die Aktualisierung nach der Entscheidung des Besuchers, bevor es die Seite ohne sie weiterlädt. 0 heißt: gar nicht warten.',
        ],

    ],

];
