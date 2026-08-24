<?php

namespace Goldnead\StatamicConsent\Commands;

use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;

class InstallCommand extends Command
{
    use RunsInPlease;

    /**
     * The `statamic:` prefix is what groups it under artisan; `please` strips it,
     * so the command a site actually types is `php please consent:install`.
     */
    protected $signature = 'statamic:consent:install {--force : Overwrite published assets and blueprint}';

    protected $description = 'Publish the consent assets and create the editable global set';

    public function handle(): int
    {
        // Assets are always forced. They are build output, not something a site
        // edits, and a half-updated pair of consent.js/consent.css is worse than
        // either version on its own.
        $this->call('vendor:publish', [
            '--tag' => 'statamic-consent-assets',
            '--force' => true,
        ]);

        $this->publishBlueprint();

        $this->createGlobalSet();

        $this->newLine();
        $this->components->info('Consent installed.');
        $this->line('  Add <comment>{{ consent:head }}</comment> to your layout head, and');
        $this->line('  <comment>{{ consent:banner }}</comment> right before </body>.');

        return self::SUCCESS;
    }

    /**
     * Publishing the blueprint is a convenience, not the job.
     *
     * On a containerised Statamic the application directory belongs to root
     * while the process runs as www-data, so this throws — and a fatal here
     * would abandon the global set, which is the part that actually matters and
     * writes somewhere else entirely. Sites that keep the blueprint in their
     * repository are in exactly this position and are not doing anything wrong.
     */
    protected function publishBlueprint(): void
    {
        try {
            $this->call('vendor:publish', [
                '--tag' => 'statamic-consent-blueprint',
                '--force' => (bool) $this->option('force'),
            ]);
        } catch (\Throwable $e) {
            $this->newLine();
            $this->components->warn('Could not write resources/blueprints/globals/consent.yaml: '.$e->getMessage());
            $this->line('  Copy it from <comment>vendor/goldnead/statamic-consent/resources/blueprints/globals/consent.yaml</comment>');
            $this->line('  and commit it, or re-run this command where the directory is writable.');
        }
    }

    protected function createGlobalSet(): void
    {
        if (GlobalSet::findByHandle('consent')) {
            $this->components->twoColumnDetail('Global set <comment>consent</comment>', '<fg=yellow>exists, left alone</>');

            return;
        }

        $set = GlobalSet::make('consent')->title('Consent');

        // Statamic 6 saves the set and its localization separately; the set has
        // to exist on disk before a localization can be written against it.
        // (Statamic 5's addLocalization() is gone — this fails loudly on the
        // real thing and not at all in a package test.)
        $set->save();

        // Seeded from the config file, so the client opens the CP and finds the
        // services this site actually uses rather than an empty screen. Handles
        // match the config, which is what keeps existing {{ consent:gate }} tags
        // working after the switch to the global set.
        $variables = $set->makeLocalization(Site::default()->handle());

        $variables->data([
            'services' => collect(config('statamic-consent.services', []))
                ->map(fn (array $service): array => array_merge(['type' => 'service', 'enabled' => true], $service))
                ->all(),
            'categories' => collect(config('statamic-consent.categories', []))
                ->map(fn (array $category): array => array_merge(['type' => 'category', 'enabled' => true], $category))
                ->all(),
        ]);

        $variables->save();

        $this->components->twoColumnDetail('Global set <comment>consent</comment>', '<info>created</>');
    }
}
