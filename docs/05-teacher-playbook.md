# Playbook giáo viên — KavMiniApp

Hướng dẫn vận hành nhanh cho cô chủ nhiệm (Phase 1).

## 1. Đăng nhập

1. Mở **http://miniapp.kav/** (web) hoặc Mini App → **Tôi là giáo viên**
2. **Production:** **Đăng nhập với Zalo** — lần đầu hệ thống tự tạo tài khoản, **không cần Admin thêm trước**.
3. **Local/dev:** Dev login khi `ZALO_DEV_LOGIN=true`.

Không có mật khẩu riêng. Admin chỉ **khóa** tài khoản nếu lạm dụng.

## 2. Tạo Profile lớp (một lần)

1. **Tạo lớp** (thanh nav)
2. Tỉnh → Xã/Phường → Trường → tên lớp + **sĩ số**
3. Lưu — dùng lại cho mọi Form sau này

Nếu chỉ **một lớp**: không hiện thanh chọn lớp.

## 3. Form đang mở & link

1. Nếu **1 lớp × 1 form đang mở**: vào thẳng trang chi tiết. Nút **Dashboard / Danh sách form** (`?list=1`) để xem list.
2. Nhiều form: danh sách **Form đang mở** — bấm **tên form** (kể cả form mới chưa có link) để vào chi tiết.
3. **Share Zalo** / **Copy link** / **Tải QR**. Mini App không hiện ảnh QR trên màn hình.
4. **Tải mẫu phiếu** = PDF để in (nếu Admin đã upload).

Link phiếu: `/miniapp/vote/{mã}`. Link cũ `/vote/{mã}` tự chuyển Mini App. Đóng/mở lại **không đổi** mã link.

## 4. Phụ huynh bình chọn

- PH mở link → Mini App. Form Đồng ý/Không: 2 nút. Form nhiều lựa chọn: **Bình chọn của bạn** (radio) + nút **Bình chọn**.
- PH **được đổi ý** khi Form còn mở và lớp chưa đóng vote.
- Đủ sĩ số: không nhận phiếu mới; vẫn cho đổi ý (trừ khi đã đóng).

## 5. Ghi chú & phiếu giấy

- Nhập ghi chú và/hoặc đính kèm ảnh/PDF trên trang chi tiết form.
- Ảnh phiếu → OCR ngay (web popup / Mini App modal) → đối chiếu → **Xác nhận**. Mỗi ảnh confirmed = 1 phiếu (không vượt sĩ số).
- Khi form còn mở: xóa ghi chú của mình; xóa phiếu giấy do mình tải. **Không** sửa phiếu Zalo của PH.

## 6. Đóng / mở lại vote lớp

- **Đóng bình chọn** → PH không gửi / đổi ý.
- **Mở lại** nếu Form admin vẫn active.
- Admin đã đóng Form: form biến khỏi «đang mở»; GV không mở lại được cho đến khi Admin mở Form.

## 7. Việc không được làm

- Không đổi phiếu PH
- Không chia sẻ App Secret / token nội bộ

## 8. Khi gặp lỗi

| Hiện tượng | Cách xử lý |
|------------|------------|
| Không đăng nhập Zalo | Báo Admin kiểm tra cấu hình Zalo / tài khoản bị khóa |
| 403 khi mở form | Đăng nhập đúng GV của lớp; Mini App phải gọi API cùng host (`miniapp.kav`, không `:8000`) |
| Link 404 | Mở lại form từ danh sách (ensure) |
| Coverage chưa đủ | Ghi chú + phiếu giấy |
| Upload lỗi | jpeg/png/webp ≤ 5MB |

## 9. Liên hệ

- Admin KAV quản lý Form + khóa GV
- Zalo setup: `docs/04-zalo-miniapp-setup.md`
