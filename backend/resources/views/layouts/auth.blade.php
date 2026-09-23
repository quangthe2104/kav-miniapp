<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Đăng nhập') — {{ config('app.name') }}</title>
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
            --danger: #b42318;
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
            align-items: center;
            justify-content: center;
            padding: 1.5rem 1rem;
        }
        a { color: var(--accent-2); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .auth-shell {
            width: 100%;
            max-width: 420px;
        }
        .auth-brand {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .auth-brand-logo {
            display: inline-block;
            background: #fff;
            border-radius: 14px;
            padding: .85rem 1.1rem;
            box-shadow: var(--shadow);
            margin-bottom: .75rem;
        }
        .auth-brand-logo img {
            display: block;
            height: 42px;
            width: auto;
            max-width: 220px;
            object-fit: contain;
        }
        .auth-brand span {
            display: block;
            font-size: .82rem;
            font-weight: 500;
            color: var(--muted);
        }
        .auth-card {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 1.5rem 1.35rem 1.35rem;
        }
        .auth-card h1 {
            margin: 0 0 .35rem;
            font-size: 1.35rem;
            letter-spacing: -.02em;
            color: var(--brand);
        }
        .auth-card .lead {
            margin: 0 0 1.25rem;
            font-size: .9rem;
            color: var(--muted);
        }
        label {
            display: block;
            font-weight: 600;
            font-size: .88rem;
            margin: .75rem 0 .3rem;
        }
        label.checkbox {
            display: flex;
            align-items: center;
            gap: .45rem;
            font-weight: 500;
            margin-top: .85rem;
            cursor: pointer;
        }
        label.checkbox input { width: auto; max-width: none; margin: 0; }
        input[type="email"],
        input[type="password"],
        input[type="text"] {
            width: 100%;
            padding: .65rem .8rem;
            border: 1px solid var(--line);
            border-radius: 10px;
            font: inherit;
            background: #fff;
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(20,191,150,.18);
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            margin-top: 1.25rem;
            background: var(--brand);
            color: #fff !important;
            border: 0;
            border-radius: 10px;
            padding: .7rem 1rem;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
            transition: transform .15s ease, background .15s ease;
        }
        .btn:hover { background: var(--brand-2); transform: translateY(-1px); }
        .auth-foot {
            margin-top: 1.25rem;
            text-align: center;
            font-size: .88rem;
        }
        .auth-foot a { font-weight: 600; }
        .demo-hint {
            margin-top: 1rem;
            padding: .55rem .75rem;
            background: var(--brand-soft);
            color: #0a6b53;
            border-radius: 10px;
            font-size: .82rem;
        }
        .ok {
            background: var(--brand-soft);
            color: #0a6b53;
            padding: .7rem .9rem;
            border-radius: 12px;
            margin-bottom: .9rem;
            font-size: .88rem;
        }
        .err {
            background: #fdecea;
            color: #7f1d1d;
            padding: .7rem .9rem;
            border-radius: 12px;
            margin-bottom: .9rem;
            font-size: .88rem;
        }
        .err ul { margin: 0; padding-left: 1.1rem; }
    </style>
    @stack('head')
</head>
<body>
<div class="auth-shell">
    <div class="auth-brand">
        <div class="auth-brand-logo">
            <img src="{{ asset('images/logo-kav.svg') }}" alt="Khan Academy Vietnam">
        </div>
        <span>@yield('subtitle', 'KavMiniApp · Quản trị')</span>
    </div>

    @if(session('status'))
        <div class="ok">{{ session('status') }}</div>
    @endif
    @if(isset($errors) && $errors->any())
        <div class="err">
            <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="auth-card">
        @yield('content')
    </div>

    @hasSection('footer')
        @yield('footer')
    @else
        <div class="auth-foot">
            <a href="{{ url('/') }}">Giáo viên →</a>
        </div>
    @endif
</div>
@stack('scripts')
</body>
</html>
