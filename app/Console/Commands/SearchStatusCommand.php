<?php

namespace App\Console\Commands;

use App\Cms\Search\SearchManager;
use Illuminate\Console\Command;
use Illuminate\Support\Number;

class SearchStatusCommand extends Command
{
    protected $signature = 'search:status';

    protected $description = 'Show the search engine in use and what its index holds';

    public function handle(SearchManager $search): int
    {
        $this->line('Engine: <info>'.$search->engineName().'</info>');
        $this->line('Live results: '.($search->instantEnabled() ? 'on' : 'off'));
        $this->newLine();

        $rows = [];
        $usesIndex = $search->engineName() !== 'database';

        foreach ($search->status() as $type => $status) {
            $rows[] = [
                $type,
                $status['enabled'] ? 'yes' : 'no',
                ! $usesIndex ? 'not needed' : ($status['ready'] ? 'ready' : 'not built (using database)'),
                $status['documents'] ?? '-',
                $status['bytes'] !== null ? Number::fileSize($status['bytes'], 1) : '-',
                $status['built_at'] ? date('Y-m-d H:i', $status['built_at']) : '-',
            ];
        }

        $this->table(['Type', 'Searchable', 'Index', 'Items', 'Size', 'Built'], $rows);

        return self::SUCCESS;
    }
}
