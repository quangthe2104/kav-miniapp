import { useEffect, useState } from 'react'
import {
  ApiError,
  confirmPaperBatch,
  fetchPaperItemImageUrl,
  getPaperBatch,
  type PaperBatchDetail,
} from '../api/client'
import { Icon } from './Icon'

function defaultChoice(batch: PaperBatchDetail, item: PaperBatchDetail['items'][number]) {
  const suggestion = item.ocr_suggestion
  if (suggestion && suggestion !== 'unknown' && batch.choices.some((c) => c.value === suggestion)) {
    return suggestion
  }
  return 'skip'
}

export function OcrConfirmModal({
  batchId,
  open,
  onClose,
  onConfirmed,
}: {
  batchId: number | null
  open: boolean
  onClose: () => void
  onConfirmed: (message: string) => void
}) {
  const [batch, setBatch] = useState<PaperBatchDetail | null>(null)
  const [choices, setChoices] = useState<Record<number, string>>({})
  const [thumbs, setThumbs] = useState<Record<number, string>>({})
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    if (!open || !batchId) return
    let cancelled = false
    let timer: number | undefined

    async function load() {
      try {
        const data = await getPaperBatch(batchId as number)
        if (cancelled) return
        setBatch(data)
        setChoices((prev) => {
          const next = { ...prev }
          for (const item of data.items) {
            if (!next[item.id]) next[item.id] = defaultChoice(data, item)
          }
          return next
        })
        if (data.status === 'processing') {
          timer = window.setTimeout(() => {
            void load()
          }, 2000)
        }
      } catch (err) {
        if (!cancelled) {
          setError(err instanceof ApiError ? err.message : 'Không tải được kết quả OCR.')
        }
      }
    }

    setError(null)
    setBatch(null)
    void load()

    return () => {
      cancelled = true
      if (timer) window.clearTimeout(timer)
    }
  }, [open, batchId])

  useEffect(() => {
    if (!open || !batch || batch.status === 'processing') return
    let cancelled = false
    const urls: string[] = []

    async function loadThumbs() {
      const next: Record<number, string> = {}
      for (const item of batch!.items) {
        try {
          const { url } = await fetchPaperItemImageUrl(item.id)
          if (cancelled) {
            URL.revokeObjectURL(url)
            return
          }
          next[item.id] = url
          urls.push(url)
        } catch {
          /* skip missing thumb */
        }
      }
      if (!cancelled) setThumbs(next)
    }

    void loadThumbs()
    return () => {
      cancelled = true
      urls.forEach((url) => URL.revokeObjectURL(url))
    }
  }, [open, batch])

  async function onConfirm() {
    if (!batchId || !batch) return
    setBusy(true)
    setError(null)
    try {
      const res = await confirmPaperBatch(
        batchId,
        batch.items.map((item) => ({
          id: item.id,
          choice: choices[item.id] || 'skip',
        })),
      )
      onConfirmed(res.message || 'Đã xác nhận phiếu giấy.')
      onClose()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Không xác nhận được.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <div
      className={`modal-backdrop${open ? ' is-open' : ''}`}
      role="presentation"
      onClick={(e) => {
        if (e.target === e.currentTarget && !busy) onClose()
      }}
    >
      {open ? (
        <div className="modal-sheet ocr-sheet" role="dialog" aria-modal="true" aria-label="Xác nhận OCR phiếu giấy">
          <div className="modal-head">
            <h3>Xác nhận OCR phiếu giấy</h3>
            <button type="button" className="linkish icon-btn modal-close" disabled={busy} onClick={onClose} aria-label="Đóng">
              <Icon name="x" />
            </button>
          </div>
          <div className="modal-body">
            {error ? <p className="err">{error}</p> : null}
            {!batch ? (
              <p className="muted">Đang tải…</p>
            ) : (
              <>
                <div className="ocr-kpi">
                  <span>
                    {batch.agree_label}: {batch.agree_count}
                  </span>
                  <span>
                    {batch.other_label}: {batch.disagree_count}
                  </span>
                  <span>
                    {batch.unknown_label || 'Không rõ'}: {batch.unknown_count}
                  </span>
                </div>
                {batch.status === 'processing' ? (
                  <p className="muted">Đang OCR… popup tự làm mới.</p>
                ) : null}
                <ul className="ocr-list">
                  {batch.items.map((item, idx) => (
                    <li key={item.id} className="ocr-item">
                      <div className="ocr-thumb">
                        {thumbs[item.id] ? (
                          <img src={thumbs[item.id]} alt={`Phiếu ${idx + 1}`} />
                        ) : (
                          <span className="muted">#{idx + 1}</span>
                        )}
                      </div>
                      <div className="ocr-meta">
                        <p>
                          <strong>#{idx + 1}</strong>{' '}
                          <span className="muted">
                            {item.ocr_suggestion_label}
                            {item.ocr_confidence_pct != null ? ` (${item.ocr_confidence_pct}%)` : ''}
                          </span>
                        </p>
                        <label className="field">
                          <select
                            aria-label="Xác nhận kết quả OCR"
                            value={choices[item.id] || 'skip'}
                            disabled={busy || batch.status !== 'ready'}
                            onChange={(e) =>
                              setChoices((prev) => ({ ...prev, [item.id]: e.target.value }))
                            }
                          >
                            {batch.choices.map((c) => (
                              <option key={c.value} value={c.value}>
                                {c.label}
                              </option>
                            ))}
                            <option value="skip">Bỏ qua</option>
                          </select>
                        </label>
                      </div>
                    </li>
                  ))}
                </ul>
                <button
                  type="button"
                  className="btn secondary btn-with-icon ocr-confirm-btn"
                  disabled={busy || batch.status !== 'ready'}
                  onClick={() => void onConfirm()}
                >
                  <Icon name="check" />
                  <span>Xác nhận lưu kết quả</span>
                </button>
              </>
            )}
          </div>
        </div>
      ) : null}
    </div>
  )
}
