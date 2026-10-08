import { useEffect, useState, type FormEvent } from 'react'
import { Link, Navigate, useNavigate, useParams } from 'react-router-dom'
import { StackedBarChart, buildQuotaSlices } from '../components/DoughnutChart'
import { Icon } from '../components/Icon'
import { OcrConfirmModal } from '../components/OcrConfirmModal'
import { ShareZaloButton } from '../components/ShareZaloButton'
import { Shell } from '../components/Shell'
import {
  ApiError,
  closeClassForm,
  deleteClassFormNote,
  deletePaperResponse,
  downloadClassFormQr,
  downloadClassFormTemplate,
  ensureFormLink,
  fetchNoteFileInlineUrl,
  fetchPaperImageUrl,
  getClassFormDetail,
  getToken,
  listProfiles,
  reopenClassForm,
  storeClassFormNote,
  type ClassFormDetail,
  type ClassProfile,
} from '../api/client'
import { copyText } from '../api/copyText'

type PreviewState = {
  title: string
  url: string
  kind: 'image' | 'pdf' | 'other'
} | null

function statusPillClass(status: string): string {
  if (status === 'closed') return 'pill muted-pill'
  if (status === 'quota_full') return 'pill warn'
  return 'pill'
}

function channelLabel(channel: string): string {
  if (channel === 'paper') return 'Giấy'
  if (channel === 'zalo') return 'Zalo'
  return channel
}

export function TeacherClassFormPage() {
  const token = getToken()
  const navigate = useNavigate()
  const { id } = useParams<{ id: string }>()
  const [data, setData] = useState<ClassFormDetail | null>(null)
  const [profiles, setProfiles] = useState<ClassProfile[]>([])
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [noteBody, setNoteBody] = useState('')
  const [noteFiles, setNoteFiles] = useState<File[]>([])
  const [copied, setCopied] = useState(false)
  const [statusMsg, setStatusMsg] = useState<string | null>(null)
  const [ocrBatchId, setOcrBatchId] = useState<number | null>(null)
  const [preview, setPreview] = useState<PreviewState>(null)
  const [previewOpen, setPreviewOpen] = useState(false)

  async function reload() {
    if (!id) return
    const d = await getClassFormDetail(id)
    setData(d)
  }

  useEffect(() => {
    if (!token || !id) return
    let cancelled = false
    getClassFormDetail(id)
      .then((d) => {
        if (!cancelled) setData(d)
      })
      .catch((err: unknown) => {
        if (cancelled) return
        setError(err instanceof ApiError ? err.message : 'Không tải được chi tiết.')
      })
    return () => {
      cancelled = true
    }
  }, [token, id])

  useEffect(() => {
    if (!token) return
    let cancelled = false
    listProfiles()
      .then((items) => {
        if (!cancelled) setProfiles(items)
      })
      .catch(() => {
        if (!cancelled) setProfiles([])
      })
    return () => {
      cancelled = true
    }
  }, [token])

  useEffect(() => {
    return () => {
      if (preview?.url) URL.revokeObjectURL(preview.url)
    }
  }, [preview])

  if (!token) return <Navigate to="/teacher/login" replace />
  if (!id) return <Navigate to="/teacher" replace />

  async function onEnsure() {
    if (!data) return
    setBusy(true)
    setError(null)
    try {
      const res = await ensureFormLink(data.class_form.profile_id, data.class_form.form_id)
      const url = res.vote_url || data.class_form.vote_url
      if (!url) throw new ApiError(500, 'Chưa nhận được link.')
      await copyText(url)
      setCopied(true)
      window.setTimeout(() => setCopied(false), 1600)
      await reload()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Không lấy được link.')
    } finally {
      setBusy(false)
    }
  }

  async function onCopyLink() {
    const url = data?.class_form.vote_url
    if (!url) {
      await onEnsure()
      return
    }
    try {
      await copyText(url)
      setCopied(true)
      setError(null)
      window.setTimeout(() => setCopied(false), 1600)
    } catch {
      setError('Không copy được link.')
    }
  }

  async function onClose() {
    if (!data) return
    const ok = window.confirm(
      `Đóng bình chọn lớp ${data.class_form.class_name} — form «${data.class_form.form_title}»?\n\nLink vẫn giữ nguyên; phụ huynh mở link sẽ thấy trạng thái đã đóng.`,
    )
    if (!ok) return
    setBusy(true)
    try {
      await closeClassForm(data.class_form.id)
      await reload()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Không đóng được.')
    } finally {
      setBusy(false)
    }
  }

  async function onReopen() {
    if (!data) return
    if (!window.confirm('Mở lại bình chọn? Link cũ sẽ hoạt động trở lại.')) return
    setBusy(true)
    try {
      await reopenClassForm(data.class_form.id)
      await reload()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Không mở lại được.')
    } finally {
      setBusy(false)
    }
  }

  async function onNote(e: FormEvent) {
    e.preventDefault()
    if (!data) return
    setBusy(true)
    setError(null)
    setStatusMsg(null)
    setOcrBatchId(null)
    try {
      const res = await storeClassFormNote(data.class_form.id, noteBody, noteFiles)
      setNoteBody('')
      setNoteFiles([])
      if (res.message) setStatusMsg(res.message)
      if (res.ocr?.batch_id) setOcrBatchId(res.ocr.batch_id)
      await reload()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Không lưu ghi chú.')
    } finally {
      setBusy(false)
    }
  }

  async function openPreview(
    title: string,
    loader: () => Promise<{ url: string; mime: string }>,
    preferPdf = false,
  ) {
    setBusy(true)
    setError(null)
    try {
      const { url, mime } = await loader()
      const kind: 'image' | 'pdf' | 'other' =
        preferPdf || mime.includes('pdf')
          ? 'pdf'
          : mime.startsWith('image/')
            ? 'image'
            : 'other'
      setPreview((prev) => {
        if (prev?.url) URL.revokeObjectURL(prev.url)
        return { title, url, kind }
      })
      requestAnimationFrame(() => setPreviewOpen(true))
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Không mở được file.')
    } finally {
      setBusy(false)
    }
  }

  function closePreview() {
    setPreviewOpen(false)
    window.setTimeout(() => {
      setPreview((prev) => {
        if (prev?.url) URL.revokeObjectURL(prev.url)
        return null
      })
    }, 280)
  }

  const statusLabel =
    data?.class_form.status_label ||
    ({
      open: 'Đang mở',
      quota_full: 'Đủ sĩ số',
      closed: 'Đã đóng',
    }[data?.class_form.status || ''] ??
      data?.class_form.status)

  const slices = data
    ? buildQuotaSlices({
        choice_counts: data.stats.choice_counts,
        remaining: data.stats.remaining,
        remaining_color: data.stats.remaining_color,
        quota: data.class_form.quota,
        coverage: data.stats.coverage,
      })
    : []

  return (
    <Shell title="Chi tiết form">
      {error ? <p className="err">{error}</p> : null}
      {statusMsg ? <p className="muted">{statusMsg}</p> : null}
      {!data && !error ? <p className="muted">Đang tải…</p> : null}

      {data ? (
        <>
          <h1 className="h1-sm">{data.class_form.form_title}</h1>
          <p className="muted">
            Lớp {data.class_form.class_name}
            {data.class_form.school ? ` — ${data.class_form.school}` : ''}
            {' · '}
            <span className={statusPillClass(data.class_form.status)}>{statusLabel}</span>
          </p>
          {profiles.length > 1 ? (
            <select
              className="class-switcher"
              aria-label="Chọn lớp"
              value={String(data.class_form.profile_id)}
              onChange={(e) => {
                navigate(`/teacher?profile_id=${e.target.value}`)
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

          {data.class_form.form_active === false ? (
            <div className="alert alert-warning" role="alert">
              Admin đã đóng Form — phụ huynh không bình chọn được. Giáo viên không mở lại được cho
              đến khi Admin mở Form.
            </div>
          ) : null}

          <section className="panel stats">
            <h2 className="section-title">Thống kê lớp</h2>
            <p className="kpi-line">
              <strong className="kpi-value">
                {data.stats.coverage}/{data.class_form.quota}
              </strong>
              <span className="muted">
                {data.stats.coverage_pct}% sĩ số · {data.stats.total} phiếu
              </span>
            </p>
            {slices.length > 0 ? (
              <StackedBarChart slices={slices} ariaLabel="Biểu đồ sĩ số lớp" />
            ) : null}
          </section>

          {data.class_form.form_active === false ? (
          <section className="panel">
            <h2 className="section-title">Ghi chú đã lưu</h2>
            <ul className="list notes-list">
              {data.notes.map((n) => (
                <li key={n.id} className="note-card">
                  <div className="note-meta">
                    <span className="tiny muted">
                      {n.created_at ? new Date(n.created_at).toLocaleString('vi-VN') : ''}
                      {n.teacher_name ? ` · ${n.teacher_name}` : ''}
                    </span>
                  </div>
                  {n.body ? <p style={{ whiteSpace: 'pre-wrap' }}>{n.body}</p> : null}
                  {n.has_file ? (
                    <button
                      type="button"
                      className="linkish"
                      disabled={busy}
                      onClick={() =>
                        openPreview(
                          n.file_name || 'File đính kèm',
                          () => fetchNoteFileInlineUrl(n.id),
                          Boolean(n.is_pdf),
                        )
                      }
                    >
                      {n.file_name || 'Xem file'}
                    </button>
                  ) : null}
                </li>
              ))}
              {data.notes.length === 0 ? (
                <li className="muted">Chưa có ghi chú.</li>
              ) : null}
            </ul>
          </section>
          ) : null}

          {data.class_form.form_active !== false ? (
          <section className="panel">
            <h2 className="section-title">Link bình chọn</h2>
            {data.class_form.vote_url ? (
              <>
                <p className="mono tiny">{data.class_form.vote_url}</p>
                <div className="btn-grid-2" style={{ marginTop: 10 }}>
                  <ShareZaloButton
                    className="btn primary btn-with-icon"
                    url={data.class_form.vote_url}
                    disabled={busy}
                    onError={setError}
                  />
                  <button
                    type="button"
                    className="btn secondary btn-with-icon"
                    disabled={busy}
                    onClick={onCopyLink}
                  >
                    <Icon name={copied ? 'check' : 'copy'} />
                    <span>{copied ? 'Đã copy' : 'Copy link'}</span>
                  </button>
                  <button
                    type="button"
                    className="btn outline btn-with-icon"
                    disabled={busy}
                    onClick={() =>
                      downloadClassFormQr(data.class_form.id).catch((err) => {
                        setError(err instanceof ApiError ? err.message : 'Không tải QR.')
                      })
                    }
                  >
                    <Icon name="qr" />
                    <span>Tải QR</span>
                  </button>
                  {data.class_form.has_template ? (
                    <button
                      type="button"
                      className="btn outline btn-with-icon"
                      disabled={busy}
                      onClick={() =>
                        downloadClassFormTemplate(
                          data.class_form.id,
                          data.class_form.form_title || 'mau-phieu',
                        )
                      }
                    >
                      <Icon name="download" />
                      <span>Tải mẫu phiếu</span>
                    </button>
                  ) : null}
                </div>
              </>
            ) : (
              <>
                <p className="muted">Chưa có link. Bấm lấy link để tạo mã cố định.</p>
                <button type="button" className="btn primary btn-with-icon" disabled={busy} onClick={onEnsure}>
                  <Icon name="link" />
                  <span>Lấy link / Copy</span>
                </button>
              </>
            )}
          </section>
          ) : null}

          {data.class_form.form_active !== false ? (
          <section className="panel">
            <h2 className="section-title">Ghi chú &amp; phiếu giấy</h2>
            <form onSubmit={onNote}>
              <label className="field">
                <span>Nội dung</span>
                <textarea
                  value={noteBody}
                  onChange={(e) => setNoteBody(e.target.value)}
                  rows={3}
                  placeholder="Ghi chú lớp / lưu ý…"
                />
              </label>
              <label className="field">
                <span>Đính kèm file</span>
                <input
                  type="file"
                  multiple
                  accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf"
                  onChange={(e) =>
                    setNoteFiles(e.target.files ? Array.from(e.target.files) : [])
                  }
                />
              </label>
              {noteFiles.length > 0 ? (
                <p className="tiny muted">{noteFiles.length} file đã chọn</p>
              ) : null}
              <p className="tiny muted">
                Ảnh phiếu → OCR ngay trong Mini App. PDF → chỉ lưu kèm ghi chú.
              </p>
              <button className="btn primary btn-with-icon" type="submit" disabled={busy}>
                <Icon name="upload" />
                <span>Lưu / chạy OCR</span>
              </button>
            </form>
          </section>
          ) : null}

          {data.class_form.form_active !== false ? (
          <section className="panel">
            <h2 className="section-title">Ghi chú đã lưu</h2>
            <ul className="list notes-list">
              {data.notes.map((n) => (
                <li key={n.id} className="note-card">
                  <div className="note-meta">
                    <span className="tiny muted">
                      {n.created_at ? new Date(n.created_at).toLocaleString('vi-VN') : ''}
                      {n.teacher_name ? ` · ${n.teacher_name}` : ''}
                    </span>
                    {n.can_delete ? (
                      <button
                        type="button"
                        className="linkish icon-btn"
                        disabled={busy}
                        aria-label="Xóa ghi chú"
                        onClick={async () => {
                          if (!window.confirm('Xóa ghi chú này?')) return
                          setBusy(true)
                          try {
                            await deleteClassFormNote(n.id)
                            await reload()
                          } catch (err) {
                            setError(err instanceof ApiError ? err.message : 'Không xóa được.')
                          } finally {
                            setBusy(false)
                          }
                        }}
                      >
                        <Icon name="trash" />
                      </button>
                    ) : null}
                  </div>
                  {n.body ? <p style={{ whiteSpace: 'pre-wrap' }}>{n.body}</p> : null}
                  {n.has_file ? (
                    <button
                      type="button"
                      className="linkish"
                      disabled={busy}
                      onClick={() =>
                        openPreview(
                          n.file_name || 'File đính kèm',
                          () => fetchNoteFileInlineUrl(n.id),
                          Boolean(n.is_pdf),
                        )
                      }
                    >
                      {n.file_name || 'Xem file'}
                    </button>
                  ) : null}
                </li>
              ))}
              {data.notes.length === 0 ? (
                <li className="muted">Chưa có ghi chú.</li>
              ) : null}
            </ul>
          </section>
          ) : null}

          <section className="panel">
            <h2 className="section-title">Chi tiết Bình chọn</h2>
            <p className="tiny muted">
              Phiếu Zalo: chỉ xem.
              {data.class_form.can_edit_paper
                ? ' Phiếu giấy do bạn tải: có thể xóa khi vote/form còn mở.'
                : ''}
            </p>
            <ul className="list">
              {data.responses.map((r, idx) => (
                <li key={r.id} className="response-card">
                  <div className="note-meta">
                    <div className="response-row">
                      <strong>#{r.stt ?? idx + 1}</strong>
                      <span>{r.zalo_name || r.zalo_user_id || '—'}</span>
                    </div>
                    {r.can_delete ? (
                      <button
                        type="button"
                        className="linkish icon-btn"
                        disabled={busy}
                        aria-label="Xóa phiếu"
                        onClick={async () => {
                          if (!window.confirm('Xóa kết quả phiếu giấy này?')) return
                          setBusy(true)
                          try {
                            await deletePaperResponse(r.id)
                            await reload()
                          } catch (err) {
                            setError(err instanceof ApiError ? err.message : 'Không xóa được.')
                          } finally {
                            setBusy(false)
                          }
                        }}
                      >
                        <Icon name="trash" />
                      </button>
                    ) : null}
                  </div>
                  <div className="tiny muted response-meta">
                    {r.phone || '—'} ·{' '}
                    {r.has_paper_image ? (
                      <button
                        type="button"
                        className="linkish channel-view"
                        disabled={busy}
                        aria-label="Xem phiếu"
                        title="Xem phiếu"
                        onClick={() =>
                          openPreview(`Phiếu giấy #${r.id}`, () => fetchPaperImageUrl(r.id))
                        }
                      >
                        <Icon name="eye" />
                        <span>{channelLabel(r.channel)}</span>
                      </button>
                    ) : (
                      channelLabel(r.channel)
                    )}
                    {' · '}
                    {r.choice_label || r.choice}
                    {r.created_at
                      ? ` · ${new Date(r.created_at).toLocaleString('vi-VN')}`
                      : ''}
                  </div>
                </li>
              ))}
              {data.responses.length === 0 ? (
                <li className="muted">Chưa có bình chọn.</li>
              ) : null}
            </ul>
          </section>

          {data.class_form.form_active !== false ? (
          <section className="panel close-row">
            <p className="tiny muted" style={{ margin: 0 }}>
              Sau khi lớp đã hoàn thành bình chọn, giáo viên chủ nhiệm có thể chủ động đóng bình
              chọn để hoàn tất.
            </p>
            {data.class_form.status !== 'closed' ? (
              <button type="button" className="btn danger btn-with-icon" disabled={busy} onClick={onClose}>
                <Icon name="lock" />
                <span>Đóng bình chọn</span>
              </button>
            ) : (
              <button type="button" className="btn accent btn-with-icon" disabled={busy} onClick={onReopen}>
                <Icon name="unlock" />
                <span>Mở lại bình chọn</span>
              </button>
            )}
          </section>
          ) : null}
        </>
      ) : null}

      <OcrConfirmModal
        batchId={ocrBatchId}
        open={ocrBatchId !== null}
        onClose={() => setOcrBatchId(null)}
        onConfirmed={(message) => {
          setStatusMsg(message)
          void reload()
        }}
      />

      <div
        className={`modal-backdrop${previewOpen && preview ? ' is-open' : ''}`}
        role="presentation"
        onClick={(e) => {
          if (e.target === e.currentTarget) closePreview()
        }}
      >
        {preview ? (
          <div className="modal-sheet" role="dialog" aria-modal="true" aria-label={preview.title}>
            <div className="modal-head">
              <h3>{preview.title}</h3>
              <button type="button" className="linkish icon-btn modal-close" onClick={closePreview} aria-label="Đóng">
                <Icon name="x" />
              </button>
            </div>
            <div className="modal-body">
              {preview.kind === 'pdf' ? (
                <iframe className="file-preview" title={preview.title} src={preview.url} />
              ) : preview.kind === 'image' ? (
                <img className="paper-preview" src={preview.url} alt={preview.title} />
              ) : (
                <p className="muted">
                  <a href={preview.url} download>
                    Tải file
                  </a>
                </p>
              )}
            </div>
          </div>
        ) : null}
      </div>

      <p className="muted">
        <Link
          to={`/teacher?profile_id=${data?.class_form.profile_id || ''}&list=1`}
        >
          ← Danh sách form
        </Link>
      </p>
    </Shell>
  )
}
