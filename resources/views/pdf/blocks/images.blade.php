{{-- ImagesBlock: films and strips, two per row, each with its caption. --}}
@if ($block->images !== [])
    <div class="block" style="page-break-inside: avoid;">
        <div class="block-title">{{ $block->heading }}</div>
        <table class="gallery">
            @foreach (array_chunk($block->images, 2) as $pair)
                <tr>
                    @foreach ($pair as $image)
                        <td>
                            <img src="{{ $image->dataUri }}" alt="{{ $image->caption }}">
                            <div class="cap">{{ $image->caption }}</div>
                        </td>
                    @endforeach
                    @if (count($pair) === 1)<td></td>@endif
                </tr>
            @endforeach
        </table>
    </div>
@endif
