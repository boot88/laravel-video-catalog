@extends('layouts.app')

@php
    $categoryName = $video->categoryInfo?->name ?? $video->category;
    $isoDuration = 'PT'.intdiv(max(0, (int) $video->duration), 60).'M'.(max(0, (int) $video->duration) % 60).'S';
    $schema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'VideoObject',
        'name' => $video->title,
        'description' => strip_tags((string) ($video->description ?: $video->title)),
        'thumbnailUrl' => $video->thumbnail_url,
        'uploadDate' => $video->published_at?->toIso8601String(),
        'duration' => $video->duration > 0 ? $isoDuration : null,
        'contentUrl' => $video->video_url ?: null,
        'url' => route('video.show', $video),
        'genre' => $categoryName,
        'creditText' => $video->credits,
        'author' => ['@type' => 'Organization', 'name' => config('site.name')],
        'interactionStatistic' => [
            '@type' => 'InteractionCounter',
            'interactionType' => 'https://schema.org/WatchAction',
            'userInteractionCount' => (int) $video->views,
        ],
    ]);
@endphp

@section('title', $video->title.' — '.config('site.name'))
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags((string) ($video->description ?: $video->title)), 160))
@section('canonical', route('video.show', $video))
@section('og_type', 'video.other')
@section('og_title', $video->title)
@section('og_image', $video->thumbnail_url)

@push('head')
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PRETTY_PRINT) !!}</script>
@endpush

@section('content')
    <div class="page-shell page-shell--watch">
        <nav class="breadcrumbs" aria-label="Хлебные крошки">
            <a href="{{ route('video.index') }}">Главная</a>
            <span>/</span>
            @if ($video->categoryInfo)
                <a href="{{ route('categories.show', $video->categoryInfo) }}">{{ $video->categoryInfo->name }}</a>
                <span>/</span>
            @endif
            <span aria-current="page">{{ \Illuminate\Support\Str::limit($video->title, 60) }}</span>
        </nav>

        <div class="watch-layout">
            <div class="watch-main">
                <div class="player-shell" style="--media-ratio: {{ $video->aspect_ratio }}">
                    @if ($video->has_video)
                        <video id="main-video" class="main-video" controls preload="metadata" playsinline
                            poster="{{ $video->thumbnail_url }}"
                            data-title="{{ $video->title }}">
                            <source src="{{ $video->video_url }}" type="{{ $video->mime_type }}">
                            Ваш браузер не поддерживает видео.
                        </video>
                    @else
                        <div class="player-empty">
                            <span class="eyebrow">Файл недоступен</span>
                            <p>Видео ещё не загружено в storage. Метаданные сохранены — добавьте файл и обновите страницу.</p>
                        </div>
                    @endif
                </div>

                <header class="watch-header">
                    <div class="watch-header__top">
                        <div>
                            <span class="eyebrow">{{ $categoryName }}{{ $video->published_label ? ' · '.$video->published_label : '' }}</span>
                            <h1>{{ $video->title }}</h1>
                        </div>
                        <span class="watch-header__views">{{ number_format($video->views, 0, ',', ' ') }} <small>просмотров</small></span>
                    </div>

                    <div class="watch-actions">
                        <button class="action-chip {{ $isLiked ? 'is-active' : '' }}" type="button"
                            data-like-url="{{ route('video.like', $video) }}"
                            data-like-button
                            aria-pressed="{{ $isLiked ? 'true' : 'false' }}">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 10v10H3V10h4Zm4 10a2 2 0 0 1-2-2v-7l4-7a2 2 0 0 1 2 2v4h4.5a2 2 0 0 1 2 2.3l-1.2 6a3 3 0 0 1-3 2.7H11Z"></path></svg>
                            <span>Нравится</span>
                            <strong data-like-count>{{ number_format($video->likes, 0, ',', ' ') }}</strong>
                        </button>

                        <button class="action-chip {{ $isFavorite ? 'is-active' : '' }}" type="button"
                            data-favorite-url="{{ route('video.favorite', $video) }}"
                            data-favorite-button
                            aria-pressed="{{ $isFavorite ? 'true' : 'false' }}">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21.2l7.8-7.7 1.1-1.1a5.5 5.5 0 0 0-.1-7.8Z"></path></svg>
                            <span data-favorite-label>{{ $isFavorite ? 'В избранном' : 'В избранное' }}</span>
                        </button>

                        <a class="action-chip" href="{{ route('video.fullscreen', $video) }}" target="_blank" rel="noopener">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 3H3v5M16 3h5v5M8 21H3v-5M16 21h5v-5"></path></svg>
                            <span>Во весь экран</span>
                        </a>

                        <button class="action-chip" type="button" data-share data-title="{{ $video->title }}">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><path d="m8.6 10.7 6.8-4.4M8.6 13.3l6.8 4.4"></path></svg>
                            <span>Поделиться</span>
                        </button>
                    </div>
                </header>

                <section class="watch-description">
                    <p>{{ $video->description ?: 'Описание пока отсутствует.' }}</p>
                    @if ($video->credits)
                        <p class="watch-description__credits">{{ $video->credits }}</p>
                    @endif
                </section>

                <section class="watch-comments" aria-labelledby="comments-title">
                    <div class="watch-comments__head">
                        <h2 id="comments-title">Комментарии <span>{{ $video->comments_count }}</span></h2>
                        @if (config('site.comments_require_moderation'))
                            <span class="badge">Модерация включена</span>
                        @endif
                    </div>

                    @if ($errors->any())
                        <div class="form-errors" role="alert">
                            @foreach ($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    <form class="comment-form" action="{{ route('video.comment.store', $video) }}" method="post">
                        @csrf
                        <div class="comment-form__row">
                            <label>
                                <span class="sr-only">Имя</span>
                                <input type="text" name="username" value="{{ old('username') }}" maxlength="50" placeholder="Имя (необязательно)" autocomplete="nickname">
                            </label>
                            <span class="comment-form__hint">Не более 3 комментариев в час с одного IP</span>
                        </div>
                        <label>
                            <span class="sr-only">Комментарий</span>
                            <textarea name="content" rows="3" maxlength="1000" required placeholder="Что вы думаете об этой работе?">{{ old('content') }}</textarea>
                        </label>
                        <div class="comment-form__actions">
                            <span data-char-count>0 / 1000</span>
                            <button class="button button--dark" type="submit">Отправить</button>
                        </div>
                    </form>

                    <div class="comment-list">
                        @forelse ($comments as $comment)
                            <article class="comment">
                                <div class="comment__avatar">{{ mb_strtoupper(mb_substr($comment->username, 0, 1)) }}</div>
                                <div>
                                    <header>
                                        <strong>{{ $comment->username }}</strong>
                                        <time datetime="{{ $comment->created_at->toIso8601String() }}">{{ $comment->created_at->translatedFormat('j M Y, H:i') }}</time>
                                    </header>
                                    <p>{{ $comment->content }}</p>
                                </div>
                            </article>
                        @empty
                            <p class="comment-list__empty">Пока никто не высказался. Будьте первым.</p>
                        @endforelse
                    </div>

                    <x-pagination :paginator="$comments" />
                </section>
            </div>


            <aside class="watch-aside">
                <div class="aside-card aside-card--credits">
                    <span class="eyebrow">Команда</span>
                    @if ($video->models->isNotEmpty())
                        <div class="aside-models">
                            @foreach ($video->models as $model)
                                <a href="{{ route('models.show', $model) }}">
                                    <img src="{{ $model->photo_url }}" alt="{{ $model->name }}" loading="lazy">
                                    <span>{{ $model->name }}</span>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <p>Модели не указаны.</p>
                    @endif

                    @if ($video->brand)
                        <div class="aside-row">
                            <span>Бренд</span>
                            <a href="{{ route('brands.show', $video->brand) }}">{{ $video->brand->name }}</a>
                        </div>
                    @endif

                    @if ($video->collection)
                        <div class="aside-row">
                            <span>Коллекция</span>
                            <a href="{{ route('collections.show', $video->collection) }}">{{ $video->collection->title }}</a>
                        </div>
                    @endif

                    <div class="aside-row">
                        <span>Качество</span>
                        <strong>{{ $video->quality }}</strong>
                    </div>

                    @if ($video->width && $video->height)
                        <div class="aside-row">
                            <span>Кадр</span>
                            <strong>{{ $video->width }} × {{ $video->height }}</strong>
                        </div>
                    @endif
                </div>

                @if ($video->tags->isNotEmpty())
                    <div class="aside-card">
                        <span class="eyebrow">Теги</span>
                        <div class="tag-cloud">
                            @foreach ($video->tags as $tag)
                                <a href="{{ route('tags.show', $tag) }}">#{{ $tag->name }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="watch-nav">
                    @if ($previous)
                        <a href="{{ route('video.show', $previous) }}">
                            <span>← Предыдущее</span>
                            <strong>{{ \Illuminate\Support\Str::limit($previous->title, 42) }}</strong>
                        </a>
                    @endif
                    @if ($next)
                        <a href="{{ route('video.show', $next) }}">
                            <span>Следующее →</span>
                            <strong>{{ \Illuminate\Support\Str::limit($next->title, 42) }}</strong>
                        </a>
                    @endif
                </div>
            </aside>
        </div>

        @if ($related->isNotEmpty())
            <section class="section section--related">
                <x-section-heading eyebrow="По теме" title="Смотрите также" :link="route('videos.browse')" link-label="Все показы" />
                <div class="video-grid video-grid--four">
                    @foreach ($related as $item)
                        <x-video-card :video="$item" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
