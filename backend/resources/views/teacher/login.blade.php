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

<div style="margin-top:1.25rem;padding-top:1rem;border-top:1px solid var(--line)">
    <p class="muted" style="margin:0 0 .65rem;font-size:.88rem">Hoặc thử giao diện Mini App trên trình duyệt:</p>
    <a class="btn" href="{{ url('/miniapp') }}" target="_blank" rel="noopener" style="background:var(--accent);margin-top:0">Mở Mini App test →</a>
</div>

@if($devLogin)
<details style="margin-top:1.25rem;border:1px solid var(--line);border-radius:12px;padding:.75rem .9rem;background:#f8fafc">
    <summary style="cursor:pointer;font-weight:600;color:var(--brand)">Dev login (local)</summary>
    <p class="muted" style="font-size:.85rem;margin:.65rem 0">Chỉ hiện khi <code>ZALO_DEV_LOGIN=true</code>. Tài khoản mới được tạo tự động.</p>
    <form method="POST" action="{{ route('teacher.dev-login') }}">
        @csrf
        <label for="phone">SĐT (demo: 0900000001)</label>
        <input id="phone" name="phone" value="{{ old('phone', '0900000001') }}">
        <label for="zalo_id">hoặc Zalo ID (demo: dev-teacher-1)</label>
        <input id="zalo_id" name="zalo_id" value="{{ old('zalo_id') }}">
        <button class="btn" type="submit" style="background:var(--accent)">Đăng nhập demo</button>
    </form>
</details>
@endif
@endsection

@section('footer')
<div class="auth-foot">
    <a href="{{ url('/admin/login') }}">Admin →</a>
</div>
@endsection
