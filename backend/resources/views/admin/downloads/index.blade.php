@extends('admin.layout')
@section('title', 'App downloads')
@section('inline-errors', '1')
@section('content')
<x-admin.page-header title="App downloads" subtitle="Upload the Android app so officers can install it from the public download page.">
    <a class="btn btn-ghost" href="{{ route('downloads.index') }}" target="_blank" rel="noopener"><x-admin.icon name="eye" /> View download page</a>
</x-admin.page-header>

<div class="grid grid-sidebar">
    <div class="card">
        <div class="card-head"><h2>Uploaded builds</h2><span class="muted small">{{ number_format($releases->total()) }} in total</span></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Build</th><th class="nowrap">Size · SHA-256</th><th>Uploaded</th><th>Status</th><th aria-label="Actions"></th></tr></thead>
            <tbody>
            @forelse($releases as $r)
                <tr>
                    <td style="min-width:150px"><div class="cell-title">Version {{ $r->version }}</div>
                        <div class="cell-sub">{{ $variants[$r->variant] ?? ucfirst($r->variant) }}</div>
                        @if($r->notes)<div class="cell-sub">{{ \Illuminate\Support\Str::limit($r->notes, 60) }}</div>@endif</td>
                    <td><span class="nowrap">{{ $r->sizeInMb() }} MB</span>
                        <div class="cell-sub nowrap"><span class="mono" title="SHA-256 {{ $r->sha256 }}">{{ substr($r->sha256, 0, 10) }}…</span></div></td>
                    <td class="small"><div class="nowrap">{{ $r->created_at?->format('d M Y') }}</div><div class="cell-sub">{{ $r->created_at?->format('H:i') }} · {{ $r->uploader?->display_name ?? '—' }}</div></td>
                    <td>
                        @if(isset($current[$r->id]))
                            <x-admin.badge value="online" tone="success">Offered now</x-admin.badge>
                        @elseif($r->is_published)
                            <x-admin.badge value="published" tone="info">Published</x-admin.badge>
                        @else
                            <x-admin.badge value="unpublished">Unpublished</x-admin.badge>
                        @endif
                    </td>
                    <td><div class="row-actions" style="flex-wrap:nowrap">
                        <form method="POST" action="{{ route('admin.downloads.toggle', $r) }}"
                            @if($r->is_published) data-confirm="Stop offering version {{ $r->version }}?" data-confirm-body="Officers will no longer be able to download it. The previous published build, if any, will be offered instead." data-confirm-ok="Unpublish"
                            @else data-confirm="Publish version {{ $r->version }} again?" data-confirm-body="If it is the newest build for its phones, it will be offered for download." data-confirm-ok="Publish" @endif>
                            @csrf <button class="btn btn-sm btn-ghost" type="submit">{{ $r->is_published ? 'Unpublish' : 'Publish' }}</button>
                        </form>
                        <form method="POST" action="{{ route('admin.downloads.destroy', $r) }}" data-confirm="Delete version {{ $r->version }}?" data-confirm-body="The file is removed from the server. This can't be undone." data-confirm-ok="Delete">
                            @csrf @method('DELETE') <button class="btn btn-sm btn-danger" type="submit" aria-label="Delete version {{ $r->version }}"><x-admin.icon name="trash" /></button>
                        </form>
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="5"><x-admin.empty icon="download" title="No app uploaded yet">Use the form to upload the first Android build.</x-admin.empty></td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $releases->links() }}
    </div>

    <form method="POST" action="{{ route('admin.downloads.store') }}" enctype="multipart/form-data" class="card card-body">
        @csrf
        <h2 style="margin-top:0">Upload Android app</h2>
        <div class="field"><label for="variant">Phones</label>
            <select id="variant" name="variant" required>
                @foreach($variants as $value => $label)
                    <option value="{{ $value }}" @selected(old('variant', 'standard') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <div class="help">Most phones use the standard build. Upload the 32-bit build for older phones if you have one.</div>
            @error('variant')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field"><label for="version">Version</label>
            <input id="version" type="text" name="version" value="{{ old('version') }}" required maxlength="30" pattern="[0-9A-Za-z.+\-]+" placeholder="1.0.0" autocomplete="off">
            @error('version')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field"><label for="notes">What's new <span class="muted">(optional)</span></label>
            <textarea id="notes" name="notes" rows="3" maxlength="2000">{{ old('notes') }}</textarea>
            @error('notes')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field"><label for="file">APK file</label>
            <input id="file" type="file" name="file" accept=".apk,application/vnd.android.package-archive" required>
            <div class="help">The signed release .apk. Up to 200 MB (this server accepts up to {{ $uploadLimit }} per upload).</div>
            @error('file')<div class="error">{{ $message }}</div>@enderror</div>
        <button class="btn" type="submit"><x-admin.icon name="upload" /> Upload and publish</button>
        <p class="muted small" style="margin-bottom:0">The newest published build for each type of phone is the one officers download.</p>
    </form>
</div>
@endsection
