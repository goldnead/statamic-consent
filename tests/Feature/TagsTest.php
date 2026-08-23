<?php

namespace Goldnead\StatamicConsent\Tests\Feature;

use Goldnead\StatamicConsent\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Antlers;

class TagsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['statamic-consent.services' => [
            ['handle' => 'session', 'name' => 'Session', 'category' => 'essential'],
            [
                'handle' => 'youtube',
                'name' => 'YouTube',
                'category' => 'external_media',
                'policy_url' => 'https://policies.google.com/privacy',
                'block_content' => true,
            ],
            [
                'handle' => 'analytics_pixel',
                'name' => 'Pixel',
                'category' => 'analytics',
                'block_content' => false,
            ],
        ]]);
    }

    /**
     * The third argument marks the template as trusted. Without it Antlers
     * treats the string as user data and skips every tag without a word of
     * complaint — the test then asserts against an empty string and passes for
     * the wrong reason.
     */
    protected function parse(string $template): string
    {
        return (string) Antlers::parse($template, [], true);
    }

    #[Test]
    public function the_gate_keeps_the_embed_inside_a_template_element(): void
    {
        $html = $this->parse('{{ consent:gate service="youtube" }}<iframe src="https://www.youtube.com/embed/x"></iframe>{{ /consent:gate }}');

        // The iframe must exist only inside <template>. A browser parses that
        // but issues no request, which is the entire two-click guarantee.
        $this->assertStringContainsString('<template data-consent-embed>', $html);
        $this->assertStringContainsString('<iframe src="https://www.youtube.com/embed/x">', $html);

        $withoutTemplate = preg_replace('#<template data-consent-embed>.*?</template>#s', '', $html);
        $this->assertStringNotContainsString('<iframe', (string) $withoutTemplate);
    }

    #[Test]
    public function the_gate_shows_the_placeholder_and_a_load_button(): void
    {
        $html = $this->parse('{{ consent:gate service="youtube" }}<iframe></iframe>{{ /consent:gate }}');

        $this->assertStringContainsString('data-consent-gate="youtube"', $html);
        $this->assertStringContainsString('data-consent-allow="youtube"', $html);
        $this->assertStringContainsString('csnt-gate__placeholder', $html);
    }

    #[Test]
    public function a_service_that_does_not_block_content_renders_straight_through(): void
    {
        $html = $this->parse('{{ consent:gate service="analytics_pixel" }}<span>plain</span>{{ /consent:gate }}');

        $this->assertStringNotContainsString('<template', $html);
        $this->assertStringContainsString('<span>plain</span>', $html);
    }

    #[Test]
    public function an_unknown_service_stays_blocked_instead_of_failing_open(): void
    {
        $html = $this->parse('{{ consent:gate service="tpyo" }}<iframe src="https://example.com"></iframe>{{ /consent:gate }}');

        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringContainsString('csnt-gate--unknown', $html);
        $this->assertStringContainsString('tpyo', $html);
    }

    #[Test]
    public function a_gate_without_a_service_parameter_stays_blocked(): void
    {
        $html = $this->parse('{{ consent:gate }}<iframe src="https://example.com"></iframe>{{ /consent:gate }}');

        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringContainsString('csnt-gate--unknown', $html);
    }

    #[Test]
    public function the_head_tag_inlines_the_payload_and_the_script(): void
    {
        $html = $this->parse('{{ consent:head }}');

        $this->assertStringContainsString('id="statamic-consent-config"', $html);
        $this->assertStringContainsString('vendor/statamic-consent/consent.js', $html);
        $this->assertStringContainsString('vendor/statamic-consent/consent.css', $html);

        preg_match('#<script id="statamic-consent-config" type="application/json">(.*?)</script>#s', $html, $matches);
        $payload = json_decode($matches[1], true);

        $this->assertIsArray($payload);
        $this->assertSame(['session'], $payload['required']);
    }

    #[Test]
    public function the_payload_cannot_break_out_of_its_script_tag(): void
    {
        // A service name is client-editable text. Unescaped, "</script>" inside
        // it would end the JSON block early and turn the rest into markup.
        config(['statamic-consent.services' => [
            ['handle' => 'evil', 'name' => '</script><img src=x onerror=alert(1)>', 'category' => 'analytics'],
        ]]);

        $html = $this->parse('{{ consent:head }}');

        $this->assertStringNotContainsString('</script><img', $html);
        $this->assertStringContainsString('id="statamic-consent-config"', $html);
    }

    #[Test]
    public function the_banner_renders_every_way_out(): void
    {
        $html = $this->parse('{{ consent:banner }}');

        $this->assertStringContainsString('data-consent-accept-all', $html);
        $this->assertStringContainsString('data-consent-necessary', $html);
        $this->assertStringContainsString('data-consent-reject-all', $html);
        $this->assertStringContainsString('data-consent-open', $html);
    }

    #[Test]
    public function the_banner_starts_hidden_so_a_decided_visitor_sees_no_flash(): void
    {
        $html = $this->parse('{{ consent:banner }}');

        $this->assertMatchesRegularExpression('#<div class="csnt" data-consent-root hidden>#', $html);
    }

    #[Test]
    public function the_banner_offers_a_toggle_for_optional_services_only(): void
    {
        $html = $this->parse('{{ consent:banner }}');

        $this->assertStringContainsString('data-consent-service="youtube"', $html);
        $this->assertStringNotContainsString('data-consent-service="session"', $html);
    }

    #[Test]
    public function the_settings_link_renders_a_button_that_opens_the_dialog(): void
    {
        $html = $this->parse('{{ consent:settings_link label="Cookies" }}');

        $this->assertStringContainsString('data-consent-open', $html);
        $this->assertStringContainsString('Cookies', $html);
    }

    #[Test]
    public function granted_hides_its_contents_without_a_decision(): void
    {
        $html = $this->parse('{{ consent:granted service="youtube" }}shown{{ /consent:granted }}');

        $this->assertSame('', trim($html));
    }
}
