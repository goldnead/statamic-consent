<?php

namespace Goldnead\StatamicConsent\Tests\Feature;

use Goldnead\StatamicConsent\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;

class InstallCommandTest extends TestCase
{
    #[Test]
    public function it_creates_the_global_set_seeded_from_the_config(): void
    {
        $this->assertNull(GlobalSet::findByHandle('consent'));

        Artisan::call('statamic:consent:install');

        $set = GlobalSet::findByHandle('consent');
        $this->assertNotNull($set);

        $data = $set->inDefaultSite()->data()->all();
        $handles = collect($data['services'])->pluck('handle')->all();

        $this->assertContains('youtube', $handles);
        $this->assertContains('google_maps', $handles);
    }

    #[Test]
    public function it_leaves_an_existing_global_set_alone(): void
    {
        $set = GlobalSet::make('consent')->title('Consent');
        $set->save();
        $variables = $set->makeLocalization($this->defaultSite());
        $variables->data(['banner_title' => 'Schon da']);
        $variables->save();

        Artisan::call('statamic:consent:install');

        // Re-running the installer after an update must not overwrite what the
        // client wrote. It is the documented way to refresh the assets.
        $this->assertSame(
            'Schon da',
            GlobalSet::findByHandle('consent')->inDefaultSite()->data()->get('banner_title')
        );
    }

    #[Test]
    public function a_blueprint_it_cannot_write_does_not_abandon_the_global_set(): void
    {
        // The containerised case: the application directory belongs to root and
        // the process runs as www-data. A fatal here would skip the global set,
        // which writes somewhere else entirely and is the part that matters.
        $path = resource_path('blueprints/globals');
        @mkdir(dirname($path), 0555, true);
        @mkdir($path, 0555, true);
        @chmod($path, 0555);

        $exit = Artisan::call('statamic:consent:install');

        $this->assertSame(0, $exit);
        $this->assertNotNull(GlobalSet::findByHandle('consent'));

        @chmod($path, 0755);
    }

    protected function defaultSite(): string
    {
        return Site::default()->handle();
    }
}
