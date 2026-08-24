<?php

namespace Goldnead\StatamicConsent\Commands;

use Goldnead\StatamicConsent\Records\Recorder;
use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;

class PruneRecordsCommand extends Command
{
    use RunsInPlease;

    protected $signature = 'statamic:consent:prune';

    protected $description = 'Delete consent records older than the configured retention';

    public function handle(Recorder $recorder): int
    {
        if (! $recorder->enabled()) {
            $this->components->warn('The consent record is switched off; nothing to prune.');

            return self::SUCCESS;
        }

        $days = config('statamic-consent.record.keep_days');

        if (! is_numeric($days)) {
            // Keeping everything forever is a choice a site can make, but it is
            // the opposite of data minimisation and should not happen by an
            // unattended command quietly doing nothing.
            $this->components->warn('record.keep_days is not set, so nothing is ever deleted. Set it, or accept that this log grows without end.');

            return self::SUCCESS;
        }

        $deleted = $recorder->prune();

        $this->components->info($deleted === 0
            ? 'Nothing older than '.(int) $days.' days.'
            : $deleted.' record(s) older than '.(int) $days.' days deleted.');

        return self::SUCCESS;
    }
}
