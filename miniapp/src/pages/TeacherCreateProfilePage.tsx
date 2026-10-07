import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import { Icon } from '../components/Icon'
import { Combobox } from '../components/Combobox'
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
  const [className, setClassName] = useState('')
  const [quota, setQuota] = useState('40')

  const [loadingWards, setLoadingWards] = useState(false)
  const [loadingSchools, setLoadingSchools] = useState(false)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!token) return
    listProvinces()
      .then(setProvinces)
      .catch((err: unknown) => {
        setError(err instanceof ApiError ? err.message : 'Không tải được danh sách tỉnh.')
      })
  }, [token])

  useEffect(() => {
    setWards([])
    setWardId('')
    if (!provinceId) return
    let cancelled = false
    setLoadingWards(true)
    listWards(provinceId)
      .then((rows) => {
        if (!cancelled) setWards(rows)
      })
      .catch((err: unknown) => {
        if (!cancelled) setError(err instanceof ApiError ? err.message : 'Không tải được phường/xã.')
      })
      .finally(() => {
        if (!cancelled) setLoadingWards(false)
      })
    return () => {
      cancelled = true
    }
  }, [provinceId])

  useEffect(() => {
    setSchools([])
    setSchoolId('')
    if (!wardId) return
    let cancelled = false
    setLoadingSchools(true)
    listSchools(wardId)
      .then((rows) => {
        if (!cancelled) setSchools(rows)
      })
      .catch((err: unknown) => {
        if (!cancelled) setError(err instanceof ApiError ? err.message : 'Không tải được trường.')
      })
      .finally(() => {
        if (!cancelled) setLoadingSchools(false)
      })
    return () => {
      cancelled = true
    }
  }, [wardId])

  const provinceOptions = useMemo(
    () => provinces.map((p) => ({ value: String(p.id), label: p.name })),
    [provinces],
  )
  const wardOptions = useMemo(
    () => wards.map((w) => ({ value: String(w.id), label: w.name })),
    [wards],
  )
  const schoolOptions = useMemo(
    () =>
      schools.map((s) => ({
        value: String(s.id),
        label: `${s.external_id ? `[${s.external_id}] ` : ''}${s.name}`,
      })),
    [schools],
  )
  if (!token) return <Navigate to="/teacher/login" replace />

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    if (!provinceId || !wardId || !schoolId) {
      setError('Vui lòng chọn đủ Tỉnh, Phường/Xã và Trường.')
      return
    }
    if (!className.trim() || !quota) {
      setError('Vui lòng nhập tên lớp và sĩ số.')
      return
    }
    setSaving(true)
    try {
      const profile = await createProfile({
        school_id: Number(schoolId),
        class_name: className.trim(),
        quota: Number(quota),
      })
      navigate(`/teacher/profiles/${profile.id}`, { replace: true })
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Không tạo được lớp. Thử lại.')
    } finally {
      setSaving(false)
    }
  }

  return (
    <Shell title="Tạo lớp mới">
      <p className="lead">Chọn trường, nhập tên lớp &amp; sĩ số rồi bấm Tạo lớp.</p>

      <form className="panel" onSubmit={onSubmit} noValidate>
        <Combobox
          label="Tỉnh"
          options={provinceOptions}
          value={provinceId}
          onChange={setProvinceId}
          placeholder="Gõ hoặc chọn tỉnh…"
        />
        <Combobox
          key={`ward-${provinceId}`}
          label="Phường / Xã"
          options={wardOptions}
          value={wardId}
          onChange={setWardId}
          disabled={!provinceId}
          loading={loadingWards}
          placeholder={provinceId ? 'Gõ hoặc chọn phường / xã…' : 'Chọn tỉnh trước'}
        />
        <Combobox
          key={`school-${wardId}`}
          label="Trường"
          options={schoolOptions}
          value={schoolId}
          onChange={setSchoolId}
          disabled={!wardId}
          loading={loadingSchools}
          placeholder={wardId ? 'Gõ tên hoặc mã trường…' : 'Chọn phường / xã trước'}
        />

        <label className="field">
          <span>Tên lớp</span>
          <input
            value={className}
            onChange={(e) => setClassName(e.target.value)}
            placeholder="Ví dụ: 5A"
            maxLength={100}
          />
        </label>
        <label className="field">
          <span>Sĩ số</span>
          <input
            type="number"
            inputMode="numeric"
            min={1}
            max={80}
            value={quota}
            onChange={(e) => setQuota(e.target.value)}
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
