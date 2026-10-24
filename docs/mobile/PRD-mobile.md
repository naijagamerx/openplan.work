# PRD — OpenPlan Work Mobile App (Flutter)

> **Product Requirements Document** for the native cross-platform mobile app.
> Companion docs: [`TRD-mobile.md`](./TRD-mobile.md) (technical contract) · [`FLOW-1to1.md`](./FLOW-1to1.md) (desktop↔mobile screen flow).
> Parent product docs: [`../PRD.md`](../PRD.md), [`../TRD.md`](../TRD.md), [`../DESIGN.md`](../DESIGN.md).
> Status: Draft v0.1 · Owner: product · Last updated: 2026-06-26

---

## 0. Context

OpenPlan Work is a PHP monolith with a 54-endpoint REST API, a hand-tuned mobile **web** UI (~60–94 `mobile/views/*.php` screens), and a **shipping Android WebView wrapper** (`/android/`). There is **no iOS app**. This PRD covers replacing the WebView approach with a real cross-platform native app built in **Flutter**, consuming the existing API.

**Locked decisions**

| Decision | Choice | Consequence |
|---|---|---|
| Framework | Flutter (Dart) | One codebase, both OS |
| Auth | Server-side decrypt + per-device token | Backend redesign (the gate); master password never sent per-request |
| iOS builds | User owns a Mac | iOS unblocked; Windows handles Android only |
| Migration | Keep WebView app live until parity | Zero downtime, reversible |

---

## 1. Vision

Give users a fast, offline-capable native app (iOS + Android) for the work they do on the go — capturing tasks, glancing at the dashboard, marking habits, logging finance/water, viewing invoices, and talking to the AI assistant — **without weakening the master-password encryption model**.

## 2. Goals

- **G1** — Native parity for Tier-1 features (§5) with the mobile web experience.
- **G2** — Secure direct-API auth: log in once; master password never travels per request.
- **G3** — Offline read + queued writes for core modules (tasks, notes, habits, finance, water).
- **G4** — Native capabilities the WebView can't offer: push notifications (reminders), biometric unlock, share-sheet export, deep links.
- **G5** — One Dart codebase, CI-built for both stores.

## 3. Non-Goals (v1)

- Replacing the desktop web app or the mobile web views (both remain; WebView stays live during migration).
- Admin surfaces, backup/restore, audit logs, scheduler, full release export (desktop-only).
- Real-time collaborative editing / conflict-free sync (basic last-write-wins only in v1).
- Flutter Web target (native iOS/Android only — avoids CORS work; see TRD §B11).

## 4. Personas

- **P1 — Solo operator / freelancer (primary).** Runs their whole business in OpenPlan. On mobile wants: quick task capture, dashboard glance, mark invoice paid, log an expense, ask the AI "what's due today".
- **P2 — Field worker.** Often offline. Needs habit/water/task logging that syncs when back online.
- **P3 — Manager checking in.** Read-heavy: dashboard, project status, recent notes, AI summary.

## 5. Scope — Feature Tiers

Each Tier-1/2 feature maps to a screen in [`FLOW-1to1.md`](./FLOW-1to1.md).

### Tier 1 — MVP (must ship)

| Feature | User stories | Acceptance criteria |
|---|---|---|
| **Auth** | Log in once with email + password + master password and stay logged in securely. | Device token in secure enclave; biometric unlock on app open; "Devices" list shows + revokes sessions; wrong master → clear error. |
| **Dashboard** | See today's tasks, counts, quick stats at a glance. | Loads < 1.5s from cache; pull-to-refresh; tap a stat → drills into module. |
| **Tasks / Projects** | Create/edit/complete tasks; view by project; subtasks; due dates. | Create task offline → syncs; status change reflects on web after refresh; Kanban viewable (horizontal scroll). |
| **Notes** | List, search, quick-create, edit, tag, pin/favorite. | Search filters client-side on cached set; 10k char limit enforced; color + pin honored. |
| **Habits** | View habits, mark complete, see streaks, start/stop timer. | One-tap complete; streak updates; timer survives app backgrounding. |
| **Water** | Log intake, see goal progress, view history. | Quick +250ml; goal ring; AI plan viewable. |
| **Finance** | Quick-add expense/revenue; list + summary. | Add transaction offline → syncs; currency symbol correct (USD/EUR/GBP/ZAR). |
| **Invoices** | List, view detail, mark status (draft→sent→paid). | Status change persists; PDF/export via share sheet (generation = Tier-2). |
| **Clients (CRM)** | List, view, quick add/edit. | Phone/email tappable (native call/mail intents). |
| **AI Assistant** | Chat with the agent; it can create tasks/notes etc. | Native chat UI; function-call results render as recap + next steps. |
| **Pomodoro / Todos** | Run timer; lightweight checklist. | Timer + session log; todos check/uncheck. |

### Tier 2 — Post-MVP

Advanced invoices + on-device PDF, Inventory, Knowledge Base (flat search instead of drag-tree), Meetings, Tickets, richer offline sync, push for all reminder types.

### Tier 3 — Out of scope (desktop/web only)

Admin/users/subscriptions/payments admin, audit logs, scheduler/cron, backup/restore, 3-pane notes editor, settings hub admin sections, release export.

## 6. Non-Functional Requirements

- **Performance:** cold start < 2.5s; cached screens render offline instantly; API timeout 15s with retry.
- **Security:** token in `flutter_secure_storage` (Keychain/Keystore) behind biometric; no plaintext master at rest on device; TLS only; revocable sessions.
- **Reliability:** offline queue with retry + conflict surfacing; graceful network-loss banners.
- **Accessibility:** Material 3 dynamic type, 48dp touch targets, VoiceOver/TalkBack labels.
- **Compatibility:** iOS 15+, Android 8+ (minSdk 26; current WebView app is minSdk 24).

## 7. Success Metrics

- Tier-1 feature parity verified against mobile web.
- 99% of post-login requests carry **no** master password (verified via proxy capture).
- < 1% crash-free-session regression vs WebView baseline.
- Time-to-first-task-capture < 5s from app open (warm).

## 8. Release Phases

1. **Internal alpha** — Android debug build, Tier-1 read screens, dev backend.
2. **Closed beta** — both platforms (TestFlight + Play internal), Tier-1 full, biometric + offline.
3. **Public v1** — Tier-1 hardened, push notifications, store listings; WebView app deprecated.
