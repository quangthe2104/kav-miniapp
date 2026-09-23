<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') — {{ config('app.name') }}</title>
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
            --shadow: 0 10px 30px rgba(10, 42, 102, .08);
            --radius: 16px;
            --sidebar: 268px;
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
        }
        a { color: var(--accent-2); text-decoration: none; }
        .shell { display: grid; grid-template-columns: var(--sidebar) 1fr; min-height: 100vh; }
        .sidebar {
            position: sticky; top: 0; height: 100vh;
            padding: 1.25rem 1rem;
            background: linear-gradient(185deg, #071c45 0%, #0a2a66 55%, #0f3b8a 100%);
            color: #eef3ff;
            display: flex; flex-direction: column; gap: 1rem;
        }
        .brand {
            display: flex; flex-direction: column; gap: .65rem;
            padding: .2rem .35rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,.12);
        }
        .brand-logo {
            background: #fff;
            border-radius: 12px;
            padding: .65rem .75rem;
            box-shadow: 0 8px 20px rgba(0,0,0,.12);
        }
        .brand-logo img {
            display: block;
            width: 100%;
            height: auto;
            max-height: 42px;
            object-fit: contain;
            object-position: left center;
        }
        .brand span {
            display: block;
            font-size: .78rem;
            font-weight: 500;
            opacity: .8;
            padding-left: .15rem;
        }
        .side-nav { display: flex; flex-direction: column; gap: .35rem; flex: 1; }
        .side-nav a {
            display: inline-flex; align-items: center; gap: .45rem;
            color: #e8eefc; padding: .7rem .85rem; border-radius: 12px;
            font-weight: 500; transition: background .18s ease, transform .18s ease;
            text-decoration: none;
        }
        .side-nav a:hover { background: rgba(20,191,150,.16); transform: translateX(2px); }
        .side-nav a.active {
            background: rgba(20,191,150,.22);
            box-shadow: inset 0 0 0 1px rgba(20,191,150,.35);
        }
        .side-foot { padding-top: .5rem; border-top: 1px solid rgba(255,255,255,.12); }
        .side-foot form { margin: 0; }
        .side-foot button {
            width: 100%; background: rgba(0,0,0,.22); color: #fff; border: 0;
            border-radius: 12px; padding: .7rem .85rem; font: inherit; font-weight: 600; cursor: pointer;
        }
        .side-foot button:hover { background: rgba(20,191,150,.25); }
        .main { padding: 1.25rem 1.5rem 2rem; }
        .topbar {
            display: flex; justify-content: space-between; align-items: end; gap: 1rem;
            margin-bottom: 1.1rem;
        }
        .topbar h1 { margin: 0; font-size: 1.55rem; letter-spacing: -.02em; color: var(--brand); }
        .topbar p { margin: .35rem 0 0; color: var(--muted); font-size: .95rem; }
        .card {
            background: var(--panel); border: 1px solid var(--line);
            border-radius: var(--radius); box-shadow: var(--shadow); padding: 1rem 1.15rem;
        }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: .45rem;
            background: var(--brand); color: #fff !important; border: 0; border-radius: 10px;
            padding: .55rem .9rem; font: inherit; font-weight: 600; cursor: pointer;
            transition: transform .15s ease, background .15s ease;
        }
        .btn .icon { flex-shrink: 0; }
        .icon { display: inline-block; vertical-align: -2px; }
        .btn:hover { background: var(--brand-2); transform: translateY(-1px); }
        .btn.secondary { background: var(--accent); }
        .btn.secondary:hover { background: var(--accent-2); }
        .btn.danger { background: var(--danger); }
        .btn.ghost { background: var(--navy-soft); color: var(--brand) !important; }
        .btn:disabled, .btn.is-disabled { opacity: .45; cursor: not-allowed; pointer-events: none; transform: none; }
        label { display: block; font-weight: 600; margin: .55rem 0 .25rem; }
        input, select, textarea {
            width: 100%; max-width: 640px; padding: .6rem .75rem;
            border: 1px solid var(--line); border-radius: 10px; font: inherit; background: #fff;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: .65rem .4rem; border-bottom: 1px solid var(--line); vertical-align: top; }
        th { color: var(--muted); font-size: .82rem; text-transform: uppercase; letter-spacing: .04em; }
        .muted { color: var(--muted); }
        .ok { background: var(--brand-soft); color: #0a6b53; padding: .7rem .9rem; border-radius: 12px; margin-bottom: .9rem; }
        .err { background: #fdecea; color: #7f1d1d; padding: .7rem .9rem; border-radius: 12px; margin-bottom: .9rem; }
        .grid { display: grid; gap: 1rem; }
        .grid-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .grid-2 { grid-template-columns: 1.4fr 1fr; }
        .kpi {
            position: relative; overflow: hidden;
            transition: transform .18s ease, box-shadow .18s ease;
        }
        .kpi:hover { transform: translateY(-2px); box-shadow: 0 14px 34px rgba(10,42,102,.12); }
        .kpi .label { color: var(--muted); font-size: .86rem; font-weight: 500; }
        .kpi .value { margin-top: .35rem; font-size: 1.75rem; font-weight: 700; letter-spacing: -.03em; color: var(--brand); }
        .kpi .hint { margin-top: .35rem; font-size: .82rem; color: var(--muted); }
        .kpi::after {
            content: ""; position: absolute; right: -18px; top: -18px;
            width: 84px; height: 84px; border-radius: 50%;
            background: radial-gradient(circle, rgba(20,191,150,.22), transparent 70%);
        }
        .badge {
            display: inline-flex; align-items: center; padding: .18rem .55rem;
            border-radius: 999px; font-size: .75rem; font-weight: 600;
            background: var(--brand-soft); color: #0a6b53;
        }
        .badge.closed { background: #f3e8e8; color: var(--danger); }
        .badge.draft { background: var(--navy-soft); color: var(--brand); }
        .chart-wrap { position: relative; height: 280px; }
        .chart-wrap.sm { height: 220px; }
        @media (max-width: 1100px) {
            .grid-4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .grid-2, .grid-3 { grid-template-columns: 1fr; }
        }
        @media (max-width: 860px) {
            .shell { grid-template-columns: 1fr; }
            .sidebar { position: relative; height: auto; }
            .side-nav { flex-direction: row; flex-wrap: wrap; }
        }
    </style>
    @stack('head')
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-logo">
                <img src="{{ asset('images/logo-kav.svg') }}" alt="Khan Academy Vietnam">
            </div>
            <span>Admin Console · KavMiniApp</span>
        </div>
        <nav class="side-nav">
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"><x-icon name="chart" :size="15" /> Dashboard</a>
            <a href="{{ route('admin.forms.index') }}" class="{{ request()->routeIs('admin.forms.*') ? 'active' : '' }}"><x-icon name="file" :size="15" /> Forms / Vote</a>
            <a href="{{ route('admin.teachers.index') }}" class="{{ request()->routeIs('admin.teachers.*') ? 'active' : '' }}"><x-icon name="users" :size="15" /> Giáo viên</a>
            <a href="{{ route('admin.schools.index') }}" class="{{ request()->routeIs('admin.schools.*') || request()->routeIs('admin.catalog.*') ? 'active' : '' }}"><x-icon name="school" :size="15" /> Danh sách Trường</a>
            <a href="{{ route('admin.audit.index') }}" class="{{ request()->routeIs('admin.audit.*') ? 'active' : '' }}"><x-icon name="note" :size="15" /> Audit logs</a>
            <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}"><x-icon name="settings" :size="15" /> Tài khoản admin</a>
        </nav>
        <div class="side-foot">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"><x-icon name="log-out" :size="15" /> Đăng xuất</button>
            </form>
        </div>
    </aside>
    <main class="main">
        @if(session('status'))
            <div class="ok">{{ session('status') }}</div>
        @endif
        @if(isset($errors) && $errors->any())
            <div class="err">
                <ul style="margin:0;padding-left:1.1rem">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif
        @yield('content')
    </main>
</div>
@stack('scripts')
</body>
</html>
