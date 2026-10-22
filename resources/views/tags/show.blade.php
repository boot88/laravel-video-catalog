@extends('layouts.app')

@section('title', '#'.$tag->name.' — '.config('site.name'))
@section('meta_description', 'Видео с тегом #'.$tag->name.' на Fashion Video Hub.')

@section('content')
    <div class="page-shell page-shell--catalog">
        <header class="catalog-header">
            <div>
                <span class="eyebrow">Тег</span>
                <h1>#{{ $tag->name }}</h1>
                <p>Все опубликованные работы с этим тегом.</p>
            </div>
            <span class="catalog-header__count">{{ number_format($videos->total(), 0, ',', ' ') }} <small>работ</small></span>
        </header>

        @if ($videos->isEmpty())
            <div class="empty-state"><p>Пока нет видео с этим тегом.</p></div>
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
