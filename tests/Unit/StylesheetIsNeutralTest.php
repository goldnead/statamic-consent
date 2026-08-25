<?php

namespace Goldnead\StatamicConsent\Tests\Unit;

use Goldnead\StatamicConsent\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The shipped stylesheet must not carry any one site's identity.
 *
 * Until 1.5.0 it did: the defaults were adriangoldner.com's yellow, cream, 40px
 * corners, pill buttons and monospace capitals, so every installation wore
 * another company's brand until somebody overrode it. Nothing in the suite
 * noticed, because a stylesheet is not code that runs. These tests are the
 * thing that notices.
 */
class StylesheetIsNeutralTest extends TestCase
{
    protected function css(): string
    {
        return file_get_contents(__DIR__.'/../../resources/dist/consent.css');
    }

    #[Test]
    public function no_palette_of_one_particular_site_survives(): void
    {
        foreach (['#E8B931', '#F5D04A', '#A67D14', '#FAF8F4', '#F3F0EA', '#141210', 'JetBrains'] as $spur) {
            $this->assertStringNotContainsStringIgnoringCase(
                $spur,
                $this->css(),
                "The stylesheet still carries {$spur}, which belongs to one specific website."
            );
        }
    }

    #[Test]
    public function the_three_type_roles_are_inherited_rather_than_imposed(): void
    {
        foreach (['--csnt-font', '--csnt-font-display', '--csnt-font-ui'] as $token) {
            $this->assertMatchesRegularExpression(
                '/'.preg_quote($token, '/').':\s*inherit;/',
                $this->css(),
                "{$token} should inherit from the host site, not name a face."
            );
        }
    }

    #[Test]
    public function every_look_is_reachable_without_important(): void
    {
        // A shape and a label style are as much a handwriting as a colour is.
        // These used to be literals in the rules, so a host could only get at
        // them with !important.
        foreach ([
            '--csnt-radius-button', '--csnt-radius-pill', '--csnt-btn-size', '--csnt-btn-weight',
            '--csnt-btn-tracking', '--csnt-btn-transform', '--csnt-label-tracking',
            '--csnt-label-transform', '--csnt-title-weight', '--csnt-title-tracking',
            '--csnt-shadow-knob',
        ] as $token) {
            $this->assertStringContainsString("{$token}:", $this->css(), "{$token} is not defined.");
            $this->assertGreaterThan(
                1,
                substr_count($this->css(), $token),
                "{$token} is defined but never used, which promises the host something that does nothing."
            );
        }
    }

    #[Test]
    public function the_token_blocks_are_layered_so_the_host_outranks_dark_mode(): void
    {
        // Unlayered CSS beats every layer whatever the specificity. Without the
        // layer, :root[data-consent-theme="dark"] (0,1,1) outranks a host's
        // plain :root (0,1,0) and deletes their brand the moment dark mode is
        // switched on: visible in light, gone in dark.
        $this->assertStringContainsString('@layer csnt-theme {', $this->css());

        $layer = substr($this->css(), strpos($this->css(), '@layer csnt-theme {'));
        $this->assertStringContainsString('[data-consent-theme="dark"]', $layer);
        $this->assertStringContainsString('prefers-color-scheme: dark', $layer);
    }

    #[Test]
    public function the_readme_quotes_the_values_the_stylesheet_actually_ships(): void
    {
        // A wrong default in the docs is worse than none: somebody copies the
        // "full set" to change two tokens and silently moves the rest.
        $readme = file_get_contents(__DIR__.'/../../README.md');
        preg_match('/^@layer csnt-theme \{\n  :root \{\n(.*?)^  \}$/ms', $this->css(), $treffer);
        preg_match_all('/--csnt-([\w-]+):\s*([^;]+);/', $treffer[1], $tokens, PREG_SET_ORDER);

        $this->assertGreaterThan(25, count($tokens), 'The token block was not found.');

        foreach ($tokens as [, $name, $wert]) {
            $this->assertMatchesRegularExpression(
                '/--csnt-'.preg_quote($name, '/').':\s*'.preg_quote(trim($wert), '/').';/',
                $readme,
                "README does not document --csnt-{$name} with its shipped default ".trim($wert).'.'
            );
        }
    }

    #[Test]
    public function nothing_shouts_over_the_host_with_important(): void
    {
        // [hidden] is the one legitimate case: it is mechanics, not a look.
        // Match whole rules, not lines — the selector and the declaration are
        // rarely on the same line.
        preg_match_all('/([^{}]*)\{([^{}]*!important[^{}]*)\}/', $this->css(), $regeln, PREG_SET_ORDER);

        $this->assertNotEmpty($regeln, 'Expected the one [hidden] rule to exist.');

        foreach ($regeln as [, $selektor, $block]) {
            $this->assertStringContainsString(
                '[hidden]',
                $selektor,
                'Unexpected !important in: '.trim($selektor).' {'.trim($block).' }'
            );
        }
    }
}
