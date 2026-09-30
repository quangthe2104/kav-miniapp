# Zalo Mini App — Setup & redeploy (KavMiniApp)

> Runbook vận hành. Nghiệp vụ / pháp lý tổng: [khan-parent-consent-zalo-plan.md](./khan-parent-consent-zalo-plan.md) §19.  
> Progress: [03-phase-progress.md](./03-phase-progress.md).

| Mục | Giá trị đã chốt |
|-----|-----------------|
| Chủ sở hữu | **Khan Academy Vietnam** (doanh nghiệp VN có ĐKKD) |
| Loại hình sở hữu trên form Mini App | **Doanh nghiệp** |
| Không chọn | Cơ quan nhà nước / CQNN |
| OA nghiệp vụ chat form | Không dùng |
| OA nền tảng | Cần OA **doanh nghiệp** để verify + publish Live |
| Gói OA MVP | **Cơ bản** (nếu chỉ Mini App; nâng Tăng trưởng khi cần OpenAPI) |

---

## 1. Phân biệt ID (không nhầm)

| ID | Ý nghĩa | Điền vào |
|----|---------|----------|
| **Zalo App ID** | App cha trên Developers | `backend/.env` → `ZALO_APP_ID` |
| **Zalo App Secret** | Bí mật App cha | `ZALO_APP_SECRET` (password manager; **không** commit Git) |
| **Mini App ID** | ID Mini App vote/teacher | `ZALO_MINIAPP_ID` (backend tham chiếu) + config frontend / CLI deploy |
| **OA ID** | Official Account DN | `ZALO_OA_ID` (để trống đến khi có OA) |

Ghi lại khi tạo xong (nội bộ KAV):

```text
ZALO_APP_ID=717651661172288981
ZALO_APP_SECRET=          # chỉ password manager / backend/.env trên server
ZALO_MINIAPP_ID=2203119465038830853
ZALO_OA_ID=               # sau khi có OA
Owner account Zalo=
Created date=
```

---

## 2. Loại hình sở hữu — chọn gì?

Khi form hỏi loại hình sở hữu (thường 3 lựa chọn):

| Lựa chọn | Quyết định KavMiniApp |
|----------|------------------------|
| **Doanh nghiệp** | **Chọn** |
| Cá nhân | Không dùng cho Live lâu dài |
| Cơ quan nhà nước | **Cấm** |

**Xác thực Doanh nghiệp** (có thể sau bước tạo):

1. **Ưu tiên:** gắn OA doanh nghiệp KAV đã verify → Admin OA xác nhận liên kết  
2. Hoặc upload ĐKKD + CCCD người đại diện (nếu cổng cho phép)

Dev/Testing bằng QR với Admin / Developer / Người dùng thử nghiệm **không bắt buộc** đã Live;  
**Publish Live** yêu cầu xác thực chủ sở hữu xong.

---

## 3. Tạo mới từng bước (lần đầu / tái tạo)

### 3.1 Tài khoản

1. Zalo Admin kỹ thuật đã **eKYC**  
2. Có thể dùng Zalo cá nhân tạo App trước, sau gắn OA + thêm Admin KAV  
3. Đăng nhập [https://developers.zalo.me/](https://developers.zalo.me/)

### 3.2 Tạo Zalo App (App cha)

1. **Thêm ứng dụng mới**  
2. Điền:
   - Tên: `KavMiniApp` hoặc `KAV Parent Consent`
   - Danh mục: Giáo dục / tiện ích gần nhất
   - Mô tả ngắn: xem §5  
3. Lưu **App ID** + **App Secret**  
4. Cập nhật thông tin liên hệ → **Kích hoạt** app nếu có tùy chọn

### 3.3 Tạo Mini App

1. Trong App → **Tạo Mini App** (hoặc [https://mini.zalo.me/](https://mini.zalo.me/))  
2. **Loại hình sở hữu: Doanh nghiệp**  
3. Tên gợi ý: `KAV Form lớp`  
4. Icon / mô tả: §5  
5. Đồng ý điều khoản → tạo → lưu **Mini App ID**

### 3.4 Phân quyền test

| Vai trò | Ai |
|---------|-----|
| Admin | Acc tạo App + ≥1 người KAV |
| Developer | Dev team |
| Người dùng thử nghiệm | Cô / PH pilot |

### 3.5 Env backend

```env
ZALO_APP_ID=
ZALO_APP_SECRET=
ZALO_OA_ID=
ZALO_MINIAPP_ID=
ZALO_WEB_REDIRECT_URI="${APP_URL}/teacher/zalo/callback"
ZALO_DEV_LOGIN=true
# web = {APP_URL}/miniapp/vote/{token} · miniapp = https://zalo.me/s/{ZALO_MINIAPP_ID}/vote/{token}
ZALO_VOTE_LINK=web
# Chỉ khi test bản chưa Live: env=TESTING&version=<số phiên bản>
ZALO_MINIAPP_LINK_QUERY=
# SĐT phụ huynh hiển thị cho GV: full | masked (chỉ 3 số cuối)
ZALO_PARENT_PHONE_DISPLAY=full
# Mini App chạy trên domain Zalo → bắt buộc có 2 origin này
CORS_ALLOWED_ORIGINS=https://miniapp.kav.edu.vn,https://h5.zdn.vn,zbrowser://h5.zdn.vn
```

- `ZALO_APP_ID` + `ZALO_APP_SECRET`: backend đổi access token → hồ sơ Zalo (`graph.zalo.me/v2.0/me`, header `access_token` + `appsecret_proof`).  
- `ZALO_MINIAPP_ID`: dùng cho link vote dạng Mini App và `zmp deploy` (`miniapp/.env` → `APP_ID`).  
- **Teacher Zalo Login web:** Callback URL trên Developers phải khớp `ZALO_WEB_REDIRECT_URI` (prod HTTPS).  
- **Tên + SĐT phụ huynh:** khi bấm bình chọn, Mini App xin `scope.userInfo` + `scope.userPhonenumber` (một popup Zalo). Server xác minh `access_token` thuộc đúng phụ huynh, đổi token SĐT qua `graph.zalo.me/v2.0/me/info` (header `access_token`, `code`, `secret_key`). Từ chối vẫn gửi phiếu được. User thường cần quyền **Số điện thoại** được duyệt trên trang quản lý Mini App.  
- `ZALO_DEV_LOGIN=true` chỉ cho UAT; **tắt trước khi Live**.  
- Sau khi sửa `.env` trên server: `php artisan config:cache`.

### 3.6 Build & deploy Mini App (Testing — trước khi nộp duyệt)

Source `miniapp/` build 2 kiểu từ cùng code; `@platform` chọn `src/platform/web.ts` hoặc `src/platform/zalo.ts`:

| Build | Lệnh | Chạy ở | Khác biệt |
|-------|------|--------|-----------|
| Web | `npm run build:cpanel` | `https://miniapp.kav.edu.vn/miniapp/` | `localStorage`, router `/miniapp`, API same-origin |
| Zalo | `npm run build:zalo` | Zalo (`/zapps/{APP_ID}`) | `zmp-sdk` getAccessToken / `nativeStorage` / share sheet / `downloadFile`; router `window.BASE_PATH`; API tuyệt đối `https://miniapp.kav.edu.vn` |

**Cách A — chạy trên hosting cPanel** (đã có Node 22; SSH hoặc cPanel → Terminal):

```bash
cd ~/repositories/kav-miniapp
bash scripts/zalo-deploy.sh --token <ACCESS_TOKEN>   # lần đầu / khi phiên zmp hết hạn
bash scripts/zalo-deploy.sh --desc "mô tả"           # các lần sau
```

Access token: [developers.zalo.me](https://developers.zalo.me/) → **Công cụ → API Explorer** → chọn app **KAV Parent Consent** → **Lấy Access Token**. Script tự cài `zmp-cli` vào `~/zmp-tools`, build `dist-zalo`, deploy **Testing** (không hỏi tương tác).

**Cách B — máy dev** (Node 20+):

```bash
npm install -g zmp-cli
cd miniapp
npm install
# miniapp/.env phải có: APP_ID=2203119465038830853
zmp login            # quét QR bằng Zalo tài khoản Admin/Developer của Mini App
```

Mỗi lần cần test bản mới:

```bash
cd miniapp
npm run build:zalo   # → dist-zalo/ + app-config.json (listCSS/listSyncJS tự sinh)
zmp deploy           # chọn "Deploy your existing project", build folder: dist-zalo, status: Testing
```

1. Trên [mini.zalo.me](https://mini.zalo.me/) → Mini App → thêm tài khoản test vào **Admin / Developer / Người dùng thử nghiệm**  
2. Quét QR (hoặc mở link `https://zalo.me/s/2203119465038830853/?env=TESTING&version=<N>`) bằng tài khoản đã thêm  
3. Muốn link vote GV tạo ra mở thẳng bản Testing: trên server đặt `ZALO_VOTE_LINK=miniapp`, `ZALO_MINIAPP_LINK_QUERY=env=TESTING&version=<N>` → `config:cache`  
4. Debug trên máy thật: thêm `zDebug=true` vào query link test  
5. Khi Live: xóa `ZALO_MINIAPP_LINK_QUERY`, giữ `ZALO_VOTE_LINK=miniapp`, `ZALO_DEV_LOGIN=false`

> Link vote cũ dạng web vẫn mở được; nhưng khi `ZALO_DEV_LOGIN=false`, phụ huynh chỉ gửi phiếu được **trong Mini App** (cần Zalo access token).

---

## 4. Khi có OA doanh nghiệp KAV

1. Không tạo App/Mini App mới (trừ khi Zalo bắt buộc và có quyết định chuyển ID)  
2. Trên Mini App hiện tại: xác thực chủ sở hữu bằng **OA DN**  
3. Admin OA **xác nhận** request  
4. Điền `ZALO_OA_ID` vào `.env`  
5. Thêm Admin KAV trên App nếu chưa có  
6. Xin quyền API cần thiết (user id; SĐT nếu dùng)  
7. Nộp duyệt **Live** (thường 3–5 ngày làm việc)

---

## 5. Copy mô tả điền form (VI)

**Tên Mini App:** `KAV Form lớp`

**Mô tả ngắn:**

> Ứng dụng hỗ trợ giáo viên thu thập bình chọn của phụ huynh theo từng lớp và từng biểu mẫu. Phụ huynh mở link lớp để bình chọn trên Zalo; giáo viên theo dõi tiến độ và bổ sung phiếu giấy khi cần.

**Mô tả đầy đủ:**

> KavMiniApp là Mini App của Khan Academy Vietnam giúp nhà trường và giáo viên chủ nhiệm tổ chức thu thập ý kiến phụ huynh theo lớp.
>
> Giáo viên tạo hồ sơ lớp một lần, chọn biểu mẫu đang mở và gửi link vào nhóm Zalo lớp. Phụ huynh đăng nhập Zalo để bình chọn (hai lựa chọn hoặc danh sách tùy chỉnh); có thể đổi ý trước khi biểu mẫu đóng. Giáo viên theo dõi số phiếu theo sĩ số, xem kết quả (không chỉnh sửa phiếu phụ huynh), ghi chú, và tải lên phiếu giấy để bổ sung.
>
> Mục đích: hỗ trợ vận hành thu thập ý kiến phụ huynh minh bạch, nhanh và đúng lớp — không thay thế quy trình pháp lý của cơ quan nhà nước.

**Mục đích xử lý dữ liệu (nếu hỏi):**

> Định danh người dùng Zalo (và số điện thoại nếu được cấp quyền) để ghi nhận phiếu theo lớp/biểu mẫu, chống vote trùng, phục vụ thống kê nội bộ trường/tổ chức vận hành chương trình.

Ghi rõ vận hành bởi **Khan Academy Vietnam (doanh nghiệp)** — không mô tả như app CQNN.

---

## 6. Checklist redeploy / kiểm tra lại

Khi deploy lại hoặc onboard người mới:

- [ ] Đúng **App ID** / **Mini App ID** (không lẫn)  
- [ ] App Secret khớp App ID (không lấy token từ app khác trên Developers)  
- [ ] Loại hình sở hữu = **Doanh nghiệp**; trạng thái verify OA/DN  
- [ ] List Admin / Developer / Tester còn đủ  
- [ ] `.env` production: `ZALO_*` đúng; `ZALO_DEV_LOGIN=false` trên prod  
- [ ] Domain HTTPS backend khớp cấu hình Login / whitelist domain  
- [ ] Deploy Mini App đúng môi trường (Testing vs Live)  
- [ ] QR / deep link vote trỏ đúng Mini App version đang Live  
- [ ] Audit: không commit Secret vào Git  

---

## 7. Lỗi thường gặp

| Hiện tượng | Kiểm tra |
|------------|----------|
| QR Development không mở | Acc có trong Admin/Developer/Tester? |
| Mở Mini App trắng trang | `app-config.json` khớp file trong `dist-zalo/assets`? build lại `npm run build:zalo` rồi deploy |
| «Network error» khi gọi API | CORS thiếu `https://h5.zdn.vn` / `zbrowser://h5.zdn.vn`; API phải HTTPS |
| «Không lấy được phiên đăng nhập Zalo» | `getAccessToken` lỗi — đóng/mở lại Mini App; kiểm tra acc test |
| Auth 422 «Zalo từ chối access token» | Sai `ZALO_APP_SECRET` (appsecret_proof) hoặc Mini App không thuộc `ZALO_APP_ID` |
| Tải QR / mẫu phiếu lỗi với PH/GV thường | `downloadFile` cần xin quyền API trên trang quản lý Mini App (Admin/Dev không bị) |
| Deploy / token lỗi | Nhầm Mini App ID ↔ App ID; sai Secret |
| API SĐT fail trên user thường | Tester được bypass; production cần quyền đã duyệt |
| Publish bị từ chối | Sai loại hình CQNN; thiếu OA/DN; mô tả mục đích mơ hồ |

---

## 8. Liên kết

- Portal: [developers.zalo.me](https://developers.zalo.me/) · [mini.zalo.me](https://mini.zalo.me/)  
- Plan §19: [khan-parent-consent-zalo-plan.md](./khan-parent-consent-zalo-plan.md)  
- Env mẫu kiến trúc: [02-technical-architecture.md](./02-technical-architecture.md)  
- Agents index: [../AGENTS.md](../AGENTS.md)
