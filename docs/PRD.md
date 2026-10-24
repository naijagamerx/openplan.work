# OpenPlan Work — Product Requirements Document (PRD)

**Version:** 1.0.0
**Last Updated:** 2026-05-28
**Status:** Living document — synthesized from codebase analysis + existing docs
**Owners:** Project maintainer (`naijagamerx`)

> This PRD describes **what** OpenPlan Work is and **why** it exists. For implementation details, see `docs/TRD.md`. For UX/architecture, see `docs/DESIGN.md`.

---

## 1. Product Vision

OpenPlan Work is a **self-hosted, zero-knowledge encrypted business operations suite** that combines task management, CRM, invoicing, finance, productivity tracking, and an autonomous AI agent into a single PHP monolith. It is designed for solo operators, startups, and small teams who want full data ownership without sacrificing modern AI workflows.

**Three founding principles:**
1. **Local-first, cloud-ready** — runs on a single PHP server (no DB, no compose, no Docker required); deploys to shared hosting in one upload.
2. **Data ownership** — every record at rest is AES-256-GCM encrypted under a per-user master password the server never stores in plaintext.
3. **AI as a first-class operator** — the AI agent and the Model Context Protocol (MCP) server can drive the entire app via the same APIs a human uses.

---

## 2. Target Personas

| Persona | Need | What OpenPlan Solves |
|---|---|---|
| **Solo founder / consultant** | Quote → invoice → get paid; track tasks per client | CRM + invoices + tasks + finance in one tool |
| **Indie developer** | Personal task board, knowledge base, AI helper, voice control | Tasks, KB, AI agent, Alexa skill |
| **Small studio (2–10)** | Multi-user workspace with admin controls, no SaaS lock-in | Admin/user roles, per-user encryption, on-prem |
| **Privacy-sensitive professional** | Encrypted notes & client data, no third-party processors | AES-256-GCM at rest, optional BYO AI keys |
| **AI coding agent (Claude Code, Cursor)** | Programmatic access to plans/tasks/notes | Native MCP stdio server with ~50 tools |

---

## 3. Core Functional Requirements

### 3.1 Authentication & Authorization
- Email + password login (bcrypt cost 12).
- Optional **2-step master-password** prompt to unlock encryption key after credential login.
- Roles: `admin` (full system access) and `user` (personal workspace).
- Session timeout configurable per user (`sessionTimeoutPreference`).
- CSRF token on every non-GET request (32-byte random, 1-per-session).
- Rate-limited login attempts (5 per 15 min via `RateLimiter`).
- Optional persistent-login tokens for "remember me".

### 3.2 Task & Project Management
- Hierarchical: **Project → Task → Subtask** (tasks embedded in projects).
- Task fields: title, description, status (`backlog|todo|in_progress|review|done`), priority (`low|medium|high|urgent`), start/due dates, estimated/actual minutes, subtasks, time entries, recurrence, parent task, linked habit, linked inventory.
- Kanban board view, calendar view, list view.
- Task templates + "create from template".
- Bulk operations (delete, status update).
- Reminder/notification system (`OVERDUE`, `DUE_SOON`, `NOT_STARTED`).

### 3.3 CRM (Clients)
- Client fields: name, email, phone, company, address (nested object), website, notes.
- Linked to projects + invoices.

### 3.4 Invoicing (Two Tiers)
- **Standard invoices** (`invoices.php`): line items, subtotal, tax, total, currency, due date, status.
- **Advanced invoices** (`advanced-invoices.php`): custom templates, modern PDF view, QR-code receipt parsing.
- Multi-currency: USD, EUR, GBP, ZAR.
- Status flow: `draft → sent → paid|overdue|cancelled`.
- PDF generation via html2pdf.

### 3.5 Finance Journal
- Transactions: type (`expense|revenue`), amount, category, date, project/client links.
- Auto-numbered (`YYYY-NNNN`).
- Summary endpoint for AI agent and dashboard widgets.

### 3.6 Productivity
- **Habits** — daily/weekly/monthly with streaks, target durations, reminder times; habit timer (start/stop) and per-day completions log.
- **Water tracker** — daily goal, AI-generated hydration plans, history.
- **Pomodoro** — sessions logged to `pomodoro_sessions`.
- **Todos** — lightweight quick-list, separate from project tasks.
- **Notes** — tag-based, color-coded, pinned/favorited, optional link to task or project, max 10000 chars/note.
- **Knowledge Base** — folder tree, file uploads (Markdown, XML, images), text-batch upload, full-text search, AI RAG.

### 3.7 Inventory
- SKU items with stock levels, history, adjustments.
- Linkable to tasks (`task-inventory-link.php`).

### 3.8 AI Agent
- Chat assistant with **function calling** — can autonomously create/update tasks, projects, clients, invoices, transactions, notes from natural-language requests.
- Providers (BYO key): **Groq**, **OpenRouter**, **Gemini**, **Ollama** (local).
- Conversation memory with windowing (`ConversationMemory`).
- Per-action follow-up recap + "next 2-3 suggested actions" (see `AIAgent::finalizeAssistantMessage()`).
- Shared-key mode: platform admin can supply keys for subscriber tiers (`admin-shared-keys.php`).

### 3.9 MCP Server (Programmatic AI Access)
- Native Node.js stdio MCP server (`mcp-server/index.js`).
- Authenticates via **`MCP_API_TOKEN`** (preferred) or legacy email + master password.
- ~50 tools covering tasks, projects, clients, invoices, notes, KB, habits, water, finance.
- Loopback-only by default (127.0.0.1, ::1).

### 3.10 Alexa Skill (Voice)
- Production skill ID configured (see `memory/alexa_skill_setup.md`).
- Two operating modes:
  - **Single-owner** — env-var-bound to one user.
  - **Multi-user OAuth** — account-linking via `api/oauth/{authorize,token,revoke}.php`.
- Amazon signature verification (`AlexaSignatureVerifier`).

### 3.11 Admin / Ops
- User management (create, promote, ban).
- Subscription + plan management (`plans.php`, `subscriptions.php`, `payment-methods.php`).
- Backups (create, list, restore, schedule).
- Audit logs (`audit.php`).
- Data export (JSON or ZIP) and import.
- Clean **release export** that strips live data + secrets (`release-export.php`).
- Cron / scheduler (`scheduler_*.php`, `cron/scheduler_simple.php`).
- Data recovery and deep-migration tools.

### 3.12 Mobile
- Dedicated mobile views (94 templates in `mobile/views/`) with parity to desktop.
- Routing decision in `DeviceDetector`: URL prefix `/mobile/` → explicit `?device=...` → cookie → `Sec-CH-UA-Mobile` hint → UA regex fallback.
- Mobile partials: bottom-nav, off-canvas menu, mobile header.

### 3.13 Theme & Branding
- Tailwind CSS (CDN) + Inter font.
- Heroicons (outline) only — **never** icon fonts (enforced in `CLAUDE.md`).
- Business-configurable name/email/phone/address/currency/tax rate stored in encrypted `config.json.enc`.

---

## 4. Non-Functional Requirements

| Category | Requirement |
|---|---|
| **Security** | AES-256-GCM at rest, bcrypt(12) passwords, CSRF on all writes, HTTP-only + SameSite=Strict cookies, `.htaccess` blocks `/data/` and `/includes/` |
| **Privacy** | Server never persists master password; it lives only in session memory or env var |
| **Performance** | Flat-file JSON reads under ~5ms typical (small datasets); `flock(LOCK_EX)` for atomic writes |
| **Portability** | Single-folder upload; no DB server, no package manager, no build step |
| **Compatibility** | PHP 8.0+, `openssl`, `json`, `mbstring`, `curl`. Apache `mod_rewrite` recommended |
| **Extensibility** | New API = drop a file in `api/`; new view = drop a file in `views/`; new MCP tool = add definition in `mcp-server/index.js` |
| **Observability** | Audit logs, `data/php_error.log`, scheduler status endpoint, health check |
| **Recoverability** | Per-write atomic via `flock`, scheduled backups, restore-from-backup workflow |

---

## 5. Out of Scope (Current Version)

- Real-time collaboration (no websockets, no operational transform).
- Native mobile apps (Android wrapper exists; iOS does not).
- Multi-tenant SaaS billing infrastructure (subscription tables exist; payment-provider integration is BYO).
- Public open-graph/social share workflows beyond `SEOHelper`.
- Federated identity (no OAuth-as-IdP except for Alexa account-linking).
- Enterprise hardening: session fingerprint TODO remains in `Auth.php`; SLO controls, formal compliance/audit depth pending.

---

## 6. Success Criteria

A release is considered shippable when:
1. `php test/smoke_test_comprehensive.php` passes (currently **28 pass, 0 fail**).
2. PHP lint passes across runtime scope (currently 190 files, 0 syntax errors).
3. MCP full-channel smoke (`test/mcp_channel_smoke.mjs`) passes (currently **PASS — 81 tools discovered**).
4. `release-export.php` produces a clean artifact with no live data, master password, or AI keys.
5. Per `project_status.md`, project audit score ≥ 80/100 (current: **84/100**).

---

## 7. Documentation Map

- **PRD** (this file) — product intent, requirements, scope.
- **TRD** (`docs/TRD.md`) — technical contract: classes, APIs, schemas, encryption.
- **DESIGN** (`docs/DESIGN.md`) — UX flows, routing, layout, theme.
- **User Guide** (`docs/OpenPlan-Work-User-Guide.md`) — end-user instructions.
- **Complete Documentation** (`docs/OpenPlan-Work-Complete-Documentation.md`) — auto-generated reference.
- **CLAUDE.md** — agent operating rules (icons, routing, FTP, pitfalls).
- **Hosted Setup** (`docs/HOSTED_SETUP.md`) — env-var deployment guide.
- **Release Export** (`docs/EXPORT_RELEASE.md`) — clean-zip workflow.

---

## 8. Open Questions / Future Work

| # | Question | Source |
|---|---|---|
| 1 | When will session fingerprint TODO be resolved? | `Auth.php` |
| 2 | Will payment-provider integration ship in-tree or stay BYO? | `payments.php`, `payment-methods.php` |
| 3 | Will Anthropic provider class be added (currently only via OpenRouter proxy)? | `includes/` survey |
| 4 | Will the Android WebView wrapper get an iOS equivalent? | `android/` exists, no iOS dir |
| 5 | Should the user-guide PDF be regenerated on each release? | `scripts/generate_docs_pdf.php` exists |
