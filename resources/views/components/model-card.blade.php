@props(['model', 'index' => null])

<a class="model-card" href="{{ route('models.show', $model) }}">
    <div class="model-card__photo">
        <img src="{{ $model->photo_url }}" alt="{{ $model->name }}" decoding="async">
        @if ($index)
            <span class="model-card__index">{{ str_pad((string) $index, 2, '0', STR_PAD_LEFT) }}</span>
        @endif
    </div>
    <div class="model-card__body">
        <h3>{{ $model->name }}</h3>
        <p>{{ $model->city ?: 'Портфолио' }}@if ($model->height) · {{ $model->height }} см @endif</p>
        <span>{{ $model->published_videos_count ?? 0 }} видео</span>
    </div>
</a>
