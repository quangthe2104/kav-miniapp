@push('scripts')
<script>
(function () {
    const form = document.getElementById('form-editor');
    const preset = document.getElementById('options_preset');
    const panel = document.getElementById('custom-options-panel');
    const list = document.getElementById('custom-choices');
    const addBtn = document.getElementById('add-choice');
    const noEnd = document.getElementById('no_end_date');
    const endsAt = document.getElementById('ends_at');

    function slugify(text) {
        return String(text || '')
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_|_$/g, '')
            .slice(0, 32);
    }

    function syncPreset() {
        if (!preset || !panel) return;
        panel.style.display = preset.value === 'custom' ? '' : 'none';
    }

    function syncNoEnd() {
        if (!noEnd || !endsAt) return;
        endsAt.disabled = noEnd.checked;
        if (noEnd.checked) endsAt.value = '';
    }

    function choiceValue(row) {
        const valueInput = row.querySelector('input[name*="[value]"]');
        const labelInput = row.querySelector('input[name*="[label]"]');
        const raw = (valueInput?.value || '').trim();
        if (raw) return slugify(raw);
        return slugify(labelInput?.value || '');
    }

    function bindValueSync(row) {
        const valueInput = row.querySelector('input[name*="[value]"]');
        const labelInput = row.querySelector('input[name*="[label]"]');
        const agree = row.querySelector('.agree-check');
        if (!agree) return;
        const sync = () => { agree.value = choiceValue(row); };
        [valueInput, labelInput].forEach((el) => {
            if (!el) return;
            el.removeEventListener('input', el._kavSync);
            el._kavSync = sync;
            el.addEventListener('input', sync);
        });
        sync();
    }

    function reindexRows() {
        const rows = list.querySelectorAll('.custom-choice-row');
        rows.forEach((row, i) => {
            const valueInput = row.querySelector('input[name*="[value]"]');
            const labelInput = row.querySelector('input[name*="[label]"]');
            const remove = row.querySelector('.remove-choice');
            if (valueInput) valueInput.name = `custom_choices[${i}][value]`;
            if (labelInput) labelInput.name = `custom_choices[${i}][label]`;
            if (remove) remove.disabled = rows.length <= 2;
            bindValueSync(row);
        });
    }

    function addRow() {
        const rows = list.querySelectorAll('.custom-choice-row');
        const tpl = rows[0].cloneNode(true);
        tpl.querySelectorAll('input').forEach((el) => {
            if (el.type === 'checkbox') {
                el.checked = false;
                el.value = '';
            } else {
                el.value = '';
            }
        });
        list.appendChild(tpl);
        reindexRows();
    }

    list?.addEventListener('click', (e) => {
        const btn = e.target.closest('.remove-choice');
        if (!btn) return;
        const rows = list.querySelectorAll('.custom-choice-row');
        if (rows.length <= 2) return;
        btn.closest('.custom-choice-row')?.remove();
        reindexRows();
    });

    form?.addEventListener('submit', () => {
        if (preset?.value === 'custom') {
            list?.querySelectorAll('.custom-choice-row').forEach(bindValueSync);
        }
        if (noEnd?.checked && endsAt) {
            endsAt.disabled = false;
            endsAt.value = '';
        }
    });

    addBtn?.addEventListener('click', addRow);
    preset?.addEventListener('change', syncPreset);
    noEnd?.addEventListener('change', syncNoEnd);

    list?.querySelectorAll('.custom-choice-row').forEach(bindValueSync);
    syncPreset();
    syncNoEnd();
})();
</script>
@endpush
