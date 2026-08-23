<?php

namespace Goldnead\StatamicConsent\Tests\Feature;

use Goldnead\StatamicConsent\Support\Registry;
use Goldnead\StatamicConsent\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;

/**
 * The wording is per site, the handles are not.
 *
 * A single-site suite proves nothing about this path: the registry reads the
 * global set's localisation for the *current* site, and a site that has no
 * localisation of its own must fall back to the default one rather than render
 * an empty banner.
 */
class MultisiteTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('statamic.system.multisite', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Site::setSites([
            'en' => ['name' => 'English', 'locale' => 'en_US', 'url' => 'http://localhost/'],
            'de' => ['name' => 'Deutsch', 'locale' => 'de_DE', 'url' => 'http://localhost/de/'],
        ]);

        config(['statamic-consent.services' => [
            ['handle' => 'youtube', 'name' => 'YouTube', 'category' => 'external_media'],
        ]]);
    }

    protected function seedGlobalSet(): void
    {
        // A global set only has the sites it declares; without this the German
        // localisation is written but never read, which is also what a
        // single-site install looks like from the registry's side.
        $set = GlobalSet::make('consent')->title('Consent')->sites(['en', 'de']);
        $set->save();

        $en = $set->makeLocalization('en');
        $en->data(['banner_title' => 'Cookies here']);
        $en->save();

        $de = $set->makeLocalization('de');
        $de->data(['banner_title' => 'Kekse hier']);
        $de->save();
    }

    #[Test]
    public function each_site_reads_its_own_wording(): void
    {
        $this->seedGlobalSet();

        Site::setCurrent('en');
        $this->assertSame('Cookies here', $this->app->make(Registry::class)->texts()['banner_title']);

        // A fresh registry per site: it caches the global set for one request,
        // which is the point — within a request the site does not change.
        Site::setCurrent('de');
        $this->assertSame('Kekse hier', (new Registry)->texts()['banner_title']);
    }

    #[Test]
    public function a_site_without_its_own_localisation_falls_back_to_the_default(): void
    {
        $set = GlobalSet::make('consent')->title('Consent');
        $set->save();

        $en = $set->makeLocalization('en');
        $en->data(['banner_title' => 'Cookies here']);
        $en->save();

        Site::setCurrent('de');

        $this->assertSame('Cookies here', (new Registry)->texts()['banner_title']);
    }

    #[Test]
    public function the_service_handles_are_the_same_on_every_site(): void
    {
        $this->seedGlobalSet();

        Site::setCurrent('en');
        $en = collect((new Registry)->services())->pluck('handle')->all();

        Site::setCurrent('de');
        $de = collect((new Registry)->services())->pluck('handle')->all();

        // If these diverged, a {{ consent:gate service="youtube" }} in a shared
        // layout would block on one site and fall through on the other.
        $this->assertSame($en, $de);
    }
}
