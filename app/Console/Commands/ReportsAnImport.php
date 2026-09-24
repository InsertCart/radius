<?php

namespace App\Console\Commands;

use App\Cms\Transfer\ImportReport;
use Illuminate\Console\Command;

/**
 * Prints an import report to the console.
 *
 * Shared by both importers so the two read the same, and so the exit code
 * means the same thing in both: a run with failures in it does not report
 * success, because a scripted migration needs to be able to tell.
 */
trait ReportsAnImport
{
    private function printReport(ImportReport $report): int
    {
        $this->newLine();

        foreach ($report->counts() as $type => $counts) {
            $summary = collect($counts)
                ->filter()
                ->map(fn ($count, $outcome) => $count.' '.$outcome)
                ->implode(', ');

            $this->components->twoColumnDetail($type, $summary ?: 'nothing');
        }

        foreach ($report->warnings() as $warning) {
            $this->components->warn($warning);
        }

        foreach ($report->notes() as $type => $notes) {
            $this->newLine();
            $this->components->info($type.':');

            foreach ($notes as $note) {
                $this->line('  - '.$note);
            }
        }

        $this->newLine();
        $this->components->info($report->summary());

        return $report->total(ImportReport::FAILED) > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
