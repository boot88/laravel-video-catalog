@extends('layouts.app')

@section('title', $pageTitle.' — '.config('site.name'))
@section('meta_description', $activeCategory?->description ?: 'Каталог fashion-видео: показы, лукбуки, backstage и кампании. Фильтры по категории, бренду, модели и сезону.')
@section('canonical', $activeCategory ? route('categories.show', $activeCategory) : url()->current().(request()->getQueryString() ? '?'.request()->getQueryString() : ''))
@section('og_image', $videos->first()?->thumbnail_url ?: asset('media/models/model-07.webp'))

@section('content')
    <div class="page-shell page-shell--catalog">
        <header class="catalog-header">
            <div>
                <span class="eyebrow">{{ $activeCategory ? 'Направление' : 'Каталог' }}</span>
                <h1>{{ $pageTitle }}</h1>
                @if ($activeCategory?->description)
                    <p>{{ $activeCategory->description }}</p>
                @elseif ($filters['q'] !== '')
                    <p>Результаты поиска по запросу «{{ $filters['q'] }}».</p>
                @else
                    <p>Отбор редакции: показы, кампании, backstage и портфолио. Фильтруйте по направлению, бренду и модели.</p>
                @endif
            </div>
            <span class="catalog-header__count">{{ number_format($videos->total(), 0, ',', ' ') }} <small>работ</small></span>
        </header>

        <form class="filter-bar" method="get" action="{{ $activeCategory ? route('categories.show', $activeCategory) : route('videos.browse') }}">
            <label class="filter-bar__search">
                <span class="sr-only">Поиск</span>
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m16.5 16.5 4 4"></path></svg>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Название, модель, бренд…">
            </label>

            @unless ($activeCategory)
                <label>
                    <span>Категория</span>
                    <select name="category">
                        <option value="">Все</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->slug }}" @selected($filters['category'] === $category->slug)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endunless

            <label>
                <span>Бренд</span>
                <select name="brand">
                    <option value="">Все</option>
                    @foreach ($brands as $brand)
                        <option value="{{ $brand->slug }}" @selected($filters['brand'] === $brand->slug)>{{ $brand->name }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                <span>Модель</span>
                <select name="model">
                    <option value="">Все</option>
                    @foreach ($models as $model)
                        <option value="{{ $model->slug }}" @selected($filters['model'] === $model->slug)>{{ $model->name }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                <span>Сортировка</span>
                <select name="sort">
                    <option value="new" @selected($filters['sort'] === 'new')>Новые</option>
                    <option value="popular" @selected($filters['sort'] === 'popular')>Популярные</option>
                    <option value="views" @selected($filters['sort'] === 'views')>По просмотрам</option>
                    <option value="oldest" @selected($filters['sort'] === 'oldest')>Архив</option>
                </select>
            </label>

            <button class="button button--dark" type="submit">Показать</button>
            @if (array_filter($filters, fn ($value, $key) => $key !== 'sort' && $value !== '', ARRAY_FILTER_USE_BOTH))
                <a class="filter-bar__reset" href="{{ $activeCategory ? route('categories.show', $activeCategory) : route('videos.browse') }}">Сбросить</a>
            @endif
        </form>

        @if ($videos->isEmpty())
            <div class="empty-state">
                <span class="eyebrow">Ничего не найдено</span>
                <h2>Попробуйте другой запрос</h2>
                <p>Сбросьте фильтры или посмотрите весь каталог.</p>
                <a class="button button--dark" href="{{ route('videos.browse') }}">Все показы</a>
            </div>
        @else
            <div class="video-grid video-grid--catalog">
                @foreach ($videos as $video)
                    <x-video-card :video="$video" />
                @endforeach
            </div>

            <x-pagination :paginator="$videos" />
        @endif
    </div>
@endsection
