<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Move paid downloads off the public disk.
 *
 * Until now a digital product's file was stored in the media folder, which the
 * web server hands out to anyone who asks for the address. The payment check on
 * the download route was therefore only as good as the secrecy of a URL that
 * appeared in the page source of every order.
 *
 * This migration adds the columns needed to serve a file under its real name
 * and then relocates every existing file to the private disk, rewriting the
 * stored paths as it goes. Sites with no digital products simply get the new
 * columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('digital_name')->nullable()->after('digital_file');
            $table->unsignedBigInteger('digital_size')->nullable()->after('digital_name');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('digital_name')->nullable()->after('digital_file');
            $table->unsignedBigInteger('digital_size')->nullable()->after('digital_name');
        });

        $this->relocateExistingFiles();
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['digital_name', 'digital_size']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['digital_name', 'digital_size']);
        });

        // The files are deliberately left on the private disk. Putting paid
        // downloads back in a public folder to undo a migration would be a
        // surprising thing for a rollback to do.
    }

    /**
     * Copy each file from the media disk to the private one under a fresh,
     * unguessable name.
     *
     * The public copy is deleted afterwards: leaving it there would leave the
     * hole this migration exists to close. Order items are matched by path, so
     * a file sold to twenty customers moves once and all twenty rows follow it.
     */
    private function relocateExistingFiles(): void
    {
        $public = Storage::disk(config('cms.media.disk', 'public'));
        $private = Storage::disk(config('cms.downloads.disk', 'private'));
        $directory = trim(config('cms.downloads.directory', 'downloads'), '/');

        $paths = DB::table('products')
            ->whereNotNull('digital_file')
            ->where('digital_file', '<>', '')
            ->pluck('digital_file')
            ->merge(DB::table('order_items')
                ->whereNotNull('digital_file')
                ->where('digital_file', '<>', '')
                ->pluck('digital_file'))
            ->unique();

        foreach ($paths as $old) {
            // Anything already under the downloads directory has been moved by
            // an earlier run, or was never public in the first place.
            if (str_starts_with($old, $directory.'/')) {
                continue;
            }

            $name = basename($old);
            $extension = pathinfo($name, PATHINFO_EXTENSION);
            $base = Str::limit(Str::slug(pathinfo($name, PATHINFO_FILENAME)) ?: 'download', 60, '');

            $new = $directory.'/'.date('Y/m').'/'.$base.'-'.Str::random(24)
                .($extension !== '' ? '.'.strtolower($extension) : '');

            if ($public->exists($old)) {
                $stream = $public->readStream($old);

                if ($stream) {
                    $private->writeStream($new, $stream);

                    if (is_resource($stream)) {
                        fclose($stream);
                    }

                    $public->delete($old);
                }
            }

            // The rows are rewritten whether or not the file was found. A row
            // pointing at a public path that no longer exists is worse than one
            // pointing at a private path that does not: the first invites the
            // old behaviour back if the file ever reappears.
            DB::table('products')->where('digital_file', $old)
                ->update(['digital_file' => $new, 'digital_name' => $name]);

            DB::table('order_items')->where('digital_file', $old)
                ->update(['digital_file' => $new, 'digital_name' => $name]);
        }
    }
};
