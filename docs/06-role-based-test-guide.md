# Hướng dẫn test theo vai trò — KavMiniApp

UAT / manual smoke Phase 1. **Môi trường chuẩn local:** WAMP domain `miniapp.kav` (MySQL).

| Cập nhật | 2026-08-14 |
|----------|------------|
| Progress | `docs/03-phase-progress.md` |
| Playbook GV | [05-teacher-playbook.md](./05-teacher-playbook.md) |

---

## 0. Chuẩn bị

DocumentRoot Apache: `backend/public`. Mini App:

```bat
cd miniapp
npm run build:wamp
```

`.env` backend: `APP_URL=http://miniapp.kav` · `ZALO_DEV_LOGIN=true` · MySQL.  
`.env` miniapp: `VITE_API_BASE_URL=http://miniapp.kav` (rồi build lại).

| Kênh | URL |
|------|-----|
| Web GV | `http://miniapp.kav/` |
| Admin | `http://miniapp.kav/admin` |
| Mini App | `http://miniapp.kav/miniapp/` |

**Không** vừa mở Mini App trên `miniapp.kav` vừa để API trỏ `127.0.0.1:8000` (SQLite / 403).

Vite `npm run dev` chỉ khi dev HMR — khi đó `VITE_API_BASE_URL` phải trùng backend đang chạy.

| Vai trò | Định danh |
|---------|-----------|
| Admin | `admin@kav.local` / `password` |
| GV demo | SĐT `0900000001` · Zalo ID `dev-teacher-1` |

---

## 1. Admin (web)

- [ ] Login → dashboard **theo Form** + lọc + bảng lớp
- [ ] Forms: tạo Form nhị phân **và** Form tùy chỉnh (>2 lựa chọn, không bị chặn max 5)
- [ ] Đóng Form → class_form lớp đóng; mở lại Form → GV vào được
- [ ] Trường + import Excel; Giáo viên xem/khóa (không màn whitelist)
- [ ] Audit tiếng Việt; Export CSV

---

## 2. Giáo viên — Web

Đăng nhập: nút **Đăng nhập với Zalo** tại `/teacher/login` (cần Callback URL khai báo trên developers.zalo.me). Trang web không còn form dev login; muốn test không qua Zalo thì dùng bản `/miniapp` (build `VITE_ENABLE_DEV_LOGIN=true`) khi `ZALO_DEV_LOGIN=true`.

- [ ] 1 lớp: **không** thanh chọn lớp
- [ ] Lớp chỉ có 1 form đang mở: vào thẳng chi tiết (kể cả GV nhiều lớp); `?list=1` xem list + nút **Xem chi tiết form**
- [ ] Form mới: bấm **tên form** → vào chi tiết (không 403)
- [ ] Copy link / Share Zalo / Tải QR / (web) thấy QR
- [ ] Ghi chú + upload giấy + OCR confirm
- [ ] Bảng phiếu chỉ xem; đóng / mở lại vote
- [ ] Teacher khác không mở được class-form của mình (403 + thông báo)

---

## 3. Giáo viên — Mini App

`http://miniapp.kav/miniapp/` → Giáo viên → mock `dev-teacher-1`.

- [ ] List form đang mở; bấm tên Form khảo sát / form 2 → chi tiết 200
- [ ] Thống kê stacked bar; **không** ảnh QR; có nút Tải QR
- [ ] OCR modal trong app

---

## 4. Phụ huynh

Link: `http://miniapp.kav/miniapp/vote/{token}`.

**Form nhị phân**

- [ ] 2 nút dock; vote `parent-a`; đổi ý; `parent-b`; đủ sĩ số thì chặn phiếu mới

**Form tùy chỉnh**

- [ ] Radio **Bình chọn của bạn** dưới thống kê
- [ ] Nút **Bình chọn** chữ trắng + icon
- [ ] Chưa chọn → bấm nút cuộn tới list; đã chọn → submit

- [ ] GV đóng vote → PH không gửi/đổi ý
- [ ] Admin đóng Form → PH không vote dù CF từng open

---

## 5. Ma trận pass

| Vai trò | Pass khi… |
|---------|-----------|
| Admin | CRUD Form (kể cả custom nhiều choice), dashboard, export, cascade đóng/mở |
| GV web | Tạo lớp, mở form 2 không 403, link/QR, ghi chú/giấy, không sửa phiếu PH |
| GV Mini App | Cùng API host, list + chi tiết, stacked bar, Tải QR |
| PH | Nhị phân 2 nút; custom radio + Bình chọn; đổi ý; chặn quota/đóng |

---

## 6. Lỗi thường gặp

| Hiện tượng | Cách xử lý |
|------------|------------|
| Mini App 403 / `personal_access_tokens` SQLite | Build với `VITE_API_BASE_URL=http://miniapp.kav`; hard refresh |
| Teacher mock fail | `ZALO_DEV_LOGIN=true`; dùng `dev-teacher-1` |
| Vote 404 | Mở form từ dashboard (ensure) |
| Zalo OAuth local | Dev login; callback HTTPS khi Live |

---

## 7. Liên kết

- Playbook: [05-teacher-playbook.md](./05-teacher-playbook.md)
- Zalo: [04-zalo-miniapp-setup.md](./04-zalo-miniapp-setup.md)
- Phase 1: [01-phase1-detail.md](./01-phase1-detail.md)
- Kiến trúc: [02-technical-architecture.md](./02-technical-architecture.md)
