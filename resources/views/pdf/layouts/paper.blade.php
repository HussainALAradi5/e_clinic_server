{{--
    Letterhead shared by every generated PDF: a brand header and a footer repeated on each page, and the brand
    styles. Pages fill @section('content'). Variables: $clinic (profile), $logo (data URI), $colors, $title,
    $reference, $generatedAt, $printedBy.
--}}
@php($c = $colors)
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $title }} {{ $reference }}</title>
<style>
    @page { margin: 128px 42px 70px 42px; }
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: {{ $c['ink'] }}; line-height: 1.45; margin: 0; }
    table { border-collapse: collapse; width: 100%; }
    p { margin: 0 0 6px; }

    header.letterhead { position: fixed; top: -116px; left: 0; right: 0; height: 86px; }
    header.letterhead .band { height: 5px; background: {{ $c['brand'] }}; border-radius: 3px; }
    header.letterhead .band i { display: block; width: 64px; height: 5px; background: {{ $c['accent'] }}; border-radius: 3px; float: right; }
    header.letterhead td { vertical-align: middle; padding-top: 12px; }
    .brand-name { font-size: 15pt; font-weight: bold; color: {{ $c['brand_dark'] }}; letter-spacing: -0.2px; }
    .brand-tag { font-size: 7.5pt; color: {{ $c['muted'] }}; }
    .head-ref { text-align: right; font-size: 7.5pt; color: {{ $c['muted'] }}; line-height: 1.5; }
    .head-ref b { color: {{ $c['ink'] }}; font-size: 8.5pt; }

    footer.footline { position: fixed; bottom: -48px; left: 0; right: 0; height: 30px; border-top: 1px solid {{ $c['line'] }}; padding-top: 7px; font-size: 7pt; color: {{ $c['muted'] }}; }

    .title-row td { vertical-align: bottom; }
    h1 { font-size: 19pt; margin: 0; color: {{ $c['ink'] }}; letter-spacing: -0.3px; }
    .eyebrow { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 1.2px; color: {{ $c['brand'] }}; font-weight: bold; margin-bottom: 2px; }
    .badge { display: inline-block; padding: 3px 9px; border-radius: 10px; font-size: 7.5pt; font-weight: bold; margin-left: 4px; }

    .tiles { margin: 14px 0 6px; }
    .tiles td { background: {{ $c['brand_soft'] }}; padding: 9px 12px; border-left: 3px solid {{ $c['brand'] }}; width: 25%; vertical-align: top; }
    .tiles td.gap { background: none; border: 0; width: 8px; padding: 0; }
    .tile-label { font-size: 7pt; text-transform: uppercase; letter-spacing: 0.8px; color: {{ $c['muted'] }}; }
    .tile-value { font-size: 10.5pt; font-weight: bold; color: {{ $c['brand_dark'] }}; }

    .block { margin-top: 16px; page-break-inside: auto; }
    .block-title { font-size: 8pt; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; color: {{ $c['brand'] }}; border-bottom: 1.5px solid {{ $c['brand_soft'] }}; padding-bottom: 4px; margin-bottom: 8px; }

    .facts td { padding: 4px 0; vertical-align: top; border-bottom: 1px solid {{ $c['line'] }}; }
    .facts .label { color: {{ $c['muted'] }}; width: 17%; padding-right: 8px; font-size: 8.5pt; }
    .facts .value { width: 33%; padding-right: 14px; font-weight: bold; }

    .grid th { background: {{ $c['brand'] }}; color: #fff; text-align: left; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.5px; padding: 6px 7px; }
    .grid th:first-child { border-top-left-radius: 4px; }
    .grid th:last-child { border-top-right-radius: 4px; }
    .grid td { padding: 5px 7px; border-bottom: 1px solid {{ $c['line'] }}; vertical-align: top; font-size: 8.5pt; }
    .grid tr:nth-child(even) td { background: {{ $c['stripe'] }}; }
    .grid thead { display: table-header-group; }
    .grid tr { page-break-inside: avoid; }
    .num { text-align: right; white-space: nowrap; }
    .empty { color: {{ $c['muted'] }}; font-style: italic; padding: 10px 0; }

    .totals { width: 46%; margin-left: 54%; margin-top: 8px; }
    .totals td { padding: 4px 7px; font-size: 9pt; }
    .totals .grand td { background: {{ $c['brand'] }}; color: #fff; font-weight: bold; font-size: 10.5pt; padding: 7px; }

    .notice { padding: 9px 12px; border-radius: 5px; margin-top: 14px; border-left: 4px solid; }
    .notice b { display: block; font-size: 9.5pt; margin-bottom: 2px; }

    .gallery td { width: 50%; padding: 0 6px 10px 0; vertical-align: top; text-align: center; }
    .gallery img { width: 100%; border-radius: 5px; background: #000; }
    .gallery .cap { font-size: 7.5pt; color: {{ $c['muted'] }}; margin-top: 3px; }

    .sign { margin-top: 14px; page-break-inside: avoid; }
    .sign td { width: 50%; vertical-align: bottom; }
    .sign .sign-art { height: 62px; width: 82%; text-align: left; }
    .sign .sign-art img { max-height: 60px; max-width: 200px; }
    .sign .line { border-top: 1px solid {{ $c['ink'] }}; width: 82%; padding-top: 4px; font-size: 8pt; color: {{ $c['muted'] }}; }
    .sign .name { font-weight: bold; font-size: 10pt; color: {{ $c['ink'] }}; }
    .verify .barcode { width: 190px; height: 44px; }

    .verify { margin-top: 20px; background: {{ $c['stripe'] }}; border: 1px solid {{ $c['line'] }}; border-radius: 6px; page-break-inside: avoid; }
    .verify td { padding: 10px 12px; vertical-align: middle; }
    .verify img { width: 86px; height: 86px; }
    .verify .by { margin-top: 4px; font-size: 7.5pt; color: {{ $c['muted'] }}; }
    .verify .ref { font-weight: bold; font-size: 10pt; color: {{ $c['brand_dark'] }}; }
</style>
</head>
<body>
<header class="letterhead">
    <div class="band"><i></i></div>
    <table>
        <tr>
            <td style="width: 40px;"><img src="{{ $logo }}" style="max-width: 40px; max-height: 40px;" alt=""></td>
            <td>
                <div class="brand-name">{{ $clinic['name'] }}</div>
                <div class="brand-tag">{{ $clinic['tagline'] }}</div>
            </td>
            <td class="head-ref">
                <b>{{ $reference }}</b><br>
                {{ $title }}<br>
                {{ __('documents.letterhead.printed', ['at' => $generatedAt]) }}@if ($printedBy)<br>{{ __('documents.letterhead.printed_by', ['name' => $printedBy]) }}@endif
            </td>
        </tr>
    </table>
</header>
<footer class="footline">
    {{ $clinic['name'] }} · {{ $clinic['address'] }}<br>
    {{ $clinic['phone'] }} · {{ $clinic['email'] }} · {{ $clinic['website'] }}
</footer>
<main>
    @yield('content')
</main>
</body>
</html>
