<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\HomeSlide;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class HomeSlideController extends Controller
{
    /** @return array<string, string> */
    private function routeLabels(): array
    {
        return [
            'home' => 'Главная',
            'category' => 'Каталог (список категорий)',
            'about' => 'О нас',
            'contacts' => 'Контакты',
            'seller.products.create' => 'Продавцу: создать товар',
            'seller.dashboard' => 'Кабинет продавца',
        ];
    }

    public function index(): Response
    {
        $slides = HomeSlide::query()
            ->ordered()
            ->get()
            ->map(function (HomeSlide $s) {
                [$href] = $s->resolveLink();

                return [
                    'id' => $s->id,
                    'title' => $s->title,
                    'description' => $s->description,
                    'button_text' => $s->button_text,
                    'image_path' => str_starts_with((string) $s->image_path, '/')
                        ? $s->image_path
                        : '/'.$s->image_path,
                    'sort_order' => $s->sort_order,
                    'is_active' => $s->is_active,
                    'link_type' => $s->link_type,
                    'link_target' => $s->link_target,
                    'resolved_href' => $href,
                ];
            });

        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('parent_id')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id'])
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->parent_id ? '↳ '.$c->name : $c->name,
            ]);

        $routeOptions = collect(HomeSlide::ALLOWED_ROUTE_NAMES)->map(fn (string $name) => [
            'value' => $name,
            'label' => $this->routeLabels()[$name] ?? $name,
        ])->values()->all();

        return Inertia::render('Admin/HomeSlides', [
            'slides' => $slides,
            'categories' => $categories,
            'routeOptions' => $routeOptions,
            'slideFieldLimits' => [
                'title' => 80,
                'description' => 400,
                'button_text' => 32,
                'link_target' => 500,
            ],
            'linkTypes' => [
                ['value' => HomeSlide::LINK_NONE, 'label' => 'Без ссылки'],
                ['value' => HomeSlide::LINK_CATEGORY, 'label' => 'Категория'],
                ['value' => HomeSlide::LINK_PRODUCT, 'label' => 'Товар (по ID)'],
                ['value' => HomeSlide::LINK_ROUTE, 'label' => 'Страница сайта'],
                ['value' => HomeSlide::LINK_URL, 'label' => 'URL или путь'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedSlide($request, true, null);
        [$position, $relativeId] = $this->validatedDisplayPosition($request);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('image');
        $imagePath = $this->storeImage($file);

        $slide = HomeSlide::query()->create([
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'button_text' => $data['button_text'] ?? null,
            'image_path' => $imagePath,
            'sort_order' => 0,
            'is_active' => (bool) ((int) ($data['is_active'] ?? 1)),
            'link_type' => $data['link_type'],
            'link_target' => $this->normalizedLinkTarget($data['link_type'], $data['link_target'] ?? null),
        ]);

        $this->applyDisplayPosition($slide, $position, $relativeId);

        return redirect()->route('admin.home-slides.index')->with('success', 'Слайд добавлен.');
    }

    public function update(Request $request, HomeSlide $homeSlide): RedirectResponse
    {
        $data = $this->validatedSlide($request, false, $homeSlide);
        [$position, $relativeId] = $this->validatedDisplayPosition($request, $homeSlide);

        $payload = [
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'button_text' => $data['button_text'] ?? null,
            'is_active' => array_key_exists('is_active', $data) ? (bool) (int) $data['is_active'] : $homeSlide->is_active,
            'link_type' => $data['link_type'],
            'link_target' => $this->normalizedLinkTarget($data['link_type'], $data['link_target'] ?? null),
        ];

        if ($request->hasFile('image')) {
            $this->deleteStoredImageIfOwned($homeSlide->image_path);
            /** @var \Illuminate\Http\UploadedFile $file */
            $file = $request->file('image');
            $payload['image_path'] = $this->storeImage($file);
        }

        $homeSlide->update($payload);
        $this->applyDisplayPosition($homeSlide, $position, $relativeId);

        return redirect()->route('admin.home-slides.index')->with('success', 'Слайд обновлён.');
    }

    public function destroy(HomeSlide $homeSlide): RedirectResponse
    {
        $this->deleteStoredImageIfOwned($homeSlide->image_path);
        $homeSlide->delete();

        return redirect()->route('admin.home-slides.index')->with('success', 'Слайд удалён.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedSlide(Request $request, bool $imageRequired, ?HomeSlide $existing = null): array
    {
        $linkTypes = [
            HomeSlide::LINK_NONE,
            HomeSlide::LINK_CATEGORY,
            HomeSlide::LINK_PRODUCT,
            HomeSlide::LINK_ROUTE,
            HomeSlide::LINK_URL,
        ];

        /** @var array<string, mixed> $data */
        $data = $request->validate([
            'title' => 'nullable|string|max:80',
            'description' => 'nullable|string|max:400',
            'button_text' => 'nullable|string|max:32',
            'is_active' => 'nullable|in:0,1',
            'link_type' => ['required', 'string', Rule::in($linkTypes)],
            'link_target' => 'nullable|string|max:500',
            'image' => [
                $imageRequired ? 'required' : 'nullable',
                'file',
                'image',
                'max:5120',
            ],
        ]);

        $type = $data['link_type'];
        $target = isset($data['link_target']) ? trim((string) $data['link_target']) : '';

        if ($type !== HomeSlide::LINK_NONE && $target === '') {
            throw ValidationException::withMessages([
                'link_target' => 'Укажите цель ссылки для выбранного типа.',
            ]);
        }

        if ($type === HomeSlide::LINK_CATEGORY) {
            $request->validate(['link_target' => 'required|exists:categories,id']);
        }

        if ($type === HomeSlide::LINK_PRODUCT) {
            $request->validate(['link_target' => 'required|exists:products,id']);
        }

        if ($type === HomeSlide::LINK_ROUTE) {
            $request->validate(['link_target' => ['required', 'string', Rule::in(HomeSlide::ALLOWED_ROUTE_NAMES)]]);
        }

        if ($type === HomeSlide::LINK_URL) {
            $request->validate([
                'link_target' => ['required', 'string', 'max:500', function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || $value === '') {
                        $fail('Укажите корректный URL или путь.');

                        return;
                    }
                    $v = trim($value);
                    if (str_starts_with($v, 'http://') || str_starts_with($v, 'https://')) {
                        if (! filter_var($v, FILTER_VALIDATE_URL)) {
                            $fail('Некорректный URL.');
                        }

                        return;
                    }
                    if (str_starts_with($v, '/')) {
                        return;
                    }
                    if (preg_match('/^[a-zA-Z0-9][a-zA-Z0-9/_\\-]*$/', $v)) {
                        return;
                    }
                    $fail('Разрешены пути, начинающиеся с /, или http(s)://…');
                }],
            ]);
        }

        $data['link_target'] = $type === HomeSlide::LINK_NONE ? null : $request->input('link_target');

        if ($request->hasFile('image')) {
            $this->assertLandscapeSlideImage($request->file('image'));
        }

        return $data;
    }

    /**
     * Слайдер — широкий баннер: ширина строго больше высоты, без «вертикалок».
     */
    private function assertLandscapeSlideImage(UploadedFile $file): void
    {
        $path = $file->getRealPath();
        if ($path === false || ! is_readable($path)) {
            throw ValidationException::withMessages([
                'image' => 'Не удалось прочитать файл изображения.',
            ]);
        }

        $info = @getimagesize($path);
        if ($info === false) {
            throw ValidationException::withMessages([
                'image' => 'Файл не является корректным изображением.',
            ]);
        }

        [$width, $height] = $info;

        if ($width < 640) {
            throw ValidationException::withMessages([
                'image' => 'Минимальная ширина изображения — 640 px (сейчас '.$width.' px).',
            ]);
        }

        if ($height < 200) {
            throw ValidationException::withMessages([
                'image' => 'Минимальная высота изображения — 200 px.',
            ]);
        }

        if ($width <= $height) {
            throw ValidationException::withMessages([
                'image' => 'Нужна горизонтальная (альбомная) картинка: ширина должна быть больше высоты.',
            ]);
        }

        $ratio = $width / $height;
        if ($ratio < 1.25) {
            throw ValidationException::withMessages([
                'image' => 'Слишком «высокий» кадр для баннера. Соотношение ширины к высоте не меньше 1,25:1 (например 5:4 или шире).',
            ]);
        }
    }

    /** @return array{0: string, 1: ?int} */
    private function validatedDisplayPosition(Request $request, ?HomeSlide $exclude = null): array
    {
        $request->validate([
            'display_position' => ['required', 'string', Rule::in(['first', 'last', 'after', 'before'])],
            'relative_slide_id' => 'nullable|integer|exists:home_slides,id',
        ]);

        $position = (string) $request->input('display_position');
        $relativeId = $request->filled('relative_slide_id') ? (int) $request->input('relative_slide_id') : null;

        if (in_array($position, ['after', 'before'], true)) {
            if ($relativeId === null) {
                throw ValidationException::withMessages([
                    'display_position' => 'Выберите слайд, относительно которого задать положение.',
                ]);
            }
            if ($exclude !== null && $relativeId === $exclude->id) {
                throw ValidationException::withMessages([
                    'relative_slide_id' => 'Нельзя указать тот же слайд.',
                ]);
            }
        }

        return [$position, $relativeId];
    }

    private function applyDisplayPosition(HomeSlide $slide, string $position, ?int $relativeId): void
    {
        $others = HomeSlide::query()
            ->where('id', '!=', $slide->id)
            ->ordered()
            ->get();

        $insertAt = match ($position) {
            'first' => 0,
            'last' => $others->count(),
            'after' => $this->indexOfSlide($others, $relativeId) + 1,
            'before' => $this->indexOfSlide($others, $relativeId),
            default => $others->count(),
        };

        if (in_array($position, ['after', 'before'], true) && $insertAt < 0) {
            throw ValidationException::withMessages([
                'relative_slide_id' => 'Слайд для позиционирования не найден.',
            ]);
        }

        $ordered = $others->values();
        $ordered->splice($insertAt, 0, [$slide]);

        foreach ($ordered->values() as $index => $item) {
            if ((int) $item->sort_order !== $index * 10) {
                $item->update(['sort_order' => $index * 10]);
            }
        }
    }

    private function indexOfSlide(\Illuminate\Support\Collection $slides, ?int $id): int
    {
        if ($id === null) {
            return -1;
        }

        foreach ($slides->values() as $index => $slide) {
            if ($slide->id === $id) {
                return $index;
            }
        }

        return -1;
    }

    private function normalizedLinkTarget(string $type, ?string $target): ?string
    {
        if ($type === HomeSlide::LINK_NONE) {
            return null;
        }

        if ($target === null) {
            return null;
        }

        $t = trim($target);

        return $t === '' ? null : $t;
    }

    private function storeImage(\Illuminate\Http\UploadedFile $file): string
    {
        $dir = public_path('img/slides');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $name = time().'_'.Str::random(8).'.'.$file->getClientOriginalExtension();
        $file->move($dir, $name);

        return '/img/slides/'.$name;
    }

    private function deleteStoredImageIfOwned(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }
        $normalized = str_starts_with($path, '/') ? $path : '/'.$path;
        if (! str_starts_with($normalized, '/img/slides/')) {
            return;
        }
        $full = public_path(ltrim($normalized, '/'));
        if (is_file($full)) {
            @unlink($full);
        }
    }
}
