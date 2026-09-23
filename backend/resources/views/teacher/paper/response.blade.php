@extends('layouts.teacher')
@section('title', 'Chi tiết phiếu giấy')

@section('nav')
    <a href="{{ route('teacher.dashboard') }}">Lớp của tôi</a>
    <a href="{{ route('teacher.class-forms.show', $classForm) }}">Form lớp</a>
@endsection

@section('content')
<div class="t-top">
    <div>
        <h1>Phiếu giấy #{{ $response->id }}</h1>
        <p class="muted" style="margin:0">
            {{ $form?->choiceLabel((string) $response->choice) }}
            · {{ optional($response->created_at)?->format('d/m/Y H:i') }}
            @if($response->ocr_suggestion)
                · OCR gợi ý: {{ $form?->choiceLabel((string) $response->ocr_suggestion) ?? $response->ocr_suggestion }}
                @if($response->ocr_confidence !== null)
                    ({{ (int) round(((float) $response->ocr_confidence) * 100) }}%)
                @endif
            @endif
        </p>
    </div>
    <a class="btn ghost" href="{{ route('teacher.class-forms.show', $classForm) }}"><x-icon name="arrow-left" /> Form lớp</a>
</div>

<div class="card" style="text-align:center">
    <img
        src="{{ route('teacher.responses.paper', [$response, 'raw' => 1]) }}"
        alt="Ảnh phiếu giấy"
        style="max-width:100%;height:auto;border-radius:12px;border:1px solid var(--line)"
    >
    <p style="margin-top:1rem">
        <a class="btn ghost" href="{{ route('teacher.responses.paper', [$response, 'raw' => 1]) }}" download="phieu-{{ $response->id }}.jpg">
            <x-icon name="download" /> Tải ảnh
        </a>
    </p>
</div>
@endsection
