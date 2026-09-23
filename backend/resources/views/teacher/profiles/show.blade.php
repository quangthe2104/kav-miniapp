@extends('layouts.teacher')
@section('title', 'Chi tiết lớp')

@section('nav')
    <a href="{{ route('teacher.dashboard') }}">Lớp của tôi</a>
@endsection

@section('content')
@php
    $statusLabel = [
        'open' => 'Đang mở',
        'quota_full' => 'Đủ sĩ số',
        'closed' => 'Đã đóng',
        'none' => 'Chưa gắn',
    ];
@endphp

<div class="t-top">
    <div>
        <h1>Lớp {{ $profile->class_name }}</h1>
        <p>{{ $profile->school->name }} · {{ $profile->school->ward?->name }} / {{ $profile->school->ward?->province?->name }}</p>
    </div>
    <a class="btn ghost" href="{{ route('teacher.dashboard') }}">← Dashboard</a>
</div>

<div class="card">
    <div class="grid grid-3">
        <div>
            <div class="muted" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.04em">Trường</div>
            <div style="font-weight:600;margin-top:.2rem">{{ $profile->school->name }}</div>
            <div class="muted" style="font-size:.82rem">[{{ $profile->school->external_id }}]</div>
        </div>
        <div>
            <div class="muted" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.04em">Địa bàn</div>
            <div style="font-weight:600;margin-top:.2rem">{{ $profile->school->ward?->name }}</div>
            <div class="muted" style="font-size:.82rem">{{ $profile->school->ward?->province?->name }}</div>
        </div>
        <div>
            <div class="muted" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.04em">Sĩ số</div>
            <div style="font-weight:700;font-size:1.4rem;margin-top:.1rem;color:var(--brand)">{{ $profile->quota }}</div>
        </div>
    </div>
</div>

<div class="card">
    <h2>Form active — lấy link</h2>
    <p class="muted" style="margin-top:0">Mỗi form: tạo / mở link, xem coverage và trạng thái. Giáo viên chỉ xem kết quả phụ huynh, không sửa phiếu.</p>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Form</th>
                <th>Coverage</th>
                <th>Trạng thái</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @foreach($formRows as $row)
                @php
                    $form = $row['form'];
                    $cf = $row['class_form'];
                @endphp
                <tr>
                    <td>
                        @if($cf)
                            <a href="{{ route('teacher.class-forms.show', $cf) }}"><strong>{{ $form->title }}</strong></a>
                        @else
                            <form method="POST" action="{{ route('teacher.profiles.forms.ensure', [$profile, $form]) }}" class="inline">
                                @csrf
                                <button type="submit" class="title-link">{{ $form->title }}</button>
                            </form>
                        @endif
                    </td>
                    <td>
                        @if($cf)
                            <div>{{ $row['coverage'] }} / {{ $profile->quota }} ({{ $row['coverage_pct'] }}%)</div>
                            <div class="bar" aria-hidden="true"><span style="width:{{ $row['coverage_pct'] }}%"></span></div>
                        @else
                            <span class="muted">Chưa có link</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge {{ $row['status'] }}">{{ $statusLabel[$row['status']] ?? $row['status'] }}</span>
                    </td>
                    <td>
                        <div class="btn-row">
                            <form method="POST" action="{{ route('teacher.profiles.forms.ensure', [$profile, $form]) }}">
                                @csrf
                                <button class="btn {{ $cf ? 'ghost' : 'secondary' }}" type="submit">
                                    {{ $cf ? 'Lấy link' : 'Tạo / lấy link' }}
                                </button>
                            </form>
                            @if($cf)
                                <a class="btn" href="{{ route('teacher.class-forms.show', $cf) }}">QR & kết quả</a>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
