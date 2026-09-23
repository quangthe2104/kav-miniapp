@extends('layouts.admin')

@section('title', 'Dashboard')

@push('head')
<style>
    .dash-sticky {
        position: sticky;
        top: 0;
        z-index: 30;
        margin: -1.25rem -1.5rem 1.1rem;
        padding: .85rem 1.5rem;
        background: rgba(247, 249, 253, .92);
        backdrop-filter: blur(10px);
        border-bottom: 1px solid var(--line);
        box-shadow: 0 8px 24px rgba(10, 42, 102, .04);
    }
    .dash-sticky form {
        display: grid;
        grid-template-columns: minmax(180px, 1.4fr) repeat(3, minmax(140px, 1fr)) auto;
        gap: .65rem;
        align-items: end;
    }
    .dash-sticky label {
        margin: 0 0 .2rem;
        font-size: .72rem;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: var(--muted);
    }
    .dash-sticky select {
        max-width: none;
        width: 100%;
        margin: 0;
    }
    .dash-sticky .actions {
        display: flex;
        gap: .45rem;
        flex-wrap: wrap;
    }
    .pct-bar {
        height: 6px;
        border-radius: 999px;
        background: var(--navy-soft);
        overflow: hidden;
        margin-top: .35rem;
    }
    .pct-bar > span {
        display: block;
        height: 100%;
        background: linear-gradient(90deg, var(--accent), var(--brand));
        border-radius: inherit;
    }
    @media (max-width: 1100px) {
        .dash-sticky form { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 700px) {
        .dash-sticky { margin: -1.25rem -1rem 1rem; padding: .75rem 1rem; }
        .dash-sticky form { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')
<div class="dash-sticky">
    <form method="GET" action="{{ route('admin.dashboard') }}" id="dash-filters">
        <div>
            <label for="form_id">Form</label>
            <select name="form_id" id="form_id" @disabled($forms->isEmpty())>
                @forelse($forms as $f)
                    <option value="{{ $f->id }}" @selected((int) ($filters['form_id'] ?? 0) === (int) $f->id)>
                        {{ $f->title }} ({{ $f->status }})
                    </option>
                @empty
                    <option value="">Chưa có Form</option>
                @endforelse
            </select>
        </div>
        <div>
            <label for="province_id">Tỉnh</label>
            <select name="province_id" id="province_id">
                <option value="">Tất cả</option>
                @foreach($provinces as $p)
                    <option value="{{ $p->id }}" @selected((int) ($filters['province_id'] ?? 0) === (int) $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="ward_id">Phường / Xã</label>
            <select name="ward_id" id="ward_id" @disabled(! $filters['province_id'])>
                <option value="">Tất cả</option>
                @foreach($wards as $w)
                    <option value="{{ $w->id }}" @selected((int) ($filters['ward_id'] ?? 0) === (int) $w->id)>{{ $w->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="school_id">Trường</label>
            <select name="school_id" id="school_id" @disabled(! $filters['ward_id'])>
                <option value="">Tất cả</option>
                @foreach($schools as $s)
                    <option value="{{ $s->id }}" @selected((int) ($filters['school_id'] ?? 0) === (int) $s->id)>
                        [{{ $s->external_id }}] {{ $s->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="actions">
            <button class="btn" type="submit">Lọc</button>
            @if($form)
                <a class="btn ghost" href="{{ route('admin.forms.export', $form) }}">Export CSV</a>
            @endif
        </div>
    </form>
</div>

<div class="topbar">
    <div>
        <h1>Dashboard theo Form</h1>
        <p>
            @if($form)
                Đang xem: <strong>{{ $form->title }}</strong>
                · KPI và biểu đồ theo phạm vi địa lý đã chọn.
            @else
                Chưa có Form — hãy tạo Form đầu tiên để theo dõi coverage.
            @endif
        </p>
    </div>
    <a class="btn" href="{{ route('admin.forms.create') }}">+ Tạo Form</a>
</div>

@if($kpis)
<div class="grid grid-4" style="margin-bottom:1rem">
    <div class="card kpi">
        <div class="label">Trường tham gia</div>
        <div class="value">{{ $kpis['school_participation_pct'] !== null ? $kpis['school_participation_pct'].'%' : '—' }}</div>
        <div class="hint">{{ $kpis['schools_participating'] }} / {{ $kpis['schools_total'] }} trường có link form</div>
    </div>
    <div class="card kpi">
        <div class="label">Lớp hoàn thành</div>
        <div class="value">{{ $kpis['class_completion_pct'] !== null ? $kpis['class_completion_pct'].'%' : '—' }}</div>
        <div class="hint">{{ $kpis['class_forms_completed'] }} / {{ $kpis['class_forms_total'] }} lớp × form</div>
    </div>
    <div class="card kpi">
        <div class="label">Phiếu hợp lệ</div>
        <div class="value">{{ number_format($kpis['valid_responses']) }}</div>
        <div class="hint">Đồng ý {{ $choice_totals['agree'] }} · Không {{ $choice_totals['disagree'] }}</div>
    </div>
    <div class="card kpi">
        <div class="label">Tỉ lệ đồng ý</div>
        <div class="value">{{ $kpis['agree_rate_pct'] !== null ? $kpis['agree_rate_pct'].'%' : '—' }}</div>
        <div class="hint">Trên phiếu valid trong phạm vi lọc</div>
    </div>
</div>

<div class="grid grid-2" style="margin-bottom:1rem">
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem">
            <strong>Phiếu 14 ngày gần nhất</strong>
            <span class="muted" style="font-size:.85rem">Valid responses / ngày</span>
        </div>
        <div class="chart-wrap">
            <canvas id="chartDaily"></canvas>
        </div>
    </div>
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem">
            <strong>Cơ cấu kết quả & kênh</strong>
            <span class="muted" style="font-size:.85rem">Đồng ý / Không · Zalo / Giấy</span>
        </div>
        <div class="grid" style="grid-template-columns:1fr 1fr;gap:1rem">
            <div class="chart-wrap sm"><canvas id="chartChoice"></canvas></div>
            <div class="chart-wrap sm"><canvas id="chartChannel"></canvas></div>
        </div>
    </div>
</div>

<div class="card" style="margin-bottom:1rem">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;gap:1rem;flex-wrap:wrap">
            <strong>Bảng lớp (A → Z)</strong>
            <span class="muted" style="font-size:.85rem">Coverage · % hoàn thành · % đồng ý · trạng thái</span>
        </div>
        <div style="overflow-x:auto">
            <table>
                <thead>
                <tr>
                    <th>Lớp</th>
                    <th>Trường</th>
                    <th>GV</th>
                    <th>N / Sĩ số</th>
                    <th>% HT</th>
                    <th>% Đ.ý</th>
                    <th>Trạng thái</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($class_rows as $row)
                    @php
                        $profile = $row->classProfile;
                        $coverage = (int) ($row->coverage_sum ?? 0);
                        $quota = (int) ($profile?->quota ?? 0);
                    @endphp
                    <tr>
                        <td style="font-weight:600;white-space:nowrap">{{ $profile?->class_name }}</td>
                        <td>
                            <div>{{ $profile?->school?->name }}</div>
                            <div class="muted" style="font-size:.82rem">{{ $profile?->school?->external_id }}</div>
                        </td>
                        <td>{{ $profile?->teacher?->name ?: '—' }}</td>
                        <td>
                            {{ $coverage }} / {{ $quota }}
                            <div class="pct-bar"><span style="width: {{ min(100, (float) $row->completion_pct) }}%"></span></div>
                        </td>
                        <td>{{ number_format((float) $row->completion_pct, 1) }}%</td>
                        <td>{{ $row->agree_pct !== null ? number_format((float) $row->agree_pct, 1).'%' : '—' }}</td>
                        <td>
                            <span class="badge {{ $row->status === 'closed' ? 'closed' : ($row->status === 'open' ? '' : 'draft') }}">
                                {{ $row->status }}
                            </span>
                        </td>
                        <td style="white-space:nowrap">
                            @if(Route::has('admin.class-forms.show'))
                                <a href="{{ route('admin.class-forms.show', $row) }}">Chi tiết</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">Chưa có lớp gắn form này trong phạm vi lọc.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($class_rows && $class_rows->hasPages())
            <div style="margin-top:.75rem">{{ $class_rows->links() }}</div>
        @endif
    </div>

    <div class="card">
        <strong>Phiếu gần đây</strong>
        <table style="margin-top:.75rem">
            <thead>
            <tr><th>Thời gian</th><th>Lớp / Trường</th><th>Kết quả</th></tr>
            </thead>
            <tbody>
            @forelse($recent as $r)
                <tr>
                    <td class="muted" style="white-space:nowrap">{{ optional($r->created_at)?->format('d/m H:i') }}</td>
                    <td>
                        <div>{{ $r->classForm?->classProfile?->class_name }}</div>
                        <div class="muted" style="font-size:.82rem">{{ $r->classForm?->classProfile?->school?->name }}</div>
                    </td>
                    <td>
                        {{ $r->choice === 'agree' ? 'Đồng ý' : ($r->choice === 'disagree' ? 'Không đồng ý' : $r->choice) }}
                        <div class="muted" style="font-size:.82rem">{{ $r->channel }}</div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="muted">Chưa có phiếu.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const province = document.getElementById('province_id');
    const ward = document.getElementById('ward_id');
    const school = document.getElementById('school_id');
    const formSelect = document.getElementById('form_id');
    const filterForm = document.getElementById('dash-filters');

    const resetSelect = (el, placeholder, disabled = true) => {
        el.innerHTML = `<option value="">${placeholder}</option>`;
        el.disabled = disabled;
    };

    province?.addEventListener('change', async () => {
        resetSelect(ward, 'Tất cả', !province.value);
        resetSelect(school, 'Tất cả', true);
        if (!province.value) return;
        ward.innerHTML = '<option value="">Đang tải...</option>';
        const res = await fetch(`{{ url('/admin/catalog/provinces') }}/${province.value}/wards`);
        const data = await res.json();
        ward.innerHTML = '<option value="">Tất cả</option>' + data.map(w => `<option value="${w.id}">${w.name}</option>`).join('');
        ward.disabled = false;
    });

    ward?.addEventListener('change', async () => {
        resetSelect(school, 'Tất cả', !ward.value);
        if (!ward.value) return;
        school.innerHTML = '<option value="">Đang tải...</option>';
        const res = await fetch(`{{ url('/admin/catalog/wards') }}/${ward.value}/schools`);
        const data = await res.json();
        school.innerHTML = '<option value="">Tất cả</option>' + data.map(s => `<option value="${s.id}">[${s.external_id}] ${s.name}</option>`).join('');
        school.disabled = false;
    });

    formSelect?.addEventListener('change', () => filterForm?.submit());
});
</script>
@if($kpis)
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(() => {
    const navy = '#0a2a66';
    const agree = '#14bf96';
    const disagree = '#b42318';
    const navySoft = 'rgba(10,42,102,.14)';
    const muted = '#5b6b8c';
    const remaining = '#cbd5e1';

    const dailyLabels = @json($daily_labels);
    const dailyValues = @json($daily_values);

    new Chart(document.getElementById('chartDaily'), {
        type: 'line',
        data: {
            labels: dailyLabels,
            datasets: [{
                label: 'Phiếu',
                data: dailyValues,
                borderColor: navy,
                backgroundColor: navySoft,
                fill: true,
                tension: .35,
                pointRadius: 3,
                pointBackgroundColor: agree,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(10,42,102,.06)' } },
                x: { grid: { display: false } },
            },
        },
    });

    new Chart(document.getElementById('chartChoice'), {
        type: 'doughnut',
        data: {
            labels: ['Đồng ý', 'Không đồng ý'],
            datasets: [{
                data: [{{ $choice_totals['agree'] }}, {{ $choice_totals['disagree'] }}],
                backgroundColor: [agree, disagree],
                borderWidth: 0,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            cutout: '62%',
        },
    });

    new Chart(document.getElementById('chartChannel'), {
        type: 'doughnut',
        data: {
            labels: ['Zalo', 'Giấy'],
            datasets: [{
                data: [{{ $by_channel['zalo'] }}, {{ $by_channel['paper'] }}],
                backgroundColor: [navy, remaining],
                borderWidth: 0,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            cutout: '62%',
        },
    });
})();
</script>
@endif
@endpush
