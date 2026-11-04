{{-- TextBlock: a titled run of paragraphs (line breaks kept). --}}
@if ($block->paragraphs !== [])
    <div class="block">
        <div class="block-title">{{ $block->heading }}</div>
        @foreach ($block->paragraphs as $paragraph)
            <p>{!! nl2br(e($paragraph)) !!}</p>
        @endforeach
    </div>
@endif
