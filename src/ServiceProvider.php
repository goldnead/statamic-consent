<?php

namespace Goldnead\StatamicConsent;

use Goldnead\StatamicConsent\Integrations\Insights\Decisions;
use Goldnead\StatamicConsent\Support\Registry;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Support\Facades\Log;
use Statamic\Providers\AddonServiceProvider;
use Throwable;

class ServiceProvider extends AddonServiceProvider
{
    /**
     * The metric handles this addon contributes, and the classes behind them.
     *
     * Handle and class both, so the registry can store the class name without
     * constructing anything to find out what it is called. An install with
     * twenty addons would otherwise build every metric object of every one of
     * them on a request that renders none.
     *
     * The handles are frozen from the moment they are registered — they end up
     * in saved dashboards and in URLs. Renaming one is a breaking change.
     *
     * @var array<class-string, string>
     */
    protected const INSIGHTS_METRICS = [
        Decisions::class => 'consent.decisions',
    ];

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

        $this->registerInsightsMetrics();

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

    /**
     * Offer the consent figures to the analytics addon, if it is there.
     *
     * From an `app->booted()` callback rather than straight from `bootAddon()`:
     * the sibling's container bindings only exist once its own provider has
     * booted, and this one may boot first. Registering earlier registers into
     * nothing, silently — an empty screen with no error anywhere, which is the
     * worst shape this failure could take.
     *
     * **Nothing here throws, ever.** A missing, half-installed or mid-upgrade
     * analytics addon must cost a tile on a screen nobody has open, never a
     * page load on the site that shows the banner. The guards are three, and
     * each one has caught a real variation of "installed but not quite": the
     * class may be absent, the container may refuse to build the manager, and
     * an older release of the sibling may have the facade without this method
     * on it.
     *
     * The metric classes name the sibling's contract in their `extends` and
     * their type hints, which is safe precisely because of the first guard: PHP
     * loads a class when something touches it, and nothing touches these unless
     * the facade exists. Hence `suggest` in composer.json rather than `require`
     * — installing a cookie banner must not drag an analytics package in.
     */
    protected function registerInsightsMetrics(): void
    {
        $this->app->booted(function (): void {
            $facade = '\Goldnead\StatamicInsights\Facades\Insights';

            if (! class_exists($facade)) {
                return;
            }

            try {
                $manager = $facade::getFacadeRoot();

                // Asked of the object, never of the facade: a facade forwards
                // through `__callStatic` and declares none of what it forwards,
                // so the probe on the facade itself is always false.
                if (! is_object($manager) || ! method_exists($manager, 'registerMetric')) {
                    return;
                }

                foreach (self::INSIGHTS_METRICS as $class => $handle) {
                    $manager->registerMetric($class, $handle);
                }
            } catch (Throwable $e) {
                Log::warning('statamic-consent: the insights metrics could not be registered.', [
                    'exception' => $e->getMessage(),
                ]);
            }
        });
    }
}
