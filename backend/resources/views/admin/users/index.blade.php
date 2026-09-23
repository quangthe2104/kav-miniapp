@extends('layouts.admin')
@section('title','Tài khoản admin')
@section('content')
<div class="topbar">
    <div>
        <h1>Tài khoản admin</h1>
        <p>Tạo, đặt lại mật khẩu và khóa tài khoản quản trị. Không xóa cứng.</p>
    </div>
    <a class="btn" href="{{ route('admin.users.create') }}">+ Tạo admin</a>
</div>
<div class="card">
<table>
<thead><tr><th>ID</th><th>Tên</th><th>Email</th><th>Status</th><th></th></tr></thead>
<tbody>
@forelse($users as $u)
<tr>
    <td>{{ $u->id }}</td>
    <td>{{ $u->name }}</td>
    <td>{{ $u->email }}</td>
    <td><span class="badge {{ $u->status === 'disabled' ? 'closed' : '' }}">{{ $u->status }}</span></td>
    <td style="display:flex;gap:.4rem;flex-wrap:wrap">
        <a class="btn ghost" href="{{ route('admin.users.edit', $u) }}" style="padding:.35rem .65rem;font-size:.82rem">Sửa</a>
        @if($u->status === 'active' && $u->id !== auth()->id())
            <form method="POST" action="{{ route('admin.users.disable', $u) }}" onsubmit="return confirm('Khóa tài khoản admin này?')">
                @csrf
                @method('PATCH')
                <button class="btn danger" type="submit" style="padding:.35rem .65rem;font-size:.82rem">Khóa</button>
            </form>
        @endif
    </td>
</tr>
@empty
<tr><td colspan="5" class="muted">Chưa có tài khoản admin.</td></tr>
@endforelse
</tbody>
</table>
{{ $users->links() }}
</div>
@endsection
