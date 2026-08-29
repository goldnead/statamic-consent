<?php

namespace Goldnead\StatamicConsent\Integrations\Insights;

use Goldnead\StatamicInsights\Support\TableMetric;

/**
 * What every consent figure here has in common.
 *
 * The analytics addon owns the time range, the comparison, the chart and the
 * screen; this addon owns the question and the query. Nothing below names a
 * table belonging to the sibling, and the sibling names none of these — which
 * is the rule its contract exists to enforce.
 *
 * **Nothing here counts people.** `consent_id` is a random token in the
 * visitor's own cookie, not an identifier of a human being: a browser that
 * clears cookies produces a fresh one on every visit, a household sharing one
 * computer shares one, and a person on a phone and a laptop is two. So
 * `count(distinct consent_id)` is not a number of users, not a number of
 * visitors, and not a number of anything a person would recognise — it is a
 * count of cookies that happen to be alive. Do not add it later under a label
 * like "Nutzer" or "Besucher". This table records **decisions**, and decisions
 * are what is counted here.
 *
 * That restraint is the point of the table rather than a limitation of it: the
 * proof log deliberately holds no IP address and no user agent, so that the
 * evidence a consent banner has to produce does not itself become a second
 * tracking database. A metric that reconstructed a headcount from it would give
 * back exactly what the design refused to collect.
 *
 * **The timestamp is `decided_at`, and it is the browser's own clock.** The
 * recorder believes it within a bounded window and falls back to the server's
 * time when the device claims a date more than a day ahead or more than five
 * years back (`Recorder::decidedAt()`). It is the right column all the same: a
 * decision happened when the visitor clicked, and `created_at` is when the
 * server heard about it — which can be a page load later, or after an offline
 * spell, and in a different bucket. A chart of when people decided must be
 * drawn on when they decided; the small drift a wrong device clock adds is the
 * honest price of that, and it is bounded rather than pretended away.
 *
 * **Availability follows the proof log.** The migrations load only when
 * `statamic-consent.record.enabled` is on, so an installation that never
 * switched the log on has no table, `available()` is false and the metric is
 * left out of every screen. That is the correct answer and not a zero: "this
 * site keeps no record" and "nobody has decided anything" are different
 * statements, and a confident 0 for the first is the quiet kind of wrong.
 */
abstract class ConsentMetric extends TableMetric
{
    protected function table(): string
    {
        return 'consent_records';
    }

    /**
     * When the visitor decided, not when the row was written.
     *
     * See the class docblock: it is the browser's clock, bounded by the
     * recorder, and it is the only column that answers the question a chart of
     * decisions is asking.
     */
    protected function timestamp(): string
    {
        return 'decided_at';
    }

    public function group(): string
    {
        return __('statamic-consent::messages.metric_group');
    }

    /**
     * The words for a row that has no value in the dimension it is split by.
     *
     * Per dimension, because "no site" and "no version" read differently and a
     * shared dash tells a reader nothing. A row without a value is still a row:
     * dropping it would make the split disagree with the total, and nothing on
     * the screen would say why.
     */
    protected function missingLabel(string $dimension): string
    {
        return __('statamic-consent::messages.metric_no_'.$dimension);
    }
}
