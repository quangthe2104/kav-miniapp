import { useEffect, useState, type FormEvent } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import { Icon } from '../components/Icon'
import { Shell } from '../components/Shell'
import {
  ApiError,
  createProfile,
  getToken,
  listProvinces,
  listSchools,
  listWards,
  type CatalogItem,
  type SchoolItem,
} from '../api/client'

export function TeacherCreateProfilePage() {
  const token = getToken()
  const navigate = useNavigate()

  const [provinces, setProvinces] = useState<CatalogItem[]>([])
  const [wards, setWards] = useState<CatalogItem[]>([])
  const [schools, setSchools] = useState<SchoolItem[]>([])

  const [provinceId, setProvinceId] = useState('')
  const [wardId, setWardId] = useState('')
  const [schoolId, setSchoolId] = useState('')
  const [schoolQ, setSchoolQ] = useState('')
  const [className, setClassName] = useState('')
  const [quota, setQuota] = useState('40')

  const [loadingCatalog, setLoadingCatalog] = useState(false)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!token) return
    listProvinces()
      .then(setProvinces)
      .catch((err: unknown) => {
        setError(
          err instanceof ApiError ? err.message : 'Không tải được danh sách tỉnh.',
        )
      })
  }, [token])

  useEffect(() => {
    if (!provinceId) {
      setWards([])
      setWardId('')
      return
    }
    setLoadingCatalog(true)
    setWards([])
    setWardId('')
    setSchools([])
    setSchoolId('')
    listWards(provinceId)
      .then(setWards)
      .catch((err: unknown) => {
        setError(
          err instanceof ApiError ? err.message : 'Không tải được phường/xã.',
        )
      })
      .finally(() => setLoadingCatalog(false))
  }, [provinceId])

  useEffect(() => {
    if (!wardId) {
      setSchools([])
      setSchoolId('')
      return
    }
    setLoadingCatalog(true)
    setSchoolId('')
    const handle = window.setTimeout(() => {
      listSchools(wardId, schoolQ)
        .then(setSchools)
        .catch((err: unknown) => {
          setError(
            err instanceof ApiError ? err.message : 'Không tải được trường.',
          )
        })
        .finally(() => setLoadingCatalog(false))
    }, schoolQ ? 250 : 0)
    return () => window.clearTimeout(handle)
  }, [wardId, schoolQ])

  if (!token) return <Navigate to="/teacher/login" replace />

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    setSaving(true)
    try {
      const profile = await createProfile({
        school_id: Number(schoolId),
        class_name: className.trim(),
        quota: Number(quota),
      })
      navigate(`/teacher/profiles/${profile.id}`, { replace: true })
    } catch (err) {
      setError(
        err instanceof ApiError ? err.message : 'Không tạo được lớp. Thử lại.',
      )
    } finally {
      setSaving(false)
    }
  }

  return (
    <Shell title="Tạo lớp">
      <p className="lead">
        Chọn trường, đặt tên lớp và sĩ số — xong là lấy link gửi phụ huynh ngay
        trong Mini App.
      </p>

      <form className="panel" onSubmit={onSubmit}>
        <label className="field">
          <span>Tỉnh / Thành</span>
          <select
            value={provinceId}
            onChange={(e) => setProvinceId(e.target.value)}
            required
          >
            <option value="">— Chọn —</option>
            {provinces.map((p) => (
              <option key={p.id} value={p.id}>
                {p.name}
              </option>
            ))}
          </select>
        </label>

        <label className="field">
          <span>Phường / Xã</span>
          <select
            value={wardId}
            onChange={(e) => setWardId(e.target.value)}
            required
            disabled={!provinceId || loadingCatalog}
          >
            <option value="">— Chọn —</option>
            {wards.map((w) => (
              <option key={w.id} value={w.id}>
                {w.name}
              </option>
            ))}
          </select>
        </label>

        <label className="field">
          <span>Tìm trường (tuỳ chọn)</span>
          <input
            value={schoolQ}
            onChange={(e) => setSchoolQ(e.target.value)}
            placeholder="Tên hoặc mã trường"
            disabled={!wardId}
          />
        </label>

        <label className="field">
          <span>Trường</span>
          <select
            value={schoolId}
            onChange={(e) => setSchoolId(e.target.value)}
            required
            disabled={!wardId || loadingCatalog}
          >
            <option value="">— Chọn —</option>
            {schools.map((s) => (
              <option key={s.id} value={s.id}>
                {s.external_id ? `[${s.external_id}] ` : ''}
                {s.name}
              </option>
            ))}
          </select>
        </label>

        <label className="field">
          <span>Tên lớp</span>
          <input
            value={className}
            onChange={(e) => setClassName(e.target.value)}
            placeholder="Ví dụ: 5A"
            required
            maxLength={100}
          />
        </label>

        <label className="field">
          <span>Sĩ số</span>
          <input
            type="number"
            min={1}
            max={80}
            value={quota}
            onChange={(e) => setQuota(e.target.value)}
            required
          />
        </label>

        {error ? <p className="err">{error}</p> : null}

        <button className="btn primary btn-with-icon" type="submit" disabled={saving}>
          <Icon name="plus" />
          <span>{saving ? 'Đang tạo…' : 'Tạo lớp'}</span>
        </button>
      </form>

      <p className="muted">
        <Link to="/teacher">← Danh sách lớp</Link>
      </p>
    </Shell>
  )
}
