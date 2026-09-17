<?php

namespace App\Console\Commands;

use App\Cms\Search\SearchManager;
use Illuminate\Console\Command;

/**
 * Rebuilds the search index from the database.
 *
 * The admin screen does the same, but a web request can hit PHP's time limit
 * on a very large site. From the command line there is no limit, and a cron
 * entry can run it nightly to refresh anything saved outside the admin.
 */
class SearchRebuildCommand extends Command
{
    protected $signature = 'search:rebuild
                            {type? : Only this content type (post, product, page, ...)}
                            {--engine= : Build this engine instead of the one chosen in Settings -> Search}';

    protected $description = 'Rebuild the site search index';

    public function handle(SearchManager $search): int
    {
        $engine = $this->option('engine') ?: $search->engineName();

        if ($engine === 'database') {
            $this->info('The database engine has no index to build. Choose "index" under Settings -> Search, or pass --engine=index.');

            return self::SUCCESS;
        }

        $type = $this->argument('type');

        if ($type !== null && ! $search->has($type)) {
            $this->error("Unknown content type [{$type}]. Known: ".implode(', ', array_keys($search->definitions())).'.');

            return self::FAILURE;
        }

        $started = microtime(true);

        foreach ($search->rebuild($type, $engine) as $key => $count) {
            $this->line(sprintf('  %-10s %d item(s)', $key, $count));
        }

        $this->info(sprintf('Search index rebuilt in %.1fs.', microtime(true) - $started));

        return self::SUCCESS;
    }
}
