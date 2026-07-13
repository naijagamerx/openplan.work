# AUDIT 02 — Admin Features: PHP mobile app vs Flutter app

**Version:** 1.0  ·  **Date:** 2026-07-03
**Scope:** Exhaustive feature-by-feature comparison of every admin capability exposed by the PHP `/mobile/views/*` app against the Flutter app in `flutter_app/lib/`, with the exact API contracts and what it would take to reach parity.

> Drives the implementation of the Flutter **admin module**. User requirement: *"some users are also admin, build for that too."*

---

## 0. Executive summary (read this first)

### 0.1 The role flag already exists, Flutter throws it away
`device_login` already returns `user.role` (`api/auth.php:218`):
```php
'user' => ['id'=>…, 'email'=>…, 'name'=>…, 'role'=> Auth::normalizeRole($user['role'] ?? null)]
```
But Flutter discards it — `auth_controller.dart:72` reads only `name`. There is **no** `roleProvider` / `isAdminProvider` / role in `TokenStore`, and zero `isAdmin` references anywhere in `flutter_app/lib/`.

### 0.2 Admin mutation endpoints are UNREACHABLE from Flutter (CSRF)
Admin APIs enforce CSRF with `Auth::validateCsrf()` (needs a session `csrf_token`). A device-token request starts a fresh session with no CSRF token → 403. The modern data endpoints avoid this by bypassing CSRF for token auth:
```php
// ✅ Modern (works from Flutter): api/clients.php:14, api/backup.php:37, …
if (requestMethod() !== 'GET' && !Auth::isTokenAuth()) { /* require CSRF */ }
```
But **none** of the admin endpoints use this guard. Files needing the one-line fix (`if (!Auth::isTokenAuth() && !Auth::validateCsrf(...))`):
`api/users.php` (43,70,89,134,174), `payments.php` (70,94), `plans.php` (51), `payment-methods.php` (54), `subscriptions.php` (64), `admin-shared-keys.php` (38), `audit.php` (34), `release-export.php` (42,274), `data-recovery.php` (21), `wipe-data.php` (19), `backup_settings.php` (57), `mcp.php` (55,80), `tickets.php` (57). ~20 single-line edits.

### 0.3 Device-token auth DOES correctly carry admin status
`Auth::isDeviceToken()` (`includes/Auth.php:1127-1148`) resolves the user; `Auth::user()` (`:1348-1359`) loads `role` from the users collection; `Auth::isAdmin()` → `role() === 'admin'` works for device tokens. **Server enforcement is fine.** The two gaps: (a) Flutter never learns the role, (b) CSRF blocks Flutter writes.

### 0.4 Parity status — every admin feature is MISSING from Flutter
| # | Admin feature | PHP file | API endpoint(s) | Flutter status | Priority |
|---|---|---|---|---|---|
| 1 | Admin hub / launcher | `admin-hub.php` | (aggregate) | **MISSING** | High |
| 2 | User management | `users.php` | `api/users.php` | **MISSING** | High |
| 3 | Payment approvals | `admin-payments.php` | `api/payments.php` | **MISSING** | High |
| 4 | Subscription plans CRUD | `admin-plans.php` | `api/plans.php` | **MISSING** | High |
| 5 | Payment methods CRUD | `admin-payment-methods.php` | `api/payment-methods.php` | **MISSING** | High |
| 6 | Subscribers (extend/cancel) | `admin-subscriptions.php` | `api/subscriptions.php` | **MISSING** | Med |
| 7 | Shared AI keys | `admin-shared-keys.php` | `api/admin-shared-keys.php` | **MISSING** | Med |
| 8 | Support tickets queue+thread | `admin-tickets.php`+`ticket-view.php` | `api/tickets.php` | **MISSING** | Med |
| 9 | Audit logs | `audit-logs.php` | `api/audit.php` | **MISSING** | Low |
| 10 | Release export | `release-export.php` | `api/release-export.php` | **MISSING** | Low |
| 11 | MCP token (per-user) | `mcp.php` | `api/users.php` (`generate_mcp_token`) | **MISSING** | Low |
| 12 | Scheduler status | `scheduler-status.php` | `api/scheduler_status.php`,`api/cron.php` | **MISSING** | Low |
| 13 | Data management hub | `data-management.php` | `api/export.php`,`api/backup.php` | **MISSING** (backup partly) | Low |
| 14 | Backup list | `backup-list.php` | `api/backup.php` | **PARTIAL** (`backup_screen.dart`) | Low |
| 15 | Backup settings (auto) | `backup-settings.php` | `api/backup_settings.php` | **MISSING** | Low |
| 16 | Data recovery | `data-recovery.php` | `api/data-recovery.php` | **MISSING** | Low |
| 17 | Import data / wipe | `import-data.php` | `api/export.php`,`api/wipe-data.php` | **MISSING** | Low |
| 18 | Shared Pomodoro music | `shared-music.php` | `api/pomodoro.php` | **MISSING** | Low |

---

## 1. How Flutter would know the current user is admin

**Current state — it doesn't.** Login returns role (`api/auth.php:218`) → repository ignores it (`auth_repository.dart:33-38`) → controller reads only name (`auth_controller.dart:64-74`) → token store has no role key → router has no role awareness (`router.dart:38-54`) → no admin UI anywhere.

**What needs to change:**
1. Persist role at login: in `auth_controller.dart` after `deviceLogin`, store `user['role']` into a new `userRoleProvider` AND into `TokenStore` (new `saveRole`/`readRole` so cold start knows it).
2. `final isAdminProvider = Provider<bool>((ref) => ref.watch(userRoleProvider) == 'admin');`
3. Gate entry: admin tile in `menu_hub_screen.dart` shown only when `isAdmin`.
4. Gate route in `router.dart` redirect: `/admin/...` requires `isAdmin`.
5. **Security:** on-device role is a UX gate only — server `Auth::isAdmin()` / `requireAdmin()` is the real enforcement and already works for device tokens.

---

## 2. Per-feature audit

For each: **① PHP side · ② API contract · ③ Flutter side · ④ Status · ⑤ Build plan.**

### Feature 1 — Admin Hub (`admin-hub.php`)
① Admin-gated card grid linking all admin pages with live counts (pending payments, awaiting tickets, active subs/plans/methods, configured shared keys) + "Needs Attention" banner. ② No own API — fans out to features 2-8. ③ MISSING. ④ MISSING. ⑤ `admin_hub_screen.dart` + `adminOverviewProvider` (parallel GETs reduced to counts).

### Feature 2 — User management (`users.php` + `api/users.php`)
① Admin-gated. Per-user: avatar, name, email, Verified/Banned badges, role `<select>` (disabled for last admin), Ban/Unban, Delete, Bulk Ban Spam. ② `GET ?action=list`; `POST ?action=update_role {user_id,role}`; `POST ?action=toggle_ban {user_id}`; `POST ?action=bulk_ban_spam`; `POST ?action=delete_user {user_id}` (refuses admins + last admin). All CSRF-gated. ③ MISSING. ④ MISSING. ⑤ `AdminUser` model + `admin_users_repository.dart` + `admin_users_screen.dart`. ⚠️ Needs CSRF fix.

### Feature 3 — Payment approvals (`admin-payments.php` + `api/payments.php`)
① Admin-gated. Filter pills (Pending/Approved/Rejected/All). Cards: plan name (planSnapshot), user email, status, amount+currency, method, submitted date, rejection reason. Proof image/PDF inline (`api/payment-proof.php?id=`). Pending → Approve / Reject (reason required). ② `GET ?all=1&status=`; `POST ?id=&action=approve {note?}`; `POST ?id=&action=reject {reason}`. ③ MISSING. ④ MISSING. ⑤ `AdminPayment` model + repo + screen. Proof via Dio (token attached). ⚠️ Needs CSRF fix.

### Feature 4 — Subscription plans CRUD (`admin-plans.php` + `api/plans.php`)
① Inline create/edit form: name, price, currency (USD/EUR/GBP/ZAR), durationDays, monthlyRequestCap (0=∞), provider checkboxes (groq/openrouter/gemini/ollama), description, active. List with Edit/Delete. ② `GET` (any user) / `?activeOnly=1`; `POST` create; `PUT ?id=` update; `DELETE ?id=`. Plan shape `{id,name,price,currency,durationDays,monthlyRequestCap,providers[],description,active,…}`. ③ MISSING. ④ MISSING. ⑤ `Plan` model + repo + list/form screens. GET works today; writes need CSRF fix.

### Feature 5 — Payment methods CRUD (`admin-payment-methods.php` + `api/payment-methods.php`)
① Form with type select (bank/crypto) toggling field groups: bank→{bankName,accountName,accountNumber,branchCode,swift/IBAN}; crypto→{network,walletAddress}. Plus instructions + active. ② Mirrors plans.php exactly. ③ MISSING. ④ MISSING. ⑤ `PaymentMethod` (type discriminator) + repo + form.

### Feature 6 — Subscribers (`admin-subscriptions.php` + `api/subscriptions.php`)
① Lists all subs with user email + plan name + status + dates. Active → Extend (days, default 30) / Cancel. ② `GET ?all=1`; `POST ?id=&action=extend {days}`; `POST ?id=&action=cancel`. ③ MISSING. ④ MISSING. ⑤ `Subscription` model + repo + screen.

### Feature 7 — Shared AI keys (`admin-shared-keys.php` + `api/admin-shared-keys.php`)
① "Import from your Settings" CTA + per-provider card (Groq/OpenRouter/Gemini): configured badge, masked `••<lastFour>`, model count enabled/total. Manual entry form (one password field per provider; blank=keep). Raw keys never leave server. ② `GET` status; `POST ?action=import`; `POST` save `{groqApiKey?,openrouterApiKey?,geminiApiKey?}`. ③ MISSING. ④ MISSING. ⑤ `SharedKeyStatus` model + repo + screen.

### Feature 8 — Support tickets (`admin-tickets.php` + `ticket-view.php` + `api/tickets.php`)
① Queue (admin): all tickets with subject/email/count/last-updated/status. Thread (`ticket-view.php`): owner-OR-admin, chat-style, reply posts `?id=&action=reply {body}`, close posts `?action=close`. `authorRole` set from `Auth::isAdmin()`. ② `GET ?all=1`; `POST ?id=&action=reply {body}`; `POST ?id=&action=close`. ③ MISSING. ④ MISSING. ⑤ `Ticket`+`TicketMessage` + repo + queue + thread screens (thread reusable for user-side support).

### Feature 9 — Audit logs (`audit-logs.php` + `api/audit.php`)
① Admin-gated page (but API only checks `Auth::check()` — ⚠️ security note: any user can read full audit log via API). Stats + filters (event/type/date/search) + list + CSV/JSON export. ② `GET ?action=list {event,user_id,resource_type,from,to,search,limit≤500,offset}`; `?action=stats`; `?action=types`; `POST ?action=clear {before?}`. ③ MISSING. ④ MISSING. ⑤ `AuditLog` model + repo + screen. Flag: tighten API to `requireAdmin()`.

### Feature 10 — Release export (`release-export.php` + `api/release-export.php`)
① Admin. Radio hosted/local → POST builds zip. Lists prior artifacts. ② `POST {export_type}`; `api/release-download.php` serves file. ③ MISSING. ④ MISSING. ⑤ Low priority — better as web-only devops tool.

### Feature 11 — MCP token (`mcp.php` + `api/users.php`)
① NOT admin-gated (any user manages own token). Masked current token + Regenerate/Revoke, or Generate. Once-shown full token + copyable MCP config JSON. ② `POST ?action=generate_mcp_token`; `POST ?action=revoke_mcp_token`. ⚠️ **Possible bug:** `api/users.php:8` calls `requireAdmin()` unconditionally at top — may block non-admins from generating their own token despite the UI. Verify before building. ③ MISSING. ④ MISSING. ⑤ Small `mcp_token_screen.dart`.

### Feature 12 — Scheduler status (`scheduler-status.php`)
① Any user. Shows scheduler running/stopped, uptime, PID, PHP version, scheduled jobs. Start/Stop → `POST api/cron.php {action:'start'|'stop'}`. Auto-refresh 10s. ② status GET + cron POST. ③ MISSING. ④ MISSING. ⑤ Low priority — ops tool.

### Feature 13 — Data management hub (`data-management.php`)
① Admin. Hub: Export (ZIP/JSON, include-music), Import (file upload overwrites all), links to Backups/Recovery/Backup Settings, Danger Zone → Wipe. ② Orchestrates 14-17. ③ PARTIAL (only backup exists). ④ PARTIAL. ⑤ Consolidate into `data_management_screen.dart`.

### Feature 14 — Backup list (`backup-list.php` + `api/backup.php`) ✅ THE MODEL
① Admin. List all backups (auto+manual) with Download/Restore/Delete + Create Now + stats. ② `GET ?action=stats`; `POST ?action=create`; `POST ?action=restore {filename}`; `POST ?action=delete {filename}`; `GET ?action=download`. **✅ CSRF ALREADY BYPASSED for token auth** (`backup.php:37` uses `isTokenAuth()`). ③ PARTIAL — `backup_screen.dart`+`backup_repository.dart` implement create/restore/delete and **work today**. ④ PARTIAL. ⑤ Add stats + download. **This is the reference pattern for every other admin endpoint's CSRF fix.**

### Feature 15 — Backup settings (`backup-settings.php` + `api/backup_settings.php`)
① Auto-backup toggle, frequency (daily/weekly), retention (3-90 days), last-auto-backup. ② `GET`; `POST {enabled,frequency,retention}`. **CSRF NOT bypassed** (`:57`) → needs fix. ③ MISSING. ④ MISSING. ⑤ Small form. `BackupSettings` model.

### Feature 16 — Data recovery (`data-recovery.php` + `api/data-recovery.php`)
① Two-step: diagnostic (per-collection OK/LOCKED) → enter old master password → re-encrypt locked collections. ② `POST ?action=diagnostic`; `POST ?action=recover {old_password}`. CSRF not bypassed → fix. ③ MISSING. ④ MISSING. ⑤ Niche but valuable.

### Feature 17 — Import / wipe (`import-data.php` + `api/export.php` + `api/wipe-data.php`)
① Auto-backup settings + export + import (file upload) + heavily-guarded Wipe (password + typed "DELETE ALL DATA" + 10s countdown + optional pre-wipe backup + keep-music). ② `POST api/export.php?action=import` (multipart) — **✅ already bypasses CSRF** (`export.php:294`). `POST api/wipe-data.php {password,confirmation,create_backup,keep_music}` — CSRF not bypassed → fix. ③ MISSING. ④ MISSING. ⑤ Import via Dio FormData; wipe with same multi-confirmation UX. ⚠️ Highest-risk action.

### Feature 18 — Shared Pomodoro music (`shared-music.php` + `api/pomodoro.php`)
① Admin. Upload (track name + audio .mp3/.wav/.m4a) + list + delete. ② `POST` multipart `action=upload_shared`; `POST` json `action=delete_shared {filename}`. **✅ CSRF bypassed** (`pomodoro.php:15`). ③ MISSING. ④ MISSING. ⑤ Low priority.

---

## 3. Cross-cutting findings

### 3.1 The CSRF/token-auth inconsistency (must fix first)
```php
// ✅ Modern (works from Flutter): api/backup.php:37, api/clients.php:14, api/tasks.php, …
if (requestMethod() !== 'GET' && !Auth::isTokenAuth()) { if (!Auth::validateCsrf($t)) errorResponse(…,403); }
// ❌ Legacy (BLOCKS Flutter): ALL admin mutation endpoints
if (!Auth::validateCsrf($t)) errorResponse(…,403);
```
Fix = wrap each `validateCsrf` in `if (!Auth::isTokenAuth() && …)`. Safe: `isTokenAuth()` only true for bearer-token requests with no ambient cookie → structurally immune to CSRF. ~20 one-line edits.

### 3.2 GET endpoints already work
Admin read endpoints need only `Auth::check()` + `Auth::isAdmin()`, both resolve correctly for device tokens. An admin could *view* lists from Flutter today; only writes are blocked.

### 3.3 Page vs API gating inconsistencies
- `mcp.php`: page allows any user, but `api/users.php` requires admin at top — possible bug (Feature 11).
- `scheduler-status.php`: any user (read-only; start/stop needs CSRF).
- `ticket-view.php`: owner-OR-admin (correctly enforced in API).
- `api/audit.php`: only `Auth::check()` — any user can read full audit log (security flag).

### 3.4 No device-token concept of CSRF token
No `device_login` CSRF field, no endpoint to mint one. Clean fix = §3.1 bypass, not "ship CSRF to app."

---

## 4. Recommended implementation order
1. **Foundation (unblocks all):** §1 persist role + `isAdminProvider` + route guard; §3.1 server CSRF bypass for admin endpoints.
2. **High-value admin CRUD:** Features 2 (users), 4 (plans), 5 (payment methods), 3 (payment approvals).
3. **Operational admin:** 6 (subscribers), 7 (shared keys), 8 (tickets).
4. **Data admin:** 14 (finish backup — partly works), 15 (backup settings), 13 (hub), 17 (import/wipe), 16 (recovery).
5. **Long tail:** 1 (hub polish), 9 (audit), 11 (MCP), 12 (scheduler), 10 (release export), 18 (shared music).

---

## 5. Essential files
**PHP:** `api/auth.php` (device_login role @218); `includes/Auth.php` (`isAdmin`@1397, `requireAdmin`@1401, `isDeviceToken`@1127, `isTokenAuth`@1179, `validateCsrf`@1500, role resolution @1348-1359); `api/{users,payments,plans,payment-methods,subscriptions,admin-shared-keys,tickets,audit,backup,release-export,data-recovery,wipe-data,backup_settings,mcp,pomodoro,scheduler_status,cron}.php`; `mobile/views/admin-{hub,payments,plans,payment-methods,shared-keys,subscriptions,tickets}.php`, `users.php`, `ticket-view.php`, `audit-logs.php`, `release-export.php`, `data-management.php`, `data-recovery.php`, `import-data.php`, `backup-list.php`, `backup-settings.php`, `shared-music.php`, `mcp.php`, `scheduler-status.php`; `includes/{PaymentsAPI,SubscriptionsAPI,PlansAPI,PaymentMethodsAPI,AISharedKeysAPI,TicketsAPI}.php`.

**Flutter (modify):** `lib/auth/{auth_repository,auth_controller,token_store}.dart`, `lib/router.dart`, `lib/screens/menu_hub_screen.dart`.
**Flutter (create, suggested `lib/admin/`):** models `admin_user,plan,payment_method,admin_payment,subscription,shared_key_status,ticket,audit_log,backup_settings`; repositories `admin_{users,plans,payment_methods,payments,subscriptions,shared_keys,tickets}_repository` + `audit_repository` + `backup_settings_repository`; screens `admin_hub,admin_users,admin_plans+plan_form,admin_payment_methods+payment_method_form,admin_payments,admin_subscriptions,admin_shared_keys,admin_tickets+ticket_thread,audit_logs`.
**Reference:** `lib/screens/backup_screen.dart` + `lib/repositories/backup_repository.dart` — the only working admin-adjacent Flutter code; copy its pattern.
