@props(['name', 'size' => 16])
@php
    $s = (int) $size;
    $isBrand = $name === 'zalo';
@endphp
<svg {{ $attributes->merge(['class' => 'icon'.($isBrand ? ' icon-zalo' : ''), 'width' => $s, 'height' => $s, 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => $isBrand ? 'none' : 'currentColor', 'stroke-width' => $isBrand ? '0' : '2', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round', 'aria-hidden' => 'true']) }}>
@switch($name)
    @case('plus')
        <path d="M12 5v14M5 12h14"/>
        @break
    @case('copy')
        <rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>
        @break
    @case('share')
        <circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5 15.4 17.5M15.4 6.5 8.6 10.5"/>
        @break
    @case('zalo')
        <rect x="2" y="2" width="20" height="20" rx="6" fill="#0068ff" stroke="none"/>
        <path fill="#fff" stroke="none" d="M7 8.2h10v1.7L10.4 15.6H17v1.7H7v-1.7l6.6-5.7H7z"/>
        @break
    @case('link')
        <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
        @break
    @case('download')
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/>
        @break
    @case('upload')
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v12"/>
        @break
    @case('qr')
        <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14v6M14 20h3"/>
        @break
    @case('file')
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>
        @break
    @case('note')
        <path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>
        @break
    @case('lock')
        <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        @break
    @case('unlock')
        <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/>
        @break
    @case('check')
        <path d="M20 6 9 17l-5-5"/>
        @break
    @case('x')
        <path d="M18 6 6 18M6 6l12 12"/>
        @break
    @case('trash')
        <path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M10 11v6M14 11v6"/>
        @break
    @case('home')
        <path d="M3 10.5 12 3l9 7.5"/><path d="M5 10v10h14V10"/>
        @break
    @case('arrow-left')
        <path d="M19 12H5M12 19l-7-7 7-7"/>
        @break
    @case('filter')
        <path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/>
        @break
    @case('users')
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
        @break
    @case('school')
        <path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>
        @break
    @case('chart')
        <path d="M18 20V10M12 20V4M6 20v-6"/>
        @break
    @case('settings')
        <circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M1 12h2M21 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4"/>
        @break
    @case('log-out')
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>
        @break
    @case('save')
        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/>
        @break
    @case('eye')
        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/>
        @break
    @case('info')
        <circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>
        @break
    @default
        <circle cx="12" cy="12" r="9"/>
@endswitch
</svg>
