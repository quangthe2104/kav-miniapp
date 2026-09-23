# Tổng quan các Phase — KavMiniApp

| Mục | Giá trị |
|-----|---------|
| Sản phẩm | Thu thập **bình chọn** phụ huynh theo **Class Profile × Form** (Zalo Mini App + Laravel) |
| Chủ sở hữu | Khan Academy Vietnam |
| Mô hình vận hành | **1 Form `active` tại một thời điểm**; scale hệ thống hiện tại (vote theo lớp × Zalo) |
| Tài liệu sản phẩm | [khan-parent-consent-zalo-plan.md](./khan-parent-consent-zalo-plan.md) |
| Kiến trúc kỹ thuật | [02-technical-architecture.md](./02-technical-architecture.md) |
| Chi tiết Phase 1 | [01-phase1-detail.md](./01-phase1-detail.md) |
| Theo dõi tiến độ | [03-phase-progress.md](./03-phase-progress.md) |
| Local (WAMP) | `http://miniapp.kav/` (GV) · `/admin` · `/miniapp` |

## Mục tiêu quy mô (neo lịch)

> **Mốc năm = lúc hệ thống ĐẠT TỚI quy mô đó.** Việc chuẩn bị (hardening/scale) phải **hoàn tất trước 1 năm**.

| Mốc ĐẠT TỚI | Lớp (ước) | PH (≈ 50/lớp) | Phase phải xong TRƯỚC đó |
|-------------|-----------|---------------|--------------------------|
| **Hết 2027** | ~**25.000** | ~**1,25 triệu** | Phase 2 xong **hết 2026** |
| **Hết 2028** | ~**200.000** | ~**10 triệu** | Phase 3 xong **hết 2027** |
| **2029** | (~400K lớp nếu ~50 PH) | ~**20 triệu** | Phase 4 xong **hết 2028** |

Không có phân quyền Sở / Phòng / Trường trên đường critical path.  
Roster HS + phiếu mã ID = **backlog** (chỉ làm khi nghiệp vụ yêu cầu gắn từng học sinh).

## Thứ tự Phase

| Phase | Tên | Chuẩn bị xong | Mốc đạt tới | Trạng thái | Ghi chú |
|-------|-----|---------------|-------------|------------|---------|
| **0** | OA / Mini App Live / HTTPS | 2026 | — | Đang chờ KAV | Catalog Thanh Hóa + PDF mẫu đã có |
| **1** | MVP Pilot | 2026 | — | Đang làm (~90% code) | Web + Mini App + OCR; còn UAT + Live |
| **2** | Hardening production | **hết 2026** | ~25K lớp (2027) | Chờ | Redis, S3, workers, LB, monitoring |
| **3** | Scale mid | **hết 2027** | ~200K lớp / ~10M PH (2028) | Chờ | Replica, stats pre-agg, CDN |
| **4** | Full capacity | **hết 2028** | ~20M PH (2029) | Chờ | Capacity peak, DR |

```text
Chuẩn bị:  Phase 0–1 ──► Phase 2 ──► Phase 3 ──► Phase 4
            2026         hết 2026    hết 2027    hết 2028
Đạt tới:                 25K(2027)   200K(2028)  20M(2029)
```

## Quy tắc sản phẩm (scale)

1. **Một Form đang mở (`active`) tại một thời điểm** — Form khác `draft` / `closed`. Hệ thống có thể enforce (cảnh báo hoặc chặn) từ Phase 2.
2. Schema Phase 1 giữ nguyên: vote theo Zalo / lớp; giấy multi-upload không mã in.
3. Scale = hạ tầng + vận hành campaign, **không** thêm tầng Sở–Phòng.

## Ranh giới MVP (Phase 1) — đúng hệ thống hiện tại

**Có**

- Nhiều Form trong hệ thống; vận hành khuyến nghị **1 Form active** mỗi đợt
- Class Profile; lazy `class_form` khi GV mở form / lấy link (`ensure`)
- GV: Mini App + Web Laravel, **Zalo Login** (lần đầu tự tạo `teachers`; Admin khóa nếu cần)
- Admin: email/password; dashboard **theo từng Form**; import trường Excel
- PH: Mini App `/miniapp/vote/{token}`; unique 1 Zalo / class_form; đổi ý khi form còn mở
- Giấy: multi-upload, OCR checkbox theo layout Form, cô xác nhận (không mã in)
- Dashboard + list phiếu + export CSV; audit tiếng Việt
- Admin đóng Form → cascade đóng `class_forms`; mở lại → cascade reopen; `ensure` tự mở CF đã đóng nếu Form còn active

**Không (Phase 1 và không trên critical path tới 20M)**

- Excel danh sách HS / vote theo học sinh / phiếu in có mã → **BACKLOG**
- Phân quyền Sở / Phòng / Trường → **không làm**
- OpenAPI gửi tin OA hàng loạt; Form builder đa câu hỏi → backlog tuỳ nhu cầu

## Workflow agent (bắt buộc)

```text
project-manager  →  system-architect  →  security (nếu đụng auth/PII/upload/OCR/public API)
                                      →  developer  →  tester
```

- Scope Phase 1: `01-phase1-detail.md` + product plan
- Architecture: `02-technical-architecture.md`
- Cập nhật Status / checkbox trong `03-phase-progress.md` khi xong việc

## Stack chốt

| Thành phần | Công nghệ |
|------------|-----------|
| Backend | **Laravel 13** (PHP ≥ 8.3), MySQL |
| Admin + Teacher web | Blade trên Laravel (brand KAV) |
| Mini App | React + Vite + Zalo Mini App SDK; `base: /miniapp/` |
| OCR | GD heuristic + `forms.ocr_layout_json`; queue job |
| Hosting dev | WAMP, vhost `miniapp.kav` → `backend/public` |
| Production (Phase 2+) | LB multi-app, Redis, object storage, queue workers |
