<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategoryAttribute;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $roots = $this->seedRoots();

        $this->purgeChildCategories();

        $subNamesByRoot = [
            'electronics' => ['Смартфоны и гаджеты', 'Ноутбуки и ПК', 'ТВ и медиа', 'Периферия и аксессуары'],
            'clothing' => ['Верхняя одежда', 'Повседневная одежда', 'Обувь', 'Аксессуары'],
            'home' => ['Мебель для дома', 'Текстиль и декор', 'Кухня и посуда', 'Хранение и порядок'],
            'sports' => ['Фитнес и тренажёры', 'Туризм и активный отдых', 'Командные виды', 'Инвентарь для зала'],
            'auto' => ['Шины и диски', 'Масла и жидкости', 'Аккумуляторы и электрика', 'Аксессуары в салон'],
            'books' => ['Художественная литература', 'Учебники и методички', 'Деловая литература', 'Детские книги'],
            'beauty' => ['Уход за кожей', 'Декоративная косметика', 'Парфюмерия', 'Инструменты и аппараты'],
            'kids' => ['Игрушки и развитие', 'Одежда для детей', 'Товары для малышей', 'Школа и творчество'],
            'pets' => ['Корма и лакомства', 'Игрушки и аксессуары', 'Гигиена и уход', 'Одежда и переноски'],
            'furniture' => ['Офисная мебель', 'Мебель для спальни', 'Гостиная и столовая', 'Прихожая и хранение'],
        ];

        foreach ($roots as $root) {
            $names = $subNamesByRoot[$root->slug] ?? [
                "{$root->name} — раздел 1",
                "{$root->name} — раздел 2",
                "{$root->name} — раздел 3",
                "{$root->name} — раздел 4",
            ];

            for ($i = 1; $i <= 4; $i++) {
                $slug = $root->slug.'-s'.$i;
                $child = Category::query()->updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => $names[$i - 1],
                        'parent_id' => $root->id,
                        'is_active' => true,
                    ]
                );

                $this->addAttributes($child->id, $this->attributeTemplateForRootSlug($root->slug));
            }
        }
    }

    /** @return list<Category> */
    private function seedRoots(): array
    {
        $slugs = [
            ['slug' => 'electronics', 'name' => 'Электроника'],
            ['slug' => 'clothing', 'name' => 'Одежда и обувь'],
            ['slug' => 'home', 'name' => 'Дом и интерьер'],
            ['slug' => 'sports', 'name' => 'Спорт и отдых'],
            ['slug' => 'auto', 'name' => 'Автотовары'],
            ['slug' => 'books', 'name' => 'Книги и медиа'],
            ['slug' => 'beauty', 'name' => 'Красота и здоровье'],
            ['slug' => 'kids', 'name' => 'Детские товары'],
            ['slug' => 'pets', 'name' => 'Зоотовары'],
            ['slug' => 'furniture', 'name' => 'Мебель'],
        ];

        $roots = [];
        foreach ($slugs as $row) {
            $roots[] = Category::query()->firstOrCreate(
                ['slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'is_active' => true,
                ]
            );
        }

        return $roots;
    }

    private function purgeChildCategories(): void
    {
        $childIds = Category::query()->whereNotNull('parent_id')->pluck('id');
        if ($childIds->isEmpty()) {
            return;
        }

        CategoryAttribute::query()->whereIn('category_id', $childIds)->delete();
        Category::query()->whereNotNull('parent_id')->delete();
    }

    /**
     * Шаблон атрибутов для всех листьев данного корня (одинаковый набор для 4 подкатегорий).
     * У каждого листа должны быть product-атрибуты и минимум один variant-атрибут с >=3 значениями для 3 SKU.
     *
     * @return list<array{name: string, type: string, options: ?array, required: bool, applies_to: string}>
     */
    private function attributeTemplateForRootSlug(string $rootSlug): array
    {
        return match ($rootSlug) {
            'electronics' => [
                ['name' => 'Бренд', 'type' => 'select', 'options' => ['Samsung', 'Apple', 'Xiaomi', 'LG', 'Sony'], 'required' => true, 'applies_to' => 'product'],
                ['name' => 'Диагональ экрана', 'type' => 'number', 'options' => null, 'required' => true, 'applies_to' => 'product'],
                ['name' => 'Память', 'type' => 'select', 'options' => ['128GB', '256GB', '512GB'], 'required' => true, 'applies_to' => 'variant'],
                ['name' => 'Цвет', 'type' => 'select', 'options' => ['Черный', 'Белый', 'Синий'], 'required' => true, 'applies_to' => 'variant'],
            ],
            'clothing' => [
                ['name' => 'Бренд', 'type' => 'text', 'options' => null, 'required' => true, 'applies_to' => 'product'],
                ['name' => 'Материал', 'type' => 'text', 'options' => null, 'required' => false, 'applies_to' => 'product'],
                ['name' => 'Размер', 'type' => 'select', 'options' => ['S', 'M', 'L', 'XL'], 'required' => true, 'applies_to' => 'variant'],
                ['name' => 'Цвет', 'type' => 'select', 'options' => ['Черный', 'Бежевый', 'Синий', 'Серый'], 'required' => true, 'applies_to' => 'variant'],
            ],
            'home', 'furniture' => [
                ['name' => 'Материал', 'type' => 'select', 'options' => ['ДСП', 'МДФ', 'Массив', 'Металл'], 'required' => true, 'applies_to' => 'product'],
                ['name' => 'Стиль', 'type' => 'select', 'options' => ['Современный', 'Сканди', 'Классика'], 'required' => false, 'applies_to' => 'product'],
                ['name' => 'Цвет', 'type' => 'select', 'options' => ['Белый', 'Дуб', 'Венге', 'Серый'], 'required' => true, 'applies_to' => 'variant'],
                ['name' => 'Комплектация', 'type' => 'select', 'options' => ['Базовая', 'Стандарт', 'Премиум'], 'required' => true, 'applies_to' => 'variant'],
            ],
            'sports' => [
                ['name' => 'Бренд', 'type' => 'text', 'options' => null, 'required' => true, 'applies_to' => 'product'],
                ['name' => 'Назначение', 'type' => 'select', 'options' => ['Дом', 'Зал', 'Улица', 'Путешествия'], 'required' => true, 'applies_to' => 'product'],
                ['name' => 'Уровень', 'type' => 'select', 'options' => ['Начальный', 'Средний', 'Профи'], 'required' => true, 'applies_to' => 'variant'],
                ['name' => 'Размер', 'type' => 'select', 'options' => ['Универсальный', 'S', 'M', 'L'], 'required' => true, 'applies_to' => 'variant'],
            ],
            'auto' => [
                ['name' => 'Сезонность', 'type' => 'select', 'options' => ['Летние', 'Зимние', 'Всесезонные'], 'required' => true, 'applies_to' => 'product'],
                ['name' => 'Бренд', 'type' => 'text', 'options' => null, 'required' => true, 'applies_to' => 'product'],
                ['name' => 'Диаметр', 'type' => 'select', 'options' => ['R15', 'R16', 'R17', 'R18'], 'required' => true, 'applies_to' => 'variant'],
                ['name' => 'Индекс', 'type' => 'select', 'options' => ['91V', '94W', '96Y'], 'required' => true, 'applies_to' => 'variant'],
            ],
            'books' => [
                ['name' => 'Жанр', 'type' => 'select', 'options' => ['Роман', 'Детектив', 'Фантастика', 'Биография'], 'required' => true, 'applies_to' => 'product'],
                ['name' => 'Язык', 'type' => 'select', 'options' => ['Русский', 'Английский', 'Двуязычный'], 'required' => true, 'applies_to' => 'product'],
                ['name' => 'Формат', 'type' => 'select', 'options' => ['Бумажный', 'Электронный', 'Комплект'], 'required' => true, 'applies_to' => 'variant'],
            ],
            'beauty' => [
                ['name' => 'Бренд', 'type' => 'text', 'options' => null, 'required' => true, 'applies_to' => 'product'],
                ['name' => 'Тип', 'type' => 'select', 'options' => ['Уход', 'Декоративная', 'Парфюм', 'Инструмент'], 'required' => true, 'applies_to' => 'product'],
                ['name' => 'Объём', 'type' => 'select', 'options' => ['30 мл', '50 мл', '100 мл', '250 мл'], 'required' => true, 'applies_to' => 'variant'],
            ],
            'kids' => [
                ['name' => 'Возраст', 'type' => 'select', 'options' => ['0+', '3+', '6+', '12+'], 'required' => true, 'applies_to' => 'product'],
                ['name' => 'Бренд', 'type' => 'text', 'options' => null, 'required' => true, 'applies_to' => 'product'],
                ['name' => 'Комплектация', 'type' => 'select', 'options' => ['Мини', 'Стандарт', 'Макси'], 'required' => true, 'applies_to' => 'variant'],
            ],
            'pets' => [
                ['name' => 'Вид', 'type' => 'select', 'options' => ['Корм', 'Лакомства', 'Игрушки', 'Аксессуары'], 'required' => true, 'applies_to' => 'product'],
                ['name' => 'Бренд', 'type' => 'text', 'options' => null, 'required' => true, 'applies_to' => 'product'],
                ['name' => 'Вес', 'type' => 'select', 'options' => ['400 г', '1 кг', '2.5 кг', '7.5 кг'], 'required' => true, 'applies_to' => 'variant'],
            ],
            default => [
                ['name' => 'Бренд', 'type' => 'text', 'options' => null, 'required' => true, 'applies_to' => 'product'],
                ['name' => 'Серия', 'type' => 'select', 'options' => ['Lite', 'Standard', 'Pro'], 'required' => true, 'applies_to' => 'product'],
                ['name' => 'Комплектация', 'type' => 'select', 'options' => ['Базовая', 'Расширенная', 'Премиум'], 'required' => true, 'applies_to' => 'variant'],
            ],
        };
    }

    private function addAttributes(int $categoryId, array $attributes): void
    {
        foreach ($attributes as $attr) {
            CategoryAttribute::query()->firstOrCreate(
                [
                    'category_id' => $categoryId,
                    'name' => $attr['name'],
                ],
                [
                    'type' => $attr['type'],
                    'options' => is_array($attr['options']) ? json_encode($attr['options']) : $attr['options'],
                    'required' => $attr['required'],
                    'applies_to' => $attr['applies_to'] ?? 'product',
                ]
            );
        }
    }
}
