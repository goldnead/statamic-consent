<?php

namespace Goldnead\StatamicConsent\Tests\Feature;

use Goldnead\StatamicConsent\Records\ConsentRecord;
use Goldnead\StatamicConsent\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class PruneRecordsCommandTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('statamic-consent.record.enabled', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    #[Test]
    public function a_non_numeric_retention_fails_and_deletes_nothing(): void
    {
        config(['statamic-consent.record.keep_days' => 'abc']);

        ConsentRecord::create(['consent_id' => 'uralt', 'version' => 1, 'granted' => [], 'how' => 'gate', 'decided_at' => now()->subYears(9)]);

        $this->artisan('statamic:consent:prune')
            ->expectsOutputToContain('keep_days')
            ->assertExitCode(1);

        $this->assertSame(1, ConsentRecord::count());
    }

    #[Test]
    public function a_negative_retention_fails_and_deletes_nothing(): void
    {
        config(['statamic-consent.record.keep_days' => -5]);

        ConsentRecord::create(['consent_id' => 'frisch', 'version' => 1, 'granted' => [], 'how' => 'gate', 'decided_at' => now()]);

        $this->artisan('statamic:consent:prune')
            ->expectsOutputToContain('keep_days')
            ->assertExitCode(1);

        $this->assertSame(1, ConsentRecord::count());
    }

    #[Test]
    public function a_null_retention_is_the_documented_keep_everything_and_still_succeeds(): void
    {
        config(['statamic-consent.record.keep_days' => null]);

        ConsentRecord::create(['consent_id' => 'uralt', 'version' => 1, 'granted' => [], 'how' => 'gate', 'decided_at' => now()->subYears(9)]);

        $this->artisan('statamic:consent:prune')
            ->assertExitCode(0);

        $this->assertSame(1, ConsentRecord::count());
    }
}
