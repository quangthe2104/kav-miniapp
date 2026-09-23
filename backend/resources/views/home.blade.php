<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Montserrat:400,500,600,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand: #0a2a66;
            --accent: #14bf96;
            --muted: #5b6b8c;
            --bg: #f4f7fc;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Montserrat", system-ui, sans-serif;
            color: var(--brand);
            background:
                radial-gradient(900px 420px at 0% 0%, rgba(20,191,150,.16), transparent 55%),
                radial-gradient(700px 380px at 100% 10%, rgba(10,42,102,.10), transparent 50%),
                var(--bg);
            display: grid;
            place-items: center;
            padding: 2rem 1rem;
        }
        .hero {
            width: min(720px, 100%);
            background: #fff;
            border: 1px solid #d8e0ef;
            border-radius: 20px;
            padding: 2rem 1.75rem;
            box-shadow: 0 18px 40px rgba(10,42,102,.08);
        }
        .logo { display: block; height: 48px; width: auto; margin-bottom: 1.25rem; }
        h1 { margin: 0 0 .5rem; font-size: 1.75rem; letter-spacing: -.02em; }
        .muted { color: var(--muted); line-height: 1.55; }
        .actions { margin: 1.35rem 0 1rem; display: flex; flex-wrap: wrap; gap: .55rem; }
        a.btn {
            display: inline-block;
            padding: .65rem 1.05rem;
            background: var(--brand);
            color: #fff;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
        }
        a.btn.secondary { background: var(--accent); }
        ul.muted { padding-left: 1.1rem; margin: 0; }
        code { background: #eef2fa; padding: .1rem .35rem; border-radius: 6px; }
    </style>
</head>
<body>
    <div class="hero">
        <img class="logo" src="{{ asset('images/logo-kav.svg') }}" alt="Khan Academy Vietnam">
        <h1>KavMiniApp</h1>
        <p class="muted">Hệ thống Form/Vote phụ huynh theo Class Profile — Khan Academy Vietnam.</p>
        <div class="actions">
            <a class="btn" href="{{ route('login') }}">Admin đăng nhập</a>
            <a class="btn secondary" href="{{ route('teacher.login') }}">Giáo viên (Zalo/dev)</a>
        </div>
        <ul class="muted">
            <li>Admin demo: <code>admin@kav.local</code> / <code>password</code></li>
            <li>GV demo: SĐT <code>0900000001</code></li>
        </ul>
    </div>
</body>
</html>
