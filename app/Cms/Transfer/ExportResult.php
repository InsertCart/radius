<?php

namespace App\Cms\Transfer;

/**
 * A finished export: where the file is, what to call it, and what went in.
 */
class ExportResult
{
    /**
     * @param  array<string, int>  $counts  Records written, per type.
     */
    public function __construct(
        public string $path,
        public string $filename,
        public array $counts = [],
        public int $mediaFiles = 0,
    ) {}

    public function total(): int
    {
        return array_sum($this->counts);
    }

    public function isEmpty(): bool
    {
        return $this->total() === 0 && $this->mediaFiles === 0;
    }

    public function size(): int
    {
        return is_file($this->path) ? (int) filesize($this->path) : 0;
    }

    public function summary(): string
    {
        $parts = [];

        foreach ($this->counts as $type => $count) {
            $parts[] = $count.' '.str_replace('_', ' ', $type);
        }

        if ($this->mediaFiles > 0) {
            $parts[] = $this->mediaFiles.' file'.($this->mediaFiles === 1 ? '' : 's');
        }

        return $parts === [] ? 'Nothing matched.' : implode(', ', $parts).'.';
    }
}
