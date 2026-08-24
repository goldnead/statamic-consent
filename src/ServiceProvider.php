<?php

namespace Goldnead\StatamicConsent;

use Goldnead\StatamicConsent\Support\Registry;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $viewNamespace = 'statamic-consent';

    protected $routes = [
        'web' => __DIR__.'/../routes/web.php',
    ];

    /**
     * The parent boots config off the addon directory, which is resolved through
     * the manifest and comes up empty in package test suites. Config is merged
     * explicitly in register() with an absolute path instead.
     */
    protected $config = false;

    public function register()
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__.'/../config/statamic-consent.php', 'statamic-consent');

        // One registry per request. The banner, every gate on the page and the
        // payload in the head must agree about which services exist; separate
        // instances would each re-read the global set and could disagree mid-render.
        $this->app->scoped(Registry::class);
    }

    public function bootAddon()
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'statamic-consent');

        // The consent cookie is written by JavaScript and is therefore not
        // encrypted. Laravel's EncryptCookies middleware discards anything it
        // cannot decrypt, so without this the server sees no cookie at all and
        // {{ consent:granted }} is false for everyone, forever — a failure that
        // looks exactly like "nobody has consented yet".
        EncryptCookies::except([
            (string) config('statamic-consent.cookie.name', 'statamic_consent'),
        ]);

        // Only when the proof log is switched on. Loading them unconditionally
        // would force a table on every flat installation that never asked for
        // one. Same reasoning leadhub uses for its eloquent driver.
        if (config('statamic-consent.record.enabled', false)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'statamic-consent-migrations');

        $this->publishes([
            __DIR__.'/../config/statamic-consent.php' => config_path('statamic-consent.php'),
        ], 'statamic-consent-config');

        // Assets go to public/, which is what the {{ consent:head }} tag points
        // at. Marked as a "force" target in the install command, because a stale
        // copy here is a banner that behaves like the previous release.
        $this->publishes([
            __DIR__.'/../resources/dist' => public_path('vendor/statamic-consent'),
        ], 'statamic-consent-assets');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/statamic-consent'),
        ], 'statamic-consent-views');

        $this->publishes([
            __DIR__.'/../resources/blueprints/globals/consent.yaml' => resource_path('blueprints/globals/consent.yaml'),
        ], 'statamic-consent-blueprint');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/statamic-consent'),
        ], 'statamic-consent-translations');
    }
}
