{{-- NoticeBlock: coloured call-out. --}}
<div class="notice" style="background: {{ $block->tone->wash() }}; border-color: {{ $block->tone->ink() }}; color: {{ $block->tone->ink() }};">
    <b>{{ $block->title }}</b>
    {{ $block->text }}
</div>
