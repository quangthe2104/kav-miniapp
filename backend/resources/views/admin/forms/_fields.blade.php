@php
    $preset = old('options_preset', $resolved['preset'] ?? 'agree_disagree');
    $customChoices = old('custom_choices');
    if (! is_array($customChoices)) {
        $customChoices = ($resolved['preset'] ?? '') === 'custom'
            ? ($resolved['choices'] ?? [])
            : [
                ['value' => '', 'label' => ''],
                ['value' => '', 'label' => ''],
            ];
    }
    while (count($customChoices) < 2) {
        $customChoices[] = ['value' => '', 'label' => ''];
    }
    $agreeSelected = old('agree_values', $resolved['agree_values'] ?? []);
    if (! is_array($agreeSelected)) {
        $agreeSelected = [];
    }
    $noEndDate = (bool) old('no_end_date', optional($form)->no_end_date ?? false);
@endphp

<label>Tiêu đề</label>
<input name="title" value="{{ old('title', optional($form)->title) }}" required>

<label>Nội dung hiển thị phụ huynh</label>
<textarea name="content" rows="8" required>{{ old('content', optional($form)->content) }}</textarea>

<label>Tùy chọn kết quả</label>
<select name="options_preset" id="options_preset">
    <option value="agree_disagree" @selected($preset === 'agree_disagree')>Đồng ý / Không đồng ý</option>
    <option value="yes_no" @selected($preset === 'yes_no')>Có / Không</option>
    <option value="custom" @selected($preset === 'custom')>Tùy chỉnh kết quả</option>
</select>

<div id="custom-options-panel" style="{{ $preset === 'custom' ? '' : 'display:none;' }} margin-top:.75rem; padding:.85rem 1rem; border:1px dashed var(--line); border-radius:12px; background:#fafbfe">
    <p class="muted" style="margin:.2rem 0 .75rem">Mỗi dòng: <strong>value</strong> (slug a-z0-9_-) và <strong>label</strong> hiển thị. Đánh dấu lựa chọn tính KPI “đồng ý”. Không giới hạn số lượng (tối thiểu 2).</p>
    <div id="custom-choices">
        @foreach($customChoices as $i => $row)
            <div class="custom-choice-row" style="display:grid;grid-template-columns:1fr 1.4fr auto auto;gap:.45rem;align-items:end;margin-bottom:.45rem">
                <div>
                    <label style="margin:0;font-size:.8rem">Value</label>
                    <input name="custom_choices[{{ $i }}][value]" value="{{ $row['value'] ?? '' }}" placeholder="vd: yes" pattern="[a-z0-9_-]+" maxlength="32">
                </div>
                <div>
                    <label style="margin:0;font-size:.8rem">Label</label>
                    <input name="custom_choices[{{ $i }}][label]" value="{{ $row['label'] ?? '' }}" placeholder="vd: Có" maxlength="100">
                </div>
                <label style="display:flex;align-items:center;gap:.35rem;font-weight:500;margin:0 0 .35rem;white-space:nowrap">
                    <input type="checkbox" name="agree_values[]" value="{{ $row['value'] ?? '' }}" class="agree-check"
                           style="width:auto;max-width:none"
                           @checked(in_array(($row['value'] ?? ''), $agreeSelected, true))
                           data-sync-from-value>
                    KPI đồng ý
                </label>
                <button type="button" class="btn ghost remove-choice" style="padding:.4rem .55rem" @disabled(count($customChoices) <= 2)>×</button>
            </div>
        @endforeach
    </div>
    <button type="button" class="btn ghost" id="add-choice" style="margin-top:.35rem">+ Thêm lựa chọn</button>
</div>

<label>Trạng thái</label>
<select name="status">
    @foreach([
        'draft' => 'draft — nháp',
        'active' => 'active — đang mở',
        'closed' => 'closed — đã đóng / lưu trữ',
    ] as $st => $label)
        <option value="{{ $st }}" @selected(old('status', optional($form)->status ?? 'active') === $st)>{{ $label }}</option>
    @endforeach
</select>
<p class="muted" style="margin:.25rem 0 .75rem">Không xóa Form — dùng <strong>closed</strong> để đóng/lưu trữ. Form closed không nhận phiếu mới.</p>

<label>Bắt đầu</label>
<input type="datetime-local" name="starts_at" value="{{ old('starts_at', optional(optional($form)->starts_at)->format('Y-m-d\TH:i')) }}">

<label>Kết thúc</label>
<input type="datetime-local" name="ends_at" id="ends_at" value="{{ old('ends_at', optional(optional($form)->ends_at)->format('Y-m-d\TH:i')) }}" @disabled($noEndDate)>

<label style="display:flex;align-items:center;gap:.5rem;font-weight:600;margin-top:.65rem">
    <input type="checkbox" name="no_end_date" value="1" id="no_end_date" style="width:auto;max-width:none" @checked($noEndDate)>
    Không kết thúc
</label>
<p class="muted" style="margin:.25rem 0 0">Khi bật, hệ thống bỏ ngày kết thúc — form active không tự hết hạn theo lịch.</p>
