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

        $id = $this->argument('id');

        $records = $id
            ? ConsentRecord::query()->forConsentId($id)->get()
            : ConsentRecord::query()->orderByDesc('decided_at')->limit((int) $this->option('latest'))->get();

        if ($records->isEmpty()) {
            $this->components->warn($id
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

        if ($path = $this->option('csv')) {
            return $this->writeCsv($path, $rows);
        }

        $this->table(['Consent-ID', 'Entschieden am', 'Fassung', 'Wie', 'Erlaubt', 'Site'], $rows);

        if ($id) {
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
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['consent_id', 'decided_at', 'version', 'how', 'granted', 'site'], ',', '"', '');

        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '');
        }

        fclose($handle);

        $this->components->info(count($rows).' record(s) written to '.$path);

        return self::SUCCESS;
    }
}
