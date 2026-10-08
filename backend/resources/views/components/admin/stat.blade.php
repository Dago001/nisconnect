@props(['label', 'value', 'hint' => null, 'icon' => null, 'href' => null])
<{{ $href ? 'a href='.e($href) : 'div' }} class="card stat" @if($href) style="text-decoration:none;color:inherit" @endif>
    <div class="label">@if($icon)<x-admin.icon :name="$icon" />@endif {{ $label }}</div>
    <div class="value">{{ is_numeric($value) ? number_format($value) : $value }}</div>
    @if($hint)<div class="hint">{{ $hint }}</div>@endif
</{{ $href ? 'a' : 'div' }}>
