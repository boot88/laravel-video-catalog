@extends('layouts.app')

@section('title', 'О проекте — '.config('site.name'))
@section('meta_description', 'Fashion Video Hub — редакционная видеоплатформа о показах, кампаниях, backstage и портфолио моделей.')

@section('content')
    <div class="page-shell page-shell--prose">
        <header class="prose-hero">
            <span class="eyebrow">О проекте</span>
            <h1>Видео о моде, а не лента ради ленты.</h1>
            <p>Fashion Video Hub собирает показы, кампании, backstage и портфолио в один спокойный архив. Здесь важны автор, контекст и права на материал — а не скорость публикации.</p>
        </header>

        <div class="prose-grid">
            <section>
                <h2>Что публикуем</h2>
                <p>Показы и презентации коллекций, лукбуки, fashion-фильмы, backstage и портфолио моделей. Каждое видео получает категорию, бренд, коллекцию, участников и кредиты.</p>

                <h2>Права и согласие</h2>
                <p>Мы публикуем только материалы с подтверждёнными правами. Для съёмок с участием моделей необходимо согласие на публикацию. Демонстрационные фото и видео в этой сборке имеют свободные лицензии — полный список на странице <a href="{{ route('credits') }}">«Источники и лицензии»</a>.</p>

                <h2>Как добавить работу</h2>
                <p>Подготовьте mp4/webm, превью и описание. Положите файлы в <code>public/images/import/&lt;категория&gt;</code>, добавьте <code>metadata.json</code> и выполните:</p>
                <pre><code>php artisan videos:import &lt;категория&gt;
php artisan videos:generate-previews</code></pre>
                <p>Импорт создаёт категории, бренды, коллекции, модели и теги из метаданных, проверяет длительность и размер кадра через ffprobe.</p>
            </section>

            <aside class="prose-aside">
                <div><strong>{{ number_format($stats['videos'], 0, ',', ' ') }}</strong><span>опубликованных видео</span></div>
                <div><strong>{{ number_format($stats['views'], 0, ',', ' ') }}</strong><span>просмотров</span></div>
                <div><strong>CC0 / CC BY</strong><span>лицензии демо-материалов</span></div>
                <a class="button button--dark" href="{{ route('credits') }}">Источники и лицензии <span aria-hidden="true">↗</span></a>
            </aside>
        </div>
    </div>
@endsection
