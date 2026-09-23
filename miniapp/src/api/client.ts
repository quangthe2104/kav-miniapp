const TOKEN_KEY = 'kav_sanctum_token'

/** Same-origin when Mini App is served under Laravel `/miniapp/`; override via VITE_API_BASE_URL. */
export const API_BASE = (() => {
  const fromEnv = String(import.meta.env.VITE_API_BASE_URL || '')
    .trim()
    .replace(/\/$/, '')
  if (fromEnv) return fromEnv
  if (typeof window !== 'undefined' && window.location?.origin) {
    return window.location.origin
  }
  return 'http://miniapp.kav'
})()

export class ApiError extends Error {
  status: number
  body: unknown

  constructor(status: number, message: string, body?: unknown) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.body = body
  }
}

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY)
}

export function setToken(token: string | null): void {
  if (token) localStorage.setItem(TOKEN_KEY, token)
  else localStorage.removeItem(TOKEN_KEY)
}

function validationMessage(body: unknown): string | null {
  if (!body || typeof body !== 'object') return null
  const o = body as Record<string, unknown>
  if (typeof o.message === 'string') return o.message
  if (o.errors && typeof o.errors === 'object') {
    const first = Object.values(o.errors as Record<string, unknown>)[0]
    if (Array.isArray(first) && typeof first[0] === 'string') return first[0]
    if (typeof first === 'string') return first
  }
  return null
}

export async function api<T>(
  path: string,
  options: RequestInit = {},
): Promise<T> {
  const headers = new Headers(options.headers)
  if (!headers.has('Accept')) headers.set('Accept', 'application/json')
  if (options.body && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json')
  }
  const token = getToken()
  if (token) headers.set('Authorization', `Bearer ${token}`)

  const res = await fetch(`${API_BASE}${path}`, { ...options, headers })

  if (res.status === 204) return undefined as T

  let body: unknown = null
  const text = await res.text()
  if (text) {
    try {
      body = JSON.parse(text)
    } catch {
      body = text
    }
  }

  if (res.status === 401) {
    setToken(null)
    throw new ApiError(401, 'Phiên đăng nhập hết hạn hoặc chưa đăng nhập.', body)
  }

  if (res.status === 422) {
    throw new ApiError(
      422,
      validationMessage(body) || 'Dữ liệu không hợp lệ.',
      body,
    )
  }

  if (!res.ok) {
    throw new ApiError(
      res.status,
      validationMessage(body) || `Lỗi máy chủ (${res.status}).`,
      body,
    )
  }

  return body as T
}

export type FormOption = {
  value: string
  label: string
  role?: string | null
}

export const DEFAULT_VOTE_OPTIONS: FormOption[] = [
  { value: 'agree', label: 'Đồng ý', role: 'agree' },
  { value: 'disagree', label: 'Không đồng ý', role: 'disagree' },
]

type AuthPayload = {
  token?: string
  access_token?: string
  data?: { token?: string }
  user?: {
    zalo_user_id?: string
    name?: string | null
    role?: string
    teacher_id?: number | null
  }
}

async function exchangeAuth(body: Record<string, unknown>): Promise<string> {
  const data = await api<AuthPayload>('/api/miniapp/v1/auth', {
    method: 'POST',
    body: JSON.stringify(body),
  })
  const token = data.token || data.access_token || data.data?.token
  if (!token) throw new ApiError(500, 'Auth response thiếu token.')
  setToken(token)
  return token
}

/** Exchange a real Zalo Mini App access token; register_as=teacher auto-provisions GV. */
export async function registerAsTeacher(accessToken: string) {
  return exchangeAuth({
    access_token: accessToken,
    register_as: 'teacher',
  })
}

/** Exchange Zalo access token as parent (no teacher provision). */
export async function registerAsParent(accessToken: string) {
  return exchangeAuth({
    access_token: accessToken,
    register_as: 'parent',
  })
}

/** Dev / mock teacher login for local browser testing. */
export async function mockTeacherLogin(zaloUserId: string) {
  return exchangeDevAuth(zaloUserId, 'teacher')
}

/** Dev / mock parent login when vote POST requires Sanctum. */
export async function mockParentLogin(zaloUserId: string) {
  return exchangeDevAuth(zaloUserId)
}

async function exchangeDevAuth(
  zaloUserId: string,
  registerAs?: 'teacher' | 'parent',
) {
  const id = zaloUserId.trim()
  const registerBody = registerAs ? { register_as: registerAs } : {}

  try {
    return await exchangeAuth({
      access_token: `dev:${id}`,
      ...registerBody,
    })
  } catch (err) {
    if (!(err instanceof ApiError) || err.status < 400) throw err
    return exchangeAuth({
      zalo_user_id: id,
      dev: true,
      ...registerBody,
    })
  }
}

/**
 * Try Zalo Mini App SDK getAccessToken when running inside Zalo.
 * Returns null when SDK is unavailable (browser / WAMP).
 * Uses runtime globals only — zmp-sdk is not a build dependency.
 */
export async function tryZaloAccessToken(): Promise<string | null> {
  const w = window as Window & {
    zmp?: { getAccessToken?: () => Promise<string> }
    ZaloSocialSDK?: { getAccessToken?: () => Promise<string> }
  }
  const getters = [w.zmp?.getAccessToken, w.ZaloSocialSDK?.getAccessToken]
  for (const getAccessToken of getters) {
    if (typeof getAccessToken !== 'function') continue
    try {
      const token = await getAccessToken()
      if (token?.trim()) return token.trim()
    } catch {
      /* try next */
    }
  }
  return null
}

export type ClassProfile = {
  id: number
  label?: string
  name?: string
  class_name?: string
  grade?: string
  quota?: number
  school?: { name?: string } | string | null
  school_name?: string
  ward?: string | null
  province?: string | null
  status?: string | null
}

export type CatalogItem = {
  id: number
  name: string
  code?: string | null
}

export type SchoolItem = {
  id: number
  name: string
  external_id?: string | null
}

export type ActiveForm = {
  id: number
  title?: string
  name?: string
  class_form_id?: number | null
  status?: string
  class_form?: {
    id?: number
    status?: string
    coverage?: number
    agree?: number
    disagree?: number
  } | null
}

export type ProfileDetail = ClassProfile & {
  forms?: ActiveForm[]
  active_forms?: ActiveForm[]
}

export type EnsureLinkResponse = {
  vote_url?: string
  url?: string
  invite_token?: string
  class_form_id?: number
  data?: { vote_url?: string; url?: string }
}

export type VoteChoice = string

export type CoverageStats = {
  agree?: number
  disagree?: number
  total?: number
  total_weight?: number
  coverage?: number
  coverage_percent?: number
  agree_weight?: number
  disagree_weight?: number
  remaining?: number
  remaining_color?: string
  choice_counts?: ChoiceCount[]
}

export type ChoiceCount = {
  value: string
  label: string
  count: number
  color?: string
}

export type VotePayload = {
  form?: {
    id?: number
    title?: string
    name?: string
    content?: string
    options?: FormOption[]
    options_json?: {
      choices?: FormOption[]
      preset?: string
    } | FormOption[] | null
  }
  options?: FormOption[]
  options_json?: { choices?: FormOption[] } | FormOption[] | null
  class?: { name?: string; school?: string; quota?: number }
  class_profile?: { label?: string; class_name?: string; school_name?: string }
  title?: string
  form_active?: boolean
  accepting_new?: boolean
  can_change?: boolean
  is_open?: boolean
  open?: boolean
  my_choice?: VoteChoice | null
  choice?: VoteChoice | null
  coverage?: number | CoverageStats
  stats?: CoverageStats
  message?: string
  status?: string
}

/** Normalize form options from vote payload; fall back to binary Đồng ý / Không. */
export function voteOptionsOf(payload: VotePayload | null | undefined): FormOption[] {
  if (!payload) return DEFAULT_VOTE_OPTIONS

  const candidates: unknown[] = [
    payload.options,
    payload.form?.options,
    payload.options_json,
    payload.form?.options_json,
  ]

  for (const raw of candidates) {
    const list = normalizeOptionList(raw)
    if (list.length > 0) return list
  }

  return DEFAULT_VOTE_OPTIONS
}

function normalizeOptionList(raw: unknown): FormOption[] {
  if (!raw) return []
  const choices = Array.isArray(raw)
    ? raw
    : typeof raw === 'object' &&
        Array.isArray((raw as { choices?: unknown }).choices)
      ? (raw as { choices: unknown[] }).choices
      : []

  const out: FormOption[] = []
  for (const item of choices) {
    if (!item || typeof item !== 'object') continue
    const o = item as Record<string, unknown>
    const value = String(o.value ?? '').trim()
    if (!value) continue
    const label = String(o.label ?? value).trim() || value
    const role =
      o.role != null
        ? String(o.role)
        : o.maps_to != null
          ? String(o.maps_to)
          : null
    out.push({ value, label, role })
  }
  return out
}

export function optionLabel(
  options: FormOption[],
  value: string | null | undefined,
): string {
  if (!value) return ''
  return options.find((o) => o.value === value)?.label || value
}

export function listProfiles() {
  return api<
    ClassProfile[] | { data: ClassProfile[]; profiles?: ClassProfile[] } | { profiles: ClassProfile[] }
  >('/api/miniapp/v1/teacher/profiles').then((r) => {
    if (Array.isArray(r)) return r
    if (r && typeof r === 'object' && Array.isArray((r as { profiles?: ClassProfile[] }).profiles)) {
      return (r as { profiles: ClassProfile[] }).profiles
    }
    if (r && typeof r === 'object' && Array.isArray((r as { data?: ClassProfile[] }).data)) {
      return (r as { data: ClassProfile[] }).data
    }
    return []
  })
}

export function listProvinces() {
  return api<{ provinces: CatalogItem[] }>(
    '/api/miniapp/v1/teacher/catalog/provinces',
  ).then((r) => r.provinces || [])
}

export function listWards(provinceId: string | number) {
  return api<{ wards: CatalogItem[] }>(
    `/api/miniapp/v1/teacher/catalog/provinces/${provinceId}/wards`,
  ).then((r) => r.wards || [])
}

export function listSchools(wardId: string | number, q = '') {
  const qs = q.trim() ? `?q=${encodeURIComponent(q.trim())}` : ''
  return api<{ schools: SchoolItem[] }>(
    `/api/miniapp/v1/teacher/catalog/wards/${wardId}/schools${qs}`,
  ).then((r) => r.schools || [])
}

export async function createProfile(input: {
  school_id: number
  class_name: string
  quota: number
}): Promise<ClassProfile> {
  const r = await api<{ profile: ClassProfile; message?: string }>(
    '/api/miniapp/v1/teacher/profiles',
    {
      method: 'POST',
      body: JSON.stringify(input),
    },
  )
  return r.profile
}

export type ProfileFormRow = {
  class_form_id: number | null
  form_id: number
  title: string
  status: string
  status_label: string
  coverage: number
  coverage_pct: number
  quota: number
  choice_counts: { value: string; label: string; count: number }[]
  choice_compact: string
  choice_tooltip: string
  has_template: boolean
}

export function getProfileForms(profileId: string | number, status = 'open') {
  const qs = `?status=${encodeURIComponent(status)}`
  return api<{
    profile: ClassProfile
    forms: ProfileFormRow[]
  }>(`/api/miniapp/v1/teacher/profiles/${profileId}${qs}`)
}

export type ClassFormDetail = {
  class_form: {
    id: number
    status: string
    status_label?: string
    form_active?: boolean
    can_edit_paper?: boolean
    form_id: number
    form_title?: string
    profile_id: number
    class_name: string
    school?: string | null
    quota: number
    has_template: boolean
    vote_url?: string | null
  }
  stats: {
    coverage: number
    coverage_pct: number
    total: number
    remaining?: number
    remaining_color?: string
    choice_counts: ChoiceCount[]
  }
  responses: {
    id: number
    stt?: number
    choice: string
    choice_label?: string
    channel: string
    phone?: string | null
    zalo_user_id?: string | null
    zalo_name?: string | null
    has_paper_image?: boolean
    paper_image_url?: string | null
    can_delete?: boolean
    created_at?: string | null
  }[]
  notes: {
    id: number
    body?: string | null
    file_name?: string | null
    has_file: boolean
    teacher_name?: string | null
    is_image?: boolean
    is_pdf?: boolean
    file_url?: string | null
    file_inline_url?: string | null
    can_delete?: boolean
    created_at?: string | null
  }[]
}

export function getClassFormDetail(id: string | number) {
  return api<ClassFormDetail>(`/api/miniapp/v1/teacher/class-forms/${id}`)
}

export function closeClassForm(id: string | number) {
  return api<{ status: string; message?: string }>(
    `/api/miniapp/v1/teacher/class-forms/${id}/close`,
    { method: 'POST' },
  )
}

export function reopenClassForm(id: string | number) {
  return api<{ status: string; message?: string }>(
    `/api/miniapp/v1/teacher/class-forms/${id}/reopen`,
    { method: 'POST' },
  )
}

export async function storeClassFormNote(
  id: string | number,
  body: string,
  files: File[] | File | null,
) {
  const fd = new FormData()
  if (body.trim()) fd.append('body', body.trim())
  const list = Array.isArray(files) ? files : files ? [files] : []
  for (const f of list) {
    fd.append('files[]', f)
  }

  const headers = new Headers()
  headers.set('Accept', 'application/json')
  const token = getToken()
  if (token) headers.set('Authorization', `Bearer ${token}`)

  const res = await fetch(
    `${API_BASE}/api/miniapp/v1/teacher/class-forms/${id}/notes`,
    { method: 'POST', headers, body: fd },
  )
  const text = await res.text()
  let parsed: unknown = null
  if (text) {
    try {
      parsed = JSON.parse(text)
    } catch {
      parsed = text
    }
  }
  if (!res.ok) {
    throw new ApiError(
      res.status,
      validationMessage(parsed) || `Lỗi máy chủ (${res.status}).`,
      parsed,
    )
  }
  return parsed as {
    note: ClassFormDetail['notes'][number] | null
    message?: string
    ocr?: { batch_id: number; images: number; confirm_url?: string } | null
  }
}

export type PaperBatchChoice = { value: string; label: string }

export type PaperBatchItem = {
  id: number
  status: string
  ocr_suggestion?: string | null
  ocr_suggestion_label: string
  ocr_confidence?: number | null
  ocr_confidence_pct?: number | null
}

export type PaperBatchDetail = {
  id: number
  status: string
  agree_count: number
  disagree_count: number
  unknown_count: number
  agree_label: string
  other_label: string
  unknown_label?: string
  choices: PaperBatchChoice[]
  items: PaperBatchItem[]
}

export function getPaperBatch(batchId: string | number) {
  return api<PaperBatchDetail>(`/api/miniapp/v1/teacher/paper-batches/${batchId}`)
}

export function fetchPaperItemImageUrl(itemId: string | number) {
  return fetchAuthBlobUrl(`/api/miniapp/v1/teacher/paper-items/${itemId}/image?inline=1`)
}

export function confirmPaperBatch(
  batchId: string | number,
  items: { id: number; choice: string }[],
) {
  return api<{ ok?: boolean; message?: string; confirmed?: number; skipped?: number }>(
    `/api/miniapp/v1/teacher/paper-batches/${batchId}/confirm`,
    { method: 'POST', body: JSON.stringify({ items }) },
  )
}

async function authBlobResponse(path: string): Promise<Response> {
  const headers = new Headers()
  headers.set('Accept', '*/*')
  const token = getToken()
  if (token) headers.set('Authorization', `Bearer ${token}`)

  const res = await fetch(`${API_BASE}${path}`, { headers })
  if (!res.ok) {
    let message = `Không tải được (${res.status}).`
    try {
      const j = await res.json()
      if (j?.message) message = j.message
    } catch {
      /* ignore */
    }
    throw new ApiError(res.status, message)
  }
  return res
}

async function downloadAuthBlob(path: string, filename: string) {
  const res = await authBlobResponse(path)
  const blob = await res.blob()
  const fromHeader = filenameFromContentDisposition(res.headers.get('Content-Disposition'))
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = fromHeader || filename
  a.click()
  URL.revokeObjectURL(url)
}

function filenameFromContentDisposition(header: string | null): string | null {
  if (!header) return null
  const utf = header.match(/filename\*=UTF-8''([^;]+)/i)
  if (utf?.[1]) {
    try {
      return decodeURIComponent(utf[1].trim().replace(/^"|"$/g, ''))
    } catch {
      /* ignore */
    }
  }
  const plain = header.match(/filename="?([^";]+)"?/i)
  return plain?.[1]?.trim() || null
}

/** Fetch authenticated blob and return an object URL (caller must revoke). */
export async function fetchAuthBlobUrl(path: string): Promise<{ url: string; mime: string }> {
  const res = await authBlobResponse(path)
  const blob = await res.blob()
  return {
    url: URL.createObjectURL(blob),
    mime: blob.type || res.headers.get('Content-Type') || '',
  }
}

export function downloadClassFormTemplate(
  classFormId: string | number,
  _title?: string,
) {
  return downloadAuthBlob(
    `/api/miniapp/v1/teacher/class-forms/${classFormId}/template`,
    'mau-phieu.pdf',
  )
}

export function downloadClassFormQr(classFormId: string | number) {
  return downloadAuthBlob(
    `/api/miniapp/v1/teacher/class-forms/${classFormId}/qr`,
    `vote-qr-${classFormId}.png`,
  )
}

/** Object URL for QR preview (Bearer). Caller revokes. */
export function fetchClassFormQrUrl(classFormId: string | number) {
  return fetchAuthBlobUrl(`/api/miniapp/v1/teacher/class-forms/${classFormId}/qr`)
}

export function downloadNoteFile(noteId: string | number, filename: string) {
  return downloadAuthBlob(
    `/api/miniapp/v1/teacher/class-form-notes/${noteId}/file`,
    filename,
  )
}

export function fetchNoteFileInlineUrl(noteId: string | number) {
  return fetchAuthBlobUrl(
    `/api/miniapp/v1/teacher/class-form-notes/${noteId}/file?inline=1`,
  )
}

export function fetchPaperImageUrl(responseId: string | number) {
  return fetchAuthBlobUrl(
    `/api/miniapp/v1/teacher/responses/${responseId}/paper?inline=1`,
  )
}

export function deleteClassFormNote(noteId: string | number) {
  return api<{ message?: string }>(
    `/api/miniapp/v1/teacher/class-form-notes/${noteId}`,
    { method: 'DELETE' },
  )
}

export function deletePaperResponse(responseId: string | number) {
  return api<{ message?: string }>(
    `/api/miniapp/v1/teacher/responses/${responseId}/paper`,
    { method: 'DELETE' },
  )
}

export function getProfile(id: string | number) {
  return api<
    | ProfileDetail
    | {
        profile?: ProfileDetail
        active_forms?: ActiveForm[]
        data?: ProfileDetail
      }
  >(`/api/miniapp/v1/teacher/profiles/${id}`).then((r) => {
    if (!r || typeof r !== 'object') return r as ProfileDetail
    if ('profile' in r && r.profile) {
      return {
        ...r.profile,
        active_forms: r.active_forms || r.profile.active_forms || [],
      }
    }
    if ('data' in r && r.data && typeof r.data === 'object' && 'id' in r.data) {
      return r.data
    }
    return r as ProfileDetail
  })
}

export async function ensureFormLink(
  profileId: string | number,
  formId: string | number,
) {
  const r = await api<EnsureLinkResponse>(
    `/api/miniapp/v1/teacher/profiles/${profileId}/forms/${formId}/ensure`,
    { method: 'POST' },
  )
  const voteUrl = r.vote_url || r.url || r.data?.vote_url || r.data?.url
  return { ...r, vote_url: voteUrl }
}

export function getVote(token: string) {
  return api<VotePayload>(`/api/miniapp/v1/vote/${encodeURIComponent(token)}`)
}

export function postVote(token: string, choice: VoteChoice) {
  return api<{ message?: string; updated?: boolean }>(
    `/api/miniapp/v1/vote/${encodeURIComponent(token)}`,
    {
      method: 'POST',
      body: JSON.stringify({ choice }),
    },
  )
}

export function schoolNameOf(p: ClassProfile): string {
  if (typeof p.school === 'string') return p.school
  return p.school?.name || p.school_name || ''
}

export function classNameOf(p: ClassProfile): string {
  return p.class_name || p.name || p.label || `Lớp #${p.id}`
}

export function profileTitle(p: ClassProfile): string {
  const school = schoolNameOf(p)
  const cls = classNameOf(p)
  return school ? `${school} · ${cls}` : cls
}

/** Pull invite token from pasted full vote URL or raw token. */
export function extractInviteToken(input: string): string {
  const t = input.trim()
  if (!t) return ''
  try {
    const u = new URL(t)
    const m = u.pathname.match(/\/vote\/([^/]+)/i)
    if (m?.[1]) return decodeURIComponent(m[1])
  } catch {
    /* not a full URL */
  }
  const m2 = t.match(/\/vote\/([^/?#]+)/i)
  if (m2?.[1]) return decodeURIComponent(m2[1])
  return t
}
