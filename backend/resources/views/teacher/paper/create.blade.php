@extends('layouts.teacher')
@section('title', 'Upload giấy')

@section('nav')
    <a href="{{ route('teacher.dashboard') }}">Lớp của tôi</a>
    <a href="{{ route('teacher.class-forms.show', $classForm) }}">Form lớp</a>
@endsection

@section('content')
@php
    $form = $classForm->form;
    $choices = $form?->resolvedOptions()['choices'] ?? [
        ['value' => 'agree', 'label' => 'Đồng ý'],
        ['value' => 'disagree', 'label' => 'Không đồng ý'],
    ];
    $labelA = $choices[0]['label'] ?? 'Đồng ý';
    $labelB = $choices[1]['label'] ?? 'Không đồng ý';
@endphp

<div class="t-top">
    <div>
        <h1>Upload phiếu giấy</h1>
        <p>{{ $form?->title }} · Lớp {{ $classForm->classProfile->class_name ?? '' }}</p>
    </div>
    <a class="btn ghost" href="{{ route('teacher.class-forms.show', $classForm) }}"><x-icon name="arrow-left" /> Quay lại</a>
</div>

<div class="card">
    <h2>Tóm tắt</h2>
    <ol style="margin:0;padding-left:1.2rem;line-height:1.55">
        <li>Chụp / chọn nhiều ảnh phiếu giấy (không cần mã ID).</li>
        <li>Hệ thống OCR gợi ý <strong>{{ $labelA }}</strong> / <strong>{{ $labelB }}</strong> (có thể “chưa đọc được”).</li>
        <li>Bạn xác nhận hàng loạt trước khi lưu vào coverage lớp.</li>
    </ol>
</div>

<div class="card">
    <h2>Chọn ảnh</h2>
    <form method="POST" action="{{ route('teacher.paper.store', $classForm) }}" enctype="multipart/form-data">
        @csrf
        <label for="images">Ảnh phiếu (JPEG/PNG/WebP, tối đa 50 ảnh)</label>
        <input id="images" type="file" name="images[]" accept="image/*" multiple required style="max-width:100%">
        <p style="margin-top:1rem"><button class="btn secondary" type="submit"><x-icon name="upload" /> Upload &amp; OCR</button></p>
    </form>
</div>
@endsection
