@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
(function () {
    const formId = {{ isset($form) && $form->id ? (int) $form->id : 'null' }};
    if (!formId) return;

    if (window['pdfjsLib']) {
        pdfjsLib.GlobalWorkerOptions.workerSrc =
            'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    }

    const detectUrl = @json(route('admin.forms.ocr.detect', $form));
    const previewUrl = @json(route('admin.forms.ocr.preview', $form));
    const pdfUrl = @json(optional($form)->consent_pdf_path ? route('admin.forms.consent-pdf', $form) : null);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('input[name="_token"]')?.value;
    const initialLayout = @json($form->ocrLayout() ?? new \stdClass());
    const choices = @json($resolved['choices'] ?? []);
    const autoRun = @json((bool) session('ocr_auto'));

    const consentInput = document.getElementById('consent_pdf');
    const fromPdfBtn = document.getElementById('ocr-from-pdf-btn');
    const editBtn = document.getElementById('ocr-edit-btn');
    const detectBtn = document.getElementById('ocr-detect-btn');
    const clearBtn = document.getElementById('ocr-clear-zone');
    const wrap = document.getElementById('ocr-canvas-wrap');
    const img = document.getElementById('ocr-preview-img');
    const zoneEl = document.getElementById('ocr-zone');
    const boxesEl = document.getElementById('ocr-boxes');
    const mapEl = document.getElementById('ocr-map');
    const warnEl = document.getElementById('ocr-warn');
    const statusEl = document.getElementById('ocr-status');
    const zoneHint = document.getElementById('ocr-zone-hint');
    const hidden = document.getElementById('ocr_layout_json');
    const confirmCb = document.getElementById('ocr_layout_confirm');
    const editor = document.getElementById('form-editor');

    let state = {
        zone: initialLayout.zone || null,
        boxes: Array.isArray(initialLayout.boxes) ? initialLayout.boxes : [],
        preview_path: initialLayout.preview_path || null,
        confirmed_at: initialLayout.confirmed_at || null,
    };
    let pageBlob = null;
    let drawing = false;
    let start = null;
    let allowDraw = false;

    function setStatus(msg) {
        if (statusEl) statusEl.textContent = msg || '';
    }

    function setManualMode(on, message) {
        allowDraw = !!on;
        if (detectBtn) detectBtn.style.display = on ? '' : 'none';
        if (clearBtn) clearBtn.style.display = on ? '' : 'none';
        if (zoneHint) zoneHint.style.display = on ? '' : 'none';
        if (zoneEl) zoneEl.style.cursor = on ? 'crosshair' : 'default';
        if (warnEl && message) warnEl.textContent = message;
    }

    function syncHidden() {
        if (!hidden) return;
        hidden.value = JSON.stringify({
            preview_path: state.preview_path,
            zone: state.zone,
            boxes: state.boxes,
        });
    }

    function pctBox(x1, y1, x2, y2, nw, nh) {
        const left = Math.min(x1, x2);
        const top = Math.min(y1, y2);
        return {
            x: +(left / nw * 100).toFixed(2),
            y: +(top / nh * 100).toFixed(2),
            w: +(Math.abs(x2 - x1) / nw * 100).toFixed(2),
            h: +(Math.abs(y2 - y1) / nh * 100).toFixed(2),
        };
    }

    function renderZone() {
        if (!zoneEl || !img || !state.zone) {
            if (zoneEl) zoneEl.style.display = 'none';
            return;
        }
        const nw = img.clientWidth;
        const nh = img.clientHeight;
        zoneEl.style.display = 'block';
        zoneEl.style.left = (state.zone.x / 100 * nw) + 'px';
        zoneEl.style.top = (state.zone.y / 100 * nh) + 'px';
        zoneEl.style.width = (state.zone.w / 100 * nw) + 'px';
        zoneEl.style.height = (state.zone.h / 100 * nh) + 'px';
    }

    function renderBoxes() {
        if (!boxesEl || !img) return;
        boxesEl.innerHTML = '';
        const nw = img.clientWidth;
        const nh = img.clientHeight;
        state.boxes.forEach((b, i) => {
            const el = document.createElement('div');
            el.style.cssText = 'position:absolute;border:2px solid #0a2a66;background:rgba(10,42,102,.12);pointer-events:none;font-size:11px;color:#0a2a66;font-weight:600;padding:1px 3px';
            el.style.left = (b.x / 100 * nw) + 'px';
            el.style.top = (b.y / 100 * nh) + 'px';
            el.style.width = (b.w / 100 * nw) + 'px';
            el.style.height = (b.h / 100 * nh) + 'px';
            el.textContent = b.id || ('b' + (i + 1));
            boxesEl.appendChild(el);
        });
        renderMap();
        syncHidden();
    }

    function renderMap() {
        if (!mapEl) return;
        if (!state.boxes.length) {
            mapEl.innerHTML = '';
            return;
        }
        mapEl.innerHTML = '<p style="margin:0 0 .4rem;font-weight:600">Kết quả nhận diện — xác nhận map ô → lựa chọn</p>';
        state.boxes.forEach((b, i) => {
            const row = document.createElement('div');
            row.style.cssText = 'display:flex;gap:.5rem;align-items:center;margin-bottom:.35rem;flex-wrap:wrap';
            const label = document.createElement('span');
            label.textContent = (b.id || ('b' + (i + 1))) + ' (' + (b.shape || 'square') + ')';
            label.style.minWidth = '7rem';
            const sel = document.createElement('select');
            sel.innerHTML = '<option value="">— chọn —</option>' + choices.map((c) =>
                `<option value="${c.value}" ${b.option_value === c.value ? 'selected' : ''}>${c.label}</option>`
            ).join('');
            sel.addEventListener('change', () => {
                state.boxes[i].option_value = sel.value;
                syncHidden();
            });
            row.appendChild(label);
            row.appendChild(sel);
            mapEl.appendChild(row);
        });
    }

    function showPreview(url) {
        if (!img || !wrap) return;
        return new Promise((resolve) => {
            img.onload = () => {
                wrap.style.display = 'block';
                renderZone();
                renderBoxes();
                resolve();
            };
            img.src = url + (String(url).includes('blob:') ? '' : ((url.includes('?') ? '&' : '?') + 't=' + Date.now()));
        });
    }

    async function pdfToPngBlob(pdfSource) {
        if (!window['pdfjsLib']) throw new Error('Không tải được thư viện PDF.js');
        const loadingTask = typeof pdfSource === 'string'
            ? pdfjsLib.getDocument({ url: pdfSource, withCredentials: true })
            : pdfjsLib.getDocument({ data: pdfSource });
        const pdf = await loadingTask.promise;
        const page = await pdf.getPage(1);
        const viewport = page.getViewport({ scale: 1.6 });
        const canvas = document.createElement('canvas');
        canvas.width = viewport.width;
        canvas.height = viewport.height;
        await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
        return await new Promise((resolve, reject) => {
            canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('Không tạo được ảnh từ PDF'))), 'image/png');
        });
    }

    async function arrayBufferFromFile(file) {
        return await file.arrayBuffer();
    }

    async function postDetect(blob, withZone) {
        const fd = new FormData();
        fd.append('image', blob, 'preview.png');
        if (withZone && state.zone) {
            fd.append('zone[x]', state.zone.x);
            fd.append('zone[y]', state.zone.y);
            fd.append('zone[w]', state.zone.w);
            fd.append('zone[h]', state.zone.h);
        }
        const res = await fetch(detectUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: fd,
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            throw new Error(data.message || data.errors?.image?.[0] || ('Lỗi ' + res.status));
        }
        return data;
    }

    async function applyDetectResult(data) {
        state.zone = data.zone || state.zone;
        state.boxes = data.boxes || [];
        state.preview_path = data.preview_path || state.preview_path;
        if (warnEl) warnEl.textContent = data.warning || '';
        await showPreview(data.preview_url || previewUrl);
        renderZone();
        renderBoxes();
        setManualMode(!!data.needs_manual_zone || !!(data.warning && data.boxes && data.boxes.length !== choices.length), data.warning || null);
        if (data.needs_manual_zone) {
            setStatus('Cần bôi vùng nhận diện trên ảnh.');
        } else if (!data.needs_manual_zone && state.boxes.length >= 2 && confirmCb) {
            setStatus('Đã nhận diện ' + state.boxes.length + ' ô. Kiểm tra map rồi tick «Xác nhận cấu hình OCR» và Lưu Form.');
        } else {
            setStatus('Đã nhận diện. Hãy xác nhận map và lưu Form.');
        }
    }

    async function runFromPdf() {
        const localPdf = consentInput?.files?.[0];
        fromPdfBtn && (fromPdfBtn.disabled = true);
        setStatus('Đang chuyển PDF → ảnh (trang 1)…');
        if (warnEl) warnEl.textContent = '';
        try {
            let blob;
            if (localPdf) {
                const buf = await arrayBufferFromFile(localPdf);
                blob = await pdfToPngBlob(buf);
            } else if (pdfUrl) {
                blob = await pdfToPngBlob(pdfUrl);
            } else {
                throw new Error('Chưa có PDF mẫu. Chọn file PDF rồi Lưu Form, hoặc chọn PDF mới rồi bấm nhận diện.');
            }
            pageBlob = blob;
            const objectUrl = URL.createObjectURL(blob);
            await showPreview(objectUrl);
            setStatus('Đang tự nhận diện ô…');
            const data = await postDetect(blob, false);
            await applyDetectResult(data);
        } catch (err) {
            setManualMode(true, err.message || 'Nhận diện thất bại — hãy bôi vùng trên ảnh.');
            setStatus('Lỗi: ' + (err.message || 'không xác định'));
        } finally {
            if (fromPdfBtn) {
                fromPdfBtn.disabled = !pdfUrl && !(consentInput?.files?.[0]);
            }
        }
    }

    async function runDetectWithZone() {
        if (!pageBlob && !state.preview_path) {
            alert('Chạy «Nhận diện từ PDF mẫu» trước.');
            return;
        }
        if (!state.zone || state.zone.w < 2 || state.zone.h < 2) {
            alert('Hãy kéo một vùng bao các ô chọn trên ảnh.');
            return;
        }
        detectBtn.disabled = true;
        setStatus('Đang nhận diện theo vùng đã chọn…');
        try {
            let blob = pageBlob;
            if (!blob) {
                const resPrev = await fetch(previewUrl, { credentials: 'same-origin' });
                if (!resPrev.ok) throw new Error('Không tải được ảnh preview.');
                blob = await resPrev.blob();
                pageBlob = blob;
            }
            const data = await postDetect(blob, true);
            await applyDetectResult(data);
        } catch (err) {
            alert(err.message || 'Nhận diện thất bại.');
        } finally {
            detectBtn.disabled = false;
        }
    }

    img?.addEventListener('mousedown', (e) => {
        if (!allowDraw || !img.clientWidth) return;
        drawing = true;
        const rect = img.getBoundingClientRect();
        start = { x: e.clientX - rect.left, y: e.clientY - rect.top };
        e.preventDefault();
    });
    window.addEventListener('mousemove', (e) => {
        if (!drawing || !start || !zoneEl || !img) return;
        const rect = img.getBoundingClientRect();
        const x = Math.max(0, Math.min(img.clientWidth, e.clientX - rect.left));
        const y = Math.max(0, Math.min(img.clientHeight, e.clientY - rect.top));
        state.zone = pctBox(start.x, start.y, x, y, img.clientWidth, img.clientHeight);
        renderZone();
    });
    window.addEventListener('mouseup', () => {
        if (!drawing) return;
        drawing = false;
        syncHidden();
    });

    clearBtn?.addEventListener('click', () => {
        state.zone = null;
        renderZone();
        syncHidden();
    });

    fromPdfBtn?.addEventListener('click', () => runFromPdf());
    detectBtn?.addEventListener('click', () => runDetectWithZone());

    editBtn?.addEventListener('click', () => {
        state.confirmed_at = null;
        if (confirmCb) confirmCb.checked = false;
        setManualMode(true, 'Đang sửa cấu hình OCR. Chạy nhận diện lại hoặc kéo vùng rồi xác nhận.');
        setStatus('Chế độ sửa cấu hình — nhận diện lại từ PDF hoặc kéo vùng.');
        if (fromPdfBtn) {
            fromPdfBtn.style.display = '';
            fromPdfBtn.disabled = !(pdfUrl || consentInput?.files?.[0]);
        }
        if (editBtn) editBtn.style.display = 'none';
        if (state.preview_path) {
            showPreview(previewUrl);
        }
        syncHidden();
    });

    // If already calibrated, show preview/boxes but keep draw tools hidden until "Sửa lại"
    if (state.confirmed_at) {
        if (fromPdfBtn) fromPdfBtn.style.display = 'none';
    }

    consentInput?.addEventListener('change', () => {
        if (fromPdfBtn) {
            fromPdfBtn.style.display = '';
            fromPdfBtn.disabled = !(consentInput.files?.[0] || pdfUrl);
        }
        if (consentInput.files?.[0]) {
            setStatus('Đã chọn PDF mới. Có thể bấm «Nhận diện từ PDF mẫu» ngay (chưa cần Lưu), rồi Lưu Form kèm xác nhận OCR.');
        }
    });

    editor?.addEventListener('submit', () => syncHidden());

    if (state.preview_path) {
        showPreview(previewUrl).then(() => {
            if (state.boxes?.length) renderBoxes();
            if (state.confirmed_at) {
                setManualMode(false);
                setStatus('Đã calibrate. Bấm «Sửa lại cấu hình» nếu cần chỉnh vùng / map.');
            } else if (!state.boxes || state.boxes.length < 2) {
                setManualMode(true);
            }
        });
    }

    syncHidden();
    window.addEventListener('resize', () => {
        renderZone();
        renderBoxes();
    });

    if (autoRun && (pdfUrl || consentInput?.files?.[0])) {
        runFromPdf();
    }
})();
</script>
@endpush
