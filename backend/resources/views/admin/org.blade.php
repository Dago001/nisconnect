@extends('admin.layout')
@section('title', 'Organisation')
@section('content')
<h1>Official channels</h1>
<form method="POST" action="{{ route('admin.org.channels.store') }}" style="display:flex; gap:8px; margin-bottom:12px; max-width:600px;">
    @csrf
    <input type="text" name="name" placeholder="Channel name" required>
    <input type="text" name="description" placeholder="Description">
    <button class="btn">Create channel</button>
</form>
<table>
    <tr><th>Name</th><th>Members</th><th>Add publisher (Service No.)</th></tr>
    @forelse($channels as $c)
    <tr>
        <td>{{ $c->name }}</td>
        <td>{{ $c->members_count }}</td>
        <td>
            <form method="POST" action="{{ route('admin.org.channels.publisher', $c) }}" style="display:flex; gap:6px;">
                @csrf<input type="text" name="service_number" inputmode="numeric" placeholder="123456"><button class="btn">Add</button>
            </form>
        </td>
    </tr>
    @empty
    <tr><td colspan="3" style="color:var(--grey);">No channels yet.</td></tr>
    @endforelse
</table>

<h1 style="margin-top:28px;">Organisational groups</h1>
<form method="POST" action="{{ route('admin.org.groups.store') }}" style="display:flex; gap:8px; margin-bottom:12px; max-width:600px;">
    @csrf
    <input type="text" name="name" placeholder="Group name (e.g. Software & Data Department)" required>
    <input type="text" name="description" placeholder="Description">
    <button class="btn">Create group</button>
</form>
<table>
    <tr><th>Name</th><th>Description</th></tr>
    @forelse($groups as $g)
    <tr><td>{{ $g->name }}</td><td>{{ $g->description }}</td></tr>
    @empty
    <tr><td colspan="2" style="color:var(--grey);">No organisational groups yet.</td></tr>
    @endforelse
</table>
@endsection
