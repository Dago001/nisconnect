@props(['series', 'height' => 160, 'alt' => false, 'label' => 'Chart'])
{{-- series: ['label' => value, ...] (e.g. dates => counts). Rendered server-side as SVG. --}}
@php
    $values = array_values($series); $labels = array_keys($series);
    $n = max(count($values), 1); $max = max(max($values ?: [0]), 1);
    $w = 600; $h = $height; $pad = 22; $gap = 4;
    $bw = ($w - $pad) / $n - $gap;
@endphp
<svg class="chart" viewBox="0 0 {{ $w }} {{ $h }}" role="img" aria-label="{{ $label }}">
    <title>{{ $label }}</title>
    <line class="axis" x1="0" y1="{{ $h - $pad }}" x2="{{ $w }}" y2="{{ $h - $pad }}" />
    @foreach($values as $i => $v)
        @php $bh = ($h - $pad - 14) * $v / $max; $x = $pad / 2 + $i * ($bw + $gap); @endphp
        <rect class="bar {{ $alt ? 'alt' : '' }}" x="{{ round($x, 1) }}" y="{{ round($h - $pad - $bh, 1) }}" width="{{ round(max($bw, 1), 1) }}" height="{{ round(max($bh, 0), 1) }}" rx="3">
            <title>{{ $labels[$i] }}: {{ number_format($v) }}</title>
        </rect>
        @if($n <= 16 || $i % (int) ceil($n / 8) === 0)
            <text x="{{ round($x + $bw / 2, 1) }}" y="{{ $h - 6 }}" text-anchor="middle">{{ $labels[$i] }}</text>
        @endif
    @endforeach
</svg>
