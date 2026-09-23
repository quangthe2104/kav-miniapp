@php
    $form = $batch->classForm?->form;
    $resolved = $form?->resolvedOptions() ?? \App\Models\Form::defaultOptions();
    $choices = $resolved['choices'];
    $ocrLabels = \App\Models\Form::ocrSummaryLabels($form);
    $agreeLabel = $ocrLabels['agree_label'];
    $otherLabel = $ocrLabels['other_label'];
    $unknownLabel = $ocrLabels['unknown_label'];
@endphp
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>OCR batch #{{ $batch->id }}</title>
    @if($batch->status === 'processing')
        <meta http-equiv="refresh" content="2">
    @endif
    <link href="https://fonts.bunny.net/css?family=Montserrat:400,500,600,700&display=swap" rel="stylesheet">
    <style>
        :root { --ink:#0a2a66; --muted:#5b6b8c; --line:#d8e0ef; --accent:#14bf96; --danger:#b42318; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: Montserrat, system-ui, sans-serif; color: var(--ink); font-size: .92rem; }
        .muted { color: var(--muted); }
        .ok { background:#e5f9f3; padding:.65rem .8rem; border-radius:10px; margin-bottom:.75rem; }
        .err { background:#fdecea; color: var(--danger); padding:.65rem .8rem; border-radius:10px; margin-bottom:.75rem; }
        table { width:100%; border-collapse: collapse; }
        th, td { border-bottom:1px solid var(--line); padding:.45rem .35rem; text-align:left; vertical-align:middle; }
        th { font-size:.8rem; color: var(--muted); }
        select {
            width: 100%;
            max-width: 180px;
            appearance: none;
            -webkit-appearance: none;
            padding: .5rem 2rem .5rem .7rem;
            border: 1.5px solid #c9d4e4;
            border-radius: 10px;
            font: inherit;
            font-weight: 500;
            color: var(--ink);
            background-color: #fff;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' stroke='%230a2a66' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m4 6 4 4 4-4'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right .55rem center;
            background-size: 14px;
            cursor: pointer;
        }
        select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(20,191,150,.18);
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            border: 0;
            border-radius: 10px;
            padding: .65rem 1rem;
            background: var(--accent);
            color: #fff;
            font: inherit;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            width: auto;
        }
        .btn .icon { flex-shrink: 0; width: 1.1rem; height: 1.1rem; }
        .btn:disabled { opacity:.5; cursor:not-allowed; }
        .kpi { display:flex; gap:.75rem; flex-wrap:wrap; margin-bottom:.75rem; }
        .kpi span { background:#eef2fa; padding:.35rem .6rem; border-radius:8px; font-size:.82rem; }
        a { color: var(--accent); }
    </style>
</head>
<body>
@if(session('status'))<div class="ok">{{ session('status') }}</div>@endif
@if(isset($errors) && $errors->any())
    <div class="err"><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<p class="muted" style="margin-top:0">Batch #{{ $batch->id }} · {{ $batch->status }}</p>
<div class="kpi">
    <span>{{ $agreeLabel }}: {{ $batch->agree_count }}</span>
    <span>{{ $otherLabel }}: {{ $batch->disagree_count }}</span>
    <span>{{ $unknownLabel }}: {{ $batch->unknown_count }}</span>
</div>

@if($batch->status === 'processing')
    <p class="muted">Đang OCR… popup tự làm mới.</p>
@endif

@if(filled($batch->pending_note_body))
    <p><strong>Ghi chú sẽ lưu:</strong></p>
    <p style="white-space:pre-wrap;margin-top:0">{{ $batch->pending_note_body }}</p>
@endif

@if(in_array($batch->status, ['ready','processing'], true))
<form id="confirm-form" method="POST" action="{{ route('teacher.paper.confirm', $batch) }}">
    @csrf
    <input type="hidden" name="ajax" value="1">
    <table>
        <thead>
        <tr>
            <th>#</th>
            <th>Ảnh</th>
            <th>Gợi ý OCR</th>
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
                <td><a href="{{ route('teacher.paper.items.image', $item) }}" target="_blank" rel="noopener">Xem</a></td>
                <td>{{ $suggestionLabel }}@if($confidencePct !== null) <span class="muted">({{ $confidencePct }}%)</span>@endif</td>
                <td>
                    <select name="items[{{ $i }}][choice]">
                        @foreach($choices as $choice)
                            <option value="{{ $choice['value'] }}" @selected($item->ocr_suggestion === $choice['value'])>{{ $choice['label'] }}</option>
                        @endforeach
                        <option value="skip" @selected($item->ocr_suggestion === 'unknown' || ! $item->ocr_suggestion)>Bỏ qua</option>
                    </select>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <p style="margin-top:.9rem">
        <button class="btn" type="submit" @disabled($batch->status!=='ready')>
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
            Xác nhận lưu kết quả
        </button>
    </p>
</form>
@elseif($batch->status === 'confirmed')
    <p class="ok">Đã xác nhận. Đóng popup để xem cập nhật.</p>
    <script>window.parent.postMessage({ type: 'kav-paper-confirmed', message: 'Đã xác nhận phiếu giấy.' }, '*');</script>
@endif

<script>
(() => {
  const form = document.getElementById('confirm-form');
  if (!form) return;
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = form.querySelector('button[type="submit"]');
    if (btn) btn.disabled = true;
    try {
      const fd = new FormData(form);
      const res = await fetch(form.action, {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: fd,
        credentials: 'same-origin',
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data.message || data.errors?.paper?.[0] || ('Lỗi ' + res.status));
      window.parent.postMessage({ type: 'kav-paper-confirmed', message: data.message || 'Đã lưu.' }, '*');
    } catch (err) {
      alert(err.message || 'Không xác nhận được.');
      if (btn) btn.disabled = false;
    }
  });
})();
</script>
</body>
</html>
