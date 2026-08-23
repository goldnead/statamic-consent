<?php

namespace Goldnead\StatamicConsent\Tests;

use Goldnead\StatamicConsent\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    /**
     * The suite runs on the configuration that ships. A test case that switches
     * options on for everything hides exactly the failure that only a default
     * installation sees — which, for an addon installed once per client site, is
     * every installation.
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('statamic.system.multisite', false);
    }
}
