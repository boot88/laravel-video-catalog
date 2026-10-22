<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('site.name').' — '.config('site.tagline'))</title>
    <meta name="description" content="@yield('meta_description', config('site.description'))">
    <link rel="canonical" href="@yield('canonical', url()->current())">
    <meta name="theme-color" content="#0b0b0c">
    <meta name="robots" content="@yield('robots', 'index,follow')">

    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:site_name" content="{{ config('site.name') }}">
    <meta property="og:title" content="@yield('og_title', config('site.name'))">
    <meta property="og:description" content="@yield('meta_description', config('site.description'))">
    <meta property="og:url" content="@yield('canonical', url()->current())">
    <meta property="og:image" content="@yield('og_image', asset('media/models/model-07.webp'))">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('og_title', config('site.name'))">
    <meta name="twitter:description" content="@yield('meta_description', config('site.description'))">
    <meta name="twitter:image" content="@yield('og_image', asset('media/models/model-07.webp'))">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bodoni+Moda:opsz,wght@6..96,400;6..96,500;6..96,600&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    @stack('head')
</head>
<body class="@yield('body_class')">
    <a class="skip-link" href="#main">К содержанию</a>

    <header class="site-header" data-header>
        <div class="site-header__inner">
            <a class="wordmark" href="{{ route('video.index') }}" aria-label="{{ config('site.name') }} — на главную">
                <span class="wordmark__mark">F<span>/</span>V</span>
                <span class="wordmark__text">Fashion<br>Video Hub</span>
            </a>

            <nav class="site-nav" aria-label="Основная навигация">
                <a href="{{ route('videos.browse') }}" @class(['is-active' => request()->routeIs('videos.*')])>Показы</a>
                <a href="{{ route('models.index') }}" @class(['is-active' => request()->routeIs('models.*')])>Модели</a>
                <a href="{{ route('brands.index') }}" @class(['is-active' => request()->routeIs('brands.*')])>Бренды</a>
                <a href="{{ route('collections.index') }}" @class(['is-active' => request()->routeIs('collections.*')])>Коллекции</a>
                <a href="{{ route('about') }}" @class(['is-active' => request()->routeIs('about')])>О проекте</a>
            </nav>

            <div class="header-tools">
                <form class="header-search" action="{{ route('videos.search') }}" method="get" role="search">
                    <label class="sr-only" for="header-q">Поиск по видео, моделям и брендам</label>
                    <input id="header-q" type="search" name="q" value="{{ request('q') }}" placeholder="Поиск" autocomplete="off">
                    <button type="submit" aria-label="Найти">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m16.5 16.5 4 4"></path></svg>
                    </button>
                </form>

                <a class="icon-button favorites-link" href="{{ route('favorites.index') }}" aria-label="Избранное">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21.2l7.8-7.7 1.1-1.1a5.5 5.5 0 0 0-.1-7.8Z"></path></svg>
                    <span class="favorites-count" data-favorites-count>{{ count(session('favorite_videos', [])) }}</span>
                </a>

                <button class="menu-toggle" type="button" aria-label="Открыть меню" aria-expanded="false" data-menu-toggle>
                    <span></span><span></span>
                </button>
            </div>
        </div>

        <div class="mobile-nav" data-mobile-nav hidden>
            <form class="mobile-nav__search" action="{{ route('videos.search') }}" method="get" role="search">
                <label class="sr-only" for="mobile-q">Поиск</label>
                <input id="mobile-q" type="search" name="q" value="{{ request('q') }}" placeholder="Найти показ, модель, бренд">
                <button type="submit">Найти</button>
            </form>
            <a href="{{ route('videos.browse') }}">Все показы</a>
            <a href="{{ route('models.index') }}">Модели</a>
            <a href="{{ route('brands.index') }}">Бренды</a>
            <a href="{{ route('collections.index') }}">Коллекции</a>
            <a href="{{ route('favorites.index') }}">Избранное</a>
            <a href="{{ route('about') }}">О проекте</a>
            <a href="{{ route('credits') }}">Источники и лицензии</a>
        </div>
    </header>


    @if (session('success') || session('error'))
        <div class="flash-stack" role="status">
            @if (session('success'))
                <div class="flash flash--success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="flash flash--error">{{ session('error') }}</div>
            @endif
        </div>
    @endif

    <main id="main">
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="site-footer__top">
            <div class="footer-brand">
                <a class="wordmark wordmark--footer" href="{{ route('video.index') }}">
                    <span class="wordmark__mark">F<span>/</span>V</span>
                </a>
                <p>{{ config('site.description') }}</p>
            </div>

            <div class="footer-column">
                <h2>Смотреть</h2>
                <a href="{{ route('videos.browse') }}">Все показы</a>
                <a href="{{ route('videos.browse', ['sort' => 'popular']) }}">Популярное</a>
                <a href="{{ route('collections.index') }}">Коллекции</a>
                <a href="{{ route('favorites.index') }}">Избранное</a>
            </div>

            <div class="footer-column">
                <h2>Участники</h2>
                <a href="{{ route('models.index') }}">Модели</a>
                <a href="{{ route('brands.index') }}">Бренды</a>
                <a href="{{ route('about') }}">О проекте</a>
                <a href="{{ route('privacy') }}">Конфиденциальность</a>
                <a href="{{ route('credits') }}">Источники и лицензии</a>
            </div>

            <div class="footer-stats">
                <span>{{ number_format($stats['videos'] ?? 0, 0, ',', ' ') }} <small>видео</small></span>
                <span>{{ number_format($stats['views'] ?? 0, 0, ',', ' ') }} <small>просмотров</small></span>
                <span>{{ number_format($stats['models'] ?? 0, 0, ',', ' ') }} <small>моделей</small></span>
            </div>
        </div>

        <div class="site-footer__bottom">
            <span>&copy; {{ date('Y') }} {{ config('site.name') }}</span>
            <span>Демонстрационные материалы: фото и видео — CC0 / CC BY, атрибуция на странице «Источники».</span>
            <a href="{{ route('sitemap') }}">sitemap.xml</a>
        </div>
    </footer>

    <div class="toast" data-toast role="status" aria-live="polite"></div>

    @stack('scripts')
</body>
</html>
