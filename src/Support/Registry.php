<?php

namespace Goldnead\StatamicConsent\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;

/**
 * The one place that answers "what may load, and what does the visitor read".
 *
 * Values come from the config file and are overridden, key by key, by the
 * `consent` global set when it exists. That order matters: the config file is
 * the developer's contract (handles that templates refer to), the global set is
 * the client's wording. A client who empties a text field gets the shipped text
 * back, not an empty banner.
 */
class Registry
{
    /** @var array<string, mixed>|null */
    protected ?array $globals = null;

    /**
     * The categories a visitor sees, each with the services that belong to it.
     * Categories without a single service are dropped — an empty group in the
     * dialog reads as a bug.
     *
     * @return list<array<string, mixed>>
     */
    public function categories(): array
    {
        $services = collect($this->services());

        return collect($this->raw('categories'))
            ->map(fn (array $category): array => [
                'handle' => (string) Arr::get($category, 'handle'),
                'name' => $this->text($category, 'name', 'categories', (string) Arr::get($category, 'handle'))
                    ?: (string) Arr::get($category, 'handle'),
                'description' => $this->text($category, 'description', 'categories', (string) Arr::get($category, 'handle')),
                'required' => Arr::get($category, 'handle') === 'essential',
                'services' => $services
                    ->where('category', Arr::get($category, 'handle'))
                    ->values()
                    ->all(),
            ])
            ->reject(fn (array $category): bool => $category['handle'] === '' || $category['services'] === [])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function services(): array
    {
        return collect($this->raw('services'))
            ->map(function (array $service): array {
                $handle = (string) Arr::get($service, 'handle');
                $category = (string) Arr::get($service, 'category', 'essential');

                return [
                    'handle' => $handle,
                    'name' => $this->text($service, 'name', 'services', $handle) ?: $handle,
                    'description' => $this->text($service, 'description', 'services', $handle),
                    'category' => $category,
                    // Essential services are never optional, whatever the field
                    // says. A required analytics service is a contradiction the
                    // dialog cannot express, so the category decides.
                    'required' => $category === 'essential',
                    'policy_url' => $this->url(Arr::get($service, 'policy_url')),
                    'block_content' => (bool) Arr::get($service, 'block_content', false),
                    'block_message' => (string) Arr::get($service, 'block_message', ''),
                ];
            })
            ->reject(fn (array $service): bool => $service['handle'] === '')
            ->unique('handle')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function service(string $handle): ?array
    {
        return collect($this->services())->firstWhere('handle', $handle);
    }

    /**
     * Every handle that is on by default, i.e. everything essential. Used by
     * both the JS and the server-side read, so the two cannot drift.
     *
     * @return list<string>
     */
    public function requiredHandles(): array
    {
        return collect($this->services())
            ->where('required', true)
            ->pluck('handle')
            ->all();
    }

    /**
     * The visitor-facing wording. Falls back to the translation files, so an
     * installation that never opens the global set still reads correctly.
     *
     * @return array<string, string>
     */
    public function texts(): array
    {
        $keys = [
            'banner_title', 'banner_description', 'accept_all_label', 'only_necessary_label',
            'settings_label', 'privacy_policy_label', 'imprint_label',
            'modal_title', 'modal_description', 'save_label', 'reject_all_label',
            'close_label', 'blocked_title', 'blocked_message', 'blocked_button_label',
            'always_active_label',
        ];

        $texts = [];

        foreach ($keys as $key) {
            $value = $this->globals()[$key] ?? null;
            $texts[$key] = is_string($value) && trim($value) !== ''
                ? $value
                : (string) __('statamic-consent::messages.'.$key);
        }

        return $texts;
    }

    public function privacyPolicyUrl(): ?string
    {
        return $this->url($this->globals()['privacy_policy_url'] ?? null);
    }

    public function imprintUrl(): ?string
    {
        return $this->url($this->globals()['imprint_url'] ?? null);
    }

    /**
     * Everything the browser needs, in one object. The version travels with it:
     * the script compares it against the stored decision and re-asks when they
     * differ, which is what makes a new service actually get consented to
     * instead of silently inheriting an old yes.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'version' => (int) config('statamic-consent.version', 1),
            'cookie' => [
                'name' => (string) config('statamic-consent.cookie.name', 'statamic_consent'),
                'days' => (int) config('statamic-consent.cookie.days', 182),
                'sameSite' => (string) config('statamic-consent.cookie.same_site', 'Lax'),
                'secure' => request()->isSecure(),
            ],
            'rejectOnDismiss' => (bool) config('statamic-consent.reject_on_dismiss', true),
            'respectGpc' => (bool) config('statamic-consent.respect_gpc', true),
            'required' => $this->requiredHandles(),
            'categories' => $this->categories(),
            'texts' => $this->texts(),
            'links' => [
                'privacy' => $this->privacyPolicyUrl(),
                'imprint' => $this->imprintUrl(),
            ],
            'googleConsentMode' => $this->googleConsentMode(),
        ];
    }

    /**
     * The Google Consent Mode v2 mapping, or null when it is switched off.
     *
     * Signals with no services behind them are kept rather than dropped: Google
     * reads a missing signal as "not answered", and an unanswered signal is not
     * the same statement as a denied one.
     *
     * @return array<string, mixed>|null
     */
    public function googleConsentMode(): ?array
    {
        if (! config('statamic-consent.google_consent_mode.enabled', false)) {
            return null;
        }

        $signals = collect(config('statamic-consent.google_consent_mode.signals', []))
            ->map(fn ($handles): array => collect($handles)
                ->filter(fn ($handle): bool => is_string($handle) && $handle !== '')
                ->values()
                ->all())
            ->all();

        return [
            'signals' => $signals,
            'waitForUpdate' => (int) config('statamic-consent.google_consent_mode.wait_for_update', 500),
        ];
    }

    /**
     * The server's reading of the cookie. Deliberately conservative: an absent,
     * malformed or outdated cookie is a "no" for everything optional. The gate
     * tag uses this only to skip work — the browser stays the authority, because
     * a page may be served from a cache that never saw this visitor's cookie.
     */
    public function granted(string $handle, ?Request $request = null): bool
    {
        if (in_array($handle, $this->requiredHandles(), true)) {
            return true;
        }

        return in_array($handle, $this->decision($request), true);
    }

    /**
     * @return list<string>
     */
    public function decision(?Request $request = null): array
    {
        $request ??= request();
        $raw = $request->cookie((string) config('statamic-consent.cookie.name', 'statamic_consent'));

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode(rawurldecode($raw), true);

        if (! is_array($decoded)) {
            return [];
        }

        if ((int) ($decoded['v'] ?? 0) !== (int) config('statamic-consent.version', 1)) {
            return [];
        }

        return collect($decoded['granted'] ?? [])
            ->filter(fn ($handle): bool => is_string($handle))
            ->values()
            ->all();
    }

    /**
     * A field the site filled in wins. Left empty, the shipped wording for the
     * handles this addon knows steps in — in the visitor's language, which a
     * value hard-coded in the config file could never be.
     *
     * @param  array<string, mixed>  $row
     */
    protected function text(array $row, string $field, string $group, string $handle): string
    {
        $own = Arr::get($row, $field);

        if (is_string($own) && trim($own) !== '') {
            return $own;
        }

        $key = 'statamic-consent::messages.'.$group.'.'.$handle.'.'.$field;
        $translated = __($key);

        return is_string($translated) && $translated !== $key ? $translated : '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function raw(string $key): array
    {
        $fromGlobals = $this->globals()[$key] ?? null;

        $source = is_array($fromGlobals) && $fromGlobals !== []
            ? $fromGlobals
            : config('statamic-consent.'.$key, []);

        return collect($source)
            ->filter(fn ($row): bool => is_array($row))
            // Replicator rows carry a "type" key that is set structure, not data.
            ->map(fn (array $row): array => Arr::except($row, ['type', 'id', 'enabled']))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function globals(): array
    {
        if ($this->globals !== null) {
            return $this->globals;
        }

        $set = GlobalSet::findByHandle('consent');

        if (! $set) {
            return $this->globals = [];
        }

        $localized = $set->in(Site::current()->handle()) ?? $set->inDefaultSite();

        return $this->globals = $localized ? $localized->data()->all() : [];
    }

    /**
     * Statamic's link fieldtype yields an entry ID for internal targets. A raw
     * ID printed into an href is a dead link, so it is resolved here.
     */
    protected function url(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        if (str_starts_with($value, 'entry::')) {
            // The facade is typed against the contract, which does not declare
            // url() — only the default implementation does. The annotation says
            // what is actually there rather than adding a baseline entry.
            /** @var \Statamic\Entries\Entry|null $entry */
            $entry = Entry::find(substr($value, 7));

            return $entry?->url();
        }

        return $value;
    }
}
