<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

# VideoHosting

Витрина видео: категории, превью из кадра (ffmpeg), лайки, комментарии.

## Запуск

```bash
php artisan serve    # http://127.0.0.1:8000
# либо всё сразу (web + queue + vite):
composer dev
```

Первичная установка: `composer install && npm install`, скопировать `.env.example`
в `.env`, `php artisan key:generate`, `php artisan migrate`, `php artisan storage:link`
(без символической ссылки все `/storage/*` отдают 403).

## Как добавить видео

Единственный способ — команда `videos:import`: она читает файлы из папки,
переносит их в `storage/app/public/videos`, заполняет запись в БД (длительность
и качество через ffprobe) и создаёт превью из кадра видео.

1. Положить файлы в папку категории — `public/images/import/<категория>`:

```bash
mkdir -p public/images/import/blondinki
cp ~/видео/*.mp4 public/images/import/blondinki/
```

2. Запустить импорт:

```bash
php artisan videos:import blondinki            # категория = имя папки
php artisan videos:import                      # все папки внутри public/images/import
php artisan videos:import "блондинки" --category="Блондинки"
php artisan videos:import blondinki --dry-run  # показать план, ничего не менять
php artisan videos:import blondinki --copy     # оставить оригиналы в папке
php artisan videos:import blondinki --force    # обновить уже добавленные
php artisan videos:import blondinki --limit=10 # только первые 10 файлов
php artisan videos:import blondinki --views=5000  # стартовые просмотры (позиция на главной)
```

По умолчанию файлы **переносятся** в `storage/app/public/videos` (папка-инбокс
после импорта пустеет — так файлы не дублируются и не лежат в `public`). Имя файла
транслитерируется: «блондинка 1.mp4» → `blondinka-1.mp4`, заголовок → «Блондинка 1».
Повторный запуск ничего не дублирует: файлы, уже добавленные в базу, пропускаются
(по имени файла), для перезаписи нужен `--force`.

> Имя файла в `storage/app/public/videos` — идентификатор видео (по нему строится
> ссылка и ищется запись). Если одинаковые имена встречаются в разных папках,
> второй файл получает суффикс категории: `m2-res_1920p.mp4` →
> `m2-res-1920p-runway.mp4`, поэтому оба видео импортируются, а не «съедают»
> друг друга.

3. Превью генерируются сразу. Если импорт был с `--skip-thumbnails`, догенерировать:

```bash
php artisan videos:generate-thumbnails          # только те, у кого нет настоящего превью
php artisan videos:generate-thumbnails --force --limit=100
```

Сортировка главной — по колонке `rating` (= views + likes + комментарии), поэтому
новые видео с `views=0` оказываются в конце списка. Чтобы они были наверху,
импортируйте с `--views=5000` (или накрутите просмотры/лайки на сайте).

> ⚠️ `php artisan db:seed` / `migrate:fresh --seed` вызовет `VideoSeeder`: он
> очищает таблицы и создаёт 160 демо-записей, использующих 4 реальных файла из
> `storage/app/public/videos`. Для реального контента используйте `videos:import`.

## Проверка

```bash
php artisan test
```

