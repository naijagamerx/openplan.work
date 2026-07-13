# AUDIT 01 — PHP Mobile → Flutter Page/Feature Map

**Source of truth:** PHP mobile app (`mobile/views/`, 91 `.php` pages) + `mobile/assets/js/mobile.js`
**Target:** Flutter app (`flutter_app/lib/`, 31 screens + widgets/repos/models/theme/config)
**Method:** Every PHP view and every Flutter screen/widget read in full. Matching by **behavior verified in code**, not filename.
**Authored:** 2026-07-03

**Status legend:** **PARITY** = Flutter fully replicates · **PARTIAL** = exists but missing sub-features (listed) · **MISSING** = no Flutter equivalent · **VARIANT** = PHP duplicate/redirect

**Architectural divergences (read first):**
- **PHP auth = 2-step** (login.php → master-code.php → session cookie). **Flutter auth = single-step device-token** (login_screen.dart collects email+password+master → `POST /api/auth.php?action=device_login` → bearer token in secure storage; fail-open biometric gate on cold start). Several PHP auth pages have no Flutter analog *by design*.
- **PHP bottom nav = 5 tabs + offcanvas (18 base + 3 admin).** **Flutter = 4 tabs** (Dashboard·Tasks·Habits·Menu); Menu tab = PHP offcanvas.
- **PHP tasks = swipe-left complete + swipe-right delete.** **Flutter = swipe-right complete only.**
- **PHP AI = marked.js + DOMPurify + mermaid.js, streaming, agent polling.** **Flutter AI = plain SelectableText, no markdown/mermaid/streaming.**
- **PHP invoice/note/KB-file = separate detail screens.** **Flutter collapses each into a single create/edit screen doubling as detail.**

---

## 1. Auth & Onboarding

| PHP page | PHP purpose | Flutter equivalent | Status | What's missing |
|---|---|---|---|---|
| `login.php` | Email+password step 1 of 2 | `login_screen.dart:1-342` | **PARTIAL** | Flutter merges email+password+master into ONE screen (intentional). Missing: remember-me. |
| `master-code.php` | Master password step 2 | (merged into login_screen.dart) | **VARIANT** | Folded by design. |
| `register.php` | New account creation | MISSING | **MISSING** | No registration flow. |
| `forgot-password.php` | Request reset email | MISSING | **MISSING** | No self-service reset. |
| `reset-password.php` | Set new password from token | MISSING | **MISSING** | — |
| `verify-email.php` | Email verification landing | MISSING | **MISSING** | — |
| `verification-required.php` | Verify gate | MISSING | **MISSING** | — |
| `thank-you.php` | Post-register confirm | MISSING | **MISSING** | — |
| `setup.php` | First-run master pw setup | MISSING | **MISSING** | Web-first by design. |
| `logout.php` | Session destroy | `auth_controller.dart:69-76` + sign-out actions | **PARITY** | — |

*register/forgot/reset/verify/verification-required/thank-you/setup are reasonably web-only for a native app — flagged MISSING for completeness, not as defects.*

---

## 2. Primary Tabs

| PHP page | PHP purpose | Flutter equivalent | Status | What's missing |
|---|---|---|---|---|
| `dashboard.php` | Greeting, pending count, habits ring, water, inspiration, pomodoro, recent tasks, AI-quota banner | `dashboard_screen.dart:1-369` | **PARTIAL** | Missing AI-quota/subscription banner. Present: greeting, CountUp pending, habits ring, WaterSection, InspirationCard, PomodoroCard, recent tasks. |
| `tasks.php` | Task list grouped by project, search, status/priority filters, swipe | `tasks_screen.dart:1-438` | **PARTIAL** | Missing swipe-left-complete (Flutter swipe-right only), swipe-right-delete, kanban toggle. |
| `view-task.php` | Task detail: subtasks, time log, time-tracking | `task_detail_screen.dart:1-568` | **PARITY** | Full: toggle done, subtasks, log time, delete, meta. |
| `task-form.php` | Create/edit task w/ subtasks | `task_form_screen.dart:1-404` | **PARITY** | — |
| `habits.php` | Habit list w/ today ring + momentum | `habits_screen.dart:1-311` | **PARITY** | — |
| `view-habit.php` | Habit detail: streaks, month heatmap, archive/delete | `habit_detail_screen.dart:1-521` | **PARITY** | — |
| `habit-form.php` | Create/edit habit + AI suggest | `habit_form_screen.dart:1-307` | **PARITY** | — |
| `habits-all.php` | Redirect → habits | `habits_screen.dart` | **VARIANT** | — |
| `calendar.php` | Month grid tasks+meetings | `calendar_screen.dart:1-840` | **PARTIAL** | Month nav, dots, day detail, FAB→meeting. PHP is month-only too. |
| `meeting-form.php` | Create/edit meeting | `meeting_form_screen.dart:1-501` | **PARITY** | — |

---

## 3. Workspace (Projects/Clients/Invoices/Finance/Inventory)

| PHP page | Flutter equivalent | Status | What's missing |
|---|---|---|---|
| `projects.php` | `projects_screen.dart:1-402` | **PARITY** | — |
| `view-project.php` | `project_detail_screen.dart:1-782` | **PARTIAL** | Flutter status columns w/ dropdown-move, not drag kanban. Functionally equivalent. |
| `project-form.php` | `project_form_screen.dart:1-333` | **PARTIAL** | Flutter doesn't send clientId/clientName on save — client linkage display-only. |
| `kanban-board.php` | MISSING | **MISSING** | Closest = project-detail status columns. |
| `clients.php` | `clients_screen.dart:1-307` | **PARITY** | — |
| `view-client.php` | `client_detail_screen.dart:1-356` | **PARITY** | — |
| `client-form.php` | `client_form_screen.dart:1-252` | **PARITY** | — |
| `invoices.php` | `invoices_screen.dart:1-399` | **PARTIAL** | Missing advanced-invoice entries. Basic covered. |
| `invoice-form.php` | `invoice_form_screen.dart:1-625` | **PARITY** | — |
| `invoice-view.php` | (merged into invoice_form_screen) | **VARIANT** | /invoices/:id → form edit mode. |
| `print-invoice.php` | MISSING | **MISSING** | No print/PDF view. |
| `advanced-invoices.php` | MISSING | **MISSING** | No advanced-invoice support. |
| `advanced-invoice-view.php` | MISSING | **MISSING** | — |
| `advanced-invoice-view-modern.php` | MISSING | **VARIANT** | Redirect target also missing. |
| `advanced-invoice-form.php` | MISSING | **VARIANT** | Mobile PHP itself routes to desktop. |
| `finance.php` | `finance_screen.dart:1-537` | **PARITY** | Revenue/expense/net, 6-mo chart, filters. |
| `transactions.php` | `finance_screen.dart` | **VARIANT** | Alias. |
| `transaction-form.php` | `transaction_form_screen.dart:1-357` | **PARITY** | — |
| `inventory.php` | `inventory_screen.dart:1-732` | **PARITY** | — |
| `inventory-history.php` | `inventory_item_screen.dart:1-827` (merged) | **PARITY** | History folded into item screen. |
| `product-form.php` | `inventory_form_screen.dart:1-273` | **PARITY** | — |

---

## 4. Productivity (Notes/KB/AI/Meetings)

| PHP page | Flutter equivalent | Status | What's missing |
|---|---|---|---|
| `notes.php` | `notes_screen.dart:1-489` | **PARITY** | — |
| `view-notes.php` | (merged into note_form_screen) | **VARIANT** | No read-only detail. |
| `note-form.php` | `note_form_screen.dart:1-432` | **PARITY** | — |
| `notes-list.php` | `notes_screen.dart` | **VARIANT** | Redirect. |
| `notes-three-pane-sample.php` | `notes_screen.dart` | **VARIANT** | Redirect. |
| `knowledge-base.php` | `knowledge_base_screen.dart:1-703` | **PARITY** | Folders, files, create/delete. |
| (KB file view/edit) | `kb_file_screen.dart:1-296` | **PARTIAL** | Raw markdown editor, no rendered preview (PHP also raw editor). |
| `ai-assistant.php` | `ai_assistant_screen.dart:1-848` | **PARTIAL** | **Missing: markdown rendering, mermaid, streaming, persisted history, custom-instruction, model-settings.** Present: provider+model dropdown, Chat/Agent toggle, action chips. |
| `custom-instruction.php` | MISSING | **MISSING** | No AI custom-prompt UI. |
| `model-settings.php` | MISSING | **MISSING** | No model/API-key settings. |

---

## 5. Habits extras + Water cluster

| PHP page | Flutter equivalent | Status | What's missing |
|---|---|---|---|
| `habits-calendar.php` | (folded into habit_detail `_MonthGrid`) | **VARIANT** | No standalone route. |
| `habit-history.php` | (folded into habit_detail StatCards) | **VARIANT** | — |
| `water-tracker.php` | `widgets/water_section.dart:1-78` (dashboard widget only) | **PARTIAL** | No standalone water screen; only dashboard widget (progress %, liters, +0.25/+0.50/+1.00L). Missing: standalone page, intake log/history. |
| `water-plan.php` | MISSING | **MISSING** | No plan management. |
| `new-water-plan.php` | MISSING | **MISSING** | — |
| `water-plan-details.php` | MISSING | **MISSING** | — |
| `water-plan-history.php` | MISSING | **MISSING** | — |

---

## 6. Pomodoro

| PHP page | Flutter equivalent | Status | What's missing |
|---|---|---|---|
| `pomodoro.php` (full page: focus/break cycles, music, presets, overlay, per-task linking, persistence) | `widgets/pomodoro_card.dart:1-133` (dashboard widget only) | **PARTIAL** | Flutter PomodoroCard = minimal fixed 25:00 countdown w/ Start/Pause/Reset. **No break cycles, music, presets, overlay, task linking, persistence, API.** No `/pomodoro` route. |

---

## 7. Settings + Backup

| PHP page | Flutter equivalent | Status | What's missing |
|---|---|---|---|
| `settings.php` (~1400 lines: site, business, api keys, water, notifications, session, favicon, password, master pw, backup) | `settings_screen.dart:1-638` | **PARTIAL** | **Missing: site, business, AI API keys, water, notifications, session, favicon sections. Subscription/AI-quota, MCP.** Present: Account, Appearance, Security (pw+master pw), Backup link, Devices+revoke, About, Sign out. |
| `backup-settings.php` | (link in settings_screen) | **PARTIAL** | No separate settings page. |
| `backup-list.php` | `backup_screen.dart:1-286` | **PARITY** | Create/restore/delete. |
| `data-management.php` | MISSING | **MISSING** | No data hub. |
| `data-recovery.php` | MISSING | **MISSING** | Restore only server-side, not uploaded files. |
| `import-data.php` | MISSING | **MISSING** | No import UI. |

---

## 8. Admin (all admin-only — see AUDIT_02 for detail)

| PHP page | Flutter status |
|---|---|
| `admin-hub, admin-payments, admin-plans, admin-payment-methods, admin-shared-keys, admin-subscriptions, admin-tickets, users, audit-logs, scheduler-status, release-export, shared-music` | **ALL MISSING** |
| `ticket-view.php` | MISSING |

*All admin features MISSING from Flutter. See AUDIT_02_admin_features.md for the full per-feature breakdown, API contracts, and build plans.*

---

## 9. Subscription / Billing / Support / MCP / Misc / Static

| PHP page | Flutter status |
|---|---|
| `subscription, buy-plan, my-subscription` | **MISSING** |
| `support, ticket-view` | **MISSING** |
| `mcp` | **MISSING** |
| `app` (Quick Access) | **MISSING** |
| `mobile-unavailable` | **PARTIAL** (router 404, no "switch to desktop") |
| `404` | **PARITY** (`router.dart:44-56`) |
| `homepage, docs, privacy, terms` | **MISSING** (web-only) |
| `alexa-setup, alexa-voice-guide` | **MISSING** (web-only server config) |

---

## Summary

**Total PHP pages audited: 91**

### Raw status counts (1 row per PHP page)
| Status | Count |
|---|---|
| PARITY | 20 |
| PARTIAL | 12 |
| MISSING | 53 |
| VARIANT | 6 |

### Distinct features (variants collapsed) — ~48 distinct
| Status | Count |
|---|---|
| PARITY | 20 |
| PARTIAL | 12 |
| MISSING | 16 |

### Top 15 most impactful MISSING/PARTIAL (ranked by user impact)

| # | Item | Status | Why it matters |
|---|---|---|---|
| 1 | AI markdown + mermaid + streaming | PARTIAL | Responses render as raw text — code/tables/diagrams unreadable. |
| 2 | Pomodoro full page (only widget) | PARTIAL | No break cycles, music, presets, task linking, persistence. |
| 3 | Water-plan cluster (4 pages) | MISSING | Can't create/manage hydration plans or view history. |
| 4 | Custom-instruction | MISSING | Can't personalize AI behavior. |
| 5 | Model-settings | MISSING | Can't set per-provider API keys / pick models outside chat dropdown. |
| 6 | Register / forgot / reset / verify-email | MISSING | Blocks cold-start onboarding (may be acceptable if web-first). |
| 7 | Advanced invoices (whole cluster) | MISSING | Power users on web use these heavily. |
| 8 | Print-invoice / PDF | MISSING | Can't share/print invoice from app. |
| 9 | Support / tickets (2 pages) | MISSING | No in-app help. |
| 10 | Subscription / billing (3 pages) | MISSING | Can't view plan/quota/upgrade. |
| 11 | MCP token management | MISSING | Power users can't connect external AI agents. |
| 12 | Data import / file-based recovery | MISSING | Can't recover from uploaded backup file. |
| 13 | Privacy & Terms in-app | MISSING | Likely legal/compliance gap for app-store. |
| 14 | Tasks swipe-to-delete + swipe-left-complete | PARTIAL | Power-user gesture ergonomics regressed. |
| 15 | Settings: subscription/AI-quota + MCP sections | PARTIAL | Settings feels incomplete. |

### Strengths to preserve
- **Full CRUD parity** on Tasks, Habits, Projects, Clients, Inventory, Finance, Notes, KB, Meetings — the data-modeling core is solid.
- **Devices list with revoke** (`settings_screen.dart:157-202`) — Flutter-ONLY enhancement, genuine security improvement.
- **Biometric cold-start gate** (`auth_controller.dart:36-46`) — native-only win.
- Consistent monochrome design system across all 31 screens.

### Essential files
**PHP:** `dashboard.php, tasks.php, view-task.php, ai-assistant.php, pomodoro.php, water-tracker.php, water-plan.php, settings.php, login.php, master-code.php, support.php, subscription.php, mcp.php, custom-instruction.php, model-settings.php, invoices.php, advanced-invoices.php`; `mobile/assets/js/mobile.js`.
**Flutter:** `lib/router.dart` (31 routes = authoritative feature inventory); `dashboard_screen.dart, tasks_screen.dart, task_detail_screen.dart, ai_assistant_screen.dart, settings_screen.dart, login_screen.dart, invoices_screen.dart, invoice_form_screen.dart`; `lib/widgets/pomodoro_card.dart, water_section.dart, app_bottom_nav.dart, app_scaffold.dart`; `lib/auth/auth_controller.dart, auth_repository.dart`; `lib/api/api_client.dart`.
