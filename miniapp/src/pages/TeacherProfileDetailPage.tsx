import { useEffect, useState } from 'react'
import { Link, Navigate, useParams } from 'react-router-dom'
import { ShareZaloButton } from '../components/ShareZaloButton'
import { Shell } from '../components/Shell'
import {
  ApiError,
  ensureFormLink,
  getProfile,
  getToken,
  profileTitle,
  type ActiveForm,
  type ProfileDetail,
} from '../api/client'
import { copyText } from '../api/copyText'

function formsOf(detail: ProfileDetail): ActiveForm[] {
  return detail.active_forms || detail.forms || []
}

function miniappVotePath(voteUrl: string): string | null {
  try {
    const u = new URL(voteUrl, window.location.origin)
    const m = u.pathname.match(/\/vote\/([^/]+)/i)
    if (m?.[1]) return `/vote/${decodeURIComponent(m[1])}`
  } catch {
    /* ignore */
  }
  const m2 = voteUrl.match(/\/vote\/([^/?#]+)/i)
  return m2?.[1] ? `/vote/${decodeURIComponent(m2[1])}` : null
}

export function TeacherProfileDetailPage() {
  const token = getToken()
  const { id } = useParams<{ id: string }>()
  const [detail, setDetail] = useState<ProfileDetail | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [busyForm, setBusyForm] = useState<number | null>(null)
  const [copiedFor, setCopiedFor] = useState<number | null>(null)
  const [linkByForm, setLinkByForm] = useState<Record<number, string>>({})

  useEffect(() => {
    if (!token || !id) return
    let cancelled = false
    getProfile(id)
      .then((d) => {
        if (!cancelled) setDetail(d)
      })
      .catch((err: unknown) => {
        if (cancelled) return
        setError(
          err instanceof ApiError ? err.message : 'Không tải được chi tiết lớp.',
        )
      })
    return () => {
      cancelled = true
    }
  }, [token, id])

  if (!token) return <Navigate to="/teacher/login" replace />
  if (!id) return <Navigate to="/teacher" replace />

  const profileId = id

  async function onEnsure(form: ActiveForm) {
    setError(null)
    setBusyForm(form.id)
    try {
      const res = await ensureFormLink(profileId, form.id)
      const url = res.vote_url
      if (!url) throw new ApiError(500, 'Chưa nhận được link phiếu.')
      setLinkByForm((prev) => ({ ...prev, [form.id]: url }))
      await copyText(url)
      setCopiedFor(form.id)
      window.setTimeout(() => setCopiedFor(null), 2000)
    } catch (err) {
      setError(
        err instanceof ApiError ? err.message : 'Không tạo / copy link được.',
      )
    } finally {
      setBusyForm(null)
    }
  }

  const forms = detail ? formsOf(detail) : []

  return (
    <Shell title="Chi tiết lớp">
      {detail ? <h1 className="h1-sm">{profileTitle(detail)}</h1> : null}
      {detail && typeof detail.quota === 'number' ? (
        <p className="muted">Sĩ số: {detail.quota}</p>
      ) : null}
      {error ? <p className="err">{error}</p> : null}

      {!detail && !error ? <p className="muted">Đang tải…</p> : null}

      {detail ? (
        forms.length === 0 ? (
          <p className="muted">Chưa có form đang mở cho lớp này.</p>
        ) : (
          <ul className="list">
            {forms.map((f) => {
              const cf = f.class_form
              const voteUrl = linkByForm[f.id]
              const deep = voteUrl ? miniappVotePath(voteUrl) : null
              const status = cf?.status || f.status
              return (
                <li key={f.id} className="panel form-row">
                  <div>
                    <strong>{f.title || f.name || `Form #${f.id}`}</strong>
                    {status ? <span className="pill">{status}</span> : null}
                    {typeof cf?.coverage === 'number' ? (
                      <p className="tiny muted" style={{ marginTop: 6 }}>
                        Coverage: {cf.coverage}
                        {typeof cf.agree === 'number' || typeof cf.disagree === 'number'
                          ? ` · Đồng ý ${cf.agree ?? 0} / Không ${cf.disagree ?? 0}`
                          : ''}
                      </p>
                    ) : null}
                    {voteUrl ? (
                      <p className="mono tiny">{voteUrl}</p>
                    ) : null}
                  </div>
                  <div className="form-actions">
                    <ShareZaloButton
                      className="btn accent btn-with-icon"
                      disabled={busyForm === f.id}
                      url={voteUrl}
                      getUrl={
                        voteUrl
                          ? undefined
                          : async () => {
                              setBusyForm(f.id)
                              try {
                                const res = await ensureFormLink(profileId, f.id)
                                const url = res.vote_url
                                if (!url) throw new ApiError(500, 'Chưa nhận được link phiếu.')
                                setLinkByForm((prev) => ({ ...prev, [f.id]: url }))
                                return url
                              } finally {
                                setBusyForm(null)
                              }
                            }
                      }
                      onError={setError}
                    />
                    <button
                      type="button"
                      className="btn outline"
                      disabled={busyForm === f.id}
                      onClick={() => onEnsure(f)}
                    >
                      {busyForm === f.id
                        ? '…'
                        : copiedFor === f.id
                          ? 'Đã copy link'
                          : 'Copy link phiếu'}
                    </button>
                    {deep ? (
                      <Link className="btn outline" to={deep}>
                        Xem phiếu
                      </Link>
                    ) : null}
                  </div>
                </li>
              )
            })}
          </ul>
        )
      ) : null}

      <p className="muted">
        <Link to="/teacher">← Danh sách lớp</Link>
      </p>
    </Shell>
  )
}
