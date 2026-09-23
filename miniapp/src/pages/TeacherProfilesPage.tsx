import { useEffect, useMemo, useState } from 'react'
import { Link, Navigate, useSearchParams } from 'react-router-dom'
import { ShareZaloButton } from '../components/ShareZaloButton'
import { Icon } from '../components/Icon'
import { Shell } from '../components/Shell'
import {
  ApiError,
  ensureFormLink,
  getToken,
  getProfileForms,
  listProfiles,
  downloadClassFormTemplate,
  type ClassProfile,
  type ProfileFormRow,
} from '../api/client'

export function TeacherProfilesPage() {
  const token = getToken()
  const [search, setSearch] = useSearchParams()
  const [profiles, setProfiles] = useState<ClassProfile[] | null>(null)
  const [forms, setForms] = useState<ProfileFormRow[] | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState<string | null>(null)
  const [autoOpenId, setAutoOpenId] = useState<number | null>(null)
  const [autoOpening, setAutoOpening] = useState(false)
  const [autoOpenFailed, setAutoOpenFailed] = useState(false)
  const [openingFormId, setOpeningFormId] = useState<number | null>(null)

  const profileId = search.get('profile_id') || ''
  const stayOnList = search.get('list') === '1'

  useEffect(() => {
    if (!token) return
    let cancelled = false
    listProfiles()
      .then((items) => {
        if (cancelled) return
        setProfiles(items)
        if (!search.get('profile_id') && items[0]) {
          const next = new URLSearchParams(search)
          next.set('profile_id', String(items[0].id))
          next.delete('status')
          setSearch(next, { replace: true })
        }
      })
      .catch((err: unknown) => {
        if (cancelled) return
        setError(err instanceof ApiError ? err.message : 'Không tải được lớp.')
        setProfiles([])
      })
    return () => {
      cancelled = true
    }
  }, [token])

  useEffect(() => {
    if (!token || !profileId) {
      setForms(null)
      return
    }
    let cancelled = false
    setForms(null)
    getProfileForms(profileId, 'open')
      .then((data) => {
        if (!cancelled) setForms(data.forms)
      })
      .catch((err: unknown) => {
        if (cancelled) return
        setError(err instanceof ApiError ? err.message : 'Không tải được form.')
        setForms([])
      })
    return () => {
      cancelled = true
    }
  }, [token, profileId])

  useEffect(() => {
    if (stayOnList || autoOpenFailed || !profiles || forms === null) return
    if (profiles.length !== 1 || forms.length !== 1) return

    const row = forms[0]
    const profile = profiles[0]
    if (!row || !profile) return
    let cancelled = false

    async function openSingleForm() {
      setAutoOpening(true)
      try {
        const res = await ensureFormLink(profile.id, row.form_id)
        if (!cancelled && res.class_form_id) {
          setAutoOpenId(res.class_form_id)
        } else if (!cancelled) {
          setAutoOpenFailed(true)
        }
      } catch (err) {
        if (!cancelled) {
          setError(err instanceof ApiError ? err.message : 'Không mở được form.')
          setAutoOpenFailed(true)
        }
      } finally {
        if (!cancelled) setAutoOpening(false)
      }
    }

    void openSingleForm()
    return () => {
      cancelled = true
    }
  }, [stayOnList, autoOpenFailed, profiles, forms])

  const selected = useMemo(
    () => profiles?.find((p) => String(p.id) === String(profileId)),
    [profiles, profileId],
  )

  if (!token) return <Navigate to="/teacher/login" replace />
  if (autoOpenId) {
    return <Navigate to={`/teacher/class-forms/${autoOpenId}`} replace />
  }

  const pendingAutoOpen =
    !stayOnList &&
    !autoOpenFailed &&
    profiles !== null &&
    profiles.length === 1 &&
    (forms === null || forms.length === 1)

  async function openFormDetail(row: ProfileFormRow) {
    if (!selected) return
    setOpeningFormId(row.form_id)
    setError(null)
    try {
      const res = await ensureFormLink(selected.id, row.form_id)
      if (!res.class_form_id) throw new ApiError(500, 'Chưa tạo được form lớp.')
      setAutoOpenId(res.class_form_id)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Không mở được form.')
    } finally {
      setOpeningFormId(null)
    }
  }

  async function onDownload(row: ProfileFormRow) {
    if (!row.class_form_id) {
      setError('Hãy Share Zalo / mở form trước, rồi tải mẫu phiếu.')
      return
    }
    setBusy(`dl-${row.form_id}`)
    setError(null)
    try {
      await downloadClassFormTemplate(row.class_form_id, row.title)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Không tải được mẫu phiếu.')
    } finally {
      setBusy(null)
    }
  }

  return (
    <Shell title="Lớp của tôi">
      {error ? <p className="err">{error}</p> : null}

      {profiles === null || autoOpening || pendingAutoOpen ? (
        <p className="muted">
          {autoOpening || (forms !== null && forms.length === 1) ? 'Đang mở form…' : 'Đang tải…'}
        </p>
      ) : profiles.length === 0 ? (
        <section className="panel onboarding">
          <h1 className="h1-sm">Chào mừng!</h1>
          <p className="lead">
            Tạo lớp và lấy link gửi phụ huynh ngay trong Mini App.
          </p>
          <Link className="btn primary btn-with-icon" to="/teacher/profiles/create">
            <Icon name="plus" />
            <span>Tạo lớp đầu tiên</span>
          </Link>
        </section>
      ) : (
        <>
          {profiles.length > 1 ? (
            <select
              className="class-switcher"
              aria-label="Chọn lớp"
              value={profileId}
              onChange={(e) => {
                const next = new URLSearchParams(search)
                next.set('profile_id', e.target.value)
                next.delete('status')
                setSearch(next)
              }}
            >
              {profiles.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.class_name}
                  {typeof p.school === 'string' && p.school ? ` — ${p.school}` : ''}
                </option>
              ))}
            </select>
          ) : null}

          {forms === null ? (
            <p className="muted">Đang tải form…</p>
          ) : (
            <section className="panel forms-open-box">
              <h2 className="section-title">
                Form đang mở
                {selected ? ` — lớp ${selected.class_name || ''}` : ''}
              </h2>
              {selected ? (
                <p className="tiny muted" style={{ marginTop: 0 }}>
                  Sĩ số {selected.quota}
                  {selected.ward || selected.province
                    ? ` · ${[selected.ward, selected.province].filter(Boolean).join(', ')}`
                    : ''}
                </p>
              ) : null}
              <ul className="list forms-open-list">
                {forms.map((row) => (
                  <li key={`${row.form_id}-${row.class_form_id ?? 'new'}`} className="form-row">
                    <div>
                      <button
                        type="button"
                        className="linkish form-title-btn"
                        disabled={busy !== null || openingFormId !== null}
                        onClick={() => void openFormDetail(row)}
                      >
                        <strong>
                          {openingFormId === row.form_id ? 'Đang mở…' : row.title}
                        </strong>
                      </button>
                      <p className="tiny muted" style={{ marginTop: 6 }}>
                        HT {row.coverage}/{row.quota} ({row.coverage_pct}%)
                        {' · '}
                        <span title={row.choice_tooltip} style={{ cursor: 'help' }}>
                          {row.choice_compact} ⓘ
                        </span>
                      </p>
                    </div>
                    <div className="form-actions">
                      <ShareZaloButton
                        className="btn accent btn-with-icon"
                        disabled={busy === `share-${row.form_id}`}
                        getUrl={async () => {
                          if (!selected) throw new Error('Chưa chọn lớp.')
                          setBusy(`share-${row.form_id}`)
                          try {
                            const res = await ensureFormLink(selected.id, row.form_id)
                            if (!res.vote_url) throw new ApiError(500, 'Chưa nhận được link.')
                            const data = await getProfileForms(selected.id, 'open')
                            setForms(data.forms)
                            return res.vote_url
                          } finally {
                            setBusy(null)
                          }
                        }}
                        onError={setError}
                      />
                      {row.has_template ? (
                        <button
                          type="button"
                          className="btn outline btn-with-icon"
                          disabled={busy === `dl-${row.form_id}`}
                          onClick={() => onDownload(row)}
                        >
                          <Icon name="download" />
                          <span>Tải mẫu phiếu</span>
                        </button>
                      ) : null}
                    </div>
                  </li>
                ))}
                {forms.length === 0 ? (
                  <li className="muted">Chưa có form đang mở cho lớp này.</li>
                ) : null}
              </ul>
            </section>
          )}
        </>
      )}

      <p className="muted">
        <Link to="/">← Trang chủ</Link>
      </p>
    </Shell>
  )
}
