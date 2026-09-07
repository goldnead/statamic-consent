<?php

namespace Goldnead\StatamicConsent\Support;

use Goldnead\BrandContext\Contracts\ProvidesSettings;

/**
 * Die Betriebswerte, die ein Betreiber im Control Panel ändern darf.
 *
 * Nur die Feldliste. Seite, Formular, Validierung, Speicher und Rechteprüfung
 * kommen aus `goldnead/statamic-brand-context` — siehe {@see ProvidesSettings}.
 *
 * **Abgrenzung zum Global-Set.** Alles, was Inhalt ist und übersetzt werden
 * muss, steht schon im Global-Set `consent` (Banner- und Dialogtexte,
 * `privacy_policy_url`, `imprint_url`, die Listen `services` und
 * `categories`). Für lokalisierbaren Inhalt ist ein Global das richtige Mittel,
 * und zwei Stellen für denselben Wert wären schlimmer als eine fehlende. Hier
 * steht deshalb ausschließlich, was das Global-Set nicht abdeckt.
 *
 * **Was nicht hier steht, und warum.**
 *
 * - `cookie.name` und `cookie.same_site`. Ein Namenswechsel verwirft still jede
 *   gespeicherte Entscheidung — der Server findet das alte Cookie nicht mehr —
 *   ohne dass es wie eine Verwerfung aussieht. `cookie.name` wird außerdem in
 *   `bootAddon()` an `EncryptCookies::except()` gegeben, also gelesen, bevor
 *   die Einstellungs-Schicht ihre Werte auf die Config legt.
 * - `record.enabled`. Das Einschalten lädt die Migrationen und braucht
 *   `php artisan migrate`; ein Schalter, der die Tabelle nicht mitbringt, ist
 *   im laufenden Betrieb keiner. Wird ebenfalls in `bootAddon()` gelesen.
 * - `record.rate_limit`. Wird beim Registrieren der Route gelesen
 *   (`routes/web.php:19`), also vor `SettingsManager::apply()`. Ein Schalter,
 *   der erst beim nächsten Deploy wirkt, ist eine Lüge in der Oberfläche.
 * - `google_consent_mode.signals`. Eine verschachtelte Abbildung von vier
 *   Google-Signalen auf Listen von Dienst-Handles. Der Vertrag dieser Schicht
 *   kennt dafür keinen Typ, und ein Textfeld, das eine Abbildung über ein
 *   erfundenes Trennzeichen hin- und zurückschreibt, ist ein schlechterer
 *   Editor als keiner.
 */
class Settings implements ProvidesSettings
{
    /**
     * Bleibt für immer stehen: der Wert steht in `brand_settings.namespace` in
     * jeder Zeile, ein neuer Name verwaist jede gespeicherte Änderung.
     */
    public static function settingsNamespace(): string
    {
        return 'consent';
    }

    /**
     * Nicht identisch mit dem Namensraum. Die Config-Datei dieses Addons heißt
     * `statamic-consent.php`, jedes `config('statamic-consent.…')` im Code
     * liest von dort.
     */
    public static function settingsConfigPath(): string
    {
        return 'statamic-consent';
    }

    public static function settingsPermission(): string
    {
        return 'manage consent settings';
    }

    /**
     * @return array<int, array{title: string, description: string, fields: array<int, array<string, mixed>>}>
     */
    public static function settingsGroups(): array
    {
        return [
            [
                'title' => __('statamic-consent::settings.groups.decision.title'),
                'description' => __('statamic-consent::settings.groups.decision.description'),
                'fields' => [
                    static::field('version', 'integer', ['min' => 1]),
                    static::field('cookie.days', 'integer', ['min' => 1]),
                    static::field('respect_gpc', 'boolean'),
                ],
            ],
            [
                'title' => __('statamic-consent::settings.groups.assets.title'),
                'description' => __('statamic-consent::settings.groups.assets.description'),
                'fields' => [
                    static::field('assets.styles', 'boolean'),
                    static::field('assets.scripts', 'boolean'),
                ],
            ],
            [
                'title' => __('statamic-consent::settings.groups.record.title'),
                'description' => __('statamic-consent::settings.groups.record.description'),
                'fields' => [
                    // `nullable` ist hier ein echter Zustand: leer heißt
                    // "alles behalten", und das ist etwas anderes als null Tage.
                    static::field('record.keep_days', 'integer', ['min' => 1, 'nullable' => true]),
                ],
            ],
            [
                'title' => __('statamic-consent::settings.groups.google.title'),
                'description' => __('statamic-consent::settings.groups.google.description'),
                'fields' => [
                    static::field('google_consent_mode.enabled', 'boolean'),
                    // 0 heißt "nicht warten" und muss erreichbar bleiben.
                    static::field('google_consent_mode.wait_for_update', 'integer', ['min' => 0]),
                ],
            ],
        ];
    }

    /**
     * Ein Feld, mit Beschriftung und Beschreibung aus den Sprachdateien.
     *
     * Der Übersetzungsschlüssel ist der Config-Pfad mit flachgelegten Punkten:
     * ein Punkt im Sprachschlüssel ist für den Übersetzer ein Pfadtrenner, und
     * `settings.fields.cookie.days.label` würde als vier verschachtelte Arrays
     * gesucht, die es nicht gibt.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected static function field(string $key, string $type, array $extra = []): array
    {
        $handle = str_replace('.', '_', $key);

        return array_merge([
            'key' => $key,
            'type' => $type,
            'label' => __("statamic-consent::settings.fields.{$handle}.label"),
            'description' => __("statamic-consent::settings.fields.{$handle}.description"),
            'nullable' => false,
        ], $extra);
    }
}
