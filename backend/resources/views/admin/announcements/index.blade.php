@extends('admin.layout')
@section('title', 'Announcements')
@section('inline-errors', '1')
@section('content')
<x-admin.page-header title="Announcements" subtitle="Send a notice to officers. It appears in their in-app notifications and as a push alert on their devices." />

<div class="grid grid-sidebar">
    <div class="card">
        <div class="card-head"><h2>Sent announcements</h2><span class="muted small">{{ number_format($history->total()) }} in total</span></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Announcement</th><th>Audience</th><th>Recipients</th><th>Sent by</th><th>Date</th></tr></thead>
            <tbody>
            @forelse($history as $a)
                <tr>
                    <td><div class="cell-title">{{ $a->title }} @if($a->priority === 'urgent')<x-admin.badge value="urgent" />@endif</div>
                        <div class="cell-sub">{{ \Illuminate\Support\Str::limit($a->body, 90) }}</div></td>
                    <td>{{ $labels[$a->audience_type] ?? ucfirst($a->audience_type) }}@if($a->audience_value)<div class="cell-sub">{{ $a->audience_value }}</div>@endif</td>
                    <td>{{ number_format($a->recipients_count) }}</td>
                    <td>{{ $a->sender?->display_name ?? '—' }}</td>
                    <td class="nowrap small">{{ $a->created_at?->format('d M Y, H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="5"><x-admin.empty icon="bell" title="No announcements yet">Use the form to send the first one.</x-admin.empty></td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $history->links() }}
    </div>

    <form method="POST" action="{{ route('admin.announcements.store') }}" class="card card-body"
        data-confirm="Send this announcement?" data-confirm-body="It will be delivered straight away and can't be recalled." data-confirm-ok="Send">
        @csrf
        <h2 style="margin-top:0">New announcement</h2>
        <div class="field"><label for="title">Title</label>
            <input id="title" type="text" name="title" value="{{ old('title') }}" required maxlength="120">
            @error('title')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field"><label for="body">Message</label>
            <textarea id="body" name="body" rows="6" required maxlength="2000">{{ old('body') }}</textarea>
            <div class="help">Up to 2,000 characters. Don't include classified or personal information.</div>
            @error('body')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field"><label for="audience">Send to</label>
            <select id="audience" name="audience" required>
                <option value="all" @selected(old('audience', 'all') === 'all')>All officers ({{ number_format($everyone) }} active)</option>
                @foreach($options as $type => $values)
                    @if($values->isNotEmpty())
                        <optgroup label="{{ $labels[$type] }}">
                            @foreach($values as $v)
                                <option value="{{ $type }}|{{ $v }}" @selected(old('audience') === $type.'|'.$v)>{{ $labels[$type] }}: {{ $v }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                @endforeach
            </select>
            <div class="help">Only officers with an active account receive it.</div>
            @error('audience')<div class="error">{{ $message }}</div>@enderror</div>
        <fieldset class="field" style="border:0;padding:0;margin:0 0 14px">
            <legend style="font-weight:600;margin-bottom:6px">Priority</legend>
            <label class="check"><input type="radio" name="priority" value="normal" @checked(old('priority', 'normal') === 'normal')> <span>Normal</span></label>
            <label class="check"><input type="radio" name="priority" value="urgent" @checked(old('priority') === 'urgent')> <span>Urgent — highlighted for officers</span></label>
            @error('priority')<div class="error">{{ $message }}</div>@enderror
        </fieldset>
        <button class="btn" type="submit"><x-admin.icon name="megaphone" /> Send announcement</button>
    </form>
</div>
@endsection
