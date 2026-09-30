# KavMiniApp

Nền tảng thu thập **bình chọn** phụ huynh theo lớp (Zalo Mini App + Laravel) — Khan Academy Vietnam.

| Mục | Giá trị |
|-----|---------|
| Repo | [https://github.com/quangthe2104/kav-miniapp.git](https://github.com/quangthe2104/kav-miniapp.git) |
| Stack | Laravel **13** · PHP ≥ 8.3 · MySQL · React/Vite Mini App |
| Vận hành | **1 Form `active` / thời điểm** · không phân quyền Sở–Phòng |
| Local | `http://miniapp.kav` → DocumentRoot `backend/public` |

---

## Mục lục

1. [Tài liệu](#tài-liệu)
2. [Cấu trúc repo](#cấu-trúc-repo)
3. [Chạy local (WAMP)](#chạy-local-wamp)
4. [Deploy production](#deploy-production)
5. [Push lên GitHub](#push-lên-github)
6. [Trạng thái / roadmap](#trạng-thái--roadmap)

---

## Tài liệu

| File | Nội dung |
|------|----------|
| [docs/00-overview-phases.md](docs/00-overview-phases.md) | Roadmap phase + quy mô 2027–2029 |
| [docs/01-phase1-detail.md](docs/01-phase1-detail.md) | Phase 1 as-built |
| [docs/02-technical-architecture.md](docs/02-technical-architecture.md) | Kiến trúc + ADR |
| [docs/03-phase-progress.md](docs/03-phase-progress.md) | Board tiến độ (checkbox) |
| [docs/04-zalo-miniapp-setup.md](docs/04-zalo-miniapp-setup.md) | OA / Mini App Live |
| [docs/05-teacher-playbook.md](docs/05-teacher-playbook.md) | Playbook GV |
| [docs/06-role-based-test-guide.md](docs/06-role-based-test-guide.md) | UAT theo vai trò |
| [docs/khan-parent-consent-zalo-plan.md](docs/khan-parent-consent-zalo-plan.md) | Plan sản phẩm |
| [AGENTS.md](AGENTS.md) | Hướng dẫn agent |

---

## Cấu trúc repo

```text
kavminiapp/
├── backend/          # Laravel 13 (API + Admin/Teacher Blade)
│   └── public/       # DocumentRoot web + bản build Mini App (/miniapp)
├── miniapp/          # React + Vite (source Mini App)
├── docs/             # Tài liệu kỹ thuật & sản phẩm
└── README.md
```

---

## Chạy local (WAMP)

### Yêu cầu

- PHP ≥ 8.3 (pdo_mysql, openssl, mbstring, fileinfo, gd)
- Composer 2 · Node 20+ · MySQL 8+
- Apache vhost trỏ DocumentRoot vào `backend/public`

### 1. Backend

```bat
cd backend
copy .env.example .env
composer install
php artisan key:generate
```

Sửa `backend/.env` (khuyến nghị MySQL, không dùng SQLite khi test Mini App qua WAMP):

```env
APP_URL=http://miniapp.kav
APP_TIMEZONE=Asia/Ho_Chi_Minh
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kavminiapp
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database
ZALO_DEV_LOGIN=true
```

```bat
php artisan migrate --seed
php artisan storage:link
```

### 2. Mini App

```bat
cd miniapp
copy .env.example .env
```

`miniapp/.env`:

```env
VITE_API_BASE_URL=http://miniapp.kav
VITE_ENABLE_DEV_LOGIN=true
```

```bat
npm install
npm run build:wamp
```

Lệnh `build:wamp` build production rồi copy vào `backend/public/miniapp/`.

### 3. Apache vhost

DocumentRoot = `D:/wamp64/www/kavminiapp/backend/public` · ServerName `miniapp.kav` · hosts: `127.0.0.1 miniapp.kav` · **Restart Apache**.

| URL | Vai trò |
|-----|---------|
| http://miniapp.kav/ | Giáo viên (web) |
| http://miniapp.kav/admin | Admin |
| http://miniapp.kav/miniapp | Mini App |

**Demo seed:** Admin `admin@kav.local` / `password` · GV Dev `0900000001` hoặc `dev-teacher-1` (khi `ZALO_DEV_LOGIN=true`).

> **Không** trộn build Mini App WAMP với `php artisan serve` (`127.0.0.1:8000`) — dễ 401/403 do sai DB/host.

Queue OCR local: `php artisan queue:work` (hoặc để `QUEUE_CONNECTION=sync` khi debug).

---

## Deploy production

### Checklist trước deploy

- [ ] Domain **HTTPS** (bắt buộc Zalo Login + Mini App Live)
- [ ] `APP_DEBUG=false` · `APP_ENV=production`
- [ ] `ZALO_DEV_LOGIN=false` · `VITE_ENABLE_DEV_LOGIN=false`
- [ ] Điền `ZALO_APP_ID` / `ZALO_APP_SECRET` / `ZALO_MINIAPP_ID` / `ZALO_OA_ID`
- [ ] `ZALO_WEB_REDIRECT_URI` khớp Developers: `https://<domain>/teacher/zalo/callback`
- [ ] MySQL production + backup
- [ ] Queue worker chạy nền (OCR / jobs)
- [ ] `storage` và `bootstrap/cache` ghi được bởi PHP user

Chi tiết OA / Live: [docs/04-zalo-miniapp-setup.md](docs/04-zalo-miniapp-setup.md).

### Các bước trên server

```bash
# Clone
git clone https://github.com/quangthe2104/kav-miniapp.git
cd kav-miniapp

# Backend
cd backend
cp .env.example .env
# sửa .env production (APP_URL=https://..., DB_*, ZALO_*, QUEUE_*)
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Mini App (build trên CI hoặc trên server có Node)
cd ../miniapp
cp .env.example .env
# VITE_API_BASE_URL=https://<domain-của-bạn>
# VITE_ENABLE_DEV_LOGIN=false
npm ci
npm run build:wamp
```

DocumentRoot web server = `backend/public`.

**Worker (systemd / Supervisor ví dụ):**

```bash
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

**Scheduler (cron):**

```cron
* * * * * cd /path/to/kav-miniapp/backend && php artisan schedule:run >> /dev/null 2>&1
```

(`forms:close-expired` chạy theo schedule Laravel.)

### Deploy trên cPanel (Git Version Control)

Repo có sẵn `.cpanel.yml` → gọi `scripts/cpanel-deploy.sh`:

1. Đồng bộ code vào docroot (`DEPLOYPATH`, mặc định `/home/kavminiapp/public_html`). File `.htaccess` + `index.php` ở root chuyển mọi request vào `backend/public` và chặn truy cập trực tiếp `backend/*`.
2. Giữ nguyên trên server: `backend/.env`, `backend/vendor`, `backend/storage` (ảnh phiếu, log).
3. `composer install --no-dev` (tự tải `composer.phar` nếu server không có), `migrate --force`, `storage:link`, cache config/route/view.

**Lần đầu:**

1. cPanel → **Git Version Control** → clone repo (nên dùng **deploy key SSH** hoặc fine-grained token chỉ đọc, không để PAT quyền rộng trong Remote URL).
2. Sửa `DEPLOYPATH` trong `.cpanel.yml` nếu domain không dùng `public_html` (addon/subdomain có docroot riêng).
3. **Deploy HEAD Commit** → script tạo `backend/.env` từ `.env.example` rồi **dừng**.
4. Sửa `backend/.env` trên server (`APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`, `DB_*`, `ZALO_*`) → **Deploy HEAD Commit** lần nữa.
5. Cron (cPanel → Cron Jobs):

```cron
* * * * * cd /home/kavminiapp/public_html/backend && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /home/kavminiapp/public_html/backend && /usr/local/bin/php artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

PHP ≥ 8.3: script tự dò `ea-php84/83`; có thể ép bằng biến `PHP_BIN`.

**Mini App cho cPanel:** trước khi commit, build bằng

```bat
cd miniapp
npm run build:cpanel
```

(API gọi same-origin theo domain đang chạy; mặc định tắt UI dev login. Giai đoạn UAT chưa có OA: `set VITE_ENABLE_DEV_LOGIN=true` rồi build — backend vẫn chặn nếu `ZALO_DEV_LOGIN=false`.)

**Cập nhật:** push lên GitHub → cPanel **Update from Remote** → **Deploy HEAD Commit**.

### Đưa lên Zalo Mini App (Testing → nộp duyệt)

Bản Mini App trong Zalo build riêng từ `miniapp/` và deploy lên CDN Zalo bằng `zmp-cli` (backend vẫn là domain HTTPS ở trên):

```bash
npm install -g zmp-cli
cd miniapp
npm install
# miniapp/.env: APP_ID=2203119465038830853 (Mini App ID)
zmp login
npm run build:zalo   # dist-zalo/ + app-config.json
zmp deploy           # existing project → dist-zalo → Testing → quét QR
```

Server cần `CORS_ALLOWED_ORIGINS` có `https://h5.zdn.vn,zbrowser://h5.zdn.vn`. Chi tiết (tài khoản test, link vote dạng Mini App, nộp duyệt): [docs/04-zalo-miniapp-setup.md](docs/04-zalo-miniapp-setup.md) §3.6.

### Cập nhật phiên bản mới

```bash
cd /path/to/kav-miniapp
git pull origin main   # hoặc master / nhánh bạn dùng
cd backend && composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
cd ../miniapp && npm ci && npm run build:wamp
# restart php-fpm / queue worker nếu cần
```

### Biến môi trường quan trọng

| Biến | Ghi chú |
|------|---------|
| `APP_URL` | Origin HTTPS production |
| `DB_*` | MySQL |
| `QUEUE_CONNECTION` | `database` hoặc `redis` (Phase 2+) |
| `ZALO_*` | App / OA / Mini App ID — **không commit** |
| `VITE_API_BASE_URL` | Phải trùng `APP_URL` khi build Mini App |
| `ZALO_VOTE_LINK` | `web` (mặc định) hoặc `miniapp` → link vote `zalo.me/s/{ZALO_MINIAPP_ID}/vote/...` |
| `CORS_ALLOWED_ORIGINS` | Thêm `https://h5.zdn.vn,zbrowser://h5.zdn.vn` cho Zalo Mini App |

---

## Push lên GitHub

Remote: **https://github.com/quangthe2104/kav-miniapp.git**

Repo local hiện có thể **chưa** có `.git`. Lần đầu:

### 1. Kiểm tra trước khi commit

- Không commit `backend/.env`, `miniapp/.env`, `vendor/`, `node_modules/`
- Không commit secret Zalo / DB password
- Dùng `.gitignore` ở root (đã có trong repo)

### 2. Init + remote (lần đầu)

PowerShell / Git Bash tại thư mục project:

```bash
cd D:/wamp64/www/kavminiapp

git init
git branch -M main

git remote add origin https://github.com/quangthe2104/kav-miniapp.git
# nếu remote đã tồn tại:
# git remote set-url origin https://github.com/quangthe2104/kav-miniapp.git

git add .
git status
# rà lại: không thấy .env / vendor / node_modules

git commit -m "Initial commit: KavMiniApp Phase 1 (Laravel 13 + Mini App)"

git push -u origin main
```

Nếu GitHub repo đã có README/license từ web, lần push đầu có thể cần:

```bash
git pull origin main --rebase
git push -u origin main
```

Cài hook bỏ trailer `Co-authored-by: Cursor` (một lần sau khi clone):

```bat
powershell -ExecutionPolicy Bypass -File scripts/install-git-hooks.ps1
```

### 3. Các lần sau

```bash
git add .
git commit -m "Mô tả thay đổi ngắn gọn"
git push origin main
```

### 4. Xác thực GitHub

- **HTTPS:** Personal Access Token (Settings → Developer settings → PAT) khi Git hỏi password
- **SSH (tuỳ chọn):**

```bash
git remote set-url origin git@github.com:quangthe2104/kav-miniapp.git
git push -u origin main
```

### 5. Clone máy khác

```bash
git clone https://github.com/quangthe2104/kav-miniapp.git
cd kav-miniapp
# làm theo mục "Chạy local" hoặc "Deploy production"
```

---

## Trạng thái / roadmap

- **Đang làm:** Phase 0 (~30%) + Phase 1 (~90% code) — còn UAT, HTTPS, Mini App Live
- **Chuẩn bị xong trước mốc 1 năm** (mốc năm = lúc đạt tới quy mô):
  - Phase 2 xong hết **2026** → ~25K lớp (2027)
  - Phase 3 xong hết **2027** → ~200K lớp / ~10M PH (2028)
  - Phase 4 xong hết **2028** → ~20M PH (2029)

Chi tiết tick: [docs/03-phase-progress.md](docs/03-phase-progress.md).
