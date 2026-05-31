# Аватар отправителя в списке писем (слева в почте)

Картинка **внутри** письма (в HTML) и **аватар в списке входящих** — это разные вещи.

| Где | Как настраивается |
|-----|-------------------|
| Внутри письма (логотип в шаблоне) | Laravel, `mail.partials.brand-logo` — уже работает |
| Слева в списке (вместо буквы «A») | Gravatar, BIMI, настройки почтового сервиса — **не из PHP** |

Пока нет Gravatar/BIMI, клиенты (Gmail, Яндекс.Почта, Mail.ru) показывают **первую букву** имени отправителя (`MAIL_FROM_NAME`, например Alvora → «A»).

## 1. Яндекс.Почта и Mail.ru — Gravatar (проще всего)

1. Зарегистрируйтесь на [gravatar.com](https://gravatar.com).
2. Добавьте **тот же email**, что в `.env`:
   - `MAIL_FROM_ADDRESS=noreply@alvoraplace.ru`
3. Загрузите логотип (квадрат, лучше 512×512, можно из `public/icons/icon-512.png`).
4. Привяжите картинку к этому адресу.
5. Модерация Яндекса может занять **несколько недель**.

Важно: адрес в Gravatar должен **совпадать** с адресом в поле «От кого» в письме.

## 2. Gmail — BIMI (логотип в инбоксе)

Нужны:

- Работающие **SPF**, **DKIM**, **DMARC** (политика `quarantine` или `reject`) для домена `alvoraplace.ru`.
- Логотип в формате **SVG Tiny 1.2** (квадрат, фон сплошной).
- DNS-запись BIMI.

Пример TXT-записи:

```text
Имя:  default._bimi.alvoraplace.ru
Тип:  TXT
Значение: v=BIMI1; l=https://alvoraplace.ru/bimi/logo.svg;
```

Файл положите в `public/bimi/logo.svg` (после конвертации из `public/icons/icon-source.png`).

Проверка: [BIMI Group](https://bimigroup.org/), Google Postmaster Tools.

## 3. Yandex Cloud Postbox

Postbox **не подставляет** аватар из кода письма. Настройте Gravatar для `MAIL_FROM_ADDRESS` или BIMI для Gmail.

Убедитесь, что в консоли Postbox домен подтверждён, DKIM — Verified.

## 4. Что проверить в `.env`

```env
MAIL_FROM_ADDRESS=noreply@alvoraplace.ru
MAIL_FROM_NAME="Alvora"
```

Имя влияет на букву, если аватар не подключён. Логотип в списке даёт Gravatar/BIMI, а не `MAIL_FROM_NAME`.

## 5. Favicon сайта (дополнительно)

На домене `alvoraplace.ru` должны открываться `/icons/icon-192.png` и favicon — часть клиентов подтягивает иконку сайта. В `resources/views/app.blade.php` ссылки уже есть.

---

**Итог:** в коде маркетплейса аватар в списке писем включить нельзя — только Gravatar + BIMI + DNS. Внутренний логотип в письме оставляем как есть.
