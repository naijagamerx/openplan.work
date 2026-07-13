# AUDIT 03 — Secondary & Utility Features: PHP Mobile vs Flutter

**Date:** 2026-07-03  ·  **Method:** direct file reads, file:line evidence on both sides.

---

## 1. Water Tracking (full feature)

### PHP evidence — 5 dedicated pages + 2 API endpoints
| File | Purpose |
|---|---|
| `water-plan.php` | Active plan overview: today's %, planned/completed/missed liters, scheduled reminder list with live status, client-side auto-mark-missed (MISSED_GRACE_MINUTES=10). |
| `water-plan-details.php` | Per-plan: planned vs actual cards, "Quick Log 1 Drink", aggressive OS-notification reminders (PRE_ALERT_MINUTES=5), inspiration quote. |
| `water-plan-history.php` | All plans (manual+history), activate/delete, totals. |
| `new-water-plan.php` | Create/edit: name, daily goal (L), drink size (L), Quick Presets (fullDay/halfDay/workDay), free-form schedule rows {time, amount}. → `create_manual_plan`/`update_manual_plan`. |
| `water-tracker.php` | Legacy: today's glasses +/−, 7-day average, goal-met days. → `api/water.php?action=log|undo`. |

A "water plan" = `{name, dailyGoal(ml), glassSize(ml), isActive, schedule:[{id,time,amount,completed,missed,lastNotifiedAt}]}`. Stored in `water_plans` + `water_plan_history`. Active plan auto-deactivates end-of-day.

### API (`api/habits.php` co-locates most water actions + `api/water.php`)
GET: `get_water_tracker`(59-89), `get_water_plan`(90-169), `list_water_plan_history`(170-205), `get_daily_tracking`(1632-1704), `get_tracking_history`(1705-1754).
POST: `add_water_glass`(635-735), `set_water_goal`(736-770), `set_water_reminder`(771-803), `get_water_quote`(804-994, AI quote), `complete_water_plan`(995-1063), `clear_water_plan`(1064-1072), `save/load/delete_water_plan_history`, **`generate_water_plan`**(1199-1287, body-metric AI plan), `create_manual_plan`(1288-1335), `update_manual_plan`(1336-1380), **`activate_plan`**(1381-1423), `delete_plan`(1424-1441), **`complete_reminder`**(1442-1546), **`mark_reminder_missed`**(1547-1631).

### Flutter evidence
- `lib/widgets/water_section.dart` (78 lines): dashboard widget only — progress %, liters-in/goal, 3 buttons (+0.25/+0.50/+1.00 L).
- `lib/repositories/water_repository.dart` (53 lines): only `status()` + `add(amountMl)`.
- **MISSING:** no standalone water-plan screen, no history, no plan creation/edit, no reminders, no complete-reminder, no mark-missed, no activate-plan, no AI plan generation.

### Status: ⚠️ STUB / MINIMAL — dashboard widget only.
### Effort: **L** — `WaterPlan` model, expand `WaterRepository` (10+ methods), 3-4 new screens, routes (no `/water` route exists).

---

## 2. Pomodoro (full feature)

### PHP evidence — `mobile/views/pomodoro.php` (1265 lines)
- Big circular conic-gradient progress ring.
- Timer display + state label (Ready/Focus/Paused).
- **Presets:** 25m/15m/5m/**Custom** (focus+break inputs, persisted `pomodoroCustomFocus/Break/Mode`).
- **3 live stats:** Completed Today, Current Session, Focus Time.
- **Focus Music:** full library — Library modal, Upload, Play/Pause, "Play while running" switch, Loop switch, volume slider (13 localStorage keys, `PomodoroAudioManager`).
- **App Focus Tracker** table (per-page seconds from `pomodoro_page_focus`).
- **Recent Sessions** table (last 8 from `pomodoro_sessions`).
- Break auto-switch on completion, sound, toast. Cross-page state via `pomodoroStateV2`.

### PHP `mobile.js` — Global draggable mini-timer
`Mobile.pomodoroOverlay` (`mobile.js:784-1219`): draggable overlay on **every page**. Audio element + track switching, volume, loop, autoplay-during-focus, draggable position persisted, hide/show, play/pause, reset, clock display.

### API (`api/pomodoro.php`, 769 lines)
`complete`(35-50), `list`(52-55), `music_list`(57-59), `music_upload`(61-63), `music_delete`(65-67), `music_rename`(69-71), `music_download`(27-30, HTTP range streaming), admin-only `shared_music_*`(73-91).

### Flutter evidence
- `lib/widgets/pomodoro_card.dart` (133 lines): **dashboard widget only.** Hardcoded 25-min focus timer. Start/Pause/Reset. Local `Timer.periodic` — **no persistence, no API, no presets, no breaks, no music, no stats, no sessions, no overlay.** On completion just zeros out. Decorative.
- No `/pomodoro` route.

### Status: ⚠️ DECORATIVE ONLY — no persistence, no API.
### Effort: **L** — `PomodoroRepository`+`PomodoroMusicRepository`, full `PomodoroScreen` (presets, ring, stats, breaks, completion→API), music library (file_picker+just_audio/audioplayers), sessions/focus tables, persist running state (Riverpod long-lived controller), optional global draggable overlay, route.

---

## 3. Task Timer (stopwatch on view-task)

### PHP evidence — `mobile/views/view-task.php`
`Mobile.viewTaskTimer` (`view-task.php:697-896`): per-task timer; countdown mode if `estimatedMinutes>0` else stopwatch; Start/Pause/Resume/Stop&Log; **overtime alert + vibrate** (`navigator.vibrate`); persisted in `localStorage('mobileTaskTimer')`; Stop&Log confirms minutes then PUTs time entry; `?autostart=1` auto-starts.

### PHP `mobile.js` — Floating timer pill
`Mobile.floatingTimer` (`mobile.js:1225-1334`): black pill at bottom on **every page except the active task's view-task** when a timer runs; shows title+countdown; tap → view-task; polls localStorage every 1s.

### Flutter evidence — `task_detail_screen.dart`
- **NO live timer.** "Time Tracking" = read-only meta (estimated/logged minutes).
- `_logTime()` (`:205-266`): manual Minutes+Description dialog → `logTime()`. Manual entry only (= PHP's log modal, NOT the stopwatch).
- **MISSING:** live Start/Pause/Resume/Stop, countdown vs stopwatch, overtime alert+vibrate, persistence, floating pill.

### Status: ❌ MISSING (manual log only).
### Effort: **M** — `TaskTimerController` (ChangeNotifier, persistence to SharedPreferences), timer widget in detail screen, Stop&Log→reuse `logTime()`, global floating-pill overlay.

---

## 4. Notifications

### PHP evidence — two systems
**(a) In-app bell dropdown** (`header-mobile.php:101-131`): bell icon + red badge on every header; `#notif-panel-mobile`; populated by `App.notificationCenter.initialize()` (`assets/js/app.js:1127`): polls `api/tasks.php` every 60s, derives OVERDUE/DUE_SOON/NOT_STARTED from `dueDate`.
**(b) OS notifications for habit reminders** (`mobile.js:658-780`): `requestNotificationPermission()`; `checkReminders()` polls `api/habits.php`, fires `new Notification(...)` with canvas-built icon; dedup via `lazyman_mobile_habit_reminders_sent_v2` localStorage. Also water reminder sounds.

### Flutter evidence
- **NO notification UI** — no bell, no badge, no dropdown. (No widget/screen/repository.)
- **NO local notifications plugin** — pubspec has no `flutter_local_notifications`/`awesome_notifications`.
- No OS-notification scheduling for habits/water/pomodoro.

### Status: ❌ MISSING entirely.
### Effort: **M** — add `flutter_local_notifications`+`flutter_timezone`; `NotificationsRepository` polling tasks.php; bell+badge in `AppScaffold` AppBar; habit reminder scheduler; water/pomodoro notifications; channel+permission setup.

---

## 5. Settings depth

### PHP evidence — `settings.php` (~1400 lines), sections (`:55-113`)
site(siteName), business(name/email/phone/address/currency/taxRate), api(groq/openrouter keys), water(goal/interval/notifications), notifications(sound/volume/overdue/due-soon/not-started), session(timeout), security(password, master pw), favicon upload, backup management, theme toggle, developer tools, hosted features.

### Flutter evidence — `settings_screen.dart` (638 lines) has ONLY:
Account, Appearance (theme), Security (change pw + master pw), Backup link, Devices+revoke, About, Sign out.

### PHP sections MISSING from Flutter
| Section | In Flutter? |
|---|---|
| Site (siteName) | ❌ |
| Business (6 fields) | ❌ |
| AI API Keys (groq/openrouter) | ❌ |
| Water (goal/interval/notifications) | ❌ |
| Notifications (sound/volume/alerts) | ❌ |
| Session Timeout | ❌ (N/A — token auth) |
| Favicon upload | ❌ (N/A native) |
| Developer Tools | ❌ |
| Hosted Features/Deployment | ❌ (N/A) |
| Theme / Password / Master-pw / Backup / Account | ✅ |
| **Devices (revoke)** | ✅ Flutter-ONLY |

### Status: ⚠️ PARTIAL — 6/~13 sections.
### Effort: **M** — `ConfigRepository` GET/PUT `settings.php`; new sections (Site, Business, AI Keys, Water, Notifications); decide admin-only vs user.

---

## 6. AI configuration pages

### PHP evidence
- `custom-instruction.php`: single form, `config.customInstructions` textarea, POST saves to `config` w/ `customInstructionsUpdatedAt`.
- `model-settings.php`: per-provider model config (model list, default selection, enable toggles).

### Flutter evidence
- **MISSING.** No screens, no routes. `AiAssistantScreen` consumes AI but doesn't configure it. Settings has no AI-key/custom-instruction section.

### Status: ❌ MISSING (both).
### Effort: **S** — `CustomInstructionScreen` (textarea→config), `ModelSettingsScreen` (per-provider model list/toggles), 2 routes.

---

## 7. Auth secondary flows

### PHP evidence — 8 secondary auth views
`register.php` (POST `auth.php?action=register`), `setup.php`, `forgot-password.php`, `reset-password.php`, `verify-email.php`, `verification-required.php`, `master-code.php`, `thank-you.php`.

### Flutter evidence
- Only `login_screen.dart` (3-field login → device_login). No register/forgot/reset/verify/master-code/thank-you. No links to them.

### Which missing flows are needed?
- **register** — Needed if self-service signup desired. MED.
- **forgot-password / reset-password** — Needed (recovery). MED-HIGH.
- **verify-email / verification-required** — Needed only if email verification enforced. LOW.
- **setup** — N/A (server-side).
- **master-code** — N/A (Flutter collects master inline).
- **thank-you** — Only if register built.

### Status: ⚠️ PARTIAL — login only.
### Effort: **M** — Register/Forgot/Reset screens + links from login + routes.

---

## 8. Misc / static pages

### PHP evidence — 9 views
`homepage.php`(marketing), `privacy.php`, `terms.php`, `docs.php`, `support.php`(ticket list + new-ticket form → ticket API), `alexa-setup.php`, `alexa-voice-guide.php`, `mobile-unavailable.php`, `404.php`.

### Flutter evidence
- **404**: ✅ inline `_NotFoundScreen` (`router.dart:199-235`).
- **All others: MISSING.**

### Needed for native?
| Page | Needed? |
|---|---|
| homepage (marketing) | ❌ N/A (app store listing) |
| privacy / terms | ⚠️ Recommended (legal) — link from Settings/About |
| docs | ⚠️ Optional (webview/external) |
| support (tickets) | ⚠️ Optional (in-app help) |
| alexa-setup / alexa-voice-guide | ❌ N/A (Alexa config) |
| mobile-unavailable | ❌ N/A (desktop-redirect) |

### Status: ⚠️ PARTIAL — 404 only.
### Effort: **S** — `LegalScreen` (privacy/terms), optional `SupportScreen`.

---

## 9. Offcanvas menu completeness

### PHP evidence — `offcanvas-menu.php`
Base menu (18 items, `:11-120`): Dashboard, Quick Access(app), Clients, Projects, Tasks, Notes, Habits, Knowledge Base, Calendar, Pomodoro, Water Plan, Invoices, Advanced Invoices, Finance, Inventory, AI Assistant, Settings, Data Management.
Admin-only (3, `:123-142`): Users, Release Export, Shared Music.

### Flutter evidence — `menu_hub_screen.dart`
Workspace(5): Projects, Clients, Invoices, Finance, Inventory. Productivity(5): Notes, KB, AI, Calendar, Meetings. System(2): Settings, Backup. Total **12 cards**.

### PHP menu items absent from Flutter hub
| Item | In hub? |
|---|---|
| Dashboard / Tasks / Habits | ❌ (bottom-nav tabs instead — correct) |
| Quick Access (app) | ❌ MISSING |
| Pomodoro | ❌ MISSING |
| Water Plan | ❌ MISSING |
| Advanced Invoices | ❌ MISSING |
| Data Management | ❌ MISSING (Backup ≠ Data Management) |
| Admin: Users / Release Export / Shared Music | ❌ MISSING (admin section) |
| Meetings | ➕ Flutter-only addition |

### Status: ⚠️ PARTIAL — 12/~18 base items.
### Effort: **S** once underlying screens exist — add `_Module` entries + conditional admin group.

---

## 10. Cross-cutting JS features (`mobile.js`)

`Mobile.init()` (`:1779-1807`) wires on **every page**:

| Feature | PHP evidence | In Flutter? |
|---|---|---|
| Theme toggle | `theme` module (`:610-652`) | ✅ `theme_controller.dart` + Appearance |
| Session keepalive | `initSessionKeepalive` (`:216-248`) pings `auth.php?action=status` every 5min | ❌ (token model — 401 on next call; arguably N/A) |
| Toasts (queueToast survives nav) | `ui.queueToast` (`:502-508`) sessionStorage | ❌ (Flutter SnackBar per-screen; nothing survives nav) |
| Gestures (swipe complete/delete) | `gestures.initSwipes` (`:1630-1704`) | ❌ (Flutter tap/checkbox only; no Dismissible swipes on task list) |
| Pull-to-refresh | `gestures.initPullToRefresh` (`:1709-1768`) | ⚠️ PARTIAL (Flutter `RefreshIndicator` per-screen — idiomatic) |
| Habit reminder OS notifications | `habits` module (`:658-780`) | ❌ (see Feature 4) |
| Pomodoro overlay (global draggable) | `pomodoroOverlay` (`:784-1219`) | ❌ (see Feature 2) |
| Floating task-timer pill | `floatingTimer` (`:1225-1334`) | ❌ (see Feature 3) |
| Confirm dialog / loading | `ui.confirmAction`/`showLoading` | ✅ (`confirmDialog` helper) |

### Status: ⚠️ PARTIAL — only Theme + pull-to-refresh + confirm carried over.
### Effort: **M** — global toast host (ScaffoldMessenger wrapper); swipe gestures (Dismissible); optional keepalive interceptor.

---

## Summary table

| # | Feature | Status | Priority | Effort |
|---|---|---|---|---|
| 1 | Water tracking (full) | ⚠️ Stub | High | **L** |
| 2 | Pomodoro (full) | ⚠️ Decorative | High | **L** |
| 3 | Task timer (stopwatch) | ❌ Missing | High | **M** |
| 4 | Notifications (bell + OS) | ❌ Missing | High | **M** |
| 5 | Settings depth | ⚠️ Partial (6/~13) | Medium | **M** |
| 6 | AI config pages | ❌ Missing | Medium | **S** |
| 7 | Auth secondary flows | ⚠️ Login only | Medium | **M** |
| 8 | Misc/static pages | ⚠️ 404 only | Low | **S** |
| 9 | Offcanvas menu completeness | ⚠️ 12/~18 | Medium | **S** (gated on 1/2) |
| 10 | Cross-cutting JS features | ⚠️ Theme + PTR only | Medium | **M** |

### Essential files
**PHP:** `water-plan.php`, `water-plan-details.php`, `water-plan-history.php`, `new-water-plan.php`, `water-tracker.php`; `pomodoro.php`; `view-task.php`(timer `:697-896`); `settings.php`, `custom-instruction.php`, `model-settings.php`; `header-mobile.php`(bell `:101-131`); `offcanvas-menu.php`(`:11-142`); `mobile/assets/js/mobile.js`(theme `:610`, habits `:658`, pomodoroOverlay `:784`, floatingTimer `:1225`, gestures `:1626`, init `:1779`); `assets/js/app.js`(notificationCenter `:1127`); `api/habits.php`, `api/water.php`, `api/pomodoro.php`.
**Flutter:** `lib/widgets/water_section.dart`, `lib/repositories/water_repository.dart`; `lib/widgets/pomodoro_card.dart`; `lib/screens/task_detail_screen.dart`(`_logTime :205`); `lib/screens/settings_screen.dart`, `menu_hub_screen.dart`; `lib/screens/login_screen.dart`, `lib/auth/auth_repository.dart`; `lib/router.dart`; `lib/theme/theme_controller.dart`.
