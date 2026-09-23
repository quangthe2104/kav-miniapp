---
name: kavminiapp-phase1-workflow
description: >-
  Run the KavMiniApp Phase 1 multi-agent workflow (PM → architect → security →
  developer → tester) and update docs/03-phase-progress.md. Use when starting a
  sprint task, implementing Phase 1 features, or the user mentions workflow /
  subagents / tiến độ.
---

# KavMiniApp Phase 1 workflow

## When to use

- Kick off or continue Phase 0/1 work
- User asks to follow project agents / workflow
- Closing a task and updating progress

## Workflow (mandatory order)

1. **project-manager** — confirm phase/sprint, in/out scope, task IDs from `docs/03-phase-progress.md`
2. **system-architect** — design if schema/API/auth/OCR/coverage changes (skip for trivial copy tweaks)
3. **security** — if auth, PII phone, uploads, public vote token, export, or deploy
4. **developer** — implement against `docs/01-phase1-detail.md` + `docs/02-technical-architecture.md`
5. **tester** — run acceptance from Phase 1 detail
6. Update **`docs/03-phase-progress.md`**: checkbox `[x]`, Status, %, blockers, nhật ký

## Scope guard

- Default = **Phase 1 only**
- Roster / Excel / printed paper ID → **backlog** (do not pull in unless user orders)
- Phase 2+ = hardening/scale (25K → 200K → 20M) — only when user kicks off
- Stack: Laravel **13**, Teacher Zalo Login, paper multi-upload OCR+confirm; **1 Form active** ops rule

## Progress update template

```markdown
| ID | Status |
| P1-Wx-xx | done |
```

Refresh Phase % roughly from completed sprint rows.
