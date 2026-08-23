<?php

namespace Goldnead\StatamicConsent\Tests\Feature;

use Goldnead\StatamicConsent\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Antlers;

/**
 * Renders the real markup to disk so it can be looked at in a browser. Skipped
 * unless CONSENT_DEMO_OUT is set, so it never runs in CI.
 */
class RenderDemoTest extends TestCase
{
    #[Test]
    public function it_writes_a_demo_page(): void
    {
        if (! $out = getenv('CONSENT_DEMO_OUT')) {
            $this->markTestSkipped('CONSENT_DEMO_OUT not set.');
        }

        $head = (string) Antlers::parse('{{ consent:head }}', [], true);
        $banner = (string) Antlers::parse('{{ consent:banner }}', [], true);
        $gate = (string) Antlers::parse(
            '{{ consent:gate service="youtube" }}<iframe width="100%" height="360" src="https://www.youtube.com/embed/dQw4w9WgXcQ" frameborder="0" allowfullscreen></iframe>{{ /consent:gate }}',
            [],
            true
        );
        $link = (string) Antlers::parse('{{ consent:settings_link }}', [], true);

        // The published asset URLs point at public/vendor; for the demo they are
        // rewritten to sit next to the file.
        $head = str_replace(url('/vendor/statamic-consent/'), './', $head);

        file_put_contents($out, <<<HTML
        <!doctype html>
        <html lang="de">
        <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Consent Demo</title>
        {$head}
        <style>
          body { font-family: system-ui, sans-serif; margin: 0; color: #16181d; background: #fff; }
          main { max-width: 46rem; margin: 0 auto; padding: 3rem 1.5rem 14rem; }
          h1 { font-size: 1.75rem; }
          footer { border-top: 1px solid #e2e5ea; margin-top: 3rem; padding-top: 1rem; font-size: 0.875rem; color: #5b6472; }
        </style>
        </head>
        <body>
        <main>
          <h1>Konzerte</h1>
          <p>Ein eingebettetes Video, das erst nach Einwilligung lädt:</p>
          {$gate}
          <footer>{$link}</footer>
        </main>
        {$banner}
        </body>
        </html>
        HTML);

        $this->assertFileExists($out);
    }
}
