<?php

namespace Goldnead\StatamicConsent\Tests\Feature;

use Goldnead\StatamicConsent\Records\ConsentRecord;
use Goldnead\StatamicConsent\Records\Recorder;
use Goldnead\StatamicConsent\Support\Registry;
use Goldnead\StatamicConsent\Tests\TestCase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;

class RecordTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('statamic-consent.record.enabled', true);
        $app['config']->set('statamic-consent.services', [
            ['handle' => 'session', 'name' => 'Session', 'category' => 'essential'],
            ['handle' => 'youtube', 'name' => 'YouTube', 'category' => 'external_media'],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    protected function requestWith(array $decision): Request
    {
        $request = Request::create('/!/statamic-consent/record', 'POST');
        $request->cookies->set('statamic_consent', rawurlencode(json_encode($decision)));

        return $request;
    }

    protected function decision(array $overrides = []): array
    {
        return array_merge([
            'v' => config('statamic-consent.version'),
            'granted' => ['session', 'youtube'],
            'ts' => now()->getTimestampMs(),
            'how' => 'accept_all',
            'id' => 'abc-123',
        ], $overrides);
    }

    protected function recorder(): Recorder
    {
        return $this->app->make(Recorder::class);
    }

    #[Test]
    public function it_records_what_the_cookie_says(): void
    {
        $this->recorder()->record($this->requestWith($this->decision()));

        $record = ConsentRecord::first();

        $this->assertSame('abc-123', $record->consent_id);
        $this->assertSame(['session', 'youtube'], $record->granted);
        $this->assertSame('accept_all', $record->how);
        $this->assertSame(config('statamic-consent.version'), $record->version);
    }

    #[Test]
    public function a_request_without_the_cookie_records_nothing(): void
    {
        // This is what a cross-site post looks like: SameSite=Lax does not send
        // the cookie on one, so there is nothing to write. That is the whole
        // CSRF defence for this endpoint.
        $this->recorder()->record(Request::create('/!/statamic-consent/record', 'POST'));

        $this->assertSame(0, ConsentRecord::count());
    }

    #[Test]
    public function it_stores_no_ip_and_no_user_agent(): void
    {
        $request = $this->requestWith($this->decision());
        $request->headers->set('User-Agent', 'Mozilla/5.0 verraeterisch');
        $request->server->set('REMOTE_ADDR', '203.0.113.9');

        $this->recorder()->record($request);

        $columns = array_keys(ConsentRecord::first()->getAttributes());

        // Both are personal data in their own right and neither is needed: the
        // id does the linking. Storing them would turn a proof log into a
        // visitor database.
        $this->assertNotContains('ip', $columns);
        $this->assertNotContains('ip_address', $columns);
        $this->assertNotContains('user_agent', $columns);
    }

    #[Test]
    public function an_unknown_handle_in_the_cookie_is_dropped(): void
    {
        // The cookie is under the visitor's control. A handle this site does not
        // offer is either a stale decision or someone editing it; either way it
        // is not evidence of anything.
        $this->recorder()->record($this->requestWith($this->decision([
            'granted' => ['youtube', 'erfunden'],
        ])));

        $this->assertSame(['youtube'], ConsentRecord::first()->granted);
    }

    #[Test]
    public function the_same_decision_is_recorded_once(): void
    {
        $decision = $this->decision();

        $this->recorder()->record($this->requestWith($decision));
        $this->recorder()->record($this->requestWith($decision));
        $this->recorder()->record($this->requestWith($decision));

        // A visitor who reloads a page does not create a second record of the
        // same decision.
        $this->assertSame(1, ConsentRecord::count());
    }

    #[Test]
    public function a_new_decision_is_a_new_record(): void
    {
        $this->recorder()->record($this->requestWith($this->decision()));
        $this->recorder()->record($this->requestWith($this->decision([
            'granted' => ['session'],
            'how' => 'reject_all',
            'ts' => now()->addMinute()->getTimestampMs(),
        ])));

        $this->assertSame(2, ConsentRecord::count());
        $this->assertSame('reject_all', ConsentRecord::query()->orderByDesc('decided_at')->first()->how);
    }

    #[Test]
    public function an_impossible_clock_falls_back_to_server_time(): void
    {
        // A device with a wrong clock would otherwise date a record 1970 or
        // 2049, and impossible dates make a proof log worth less than one that
        // admits the fallback.
        $this->recorder()->record($this->requestWith($this->decision([
            'ts' => 0,
            'id' => 'kaputte-uhr',
        ])));

        $this->assertTrue(ConsentRecord::first()->decided_at->isAfter(now()->subMinute()));
    }

    #[Test]
    public function an_invented_how_becomes_unknown(): void
    {
        $this->recorder()->record($this->requestWith($this->decision(['how' => '<script>'])));

        $this->assertSame('unknown', ConsentRecord::first()->how);
    }

    #[Test]
    public function a_cookie_from_an_older_version_records_nothing(): void
    {
        config(['statamic-consent.version' => 5]);

        $this->recorder()->record($this->requestWith($this->decision(['v' => 1])));

        $this->assertSame(0, ConsentRecord::count());
    }

    #[Test]
    public function nothing_is_recorded_while_the_feature_is_off(): void
    {
        config(['statamic-consent.record.enabled' => false]);

        $this->recorder()->record($this->requestWith($this->decision()));

        $this->assertSame(0, ConsentRecord::count());
    }

    #[Test]
    public function the_payload_tells_the_browser_whether_to_ping(): void
    {
        $registry = $this->app->make(Registry::class);
        $this->assertTrue($registry->payload()['record']);

        config(['statamic-consent.record.enabled' => false]);
        $this->assertFalse((new Registry)->payload()['record']);
    }

    #[Test]
    public function pruning_deletes_only_what_is_past_the_retention(): void
    {
        config(['statamic-consent.record.keep_days' => 30]);

        ConsentRecord::create(['consent_id' => 'alt', 'version' => 1, 'granted' => [], 'how' => 'gate', 'decided_at' => now()->subDays(40)]);
        ConsentRecord::create(['consent_id' => 'neu', 'version' => 1, 'granted' => [], 'how' => 'gate', 'decided_at' => now()->subDays(10)]);

        $this->assertSame(1, $this->recorder()->prune());
        $this->assertSame(['neu'], ConsentRecord::pluck('consent_id')->all());
    }

    #[Test]
    public function pruning_deletes_nothing_without_a_retention(): void
    {
        config(['statamic-consent.record.keep_days' => null]);

        ConsentRecord::create(['consent_id' => 'uralt', 'version' => 1, 'granted' => [], 'how' => 'gate', 'decided_at' => now()->subYears(9)]);

        $this->assertSame(0, $this->recorder()->prune());
        $this->assertSame(1, ConsentRecord::count());
    }

    #[Test]
    public function the_record_for_one_person_comes_back_newest_first(): void
    {
        ConsentRecord::create(['consent_id' => 'p', 'version' => 1, 'granted' => [], 'how' => 'reject_all', 'decided_at' => now()->subDay()]);
        ConsentRecord::create(['consent_id' => 'p', 'version' => 1, 'granted' => ['youtube'], 'how' => 'accept_all', 'decided_at' => now()]);
        ConsentRecord::create(['consent_id' => 'andere', 'version' => 1, 'granted' => [], 'how' => 'gate', 'decided_at' => now()]);

        $found = ConsentRecord::query()->forConsentId('p')->get();

        $this->assertCount(2, $found);
        $this->assertSame('accept_all', $found->first()->how);
    }
}
