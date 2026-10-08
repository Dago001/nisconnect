@props(['icon' => 'inbox', 'title' => 'Nothing here yet'])
<div class="empty">
    <x-admin.icon :name="$icon" />
    <div style="font-weight:600;color:var(--text)">{{ $title }}</div>
    @if(trim($slot) !== '')<div class="small" style="margin-top:4px">{{ $slot }}</div>@endif
</div>
