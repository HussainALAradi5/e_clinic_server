{{--
    A list export or report: title, what it shows, then one striped table whose header repeats on every page.
    Variables: $heading, $subtitle, $table (TableBlock), plus the letterhead variables of layouts.paper.
--}}
@extends('pdf.layouts.paper')

@section('content')
    <div class="eyebrow">{{ __('documents.letterhead.export', ['clinic' => $clinic['name']]) }}</div>
    <h1>{{ $heading }}</h1>
    <p style="color: {{ $colors['muted'] }}; margin-top: 2px;">{{ $subtitle }}</p>
    @include($table->view(), ['block' => $table])
@endsection
