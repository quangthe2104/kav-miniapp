@extends('layouts.admin')
@section('title','Giáo viên')
@section('content')
<div class="topbar">
    <div>
        <h1>Giáo viên</h1>
        <p>Danh sách giáo viên tự đăng ký qua Zalo. Admin có thể khóa tài khoản nếu cần.</p>
    </div>
</div>
<div class="card" style="margin-bottom:1rem">
    <form method="GET" action="{{ route('admin.teachers.index') }}" style="display:flex;gap:.75rem;align-items:end;flex-wrap:wrap">
        <div style="flex:1;min-width:200px">
            <label for="q">Tìm theo tên, SĐT, Zalo ID</label>
            <input id="q" name="q" value="{{ $q ?? '' }}" placeholder="Nhập từ khóa…">
        </div>
        <button class="btn secondary" type="submit">Tìm</button>
        @if(!empty($q))
            <a class="btn ghost" href="{{ route('admin.teachers.index') }}">Xóa lọc</a>
        @endif
    </form>
</div>
<div class="card">
<table>
<thead><tr><th>ID</th><th>Tên</th><th>SĐT</th><th>Zalo ID</th><th>Trường</th><th>Status</th><th></th></tr></thead>
<tbody>
@forelse($teachers as $t)
<tr>
    <td>{{ $t->id }}</td>
    <td>{{ $t->name ?: '—' }}</td>
    <td>{{ $t->phone ?: '—' }}</td>
    <td>{{ $t->zalo_id ?: '—' }}</td>
    <td>{{ $t->school?->name ?: '—' }}</td>
    <td><span class="badge {{ $t->status === 'disabled' ? 'closed' : '' }}">{{ $t->status }}</span></td>
    <td>
        @if($t->status === 'active')
            <form method="POST" action="{{ route('admin.teachers.update', $t) }}" onsubmit="return confirm('Khóa tài khoản giáo viên này?')">
                @csrf
                @method('PATCH')
                @if(!empty($q))<input type="hidden" name="q" value="{{ $q }}">@endif
                <input type="hidden" name="status" value="disabled">
                <button class="btn danger" type="submit" style="padding:.35rem .65rem;font-size:.82rem">Khóa</button>
            </form>
        @else
            <form method="POST" action="{{ route('admin.teachers.update', $t) }}">
                @csrf
                @method('PATCH')
                @if(!empty($q))<input type="hidden" name="q" value="{{ $q }}">@endif
                <input type="hidden" name="status" value="active">
                <button class="btn ghost" type="submit" style="padding:.35rem .65rem;font-size:.82rem">Mở khóa</button>
            </form>
        @endif
    </td>
</tr>
@empty
<tr><td colspan="7" class="muted">Chưa có giáo viên nào.</td></tr>
@endforelse
</tbody>
</table>
{{ $teachers->links() }}
</div>
@endsection
