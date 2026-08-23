<?php

namespace Goldnead\StatamicConsent\Tags;

use Goldnead\StatamicConsent\Support\Registry;
use Illuminate\Contracts\View\Factory;
use Statamic\Tags\Tags;

class Consent extends Tags
{
    protected static $handle = 'consent';

    /**
     * Resolved per call rather than injected: Statamic instantiates tag classes
     * itself, so a constructor argument here never gets filled.
     */
    protected function registry(): Registry
    {
        return app(Registry::class);
    }

    /**
     * {{ consent:head }} — stylesheet and script, for the <head>.
     *
     * The payload is inlined rather than fetched, because a banner that appears
     * one request later than the page is a banner the visitor has already
     * scrolled past.
     */
    public function head(): string
    {
        $out = [];

        if (config('statamic-consent.assets.styles', true)) {
            $out[] = '<link rel="stylesheet" href="'.e($this->asset('consent.css')).'">';
        }

        if (config('statamic-consent.assets.scripts', true)) {
            $payload = json_encode(
                $this->registry()->payload(),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            );

            $out[] = '<script id="statamic-consent-config" type="application/json">'.$payload.'</script>';
            $out[] = '<script src="'.e($this->asset('consent.js')).'" defer></script>';
        }

        return implode("\n", $out);
    }

    /**
     * {{ consent:banner }} — the banner and the settings dialog.
     *
     * Rendered hidden and revealed by the script. Doing it the other way round
     * makes the banner flash on every page for visitors who decided months ago.
     */
    public function banner(): string
    {
        return $this->render('statamic-consent::banner', [
            'texts' => $this->registry()->texts(),
            'categories' => $this->registry()->categories(),
            'policy_label' => __('statamic-consent::messages.service_policy_label'),
            'privacy_url' => $this->registry()->privacyPolicyUrl(),
            'imprint_url' => $this->registry()->imprintUrl(),
        ]);
    }

    /**
     * {{ consent:gate service="youtube" }}<iframe …>{{ /consent:gate }}
     *
     * The two-click rule. The embed is parked in a <template>, which browsers
     * parse but do not load: no request reaches the third party until the
     * visitor allows it. Rendering the iframe and hiding it with CSS would look
     * identical and be exactly the violation this tag exists to prevent.
     */
    public function gate(): string
    {
        $handle = (string) $this->params->get('service', '');
        $service = $this->registry()->service($handle);

        // An unknown handle must not fail open. A typo would otherwise silently
        // publish an unconsented embed, which is the one outcome worth being
        // loud about — so it stays blocked and says why.
        if (! $service) {
            return $this->render('statamic-consent::gate-unknown', [
                'texts' => $this->registry()->texts(),
                'message' => __('statamic-consent::messages.unknown_service', ['handle' => $handle]),
            ]);
        }

        if (! $service['block_content']) {
            return (string) $this->parse();
        }

        return $this->render('statamic-consent::gate', [
            'service' => $service,
            'texts' => $this->registry()->texts(),
            'policy_label' => __('statamic-consent::messages.service_policy_label'),
            'title' => $this->params->get('title'),
            'cover' => $this->params->get('cover'),
            'embed' => (string) $this->parse(),
        ]);
    }

    /**
     * {{ consent:granted service="youtube" }}…{{ /consent:granted }}
     *
     * For markup that only makes sense with consent already given — a note, a
     * link, a fallback. Server-side, so it is wrong on a cached page; use the
     * gate for anything that loads a third party.
     */
    public function granted(): string
    {
        return $this->registry()->granted((string) $this->params->get('service', ''))
            ? (string) $this->parse()
            : '';
    }

    /**
     * {{ consent:settings_link }} — reopens the dialog.
     *
     * Belongs in the footer of every page: a decision that cannot be revisited
     * is not a decision that was freely given.
     */
    public function settingsLink(): string
    {
        $label = (string) ($this->params->get('label') ?: $this->registry()->texts()['settings_label']);

        return '<button type="button" data-consent-open class="'
            .e((string) $this->params->get('class', 'csnt-settings-link'))
            .'">'.e($label).'</button>';
    }

    /**
     * Views are rendered through the factory rather than the view() helper, so
     * a namespaced string stays a plain string instead of having to be lied
     * about as a view-string.
     *
     * @param  array<string, mixed>  $data
     */
    protected function render(string $view, array $data): string
    {
        /** @var Factory $factory */
        $factory = app('view');

        return (string) $factory->make($view, $data)->render();
    }

    /**
     * The published asset URL, cache-busted by file contents so a client's
     * browser does not keep an old banner after an update.
     */
    protected function asset(string $file): string
    {
        $path = public_path('vendor/statamic-consent/'.$file);
        $url = url('/vendor/statamic-consent/'.$file);

        return is_file($path) ? $url.'?v='.substr(md5_file($path) ?: '', 0, 8) : $url;
    }
}
