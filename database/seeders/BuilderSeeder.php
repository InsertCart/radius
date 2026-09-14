<?php

namespace Database\Seeders;

use App\Models\DesignToken;
use Illuminate\Database\Seeder;

/**
 * Seeds the design tokens the editor offers in its colour and font pickers.
 *
 * Existing values are never overwritten: re-running this after an upgrade adds
 * any new tokens without undoing a site owner's palette.
 */
class BuilderSeeder extends Seeder
{
    public function run(): void
    {
        $sort = 0;

        foreach (config('builder.tokens', []) as $group => $tokens) {
            foreach ($tokens as $key => $token) {
                DesignToken::firstOrCreate(
                    ['group' => $group, 'key' => $key],
                    [
                        'label' => $token['label'],
                        'value' => $token['value'],
                        'sort_order' => $sort++,
                    ]
                );
            }
        }

        $this->command?->info('  '.DesignToken::count().' design tokens available.');
    }
}
