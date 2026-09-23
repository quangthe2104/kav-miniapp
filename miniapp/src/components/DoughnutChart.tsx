export type DoughnutSlice = {
  label: string
  value: number
  color: string
}

const AGREE_COLOR = '#14bf96'
const DISAGREE_COLOR = '#b42318'
const REMAINING_COLOR = '#cbd5e1'
const OTHER_PALETTE = ['#0a2a66', '#f59e0b', '#6366f1', '#0ea5e9', '#8b5cf6', '#64748b']

/** Client-side fallback matching Form::choiceChartColors + remainingChartColor. */
export function colorForChoice(value: string, agreeValues: string[] = ['agree', 'yes']): string {
  if (agreeValues.includes(value) || value === 'agree' || value === 'yes') return AGREE_COLOR
  if (value === 'disagree' || value === 'no') return DISAGREE_COLOR
  const idx = Math.abs(hashStr(value)) % OTHER_PALETTE.length
  return OTHER_PALETTE[idx]
}

export function remainingColor(): string {
  return REMAINING_COLOR
}

function hashStr(s: string): number {
  let h = 0
  for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) | 0
  return h
}

/**
 * Map choice_counts (+ optional remaining) to chart slices.
 * Prefer API `color` when present; otherwise compute client-side.
 */
export function buildQuotaSlices(input: {
  choice_counts?: { value: string; label: string; count: number; color?: string }[]
  remaining?: number
  remaining_color?: string
  quota?: number
  coverage?: number
}): DoughnutSlice[] {
  const counts = input.choice_counts || []
  let paletteIdx = 0
  const slices: DoughnutSlice[] = counts.map((c) => {
    let color = c.color
    if (!color) {
      if (c.value === 'agree' || c.value === 'yes') color = AGREE_COLOR
      else if (c.value === 'disagree' || c.value === 'no') color = DISAGREE_COLOR
      else {
        color = OTHER_PALETTE[paletteIdx % OTHER_PALETTE.length]
        paletteIdx++
      }
    }
    return { label: c.label, value: Math.max(0, Number(c.count) || 0), color }
  })

  let remaining =
    typeof input.remaining === 'number'
      ? input.remaining
      : typeof input.quota === 'number' && typeof input.coverage === 'number'
        ? Math.max(0, input.quota - input.coverage)
        : 0

  if (remaining > 0 || slices.length > 0) {
    slices.push({
      label: 'Chưa bình chọn',
      value: Math.max(0, remaining),
      color: input.remaining_color || REMAINING_COLOR,
    })
  }

  return slices
}

function polar(cx: number, cy: number, r: number, angle: number) {
  const a = ((angle - 90) * Math.PI) / 180
  return { x: cx + r * Math.cos(a), y: cy + r * Math.sin(a) }
}

function arcPath(
  cx: number,
  cy: number,
  rOuter: number,
  rInner: number,
  startAngle: number,
  endAngle: number,
): string {
  const large = endAngle - startAngle > 180 ? 1 : 0
  const o1 = polar(cx, cy, rOuter, startAngle)
  const o2 = polar(cx, cy, rOuter, endAngle)
  const i1 = polar(cx, cy, rInner, endAngle)
  const i2 = polar(cx, cy, rInner, startAngle)
  return [
    `M ${o1.x} ${o1.y}`,
    `A ${rOuter} ${rOuter} 0 ${large} 1 ${o2.x} ${o2.y}`,
    `L ${i1.x} ${i1.y}`,
    `A ${rInner} ${rInner} 0 ${large} 0 ${i2.x} ${i2.y}`,
    'Z',
  ].join(' ')
}

/** Horizontal stacked bar (agree / disagree / remaining) for parent stats. */
export function StackedBarChart({
  slices,
  ariaLabel = 'Biểu đồ tỉ lệ',
}: {
  slices: DoughnutSlice[]
  ariaLabel?: string
}) {
  const total = slices.reduce((s, x) => s + x.value, 0)
  const pct = (v: number) => (total > 0 ? (v / total) * 100 : 0)

  return (
    <div className="stacked-bar-wrap" aria-label={ariaLabel}>
      <div className="stacked-bar" role="img" aria-label={ariaLabel}>
        {total <= 0 ? (
          <span className="stacked-seg empty" style={{ width: '100%' }} />
        ) : (
          slices.map((s) =>
            s.value > 0 ? (
              <span
                key={s.label}
                className="stacked-seg"
                style={{ width: `${pct(s.value)}%`, background: s.color }}
                title={`${s.label}: ${s.value}`}
              />
            ) : null,
          )
        )}
      </div>
      <div className="pie-legend">
        {slices.map((s) => (
          <span key={s.label}>
            <i style={{ background: s.color }} />
            {s.label} ({s.value})
          </span>
        ))}
      </div>
    </div>
  )
}

export function DoughnutChart({
  slices,
  size = 200,
  thickness = 36,
  ariaLabel = 'Biểu đồ tỉ lệ',
}: {
  slices: DoughnutSlice[]
  size?: number
  thickness?: number
  ariaLabel?: string
}) {
  const total = slices.reduce((s, x) => s + x.value, 0)
  const cx = size / 2
  const cy = size / 2
  const rOuter = size / 2 - 4
  const rInner = Math.max(8, rOuter - thickness)

  if (total <= 0) {
    return (
      <div className="pie-wrap" aria-label={ariaLabel}>
        <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} role="img">
          <circle
            cx={cx}
            cy={cy}
            r={(rOuter + rInner) / 2}
            fill="none"
            stroke={REMAINING_COLOR}
            strokeWidth={thickness}
          />
        </svg>
        <div className="pie-legend">
          {slices.map((s) => (
            <span key={s.label}>
              <i style={{ background: s.color }} />
              {s.label} ({s.value})
            </span>
          ))}
        </div>
      </div>
    )
  }

  let angle = 0
  const paths: { d: string; color: string; key: string }[] = []
  slices.forEach((s, idx) => {
    if (s.value <= 0) return
    const sweep = (s.value / total) * 360
    // Full circle: SVG arc with same start/end is empty — use two semicircles
    if (sweep >= 359.999) {
      paths.push({
        d: arcPath(cx, cy, rOuter, rInner, 0, 180),
        color: s.color,
        key: `${s.label}-a-${idx}`,
      })
      paths.push({
        d: arcPath(cx, cy, rOuter, rInner, 180, 360),
        color: s.color,
        key: `${s.label}-b-${idx}`,
      })
      angle = 360
      return
    }
    const start = angle
    const end = angle + sweep
    paths.push({
      d: arcPath(cx, cy, rOuter, rInner, start, end),
      color: s.color,
      key: `${s.label}-${idx}`,
    })
    angle = end
  })

  return (
    <div className="pie-wrap" aria-label={ariaLabel}>
      <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} role="img">
        {paths.map((p) => (
          <path key={p.key} d={p.d} fill={p.color} />
        ))}
      </svg>
      <div className="pie-legend">
        {slices.map((s) => (
          <span key={s.label}>
            <i style={{ background: s.color }} />
            {s.label} ({s.value})
          </span>
        ))}
      </div>
    </div>
  )
}

export function ChoiceIcon({
  role,
}: {
  role: 'agree' | 'disagree' | 'other'
}) {
  if (role === 'agree') {
    return (
      <svg className="btn-icon" viewBox="0 0 20 20" aria-hidden="true">
        <path
          fill="currentColor"
          d="M8.2 14.4 3.8 10l1.4-1.4 3 3 6.6-6.6L16.2 6.4z"
        />
      </svg>
    )
  }
  if (role === 'disagree') {
    return (
      <svg className="btn-icon" viewBox="0 0 20 20" aria-hidden="true">
        <path
          fill="currentColor"
          d="M5.3 4.3 4.3 5.3 8.9 9.9l-4.6 4.6 1 1 4.6-4.6 4.6 4.6 1-1-4.6-4.6 4.6-4.6-1-1-4.6 4.6z"
        />
      </svg>
    )
  }
  return (
    <svg className="btn-icon" viewBox="0 0 20 20" aria-hidden="true">
      <circle cx="10" cy="10" r="6" fill="none" stroke="currentColor" strokeWidth="2" />
    </svg>
  )
}

export function ChangeVoteIcon() {
  return (
    <svg className="btn-icon" viewBox="0 0 20 20" aria-hidden="true">
      <path
        fill="currentColor"
        d="M3.2 13.7 13.1 3.8l3.1 3.1-9.9 9.9H3.2v-3.1zm11.3-11.3 1.4-1.4a1.2 1.2 0 0 1 1.7 0l1.4 1.4a1.2 1.2 0 0 1 0 1.7l-1.4 1.4-3.1-3.1z"
      />
    </svg>
  )
}
