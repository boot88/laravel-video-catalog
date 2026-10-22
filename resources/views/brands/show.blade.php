@extends('layouts.app')

@section('title', $brand->name.' — бренд — '.config('site.name'))
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags((string) ($brand->description ?: 'Коллекции, показы и кампании бренда '.$brand->name)), 160))

@section('content')
    <div class="page-shell page-shell--profile">
        <nav class="breadcrumbs" aria-label="Хлебные крошки">
            <a href="{{ route('video.index') }}">Главная</a><span>/</span>
            <a href="{{ route('brands.index') }}">Бренды</a><span>/</span>
            <span aria-current="page">{{ $brand->name }}</span>
        </nav>

        <header class="brand-hero">
            <span class="brand-hero__mark">{{ mb_substr($brand->name, 0, 1) }}</span>
            <div>
                <span class="eyebrow">{{ $brand->country ?: 'Independent label' }}</span>
                <h1>{{ $brand->name }}</h1>
                <p>{{ $brand->description ?: 'Независимый бренд в редакционной подборке Fashion Video Hub.' }}</p>
                <div class="brand-hero__meta">
                    <span>{{ $brand->published_videos_count ?? $videos->total() }} видео</span>
                    @if ($brand->website)
                        <a href="{{ $brand->website }}" target="_blank" rel="noopener">Сайт бренда ↗</a>
                    @endif
                </div>
            </div>
        </header>

        @if ($brand->collections->isNotEmpty())
            <section class="section">
                <x-section-heading eyebrow="Архив" title="Коллекции" />
                <div class="collection-grid">
                    @foreach ($brand->collections as $collection)
                        <x-collection-card :collection="$collection" />
                    @endforeach
                </div>
            </section>
        @endif

        <section class="section">
            <x-section-heading eyebrow="Видео" title="Показы и кампании" />
            @if ($videos->isEmpty())
                <div class="empty-state"><p>Пока нет опубликованных видео бренда.</p></div>
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
