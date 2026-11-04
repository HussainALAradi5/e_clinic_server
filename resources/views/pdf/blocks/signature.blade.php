{{-- SignatureBlock: the signer's signature image (or a blank line) over their name and role, beside the clinic stamp. --}}
<table class="sign">
    <tr>
        <td>
            <div class="sign-art">
                @if ($block->signatureImage)
                    <img src="{{ $block->signatureImage }}" alt="{{ __('documents.common.signature') }}">
                @endif
            </div>
            <div class="line"><span class="name">{{ $block->name }}</span><br>{{ $block->role }} · {{ __('documents.common.signature') }}</div>
        </td>
        <td>
            @if ($block->stamp)
                <div class="sign-art">
                    @if ($block->stampImage)
                        <img src="{{ $block->stampImage }}" alt="{{ __('documents.common.stamp') }}">
                    @endif
                </div>
                <div class="line">{{ __('documents.common.stamp') }}</div>
            @endif
        </td>
    </tr>
</table>
