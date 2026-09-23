<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Montserrat:400,500,600,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #0a2a66;
            --muted: #5b6b8c;
            --line: #d8e0ef;
            --bg: #f4f7fc;
            --brand: #0a2a66;
            --accent: #14bf96;
            --accent-2: #0fa883;
            --danger: #9b2226;
            --brand-soft: #e5f9f3;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Montserrat", system-ui, sans-serif;
            background:
                radial-gradient(800px 360px at 0% 0%, rgba(20,191,150,.12), transparent 60%),
                radial-gradient(700px 320px at 100% 0%, rgba(10,42,102,.08), transparent 55%),
                var(--bg);
            color: var(--ink);
        }
        a { color: var(--accent-2); }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 1.25rem; }
        .nav {
            background: #fff;
            border-bottom: 1px solid var(--line);
            box-shadow: 0 4px 18px rgba(10,42,102,.04);
        }
        .nav .wrap {
            display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;
        }
        .nav-logo {
            display: inline-flex; align-items: center;
            margin-right: .35rem;
        }
        .nav-logo img {
            height: 34px; width: auto; display: block;
        }
        .nav a { text-decoration: none; color: var(--brand); font-weight: 600; }
        .nav a:hover { color: var(--accent-2); }
        .card {
            background: #fff; border: 1px solid var(--line); border-radius: 12px;
            padding: 1rem 1.25rem; margin: .75rem 0;
            box-shadow: 0 8px 24px rgba(10,42,102,.05);
        }
        .btn {
            display: inline-block; background: var(--brand); color: #fff !important;
            border: 0; border-radius: 8px; padding: .55rem .9rem; text-decoration: none;
            cursor: pointer; font-weight: 600; font-family: inherit;
        }
        .btn:hover { filter: brightness(1.08); }
        .btn.secondary { background: var(--accent); }
        .btn.danger { background: var(--danger); }
        label { display: block; font-weight: 600; margin: .5rem 0 .25rem; }
        input, select, textarea {
            width: 100%; max-width: 560px; padding: .55rem .7rem;
            border: 1px solid var(--line); border-radius: 8px; font: inherit;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid var(--line); text-align: left; padding: .55rem .35rem; vertical-align: top; }
        .muted { color: var(--muted); }
        .ok { background: var(--brand-soft); color: #0a6b53; padding: .6rem .8rem; border-radius: 8px; }
        .err { background: #fdecea; color: #7f1d1d; padding: .6rem .8rem; border-radius: 8px; }
        .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        h1, h2 { color: var(--brand); }
        @media (max-width:800px){ .grid2{ grid-template-columns:1fr; } }
        @media print { .no-print { display:none !important; } body { background:#fff; } .card { border:0; box-shadow:none; } }
    </style>
    @stack('head')
</head>
<body>
<nav class="nav no-print">
    <div class="wrap">
        <a class="nav-logo" href="{{ url('/') }}" title="Khan Academy Vietnam">
            <img src="{{ asset('images/logo-kav.svg') }}" alt="Khan Academy Vietnam">
        </a>
        @yield('nav')
    </div>
</nav>
<main class="wrap">
    @if(session('status'))
        <div class="ok">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="err">
            <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
    @yield('content')
</main>
@stack('scripts')
</body>
</html>
