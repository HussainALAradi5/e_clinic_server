{{-- TotalsBlock: right-aligned money lines ending with the highlighted grand line. --}}
<table class="totals">
    @foreach ($block->lines as $line)
        <tr><td>{{ $line->label }}</td><td class="num">{{ $line->value }}</td></tr>
    @endforeach
    <tr class="grand"><td>{{ $block->grand->label }}</td><td class="num">{{ $block->grand->value }}</td></tr>
</table>
