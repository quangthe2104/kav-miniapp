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
        <p>Chọn trường, nhập tên lớp & sĩ số rồi bấm Tạo lớp.</p>
    </div>
</div>

<div class="card">
<form method="POST" action="{{ route('teacher.profiles.store') }}" id="profile-form">
    @csrf

    <label for="province_input">Tỉnh</label>
    <div class="combo" id="province_combo">
        <input id="province_input" class="combo-input" type="text" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="province_list" autocomplete="off" placeholder="Gõ hoặc chọn tỉnh…">
        <ul class="combo-list" id="province_list" role="listbox" hidden></ul>
    </div>

    <label for="ward_input">Phường / Xã</label>
    <div class="combo" id="ward_combo">
        <input id="ward_input" class="combo-input" type="text" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="ward_list" autocomplete="off" placeholder="Gõ hoặc chọn phường / xã…" data-idle="Chọn tỉnh trước" disabled>
        <ul class="combo-list" id="ward_list" role="listbox" hidden></ul>
    </div>

    <label for="school_input">Trường</label>
    <div class="combo" id="school_combo">
        <input id="school_input" class="combo-input" type="text" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="school_list" autocomplete="off" placeholder="Gõ tên hoặc mã trường…" data-idle="Chọn phường / xã trước" disabled>
        <ul class="combo-list" id="school_list" role="listbox" hidden></ul>
        <input type="hidden" name="school_id" id="school_id">
    </div>
    <p class="combo-error" id="school_error" hidden>Vui lòng chọn đủ Tỉnh, Phường/Xã và Trường.</p>

    <label for="class_name">Tên lớp</label>
    <input id="class_name" name="class_name" value="{{ old('class_name') }}" required placeholder="Ví dụ: 5A" maxlength="100">

    <label for="quota">Sĩ số</label>
    <input id="quota" type="number" name="quota" min="1" max="80" value="{{ old('quota', 40) }}" required>

    <p class="btn-row" style="margin-top:1.1rem">
        <button class="btn secondary" type="submit"><x-icon name="plus" /> Tạo lớp</button>
    </p>
</form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const PROVINCES = {{ \Illuminate\Support\Js::from($provinces->map(fn ($p) => ['value' => (string) $p->id, 'label' => $p->name])->values()) }};

  // Accent-insensitive so "thanh hoa" matches "Thanh Hóa".
  const fold = (s) => (s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .replace(/đ/g, 'd').replace(/Đ/g, 'D').toLowerCase().trim();

  function createCombo(root, onChange) {
    const input = root.querySelector('.combo-input');
    const list = root.querySelector('.combo-list');
    const hidden = root.querySelector('input[type="hidden"]');
    const placeholder = input.placeholder;
    let items = [];
    let value = '';
    let typed = null;
    let shown = [];
    let active = -1;

    const labelOf = (v) => (items.find((i) => i.value === v) || {}).label || '';
    const isOpen = () => !list.hidden;

    function render() {
      const q = typed === null ? '' : fold(typed);
      shown = q ? items.filter((i) => fold(i.label).includes(q)) : items;
      list.replaceChildren();
      if (shown.length === 0) {
        const li = document.createElement('li');
        li.className = 'combo-empty';
        li.textContent = 'Không có kết quả';
        list.append(li);
      }
      shown.forEach((item, idx) => {
        const li = document.createElement('li');
        li.id = `${list.id}-${idx}`;
        li.dataset.idx = String(idx);
        li.setAttribute('role', 'option');
        li.setAttribute('aria-selected', String(item.value === value));
        li.textContent = item.label;
        if (idx === active) li.classList.add('active');
        list.append(li);
      });
      if (active >= 0) {
        input.setAttribute('aria-activedescendant', `${list.id}-${active}`);
        list.querySelector('.active')?.scrollIntoView({ block: 'nearest' });
      } else {
        input.removeAttribute('aria-activedescendant');
      }
    }

    function open() {
      if (input.disabled) return;
      list.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      render();
    }

    function close() {
      typed = null;
      input.value = labelOf(value);
      list.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
    }

    function choose(item) {
      const changed = item.value !== value;
      value = item.value;
      if (hidden) hidden.value = value;
      close();
      if (changed) onChange(value);
    }

    input.addEventListener('focus', () => {
      input.select();
      active = items.findIndex((i) => i.value === value);
      open();
    });
    input.addEventListener('click', () => { if (!isOpen()) open(); });
    input.addEventListener('input', () => {
      typed = input.value;
      active = 0;
      open();
    });
    input.addEventListener('blur', () => {
      if (typed !== null && fold(typed) && shown.length === 1) choose(shown[0]);
      else close();
    });
    input.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        if (!isOpen()) return open();
        active = Math.min(Math.max(active + (e.key === 'ArrowDown' ? 1 : -1), 0), shown.length - 1);
        render();
      } else if (e.key === 'Enter' && isOpen()) {
        e.preventDefault();
        const pick = shown[active] || (shown.length === 1 ? shown[0] : null);
        if (pick) choose(pick);
      } else if (e.key === 'Escape' && isOpen()) {
        e.preventDefault();
        close();
      }
    });
    list.addEventListener('mousedown', (e) => e.preventDefault());
    list.addEventListener('click', (e) => {
      const li = e.target.closest('li[data-idx]');
      if (li) choose(shown[Number(li.dataset.idx)]);
    });

    function reset(nextItems, text) {
      items = nextItems;
      value = '';
      typed = null;
      active = -1;
      if (hidden) hidden.value = '';
      input.value = '';
      input.placeholder = text;
      input.disabled = nextItems.length === 0;
      list.hidden = true;
    }

    return {
      get value() { return value; },
      focus() { input.focus(); },
      setItems(nextItems) { reset(nextItems, nextItems.length ? placeholder : 'Không có dữ liệu'); },
      setIdle() { reset([], input.dataset.idle || placeholder); },
      setLoading() { reset([], 'Đang tải…'); },
    };
  }

  const schoolError = document.getElementById('school_error');

  const school = createCombo(document.getElementById('school_combo'), () => {
    schoolError.hidden = true;
  });

  const ward = createCombo(document.getElementById('ward_combo'), async (wardId) => {
    school.setLoading();
    const res = await fetch(`/teacher/catalog/wards/${wardId}/schools`);
    const data = await res.json();
    if (ward.value !== wardId) return;
    school.setItems(data.map((s) => ({ value: String(s.id), label: `[${s.external_id}] ${s.name}` })));
  });

  const province = createCombo(document.getElementById('province_combo'), async (provinceId) => {
    school.setIdle();
    ward.setLoading();
    const res = await fetch(`/teacher/catalog/provinces/${provinceId}/wards`);
    const data = await res.json();
    if (province.value !== provinceId) return;
    ward.setItems(data.map((w) => ({ value: String(w.id), label: w.name })));
  });

  province.setItems(PROVINCES);
  ward.setIdle();
  school.setIdle();

  document.getElementById('profile-form').addEventListener('submit', (e) => {
    if (school.value) return;
    e.preventDefault();
    schoolError.hidden = false;
    (province.value ? (ward.value ? school : ward) : province).focus();
  });
});
</script>
@endpush
@endsection
