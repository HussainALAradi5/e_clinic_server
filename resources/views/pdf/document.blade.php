{{--
    One record as a document (sick leave, result, invoice…): eyebrow + title + badges, key fact tiles, the blocks
    in order, then the verification card. Variables: $page (DocumentPage), $qr (data URI or null),
    plus the letterhead variables of layouts.paper.
--}}
@extends('pdf.layouts.paper')

@section('content')
    <table class="title-row">
        <tr>
            <td>
                <div class="eyebrow">{{ __('documents.letterhead.eyebrow', ['clinic' => $clinic['name']]) }}</div>
                <h1>{{ $page->title }}</h1>
            </td>
            <td style="text-align: right;">
                @foreach ($page->badges as $badge)
                    <span class="badge" style="color: {{ $badge->tone->ink() }}; background: {{ $badge->tone->wash() }};">{{ $badge->label }}</span>
                @endforeach
            </td>
        </tr>
    </table>

    @if ($page->highlights !== [])
        <table class="tiles">
            <tr>
                @foreach ($page->highlights as $fact)
                    @unless ($loop->first)<td class="gap"></td>@endunless
                    @php($tone = $fact->tone ?? \App\Enums\Documents\DocumentTone::Success)
                    <td style="background: {{ $tone->wash() }}; border-left-color: {{ $tone->ink() }};">
                        <div class="tile-label">{{ $fact->label }}</div>
                        <div class="tile-value" style="color: {{ $tone->ink() }};">{{ $fact->value }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    @foreach ($page->blocks as $block)
        @include($block->view(), ['block' => $block])
    @endforeach

    @if ($qr !== null || $page->verifyNote !== '')
        <table class="verify">
            <tr>
                @if ($qr !== null)
                    <td style="width: 100px;"><img src="{{ $qr }}" alt="{{ __('documents.letterhead.verification') }}"></td>
                @endif
                <td>
                    <div class="ref">{{ $page->reference }}</div>
                    <div>{{ $page->verifyNote }}</div>
                    <div class="by">
                        @if ($page->issuedBy){{ __('documents.letterhead.issued_by') }} <b>{{ $page->issuedBy }}</b> · @endif
                        {{ __('documents.letterhead.printed', ['at' => $generatedAt]) }}@if ($printedBy) {{ __('documents.letterhead.printed_by', ['name' => '']) }}<b>{{ $printedBy }}</b>@endif
                    </div>
                </td>
            </tr>
        </table>
    @endif
@endsection
