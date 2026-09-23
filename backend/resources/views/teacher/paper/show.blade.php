@extends('layouts.teacher')
@section('title', 'Xác nhận giấy')

@section('nav')
    <a href="{{ route('teacher.dashboard') }}">Lớp của tôi</a>
    <a href="{{ route('teacher.class-forms.show', $batch->class_form_id) }}">Form lớp</a>
@endsection

@section('content')
@php
    $form = $batch->classForm?->form;
    $resolved = $form?->resolvedOptions() ?? \App\Models\Form::defaultOptions();
    $choices = $resolved['choices'];
    $ocrLabels = \App\Models\Form::ocrSummaryLabels($form);
    $agreeLabel = $ocrLabels['agree_label'];
    $otherLabel = $ocrLabels['other_label'];
    $unknownLabel = $ocrLabels['unknown_label'];
@endphp

<div class="t-top">
    <div>
        <h1>Batch #{{ $batch->id }}</h1>
        <p>Trạng thái: <span class="badge {{ $batch->status === 'confirmed' ? 'quota_full' : 'open' }}">{{ $batch->status }}</span>
            @if($form?->hasCalibratedOcrLayout())
                · <span class="muted">OCR đã calibrate theo Form</span>
            @endif
        </p>
    </div>
    <a class="btn ghost" href="{{ route('teacher.class-forms.show', $batch->class_form_id) }}"><x-icon name="arrow-left" /> Form lớp</a>
</div>

<div class="grid grid-3" style="margin-bottom:1rem">
    <div class="card kpi">
        <div class="label">{{ $agreeLabel }} (OCR)</div>
        <div class="value">{{ $batch->agree_count }}</div>
    </div>
    <div class="card kpi">
        <div class="label">{{ $otherLabel }} (OCR)</div>
        <div class="value">{{ $batch->disagree_count }}</div>
    </div>
    <div class="card kpi">
        <div class="label">{{ $unknownLabel }}</div>
        <div class="value">{{ $batch->unknown_count }}</div>
    </div>
</div>

@if($batch->status === 'processing')
    <div class="card">
        <p class="muted" style="margin:0">Đang xử lý OCR… hãy refresh trang.</p>
        <p style="margin:.75rem 0 0"><a class="btn ghost" href="{{ url()->current() }}">Refresh</a></p>
    </div>
@endif

@if(filled($batch->pending_note_body) || ! empty($batch->pending_note_files))
    <div class="card" style="margin-bottom:1rem">
        <h2 style="margin-top:0">Ghi chú sẽ lưu khi xác nhận</h2>
        @if(filled($batch->pending_note_body))
            <p style="white-space:pre-wrap;margin:.35rem 0 0">{{ $batch->pending_note_body }}</p>
        @endif
        <p class="muted" style="margin:.5rem 0 0;font-size:.85rem">
            Ảnh phiếu đã xác nhận (không bỏ qua) và PDF đính kèm (nếu có) sẽ được lưu vào phần Ghi chú của form lớp.
        </p>
    </div>
@endif

@if(in_array($batch->status, ['ready','processing'], true))
<form method="POST" action="{{ route('teacher.paper.confirm', $batch) }}">
    @csrf
    <div class="card">
        <h2>Xác nhận từng phiếu</h2>
        <p class="muted" style="margin-top:0">Chọn theo lựa chọn của Form, hoặc bỏ qua. Bấm xác nhận để lưu kết quả vote và ghi chú đính kèm.</p>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>#</th>
                    <th>Ảnh</th>
                    <th>
                        Gợi ý OCR
                        <span
                            class="muted"
                            title="Số % cạnh kết quả là chỉ số tin cậy của OCR (0–100%). Càng cao càng chắc; vẫn cần giáo viên xác nhận trước khi lưu."
                            style="display:inline-flex;align-items:center;margin-left:.2rem;cursor:help;vertical-align:middle"
                            aria-label="Chỉ số tin cậy OCR"
                        ><x-icon name="info" :size="14" /></span>
                    </th>
                    <th>Xác nhận</th>
                </tr>
                </thead>
                <tbody>
                @foreach($batch->items as $i => $item)
                    @php
                        $suggestionLabel = $item->ocr_suggestion && $item->ocr_suggestion !== 'unknown'
                            ? ($form?->choiceLabel((string) $item->ocr_suggestion) ?? $item->ocr_suggestion)
                            : $unknownLabel;
                        $confidencePct = $item->ocr_confidence !== null
                            ? (int) round(((float) $item->ocr_confidence) * 100)
                            : null;
                    @endphp
                    <tr>
                        <td>{{ $item->id }}
                            <input type="hidden" name="items[{{ $i }}][id]" value="{{ $item->id }}">
                        </td>
                        <td>
                            <a href="{{ route('teacher.paper.items.image', $item) }}" target="_blank" rel="noopener">
                                <x-icon name="eye" :size="14" /> Xem
                            </a>
                        </td>
                        <td>
                            {{ $suggestionLabel }}
                            @if($confidencePct !== null)
                                <span
                                    class="muted"
                                    title="Chỉ số tin cậy OCR: {{ $confidencePct }}%"
                                    style="white-space:nowrap"
                                >({{ $confidencePct }}%)</span>
                            @endif
                        </td>
                        <td>
                            <select name="items[{{ $i }}][choice]" style="max-width:100%">
                                @foreach($choices as $choice)
                                    <option value="{{ $choice['value'] }}" @selected($item->ocr_suggestion === $choice['value'])>
                                        {{ $choice['label'] }}
                                    </option>
                                @endforeach
                                <option value="skip" @selected($item->ocr_suggestion === 'unknown' || ! $item->ocr_suggestion)>Bỏ qua</option>
                            </select>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p style="margin-top:1rem">
            <button class="btn secondary" type="submit" @disabled($batch->status!=='ready')><x-icon name="check" /> Xác nhận lưu kết quả</button>
        </p>
    </div>
</form>
@endif
@endsection
