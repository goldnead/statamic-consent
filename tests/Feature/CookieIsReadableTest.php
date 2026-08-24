<?php

namespace Goldnead\StatamicConsent\Tests\Feature;

use Goldnead\StatamicConsent\ServiceProvider;
use Goldnead\StatamicConsent\Support\Registry;
use Goldnead\StatamicConsent\Tests\TestCase;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\Test;

/**
 * The cookie is written by JavaScript and is therefore not encrypted.
 *
 * Laravel's EncryptCookies middleware discards anything it cannot decrypt, so
 * without an exemption the server sees no cookie at all — and the failure looks
 * exactly like "nobody has consented yet", which is why it survived a green
 * suite, a playground and a production install.
 */
class CookieIsReadableTest extends TestCase
{
    protected function middleware(): EncryptCookies
    {
        return new EncryptCookies($this->app->make('encrypter'));
    }

    #[Test]
    public function the_consent_cookie_is_exempt_from_encryption(): void
    {
        $this->assertTrue($this->middleware()->isDisabled('statamic_consent'));
    }

    #[Test]
    public function a_renamed_cookie_is_exempt_too(): void
    {
        config(['statamic-consent.cookie.name' => 'zustimmung']);

        (new ServiceProvider($this->app))->bootAddon();

        $this->assertTrue($this->middleware()->isDisabled('zustimmung'));
    }

    #[Test]
    public function an_unencrypted_cookie_survives_the_middleware(): void
    {
        config(['statamic-consent.services' => [
            ['handle' => 'youtube', 'name' => 'YouTube', 'category' => 'external_media'],
        ]]);

        $request = Request::create('/');
        $request->cookies->set('statamic_consent', rawurlencode(json_encode([
            'v' => config('statamic-consent.version'),
            'granted' => ['youtube'],
            'ts' => 1755000000000,
        ])));

        $seen = null;

        // Run the real middleware over the request, the way a page load does.
        $this->middleware()->handle($request, function (Request $passed) use (&$seen): \Symfony\Component\HttpFoundation\Response {
            $seen = $passed->cookie('statamic_consent');

            return new Response('ok');
        });

        $this->assertNotNull($seen, 'EncryptCookies discarded the consent cookie.');

        $registry = $this->app->make(Registry::class);

        $this->assertTrue($registry->granted('youtube', $request));
    }
}
