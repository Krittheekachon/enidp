@php
    $items = $items ?? [];
    $count = $count ?? count($items);
    $tone = $tone ?? 'neutral';
    $limit = $limit ?? 6;
@endphp

<div class="summary-card is-{{ $tone }}">
    <p class="summary-title">{{ $title }}</p>
    <p class="summary-count">{{ $count }}</p>
    <p class="summary-description">{{ $description }}</p>

    @if(!empty($items) && !empty($formatter))
        <div class="summary-list">
            @foreach(array_slice($items, 0, $limit) as $item)
                <p>{{ $formatter($item) }}</p>
            @endforeach

            @if(count($items) > $limit)
                <p class="muted-note">และอีก {{ count($items) - $limit }} รายการ</p>
            @endif
        </div>
    @endif
</div>


