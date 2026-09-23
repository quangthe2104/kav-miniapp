@extends('layouts.admin')
@section('title','Sửa Form')
@section('content')
<div class="topbar">
    <div>
        <h1>Sửa Form #{{ $form->id }}</h1>
        <p>{{ $form->title }}</p>
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        <a class="btn ghost" href="{{ route('admin.forms.export', $form) }}">Export CSV</a>
        @if($form->consent_pdf_path)
            <a class="btn ghost" href="{{ route('admin.forms.consent-pdf', $form) }}" target="_blank" rel="noopener">Xem PDF mẫu</a>
        @endif
        <a class="btn secondary" href="{{ route('admin.forms.index') }}">← Forms</a>
    </div>
</div>

<form method="POST" action="{{ route('admin.forms.update', $form) }}" enctype="multipart/form-data" id="form-editor">
    @csrf @method('PUT')
    <div class="grid grid-2" style="align-items:start">
        <div class="card" style="margin:0">
            <h2 style="margin-top:0;font-size:1.05rem">Thông tin Form</h2>
            @include('admin.forms._fields', ['form' => $form, 'resolved' => $resolved])
            <p style="margin-top:1.1rem;margin-bottom:0">
                <button class="btn" type="submit">Cập nhật</button>
            </p>
        </div>
        <div class="card" style="margin:0">
            <h2 style="margin-top:0;font-size:1.05rem">PDF mẫu &amp; OCR</h2>
            @include('admin.forms._pdf_ocr', ['form' => $form, 'resolved' => $resolved])
        </div>
    </div>
</form>
@endsection
@include('admin.forms._options_script')
@include('admin.forms._ocr_script')
