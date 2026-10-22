@extends('layouts.app')

@section('title', 'Источники и лицензии — '.config('site.name'))
@section('meta_description', 'Атрибуция демонстрационных фотографий и видео: авторы, лицензии и ссылки на оригиналы.')

@section('content')
    <div class="page-shell page-shell--prose">
        <header class="prose-hero">
            <span class="eyebrow">Права</span>
            <h1>Источники и лицензии</h1>
            <p>Все демонстрационные фото и видео взяты из открытых источников под CC0 / CC BY / CC BY-SA. Ниже — авторы, лицензии и ссылки на оригиналы. Производные фрагменты видео распространяются под той же лицензией, что и оригинал.</p>
        </header>

        <section class="credits-section">
            <h2>Фотографии моделей</h2>
            <div class="credits-list">
                @foreach ($photos as $photo)
                    <article>
                        <img src="{{ asset('media/models/'.$photo['file']) }}" alt="" loading="lazy">
                        <div>
                            <h3>{{ $photo['title'] }}</h3>
                            <p>{{ $photo['author'] }} · {{ $photo['license'] }}</p>
                            <div class="credits-links">
                                <a href="{{ $photo['source_url'] }}" target="_blank" rel="noopener">Оригинал ↗</a>
                                <a href="{{ $photo['license_url'] }}" target="_blank" rel="noopener">Лицензия ↗</a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="credits-section">
            <h2>Видео</h2>
            <div class="credits-list credits-list--video">
                @foreach ($videos as $video)
                    <article>
                        <div>
                            <h3>{{ $video['title'] }}</h3>
                            <p>{{ $video['author'] }} · {{ $video['license'] }}</p>
                            @if (! empty($video['note']))
                                <p class="muted">{{ $video['note'] }}</p>
                            @endif
                            <div class="credits-links">
                                <a href="{{ $video['source_url'] }}" target="_blank" rel="noopener">Оригинал ↗</a>
                                <a href="{{ $video['license_url'] }}" target="_blank" rel="noopener">Лицензия ↗</a>
                                <a href="{{ asset('media/videos/'.$video['file']) }}" target="_blank" rel="noopener">Фрагмент ↗</a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <p class="prose-note">Если вы правообладатель и считаете, что материал использован некорректно, напишите на <a href="mailto:{{ config('site.contact_email') }}">{{ config('site.contact_email') }}</a>.</p>
    </div>
@endsection
