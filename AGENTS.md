# AGENTS.md — KavMiniApp

Hướng dẫn cho Agent / Subagent trong repo này.

## Tài liệu

| File | Vai trò |
|------|---------|
| [docs/00-overview-phases.md](docs/00-overview-phases.md) | Roadmap phase + quy mô 2027–2029 |
| [docs/01-phase1-detail.md](docs/01-phase1-detail.md) | Chi tiết & acceptance Phase 1 |
| [docs/02-technical-architecture.md](docs/02-technical-architecture.md) | Kiến trúc Laravel 13 + ADR |
| [docs/03-phase-progress.md](docs/03-phase-progress.md) | Board tiến độ (living, checkbox) |
| [docs/04-zalo-miniapp-setup.md](docs/04-zalo-miniapp-setup.md) | Runbook tạo / sở hữu / redeploy Mini App |
| [docs/05-teacher-playbook.md](docs/05-teacher-playbook.md) | Playbook vận hành cho giáo viên |
| [docs/06-role-based-test-guide.md](docs/06-role-based-test-guide.md) | Hướng dẫn test theo vai trò (Admin/GV/PH) |
| [docs/khan-parent-consent-zalo-plan.md](docs/khan-parent-consent-zalo-plan.md) | Plan sản phẩm |

ADR nằm trong [docs/02-technical-architecture.md](docs/02-technical-architecture.md) §11.

## Subagents (`.cursor/agents/`)

| Agent | Vai trò |
|-------|---------|
| `project-manager` | Scope, sprint, cập nhật progress |
| `system-architect` | Schema/API/ADR |
| `security` | Auth, PII, upload, vote abuse |
| `developer` | Implement Laravel 13 + Mini App |
| `tester` | Verify acceptance |

## Workflow

```text
project-manager → system-architect → security? → developer → tester
                 → update docs/03-phase-progress.md (tick checkbox)
```

Skill hỗ trợ: `.cursor/skills/kavminiapp-phase1-workflow/`.

## Stack & roadmap tóm tắt

- **Laravel 13** · PHP ≥ 8.3 · MySQL  
- Teacher: Zalo Login (web + Mini App) · Admin: email/password  
- Copy: **bình chọn** (không dùng «đồng thuận» trên UI)
- **1 Form `active` / thời điểm** khi vận hành scale
- Phase 1 giấy: multi-upload OCR + confirm (không mã in)
- Scale (mốc năm = đạt tới; chuẩn bị xong trước 1 năm): Phase 2 xong hết 2026 → 25K lớp (2027) · Phase 3 xong hết 2027 → 200K lớp (2028) · Phase 4 xong hết 2028 → ~20M PH (2029)
- Không Sở–Phòng; roster HS = backlog
- Local: `http://miniapp.kav` — Mini App `VITE_API_BASE_URL` trùng `APP_URL`
