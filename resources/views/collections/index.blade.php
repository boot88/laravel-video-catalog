@extends('layouts.app')

@section('title', 'Коллекции — '.config('site.name'))
@section('meta_description', 'Коллекции сезона: показы, лукбуки и кампании брендов в редакционном архиве Fashion Video Hub.')

@section('content')
    <div class="page-shell page-shell--catalog">
        <header class="catalog-header">
            <div>
                <span class="eyebrow">Сезон за сезоном</span>
                <h1>Коллекции</h1>
                <p>Архив показов и кампаний: от независимых ателье до экспериментальных дебютов.</p>
            </div>
            <span class="catalog-header__count">{{ number_format($collections->total(), 0, ',', ' ') }} <small>коллекций</small></span>
        </header>

        <div class="collection-grid">
            @foreach ($collections as $collection)
                <x-collection-card :collection="$collection" />
            @endforeach
        </div>

        <x-pagination :paginator="$collections" />
    </div>
@endsection
