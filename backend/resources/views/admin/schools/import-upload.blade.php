@extends('layouts.admin')
@section('title','Import trường')
@section('content')
<div class="topbar">
    <div>
        <h1>Import danh sách Trường</h1>
        <p>Bước 1/3 — Upload file .xlsx / .xls / .csv. Hệ thống sẽ hiện header và 5 dòng mẫu để map cột.</p>
    </div>
    <a class="btn ghost" href="{{ route('admin.schools.index') }}">← Danh sách</a>
</div>

<div class="card" style="max-width:640px">
    <ol class="muted" style="margin:0 0 1rem 1.1rem;padding:0;line-height:1.6">
        <li><strong style="color:var(--brand)">Upload</strong> file</li>
        <li>Map cột → Validate</li>
        <li>Xác nhận &amp; upsert theo <code>external_id</code></li>
    </ol>
    <form method="POST" action="{{ route('admin.schools.import.upload') }}" enctype="multipart/form-data">
        @csrf
        <label for="file">File Excel / CSV</label>
        <input id="file" type="file" name="file" accept=".xlsx,.xls,.csv,text/csv" required>
        <p class="muted" style="margin:.5rem 0 1rem;font-size:.88rem">
            Tối đa 10MB. Cột cần có: mã trường, tên, mã hoặc tên phường/xã. Có thể thêm cấp học và mã tỉnh.
        </p>
        <button class="btn secondary" type="submit">Tiếp tục — xem preview</button>
    </form>
</div>
@endsection
