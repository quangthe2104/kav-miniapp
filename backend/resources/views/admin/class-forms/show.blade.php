@extends('layouts.admin')

@section('title', 'Chi tiết lớp × Form')

@section('content')
<div class="topbar">
    <div>
        <h1>{{ $classForm->classProfile->class_name }} · {{ $classForm->form->title }}</h1>
        <p>
            {{ $classForm->classProfile->school->name }}
            @if($classForm->classProfile->school->ward)
                · {{ $classForm->classProfile->school->ward->name }}
                @if($classForm->classProfile->school->ward->province)
                    / {{ $classForm->classProfile->school->ward->province->name }}
                @endif
            @endif
        </p>
    </div>
    <a class="btn ghost" href="{{ route('admin.dashboard', ['form_id' => $classForm->form_id]) }}">← Dashboard</a>
</div>

<div class="grid grid-4" style="margin-bottom:1rem">
    <div class="card kpi">
        <div class="label">Coverage</div>
        <div class="value">{{ $coverage }} / {{ $classForm->classProfile->quota }}</div>
        <div class="hint">Trạng thái: {{ $classForm->status }}</div>
    </div>
    <div class="card kpi">
        <div class="label">Đồng ý</div>
        <div class="value">{{ $stats['agree'] }}</div>
        <div class="hint">Phiếu valid</div>
    </div>
    <div class="card kpi">
        <div class="label">Không đồng ý</div>
        <div class="value">{{ $stats['disagree'] }}</div>
        <div class="hint">Phiếu valid</div>
    </div>
    <div class="card kpi">
        <div class="label">Tổng phiếu</div>
        <div class="value">{{ $stats['total'] }}</div>
        <div class="hint">GV: {{ $classForm->classProfile->teacher?->name ?: '—' }}</div>
    </div>
</div>

@if($classForm->teacher_note)
<div class="card" style="margin-bottom:1rem">
    <strong>Ghi chú giáo viên</strong>
    <p style="white-space:pre-wrap;margin:.5rem 0 0">{{ $classForm->teacher_note }}</p>
</div>
@endif

<div class="card">
    <strong>Danh sách phiếu (chỉ xem)</strong>
    <table style="margin-top:.75rem">
        <thead>
        <tr>
            <th>Thời gian</th>
            <th>Kênh</th>
            <th>SĐT / Zalo</th>
            <th>Kết quả</th>
        </tr>
        </thead>
        <tbody>
        @forelse($responses as $r)
            <tr>
                <td class="muted" style="white-space:nowrap">{{ optional($r->created_at)?->format('d/m/Y H:i') }}</td>
                <td>{{ $r->channel }}</td>
                <td>{{ $r->phone ?: $r->zalo_user_id ?: '—' }}</td>
                <td>{{ $r->choice === 'agree' ? 'Đồng ý' : ($r->choice === 'disagree' ? 'Không đồng ý' : $r->choice) }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">Chưa có phiếu.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $responses->links() }}
</div>
@endsection
