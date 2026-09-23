<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts: Vite optional for Phase 1 plain admin -->
        {{-- @vite(['resources/css/app.css', 'resources/js/app.js']) --}}
        <style>
            body { font-family: system-ui, sans-serif; }
            .min-h-screen { min-height: 100vh; }
            .flex { display: flex; }
            .flex-col { flex-direction: column; }
            .items-center { align-items: center; }
            .justify-center { justify-content: center; }
            .bg-gray-100 { background: #f3f4f6; }
            .bg-white { background: #fff; }
            .shadow-md { box-shadow: 0 4px 10px rgba(0,0,0,.08); }
            .rounded-lg { border-radius: 12px; }
            .p-6 { padding: 1.5rem; }
            .mt-6 { margin-top: 1.5rem; }
            .w-full { width: 100%; }
            .sm\:max-w-md { max-width: 28rem; }
        </style>
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
            <div>
                <a href="/">
                    <x-application-logo class="w-20 h-20 fill-current text-gray-500" />
                </a>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
