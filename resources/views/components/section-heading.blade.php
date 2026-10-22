@props(['eyebrow' => null, 'title', 'link' => null, 'linkLabel' => 'Смотреть все', 'id' => null])

<div class="section-heading">
    <div>
        @if ($eyebrow)
            <span class="eyebrow">{{ $eyebrow }}</span>
        @endif
        <h2 @if ($id) id="{{ $id }}" @endif>{{ $title }}</h2>
    </div>
    @if ($link)
        <a class="text-link" href="{{ $link }}">{{ $linkLabel }} <span aria-hidden="true">↗</span></a>
    @endif
</div>
