<?php

namespace Goldnead\StatamicConsent\Records;

use Goldnead\StatamicConsent\Support\Registry;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Statamic\Facades\Site;

/**
 * Writes the proof, and decides what counts as proof.
 *
 * The rule that shapes everything here: **it records what the cookie says, not
 * what the request body says.** The browser sends no payload at all — it only
 * pings. Anything a page could put in a body, a stranger could put there too.
 */
class Recorder
{
    public function __construct(protected Registry $registry) {}

    public function enabled(): bool
    {
        return (bool) config('statamic-consent.record.enabled', false);
    }

    /**
     * Returns the stored record, or null when there was nothing to store.
     *
     * Null is the ordinary answer, not an error: a request without a valid
     * cookie is what a cross-site post looks like, because SameSite=Lax does not
     * send the cookie on one. That is the whole CSRF defence, and it is why this
     * endpoint needs no token.
     */
    public function record(Request $request): ?ConsentRecord
    {
        if (! $this->enabled()) {
            return null;
        }

        $decision = $this->registry->rawDecision($request);

        if ($decision === null) {
            return null;
        }

        $id = $decision['id'] ?? null;

        if (! is_string($id) || trim($id) === '' || strlen($id) > 64) {
            return null;
        }

        // Only handles this site actually offers. A cookie is under the
        // visitor's control, so an unknown handle is either a stale decision or
        // someone editing it — either way it is not evidence of anything.
        $known = collect($this->registry->services())->pluck('handle')->all();

        $granted = collect($decision['granted'] ?? [])
            ->filter(fn ($handle): bool => is_string($handle) && in_array($handle, $known, true))
            ->values()
            ->all();

        return ConsentRecord::firstOrCreate(
            [
                'consent_id' => $id,
                'decided_at' => $this->decidedAt($decision),
            ],
            [
                'version' => (int) ($decision['v'] ?? 0),
                'granted' => $granted,
                'how' => $this->how($decision),
                'site' => Site::current()->handle(),
            ]
        );
    }

    /**
     * The browser's clock, believed but bounded.
     *
     * A device with a wrong clock would otherwise write a record dated 1970 or
     * 2049, and a proof log with impossible dates in it is worth less than one
     * that admits it fell back to the server's time.
     */
    protected function decidedAt(array $decision): Carbon
    {
        $ts = $decision['ts'] ?? null;

        if (! is_numeric($ts)) {
            return now();
        }

        $claimed = Carbon::createFromTimestampMs((int) $ts);

        if ($claimed->isAfter(now()->addDay()) || $claimed->isBefore(now()->subYears(5))) {
            return now();
        }

        return $claimed;
    }

    protected function how(array $decision): string
    {
        $how = $decision['how'] ?? '';

        $known = ['accept_all', 'necessary_only', 'reject_all', 'custom', 'gate', 'gpc'];

        return is_string($how) && in_array($how, $known, true) ? $how : 'unknown';
    }

    public function prune(): int
    {
        $days = config('statamic-consent.record.keep_days');

        if (! is_numeric($days)) {
            return 0;
        }

        return ConsentRecord::query()->olderThan(now()->subDays((int) $days))->delete();
    }
}
