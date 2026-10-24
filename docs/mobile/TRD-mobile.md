# TRD — OpenPlan Work Mobile App (Flutter)

> **Technical Requirements Document** for the native cross-platform mobile app.
> Companion docs: [`PRD-mobile.md`](./PRD-mobile.md) (product) · [`FLOW-1to1.md`](./FLOW-1to1.md) (screen flow).
> Backend contract reference: [`../TRD.md`](../TRD.md) §6 (REST endpoints), [`../OpenPlan-Work-Complete-Documentation.md`](../OpenPlan-Work-Complete-Documentation.md) (data schemas).
> Status: Draft v0.1 · Last updated: 2026-06-26

---

## B1. Stack

- **Client:** Flutter (stable), Dart 3. UI = Material 3 (theme mirrors Tailwind palette + Inter).
- **HTTP:** `dio` (interceptors for Bearer + envelope unwrap + retry).
- **State:** `riverpod` (recommended) — testable, compile-safe providers.
- **Routing / deep links:** `go_router`.
- **Secure storage:** `flutter_secure_storage`; biometrics: `local_auth`.
- **Local cache (offline):** `drift` (SQLite) recommended for relational task/project data, or `hive` — decide at kickoff.
- **Push:** `firebase_messaging` (FCM) for Android + APNs bridge for iOS.
- **Backend:** existing PHP, **unchanged except additive device-token auth** (§B4).

## B2. High-Level Architecture

```
Flutter app ──HTTPS, Authorization: Bearer <device_token>──► PHP REST API ──► encrypted JSON store
   lib/api (Dio)        JSON envelope {success,data,message,timestamp}         (AES-256-GCM at rest)
   lib/repositories ◄────────── plaintext data (server decrypts) ─────────────
   lib/screens/widgets       offline cache (drift) ◄──► sync queue
```

Backend remains the source of truth; the app is a thin native client. The legacy `/android/` WebView wrapper continues loading `mobile/views/*` until Flutter reaches Tier-1 parity.

## B3. Flutter Project Structure (`/flutter_app/`)

```
flutter_app/
  lib/
    main.dart
    config/        # env: dev=http://10.0.2.2/taskmanager (emulator), prod=https://openplan.work
    api/           # ApiClient(Dio): bearer interceptor, unwrap {success,data}, error mapping, retry
    auth/          # device_login flow, secure token store, biometric gate, session state
    models/        # Task, Project, Subtask, Client, Invoice, LineItem, Note, Habit, Transaction, ...
    repositories/  # TasksRepo, NotesRepo, ... (api + cache + sync queue)
    sync/          # offline queue, conflict policy
    screens/       # dashboard, tasks, kanban, task_detail, notes, note_edit, habits, water,
                   #   finance, invoices, invoice_detail, clients, ai_chat, pomodoro, settings, devices
    widgets/       # cards, bottom_nav, empty_state, stat_tile, ...
    theme/         # Material 3 ColorScheme + Inter typography (mirror Tailwind tokens)
  test/            # unit (repos/models) + widget tests
  integration_test/
  android/  ios/   # generated platform shells (ios built on Mac)
```

CI lives at repo root: `.github/workflows/mobile.yml`.

## B4. Backend Device-Token Auth (the gate) — full design

> **Status: IMPLEMENTED + verified locally (2026-06-26).** Files below exist and pass
> a 22-check crypto round-trip plus full HTTP integration (Bearer → 200 with
> server-side-decrypted data, no master sent; garbage token → 401; revoke → 401;
> CSRF bypassed for token auth but still enforced for web sessions).
> **DEPLOYED to live (openplan.work) 2026-06-26** — verified: device_login endpoint
> live, all touched endpoints return no 500s, uploaded files byte-match local.

**Chosen model:** *token-as-key wrap* (the existing MCP `mcpWrappedMaster` pattern) stored in a dedicated revocable file, surfaced over `Authorization: Bearer`. On login the server wraps the master password using the device token as the key; on each request the client sends only the token; the server unwraps the master, decrypts, and returns plaintext. Only `sha256(token)` is persisted — a data-dir leak alone decrypts nothing.

**Why this over a server-secret model:** a single server secret that (combined with a data-dir leak) could decrypt *every* user's master is avoided. Security rests on per-device tokens that never touch disk. Optional `DEVICE_TOKEN_PEPPER` adds offline resistance without becoming a single point of compromise.

### Grounding in the real code

- `Encryption::__construct($master)` derives key `sha256($master)`; `encrypt/decrypt` do AES-256-GCM + `base64(iv|tag|ct)` — `includes/Encryption.php:12-89`.
- `new Database($master, $userId)` → scoped encrypted store — `includes/Database.php:12-31`.
- Every endpoint resolves the key via `getMasterPassword()`, which reads `$_SESSION['master_password']` first — `includes/Helpers.php:340-384`. **So if the Bearer path puts the unwrapped master into that session slot and sets a user id, all existing endpoints work unchanged** — exactly how `Auth::isMcp()` already works (`Auth.php:1018-1072`).
- Templates to copy: MCP token mint `Auth::generateMcpApiToken()` (`Auth.php:1656-1711`) + unwrap (`Auth.php:1052`); Alexa store mechanics `includes/AlexaOAuth.php:343-401` (atomic `flock` writes, `chmod 0600`, sha256 hashing, list/revoke).

### New / changed backend files

- **NEW `includes/DeviceTokens.php`** — store `data/device_tokens.json`. Record shape:
  ```json
  { "id":"uuid","userId":"…","name":"Pixel 8","platform":"android",
    "tokenHash":"sha256(token)","tokenLast4":"1a2b",
    "wrappedMaster":"AES-GCM blob (key = token[+pepper])",
    "expiresAt":null,"createdAt":"…","lastUsedAt":"…","revoked":false }
  ```
  Methods: `issue($userId,$master,$name,$platform)`, `resolve($tokenPlain) → ['userId','master','recordId']`, `revoke`, `revokeAllForUser`, `listForUser`, `touchLastUsed`, `pepper`.
- **`includes/Auth.php`** — add `$deviceUserId`; `isDeviceToken()` (parse `Authorization: Bearer`, `DeviceTokens::resolve()`, set `$_SESSION['master_password']` + `$deviceUserId`); `isTokenAuth()` = superset of `isMcp()`; thin wrappers `createDeviceToken/listDeviceTokens/revokeDeviceToken`. Wire into `check()` (new branch after the MCP branch, ~`Auth.php:1119`); `userId()` precedence `device ?? mcp ?? session` (`Auth.php:1252`); propagate revoke in `toggleBan()` (`:885`) and `revokeAllTokens()` (`:245`). Reuse `verifyEmailPasswordOnly()` (`:32`) and `validateUserMasterPassword()` (`:807`) for login (they do not mutate the session).
- **`api/auth.php`** — new cases `device_login` (+ optional `device_refresh`), reusing the `RateLimiter` block (`auth.php:47-127`). No CSRF (credential bootstrap, like `api/oauth/token.php`).
- **NEW `api/devices.php`** — `?action=list|revoke|rename`; session OR device-token auth; CSRF required for session callers, exempt for token callers via `Auth::isTokenAuth()`.
- **13 data endpoints** — mechanical find/replace `!Auth::isMcp()` → `!Auth::isTokenAuth()` in the CSRF guard (template `api/tasks.php:14`; also todos, projects, clients, invoices, advanced-invoices, inventory, finance, habits, models, water, knowledge-base + conditional variants in notes/ai/export/pomodoro/backup). `isTokenAuth()` is a strict superset → MCP cannot regress.
- **`config.php`** — optional `DEVICE_TOKEN_PEPPER` env (empty = pure MCP model). Keep in `.env`, outside `data/`.
- **`includes/Encryption.php`** — reused verbatim.

### Request / response shapes

```
POST api/auth.php?action=device_login
  body: { email, password, master_password, device_name, platform }
  200 : { success:true, data:{ device_token, token_type:"Bearer", expires_in:null,
                               device:{id,name,last4,createdAt}, user:{id,email,name,role} } }

GET  api/tasks.php   Header: Authorization: Bearer <token>
  200 : { success:true, data:[ …tasks… ] }      # decrypted server-side, no master sent

GET  api/devices.php?action=list   → { data:{ devices:[{id,name,platform,last4,lastUsedAt}] } }
POST api/devices.php?action=revoke → { id }      # session caller also sends csrf_token
```

### Safety

Purely additive. Web session auth, MCP, and Alexa OAuth are untouched. On `change_master_password` (`auth.php:413`) → call `DeviceTokens::revokeAllForUser()` (wraps go stale). Banning/logout also revoke device tokens.

### Security posture change (explicit)

A *wrapped* copy of the master persists server-side per token, openable only with the never-stored per-device token. This generalizes the existing plaintext `mcpWrappedMaster` exposure but **improves** it: dedicated `0600` file + revocability + expiry that MCP tokens lack.

## B5. API Client Layer (Dart)

- `ApiClient` wraps Dio; base URL from `config`. Request interceptor attaches `Authorization: Bearer`. Response interceptor unwraps `{success,data}` and throws `ApiException(message)` on `success:false`. `401` → clear token + route to login. Retry/backoff on network errors.
- One repository per module; repositories read cache first, then network; writes go through the sync queue.

## B6. Data Models

Mirror the schemas in [`../OpenPlan-Work-Complete-Documentation.md`](../OpenPlan-Work-Complete-Documentation.md) (Project with embedded tasks, Client, Invoice with lineItems, Note, etc.). Dart classes with `fromJson`/`toJson`. **No backend schema changes.**

## B7. Offline / Sync

- Cache: drift tables per module, with a last-synced timestamp.
- Writes while offline → `sync_queue` (op, payload, ts); flush on reconnect; v1 conflict = last-write-wins, with a surfaced banner when server `updatedAt` is newer.
- Read-only modules (dashboard stats) use cache-then-refresh.

## B8. Push Notifications

- FCM (Android) + APNs (iOS). Backend needs a small endpoint to register device push tokens (Tier-2: extend `api/devices.php` with `push_token`). Triggers = habit/water/task reminders via the existing cron (`api/cron.php`).

## B9. Security

- Device token in the secure enclave; biometric gate on launch (`local_auth`).
- No master password persisted on device. TLS pinning optional (Tier-2).
- Revocable sessions via the Devices screen.

## B10. CI/CD

- `.github/workflows/mobile.yml` on push to `main` touching `flutter_app/**`:
  - ubuntu: `flutter analyze` + `flutter test`.
  - ubuntu: `flutter build apk --debug`.
  - **macos runner:** `flutter build ios --no-codesign`.
- Signing (Android keystore, iOS profiles) as GitHub secrets; release lanes (Fastlane/TestFlight) Tier-2.

## B11. Constraints / Notes

- Native clients are **not** browsers → no CORS/preflight needed (no backend CORS work). If Flutter **Web** is ever targeted, `api/*` must add `Access-Control-Allow-Origin/Headers` + OPTIONS handling — explicitly deferred.
- Emulator → host backend via `http://10.0.2.2/taskmanager`; physical device → LAN IP or prod.

## B12. Testing

- Unit: models (`fromJson`), repositories (mocked Dio), sync queue.
- Widget: key screens render states (loading/empty/error/data).
- Integration: login → load tasks → create task → verify on web.
- Backend: curl suite (below) + `php test/run_all_tests.php` for no-regression.

### End-to-end verification

1. Backend curl: `device_login` → Bearer GET/POST tasks (no master sent) → devices list/revoke → ban propagation → 401.
2. `php test/run_all_tests.php` green (web/MCP no-regression).
3. Flutter: emulator against `http://10.0.2.2/taskmanager`, login, tasks load decrypted, created task appears on web; iOS simulator on Mac same flow.
4. CI: branch push → analyze/test + Android + iOS builds green.
5. Security: proxy-capture confirms no master password after login; revoked token → 401.

## B13. Open decisions / risks

Security posture (wrapped master at rest) — documented + mitigated · multi-month effort, MVP = Tier 1 · offline conflict policy = last-write-wins v1 · device-token TTL (recommend non-expiring + revocation) · pepper yes/no · `change_master_password` must revoke tokens · iOS release signing deferred to Tier-2.
