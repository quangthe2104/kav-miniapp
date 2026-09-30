# Theo dõi tiến độ các Phase — KavMiniApp

> **Living document.** Tick checkbox `[x]` + cập nhật cột Status khi hoàn thành.  
> Owner cập nhật chính: `project-manager` (sau khi developer/tester xong).  
> Roadmap quy mô: [00-overview-phases.md](./00-overview-phases.md).

| Cập nhật lần cuối | 2026-08-14 (chuẩn bị lùi trước mốc đạt tới 1 năm) |
|-------------------|------------|
| Phase đang focus | **Phase 1** — còn UAT + OA Live (Phase 0) |
| % Phase 1 (MVP) | **~90%** |
| Mốc tiếp theo | Đóng Phase 0–1 → **Phase 2 xong hết 2026** để đạt ~25K lớp năm 2027 |

> **Quy ước:** mốc năm = lúc **đạt tới** quy mô; phần chuẩn bị phải **xong trước 1 năm**.

## Chú thích Status

| Status | Ý nghĩa |
|--------|---------|
| `todo` | Chưa làm |
| `doing` | Đang làm |
| `blocked` | Block — ghi lý do ở bảng Blockers |
| `done` | Xong + đã verify cơ bản |
| `skipped` | Cố ý bỏ / chuyển phase khác |
| `superseded` | Thay thế bởi task mới (ghi ID) |
| `extended` | Done cơ bản; mở rộng bởi task mới |
| `cancelled` | Không còn trong roadmap |

---

## Tổng quan Phase (tick nhanh)

| Phase | Tên | Chuẩn bị xong | Đạt tới | Status | % | Tick |
|-------|-----|---------------|---------|--------|---|------|
| 0 | OA / Live / HTTPS | 2026 | — | doing | 45% | [ ] |
| 1 | MVP Pilot | 2026 | — | doing | 90% | [~] code xong; UAT/Live còn |
| 2 | Hardening + rollout | **hết 2026** | ~25K lớp / ~1,25M PH (2027) | todo | 0% | [ ] |
| 3 | Scale mid | **hết 2027** | ~200K lớp / ~10M PH (2028) | todo | 0% | [ ] |
| 4 | Full capacity | **hết 2028** | ~20M PH (2029) | todo | 0% | [ ] |

```text
Bạn đang ở đây ──► Phase 0 (~45%) + Phase 1 (~90% code, UAT production + Mini App Testing)
Sau pilot Live ──► Phase 2 (hardening 2027)
```

**Ngoài critical path**

| Hạng mục | Trạng thái |
|----------|------------|
| Phân quyền Sở / Phòng / Trường | `cancelled` — không làm |
| Roster HS + phiếu mã ID | `backlog` — chỉ khi nghiệp vụ yêu cầu |
| Soft launch cũ (reminder / reviewer / form đa câu) | `backlog` tuỳ chọn, không chặn scale |

---

## Blockers

| ID | Mô tả | Phase | Owner | Since |
|----|-------|-------|-------|-------|
| B1 | Chưa có Zalo OA — chưa verify chủ sở hữu / publish Live Mini App | 0/1 | KAV | 2026-08-11 |

---

## Phase 0 — Chuẩn bị (2026)

| Done | ID | Task | Status | Owner hint |
|------|----|------|--------|------------|
| [ ] | P0-01 | Tạo Zalo OA doanh nghiệp KAV + xác thực ĐKKD | todo | KAV |
| [~] | P0-02 | Tạo Mini App trên Developers, gắn OA | doing | Mini App ID `2203119465038830853` đã có; chờ OA |
| [ ] | P0-03 | Cấu hình quyền Mini App (user / SĐT nếu có) | todo | Dev |
| [x] | P0-04 | Domain HTTPS cho API/web | done | `https://miniapp.kav.edu.vn` (cPanel + Let's Encrypt, Cloudflare) |
| [x] | P0-05 | Chọn tỉnh pilot + file CSV trường | done | Thanh Hóa + Excel CSGD đã seed |
| [x] | P0-06 | Mẫu PDF phiếu giấy Form (checkbox cố định) | done | Admin upload PDF trên Form; GV tải PDF |
| [ ] | P0-07 | Quyết định gói OA (Cơ bản MVP) | todo | KAV |

**% Phase 0:** 45% (3/7 + P0-02 dở)

---

## Phase 1 — MVP Pilot (2026)

### W1 — Bootstrap & Admin

| Done | ID | Task | Status | Owner hint |
|------|----|------|--------|------------|
| [x] | P1-W1-01 | Tạo repo `backend` Laravel 13 | done | developer |
| [x] | P1-W1-02 | MySQL + migrations danh mục + forms + teachers + … | done | developer |
| [x] | P1-W1-03 | Admin login email/password | done | Breeze |
| [x] | P1-W1-04 | Admin CRUD Form + upload PDF mẫu | done | developer |
| [x] | P1-W1-05 | Import provinces/wards/schools (Thanh Hóa) | done | 166 xã, 674 trường, `external_id` |
| [~] | P1-W1-06 | Whitelist teachers | superseded | → **P1-ADM-03** GV tự đăng ký Zalo |
| [ ] | P1-W1-07 | Security review auth admin | todo | security |
| [ ] | P1-W1-08 | Test W1 acceptance (UAT formal) | doing | manual smoke |

**% W1:** 85%

### W2 — Profile, link, vote

| Done | ID | Task | Status | Owner hint |
|------|----|------|--------|------------|
| [x] | P1-W2-01 | Migrations class_profiles, class_forms, responses | done | developer |
| [x] | P1-W2-02 | Zalo Mini App auth + Sanctum | done | `POST /api/miniapp/v1/auth` + Bearer |
| [x] | P1-W2-03 | Teacher Zalo Login web OAuth | done | OAuth + Dev login local |
| [x] | P1-W2-04 | Class Profile API/Web + Policy | done | Web + API teacher profiles |
| [x] | P1-W2-05 | Ensure ClassForm + token/link/QR | done | `/miniapp/vote/{token}` + Share Zalo |
| [x] | P1-W2-06 | Parent vote + unique + CoverageService | done | Vote web + API Mini App |
| [x] | P1-W2-07 | Mini App UI Teacher + Parent | done | `miniapp/` Vite React |
| [x] | P1-W2-08 | Security: invite token, rate limit vote | done | hash token + throttle |
| [ ] | P1-W2-09 | Test W2 acceptance | todo | tester |

**% W2:** 85%

### W3 — Notes + Paper OCR batch

| Done | ID | Task | Status | Owner hint |
|------|----|------|--------|------------|
| [x] | P1-W3-01 | Ghi chú form lớp + cấm GV sửa phiếu | done | `class_form_notes` |
| [x] | P1-W3-02 | paper_upload_batches/items + multi-upload | done | developer |
| [x] | P1-W3-03 | PaperCheckboxOcrService + Job | done | GD + `ocr_layout_json` / config |
| [x] | P1-W3-04 | UI tóm tắt + confirm | done | web popup + Mini App modal |
| [x] | P1-W3-05 | Đóng class_form | done | GV đóng → PH không đổi ý |
| [x] | P1-W3-06 | Security: upload MIME/size/private disk | done | max 5MB |
| [ ] | P1-W3-07 | Test W3 acceptance | todo | tester |

**% W3:** 85%

### W4 — Dashboard & export

| Done | ID | Task | Status | Owner hint |
|------|----|------|--------|------------|
| [~] | P1-W4-01 | Dashboard đa cấp Admin | superseded | → **P1-ADM-02** theo Form |
| [x] | P1-W4-02 | Teacher stats + list phiếu | done | read-only |
| [x] | P1-W4-03 | Export CSV | done | chunk 500 |
| [x] | P1-W4-04 | Audit logs | extended | ngôn ngữ tự nhiên VN |
| [x] | P1-W4-05 | Rate limits | done | vote/login/upload/confirm |
| [ ] | P1-W4-06 | Test W4 acceptance | todo | tester |

**% W4:** 80%

### W5 — Hardening & UAT (Phase 1)

| Done | ID | Task | Status | Owner hint |
|------|----|------|--------|------------|
| [x] | P1-W5-01 | Đóng Form theo thời hạn | done | `forms:close-expired` |
| [ ] | P1-W5-02 | UX lỗi + failed jobs monitoring | todo | developer |
| [x] | P1-W5-03 | Playbook GV | done | `docs/05-teacher-playbook.md` |
| [ ] | P1-W5-04 | UAT theo [06-role-based-test-guide.md](./06-role-based-test-guide.md) | todo | tester |
| [ ] | P1-W5-05 | Pre-deploy security checklist | todo | security |

**% W5:** 35%

### W6 — Pilot thật

| Done | ID | Task | Status | Owner hint |
|------|----|------|--------|------------|
| [x] | P1-W6-01 | Deploy production HTTPS | done | cPanel Git deploy (`.cpanel.yml`); UAT `ZALO_DEV_LOGIN=true` |
| [~] | P1-W6-02 | Mini App submit duyệt / testing | doing | Testing **v1** đã deploy từ hosting (`scripts/zalo-deploy.sh`); UAT link vote = Mini App `env=TESTING&version=1`; chờ test thật → nộp duyệt |
| [ ] | P1-W6-03 | Chạy pilot (1 Form active) | todo | KAV |
| [ ] | P1-W6-04 | Hotfix từ feedback | todo | developer |
| [~] | P1-W6-05 | Báo cáo go/no-go Phase 4 vs 2 | superseded | → go/no-go **Phase 2** hardening |

**% W6:** 30%

**% Phase 1 tổng:** ~90% (code); còn UAT + Live + pilot

---

### Phase 1 — Admin / Form / Mini App (đã gộp)

ADR: [02-technical-architecture.md](./02-technical-architecture.md) §11.

| Done | ID | Task | Status | Owner hint |
|------|----|------|--------|------------|
| [x] | P1-ADM-00 | ADR + chốt D1–D7 | done | architect + PM |
| [x] | P1-ADM-01 | UI `/admin/login` | done | developer |
| [x] | P1-ADM-02 | Dashboard theo Form + lọc + bảng lớp | done | developer |
| [x] | P1-ADM-03 | Bỏ whitelist; Teacher self-service Zalo | done | developer + security |
| [x] | P1-ADM-04 | Danh sách Trường + import Excel | done | developer |
| [x] | P1-ADM-05 | Form CRUD (options custom, no end, template) | done | developer |
| [x] | P1-ADM-06 | Quản lý tài khoản admin | done | developer + security |
| [x] | P1-ADM-07 | Cascade đóng/mở class_forms | done | developer |
| [x] | P1-ADM-08 | Audit logs ngôn ngữ tự nhiên | done | developer |
| [x] | P1-ADM-09 | UI Giáo viên web | done | developer |
| [x] | P1-ADM-10 | UI Mini App | done | stacked bar, radio custom, ensure |
| [ ] | P1-ADM-11 | Test acceptance toàn bộ | todo | tester |
| [x] | P1-ADM-12 | Playbook + test guide + kiến trúc as-built | done | PM |

### P1-OCR — Calibrate OCR theo Form

| Done | ID | Task | Status | Owner hint |
|------|----|------|--------|------------|
| [x] | P1-OCR-00 | Schema `ocr_layout_json` + helpers | done | architect + developer |
| [x] | P1-OCR-01 | Admin UI zone + detect + confirm map | done | developer |
| [x] | P1-OCR-02 | Multi-box OCR runtime + Job | done | developer |
| [x] | P1-OCR-03 | Teacher paper confirm theo Form choices | done | developer |
| [x] | P1-OCR-04 | Security admin-only / private / throttle | done | security |
| [ ] | P1-OCR-05 | Test acceptance OCR calibrate | todo | tester |

**% Admin / OCR gói:** ~95% (còn UAT)

---

## Phase 2 — Hardening production (chuẩn bị xong **hết 2026**)

**Mục tiêu:** sẵn sàng cho ~**25.000 lớp** / ~**1,25 triệu** PH (đạt tới trong **2027**); 1 Form active / đợt.

| Done | ID | Task | Status |
|------|----|------|--------|
| [ ] | P2-01 | ADR + capacity plan 25K lớp / peak RPS | todo |
| [ ] | P2-02 | Redis (cache, session, rate limit) | todo |
| [ ] | P2-03 | Object storage (S3) + CDN ảnh phiếu | todo |
| [ ] | P2-04 | Queue Redis/SQS + workers OCR / export tách app | todo |
| [ ] | P2-05 | Coverage counter (atomic) — giảm `SUM()` mỗi vote | todo |
| [ ] | P2-06 | Multi-app + load balancer | todo |
| [ ] | P2-07 | Backup/restore đã luyện; monitoring APM + alert | todo |
| [ ] | P2-08 | Export CSV async (job + download link) | todo |
| [ ] | P2-09 | Enforce **1 Form `active`** tại một thời điểm | todo |
| [ ] | P2-10 | Throttle theo user/token (không chỉ IP) | todo |
| [ ] | P2-11 | Import trường theo đợt mở rộng | todo |
| [ ] | P2-12 | Load test & go/no-go mở rộng tới 25K lớp | todo |

**% Phase 2:** 0%

**Cổng ra Phase 3:** Hạ tầng chịu được ~25K lớp (load test đạt); không mất dữ liệu khi failover giả định — **xong hết 2026** trước khi lượng lớp tăng trong 2027.

---

## Phase 3 — Scale mid (chuẩn bị xong **hết 2027**)

**Mục tiêu:** sẵn sàng cho ~**200.000 lớp** / ~**10 triệu** PH (đạt tới trong **2028**).

| Done | ID | Task | Status |
|------|----|------|--------|
| [ ] | P3-01 | MySQL read replica (dashboard / export đọc replica) | todo |
| [ ] | P3-02 | Stats materialize / cache theo `class_form` + Form đang mở | todo |
| [ ] | P3-03 | CDN Mini App + static assets | todo |
| [ ] | P3-04 | Partition / archive audit & ảnh phiếu cũ | todo |
| [ ] | P3-05 | Load test kịch bản mở Form toàn đợt (~200K lớp) | todo |
| [ ] | P3-06 | Runbook campaign (giờ mở Form, hotline, cascade) | todo |
| [ ] | P3-07 | Connection pool / app autoscaling theo peak | todo |

**% Phase 3:** 0%

**Cổng ra Phase 4:** Hạ tầng chịu ~200K lớp / ~10M PH (load test đạt) — **xong hết 2027** trước mốc 2028.

---

## Phase 4 — Full capacity (chuẩn bị xong **hết 2028**)

**Mục tiêu:** sẵn sàng cho ~**20 triệu** phụ huynh (~400K lớp nếu ~50 PH/lớp) — đạt tới trong **2029**.

| Done | ID | Task | Status |
|------|----|------|--------|
| [ ] | P4-01 | Capacity plan peak gấp đôi Phase 3 | todo |
| [ ] | P4-02 | DR multi-AZ / failover có runbook | todo |
| [ ] | P4-03 | Tối ưu Zalo auth / session cache ở burst | todo |
| [ ] | P4-04 | (Tuỳ) partition/`responses` theo năm hoặc `form_id` | todo |
| [ ] | P4-05 | Pre-aggregate theo trường/tỉnh cho Form đang chạy | todo |
| [ ] | P4-06 | Load test + go-live toàn quốc | todo |

**% Phase 4:** 0%

---

## BACKLOG (không chặn scale)

| Done | ID | Task | Status |
|------|----|------|--------|
| [ ] | BL-01 | Roster Excel HS + vote theo học sinh | backlog |
| [ ] | BL-02 | In phiếu + `paper_code` + OCR mã ID | backlog |
| [ ] | BL-03 | Reminder coverage thiếu | backlog |
| [ ] | BL-04 | Reviewer phiếu giấy tập trung | backlog |
| [ ] | BL-05 | Form builder đa câu hỏi | backlog |
| [ ] | BL-06 | OA broadcast / OpenAPI tin hàng loạt | backlog |
| [~] | BL-07 | Viewer Sở / Phòng / Trường | cancelled |

Chi tiết roster (nếu kick-off): [khan-parent-consent-zalo-plan.md](./khan-parent-consent-zalo-plan.md) §18.

---

## Nhật ký cập nhật

| Ngày | Ghi chú |
|------|---------|
| 2026-08-11 | Khởi tạo board; mọi task `todo` |
| 2026-08-11 | Scaffold Laravel 13.24; seed Thanh Hóa 166 xã + 674 trường (`external_id`); admin/teacher/vote/consent/paper batch web; mẫu phiếu có ô tick Đồng ý/Không đồng ý |
| 2026-08-11 | Rule: GV không sửa phiếu; 1 `teacher_note`/class_form khi thiếu coverage; PH đổi ý khi form mở. Admin dashboard Chart.js + export CSV. Placeholder `ZALO_APP_ID` / secret / OA trong `.env` |
| 2026-08-11 | Brand: `#0a2a66` / `#14bf96`, logo KAV, Montserrat. AuditLogService + throttle login/upload |
| 2026-08-11 | Docs: loại hình sở hữu Mini App = **Doanh nghiệp**; runbook `docs/04-zalo-miniapp-setup.md` |
| 2026-08-11 | API `/api/miniapp/v1/*` + Sanctum; Teacher Zalo OAuth web; scaffold `miniapp/`; `forms:close-expired` |
| 2026-08-11 | OCR checkbox heuristic GD + `config/ocr.php`; Admin `/admin/audit`; playbook `docs/05-teacher-playbook.md` |
| 2026-08-12 | Thêm `docs/06-role-based-test-guide.md` (UAT Admin / GV / PH — web + Mini App) |
| 2026-08-12 | **Kickoff Admin Redesign Sprint** — dashboard theo Form, bỏ whitelist GV, Danh sách Trường + import Excel, Form options_json, audit VN, UI GV web + Mini App. Implemented P1-ADM-01…10; security hardening WP3. Phase 1 ~85%. |
| 2026-08-13 | **P1-OCR:** `forms.ocr_layout_json` + Admin detect/calibrate; multi-choice paper OCR + confirm; fallback `config/ocr.php`. |
| 2026-08-13 | Bỏ mẫu HTML `/consent-form`; chỉ còn PDF upload trên Form. |
| 2026-08-13 | GV UX: xóa ghi chú + xóa phiếu giấy; cột STT; **Xem phiếu** / OCR popup. |
| 2026-08-13 | `APP_TIMEZONE=Asia/Ho_Chi_Minh`; OCR layout Form; pad box; fallback config. |
| 2026-08-13 | Fix OCR false positive / dấu X mỏng. |
| 2026-08-13 | Mini App parity; link `/miniapp/vote/{token}`; Share Zalo; 1 lớp×1 form → thẳng chi tiết; OCR confirm Mini App. |
| 2026-08-14 | Fix Mini App API base `miniapp.kav`; cascade reopen Form; docs as-built; xóa sprint tạm 07/08. Phase 1 ~90%. |
| 2026-08-14 | **Roadmap mới:** Phase 2 = hardening → 25K lớp (hết 2027); Phase 3 = 200K lớp / ~10M PH (hết 2028); Phase 4 = ~20M PH (2029). Bỏ Sở–Phòng. Roster → backlog. 1 Form active / thời điểm. Progress board thêm cột checkbox. |
| 2026-08-14 | **Lùi chuẩn bị trước mốc 1 năm:** mốc năm = lúc ĐẠT TỚI; Phase 2 xong hết 2026, Phase 3 xong hết 2027, Phase 4 xong hết 2028. |
| 2026-09-30 | Deploy UAT `https://miniapp.kav.edu.vn` (cPanel, AutoSSL); redirect `/backend/public/*` → URL chuẩn. |
| 2026-09-30 | **Mini App thật:** `zmp-sdk` (getAccessToken, nativeStorage, share, downloadFile) qua `@platform` web/zalo; `npm run build:zalo` → `dist-zalo` + `app-config.json`; backend `appsecret_proof`; `ZALO_VOTE_LINK=miniapp` → `zalo.me/s/{id}/vote/{token}`; CORS `h5.zdn.vn`. |
| 2026-09-30 | Build + `zmp deploy` chạy trên hosting cPanel (`scripts/zalo-deploy.sh`) → Mini App **Testing v1**; server `ZALO_VOTE_LINK=miniapp`, `ZALO_MINIAPP_LINK_QUERY=env=TESTING&version=1`. |
