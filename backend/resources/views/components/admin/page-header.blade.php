@props(['title', 'subtitle' => null, 'crumbs' => []])
{{-- crumbs: ['Label' => url, ...]; the page title is appended automatically --}}
<div class="page-head">
    <div>
        @if($crumbs)
            <nav class="crumbs" aria-label="Breadcrumb">
                @foreach($crumbs as $label => $url)<a href="{{ $url }}">{{ $label }}</a> / @endforeach
                <span>{{ $title }}</span>
            </nav>
        @endif
        <h1>{{ $title }}</h1>
        @if($subtitle)<p>{{ $subtitle }}</p>@endif
    </div>
    @if(trim($slot) !== '')<div class="actions">{{ $slot }}</div>@endif
</div>
