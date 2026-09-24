<?php

namespace App\Cms\Transfer;

/**
 * What an import did, counted per content type.
 *
 * An import is not all-or-nothing. A single post with a malformed date should
 * not roll back the other nine hundred, so every record is judged on its own
 * and the outcome is written down here. What the admin screen shows afterwards
 * is this object.
 *
 * Detail is capped per type: a bundle where every row fails would otherwise
 * build a forty-thousand-line array on the way to saying "your file is wrong".
 */
class ImportReport
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const SKIPPED = 'skipped';

    public const FAILED = 'failed';

    /** @var array<string, array<string, int>> */
    private array $counts = [];

    /** @var array<string, string[]> */
    private array $notes = [];

    /** @var string[] */
    private array $warnings = [];

    /** True when a run stopped on the clock rather than at the end of the file. */
    public bool $stoppedEarly = false;

    public function record(string $type, string $outcome, ?string $note = null): void
    {
        $this->counts[$type] ??= [self::CREATED => 0, self::UPDATED => 0, self::SKIPPED => 0, self::FAILED => 0];
        $this->counts[$type][$outcome] = ($this->counts[$type][$outcome] ?? 0) + 1;

        if ($note !== null) {
            $this->note($type, $note);
        }
    }

    public function fail(string $type, string $message): void
    {
        $this->record($type, self::FAILED, $message);
    }

    public function note(string $type, string $message): void
    {
        $this->notes[$type] ??= [];

        $max = (int) config('transfer.max_notes', 50);

        if (count($this->notes[$type]) < $max) {
            $this->notes[$type][] = $message;
        } elseif (count($this->notes[$type]) === $max) {
            $this->notes[$type][] = 'More of the same were not listed.';
        }
    }

    /** Something worth saying that is not about one record. */
    public function warn(string $message): void
    {
        if (! in_array($message, $this->warnings, true)) {
            $this->warnings[] = $message;
        }
    }

    /** @return array<string, array<string, int>> */
    public function counts(): array
    {
        return $this->counts;
    }

    /** @return string[] */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /** @return array<string, string[]> */
    public function notes(): array
    {
        return $this->notes;
    }

    public function total(string $outcome): int
    {
        return array_sum(array_column($this->counts, $outcome));
    }

    public function touched(): int
    {
        return $this->total(self::CREATED) + $this->total(self::UPDATED);
    }

    public function isEmpty(): bool
    {
        return $this->counts === [];
    }

    /** One line for a flash message or a console summary. */
    public function summary(): string
    {
        $parts = [];

        foreach ([self::CREATED => 'created', self::UPDATED => 'updated', self::SKIPPED => 'skipped', self::FAILED => 'failed'] as $key => $word) {
            $total = $this->total($key);

            if ($total > 0) {
                $parts[] = $total.' '.$word;
            }
        }

        return $parts === [] ? 'Nothing to import.' : implode(', ', $parts).'.';
    }

    public function toArray(): array
    {
        return [
            'counts' => $this->counts,
            'notes' => $this->notes,
            'warnings' => $this->warnings,
            'stopped_early' => $this->stoppedEarly,
            'summary' => $this->summary(),
        ];
    }

    /** Rebuilds a report saved into the session by the controller. */
    public static function fromArray(array $data): self
    {
        $report = new self;
        $report->counts = $data['counts'] ?? [];
        $report->notes = $data['notes'] ?? [];
        $report->warnings = $data['warnings'] ?? [];
        $report->stoppedEarly = (bool) ($data['stopped_early'] ?? false);

        return $report;
    }
}
