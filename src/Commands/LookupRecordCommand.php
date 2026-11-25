<?php

namespace Goldnead\StatamicConsent\Commands;

use Goldnead\StatamicConsent\Records\ConsentRecord;
use Goldnead\StatamicConsent\Records\Recorder;
use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;

/**
 * Answers the one question a proof log exists for: what did this person consent
 * to, and when?
 *
 * Deliberately a command and not a control panel screen. A native listing in
 * Statamic 6 is an Inertia page, which means a Vue build, a committed bundle and
 * a CI job that proves the bundle is current — machinery this addon does not
 * have and does not want, because a stale bundle is a failure mode the goldnead
 * family has already been bitten by twice. Weighed against a lookup that happens
 * when a lawyer writes, that is the wrong trade.
 */
class LookupRecordCommand extends Command
{
    use RunsInPlease;

    protected $signature = 'statamic:consent:lookup
        {id? : The consent id from the visitor\'s cookie}
        {--latest=20 : Show the most recent decisions instead}
        {--csv= : Write the result to this file as CSV}';

    protected $description = 'Look up what a visitor consented to, and when';

    public function handle(Recorder $recorder): int
    {
        if (! $recorder->enabled()) {
            $this->components->warn('The consent record is switched off, so there is nothing to look up.');
            $this->line('  Set <comment>record.enabled</comment> in config/statamic-consent.php, then run <comment>php artisan migrate</comment>.');

            return self::FAILURE;
        }

        // A consent id of "0" is a real id, so the test is for null, not for truthiness.
        $id = $this->argument('id');

        $limit = null;
        $truncated = false;

        if ($id !== null) {
            $records = ConsentRecord::query()->forConsentId($id)->get();
        } else {
            $limit = filter_var($this->option('latest'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

            if ($limit === false) {
                $this->components->error('--latest braucht eine ganze Zahl ab 1, zum Beispiel --latest=20.');

                return self::FAILURE;
            }

            // One more than asked for, so a full list can be told apart from a cut one.
            $found = ConsentRecord::query()->orderByDesc('decided_at')->limit($limit + 1)->get();
            $truncated = $found->count() > $limit;
            $records = $found->take($limit)->values();
        }

        if ($records->isEmpty()) {
            $this->components->warn($id !== null
                ? 'No record for "'.$id.'".'
                : 'No records yet.');

            return self::SUCCESS;
        }

        $rows = $records->map(fn (ConsentRecord $r): array => [
            $r->consent_id,
            $r->decided_at->format('Y-m-d H:i:s'),
            'v'.$r->version,
            $r->how,
            $r->granted === [] ? '—' : implode(', ', $r->granted),
            $r->site ?? '—',
        ])->all();

        if ($truncated) {
            $this->components->warn('Nur die letzten '.$limit.' Einträge. Mit --latest=N mehr anzeigen.');
        }

        if ($path = $this->option('csv')) {
            return $this->writeCsv($path, $rows);
        }

        $this->table(['Consent-ID', 'Entschieden am', 'Fassung', 'Wie', 'Erlaubt', 'Site'], $rows);

        if ($id !== null) {
            $this->newLine();
            $this->line('  Die oberste Zeile ist die geltende Entscheidung.');
            $this->line('  <comment>Fassung</comment> belegt, welche Dienste dabei zur Wahl standen.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<list<string>>  $rows
     */
    protected function writeCsv(string $path, array $rows): int
    {
        $handle = @fopen($path, 'w');

        if ($handle === false) {
            $this->components->error('Cannot write to '.$path);

            return self::FAILURE;
        }

        // BOM, because this file is opened in Excel by whoever asked for it.
        // Every write is checked: on a full disk PHP returns false, and the
        // command must not claim a proof file it could not finish.
        $ok = @fwrite($handle, "\xEF\xBB\xBF") !== false
            && @fputcsv($handle, ['consent_id', 'decided_at', 'version', 'how', 'granted', 'site'], ',', '"', '') !== false;

        foreach ($rows as $row) {
            $ok = $ok && @fputcsv($handle, $row, ',', '"', '') !== false;
        }

        $flushed = @fflush($handle);
        $closed = @fclose($handle);

        if (! $ok || ! $flushed || ! $closed) {
            $this->components->error('Schreiben nach '.$path.' fehlgeschlagen. Die Datei ist unvollständig, bitte nicht als Beleg verwenden.');

            return self::FAILURE;
        }

        $this->components->info(count($rows).' record(s) written to '.$path);

        return self::SUCCESS;
    }
}
