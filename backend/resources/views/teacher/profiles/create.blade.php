@extends('layouts.teacher')
@section('title', 'Tạo lớp')

@section('nav')
    <a href="{{ route('teacher.dashboard') }}">Lớp của tôi</a>
@endsection

@section('nav_actions')
@endsection

@section('content')
<div class="t-top">
    <div>
        <h1>Tạo lớp mới</h1>
        <p>Chọn trường → nhập tên lớp & sĩ số → xác nhận.</p>
    </div>
</div>

<div class="steps" id="wizard-steps" aria-label="Các bước tạo lớp">
    <span class="step-pill active" data-step-indicator="1">1. Trường</span>
    <span class="step-pill" data-step-indicator="2">2. Lớp & sĩ số</span>
    <span class="step-pill" data-step-indicator="3">3. Xác nhận</span>
</div>

<div class="card">
<form method="POST" action="{{ route('teacher.profiles.store') }}" id="profile-form">
    @csrf

    <div class="step-panel" data-step="1">
        <h2>Bước 1 — Chọn trường</h2>
        <label for="province_id">Tỉnh</label>
        <select id="province_id" required>
            <option value="">— Chọn —</option>
            @foreach($provinces as $p)
                <option value="{{ $p->id }}">{{ $p->name }}</option>
            @endforeach
        </select>

        <label for="ward_id">Phường / Xã</label>
        <select id="ward_id" required disabled><option value="">—</option></select>

        <label for="school_search">Tìm trường theo tên</label>
        <input type="search" id="school_search" placeholder="Gõ tên trường…" disabled autocomplete="off">

        <label for="school_id">Trường</label>
        <select name="school_id" id="school_id" required disabled><option value="">—</option></select>

        <p class="btn-row" style="margin-top:1.1rem">
            <button class="btn" type="button" id="btn-next-1">Tiếp tục <x-icon name="check" /></button>
        </p>
    </div>

    <div class="step-panel" data-step="2" hidden>
        <h2>Bước 2 — Tên lớp & sĩ số</h2>
        <label for="class_name">Tên lớp</label>
        <input id="class_name" name="class_name" required placeholder="Ví dụ: 5A" maxlength="100">

        <label for="quota">Sĩ số (N)</label>
        <input id="quota" type="number" name="quota" min="1" max="80" value="40" required>

        <p class="muted" style="font-size:.85rem;margin:.5rem 0 0">Sĩ số dùng làm mục tiêu coverage phụ huynh phản hồi.</p>

        <p class="btn-row" style="margin-top:1.1rem">
            <button class="btn ghost" type="button" data-back="1"><x-icon name="arrow-left" /> Quay lại</button>
            <button class="btn" type="button" id="btn-next-2">Tiếp tục <x-icon name="check" /></button>
        </p>
    </div>

    <div class="step-panel" data-step="3" hidden>
        <h2>Bước 3 — Xác nhận</h2>
        <dl style="margin:0;display:grid;gap:.55rem">
            <div><dt class="muted" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.04em">Trường</dt><dd id="confirm-school" style="margin:.15rem 0 0;font-weight:600">—</dd></div>
            <div><dt class="muted" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.04em">Tên lớp</dt><dd id="confirm-class" style="margin:.15rem 0 0;font-weight:600">—</dd></div>
            <div><dt class="muted" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.04em">Sĩ số</dt><dd id="confirm-quota" style="margin:.15rem 0 0;font-weight:600">—</dd></div>
        </dl>
        <p class="btn-row" style="margin-top:1.1rem">
            <button class="btn ghost" type="button" data-back="2"><x-icon name="arrow-left" /> Quay lại</button>
            <button class="btn secondary" type="submit"><x-icon name="plus" /> Tạo lớp</button>
        </p>
    </div>
</form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const province = document.getElementById('province_id');
  const ward = document.getElementById('ward_id');
  const school = document.getElementById('school_id');
  const schoolSearch = document.getElementById('school_search');
  let schoolOptions = [];

  function goStep(n) {
    document.querySelectorAll('.step-panel').forEach((el) => {
      el.hidden = Number(el.dataset.step) !== n;
    });
    document.querySelectorAll('[data-step-indicator]').forEach((el) => {
      const s = Number(el.dataset.stepIndicator);
      el.classList.toggle('active', s === n);
      el.classList.toggle('done', s < n);
    });
  }

  function fillSchools(list) {
    const q = (schoolSearch.value || '').trim().toLowerCase();
    const filtered = q
      ? list.filter((s) => (`[${s.external_id}] ${s.name}`).toLowerCase().includes(q))
      : list;
    school.innerHTML = '<option value="">— Chọn —</option>' + filtered.map((s) =>
      `<option value="${s.id}">[${s.external_id}] ${s.name}</option>`
    ).join('');
    school.disabled = filtered.length === 0;
  }

  province.addEventListener('change', async () => {
    ward.innerHTML = '<option value="">Đang tải...</option>';
    ward.disabled = true;
    school.disabled = true;
    schoolSearch.disabled = true;
    schoolSearch.value = '';
    school.innerHTML = '<option value="">—</option>';
    schoolOptions = [];
    if (!province.value) {
      ward.innerHTML = '<option value="">—</option>';
      return;
    }
    const res = await fetch(`/teacher/catalog/provinces/${province.value}/wards`);
    const data = await res.json();
    ward.innerHTML = '<option value="">— Chọn —</option>' + data.map((w) => `<option value="${w.id}">${w.name}</option>`).join('');
    ward.disabled = false;
  });

  ward.addEventListener('change', async () => {
    school.innerHTML = '<option value="">Đang tải...</option>';
    school.disabled = true;
    schoolSearch.disabled = true;
    schoolSearch.value = '';
    schoolOptions = [];
    if (!ward.value) {
      school.innerHTML = '<option value="">—</option>';
      return;
    }
    const res = await fetch(`/teacher/catalog/wards/${ward.value}/schools`);
    schoolOptions = await res.json();
    fillSchools(schoolOptions);
    schoolSearch.disabled = false;
  });

  schoolSearch.addEventListener('input', () => fillSchools(schoolOptions));

  document.getElementById('btn-next-1').addEventListener('click', () => {
    if (!province.value || !ward.value || !school.value) {
      alert('Vui lòng chọn đủ Tỉnh, Phường/Xã và Trường.');
      return;
    }
    goStep(2);
  });

  document.getElementById('btn-next-2').addEventListener('click', () => {
    const className = document.getElementById('class_name').value.trim();
    const quota = document.getElementById('quota').value;
    if (!className || !quota) {
      alert('Vui lòng nhập tên lớp và sĩ số.');
      return;
    }
    const schoolText = school.options[school.selectedIndex]?.text || '—';
    document.getElementById('confirm-school').textContent = schoolText;
    document.getElementById('confirm-class').textContent = className;
    document.getElementById('confirm-quota').textContent = quota;
    goStep(3);
  });

  document.querySelectorAll('[data-back]').forEach((btn) => {
    btn.addEventListener('click', () => goStep(Number(btn.dataset.back)));
  });
});
</script>
@endpush
@endsection
