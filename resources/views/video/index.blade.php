@extends('layouts.app')

@section('title', config('site.name').' — показы, кампании и портфолио моделей')
@section('meta_description', 'Редакционная видеоплатформа о моде: свежие показы, лукбуки, backstage, кампании и портфолио моделей в одном месте.')
@section('og_image', $hero ? $hero->thumbnail_url : asset('media/models/model-07.webp'))

@section('content')
    <div class="page-shell">
        <section class="hero" aria-labelledby="hero-title">
            @if ($hero)
                <a class="hero__media" href="{{ route('video.show', $hero) }}" style="--media-ratio: {{ $hero->aspect_ratio }}">
                    <img src="{{ $hero->thumbnail_url }}" alt="{{ $hero->title }}" fetchpriority="high" decoding="async">
                    <span class="hero__scrim"></span>
                    <span class="hero__tag">Главный показ</span>
                    <span class="hero__play">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5v13l10-6.5-10-6.5Z"></path></svg>
                    </span>
                </a>

                <div class="hero__copy">
                    <span class="eyebrow">Редакционный выбор · {{ $hero->published_label ?: 'Сегодня' }}</span>
                    <h1 id="hero-title">{{ $hero->title }}</h1>
                    <p>{{ \Illuminate\Support\Str::limit(strip_tags($hero->description ?: $hero->credits ?: 'Новый взгляд на моду, движение и людей, которые её создают.'), 220) }}</p>

                    <div class="hero__meta">
                        @if ($hero->categoryInfo)
                            <a href="{{ route('categories.show', $hero->categoryInfo) }}">{{ $hero->categoryInfo->name }}</a>
                        @endif
                        @if ($hero->brand)
                            <span>/</span>
                            <a href="{{ route('brands.show', $hero->brand) }}">{{ $hero->brand->name }}</a>
                        @endif
                        @if ($hero->models->isNotEmpty())
                            <span>/</span>
                            <a href="{{ route('models.show', $hero->models->first()) }}">{{ $hero->models->first()->name }}</a>
                        @endif
                    </div>

                    <div class="hero__actions">
                        <a class="button button--light" href="{{ route('video.show', $hero) }}">
                            <span>Смотреть</span>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"></path></svg>
                        </a>
                        <button class="button button--ghost" type="button"
                            data-favorite-url="{{ route('video.favorite', $hero) }}"
                            data-favorite-button
                            aria-pressed="{{ in_array($hero->getKey(), session('favorite_videos', []), true) ? 'true' : 'false' }}">
                            {{ in_array($hero->getKey(), session('favorite_videos', []), true) ? 'В избранном' : 'В избранное' }}
                        </button>
                    </div>
                </div>
            @else
                <div class="empty-state empty-state--hero">
                    <span class="eyebrow">Скоро</span>
                    <h1 id="hero-title">Новая редакция уже готовится</h1>
                    <p>Добавьте видео командой <code>php artisan videos:import</code> — и они появятся здесь.</p>
                </div>
            @endif

            <aside class="hero__aside">
                <div class="hero__note">
                    <span class="eyebrow">F/V — это</span>
                    <p>Показы, кампании, backstage и портфолио. Без бесконечной ленты: только отобранные работы и люди, которые их делают.</p>
                </div>

                <div class="hero__stats">
                    <div><strong>{{ number_format($stats['videos'], 0, ',', ' ') }}</strong><span>видео</span></div>
                    <div><strong>{{ number_format($stats['views'], 0, ',', ' ') }}</strong><span>просмотров</span></div>
                    <div><strong>{{ number_format($stats['models'], 0, ',', ' ') }}</strong><span>моделей</span></div>
                </div>

                <div class="hero__categories">
                    <span class="eyebrow">Направления</span>
                    @foreach ($categories->take(5) as $category)
                        <a href="{{ route('categories.show', $category) }}">{{ $category->name }} <span>{{ $category->published_videos_count }}</span></a>
                    @endforeach
                </div>

                @if ($popularVideos->isNotEmpty())
                    <div class="hero__picks">
                        <span class="eyebrow">Сейчас смотрят</span>
                        @foreach ($popularVideos->take(3) as $pick)
                            <a href="{{ route('video.show', $pick) }}">
                                <img src="{{ $pick->thumbnail_url }}" alt="" loading="lazy">
                                <span>
                                    <strong>{{ \Illuminate\Support\Str::limit($pick->title, 42) }}</strong>
                                    <small>{{ number_format($pick->views, 0, ',', ' ') }} просмотров</small>
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </aside>
        </section>

        <nav class="category-rail" aria-label="Категории">
            <a class="category-rail__item {{ ! request('category') ? 'is-active' : '' }}" href="{{ route('videos.browse') }}">Все</a>
            @foreach ($categories as $category)
                <a class="category-rail__item {{ request('category') === $category->slug ? 'is-active' : '' }}" href="{{ route('categories.show', $category) }}">
                    {{ $category->name }} <sup>{{ $category->published_videos_count }}</sup>
                </a>
            @endforeach
        </nav>

        <section class="section section--new" aria-labelledby="new-title">
            <x-section-heading id="new-title" eyebrow="Только что" title="Новое" :link="route('videos.browse')" link-label="Все показы" />

            @if ($newVideos->isEmpty())
                <div class="empty-state">
                    <p>Пока нет опубликованных видео в этой редакции.</p>
                </div>
            @else
                <div class="video-grid">
                    @foreach ($newVideos as $video)
                        <x-video-card :video="$video" />
                    @endforeach
                </div>
            @endif
        </section>

        <section class="split-section">
            <div class="section section--popular">
                <x-section-heading eyebrow="За неделю" title="Сейчас смотрят" :link="route('videos.browse', ['sort' => 'popular'])" link-label="Весь рейтинг" />

                <div class="popular-list">
                    @foreach ($popularVideos as $index => $video)
                        <a class="popular-item" href="{{ route('video.show', $video) }}">
                            <span class="popular-item__index">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="popular-item__thumb">
                                <img src="{{ $video->thumbnail_url }}" alt="" loading="lazy" decoding="async">
                            </span>
                            <span class="popular-item__copy">
                                <strong>{{ $video->title }}</strong>
                                <small>{{ $video->categoryInfo?->name ?? $video->category }} · {{ number_format($video->views, 0, ',', ' ') }} просмотров</small>
                            </span>
                            <span class="popular-item__arrow" aria-hidden="true">↗</span>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="section section--models">
                <x-section-heading eyebrow="Лица редакции" title="Модели" :link="route('models.index')" link-label="Все портфолио" />

                <div class="model-grid model-grid--home">
                    @foreach ($models as $index => $model)
                        <x-model-card :model="$model" :index="$index + 1" />
                    @endforeach
                </div>
            </div>
        </section>

        <section class="section section--collections" aria-labelledby="collections-title">
            <x-section-heading id="collections-title" eyebrow="Сезон за сезоном" title="Коллекции" :link="route('collections.index')" link-label="Все коллекции" />

            <div class="collection-grid">
                @foreach ($collections as $collection)
                    <x-collection-card :collection="$collection" />
                @endforeach
            </div>
        </section>

        <section class="editorial-banner">
            <span class="eyebrow">Для дизайнеров и моделей</span>
            <h2>Покажите работу там, где её увидят.</h2>
            <p>Присылайте показы, лукбуки и кампании. Мы публикуем только материалы с подтверждёнными правами и согласием участников.</p>
            <a class="button button--dark" href="{{ route('about') }}">Как попасть в редакцию <span aria-hidden="true">↗</span></a>
        </section>
    </div>
@endsection
