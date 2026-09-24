<?php

namespace App\Cms\Transfer;

use App\Cms\Transfer\Resources\CategoryResource;
use App\Cms\Transfer\Resources\CommentResource;
use App\Cms\Transfer\Resources\CouponResource;
use App\Cms\Transfer\Resources\MediaResource;
use App\Cms\Transfer\Resources\MenuResource;
use App\Cms\Transfer\Resources\PageResource;
use App\Cms\Transfer\Resources\PostResource;
use App\Cms\Transfer\Resources\ProductResource;
use App\Cms\Transfer\Resources\TagResource;
use App\Cms\Transfer\Resources\TransferResource;

/**
 * Everything that can be moved in or out, in the order it has to happen.
 *
 * The order is the important part and it is not alphabetical. Media first,
 * because a post's featured image has to exist before the post points at it.
 * Categories and tags before the things filed under them. Comments after
 * posts. Menus last of all, because a menu item is a pointer at something the
 * other nine types were busy creating.
 */
class ResourceRegistry
{
    private const RESOURCES = [
        MediaResource::class,
        CategoryResource::class,
        TagResource::class,
        PageResource::class,
        PostResource::class,
        CommentResource::class,
        ProductResource::class,
        CouponResource::class,
        MenuResource::class,
    ];

    /** @var array<string, TransferResource>|null */
    private ?array $resolved = null;

    /** @return array<string, TransferResource> */
    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $resources = [];

        foreach (self::RESOURCES as $class) {
            $resource = app($class);
            $resources[$resource->key()] = $resource;
        }

        return $this->resolved = $resources;
    }

    /**
     * The types this site can actually offer.
     *
     * A module that is switched off takes its content type with it, the same
     * way it takes its admin section: a site with the shop turned off has no
     * business offering "export products".
     *
     * @return array<string, TransferResource>
     */
    public function available(): array
    {
        return array_filter(
            $this->all(),
            fn (TransferResource $resource) => $resource->module() === null || modules()->enabled($resource->module())
        );
    }

    public function get(string $key): ?TransferResource
    {
        return $this->available()[$key] ?? null;
    }

    /** @return string[] */
    public function keys(): array
    {
        return array_keys($this->available());
    }

    /**
     * Puts a caller's selection back into import order.
     *
     * @param  string[]  $keys
     * @return array<string, TransferResource>
     */
    public function ordered(array $keys): array
    {
        return array_filter(
            $this->available(),
            fn (TransferResource $resource) => in_array($resource->key(), $keys, true)
        );
    }
}
