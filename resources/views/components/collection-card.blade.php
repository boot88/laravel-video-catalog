@props(['collection'])

<a class="collection-card" href="{{ route('collections.show', $collection) }}">
    <div class="collection-card__cover">
        <img src="{{ $collection->cover_url }}" alt="{{ $collection->title }}" decoding="async">
        <span class="collection-card__season">{{ $collection->season ?: 'Collection' }}</span>
    </div>
    <div class="collection-card__body">
        <span class="eyebrow">{{ $collection->brand?->name ?? 'Независимый дизайнер' }}</span>
        <h3>{{ $collection->title }}</h3>
        <p>{{ $collection->city }}{{ $collection->event_date ? ' · '.$collection->event_date->translatedFormat('Y') : '' }} · {{ $collection->published_videos_count ?? 0 }} видео</p>
    </div>
</a>
