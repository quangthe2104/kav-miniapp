# Phase 1 — Chi tiết triển khai MVP (as-built)

Source of truth coding Phase 1. Nghiệp vụ: [khan-parent-consent-zalo-plan.md](./khan-parent-consent-zalo-plan.md). Kiến trúc: [02-technical-architecture.md](./02-technical-architecture.md). Board: [03-phase-progress.md](./03-phase-progress.md).

## Mục tiêu

Pilot thu thập bình chọn theo lớp: Admin tạo Form → GV tạo Profile + lấy link → PH bình chọn Zalo → giấy multi-upload OCR confirm → dashboard/export.

## Stack

- Laravel **13** · PHP ≥ 8.3 · MySQL
- Blade Teacher/Admin web (brand `#0a2a66` / `#14bf96`)
- React/Vite Mini App (`base: /miniapp/`)
- Queue `database` · Sanctum · Zalo Login
- Local: `http://miniapp.kav/` (không trộn với `php artisan serve` khi Mini App đã build vào `public/miniapp`)

## Ngoài phạm vi Phase 1

- Roster Excel HS, vote theo HS, phiếu in mã ID → **BACKLOG** (không chặn scale)
- OpenAPI broadcast OA
- Form builder đa loại câu hỏi
- Phân quyền Sở/Phòng/Trường → **không làm**
- Whitelist GV (đã **bỏ** — GV tự đăng ký Zalo)
- Hardening production / scale 25K→200K→20M → **Phase 2–4** (xem [00-overview-phases.md](./00-overview-phases.md))

**Vận hành:** khuyến nghị **1 Form `active` / thời điểm** (enforce kỹ thuật từ Phase 2).

---

## Đã triển khai (as-built)

### Admin

- Login email/password; quản lý tài khoản admin (`/admin/users`)
- CRUD Form: title, content, status, `starts_at` / `ends_at` / **không kết thúc**, PDF mẫu, **tùy chọn kết quả** (`options_json`)
- Calibrate OCR trên Form (`ocr_layout_json`)
- Dashboard KPI/chart **scoped theo Form** + lọc địa lý + bảng lớp
- Danh sách trường + import Excel
- Giáo viên: xem + **khóa** (không whitelist trước)
- Export CSV; audit ngôn ngữ tự nhiên
- Đóng Form → cascade đóng `class_forms`; mở lại Form → cascade reopen

### Giáo viên (web + Mini App)

- Zalo OAuth + Dev login (`ZALO_DEV_LOGIN`); lần đầu tự tạo `teachers`
- Tạo Class Profile (tỉnh / xã / trường / tên lớp / sĩ số)
- Danh sách **form đang mở** (`Form::activeNow()`); lớp chỉ có **1 form đang mở** → vào thẳng chi tiết (`?list=1` để xem list); nút **Xem chi tiết form**
- 1 lớp: **không** hiện bộ lọc lớp
- Bấm tên form chưa có `class_form` → `ensure` rồi vào chi tiết
- Link cố định `/miniapp/vote/{token}`; Share Zalo; Copy link; **Tải QR** (Mini App không hiện ảnh QR trên màn; web vẫn hiện QR)
- Thống kê: Mini App = stacked bar (như PH); web = KPI + doughnut
- Ghi chú nhiều bản + file; phiếu giấy OCR confirm (web popup + Mini App modal)
- Xóa ghi chú / xóa phiếu giấy do chính GV tải (khi form còn mở)
- Đóng / mở lại bình chọn lớp (không được nếu Admin đã đóng Form)

### Phụ huynh (Mini App)

- GET `/vote/{token}` redirect → `/miniapp/vote/{token}`
- Form nhị phân (Đồng ý/Không hoặc Có/Không): 2 nút dock
- Form **tùy chỉnh**: radio list **Bình chọn của bạn** dưới thống kê + 1 nút **Bình chọn** (chưa chọn thì cuộn tới list)
- Đổi ý khi form còn mở; chặn phiếu mới khi `quota_full`; chặn hết khi đóng

### OCR giấy

- Ưu tiên layout Form (ô map `option_value`); fallback 2 vùng `config/ocr.php`
- Job `ProcessPaperOcrBatchJob`; confirm bắt buộc
- KPI confirm: Đồng ý / Không đồng ý (hoặc label Form) / **Không rõ**

### Coverage

- `SUM(coverage_weight)` `status=valid`; Phase 1 mỗi phiếu **weight = 1**
- Không dùng `parent_coverage_notes` cho weight
- Unique Zalo: `(class_form_id, zalo_user_id)`

---

## Còn lại trước pilot thật

| Hạng mục | Ghi chú |
|----------|---------|
| UAT 2 Form song song | Tester — [06-role-based-test-guide.md](./06-role-based-test-guide.md) |
| Pre-deploy security | `APP_DEBUG=false`, HTTPS, không log secret |
| Domain HTTPS + Mini App Live | KAV OA doanh nghiệp — [04-zalo-miniapp-setup.md](./04-zalo-miniapp-setup.md) |
| UX lỗi / failed jobs monitoring | Queue OCR production |
| Pilot 20–50 trường | Sau Live |

---

## Checklist bảo mật trước pilot

- [ ] `APP_DEBUG=false` trên production
- [ ] HTTPS only
- [ ] Policy Teacher isolation (đã có `created_by_teacher_id`)
- [ ] Private disk ảnh giấy
- [ ] Không log Zalo secret / token
- [ ] Rate limit vote + auth
- [ ] CSRF web; Sanctum Mini App
- [ ] Mini App `VITE_API_BASE_URL` trùng `APP_URL` (WAMP: `http://miniapp.kav`)

---

## Verify scenarios (tester)

1. Admin tạo Form A (nhị phân) + Form B (tùy chỉnh nhiều lựa chọn) → cả hai hiện trên lớp cũ
2. GV Zalo/dev tạo Profile → bấm tên form (kể cả chưa có link) → vào chi tiết
3. PH bình chọn → thấy trong list phiếu GV
4. Cùng Zalo gửi lại → đổi ý (không tạo phiếu thứ 2)
5. Form tùy chỉnh: radio + nút Bình chọn; nội dung dài → bấm nút chưa chọn thì cuộn tới list
6. Multi-upload giấy → tóm tắt → confirm
7. Coverage = N → khóa phiếu mới
8. Teacher khác 403 khi mở `class_form` không phải của mình
9. Admin đóng Form → PH không vote; GV không Share/upload; Admin mở lại → GV `ensure`/mở được
10. Mini App build trỏ đúng `miniapp.kav` (không 403 do gọi `:8000`/SQLite)
