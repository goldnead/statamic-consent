<?php

namespace Goldnead\StatamicConsent\Integrations\Insights;

use Goldnead\StatamicInsights\Contracts\HasBreakdowns;
use Goldnead\StatamicInsights\Support\MetricQuery;
use Goldnead\StatamicInsights\Support\Unit;

/**
 * How many consent decisions were recorded.
 *
 * Decisions, not people — see {@see ConsentMetric} for why that distinction is
 * load-bearing here and not pedantry.
 *
 * **There is deliberately no consent rate beside this number, and the reason is
 * that the data cannot carry one honestly.** Three findings, each checked
 * against the code rather than assumed:
 *
 * 1. **`granted` is not a list of what was consented to.** The browser writes
 *    `unique(required.concat(granted))`, and `required` is every handle in the
 *    `essential` category (`Registry::requiredHandles()`). So the essential
 *    services are in the list on *every* record, including one where the
 *    visitor rejected everything. Telling consent from refusal therefore means
 *    subtracting the handles that count as essential — a classification that
 *    lives in `config('statamic-consent.services')` as it stands **today**,
 *    while each record carries the `version` it was decided under. Judging a
 *    decision made under version 3 by version 5's categories is a guess, and a
 *    site that recategorised or renamed one service would silently restate its
 *    own history.
 *
 * 2. **`how = 'gate'` is not a decision about the set.** It records one embed
 *    being unblocked where it stood. Counting those alongside `accept_all`
 *    would inflate any "accepted something" rate with visitors who allowed a
 *    single video and nothing else.
 *
 * 3. **A JSON aggregate is not portable.** MySQL spells it `JSON_LENGTH`,
 *    Postgres and SQLite `json_array_length`. `TableMetric` deliberately keeps
 *    every dialect difference in the one switch it needs
 *    (`bucketExpression()`); a rate over this column would demand a second one,
 *    inside a metric whose whole job is counting rows.
 *
 * What answers the same question without guessing is the split by `how`:
 * `accept_all`, `necessary_only`, `reject_all`, `custom`, `gate`, `gpc`. Those
 * are the visitor's own words for what they did, written down at the moment
 * they did it, and their proportions are exactly what somebody asking for a
 * "consent rate" wants to see. A guessed rate would be worse than none: it
 * would be believed, printed in a report, and disagree with the split sitting
 * directly beneath it.
 */
class Decisions extends ConsentMetric implements HasBreakdowns
{
    /**
     * The splits on offer, and the columns behind them.
     *
     * A fixed map rather than the dimension itself: the column name reaches raw
     * SQL in `splitByColumn()`, and a dimension that arrives from a URL must
     * never be able to name a column of its own choosing.
     *
     * @var array<string, string>
     */
    protected const DIMENSIONS = [
        'version' => 'version',
        'how' => 'how',
        'site' => 'site',
    ];

    public function handle(): string
    {
        return 'consent.decisions';
    }

    public function label(): string
    {
        return __('statamic-consent::messages.metric_decisions');
    }

    public function description(): ?string
    {
        return __('statamic-consent::messages.metric_decisions_description');
    }

    public function unit(): string
    {
        return Unit::COUNT;
    }

    public function value(MetricQuery $query): int|float|null
    {
        if (! $this->available()) {
            return null;
        }

        return (int) $this->inPeriod($query)->count();
    }

    public function series(MetricQuery $query): array
    {
        if (! $this->available()) {
            return [];
        }

        return array_map(
            fn ($measured) => (int) $measured,
            $this->bucketed($this->inPeriod($query), $query, 'count(*)'),
        );
    }

    public function breakdowns(): array
    {
        return [
            'version' => __('statamic-consent::messages.metric_breakdown_version'),
            'how' => __('statamic-consent::messages.metric_breakdown_how'),
            'site' => __('statamic-consent::messages.metric_breakdown_site'),
        ];
    }

    public function breakdown(MetricQuery $query, string $dimension, int $limit = 20): array
    {
        $column = self::DIMENSIONS[$dimension] ?? null;

        if ($column === null || ! $this->available()) {
            return [];
        }

        $rows = $this->splitByColumn($this->inPeriod($query), $query, $column, 'count(*)', $limit);

        return array_map(fn (array $row) => [
            'key' => $row['key'],
            'label' => $row['key'] === null
                ? $this->missingLabel($dimension)
                : $this->labelFor($dimension, $row['key']),
            'value' => (int) $row['value'],
        ], $rows);
    }

    /**
     * What to print for a value that is there.
     *
     * `how` is a small closed vocabulary and reads as jargon unless it is
     * translated. A value outside that vocabulary keeps itself rather than
     * disappearing behind a fallback word — the recorder already maps anything
     * it does not know to `unknown`, so a stranger here means a row written by
     * something else, and hiding it would hide exactly the thing worth seeing.
     *
     * A version is a number, and a bare "3" in a list beside "5" says nothing
     * about what it is a version of.
     */
    protected function labelFor(string $dimension, string $key): string
    {
        if ($dimension === 'version') {
            return __('statamic-consent::messages.metric_version_label', ['version' => $key]);
        }

        if ($dimension !== 'how') {
            return $key;
        }

        $translated = __('statamic-consent::messages.metric_how.'.$key);

        return is_string($translated) && $translated !== 'statamic-consent::messages.metric_how.'.$key
            ? $translated
            : $key;
    }
}
