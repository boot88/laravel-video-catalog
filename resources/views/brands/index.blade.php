@extends('layouts.app')

@section('title', 'Бренды — '.config('site.name'))
@section('meta_description', 'Бренды и дизайнеры на Fashion Video Hub: коллекции, показы и кампании в одном каталоге.')

@section('content')
    <div class="page-shell page-shell--catalog">
        <header class="catalog-header">
            <div>
                <span class="eyebrow">Дизайнеры</span>
                <h1>Бренды</h1>
                <p>Независимые марки и ателье, чьи коллекции и показы вошли в редакцию. Названия в демо-версии вымышлены.</p>
            </div>
            <span class="catalog-header__count">{{ number_format($brands->total(), 0, ',', ' ') }} <small>брендов</small></span>
        </header>

        <div class="brand-grid">
            @foreach ($brands as $brand)
                <a class="brand-card" href="{{ route('brands.show', $brand) }}">
                    <span class="brand-card__mark">{{ mb_substr($brand->name, 0, 1) }}</span>
                    <div>
                        <span class="eyebrow">{{ $brand->country ?: 'Independent' }}</span>
                        <h2>{{ $brand->name }}</h2>
                        <p>{{ \Illuminate\Support\Str::limit($brand->description, 120) }}</p>
                        <span class="brand-card__meta">{{ $brand->collections_count }} коллекций · {{ $brand->published_videos_count }} видео</span>
                    </div>
                    <span class="brand-card__arrow" aria-hidden="true">↗</span>
                </a>
            @endforeach
        </div>

        <x-pagination :paginator="$brands" />
    </div>
@endsection
