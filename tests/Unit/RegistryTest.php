<?php

namespace Goldnead\StatamicConsent\Tests\Unit;

use Goldnead\StatamicConsent\Support\Registry;
use Goldnead\StatamicConsent\Tests\TestCase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;

class RegistryTest extends TestCase
{
    protected function registry(): Registry
    {
        return $this->app->make(Registry::class);
    }

    #[Test]
    public function it_ships_no_services_at_all(): void
    {
        // A service listed by default appears in the banner of every fresh
        // install, describing data processing that site may not do.
        $this->assertSame([], $this->registry()->services());
    }

    #[Test]
    public function it_reads_services_from_the_config_file(): void
    {
        config(['statamic-consent.services' => [
            ['handle' => 'youtube', 'name' => 'YouTube', 'category' => 'external_media'],
            ['handle' => 'google_maps', 'name' => 'Google Maps', 'category' => 'external_media'],
        ]]);

        $handles = collect($this->registry()->services())->pluck('handle')->all();

        $this->assertContains('youtube', $handles);
        $this->assertContains('google_maps', $handles);
    }

    #[Test]
    public function a_list_emptied_in_the_control_panel_stays_empty(): void
    {
        config(['statamic-consent.services' => [
            ['handle' => 'youtube', 'name' => 'YouTube', 'category' => 'external_media'],
        ]]);

        $set = GlobalSet::make('consent')->title('Consent');
        $set->save();
        $variables = $set->makeLocalization(Site::default()->handle());
        // The client deleted every row. That is an answer, not a missing value —
        // falling back to the config here would hand them back what they removed.
        $variables->data(['services' => []]);
        $variables->save();

        $this->assertSame([], (new Registry)->services());
    }

    #[Test]
    public function it_drops_categories_that_have_no_services(): void
    {
        config(['statamic-consent.services' => [
            ['handle' => 'youtube', 'name' => 'YouTube', 'category' => 'external_media'],
        ]]);

        $handles = collect($this->registry()->categories())->pluck('handle')->all();

        $this->assertSame(['external_media'], $handles);
    }

    #[Test]
    public function a_service_in_the_essential_category_is_always_required(): void
    {
        config(['statamic-consent.services' => [
            ['handle' => 'session', 'name' => 'Session', 'category' => 'essential'],
            ['handle' => 'youtube', 'name' => 'YouTube', 'category' => 'external_media'],
        ]]);

        $this->assertSame(['session'], $this->registry()->requiredHandles());
        $this->assertTrue($this->registry()->granted('session'));
    }

    #[Test]
    public function no_cookie_means_no_consent(): void
    {
        config(['statamic-consent.services' => [
            ['handle' => 'youtube', 'name' => 'YouTube', 'category' => 'external_media'],
        ]]);

        $this->assertFalse($this->registry()->granted('youtube', new Request));
    }

    #[Test]
    public function it_reads_a_granted_service_from_the_cookie(): void
    {
        config(['statamic-consent.services' => [
            ['handle' => 'youtube', 'name' => 'YouTube', 'category' => 'external_media'],
        ]]);

        $this->assertTrue($this->registry()->granted('youtube', $this->requestWithConsent(['youtube'])));
    }

    #[Test]
    public function a_cookie_from_an_older_version_grants_nothing(): void
    {
        config([
            'statamic-consent.version' => 2,
            'statamic-consent.services' => [
                ['handle' => 'youtube', 'name' => 'YouTube', 'category' => 'external_media'],
            ],
        ]);

        // Written under version 1: the visitor never saw whatever version 2 added.
        $request = $this->requestWithConsent(['youtube'], version: 1);

        $this->assertFalse($this->registry()->granted('youtube', $request));
    }

    #[Test]
    public function a_malformed_cookie_grants_nothing(): void
    {
        config(['statamic-consent.services' => [
            ['handle' => 'youtube', 'name' => 'YouTube', 'category' => 'external_media'],
        ]]);

        $request = Request::create('/');
        $request->cookies->set('statamic_consent', 'not-json-at-all');

        $this->assertFalse($this->registry()->granted('youtube', $request));
    }

    #[Test]
    public function the_payload_carries_the_version_and_the_required_handles(): void
    {
        config(['statamic-consent.services' => [
            ['handle' => 'session', 'name' => 'Session', 'category' => 'essential'],
            ['handle' => 'youtube', 'name' => 'YouTube', 'category' => 'external_media'],
        ]]);

        $payload = $this->registry()->payload();

        $this->assertSame(config('statamic-consent.version'), $payload['version']);
        $this->assertSame(['session'], $payload['required']);
        $this->assertArrayHasKey('banner_title', $payload['texts']);
    }

    #[Test]
    public function a_text_left_empty_falls_back_to_the_shipped_translation(): void
    {
        $this->assertNotSame('', $this->registry()->texts()['banner_title']);
    }

    #[Test]
    public function a_shipped_service_without_a_description_gets_the_translated_one(): void
    {
        config(['statamic-consent.services' => [
            ['handle' => 'youtube', 'name' => 'YouTube', 'category' => 'external_media'],
        ]]);

        $service = $this->registry()->service('youtube');

        $this->assertSame('Embedded videos from YouTube.', $service['description']);
    }

    #[Test]
    public function a_description_set_on_the_site_beats_the_translated_default(): void
    {
        config(['statamic-consent.services' => [
            [
                'handle' => 'youtube',
                'name' => 'YouTube',
                'category' => 'external_media',
                'description' => 'Konzertmitschnitte.',
            ],
        ]]);

        $this->assertSame('Konzertmitschnitte.', $this->registry()->service('youtube')['description']);
    }

    #[Test]
    public function an_unknown_handle_without_wording_stays_empty_rather_than_printing_a_key(): void
    {
        config(['statamic-consent.services' => [
            ['handle' => 'some_pixel', 'name' => 'Pixel', 'category' => 'analytics'],
        ]]);

        $this->assertSame('', $this->registry()->service('some_pixel')['description']);
    }

    #[Test]
    public function a_shipped_category_is_named_in_the_visitors_language(): void
    {
        config(['statamic-consent.categories' => [['handle' => 'external_media']]]);
        config(['statamic-consent.services' => [
            ['handle' => 'youtube', 'name' => 'YouTube', 'category' => 'external_media'],
        ]]);

        $this->assertSame('External media', $this->registry()->categories()[0]['name']);

        $this->app->setLocale('de');

        $this->assertSame('Externe Medien', $this->registry()->categories()[0]['name']);
    }

    /**
     * @param  list<string>  $granted
     */
    protected function requestWithConsent(array $granted, ?int $version = null): Request
    {
        $request = Request::create('/');
        $request->cookies->set('statamic_consent', rawurlencode(json_encode([
            'v' => $version ?? config('statamic-consent.version'),
            'granted' => $granted,
            'ts' => 1755000000000,
        ])));

        return $request;
    }
}
