@extends('layouts.auth')
@section('title', 'Admin đăng nhập')
@section('subtitle', 'Admin Console · KavMiniApp')

@section('content')
<h1>Đăng nhập quản trị</h1>
<p class="lead">Dành cho quản trị viên Khan Academy Vietnam.</p>

<form method="POST" action="{{ route('login') }}">
    @csrf
    <label for="email">Email</label>
    <input
        id="email"
        type="email"
        name="email"
        value="{{ old('email', config('app.debug') ? 'admin@kav.local' : '') }}"
        required
        autofocus
        autocomplete="username"
    >

    <label for="password">Mật khẩu</label>
    <input
        id="password"
        type="password"
        name="password"
        required
        autocomplete="current-password"
    >

    <label class="checkbox">
        <input type="checkbox" name="remember" @checked(old('remember'))>
        Ghi nhớ đăng nhập
    </label>

    <button class="btn" type="submit">Đăng nhập</button>
</form>

@if(config('app.debug'))
    <p class="demo-hint">Demo: <strong>admin@kav.local</strong> / mật khẩu seeder.</p>
@endif
@endsection
