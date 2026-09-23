<label>PDF mẫu (tuỳ chọn)</label>
<input type="file" name="consent_pdf" id="consent_pdf" accept="application/pdf">
@if(optional($form)->consent_pdf_path)
    <p class="muted" style="margin:.35rem 0 0">
        Đã có PDF mẫu.
        <a href="{{ route('admin.forms.consent-pdf', $form) }}" target="_blank" rel="noopener">Xem / tải PDF đã upload</a>
    </p>
@else
    <p class="muted" style="margin:.35rem 0 .75rem">Chưa upload PDF mẫu. Giáo viên chỉ tải được mẫu khi Admin đã đính kèm file.</p>
@endif

@if(isset($form) && $form->id)
@php
    $ocrLayout = $form->ocrLayout() ?? [];
    $ocrConfirmed = ! empty($ocrLayout['confirmed_at']);
@endphp
<div style="margin-top:1rem;padding:1rem;border:1px dashed var(--line);border-radius:12px;background:#fafbfe">
    <h3 style="margin:.2rem 0 .5rem;font-size:1rem">Cấu hình OCR phiếu giấy</h3>
    <p class="muted" style="margin:0 0 .75rem;font-size:.85rem">
        Hệ thống chuyển trang 1 của PDF mẫu thành ảnh → tự nhận diện ô chọn → bạn xác nhận map.
        Nếu nhận diện kém, kéo một vùng bao các ô rồi nhận diện lại.
        @if($ocrConfirmed)
            <span style="color:var(--accent,#0a7)">Đã calibrate.</span>
        @else
            Chưa calibrate — OCR dùng vùng mặc định hệ thống.
        @endif
    </p>

    <div class="btn-row" style="margin:0;flex-wrap:wrap">
        <button type="button" class="btn secondary" id="ocr-from-pdf-btn"
                @disabled(! optional($form)->consent_pdf_path)>
            Nhận diện từ PDF mẫu
        </button>
        @if($ocrConfirmed)
            <button type="button" class="btn ghost" id="ocr-edit-btn">Sửa lại cấu hình</button>
        @endif
        <button type="button" class="btn ghost" id="ocr-detect-btn" style="display:none">Nhận diện lại (theo vùng)</button>
        <button type="button" class="btn ghost" id="ocr-clear-zone" style="display:none">Xóa vùng</button>
    </div>
    <p id="ocr-status" class="muted" style="font-size:.85rem;margin:.5rem 0 0"></p>
    <p id="ocr-warn" class="muted" style="font-size:.85rem;margin:.35rem 0 0;color:#b45309"></p>

    <div id="ocr-canvas-wrap" style="position:relative;margin-top:.75rem;max-width:100%;display:none;user-select:none">
        <img id="ocr-preview-img" alt="OCR preview" style="max-width:100%;display:block;border:1px solid var(--line);border-radius:8px">
        <div id="ocr-zone" style="position:absolute;border:2px solid #14bf96;background:rgba(20,191,150,.15);display:none"></div>
        <div id="ocr-boxes"></div>
    </div>
    <p id="ocr-zone-hint" class="muted" style="display:none;font-size:.82rem;margin:.4rem 0 0">
        Kéo chuột trên ảnh để bôi vùng chứa các ô Đồng ý / lựa chọn, rồi bấm «Nhận diện lại».
    </p>

    <div id="ocr-map" style="margin-top:.75rem"></div>

    <label style="display:flex;align-items:center;gap:.45rem;margin-top:.85rem;font-weight:600">
        <input type="checkbox" name="ocr_layout_confirm" value="1" id="ocr_layout_confirm" style="width:auto;max-width:none"
               @checked($ocrConfirmed)>
        Xác nhận cấu hình OCR (lưu cùng Form)
    </label>
    <input type="hidden" name="ocr_layout_json" id="ocr_layout_json" value="">
</div>
@else
    <p class="muted" style="margin-top:.75rem;font-size:.85rem">Sau khi tạo Form có PDF, mở lại trang sửa để cấu hình OCR.</p>
@endif
