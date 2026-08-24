<?php

namespace Goldnead\StatamicConsent\Tests\Feature;

use Goldnead\StatamicConsent\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Antlers;

/**
 * A site that loads no third party has nothing to ask about.
 *
 * Strictly necessary cookies need no consent, so a banner there is not caution,
 * it is noise — and it trains people to click the nearest button. It is also the
 * state every installation is in on its first day, before anyone has entered a
 * service.
 */
class NothingToAskTest extends TestCase
{
    protected function parse(string $template): string
    {
        return (string) Antlers::parse($template, [], true);
    }

    protected function onlyEssential(): void
    {
        config(['statamic-consent.services' => [
            ['handle' => 'session', 'name' => 'Session', 'category' => 'essential'],
        ]]);
    }

    #[Test]
    public function the_banner_renders_nothing(): void
    {
        $this->onlyEssential();

        $this->assertSame('', trim($this->parse('{{ consent:banner }}')));
    }

    #[Test]
    public function the_head_renders_nothing(): void
    {
        $this->onlyEssential();

        // No stylesheet, no script, no payload: the page is untouched.
        $this->assertSame('', trim($this->parse('{{ consent:head }}')));
    }

    #[Test]
    public function the_settings_link_renders_nothing(): void
    {
        $this->onlyEssential();

        $this->assertSame('', trim($this->parse('{{ consent:settings_link }}')));
    }

    #[Test]
    public function no_services_at_all_is_the_same_answer(): void
    {
        config(['statamic-consent.services' => []]);

        $this->assertSame('', trim($this->parse('{{ consent:head }}')));
        $this->assertSame('', trim($this->parse('{{ consent:banner }}')));
    }

    #[Test]
    public function one_optional_service_brings_everything_back(): void
    {
        config(['statamic-consent.services' => [
            ['handle' => 'session', 'name' => 'Session', 'category' => 'essential'],
            ['handle' => 'youtube', 'name' => 'YouTube', 'category' => 'external_media'],
        ]]);

        $this->assertStringContainsString('data-consent-banner', $this->parse('{{ consent:banner }}'));
        $this->assertStringContainsString('consent.js', $this->parse('{{ consent:head }}'));
        $this->assertStringContainsString('data-consent-open', $this->parse('{{ consent:settings_link }}'));
    }

    #[Test]
    public function a_gate_still_blocks_even_with_the_banner_gone(): void
    {
        // The gate must not fall through just because nothing is rendered around
        // it: a service that blocks content is by definition optional, so this
        // combination cannot occur — but if a config ever produced it, the safe
        // answer is still "blocked".
        config(['statamic-consent.services' => [
            ['handle' => 'session', 'name' => 'Session', 'category' => 'essential'],
        ]]);

        $html = $this->parse('{{ consent:gate service="youtube" }}<iframe src="https://example.com"></iframe>{{ /consent:gate }}');

        $this->assertStringNotContainsString('<iframe', $html);
    }
}
