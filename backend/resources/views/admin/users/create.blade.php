@extends('layouts.admin')
@section('title','Tạo tài khoản admin')
@section('content')
<div class="topbar">
    <div>
        <h1>Tạo tài khoản admin</h1>
        <p>Chỉ admin hiện có mới được tạo tài khoản mới.</p>
    </div>
    <a class="btn secondary" href="{{ route('admin.users.index') }}">← Danh sách</a>
</div>
<div class="card">
<form method="POST" action="{{ route('admin.users.store') }}">
    @csrf
    <label for="name">Tên</label>
    <input id="name" name="name" value="{{ old('name') }}" required>
    <label for="email">Email</label>
    <input id="email" type="email" name="email" value="{{ old('email') }}" required>
    <label for="password">Mật khẩu</label>
    <input id="password" type="password" name="password" required autocomplete="new-password">
    <label for="password_confirmation">Xác nhận mật khẩu</label>
    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
    <label for="status">Trạng thái</label>
    <select id="status" name="status">
        <option value="active" @selected(old('status', 'active') === 'active')>active</option>
        <option value="disabled" @selected(old('status') === 'disabled')>disabled</option>
    </select>
    <p style="margin-top:1rem"><button class="btn" type="submit">Tạo</button></p>
</form>
</div>
@endsection
