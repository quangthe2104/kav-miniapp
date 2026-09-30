import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import {
  ChangeVoteIcon,
  ChoiceIcon,
  StackedBarChart,
  buildQuotaSlices,
} from '../components/DoughnutChart'
import { Shell } from '../components/Shell'
import {
  ApiError,
  ZALO_SESSION_ERROR,
  extractInviteToken,
  getToken,
  getVote,
  isZaloApp,
  mockParentLogin,
  optionLabel,
  postVote,
  registerAsParent,
  tryZaloAccessToken,
  voteOptionsOf,
  type CoverageStats,
  type FormOption,
  type VoteChoice,
  type VotePayload,
} from '../api/client'

const isDevBuild = import.meta.env.DEV
const forceDevLogin =
  import.meta.env.VITE_ENABLE_DEV_LOGIN === 'true' || isDevBuild

function isOpen(payload: VotePayload): boolean {
  if (typeof payload.form_active === 'boolean') return payload.form_active
  if (typeof payload.can_change === 'boolean') return payload.can_change
  if (typeof payload.is_open === 'boolean') return payload.is_open
  if (typeof payload.open === 'boolean') return payload.open
  return payload.status !== 'closed'
}

function coverageOf(payload: VotePayload): CoverageStats {
  if (payload.stats) return payload.stats
  if (payload.coverage && typeof payload.coverage === 'object') {
    return payload.coverage
  }
  return {}
}

function myChoice(payload: VotePayload): VoteChoice | null {
  return payload.my_choice ?? payload.choice ?? null
}

function choiceRole(
  opt: FormOption,
  idx: number,
): 'agree' | 'disagree' | 'other' {
  if (
    opt.role === 'agree' ||
    opt.value === 'agree' ||
    opt.value === 'yes' ||
    idx === 0
  ) {
    return 'agree'
  }
  if (
    opt.role === 'disagree' ||
    opt.value === 'disagree' ||
    opt.value === 'no'
  ) {
    return 'disagree'
  }
  return 'other'
}

/** Fixed bottom dock: 2 nút ngang (Đồng ý rộng hơn) chỉ khi form nhị phân. */
function isBinaryAgreeDisagree(options: FormOption[]): boolean {
  if (options.length !== 2) return false
  const roles = options.map((o, i) => choiceRole(o, i))
  return roles.includes('agree') && roles.includes('disagree')
}

function voteBtnClass(
  role: 'agree' | 'disagree' | 'other',
  selected: boolean,
  sizeClass: string,
): string {
  const base = 'btn btn-with-icon'
  if (role === 'agree') {
    return `${base} accent${selected ? ' selected' : ''} ${sizeClass}`.trim()
  }
  if (role === 'disagree') {
    return `${base} outline${selected ? ' selected' : ''} ${sizeClass}`.trim()
  }
  return `${base} outline${selected ? ' selected' : ''} ${sizeClass}`.trim()
}

export function ParentTokenEntryPage() {
  const [raw, setRaw] = useState('')
  const navigate = useNavigate()

  return (
    <Shell title="Phụ huynh · mở phiếu">
      <section className="hero-block">
        <h1 className="h1-sm">Mở phiếu bình chọn</h1>
        <p className="lead">
          Dán link cô gửi trong group Zalo (hoặc chỉ phần mã sau{' '}
          <code>/vote/</code>).
        </p>
      </section>
      <form
        className="panel"
        onSubmit={(e) => {
          e.preventDefault()
          const t = extractInviteToken(raw)
          if (t) navigate(`/vote/${encodeURIComponent(t)}`)
        }}
      >
        <label className="field">
          <span>Link hoặc mã phiếu</span>
          <input
            value={raw}
            onChange={(e) => setRaw(e.target.value)}
            placeholder="https://…/vote/… hoặc mã phiếu"
            required
          />
        </label>
        <button className="btn primary" type="submit">
          Mở phiếu
        </button>
      </form>
      <p className="muted">
        <Link to="/">← Trang chủ</Link>
      </p>
    </Shell>
  )
}

export function ParentVotePage() {
  const { token = '' } = useParams<{ token: string }>()
  const [data, setData] = useState<VotePayload | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)
  const [zaloId, setZaloId] = useState('parent-demo-1')
  const [authed, setAuthed] = useState(Boolean(getToken()))
  const [showTestLogin, setShowTestLogin] = useState(forceDevLogin)
  const [changingVote, setChangingVote] = useState(false)
  const [pendingChoice, setPendingChoice] = useState<VoteChoice | null>(null)
  const choicesRef = useRef<HTMLElement | null>(null)

  useEffect(() => {
    let cancelled = false

    async function loadVote() {
      setData(null)
      setError(null)
      setChangingVote(false)
      setPendingChoice(null)

      try {
        if (!getToken()) {
          const accessToken = await tryZaloAccessToken()
          if (accessToken && !cancelled) {
            try {
              await registerAsParent(accessToken)
              setAuthed(true)
            } catch {
              /* Zalo token invalid — parent can auth when voting */
            }
          }
        } else {
          setAuthed(true)
        }

        const payload = await getVote(token)
        if (!cancelled) setData(payload)
      } catch (err: unknown) {
        if (cancelled) return
        setError(err instanceof ApiError ? err.message : 'Không tải được phiếu.')
      }
    }

    loadVote()

    return () => {
      cancelled = true
    }
  }, [token])

  async function ensureAuth() {
    if (getToken()) {
      setAuthed(true)
      return
    }
    const accessToken = await tryZaloAccessToken()
    if (accessToken) {
      await registerAsParent(accessToken)
      setAuthed(true)
      return
    }
    if (isZaloApp && !forceDevLogin) {
      throw new ApiError(401, ZALO_SESSION_ERROR)
    }
    if (forceDevLogin || showTestLogin) {
      await mockParentLogin(zaloId)
      setAuthed(true)
      return
    }
    setShowTestLogin(true)
    throw new ApiError(401, 'Vui lòng mở trong Zalo Mini App để gửi phiếu.')
  }

  async function vote(choice: VoteChoice) {
    setSaving(true)
    setError(null)
    try {
      await ensureAuth()
      await postVote(token, choice)
      const next = await getVote(token)
      setData({ ...next, my_choice: next.my_choice ?? choice })
      setChangingVote(false)
      setAuthed(true)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Gửi phiếu thất bại.')
    } finally {
      setSaving(false)
    }
  }

  const title = data?.form?.title || data?.form?.name || data?.title || 'Phiếu bình chọn'
  const content = data?.form?.content?.trim() || ''
  const options: FormOption[] = voteOptionsOf(data)
  const open = data ? isOpen(data) : false
  const choice = data ? myChoice(data) : null
  const cov = data ? coverageOf(data) : {}
  const quota = data?.class?.quota
  const coverageNum =
    typeof data?.coverage === 'number'
      ? data.coverage
      : typeof cov.coverage === 'number'
        ? cov.coverage
        : undefined

  const coveragePct =
    typeof cov.coverage_percent === 'number'
      ? cov.coverage_percent
      : typeof coverageNum === 'number' && typeof quota === 'number'
        ? Math.min(100, Math.round((coverageNum / Math.max(1, quota)) * 100))
        : null

  const slices = data
    ? buildQuotaSlices({
        choice_counts:
          cov.choice_counts && cov.choice_counts.length > 0
            ? cov.choice_counts
            : [
                {
                  value: 'agree',
                  label: optionLabel(options, 'agree') || options[0]?.label || 'Đồng ý',
                  count: cov.agree_weight ?? cov.agree ?? 0,
                },
                {
                  value: 'disagree',
                  label:
                    optionLabel(options, 'disagree') ||
                    options[1]?.label ||
                    'Không đồng ý',
                  count: cov.disagree_weight ?? cov.disagree ?? 0,
                },
              ],
        remaining: cov.remaining,
        remaining_color: cov.remaining_color,
        quota,
        coverage: coverageNum,
      })
    : []

  const classLabel = data?.class
    ? [data.class.school, data.class.name].filter(Boolean).join(' · ')
    : data?.class_profile
      ? [data.class_profile.school_name, data.class_profile.class_name || data.class_profile.label]
          .filter(Boolean)
          .join(' · ')
      : null

  const binaryDock = isBinaryAgreeDisagree(options)
  const multiRadio = !binaryDock && options.length > 0
  const voted = Boolean(choice)
  const showVoteButtons = open && (!voted || changingVote)
  const showVotedSummary = voted && !changingVote
  const showChangeButton = open && voted && !changingVote
  const choiceIdx = choice ? options.findIndex((o) => o.value === choice) : -1
  const votedRole =
    choiceIdx >= 0 ? choiceRole(options[choiceIdx], choiceIdx) : 'other'
  const hasBottomDock = Boolean(data && open && (showVoteButtons || showChangeButton))

  useEffect(() => {
    if (changingVote && choice) {
      setPendingChoice(choice)
    }
    if (!changingVote && !voted) {
      setPendingChoice(null)
    }
  }, [changingVote, choice, voted])

  function onMultiVoteClick() {
    if (!pendingChoice) {
      choicesRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' })
      return
    }
    void vote(pendingChoice)
  }

  return (
    <Shell title="Phiếu phụ huynh" showNav={false}>
      <div className={hasBottomDock ? 'has-vote-dock' : undefined}>
      {error ? <p className="err">{error}</p> : null}
      {!data && !error ? <p className="muted">Đang tải phiếu…</p> : null}

      {data ? (
        <>
          <h1 className="h1-sm">{title}</h1>
          {classLabel ? <p className="muted">{classLabel}</p> : null}
          {content ? <p className="form-content">{content}</p> : null}

          <section className="panel stats">
            <h2>Thống kê lớp</h2>
            {typeof coverageNum === 'number' && typeof quota === 'number' ? (
              <p className="kpi-line">
                <strong>
                  {coverageNum}/{quota}
                </strong>
                <span className="muted">
                  {typeof cov.coverage_percent === 'number'
                    ? ` · ${cov.coverage_percent}%`
                    : coveragePct !== null
                      ? ` · ${coveragePct}%`
                      : ''}{' '}
                  sĩ số
                </span>
              </p>
            ) : null}
            {slices.length > 0 ? (
              <StackedBarChart slices={slices} ariaLabel="Biểu đồ sĩ số lớp" />
            ) : null}
          </section>

          {showVotedSummary ? (
            <section
              className={`alert ${
                votedRole === 'disagree' ? 'alert-danger' : 'alert-success'
              }`}
              role="status"
            >
              <p className="alert-title">Phiếu của bạn</p>
              <p className="alert-body">
                <ChoiceIcon role={votedRole} />
                <span>
                  Đã bình chọn: <strong>{optionLabel(options, choice!)}</strong>
                </span>
              </p>
            </section>
          ) : null}

          {open ? (
            <>
              {!authed && showTestLogin ? (
                <div className="panel">
                  <label className="field">
                    <span>Mã định danh thử nghiệm</span>
                    <input
                      value={zaloId}
                      onChange={(e) => setZaloId(e.target.value)}
                      placeholder="parent-demo-1"
                    />
                  </label>
                  <p className="tiny muted">
                    Chỉ hiện khi test ngoài Zalo Mini App.
                  </p>
                </div>
              ) : null}
              {changingVote ? (
                <p className="vote-change-back">
                  <button
                    type="button"
                    className="linkish"
                    onClick={() => {
                      setChangingVote(false)
                      setPendingChoice(null)
                    }}
                  >
                    ← Quay lại
                  </button>
                </p>
              ) : null}

              {showVoteButtons && multiRadio ? (
                <section
                  className="panel vote-choices-panel"
                  ref={choicesRef}
                  id="vote-choices"
                >
                  <h2>Bình chọn của bạn</h2>
                  <div className="vote-radio-list" role="radiogroup" aria-label="Bình chọn của bạn">
                    {options.map((opt) => {
                      const selected = pendingChoice === opt.value
                      return (
                        <label
                          key={opt.value}
                          className={`vote-radio-option${selected ? ' is-selected' : ''}`}
                        >
                          <input
                            type="radio"
                            name="vote-choice"
                            value={opt.value}
                            checked={selected}
                            disabled={saving}
                            onChange={() => setPendingChoice(opt.value)}
                          />
                          <span>{opt.label}</span>
                        </label>
                      )
                    })}
                  </div>
                </section>
              ) : null}

              {showChangeButton ? (
                <div className="vote-actions vote-actions--single">
                  <button
                    type="button"
                    className="btn outline vote-btn-change btn-with-icon"
                    onClick={() => setChangingVote(true)}
                  >
                    <ChangeVoteIcon />
                    <span>Thay đổi ý kiến</span>
                  </button>
                </div>
              ) : null}

              {showVoteButtons && binaryDock ? (
                <div className="vote-actions">
                  {options.map((opt, idx) => {
                    const selected = choice === opt.value
                    const role = choiceRole(opt, idx)
                    const sizeClass =
                      role === 'agree' ? 'vote-btn-agree' : 'vote-btn-disagree'
                    return (
                      <button
                        key={opt.value}
                        type="button"
                        className={voteBtnClass(role, selected, sizeClass)}
                        disabled={saving}
                        onClick={() => vote(opt.value)}
                      >
                        <ChoiceIcon role={role} />
                        <span>{opt.label}</span>
                      </button>
                    )
                  })}
                </div>
              ) : null}

              {showVoteButtons && multiRadio ? (
                <div className="vote-actions vote-actions--single">
                  <button
                    type="button"
                    className="btn accent vote-btn-submit btn-with-icon"
                    disabled={saving}
                    onClick={onMultiVoteClick}
                  >
                    <ChoiceIcon role="agree" />
                    <span>{saving ? 'Đang gửi…' : 'Bình chọn'}</span>
                  </button>
                </div>
              ) : null}
            </>
          ) : !voted ? (
            <p className="banner warn">Phiếu đã đóng — không thể gửi / đổi ý.</p>
          ) : null}
        </>
      ) : null}

      <p className="muted">
        <Link to="/parent">← Nhập mã khác</Link>
      </p>
      </div>
    </Shell>
  )
}
