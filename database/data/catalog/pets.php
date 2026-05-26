<?php

/**
 * Каталог: Зоотовары (4 подкатегории × 4 товара).
 * Атрибуты: Вид, Бренд (product); Вес (variant).
 */
return [
    'pets-s1' => [
        [
            'title' => 'Royal Canin Medium Adult',
            'short_description' => 'Сухой корм для собак 11–25 кг',
            'description' => 'Баланс белков и жиров, поддержка пищеварения и иммунитета. Гранулы адаптированы под челюсть средних пород.',
            'product_attrs' => ['Вид' => 'Корм', 'Бренд' => 'Royal Canin'],
            'variants' => [
                ['options' => ['Вес' => '400 г'], 'price' => 490, 'stock' => 40],
                ['options' => ['Вес' => '1 кг'], 'price' => 990, 'stock' => 30],
                ['options' => ['Вес' => '7.5 кг'], 'price' => 5490, 'old_price' => 6190, 'stock' => 12],
            ],
        ],
        [
            'title' => 'Whiskas с курицей в соусе 24×85 г',
            'short_description' => 'Влажный корм для кошек',
            'description' => 'Паучи с кусочками мяса, витамины и таурин. Для взрослых кошек от 1 года, ежедневный рацион или дополнение.',
            'product_attrs' => ['Вид' => 'Корм', 'Бренд' => 'Whiskas'],
            'variants' => [
                ['options' => ['Вес' => '400 г'], 'price' => 690, 'stock' => 35],
                ['options' => ['Вес' => '1 кг'], 'price' => 1290, 'stock' => 25],
                ['options' => ['Вес' => '2.5 кг'], 'price' => 2490, 'old_price' => 2790, 'stock' => 14],
            ],
        ],
        [
            'title' => 'Лакомство Pedigree Dentastix Medium',
            'short_description' => 'Палочки для чистки зубов',
            'description' => 'Снижают зубной налёт, свежесть дыхания. Для собак 10–25 кг, 7 штук в упаковке, низкая калорийность.',
            'product_attrs' => ['Вид' => 'Лакомства', 'Бренд' => 'Pedigree'],
            'variants' => [
                ['options' => ['Вес' => '400 г'], 'price' => 390, 'stock' => 50],
                ['options' => ['Вес' => '1 кг'], 'price' => 890, 'stock' => 32],
                ['options' => ['Вес' => '2.5 кг'], 'price' => 1990, 'old_price' => 2290, 'stock' => 16],
            ],
        ],
        [
            'title' => 'Dreamies с лососем',
            'short_description' => 'Хрустящие лакомства для кошек',
            'description' => 'Мягкая начинка, без добавления сахара. Для дрессировки и поощрения, пакет с zip-замком.',
            'product_attrs' => ['Вид' => 'Лакомства', 'Бренд' => 'Dreamies'],
            'variants' => [
                ['options' => ['Вес' => '400 г'], 'price' => 290, 'stock' => 55],
                ['options' => ['Вес' => '1 кг'], 'price' => 590, 'stock' => 38],
                ['options' => ['Вес' => '2.5 кг'], 'price' => 1290, 'old_price' => 1490, 'stock' => 18],
            ],
        ],
    ],
    'pets-s2' => [
        [
            'title' => 'Игрушка KONG Classic красная',
            'short_description' => 'Жевательная игрушка из натурального каучука',
            'description' => 'Можно наполнять лакомствами, отскакивает непредсказуемо. Для собак средних пород, долговечная.',
            'product_attrs' => ['Вид' => 'Игрушки', 'Бренд' => 'KONG'],
            'variants' => [
                ['options' => ['Вес' => '400 г'], 'price' => 1290, 'stock' => 22],
                ['options' => ['Вес' => '1 кг'], 'price' => 1490, 'stock' => 16],
                ['options' => ['Вес' => '2.5 кг'], 'price' => 1790, 'old_price' => 2090, 'stock' => 9],
            ],
        ],
        [
            'title' => 'Дразнилка GiGwi Feather Teaser',
            'short_description' => 'Перо на гибкой удочке для кошек',
            'description' => 'Стимулирует охотничий инстинкт, сменные насадки. Безопасные материалы, хранить в недоступном месте.',
            'product_attrs' => ['Вид' => 'Игрушки', 'Бренд' => 'GiGwi'],
            'variants' => [
                ['options' => ['Вес' => '400 г'], 'price' => 590, 'stock' => 30],
                ['options' => ['Вес' => '1 кг'], 'price' => 690, 'stock' => 22],
                ['options' => ['Вес' => '2.5 кг'], 'price' => 890, 'old_price' => 990, 'stock' => 12],
            ],
        ],
        [
            'title' => 'Поводок Flexi New Classic M 5 м',
            'short_description' => 'Рулетка для собак до 20 кг',
            'description' => 'Трос 5 метров, кнопка стоп и фиксации, эргономическая ручка. Надёжный механизм, сменный трос.',
            'product_attrs' => ['Вид' => 'Аксессуары', 'Бренд' => 'Flexi'],
            'variants' => [
                ['options' => ['Вес' => '400 г'], 'price' => 2490, 'stock' => 15],
                ['options' => ['Вес' => '1 кг'], 'price' => 2690, 'stock' => 11],
                ['options' => ['Вес' => '2.5 кг'], 'price' => 2990, 'old_price' => 3390, 'stock' => 6],
            ],
        ],
        [
            'title' => 'Лежанка Ferplast Sweet Grey',
            'short_description' => 'Мягкая лежанка с бортиками',
            'description' => 'Съёмный чехол на молнии, наполнитель из пены. Размер M для кошек и мелких собак, стирка при 30°C.',
            'product_attrs' => ['Вид' => 'Аксессуары', 'Бренд' => 'Ferplast'],
            'variants' => [
                ['options' => ['Вес' => '400 г'], 'price' => 1990, 'stock' => 18],
                ['options' => ['Вес' => '1 кг'], 'price' => 2290, 'stock' => 13],
                ['options' => ['Вес' => '2.5 кг'], 'price' => 2690, 'old_price' => 3090, 'stock' => 7],
            ],
        ],
    ],
    'pets-s3' => [
        [
            'title' => 'Шампунь 8in1 Perfect Coat White Pearl',
            'short_description' => 'Шампунь для светлой шерсти',
            'description' => 'Подчёркивает белизну, увлажняет кожу, pH сбалансирован. Без парабенов, для собак и кошек.',
            'product_attrs' => ['Вид' => 'Аксессуары', 'Бренд' => '8in1'],
            'variants' => [
                ['options' => ['Вес' => '400 г'], 'price' => 690, 'stock' => 25],
                ['options' => ['Вес' => '1 кг'], 'price' => 990, 'stock' => 18],
                ['options' => ['Вес' => '2.5 кг'], 'price' => 1490, 'old_price' => 1690, 'stock' => 9],
            ],
        ],
        [
            'title' => 'Наполнитель Ever Clean Extra Strength',
            'short_description' => 'Комкующийся наполнитель с углем',
            'description' => 'Бентонит, контроль запаха, низкая пыль. Для закрытых туалетов, экономичный расход.',
            'product_attrs' => ['Вид' => 'Аксессуары', 'Бренд' => 'Ever Clean'],
            'variants' => [
                ['options' => ['Вес' => '1 кг'], 'price' => 490, 'stock' => 20],
                ['options' => ['Вес' => '2.5 кг'], 'price' => 990, 'stock' => 28],
                ['options' => ['Вес' => '7.5 кг'], 'price' => 2490, 'old_price' => 2790, 'stock' => 14],
            ],
        ],
        [
            'title' => 'Когтерез Miller\'s Forge',
            'short_description' => 'Гильотинный когтерез для собак',
            'description' => 'Острые лезвия из нержавейки, пружинный механизм. Для средних и крупных пород, с пилкой в комплекте.',
            'product_attrs' => ['Вид' => 'Аксессуары', 'Бренд' => 'Miller\'s Forge'],
            'variants' => [
                ['options' => ['Вес' => '400 г'], 'price' => 890, 'stock' => 16],
                ['options' => ['Вес' => '1 кг'], 'price' => 990, 'stock' => 12],
                ['options' => ['Вес' => '2.5 кг'], 'price' => 1190, 'old_price' => 1390, 'stock' => 6],
            ],
        ],
        [
            'title' => 'Спрей Beaphar Anti-Parasite',
            'short_description' => 'Спрей от блох и клещей',
            'description' => 'Действует до 4 недель, для собак и кошек от 12 недель. Не смывать 48 часов, обработать помещение.',
            'product_attrs' => ['Вид' => 'Аксессуары', 'Бренд' => 'Beaphar'],
            'variants' => [
                ['options' => ['Вес' => '400 г'], 'price' => 590, 'stock' => 22],
                ['options' => ['Вес' => '1 кг'], 'price' => 790, 'stock' => 16],
                ['options' => ['Вес' => '2.5 кг'], 'price' => 990, 'old_price' => 1190, 'stock' => 8],
            ],
        ],
    ],
    'pets-s4' => [
        [
            'title' => 'Дождевик Hurtta Torrent Coat',
            'short_description' => 'Мембранный дождевик для собак',
            'description' => 'Светоотражающие элементы, регулировка на груди и талии. Размеры 25–65 см по спине, для дождливой погоды.',
            'product_attrs' => ['Вид' => 'Аксессуары', 'Бренд' => 'Hurtta'],
            'variants' => [
                ['options' => ['Вес' => '400 г'], 'price' => 3990, 'stock' => 10],
                ['options' => ['Вес' => '1 кг'], 'price' => 4290, 'stock' => 7],
                ['options' => ['Вес' => '2.5 кг'], 'price' => 4790, 'old_price' => 5490, 'stock' => 4],
            ],
        ],
        [
            'title' => 'Переноска Ferplast Atlas 50',
            'short_description' => 'Пластиковая переноска для авиа',
            'description' => 'Соответствует IATA для салона, вентиляция, замок. Для кошек и мелких собак до 7 кг, съёмная решётка.',
            'product_attrs' => ['Вид' => 'Аксессуары', 'Бренд' => 'Ferplast'],
            'variants' => [
                ['options' => ['Вес' => '1 кг'], 'price' => 3490, 'stock' => 9],
                ['options' => ['Вес' => '2.5 кг'], 'price' => 3990, 'stock' => 6],
                ['options' => ['Вес' => '7.5 кг'], 'price' => 4490, 'old_price' => 4990, 'stock' => 3],
            ],
        ],
        [
            'title' => 'Ошейник Hunter Modern Luxus',
            'short_description' => 'Кожаный ошейник с латунной фурнитурой',
            'description' => 'Натуральная кожа, мягкая подкладка, кольцо для поводка. Размеры 30–55 см, классический дизайн.',
            'product_attrs' => ['Вид' => 'Аксессуары', 'Бренд' => 'Hunter'],
            'variants' => [
                ['options' => ['Вес' => '400 г'], 'price' => 1990, 'stock' => 14],
                ['options' => ['Вес' => '1 кг'], 'price' => 2290, 'stock' => 10],
                ['options' => ['Вес' => '2.5 кг'], 'price' => 2690, 'old_price' => 3090, 'stock' => 5],
            ],
        ],
        [
            'title' => 'Свитер Pet Fashion Wool Mix',
            'short_description' => 'Вязаный свитер для мелких пород',
            'description' => 'Шерсть и акрил, отверстие для поводка, не сковывает движения. Размеры XS–M, стирка вручную.',
            'product_attrs' => ['Вид' => 'Аксессуары', 'Бренд' => 'Pet Fashion'],
            'variants' => [
                ['options' => ['Вес' => '400 г'], 'price' => 1290, 'stock' => 20],
                ['options' => ['Вес' => '1 кг'], 'price' => 1490, 'stock' => 14],
                ['options' => ['Вес' => '2.5 кг'], 'price' => 1790, 'old_price' => 2090, 'stock' => 7],
            ],
        ],
    ],
];
