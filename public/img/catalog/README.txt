Демо-картинки для массового каталога (опционально)
==============================================

Сидер `DemoCatalogSeeder` для каждого товара ищет файлы по шаблону:

  public/img/catalog/user-{seller_id}/product-{seq}/{1|2|3}.{jpg|jpeg|png|webp}

где:
  seller_id — 6 или 7 (чередование по товарам);
  seq — глобальный номер товара от 1 до N (порядок создания: обход всех подкатегорий × 4 товара).

Пример для первого товара продавца 6:

  public/img/catalog/user-6/product-1/1.jpg
  public/img/catalog/user-6/product-1/2.jpg
  public/img/catalog/user-6/product-1/3.jpg

Если файла нет, подставляется `/img/products/default.png`.

После добавления своих файлов перезапустите сидер каталога или `php artisan migrate:fresh --seed`.
