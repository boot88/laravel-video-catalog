@props(['video', 'rank' => null])

@php
    $isFavorite = in_array($video->getKey(), session('favorite_videos', []), true);
    $categoryName = $video->categoryInfo?->name ?? $video->category;
    $modelNames = $video->models->pluck('name')->take(2)->implode(', ');
@endphp

<article class="video-card video-card--{{ $video->orientation }}" data-video-card>
    <a class="video-card__media" href="{{ route('video.show', $video) }}" style="--media-ratio: {{ $video->aspect_ratio }}" aria-label="Смотреть «{{ $video->title }}»">
        <img src="{{ $video->thumbnail_url }}" alt="{{ $video->title }}" loading="lazy" decoding="async" width="640" height="480">

        @if ($video->preview_url)
            <video class="video-card__preview" data-preview-src="{{ $video->preview_url }}" muted loop playsinline preload="none" aria-hidden="true"></video>
        @endif

        <span class="video-card__play" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M8 5.5v13l10-6.5-10-6.5Z"></path></svg>
        </span>

        @if ($rank)
            <span class="video-card__rank">{{ str_pad((string) $rank, 2, '0', STR_PAD_LEFT) }}</span>
        @endif

        <span class="video-card__duration">{{ $video->formatted_duration }}</span>

        @unless ($video->has_video)
            <span class="video-card__missing">файл недоступен</span>
        @endunless
    </a>

    <div class="video-card__body">
        <div class="video-card__eyebrow">
            @if ($categoryName)
                <a href="{{ $video->categoryInfo ? route('categories.show', $video->categoryInfo) : route('videos.browse', ['category' => $video->category]) }}">{{ $categoryName }}</a>
            @endif
            @if ($video->brand)
                <span class="dot">·</span>
                <a href="{{ route('brands.show', $video->brand) }}">{{ $video->brand->name }}</a>
            @endif
        </div>

        <h3 class="video-card__title">
            <a href="{{ route('video.show', $video) }}">{{ $video->title }}</a>
        </h3>

        <div class="video-card__meta">
            @if ($modelNames !== '')
                <span>{{ $modelNames }}</span>
            @endif
            <span>{{ number_format($video->views, 0, ',', ' ') }} просмотров</span>
        </div>

        <button class="favorite-button {{ $isFavorite ? 'is-active' : '' }}" type="button"
            data-favorite-url="{{ route('video.favorite', $video) }}"
            aria-pressed="{{ $isFavorite ? 'true' : 'false' }}"
            aria-label="{{ $isFavorite ? 'Убрать из избранного' : 'Добавить в избранное' }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21.2l7.8-7.7 1.1-1.1a5.5 5.5 0 0 0-.1-7.8Z"></path></svg>
        </button>
    </div>
</article>
