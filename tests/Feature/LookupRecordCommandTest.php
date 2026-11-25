<?php

namespace Goldnead\StatamicConsent\Tests\Feature;

use Goldnead\StatamicConsent\Records\ConsentRecord;
use Goldnead\StatamicConsent\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class LookupRecordCommandTest extends TestCase
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

    protected function record(string $consentId, string $decidedAt = '2026-11-01 10:00:00'): void
    {
        ConsentRecord::create([
            'consent_id' => $consentId,
            'version' => 1,
            'granted' => ['youtube'],
            'how' => 'accept_all',
            'site' => 'default',
            'decided_at' => $decidedAt,
        ]);
    }

    #[Test]
    public function a_consent_id_of_zero_is_looked_up_and_not_replaced_by_the_latest(): void
    {
        $this->record('0');
        $this->record('andere');

        $this->artisan('statamic:consent:lookup', ['id' => '0'])
            ->assertExitCode(0)
            ->doesntExpectOutputToContain('andere');
    }

    #[Test]
    public function a_latest_of_zero_is_refused(): void
    {
        $this->record('a');

        $this->artisan('statamic:consent:lookup', ['--latest' => '0'])
            ->assertExitCode(1)
            ->expectsOutputToContain('--latest');
    }

    #[Test]
    public function a_latest_that_is_not_a_number_is_refused(): void
    {
        $this->record('a');

        $this->artisan('statamic:consent:lookup', ['--latest' => 'viele'])
            ->assertExitCode(1)
            ->expectsOutputToContain('--latest');
    }

    #[Test]
    public function a_truncated_list_says_so(): void
    {
        for ($i = 1; $i <= 21; $i++) {
            $this->record('id-'.$i, sprintf('2026-11-01 10:%02d:00', $i));
        }

        $this->artisan('statamic:consent:lookup')
            ->assertExitCode(0)
            ->expectsOutputToContain('Nur die letzten 20');
    }

    #[Test]
    public function a_csv_that_is_written_reports_success(): void
    {
        if (! file_exists('/dev/null')) {
            $this->markTestSkipped('No /dev/null on this system.');
        }

        $this->record('a');

        $this->artisan('statamic:consent:lookup', ['--csv' => '/dev/null'])
            ->assertExitCode(0)
            ->expectsOutputToContain('1 record(s) written');
    }

    #[Test]
    public function a_csv_that_cannot_be_written_fails_and_does_not_claim_success(): void
    {
        if (! file_exists('/dev/full')) {
            $this->markTestSkipped('No /dev/full on this system to simulate a full disk.');
        }

        $this->record('a');

        $this->artisan('statamic:consent:lookup', ['--csv' => '/dev/full'])
            ->assertExitCode(1)
            ->doesntExpectOutputToContain('written');
    }
}
