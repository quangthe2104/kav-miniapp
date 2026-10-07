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

@if($devLogin)
<form method="POST" action="{{ route('teacher.dev-login') }}" style="margin-top:1.25rem;padding-top:1rem;border-top:1px dashed var(--line)">
    @csrf
    <p class="muted" style="margin:0 0 .5rem;font-size:.85rem">Đăng nhập thử (chỉ hiện trên máy local).</p>
    <label for="zalo_id">Zalo ID thử nghiệm</label>
    <input id="zalo_id" name="zalo_id" value="{{ old('zalo_id', 'dev-teacher-1') }}">
    <button class="btn" type="submit" style="background:var(--accent)">Đăng nhập thử</button>
</form>
@endif
@endsection

@section('footer')
<div class="auth-foot">
    <a href="{{ url('/admin/login') }}">Admin →</a>
</div>
@endsection
