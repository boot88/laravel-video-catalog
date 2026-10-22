@extends('layouts.app')

@section('title', 'Модели — '.config('site.name'))
@section('meta_description', 'Портфолио моделей: показы, кампании и съёмки. Знакомьтесь с лицами новой редакции Fashion Video Hub.')

@section('content')
    <div class="page-shell page-shell--catalog">
        <header class="catalog-header">
            <div>
                <span class="eyebrow">Лица редакции</span>
                <h1>Модели</h1>
                <p>Портфолио участниц и участников: показы, кампании, editorial и backstage. Все профили — демонстрационные.</p>
            </div>
            <span class="catalog-header__count">{{ number_format($models->total(), 0, ',', ' ') }} <small>профилей</small></span>
        </header>

        <div class="model-grid">
            @foreach ($models as $index => $model)
                <x-model-card :model="$model" :index="$models->firstItem() + $index" />
            @endforeach
        </div>

        <x-pagination :paginator="$models" />
    </div>
@endsection
