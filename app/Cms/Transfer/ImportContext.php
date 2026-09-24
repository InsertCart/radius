<?php

namespace App\Cms\Transfer;

use App\Models\Media;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * The state one import run shares between its resources.
 *
 * Three jobs. It resolves a media path from the old site to one on this one,
 * it resolves an author to a local account, and it rewrites the addresses
 * inside imported HTML so a body that said <img src="oldsite.com/..."> ends up
 * pointing at the copy this site now holds.
 *
 * All three are per-run caches as much as they are resolvers. A blog where
 * every post shares one header image should fetch that image once.
 */
class ImportContext
{
    /** Old media path or URL => path on this site. */
    private array $mediaPaths = [];

    /** Old media path or URL => the id it was stored under. */
    private array $mediaIds = [];

    /** Addresses to swap inside imported HTML: old => new. */
    private array $rewrites = [];

    /** Regex => new address, for families of addresses rather than one. */
    private array $patterns = [];

    /** Email (lowercased) => user id. */
    private array $authors = [];

    /** Addresses already tried and refused, so one dead image is fetched once. */
    private array $failed = [];

    /** Whether the last media() call created something. */
    private bool $created = false;

    public ?BundleReader $bundle = null;

    /** The site an import came from, when the file says. Used to spot its own URLs. */
    public ?string $sourceUrl = null;

    private ?int $deadline = null;

    public function __construct(
        public ImportOptions $options,
        public ImportReport $report,
        private MediaSideloader $sideloader,
    ) {}

    // Time ----------------------------------------------------------------

    /** Stops the run after $seconds. Set for web requests, left off on the CLI. */
    public function limitTo(int $seconds): void
    {
        $this->deadline = time() + max(5, $seconds);
    }

    public function outOfTime(): bool
    {
        return $this->deadline !== null && time() >= $this->deadline;
    }

    // Media ---------------------------------------------------------------

    /**
     * The path on this site for an image an import refers to.
     *
     * Order matters. A file carried inside the bundle is always preferred, a
     * record already on this site is next, and reaching out to the old server
     * is the last resort and only when the person asked for it.
     */
    public function mediaPath(?string $path, ?string $url = null): ?string
    {
        $media = $this->media($path, $url);

        return $media?->path;
    }

    /**
     * @param  bool  $count  False when the caller reports the outcome itself,
     *                       as the media resource does for its own records.
     */
    public function media(?string $path, ?string $url = null, bool $count = true): ?Media
    {
        $this->created = false;

        $key = $path ?: $url;

        if (blank($key) || isset($this->failed[$key])) {
            return null;
        }

        if (isset($this->mediaIds[$key])) {
            return Media::find($this->mediaIds[$key]);
        }

        // Something already at this address on this site: a re-run of the same
        // import, or a library the two sites share.
        if ($existing = $this->sideloader->existingByPath($path)) {
            return $this->remember($key, $existing, $url);
        }

        if (! $this->options->importMedia) {
            return null;
        }

        $media = null;

        try {
            if ($path && $this->bundle && ($local = $this->bundle->extractMedia($path))) {
                $media = $this->sideloader->fromFile($local, basename($path), $path, $this->options->authorId);
                @unlink($local);
            } elseif ($url && $this->options->downloadMedia) {
                $media = $this->sideloader->fromUrl($url, $this->options->authorId);
            }
        } catch (MediaRefused $e) {
            $this->failed[$key] = true;
            $this->report->note('media', ($url ?: $path).' - '.$e->getMessage());

            return null;
        }

        if (! $media) {
            $this->failed[$key] = true;

            return null;
        }

        $this->created = true;

        if ($count) {
            $this->report->record('media', ImportReport::CREATED);
        }

        return $this->remember($key, $media, $url);
    }

    /** Whether the last media() call put a new file in the library. */
    public function justCreated(): bool
    {
        return $this->created;
    }

    private function remember(string $key, Media $media, ?string $url): Media
    {
        $this->mediaIds[$key] = $media->id;
        $this->mediaPaths[$key] = $media->path;

        // Both the address the old site served it from and the bare path get
        // swapped inside imported HTML, because exports disagree about which
        // they store.
        if ($url) {
            $this->rewrites[$url] = $media->url;
        }

        if ($key !== $media->path) {
            $this->rewrites[$key] = $media->url;
        }

        return $media;
    }

    /** Lets the WordPress importer register a mapping it worked out itself. */
    public function mapMedia(string $from, Media $media): void
    {
        $this->remember($from, $media, str_starts_with($from, 'http') ? $from : null);
    }

    /**
     * Points every scaled copy of an image at the one file we now hold.
     *
     * WordPress publishes "cover.jpg" and beside it "cover-300x200.jpg",
     * "cover-1024x683.jpg" and however many more, named after the pixel size
     * that particular crop came out at. A post body almost always references
     * one of those rather than the original, and the sizes cannot be guessed -
     * they depend on the image's own proportions. So they are matched by shape
     * instead: same stem, same extension, a WxH in between.
     */
    public function mapMediaVariants(string $url, Media $media): void
    {
        $extension = pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);

        if ($extension === '') {
            return;
        }

        $base = substr($url, 0, -(strlen($extension) + 1));

        $this->patterns['#'.preg_quote($base, '#').'-\d{1,5}x\d{1,5}\.'.preg_quote($extension, '#').'#i'] = $media->url;
    }

    // Authors -------------------------------------------------------------

    /**
     * The local account for an author named in an import.
     *
     * Matched on email, because that is the only thing two sites are likely to
     * agree on. An unknown author falls back to whoever is running the import,
     * unless they asked for accounts to be created - and an account created
     * here gets no usable password, so an import can never hand anybody a way
     * in.
     *
     * @param  array{name?: ?string, email?: ?string}|null  $author
     */
    public function author(?array $author): ?int
    {
        $email = strtolower(trim((string) ($author['email'] ?? '')));

        if ($email === '') {
            return $this->options->authorId;
        }

        if (isset($this->authors[$email])) {
            return $this->authors[$email];
        }

        if ($user = User::where('email', $email)->first()) {
            return $this->authors[$email] = $user->id;
        }

        if (! $this->options->createAuthors) {
            return $this->options->authorId;
        }

        $user = User::create([
            'name' => Str::limit(trim((string) ($author['name'] ?? '')) ?: Str::before($email, '@'), 120, ''),
            'email' => $email,
            // A random secret nobody holds. The account exists so posts have a
            // byline; signing in means asking for a password reset.
            'password' => bcrypt(Str::random(40)),
            'role' => User::ROLE_CUSTOMER,
            'status' => 'active',
        ]);

        $this->report->record('users', ImportReport::CREATED);

        return $this->authors[$email] = $user->id;
    }

    // Content -------------------------------------------------------------

    /**
     * Swaps every address this run has re-homed for its address here.
     *
     * Longest first: replacing ".../image.jpg" before ".../image-300x200.jpg"
     * would leave the size suffix dangling off a new URL.
     */
    public function rewrite(?string $html): ?string
    {
        if (blank($html) || ! $this->options->rewriteUrls) {
            return $html;
        }

        // Patterns first: an exact swap of the original would otherwise leave
        // "…/new-name.jpg-300x200.jpg" behind where a sized copy was named.
        foreach ($this->patterns as $pattern => $replacement) {
            $html = preg_replace_callback($pattern, fn () => $replacement, (string) $html) ?? $html;
        }

        if ($this->rewrites === []) {
            return $html;
        }

        $from = array_keys($this->rewrites);
        usort($from, fn ($a, $b) => strlen($b) <=> strlen($a));

        return str_replace($from, array_map(fn ($key) => $this->rewrites[$key], $from), $html);
    }

    /** @return array<string, string> */
    public function rewrites(): array
    {
        return $this->rewrites;
    }
}
