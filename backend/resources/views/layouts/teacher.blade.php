<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Giáo viên') — {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Montserrat:400,500,600,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #0a2a66;
            --muted: #5b6b8c;
            --line: #d8e0ef;
            --panel: #ffffff;
            --bg0: #eef2fa;
            --bg1: #f7f9fd;
            --brand: #0a2a66;
            --brand-2: #0d3888;
            --accent: #14bf96;
            --accent-2: #0fa883;
            --brand-soft: #e5f9f3;
            --navy-soft: #e8eef8;
            --vote-agree: #14bf96;
            --vote-disagree: #b42318;
            --danger: #b42318;
            --warn: #9a6700;
            --warn-soft: #fff4d6;
            --shadow: 0 10px 30px rgba(10, 42, 102, .08);
            --radius: 16px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Montserrat", system-ui, sans-serif;
            color: var(--ink);
            background:
                radial-gradient(900px 420px at 0% -10%, rgba(20,191,150,.16), transparent 60%),
                radial-gradient(700px 380px at 100% 0%, rgba(10,42,102,.10), transparent 55%),
                linear-gradient(180deg, var(--bg0), var(--bg1));
            display: flex;
            flex-direction: column;
        }
        a { color: var(--accent-2); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .t-nav {
            background: linear-gradient(185deg, #071c45 0%, #0a2a66 55%, #0f3b8a 100%);
            color: #eef3ff;
            box-shadow: 0 8px 24px rgba(7, 28, 69, .22);
            position: sticky;
            top: 0;
            z-index: 40;
        }
        .t-nav-inner {
            max-width: 1100px;
            margin: 0 auto;
            padding: .85rem 1.15rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .75rem 1rem;
        }
        .t-brand {
            display: inline-flex;
            align-items: center;
            gap: .65rem;
            margin-right: .25rem;
            text-decoration: none !important;
        }
        .t-brand-logo {
            background: #fff;
            border-radius: 10px;
            padding: .4rem .55rem;
            box-shadow: 0 6px 16px rgba(0,0,0,.12);
        }
        .t-brand-logo img {
            display: block;
            height: 28px;
            width: auto;
            max-width: 160px;
            object-fit: contain;
        }
        .t-brand-meta {
            display: flex;
            flex-direction: column;
            gap: .1rem;
        }
        .t-brand-meta strong {
            font-size: .92rem;
            font-weight: 700;
            color: #fff;
        }
        .t-brand-meta span {
            font-size: .72rem;
            font-weight: 500;
            opacity: .78;
        }
        .t-nav-links {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .35rem .55rem;
            flex: 1;
        }
        .t-nav-links a {
            color: #e8eefc;
            font-weight: 600;
            font-size: .88rem;
            padding: .45rem .7rem;
            border-radius: 10px;
            text-decoration: none !important;
            transition: background .15s ease;
        }
        .t-nav-links a:hover,
        .t-nav-links a.active {
            background: rgba(20,191,150,.22);
        }
        .t-nav-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .45rem;
            margin-left: auto;
        }
        .t-nav-actions form { margin: 0; }
        .t-nav-actions .btn {
            width: auto;
            margin: 0;
            padding: .45rem .75rem;
            font-size: .85rem;
            background: rgba(0,0,0,.22);
        }
        .t-nav-actions .btn:hover { background: rgba(20,191,150,.28); transform: none; }
        .t-nav-actions .btn.cta {
            background: var(--accent);
            color: #fff !important;
        }
        .t-nav-actions .btn.cta:hover { background: var(--accent-2); }
        .t-main {
            flex: 1;
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
            padding: 1.25rem 1.15rem 2rem;
        }
        .t-top {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: flex-end;
            gap: .75rem 1rem;
            margin-bottom: 1.1rem;
        }
        .t-top h1 {
            margin: 0;
            font-size: clamp(1.35rem, 3vw, 1.65rem);
            letter-spacing: -.02em;
            color: var(--brand);
        }
        .t-top p {
            margin: .35rem 0 0;
            color: var(--muted);
            font-size: .92rem;
        }
        .class-switcher {
            margin-top: .75rem;
        }
        .class-switcher select {
            min-width: min(100%, 28rem);
            max-width: 100%;
        }
        .card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 1rem 1.15rem;
            margin: .85rem 0;
        }
        .forms-open-card {
            margin-top: 1.15rem;
        }
        .card h2 {
            margin: 0 0 .75rem;
            font-size: 1.05rem;
            color: var(--brand);
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            background: var(--brand);
            color: #fff !important;
            border: 0;
            border-radius: 10px;
            padding: .55rem .9rem;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none !important;
            transition: transform .15s ease, background .15s ease;
            line-height: 1.2;
        }
        .btn .icon { flex-shrink: 0; }
        .icon { display: inline-block; vertical-align: -2px; }
        .btn:hover { background: var(--brand-2); transform: translateY(-1px); }
        .btn.secondary { background: var(--accent); }
        .btn.secondary:hover { background: var(--accent-2); }
        .btn.danger { background: var(--danger); }
        .btn.ghost { background: var(--navy-soft); color: var(--brand) !important; }
        .btn.ghost:hover { background: #dce6f5; }
        .btn:disabled,
        .btn.is-disabled,
        a.btn.is-disabled {
            background: #e8edf5 !important;
            color: #94a3b8 !important;
            border: 1px solid #d8e0ef !important;
            opacity: 1;
            cursor: not-allowed;
            transform: none;
            pointer-events: none;
            filter: none;
            box-shadow: none;
        }
        .btn:disabled:hover,
        .btn.is-disabled:hover {
            background: #e8edf5 !important;
            transform: none;
        }
        .btn-row { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
        button.title-link {
            border: 0;
            padding: 0;
            background: transparent;
            color: var(--brand);
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            text-align: left;
            text-decoration: underline;
            text-underline-offset: 2px;
        }
        button.title-link:hover { color: var(--brand-2); }
        form.inline { display: inline; margin: 0; }
        .grid-detail {
            display: grid;
            grid-template-columns: minmax(260px, 0.95fr) minmax(280px, 1.05fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .qr-box {
            text-align: center;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 1rem;
        }
        .qr-box img, .qr-box canvas {
            display: block;
            margin: 0 auto;
            width: 220px;
            height: 220px;
            border-radius: 8px;
            background: #fff;
        }
        .vote-url {
            word-break: break-all;
            font-size: .82rem;
            margin: .75rem 0;
            color: var(--muted);
        }
        @media (max-width: 860px) {
            .grid-detail { grid-template-columns: 1fr; }
        }
        label { display: block; font-weight: 600; margin: .55rem 0 .25rem; font-size: .9rem; }
        input, select, textarea {
            width: 100%;
            max-width: 560px;
            padding: .6rem .75rem;
            border: 1px solid var(--line);
            border-radius: 10px;
            font: inherit;
            background: #fff;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(20,191,150,.18);
        }
        table { width: 100%; border-collapse: collapse; }
        th, td {
            text-align: left;
            padding: .65rem .4rem;
            border-bottom: 1px solid var(--line);
            vertical-align: top;
        }
        th {
            color: var(--muted);
            font-size: .78rem;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .muted { color: var(--muted); }
        .ok {
            background: var(--brand-soft);
            color: #0a6b53;
            padding: .7rem .9rem;
            border-radius: 12px;
            margin-bottom: .9rem;
        }
        .err {
            background: #fdecea;
            color: #7f1d1d;
            padding: .7rem .9rem;
            border-radius: 12px;
            margin-bottom: .9rem;
        }
        .err ul { margin: 0; padding-left: 1.1rem; }
        .alert-warning {
            background: #fff4e5;
            color: #7a3e00;
            border: 1px solid #f0c98a;
            padding: .75rem .95rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            font-size: .92rem;
            line-height: 1.45;
        }
        .onboard {
            background: linear-gradient(135deg, rgba(20,191,150,.14), rgba(10,42,102,.08));
            border: 1px solid rgba(20,191,150,.35);
            color: var(--brand);
            padding: 1rem 1.1rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
            box-shadow: var(--shadow);
        }
        .onboard strong { display: block; margin-bottom: .35rem; font-size: 1.02rem; }
        .onboard p { margin: 0; color: var(--ink); font-size: .92rem; line-height: 1.45; }
        .grid { display: grid; gap: 1rem; }
        .grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .grid-2 { grid-template-columns: 1.3fr 1fr; }
        .kpi {
            position: relative;
            overflow: hidden;
            margin: 0;
            transition: transform .18s ease, box-shadow .18s ease;
        }
        .kpi:hover { transform: translateY(-2px); box-shadow: 0 14px 34px rgba(10,42,102,.12); }
        .kpi .label { color: var(--muted); font-size: .86rem; font-weight: 500; }
        .kpi .value {
            margin-top: .35rem;
            font-size: 1.7rem;
            font-weight: 700;
            letter-spacing: -.03em;
            color: var(--brand);
        }
        .kpi .hint { margin-top: .35rem; font-size: .8rem; color: var(--muted); }
        .kpi::after {
            content: "";
            position: absolute;
            right: -18px;
            top: -18px;
            width: 84px;
            height: 84px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(20,191,150,.22), transparent 70%);
        }
        .badge {
            display: inline-flex;
            align-items: center;
            padding: .18rem .55rem;
            border-radius: 999px;
            font-size: .75rem;
            font-weight: 600;
            background: var(--brand-soft);
            color: #0a6b53;
            white-space: nowrap;
        }
        .badge.open { background: var(--brand-soft); color: #0a6b53; }
        .badge.quota_full { background: var(--navy-soft); color: var(--brand); }
        .badge.closed { background: #f3e8e8; color: var(--danger); }
        .badge.draft, .badge.none { background: #eef1f6; color: var(--muted); }
        .bar {
            height: 8px;
            background: var(--navy-soft);
            border-radius: 999px;
            overflow: hidden;
            margin-top: .4rem;
            max-width: 220px;
        }
        .bar > span {
            display: block;
            height: 100%;
            background: linear-gradient(90deg, var(--accent), var(--accent-2));
            border-radius: inherit;
        }
        .steps {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            margin: 0 0 1rem;
        }
        .step-pill {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .4rem .7rem;
            border-radius: 999px;
            font-size: .82rem;
            font-weight: 600;
            background: var(--navy-soft);
            color: var(--muted);
        }
        .step-pill.active {
            background: var(--brand);
            color: #fff;
        }
        .step-pill.done {
            background: var(--brand-soft);
            color: #0a6b53;
        }
        .step-panel[hidden] { display: none !important; }
        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .t-footer {
            border-top: 1px solid var(--line);
            background: rgba(255,255,255,.72);
            padding: 1rem 1.15rem 1.35rem;
            margin-top: auto;
        }
        .t-footer-inner {
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            flex-wrap: wrap;
            gap: .75rem 1.25rem;
            align-items: center;
            justify-content: space-between;
            font-size: .85rem;
            color: var(--muted);
        }
        .t-footer a { font-weight: 600; }
        @media (max-width: 900px) {
            .grid-3, .grid-2 { grid-template-columns: 1fr; }
            .t-brand-meta { display: none; }
            .t-nav-actions { width: 100%; margin-left: 0; }
            .t-nav-actions .btn.cta { flex: 1; }
        }
        @media (max-width: 640px) {
            input, select, textarea { max-width: 100%; }
            .t-nav-links { width: 100%; }
            th, td { font-size: .9rem; }
        }
        @media print {
            .t-nav, .t-footer, .no-print { display: none !important; }
            body { background: #fff; }
            .card { box-shadow: none; border: 0; }
        }
    </style>
    @stack('head')
</head>
<body>
<header class="t-nav no-print">
    <div class="t-nav-inner">
        <a class="t-brand" href="{{ route('teacher.dashboard') }}" title="Khan Academy Vietnam">
            <span class="t-brand-logo">
                <img src="{{ asset('images/logo-kav.svg') }}" alt="Khan Academy Vietnam">
            </span>
            <span class="t-brand-meta">
                <strong>KavMiniApp</strong>
                <span>Cổng giáo viên</span>
            </span>
        </a>
        <nav class="t-nav-links" aria-label="Menu giáo viên">
            @hasSection('nav')
                @yield('nav')
            @else
                <a href="{{ route('teacher.dashboard') }}" class="{{ request()->routeIs('teacher.dashboard') ? 'active' : '' }}">Lớp của tôi</a>
            @endif
        </nav>
        <div class="t-nav-actions">
            @yield('nav_actions')
            @unless(request()->routeIs('teacher.login') || request()->routeIs('home'))
                <a class="btn cta" href="{{ route('teacher.profiles.create') }}"><x-icon name="plus" /> Tạo lớp mới</a>
                <form method="POST" action="{{ route('teacher.logout') }}">
                    @csrf
                    <button class="btn" type="submit"><x-icon name="log-out" /> Đăng xuất</button>
                </form>
            @endunless
        </div>
    </div>
</header>

<main class="t-main">
    @if(session('status'))
        <div class="ok">{{ session('status') }}</div>
    @endif
    @if(session('onboarding'))
        <div class="onboard">
            <strong>Chào mừng!</strong>
            <p>Bạn có thể tạo lớp và lấy link gửi phụ huynh ngay — không cần Admin cấp tài khoản.</p>
        </div>
    @endif
    @if(isset($errors) && $errors->any())
        <div class="err">
            <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
    @yield('content')
</main>

<footer class="t-footer no-print">
    <div class="t-footer-inner">
        <div>
            <strong style="color:var(--brand)">Hướng dẫn nhanh:</strong>
            tạo lớp → lấy link / QR / Share Zalo → gửi group lớp. Mẫu phiếu tải PDF theo từng form.
        </div>
        <div>
            <a href="{{ url('/miniapp') }}" target="_blank" rel="noopener">Mini App</a>
            <span aria-hidden="true"> · </span>
            <span title="docs/05-teacher-playbook.md">Playbook GV (nội bộ)</span>
        </div>
    </div>
</footer>
<script>
window.kavShareZalo = function (url) {
    if (!url) return;
    var share = 'https://zalo.me/share?u=' + encodeURIComponent(url);
    // Không dùng window.open + noopener rồi fallback location.href:
    // nhiều trình duyệt trả null dù đã mở tab → bị chuyển trang hiện tại.
    var a = document.createElement('a');
    a.href = share;
    a.target = '_blank';
    a.rel = 'noopener noreferrer';
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    a.remove();
};
</script>
@stack('scripts')
</body>
</html>
