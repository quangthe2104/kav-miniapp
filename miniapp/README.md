# KavMiniApp — Zalo Mini App UI

React UI cho Form/Vote phụ huynh (Phase 1). Serve production qua Laravel `backend/public/miniapp/`.

## Stack

- Vite + React + TypeScript · `react-router-dom`
- API Laravel Sanctum Bearer

## Local WAMP (khuyến nghị)

```bat
cd miniapp
copy .env.example .env
```

`.env`: `VITE_API_BASE_URL=http://miniapp.kav` · `VITE_ENABLE_DEV_LOGIN=true`

```bat
npm install
npm run build:wamp
```

Mở `http://miniapp.kav/miniapp/`. Backend MySQL + `APP_URL=http://miniapp.kav`.

## Dev HMR (tuỳ chọn)

```bat
npm run dev
```

`VITE_API_BASE_URL` phải trùng backend đang chạy. **Không** build WAMP với URL `:8000` rồi mở `miniapp.kav`.

## Env

| Biến | Mô tả |
|------|--------|
| `VITE_API_BASE_URL` | Origin API (WAMP: `http://miniapp.kav`) |
| `VITE_ENABLE_DEV_LOGIN` | Mock login trình duyệt |
| `VITE_ZALO_MINIAPP_ID` | Optional zmp-cli |

## Màn hình

| Path | |
|------|--|
| `/` | Chọn vai trò |
| `/teacher/login` | Zalo / mock |
| `/teacher` | Form đang mở theo lớp |
| `/teacher/profiles/create` | Tạo lớp |
| `/teacher/class-forms/:id` | Chi tiết form lớp |
| `/vote/:token` | Phiếu phụ huynh |
| `/parent` | Nhập mã |

## Zalo Live

Xem `docs/04-zalo-miniapp-setup.md`. Local không cần Zalo app nếu dùng mock auth.
