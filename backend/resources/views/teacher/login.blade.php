@extends('layouts.auth')
@section('title', 'Đăng nhập giáo viên')
@section('subtitle', 'Cổng giáo viên · KavMiniApp')

@section('content')
<h1>Đăng nhập giáo viên</h1>
<p class="lead">Đăng nhập bằng Zalo — lần đầu hệ thống tự tạo tài khoản, sau đó bạn tạo lớp và lấy link gửi phụ huynh ngay.</p>

@if($zaloReady)
    <a class="btn" href="{{ route('teacher.zalo.redirect') }}" style="background:#0068ff">Đăng nhập với Zalo</a>
@else
    <div class="err" style="margin-top:1rem">Chưa cấu hình Zalo App. Liên hệ Admin để bật đăng nhập.</div>
@endif
@endsection

@section('footer')
<div class="auth-foot">
    <a href="{{ url('/admin/login') }}">Admin →</a>
</div>
@endsection
