<?php

namespace App\Cms\Marketplace;

/**
 * The whole theme directory, as read from the catalogue file.
 *
 * The directory is small and curated, so it is held in full and searched in
 * memory - there is no search API to call. If it ever outgrows one file, a
 * paginated shape can be introduced under a new `format` number.
 */
class Catalog
{
    /**
     * @param  array<string, MarketplaceItem>  $items  keyed by slug, in catalogue order
     */
    private function __construct(
        public readonly int $format,
        public readonly array $items,
        public readonly ?string $generatedAt,
        public readonly array $raw,
    ) {}

    /**
     * @throws MarketplaceException when the document as a whole is unusable
     */
    /**
     * @param  bool  $includePaid  keep paid listings. The theme directory cannot sell,
     *                             so it drops them; the plugin directory lists them
     *                             with a link to where they are bought.
     */
    public static function fromArray(array $data, bool $includePaid = false): self
    {
        $format = (int) ($data['format'] ?? 1);
        $supported = (int) config('marketplace.supported_format', 1);

        // The one thing never guessed at: a structure this release predates.
        if ($format > $supported) {
            throw new MarketplaceException(
                "The directory uses a newer format (version {$format}) than this release of the CMS "
                ."understands (version {$supported}). Update the CMS to browse it."
            );
        }

        $entries = $data['items'] ?? $data['themes'] ?? null;

        if (! is_array($entries)) {
            throw new MarketplaceException('The directory did not contain a list of items.');
        }

        $items = [];

        foreach ($entries as $entry) {
            $item = MarketplaceItem::tryFromArray($entry);

            // Unusable entries are skipped, and so are paid ones unless asked
            // for. The first listing of a slug wins.
            if ($item === null || (! $includePaid && ! $item->isFree()) || isset($items[$item->slug])) {
                continue;
            }

            $items[$item->slug] = $item;
        }

        return new self(
            format: $format,
            items: $items,
            generatedAt: is_string($data['generated_at'] ?? null) ? $data['generated_at'] : null,
            raw: $data,
        );
    }

    public function find(string $slug): ?MarketplaceItem
    {
        return $this->items[$slug] ?? null;
    }

    /** @return MarketplaceItem[] */
    public function search(?string $query = null, ?string $tag = null): array
    {
        return array_values(array_filter($this->items, function (MarketplaceItem $item) use ($query, $tag) {
            return (blank($query) || $item->matches($query))
                && (blank($tag) || $item->hasTag($tag));
        }));
    }

    /** Every tag in use, most common first. @return array<string, int> */
    public function tags(): array
    {
        $counts = [];

        foreach ($this->items as $item) {
            foreach ($item->tags as $tag) {
                $counts[$tag] = ($counts[$tag] ?? 0) + 1;
            }
        }

        uksort($counts, fn ($a, $b) => [$counts[$b], $a] <=> [$counts[$a], $b]);

        return $counts;
    }

    public function count(): int
    {
        return count($this->items);
    }
}
