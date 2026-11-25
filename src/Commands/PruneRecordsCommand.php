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

        // Null is the documented "keep everything" choice in the config. Keeping
        // everything forever is the opposite of data minimisation, so it is said
        // out loud, but it is a deliberate setting and not an error.
        if ($days === null) {
            $this->components->warn('record.keep_days is not set, so nothing is ever deleted. Set it, or accept that this log grows without end.');

            return self::SUCCESS;
        }

        // Anything else that is not a retention in days is a mistake. An unattended
        // run must fail visibly rather than report success while doing nothing.
        if (! is_numeric($days) || $days < 0) {
            $this->components->error('record.keep_days must be a number of days, 0 or more. Nothing was deleted. Fix the config and run the command again.');

            return self::FAILURE;
        }

        $deleted = $recorder->prune();

        $this->components->info($deleted === 0
            ? 'Nothing older than '.(int) $days.' days.'
            : $deleted.' record(s) older than '.(int) $days.' days deleted.');

        return self::SUCCESS;
    }
}
