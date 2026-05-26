<?php

/**
 * Каталог: Красота и здоровье (4 подкатегории × 4 товара).
 * Атрибуты: Бренд, Тип (product); Объём (variant).
 */
return [
    'beauty-s1' => [
        [
            'title' => 'CeraVe Увлажняющий крем для лица',
            'short_description' => 'Крем с церамидами и гиалуроновой кислотой',
            'description' => 'Восстанавливает барьер кожи, некомедогенен, без отдушек. Подходит для сухой и чувствительной кожи, дерматологически протестирован.',
            'product_attrs' => ['Бренд' => 'CeraVe', 'Тип' => 'Уход'],
            'variants' => [
                ['options' => ['Объём' => '50 мл'], 'price' => 1290, 'stock' => 35],
                ['options' => ['Объём' => '100 мл'], 'price' => 1890, 'stock' => 28],
                ['options' => ['Объём' => '250 мл'], 'price' => 2990, 'old_price' => 3490, 'stock' => 14],
            ],
        ],
        [
            'title' => 'La Roche-Posay Effaclar Duo+',
            'short_description' => 'Средство против несовершенств',
            'description' => 'Ниацинамид и LHA-кислота, уменьшает воспаления и следы постакне. Лёгкая текстура, под макияж.',
            'product_attrs' => ['Бренд' => 'La Roche-Posay', 'Тип' => 'Уход'],
            'variants' => [
                ['options' => ['Объём' => '30 мл'], 'price' => 1490, 'stock' => 30],
                ['options' => ['Объём' => '50 мл'], 'price' => 2190, 'stock' => 22],
                ['options' => ['Объём' => '100 мл'], 'price' => 3490, 'old_price' => 3990, 'stock' => 10],
            ],
        ],
        [
            'title' => 'The Ordinary Niacinamide 10% + Zinc 1%',
            'short_description' => 'Сыворотка для жирной кожи',
            'description' => 'Сужает поры, выравнивает тон, контролирует себум. Водная основа, наносить утром и вечером до крема.',
            'product_attrs' => ['Бренд' => 'The Ordinary', 'Тип' => 'Уход'],
            'variants' => [
                ['options' => ['Объём' => '30 мл'], 'price' => 890, 'stock' => 45],
                ['options' => ['Объём' => '50 мл'], 'price' => 1190, 'stock' => 32],
                ['options' => ['Объём' => '100 мл'], 'price' => 1890, 'old_price' => 2190, 'stock' => 16],
            ],
        ],
        [
            'title' => 'Vichy Minéral 89',
            'short_description' => 'Гиалуроновая сыворотка-концентрат',
            'description' => '89% термальной воды Вичи, увлажнение и сияние. Первый шаг ухода, подходит для всех типов кожи.',
            'product_attrs' => ['Бренд' => 'Vichy', 'Тип' => 'Уход'],
            'variants' => [
                ['options' => ['Объём' => '30 мл'], 'price' => 1990, 'stock' => 25],
                ['options' => ['Объём' => '50 мл'], 'price' => 2790, 'stock' => 18],
                ['options' => ['Объём' => '100 мл'], 'price' => 4490, 'old_price' => 4990, 'stock' => 8],
            ],
        ],
    ],
    'beauty-s2' => [
        [
            'title' => 'Maybelline Lash Sensational Sky High',
            'short_description' => 'Тушь для объёма и длины',
            'description' => 'Щёточка с изогнутыми щетинками, насыщенный чёрный пигмент. Не осыпается, легко смывается мицеллярной водой.',
            'product_attrs' => ['Бренд' => 'Maybelline', 'Тип' => 'Декоративная'],
            'variants' => [
                ['options' => ['Объём' => '30 мл'], 'price' => 890, 'stock' => 50],
                ['options' => ['Объём' => '50 мл'], 'price' => 990, 'stock' => 38],
                ['options' => ['Объём' => '100 мл'], 'price' => 1290, 'old_price' => 1490, 'stock' => 20],
            ],
        ],
        [
            'title' => 'MAC Studio Fix Fluid SPF 15',
            'short_description' => 'Стойкий тональный флюид',
            'description' => 'Покрытие medium-to-full, матовый финиш, 24 оттенка в линейке. Для комбинированной и жирной кожи.',
            'product_attrs' => ['Бренд' => 'MAC', 'Тип' => 'Декоративная'],
            'variants' => [
                ['options' => ['Объём' => '30 мл'], 'price' => 3490, 'stock' => 22],
                ['options' => ['Объём' => '50 мл'], 'price' => 4490, 'stock' => 15],
                ['options' => ['Объём' => '100 мл'], 'price' => 5990, 'old_price' => 6790, 'stock' => 7],
            ],
        ],
        [
            'title' => 'NYX Professional Makeup Soft Matte Lip Cream',
            'short_description' => 'Жидкая матовая помада',
            'description' => 'Кремовая текстура, стойкость до 8 часов. Популярные оттенки Cannes, Abu Dhabi, Stockholm.',
            'product_attrs' => ['Бренд' => 'NYX', 'Тип' => 'Декоративная'],
            'variants' => [
                ['options' => ['Объём' => '30 мл'], 'price' => 690, 'stock' => 40],
                ['options' => ['Объём' => '50 мл'], 'price' => 790, 'stock' => 30],
                ['options' => ['Объём' => '100 мл'], 'price' => 990, 'old_price' => 1190, 'stock' => 15],
            ],
        ],
        [
            'title' => 'L\'Oréal Paris Infaillible 24H Fresh Wear',
            'short_description' => 'Тональный крем с дыхающей формулой',
            'description' => 'Лёгкое покрытие, стойкость в жару и влагу. SPF 25, витамин C в составе для сияния.',
            'product_attrs' => ['Бренд' => 'L\'Oréal Paris', 'Тип' => 'Декоративная'],
            'variants' => [
                ['options' => ['Объём' => '30 мл'], 'price' => 1290, 'stock' => 33],
                ['options' => ['Объём' => '50 мл'], 'price' => 1590, 'stock' => 24],
                ['options' => ['Объём' => '100 мл'], 'price' => 2190, 'old_price' => 2490, 'stock' => 11],
            ],
        ],
    ],
    'beauty-s3' => [
        [
            'title' => 'Chanel Chance Eau Tendre',
            'short_description' => 'Цветочно-фруктовый парфюм',
            'description' => 'Ноты грейпфрута, жасмина и белого мускуса. Лёгкий, романтичный аромат на каждый день.',
            'product_attrs' => ['Бренд' => 'Chanel', 'Тип' => 'Парфюм'],
            'variants' => [
                ['options' => ['Объём' => '30 мл'], 'price' => 8990, 'stock' => 10],
                ['options' => ['Объём' => '50 мл'], 'price' => 11990, 'stock' => 7],
                ['options' => ['Объём' => '100 мл'], 'price' => 15990, 'old_price' => 17990, 'stock' => 4],
            ],
        ],
        [
            'title' => 'Dior Sauvage Eau de Parfum',
            'short_description' => 'Древесно-пряный мужской аромат',
            'description' => 'Бергамот Калабрии, амброксан, ваниль из Папуа. Стойкость 8+ часов, фирменный флакон.',
            'product_attrs' => ['Бренд' => 'Dior', 'Тип' => 'Парфюм'],
            'variants' => [
                ['options' => ['Объём' => '30 мл'], 'price' => 7490, 'stock' => 12],
                ['options' => ['Объём' => '50 мл'], 'price' => 9990, 'stock' => 9],
                ['options' => ['Объём' => '100 мл'], 'price' => 13990, 'old_price' => 15490, 'stock' => 5],
            ],
        ],
        [
            'title' => 'Zielinski & Rozen Black Vanilla',
            'short_description' => 'Нишевая ваниль с дымом',
            'description' => 'Российский нишевый дом, натуральные масла. Унисекс, тёплый шлейф для вечера.',
            'product_attrs' => ['Бренд' => 'Zielinski & Rozen', 'Тип' => 'Парфюм'],
            'variants' => [
                ['options' => ['Объём' => '30 мл'], 'price' => 4990, 'stock' => 14],
                ['options' => ['Объём' => '50 мл'], 'price' => 6990, 'stock' => 10],
                ['options' => ['Объём' => '100 мл'], 'price' => 9990, 'old_price' => 11490, 'stock' => 5],
            ],
        ],
        [
            'title' => 'Lancôme La Vie Est Belle',
            'short_description' => 'Ирисово-патчулиевый женский парфюм',
            'description' => 'Иконический флакон, ноты груши, жасмина и пралине. Подарочная упаковка доступна.',
            'product_attrs' => ['Бренд' => 'Lancôme', 'Тип' => 'Парфюм'],
            'variants' => [
                ['options' => ['Объём' => '30 мл'], 'price' => 6490, 'stock' => 11],
                ['options' => ['Объём' => '50 мл'], 'price' => 8990, 'stock' => 8],
                ['options' => ['Объём' => '100 мл'], 'price' => 12490, 'old_price' => 13990, 'stock' => 4],
            ],
        ],
    ],
    'beauty-s4' => [
        [
            'title' => 'Foreo LUNA mini 3',
            'short_description' => 'Щётка для очищения лица',
            'description' => 'Силиконовые щетинки T-Sonic, 12 режимов, USB-зарядка на 300+ использований. Водонепроницаемость IP67.',
            'product_attrs' => ['Бренд' => 'Foreo', 'Тип' => 'Инструмент'],
            'variants' => [
                ['options' => ['Объём' => '30 мл'], 'price' => 12990, 'stock' => 8],
                ['options' => ['Объём' => '50 мл'], 'price' => 13990, 'stock' => 6],
                ['options' => ['Объём' => '100 мл'], 'price' => 14990, 'old_price' => 16990, 'stock' => 3],
            ],
        ],
        [
            'title' => 'Dyson Airwrap Complete Long',
            'short_description' => 'Стайлер с технологией Coanda',
            'description' => 'Завивка, выпрямление и сушка без экстремального нагрева. Насадки для длинных волос, кейс в комплекте.',
            'product_attrs' => ['Бренд' => 'Dyson', 'Тип' => 'Инструмент'],
            'variants' => [
                ['options' => ['Объём' => '30 мл'], 'price' => 54990, 'stock' => 3],
                ['options' => ['Объём' => '50 мл'], 'price' => 56990, 'stock' => 2],
                ['options' => ['Объём' => '100 мл'], 'price' => 59990, 'old_price' => 64990, 'stock' => 1],
            ],
        ],
        [
            'title' => 'Philips Series 5000 BRL950/00',
            'short_description' => 'Эпилятор с насадками для тела и лица',
            'description' => 'Диски Satinelle, подсветка SmartLight, беспроводная работа 40 мин. Водонепроницаемый, для душа.',
            'product_attrs' => ['Бренд' => 'Philips', 'Тип' => 'Инструмент'],
            'variants' => [
                ['options' => ['Объём' => '30 мл'], 'price' => 8990, 'stock' => 10],
                ['options' => ['Объём' => '50 мл'], 'price' => 9490, 'stock' => 7],
                ['options' => ['Объём' => '100 мл'], 'price' => 9990, 'old_price' => 11490, 'stock' => 4],
            ],
        ],
        [
            'title' => 'Xiaomi InFace MSK-03',
            'short_description' => 'Массажёр для лица с микротоками',
            'description' => 'RF-лифтинг и LED-терапия, 5 уровней интенсивности. Компактный, зарядка USB-C, приложение Mi Home.',
            'product_attrs' => ['Бренд' => 'Xiaomi', 'Тип' => 'Инструмент'],
            'variants' => [
                ['options' => ['Объём' => '30 мл'], 'price' => 3990, 'stock' => 15],
                ['options' => ['Объём' => '50 мл'], 'price' => 4290, 'stock' => 11],
                ['options' => ['Объём' => '100 мл'], 'price' => 4790, 'old_price' => 5490, 'stock' => 6],
            ],
        ],
    ],
];
