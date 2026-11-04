{{-- TableBlock: brand header row (repeated on every page), striped rows, numbers right-aligned. --}}
<div class="block">
    @if ($block->heading !== '')
        <div class="block-title">{{ $block->heading }}</div>
    @endif
    <table class="grid">
        <thead>
            <tr>
                @foreach ($block->headers as $i => $header)
                    <th @class(['num' => in_array($i, $block->numeric, true)])>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @php($any = false)
            @foreach ($block->rows as $row)
                @php($any = true)
                <tr>
                    @foreach ($row as $i => $cell)
                        <td @class(['num' => in_array($i, $block->numeric, true)])>{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
            @unless ($any)
                <tr><td class="empty" colspan="{{ count($block->headers) }}">{{ $block->empty }}</td></tr>
            @endunless
        </tbody>
    </table>
</div>
