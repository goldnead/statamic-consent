<?php

namespace Goldnead\StatamicConsent\Tests\Feature;

use Goldnead\StatamicConsent\Support\Registry;
use Goldnead\StatamicConsent\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Closing the banner without deciding is not consent, and that is not a setting.
 *
 * There used to be a `reject_on_dismiss` key. It was handed to the browser on
 * every page and the browser never read it: the behaviour was hard-wired to the
 * strict reading all along. Harmless in effect — the strict reading is the
 * correct one — but the config file and the README both promised a switch, and
 * a site that set it to `false` believed it had changed something.
 *
 * These tests pin the behaviour that actually exists, so nobody re-adds a knob
 * for it.
 */
class DismissIsNotConsentTest extends TestCase
{
    #[Test]
    public function the_setting_that_never_applied_is_gone(): void
    {
        $this->assertNull(
            config('statamic-consent.reject_on_dismiss'),
            'A config key that steers nothing is a promise the addon does not keep.'
        );
    }

    #[Test]
    public function the_browser_is_not_handed_a_flag_it_ignores(): void
    {
        $ausgeliefert = app(Registry::class)
            ->payload();

        $this->assertArrayNotHasKey('rejectOnDismiss', $ausgeliefert);

        // The one signal the script really does act on stays.
        $this->assertArrayHasKey('respectGpc', $ausgeliefert);
    }

    #[Test]
    public function closing_the_dialog_without_deciding_is_still_wired_to_bring_the_banner_back(): void
    {
        // The rule lives in the shipped script. Asserting on the source is
        // crude, but it is the only place this behaviour exists, and the
        // alternative — trusting a comment — is what let the dead setting
        // survive.
        $js = file_get_contents(__DIR__.'/../../resources/dist/consent.js');

        $this->assertMatchesRegularExpression(
            '/function closeModal\(\).*?if \(!state\).*?showBanner\(\)/s',
            $js,
            'Closing without a stored decision must show the banner again.'
        );
    }
}
