@extends('layouts.app')

@section('title', 'Избранное — '.config('site.name'))
@section('meta_description', 'Ваша личная подборка показов, кампаний и портфолио на Fashion Video Hub.')
@section('robots', 'noindex,nofollow')

@section('content')
    <div class="page-shell page-shell--catalog">
        <header class="catalog-header">
            <div>
                <span class="eyebrow">Личная подборка</span>
                <h1>Избранное</h1>
                <p>Сохраняйте работы, к которым хотите вернуться. Подборка хранится в этой сессии браузера.</p>
            </div>
            <span class="catalog-header__count">{{ number_format($videos->count(), 0, ',', ' ') }} <small>работ</small></span>
        </header>

        @if ($videos->isEmpty())
            <div class="empty-state">
                <span class="eyebrow">Пока пусто</span>
                <h2>Добавьте первый показ</h2>
                <p>Нажмите на сердечко в карточке видео или на странице просмотра.</p>
                <a class="button button--dark" href="{{ route('videos.browse') }}">Смотреть каталог</a>
            </div>
        @else
            <div class="video-grid video-grid--catalog">
                @foreach ($videos as $video)
                    <x-video-card :video="$video" />
                @endforeach
            </div>
        @endif
    </div>
@endsection
