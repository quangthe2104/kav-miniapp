@extends('layouts.admin')
@section('title','Danh mục')
@section('content')
<div class="topbar">
    <div>
        <h1>Danh mục địa lý & trường</h1>
        <p>Catalog đã seed cho pilot Thanh Hóa. Cột <code>external_id</code> = mã ID trường từ file CSGD.</p>
    </div>
</div>
<div class="grid grid-3" style="margin-bottom:1rem">
    <div class="card kpi">
        <div class="label">Tỉnh</div>
        <div class="value" style="font-size:1.2rem">{{ $province?->name ?? '—' }}</div>
        <div class="hint">Mã {{ $province?->code ?? '—' }}</div>
    </div>
    <div class="card kpi">
        <div class="label">Phường / Xã</div>
        <div class="value">{{ $wardsCount }}</div>
    </div>
    <div class="card kpi">
        <div class="label">Trường</div>
        <div class="value">{{ $schoolsCount }}</div>
    </div>
</div>
<div class="card">
<table>
<thead><tr><th>ID hệ thống</th><th>Mã trường</th><th>Tên</th><th>Phường/Xã</th><th>Cấp</th></tr></thead>
<tbody>
@foreach($schools as $s)
<tr>
    <td>{{ $s->id }}</td>
    <td>{{ $s->external_id }}</td>
    <td>{{ $s->name }}</td>
    <td>{{ $s->ward?->name }}</td>
    <td>{{ $s->level }}</td>
</tr>
@endforeach
</tbody>
</table>
{{ $schools->links() }}
</div>
@endsection
