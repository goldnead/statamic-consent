<?php

namespace Goldnead\StatamicConsent;

use Goldnead\StatamicConsent\Console\InstallCommand;
use Goldnead\StatamicConsent\Support\Registry;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $viewNamespace = 'statamic-consent';

    /**
     * The parent boots config off the addon directory, which is resolved through
     * the manifest and comes up empty in package test suites. Config is merged
     * explicitly in register() with an absolute path instead.
     */
    protected $config = false;

    protected $commands = [
        InstallCommand::class,
    ];

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
