@extends('admin.layout')
@section('title', 'Import personnel')
@section('inline-errors', '1')
@section('content')
<x-admin.page-header title="Import personnel" subtitle="Add or update many personnel records at once from a CSV file."
    :crumbs="['Personnel records' => route('admin.personnel.index')]">
    <a class="btn btn-ghost" href="{{ route('admin.personnel.import.template') }}"><x-admin.icon name="download" /> Download template</a>
</x-admin.page-header>

@include('admin.personnel.provider-notice')

<div class="grid grid-sidebar">
    <div class="stack">
        <form method="POST" action="{{ route('admin.personnel.import.store') }}" enctype="multipart/form-data" class="card card-body">
            @csrf
            <div class="field"><label for="file">CSV file</label>
                <input id="file" type="file" name="file" accept=".csv,text/csv" required>
                <div class="help">Up to 5 MB, with the header row from the template. Records are matched by Service Number: new ones are created, existing ones updated.</div>
                @error('file')<div class="error">{{ $message }}</div>@enderror</div>
            <label class="check field"><input type="checkbox" name="dry_run" value="1" @checked(old('dry_run', $result['dry_run'] ?? true))>
                <span><strong>Dry run</strong> — check the file and show what would change, without saving anything.</span></label>
            <button class="btn" type="submit"><x-admin.icon name="upload" /> Check and import</button>
        </form>

        @if($result)
            <div class="card">
                <div class="card-head"><h2>{{ $result['dry_run'] ? 'Dry run preview' : 'Import result' }}</h2>
                    <span class="muted small">{{ $result['file'] }}</span></div>
                <div class="card-body">
                    <div class="stats">
                        @if($result['dry_run'])
                            <x-admin.stat label="To create" :value="$result['create']" icon="plus" />
                            <x-admin.stat label="To update" :value="$result['update']" icon="edit" />
                        @else
                            <x-admin.stat label="Created" :value="$result['created'] ?? 0" icon="plus" />
                            <x-admin.stat label="Updated" :value="$result['updated'] ?? 0" icon="edit" />
                        @endif
                        <x-admin.stat label="Unchanged" :value="$result['unchanged']" icon="check" />
                        <x-admin.stat label="Rows with errors" :value="$result['error_count']" icon="alert" />
                    </div>
                    @if(! $result['dry_run'] && ($result['suspended'] ?? 0) > 0)
                        <div class="alert alert-warning">{{ $result['suspended'] }} account(s) were suspended because their personnel status is no longer active.</div>
                    @endif
                    @if($result['dry_run'] && ($result['create'] + $result['update']) > 0)
                        <p class="muted">Happy with this? Choose the same file again, untick <em>Dry run</em> and import.</p>
                    @endif
                </div>

                @if($result['errors'])
                    <div class="card-head"><h2>Problems found</h2><span class="muted small">These rows will be skipped</span></div>
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>Row</th><th>Problem</th></tr></thead>
                        <tbody>
                        @foreach($result['errors'] as $e)
                            <tr><td class="mono nowrap">{{ $e['line'] }}</td><td>{{ $e['message'] }}</td></tr>
                        @endforeach
                        </tbody>
                    </table></div>
                    @if($result['error_count'] > count($result['errors']))
                        <div class="card-body small muted">Showing the first {{ count($result['errors']) }} of {{ $result['error_count'] }} problems.</div>
                    @endif
                @endif

                @if($result['preview'])
                    <div class="card-head"><h2>{{ $result['dry_run'] ? 'Changes to be made' : 'Changes made' }}</h2></div>
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>Row</th><th>Service No.</th><th>Name</th><th>Action</th></tr></thead>
                        <tbody>
                        @foreach($result['preview'] as $p)
                            <tr><td class="mono">{{ $p['line'] }}</td><td class="mono">{{ $p['service_number'] }}</td><td>{{ $p['name'] }}</td>
                                <td><x-admin.badge :value="$p['action']" :tone="$p['action'] === 'create' ? 'success' : 'info'" /></td></tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </div>
        @endif
    </div>

    <div class="card card-body">
        <h2 style="margin-top:0">File format</h2>
        <p class="small">The first row must be the header. Required columns are <span class="mono">service_number</span>, <span class="mono">surname</span> and <span class="mono">first_name</span>.</p>
        <ul class="small mono" style="padding-left:18px">
            @foreach($columns as $c)<li>{{ $c }}</li>@endforeach
        </ul>
        <p class="small muted">Service Numbers are read as text, so leading zeroes are kept — but spreadsheet programs may drop them. Format that column as <em>Text</em> before saving. An empty status means <em>active</em>; others are retired, suspended or dismissed.</p>
    </div>
</div>
@endsection
