{{-- FactsBlock: label / value pairs, two per row; a wide fact takes the whole row. --}}
<div class="block">
    <div class="block-title">{{ $block->heading }}</div>
    <table class="facts">
        @php($pending = null)
        @foreach ($block->facts as $fact)
            @if ($fact->wide)
                @if ($pending)
                    <tr><td class="label">{{ $pending->label }}</td><td class="value" colspan="3">@include('pdf.blocks.fact-value', ['fact' => $pending])</td></tr>
                    @php($pending = null)
                @endif
                <tr><td class="label">{{ $fact->label }}</td><td class="value" colspan="3">@include('pdf.blocks.fact-value', ['fact' => $fact])</td></tr>
            @elseif ($pending === null)
                @php($pending = $fact)
            @else
                <tr>
                    <td class="label">{{ $pending->label }}</td><td class="value">@include('pdf.blocks.fact-value', ['fact' => $pending])</td>
                    <td class="label">{{ $fact->label }}</td><td class="value">@include('pdf.blocks.fact-value', ['fact' => $fact])</td>
                </tr>
                @php($pending = null)
            @endif
        @endforeach
        @if ($pending)
            <tr><td class="label">{{ $pending->label }}</td><td class="value" colspan="3">@include('pdf.blocks.fact-value', ['fact' => $pending])</td></tr>
        @endif
    </table>
</div>
