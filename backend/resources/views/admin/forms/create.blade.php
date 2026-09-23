@extends('layouts.admin')
@section('title','Tạo Form')
@section('content')
<div class="topbar">
    <div>
        <h1>Tạo Form</h1>
        <p>Form active sẽ hiện trên profile lớp để giáo viên lấy link vote.</p>
    </div>
    <a class="btn secondary" href="{{ route('admin.forms.index') }}">← Forms</a>
</div>

<form method="POST" action="{{ route('admin.forms.store') }}" enctype="multipart/form-data" id="form-editor">
    @csrf
    <div class="grid grid-2" style="align-items:start">
        <div class="card" style="margin:0">
            <h2 style="margin-top:0;font-size:1.05rem">Thông tin Form</h2>
            @include('admin.forms._fields', ['form' => null, 'resolved' => $resolved])
            <p style="margin-top:1.1rem;margin-bottom:0">
                <button class="btn" type="submit">Lưu</button>
            </p>
        </div>
        <div class="card" style="margin:0">
            <h2 style="margin-top:0;font-size:1.05rem">PDF mẫu &amp; OCR</h2>
            @include('admin.forms._pdf_ocr', ['form' => null, 'resolved' => $resolved])
        </div>
    </div>
</form>
@endsection
@include('admin.forms._options_script')
