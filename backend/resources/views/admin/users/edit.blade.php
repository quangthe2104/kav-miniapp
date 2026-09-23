@extends('layouts.admin')
@section('title','Sửa tài khoản admin')
@section('content')
<div class="topbar">
    <div>
        <h1>Sửa admin #{{ $user->id }}</h1>
        <p>{{ $user->email }} — để trống mật khẩu nếu không đổi.</p>
    </div>
    <a class="btn secondary" href="{{ route('admin.users.index') }}">← Danh sách</a>
</div>
<div class="card">
<form method="POST" action="{{ route('admin.users.update', $user) }}">
    @csrf
    @method('PUT')
    <label for="name">Tên</label>
    <input id="name" name="name" value="{{ old('name', $user->name) }}" required>
    <label for="email">Email</label>
    <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required>
    <label for="password">Mật khẩu mới (tuỳ chọn)</label>
    <input id="password" type="password" name="password" autocomplete="new-password">
    <label for="password_confirmation">Xác nhận mật khẩu mới</label>
    <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password">
    <label for="status">Trạng thái</label>
    <select id="status" name="status" @disabled($user->id === auth()->id())>
        @foreach(['active', 'disabled'] as $st)
            <option value="{{ $st }}" @selected(old('status', $user->status) === $st)>{{ $st }}</option>
        @endforeach
    </select>
    @if($user->id === auth()->id())
        <input type="hidden" name="status" value="active">
        <p class="muted" style="margin-top:.35rem">Không thể tự khóa tài khoản đang đăng nhập.</p>
    @endif
    <p style="margin-top:1rem"><button class="btn" type="submit">Cập nhật</button></p>
</form>
</div>
@endsection
