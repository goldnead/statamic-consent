<?php

namespace Goldnead\StatamicConsent\Tests\Feature;

use Goldnead\StatamicConsent\Integrations\Insights\Decisions;
use Goldnead\StatamicConsent\Records\ConsentRecord;
use Goldnead\StatamicConsent\Tests\TestCase;
use Goldnead\StatamicInsights\Contracts\Metric;
use Goldnead\StatamicInsights\Facades\Insights as InsightsStandIn;
use Goldnead\StatamicInsights\Support\MetricQuery;
use Goldnead\StatamicInsights\Support\Period;
use Goldnead\StatamicInsights\Support\Unit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

/**
 * The one number this addon offers the analytics addon.
 *
 * Every expectation below is worked out by hand from a fixture small enough to
 * add up in the head, so a query that drifted shows up as an arithmetic
 * disagreement rather than as a green suite over a different report.
 *
 * Tested against a stand-in for the contract rather than the real package: the
 * sibling is optional, and a test that needed it installed would be proving the
 * opposite of what this addon claims. See `tests/Fakes/insights-contracts.php`
 * for why that is a required file and not an autoload entry, and
 * `InsightsContractsMatchTest` for what holds the copies honest.
 *
 * Time is frozen. The buckets are asserted as literal dates, and a suite that
 * ran across midnight would otherwise fail once a night for reasons that have
 * nothing to do with the code.
 */
class InsightsMetricsTest extends TestCase
{
    /** The day everything below is measured from. */
    protected const HEUTE = '2026-08-20 12:00:00';

    /** Collects what the service provider registers. */
    protected object $insights;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.default', 'testing');

        // The proof log is what writes the rows these figures count. Off by
        // default, and an installation that leaves it off has no table at all —
        // which `a_metric_cannot_answer_without_the_table` is about.
        $app['config']->set('statamic-consent.record.enabled', true);
    }

    protected function setUp(): void
    {
        // Before the application exists, all three. The contracts have to be
        // there before the base class is loaded, the base class before a metric
        // class is, and the facade before the provider's `booted()` callback
        // asks whether it is — a callback that has already run cannot be given a
        // second chance.
        require_once __DIR__.'/../Fakes/insights-contracts.php';

        if (! class_exists('Goldnead\StatamicInsights\Support\TableMetric')) {
            require_once __DIR__.'/../Fakes/insights-table-metric.php';
        }

        require_once __DIR__.'/../Fakes/insights-facade.php';

        $this->insights = new class
        {
            /** @var array<string, string> */
            public array $registered = [];

            /**
             * Stricter than the real manager on purpose.
             *
             * The genuine one accepts a metric without a handle and works one
             * out by constructing it. Accepting that here would let the provider
             * drop the handle and still look correct — and the handle is the
             * half that ends up in saved dashboards and URLs.
             */
            public function registerMetric(string|Metric|\Closure $metric, ?string $handle = null): void
            {
                if (! is_string($metric) || $handle === null) {
                    throw new \InvalidArgumentException('This addon registers metrics lazily: a class name and a handle.');
                }

                $this->registered[$handle] = $metric;
            }
        };

        InsightsStandIn::$root = $this->insights;

        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        Carbon::setTestNow(Carbon::parse(self::HEUTE));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        InsightsStandIn::$root = null;

        parent::tearDown();
    }

    // -- The fixture --------------------------------------------------------

    /**
     * Five decisions, four of them inside the window.
     *
     * Every awkward case is in it: two versions of the banner, four different
     * kinds of decision, a record from a second site, one with no site at all,
     * and one decided before the window that must not appear in a single figure.
     *
     * Inside the window: four decisions on two days.
     */
    protected function fixture(): void
    {
        $this->record('2026-08-15 10:00:00', 3, 'accept_all', 'default', ['session', 'youtube']);
        $this->record('2026-08-15 18:00:00', 3, 'reject_all', 'default', ['session']);
        $this->record('2026-08-18 09:00:00', 3, 'accept_all', 'de', ['session', 'youtube']);

        // No site. A row in the split, never an omission — see `missingLabel`.
        $this->record('2026-08-18 11:00:00', 2, 'custom', null, ['session']);

        // Decided six weeks before the window. Not a figure here.
        $this->record('2026-07-02 10:00:00', 2, 'accept_all', 'default', ['session', 'youtube']);
    }

    /** @param  array<int, string>  $granted */
    protected function record(string $decidedAt, int $version, string $how, ?string $site, array $granted = []): ConsentRecord
    {
        return ConsentRecord::create([
            // A cookie token, not a person. Unique per row here only because the
            // table refuses a second decision at the same instant from the same
            // token — nothing in these figures counts them.
            'consent_id' => 'ct-'.md5($decidedAt.$how),
            'version' => $version,
            'granted' => $granted,
            'how' => $how,
            'site' => $site,
            'decided_at' => $decidedAt,
        ]);
    }

    /** The ten days the fixture lives in, bucketed by day. */
    protected function frage(array $filters = [], string $bucket = MetricQuery::BUCKET_DAY): MetricQuery
    {
        return new MetricQuery(
            Period::between(Carbon::parse('2026-08-11')->startOfDay(), Carbon::parse('2026-08-20')->endOfDay()),
            $bucket,
            $filters,
        );
    }

    /** @return array<string, int|float> */
    protected function keyed(array $rows): array
    {
        $keyed = [];

        foreach ($rows as $row) {
            $keyed[$row['key'] ?? ''] = $row['value'];
        }

        return $keyed;
    }

    // -- The number ---------------------------------------------------------

    /** Four decisions in the window, and the July one is not one of them. */
    #[Test]
    public function the_figure_matches_what_was_counted_by_hand(): void
    {
        $this->fixture();

        $this->assertSame(4, (new Decisions)->value($this->frage()));

        // Five rows exist. The window is what makes the difference, and a
        // missing bound would show up here rather than on a dashboard.
        $this->assertSame(5, ConsentRecord::count());
    }

    /** The handle is a contract. It ends up in saved dashboards and in URLs. */
    #[Test]
    public function the_handle_and_unit_are_the_ones_that_were_promised(): void
    {
        $metrik = new Decisions;

        $this->assertSame('consent.decisions', $metrik->handle());
        $this->assertSame(Unit::COUNT, $metrik->unit());
        $this->assertSame(__('statamic-consent::messages.metric_group'), $metrik->group());
        $this->assertNotSame('', $metrik->label());
        $this->assertNotEmpty($metrik->description());

        // A count needs nothing beyond its unit. No currency, no anything.
        $this->assertSame([], $metrik->meta($this->frage()));

        // And the group is a translated word, not a key that leaked onto a screen.
        $this->assertStringNotContainsString('statamic-consent::', $metrik->group());
    }

    // -- Nothing to measure -------------------------------------------------

    /**
     * No table, no answer — and not a zero.
     *
     * This is the ordinary state of an installation that never switched the
     * proof log on: the migrations do not load, so the table is not there. A
     * confident "0 decisions" for a site that keeps no record is the quiet kind
     * of wrong, and it would sit on a dashboard looking like a compliance
     * problem rather than a switch nobody flipped.
     */
    #[Test]
    public function a_metric_cannot_answer_without_the_table(): void
    {
        $this->assertTrue((new Decisions)->available());

        // A second, empty database rather than dropping the table in this one.
        // Dropping it would leave the suite unable to roll its own migrations
        // back, and a test that breaks its neighbours' teardown reports the
        // wrong failure everywhere afterwards.
        config()->set('database.connections.ohne_einwilligungen', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $vorher = DB::getDefaultConnection();
        DB::purge('ohne_einwilligungen');
        DB::setDefaultConnection('ohne_einwilligungen');

        try {
            $metrik = new Decisions;

            $this->assertFalse($metrik->available(), 'it answered without a consent_records table.');
            $this->assertNull($metrik->value($this->frage()), 'nothing to measure is not a zero.');
            $this->assertSame([], $metrik->series($this->frage()));
            $this->assertSame([], $metrik->breakdown($this->frage(), 'how'));
        } finally {
            DB::setDefaultConnection($vorher);
        }
    }

    // -- The splits ---------------------------------------------------------

    /**
     * A decision with no site is a row keyed null, not a missing row.
     *
     * A report that quietly excludes rows is the hardest kind of wrong to
     * notice: the rows still add up among themselves, and only the total
     * disagrees — which is the number nobody re-adds.
     */
    #[Test]
    public function a_decision_without_a_site_keeps_its_place_in_the_split(): void
    {
        $this->fixture();

        $zeilen = (new Decisions)->breakdown($this->frage(), 'site');

        $this->assertCount(3, $zeilen);

        // Largest first: two on the default site, then one each.
        $this->assertSame('default', $zeilen[0]['key']);
        $this->assertSame(2, $zeilen[0]['value']);

        $this->assertSame(['default' => 2, 'de' => 1, '' => 1], $this->keyed($zeilen));

        $ohneSite = array_values(array_filter($zeilen, fn (array $zeile) => $zeile['key'] === null));

        $this->assertCount(1, $ohneSite, 'the record without a site must be a row of its own.');
        $this->assertSame(__('statamic-consent::messages.metric_no_site'), $ohneSite[0]['label']);
        $this->assertStringNotContainsString('statamic-consent::', $ohneSite[0]['label']);

        // And the split adds up to the figure it splits.
        $this->assertSame(4, array_sum(array_column($zeilen, 'value')));
    }

    /** Version and kind of decision, both adding up to the same four. */
    #[Test]
    public function the_other_two_splits_add_up_the_same_way(): void
    {
        $this->fixture();

        $metrik = new Decisions;

        $nachFassung = $metrik->breakdown($this->frage(), 'version');

        $this->assertSame(['3' => 3, '2' => 1], $this->keyed($nachFassung));
        $this->assertSame(4, array_sum(array_column($nachFassung, 'value')));

        $nachArt = $metrik->breakdown($this->frage(), 'how');

        $this->assertSame(['accept_all' => 2, 'reject_all' => 1, 'custom' => 1], $this->keyed($nachArt));
        $this->assertSame(4, array_sum(array_column($nachArt, 'value')));
    }

    /**
     * A number is not a caption, and jargon is not a label.
     *
     * `3` beside `2` says nothing about what it is a version of, and
     * `necessary_only` is how the code spells it, not how a person reads it.
     */
    #[Test]
    public function the_split_labels_are_words_a_person_can_read(): void
    {
        $this->fixture();

        $metrik = new Decisions;

        $fassungen = $metrik->breakdown($this->frage(), 'version');

        $this->assertSame(__('statamic-consent::messages.metric_version_label', ['version' => '3']), $fassungen[0]['label']);
        $this->assertStringContainsString('3', $fassungen[0]['label']);

        $arten = [];

        foreach ($metrik->breakdown($this->frage(), 'how') as $zeile) {
            $arten[$zeile['key']] = $zeile['label'];
        }

        $this->assertSame(__('statamic-consent::messages.metric_how.accept_all'), $arten['accept_all']);
        $this->assertSame(__('statamic-consent::messages.metric_how.reject_all'), $arten['reject_all']);

        foreach ($arten as $beschriftung) {
            $this->assertStringNotContainsString('statamic-consent::', $beschriftung);
        }
    }

    /**
     * A kind of decision nobody translated keeps its own name.
     *
     * The recorder maps everything it does not know to `unknown`, so a stranger
     * in this column was written by something else — and hiding it behind a
     * fallback word would hide exactly the row worth looking at.
     */
    #[Test]
    public function an_untranslated_kind_of_decision_keeps_its_raw_value(): void
    {
        $this->record('2026-08-16 12:00:00', 3, 'aus-einem-skript', 'default');

        $zeilen = (new Decisions)->breakdown($this->frage(), 'how');

        $this->assertSame('aus-einem-skript', $zeilen[0]['key']);
        $this->assertSame('aus-einem-skript', $zeilen[0]['label']);
    }

    /** A split nobody offers is empty, not an error. */
    #[Test]
    public function an_unknown_split_is_empty(): void
    {
        $this->fixture();

        $this->assertSame([], (new Decisions)->breakdown($this->frage(), 'consent_id'));
        $this->assertSame([], (new Decisions)->breakdown($this->frage(), 'granted'));

        $this->assertSame(['version', 'how', 'site'], array_keys((new Decisions)->breakdowns()));

        foreach ((new Decisions)->breakdowns() as $beschriftung) {
            $this->assertStringNotContainsString('statamic-consent::', $beschriftung);
        }
    }

    /** Largest first, and no more than asked for. */
    #[Test]
    public function a_split_is_ordered_by_size_and_respects_the_limit(): void
    {
        $this->fixture();

        $zeilen = (new Decisions)->breakdown($this->frage(), 'version', 1);

        $this->assertCount(1, $zeilen);
        $this->assertSame('3', $zeilen[0]['key'], 'the busiest version, not the first one written.');

        $this->assertCount(1, (new Decisions)->breakdown($this->frage(), 'how', 1));
        $this->assertSame('accept_all', (new Decisions)->breakdown($this->frage(), 'how', 1)[0]['key']);
    }

    // -- Over time ----------------------------------------------------------

    /**
     * Only the buckets that have something in them.
     *
     * The empty days are Insights' job — it fills the range for every metric at
     * once. A metric that filled its own would be filled twice, and one that
     * invented a bucket outside the range would draw a column the axis has no
     * place for.
     */
    #[Test]
    public function a_series_returns_only_the_buckets_that_have_data(): void
    {
        $this->fixture();

        $this->assertSame(
            ['2026-08-15' => 2, '2026-08-18' => 2],
            (new Decisions)->series($this->frage()),
        );
    }

    /**
     * The grain comes from the question, not from the period.
     *
     * Insights decides the grain and puts it in the query. A metric that worked
     * it out again from the period length could disagree with the axis it is
     * drawn on.
     */
    #[Test]
    public function a_monthly_question_gets_monthly_buckets(): void
    {
        $this->fixture();

        $this->assertSame(
            ['2026-08' => 4],
            (new Decisions)->series($this->frage([], MetricQuery::BUCKET_MONTH)),
        );
    }

    /**
     * The visitor's clock draws the chart, not the server's.
     *
     * `decided_at` is when somebody clicked; `created_at` is when the server
     * heard about it, which can be a page load or an offline spell later and in
     * another bucket entirely. Here the row is written today about a decision
     * made on the 15th, and it belongs to the 15th.
     *
     * `created_at` is a database default (`useCurrent()`), so it carries the
     * database's clock rather than the frozen one — which is what makes the two
     * columns genuinely disagree here instead of only nominally. The assertion
     * below is on that disagreement and not on a fixed date, so the test says
     * the same thing on any day it is run.
     */
    #[Test]
    public function a_decision_is_bucketed_when_it_was_made_not_when_it_arrived(): void
    {
        $satz = $this->record('2026-08-15 23:30:00', 3, 'accept_all', 'default');

        $this->assertNotSame(
            '2026-08-15',
            Carbon::parse($satz->fresh()->created_at)->toDateString(),
            'the row has to have been written on a different day than it was decided, or this test proves nothing.',
        );

        $this->assertSame(['2026-08-15' => 1], (new Decisions)->series($this->frage()));
    }

    // -- Nothing decided ----------------------------------------------------

    /**
     * A period in which nobody decided anything is a zero, not a null.
     *
     * The distinction runs the other way from `available()`: the table is
     * there, the question applies, and the answer is that nothing happened.
     * Null would say the question could not be asked.
     */
    #[Test]
    public function a_quiet_period_is_a_zero(): void
    {
        $this->fixture();

        $leer = new MetricQuery(
            Period::between(Carbon::parse('2025-01-01')->startOfDay(), Carbon::parse('2025-01-31')->endOfDay()),
        );

        $this->assertSame(0, (new Decisions)->value($leer));
        $this->assertSame([], (new Decisions)->series($leer));
        $this->assertSame([], (new Decisions)->breakdown($leer, 'how'));
    }

    /**
     * An open-ended period has no bounds at all, and here that is safe.
     *
     * `Period::fromPreset('all')` carries a null `from` and a null `to`, so
     * `TableMetric::inPeriod()` adds neither `when()` clause and the query
     * windows on nothing. For a metric over a **nullable** timestamp that is a
     * trap: the rows that have no timestamp — and therefore belong in no bucket
     * of any chart — would silently join the total, and the figure would
     * disagree with the sum of its own series.
     *
     * `consent_records.decided_at` is `NOT NULL`, so every row has a place on
     * the axis and counting all of them is exactly what "all time" means. The
     * column is asserted rather than assumed: a later migration that made it
     * nullable would turn this figure into the wrong kind of number, and it
     * should break here rather than on a dashboard.
     *
     * Reported from the sibling addon `statamic-booking`, where the same base
     * class meets a nullable column and the trap is real.
     */
    #[Test]
    public function an_open_ended_period_counts_every_row_and_every_row_has_a_date(): void
    {
        $this->fixture();

        $spalte = collect(Schema::getColumns('consent_records'))
            ->firstWhere('name', 'decided_at');

        $this->assertNotNull($spalte, 'the column the whole metric hangs on is gone.');
        $this->assertFalse(
            $spalte['nullable'],
            'decided_at became nullable. An unbounded period adds no where-clause, so rows without a '
            .'timestamp would now be counted in the total while appearing in no bucket of the series.',
        );

        $offen = new MetricQuery(Period::fromPreset('all'), MetricQuery::BUCKET_MONTH);

        // All five, including the July decision the ten-day window excludes.
        $this->assertSame(5, (new Decisions)->value($offen));

        $this->assertSame(['2026-07' => 1, '2026-08' => 4], (new Decisions)->series($offen));

        // And the figure agrees with the sum of its own buckets, which is the
        // check a phantom row would fail.
        $this->assertSame(
            (new Decisions)->value($offen),
            array_sum((new Decisions)->series($offen)),
        );
    }

    // -- The wiring ---------------------------------------------------------

    /**
     * The provider hands the metric to the sibling, lazily and by handle.
     *
     * By class name rather than instance, so booting this addon does not build
     * a metric object on a request that renders none.
     */
    #[Test]
    public function the_service_provider_offers_the_metric_to_the_sibling(): void
    {
        $this->assertSame(
            ['consent.decisions' => Decisions::class],
            $this->insights->registered,
        );
    }
}
