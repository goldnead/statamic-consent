<?php

namespace Goldnead\StatamicConsent\Tests\Feature;

use Goldnead\StatamicConsent\Records\ConsentRecord;
use Goldnead\StatamicConsent\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class RecordEndpointTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('statamic-consent.record.enabled', true);
        $app['config']->set('statamic-consent.services', [
            ['handle' => 'youtube', 'name' => 'YouTube', 'category' => 'external_media'],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    protected function cookie(array $overrides = []): string
    {
        return rawurlencode(json_encode(array_merge([
            'v' => config('statamic-consent.version'),
            'granted' => ['youtube'],
            'ts' => now()->getTimestampMs(),
            'how' => 'accept_all',
            'id' => 'endpunkt-1',
        ], $overrides)));
    }

    #[Test]
    public function it_answers_204_and_writes_a_record(): void
    {
        $this->withUnencryptedCookie('statamic_consent', $this->cookie())
            ->post('/!/statamic-consent/record')
            ->assertNoContent();

        $this->assertSame(1, ConsentRecord::count());
    }

    #[Test]
    public function it_needs_no_csrf_token(): void
    {
        // The endpoint reads nothing but its own cookie, and SameSite=Lax keeps
        // that cookie off a cross-site post. A token would have to live in the
        // page and would be stale on anything served from a cache.
        $this->withoutExceptionHandling()
            ->withUnencryptedCookie('statamic_consent', $this->cookie())
            ->post('/!/statamic-consent/record')
            ->assertNoContent();
    }

    #[Test]
    public function it_answers_the_same_without_a_cookie(): void
    {
        // Deliberate: the endpoint must not tell a stranger whether a cookie was
        // present.
        $this->post('/!/statamic-consent/record')->assertNoContent();

        $this->assertSame(0, ConsentRecord::count());
    }

    #[Test]
    public function a_posted_body_is_ignored_entirely(): void
    {
        $this->withUnencryptedCookie('statamic_consent', $this->cookie())
            ->post('/!/statamic-consent/record', [
                'consent_id' => 'gefaelscht',
                'granted' => ['alles'],
                'how' => 'accept_all',
            ])
            ->assertNoContent();

        $record = ConsentRecord::first();

        // Everything came from the cookie. Nothing a page could put in a body,
        // a stranger could not also put there.
        $this->assertSame('endpunkt-1', $record->consent_id);
        $this->assertSame(['youtube'], $record->granted);
    }

    #[Test]
    public function it_still_answers_while_the_feature_is_off(): void
    {
        config(['statamic-consent.record.enabled' => false]);

        $this->withUnencryptedCookie('statamic_consent', $this->cookie())
            ->post('/!/statamic-consent/record')
            ->assertNoContent();

        $this->assertSame(0, ConsentRecord::count());
    }
}
