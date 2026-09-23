@extends('layouts.admin')
@section('title', 'Nhật ký hoạt động')

@push('head')
<style>
    .audit-filters {
        display: flex;
        gap: .75rem;
        flex-wrap: wrap;
        align-items: end;
    }
    .audit-filters > div { flex: 1; min-width: 180px; }
    .audit-filters label { margin-top: 0; font-size: .82rem; }
    .audit-filters input,
    .audit-filters select { max-width: none; }
    .audit-desc { font-weight: 500; line-height: 1.45; max-width: 520px; }
    .audit-meta { font-size: .82rem; }
    .badge.cat-login { background: #e8eef8; color: #0a2a66; }
    .badge.cat-vote { background: #e5f9f3; color: #0a6b53; }
    .badge.cat-paper { background: #fff4e5; color: #9a6700; }
    .badge.cat-class_form { background: #f0e8ff; color: #5b21b6; }
    .badge.cat-admin { background: #fdecea; color: #9b2226; }
    details.audit-tech summary {
        cursor: pointer;
        font-size: .78rem;
        color: var(--muted);
        user-select: none;
    }
    details.audit-tech pre {
        margin: .45rem 0 0;
        padding: .55rem .65rem;
        background: #f7f9fd;
        border: 1px solid var(--line);
        border-radius: 8px;
        font-size: .72rem;
        overflow-x: auto;
        max-width: 360px;
    }
</style>
@endpush

@section('content')
<div class="topbar">
    <div>
        <h1>Nhật ký hoạt động</h1>
        <p>Đọc được bằng tiếng Việt — chi tiết kỹ thuật thu gọn bên dưới mỗi dòng.</p>
    </div>
</div>

<div class="card" style="margin-bottom:1rem">
    <form method="GET" class="audit-filters">
        <div>
            <label for="category">Nhóm hoạt động</label>
            <select id="category" name="category">
                @foreach($categoryOptions as $value => $label)
                    <option value="{{ $value }}" @selected(($category ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="q">Tìm kiếm</label>
            <input
                id="q"
                type="search"
                name="q"
                value="{{ $search ?? '' }}"
                placeholder="Email admin, tên GV, tên form…"
            >
        </div>
        <button class="btn" type="submit">Lọc</button>
        <a class="btn ghost" href="{{ route('admin.audit.index') }}">Reset</a>
    </form>
</div>

<div class="card">
<table>
    <thead>
    <tr>
        <th>Thời gian</th>
        <th>Mô tả</th>
        <th>Nhóm</th>
        <th>Người thực hiện</th>
        <th>Đối tượng</th>
        <th>IP</th>
    </tr>
    </thead>
    <tbody>
    @forelse($logs as $log)
        @php
            $cat = $presenter->category($log->action);
        @endphp
        <tr>
            <td class="muted audit-meta" style="white-space:nowrap">
                {{ optional($log->created_at)?->format('d/m/Y H:i') }}
            </td>
            <td>
                <div class="audit-desc">{{ $presenter->describe($log) }}</div>
                <details class="audit-tech">
                    <summary>Chi tiết kỹ thuật</summary>
                    <div><code>{{ $log->action }}</code></div>
                    @if($log->payload)
                        <pre>{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    @else
                        <pre>—</pre>
                    @endif
                </details>
            </td>
            <td>
                <span class="badge cat-{{ $cat }}">{{ $presenter->categoryLabel($log->action) }}</span>
            </td>
            <td class="muted audit-meta">{{ $presenter->actorLabel($log) }}</td>
            <td class="muted audit-meta">{{ $presenter->entityLabel($log) }}</td>
            <td class="muted audit-meta">{{ $log->ip ?: '—' }}</td>
        </tr>
    @empty
        <tr><td colspan="6" class="muted">Chưa có log.</td></tr>
    @endforelse
    </tbody>
</table>
{{ $logs->links() }}
</div>
@endsection
