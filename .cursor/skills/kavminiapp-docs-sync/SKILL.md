---
name: kavminiapp-docs-sync
description: >-
  Keep KavMiniApp docs consistent when product or technical decisions change.
  Use when updating plans, ADRs, phase scope, or after stakeholder decisions
  about Forms, Zalo OA, paper OCR, scale roadmap, or roster backlog.
---

# KavMiniApp docs sync

## When to use

- New product decision from stakeholder
- Architecture ADR change
- Moving work between Phase 0–4 (scale path) or backlog

## Files to touch (as needed)

| Change type | Update |
|-------------|--------|
| Nghiệp vụ / quyết định | `docs/khan-parent-consent-zalo-plan.md` + lịch sử |
| Phase map / quy mô | `docs/00-overview-phases.md` |
| Sprint acceptance | `docs/01-phase1-detail.md` |
| Schema / API / ADR | `docs/02-technical-architecture.md` (§11 ADR) |
| Task board | `docs/03-phase-progress.md` (checkbox + Status) |
| Playbook / UAT | `docs/05-teacher-playbook.md`, `docs/06-role-based-test-guide.md` |
| Agent defaults | `AGENTS.md`, `.cursor/rules/kavminiapp-core.mdc` |

## Rules

1. Do not leave contradictory statements across docs (e.g. paper ID in Phase 1; Sở–Phòng trên critical path)
2. Append changelog rows; do not rewrite history silently
3. Critical path = Phase 0→1→2(2027)→3(2028)→4(2029). Roster = **backlog**, không đổi số Phase scale
4. Vận hành: **1 Form `active` / thời điểm**
5. Laravel version must remain **13** unless user changes stack
