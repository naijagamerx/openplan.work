# OpenPlan Work — Design Document (DESIGN.md)

**Version:** 1.0.0
**Last Updated:** 2026-05-28
**Scope:** UX flows, routing, layout, theming, and presentation conventions.

> This complements `docs/PRD.md` (what & why) and `docs/TRD.md` (how & where). DESIGN focuses on how the app **looks and feels** to a user — and the routing/layout structure that delivers it.

---

## 1. Design Principles

1. **One pane of glass** — every business workflow (tasks, clients, invoices, finance, notes, AI) lives in the same shell with a consistent sidebar/header so users never feel like they've switched tools.
2. **Mobile is first-class, not a shrunk-down desktop** — `mobile/views/*` are hand-tuned, with their own bottom-nav, off-canvas menu, and gestures. Parity, not portability.
3. **AI is a coworker, not a popup** — the agent has its own page (`ai-assistant.php`), can be invoked from anywhere, and explicitly recaps actions + suggests next steps after each turn.
4. **Heroicons only.** Inline SVG, outline variant, sized to context. Never icon fonts. (Hard rule in `CLAUDE.md`.)
5. **Tailwind utility-first.** No bespoke design system; the Tailwind CDN + Inter font is the entire visual vocabulary.
6. **Encryption is invisible to the user but obvious in the UI** — the master-password prompt is a distinct second step on login, not a hidden field.
7. **Failure is loud, recovery is one click** — toasts for every action, recovery flows for decryption failures (`data-recovery.php`).

---

## 2. Visual System

### 2.1 Typography
- **Family:** Inter (loaded from a CDN at the page head).
- **Scale:** Tailwind defaults (`text-sm`, `text-base`, `text-lg`, `text-xl`, `text-2xl`, `text-3xl`).
- **Weight:** 400 default body, 500 for labels, 600 for headings, 700 for emphasis.

### 2.2 Color
- Tailwind palette. No fixed brand palette enforced in code — accent comes from per-project `color` hex stored on `projects[].color`.
- Status mapping (see `assets/js/app.js`):
  - Task reminders: `OVERDUE` red, `DUE_SOON` amber, `NOT_STARTED` gray.
  - Habits: green completion, gray incomplete.
  - Water: blue accent throughout.
  - Pomodoro: red work / green break.

### 2.3 Icon System (CRITICAL)

From `CLAUDE.md`:
```html
<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="…"/>
</svg>
```
Sizing scale: `w-4/h-4` inline meta, `w-5/h-5` buttons, `w-6/h-6` modals, `w-8/h-8` empty states.
Use the **Outline** set from heroicons.com.

### 2.4 Components (JS-driven, defined in `assets/js/app.js`)
| Component | Helper | Notes |
|---|---|---|
| Toast | `showToast(message, type)` | types: `success`, `error`, `warning`, `info` |
| Modal | `openModal(html)` / `closeModal()` | Dynamic HTML injection |
| Confirm dialog | `confirmAction(message, onConfirm)` | Promise-style flow |
| API helper | `api.get/post/put/delete()` | Auto-injects `csrf_token` + handles FormData transparently |
| Format utils | `format.currency / date / timeAgo` | |
| Reminder engine | `tasks.*` | OVERDUE / DUE_SOON / NOT_STARTED enums |

---

## 3. Information Architecture

### 3.1 Top-level navigation (Desktop)
Sourced from `views/partials/sidebar.php`:

```
Dashboard
├── Tasks          ── Kanban, Calendar, List sub-views
├── Projects
├── Clients
├── Invoices       ── Standard + Advanced
├── Finance
├── Inventory
├── Notes          ── + Knowledge Base
├── Habits         ── + Water Tracker + Pomodoro
├── AI Assistant
├── Calendar
└── Settings       ── (Hub: ?section=…)
    ├── Business Profile
    ├── AI API Keys
    ├── Model Settings
    ├── Developer Tools  (MCP Config — all users; others admin-only)
    └── Users / Admin (admin only)
```

Per `memory/settings_hub_architecture.md`: desktop Settings is a **fail-open `?section=` hub** — mobile Settings keeps **accordion layout** intentionally.

### 3.2 Mobile navigation
- **Bottom nav** (`mobile/views/partials/bottom-nav.php`): 4–5 primary tabs.
- **Off-canvas menu** (`mobile/views/partials/offcanvas-menu.php`): full module list.
- **Header** (`mobile/views/partials/header-mobile.php`): page title + back/menu controls.

---

## 4. Routing

### 4.1 URL pattern
All routes pass through `index.php?page=<slug>`. There is no REST-style URL rewriting at the application level — `.htaccess` only blocks sensitive paths.

| Pattern | Behavior |
|---|---|
| `/` | Home (login or dashboard depending on auth state) |
| `?page=login` | Auth screen |
| `?page=setup` | First-run admin creation |
| `?page=dashboard` | Main shell |
| `?page=tasks&projectId=…` | Task list filtered by project |
| `?page=settings&section=ai-keys` | Settings hub deep link |
| `?device=mobile` / `?device=desktop` | Force device mode (persisted to cookie) |

### 4.2 Mobile vs Desktop decision tree
Implemented in `includes/DeviceDetector.php` (see TRD §3.7). Order: URL path → query param → cookie → client hint → UA regex.

### 4.3 Auth gate
`index.php:50–53` redirects any unauthenticated request for a non-public page to `?page=login`.

`$publicPages` (login, setup, forgot/reset password, verify-email, privacy, terms, thank-you, homepage) bypass the gate.

---

## 5. Key Page Flows

### 5.1 Login (two-step)
```
[email + password] ──► validate (bcrypt)
       │
       ▼
[master password] ──► derive key, decrypt user config, set session
       │
       ▼
   Dashboard
```
Implemented in `index.php:223–433`. CSRF validated at each step. Rate-limited via `RateLimiter`.

### 5.2 First-run setup
`?page=setup` → create admin user → set master password → seed empty per-user collections → redirect to dashboard.

### 5.3 Task creation
```
Dashboard → New Task button
   └── openModal(taskForm)
        └── api.post('api/tasks.php?action=add', {…, csrf_token: CSRF_TOKEN})
             └── TasksAPI::create() → embeds task in projects[].tasks[]
                  └── successResponse → toast → close modal → refresh list
```

### 5.4 AI Agent turn
```
User types message → POST api/ai-agent.php?action=chat
   ├── AIAgent.chat()
   │    └── provider (Groq/OpenRouter/Gemini/Ollama).chatCompletion(messages, functions)
   │         └── if tool_use: FunctionExecutor.validateCall() → run on TasksAPI/etc.
   │              └── append result back to conversation, loop (max 4)
   └── finalizeAssistantMessage() appends: recap + 2-3 suggested next actions
        └── successResponse → render in chat → ConversationMemory.persist()
```

### 5.5 Invoice → PDF
```
Invoice form → preview HTML (advanced-invoice-view-modern.php) → html2pdf in browser → download
```

### 5.6 Knowledge Base search (RAG-lite)
```
AI Agent receives query
   ├── If query likely needs context: search_knowledge_base()
   │    └── reads data/users/{u}/kb/*.md → returns top matches
   └── injected as system context → provider completion
```

### 5.7 Backup & restore
```
Settings → Backups → "Create" → Backup::create()
   └── encrypted ZIP written to data/backups/{timestamp}.bak
        └── download via release-download.php (admin)

To restore: api/backup.php?action=restore (with backup id) → atomic swap with current data/
```

### 5.8 Alexa voice command
```
Echo device ──HTTPS POST──► api/alexa.php
   ├── AlexaSignatureVerifier (cert chain + timestamp)
   ├── AlexaHandler::dispatch(intent)
   │    ├── Single-owner mode: env-bound user
   │    └── Multi-user OAuth mode: lookup alexa_links → resolve userId + master pw
   └── speech response (SSML)
```

---

## 6. Layout Templates

### 6.1 Desktop layouts (`views/layouts/`)
- `main.php` — sidebar + topbar + content slot. Injects `CSRF_TOKEN` and `APP_URL` globals.
- `auth.php` — centered card for login/setup/reset flows.
- `three-pane.php` — three-column experimental layout (notes-three-pane-sample, etc.).

### 6.2 Mobile layout (`mobile/views/layouts/mobile.php`)
- Header + content + bottom-nav.
- Off-canvas menu overlay on hamburger tap.
- `CSRF_TOKEN` and `APP_URL` injected the same way (avoiding the **PHP/JS variable mixing pitfall** documented in `CLAUDE.md`).

### 6.3 Partials
- `views/partials/sidebar.php` — desktop nav.
- `views/partials/header.php` — desktop topbar.
- `mobile/views/partials/header-mobile.php` — mobile topbar.
- `mobile/views/partials/bottom-nav.php` — mobile bottom tabs.
- `mobile/views/partials/offcanvas-menu.php` — slide-out menu.

---

## 7. Empty States & Error Surfaces

| Surface | Pattern |
|---|---|
| No tasks | Heroicon `w-8 h-8` clipboard + CTA "Create your first task" |
| Decryption failure | Modal: "Wrong master password. Log out and re-enter." → link to logout |
| 500 errors | Generic toast + suggestion to check `data/php_error.log` |
| Login failure | Inline error under field + rate-limit notice if applicable |
| Network failure | Toast with retry helper |

---

## 8. Accessibility

- Color contrast: rely on Tailwind defaults; verify any custom hex on `projects[].color` clears WCAG AA.
- Focus rings: keep `focus:ring-2` on all interactive controls.
- ARIA: forms use native labels; modals should set `role="dialog"` and trap focus (verify per page).
- Reduced motion: avoid gratuitous animation; respect `prefers-reduced-motion` where animations exist.

---

## 9. Notification Design

From `assets/js/app.js`:
- `NOTIFICATION_TYPE_COLORS` palette per surface (task / habit / pomodoro / water / ai).
- `NOTIFICATION_TYPE_GLYPHS` Unicode glyphs.
- `buildNotificationIcon()` generates canvas-based PWA icons.
- Browser push permission requested on first reminder.

---

## 10. AI Surface Conventions

- **Chat input** — Enter sends, Shift+Enter for newline, hard cap 4000 chars before warning.
- **Tool result chips** — Each executed function shows as a chip ("Created task 'Pay invoice'") below the assistant message.
- **Recap block** — At end of each AI turn, a small section enumerates: what was done, any errors, 2–3 suggested next actions (see `AIAgent::finalizeAssistantMessage()`).
- **Provider switch** — Settings → Model Settings allows live switch between Groq / OpenRouter / Gemini / Ollama.

---

## 11. Design Anti-patterns (Do Not Do)

- ❌ Don't use Font Awesome / Material Icons / any icon font. Heroicons only.
- ❌ Don't inline arbitrary CSS in views; stick to Tailwind utilities.
- ❌ Don't mix PHP-constant syntax with JS variables (see `CLAUDE.md` "PHP/JS Variable Mixing Bug").
- ❌ Don't show a desktop sidebar on mobile — `DeviceDetector` already routes to mobile views; do not branch in templates.
- ❌ Don't write SQL-shaped patterns; this is a flat-file store. Use `Database::load/insert/update/delete`.

---

## 12. Future Design Work

- Consistent empty-state component (currently bespoke per page).
- Component library extraction (modals, forms, kanban cards) into shared partials.
- Dark-mode pass (Tailwind `dark:` already partially used; needs systematic audit).
- A11y audit for modal focus traps and keyboard nav across all three-pane layouts.
- Cohesive iconography pass to ensure size consistency (`w-5 h-5` overwhelmingly preferred).
