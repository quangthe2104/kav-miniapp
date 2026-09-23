@extends('layouts.admin')
@section('title','Xác nhận import')
@section('content')
<div class="topbar">
    <div>
        <h1>Xác nhận import</h1>
        <p>Bước 3/3 — File <strong>{{ $originalName }}</strong>. Upsert theo <code>external_id</code> (thêm mới hoặc cập nhật).</p>
    </div>
    <form method="POST" action="{{ route('admin.schools.import.cancel') }}">
        @csrf
        <button class="btn ghost" type="submit">Hủy</button>
    </form>
</div>

<div class="grid grid-3" style="margin-bottom:1rem">
    <div class="card kpi">
        <div class="label">Tổng dòng</div>
        <div class="value">{{ $stats['total'] ?? 0 }}</div>
    </div>
    <div class="card kpi">
        <div class="label">Hợp lệ</div>
        <div class="value">{{ $stats['valid'] ?? 0 }}</div>
        <div class="hint">Sẽ được upsert</div>
    </div>
    <div class="card kpi">
        <div class="label">Lỗi</div>
        <div class="value">{{ $stats['invalid'] ?? 0 }}</div>
        <div class="hint">Bị bỏ qua khi commit</div>
    </div>
</div>

@if(($stats['valid'] ?? 0) > 0)
<div class="card" style="margin-bottom:1rem">
    <h2 style="margin:0 0 .75rem;font-size:1.05rem;color:var(--brand)">Mẫu dòng hợp lệ</h2>
    <table>
        <thead>
        <tr><th>Dòng</th><th>Mã</th><th>Tên</th><th>ward_id</th><th>Cấp</th></tr>
        </thead>
        <tbody>
        @foreach($validSample as $row)
            <tr>
                <td>{{ $row['row'] }}</td>
                <td><code>{{ $row['external_id'] }}</code></td>
                <td>{{ $row['name'] }}</td>
                <td>{{ $row['ward_id'] }}</td>
                <td>{{ $row['level'] ?: '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

@if(count($errors) > 0)
<div class="card" style="margin-bottom:1rem">
    <h2 style="margin:0 0 .75rem;font-size:1.05rem;color:var(--danger)">Lỗi theo dòng (tối đa 50 hiện)</h2>
    <table>
        <thead><tr><th>Dòng Excel</th><th>Chi tiết</th></tr></thead>
        <tbody>
        @foreach(array_slice($errors, 0, 50) as $err)
            <tr>
                <td>{{ $err['row'] }}</td>
                <td>{{ $err['message'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @if(count($errors) > 50)
        <p class="muted" style="margin:.75rem 0 0">… và {{ count($errors) - 50 }} lỗi khác.</p>
    @endif
</div>
@endif

<div class="card" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:center">
    @if(($stats['valid'] ?? 0) > 0 && ($stats['invalid'] ?? 0) <= 100)
        <form method="POST" action="{{ route('admin.schools.import.commit') }}">
            @csrf
            <button class="btn secondary" type="submit" onclick="return confirm('Upsert {{ $stats['valid'] }} trường hợp lệ?')">
                Commit — upsert {{ $stats['valid'] }} dòng
            </button>
        </form>
    @elseif(($stats['invalid'] ?? 0) > 100)
        <p class="err" style="margin:0">Quá 100 lỗi — sửa file / map cột rồi validate lại. Không cho commit.</p>
    @else
        <p class="err" style="margin:0">Không có dòng hợp lệ để import.</p>
    @endif
    <a class="btn ghost" href="{{ route('admin.schools.import.map') }}">← Sửa map cột</a>
</div>
@endsection
