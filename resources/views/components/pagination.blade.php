@props(['paginator'])

@if ($paginator->hasPages())
    <nav class="pagination" aria-label="Навигация по страницам">
        @if ($paginator->onFirstPage())
            <span class="pagination__link is-disabled">← Назад</span>
        @else
            <a class="pagination__link" href="{{ $paginator->previousPageUrl() }}" rel="prev">← Назад</a>
        @endif

        @php
            $current = $paginator->currentPage();
            $last = $paginator->lastPage();
            $start = max(1, min($current - 2, max(1, $last - 4)));
            $end = min($last, $start + 4);
        @endphp

        @if ($start > 1)
            <a class="pagination__link" href="{{ $paginator->url(1) }}">1</a>
            @if ($start > 2)
                <span class="pagination__dots">…</span>
            @endif
        @endif

        @foreach (range($start, $end) as $page)
            @if ($page === $current)
                <span class="pagination__link is-active" aria-current="page">{{ $page }}</span>
            @else
                <a class="pagination__link" href="{{ $paginator->url($page) }}">{{ $page }}</a>
            @endif
        @endforeach

        @if ($end < $last)
            @if ($end < $last - 1)
                <span class="pagination__dots">…</span>
            @endif
            <a class="pagination__link" href="{{ $paginator->url($last) }}">{{ $last }}</a>
        @endif

        @if ($paginator->hasMorePages())
            <a class="pagination__link" href="{{ $paginator->nextPageUrl() }}" rel="next">Вперёд →</a>
        @else
            <span class="pagination__link is-disabled">Вперёд →</span>
        @endif
    </nav>
@endif
