# App Flow — Desktop ↔ Mobile (1:1)

> Side-by-side mapping of each desktop web screen to its Flutter mobile counterpart, with touch-vs-mouse adjustments and key flow diagrams.
> Companion docs: [`PRD-mobile.md`](./PRD-mobile.md) · [`TRD-mobile.md`](./TRD-mobile.md).
> Status: Draft v0.1 · Last updated: 2026-06-26

---

## C1. Navigation model

- **Desktop:** left sidebar (`views/partials/sidebar.php`) + top header → all modules always visible.
- **Mobile (Flutter):** bottom nav with 5 primary destinations — **Dashboard · Tasks · Add(+) · AI · More** — plus a "More" sheet for the rest (mirrors the existing `mobile/views/partials/bottom-nav.php` + `offcanvas-menu.php`). The center **(+)** is a global quick-add (task / note / transaction / water).

## C2. Screen mapping (desktop view → Flutter screen)

| Desktop (`views/…`) | Flutter screen(s) | Touch-vs-mouse adjustments |
|---|---|---|
| `dashboard.php` (1906 ln) | `dashboard` | Stat cards stack vertically; tap-drill instead of hover; pull-to-refresh |
| `tasks.php` (kanban / calendar / list) | `tasks` + `kanban` + `task_detail` | List default; Kanban = horizontal swipe between columns; long-press to move; FAB to add |
| `projects.php` | `projects` + `project_detail` | Grid → vertical list; swipe actions (archive/edit) |
| `clients.php` | `clients` + `client_detail` | Cards → list; tap phone/email → native call/mail intent |
| `invoices.php` + `advanced-invoices.php` | `invoices` + `invoice_detail` | One combined list; status via bottom-sheet picker; export → share sheet |
| `finance.php` | `finance` | Summary widgets on top; quick-add sheet; month filter as chips |
| `inventory.php` (Tier-2) | `inventory` | List + modal edit; stock +/− steppers |
| `notes.php` (3-pane, 2398 ln) | `notes` + `note_edit` | Drop 3-pane → list → full-screen editor; tags as chips; no sidebar tree |
| `habits.php` | `habits` | Cards; one-tap complete; timer full-screen; streak ring |
| `ai-assistant.php` | `ai_chat` | Input pinned to bottom; messages scroll; function-call results as cards |
| `settings.php` (accordion hub) | `settings` + `devices` | Single column; **new Devices section** to list/revoke tokens; admin sections hidden |
| Pomodoro / Water / Todos | `pomodoro` / `water` / `todos` | Simple stat UIs; large tap targets |
| Admin / Backup / Audit / Scheduler | — | **Not ported** (desktop-only) |

## C3. Key flow diagrams

### Auth — one-time login → persistent secure session

```
App open ─► token in secure storage?
   ├─ no  ─► Login screen ─► POST device_login(email, pw, master)
   │                          ─► store token (Keychain/Keystore) ─► Dashboard
   └─ yes ─► biometric prompt ─► OK ─► Dashboard
On 401 anywhere ─► clear token ─► Login screen
Settings ► Devices ► revoke ─► that device's next call → 401 → re-login
```

### Create task — offline-capable

```
FAB(+) ─► Quick-add (title, project, due) ─► Save
   ├─ online  ─► POST api/tasks.php (Bearer) ─► optimistic insert ─► confirmed
   └─ offline ─► enqueue ─► show "will sync" ─► on reconnect flush ─► confirmed / conflict banner
```

### Invoice status change

```
invoice_detail ─► tap status chip ─► bottom-sheet (draft / sent / paid / overdue / cancelled)
              ─► PUT api/invoices.php (Bearer) ─► reflect new status
```

### AI chat with actions

```
ai_chat ─► type message ─► POST api/ai-agent.php?action=chat (Bearer)
        ─► agent executes tools server-side
        ─► response renders recap + suggested next steps
           (the finalizeAssistantMessage output from includes/AIAgent.php)
```

## C4. Theming parity

Tailwind palette + Inter + Heroicons → Flutter `ColorScheme` + Inter font + a matching icon set (Heroicons-as-SVG, or Material symbols tuned to the brand). Dark mode supported (matches the existing mobile web).
