@extends('layouts.teacher')
@section('title', 'Lớp của tôi')

@section('nav')
    <a href="{{ route('teacher.dashboard') }}" class="active">Lớp của tôi</a>
@endsection

@section('content')
<div class="t-top">
    <div>
        <h1>Xin chào, {{ $teacher->name ?: ($teacher->phone ?: 'Giáo viên') }}</h1>
        <p>Theo dõi form theo từng lớp — lấy link, tải mẫu phiếu PDF, xem tỉ lệ hoàn thành.</p>
        @if(! $isNewTeacher && $profiles->count() > 1)
            <form method="GET" action="{{ route('teacher.dashboard') }}" class="class-switcher">
                <select name="profile_id" id="profile_id" aria-label="Chọn lớp" onchange="this.form.submit()">
                    @foreach($profiles as $p)
                        <option value="{{ $p->id }}" @selected($selected && $selected->id === $p->id)>
                            {{ $p->class_name }} — {{ $p->school->name }}
                        </option>
                    @endforeach
                </select>
                <noscript><button class="btn" type="submit">Xem lớp</button></noscript>
            </form>
        @endif
    </div>
</div>

@if($isNewTeacher)
    <div class="onboard">
        <strong>Bắt đầu nhanh</strong>
        <p>Tạo lớp đầu tiên, rồi mở form để lấy link gửi phụ huynh.</p>
        <p style="margin-top:.75rem"><a class="btn secondary" href="{{ route('teacher.profiles.create') }}"><x-icon name="plus" /> Tạo lớp đầu tiên</a></p>
    </div>
@else
@if($selected)
<div class="card forms-open-card">
    <div style="margin-bottom:.75rem">
        <h2 style="margin:0">Form đang mở — lớp {{ $selected->class_name }}</h2>
        <p class="muted" style="margin:.35rem 0 0">
            {{ $selected->school->name }} · sĩ số {{ $selected->quota }}
            · {{ $selected->school->ward?->name }} — {{ $selected->school->ward?->province?->name }}
        </p>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Form</th>
                <th>Hoàn thành</th>
                <th>Kết quả</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse($formRows as $row)
                <tr>
                    <td>
                        @if($row['detail_url'])
                            <a href="{{ $row['detail_url'] }}"><strong>{{ $row['title'] }}</strong></a>
                        @elseif(!empty($row['ensure_url']))
                            <form method="POST" action="{{ $row['ensure_url'] }}" class="inline">
                                @csrf
                                <button type="submit" class="title-link">{{ $row['title'] }}</button>
                            </form>
                        @else
                            <strong>{{ $row['title'] }}</strong>
                        @endif
                    </td>
                    <td>
                        <div>{{ $row['coverage'] }} / {{ $row['quota'] }} ({{ $row['coverage_pct'] }}%)</div>
                        <div class="bar" aria-hidden="true"><span style="width:{{ $row['coverage_pct'] }}%"></span></div>
                    </td>
                    <td>
                        <span
                            class="result-compact"
                            title="{{ $row['choice_tooltip'] }}"
                            style="cursor:help;font-variant-numeric:tabular-nums"
                        >
                            {{ $row['choice_compact'] }}
                            <span aria-hidden="true" style="margin-left:.35rem;opacity:.7">ⓘ</span>
                        </span>
                    </td>
                    <td>
                        <div class="btn-row" style="margin:0;justify-content:flex-end">
                            <button
                                class="btn ghost js-share-zalo"
                                type="button"
                                data-ensure-url="{{ $row['ensure_url'] }}"
                            ><x-icon name="share" /> Share Zalo</button>
                            @if($row['has_template'])
                                @if($row['class_form'])
                                    <a class="btn ghost" href="{{ route('teacher.class-forms.template', $row['class_form']) }}">
                                        <x-icon name="download" /> Tải mẫu phiếu
                                    </a>
                                @elseif($row['form'])
                                    <a class="btn ghost" href="{{ route('teacher.forms.template', $row['form']) }}">
                                        <x-icon name="download" /> Tải mẫu phiếu
                                    </a>
                                @endif
                            @endif
                            @if($row['detail_url'])
                                <a class="btn ghost" href="{{ $row['detail_url'] }}">
                                    <x-icon name="eye" /> Xem chi tiết form
                                </a>
                            @elseif(!empty($row['ensure_url']))
                                <form method="POST" action="{{ $row['ensure_url'] }}" class="inline">
                                    @csrf
                                    <button type="submit" class="btn ghost">
                                        <x-icon name="eye" /> Xem chi tiết form
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="muted">Chưa có form đang mở cho lớp này.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <p class="muted" style="margin:.75rem 0 0;font-size:.85rem">
        Di chuột vào cột Kết quả (ⓘ) để xem chi tiết từng lựa chọn. Bấm tên form hoặc <strong>Xem chi tiết form</strong> để vào trang chi tiết.
    </p>
</div>
@endif
@endif
@endsection

@push('scripts')
<script>
(() => {
  const token = document.querySelector('meta[name="csrf-token"]')?.content;

  async function ensureVoteUrl(ensureUrl) {
    const res = await fetch(ensureUrl + (ensureUrl.includes('?') ? '&' : '?') + 'json=1', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': token,
        'X-Requested-With': 'XMLHttpRequest',
      },
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'Không lấy được link');
    if (!data.vote_url) throw new Error('Chưa nhận được link.');
    return data.vote_url;
  }

  document.querySelectorAll('.js-share-zalo').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const url = btn.getAttribute('data-ensure-url');
      if (!url || !token) return;
      const prev = btn.innerHTML;
      btn.disabled = true;
      btn.textContent = '…';
      try {
        const voteUrl = await ensureVoteUrl(url);
        if (typeof window.kavShareZalo === 'function') window.kavShareZalo(voteUrl);
      } catch (e) {
        alert(e.message || 'Không chia sẻ được.');
      } finally {
        btn.innerHTML = prev;
        btn.disabled = false;
      }
    });
  });
})();
</script>
@endpush
