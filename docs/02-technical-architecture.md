# Kiến trúc kỹ thuật — KavMiniApp

| Mục | Giá trị |
|-----|---------|
| Version | 1.2 (roadmap scale 2027–2029; 1 Form active) |
| Stack backend | **Laravel 13** · PHP ≥ 8.3 · MySQL 8+ |
| Frontend web | Blade · brand KAV (navy `#0a2a66`, teal `#14bf96`, Montserrat) |
| Mini App | React · Vite · `base: /miniapp/` · Zalo Mini App JS SDK |
| Tài liệu sản phẩm | [khan-parent-consent-zalo-plan.md](./khan-parent-consent-zalo-plan.md) |

---

## 1. Mục tiêu kiến trúc

Monolith Laravel phục vụ:

1. **Admin web** — Form, trường, dashboard theo Form, khóa GV, export, audit
2. **Teacher web** — Profile lớp, link Form, giấy OCR, thống kê (Zalo Login)
3. **API JSON** — Mini App (Teacher + Parent) + OCR jobs
4. **Storage** — ảnh phiếu giấy + file ghi chú (private disk)

Không microservices ở Phase 1–4 gần.

---

## 2. Sơ đồ tổng thể

```text
┌─────────────────────┐     ┌──────────────────────┐
│ Zalo Mini App       │     │ Web Laravel          │
│ /miniapp/ (React)   │     │ Admin: password      │
│ Parent + Teacher    │     │ Teacher: Zalo Login  │
└──────────┬──────────┘     └──────────┬───────────┘
           │ HTTPS JSON / session      │
           └────────────┬──────────────┘
                        ▼
              ┌───────────────────┐
              │ Laravel 13        │
              │ web + /api/miniapp│
              └─────────┬─────────┘
           ┌────────────┼────────────┐
           ▼            ▼            ▼
        MySQL      Private disk   Queue (database)
                   (paper/notes)  OCR jobs
```

Local WAMP: `APP_URL=http://miniapp.kav` · DocumentRoot `backend/public`.  
Mini App production build: `VITE_API_BASE_URL=http://miniapp.kav` rồi `npm run build:wamp`. **Không** trộn build `/miniapp` với `php artisan serve` (SQLite / host khác → 401/403).

---

## 3. Yêu cầu môi trường

| Thành phần | Yêu cầu |
|------------|---------|
| PHP | **≥ 8.3** |
| Laravel | **13.x** |
| MySQL | 8.0+ |
| Node | 20+ |
| ext | pdo_mysql, openssl, mbstring, fileinfo, gd |
| HTTPS | Bắt buộc khi Zalo Login / Mini App Live |

---

## 4. Cấu trúc thư mục

```text
kavminiapp/
├── docs/
├── .cursor/agents|rules|skills
├── backend/                       # Laravel 13
│   ├── app/Http/Controllers/{Admin,Teacher,Api/Miniapp}
│   ├── app/Jobs/ProcessPaperOcrBatchJob.php
│   ├── app/Services/              # Coverage, ClassFormLink, TeacherProvision,
│   │                              # AuditLog, FormDashboard, SchoolImport, Ocr/*
│   ├── public/miniapp/            # bản build copy từ miniapp/dist
│   └── routes/{web,api,console}.php
└── miniapp/                       # Vite React
```

---

## 5. Phân quyền & xác thực

| Role | Cách vào | Quyền |
|------|----------|--------|
| `admin` | Session, `users.is_admin` + `status=active` | Toàn hệ thống |
| `teacher` | Zalo OAuth web / Mini App Sanctum; **tự tạo** `teachers` theo `zalo_id` | Chỉ lớp `created_by_teacher_id` |
| `parent` | Mini App Sanctum + invite token | Submit/đổi ý theo token |

Không whitelist trước. Admin **khóa** GV (`teachers.status`) → revoke token Mini App.

| Kênh | Cơ chế |
|------|--------|
| Admin / Teacher web | Session + CSRF |
| Mini App API | Sanctum Bearer |
| Vote GET meta | Public + throttle; POST cần parent auth |

**Teacher web OAuth:** `/teacher/zalo/redirect` · `/teacher/zalo/callback`  
**Mini App auth:** `POST /api/miniapp/v1/auth` (Zalo access token hoặc mock `zalo_user_id` khi `ZALO_DEV_LOGIN`)

---

## 6. Mô hình dữ liệu (Phase 1)

```text
provinces 1──* wards 1──* schools 1──* class_profiles
forms 1──* class_forms *──1 class_profiles
class_forms 1──* responses
class_forms 1──* class_form_notes
class_forms 1──* paper_upload_batches 1──* paper_upload_items
users (admin)
teachers
audit_logs
```

`parent_coverage_notes` có thể còn trong DB (scaffold cũ) — **không dùng** cho coverage Phase 1.

### forms

| Cột | Ghi chú |
|-----|---------|
| title, content | Hiển thị PH |
| starts_at, ends_at, no_end_date | `isActive()` / `scopeActiveNow()` |
| status | draft / active / closed |
| consent_pdf_path | PDF mẫu giấy |
| options_json | `{ preset, choices[{value,label}], agree_values[] }` — custom không giới hạn số choice (min 2) |
| ocr_layout_json | zone + boxes map `option_value` + `confirmed_at` |
| scope_json | (dự phòng lọc tỉnh/trường) |

Preset: `agree_disagree` · `yes_no` · `custom`.

### class_forms

| Cột | Ghi chú |
|-----|---------|
| class_profile_id + form_id | UNIQUE |
| invite_token + invite_token_hash | Token plaintext lưu để copy/QR; hash để tra cứu |
| status | open / quota_full / closed |
| teacher_note | cột legacy; ghi chú vận hành dùng bảng `class_form_notes` |

`effectiveStatus()` / `isVoteOpen()` phụ thuộc Form `isActive()`.  
Admin đóng Form → `closeLinkedClassForms()`. Admin mở lại → `reopenLinkedClassForms()`.  
`ClassFormLinkService::ensure()` tạo CF hoặc **mở lại** CF `closed` nếu Form đang active (không đổi token).

### responses

| Cột | Ghi chú |
|-----|---------|
| choice | VARCHAR — value trong `options_json` (không chỉ agree/disagree) |
| channel | zalo \| paper |
| coverage_weight | Phase 1 = 1 |
| zalo_user_id | unique với class_form khi zalo + valid |
| image_path | phiếu giấy |
| ocr_suggestion / ocr_confidence | |
| status | valid \| void |

### Coverage (atomic)

```text
coverage = SUM(coverage_weight) WHERE class_form_id=? AND status=valid
INSERT chỉ khi coverage + weight <= quota
quota_full khi coverage >= quota
Parent UPDATE choice khi class_form chưa closed và Form isActive (kể cả quota_full)
```

Service: `CoverageService`.

---

## 7. API Mini App (`/api/miniapp/v1`)

### Auth & vote

| Method | Path | Ghi chú |
|--------|------|---------|
| POST | `/auth` | Zalo / mock → Sanctum |
| GET | `/me` | Bearer |
| GET | `/vote/{token}` | Meta form + stats (public, throttle) |
| POST | `/vote/{token}` | `{ choice }` — parent auth |

Web: `GET /vote/{token}` **redirect** `/miniapp/vote/{token}`.

### Teacher (Bearer + `MiniAppUser.role=teacher`)

| Method | Path |
|--------|------|
| GET | `/teacher/catalog/provinces` · `.../wards` · `.../schools` |
| GET/POST | `/teacher/profiles` |
| GET | `/teacher/profiles/{profile}?status=open\|closed\|all` |
| POST | `/teacher/profiles/{profile}/forms/{form}/ensure` |
| GET | `/teacher/class-forms/{id}` |
| POST | `/teacher/class-forms/{id}/close` · `/reopen` |
| POST | `/teacher/class-forms/{id}/notes` |
| GET | `/teacher/class-forms/{id}/template` · `/qr` |
| GET/DELETE | `/teacher/class-form-notes/{id}` · `.../file` |
| GET/DELETE | `/teacher/responses/{id}/paper` |
| GET | `/teacher/paper-batches/{id}` · `/teacher/paper-items/{id}/image` |
| POST | `/teacher/paper-batches/{id}/confirm` |
| GET | `/teacher/class-forms/{id}/responses` |

Web Teacher dùng cùng service (`/teacher/...` session).

### Admin (session)

`/admin` dashboard Form-scoped · `/admin/forms` · OCR detect/preview · `/admin/schools` + import · `/admin/teachers` · `/admin/users` · `/admin/audit` · export CSV.

---

## 8. Mini App (React)

Màn: Home (vai trò) · Teacher login/list/create/class-form · Parent vote · nhập mã.

- Deep link: `http://miniapp.kav/miniapp/vote/{token}`
- Share Zalo: Mini App `openShareSheet`; web `zalo.me/share` (`kavShareZalo`)
- Teacher class-form: stacked bar; không preview QR; nút Tải QR
- Form tùy chỉnh PH: radio + nút Bình chọn; form nhị phân: 2 nút dock
- OCR confirm in-app (`OcrConfirmModal`)
- Asset: `import.meta.env.BASE_URL`; logo `logo-kav.svg`

---

## 9. OCR phiếu giấy

1. Admin: PDF mẫu → detect ô → map choice → `ocr_layout_json`
2. Runtime: `PaperCheckboxOcrService::analyzeForForm` (layout Form hoặc fallback `config/ocr.php`)
3. Job `ProcessPaperOcrBatchJob`; confirm `choice` ∈ allowed ∪ skip
4. Không OCR tên HS / mã phiếu (roster = backlog)

---

## 10. Bảo mật

| Rủi ro | Kiểm soát |
|--------|-----------|
| Leak invite | Token dài; rate limit vote |
| Teacher xem lớp khác | So `created_by_teacher_id` (403) |
| PII SĐT | Chỉ GV đúng lớp + admin; HTTPS |
| Upload | MIME jpeg/png/webp, max 5MB, private disk |
| GV giả danh | Provision chỉ theo Zalo id đã verify; không bind phone từ client |
| Auto-create lạm dụng | 10 / IP / giờ |
| GV bị khóa | Revoke Sanctum tokens |
| Vượt sĩ số | Transaction + lock |
| OCR sai | Confirm bắt buộc |
| API sai host | Mini App cùng origin `APP_URL` |

Secrets chỉ `.env`: `ZALO_APP_ID`, `ZALO_APP_SECRET`, `ZALO_OA_ID`, `APP_KEY`.

---

## 11. ADR

### ADR-001: Monolith Laravel 13 + MySQL

WAMP, team nhỏ, MVP. Không Postgres trừ khi cần sau.

### ADR-002: Sanctum Mini App, Session Web

Teacher web Zalo OAuth tạo session; Mini App Bearer.

### ADR-003: Coverage weight=1, không roster Phase 1

Không `students` / `paper_slips`. Ghi chú vận hành = `class_form_notes` (nhiều bản + file), không weight.

### ADR-004: OCR gợi ý + confirm, multi-upload

Không auto-commit OCR.

### ADR-005: Lazy ClassForm

Tạo khi `ensure`; Form mới tự hiện trên Profile.

### ADR-006: Teacher self-provision, không whitelist

`TeacherProvisionService` theo `zalo_id`; Admin disable.

### ADR-007: `options_json` + `no_end_date`

Vote/OCR/export theo choices Form; Form cũ = binary mặc định.

### ADR-008: Dashboard Admin theo Form

`FormDashboardService`; KPI/chart/bảng scoped `form_id` + geo.

### ADR-009: Cascade Form ↔ class_forms

Đóng Form admin → đóng CF. Mở lại Form → reopen CF đã cascade. `ensure` reopen CF closed nếu Form active.

### ADR-010: Scale path 2027–2029, không Sở–Phòng

- Vận hành **1 Form `active` / thời điểm** (enforce từ Phase 2).
- Mốc năm = lúc **đạt tới** quy mô; hạ tầng phải chuẩn bị **xong trước 1 năm**.
- Phase 2 xong **hết 2026**: hardening → sẵn sàng ~25K lớp (đạt tới 2027).
- Phase 3 xong **hết 2027**: scale mid → sẵn sàng ~200K lớp / ~10M PH (đạt tới 2028).
- Phase 4 xong **hết 2028**: full capacity → sẵn sàng ~20M PH (đạt tới 2029).
- Không phân quyền Sở/Phòng/Trường. Roster HS = backlog, không đổi ADR-003 cho đến khi kick-off.

---

## 12. Biến môi trường chính

```env
APP_URL=http://miniapp.kav
APP_TIMEZONE=Asia/Ho_Chi_Minh
DB_CONNECTION=mysql
ZALO_DEV_LOGIN=true
ZALO_APP_ID=
ZALO_APP_SECRET=
ZALO_OA_ID=
ZALO_MINIAPP_ID=
ZALO_WEB_REDIRECT_URI="${APP_URL}/teacher/zalo/callback"
QUEUE_CONNECTION=database
```

Mini App: `VITE_API_BASE_URL=http://miniapp.kav` · `VITE_ENABLE_DEV_LOGIN=true` (build WAMP).

---

## 13. Liên kết

- Sản phẩm: [khan-parent-consent-zalo-plan.md](./khan-parent-consent-zalo-plan.md)
- Phase overview: [00-overview-phases.md](./00-overview-phases.md)
- Phase 1: [01-phase1-detail.md](./01-phase1-detail.md)
- Progress: [03-phase-progress.md](./03-phase-progress.md)
- Mini App setup: [04-zalo-miniapp-setup.md](./04-zalo-miniapp-setup.md)
- Playbook GV: [05-teacher-playbook.md](./05-teacher-playbook.md)
- Test theo vai trò: [06-role-based-test-guide.md](./06-role-based-test-guide.md)
