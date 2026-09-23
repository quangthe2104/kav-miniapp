@extends('layouts.admin')
@section('title','Danh sách Trường')
@section('content')
<div class="topbar">
    <div>
        <h1>Danh sách Trường</h1>
        <p>Catalog trường theo tỉnh / phường-xã. Import Excel để thêm hoặc cập nhật theo <code>external_id</code>.</p>
    </div>
    <a class="btn secondary" href="{{ route('admin.schools.import.create') }}">Import Excel</a>
</div>

<div class="grid grid-3" style="margin-bottom:1rem">
    <div class="card kpi">
        <div class="label">Trong phạm vi lọc</div>
        <div class="value">{{ $totalInScope }}</div>
        <div class="hint">Số trường khớp bộ lọc hiện tại</div>
    </div>
    <div class="card kpi">
        <div class="label">Trang này</div>
        <div class="value">{{ $schools->count() }}</div>
        <div class="hint">Hiển thị {{ $schools->firstItem() ?? 0 }}–{{ $schools->lastItem() ?? 0 }}</div>
    </div>
    <div class="card kpi">
        <div class="label">Tổng hệ thống</div>
        <div class="value">{{ $totalSystem }}</div>
        <div class="hint">Toàn bộ catalog</div>
    </div>
</div>

<div class="card" style="margin-bottom:1rem">
    <form method="GET" action="{{ route('admin.schools.index') }}" id="school-filters" style="display:flex;gap:.75rem;align-items:end;flex-wrap:wrap">
        <div style="min-width:180px;flex:1">
            <label for="province_id">Tỉnh</label>
            <select name="province_id" id="province_id">
                <option value="">Tất cả tỉnh</option>
                @foreach($provinces as $p)
                    <option value="{{ $p->id }}" @selected((int) ($filters['province_id'] ?? 0) === (int) $p->id)>
                        {{ $p->name }} ({{ $p->code }})
                    </option>
                @endforeach
            </select>
        </div>
        <div style="min-width:180px;flex:1">
            <label for="ward_id">Phường / Xã</label>
            <select name="ward_id" id="ward_id" @disabled(! $filters['province_id'])>
                <option value="">Tất cả</option>
                @foreach($wards as $w)
                    <option value="{{ $w->id }}" @selected((int) ($filters['ward_id'] ?? 0) === (int) $w->id)>
                        {{ $w->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div style="min-width:200px;flex:1.4">
            <label for="q">Tìm tên / mã trường</label>
            <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="VD: Hà Lan hoặc 38381402">
        </div>
        <button class="btn secondary" type="submit">Lọc</button>
        @if($filters['province_id'] || $filters['ward_id'] || ($filters['q'] ?? '') !== '')
            <a class="btn ghost" href="{{ route('admin.schools.index') }}">Xóa lọc</a>
        @endif
    </form>
</div>

<div class="card">
<table>
<thead>
<tr>
    <th>Mã trường</th>
    <th>Tên</th>
    <th>Phường / Xã</th>
    <th>Tỉnh</th>
    <th>Cấp</th>
</tr>
</thead>
<tbody>
@forelse($schools as $s)
<tr>
    <td><code>{{ $s->external_id }}</code></td>
    <td>{{ $s->name }}</td>
    <td>{{ $s->ward?->name ?? '—' }}</td>
    <td>{{ $s->ward?->province?->name ?? '—' }}</td>
    <td>{{ $s->level ?: '—' }}</td>
</tr>
@empty
<tr><td colspan="5" class="muted">Không có trường nào khớp bộ lọc.</td></tr>
@endforelse
</tbody>
</table>
{{ $schools->links() }}
</div>
@endsection

@push('scripts')
<script>
(() => {
    const province = document.getElementById('province_id');
    const ward = document.getElementById('ward_id');
    if (!province || !ward) return;

    const resetWard = () => {
        ward.innerHTML = '<option value="">Tất cả</option>';
        ward.disabled = !province.value;
    };

    province.addEventListener('change', async () => {
        resetWard();
        if (!province.value) return;
        ward.innerHTML = '<option value="">Đang tải…</option>';
        const res = await fetch(`{{ url('/admin/catalog/provinces') }}/${province.value}/wards`);
        const data = await res.json();
        ward.innerHTML = '<option value="">Tất cả</option>' + data.map(w =>
            `<option value="${w.id}">${w.name}</option>`
        ).join('');
        ward.disabled = false;
    });
})();
</script>
@endpush
