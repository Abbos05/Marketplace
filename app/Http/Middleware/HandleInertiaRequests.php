<?php

namespace App\Http\Middleware;

use App\Models\Category;
use App\Models\Product;
use App\Services\ChatService;
use App\Services\NotificationFeedService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected static function deliveryHintFor(Request $request): ?array
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }
        $user->loadMissing('defaultPickupPoint.region');
        $region = $user->defaultPickupPoint?->region;
        if (! $region) {
            return null;
        }

        return [
            'delivery_hours' => (int) $region->delivery_hours,
            'region_name' => $region->name,
        ];
    }

    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'review_vote' => fn () => $request->session()->get('review_vote'),
            ],
            'csrfToken' => fn () => csrf_token(),
            'auth' => [
                'user' => fn () => $request->user()?->loadMissing(['defaultPickupPoint.region']),
            ],
            'staffAccess' => fn () => $request->user() ? [
                'isStaff' => $request->user()->isStaff(),
                'isAdmin' => $request->user()->isAdmin(),
                'isModerator' => $request->user()->isModerator(),
                'canAssignStaffRoles' => $request->user()->canAssignStaffRoles(),
                'panelTitle' => $request->user()->isModerator()
                    ? 'Панель модератора'
                    : ($request->user()->isAdmin() ? 'Панель администратора' : null),
            ] : null,
            'delivery_hint' => fn () => self::deliveryHintFor($request),
            'categories' => fn () => Category::rootsForCatalogNav()
                ->map(fn (Category $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'img' => $c->icon ? (Product::normalizeListingUrl($c->icon) ?? '/img/products/default.png') : '/img/products/default.png',
                ])
                ->values()
                ->all(),
            'chatUnreadCount' => fn () => $request->user()
                ? app(ChatService::class)->unreadCountFor($request->user())
                : 0,
            'supportInboxUnreadCount' => fn () => $request->user()
                ? app(ChatService::class)->supportInboxUnreadFor($request->user())
                : 0,
            'messagesHubUnreadCount' => fn () => $request->user()
                ? app(ChatService::class)->unreadCountFor($request->user())
                    + app(NotificationFeedService::class)->unreadCount($request->user())
                : 0,
            'footerSocial' => fn () => config('marketplace.footer.social', []),
        ];
    }
}
