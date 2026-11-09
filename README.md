# Fashion Video Hub

Редакционная видеоплатформа о моде: показы, лукбуки, backstage, кампании и
портфолио моделей. Laravel 12 + Blade + Vite + SQLite (можно MySQL).

## Что уже есть

- редакционная главная: `featured`-герой, «Новое», «Сейчас смотрят», модели и коллекции;
- каталог с поиском и фильтрами по категории, бренду, модели, коллекции, тегу и сезону;
- страницы `/category/{slug}`, `/models`, `/models/{slug}`, `/brands`,
  `/brands/{slug}`, `/collections`, `/collections/{slug}`, `/tags/{slug}`;
- карточки видео с учётом реального соотношения сторон (вертикальные ролики не обрезаются);
- короткие беззвучные hover-превью (4 секунды, 640px) вместо загрузки полного mp4;
- лайки, избранное в сессии, просмотры «раз в сессию за 6 часов» и `view_events`
  для недельного рейтинга;
- комментарии со статусом `approved`/`pending`/`rejected`, антиспам и
  команды модерации/обезличивания;
- SEO: meta description, canonical, Open Graph/Twitter, `VideoObject` JSON-LD,
  `sitemap.xml`, `robots.txt`;
- фото моделей и архивные видео с открытыми лицензиями, страница `/credits`;
- адаптивная вёрстка и поддержка `prefers-reduced-motion`.

Административной панели намеренно нет: контент добавляется импортом и
командами. Это позволяет не тащить Filament и не привязывать модель данных
к конкретной админке.

## Запуск

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm run build
php artisan serve
```

Для локальной разработки: `composer dev` (web + queue + vite). Этот скрипт уже
запускает сервер на `0.0.0.0` и Vite в LAN-режиме, поэтому им можно
пользоваться и с телефона.

### Проверка на телефоне по Wi-Fi

**Быстро (без HMR)** — одна команда в одном терминале:

```bash
npm run mobile     # build + rm public/hot + serve --host=0.0.0.0 --port=8002
```

Телефон открывает `http://<LAN-IP>:8002`, IP можно узнать командой
`ip -4 route get 1 | awk '{print $7;exit}'`.

**С живым обновлением CSS/JS** — два терминала:

```bash
npm run serve:lan   # php artisan serve --host=0.0.0.0 --port=8002
npm run dev:lan     # VITE_DEV_HOST=<LAN-IP>: hot-файл получает IP, CORS включён
```

В dev-режиме CSS/JS отдаёт Vite, а Laravel лишь указывает на него через
`public/hot`. Если Vite запущен обычным `npm run dev`, в `public/hot` попадает
`http://127.0.0.1:5173` — с телефона это «сам телефон», поэтому страница
приезжает без стилей. Порт 5173 должен быть доступен с телефона (при
включённом ufw: `sudo ufw allow 5173/tcp`).

Если Vite остановлен, а `public/hot` остался, страница тоже останется без
стилей — удаляйте файл (`rm -f public/hot`).

Шрифты (Bodoni Moda, Inter) лежат локально в `public/fonts` и подключены через
`@font-face` в `resources/css/app.css`. Внешних CDN в шаблоне нет, поэтому
страница отрисовывается даже на устройстве без доступа в интернет (например,
телефон в локальной сети) — раньше рендер-блокирующий запрос к
`fonts.googleapis.com` в этом случае вешал первую отрисовку.

Демо-сид создаёт 160 видео, 11 категорий, 6 брендов, 6 коллекций, 8 моделей и
10 тегов. Видео используют пять коротких фрагментов из `public/media/videos`,
поэтому демо работает без ручной загрузки в `storage`.

## Импорт видео

1. Создайте папку категории в `public/images/import` и положите видео:

```bash
mkdir -p public/images/import/runway
cp ~/video/*.mp4 public/images/import/runway/
```

2. Запустите импорт:

```bash
php artisan videos:import runway
php artisan videos:import                 # все папки
php artisan videos:import runway --dry-run
php artisan videos:import runway --copy   # оставить оригиналы
php artisan videos:import runway --force  # обновить уже добавленные
php artisan videos:import runway --limit=10
php artisan videos:import runway --views=5000
php artisan videos:import runway --skip-thumbnails
```

Файлы по умолчанию переносятся в `storage/app/public/videos`; имя
транслитерируется, а одинаковые имена в разных категориях получают суффикс.
`ffprobe` определяет длительность и размер кадра, `ffmpeg` вырезает превью.

### metadata.json

Положите рядом с файлами `metadata.json` — импорт создаст категорию, бренд,
коллекцию, модели и теги, а также заполнит описание, кредиты и дату:

```json
{
  "look-01.mp4": {
    "title": "Atelier Nord — Northern Light, look 01",
    "description": "Съёмка коллекции в Копенгагене.",
    "category": "Подиум",
    "brand": "Atelier Nord",
    "brand_country": "Дания",
    "collection": "Northern Light",
    "season": "SS26",
    "city": "Копенгаген",
    "event_date": "2026-02-04",
    "models": ["Александра Волкова", "Майя Лебедева"],
    "tags": ["подиум", "минимализм"],
    "credits": "Фото и видео: студия, лицензия CC BY 4.0",
    "published_at": "2026-02-05 12:00:00",
    "featured": true,
    "thumbnail_at": 3,
    "status": "published"
  }
}
```

Ключом может быть имя файла, имя без расширения, `*` или `__default`.
Можно передать отдельный файл: `--metadata=path/to/metadata.json`.

### Превью

```bash
php artisan videos:generate-thumbnails --all
php artisan videos:generate-previews          # 5 уникальных исходников = 5 файлов
php artisan videos:generate-previews --force --width=640 --seconds=4
```

Превью хранятся в `storage/app/public/previews` и переиспользуются для всех
записей с одинаковым `filename`.

## Модерация и данные

```bash
php artisan comments:moderate                 # очередь pending
php artisan comments:moderate 42 --approve
php artisan comments:moderate 42 --reject
php artisan comments:prune --days=180         # обезличить IP/UA старых комментариев
```

Модерация включается в `.env`: `COMMENTS_REQUIRE_MODERATION=true`.
Политика данных доступна на `/privacy`, источники медиа — на `/credits`.

## Права на контент

Демонстрационные фото взяты через Openverse (CC0 / CC BY 2.0), видео —
фрагменты Wikimedia Commons (CC BY / CC BY-SA). Авторы, лицензии и ссылки на
оригиналы перечислены в `config/media_credits.php` и на `/credits`.
Для реального контента храните подтверждение прав и согласие моделей
(`models.consent_confirmed_at`).

## Проверка

```bash
php artisan test
vendor/bin/pint --test
npm run build
```
