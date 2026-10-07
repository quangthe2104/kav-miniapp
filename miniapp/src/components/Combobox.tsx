import { useEffect, useId, useRef, useState, type KeyboardEvent } from 'react'

export type ComboOption = { value: string; label: string }

/** Accent-insensitive so "thanh hoa" matches "Thanh Hóa". */
export function foldVi(s: string): string {
  return s
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/đ/g, 'd')
    .replace(/Đ/g, 'D')
    .toLowerCase()
    .trim()
}

/** Single input that both filters and picks from `options` (type to filter, tap/Enter to pick). */
export function Combobox({
  label,
  options,
  value,
  onChange,
  disabled = false,
  loading = false,
  placeholder = 'Gõ hoặc chọn…',
}: {
  label: string
  options: ComboOption[]
  value: string
  onChange: (value: string) => void
  disabled?: boolean
  loading?: boolean
  placeholder?: string
}) {
  const id = useId()
  const listId = `${id}-list`
  const listRef = useRef<HTMLUListElement>(null)

  const [open, setOpen] = useState(false)
  const [typed, setTyped] = useState<string | null>(null)
  const [active, setActive] = useState(-1)

  const selectedLabel = options.find((o) => o.value === value)?.label ?? ''
  const q = typed === null ? '' : foldVi(typed)
  const shown = q ? options.filter((o) => foldVi(o.label).includes(q)) : options
  const off = disabled || loading

  useEffect(() => {
    if (!open || active < 0) return
    listRef.current?.querySelector(`[data-idx="${active}"]`)?.scrollIntoView({ block: 'nearest' })
  }, [open, active])

  function openList() {
    setOpen(true)
    setActive(shown.findIndex((o) => o.value === value))
  }

  function choose(option: ComboOption) {
    setTyped(null)
    setOpen(false)
    if (option.value !== value) onChange(option.value)
  }

  function close() {
    setTyped(null)
    setOpen(false)
  }

  function onKeyDown(e: KeyboardEvent<HTMLInputElement>) {
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault()
      if (!open) return openList()
      const step = e.key === 'ArrowDown' ? 1 : -1
      setActive((i) => Math.min(Math.max(i + step, 0), shown.length - 1))
    } else if (e.key === 'Enter' && open) {
      e.preventDefault()
      const pick = shown[active] ?? (shown.length === 1 ? shown[0] : undefined)
      if (pick) choose(pick)
    } else if (e.key === 'Escape' && open) {
      e.preventDefault()
      close()
    }
  }

  return (
    <div className="field">
      <label htmlFor={id}>{label}</label>
      <div className="combo">
        <input
          id={id}
          className="combo-input"
          type="text"
          role="combobox"
          aria-autocomplete="list"
          aria-expanded={open}
          aria-controls={listId}
          aria-activedescendant={open && active >= 0 ? `${listId}-${active}` : undefined}
          autoComplete="off"
          value={typed ?? selectedLabel}
          placeholder={loading ? 'Đang tải…' : placeholder}
          disabled={off}
          onFocus={(e) => {
            e.target.select()
            openList()
          }}
          onClick={() => {
            if (!open) openList()
          }}
          onChange={(e) => {
            setTyped(e.target.value)
            setOpen(true)
            setActive(0)
          }}
          onBlur={() => {
            if (q && shown.length === 1) choose(shown[0])
            else close()
          }}
          onKeyDown={onKeyDown}
        />
        {open ? (
          <ul
            ref={listRef}
            id={listId}
            className="combo-list"
            role="listbox"
            onMouseDown={(e) => e.preventDefault()}
          >
            {shown.length === 0 ? (
              <li className="combo-empty">Không có kết quả</li>
            ) : (
              shown.map((o, idx) => (
                <li
                  key={o.value}
                  id={`${listId}-${idx}`}
                  data-idx={idx}
                  role="option"
                  aria-selected={o.value === value}
                  className={idx === active ? 'active' : undefined}
                  onClick={() => choose(o)}
                >
                  {o.label}
                </li>
              ))
            )}
          </ul>
        ) : null}
      </div>
    </div>
  )
}
