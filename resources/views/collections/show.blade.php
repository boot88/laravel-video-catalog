@extends('layouts.app')

@section('title', $collection->title.' — коллекция — '.config('site.name'))
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags((string) ($collection->description ?: $collection->title.' — коллекция '.$collection->brand?->name)), 160))
@section('og_image', $collection->cover_url)

@section('content')
    <div class="page-shell page-shell--profile">
        <nav class="breadcrumbs" aria-label="Хлебные крошки">
            <a href="{{ route('video.index') }}">Главная</a><span>/</span>
            <a href="{{ route('collections.index') }}">Коллекции</a><span>/</span>
            <span aria-current="page">{{ $collection->title }}</span>
        </nav>

        <header class="collection-hero">
            <div class="collection-hero__cover">
                <img src="{{ $collection->cover_url }}" alt="{{ $collection->title }}" fetchpriority="high">
                <span class="collection-hero__season">{{ $collection->season ?: 'Collection' }}</span>
            </div>
            <div class="collection-hero__copy">
                <span class="eyebrow">{{ $collection->brand?->name ?? 'Независимый дизайнер' }}</span>
                <h1>{{ $collection->title }}</h1>
                <p>{{ $collection->description ?: 'Коллекция в редакционном архиве Fashion Video Hub.' }}</p>
                <dl class="profile-facts">
                    @if ($collection->city)
                        <div><dt>Город</dt><dd>{{ $collection->city }}</dd></div>
                    @endif
                    @if ($collection->event_date)
                        <div><dt>Сезон</dt><dd>{{ $collection->event_date->translatedFormat('F Y') }}</dd></div>
                    @endif
                    <div><dt>Видео</dt><dd>{{ $videos->total() }}</dd></div>
                    @if ($collection->brand)
                        <div><dt>Бренд</dt><dd><a href="{{ route('brands.show', $collection->brand) }}">{{ $collection->brand->name }}</a></dd></div>
                    @endif
                </dl>
            </div>
        </header>

        <section class="section">
            <x-section-heading eyebrow="Показ" title="Видео коллекции" />
            @if ($videos->isEmpty())
                <div class="empty-state"><p>Видео этой коллекции ещё не опубликованы.</p></div>
            @else
                <div class="video-grid video-grid--catalog">
                    @foreach ($videos as $video)
                        <x-video-card :video="$video" />
                    @endforeach
                </div>
                <x-pagination :paginator="$videos" />
            @endif
        </section>
    </div>
@endsection
