@extends('layouts.teacher')
@section('title', 'Chi tiết form lớp')

@section('nav')
    <a href="{{ route('teacher.dashboard', ['profile_id' => $classForm->class_profile_id]) }}">Lớp của tôi</a>
@endsection

@section('content')
@php
    $form = $classForm->form;
    $statusLabel = [
        'open' => 'Đang mở',
        'quota_full' => 'Đủ sĩ số',
        'closed' => 'Đã đóng',
    ];
    $quota = max(1, (int) $classForm->classProfile->quota);
    $pct = (int) min(100, round(($coverage / $quota) * 100));
    $channelLabel = ['zalo' => 'Zalo', 'paper' => 'Giấy'];
    $canEditPaper = $canEditPaper ?? false;
    $sttBase = ($responses->currentPage() - 1) * $responses->perPage();
    $remaining = max(0, $quota - (int) $coverage);
    $chartLabels = array_map(fn ($c) => $c['label'], $stats['choice_counts']);
    $chartValues = array_map(fn ($c) => (int) $c['count'], $stats['choice_counts']);
    $chartChoiceValues = array_map(fn ($c) => (string) $c['value'], $stats['choice_counts']);
    $chartColors = $form->choiceChartColors($chartChoiceValues);
    $chartLabels[] = 'Chưa bình chọn';
    $chartValues[] = $remaining;
    $chartColors[] = \App\Models\Form::remainingChartColor();
@endphp

@push('head')
<style>
    .modal-backdrop {
        position: fixed; inset: 0;
        display: flex; align-items: center; justify-content: center;
        padding: 1rem; z-index: 80;
        background: rgba(10, 42, 102, .42);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: opacity .24s ease, visibility .24s ease, backdrop-filter .24s ease;
    }
    .modal-backdrop.is-open {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
    }
    .modal-sheet {
        background: #fff; border-radius: 16px; width: min(920px, 100%);
        max-height: min(90vh, 900px); display: flex; flex-direction: column;
        box-shadow: 0 20px 50px rgba(10, 42, 102, .22);
        transform: translateY(14px) scale(.97);
        opacity: 0;
        transition: transform .28s cubic-bezier(.22, 1, .36, 1), opacity .22s ease;
        will-change: transform, opacity;
    }
    .modal-backdrop.is-open .modal-sheet {
        transform: translateY(0) scale(1);
        opacity: 1;
    }
    .modal-head {
        display: flex; align-items: center; justify-content: space-between;
        gap: .75rem; padding: .85rem 1rem; border-bottom: 1px solid var(--line);
    }
    .modal-head h3 { margin: 0; font-size: 1.05rem; }
    .modal-body { padding: .75rem; overflow: auto; flex: 1; }
    .modal-body iframe { width: 100%; min-height: 70vh; border: 0; border-radius: 10px; background: #fff; }
    .modal-body img.paper-preview { max-width: 100%; height: auto; display: block; margin: 0 auto; border-radius: 10px; }
    .modal-body iframe.file-preview { width: 100%; min-height: 70vh; border: 0; border-radius: 10px; background: #fff; }
    .btn.tiny { padding: .3rem .55rem; font-size: .8rem; }
    .btn.icon-only {
        padding: .3rem;
        min-width: 2rem;
        background: transparent;
        color: var(--muted) !important;
    }
    .btn.icon-only:hover { background: #e8edf5; color: var(--ink) !important; }
    .modal-head .js-modal-close { padding: .35rem; min-width: 2rem; }
    body.modal-open { overflow: hidden; }
    .cf-row { margin-bottom: 1rem; }
    .cf-stat { display: flex; flex-direction: column; gap: .85rem; height: 100%; }
    .cf-stat .kpi-line { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem; }
    .cf-stat .kpi-line .value { font-size: 1.65rem; font-weight: 700; color: var(--brand); line-height: 1.1; }
    .cf-stat .chart-wrap { position: relative; height: 220px; max-width: 280px; margin: 0 auto; }
    .cf-legend { display: flex; flex-wrap: wrap; gap: .45rem .85rem; justify-content: center; font-size: .82rem; color: var(--muted); }
    .cf-legend span { display: inline-flex; align-items: center; gap: .35rem; }
    .cf-legend i { width: .7rem; height: .7rem; border-radius: 50%; display: inline-block; }
    .notes-saved-list { list-style: none; padding: 0; margin: 0; display: grid; gap: .65rem; max-height: 420px; overflow: auto; }
    .cf-close-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: .85rem 1.25rem;
        margin: .25rem 0 0;
        padding: 1rem 1.15rem;
        background: var(--panel);
        border: 1px solid var(--line);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
    }
    .cf-close-row .cf-close-hint {
        margin: 0;
        flex: 1 1 240px;
        font-size: .9rem;
        color: var(--muted);
        line-height: 1.45;
        max-width: 42rem;
    }
    @media (prefers-reduced-motion: reduce) {
        .modal-backdrop,
        .modal-sheet { transition: none !important; }
        .modal-sheet { transform: none; opacity: 1; }
    }
</style>
@endpush

<div class="t-top">
    <div>
        <h1>{{ $form->title }}</h1>
        <p>
            Lớp {{ $classForm->classProfile->class_name }} —
            {{ $classForm->classProfile->school->name }}
            · <span class="badge {{ $classForm->effectiveStatus() }}">{{ $statusLabel[$classForm->effectiveStatus()] ?? $classForm->effectiveStatus() }}</span>
        </p>
        @if(isset($profiles) && $profiles->count() > 1)
            <form method="GET" action="{{ route('teacher.dashboard') }}" class="class-switcher">
                <select name="profile_id" id="profile_id" aria-label="Chọn lớp" onchange="this.form.submit()">
                    @foreach($profiles as $p)
                        <option value="{{ $p->id }}" @selected((int) $classForm->class_profile_id === (int) $p->id)>
                            {{ $p->class_name }} — {{ $p->school->name }}
                        </option>
                    @endforeach
                </select>
                <noscript><button class="btn" type="submit">Xem lớp</button></noscript>
            </form>
        @endif
    </div>
    <a class="btn ghost" href="{{ route('teacher.dashboard', ['profile_id' => $classForm->class_profile_id, 'list' => 1]) }}">
        <x-icon name="arrow-left" /> Dashboard
    </a>
</div>

@php $formActive = (bool) $form?->isActive(); @endphp

@if(! $formActive)
    <div class="alert-warning" role="alert">
        Admin đã đóng Form — phụ huynh không bình chọn được. Giáo viên không mở lại được cho đến khi Admin mở Form.
    </div>
@endif

<div class="grid-detail cf-row">
    <div class="card cf-stat" style="margin:0">
        <h2>Tỉ lệ hoàn thành</h2>
        <div class="kpi-line">
            <div>
                <div class="value">{{ $coverage }}/{{ $quota }}</div>
                <div class="muted" style="margin-top:.2rem">{{ $pct }}% sĩ số · {{ $stats['total'] }} phiếu</div>
            </div>
        </div>
        <div class="bar" style="max-width:100%" aria-hidden="true"><span style="width:{{ $pct }}%"></span></div>
        <div class="chart-wrap">
            <canvas id="chart-quota" aria-label="Biểu đồ kết quả trên sĩ số"></canvas>
        </div>
        <div class="cf-legend">
            @foreach($stats['choice_counts'] as $i => $c)
                <span><i style="background:{{ $chartColors[$i] ?? '#94a3b8' }}"></i>{{ $c['label'] }} ({{ $c['count'] }})</span>
            @endforeach
            <span><i style="background:{{ $chartColors[count($stats['choice_counts'])] ?? '#cbd5e1' }}"></i>Chưa bình chọn ({{ $remaining }})</span>
        </div>
    </div>

    @if($formActive)
    <div class="card" style="margin:0">
        <h2>Link &amp; QR</h2>
        @if($voteUrl)
            <div class="qr-box">
                <img
                    id="vote-qr-img"
                    src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=8&data={{ urlencode($voteUrl) }}"
                    width="220"
                    height="220"
                    alt="QR code link vote"
                >
            </div>
            <p class="vote-url" id="vote-url-text">{{ $voteUrl }}</p>
            <div class="btn-row" style="justify-content:center">
                <button class="btn" type="button" id="btn-share-zalo" data-url="{{ $voteUrl }}">
                    <x-icon name="share" /> Share Zalo
                </button>
                <button class="btn secondary" type="button" id="btn-copy-link" data-url="{{ $voteUrl }}">
                    <x-icon name="copy" /> Copy link
                </button>
                <a class="btn ghost" id="btn-download-qr" href="{{ route('teacher.class-forms.qr', $classForm) }}">
                    <x-icon name="qr" /> Tải QR
                </a>
                @if($hasTemplate)
                    <a class="btn ghost" href="{{ route('teacher.class-forms.template', $classForm) }}">
                        <x-icon name="download" /> Tải mẫu phiếu
                    </a>
                @endif
            </div>
        @else
            <p class="muted">Chưa có link. Bấm lấy link để tạo mã cố định.</p>
            <form method="POST" action="{{ route('teacher.profiles.forms.ensure', [$classForm->classProfile, $form]) }}">
                @csrf
                <button class="btn" type="submit"><x-icon name="link" /> Lấy link</button>
            </form>
        @endif
    </div>
    @else
    <div class="card" style="margin:0">
        <h2>Ghi chú đã lưu</h2>
        @if($notes->isNotEmpty())
            <ul class="notes-saved-list">
                @foreach($notes as $note)
                    <li style="padding:.75rem .85rem;background:var(--navy-soft);border-radius:10px">
                        <div class="muted" style="font-size:.78rem;display:flex;justify-content:space-between;gap:.5rem;flex-wrap:wrap">
                            <span>
                                {{ $note->created_at?->format('d/m/Y H:i') }}
                                @if($note->teacher?->name) · {{ $note->teacher->name }} @endif
                            </span>
                        </div>
                        @if($note->body)
                            <p style="white-space:pre-wrap;margin:.35rem 0 0">{{ $note->body }}</p>
                        @endif
                        @if($note->hasFile())
                            <p style="margin:.4rem 0 0">
                                @if($note->isImage() || $note->isPdf())
                                    <a href="{{ route('teacher.class-form-notes.file', $note) }}"
                                       class="js-file-view"
                                       data-title="{{ $note->file_name ?: 'File đính kèm' }}"
                                       data-url="{{ route('teacher.class-form-notes.file', ['note' => $note, 'inline' => 1]) }}"
                                       data-type="{{ $note->isPdf() ? 'pdf' : 'image' }}">
                                        <x-icon name="file" :size="14" /> {{ $note->file_name ?: 'Xem file' }}
                                    </a>
                                @else
                                    <a href="{{ route('teacher.class-form-notes.file', $note) }}">
                                        <x-icon name="file" :size="14" /> {{ $note->file_name ?: 'Tải file' }}
                                    </a>
                                @endif
                            </p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @elseif($classForm->teacher_note)
            <p class="muted" style="margin-top:0">Ghi chú cũ:</p>
            <p style="white-space:pre-wrap">{{ $classForm->teacher_note }}</p>
        @else
            <p class="muted" style="margin:0">Chưa có ghi chú.</p>
        @endif
    </div>
    @endif
</div>

@if($formActive)
<div class="grid-detail cf-row">
    <div class="card" style="margin:0">
        <h2>Ghi chú &amp; phiếu giấy</h2>
        <form id="note-ocr-form" method="POST" action="{{ route('teacher.class-forms.note', $classForm) }}" enctype="multipart/form-data">
            @csrf
            <label for="note-body">Nội dung ghi chú</label>
            <textarea id="note-body" name="body" rows="4" style="max-width:100%" placeholder="Ghi chú lớp / lưu ý…">{{ old('body') }}</textarea>

            <label style="margin-top:.75rem">Đính kèm file</label>
            <input type="file" id="note-files" name="files[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf">
            <p class="muted" style="font-size:.8rem;margin:.35rem 0 0">
                Ảnh phiếu → OCR trong popup; ghi chú + file lưu khi xác nhận kết quả.
            </p>

            <div class="btn-row" style="margin-top:.9rem">
                <button class="btn" type="submit" id="note-submit-btn"><x-icon name="upload" /> Lưu / chạy OCR</button>
            </div>
        </form>
    </div>

    <div class="card" style="margin:0">
        <h2>Ghi chú đã lưu</h2>
        @if($notes->isNotEmpty())
            <ul class="notes-saved-list">
                @foreach($notes as $note)
                    <li style="padding:.75rem .85rem;background:var(--navy-soft);border-radius:10px">
                        <div class="muted" style="font-size:.78rem;display:flex;justify-content:space-between;gap:.5rem;flex-wrap:wrap">
                            <span>
                                {{ $note->created_at?->format('d/m/Y H:i') }}
                                @if($note->teacher?->name) · {{ $note->teacher->name }} @endif
                            </span>
                            @if($canEditPaper)
                                <form method="POST" action="{{ route('teacher.class-form-notes.destroy', $note) }}"
                                      onsubmit="return confirm('Xóa ghi chú này?');" style="margin:0">
                                    @csrf @method('DELETE')
                                    <button class="btn icon-only tiny" type="submit" aria-label="Xóa ghi chú" title="Xóa ghi chú"><x-icon name="trash" :size="16" /></button>
                                </form>
                            @endif
                        </div>
                        @if($note->body)
                            <p style="white-space:pre-wrap;margin:.35rem 0 0">{{ $note->body }}</p>
                        @endif
                        @if($note->hasFile())
                            <p style="margin:.4rem 0 0">
                                @if($note->isImage() || $note->isPdf())
                                    <a href="{{ route('teacher.class-form-notes.file', $note) }}"
                                       class="js-file-view"
                                       data-title="{{ $note->file_name ?: 'File đính kèm' }}"
                                       data-url="{{ route('teacher.class-form-notes.file', ['note' => $note, 'inline' => 1]) }}"
                                       data-type="{{ $note->isPdf() ? 'pdf' : 'image' }}">
                                        <x-icon name="file" :size="14" /> {{ $note->file_name ?: 'Xem file' }}
                                    </a>
                                @else
                                    <a href="{{ route('teacher.class-form-notes.file', $note) }}">
                                        <x-icon name="file" :size="14" /> {{ $note->file_name ?: 'Tải file' }}
                                    </a>
                                @endif
                            </p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @elseif($classForm->teacher_note)
            <p class="muted" style="margin-top:0">Ghi chú cũ:</p>
            <p style="white-space:pre-wrap">{{ $classForm->teacher_note }}</p>
        @else
            <p class="muted" style="margin:0">Chưa có ghi chú.</p>
        @endif
    </div>
</div>
@endif

<div class="card">
    <h2>Chi tiết Bình chọn</h2>
    <p class="muted" style="margin-top:0">
        Phiếu Zalo: chỉ xem.
        @if($canEditPaper)
            Phiếu giấy do bạn tải: có thể xóa khi vote/form còn mở.
        @endif
    </p>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>STT</th>
                <th>Tên (Zalo)</th>
                <th>SĐT</th>
                <th>Kênh</th>
                <th>Kết quả</th>
                <th>Chi tiết</th>
                <th>Thời gian</th>
                @if($canEditPaper)<th></th>@endif
            </tr>
            </thead>
            <tbody>
            @forelse($responses as $idx => $r)
                <tr>
                    <td>{{ $sttBase + $idx + 1 }}</td>
                    <td>
                        @php
                            $zName = $r->zalo_user_id ? ($zaloNames[$r->zalo_user_id] ?? null) : null;
                        @endphp
                        {{ $zName ?: ($r->zalo_user_id ?: '—') }}
                    </td>
                    <td>{{ \App\Support\ParentPhone::display($r->phone ?: ($r->zalo_user_id ? ($zaloPhones[$r->zalo_user_id] ?? null) : null)) ?? '—' }}</td>
                    <td>{{ $channelLabel[$r->channel] ?? $r->channel }}</td>
                    <td>{{ $form->choiceLabel((string) $r->choice) }}</td>
                    <td>
                        @if($r->channel === 'paper' && filled($r->image_path))
                            <button
                                type="button"
                                class="btn ghost tiny js-paper-view"
                                data-title="Phiếu giấy #{{ $r->id }}"
                                data-img="{{ route('teacher.responses.paper', [$r, 'raw' => 1]) }}"
                            ><x-icon name="eye" :size="14" /> Xem phiếu</button>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td>{{ optional($r->created_at)?->format('d/m/Y H:i') }}</td>
                    @if($canEditPaper)
                        <td>
                            @if($r->channel === 'paper' && $r->created_by_type === \App\Models\Teacher::class && (int) $r->created_by_id === (int) request()->attributes->get('teacher')->id)
                                <form method="POST" action="{{ route('teacher.responses.paper.destroy', $r) }}"
                                      onsubmit="return confirm('Xóa kết quả phiếu giấy này? Coverage sẽ được cập nhật.');">
                                    @csrf @method('DELETE')
                                    <button class="btn icon-only tiny" type="submit" aria-label="Xóa phiếu" title="Xóa phiếu"><x-icon name="trash" :size="16" /></button>
                                </form>
                            @endif
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ $canEditPaper ? 8 : 7 }}" class="muted">Chưa có bình chọn.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $responses->links() }}
</div>

@if($formActive)
<div class="cf-close-row">
    <p class="cf-close-hint">
        Sau khi lớp đã hoàn thành bình chọn, giáo viên chủ nhiệm có thể chủ động đóng bình chọn để hoàn tất.
    </p>
    @if($classForm->status !== 'closed')
        <form method="POST" action="{{ route('teacher.class-forms.close', $classForm) }}"
              onsubmit="return confirm('Đóng bình chọn lớp {{ $classForm->classProfile->class_name }} — form «{{ $form->title }}»?\n\nLink vẫn giữ nguyên; phụ huynh mở link sẽ thấy trạng thái đã đóng.');">
            @csrf
            <button class="btn danger" type="submit"><x-icon name="lock" /> Đóng bình chọn</button>
        </form>
    @else
        <form method="POST" action="{{ route('teacher.class-forms.reopen', $classForm) }}"
              onsubmit="return confirm('Mở lại bình chọn? Link cũ sẽ hoạt động trở lại.');">
            @csrf
            <button class="btn secondary" type="submit"><x-icon name="unlock" /> Mở lại bình chọn</button>
        </form>
    @endif
</div>
@endif

<div class="modal-backdrop" id="modal-paper" aria-hidden="true">
    <div class="modal-sheet" role="dialog" aria-modal="true" aria-labelledby="modal-paper-title">
        <div class="modal-head">
            <h3 id="modal-paper-title">Phiếu giấy</h3>
            <button type="button" class="btn ghost tiny js-modal-close" data-modal="modal-paper" aria-label="Đóng"><x-icon name="x" /></button>
        </div>
        <div class="modal-body" id="modal-paper-body"></div>
    </div>
</div>

<div class="modal-backdrop" id="modal-ocr" aria-hidden="true">
    <div class="modal-sheet" role="dialog" aria-modal="true" aria-labelledby="modal-ocr-title">
        <div class="modal-head">
            <h3 id="modal-ocr-title">Xác nhận OCR phiếu giấy</h3>
            <button type="button" class="btn ghost tiny js-modal-close" data-modal="modal-ocr" aria-label="Đóng"><x-icon name="x" /></button>
        </div>
        <div class="modal-body">
            <iframe id="modal-ocr-frame" title="OCR confirm" src="about:blank"></iframe>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(() => {
    const chartEl = document.getElementById('chart-quota');
    if (chartEl && window.Chart) {
        new Chart(chartEl, {
            type: 'doughnut',
            data: {
                labels: @json($chartLabels),
                datasets: [{
                    data: @json($chartValues),
                    backgroundColor: @json($chartColors),
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                cutout: '62%',
            },
        });
    }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('input[name="_token"]')?.value;
    const MODAL_MS = 280;

    function syncBodyScroll() {
        const anyOpen = document.querySelector('.modal-backdrop.is-open');
        document.body.classList.toggle('modal-open', !!anyOpen);
    }

    function openModal(id) {
        const el = document.getElementById(id);
        if (!el || el.classList.contains('is-open')) return;
        el.classList.add('is-open');
        el.setAttribute('aria-hidden', 'false');
        syncBodyScroll();
    }

    function closeModal(id) {
        const el = document.getElementById(id);
        if (!el || !el.classList.contains('is-open')) return;
        el.classList.remove('is-open');
        el.setAttribute('aria-hidden', 'true');
        syncBodyScroll();
        if (id === 'modal-ocr' || id === 'modal-paper') {
            window.setTimeout(() => {
                const backdrop = document.getElementById(id);
                if (!backdrop || backdrop.classList.contains('is-open')) return;
                if (id === 'modal-ocr') {
                    const frame = document.getElementById('modal-ocr-frame');
                    if (frame) frame.src = 'about:blank';
                } else {
                    const body = document.getElementById('modal-paper-body');
                    if (body) body.innerHTML = '';
                }
            }, MODAL_MS);
        }
    }

    document.querySelectorAll('.js-modal-close').forEach((btn) => {
        btn.addEventListener('click', () => closeModal(btn.getAttribute('data-modal')));
    });
    document.querySelectorAll('.modal-backdrop').forEach((bd) => {
        bd.addEventListener('click', (e) => {
            if (e.target === bd) closeModal(bd.id);
        });
    });
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('.modal-backdrop.is-open').forEach((bd) => closeModal(bd.id));
    });

    function showFileInModal(title, url, type) {
        document.getElementById('modal-paper-title').textContent = title || 'Xem file';
        const body = document.getElementById('modal-paper-body');
        if (type === 'pdf') {
            body.innerHTML = '<iframe class="file-preview" title="Xem PDF" src="' + url + '"></iframe>';
        } else {
            body.innerHTML = '<img class="paper-preview" src="' + url + '" alt="' + (title || 'Ảnh') + '">';
        }
        openModal('modal-paper');
    }

    document.querySelectorAll('.js-paper-view').forEach((btn) => {
        btn.addEventListener('click', () => {
            showFileInModal(
                btn.getAttribute('data-title') || 'Phiếu giấy',
                btn.getAttribute('data-img'),
                'image'
            );
        });
    });

    document.querySelectorAll('.js-file-view').forEach((link) => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            showFileInModal(
                link.getAttribute('data-title') || 'Xem file',
                link.getAttribute('data-url') || link.href,
                link.getAttribute('data-type') || 'image'
            );
        });
    });

    async function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
            return;
        }
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
    }

    const copyBtn = document.getElementById('btn-copy-link');
    copyBtn?.addEventListener('click', async () => {
        const url = copyBtn.getAttribute('data-url') || '';
        if (!url) return;
        const label = copyBtn.innerHTML;
        try {
            await copyText(url);
            copyBtn.innerHTML = '<span aria-hidden="true">✓</span> Đã copy';
            copyBtn.disabled = true;
            setTimeout(() => { copyBtn.innerHTML = label; copyBtn.disabled = false; }, 1800);
        } catch (e) {
            prompt('Copy link:', url);
        }
    });

    const shareBtn = document.getElementById('btn-share-zalo');
    shareBtn?.addEventListener('click', () => {
        const url = shareBtn.getAttribute('data-url') || '';
        if (typeof window.kavShareZalo === 'function') window.kavShareZalo(url);
    });

    const noteForm = document.getElementById('note-ocr-form');
    const noteFiles = document.getElementById('note-files');
    const noteSubmit = document.getElementById('note-submit-btn');

    function hasImageFiles(input) {
        if (!input?.files?.length) return false;
        return Array.from(input.files).some((f) => /^image\//.test(f.type) || /\.(jpe?g|png|webp)$/i.test(f.name));
    }

    noteForm?.addEventListener('submit', async (e) => {
        if (!hasImageFiles(noteFiles)) return; // normal submit for text/PDF-only
        e.preventDefault();
        if (noteSubmit) {
            noteSubmit.disabled = true;
            noteSubmit.textContent = 'Đang upload…';
        }
        try {
            const fd = new FormData(noteForm);
            fd.append('ajax', '1');
            const res = await fetch(noteForm.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf || '',
                },
                body: fd,
                credentials: 'same-origin',
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                const msg = data.message || data.errors?.body?.[0] || data.errors?.files?.[0] || ('Lỗi ' + res.status);
                throw new Error(msg);
            }
            noteForm.reset();
            document.getElementById('modal-ocr-frame').src = data.embed_url;
            openModal('modal-ocr');
        } catch (err) {
            alert(err.message || 'Không chạy OCR được.');
        } finally {
            if (noteSubmit) {
                noteSubmit.disabled = false;
                noteSubmit.innerHTML = 'Lưu / chạy OCR';
            }
        }
    });

    window.addEventListener('message', (ev) => {
        if (!ev.data || ev.data.type !== 'kav-paper-confirmed') return;
        closeModal('modal-ocr');
        window.location.reload();
    });
})();
</script>
@endpush
@endsection
