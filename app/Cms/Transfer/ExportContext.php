<?php

namespace App\Cms\Transfer;

/**
 * Carries the options through an export run and collects the files the bundle
 * will have to hold.
 *
 * Every resource that writes out an image path registers it here. That is what
 * makes "export my posts" do the useful thing: the images those posts use come
 * along, even though the person never ticked the media box.
 */
class ExportContext
{
    /** @var array<string, true> Media-disk paths referenced by exported records. */
    private array $files = [];

    public function __construct(public ExportOptions $options) {}

    /**
     * Notes a media path an exported record points at, and hands it straight
     * back so a resource can write it inline.
     */
    public function file(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        // An absolute address belongs to some other host - a hotlinked hero
        // image - and there is nothing on this disk to pack.
        if (! str_starts_with($path, 'http')) {
            $this->files[$path] = true;
        }

        return $path;
    }

    /** @return string[] Every media-disk path an exported record pointed at. */
    public function collected(): array
    {
        return array_keys($this->files);
    }
}
