{{-- A fact's value: a coloured badge when the fact has a tone (sex, state), plain text otherwise. --}}
@if ($fact->tone)
    <span class="badge" style="margin-left: 0; color: {{ $fact->tone->ink() }}; background: {{ $fact->tone->wash() }};">{{ $fact->value }}</span>
@else
    {{ $fact->value }}
@endif
