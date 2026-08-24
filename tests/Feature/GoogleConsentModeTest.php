<?php

namespace Goldnead\StatamicConsent\Tests\Feature;

use Goldnead\StatamicConsent\Support\Registry;
use Goldnead\StatamicConsent\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Antlers;

class GoogleConsentModeTest extends TestCase
{
    protected function parse(string $template): string
    {
        return (string) Antlers::parse($template, [], true);
    }

    protected function enable(array $signals = ['analytics_storage' => ['pixel']]): void
    {
        config([
            'statamic-consent.services' => [
                ['handle' => 'pixel', 'name' => 'Pixel', 'category' => 'analytics'],
            ],
            'statamic-consent.google_consent_mode.enabled' => true,
            'statamic-consent.google_consent_mode.signals' => $signals,
        ]);
    }

    #[Test]
    public function it_emits_nothing_when_switched_off(): void
    {
        $html = $this->parse('{{ consent:head }}');

        // The shipped default is off, and off has to mean absent. An addon that
        // creates a Google object on a site with no Google is the opposite of
        // what it is for.
        $this->assertStringNotContainsString('gtag', $html);
        $this->assertStringNotContainsString('dataLayer', $html);
    }

    #[Test]
    public function the_default_is_denied_for_every_mapped_signal(): void
    {
        $this->enable([
            'analytics_storage' => ['pixel'],
            'ad_storage' => [],
            'ad_user_data' => [],
            'ad_personalization' => [],
        ]);

        $html = $this->parse('{{ consent:head }}');

        $this->assertStringContainsString("gtag('consent', 'default'", $html);
        $this->assertStringContainsString('"analytics_storage":"denied"', $html);
        $this->assertStringContainsString('"ad_storage":"denied"', $html);
        $this->assertStringContainsString('"ad_user_data":"denied"', $html);
        $this->assertStringContainsString('"ad_personalization":"denied"', $html);
        $this->assertStringContainsString('"wait_for_update":500', $html);
    }

    #[Test]
    public function the_default_comes_before_the_deferred_runtime(): void
    {
        $this->enable();

        $html = $this->parse('{{ consent:head }}');

        // Order is the whole feature. A default that lands after Google's script
        // measures nothing, while the page looks like it is configured.
        $this->assertLessThan(
            strpos($html, 'consent.js'),
            strpos($html, "gtag('consent', 'default'"),
            'The Consent Mode default must be emitted before the deferred runtime.'
        );
    }

    #[Test]
    public function the_default_script_is_not_deferred(): void
    {
        $this->enable();

        $html = $this->parse('{{ consent:head }}');
        $block = substr($html, 0, (int) strpos($html, 'consent.js'));

        $this->assertStringNotContainsString('defer', $block);
        $this->assertStringNotContainsString('async', $block);
    }

    #[Test]
    public function the_payload_carries_the_mapping_for_the_runtime(): void
    {
        $this->enable(['analytics_storage' => ['pixel'], 'ad_storage' => []]);

        $mode = $this->app->make(Registry::class)->googleConsentMode();

        $this->assertSame(['pixel'], $mode['signals']['analytics_storage']);
        // Kept, not dropped: Google reads a missing signal as unanswered, which
        // is a different statement from denied.
        $this->assertSame([], $mode['signals']['ad_storage']);
    }

    #[Test]
    public function it_is_null_when_switched_off(): void
    {
        $this->assertNull(
            $this->app->make(Registry::class)->googleConsentMode()
        );
    }
}
