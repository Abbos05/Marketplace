<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route as RouteFacade;

class HomeSlide extends Model
{
    public const LINK_NONE = 'none';

    public const LINK_CATEGORY = 'category';

    public const LINK_PRODUCT = 'product';

    public const LINK_ROUTE = 'route';

    public const LINK_URL = 'url';

    /** @var list<string> */
    public const ALLOWED_ROUTE_NAMES = [
        'home',
        'category',
        'about',
        'contacts',
        'seller.products.create',
        'seller.dashboard',
    ];

    protected $fillable = [
        'title',
        'description',
        'button_text',
        'image_path',
        'sort_order',
        'is_active',
        'link_type',
        'link_target',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** @return array{0: ?string, 1: bool} [href, isExternal] */
    public function resolveLink(): array
    {
        $type = $this->link_type ?? self::LINK_NONE;
        $target = $this->link_target;

        if ($type === self::LINK_NONE || $target === null || $target === '') {
            return [null, false];
        }

        if ($type === self::LINK_CATEGORY) {
            $id = (int) $target;
            if ($id < 1 || ! Category::query()->whereKey($id)->exists()) {
                return [null, false];
            }

            return [route('category.show', $id), false];
        }

        if ($type === self::LINK_PRODUCT) {
            $id = (int) $target;
            if ($id < 1 || ! Product::query()->whereKey($id)->exists()) {
                return [null, false];
            }

            return [route('product.show', $id), false];
        }

        if ($type === self::LINK_ROUTE) {
            $name = trim((string) $target);
            if ($name === '' || ! in_array($name, self::ALLOWED_ROUTE_NAMES, true)) {
                return [null, false];
            }
            if (! RouteFacade::has($name)) {
                return [null, false];
            }

            return [route($name), false];
        }

        if ($type === self::LINK_URL) {
            $url = trim((string) $target);
            if ($url === '') {
                return [null, false];
            }
            if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                return [$url, true];
            }
            if (str_starts_with($url, '/')) {
                return [$url, false];
            }

            return ['/'.$url, false];
        }

        return [null, false];
    }

    /**
     * @return array{
     *     id: int,
     *     title: ?string,
     *     description: ?string,
     *     button_text: ?string,
     *     image: string,
     *     href: ?string,
     *     external: bool
     * }
     */
    public function toFrontendArray(): array
    {
        [$href, $external] = $this->resolveLink();
        $path = $this->image_path;
        if ($path !== '' && ! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        return [
            'id' => (int) $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'button_text' => $this->button_text,
            'image' => $path,
            'href' => $href,
            'external' => $external,
        ];
    }
}
