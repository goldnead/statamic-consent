<?php

namespace Goldnead\StatamicConsent\Tests\Feature;

use Goldnead\BrandContext\Facades\BrandSettings;
use Goldnead\BrandContext\Settings\SettingsRegistry;
use Goldnead\StatamicConsent\Support\Registry;
use Goldnead\StatamicConsent\Support\Settings;
use Goldnead\StatamicConsent\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Die Einstellungen dieses Addons an der geteilten Schicht.
 *
 * Geprüft wird nicht, dass die Schicht funktioniert — das gehört in deren
 * eigene Suite — sondern dass dieses Addon richtig daran hängt: die Anmeldung,
 * der Config-Pfad, das Recht, und vor allem, dass ein gespeicherter Wert bis
 * zum Leser durchkommt. `Registry::payload()` ist dieser Leser: das Objekt,
 * das der Browser bekommt und gegen das das Skript entscheidet.
 */
class SettingsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_registers_itself_with_the_shared_settings_layer(): void
    {
        $registry = app(SettingsRegistry::class);

        $this->assertTrue($registry->has('consent'), 'boot() hat die Einstellungen nicht angemeldet.');
        $this->assertSame(Settings::class, $registry->provider('consent'));
        // Nicht `consent`: die Config-Datei dieses Addons heißt anders als sein
        // Namensraum, und ein falscher Pfad schriebe Werte in fremde Config.
        $this->assertSame('statamic-consent', $registry->configPath('consent'));
        $this->assertSame('manage consent settings', $registry->permission('consent'));
    }

    #[Test]
    public function a_saved_version_reaches_the_payload_the_browser_gets(): void
    {
        $this->assertSame(1, app(Registry::class)->payload()['version']);

        BrandSettings::for('consent')->save(['version' => 7]);

        // Erst die Config, dann der echte Leser. Nur das zweite belegt, dass
        // der Wert dort ankommt, wo entschieden wird.
        $this->assertSame(7, config('statamic-consent.version'));
        $this->assertSame(7, app(Registry::class)->payload()['version']);
    }

    #[Test]
    public function a_saved_cookie_lifetime_reaches_the_payload(): void
    {
        BrandSettings::for('consent')->save(['cookie.days' => 30]);

        $this->assertSame(30, app(Registry::class)->payload()['cookie']['days']);
    }

    #[Test]
    public function switching_off_gpc_reaches_the_payload(): void
    {
        $this->assertTrue(app(Registry::class)->payload()['respectGpc']);

        BrandSettings::for('consent')->save(['respect_gpc' => false]);

        $this->assertFalse(app(Registry::class)->payload()['respectGpc']);
    }

    #[Test]
    public function no_key_that_is_read_while_booting_is_offered(): void
    {
        // Die Schicht legt ihre Werte aus `app->booted()` auf die Config. Was
        // vorher gelesen wird — beim Registrieren der Route, in bootAddon() —
        // sähe auf dem Bildschirm aus wie ein Schalter und wirkte erst beim
        // nächsten Deploy.
        $offered = array_keys(app(SettingsRegistry::class)->fields('consent'));

        foreach (['record.rate_limit', 'record.enabled', 'cookie.name'] as $key) {
            $this->assertNotContains($key, $offered);
        }
    }

    #[Test]
    public function nothing_the_global_set_already_owns_is_offered(): void
    {
        // Zwei Stellen für denselben Wert sind schlimmer als eine fehlende.
        $offered = array_keys(app(SettingsRegistry::class)->fields('consent'));

        foreach (['services', 'categories', 'google_consent_mode.signals'] as $key) {
            $this->assertNotContains($key, $offered);
        }
    }
}
