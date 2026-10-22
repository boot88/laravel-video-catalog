@extends('layouts.app')

@section('title', $model->name.' — портфолио — '.config('site.name'))
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags((string) ($model->bio ?: 'Портфолио модели '.$model->name.': показы, кампании и съёмки.')), 160))
@section('og_image', $model->photo_url)

@section('content')
    <div class="page-shell page-shell--profile">
        <nav class="breadcrumbs" aria-label="Хлебные крошки">
            <a href="{{ route('video.index') }}">Главная</a><span>/</span>
            <a href="{{ route('models.index') }}">Модели</a><span>/</span>
            <span aria-current="page">{{ $model->name }}</span>
        </nav>

        <header class="profile-hero">
            <div class="profile-hero__photo">
                <img src="{{ $model->photo_url }}" alt="{{ $model->name }}" fetchpriority="high">
                <span class="profile-hero__credit">Фото: {{ $model->photo_credit ?: 'CC0 / CC BY' }}</span>
            </div>
            <div class="profile-hero__copy">
                <span class="eyebrow">Портфолио</span>
                <h1>{{ $model->name }}</h1>
                <p>{{ $model->bio ?: 'Демонстрационный профиль модели. Показы, кампании и editorial-съёмки в подборке ниже.' }}</p>

                <dl class="profile-facts">
                    @if ($model->city)
                        <div><dt>Город</dt><dd>{{ $model->city }}</dd></div>
                    @endif
                    @if ($model->height)
                        <div><dt>Рост</dt><dd>{{ $model->height }} см</dd></div>
                    @endif
                    <div><dt>В редакции</dt><dd>{{ $videos->total() }} видео</dd></div>
                    @if ($model->instagram)
                        <div><dt>Соцсети</dt><dd><a href="{{ $model->instagram }}" target="_blank" rel="noopener">Instagram</a></dd></div>
                    @endif
                </dl>

                <div class="profile-hero__note">
                    <span class="eyebrow">Права</span>
                    <p>Профиль демонстрационный. Для публикации реального портфолио нужно подтверждение согласия модели и прав на фото.</p>
                </div>
            </div>
        </header>

        <section class="section">
            <x-section-heading eyebrow="Работы" title="С участием {{ $model->name }}" />
            @if ($videos->isEmpty())
                <div class="empty-state"><p>Пока нет опубликованных работ.</p></div>
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
